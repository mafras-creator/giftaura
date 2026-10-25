#!/bin/bash
# GiftAura - super administrator, staff administrators with permissions, and customer inquiries.
B=http://127.0.0.1:8089/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
sql(){ php -r '$p=new PDO("sqlite:test.db"); $p->exec($argv[1]);' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
postp(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
code(){ curl -s -o /dev/null -w '%{http_code}' -b $1 "$B/$2"; }
redir(){ curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b $1 "$B/$2"; }
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar
A=admin.jar; S=staff.jar; S2=staff2.jar; C=nimal.jar; G=guest.jar; G2=guest2.jar
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null

echo "== Super administrator: Staff accounts"
check "super admin opens Staff accounts" "$(code $A admin/staff.php)" "200"
check "topbar says Main administrator" "$(page $A admin/ | grep -c 'Main administrator')" "1"
check "menu has Staff accounts and Inquiries" "$(page $A admin/ | grep -c 'admin/staff.php')|$(page $A admin/ | grep -c 'admin/inquiries.php')" "1|2"
check "new form ticks the 3 default areas" "$(page $A admin/staff.php | grep -c 'name="permissions\[\]" value="[a-z]*" checked')" "3"
check "create without CSRF -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -b $A -d action=create -d full_name=X -d username=xx.yy "$B/admin/staff.php")" "419"
R=$(postp $A admin/staff.php -d action=create -d full_name=Nimal -d username='Bad Name!' -d 'permissions[]=products')
check "invalid username refused" "$(echo "$R" | grep -c 'Use 3–30 characters')|$(q "SELECT COUNT(*) FROM users WHERE role='staff'")" "1|0"
R=$(postp $A admin/staff.php -d action=create -d full_name='Sahan Staff' -d username=sahan.staff)
check "no area ticked refused" "$(echo "$R" | grep -c 'Tick at least one area')" "1"
R=$(postp $A admin/staff.php -d action=create -d full_name='Sahan Fernando' -d username=Sahan.Staff -d email= -d 'permissions[]=products' -d 'permissions[]=categories' -d 'permissions[]=inquiries' -d 'permissions[]=hacker')
PW=$(echo "$R" | grep -o 'temp-password__value">[^<]*' | sed -n 2p | sed 's/.*">//')
check "staff created; username shown lower-case; password shown once" "$(echo "$R" | grep -c 'Staff account created')|$(echo "$R" | grep -o 'temp-password__value">[^<]*' | sed -n 1p | sed 's/.*">//')|${#PW}" "1|sahan.staff|12"
check "row: staff, username, cleaned permissions, must change, created by admin" "$(q "SELECT role||'|'||username||'|'||permissions||'|'||must_change_password||'|'||IFNULL(email,'NULL')||'|'||created_by FROM users WHERE username='sahan.staff'")" "staff|sahan.staff|products,categories,inquiries|1|NULL|1"
check "password stored only as a hash" "$(q "SELECT COUNT(*) FROM users WHERE password_hash LIKE '%$PW%'")|$(grep -rc "$PW" ../storage/logs/ 2>/dev/null | awk -F: '{s+=$2} END {print s+0}')" "0|0"
check "password has upper, lower and digit" "$(echo "$PW" | grep -c '[A-Z]')$(echo "$PW" | grep -c '[a-z]')$(echo "$PW" | grep -c '[2-9]')" "111"
R=$(postp $A admin/staff.php -d action=create -d full_name='Other' -d username=sahan.staff -d 'permissions[]=products')
check "duplicate username refused" "$(echo "$R" | grep -c 'already taken')" "1"
check "activity log: staff.create" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action='staff.create'")" "1"
check "staff list shows the account" "$(page $A admin/staff.php | grep -c 'sahan.staff')" "1"
SID=$(q "SELECT id FROM users WHERE username='sahan.staff'")

echo "== Staff first login and forced password change"
TOKPAGE=login.php post $S login.php -d email=sahan.staff -d password=wrongpass1 >/dev/null
check "wrong password: not logged in" "$(code $S admin/)" "303"
check "login with username" "$(TOKPAGE=login.php post $S login.php -d email=SAHAN.STAFF -d password="$PW" | grep -c 'admin/')" "1"
check "must change password: dashboard -> password page" "$(redir $S admin/products.php | grep -c 'admin/password.php')" "1"
check "password page opens and shows the username" "$(page $S admin/password.php | grep -c 'sahan.staff')" "1"
TOKPAGE=admin/password.php post $S admin/password.php -d current_password="$PW" -d new_password=short -d new_password_confirm=short >/dev/null
check "weak new password refused" "$(q "SELECT must_change_password FROM users WHERE id=$SID")" "1"
TOKPAGE=admin/password.php post $S admin/password.php -d current_password="$PW" -d new_password="$PW" -d new_password_confirm="$PW" >/dev/null
check "same password refused" "$(q "SELECT must_change_password FROM users WHERE id=$SID")" "1"
R=$(TOKPAGE=admin/password.php post $S admin/password.php -d current_password="$PW" -d new_password=StaffPass2026 -d new_password_confirm=StaffPass2026)
check "new password saved -> dashboard" "$R|$(q "SELECT must_change_password FROM users WHERE id=$SID")" "303 $B/admin/|0"

echo "== What staff can and cannot open"
D=$(page $S admin/)
check "topbar says Staff; welcome lists the areas" "$(echo "$D" | grep -c 'admin-topbar__role">Staff')|$(echo "$D" | grep -c 'Products and stock, Categories, Customer inquiries')" "1|1"
check "menu: Products, Categories, Inquiries shown" "$(echo "$D" | grep -c 'admin-menu__link" href="[^"]*admin/products.php"')|$(echo "$D" | grep -c 'admin-menu__link" href="[^"]*admin/categories.php"')|$(echo "$D" | grep -c 'admin-menu__link" href="[^"]*admin/inquiries.php"')" "1|1|1"
check "menu: Orders, Customers, Reports, Settings, Staff hidden" "$(echo "$D" | grep -c 'admin/orders.php\|admin/customers.php\|admin/reports.php\|admin/settings.php\|admin/staff.php')" "0"
check "dashboard hides sales figures and failed-mail warning" "$(echo "$D" | grep -c 'Latest orders\|Revenue')" "0"
for p in products.php categories.php inventory.php inquiries.php product-edit.php; do check "staff opens $p (200)" "$(code $S admin/$p)" "200"; done
for p in orders.php customers.php reports.php report-export.php reviews.php banners.php about.php settings.php emails.php activity.php staff.php "order.php?id=1" "customer.php?id=1"; do check "staff blocked from $p (403)" "$(code $S "admin/$p")" "403"; done
R=$(TOKPAGE=admin/staff.php post $S admin/staff.php -d action=create -d full_name=Evil -d username=evil.one -d 'permissions[]=orders')
check "staff cannot create staff by POST" "$(q "SELECT COUNT(*) FROM users WHERE username='evil.one'")" "0"
R=$(TOKPAGE=admin/categories.php post $S admin/categories.php -d action=save -d name='Staff Category' -d description=Test)
check "staff adds a category" "$(q "SELECT COUNT(*) FROM categories WHERE name='Staff Category'")" "1"
check "staff cannot check out as a customer" "$(code $S checkout.php)" "303"

echo "== Super admin edits, resets, deactivates"
TOKPAGE=admin/staff.php?edit=$SID post $A admin/staff.php -d action=update -d id=$SID -d full_name='Sahan Fernando' -d email=sahan@example.com -d 'permissions[]=products' -d 'permissions[]=orders' >/dev/null
check "permissions changed to products, orders" "$(q "SELECT permissions||'|'||email FROM users WHERE id=$SID")" "products,orders|sahan@example.com"
check "staff now opens orders, not categories or inquiries" "$(code $S admin/orders.php)|$(code $S admin/categories.php)|$(code $S admin/inquiries.php)" "200|403|403"
check "edit form for a super admin id -> 404" "$(code $A "admin/staff.php?edit=1")" "404"
R=$(postp $A admin/staff.php -d action=reset_password -d id=$SID)
PW2=$(echo "$R" | grep -o 'temp-password__value">[^<]*' | sed -n 2p | sed 's/.*">//')
check "reset shows a new 12-character password" "$(echo "$R" | grep -c 'New password generated')|${#PW2}|$(q "SELECT must_change_password FROM users WHERE id=$SID")" "1|12|1"
check "staff with must-change is sent to password page" "$(redir $S admin/orders.php | grep -c 'admin/password.php')" "1"
TOKPAGE=login.php post $S2 login.php -d email=sahan.staff -d password=StaffPass2026 >/dev/null
check "old password no longer works" "$(code $S2 admin/)" "303"
TOKPAGE=admin/staff.php post $A admin/staff.php -d action=deactivate -d id=$SID >/dev/null
check "deactivated: is_active 0, logged out" "$(q "SELECT is_active FROM users WHERE id=$SID")|$(code $S admin/password.php)" "0|303"
rm -f $S2; TOKPAGE=login.php post $S2 login.php -d email=sahan.staff -d password="$PW2" >/dev/null
check "deactivated staff cannot log in" "$(code $S2 admin/)" "303"
TOKPAGE=admin/staff.php post $A admin/staff.php -d action=activate -d id=$SID >/dev/null
check "activated again" "$(q "SELECT is_active FROM users WHERE id=$SID")" "1"
check "activity log: update, reset, deactivate, activate" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action IN ('staff.update','staff.password_reset','staff.deactivate','staff.activate')")" "4"
check "customer cannot log in with a username" "$(TOKPAGE=register.php post $C register.php -d full_name='Nimal Perera' -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null; sql "UPDATE users SET email_verified_at=datetime('now') WHERE email='nimal@example.com'"; rm -f $C; TOKPAGE=login.php post $C login.php -d email=nimal -d password=secret123 >/dev/null; code $C account/)" "303"
TOKPAGE=login.php post $C login.php -d email=nimal@example.com -d password=secret123 >/dev/null
check "customer gets 403 on admin pages" "$(code $C admin/inquiries.php)|$(code $C admin/staff.php)" "403|403"

echo "== Contact form (inquiries)"
check "contact page has the form and a hidden trap field" "$(page $G contact.php | grep -c 'Send us a message')|$(page $G contact.php | grep -c 'name="website"')" "1|1"
check "logged-in customer: name and e-mail filled in" "$(page $C contact.php | grep -c 'value="nimal@example.com"')" "1"
check "?order= prefills the order and subject" "$(page $C 'contact.php?order=GA261018-KLH46R' | grep -c 'value="GA261018-KLH46R"')" "1"
check "POST without CSRF -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -d name=X "$B/contact.php")" "419"
R=$(TOKPAGE=contact.php postp $G contact.php -d name=K -d email=bad -d subject=nope -d order_number=123 -d message=short)
check "validation: 5 errors, nothing saved" "$(echo "$R" | grep -o 'class="field-error"' | wc -l | tr -d ' ')|$(q "SELECT COUNT(*) FROM customer_inquiries")" "5|0"
check "typed values kept after an error" "$(echo "$R" | grep -c 'value="bad"')" "1"
R=$(TOKPAGE=contact.php post $G contact.php -d name=Bot -d email=bot@example.com -d subject=other -d message='Buy cheap things now' -d website=http://spam.example)
check "spam trap: looks sent, nothing saved" "$R|$(q "SELECT COUNT(*) FROM customer_inquiries")" "303 $B/contact.php|0"
R=$(TOKPAGE=contact.php post $G contact.php -d name='Kamala Guest' -d email=Kamala@Example.com -d phone=0711111111 -d subject=product --data-urlencode $'message=Do you deliver <b>roses</b> to Galle?\r\nThanks')
check "guest message saved -> back to contact" "$R" "303 $B/contact.php"
check "row: guest, lower-case e-mail, status new" "$(q "SELECT IFNULL(user_id,'NULL')||'|'||email||'|'||subject||'|'||status FROM customer_inquiries")" "NULL|kamala@example.com|product|new"
check "success message shows reference INQ-0001" "$(page $G contact.php | grep -c 'reference INQ-0001')" "1"
TOKPAGE=contact.php post $C contact.php -d name='Nimal Perera' -d email=nimal@example.com -d subject=order -d order_number=ga261018-klh46r -d message='Where is my order please?' >/dev/null
check "customer message linked to the account, order upper-case" "$(q "SELECT (user_id IS NOT NULL)||'|'||order_number FROM customer_inquiries WHERE id=2")" "1|GA261018-KLH46R"
for i in 1 2 3; do TOKPAGE=contact.php post $G2 contact.php -d name=Flood -d email=flood@example.com -d subject=other -d message="Message number $i here" >/dev/null; done
check "5 messages in an hour from one IP" "$(q "SELECT COUNT(*) FROM customer_inquiries")" "5"
R=$(TOKPAGE=contact.php postp $G2 contact.php -d name=Flood -d email=other@example.com -d subject=other -d message='Message number six here')
check "6th message within the hour refused" "$(echo "$R" | grep -c 'several messages in the last hour')|$(q "SELECT COUNT(*) FROM customer_inquiries")" "1|5"

echo "== Customer: My inquiries"
MI=$(page $C account/inquiries.php)
check "account menu has My inquiries" "$(echo "$MI" | grep -A2 'href="/giftaura/account/inquiries.php"' | grep -c '<span>My inquiries</span>')" "1"
check "customer sees only own message" "$(echo "$MI" | grep -c 'Where is my order')|$(echo "$MI" | grep -c 'Galle')|$(echo "$MI" | grep -c 'INQ-0002 ·')" "1|0|1"
check "not replied yet note" "$(echo "$MI" | grep -c 'We have not replied yet')" "1"
check "guest -> login" "$(redir $G account/inquiries.php | grep -c 'login.php')" "1"

echo "== Admin and staff answer"
TOKPAGE=admin/staff.php post $A admin/staff.php -d action=update -d id=$SID -d full_name='Sahan Fernando' -d email=sahan@example.com -d 'permissions[]=inquiries' >/dev/null
rm -f $S; TOKPAGE=login.php post $S login.php -d email=sahan.staff -d password="$PW2" >/dev/null
TOKPAGE=admin/password.php post $S admin/password.php -d current_password="$PW2" -d new_password=StaffPass2027 -d new_password_confirm=StaffPass2027 >/dev/null
check "dashboard card: New inquiries" "$(page $S admin/ | grep -c 'stat-card__label">New inquiries')" "1"
check "menu badge 5" "$(page $S admin/ | grep -c 'title="New inquiries">5<')" "1"
L=$(page $S admin/inquiries.php)
check "list: 5 inquiries, message text escaped" "$(echo "$L" | grep -c 'admin-panel__title">5 inquiries')|$(echo "$L" | grep -c '&lt;b&gt;roses')|$(echo "$L" | grep -c '<b>roses')" "1|1|0"
check "filter by subject product" "$(page $S 'admin/inquiries.php?subject=product' | grep -c 'admin-panel__title">1 inquiry<')" "1"
check "search by order reference" "$(page $S 'admin/inquiries.php?q=KLH46R' | grep -c 'admin-panel__title">1 inquiry<')" "1"
check "unknown inquiry -> 404" "$(code $S 'admin/inquiry.php?id=999')" "404"
DP=$(page $S 'admin/inquiry.php?id=2')
check "detail: no order link without orders permission, account name not a link" "$(echo "$DP" | grep -c 'admin/order.php')|$(echo "$DP" | grep -c 'admin/customer.php')" "0|0"
R=$(TOKPAGE='admin/inquiry.php?id=2' postp $S 'admin/inquiry.php?id=2' -d action=reply -d message=' ' -d status=resolved)
check "empty reply refused" "$(echo "$R" | grep -c 'Write a reply')|$(q "SELECT COUNT(*) FROM inquiry_replies")" "1|0"
rm -f ../storage/mail/*inquiry* 2>/dev/null
R=$(TOKPAGE='admin/inquiry.php?id=2' post $S 'admin/inquiry.php?id=2' -d action=reply --data-urlencode $'message=Hello Nimal,\nYour order leaves today. <script>x</script>' -d status=in_progress)
check "reply saved -> back to the inquiry" "$R" "303 $B/admin/inquiry.php?id=2"
check "reply row by staff; status in progress" "$(q "SELECT admin_id FROM inquiry_replies")|$(q "SELECT status||'|'||(last_reply_at IS NOT NULL) FROM customer_inquiries WHERE id=2")" "$SID|in_progress|1"
check "e-mail log: inquiry_reply to the customer" "$(q "SELECT template||'|'||recipient FROM email_log WHERE template='inquiry_reply'")" "inquiry_reply|nimal@example.com"
MAIL=$(ls -t ../storage/mail/*.html | head -1)
check "e-mail: reference, reply escaped, link to My inquiries" "$(grep -c 'INQ-0002' "$MAIL" | awk '{print ($1>0)}')|$(grep -c '<script>x' "$MAIL")|$(grep -c 'account/inquiries.php' "$MAIL" | awk '{print ($1>0)}')" "1|0|1"
check "detail shows reply with (staff)" "$(page $A 'admin/inquiry.php?id=2' | grep -c 'Sahan Fernando</strong> (staff) replied')" "1"
MI=$(page $C account/inquiries.php)
check "customer sees the reply, escaped" "$(echo "$MI" | grep -c 'GiftAura</strong> replied')|$(echo "$MI" | grep -c '&lt;script&gt;')|$(echo "$MI" | grep -c 'In progress')" "1|1|1"
TOKPAGE='admin/inquiry.php?id=1' post $A 'admin/inquiry.php?id=1' -d action=reply -d message='Yes, we deliver to Galle.' -d status=resolved >/dev/null
MAIL=$(ls -t ../storage/mail/*.html | head -1)
check "guest reply: resolved, e-mail links to Contact page" "$(q "SELECT status FROM customer_inquiries WHERE id=1")|$(grep -c 'contact.php' "$MAIL" | awk '{print ($1>0)}')|$(grep -c 'account/inquiries.php' "$MAIL")" "resolved|1|0"
TOKPAGE='admin/inquiry.php?id=3' post $S 'admin/inquiry.php?id=3' -d action=status -d status=resolved >/dev/null
TOKPAGE='admin/inquiry.php?id=4' post $S 'admin/inquiry.php?id=4' -d action=status -d status=bogus >/dev/null
check "status change without reply; unknown status ignored" "$(q "SELECT status FROM customer_inquiries WHERE id=3")|$(q "SELECT status FROM customer_inquiries WHERE id=4")" "resolved|new"
check "activity log: replies and status" "$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action='inquiry.reply'")|$(q "SELECT COUNT(*) FROM admin_activity_log WHERE action='inquiry.status'")" "2|1"
check "filter: new only" "$(page $S 'admin/inquiries.php?status=new' | grep -c 'admin-panel__title">2 inquiries')" "1"
check "order history names who changed it" "$(grep -c 'changed_by_name' ../app/services/OrderService.php | awk '{print ($1>0)}')" "1"
check "activity log page is super-admin only" "$(code $A admin/activity.php)|$(code $S admin/activity.php)" "200|403"
check "no errors in the log" "$(grep -c 'ERROR' ../storage/logs/*.log 2>/dev/null | awk -F: '{s+=$2} END {print s+0}')" "0"

echo ""
echo "RESULT: $pass passed, $fail failed"
