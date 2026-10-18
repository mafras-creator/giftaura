#!/bin/bash
# GiftAura - product personalisation (customer types a message that is ordered with the product).
B=http://127.0.0.1:8092/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar
A=admin.jar; C=cust.jar; G=guest.jar; T2=tab2.jar
PP="product.php?slug=sample-chocolate-gift-box"
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null
EP="admin/product-edit.php?id=8"
saveprod(){ TOKPAGE=$EP post $A "$EP" -d action=save -d name=Sample%20Chocolate%20Gift%20Box -d slug=sample-chocolate-gift-box -d sku=SAMPLE-GI-001 -d category_id=3 -d price=3500 -d low_stock_threshold=5 -d is_active=1 -d is_featured=1 "$@"; }

echo "== Admin: product settings"
check "new products are not personalised" "$(q "SELECT is_customizable||'|'||custom_max_length||'|'||custom_required FROM products WHERE id=8")" "0|100|1"
E=$(page $A "$EP")
check "form has the Personalisation box (hidden details)" "$(echo "$E" | grep -c 'data-custom-details hidden')" "1"
saveprod -d is_customizable=1 -d custom_label=Name%20to%20print -d custom_max_length=5 -d custom_required=1 >/dev/null
check "longest message below 10 refused" "$(q "SELECT is_customizable FROM products WHERE id=8")" "0"
saveprod -d is_customizable=1 -d custom_label=Name -d custom_max_length=600 -d custom_required=1 >/dev/null
check "longest message above 500 refused" "$(q "SELECT is_customizable FROM products WHERE id=8")" "0"
saveprod -d is_customizable=1 -d custom_label=$(printf 'x%.0s' {1..81}) -d custom_max_length=30 -d custom_required=1 >/dev/null
check "question longer than 80 characters refused" "$(q "SELECT is_customizable FROM products WHERE id=8")" "0"
saveprod -d is_customizable=1 -d custom_label=Name%20to%20print%20on%20the%20box -d custom_max_length=30 -d custom_required=1 >/dev/null
check "saved: personalised, question, 30, required" "$(q "SELECT is_customizable||'|'||custom_label||'|'||custom_max_length||'|'||custom_required FROM products WHERE id=8")" "1|Name to print on the box|30|1"
check "activity log: personalisation on" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE details LIKE '%personalisation on%'")" "1"
E=$(page $A "$EP")
check "form shows the box ticked and details visible" "$(echo "$E" | grep -c 'data-custom-toggle checked')|$(echo "$E" | grep -c 'data-custom-details hidden')" "1|0"
check "admin list marks the product" "$(page $A admin/products.php | grep -c ' · Personalised')" "1"
check "guest cannot open the product admin" "$(curl -s -o /dev/null -w '%{http_code}' $B/$EP -b $G)" "303"

echo "== Shop: cards and product page"
H=$(page $G "")
check "card says Personalisable (featured + new arrivals)" "$(echo "$H" | grep -c 'class="personalisable"')" "2"
check "card button goes to the product page" "$(echo "$H" | grep -c "href=\"/giftaura/$PP#personalise\" aria-label=\"Personalise Sample Chocolate Gift Box\"")" "2"
check "no quick Add to cart for this product" "$(echo "$H" | grep -c 'aria-label="Add Sample Chocolate Gift Box to cart"')" "0"
check "other products keep Add to cart" "$(echo "$H" | grep -c 'aria-label="Add Sample Red Rose Bouquet to cart"')" "2"
P=$(page $G "$PP")
check "product page: message box with question, limit and Required" "$(echo "$P" | grep -c 'for="customization_text">Name to print on the box')|$(echo "$P" | grep -c 'maxlength="30"')|$(echo "$P" | grep -c 'personalise__tag">Required')" "1|1|1"

echo "== Cart"
R=$(TOKPAGE=$PP post $G cart.php -d action=add -d product_id=8 -d quantity=1 -d return=/giftaura/$PP)
check "add without the required message refused" "$R|$(page $G cart.php | grep -c 'Your cart is empty')" "303 $B/$PP|1"
LONG=$(printf 'A%.0s' {1..31})
TOKPAGE=$PP post $G cart.php -d action=add -d product_id=8 -d quantity=1 -d return=/giftaura/$PP --data-urlencode "customization_text=$LONG" >/dev/null
P=$(page $G "$PP")
check "31 characters refused, text kept for correction" "$(echo "$P" | grep -c "you typed 31")|$(echo "$P" | grep -c ">$LONG</textarea>")" "1|1"
check "kept text shown only once" "$(page $G "$PP" | grep -c ">$LONG</textarea>")" "0"
TOKPAGE=$PP post $G cart.php -d action=add -d product_id=8 -d quantity=1 --data-urlencode $'customization_text=  Happy Birthday\r\n\r\n\r\n\r\nAmma \x07 ' >/dev/null
CP=$(page $G cart.php)
check "added; message cleaned (line breaks, control character)" "$(echo "$CP" | grep -A1 'custom-note__text' | head -2 | tr '\n' '~')" '    <span class="custom-note__text">Happy Birthday~~'
check "cart shows the question and Edit link" "$(echo "$CP" | grep -c 'custom-note__label">Name to print on the box')|$(echo "$CP" | grep -c 'Edit personalisation')" "1|1"
TOKPAGE=$PP post $G cart.php -d action=add -d product_id=8 -d quantity=1 --data-urlencode 'customization_text=<b>Nimal</b> & Co' >/dev/null
CP=$(page $G cart.php)
check "adding again: qty 2 and message replaced" "$(echo "$CP" | grep -A1 'id="qty-8"' | grep -o 'value="[0-9]*"' | head -1)" 'value="2"'
check "message escaped in note and edit box (no HTML)" "$(echo "$CP" | grep -c '&lt;b&gt;Nimal&lt;/b&gt; &amp; Co')|$(echo "$CP" | grep -c '<b>Nimal</b>')" "2|0"
check "message action without CSRF -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -b $G -d action=message -d product_id=8 -d customization_text=x $B/cart.php)" "419"
TOKPAGE=cart.php post $G cart.php -d action=message -d product_id=8 -d customization_text= >/dev/null
check "empty message refused for a required product" "$(page $G cart.php | grep -c '&lt;b&gt;Nimal')" "2"
TOKPAGE=cart.php post $G cart.php -d action=add -d product_id=1 -d quantity=1 -d customization_text=ignored >/dev/null
TOKPAGE=cart.php post $G cart.php -d action=message -d product_id=1 -d customization_text=Hello >/dev/null
check "normal product cannot get a message" "$(page $G cart.php | grep -c 'cannot be personalised')" "1"
SIN=$'ශ්‍රී ලංකා'
TOKPAGE=cart.php post $G cart.php -d action=message -d product_id=8 --data-urlencode "customization_text=$SIN" >/dev/null
check "Sinhala text with joiner kept" "$(page $G cart.php | grep -c "$SIN")" "2"

echo "== Login keeps the message"
TOKPAGE=register.php post $C register.php -d full_name=Nimal%20Perera -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE users SET email_verified_at=datetime(\"now\") WHERE email=\"nimal@example.com\"");'
TOKPAGE=login.php post $G login.php -d email=nimal@example.com -d password=secret123 >/dev/null
check "guest message moved to the saved cart" "$(q "SELECT ci.customization_text FROM cart_items ci WHERE ci.product_id=8")" "$SIN"
check "normal product saved without a message" "$(q "SELECT IFNULL(customization_text,'NULL') FROM cart_items WHERE product_id=1")" "NULL"
TOKPAGE=cart.php post $G cart.php -d action=message -d product_id=8 --data-urlencode 'customization_text=Kamala & Sunil' >/dev/null
check "customer edits the message" "$(q "SELECT customization_text FROM cart_items WHERE product_id=8")" "Kamala & Sunil"

echo "== Checkout and order"
CK=$(page $G checkout.php)
check "checkout summary shows the message" "$(echo "$CK" | grep -c 'Kamala &amp; Sunil')" "1"
FP=$(echo "$CK" | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
cp $G $T2
TOKPAGE=cart.php post $T2 cart.php -d action=message -d product_id=8 --data-urlencode 'customization_text=Kamala and Sunil' >/dev/null
R=$(TOKPAGE=checkout.php post $G checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod -d cart_fingerprint=$FP)
check "message changed in another tab -> order refused" "$(q "SELECT COUNT(*) FROM orders")" "0"
FP=$(page $G checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
R=$(TOKPAGE=checkout.php post $G checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod -d cart_fingerprint=$FP)
NUM=$(q "SELECT order_number FROM orders ORDER BY id DESC LIMIT 1")
check "order placed with the message as a snapshot" "$(q "SELECT IFNULL(customization_text,'NULL') FROM order_items ORDER BY product_id")" $'NULL\nKamala and Sunil'
check "confirmation page shows it" "$(page $G "order-confirmation.php?order=$NUM" | grep -c 'custom-note__text">Kamala and Sunil')" "1"
check "My order page shows it" "$(page $G "account/order.php?order=$NUM" | grep -c 'custom-note__text">Kamala and Sunil')" "1"
OID=$(q "SELECT id FROM orders WHERE order_number='$NUM'")
check "admin order page shows it" "$(page $A "admin/order.php?id=$OID" | grep -c 'custom-note__text">Kamala and Sunil')" "1"
check "admin order list flags the order" "$(page $A admin/orders.php | grep -c 'title="Contains a personalised item"')" "1"
MAIL=$(ls -t ../storage/mail/*received* | head -1)
check "order e-mail contains the message" "$(grep -c 'Personalisation:</strong> <span style="white-space:pre-line;">Kamala and Sunil' "$MAIL")" "1"
check "cart is empty after the order" "$(q "SELECT COUNT(*) FROM cart_items")" "0"
saveprod -d is_customizable=1 -d custom_label=Name -d custom_max_length=30 >/dev/null
check "past order keeps its message after product changes" "$(q "SELECT customization_text FROM order_items WHERE product_id=8")" "Kamala and Sunil"

echo "== Optional message and switching off"
check "message now optional (box unticked)" "$(q "SELECT custom_required FROM products WHERE id=8")" "0"
check "product page says Optional" "$(page $G "$PP" | grep -c 'personalise__tag">Optional')" "1"
TOKPAGE=$PP post $G cart.php -d action=add -d product_id=8 -d quantity=1 >/dev/null
check "optional: added without a message" "$(q "SELECT quantity||'|'||IFNULL(customization_text,'NULL') FROM cart_items WHERE product_id=8")" "1|NULL"
check "cart offers to add a message" "$(page $G cart.php | grep -c 'Add your personalisation')" "1"
saveprod -d is_customizable=1 -d custom_label=Name -d custom_max_length=30 -d custom_required=1 >/dev/null
CP=$(page $G cart.php)
check "made required later: cart asks for the message, checkout blocked" "$(echo "$CP" | grep -c 'Please add your personalisation message below.')|$(curl -s -o /dev/null -w '%{redirect_url}' -b $G $B/checkout.php)" "1|$B/cart.php"
TOKPAGE=cart.php post $G cart.php -d action=message -d product_id=8 -d customization_text=Saman >/dev/null
saveprod -d custom_label=Name -d custom_max_length=30 -d custom_required=1 >/dev/null
check "switched off: card back to Add to cart" "$(page $G "" | grep -c 'aria-label="Add Sample Chocolate Gift Box to cart"')|$(page $G "" | grep -c 'class="personalisable"')" "2|0"
check "switched off: settings kept for later" "$(q "SELECT is_customizable||'|'||custom_label||'|'||custom_max_length FROM products WHERE id=8")" "0|Name|30"
FP=$(page $G checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
TOKPAGE=checkout.php post $G checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod -d cart_fingerprint=$FP >/dev/null
check "switched off: old cart message not ordered" "$(q "SELECT COUNT(*)||'|'||IFNULL(MAX(customization_text),'NULL') FROM order_items WHERE order_id=(SELECT MAX(id) FROM orders)")" "1|NULL"
check "no errors in the log" "$(grep -c 'ERROR' ../storage/logs/*.log 2>/dev/null | awk -F: '{s+=$2} END {print s+0}')" "0"

echo ""
echo "RESULT: $pass passed, $fail failed"
