#!/bin/bash
# My Evaluations — HTTP smoke tests (spec §11 scenarios 1–13 subset).
# Runs against a THROWAWAY COPY of the database; the real DB is never touched.
#
# Usage (from project root):  bash tests/smoke_my_evaluations.sh
set -u
[ -f app/bootstrap.php ] || { echo "Run from the project root"; exit 2; }

B=http://127.0.0.1:8099
DB_COPY=database/mwu_smoketest.sqlite
# Windows-style absolute path for DB_NAME (Git Bash $PWD is /c/... which PHP misreads)
DB_WIN=$(cygpath -m "$PWD/$DB_COPY" 2>/dev/null || echo "$PWD/$DB_COPY")
PASS=0; FAIL=0

ok()  { PASS=$((PASS+1)); echo "  PASS: $*"; }
bad() { FAIL=$((FAIL+1)); echo "  FAIL: $*"; }
chk() { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected [$3] got [$2]"; fi; }

step() { echo; echo "== $* =="; }

# ---- 0. Throwaway DB copy + known passwords -------------------------------
rm -f "$DB_COPY"
cp database/mwu_rpee.sqlite "$DB_COPY"

# Set known test passwords ONLY on the copy (DB_NAME env points config at it).
DB_NAME="$DB_WIN" php -r '
require "app/bootstrap.php";
$h = password_hash("Password123!", PASSWORD_BCRYPT, ["cost" => 10]);
foreach (["coordinator@mwu.edu.et", "bona@gmail.com", "chala@gmail.com", "marara@gmail.com"] as $e) {
    App\Core\Database::run("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE email = ?", [$h, $e]);
}
// The seeded call has no criteria; add a standard rubric so evaluation works.
$callId = (int) App\Core\Database::scalar("SELECT call_id FROM research_applications ORDER BY id LIMIT 1");
$n = (int) App\Core\Database::scalar("SELECT COUNT(*) FROM research_call_criteria WHERE call_id = ?", [$callId]);
if ($n === 0) {
    foreach ([["Methodology", 40], ["Significance", 30], ["Feasibility", 30]] as $i => [$name, $w]) {
        App\Core\Database::insert("research_call_criteria", [
            "call_id" => $callId, "criterion_name" => $name,
            "description" => "Test criterion", "weight_pct" => $w,
            "sort_order" => $i + 1,
        ]);
    }
}
echo "test passwords + criteria set on copy\n";
// The test coordinator has no college in the seeded data; §5 college-scope
// would (correctly) deny them. Give the coordinator the college of the
// application so the assignment-management page is reachable for the check.
$appCollege = App\Core\Database::scalar("SELECT college_id FROM research_applications ORDER BY id LIMIT 1");
if ($appCollege) {
    App\Core\Database::run("UPDATE users SET college_id = ? WHERE email = \"coordinator@mwu.edu.et\" AND college_id IS NULL", [$appCollege]);
}
echo "college scope set on copy\n";
'

# ---- 1. Start server bound to the copy -----------------------------------
DB_NAME="$DB_WIN" php -S 127.0.0.1:8099 -t public >/tmp/reep_smoke_server.log 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null; rm -f "$DB_COPY"' EXIT
for i in $(seq 1 30); do
  curl -s -m 2 -o /dev/null "$B/login.php" && break
  sleep 0.5
done
curl -s -m 5 -o /dev/null "$B/login.php" || { echo "server did not start"; cat /tmp/reep_smoke_server.log; exit 2; }

P='Password123!'
login() { # email jar -> http code
  local jar=$2 tok
  rm -f "$jar"
  tok=$(curl -s -m 15 -c "$jar" "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -d "identifier=$1&password=$P&_csrf=$tok" "$B/login.php?action=do_login"
}
tok() { curl -s -m 15 -b "$1" -c "$1" "$B/index.php" | grep -o 'name="csrf-token" content="[^"]*"\|name="_csrf" value="[^"]*"' | head -1 | sed 's/.*content="//;s/.*value="//;s/"//'; }
page() { curl -s -m 20 -b "$1" "$B/index.php?$2"; }
post() { local jar=$1 action=$2; shift 2; curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' "$B/index.php?action=$action" "$@"; }

# app under test
APP=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT id FROM research_applications ORDER BY id LIMIT 1");')
COORD_ID=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT id FROM users WHERE email=\"coordinator@mwu.edu.et\"");')

# ---- 2. Coordinator sees the sidebar item + page (spec §2, §3B) -----------
step "Coordinator sidebar + My Evaluations page"
login coordinator@mwu.edu.et /tmp/sm_coord.jar >/dev/null
code=$(curl -s -m 20 -b /tmp/sm_coord.jar -o /tmp/sm_coord_dash.html -w '%{http_code}' "$B/index.php?action=coordinator_dashboard")
chk "coordinator dashboard loads" "$code" 200
grep -q "action=my_evaluations" /tmp/sm_coord_dash.html && ok "sidebar contains My Evaluations" || bad "sidebar missing My Evaluations"

curl -s -m 20 -b /tmp/sm_coord.jar -o /tmp/sm_coord_me.html "$B/index.php?action=my_evaluations"
body=$(cat /tmp/sm_coord_me.html)
echo "$body" | grep -q "My evaluations" && ok "my_evaluations page renders" || bad "my_evaluations page missing title"
echo "$body" | grep -q "You have no evaluation assignments at this time" && ok "meaningful empty state (no assignments yet)" || bad "empty state missing"
echo "$body" | grep -q "Total Assigned" && ok "summary cards present" || bad "summary cards missing"

# ---- 3. Researcher sees sidebar + empty page (spec §2, §3A) ---------------
step "Researcher sidebar + My Evaluations page"
login bona@gmail.com /tmp/sm_res.jar >/dev/null
page /tmp/sm_res.jar "action=researcher_dashboard" > /tmp/sm_res_dash.html
grep -q "action=my_evaluations" /tmp/sm_res_dash.html && ok "researcher sidebar contains My Evaluations" || bad "researcher sidebar missing My Evaluations"
page /tmp/sm_res.jar "action=my_evaluations" > /tmp/sm_res_me.html
grep -q "You have no evaluation assignments at this time" /tmp/sm_res_me.html && ok "researcher sees empty state" || bad "researcher empty state missing"

# ---- 4. System administrator is blocked (spec §2, §7, test 13) ------------
step "System administrator exclusion"
login chala@gmail.com /tmp/sm_adm.jar >/dev/null
code=$(page /tmp/sm_adm.jar "action=my_evaluations" > /tmp/sm_adm_me.html; echo 200) # placeholder (captured properly below)
code=$(curl -s -m 20 -b /tmp/sm_adm.jar -o /tmp/sm_adm_me.html -w '%{http_code}' "$B/index.php?action=my_evaluations")
chk "admin my_evaluations rejected" "$code" 403
page /tmp/sm_adm.jar "" > /tmp/sm_adm_home.html
grep -q "action=my_evaluations" /tmp/sm_adm_home.html && bad "admin sidebar must NOT contain My Evaluations" || ok "admin sidebar has no My Evaluations"

# ---- 5. Assign coordinator as evaluator (college level) -------------------
step "Assignment creation (college level)"
T=$(tok /tmp/sm_coord.jar)
# Coordinator's own proposal is not assignable to self via UI; insert directly to model
DB_NAME="$DB_WIN" php -r '
require "app/bootstrap.php";
$appId = (int) App\Core\Database::scalar("SELECT id FROM research_applications ORDER BY id LIMIT 1");
$uid   = (int) App\Core\Database::scalar("SELECT id FROM users WHERE email=\"coordinator@mwu.edu.et\"");
$other = (int) App\Core\Database::scalar("SELECT id FROM users WHERE email=\"marara@gmail.com\"");
App\Models\EvaluationModel::assign($appId, $uid, $uid, date("Y-m-d"), "college");
App\Models\EvaluationModel::assign($appId, $other, $uid, date("Y-m-d"), "university");
echo "assignments created\n";
'

body=$(page /tmp/sm_coord.jar "action=my_evaluations")
echo "$body" | grep -q "Accept" && ok "coordinator sees assigned proposal with Accept action" || bad "assigned proposal missing from list"
echo "$body" | grep -q "Principal investigator" && ok "PI column shown" || bad "PI column missing"
echo "$body" | grep -q "badge-secondary\|badge-info" && ok "evaluation level badge shown" || bad "level badge missing"

# Accept the assignment (spec §3 workflow) -> row then offers Open Evaluation
T=$(tok /tmp/sm_coord.jar)
ASG=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT id FROM evaluator_assignments WHERE application_id='"$APP"' AND evaluator_user_id='"$COORD_ID"'");')
c=$(post /tmp/sm_coord.jar evaluator_respond -d "_csrf=$T" -d "assignment_id=$ASG" -d "response=accept")
echo "$c" | grep -q "Accept" && ok "accept response issued" || true
[ "$c" = "302" ] && ok "evaluator_respond accepted" || bad "evaluator_respond returned $c"
st=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT status FROM evaluator_assignments WHERE id='"$ASG"'");')
chk "assignment status accepted" "$st" "accepted"
body=$(page /tmp/sm_coord.jar "action=my_evaluations")
echo "$body" | grep -q "Open Evaluation" && ok "Open Evaluation offered after accept" || bad "Open Evaluation link missing"

# ---- 6. Isolation: non-assigned researcher sees nothing (test 4) -----------
page /tmp/sm_res.jar "action=my_evaluations" > /tmp/sm_res_me2.html
grep -q "Open Evaluation" /tmp/sm_res_me2.html && bad "researcher sees someone else's assignment" || ok "non-assigned researcher sees no assignments"

# Researcher-assigned university evaluation is visible to that researcher (tests 1–2)
login marara@gmail.com /tmp/sm_mar.jar >/dev/null
page /tmp/sm_mar.jar "action=my_evaluations" > /tmp/sm_mar_me.html
grep -q "University" /tmp/sm_mar_me.html && ok "researcher sees university-level assignment label" || bad "university-level assignment missing for researcher"
grep -q "Accept" /tmp/sm_mar_me.html && ok "researcher offered accept action on own assignment" || bad "researcher cannot act on own assignment"

# ---- 7. Evaluator form access control (tests 4, 5) ------------------------
step "Evaluation form access + draft + submit (tests 5,6,7,8,9,10)"
code=$(curl -s -m 20 -b /tmp/sm_res.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=evaluation_form&application_id=$APP")
# researcher not assigned -> should redirect (302) away from the form
chk "non-assigned evaluator blocked from form" "$code" 302

code=$(curl -s -m 20 -b /tmp/sm_coord.jar -o /tmp/sm_form.html -w '%{http_code}' "$B/index.php?action=evaluation_form&application_id=$APP")
chk "assigned evaluator opens form" "$code" 200
grep -q "Evaluation level" /tmp/sm_form.html && ok "form shows evaluation level" || bad "form level missing"
grep -q "Co-investigators\|Principal investigator" /tmp/sm_form.html && ok "form shows PI/team" || bad "form team display missing"
grep -q "Scoring Criteria" /tmp/sm_form.html && ok "criteria rendered" || bad "criteria missing"
grep -q "white-space:pre-wrap;\"" /tmp/sm_form.html || grep -q 'white-space:pre-wrap;"' /tmp/sm_form.html && true

# criteria ids from the copy
C1=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT id FROM research_call_criteria WHERE call_id=(SELECT call_id FROM research_applications WHERE id='"$APP"') ORDER BY id LIMIT 1");')

# draft save (test 6)
T=$(tok /tmp/sm_coord.jar)
c=$(curl -s -m 20 -b /tmp/sm_coord.jar -c /tmp/sm_coord.jar -X POST "$B/index.php?action=evaluation_save_draft" \
    -F "_csrf=$T" -F "application_id=$APP" -F "score_$C1=77" -F "strengths=Solid framing." )
echo "$c" | grep -q '"ok":true' && ok "draft saved (AJAX)" || bad "draft save failed: $c"

# incomplete submit rejected (test 8): no recommendation
T=$(tok /tmp/sm_coord.jar)
c=$(post /tmp/sm_coord.jar evaluation_submit -d "_csrf=$T" -d "application_id=$APP" -d "score_$C1=80")
chk "incomplete submit redirects" "$c" 302
n=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT COUNT(*) FROM evaluations WHERE status=\"submitted\"");')
chk "incomplete submit leaves no submitted row" "$n" 0

# complete submit (test 7)
T=$(tok /tmp/sm_coord.jar)
c=$(post /tmp/sm_coord.jar evaluation_submit -d "_csrf=$T" -d "application_id=$APP" \
    -d "score_$C1=82" -d "recommendation=accept_with_revision" \
    -d "strengths=Clear objectives." -d "weaknesses=Sampling frame unclear." \
    --data-urlencode "overall_comment=Suitable after minor corrections." \
    --data-urlencode "comments[]=Improve methodology.")
chk "evaluation_submit accepted" "$c" 302
n=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT COUNT(*) FROM evaluations WHERE status=\"submitted\"");')
chk "evaluation persisted" "$n" 1
st=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT status FROM evaluator_assignments WHERE application_id='"$APP"' AND evaluator_user_id='"$COORD_ID"'");')
chk "assignment marked completed" "$st" "completed"

# duplicate submission blocked (test 9)
T=$(tok /tmp/sm_coord.jar)
c=$(post /tmp/sm_coord.jar evaluation_submit -d "_csrf=$T" -d "application_id=$APP" \
    -d "score_$C1=90" -d "recommendation=accept" -d "strengths=x" -d "weaknesses=y" -d "overall_comment=z")
n=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT COUNT(*) FROM evaluations WHERE status=\"submitted\"");')
chk "duplicate submit prevented (still 1 row)" "$n" 1

# refresh persistence (test 10)
body=$(page /tmp/sm_coord.jar "action=my_evaluations")
echo "$body" | grep -q "View Evaluation" && ok "submitted evaluation visible after refresh" || bad "View Evaluation link missing"
echo "$body" | grep -q "Submitted" && ok "status shows Submitted" || bad "Submitted badge missing"

# ---- 8. Coordinator evaluates; PI feedback visibility (test 11) ------------
step "Feedback visibility"
# researcher (PI of app 1 = bona@gmail.com) opens their application
page /tmp/sm_res.jar "action=application_view&id=$APP" > /tmp/sm_pi_view.html
grep -q "Improve methodology" /tmp/sm_pi_view.html && ok "researcher sees to_researcher comment" || bad "researcher feedback missing"
# committee_only must not leak: none submitted here, but check the PI page has no committee comments block leak
grep -q "committee only" /tmp/sm_pi_view.html && bad "committee-only comment leaked to PI" || ok "no committee-only comments exposed to PI"

# ---- 9. Coordinator's assign-evaluators page no longer crashes ------------
step "Regression: assign evaluators page (previously crashed on missing column)"
code=$(curl -s -m 20 -b /tmp/sm_coord.jar -o /tmp/sm_assign.html -w '%{http_code}' "$B/index.php?action=assign_evaluators&id=$APP")
chk "assign_evaluators loads" "$code" 200
grep -qi "no such column" /tmp/sm_assign.html && bad "assign_evaluators still throws SQL error" || ok "no SQL error on assign page"

# ---- 10. Evaluation view read-only page (test 10) --------------------------
EV=$(DB_NAME="$DB_WIN" php -r 'require "app/bootstrap.php"; echo App\Core\Database::scalar("SELECT id FROM evaluations WHERE status=\"submitted\" LIMIT 1");')
code=$(curl -s -m 20 -b /tmp/sm_coord.jar -o /tmp/sm_evview.html -w '%{http_code}' "$B/index.php?action=evaluation_view&id=$EV")
chk "evaluator opens own submitted evaluation" "$code" 200
grep -q "Weighted score" /tmp/sm_evview.html && ok "submitted evaluation shows score" || bad "score missing on view"

echo
echo "==================================="
echo "PASS: $PASS  FAIL: $FAIL"
echo "==================================="
[ "$FAIL" -eq 0 ]

