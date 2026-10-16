#!/bin/bash
B=http://127.0.0.1:8097/giftaura
DB=test.db
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }

G=guest.jar; rm -f *.jar
echo "== Guest cart"
TOKPAGE=product.php?slug=sample-red-rose-bouquet post $G cart.php -d action=add -d product_id=1 -d quantity=2 >/dev/null
check "cart badge shows 2" "$(page $G products.php | grep -o 'cart-link__count">[0-9]*' | grep -o '[0-9]*$')" "2"
C=$(page $G cart.php)
check "subtotal 9,000" "$(echo "$C" | grep -o 'Subtotal (2 items)</dt><dd>Rs. [0-9,.]*' | grep -o 'Rs.*')" "Rs. 9,000.00"
check "delivery 400 (setting)" "$(echo "$C" | grep -o '<dt>Delivery</dt><dd>[^<]*' | sed 's/.*<dd>//')" "Rs. 400.00"
check "total 9,400" "$(echo "$C" | grep -o 'totals__grand"><dt>Total</dt><dd>[^<]*' | sed 's/.*<dd>//')" "Rs. 9,400.00"
TOKPAGE=cart.php post $G cart.php -d action=add -d product_id=2 -d quantity=5 >/dev/null
check "qty limited to stock 3" "$(page $G cart.php | grep -o 'limited to 3')" "limited to 3"
check "out-of-stock refused" "$(TOKPAGE=cart.php post $G cart.php -d action=add -d product_id=4 -d quantity=1 -d return=/giftaura/products.php | cut -d' ' -f2)" "$B/products.php"
check "out-of-stock message" "$(page $G products.php | grep -o 'Sample Sunflower Bouquet is out of stock')" "Sample Sunflower Bouquet is out of stock"
TOKPAGE=cart.php post $G cart.php -d action=add -d product_id=11 -d quantity=1 >/dev/null
check "inactive product refused" "$(page $G cart.php | grep -c 'Inactive Gift Card')" "0"
TOKPAGE=cart.php post $G cart.php -d action=add -d product_id=abc -d quantity=1 >/dev/null
check "cart has 2 lines" "$(page $G cart.php | grep -c '<li class="cart-line')" "2"
TOKPAGE=cart.php post $G cart.php -d action=update -d product_id=2 -d quantity=1 >/dev/null
check "update qty to 1" "$(page $G cart.php | grep -o 'Subtotal ([0-9]* items)')" "Subtotal (3 items)"
check "cart POST without token -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -b $G -d action=add -d product_id=1 $B/cart.php)" "419"
check "guest checkout -> login" "$(curl -s -o /dev/null -w '%{redirect_url}' -b $G $B/checkout.php)" "$B/login.php?next=%2Fgiftaura%2Fcheckout.php"

echo "== Register merges cart"
TOKPAGE=register.php post $G register.php -d full_name=Nimal%20Perera -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null
check "saved cart lines after register" "$(q "SELECT COUNT(*) FROM cart_items")" "2"
check "session cart emptied/merged count" "$(page $G cart.php | grep -o 'Subtotal ([0-9]* items)')" "Subtotal (3 items)"

echo "== Checkout validation"
CO=$(page $G checkout.php)
FP=$(echo "$CO" | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
check "checkout shows total 13,200 (9,000 + 3,800 + 400)" "$(echo "$CO" | grep -o 'Total to pay</dt><dd>[^<]*' | sed 's/.*<dd>//')" "Rs. 13,200.00"
R=$(TOKPAGE=checkout.php post $G checkout.php -d customer_name= -d customer_phone=123 -d address_id=new -d payment_method=card -d cart_fingerprint=$FP)
check "invalid form stays on page" "$R" "200 "
check "no order after invalid form" "$(q "SELECT COUNT(*) FROM orders")" "0"
R=$(TOKPAGE=checkout.php post $G checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod -d cart_fingerprint=tampered -d subtotal=1 -d total_amount=1)
check "tampered fingerprint refused" "$(q "SELECT COUNT(*) FROM orders")" "0"

echo "== Oversell protection"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE cart_items SET quantity=3 WHERE product_id=2");'
FP=$(page $G checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE products SET stock_quantity=1 WHERE id=2");'
TOKPAGE=cart.php post $G checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod -d cart_fingerprint=$FP >/dev/null
check "stock dropped mid-checkout -> no order" "$(q "SELECT COUNT(*) FROM orders")" "0"
check "stock untouched" "$(q "SELECT stock_quantity FROM products WHERE id IN (1,2) ORDER BY id" | tr '\n' ',')" "12,1,"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE products SET stock_quantity=3 WHERE id=2"); $p->exec("UPDATE cart_items SET quantity=1 WHERE product_id=2");'

echo "== Place order (COD, new address, save it)"
FP=$(page $G checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
R=$(TOKPAGE=checkout.php post $G checkout.php -d customer_name=Nimal%20Perera -d customer_phone=077-123-4567 -d address_id=new -d recipient_name=Kamala -d phone=+94711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d postal_code=20000 -d save_address=1 -d delivery_notes=Happy%20birthday -d payment_method=cod -d cart_fingerprint=$FP)
ON=$(echo "$R" | grep -o 'GA[0-9]\{6\}-[A-Z0-9]\{6\}')
check "redirect to confirmation" "$(echo $R | grep -c order-confirmation)" "1"
check "order row totals" "$(q "SELECT status, subtotal, delivery_fee, total_amount, customer_phone, recipient_phone FROM orders")" "pending|12800|400|13200|0771234567|0711111111"
check "items snapshot" "$(q "SELECT product_name, unit_price, quantity, line_total FROM order_items ORDER BY id" | tr '\n' ';')" "Sample Red Rose Bouquet|4500|2|9000;Sample Pink Rose Bunch|3800|1|3800;"
check "stock reduced 12->10, 3->2" "$(q "SELECT stock_quantity FROM products WHERE id IN (1,2) ORDER BY id" | tr '\n' ',')" "10,2,"
check "ledger rows" "$(q "SELECT product_id, quantity_change, stock_after, reason FROM inventory_movements WHERE reason='order_placed' ORDER BY id" | tr '\n' ';')" "1|-2|10|order_placed;2|-1|2|order_placed;"
check "payment pending COD" "$(q "SELECT method,status,amount FROM payments")" "cod|pending|13200"
check "history first entry" "$(q "SELECT IFNULL(from_status,'NULL'), to_status FROM order_status_history")" "NULL|pending"
check "cart emptied" "$(q "SELECT COUNT(*) FROM cart_items")" "0"
check "address saved + default" "$(q "SELECT recipient_name, district, postal_code, is_default FROM customer_addresses")" "Kamala|Kandy|20000|1"
CF=$(page $G "order-confirmation.php?order=$ON")
check "confirmation shows reference" "$(echo "$CF" | grep -q "order-ref\">$ON" && echo yes)" "yes"
check "confirmation total" "$(echo "$CF" | grep -o 'totals__grand"><dt>Total</dt><dd>[^<]*' | sed 's/.*<dd>//')" "Rs. 13,200.00"
check "order history lists it" "$(page $G account/orders.php | grep -q ">$ON</a>" && echo yes)" "yes"

echo "== Another customer cannot see it"
H=other.jar
TOKPAGE=register.php post $H register.php -d full_name=Other%20Person -d email=other@example.com -d phone=0722222222 -d password=secret123 -d password_confirm=secret123 >/dev/null
check "other: order page 404" "$(curl -s -o /dev/null -w '%{http_code}' -b $H "$B/account/order.php?order=$ON")" "404"
check "other: confirmation 404" "$(curl -s -o /dev/null -w '%{http_code}' -b $H "$B/order-confirmation.php?order=$ON")" "404"
check "other: cannot cancel" "$(TOKPAGE=account/orders.php post $H "account/order.php?order=$ON" -d action=cancel | cut -d' ' -f1)" "404"
AID=$(q "SELECT id FROM customer_addresses LIMIT 1")
check "other: cannot edit address" "$(curl -s -o /dev/null -w '%{http_code}' -b $H "$B/account/addresses.php?edit=$AID")" "404"
TOKPAGE=account/addresses.php post $H account/addresses.php -d action=delete -d address_id=$AID >/dev/null
check "other: cannot delete address" "$(q "SELECT COUNT(*) FROM customer_addresses")" "1"

echo "== Tracking"
T=$(tok trk.jar track-order.php)
check "track with right email" "$(curl -s -c trk.jar -b trk.jar --data-urlencode _csrf=$T -d order=$ON -d email=NIMAL@example.com $B/track-order.php | grep -c 'progress__step--current')" "1"
T=$(tok trk.jar track-order.php)
check "track with wrong email" "$(curl -s -c trk.jar -b trk.jar --data-urlencode _csrf=$T -d order=$ON -d email=x@example.com $B/track-order.php | grep -c 'could not find')" "1"
check "tracking hides address" "$(curl -s -c trk.jar -b trk.jar --data-urlencode _csrf=$(tok trk.jar track-order.php) -d order=$ON -d email=nimal@example.com $B/track-order.php | grep -c 'Temple Road')" "0"

echo "== Customer cancels pending order"
TOKPAGE="account/order.php?order=$ON" post $G "account/order.php?order=$ON" -d action=cancel -d reason=Changed%20my%20mind >/dev/null
check "status cancelled" "$(q "SELECT status FROM orders")" "cancelled"
check "stock restored 10->12, 2->3" "$(q "SELECT stock_quantity FROM products WHERE id IN (1,2) ORDER BY id" | tr '\n' ',')" "12,3,"
check "restore ledger rows" "$(q "SELECT COUNT(*) FROM inventory_movements WHERE reason='order_cancelled'")" "2"
TOKPAGE="account/order.php?order=$ON" post $G "account/order.php?order=$ON" -d action=cancel >/dev/null
check "second cancel does not restore twice" "$(q "SELECT stock_quantity FROM products WHERE id=1")" "12"
check "history 2 rows" "$(q "SELECT COUNT(*) FROM order_status_history")" "2"

echo "== Bank transfer order using saved address"
TOKPAGE=product.php?slug=sample-chocolate-gift-box post $G cart.php -d action=add -d product_id=8 -d quantity=1 >/dev/null
CO=$(page $G checkout.php)
FP=$(echo "$CO" | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
check "saved address offered" "$(echo "$CO" | grep -c "name=\"address_id\" value=\"$AID\"")" "1"
R=$(TOKPAGE=checkout.php post $G checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=$AID -d payment_method=bank_transfer -d cart_fingerprint=$FP)
ON2=$(echo "$R" | grep -o 'GA[0-9]\{6\}-[A-Z0-9]\{6\}')
check "bank order placed" "$(q "SELECT method,status FROM payments WHERE order_id=(SELECT id FROM orders WHERE order_number='$ON2')")" "bank_transfer|pending"
check "bank details not invented" "$(page $G "order-confirmation.php?order=$ON2" | grep -c 'has not published its bank account')" "1"
TOKPAGE="account/order.php?order=$ON2" post $G "account/order.php?order=$ON2" -d action=bank_reference -d reference=BOC-778899 >/dev/null
check "reference -> awaiting_verification" "$(q "SELECT status, reference FROM payments WHERE order_id=(SELECT id FROM orders WHERE order_number='$ON2')")" "awaiting_verification|BOC-778899"
TOKPAGE="account/order.php?order=$ON2" post $G "account/order.php?order=$ON2" -d action=bank_reference -d reference='<script>' >/dev/null
check "bad reference rejected" "$(q "SELECT reference FROM payments WHERE order_id=(SELECT id FROM orders WHERE order_number='$ON2')")" "BOC-778899"

echo "== Free delivery threshold"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE settings SET setting_value=\"5000.00\" WHERE setting_key=\"free_delivery_threshold\"");'
TOKPAGE=cart.php post $G cart.php -d action=add -d product_id=1 -d quantity=2 >/dev/null
check "free delivery over 5000" "$(page $G cart.php | grep -o '<dt>Delivery</dt><dd>[^<]*' | sed 's/.*<dd>//')" "Free"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE settings SET setting_value=\"0.00\" WHERE setting_key=\"free_delivery_threshold\"");'

echo "== Race: two customers buy the last unit at the same time"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE products SET stock_quantity=1 WHERE id=9"); $p->exec("DELETE FROM cart_items");'
TOKPAGE=product.php?slug=sample-teddy-bear post $G cart.php -d action=add -d product_id=9 -d quantity=1 >/dev/null
TOKPAGE=product.php?slug=sample-teddy-bear post $H cart.php -d action=add -d product_id=9 -d quantity=1 >/dev/null
FP1=$(page $G checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//'); T1=$(tok $G checkout.php)
FP2=$(page $H checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//'); T2=$(tok $H checkout.php)
before=$(q "SELECT COUNT(*) FROM orders")
curl -s -o /dev/null -b $G -c $G --data-urlencode _csrf=$T1 -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=$AID -d payment_method=cod -d cart_fingerprint=$FP1 $B/checkout.php &
curl -s -o /dev/null -b $H -c $H --data-urlencode _csrf=$T2 -d customer_name=Other -d customer_phone=0722222222 -d address_id=new -d recipient_name=Other -d phone=0722222222 -d address_line1=1%20Main%20St -d city=Galle -d district=Galle -d payment_method=cod -d cart_fingerprint=$FP2 $B/checkout.php &
wait
check "exactly one order for the last unit" "$(( $(q "SELECT COUNT(*) FROM orders") - before ))" "1"
check "stock is 0, never negative" "$(q "SELECT stock_quantity FROM products WHERE id=9")" "0"

echo "== Admin cannot check out"
A=admin.jar
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null
check "admin checkout redirected" "$(curl -s -o /dev/null -w '%{redirect_url}' -b $A $B/checkout.php)" "$B/cart.php"

echo "== Result: $pass passed, $fail failed"
