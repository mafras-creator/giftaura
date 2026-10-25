#!/bin/bash
# GiftAura - login before add to cart (product kept), new profile card, in-shop sales with invoices and stock.
B=http://127.0.0.1:8088/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
sql(){ php -r '$p=new PDO("sqlite:test.db"); $p->exec($argv[1]);' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
postp(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
code(){ curl -s -o /dev/null -w '%{http_code}' -b $1 "$B/$2"; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar
A=admin.jar; C=nimal.jar; G=guest.jar; G2=guest2.jar; G3=guest3.jar; S=staff.jar
PP=/giftaura/product.php?slug=sample-red-rose-bouquet
LOGIN_NEXT="$B/login.php?next=%2Fgiftaura%2Fproduct.php%3Fslug%3Dsample-red-rose-bouquet"
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null

echo "== Guest adds to cart -> asked to log in, product kept"
R=$(TOKPAGE='product.php?slug=sample-red-rose-bouquet' post $G cart.php -d action=add -d product_id=1 -d quantity=2 -d return=$PP)
check "guest Add to cart -> login page (next = the product page)" "$R" "303 $LOGIN_NEXT"
LP=$(page $G 'login.php?next=%2Fgiftaura%2Fcart.php')
check "login page: Almost there with the product name" "$(echo "$LP" | grep -c 'Almost there')|$(echo "$LP" | grep -c '<strong>Sample Red Rose Bouquet</strong> will be added to your cart')" "1|1"
check "login page offers Create an account" "$(echo "$LP" | grep -c 'New customer? Create an account')" "1"
check "register page shows the same product" "$(page $G register.php | grep -c '<strong>Sample Red Rose Bouquet</strong> will be in your cart')" "1"
check "out-of-stock product: not sent to login" "$(TOKPAGE=products.php post $G2 cart.php -d action=add -d product_id=4 -d quantity=1 -d return=/giftaura/products.php)" "303 $B/products.php"
check "nothing waiting -> no Almost there box" "$(page $G2 login.php | grep -c 'Almost there')" "0"
TOKPAGE=register.php post $C register.php -d full_name='Nimal Perera' -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null
sql "UPDATE users SET email_verified_at=datetime('now') WHERE email='nimal@example.com'"
rm -f $C
R=$(TOKPAGE=login.php post $G login.php -d email=nimal@example.com -d password=secret123 -d next=$PP)
check "login -> back to the same product page (not the cart)" "$R" "303 $B/product.php?slug=sample-red-rose-bouquet"
CP=$(page $G 'product.php?slug=sample-red-rose-bouquet')
check "cart has the product, qty 2; product page shows added message and badge 2" "$(q "SELECT product_id||'x'||quantity FROM cart_items")|$(echo "$CP" | grep -c '&quot;Sample Red Rose Bouquet&quot; has been added to your cart')|$(echo "$CP" | grep -o 'cart-link__count\">[0-9]*' | grep -o '[0-9]*$')" "1x2|1|2"
check "unsafe return address ignored -> products page" "$(TOKPAGE=products.php post $G2 cart.php -d action=add -d product_id=5 -d quantity=1 -d return=https://evil.example/x | cut -d' ' -f2)" "$B/login.php?next=%2Fgiftaura%2Fproducts.php"
check "waiting product forgotten after login" "$(page $G 'login.php' >/dev/null; curl -s -b $G -o /dev/null -w '%{http_code}' $B/login.php)" "303"
R=$(TOKPAGE='product.php?slug=sample-teddy-bear' post $G cart.php -d action=add -d product_id=9 -d quantity=1)
check "logged-in customer: Add to cart -> cart, not login" "$R" "303 $B/cart.php"
check "second product saved directly" "$(q "SELECT COUNT(*) FROM cart_items")" "2"
# new customer: personalised product, then Create account
sql "UPDATE products SET is_customizable=1, custom_label='Name on the card', custom_max_length=30, custom_required=1 WHERE id=8"
TOKPAGE='product.php?slug=sample-chocolate-gift-box' post $G3 cart.php -d action=add -d product_id=8 -d quantity=1 -d return=/giftaura/products.php?q=chocolate --data-urlencode 'customization_text=Happy birthday Amma' >/dev/null
R=$(TOKPAGE=register.php post $G3 register.php -d full_name='Kumari Silva' -d email=kumari@example.com -d phone=0712223333 -d password=secret123 -d password_confirm=secret123)
check "Create account -> back to the page they were on" "$R" "303 $B/products.php?q=chocolate"
KID=$(q "SELECT id FROM users WHERE email='kumari@example.com'")
check "new account's cart has the product and the message" "$(q "SELECT ci.product_id||'|'||ci.customization_text FROM cart_items ci JOIN carts c ON c.id=ci.cart_id WHERE c.user_id=$KID")" "8|Happy birthday Amma"
check "cart page shows the product and its message" "$(page $G3 cart.php | grep -c 'Sample Chocolate Gift Box' | awk '{print ($1>0)}')|$(page $G3 cart.php | grep -c 'Happy birthday Amma' | awk '{print ($1>0)}')" "1|1"
check "verification e-mail still sent" "$(q "SELECT COUNT(*) FROM email_log WHERE template='verify_email' AND recipient='kumari@example.com'" )" "1"
check "required message missing: not sent to login" "$(TOKPAGE='product.php?slug=sample-chocolate-gift-box' post $G2 cart.php -d action=add -d product_id=8 -d quantity=1 -d return=/giftaura/product.php?slug=sample-chocolate-gift-box | cut -d' ' -f2)" "$B/product.php?slug=sample-chocolate-gift-box"

echo "== Profile card"
sql "INSERT INTO customer_addresses (user_id,label,recipient_name,phone,address_line1,city,district,is_default) VALUES ((SELECT id FROM users WHERE email='nimal@example.com'),'Home','Nimal','0771234567','12 Temple Road','Bandarakoswatta','Kurunegala',1)"
P=$(page $G account/)
check "card: avatar initial N, big name, Verified" "$(echo "$P" | grep -c 'profile-card__avatar" aria-hidden="true">N<')|$(echo "$P" | grep -c 'profile-card__name">Nimal Perera<')|$(echo "$P" | grep -c '(Verified)')" "1|1|1"
check "card: e-mail, mobile and location from the address" "$(echo "$P" | grep -c 'nimal@example.com')|$(echo "$P" | grep -c '0771234567')|$(echo "$P" | grep -c 'Bandarakoswatta, Kurunegala, Sri Lanka')" "1|1|1"
check "Edit link; form hidden until Edit" "$(echo "$P" | grep -c 'profile-card__edit')|$(echo "$P" | grep -c 'name=\"full_name\"')" "1|0"
check "side menu: My profile active with icons, 7 items" "$(echo "$P" | grep -c 'is-active" href="/giftaura/account/" aria-current="page"')|$(echo "$P" | grep -o 'class="account-nav__icon"' | wc -l | tr -d ' ')" "1|7"
check "stats row: 0 orders" "$(echo "$P" | grep -c '<strong>0</strong><span>Orders</span>')" "1"
check "?edit=1 shows the form with Cancel" "$(page $G 'account/?edit=1' | grep -c 'name="full_name"')|$(page $G 'account/?edit=1' | grep -c '>Cancel</a>')" "1|1"
R=$(TOKPAGE='account/?edit=1' postp $G account/ -d full_name=N -d phone=123)
check "invalid input: form stays open with errors" "$(echo "$R" | grep -c 'name="full_name"')|$(echo "$R" | grep -o 'class="field-error"' | wc -l | tr -d ' ')" "1|2"
check "save -> card shows new name" "$(TOKPAGE='account/?edit=1' post $G account/ -d full_name='Nimal Perera Jr' -d phone=0779998888 >/dev/null; page $G account/ | grep -c 'profile-card__name">Nimal Perera Jr<')" "1"
check "unverified customer: tag says not verified; no address -> Sri Lanka" "$(page $G3 account/ | grep -c '(E-mail not verified)')|$(page $G3 account/ | grep -c '</svg><span class="sr-only">Location: </span>Sri Lanka<')" "1|1"
check "other account pages use the new menu" "$(page $G account/orders.php | grep -c 'account-nav__icon')" "7"

echo "== In-shop sales: access"
check "customer: 403" "$(code $G admin/sales.php)" "403"
check "admin: list, new sale, menu item" "$(code $A admin/sales.php)|$(code $A admin/sale-new.php)|$(page $A admin/ | grep -c 'admin-menu__link" href="/giftaura/admin/sales.php"')" "200|200|1"
check "new sale lists in-stock products only (10, incl. hidden online)" "$(page $A admin/sale-new.php | grep -o 'data-price=' | sort -u | wc -l | tr -d ' ')|$(page $A admin/sale-new.php | grep -c 'Sunflower')|$(page $A admin/sale-new.php | grep -c 'hidden online')" "1|0|4"
check "?product=3 preselects it" "$(page $A 'admin/sale-new.php?product=3' | grep -c 'value="3" data-price="6200" data-stock="8" selected')" "1"
check "POST without CSRF -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -b $A -d 'product_id[]=1' -d 'quantity[]=1' $B/admin/sale-new.php)" "419"

echo "== In-shop sales: validation"
NS=admin/sale-new.php
sale(){ TOKPAGE=$NS postp $A $NS "$@"; }
check "no products -> error" "$(sale -d payment_method=cash | grep -c 'Add at least one product')" "1"
check "quantity 0 -> error" "$(sale -d 'product_id[]=1' -d 'quantity[]=0' -d payment_method=cash | grep -c 'whole number from 1')" "1"
check "line without a product -> error" "$(sale -d 'product_id[]=' -d 'quantity[]=2' -d payment_method=cash | grep -c 'Choose a product on every line')" "1"
check "more than stock -> error, nothing saved" "$(sale -d 'product_id[]=2' -d 'quantity[]=4' -d payment_method=cash | grep -c 'Not enough stock for Sample Pink Rose Bunch: 3 left, 4 wanted')|$(q "SELECT COUNT(*) FROM shop_sales")|$(q "SELECT stock_quantity FROM products WHERE id=2")" "1|0|3"
check "same product on two lines counted together (2+2 > 3)" "$(sale -d 'product_id[]=2' -d 'quantity[]=2' -d 'product_id[]=2' -d 'quantity[]=2' -d payment_method=cash | grep -c '3 left, 4 wanted')" "1"
check "discount bigger than subtotal -> error" "$(sale -d 'product_id[]=7' -d 'quantity[]=1' -d payment_method=cash -d discount=2000 | grep -c 'cannot be more than the subtotal')" "1"
check "bad discount / phone / method" "$(sale -d 'product_id[]=7' -d 'quantity[]=1' -d payment_method=crypto -d discount=abc -d customer_phone=12 | grep -o 'class="field-error"' | wc -l | tr -d ' ')" "3"
check "cash received less than total -> error" "$(sale -d 'product_id[]=7' -d 'quantity[]=1' -d payment_method=cash -d amount_received=1000 | grep -c 'less than the total')" "1"
check "typed values kept after an error" "$(sale -d 'product_id[]=7' -d 'quantity[]=1' -d payment_method=card -d customer_name=Saman -d discount=5000 | grep -c 'value="Saman"')" "1"
check "nothing saved, stock unchanged" "$(q "SELECT COUNT(*) FROM shop_sales")|$(q "SELECT stock_quantity FROM products WHERE id=7")" "0|4"

echo "== In-shop sale saved"
R=$(TOKPAGE=$NS post $A $NS -d 'product_id[]=1' -d 'quantity[]=2' -d 'product_id[]=7' -d 'quantity[]=1' -d 'product_id[]=' -d 'quantity[]=' -d 'price[]=1' -d customer_name='Saman Kumara' -d customer_phone='077 123 4567' -d discount=300 -d payment_method=cash -d amount_received=11,000 -d note='Gift wrapped')
SID=$(q "SELECT id FROM shop_sales ORDER BY id DESC LIMIT 1")
check "saved -> invoice page" "$R" "303 $B/admin/sale.php?id=$SID"
INV=$(q "SELECT invoice_number FROM shop_sales WHERE id=$SID")
check "invoice number INV-YYMMDD-0001" "$INV" "INV-$(date +%y%m%d)-0001"
check "totals from database prices: 9000+1800-300 = 10500; cash 11000" "$(q "SELECT subtotal||'|'||discount||'|'||total||'|'||amount_received||'|'||payment_method||'|'||customer_phone FROM shop_sales WHERE id=$SID")" "10800|300|10500|11000|cash|0771234567"
check "items snapshot" "$(q "SELECT product_id||':'||quantity||':'||unit_price||':'||line_total FROM shop_sale_items WHERE sale_id=$SID ORDER BY product_id" | tr '\n' ' ')" "1:2:4500:9000 7:1:1800:1800 "
check "stock reduced (12->10, 4->3)" "$(q "SELECT stock_quantity FROM products WHERE id=1")|$(q "SELECT stock_quantity FROM products WHERE id=7")" "10|3"
check "stock ledger: shop_sale rows linked to the invoice" "$(q "SELECT COUNT(*) FROM inventory_movements WHERE reason='shop_sale' AND shop_sale_id=$SID AND note='$INV'")" "2"
IP=$(page $A "admin/sale.php?id=$SID")
check "invoice: shop name, number, customer, items" "$(echo "$IP" | grep -c 'invoice__shop-name">GiftAura<')|$(echo "$IP" | grep -c "<strong>$INV</strong>")|$(echo "$IP" | grep -c 'Saman Kumara')|$(echo "$IP" | grep -c 'Sample Hand Cream Trio')" "1|1|1|1"
check "invoice: subtotal, discount, total, change, served by" "$(echo "$IP" | grep -c 'Rs. 10,800.00')|$(echo "$IP" | grep -c '− Rs. 300.00')|$(echo "$IP" | grep -c 'invoice__grand"><dt>Total</dt><dd>Rs. 10,500.00')|$(echo "$IP" | grep -c '<dt>Change</dt><dd>Rs. 500.00')|$(echo "$IP" | grep -c 'Served by</dt><dd>')" "1|1|1|1|1"
check "invoice: Print button and note" "$(echo "$IP" | grep -c 'data-print')|$(echo "$IP" | grep -c 'Gift wrapped')" "1|1"
check "store address/phone shown only when set" "$(sql "UPDATE settings SET setting_value='0812345678' WHERE setting_key='store_phone'"; page $A "admin/sale.php?id=$SID" | grep -c '0812345678')" "1"
TOKPAGE=$NS post $A $NS -d 'product_id[]=11' -d 'quantity[]=1' -d payment_method=card >/dev/null
S2=$(q "SELECT id FROM shop_sales ORDER BY id DESC LIMIT 1")
check "second sale: product hidden online can be sold; card; no change line" "$(q "SELECT invoice_number||'|'||total||'|'||IFNULL(amount_received,'NULL') FROM shop_sales WHERE id=$S2")|$(page $A "admin/sale.php?id=$S2" | grep -c 'Cash received')" "INV-$(date +%y%m%d)-0002|900|NULL|0"
check "cash received ignored for card payments" "$(TOKPAGE=$NS post $A $NS -d 'product_id[]=5' -d 'quantity[]=1' -d payment_method=card -d amount_received=50 >/dev/null; q "SELECT IFNULL(amount_received,'NULL') FROM shop_sales ORDER BY id DESC LIMIT 1")" "NULL"
L=$(page $A admin/sales.php)
check "list: 3 invoices, total today 13,600" "$(echo "$L" | grep -c 'admin-panel__title">3 invoices')|$(echo "$L" | grep -c 'Rs. 13,600.00')" "1|3"
check "search by customer and phone" "$(page $A 'admin/sales.php?q=Saman' | grep -c 'admin-panel__title">1 invoice<')|$(page $A 'admin/sales.php?q=0771234567' | grep -c 'admin-panel__title">1 invoice<')" "1|1"
check "date filter: yesterday has none" "$(page $A "admin/sales.php?from=2020-01-01&to=2020-01-02" | grep -c 'admin-panel__title">0 invoices')" "1"
check "dashboard card Shop sales today" "$(page $A admin/ | grep -c 'stat-card__label">Shop sales today')" "1"
check "reports: in-shop sales card" "$(page $A admin/reports.php | grep -c 'stat-card__label">In-shop sales')|$(page $A admin/reports.php | grep -c 'Rs. 13,600.00' | awk '{print ($1>0)}')" "1|1"
check "inventory ledger links the invoice" "$(page $A admin/inventory.php | grep -c "admin/sale.php?id=$SID\">")" "2"
check "website sees the reduced stock (out of stock after selling all)" "$(TOKPAGE=$NS post $A $NS -d 'product_id[]=2' -d 'quantity[]=3' -d payment_method=cash >/dev/null; q "SELECT stock_quantity FROM products WHERE id=2")|$(page $G2 'product.php?slug=sample-pink-rose-bunch' | grep -ci 'out of stock' | awk '{print ($1>0)}')" "0|1"
check "activity log: shop_sale.create x4" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action='shop_sale.create'")" "4"
check "unknown invoice -> 404" "$(code $A 'admin/sale.php?id=999')" "404"

echo "== Cancel (void) an invoice"
SP="admin/sale.php?id=$SID"
check "reason required" "$(TOKPAGE=$SP postp $A $SP -d action=void -d reason=x | grep -c 'Write why the invoice is cancelled')|$(q "SELECT status FROM shop_sales WHERE id=$SID")" "1|completed"
R=$(TOKPAGE=$SP post $A $SP -d action=void -d reason='Customer returned the items')
check "void -> back to invoice" "$R" "303 $B/$SP"
check "status voided with reason; stock back (10->12, 3->4)" "$(q "SELECT status||'|'||void_reason||'|'||(voided_by IS NOT NULL) FROM shop_sales WHERE id=$SID")|$(q "SELECT stock_quantity FROM products WHERE id=1")|$(q "SELECT stock_quantity FROM products WHERE id=7")" "voided|Customer returned the items|1|12|4"
check "ledger: shop_sale_voided rows" "$(q "SELECT COUNT(*) FROM inventory_movements WHERE reason='shop_sale_voided' AND shop_sale_id=$SID")" "2"
VP=$(page $A $SP)
check "invoice shows Cancelled stamp and reason; no cancel form" "$(echo "$VP" | grep -c 'invoice__stamp">Cancelled')|$(echo "$VP" | grep -c 'Customer returned the items')|$(echo "$VP" | grep -c 'name="reason"')" "1|1|0"
check "cancelling twice refused, stock not added twice" "$(TOKPAGE=$SP postp $A $SP -d action=void -d reason='again please' >/dev/null; q "SELECT stock_quantity FROM products WHERE id=1")" "12"
check "list: cancelled not counted in the total" "$(page $A 'admin/sales.php?status=voided' | grep -c 'admin-panel__title">1 invoice<')|$(page $A admin/sales.php | grep -c 'Completed: Rs. 14,500.00')" "1|1"
check "unknown action -> 400" "$(TOKPAGE=$SP post $A $SP -d action=delete | cut -d' ' -f1)" "400"

echo "== Staff with and without the in-shop sales area"
R=$(postp $A admin/staff.php -d action=create -d full_name='Shop Helper' -d username=shop.helper -d 'permissions[]=shop_sales')
PW=$(echo "$R" | grep -o 'temp-password__value">[^<]*' | sed -n 2p | sed 's/.*">//')
check "staff form offers In-shop sales" "$(page $A admin/staff.php | grep -c 'value="shop_sales"')" "1"
TOKPAGE=login.php post $S login.php -d email=shop.helper -d password="$PW" >/dev/null
TOKPAGE=admin/password.php post $S admin/password.php -d current_password="$PW" -d new_password=ShopHelp2026 -d new_password_confirm=ShopHelp2026 >/dev/null
check "staff (shop_sales): can sell; menu item; cannot open products or reports" "$(code $S admin/sale-new.php)|$(page $S admin/ | grep -c 'admin-menu__link" href="/giftaura/admin/sales.php"')|$(code $S admin/products.php)|$(code $S admin/reports.php)" "200|1|403|403"
TOKPAGE=$NS post $S $NS -d 'product_id[]=9' -d 'quantity[]=1' -d payment_method=cash >/dev/null
check "staff sale saved with their name" "$(q "SELECT u.username FROM shop_sales s JOIN users u ON u.id=s.created_by ORDER BY s.id DESC LIMIT 1")" "shop.helper"
TOKPAGE=admin/staff.php post $A admin/staff.php -d action=update -d id=$(q "SELECT id FROM users WHERE username='shop.helper'") -d full_name='Shop Helper' -d 'permissions[]=inquiries' >/dev/null
check "area removed -> 403 on sales pages" "$(code $S admin/sales.php)|$(code $S "admin/sale.php?id=$SID")|$(code $S admin/sale-new.php)" "403|403|403"
check "no errors in the log" "$(grep -c 'ERROR' ../storage/logs/*.log 2>/dev/null | awk -F: '{s+=$2} END {print s+0}')" "0"

echo ""
echo "RESULT: $pass passed, $fail failed"
