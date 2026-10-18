#!/bin/bash
# GiftAura - homepage banners, category pictures and the new shop design.
B=http://127.0.0.1:8094/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
upload(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "$path"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code}' -F "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar
A=admin.jar; C=cust.jar; G=guest.jar
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null
TOKPAGE=register.php post $C register.php -d full_name=Nimal%20Perera -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null
nb(){ q "SELECT COUNT(*) FROM banners"; }
save(){ upload $A admin/banners.php -F action=save "$@" >/dev/null; }

echo "== New design"
H=$(page $G "")
check "header search with yellow button" "$(echo "$H" | grep -c 'header-search__button')" "1"
check "All categories menu lists categories" "$(echo "$H" | grep -o 'class="catmenu__link"' | wc -l | tr -d ' ')" "3"
check "no banners -> welcome section" "$(echo "$H" | grep -c 'class="welcome"')" "1"
check "round category pictures" "$(echo "$H" | grep -o 'class="category-circle"' | wc -l | tr -d ' ')" "3"
check "yellow Add to cart on product cards" "$(echo "$H" | grep -c 'btn btn--yellow btn--small btn--block')" "$(echo "$H" | grep -c 'name="action" value="add"')"
check "out-of-stock card has no Add button" "$(echo "$H" | grep -c 'product-card__soldout')" "1"
P=$(page $G "product.php?slug=sample-red-rose-bouquet")
check "product page: big yellow Add to cart" "$(echo "$P" | grep -c 'btn btn--yellow btn--large add-to-cart__button')" "1"
check "product page: Similar products" "$(echo "$P" | grep -c 'Similar products')" "1"
TOKPAGE=product.php?slug=sample-red-rose-bouquet post $G cart.php -d action=add -d product_id=1 -d quantity=2 >/dev/null
check "cart count badge in header" "$(page $G products.php | grep -o 'cart-link__count">[0-9]*' | grep -o '[0-9]*$')" "2"

echo "== Banner access control"
check "customer: Banners page 403" "$(curl -s -o /dev/null -w '%{http_code}' -b $C $B/admin/banners.php)" "403"
check "guest: Banners page -> login" "$(curl -s -o /dev/null -w '%{http_code}' $B/admin/banners.php)" "303"
T=$(tok $C account/); check "customer: forged banner POST 403" "$(curl -s -o /dev/null -w '%{http_code}' -b $C -F _csrf=$T -F action=save -F title=x -F 'image=@wide.jpg;type=image/jpeg' $B/admin/banners.php)" "403"
check "no banner created by customer" "$(nb)" "0"

echo "== Banner validation"
save -F "title=No picture"
check "picture required" "$(nb)" "0"
save -F "title=Too narrow" -F "image=@good.jpg;type=image/jpeg"
check "narrow picture (800 px) refused" "$(nb)" "0"
save -F "title=Script" -F "image=@fake.jpg;type=image/jpeg"
check "PHP disguised as JPG refused" "$(nb)" "0"
save -F "title=Bad link" -F "link_url=javascript:alert(1)" -F "image=@wide.jpg;type=image/jpeg"
check "javascript: link refused" "$(nb)" "0"
save -F "title=Bad link 2" -F "link_url=//evil.example/x" -F "image=@wide.jpg;type=image/jpeg"
check "//other-site link refused" "$(nb)" "0"
save -F "title=Bad dates" -F "starts_on=2026-12-10" -F "ends_on=2026-12-01" -F "image=@wide.jpg;type=image/jpeg"
check "end before start refused" "$(nb)" "0"
save -F "title=Button no link" -F "button_text=Shop now" -F "image=@wide.jpg;type=image/jpeg"
check "button without link refused" "$(nb)" "0"
check "no files left behind by refused uploads" "$(ls ../public/uploads/banners | grep -v gitkeep | wc -l | tr -d ' ')" "0"

echo "== Banner on the homepage"
save -F "title=Fresh flowers <b>now</b>" -F "subtitle=Delivered with care" -F "button_text=Shop flowers" -F "link_url=products.php?category=flower-bouquets" -F sort_order=2 -F show_text=1 -F is_active=1 -F "image=@wide.jpg;type=image/jpeg"
check "banner saved" "$(nb)" "1"
F1=$(q "SELECT image_file FROM banners WHERE id=1")
check "random file name" "$(echo $F1 | grep -cE '^[a-f0-9]{32}\.jpg$')" "1"
check "file stored in uploads/banners" "$([ -f ../public/uploads/banners/$F1 ] && echo yes)" "yes"
H=$(page $G "")
check "slider shown, welcome hidden" "$(echo "$H" | grep -c 'data-slider')|$(echo "$H" | grep -c 'class="welcome"')" "1|0"
check "title escaped" "$(echo "$H" | grep -c 'Fresh flowers &lt;b&gt;now&lt;/b&gt;')" "1"
check "button links inside the shop" "$(echo "$H" | grep -c 'href="/giftaura/products.php?category=flower-bouquets">Shop flowers')" "1"
check "picture served from uploads" "$(curl -s -o /dev/null -w '%{http_code}' $B/uploads/banners/$F1)" "200"
check "one slide: no arrows" "$(echo "$H" | grep -c 'data-slide-next')" "0"
save -F "title=Picture with own text" -F "link_url=https://www.example.com/offer" -F sort_order=1 -F is_active=1 -F "image=@wide.jpg;type=image/jpeg"
H=$(page $G "")
check "two slides + arrows + 2 dots" "$(echo "$H" | grep -c 'data-slide-next')|$(echo "$H" | grep -o 'data-slide-to=' | wc -l | tr -d ' ')" "1|2"
check "display order: order 1 first" "$(echo "$H" | grep -o 'aria-label="1 of 2"' | wc -l | tr -d ' ')|$(echo "$H" | grep -A3 'aria-label="1 of 2"' | grep -c 'https://www.example.com/offer')" "1|1"
check "no text: whole slide is the link, title as alt text" "$(echo "$H" | grep -c 'alt="Picture with own text"')" "1"
TOKPAGE=admin/banners.php post $A admin/banners.php -d action=hide -d id=2 >/dev/null
check "hidden banner not on homepage" "$(page $G "" | grep -c 'Picture with own text')" "0"
check "state Hidden in admin" "$(page $A admin/banners.php | grep -c '>Hidden<')" "1"
upload $A admin/banners.php -F action=save -F id=1 -F "title=Fresh flowers <b>now</b>" -F "button_text=Shop flowers" -F "link_url=products.php?category=flower-bouquets" -F starts_on=2099-01-01 -F is_active=1 -F show_text=1 >/dev/null
check "future start: Scheduled, not shown" "$(page $A admin/banners.php | grep -c '>Scheduled<')|$(page $G "" | grep -c 'data-slider')" "1|0"
upload $A admin/banners.php -F action=save -F id=1 -F "title=Fresh flowers <b>now</b>" -F starts_on=2020-01-01 -F ends_on=2020-01-31 -F is_active=1 >/dev/null
check "past end: Ended, not shown" "$(page $A admin/banners.php | grep -c '>Ended<')|$(page $G "" | grep -c 'data-slider')" "1|0"
check "editing kept the same picture" "$(q "SELECT image_file FROM banners WHERE id=1")" "$F1"
upload $A admin/banners.php -F action=save -F id=1 -F "title=Fresh flowers" -F is_active=1 -F "image=@wide.jpg;type=image/jpeg" >/dev/null
F2=$(q "SELECT image_file FROM banners WHERE id=1")
check "new picture replaces and deletes the old file" "$([ "$F1" != "$F2" ] && [ ! -f ../public/uploads/banners/$F1 ] && [ -f ../public/uploads/banners/$F2 ] && echo yes)" "yes"
rm -f ../public/uploads/banners/$F2
check "banner with missing file is skipped" "$(page $G "" | grep -c 'data-slider')|$(page $G "" | grep -c 'class="welcome"')" "0|1"
F3=$(q "SELECT image_file FROM banners WHERE id=2")
TOKPAGE=admin/banners.php post $A admin/banners.php -d action=delete -d id=2 >/dev/null
check "delete removes row and file" "$(q "SELECT COUNT(*) FROM banners WHERE id=2")|$([ -f ../public/uploads/banners/$F3 ] && echo file || echo nofile)" "0|nofile"
check "activity log: 2 created, hidden, 3 edits, deleted" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE entity_type='banner'")" "7"
check "PHP cannot be uploaded into banners folder name" "$(ls ../public/uploads/banners | grep -c '\.php')" "0"

echo "== Category pictures"
T=$(tok $A "admin/categories.php?edit=3")
curl -s -o /dev/null -c $A -b $A -F _csrf=$T -F action=save -F id=3 -F "name=Gift Items" -F slug=gift-items -F sort_order=3 -F is_active=1 -F "image=@good.png;type=image/png" $B/admin/categories.php
CF=$(q "SELECT image_file FROM categories WHERE id=3")
check "category picture saved" "$(echo $CF | grep -cE '^[a-f0-9]{32}\.png$')" "1"
check "shown in the round icon (and its popular tile)" "$(page $G "" | grep -c "category-circle__image\"><img src=\"/giftaura/uploads/categories/$CF")|$(page $G "" | grep -c "uploads/categories/$CF")" "1|2"
T=$(tok $A "admin/categories.php?edit=3")
curl -s -o /dev/null -c $A -b $A -F _csrf=$T -F action=save -F id=3 -F "name=Gift Items" -F slug=gift-items -F sort_order=3 -F is_active=1 -F "image=@fake.jpg;type=image/jpeg" $B/admin/categories.php
check "bad picture refused, category still saved, old picture kept" "$(q "SELECT image_file FROM categories WHERE id=3")" "$CF"
T=$(tok $A "admin/categories.php?edit=3")
curl -s -o /dev/null -c $A -b $A -F _csrf=$T -F action=save -F id=3 -F "name=Gift Items" -F slug=gift-items -F sort_order=3 -F is_active=1 -F remove_image=1 $B/admin/categories.php
check "remove picture clears column and file" "$(q "SELECT image_file IS NULL FROM categories WHERE id=3")|$([ -f ../public/uploads/categories/$CF ] && echo file || echo nofile)" "1|nofile"

echo "== Main categories and subcategories"
T=$(tok $A admin/categories.php)
curl -s -o /dev/null -c $A -b $A -F _csrf=$T -F action=save -F name=Hampers -F parent_id= -F sort_order=4 -F is_active=1 -F "image=@good.png;type=image/png" $B/admin/categories.php
HID=$(q "SELECT id FROM categories WHERE slug='hampers'")
check "main category Hampers added (no parent, with picture)" "$(q "SELECT parent_id IS NULL, image_file IS NOT NULL FROM categories WHERE id=$HID")" "1|1"
check "Add subcategory form is pre-filled" "$(page $A "admin/categories.php?parent=$HID" | grep -c "Add a subcategory to &quot;Hampers&quot;")|$(page $A "admin/categories.php?parent=$HID" | grep -c "value=\"$HID\" selected")" "1|1"
TOKPAGE="admin/categories.php?parent=$HID" post $A admin/categories.php -d action=save -d name=Fruit%20Hampers -d parent_id=$HID -d sort_order=0 -d is_active=1 >/dev/null
check "subcategory saved under Hampers" "$(q "SELECT parent_id FROM categories WHERE slug='fruit-hampers'")" "$HID"
check "Hampers on homepage and in menu" "$(page $G "" | grep -c 'category-circle__name">Hampers</span>')|$(page $G "" | grep -c 'category=fruit-hampers')" "1|1"

echo "== Product photos: up to 4 when adding a product"
newprod(){ local T=$(tok $A admin/product-edit.php); curl -s -o /dev/null -w '%{redirect_url}' -c $A -b $A -F _csrf=$T -F action=save -F "name=$1" -F category_id=3 -F price=1500 -F initial_stock=5 -F low_stock_threshold=2 -F is_active=1 "${@:2}" $B/admin/product-edit.php; }
R=$(newprod "Photo Test One" -F "images[]=@good.jpg;type=image/jpeg" -F "images[]=@good.png;type=image/png" -F "images[]=@wide.jpg;type=image/jpeg" -F "images[]=@good.jpg;type=image/jpeg")
P1=$(q "SELECT id FROM products WHERE name='Photo Test One'")
check "product created with 4 photos at once" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$P1")" "4"
check "first photo is the main photo" "$(q "SELECT sort_order FROM product_images WHERE product_id=$P1 AND is_primary=1")" "0"
check "upload box hidden when 4 photos" "$(page $A "admin/product-edit.php?id=$P1" | grep -c 'name="images\[\]"')" "0"
T=$(tok $A "admin/product-edit.php?id=$P1"); curl -s -o /dev/null -c $A -b $A -F _csrf=$T -F action=upload -F "images[]=@good.jpg;type=image/jpeg" "$B/admin/product-edit.php?id=$P1"
check "5th photo refused" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$P1")" "4"
newprod "Photo Test Two" -F "images[]=@good.jpg;type=image/jpeg" -F "images[]=@good.jpg;type=image/jpeg" -F "images[]=@good.jpg;type=image/jpeg" -F "images[]=@good.jpg;type=image/jpeg" -F "images[]=@good.png;type=image/png" >/dev/null
P2=$(q "SELECT id FROM products WHERE name='Photo Test Two'")
check "5 chosen -> only 4 kept" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$P2")" "4"
newprod "Photo Test Three" -F "images[]=@good.jpg;type=image/jpeg" -F "images[]=@fake.jpg;type=image/jpeg" -F "images[]=@good.png;type=image/png" >/dev/null
P3=$(q "SELECT id FROM products WHERE name='Photo Test Three'")
check "bad file skipped, good photos kept" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$P3")" "2"
check "message names the bad file" "$(page $A "admin/product-edit.php?id=$P3" | grep -c 'fake.jpg: Only JPG, PNG and WebP images are allowed.')" "1"
newprod "Photo Test Four" >/dev/null
P4=$(q "SELECT id FROM products WHERE name='Photo Test Four'")
check "product without photos still created" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$P4")" "0"
T=$(tok $A "admin/product-edit.php?id=$P4"); curl -s -o /dev/null -c $A -b $A -F _csrf=$T -F action=upload -F "images[]=@good.jpg;type=image/jpeg" -F "images[]=@good.png;type=image/png" "$B/admin/product-edit.php?id=$P4"
check "edit page: 2 photos at once" "$(q "SELECT COUNT(*) FROM product_images WHERE product_id=$P4")|$(page $A "admin/product-edit.php?id=$P4" | grep -c '2 more allowed')" "2|1"
check "no orphan files: files on disk = rows" "$(ls ../public/uploads/products | grep -c -E '^[a-f0-9]{32}\.(jpg|png)$')" "$(q "SELECT COUNT(*) FROM product_images")"

echo "== Logo (Admin -> Settings)"
logo(){ local T=$(tok $A admin/settings.php); curl -s -o /dev/null -c $A -b $A -F _csrf=$T -F action=logo_upload "$@" $B/admin/settings.php; }
check "default logo in header" "$(page $G "" | grep -c 'assets/img/logo.svg')" "2"
T=$(tok $C account/); check "customer cannot change the logo" "$(curl -s -o /dev/null -w '%{http_code}' -b $C -F _csrf=$T -F action=logo_upload -F 'logo=@good.png;type=image/png' $B/admin/settings.php)" "403"
logo -F "logo=@good.png;type=image/png"
L1=$(ls ../public/uploads/brand | grep -E '^[a-f0-9]{32}\.png$')
H=$(page $G "")
check "square logo used in header, name still shown" "$(echo "$H" | grep -c "uploads/brand/$L1")|$(echo "$H" | grep -c 'class="brand__name"')" "2|1"
check "browser tab icon is the logo (png)" "$(echo "$H" | grep -c "rel=\"icon\" href=\"/giftaura/uploads/brand/$L1\" type=\"image/png\"")" "1"
check "admin sidebar uses the logo" "$(page $A admin/ | grep -c "uploads/brand/$L1")" "2"
logo -F "logo=@fake.jpg;type=image/jpeg"
check "fake image refused, logo kept" "$(ls ../public/uploads/brand | grep -cE '^[a-f0-9]{32}')|$(page $G "" | grep -c "uploads/brand/$L1")" "1|2"
logo -F "logo=@wide.jpg;type=image/jpeg"
L2=$(ls ../public/uploads/brand | grep -E '^[a-f0-9]{32}\.jpg$')
H=$(page $G "")
check "wide logo replaces the old file" "$(ls ../public/uploads/brand | grep -cE '^[a-f0-9]{32}')|$(echo "$H" | grep -c "uploads/brand/$L2")" "1|2"
check "wide logo: store name text hidden" "$(echo "$H" | grep -c 'class="brand__name"')|$(echo "$H" | grep -c 'brand__mark--wide')" "0|1"
TOKPAGE=admin/settings.php post $A admin/settings.php -d action=logo_reset >/dev/null
check "reset: default logo back, file removed" "$(page $G "" | grep -c 'assets/img/logo.svg')|$(ls ../public/uploads/brand | grep -cE '^[a-f0-9]{32}')" "2|0"
check "logo changes in activity log" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action='settings.logo'")" "3"

echo "== About page"
rm -f ../storage/content/about.json ../public/uploads/people/*.jpg ../public/uploads/people/*.png
AB=$(page $G about.php)
check "founder, initials and location shown" "$(echo "$AB" | grep -c 'founder-card__name">Asjadh Mohamed Mafras')|$(echo "$AB" | grep -c '>AM</span>')|$(echo "$AB" | grep -c 'Bandarakoswatta, Kurunegala, Sri Lanka')" "1|1|3"
check "live numbers: categories" "$(echo "$AB" | grep -o '<dt>Categories</dt><dd>[0-9]*' | grep -o '[0-9]*$')" "$(q "SELECT COUNT(*) FROM categories WHERE parent_id IS NULL AND is_active=1")"
check "map allowed only on the About page" "$(curl -s -D - -o /dev/null $B/about.php | grep -ci 'frame-src https://www.google.com')|$(curl -s -D - -o /dev/null $B/ | grep -ci 'frame-src')" "1|0"
check "customer cannot open the editor" "$(curl -s -o /dev/null -w '%{http_code}' -b $C $B/admin/about.php)" "403"
check "editor shows draft notice" "$(page $A admin/about.php | grep -c 'please check them and press Save')" "1"
aboutsave(){ local T=$(tok $A admin/about.php); curl -s -o /dev/null -w '%{http_code}' -c $A -b $A -F _csrf=$T -F "headline=Thoughtful gifts," -F "headline_highlight=from Kurunegala" -F "story_title=Our story" -F "story=First paragraph.

Second <b>paragraph</b>." -F "founder_name=Asjadh Mohamed Mafras" -F "founder_role=Founder & owner" -F "location=Bandarakoswatta, Kurunegala, Sri Lanka" -F "map_query=Bandarakoswatta Kurunegala" "$@" $B/admin/about.php; }
check "invalid year refused" "$(aboutsave -F show_map=1 -F founded_year=1800)|$([ -f ../storage/content/about.json ] && echo saved || echo none)" "200|none"
check "save with founder photo" "$(aboutsave -F show_map=1 -F founded_year=2024 -F "founder_photo=@good.jpg;type=image/jpeg")|$(ls ../public/uploads/people | grep -cE '^[a-f0-9]{32}\.jpg$')" "303|1"
AB=$(page $G about.php)
check "new texts shown, HTML escaped, 2 paragraphs" "$(echo "$AB" | grep -c 'Second &lt;b&gt;paragraph&lt;/b&gt;.')|$(echo "$AB" | grep -c 'class="about-text"')" "1|2"
check "year shown, photo instead of initials" "$(echo "$AB" | grep -c 'Since 2024')|$(echo "$AB" | grep -c 'uploads/people/')|$(echo "$AB" | grep -c '>AM</span>')" "1|1|0"
check "role escaped" "$(echo "$AB" | grep -c 'Founder &amp; owner, GiftAura')" "1"
aboutsave -F show_map=1 -F founded_year=2024 -F remove_photo=1 >/dev/null
check "remove photo deletes the file" "$(ls ../public/uploads/people | grep -cE '^[a-f0-9]{32}')|$(page $G about.php | grep -c '>AM</span>')" "0|1"
aboutsave -F founded_year= >/dev/null
check "map can be switched off" "$(page $G about.php | grep -c '<iframe')|$(curl -s -D - -o /dev/null $B/about.php | grep -ci 'frame-src')" "0|0"
check "activity log" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action='about.update'")" "3"
rm -f ../storage/content/about.json

echo "== Popular categories"
rm -f ../storage/content/popular.json
H=$(page $G "")
check "default: tiles for the main categories with gift counts" "$(echo "$H" | grep -o 'class="popular-tile popular-tile--' | wc -l | tr -d ' ')|$(echo "$H" | grep -c 'popular-tile__tagline">[0-9]* gifts\?<')" "$(q "SELECT COUNT(*) FROM categories WHERE parent_id IS NULL AND is_active=1")|$(q "SELECT COUNT(*) FROM categories WHERE parent_id IS NULL AND is_active=1")"
popsave(){ local T=$(tok $A admin/categories.php); curl -s -o /dev/null -w '%{http_code}' -c $A -b $A --data-urlencode _csrf=$T -d action=popular "$@" $B/admin/categories.php; }
T=$(tok $C account/); check "customer cannot change tiles" "$(curl -s -o /dev/null -w '%{http_code}' -b $C --data-urlencode _csrf=$T -d action=popular -d 'popular_id[]=1' $B/admin/categories.php)" "403"
popsave -d "popular_id[]=99999" --data-urlencode "popular_tagline[]=x" >/dev/null
check "unknown category refused" "$([ -f ../storage/content/popular.json ] && echo saved || echo none)" "none"
popsave -d "popular_id[]=1" --data-urlencode "popular_tagline[]=This short line is far too long for a tile ok" >/dev/null
check "too long line refused" "$([ -f ../storage/content/popular.json ] && echo saved || echo none)" "none"
popsave -d "popular_id[]=2" --data-urlencode "popular_tagline[]=Beauty <b>begins</b> here!" -d "popular_id[]=1" --data-urlencode "popular_tagline[]=Say it with flowers!" -d "popular_id[]=2" --data-urlencode "popular_tagline[]=duplicate" -d "popular_id[]=" --data-urlencode "popular_tagline[]=" >/dev/null
H=$(page $G "")
check "chosen tiles in chosen order, duplicate ignored" "$(echo "$H" | grep -o 'popular-tile__name">[^<]*' | sed 's/.*">//' | tr '\n' ',')" "Beauty Creams,Flower Bouquets,"
check "short line escaped" "$(echo "$H" | grep -c 'Beauty &lt;b&gt;begins&lt;/b&gt; here!')" "1"
check "admin box shows the saved choice" "$(page $A admin/categories.php | grep -c 'value="Say it with flowers!"')" "1"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE categories SET is_active=0 WHERE id=2");'
check "inactive category tile hidden" "$(page $G "" | grep -o 'popular-tile__name">[^<]*' | sed 's/.*">//' | tr '\n' ',')" "Flower Bouquets,"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE categories SET is_active=1 WHERE id=2");'
rm -f ../storage/content/popular.json

echo "== Result: $pass passed, $fail failed"
