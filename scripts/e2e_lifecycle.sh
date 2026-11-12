#!/bin/bash
# MWU RPEE — End-to-end lifecycle test (run from project root)
# research call -> application -> docs -> submit -> screen -> assign ->
# evaluate -> college/university approval -> fund -> project activation
set -u
[ -f app/bootstrap.php ] || { echo "Run from the project root"; exit 2; }

B=http://127.0.0.1:8099
P='Password123!'
PASS=0; FAIL=0

say()  { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); say "  PASS: $*"; }
bad()  { FAIL=$((FAIL+1)); say "  FAIL: $*"; }
chk()  { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected $3 got $2"; fi; }

# --- helpers -----------------------------------------------------------------
login() { # email jar -> echoes login http code
  local jar=$2 tok
  rm -f "$jar"
  tok=$(curl -s -m 15 -c "$jar" "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -d "identifier=$1&password=$P&_csrf=$tok" "$B/login.php?action=do_login"
}
tok() { curl -s -m 15 -b "$1" -c "$1" "$B/index.php" | grep -o 'name="csrf-token" content="[^"]*"\|name="_csrf" value="[^"]*"' | head -1 | sed 's/.*content="//;s/.*value="//;s/"//'; }
post() { # jar action data... -> http code (urlencoded)
  local jar=$1 action=$2; shift 2
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' "$B/index.php?action=$action" "$@"
}
up() { # jar action file extra... -> http code (multipart; Windows curl needs a native path)
  local jar=$1 action=$2 f=$3; shift 3
  local wf; wf=$(cygpath -m "$f" 2>/dev/null || echo "$f")
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -F "document=@$wf;type=application/pdf" "$B/index.php?action=$action" "$@"
}
db() { timeout 10 php -r 'require "app/bootstrap.php"; echo (string) (Database::scalar($argv[1]) ?? "");' "$1"; }
page() { curl -s -m 20 -b "$1" "$B/index.php?$2"; }

# server up?
code=$(curl -s -m 10 -o /dev/null -w '%{http_code}' "$B/login.php" || echo 000)
[ "$code" = "200" ] || { say "Dev server not running (got $code) — start: php -S 127.0.0.1:8099 -t public"; exit 2; }

printf '%%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\n%%%%EOF\n' > /tmp/e2e_doc.pdf
[ -s /tmp/e2e_doc.pdf ] && ok "test PDF prepared" || { bad "cannot write test PDF"; exit 2; }

# ---------- cleanup artifacts from any previous run (keeps the test re-runnable) ----------
# FK-safe order: children before parents, for every E2E-call application and its
# project (if one was activated), the ethics chain, then the call itself.
php -r 'require "app/bootstrap.php";
  $call = Database::scalar("SELECT id FROM research_calls WHERE reference_number=?", ["MWU/RPEE/CALL/2026/E2E"]);
  if ($call) {
    $apps = array_map("intval", array_column(Database::all("SELECT id FROM research_applications WHERE call_id=?", [$call]), "id"));
    foreach ($apps as $a) {
      Database::run("DELETE FROM workflow_history WHERE entity_type=? AND entity_id=?", ["research_application", $a]);
      Database::run("DELETE FROM ethics_certificates WHERE ethics_application_id IN (SELECT id FROM ethics_applications WHERE research_application_id=?)", [$a]);
      Database::run("DELETE FROM ethics_decisions WHERE ethics_application_id IN (SELECT id FROM ethics_applications WHERE research_application_id=?)", [$a]);
      Database::run("DELETE FROM ethics_reviews WHERE ethics_application_id IN (SELECT id FROM ethics_applications WHERE research_application_id=?)", [$a]);
      Database::run("DELETE FROM workflow_history WHERE entity_type=? AND entity_id IN (SELECT id FROM ethics_applications WHERE research_application_id=?)", ["ethics_application", $a]);
      Database::run("DELETE FROM ethics_applications WHERE research_application_id=?", [$a]);
      Database::run("DELETE FROM committee_decisions WHERE application_id=?", [$a]);
      Database::run("DELETE FROM application_committee_assignments WHERE application_id=?", [$a]);
      Database::run("DELETE FROM research_agreements WHERE application_id=?", [$a]);
      Database::run("DELETE FROM revision_responses WHERE revision_request_id IN (SELECT id FROM revision_requests WHERE application_id=?)", [$a]);
      Database::run("DELETE FROM revision_requests WHERE application_id=?", [$a]);
      Database::run("DELETE FROM college_decisions WHERE application_id=?", [$a]);
      Database::run("DELETE FROM evaluations WHERE application_id=?", [$a]);
      Database::run("DELETE FROM evaluator_assignments WHERE application_id=?", [$a]);
      Database::run("DELETE FROM documents WHERE entity_type=? AND entity_id=?", ["research_application", $a]);
      foreach (array_map("intval", array_column(Database::all("SELECT id FROM research_projects WHERE application_id=?", [$a]), "id")) as $p) {
        Database::run("DELETE FROM workflow_history WHERE entity_type=? AND entity_id=?", ["research_project", $p]);
        Database::run("DELETE FROM funding_releases WHERE project_id=?", [$p]);
        Database::run("DELETE FROM research_budget_items WHERE budget_id IN (SELECT id FROM research_budgets WHERE project_id=?)", [$p]);
        Database::run("DELETE FROM research_budgets WHERE project_id=?", [$p]);
        Database::run("DELETE FROM research_milestones WHERE project_id=?", [$p]);
        Database::run("DELETE FROM research_project_members WHERE project_id=?", [$p]);
        Database::run("DELETE FROM presentations WHERE project_id=?", [$p]);
        Database::run("DELETE FROM progress_reports WHERE project_id=?", [$p]);
        Database::run("DELETE FROM project_actions WHERE project_id=?", [$p]);
        Database::run("DELETE FROM research_extension_requests WHERE project_id=?", [$p]);
        Database::run("DELETE FROM repository_records WHERE project_id=?", [$p]);
        Database::run("DELETE FROM documents WHERE entity_type=? AND entity_id=?", ["research_project", $p]);
      }
      Database::run("DELETE FROM research_projects WHERE application_id=?", [$a]);
    }
    Database::run("DELETE FROM research_applications WHERE call_id=?", [$call]);
    Database::run("DELETE FROM research_call_criteria WHERE call_id=?", [$call]);
    Database::run("DELETE FROM research_call_requirements WHERE call_id=?", [$call]);
    Database::run("DELETE FROM research_calls WHERE id=?", [$call]);
    echo "cleaned prior E2E artifacts\n";
  }' || { echo "CLEANUP FAILED — aborting"; exit 2; }

say "== [0] accounts =="
OFFICER_ID=$(db "SELECT id FROM users WHERE email='officer@mwu.edu.et'")
EVAL_ID=$(db "SELECT id FROM users WHERE email='evaluator@mwu.edu.et'")
[ -n "$OFFICER_ID" ] && [ -n "$EVAL_ID" ] && ok "officer#$OFFICER_ID evaluator#$EVAL_ID" || { bad "seed accounts missing"; exit 2; }

# College scoping (§5): the coordinator must belong to the researcher's college.
RCOLLEGE=$(db "SELECT college_id FROM users WHERE email='researcher@mwu.edu.et'")
RDEPT=$(db "SELECT department_id FROM users WHERE email='researcher@mwu.edu.et'")
CC_EMAIL=ccoordinator.cc@mwu.edu.et
php -r 'require "app/bootstrap.php";
  [$email,$college,$dept] = [$argv[1],(int)$argv[2],(int)$argv[3]];
  if (!Database::scalar("SELECT id FROM users WHERE email=?",[$email])) {
    $roleId = Database::scalar("SELECT id FROM roles WHERE name=\"college_coordinator\"");
    App\Models\UserModel::create([
      "full_name"=>"E2E College Coordinator", "email"=>$email,
      "college_id"=>$college, "department_id"=>$dept, "status"=>"active",
      "password_hash"=>password_hash("Password123!",PASSWORD_BCRYPT,["cost"=>12]),
      "must_change_password"=>0,
    ],[(int)$roleId]);
    echo "created $email\n";
  } else { echo "exists $email\n"; }' "$CC_EMAIL" "$RCOLLEGE" "$RDEPT" || { bad "coordinator setup"; exit 2; }
CC_ID=$(db "SELECT id FROM users WHERE email='$CC_EMAIL'")
[ -n "$CC_ID" ] && ok "college coordinator #$CC_ID (college $RCOLLEGE)" || { bad "coordinator missing"; exit 2; }

# ---------- [1] Officer: create + configure + publish a fresh call ----------
say "== [1] officer publishes E2E call =="
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
T=$(tok /tmp/eo.jar)
E2E_THEME=$(db "SELECT id FROM research_themes WHERE name='Agriculture and Food Security'")
c=$(post /tmp/eo.jar call_save \
  -d "_csrf=$T" -d "title=E2E Lifecycle Verification Call" -d "reference_number=MWU/RPEE/CALL/2026/E2E" \
  -d "theme=$E2E_THEME" \
  -d "description=Call created by the automated end-to-end lifecycle test." \
  -d "opens_at=$(date +%Y-%m-%d)" -d "closes_at=$(date -d '+30 days' +%Y-%m-%d)" \
  -d "max_duration=12" -d "max_budget=200000" -d "max_apps=2" \
  -d "evaluation_mode=single_blind" -d "required_evaluators=1")
chk "call_save" "$c" 302
CALL=$(db "SELECT id FROM research_calls WHERE reference_number='MWU/RPEE/CALL/2026/E2E'")
[ -n "$CALL" ] && ok "call id=$CALL created" || { bad "call row missing"; exit 2; }

T=$(tok /tmp/eo.jar)
TYPES=""; for tc in proposal cv budget_doc work_plan; do
  TYPES="$TYPES -d req_type%5B%5D=$(db "SELECT id FROM document_types WHERE type_code='$tc'") -d req_kind%5B%5D=required"
done
c=$(post /tmp/eo.jar call_config -d "_csrf=$T" -d "id=$CALL" \
  $TYPES \
  -d "crit_name%5B%5D=Methodology" -d "crit_weight%5B%5D=50" \
  -d "crit_name%5B%5D=Significance" -d "crit_weight%5B%5D=30" \
  -d "crit_name%5B%5D=Feasibility" -d "crit_weight%5B%5D=20")
chk "call_config (4 required docs, 3 criteria)" "$c" 302
c=$(post /tmp/eo.jar call_publish -d "_csrf=$T" -d "id=$CALL")
chk "call_publish" "$c" 302
chk "call status" "$(db "SELECT status FROM research_calls WHERE id=$CALL")" published

# ---------- [2] Researcher: apply, upload 4 docs, submit ----------
say "== [2] researcher creates + submits application =="
login researcher@mwu.edu.et /tmp/er.jar >/dev/null
T=$(tok /tmp/er.jar)
c=$(post /tmp/er.jar application_save \
  -d "_csrf=$T" -d "call_id=$CALL" \
  -d "title=E2E Lifecycle Test: Agro-Ecological Transition Study" \
  -d "abstract=Automated end-to-end verification application exercising the full research lifecycle." \
  -d "budget=150000" -d "duration=12" -d "start_date=$(date +%Y-%m-%d)" \
  -d "end_date=$(date -d '+12 months' +%Y-%m-%d)" -d "requires_ethics=no" \
  -d "methodology=Quasi-experimental panel design across kebeles." \
  -d "wp_activity%5B0%5D=Inception and sampling" -d "wp_start%5B0%5D=$(date +%Y-%m-%d)" -d "wp_end%5B0%5D=$(date -d '+2 months' +%Y-%m-%d)" -d "wp_resp%5B0%5D=PI" -d "wp_out%5B0%5D=Sampling frame" \
  -d "wp_activity%5B1%5D=Data collection and analysis" -d "wp_start%5B1%5D=$(date -d '+3 months' +%Y-%m-%d)" -d "wp_end%5B1%5D=$(date -d '+11 months' +%Y-%m-%d)" -d "wp_resp%5B1%5D=PI" -d "wp_out%5B1%5D=Dataset + paper draft")
chk "application_save (draft)" "$c" 302
APP=$(db "SELECT id FROM research_applications WHERE title LIKE 'E2E Lifecycle Test%' ORDER BY id DESC LIMIT 1")
[ -n "$APP" ] && ok "application id=$APP created" || { bad "application row missing"; exit 2; }
chk "initial status" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" DRAFT
chk "workflow attached" "$(db "SELECT COUNT(*) FROM research_applications WHERE id=$APP AND workflow_id>0 AND current_stage_id>0")" 1

n0=$(db "SELECT COUNT(*) FROM research_applications WHERE id=$APP") # sanity placeholder
say "  -- uploading required documents --"
i=0
for tc in proposal cv budget_doc work_plan; do
  T=$(tok /tmp/er.jar)
  DTID=$(db "SELECT id FROM document_types WHERE type_code='$tc'")
  c=$(up /tmp/er.jar application_upload /tmp/e2e_doc.pdf \
      -F "_csrf=$T" -F "entity_id=$APP" -F "document_type_id=$DTID" \
      -F "title=E2E $tc document" -F "reason=Lifecycle test upload")
  chk "upload $tc (type#$DTID)" "$c" 302
done
DOCS=$(db "SELECT COUNT(*) FROM documents WHERE entity_type='research_application' AND entity_id=$APP AND status='active'")
chk "4 active documents stored" "$DOCS" 4

# CSRF negative check: bad token must be rejected with 419
badtok=$(post /tmp/er.jar application_submit -d "_csrf=deadbeef" -d "id=$APP")
chk "CSRF rejection (419)" "$badtok" 419

c=$(post /tmp/er.jar application_submit -d "_csrf=$T" -d "id=$APP")
chk "application_submit" "$c" 302
chk "status after submit" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" SUBMITTED

# ---------- [3] Coordinator: receive + screen forward ----------
say "== [3] college coordinator screens =="
login $CC_EMAIL /tmp/ecc.jar >/dev/null
T=$(tok /tmp/ecc.jar)
c=$(post /tmp/ecc.jar screening_receive -d "_csrf=$T" -d "id=$APP")
chk "screening_receive" "$c" 302
chk "status after receive" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" COLLEGE_RECEIVED
c=$(post /tmp/ecc.jar screening_decide -d "_csrf=$T" -d "id=$APP" -d "decision=forward_to_evaluation" -d "comment=Documents complete; methodology sound.")
chk "screening_decide forward_to_evaluation" "$c" 302
chk "status after screening" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" EVALUATOR_ASSIGNMENT_PENDING

T=$(tok /tmp/ecc.jar)
c=$(post /tmp/ecc.jar assign_evaluator -d "_csrf=$T" -d "id=$APP" -d "evaluator_ids%5B%5D=$EVAL_ID" -d "deadline=$(date -d '+14 days' +%Y-%m-%d)")
chk "assign_evaluator" "$c" 302
chk "status after assign" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" EVALUATOR_ASSIGNED
chk "assignment row active" "$(db "SELECT COUNT(*) FROM evaluator_assignments WHERE application_id=$APP AND evaluator_user_id=$EVAL_ID AND is_active=1")" 1

# ---------- [4] Evaluator: score + submit ----------
say "== [4] evaluator submits evaluation =="
login evaluator@mwu.edu.et /tmp/eev.jar >/dev/null
page /tmp/eev.jar "action=my_evaluations" | grep -q "E2E Lifecycle Test" && ok "assignment visible in My evaluations" || bad "assignment not listed"
T=$(tok /tmp/eev.jar)
C1=$(db "SELECT id FROM research_call_criteria WHERE call_id=$CALL AND criterion_name='Methodology'")
C2=$(db "SELECT id FROM research_call_criteria WHERE call_id=$CALL AND criterion_name='Significance'")
C3=$(db "SELECT id FROM research_call_criteria WHERE call_id=$CALL AND criterion_name='Feasibility'")
c=$(post /tmp/eev.jar evaluation_submit \
  -d "_csrf=$T" -d "application_id=$APP" \
  -d "score_$C1=88" -d "score_comment_$C1=Rigorous design" \
  -d "score_$C2=76" -d "score_comment_$C2=Clear policy relevance" \
  -d "score_$C3=81" \
  -d "recommendation=accept" -d "strengths=Strong quasi-experimental design." \
  -d "weaknesses=Limited baseline window." -d "recommendations_text=Coordinate with woreda experts." \
  -d "overall_comment=Approve for funding." -d "comments%5B%5D=Please share instruments with the committee.")
chk "evaluation_submit" "$c" 302
chk "evaluation row submitted" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND status='submitted'")" 1
chk "assignment completed" "$(db "SELECT status FROM evaluator_assignments WHERE application_id=$APP AND evaluator_user_id=$EVAL_ID AND is_active=1")" completed
EV_STATUS=$(db "SELECT status_code FROM research_applications WHERE id=$APP")
case "$EV_STATUS" in
  EVALUATION_COMPLETED|UNDER_EVALUATION) ok "workflow advanced ($EV_STATUS)";;
  *) bad "unexpected post-evaluation status: $EV_STATUS";;
esac

# ---------- [5] Officer: committee + directorate approval chain ----------
say "== [5] college + committee + directorate approval chain =="
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
fire() { local T2; T2=$(tok /tmp/eo.jar); post /tmp/eo.jar workflow_fire -d "_csrf=$T2" -d "entity_type=research_application" -d "entity_id=$1" -d "transition=$2" -d "comment=E2E" >/dev/null; }
fire_as() { local j=$1; local T2; T2=$(tok "$j"); post "$j" workflow_fire -d "_csrf=$T2" -d "entity_type=research_application" -d "entity_id=$2" -d "transition=$3" -d "comment=E2E" >/dev/null; }
fire "$APP" "Send to college review";     chk "COLLEGE_REVIEW"            "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" COLLEGE_REVIEW
fire "$APP" "College approves";           chk "COLLEGE_APPROVED"          "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" COLLEGE_APPROVED
fire "$APP" "Submit to university";       chk "SUBMITTED_TO_UNIVERSITY"   "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" SUBMITTED_TO_UNIVERSITY
fire "$APP" "University review starts";   chk "UNIVERSITY_REVIEW"         "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" UNIVERSITY_REVIEW

# §12: Research Director assigns the research to a University Research Committee
URC=$(db "SELECT id FROM committees WHERE code='URC'")
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar app_committee_assign -d "_csrf=$T" -d "application_id=$APP" -d "committee_id=$URC" -d "meeting_date=$(date +%Y-%m-%d)")
chk "app_committee_assign" "$c" 302
chk "UNIVERSITY_COMMITTEE_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" UNIVERSITY_COMMITTEE_REVIEW
chk "active committee assignment" "$(db "SELECT COUNT(*) FROM application_committee_assignments WHERE application_id=$APP AND status='active'")" 1

# §12 Rules 8 & 9: committee member records the decision + funding recommendation
login committee@mwu.edu.et /tmp/ecm.jar >/dev/null
T=$(tok /tmp/ecm.jar)
c=$(post /tmp/ecm.jar app_committee_decide -d "_csrf=$T" -d "application_id=$APP" -d "decision=approve" \
  -d "recommended_amount=145000" -d "recommendation_note=Recommended within the available budget" \
  -d "comments=Scientific merit confirmed by the committee." -d "meeting_date=$(date +%Y-%m-%d)")
chk "committee decision recorded" "$c" 302
chk "decision row (Rule 8)" "$(db "SELECT COUNT(*) FROM committee_decisions WHERE application_id=$APP")" 1
chk "funding recommendation (Rule 9)" "$(db "SELECT CAST(recommended_amount AS INTEGER) FROM committee_decisions WHERE application_id=$APP ORDER BY id DESC LIMIT 1")" 145000

# Directorate approval — separate from the committee recommendation (Rule 8)
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
fire "$APP" "Approve (directorate)";       chk "APPROVED (directorate)"     "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" APPROVED

# §28 step 32: directorate records the budget allocation (Rule 13 negative first)
login director@mwu.edu.et /tmp/edi.jar >/dev/null
T=$(tok /tmp/edi.jar)
post /tmp/edi.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" -d "transition=Budget review starts" -d "comment=E2E" >/dev/null
chk "BUDGET_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" BUDGET_REVIEW
T=$(tok /tmp/edi.jar)
c=$(post /tmp/edi.jar application_budget -d "_csrf=$T" -d "id=$APP" -d "approved_amount=999999")
chk "over-allocation rejected (Rule 13)" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" BUDGET_REVIEW
T=$(tok /tmp/edi.jar)
c=$(post /tmp/edi.jar application_budget -d "_csrf=$T" -d "id=$APP" -d "approved_amount=150000" -d "note=E2E allocation")
chk "application_budget" "$c" 302
chk "BUDGET_APPROVED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" BUDGET_APPROVED
chk "allocation recorded" "$(php -r 'require "app/bootstrap.php"; echo (int) Database::scalar("SELECT budget_approved_amount FROM research_applications WHERE id=".$argv[1]);' "$APP")" 150000

# Funding release (finance officer)
login finance@mwu.edu.et /tmp/efi.jar >/dev/null
fire_as /tmp/efi.jar "$APP" "Release funds"
chk "FUNDED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" FUNDED

# ---------- [6] Agreement (§13, Rules 10–13) + project activation ----------
say "== [6] research agreement + project activation =="
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar agreement_issue -d "_csrf=$T" -d "application_id=$APP" \
  -d "terms=Baseline research agreement terms for the E2E project." -d "agreed_amount=150000")
chk "agreement_issue" "$c" 302
AGR=$(db "SELECT id FROM research_agreements WHERE application_id=$APP")
[ -n "$AGR" ] && ok "agreement id=$AGR issued" || { bad "agreement row missing"; exit 2; }
chk "agreement number prefix" "$(db "SELECT agreement_number LIKE 'MWU-RPEE-AGR-%' FROM research_agreements WHERE id=$AGR")" 1

# Rule 13: agreed amount above the approved allocation must be rejected
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar agreement_issue -d "_csrf=$T" -d "application_id=$APP" \
  -d "terms=Over-cap terms" -d "agreed_amount=999999")
chk "over-cap agreement rejected (Rule 13)" "$(db "SELECT COUNT(*) FROM research_agreements WHERE application_id=$APP")" 1

# Rule 11: activation must be blocked before the researcher signs
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar project_activate -d "_csrf=$T" -d "application_id=$APP")
chk "activation blocked before signature (Rule 11)" "$(db "SELECT COUNT(*) FROM research_projects WHERE application_id=$APP")" 0

# Researcher views + signs the agreement (§35 steps 35–37)
login researcher@mwu.edu.et /tmp/er.jar >/dev/null
page /tmp/er.jar "action=application_view&id=$APP" | grep -q "Research agreement" && ok "researcher can view the agreement" || bad "agreement card missing"
T=$(tok /tmp/er.jar)
c=$(post /tmp/er.jar agreement_sign -d "_csrf=$T" -d "agreement_id=$AGR" -d "signature_name=Dr. Tigist Ayele" -d "signature_note=Accepted electronically.")
chk "agreement_sign" "$c" 302
chk "agreement signed status" "$(db "SELECT status FROM research_agreements WHERE id=$AGR")" signed
chk "signed_by is the PI" "$(db "SELECT signed_by FROM research_agreements WHERE id=$AGR")" "$(db "SELECT id FROM users WHERE email='researcher@mwu.edu.et'")"

T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar project_activate -d "_csrf=$T" -d "application_id=$APP")
chk "project_activate" "$c" 302
PROJ=$(db "SELECT id FROM research_projects WHERE application_id=$APP")
[ -n "$PROJ" ] && ok "project id=$PROJ created" || { bad "project row missing"; exit 2; }
chk "project status" "$(db "SELECT status_code FROM research_projects WHERE id=$PROJ")" ACTIVE
chk "application status mirrored" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" ACTIVE
chk "PI is member" "$(db "SELECT COUNT(*) FROM research_project_members WHERE project_id=$PROJ AND member_role='principal_investigator'")" 1
chk "work plan became milestones" "$(db "SELECT COUNT(*) FROM research_milestones WHERE project_id=$PROJ")" 2
chk "budget approved on creation" "$(db "SELECT status FROM research_budgets WHERE project_id=$PROJ")" approved
chk "budget amount matches" "$(php -r 'require "app/bootstrap.php"; echo (float) Database::scalar("SELECT approved_amount FROM research_budgets WHERE project_id='"$PROJ"'");' 2>/dev/null)" 150000
chk "project workflow initialized" "$(db "SELECT COUNT(*) FROM research_projects WHERE id=$PROJ AND workflow_id>0")" 1
HIST=$(db "SELECT COUNT(*) FROM workflow_history WHERE entity_type='research_application' AND entity_id=$APP")
[ "$HIST" -ge 11 ] && ok "workflow history recorded ($HIST transitions)" || bad "workflow history thin ($HIST)"

# ---------- [7] Authorization spot-checks ----------
say "== [7] negative authorization checks =="
login researcher@mwu.edu.et /tmp/er.jar >/dev/null
T=$(tok /tmp/er.jar)
before=$(db "SELECT status_code FROM research_projects WHERE id=$PROJ")
post /tmp/er.jar workflow_fire_project -d "_csrf=$T" -d "entity_id=$PROJ" -d "transition=Suspend project" -d "reason=should fail" >/dev/null
chk "researcher cannot suspend (status unchanged)" "$(db "SELECT status_code FROM research_projects WHERE id=$PROJ")" "$before"
login coinvestigator@mwu.edu.et /tmp/eci.jar >/dev/null
T=$(tok /tmp/eci.jar)
c=$(post /tmp/eci.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" -d "transition=Approve (directorate)" -d "comment=nope" >/dev/null; echo done)
chk "co-I cannot fire transitions (no crash)" "$c" done

# ---------- [8] Ethics-path application: apply, evaluate, reach UNIVERSITY_REVIEW ----------
say "== [8] ethics-path application (requires_ethics=yes) =="
T=$(tok /tmp/er.jar)
c=$(post /tmp/er.jar application_save \
  -d "_csrf=$T" -d "call_id=$CALL" \
  -d "title=E2E Ethics Path: Community Health Survey Validation" \
  -d "abstract=End-to-end ethics route: human participants require ethics approval before activation." \
  -d "budget=120000" -d "duration=10" -d "start_date=$(date +%Y-%m-%d)" \
  -d "end_date=$(date -d '+10 months' +%Y-%m-%d)" -d "requires_ethics=yes" \
  -d "methodology=Cross-sectional validation survey with human participants.")
chk "application_save (ethics-path draft)" "$c" 302
APP2=$(db "SELECT id FROM research_applications WHERE title LIKE 'E2E Ethics Path%' ORDER BY id DESC LIMIT 1")
[ -n "$APP2" ] && ok "application id=$APP2 created" || { bad "ethics-path application missing"; exit 2; }
chk "requires_ethics flag" "$(db "SELECT requires_ethics FROM research_applications WHERE id=$APP2")" yes

for tc in proposal cv budget_doc work_plan; do
  T=$(tok /tmp/er.jar)
  DTID=$(db "SELECT id FROM document_types WHERE type_code='$tc'")
  c=$(up /tmp/er.jar application_upload /tmp/e2e_doc.pdf \
      -F "_csrf=$T" -F "entity_id=$APP2" -F "document_type_id=$DTID" \
      -F "title=E2E ethics $tc" -F "reason=Lifecycle test")
  chk "upload $tc" "$c" 302
done
c=$(post /tmp/er.jar application_submit -d "_csrf=$T" -d "id=$APP2")
chk "application_submit" "$c" 302
chk "SUBMITTED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" SUBMITTED

login $CC_EMAIL /tmp/ecc.jar >/dev/null
T=$(tok /tmp/ecc.jar)
post /tmp/ecc.jar screening_receive -d "_csrf=$T" -d "id=$APP2" >/dev/null
post /tmp/ecc.jar screening_decide -d "_csrf=$T" -d "id=$APP2" -d "decision=forward_to_evaluation" -d "comment=Ready for evaluation." >/dev/null
chk "screened to evaluator assignment" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" EVALUATOR_ASSIGNMENT_PENDING
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
T=$(tok /tmp/eo.jar)
post /tmp/eo.jar assign_evaluator -d "_csrf=$T" -d "id=$APP2" -d "evaluator_ids%5B%5D=$EVAL_ID" -d "deadline=$(date -d '+14 days' +%Y-%m-%d)" >/dev/null
chk "evaluator assigned" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" EVALUATOR_ASSIGNED
login evaluator@mwu.edu.et /tmp/eev.jar >/dev/null
T=$(tok /tmp/eev.jar)
c=$(post /tmp/eev.jar evaluation_submit \
  -d "_csrf=$T" -d "application_id=$APP2" \
  -d "score_$C1=90" -d "score_$C2=85" -d "score_$C3=88" \
  -d "recommendation=accept" -d "overall_comment=Approve; ethics documentation outstanding.")
chk "evaluation_submit" "$c" 302
chk "EVALUATION_COMPLETED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" EVALUATION_COMPLETED
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
fire "$APP2" "Send to college review";   chk "COLLEGE_REVIEW"          "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" COLLEGE_REVIEW
fire "$APP2" "College approves";         chk "COLLEGE_APPROVED"        "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" COLLEGE_APPROVED
fire "$APP2" "Submit to university";     chk "SUBMITTED_TO_UNIVERSITY" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" SUBMITTED_TO_UNIVERSITY
fire "$APP2" "University review starts"; chk "UNIVERSITY_REVIEW"       "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" UNIVERSITY_REVIEW

say "  -- §93/§12 gate: committee routing must wait for ethics --"
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar app_committee_assign -d "_csrf=$T" -d "application_id=$APP2" -d "committee_id=$URC")
chk "app_committee_assign (ethics pending)" "$c" 302
chk "blocked at UNIVERSITY_REVIEW (ethics pending)" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" UNIVERSITY_REVIEW
chk "committee assignment recorded" "$(db "SELECT COUNT(*) FROM application_committee_assignments WHERE application_id=$APP2 AND status='active'")" 1

# ---------- [9] Ethics submission, review and approval ----------
say "== [9] ethics chain =="
login researcher@mwu.edu.et /tmp/er.jar >/dev/null
T=$(tok /tmp/er.jar)
c=$(post /tmp/er.jar ethics_create \
  -d "_csrf=$T" -d "research_application_id=$APP2" \
  -d "participant_count=240" -d "participant_description=Household respondents and health extension workers" \
  -d "risk_level=low" -d "consent_process=Written informed consent in Amharic and Afaan Oromo" \
  -d "confidentiality_measures=Coded identifiers; encrypted storage")
chk "ethics_create" "$c" 302
ETH=$(db "SELECT id FROM ethics_applications WHERE research_application_id=$APP2")
[ -n "$ETH" ] && ok "ethics application id=$ETH" || { bad "ethics row missing"; exit 2; }
chk "ethics draft + workflow attached" "$(db "SELECT COUNT(*) FROM ethics_applications WHERE id=$ETH AND status='draft' AND workflow_id>0 AND current_stage_id>0")" 1
chk "ethics number prefix" "$(db "SELECT application_number LIKE 'MWU-RPEE-ETH-%' FROM ethics_applications WHERE id=$ETH")" 1

c=$(post /tmp/er.jar ethics_submit -d "_csrf=$T" -d "id=$ETH")
chk "ethics_submit" "$c" 302
chk "ethics SUBMITTED" "$(db "SELECT status_code FROM ethics_applications WHERE id=$ETH")" SUBMITTED

login officer@mwu.edu.et /tmp/eo.jar >/dev/null
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar ethics_screen -d "_csrf=$T" -d "id=$ETH" -d "track=full" -d "screening_note=Full review required (human participants).")
chk "ethics_screen (full track)" "$c" 302
chk "ethics UNDER_SCREENING" "$(db "SELECT status_code FROM ethics_applications WHERE id=$ETH")" UNDER_SCREENING

EREV=$(db "SELECT id FROM users WHERE email='ethics.reviewer@mwu.edu.et'")
c=$(post /tmp/eo.jar ethics_assign_save -d "_csrf=$T" -d "id=$ETH" -d "reviewer_ids%5B%5D=$EREV" -d "deadline=$(date -d '+10 days' +%Y-%m-%d)")
chk "ethics_assign" "$c" 302
chk "ethics UNDER_REVIEW" "$(db "SELECT status_code FROM ethics_applications WHERE id=$ETH")" UNDER_REVIEW

say "  -- §96 gate: decision blocked until the reviewer completes --"
T=$(tok /tmp/eo.jar)
post /tmp/eo.jar ethics_decide -d "_csrf=$T" -d "id=$ETH" -d "decision=approved" -d "comments=early" >/dev/null
chk "decide blocked (review incomplete)" "$(db "SELECT status_code FROM ethics_applications WHERE id=$ETH")" UNDER_REVIEW

RV=$(db "SELECT id FROM ethics_reviews WHERE ethics_application_id=$ETH AND reviewer_user_id=$EREV AND status IN ('assigned','accepted') ORDER BY id DESC LIMIT 1")
[ -n "$RV" ] && ok "review row id=$RV" || { bad "review row missing"; exit 2; }
login ethics.reviewer@mwu.edu.et /tmp/eth.jar >/dev/null
T=$(tok /tmp/eth.jar)
c=$(post /tmp/eth.jar ethics_review_respond -d "_csrf=$T" -d "id=$RV" -d "action_type=submit" -d "recommendation=approve" -d "comments=Consent process and confidentiality measures are adequate.")
chk "ethics_review_respond (approve)" "$c" 302
chk "review completed" "$(db "SELECT status FROM ethics_reviews WHERE id=$RV")" completed

login officer@mwu.edu.et /tmp/eo.jar >/dev/null
T=$(tok /tmp/eo.jar)
EXP=$(date -d '+1 year' +%F)
c=$(post /tmp/eo.jar ethics_decide -d "_csrf=$T" -d "id=$ETH" -d "decision=approved" -d "comments=Approved by the committee." -d "expires_at=$EXP")
chk "ethics_decide approved" "$c" 302
chk "ethics APPROVED (code)" "$(db "SELECT status_code FROM ethics_applications WHERE id=$ETH")" APPROVED
chk "ethics approved (status)" "$(db "SELECT status FROM ethics_applications WHERE id=$ETH")" approved
c=$(post /tmp/eo.jar ethics_certificate -d "_csrf=$T" -d "id=$ETH" -d "expires_at=$EXP")
chk "ethics_certificate" "$c" 302
chk "certificate issued" "$(db "SELECT COUNT(*) FROM ethics_certificates WHERE ethics_application_id=$ETH")" 1

# ---------- [10] Ethics cleared -> approve -> fund -> activate ----------
say "== [10] ethics-cleared → committee → directorate → budget → agreement → activation =="
fire "$APP2" "Ethics cleared";            chk "back to UNIVERSITY_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" UNIVERSITY_REVIEW
fire "$APP2" "Assign to research committee"; chk "UNIVERSITY_COMMITTEE_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" UNIVERSITY_COMMITTEE_REVIEW
login committee@mwu.edu.et /tmp/ecm.jar >/dev/null
T=$(tok /tmp/ecm.jar)
c=$(post /tmp/ecm.jar app_committee_decide -d "_csrf=$T" -d "application_id=$APP2" -d "decision=approve" -d "recommended_amount=115000" -d "comments=Approved with ethics clearance on file.")
chk "committee decision (ethics path)" "$c" 302
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
fire "$APP2" "Approve (directorate)";     chk "APPROVED (ethics cleared)" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" APPROVED
fire "$APP2" "Budget review starts";      chk "BUDGET_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" BUDGET_REVIEW
login director@mwu.edu.et /tmp/edi.jar >/dev/null
T=$(tok /tmp/edi.jar)
c=$(post /tmp/edi.jar application_budget -d "_csrf=$T" -d "id=$APP2" -d "approved_amount=120000" -d "note=E2E ethics-path allocation")
chk "allocation recorded (ethics path)" "$c" 302
login finance@mwu.edu.et /tmp/efi.jar >/dev/null
fire_as /tmp/efi.jar "$APP2" "Release funds"
chk "FUNDED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" FUNDED
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar agreement_issue -d "_csrf=$T" -d "application_id=$APP2" -d "terms=Ethics-path baseline agreement terms." -d "agreed_amount=120000")
chk "agreement issued (ethics path)" "$c" 302
AGR2=$(db "SELECT id FROM research_agreements WHERE application_id=$APP2")
login researcher@mwu.edu.et /tmp/er.jar >/dev/null
T=$(tok /tmp/er.jar)
c=$(post /tmp/er.jar agreement_sign -d "_csrf=$T" -d "agreement_id=$AGR2" -d "signature_name=Dr. Tigist Ayele")
chk "agreement signed (ethics path)" "$c" 302
login officer@mwu.edu.et /tmp/eo.jar >/dev/null
T=$(tok /tmp/eo.jar)
c=$(post /tmp/eo.jar project_activate -d "_csrf=$T" -d "application_id=$APP2")
chk "project_activate (ethics path)" "$c" 302
PROJ2=$(db "SELECT id FROM research_projects WHERE application_id=$APP2")
[ -n "$PROJ2" ] && ok "project id=$PROJ2" || { bad "ethics-path project missing"; exit 2; }
chk "project ACTIVE" "$(db "SELECT status_code FROM research_projects WHERE id=$PROJ2")" ACTIVE
chk "application ACTIVE" "$(db "SELECT status_code FROM research_applications WHERE id=$APP2")" ACTIVE

say ""
say "================================"
say "E2E RESULT: $PASS passed, $FAIL failed"
say "Artifacts: call=$CALL app=$APP project=$PROJ | ethics-app=$ETH app2=$APP2 project2=$PROJ2"
say "================================"
[ "$FAIL" -eq 0 ]

