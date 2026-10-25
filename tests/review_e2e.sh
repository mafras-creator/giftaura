#!/bin/bash
# GiftAura - product reviews: only after delivery, shown under the product, moderated by the administrator.
B=http://127.0.0.1:8091/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
sql(){ php -r '$p=new PDO("sqlite:test.db"); $p->exec($argv[1]);' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
code(){ curl -s -o /dev/null -w '%{http_code}' -b $1 "$B/$2"; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar
A=admin.jar; C=nimal.jar; K=kumari.jar; G=guest.jar
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null
customer(){ TOKPAGE=register.php post $1 register.php -d full_name="$2" -d email=$3 -d phone=$4 -d password=secret123 -d password_confirm=secret123 >/dev/null
  sql "UPDATE users SET email_verified_at=datetime('now') WHERE email='$3'"; }
order(){ local jar=$1; shift; for pid in "$@"; do TOKPAGE=cart.php post $jar cart.php -d action=add -d product_id=$pid -d quantity=1 >/dev/null; done
  local FP=$(page $jar checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
  TOKPAGE=checkout.php post $jar checkout.php -d customer_name=Test -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod -d cart_fingerprint=$FP >/dev/null; }
deliver(){ local OP="admin/order.php?id=$1"; for s in confirmed processing ready_for_delivery delivered; do TOKPAGE=$OP post $A "$OP" -d action=status -d status=$s >/dev/null; done; }
customer $C "Nimal Perera" nimal@example.com 0771234567
customer $K "Kumari Silva" kumari@example.com 0712223333
order $C 1 2
O1=$(q "SELECT id FROM orders ORDER BY id DESC LIMIT 1"); N1=$(q "SELECT order_number FROM orders WHERE id=$O1")
order $K 1
O2=$(q "SELECT id FROM orders ORDER BY id DESC LIMIT 1")
check "two orders placed" "$(q "SELECT COUNT(*) FROM orders")" "2"

echo "== Before delivery"
check "review form refused (404)" "$(code $C 'account/review.php?product=1')" "404"
check "My reviews: nothing to review" "$(page $C account/reviews.php | grep -c 'Nothing to review yet')" "1"
check "order page has no review link" "$(page $C "account/order.php?order=$N1" | grep -c 'Write a review')" "0"
check "product page: no reviews yet" "$(page $G 'product.php?slug=sample-red-rose-bouquet' | grep -c 'No reviews yet')" "1"
check "account menu has My reviews" "$(page $C account/ | grep -A2 'href="/giftaura/account/reviews.php"' | grep -c '<span>My reviews</span>')" "1"

echo "== After delivery"
deliver $O1
check "order delivered" "$(q "SELECT status FROM orders WHERE id=$O1")" "delivered"
MAIL=$(ls -t ../storage/mail/*delivered* | head -1)
check "delivered e-mail invites a review" "$(grep -c 'account/reviews.php" style=' "$MAIL")" "1"
check "My reviews lists both products" "$(page $C account/reviews.php | grep -c 'Write a review</a>')" "2"
check "order page: Write a review on each item" "$(page $C "account/order.php?order=$N1" | grep -c '★ Write a review')" "2"
RP="account/review.php?product=1"
check "review form opens" "$(code $C "$RP")" "200"
check "form shows the public name" "$(page $C "$RP" | grep -c '(Nimal P.)')" "1"
check "POST without CSRF -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -b $C -d action=save -d rating=5 "$B/$RP")" "419"
TOKPAGE=$RP post $C "$RP" -d action=save -d comment=Nice >/dev/null
check "no stars chosen -> refused" "$(q "SELECT COUNT(*) FROM product_reviews")" "0"
TOKPAGE=$RP post $C "$RP" -d action=save -d rating=6 >/dev/null
check "6 stars refused" "$(q "SELECT COUNT(*) FROM product_reviews")" "0"
LONG=$(printf 'x%.0s' {1..1001})
TOKPAGE=$RP post $C "$RP" -d action=save -d rating=5 -d comment=$LONG >/dev/null
check "1001-character comment refused" "$(q "SELECT COUNT(*) FROM product_reviews")" "0"
R=$(TOKPAGE=$RP post $C "$RP" -d action=save -d rating=5 --data-urlencode $'comment=<b>Lovely</b> roses\r\nFresh & on time')
check "review saved -> back to My reviews" "$R" "303 $B/account/reviews.php"
check "row: 5 stars, name Nimal P., delivered order" "$(q "SELECT rating||'|'||reviewer_name||'|'||order_id||'|'||is_visible FROM product_reviews")" "5|Nimal P.|$O1|1"
P=$(page $G 'product.php?slug=sample-red-rose-bouquet')
check "product page: Customer reviews (1), average 5.0" "$(echo "$P" | grep -c 'Customer reviews (1)')|$(echo "$P" | grep -c 'reviews__average">5.0')" "1|1"
check "comment shown as text (escaped)" "$(echo "$P" | grep -c '&lt;b&gt;Lovely&lt;/b&gt; roses')|$(echo "$P" | grep -c '<b>Lovely</b>')" "1|0"
check "name and Verified purchase shown, e-mail not shown" "$(echo "$P" | grep -c '<strong>Nimal P.</strong>')|$(echo "$P" | grep -c 'Verified purchase')|$(echo "$P" | grep -c 'nimal@example.com')" "1|1|0"
check "rating near the price links to the reviews" "$(echo "$P" | grep -c 'class="rating-link" href="#reviews"')" "1"
check "5-star bar full" "$(echo "$P" | grep -c 'reviews__bar-fill--100')" "1"
check "card shows rating and count" "$(page $G 'products.php?q=Red+Rose' | grep -c 'card-rating__count">(1)')" "1"
TOKPAGE=$RP post $C "$RP" -d action=save -d rating=3 -d comment=Good >/dev/null
check "edit: still one review, now 3 stars" "$(q "SELECT COUNT(*)||'|'||rating||'|'||comment FROM product_reviews")" "1|3|Good"
TOKPAGE=account/review.php?product=2 post $C "account/review.php?product=2" -d action=save -d rating=4 >/dev/null
check "second product: 4 stars without comment" "$(q "SELECT rating||'|'||IFNULL(comment,'NULL') FROM product_reviews WHERE product_id=2")" "4|NULL"
check "My reviews: none waiting, 2 written" "$(page $C account/reviews.php | grep -c 'reviewed every product')|$(page $C account/reviews.php | grep -c 'Edit or delete')" "1|2"
check "order page now says Edit your review" "$(page $C "account/order.php?order=$N1" | grep -c 'Edit your review')" "2"

echo "== Who may review"
check "customer without a delivered order: 404" "$(code $K 'account/review.php?product=1')" "404"
TOKPAGE=account/ post $K 'account/review.php?product=1' -d action=save -d rating=1 >/dev/null
check "direct POST from that customer saves nothing" "$(q "SELECT COUNT(*) FROM product_reviews WHERE user_id=(SELECT id FROM users WHERE email='kumari@example.com')")" "0"
check "product not in the order: 404" "$(code $C 'account/review.php?product=3')" "404"
check "administrator cannot review: 404" "$(code $A 'account/review.php?product=1')" "404"
check "guest -> login" "$(curl -s -o /dev/null -w '%{redirect_url}' -b $G $B/account/reviews.php | grep -c 'login.php')" "1"
deliver $O2
TOKPAGE=account/review.php?product=1 post $K "account/review.php?product=1" -d action=save -d rating=2 -d comment=Late >/dev/null
P=$(page $G 'product.php?slug=sample-red-rose-bouquet')
check "two reviews: average 2.5, half stars" "$(echo "$P" | grep -c 'reviews__average">2.5')|$(echo "$P" | grep -c 'stars stars--50 stars--large')" "1|1"
check "cancelled order does not allow reviews" "$(sql "UPDATE orders SET status='cancelled' WHERE id=$O2"; code $K 'account/review.php?product=1')" "404"
sql "UPDATE orders SET status='delivered' WHERE id=$O2"

echo "== Administrator"
check "customer gets 403 on Admin → Reviews" "$(code $C admin/reviews.php)" "403"
AR=$(page $A admin/reviews.php)
check "admin list shows 3 reviews" "$(echo "$AR" | grep -c 'admin-panel__title">3 reviews')" "1"
check "admin sees real customer name" "$(echo "$AR" | grep -c '>Kumari Silva</a>')" "1"
check "filter: 2 stars" "$(page $A 'admin/reviews.php?rating=2' | grep -c 'admin-panel__title">1 review<')" "1"
RID=$(q "SELECT id FROM product_reviews WHERE rating=2")
TOKPAGE=admin/reviews.php post $A admin/reviews.php -d id=$RID -d action=hide >/dev/null
check "hidden" "$(q "SELECT is_visible FROM product_reviews WHERE id=$RID")" "0"
P=$(page $G 'product.php?slug=sample-red-rose-bouquet')
check "hidden review not on product page; average back to 3.0" "$(echo "$P" | grep -c 'Late')|$(echo "$P" | grep -c 'reviews__average">3.0')" "0|1"
check "customer sees Hidden by the store" "$(page $K account/reviews.php | grep -c 'Hidden by the store')" "1"
TOKPAGE=account/review.php?product=1 post $K "account/review.php?product=1" -d action=save -d rating=1 -d comment=Edited >/dev/null
check "customer edit keeps it hidden" "$(q "SELECT is_visible||'|'||comment FROM product_reviews WHERE id=$RID")" "0|Edited"
TOKPAGE=admin/reviews.php post $A admin/reviews.php -d id=$RID -d action=show >/dev/null
check "shown again" "$(q "SELECT is_visible FROM product_reviews WHERE id=$RID")" "1"
check "activity log: hide + show" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action IN ('review.hide','review.show')")" "2"
TOKPAGE=admin/reviews.php post $A admin/reviews.php -d id=$RID -d action=delete >/dev/null
check "admin delete" "$(q "SELECT COUNT(*) FROM product_reviews WHERE id=$RID")" "0"
check "unknown action -> 400" "$(TOKPAGE=admin/reviews.php post $A admin/reviews.php -d id=1 -d action=boom | cut -d' ' -f1)" "400"

echo "== Customer deletes, paging"
TOKPAGE=account/review.php?product=2 post $C "account/review.php?product=2" -d action=delete >/dev/null
check "own review deleted, can write again" "$(q "SELECT COUNT(*) FROM product_reviews WHERE product_id=2")|$(page $C account/reviews.php | grep -c 'Write a review</a>')" "0|1"
for i in 1 2 3 4 5 6; do sql "INSERT INTO users(role,full_name,email,password_hash) VALUES ('customer','Buyer $i','b$i@example.com','x')"; sql "INSERT INTO product_reviews(product_id,user_id,rating,comment,reviewer_name,created_at) VALUES (1,(SELECT id FROM users WHERE email='b$i@example.com'),5,'Review $i','Buyer $i',datetime('now','-$i days'))"; done
P=$(page $G 'product.php?slug=sample-red-rose-bouquet')
check "7 reviews: 5 on the first page and Older reviews link" "$(echo "$P" | grep -o 'class="review-item"' | wc -l | tr -d ' ')|$(echo "$P" | grep -c 'rpage=2#reviews')" "5|1"
check "second page shows the rest" "$(page $G 'product.php?slug=sample-red-rose-bouquet&rpage=2' | grep -o 'class="review-item"' | wc -l | tr -d ' ')" "2"
check "page number out of range is clamped" "$(page $G 'product.php?slug=sample-red-rose-bouquet&rpage=99' | grep -c 'Page 2 of 2')" "1"
check "no errors in the log" "$(grep -c 'ERROR' ../storage/logs/*.log 2>/dev/null | awk -F: '{s+=$2} END {print s+0}')" "0"

echo ""
echo "RESULT: $pass passed, $fail failed"
