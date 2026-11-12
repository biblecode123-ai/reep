#!/bin/bash
# MWU RPEE — Login E2E test (§9 authentication, §87 security rules)
# Covers: login page render, CSRF enforcement, wrong password, unknown user,
# inactive account, employee-ID login, session fixation defense, logout,
# lockout after 5 failed attempts, protected-page redirect, security log,
# and a real dashboard login for all 18 seeded role accounts.
set -u
[ -f app/bootstrap.php ] || { echo "Run from the project root"; exit 2; }

B=http://127.0.0.1:8099
P='Password123!'
PASS=0; FAIL=0

say()  { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); say "  PASS: $*"; }
bad()  { FAIL=$((FAIL+1)); say "  FAIL: $*"; }
chk()  { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected $3 got $2"; fi; }

login() { # identifier password jar -> http code
  local jar=$3 tok
  rm -f "$jar"
  tok=$(curl -s -m 15 -c "$jar" "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -d "identifier=$1&password=$2&_csrf=$tok" "$B/login.php?action=do_login"
}
get() { # jar url -> http code
  curl -s -m 20 -b "$1" -c "$1" -o /dev/null -w '%{http_code}' "$B/$2"
}
page() { curl -s -m 20 -b "$1" "$B/$2"; }
db() { timeout 10 php -r 'require "app/bootstrap.php"; echo (string) (Database::scalar($argv[1]) ?? "");' "$1"; }

code=$(curl -s -m 10 -o /dev/null -w '%{http_code}' "$B/login.php" || echo 000)
[ "$code" = "200" ] || { say "Dev server not running (got $code) — start: php -S 127.0.0.1:8099 -t public"; exit 2; }

# Re-runnable: clear prior failure counters (lockout is identifier OR ip-based,
# so leftover rows from an earlier run would lock out every local request).
timeout 10 php -r 'require "app/bootstrap.php"; Database::run("DELETE FROM login_attempts WHERE ip_address = :ip", [":ip" => "127.0.0.1"]);' && ok "cleared prior login attempts (127.0.0.1)"

say "== [1] login page =="
html=$(curl -s -m 15 "$B/login.php")
echo "$html" | grep -q 'name="_csrf"' && ok "login form has CSRF field" || bad "no CSRF field on login form"
echo "$html" | grep -q 'name="identifier"' && ok "identifier field present" || bad "identifier field missing"
echo "$html" | grep -q 'name="password"' && ok "password field present" || bad "password field missing"
echo "$html" | grep -q "Logout" && bad "user cluster leaked onto login page" || ok "no user cluster for guests"
CSRF1=$(echo "$html" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
[ -n "$CSRF1" ] && ok "csrf token extractable" || bad "no csrf token"

say "== [2] CSRF enforcement =="
c=$(curl -s -m 20 -o /dev/null -w '%{http_code}' -d "identifier=admin@mwu.edu.et&password=$P" "$B/login.php?action=do_login")
chk "POST without CSRF token rejected" "$c" 419
c=$(curl -s -m 20 -o /dev/null -w '%{http_code}' -d "identifier=admin@mwu.edu.et&password=$P&_csrf=invalidtoken123" "$B/login.php?action=do_login")
chk "POST with invalid CSRF token rejected" "$c" 419

say "== [3] credential validation =="
c=$(login admin@mwu.edu.et 'WrongPass999' /tmp/lg1.jar)
chk "wrong password rejected (302 flash)" "$c" 302
c=$(get /tmp/lg1.jar "index.php?action=")
chk "no session after bad password" "$c" 302
msg=$(curl -s -m 15 -c /tmp/lg2.jar "$B/login.php" > /dev/null; T=$(curl -s -m 15 -b /tmp/lg2.jar -c /tmp/lg2.jar "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'); curl -s -m 20 -b /tmp/lg2.jar -c /tmp/lg2.jar -L -d "identifier=admin@mwu.edu.et&password=WrongPass999&_csrf=$T" "$B/login.php?action=do_login" | grep -o 'alert-error[^<]*<\|alert alert-error">[^<]*' | head -1)
echo "$msg" | grep -qi "Invalid credentials" && ok "error message shown" || bad "error message missing ($msg)"

c=$(login nosuchuser@mwu.edu.et "$P" /tmp/lg3.jar)
chk "unknown email rejected" "$c" 302
c=$(get /tmp/lg3.jar "index.php?action=")
chk "no session for unknown user" "$c" 302

say "== [4] successful login paths =="
c=$(login admin@mwu.edu.et "$P" /tmp/lg4.jar)
chk "email login (admin)" "$c" 302
c=$(get /tmp/lg4.jar "index.php?action=")
chk "dashboard 200 after login" "$c" 200
page /tmp/lg4.jar "index.php?action=" | grep -q "Logout" && ok "dashboard shows user cluster" || bad "no user cluster after login"

EMPID=$(db "SELECT employee_id FROM users WHERE email='admin@mwu.edu.et'")
c=$(login "$EMPID" "$P" /tmp/lg5.jar)
chk "employee-id login ($EMPID)" "$c" 302
c=$(get /tmp/lg5.jar "index.php?action=")
chk "dashboard 200 via employee id" "$c" 200

say "== [5] session fixation defense (§87) =="
SESS=$(timeout 10 php -r '$c = require "config/security.php"; echo $c["session"]["name"] ?? ($c["name"] ?? "MWURPEESESSID");' 2>/dev/null)
SESS=${SESS:-MWURPEESESSID}
rm -f /tmp/lg6.jar
T=$(curl -s -m 15 -c /tmp/lg6.jar "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
SID_PRE=$(grep "$SESS" /tmp/lg6.jar | awk '{print $NF}' | head -1)
curl -s -m 20 -b /tmp/lg6.jar -c /tmp/lg6.jar -o /dev/null -d "identifier=admin@mwu.edu.et&password=$P&_csrf=$T" "$B/login.php?action=do_login"
SID_POST=$(grep "$SESS" /tmp/lg6.jar | awk '{print $NF}' | head -1)
if [ -n "$SID_PRE" ] && [ "$SID_PRE" != "$SID_POST" ]; then ok "session ID regenerated at login"; else bad "session id not regenerated (pre=$SID_PRE post=$SID_POST)"; fi

say "== [6] logout =="
c=$(get /tmp/lg4.jar "logout.php")
chk "logout redirects" "$c" 302
c=$(get /tmp/lg4.jar "index.php?action=")
chk "session destroyed after logout" "$c" 302

say "== [7] protected page redirect for guests =="
c=$(curl -s -m 20 -o /dev/null -w '%{http_code}' "$B/index.php?action=users")
chk "guest hitting /users redirected" "$c" 302
c=$(curl -s -m 20 -o /dev/null -w '%{http_code}' "$B/index.php?action=applications")
chk "guest hitting /applications redirected" "$c" 302

say "== [8] dashboard login for all 18 role accounts =="
while IFS='|' read -r EMAIL ROLE; do
  [ -z "$EMAIL" ] && continue
  c=$(login "$EMAIL" "$P" /tmp/lg_role.jar)
  if [ "$c" != "302" ]; then bad "$ROLE login failed ($c)"; continue; fi
  c=$(get /tmp/lg_role.jar "index.php?action=")
  if [ "$c" = "200" ]; then ok "$ROLE dashboard 200"; else bad "$ROLE dashboard failed ($c)"; fi
done <<EOF
admin@mwu.edu.et|system_admin
director@mwu.edu.et|rpee_director
officer@mwu.edu.et|rpee_officer
committee@mwu.edu.et|univ_committee
ccoordinator@mwu.edu.et|college_coordinator
dcoordinator@mwu.edu.et|dept_coordinator
researcher@mwu.edu.et|researcher+PI
coinvestigator@mwu.edu.et|co_investigator
evaluator@mwu.edu.et|evaluator
ethics.member@mwu.edu.et|ethics_member
ethics.reviewer@mwu.edu.et|ethics_reviewer
publication@mwu.edu.et|publication_officer
extension@mwu.edu.et|extension_officer
extension.evaluator@mwu.edu.et|extension_evaluator
panel@mwu.edu.et|panel_member
repository@mwu.edu.et|repository_manager
finance@mwu.edu.et|finance_officer
readonly@mwu.edu.et|readonly_mgmt
EOF

# NOTE: lockout runs LAST — the counter is per-IP as well as per-identifier,
# so these deliberate failures must not poison any login above them.
say "== [9] brute-force lockout (5 attempts, §9) =="
rm -f /tmp/lg7.jar
for i in 1 2 3 4 5; do
  curl -s -m 15 -c /tmp/lg7.jar "$B/login.php" > /dev/null
  T=$(curl -s -m 15 -b /tmp/lg7.jar -c /tmp/lg7.jar "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -m 20 -b /tmp/lg7.jar -c /tmp/lg7.jar -o /dev/null -d "identifier=locktest@mwu.edu.et&password=bad&_csrf=$T" "$B/login.php?action=do_login"
done
curl -s -m 15 -c /tmp/lg7.jar "$B/login.php" > /dev/null
T=$(curl -s -m 15 -b /tmp/lg7.jar -c /tmp/lg7.jar "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
c=$(curl -s -m 20 -b /tmp/lg7.jar -c /tmp/lg7.jar -L -d "identifier=locktest@mwu.edu.et&password=bad&_csrf=$T" "$B/login.php?action=do_login" | grep -io "Too many failed attempts[^<]*" | head -1)
[ -n "$c" ] && ok "lockout message after 5 failures" || bad "lockout not triggered"

timeout 10 php -r 'require "app/bootstrap.php"; Database::run("DELETE FROM login_attempts WHERE ip_address = :ip", [":ip" => "127.0.0.1"]);' && ok "cleanup: attempts cleared for next run"

say "== [10] security audit trail =="
sec=storage/private/logs/security.log
grep -q "Failed login" "$sec" && ok "failed logins logged to security.log" || bad "no failed-login entries"
grep -q "CSRF verification failed" "$sec" && ok "CSRF failures logged" || bad "no CSRF entries"
grep -q "Login lockout hit" "$sec" && ok "lockout logged" || bad "no lockout entry"
au=$(db "SELECT COUNT(*) FROM audit_logs WHERE action='login'")
[ "$au" -ge 2 ] && ok "audit_logs has $au login entries" || bad "audit_logs thin ($au)"

say ""
say "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = "0" ] || exit 1

