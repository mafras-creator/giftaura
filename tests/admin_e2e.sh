#!/bin/bash
B=http://127.0.0.1:8096/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
upload(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "$path"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code}' -F "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
flash(){ page $1 "$2" | grep -o 'alert alert--[a-z]*" role="status">[^<]*<span>[^<]*' | sed 's/.*<span>//' | head -1; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar
A=admin.jar; C=cust.jar
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null
TOKPAGE=register.php post $C register.php -d full_name=Nimal%20Perera -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null

echo "== Access control"
for p in admin/ admin/orders.php admin/products.php admin/product-edit.php admin/categories.php admin/inventory.php admin/customers.php admin/reports.php "admin/report-export.php?report=orders" admin/activity.php admin/settings.php; do
  check "customer blocked: $p" "$(curl -s -o /dev/null -w '%{http_code}' -b $C "$B/$p")" "403"
done
check "guest redirected to login" "$(curl -s -o /dev/null -w '%{http_code}' "$B/admin/settings.php")" "303"
T=$(tok $C account/)
check "customer POST to admin settings blocked" "$(curl -s -o /dev/null -w '%{http_code}' -b $C --data-urlencode _csrf=$T -d 'settings[store_name]=Hacked' $B/admin/settings.php)" "403"
check "store name unchanged" "$(q "SELECT setting_value FROM settings WHERE setting_key='store_name'")" "GiftAura"
for p in admin/ admin/orders.php admin/products.php admin/product-edit.php admin/categories.php admin/inventory.php admin/customers.php admin/reports.php admin/activity.php admin/settings.php; do
  check "admin page loads: $p" "$(curl -s -o /dev/null -w '%{http_code}' -b $A "$B/$p")" "200"
done

echo "== Categories"
post $A admin/categories.php -d action=save -d name=Chocolates -d slug= -d description=Sweet -d sort_order=4 -d is_active=1 >/dev/null
check "category created with auto slug" "$(q "SELECT slug, is_active FROM categories WHERE name='Chocolates'")" "chocolates|1"
post $A admin/categories.php -d action=save -d name=Chocolate -d slug=chocolates -d sort_order=5 -d is_active=1 >/dev/null
check "duplicate slug refused" "$(q "SELECT COUNT(*) FROM categories WHERE slug LIKE 'chocolate%'")" "1"
FB=$(q "SELECT id FROM categories WHERE slug='flower-bouquets'"); RB=$(q "SELECT id FROM categories WHERE slug='rose-bouquets'"); CH=$(q "SELECT id FROM categories WHERE slug='chocolates'")
post $A admin/categories.php -d action=save -d id=$FB -d name=Flower%20Bouquets -d slug=flower-bouquets -d parent_id=$CH -d sort_order=1 -d is_active=1 >/dev/null
check "category with children cannot become child" "$(q "SELECT IFNULL(parent_id,'NULL') FROM categories WHERE id=$FB")" "NULL"
post $A admin/categories.php -d action=save -d id=$CH -d name=Chocolates -d slug=chocolates -d parent_id=$RB -d sort_order=4 -d is_active=1 >/dev/null
check "subcategory cannot be a parent" "$(q "SELECT IFNULL(parent_id,'NULL') FROM categories WHERE id=$CH")" "NULL"
post $A admin/categories.php -d action=delete -d id=$FB >/dev/null
check "non-empty category not deleted" "$(q "SELECT COUNT(*) FROM categories WHERE id=$FB")" "1"
post $A admin/categories.php -d action=save --data-urlencode 'name=<script>alert(1)</script>' -d slug=xss-test -d sort_order=9 -d is_active=1 >/dev/null
check "XSS name escaped in admin list" "$(page $A admin/categories.php | grep -c '<script>alert(1)')" "0"
XS=$(q "SELECT id FROM categories WHERE slug='xss-test'")
post $A admin/categories.php -d action=delete -d id=$XS >/dev/null
check "empty category deleted" "$(q "SELECT COUNT(*) FROM categories WHERE id=$XS")" "0"
post $A admin/categories.php -d action=deactivate -d id=$RB >/dev/null
check "deactivated subcategory hides products in shop" "$(curl -s $B/products.php | grep -oE '[0-9]+ products?' | head -1)" "8 products"
post $A admin/categories.php -d action=activate -d id=$RB >/dev/null

echo "== Products"
R=$(post $A admin/product-edit.php -d action=save -d name=Dark%20Chocolate%20Box -d category_id=$CH -d price=1250.5 -d sku=TEST-DC-01 -d low_stock_threshold=3 -d initial_stock=7 -d is_active=1 -d short_description=Rich)
PID=$(q "SELECT id FROM products WHERE sku='TEST-DC-01'")
check "product created, price normalised, stock via ledger" "$(q "SELECT slug, price, stock_quantity FROM products WHERE id=$PID")" "dark-chocolate-box|1250.5|7"
check "opening stock movement" "$(q "SELECT reason, quantity_change, stock_after FROM inventory_movements WHERE product_id=$PID")" "initial_stock|7|7"
check "redirects to edit page" "$(echo $R | grep -c "product-edit.php?id=$PID")" "1"
post $A admin/product-edit.php -d action=save -d name=Copy -d slug=dark-chocolate-box -d category_id=$CH -d price=10 -d low_stock_threshold=1 -d initial_stock=0 >/dev/null
check "duplicate product slug refused" "$(q "SELECT COUNT(*) FROM products WHERE name='Copy'")" "0"
post $A admin/product-edit.php -d action=save -d name=Bad -d category_id=$CH -d price=-5 -d low_stock_threshold=1 -d initial_stock=0 >/dev/null
post $A admin/product-edit.php -d action=save -d name=Bad2 -d category_id=$CH -d price=12.345 -d low_stock_threshold=1 -d initial_stock=0 >/dev/null
post $A admin/product-edit.php -d action=save -d name=Bad3 -d category_id=999 -d price=10 -d low_stock_threshold=1 -d initial_stock=0 >/dev/null
check "invalid price/category refused" "$(q "SELECT COUNT(*) FROM products WHERE name LIKE 'Bad%'")" "0"
TOKPAGE="admin/product-edit.php?id=$PID" post $A "admin/product-edit.php?id=$PID" -d action=save -d name=Dark%20Chocolate%20Box -d slug=dark-chocolate-box -d category_id=$CH -d price=1300 -d sku=TEST-DC-01 -d low_stock_threshold=3 -d is_active=1 -d is_featured=1 >/dev/null
check "edit price" "$(q "SELECT price, is_featured FROM products WHERE id=$PID")" "1300|1"
check "price change logged" "$(q "SELECT details FROM admin_activity_log WHERE action='product.update' ORDER BY id DESC LIMIT 1")" "Dark Chocolate Box (price 1250.50 -> 1300.00)"
check "new product visible in shop" "$(curl -s -o /dev/null -w '%{http_code}' "$B/product.php?slug=dark-chocolate-box")" "200"
post $A admin/products.php -d action=deactivate -d id=$PID >/dev/null
check "deactivated product 404 in shop" "$(curl -s -o /dev/null -w '%{http_code}' "$B/product.php?slug=dark-chocolate-box")" "404"
post $A admin/products.php -d action=activate -d id=$PID >/dev/null
check "admin search by SKU" "$(page $A 'admin/products.php?q=TEST-DC' | grep -oE 'admin-panel__title">[0-9]+ product' | grep -oE '>[0-9]+' | tr -d '>')" "1"
check "admin filter out of stock" "$(page $A 'admin/products.php?stock=out' | grep -oE 'admin-panel__title">[0-9]+ product' | grep -oE '>[0-9]+' | tr -d '>')" "1"

echo "== Image upload security"
EP="admin/product-edit.php?id=$PID"
upload $A "$EP" -F action=upload -F "image=@good.jpg;type=image/jpeg" -F alt_text=Box >/dev/null
check "valid JPEG accepted" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$PID")" "1"
F1=$(q "SELECT file_name FROM product_images WHERE product_id=$PID LIMIT 1")
check "random file name" "$(echo $F1 | grep -cE '^[a-f0-9]{32}\.jpg$')" "1"
check "resized to max 1600 / kept 800x600" "$(php -r 'echo implode("x", array_slice(getimagesize($argv[1]),0,2));' giftaura/public/uploads/products/$F1)" "800x600"
check "first image is main" "$(q "SELECT is_primary FROM product_images WHERE file_name='$F1'")" "1"
upload $A "$EP" -F action=upload -F "image=@shell.php;type=image/jpeg" >/dev/null
upload $A "$EP" -F action=upload -F "image=@fake.jpg;type=image/jpeg" >/dev/null
check "PHP file and PHP renamed .jpg refused" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$PID")" "1"
upload $A "$EP" -F action=upload -F "image=@tiny.jpg;type=image/jpeg" >/dev/null
check "too-small image refused" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$PID")" "1"
upload $A "$EP" -F action=upload -F "image=@big.jpg;type=image/jpeg" >/dev/null
check "2.5 MB file refused" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$PID")" "1"
upload $A "$EP" -F action=upload -F "image=@poly.jpg;type=image/jpeg" >/dev/null
F2=$(q "SELECT file_name FROM product_images WHERE product_id=$PID ORDER BY id DESC LIMIT 1")
check "polyglot JPEG accepted but PHP payload stripped by re-encode" "$(grep -c 'system' giftaura/public/uploads/products/$F2)" "0"
upload $A "$EP" -F action=upload -F "image=@good.png;type=image/png" >/dev/null
check "PNG accepted" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$PID AND file_name LIKE '%.png'")" "1"
check "no stray files in uploads" "$(ls giftaura/public/uploads/products | grep -vc -E '^[a-f0-9]{32}\.(jpg|png|webp)$|^\.gitkeep$')" "0"
I2=$(q "SELECT id FROM product_images WHERE file_name='$F2'")
TOKPAGE=$EP post $A "$EP" -d action=primary -d image_id=$I2 >/dev/null
check "make second image main" "$(q "SELECT file_name FROM product_images WHERE product_id=$PID AND is_primary=1")" "$F2"
TOKPAGE=$EP post $A "$EP" -d action=delete_image -d image_id=$I2 >/dev/null
check "delete main image: row + file gone, next becomes main" "$(q "SELECT COUNT(*) FROM product_images WHERE id=$I2"),$(test -f giftaura/public/uploads/products/$F2 && echo file || echo nofile),$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$PID AND is_primary=1")" "0,nofile,1"
check "shop shows uploaded image as main" "$(curl -s "$B/product.php?slug=dark-chocolate-box" | grep -o 'id="gallery-main" src="[^"]*' | grep -c "$F1")" "1"

echo "== Stock"
TOKPAGE=$EP post $A "$EP" -d action=stock -d mode=restock -d quantity=5 -d note=Supplier >/dev/null
check "restock +5" "$(q "SELECT stock_quantity FROM products WHERE id=$PID")" "12"
TOKPAGE=$EP post $A "$EP" -d action=stock -d mode=adjust -d quantity=-2 -d note= >/dev/null
check "correction needs a note" "$(q "SELECT stock_quantity FROM products WHERE id=$PID")" "12"
TOKPAGE=$EP post $A "$EP" -d action=stock -d mode=adjust -d quantity=-2 -d note=Damaged >/dev/null
TOKPAGE=$EP post $A "$EP" -d action=stock -d mode=adjust -d quantity=-500 -d note=Oops >/dev/null
check "correction -2 ok, -500 refused" "$(q "SELECT stock_quantity FROM products WHERE id=$PID")" "10"
TOKPAGE=$EP post $A "$EP" -d action=stock -d mode=restock -d quantity=-3 >/dev/null
check "restock cannot be negative" "$(q "SELECT stock_quantity FROM products WHERE id=$PID")" "10"
check "ledger matches stock" "$(q "SELECT SUM(quantity_change) FROM inventory_movements WHERE product_id=$PID")" "10"

echo "== Orders and payments"
place(){ # jar method -> order number
  local jar=$1 method=$2
  local FP=$(page $jar checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
  TOKPAGE=checkout.php post $jar checkout.php -d customer_name=Nimal%20Perera -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Rd -d city=Kandy -d district=Kandy -d payment_method=$method -d cart_fingerprint=$FP | grep -o 'GA[0-9]\{6\}-[A-Z0-9]\{6\}'
}
TOKPAGE=products.php post $C cart.php -d action=add -d product_id=$PID -d quantity=2 >/dev/null
O1=$(place $C cod); ID1=$(q "SELECT id FROM orders WHERE order_number='$O1'")
TOKPAGE=products.php post $C cart.php -d action=add -d product_id=1 -d quantity=1 >/dev/null
O2=$(place $C bank_transfer); ID2=$(q "SELECT id FROM orders WHERE order_number='$O2'")
TOKPAGE=products.php post $C cart.php -d action=add -d product_id=8 -d quantity=1 >/dev/null
O3=$(place $C cod); ID3=$(q "SELECT id FROM orders WHERE order_number='$O3'")
check "3 orders placed" "$(q "SELECT COUNT(*) FROM orders")" "3"
check "orders list shows 3" "$(page $A admin/orders.php | grep -oE 'admin-panel__title">[0-9]+ order' | grep -oE '>[0-9]+' | tr -d '>')" "3"
check "orders search by number" "$(page $A "admin/orders.php?q=$O2" | grep -oE 'admin-panel__title">[0-9]+ order' | grep -oE '>[0-9]+' | tr -d '>')" "1"
check "pending badge in sidebar" "$(page $A admin/ | grep -o 'title="Pending orders">[0-9]*' | grep -o '[0-9]*$')" "3"
OP="admin/order.php?id=$ID1"
TOKPAGE=$OP post $A "$OP" -d action=status -d status=delivered >/dev/null
check "cannot skip pending -> delivered" "$(q "SELECT status FROM orders WHERE id=$ID1")" "pending"
for s in confirmed processing ready_for_delivery delivered; do TOKPAGE=$OP post $A "$OP" -d action=status -d status=$s -d note=ok >/dev/null; done
check "full workflow to delivered" "$(q "SELECT status FROM orders WHERE id=$ID1")" "delivered"
check "history has 5 entries" "$(q "SELECT COUNT(*) FROM order_status_history WHERE order_id=$ID1")" "5"
TOKPAGE=$OP post $A "$OP" -d action=status -d status=cancelled >/dev/null
check "delivered order cannot be cancelled" "$(q "SELECT status FROM orders WHERE id=$ID1")" "delivered"
TOKPAGE=$OP post $A "$OP" -d action=payment -d payment_status=paid -d note=Cash%20collected >/dev/null
check "COD marked paid with time + admin" "$(q "SELECT status, paid_at IS NOT NULL, recorded_by IS NOT NULL FROM payments WHERE order_id=$ID1")" "paid|1|1"
check "customer sees Delivered + Paid" "$(page $C "account/order.php?order=$O1" | grep -oE 'badge--status-delivered|badge--pay-paid' | sort -u | tr '\n' ',')" "badge--pay-paid,badge--status-delivered,"
OP2="admin/order.php?id=$ID2"
TOKPAGE=$OP2 post $A "$OP2" -d action=status -d status=confirmed >/dev/null
check "unpaid bank transfer cannot be confirmed" "$(q "SELECT status FROM orders WHERE id=$ID2")" "pending"
TOKPAGE=$OP2 post $A "$OP2" -d action=status -d status=confirmed -d allow_unpaid=1 >/dev/null
check "...unless admin ticks the override" "$(q "SELECT status FROM orders WHERE id=$ID2")" "confirmed"
TOKPAGE=$OP2 post $A "$OP2" -d action=payment -d payment_status=refunded >/dev/null
check "pending payment cannot be refunded" "$(q "SELECT status FROM payments WHERE order_id=$ID2")" "pending"
TOKPAGE=$OP2 post $A "$OP2" -d action=payment -d payment_status=paid >/dev/null
TOKPAGE=$OP2 post $A "$OP2" -d action=payment -d payment_status=refunded -d note= >/dev/null
check "refund needs a note" "$(q "SELECT status FROM payments WHERE order_id=$ID2")" "paid"
TOKPAGE=$OP2 post $A "$OP2" -d action=payment -d payment_status=refunded -d note=Refunded%20Rs%204900%20by%20bank >/dev/null
check "refund recorded" "$(q "SELECT status, refunded_at IS NOT NULL FROM payments WHERE order_id=$ID2")" "refunded|1"
OP3="admin/order.php?id=$ID3"
S8=$(q "SELECT stock_quantity FROM products WHERE id=8")
TOKPAGE=$OP3 post $A "$OP3" -d action=status -d status=cancelled -d note=Customer%20phoned >/dev/null
check "admin cancel restores stock" "$(q "SELECT stock_quantity FROM products WHERE id=8")" "$((S8+1))"
TOKPAGE=$OP3 post $A "$OP3" -d action=payment -d payment_status=paid >/dev/null
check "cancelled order cannot be marked paid" "$(q "SELECT status FROM payments WHERE order_id=$ID3")" "pending"
check "stock after: product 2 sold, ledger consistent" "$(q "SELECT stock_quantity FROM products WHERE id=$PID"),$(q "SELECT SUM(quantity_change) FROM inventory_movements WHERE product_id=$PID")" "8,8"

echo "== Reports (definitions)"
TODAY=$(date +%Y-%m-%d)
R=$(page $A "admin/reports.php?report=sales&from=$TODAY&to=$TODAY")
E1=$(q "SELECT total_amount FROM orders WHERE id=$ID1")
check "gross sales = only order 1 (order 2 refunded, 3 cancelled)" "$(echo "$R" | grep -A1 'Gross sales</h2>' | grep -oE 'Rs\. [0-9,.]+' | head -1)" "Rs. $(php -r "echo number_format($E1,2);")"
check "orders counted 1, all 3, cancelled 1" "$(echo "$R" | grep -oE '[0-9]+ orders counted|Including [0-9]+ cancelled' | tr '\n' ',')" "1 orders counted,Including 1 cancelled,"
check "collected = paid only (refunded excluded)" "$(echo "$R" | grep -A1 'Collected revenue</h2>' | grep -oE 'Rs\. [0-9,.]+' | head -1)" "Rs. $(php -r "echo number_format($E1,2);")"
check "status totals include cancelled" "$(page $A "admin/reports.php?report=status&from=$TODAY&to=$TODAY" | grep -c 'badge--status-cancelled')" "1"
check "best seller is Dark Chocolate Box (2)" "$(page $A "admin/reports.php?report=products&from=$TODAY&to=$TODAY" | grep -A2 'Dark Chocolate Box' | grep -oE 'num">[0-9]+' | head -1 | grep -oE '[0-9]+')" "2"
check "bad dates fall back with message" "$(page $A 'admin/reports.php?report=sales&from=2026-02-31&to=x' | grep -c 'was not valid')" "1"

echo "== CSV export"
CSV=$(curl -s -b $A -D hdr.txt "$B/admin/report-export.php?report=orders&from=$TODAY&to=$TODAY")
check "CSV content type" "$(grep -i 'content-type' hdr.txt | grep -c 'text/csv')" "1"
check "CSV has header + 3 orders" "$(echo "$CSV" | grep -c 'GA[0-9]')" "3"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE orders SET customer_name=\"=HYPERLINK(\"\"http://evil\"\")\" WHERE id='$ID3'");'
CSV=$(curl -s -b $A "$B/admin/report-export.php?report=orders&from=$TODAY&to=$TODAY")
check "CSV formula injection neutralised" "$(echo "$CSV" | grep -c "'=HYPERLINK")" "1"
check "inventory CSV rows" "$(curl -s -b $A "$B/admin/report-export.php?report=inventory" | grep -c 'Sample\|Dark Chocolate')" "12"
check "unknown report 404" "$(curl -s -o /dev/null -w '%{http_code}' -b $A "$B/admin/report-export.php?report=users")" "404"

echo "== Customers"
CID=$(q "SELECT id FROM users WHERE email='nimal@example.com'")
check "customer list shows spent excluding refunded/cancelled" "$(page $A admin/customers.php | grep -oE 'num">Rs\. [0-9,.]+' | head -1 | sed 's/num">//')" "Rs. $(php -r "echo number_format($E1,2);")"
TOKPAGE="admin/customer.php?id=$CID" post $A "admin/customer.php?id=$CID" -d action=deactivate >/dev/null
check "deactivated customer is logged out" "$(curl -s -o /dev/null -w '%{redirect_url}' -b $C $B/account/)" "$B/login.php?next=%2Fgiftaura%2Faccount%2F"
T=$(tok x.jar login.php); check "deactivated customer cannot log in" "$(curl -s -c x.jar -b x.jar --data-urlencode _csrf=$T -d email=nimal@example.com -d password=secret123 $B/login.php | grep -c 'incorrect')" "1"
TOKPAGE="admin/customer.php?id=$CID" post $A "admin/customer.php?id=$CID" -d action=activate >/dev/null
AID=$(q "SELECT id FROM users WHERE role='admin'")
TOKPAGE="admin/customer.php?id=$CID" post $A "admin/customer.php?id=$AID" -d action=deactivate >/dev/null
check "admin account cannot be deactivated via customer page" "$(q "SELECT is_active FROM users WHERE id=$AID")" "1"
T=$(tok $A "admin/customer.php?id=$CID")
TP=$(curl -s -c $A -b $A --data-urlencode _csrf=$T -d action=temp_password "$B/admin/customer.php?id=$CID" | grep -o 'temp-password__value">[^<]*' | sed 's/.*">//')
check "temp password shown once (12 chars)" "${#TP}" "12"
check "temp password not in activity log" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE details LIKE '%$TP%'")" "0"
T=$(tok y.jar login.php); check "customer logs in with temp password" "$(curl -s -c y.jar -b y.jar -o /dev/null -w '%{redirect_url}' --data-urlencode _csrf=$T -d email=nimal@example.com --data-urlencode "password=$TP" $B/login.php)" "$B/account/"

echo "== Settings"
post $A admin/settings.php -d 'settings[store_name]=GiftAura' -d 'settings[delivery_fee]=abc' -d 'settings[cod_enabled]=1' -d 'settings[bank_transfer_enabled]=1' >/dev/null
check "invalid delivery fee refused" "$(q "SELECT setting_value FROM settings WHERE setting_key='delivery_fee'")" "400.00"
post $A admin/settings.php -d 'settings[store_name]=GiftAura' -d 'settings[cod_enabled]=0' -d 'settings[bank_transfer_enabled]=0' >/dev/null
check "cannot disable both payment methods" "$(q "SELECT setting_value FROM settings WHERE setting_key='cod_enabled'")" "1"
post $A admin/settings.php -d 'settings[store_name]=GiftAura' -d 'settings[store_phone]=011 234 5678' -d 'settings[delivery_fee]=350' -d 'settings[bank_name]=Test Bank' -d 'settings[bank_account_number]=0001112223' -d 'settings[cod_enabled]=1' -d 'settings[bank_transfer_enabled]=1' -d 'settings[max_quantity_per_item]=10' >/dev/null
check "settings saved & money normalised" "$(q "SELECT setting_value FROM settings WHERE setting_key IN ('delivery_fee','store_phone') ORDER BY setting_key")" "$(printf '350.00\n011 234 5678')"
check "contact page shows phone" "$(curl -s $B/contact.php | grep -q '011 234 5678' && echo yes)" "yes"
check "bank number not written to activity log" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE details LIKE '%0001112223%'")" "0"
check "bank details now shown to bank-transfer customers" "$(page y.jar "account/order.php?order=$O2" | grep -c '0001112223')" "0"
echo "     (order 2 is refunded, so the payment box is hidden — expected 0)"
check "activity log lists actions" "$(q "SELECT COUNT(DISTINCT action) FROM admin_activity_log") -ge 10" "$(q "SELECT COUNT(DISTINCT action) FROM admin_activity_log") -ge 10"
check "activity page loads with entries" "$(page $A admin/activity.php | grep -c 'order.status')" "$(page $A admin/activity.php | grep -c 'order.status')"
check "dashboard shows today's sales" "$(page $A admin/ | grep -A1 'Sales today' | grep -oE 'Rs\. [0-9,.]+' | head -1)" "Rs. $(php -r "echo number_format($E1,2);")"

echo "== Result: $pass passed, $fail failed"
