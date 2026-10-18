#!/bin/bash
# GiftAura - e-mail feature tests: verification, password reset, order e-mails,
# e-mail log, and the SMTP sender against a local fake server (fake_smtp.php).
B=http://127.0.0.1:8095/giftaura
q(){ php -r '$p=new PDO("sqlite:test.db"); foreach($p->query($argv[1])->fetchAll(PDO::FETCH_NUM) as $r) echo implode("|",$r),"\n";' "$1"; }
tok(){ curl -s -c $1 -b $1 "$B/$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2; shift 2; local T=$(tok $jar "${TOKPAGE:-$path}"); curl -s -c $jar -b $jar -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_csrf=$T" "$@" "$B/$path"; }
page(){ curl -s -c $1 -b $1 "$B/$2"; }
mtoken(){ local f=$(ls -t ../storage/mail/*.html 2>/dev/null | xargs -r grep -l -- "$1" | xargs -r grep -l "$2" | head -1); [ -n "$f" ] && grep -o "$2?token=[a-f0-9]*" "$f" | head -1 | sed "s/.*token=//"; }
newest(){ ls -t ../storage/mail/*.html | head -1; }
mailcfg(){ php -r '$f="../config/config.php"; $c=require $f; $c["mail"]=array_merge($c["mail"],json_decode($argv[1],true)); file_put_contents($f,"<?php\nreturn ".var_export($c,true).";\n");' "$1"; }
lastlog(){ q "SELECT template||'|'||status||'|'||transport FROM email_log ORDER BY id DESC LIMIT 1"; }
order(){ # place one COD order for jar $1 with saved address; prints order number
  TOKPAGE=product.php?slug=sample-red-rose-bouquet post $1 cart.php -d action=add -d product_id=1 -d quantity=1 >/dev/null
  local FP=$(page $1 checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
  TOKPAGE=checkout.php post $1 checkout.php --data-urlencode "customer_name=${2:-Nimal Perera}" -d customer_phone=0771234567 -d address_id=$AID -d payment_method=cod -d cart_fingerprint=$FP | grep -o 'GA[0-9]\{6\}-[A-Z0-9]\{6\}'
}
pass=0; fail=0
check(){ if [ "$2" == "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1: expected [$3] got [$2]"; fi; }
rm -f *.jar smtp_inbox.txt
mailcfg '{"transport":"file"}'
C=cust.jar; A=admin.jar; G=guest.jar

echo "== Registration sends a verification link"
TOKPAGE=register.php post $C register.php -d full_name=Nimal%20Perera -d email=nimal@example.com -d phone=0771234567 -d password=secret123 -d password_confirm=secret123 >/dev/null
check "e-mail log: verify_email saved" "$(lastlog)" "verify_email|saved|file"
T1=$(mtoken nimal@example.com verify-email.php)
check "link secret is 64 hex characters" "${#T1}" "64"
check "database does not store the secret" "$(q "SELECT COUNT(*) FROM user_tokens WHERE token_hash='$T1'")" "0"
check "database stores its SHA-256 hash" "$(q "SELECT COUNT(*) FROM user_tokens WHERE token_hash='$(php -r 'echo hash("sha256",$argv[1]);' $T1)'")" "1"
check "file shows TEST MODE header" "$(grep -c 'TEST MODE - this e-mail was saved, not sent' "$(newest)")" "1"
check "banner shown to unverified customer" "$(page $C account/ | grep -c 'verify-banner')" "1"

echo "== Unverified customers cannot order"
TOKPAGE=product.php?slug=sample-red-rose-bouquet post $C cart.php -d action=add -d product_id=1 -d quantity=1 >/dev/null
check "checkout page -> cart" "$(curl -s -o /dev/null -w '%{redirect_url}' -b $C $B/checkout.php)" "$B/cart.php"
TOKPAGE=cart.php post $C checkout.php -d customer_name=Nimal -d customer_phone=0771234567 -d address_id=new -d recipient_name=K -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d payment_method=cod >/dev/null
check "direct POST to checkout places no order" "$(q "SELECT COUNT(*) FROM orders")" "0"

echo "== Verification link"
check "GET shows a confirm button" "$(page $C "verify-email.php?token=$T1" | grep -c 'Confirm my e-mail address')" "1"
check "GET alone does not use the link" "$(q "SELECT COUNT(*) FROM user_tokens WHERE used_at IS NULL AND purpose='verify_email'")" "1"
check "POST without CSRF token -> 419" "$(curl -s -o /dev/null -w '%{http_code}' -d token=$T1 $B/verify-email.php)" "419"
check "tampered link refused" "$(page $C "verify-email.php?token=${T1%?}$( [ "${T1: -1}" == a ] && echo b || echo a )" | grep -c 'This link does not work')" "1"
check "non-hex link refused" "$(page $C "verify-email.php?token=%27%20OR%201=1--" | grep -c 'This link does not work')" "1"
TOKPAGE=account/ post $C resend-verification.php >/dev/null
T2=$(mtoken nimal@example.com verify-email.php)
check "resend gives a different link" "$([ "$T1" != "$T2" ] && [ ${#T2} == 64 ] && echo yes)" "yes"
check "old link stops working after resend" "$(page $C "verify-email.php?token=$T1" | grep -c 'This link does not work')" "1"
TOKPAGE=account/ post $C resend-verification.php >/dev/null
check "third link allowed" "$(q "SELECT COUNT(*) FROM user_tokens WHERE purpose='verify_email'")" "3"
TOKPAGE=account/ post $C resend-verification.php >/dev/null
check "fourth link within an hour refused" "$(q "SELECT COUNT(*) FROM user_tokens WHERE purpose='verify_email'")" "3"
check "refusal message" "$(page $C account/ | grep -c 'several links in the last hour')" "1"
T3=$(mtoken nimal@example.com verify-email.php)
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE user_tokens SET expires_at=\"2000-01-01 00:00:00\" WHERE used_at IS NULL");'
check "expired link refused" "$(page $C "verify-email.php?token=$T3" | grep -c 'This link does not work')" "1"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("UPDATE user_tokens SET expires_at=\"2099-01-01 00:00:00\" WHERE used_at IS NULL");'
R=$(TOKPAGE="verify-email.php?token=$T3" post $C verify-email.php -d token=$T3)
check "confirm -> back to cart" "$R" "303 $B/cart.php"
check "account verified" "$(q "SELECT email_verified_at IS NOT NULL FROM users WHERE email='nimal@example.com'")" "1"
check "link cannot be used twice" "$(page $C "verify-email.php?token=$T3" | grep -c 'This link does not work')" "1"
check "banner gone" "$(page $C account/ | grep -c 'verify-banner')" "0"

echo "== Order e-mails"
php -r '$p=new PDO("sqlite:test.db"); $p->exec("DELETE FROM cart_items");'
TOKPAGE=product.php?slug=sample-red-rose-bouquet post $C cart.php -d action=add -d product_id=1 -d quantity=1 >/dev/null
FP=$(page $C checkout.php | grep -o 'name="cart_fingerprint" value="[^"]*"' | sed 's/.*value="//;s/"//')
ON=$(TOKPAGE=checkout.php post $C checkout.php --data-urlencode "customer_name=Nimal <b>Bold</b> & Co" -d customer_phone=0771234567 -d address_id=new -d recipient_name=Kamala -d phone=0711111111 -d address_line1=12%20Temple%20Road -d city=Kandy -d district=Kandy -d save_address=1 -d payment_method=cod -d cart_fingerprint=$FP | grep -o 'GA[0-9]\{6\}-[A-Z0-9]\{6\}')
check "order placed" "$(q "SELECT COUNT(*) FROM orders WHERE order_number='$ON'")" "1"
check "order confirmation logged" "$(q "SELECT template||'|'||status FROM email_log WHERE order_id=(SELECT id FROM orders WHERE order_number='$ON')")" "order_placed|saved"
F=$(newest)
check "e-mail has order number" "$(grep -q "$ON" "$F" && echo yes)" "yes"
check "e-mail has total" "$(grep -q 'Rs. 4,900.00' "$F" && echo yes)" "yes"
check "e-mail links to the order page" "$(grep -q "href=\"[^\"]*account/order.php?order=$ON\"" "$F" && echo yes)" "yes"
check "customer name is escaped" "$(grep -q '&lt;b&gt;Bold&lt;/b&gt; &amp; Co' "$F" && echo yes)|$(grep -c '<b>Bold</b>' "$F")" "yes|0"
AID=$(q "SELECT id FROM customer_addresses LIMIT 1")
TOKPAGE=login.php post $A login.php -d email=admin@example.com -d password=admin123 >/dev/null
OID=$(q "SELECT id FROM orders WHERE order_number='$ON'")
OP="admin/order.php?id=$OID"
for s in confirmed processing ready_for_delivery delivered; do TOKPAGE=$OP post $A "$OP" -d action=status -d status=$s -d note=Packed%20with%20care >/dev/null; done
TOKPAGE=$OP post $A "$OP" -d action=payment -d payment_status=paid -d note=Cash >/dev/null
check "status + payment e-mails" "$(q "SELECT group_concat(template,',') FROM (SELECT template FROM email_log WHERE order_id=$OID ORDER BY id)")" "order_placed,order_status,order_status,order_status,order_status,payment"
check "delivered e-mail subject" "$(q "SELECT subject FROM email_log WHERE order_id=$OID AND subject LIKE '%delivered%'")" "Your order has been delivered ($ON)"
check "status note included" "$(grep -c 'Packed with care' "$(ls -t ../storage/mail/*delivered*.html | head -1)")" "1"
ON2=$(order $C)
TOKPAGE="account/order.php?order=$ON2" post $C "account/order.php?order=$ON2" -d action=cancel -d reason=Changed%20my%20mind >/dev/null
check "customer cancellation e-mail" "$(lastlog)" "order_status|saved|file"
check "cancel subject" "$(q "SELECT subject FROM email_log ORDER BY id DESC LIMIT 1")" "Your order has been cancelled ($ON2)"

echo "== Forgotten password"
check "login page links to reset" "$(page $G login.php | grep -c 'forgot-password.php')" "1"
before=$(q "SELECT COUNT(*) FROM email_log")
M1=$(TOKPAGE=forgot-password.php; T=$(tok $G forgot-password.php); curl -s -c $G -b $G --data-urlencode _csrf=$T -d email=nobody@example.com $B/forgot-password.php | grep -o 'If an account exists for [^,]*' | sed 's/nobody@example.com/X/')
M2=$(T=$(tok $G forgot-password.php); curl -s -c $G -b $G --data-urlencode _csrf=$T -d email=nimal@example.com $B/forgot-password.php | grep -o 'If an account exists for [^,]*' | sed 's/nimal@example.com/X/')
check "same answer for unknown and known address" "$M1" "$M2"
T=$(tok $G forgot-password.php); curl -s -o /dev/null -c $G -b $G --data-urlencode _csrf=$T -d email=admin@example.com $B/forgot-password.php
check "only the customer got an e-mail (not unknown, not admin)" "$(( $(q "SELECT COUNT(*) FROM email_log") - before ))|$(lastlog)" "1|reset_password|saved|file"
RT=$(mtoken nimal@example.com reset-password.php)
check "reset secret 64 hex" "${#RT}" "64"
check "reset page shows form" "$(page $G "reset-password.php?token=$RT" | grep -c 'name="new_password"')" "1"
R=$(TOKPAGE="reset-password.php?token=$RT" post $G reset-password.php -d token=$RT -d new_password=short -d new_password_confirm=short)
check "weak password refused" "$R" "200 "
check "link still valid after refused attempt" "$(q "SELECT COUNT(*) FROM user_tokens WHERE purpose='reset_password' AND used_at IS NULL")" "1"
R=$(TOKPAGE="reset-password.php?token=$RT" post $G reset-password.php -d token=$RT -d new_password=newpass456 -d new_password_confirm=newpass456)
check "reset -> login page" "$R" "303 $B/login.php"
check "new password works" "$(TOKPAGE=login.php post x.jar login.php -d email=nimal@example.com -d password=newpass456)" "303 $B/account/"
check "old password no longer works" "$(TOKPAGE=login.php post y.jar login.php -d email=nimal@example.com -d password=secret123 | cut -d' ' -f1)" "200"
check "reset link cannot be reused" "$(page $G "reset-password.php?token=$RT" | grep -c 'invalid, has expired')" "1"
for i in 1 2 3; do T=$(tok $G forgot-password.php); curl -s -o /dev/null -c $G -b $G --data-urlencode _csrf=$T -d email=nobody$i@example.com $B/forgot-password.php; done
T=$(tok $G forgot-password.php)
check "6th request in 15 minutes refused" "$(curl -s -c $G -b $G --data-urlencode _csrf=$T -d email=x@example.com $B/forgot-password.php | grep -c 'Too many requests')" "1"

echo "== Admin tools"
D=d.jar
TOKPAGE=register.php post $D register.php -d full_name=Dilani%20Silva -d email=dilani@example.com -d phone=0712345678 -d password=secret123 -d password_confirm=secret123 >/dev/null
DID=$(q "SELECT id FROM users WHERE email='dilani@example.com'")
check "list shows not verified" "$(page $A admin/customers.php | grep -c 'E-mail not verified')" "1"
check "customer page shows not verified" "$(page $A "admin/customer.php?id=$DID" | grep -c 'Not verified')" "1"
TOKPAGE="admin/customer.php?id=$DID" post $A "admin/customer.php?id=$DID" -d action=mark_verified >/dev/null
check "admin marked verified" "$(q "SELECT email_verified_at IS NOT NULL FROM users WHERE id=$DID")" "1"
check "activity logged" "$(q "SELECT action FROM admin_activity_log WHERE entity_id=$DID AND action='customer.email_verified'")" "customer.email_verified"
check "e-mail log page lists the order's 6 e-mails" "$(page $A admin/emails.php | grep -o "order.php?id=$OID\">$ON<" | wc -l | tr -d ' ')" "6"
check "filter by type" "$(page $A "admin/emails.php?template=reset_password" | grep -o '>[0-9]* e-mails\?</h2>')" ">1 e-mail</h2>"
check "test-mode notice on log page" "$(page $A admin/emails.php | grep -c 'TEST MODE')" "1"
check "customer cannot open e-mail log" "$(curl -s -o /dev/null -w '%{http_code}' -b x.jar $B/admin/emails.php)" "403"

echo "== Mode off"
mailcfg '{"transport":"off"}'
ON3=$(order x.jar)
check "order placed with e-mail off" "$(q "SELECT COUNT(*) FROM orders WHERE order_number='$ON3'")" "1"
check "logged as skipped" "$(lastlog)" "order_placed|skipped|off"

echo "== SMTP (fake local server)"
(php fake_smtp.php 2525 ok > /dev/null 2>&1 &); (php fake_smtp.php 2526 reject > /dev/null 2>&1 &); sleep 1
mailcfg '{"transport":"smtp","host":"127.0.0.1","port":2525,"encryption":"none","username":"shop@example.com","password":"goodpassword","from_email":"shop@example.com","from_name":"GiftAura"}'
ON4=$(order x.jar)
check "order placed" "$(q "SELECT COUNT(*) FROM orders WHERE order_number='$ON4'")" "1"
check "logged as sent" "$(lastlog)" "order_placed|sent|smtp"
check "server accepted login" "$(grep -c 'AUTH shop@example.com accepted' smtp_inbox.txt)" "1"
check "envelope recipient" "$(grep -c 'C: RCPT TO:<nimal@example.com>' smtp_inbox.txt)" "1"
check "subject header" "$(grep -c "^Subject: =?UTF-8?B?" smtp_inbox.txt)" "1"
check "multipart text + html" "$(grep -c 'Content-Type: text/plain; charset=UTF-8\|Content-Type: text/html; charset=UTF-8' smtp_inbox.txt)" "2"
check "password not in app logs" "$(grep -c goodpassword ../storage/logs/*.log | awk -F: '{s+=$2} END {print s+0}')" "0"
mailcfg '{"port":2526}'
ON5=$(order x.jar)
check "wrong password: order still placed" "$(q "SELECT COUNT(*) FROM orders WHERE order_number='$ON5'")" "1"
check "wrong password: logged as failed" "$(lastlog)" "order_placed|failed|smtp"
check "friendly App Password hint" "$(q "SELECT error LIKE '%App Password%' FROM email_log ORDER BY id DESC LIMIT 1")" "1"
check "dashboard warns about failed e-mails" "$(page $A admin/ | grep -c 'could not be sent in the last 7 days')" "1"
mailcfg '{"port":2599}'
ON6=$(order x.jar)
check "server unreachable: order still placed" "$(q "SELECT COUNT(*) FROM orders WHERE order_number='$ON6'")|$(q "SELECT status FROM email_log ORDER BY id DESC LIMIT 1")" "1|failed"
mailcfg '{"host":"smtp.example.com","port":25}'
ON7=$(order x.jar)
check "unencrypted SMTP to the internet refused" "$(q "SELECT error LIKE 'Unencrypted SMTP%' FROM email_log ORDER BY id DESC LIMIT 1")" "1"
pkill -f "fake_smtp.php" 2>/dev/null
mailcfg '{"transport":"file","host":"smtp.gmail.com","port":465,"encryption":"ssl","username":"","password":"","from_email":""}'

echo "== Result: $pass passed, $fail failed"
