#!/bin/bash
# MWU RPEE — Admin user-creation E2E test (§10, §102)
# Verifies the system admin can create a user with their role(s) through the
# admin UI, that each new user can log in, complete the forced initial
# password change, and load their dashboard. Re-runnable: cleans its own
# prior artifacts (adminuserNN@mwu.edu.et) at the start.
set -u
[ -f app/bootstrap.php ] || { echo "Run from the project root"; exit 2; }

B=http://127.0.0.1:8099
ADMIN_PW='Password123!'
INIT_PW='InitPass123!'
DEMO_PW='Password123!'
PASS=0; FAIL=0; N=0

say()  { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); say "  PASS: $*"; }
bad()  { FAIL=$((FAIL+1)); say "  FAIL: $*"; }
chk()  { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected $3 got $2"; fi; }

# --- helpers (same conventions as scripts/e2e_lifecycle.sh) ------------------
login() { # email password jar -> echoes login http code
  local jar=$3 tok
  rm -f "$jar"
  tok=$(curl -s -m 15 -c "$jar" "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -d "identifier=$1&password=$2&_csrf=$tok" "$B/login.php?action=do_login"
}
tok() { curl -s -m 15 -b "$1" -c "$1" "$B/index.php" | grep -o 'name="csrf-token" content="[^"]*"\|name="_csrf" value="[^"]*"' | head -1 | sed 's/.*content="//;s/.*value="//;s/"//'; }
post() { # jar action data... -> http code
  local jar=$1 action=$2; shift 2
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' "$B/index.php?action=$action" "$@"
}
get() { # jar query -> http code
  curl -s -m 20 -b "$1" -o /dev/null -w '%{http_code}' "$B/index.php?$2"
}
body() { curl -s -m 20 -b "$1" "$B/index.php?$2"; }
db() { timeout 10 php -r 'require "app/bootstrap.php"; echo (string) (Database::scalar($argv[1]) ?? "");' "$1"; }

# server up?
code=$(curl -s -m 10 -o /dev/null -w '%{http_code}' "$B/login.php" || echo 000)
[ "$code" = "200" ] || { say "Dev server not running (got $code) — start: php -S 127.0.0.1:8099 -t public"; exit 2; }

# ---------- cleanup artifacts of previous runs (re-runnable) ----------
php -r 'require "app/bootstrap.php";
  $n = 0;
  for ($i = 1; $i <= 18; $i++) {
    $email = sprintf("adminuser%02d@mwu.edu.et", $i);
    $uid = Database::scalar("SELECT id FROM users WHERE email=?", [$email]);
    if (!$uid) continue;
    Database::run("DELETE FROM notifications WHERE user_id=?", [$uid]);
    Database::run("DELETE FROM audit_logs WHERE user_id=?", [$uid]);
    Database::run("DELETE FROM users WHERE id=?", [$uid]);
    $n++;
  }
  echo "cleaned $n prior test users\n";' || { echo "CLEANUP FAILED — aborting"; exit 2; }

say "== [0] admin login =="
c=$(login admin@mwu.edu.et "$ADMIN_PW" /tmp/au_admin.jar)
chk "admin login" "$c" 302
T=$(tok /tmp/au_admin.jar)
[ -n "$T" ] && ok "csrf token fetched" || { bad "no csrf token"; exit 2; }

say "== [1] admin creates one user per role through the admin UI =="
while IFS='|' read -r EMAIL NAME ROLESLUG COLLEGE DEPT DASH; do
  [ -z "$EMAIL" ] && continue
  N=$((N+1))
  say "-- [$N] $ROLESLUG ($EMAIL)"
  CID=$(db "SELECT id FROM colleges WHERE code='$COLLEGE'")
  DID=$(db "SELECT id FROM departments WHERE code='$DEPT'")
  RID=$(db "SELECT id FROM roles WHERE name='$ROLESLUG'")
  [ -n "$CID" ] && [ -n "$DID" ] && [ -n "$RID" ] || { bad "org/role lookup failed ($COLLEGE/$DEPT/$ROLESLUG)"; continue; }

  T=$(tok /tmp/au_admin.jar)
  c=$(post /tmp/au_admin.jar user_save \
    -d "_csrf=$T" -d "id=0" \
    -d "full_name=$NAME" -d "email=$EMAIL" \
    -d "employee_id=TEST-AU-$N" -d "phone=+2519000000$N" \
    -d "position=Test $ROLESLUG" \
    -d "college_id=$CID" -d "department_id=$DID" -d "status=active" \
    -d "password=$INIT_PW" -d "roles%5B%5D=$RID")
  chk "user_save" "$c" 302

  UID2=$(db "SELECT id FROM users WHERE email='$EMAIL'")
  [ -n "$UID2" ] && ok "user row id=$UID2" || { bad "user row missing"; continue; }

  pv=$(php -r 'require "app/bootstrap.php"; $h=Database::scalar("SELECT password_hash FROM users WHERE email=?",[$argv[1]]); echo password_verify($argv[2],(string)$h)?"yes":"no";' "$EMAIL" "$INIT_PW")
  chk "initial password hash verifies" "$pv" "yes"

  rr=$(php -r 'require "app/bootstrap.php"; $r=\App\Models\UserModel::roleNamesFor((int)Database::scalar("SELECT id FROM users WHERE email=?",[$argv[1]])); echo (count($r)===1 && $r[0]===$argv[2])?"yes":"no";' "$EMAIL" "$ROLESLUG")
  chk "has exactly role $ROLESLUG" "$rr" "yes"

  ujar=/tmp/au_user.jar
  c=$(login "$EMAIL" "$INIT_PW" "$ujar")
  chk "login with initial password" "$c" 302
  c=$(get "$ujar" "action=")
  chk "dashboard 200 (must-change gate)" "$c" 200
  c=$(get "$ujar" "action=users")
  chk "admin pages blocked pre-change" "$c" 302

  T2=$(tok "$ujar")
  c=$(post "$ujar" profile_password -d "_csrf=$T2" \
    -d "current_password=$INIT_PW" -d "new_password=$DEMO_PW" -d "confirm_password=$DEMO_PW")
  chk "forced password change" "$c" 302

  c=$(login "$EMAIL" "$DEMO_PW" "$ujar")
  chk "login with new password" "$c" 302
  c=$(get "$ujar" "action=")
  chk "dashboard 200 after change" "$c" 200
  if [ "$DASH" != "-" ]; then
    if body "$ujar" "action=" | grep -q "$DASH"; then ok "dashboard block '$DASH'"; else bad "dashboard block '$DASH' missing"; fi
  fi
done <<EOF
adminuser01@mwu.edu.et|Test Abebe Negash|system_admin|RPEE|RM|-
adminuser02@mwu.edu.et|Test Sara Tesfaye|rpee_director|RPEE|RM|-
adminuser03@mwu.edu.et|Test Mulu Alemu|rpee_officer|RPEE|RM|-
adminuser04@mwu.edu.et|Test Dawit Girma|univ_committee|CNCS|MATH|-
adminuser05@mwu.edu.et|Test Hana Solomon|college_coordinator|CNCS|MATH|-
adminuser06@mwu.edu.et|Test Yosef Bekele|dept_coordinator|CC|CS|-
adminuser07@mwu.edu.et|Test Tigist Ayele|researcher|CC|CS|-
adminuser08@mwu.edu.et|Test Meron Girma|co_investigator|CC|CS|-
adminuser09@mwu.edu.et|Test Yonas Girma|evaluator|CANR|ANSC|-
adminuser10@mwu.edu.et|Test Marta Abebe|ethics_member|CSSH|SOC|-
adminuser11@mwu.edu.et|Test Liya Haile|ethics_reviewer|CSSH|SOC|-
adminuser12@mwu.edu.et|Test Dawit Kebede|publication_officer|RPEE|RM|Publications overview
adminuser13@mwu.edu.et|Test Sifan Ahmed|extension_officer|RPEE|RM|Extension overview
adminuser14@mwu.edu.et|Test Chala Degaga|extension_evaluator|CANR|PLSC|Extension overview
adminuser15@mwu.edu.et|Test Nardos Fikru|panel_member|COE|CIVL|My panel assignments
adminuser16@mwu.edu.et|Test Yonatan Tesfaye|repository_manager|RPEE|RM|Repository overview
adminuser17@mwu.edu.et|Test Daniel Mekonnen|finance_officer|RPEE|RM|-
adminuser18@mwu.edu.et|Test Selam Worku|readonly_mgmt|RPEE|RM|-
EOF

say "== [2] negative checks =="
# duplicate email is rejected, no second row
T=$(tok /tmp/au_admin.jar)
RID=$(db "SELECT id FROM roles WHERE name='rpee_officer'")
CID=$(db "SELECT id FROM colleges WHERE code='RPEE'")
c=$(post /tmp/au_admin.jar user_save -d "_csrf=$T" -d "id=0" \
  -d "full_name=Duplicate Test" -d "email=adminuser01@mwu.edu.et" \
  -d "status=active" -d "password=InitPass123!" -d "roles%5B%5D=$RID" \
  -d "college_id=$CID")
rows=$(db "SELECT COUNT(*) FROM users WHERE email='adminuser01@mwu.edu.et'")
chk "duplicate email rejected (rows=1)" "$rows" "1"

# co-investigator cannot create applications (research.create denied)
c=$(login adminuser08@mwu.edu.et "$DEMO_PW" /tmp/au_ci.jar)
T3=$(tok /tmp/au_ci.jar)
c=$(post /tmp/au_ci.jar application_save -d "_csrf=$T3" -d "call_id=1" -d "title=nope" \
  -d "abstract=x" -d "budget=1" -d "duration=1" -d "start_date=2026-01-01" -d "end_date=2026-02-01")
chk "co_investigator application_save denied" "$c" 403

# evaluator cannot manage calls (research.manage denied)
c=$(login adminuser09@mwu.edu.et "$DEMO_PW" /tmp/au_ev.jar)
T4=$(tok /tmp/au_ev.jar)
c=$(post /tmp/au_ev.jar call_save -d "_csrf=$T4" -d "title=nope" -d "reference_number=NOPE/1")
chk "evaluator call_save denied" "$c" 403

say "== [3] admin listing + audit =="
c=$(get /tmp/au_admin.jar "action=users")
chk "users list page" "$c" 200
if body /tmp/au_admin.jar "action=users&q=adminuser18" | grep -q "adminuser18@mwu.edu.et"; then ok "new users listed (search)"; else bad "new users not listed"; fi
n18=$(body /tmp/au_admin.jar "action=users&q=adminuser" | grep -c "adminuser[0-9]*@mwu.edu.et")
[ "${n18:-0}" -ge 18 ] && ok "all 18 test users listed ($n18 rows)" || bad "listing incomplete ($n18/18)"
created=$(db "SELECT COUNT(*) FROM audit_logs WHERE action='user.created'")
[ "$created" -ge 18 ] && ok "audit trail: $created user.created entries" || bad "audit trail thin ($created)"

say ""
say "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = "0" ] || exit 1
