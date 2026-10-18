#!/bin/bash
# GiftAura - sale prices (crossed-out normal price) and the wishlist (heart button).
B=http://127.0.0.1:8093/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar
A=admin.jar; C=cust.jar; D=other.jar; G=guest.jar
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null
EP="admin/product-edit.php?id=1"
saveprod(){ TOKPAGE=$EP post $A "$EP" -d action=save -d name=Sample%20Red%20Rose%20Bouquet -d slug=sample-red-rose-bouquet -d sku=SAMPLE-FB-001 -d category_id=4 -d low_stock_threshold=3 -d is_active=1 -d is_featured=1 "$@"; }

echo "== Sale price (admin)"
saveprod -d price=4500 -d sale_price=4500 >/dev/null
check "sale price equal to normal price refused" "$(q "SELECT price||'|'||IFNULL(compare_at_price,'-') FROM products WHERE id=1")" "4500|-"
saveprod -d price=4500 -d sale_price=0 >/dev/null
check "sale price 0 refused" "$(q "SELECT IFNULL(compare_at_price,'-') FROM products WHERE id=1")" "-"
saveprod -d price=4500 -d sale_price=abc >/dev/null
check "sale price text refused" "$(q "SELECT IFNULL(compare_at_price,'-') FROM products WHERE id=1")" "-"
saveprod -d price=4500 -d sale_price=3375 >/dev/null
check "sale saved: customer pays 3375, normal 4500" "$(q "SELECT price||'|'||compare_at_price FROM products WHERE id=1")" "3375|4500"
check "activity log mentions the sale" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE details LIKE '%on sale, normally 4500%'")" "1"
E=$(page $A "$EP")
check "edit form shows normal and sale price" "$(echo "$E" | grep -o 'id="price" name="price" value="[0-9]*' | grep -o '[0-9]*$')|$(echo "$E" | grep -o 'id="sale_price" name="sale_price" value="[0-9]*' | grep -o '[0-9]*$')" "4500|3375"
check "admin list shows the sale" "$(page $A admin/products.php | grep -c 'sale −25%, was <s>Rs. 4,500.00</s>')" "1"

echo "== Sale price (shop)"
H=$(page $G "")
check "card: -25% badge" "$(echo "$H" | grep -c 'class="sale-tag">-25%')" "2"
check "card: normal price crossed out" "$(echo "$H" | grep -c '<s class="price__was"><span class="sr-only">Normal price </span>Rs. 4,500.00</s>')" "2"
check "card: sale price shown" "$(echo "$H" | grep -c 'price__now">Rs. 3,375.00')" "2"
P=$(page $G "product.php?slug=sample-red-rose-bouquet")
check "product page: You save" "$(echo "$P" | grep -c 'You save Rs. 1,125.00 (25%)')" "1"
check "On sale page lists only sale products" "$(page $G 'products.php?sale=1' | grep -o '[0-9]* products\?</p>\|[0-9]* product\b' | head -1)" "1 product"
check "On sale link in menu and footer" "$(echo "$H" | grep -c 'products.php?sale=1')" "2"
TOKPAGE=register.php post $C register.php -d full_name=Nimal%20Perera -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE users SET email_verified_at=datetime(\"now\") WHERE email=\"nimal@example.com\"");'
TOKPAGE=product.php?slug=sample-red-rose-bouquet post $C cart.php -d action=add -d product_id=1 -d quantity=2 >/dev/null
check "cart charges the sale price" "$(page $C cart.php | grep -o 'Subtotal (2 items)</dt><dd>Rs. [0-9,.]*' | grep -o 'Rs.*')" "Rs. 6,750.00"
FP=$(page $C checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
TOKPAGE=checkout.php post $C checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod -d cart_fingerprint=$FP >/dev/null
check "order line saved at the sale price" "$(q "SELECT unit_price||'|'||line_total FROM order_items ORDER BY id DESC LIMIT 1")" "3375|6750"
saveprod -d price=4500 -d sale_price= >/dev/null
check "ending the sale restores the normal price" "$(q "SELECT price||'|'||IFNULL(compare_at_price,'-') FROM products WHERE id=1")" "4500|-"
check "past order keeps the sale price" "$(q "SELECT unit_price FROM order_items ORDER BY id DESC LIMIT 1")" "3375"
check "no badge after the sale" "$(page $G "" | grep -c 'sale-tag')" "0"
saveprod -d price=4500 -d sale_price=3375 >/dev/null

echo "== Wishlist"
H=$(page $G "")
check "heart on every product card" "$(echo "$H" | grep -o 'class="wish__button' | wc -l | tr -d ' ')" "$(echo "$H" | grep -o '<article class="product-card' | wc -l | tr -d ' ')"
check "heart on the product page picture" "$(page $G 'product.php?slug=sample-pink-rose-bunch' | grep -c 'class="wish wish--large"')" "1"
check "wishlist POST without CSRF -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -b $C -d product_id=2 $B/wishlist.php)" "419"
R=$(TOKPAGE=products.php post $G wishlist.php -d product_id=2 -d return=/giftaura/products.php)
check "guest heart -> login page" "$R" "303 $B/login.php?next=%2Fgiftaura%2Fproducts.php"
TOKPAGE=login.php post $G login.php -d email=nimal@example.com -d password=secret123 -d next=/giftaura/products.php >/dev/null
check "after login the product is saved" "$(q "SELECT product_id FROM wishlist_items w JOIN users u ON u.id=w.user_id WHERE u.email='nimal@example.com'")" "2"
check "heart shows saved" "$(page $G products.php | grep -c 'wish__button is-saved')" "1"
check "header count 1" "$(page $G products.php | grep -o 'wish-count">[0-9]*' | grep -o '[0-9]*$')" "1"
TOKPAGE=products.php post $G wishlist.php -d product_id=1 -d return=/giftaura/products.php >/dev/null
check "second product saved" "$(q "SELECT COUNT(*) FROM wishlist_items")" "2"
W=$(page $G account/wishlist.php)
check "My wishlist page lists 2" "$(echo "$W" | grep -o '<article class="product-card' | wc -l | tr -d ' ')" "2"
check "wishlist shows the sale price" "$(echo "$W" | grep -c 'price__now">Rs. 3,375.00')" "1"
TOKPAGE=products.php post $G wishlist.php -d product_id=2 -d return=/giftaura/products.php >/dev/null
check "pressing again removes it" "$(q "SELECT COUNT(*) FROM wishlist_items WHERE product_id=2")" "0"
TOKPAGE=products.php post $G wishlist.php -d product_id=11 -d return=/giftaura/products.php >/dev/null
check "inactive product cannot be saved" "$(q "SELECT COUNT(*) FROM wishlist_items WHERE product_id=11")" "0"
TOKPAGE=products.php post $G wishlist.php -d product_id=99999 -d return=/giftaura/products.php >/dev/null
check "unknown product refused" "$(q "SELECT COUNT(*) FROM wishlist_items")" "1"
R=$(TOKPAGE=products.php post $G wishlist.php -d product_id=3 -d return=https://evil.example/)
check "outside return address ignored" "$R" "303 $B/account/wishlist.php"
TOKPAGE=register.php post $D register.php -d full_name=Other%20Person -d email=other@example.com -d phone=0722222222 -d password=secret123 -d password_confirm=secret123 >/dev/null
check "other customer has an empty wishlist" "$(page $D account/wishlist.php | grep -c 'Your wishlist is empty')" "1"
check "admin cannot use a wishlist" "$(TOKPAGE=products.php post $A wishlist.php -d product_id=4 -d return=/giftaura/products.php >/dev/null; q "SELECT COUNT(*) FROM wishlist_items w JOIN users u ON u.id=w.user_id WHERE u.role='admin'")" "0"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE products SET is_active=0 WHERE id=1");'
W=$(page $G account/wishlist.php)
check "deactivated product hidden from wishlist page" "$(echo "$W" | grep -c 'Sample Red Rose Bouquet')|$(echo "$W" | grep -o '<article class="product-card' | wc -l | tr -d ' ')" "0|1"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE products SET is_active=1 WHERE id=1");'
check "guest wishlist page -> login" "$(curl -s -o /dev/null -w '%{http_code}' $B/account/wishlist.php)" "303"

echo "== Result: $pass passed, $fail failed"
