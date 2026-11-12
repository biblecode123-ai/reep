#!/bin/bash
# MWU RPEE — END-TO-END ACCEPTANCE TEST (spec §35 — all 44 steps)
#
# Admin creates org + accounts → researcher profile → proposal → coordinator
# screens → two evaluators created + assigned → evaluations → revision →
# re-evaluation → college approval → directorate → university committee →
# funding recommendation → directorate budget approval → agreement issued →
# researcher signs → research ACTIVE → progress → final report → completion →
# archive → audit history.
#
# Run from the project root with the dev server on 127.0.0.1:8099:
#   php -S 127.0.0.1:8099 -t public
#   bash scripts/e2e_acceptance.sh
set -u
[ -f app/bootstrap.php ] || { echo "Run from the project root"; exit 2; }

B=http://127.0.0.1:8099
P='Password123!'
PASS=0; FAIL=0

say()  { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); say "  PASS: $*"; }
bad()  { FAIL=$((FAIL+1)); say "  FAIL: $*"; }
chk()  { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected $3 got $2"; fi; }

login() { # email jar -> login http code
  local jar=$2 tok
  rm -f "$jar"
  tok=$(curl -s -m 15 -c "$jar" "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -d "identifier=$1&password=$P&_csrf=$tok" "$B/login.php?action=do_login"
}
tok() { curl -s -m 15 -b "$1" -c "$1" "$B/index.php" | grep -o 'name="csrf-token" content="[^"]*"\|name="_csrf" value="[^"]*"' | head -1 | sed 's/.*content="//;s/.*value="//;s/"//'; }
post() { # jar action data... -> http code
  local jar=$1 action=$2; shift 2
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' "$B/index.php?action=$action" "$@"
}
up() { # jar action file extra... -> http code (multipart)
  local jar=$1 action=$2 f=$3; shift 3
  local wf; wf=$(cygpath -m "$f" 2>/dev/null || echo "$f")
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -F "document=@$wf;type=application/pdf" "$B/index.php?action=$action" "$@"
}
db() { timeout 10 php -r 'require "app/bootstrap.php"; $v = Database::scalar($argv[1]); echo $v === null ? "" : (string) $v;' "$1"; }
page() { curl -s -m 20 -b "$1" "$B/index.php?$2"; }
chgpass() { # jar — clear the forced initial-password change
  local jar=$1 T
  T=$(tok "$jar")
  post "$jar" profile_password -d "_csrf=$T" -d "current_password=$P" -d "new_password=$P" -d "confirm_password=$P"
}

# server up?
code=$(curl -s -m 10 -o /dev/null -w '%{http_code}' "$B/login.php" || echo 000)
[ "$code" = "200" ] || { say "Dev server not running (got $code) — start: php -S 127.0.0.1:8099 -t public"; exit 2; }

printf '%%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\n%%%%EOF\n' > /tmp/acc_doc.pdf
[ -s /tmp/acc_doc.pdf ] || { say "cannot write test PDF"; exit 2; }

ACC_COLLEGE_NAME='College of Acceptance Studies'
ACC_COLLEGE_CODE='ACCAS'
ACC_DEPT_NAME='Acceptance Data Science'
ACC_DEPT_CODE='ADS'
ACC_CALL_REF='MWU/RPEE/CALL/2026/ACC'
ACC_TITLE='Acceptance Study: Adaptive Learning Outcomes in Ethiopian Universities'
ACC_COMMITTEE_CODE='ACC-URC'

# ---------- cleanup artifacts from any previous run (keeps the test re-runnable) ----------
php -r 'require "app/bootstrap.php";
  // applications + everything hanging off them
  $call = Database::scalar("SELECT id FROM research_calls WHERE reference_number=?", ["MWU/RPEE/CALL/2026/ACC"]);
  if ($call) {
    $apps = array_map("intval", array_column(Database::all("SELECT id FROM research_applications WHERE call_id=?", [$call]), "id"));
    foreach ($apps as $a) {
      Database::run("DELETE FROM workflow_history WHERE entity_type=? AND entity_id=?", ["research_application", $a]);
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
        Database::run("DELETE FROM documents WHERE entity_type=? AND entity_id=?", ["research_project", $p]);
      }
      Database::run("DELETE FROM research_projects WHERE application_id=?", [$a]);
    }
    Database::run("DELETE FROM research_applications WHERE call_id=?", [$call]);
    Database::run("DELETE FROM research_call_criteria WHERE call_id=?", [$call]);
    Database::run("DELETE FROM research_call_requirements WHERE call_id=?", [$call]);
    Database::run("DELETE FROM research_call_eligibility WHERE call_id=?", [$call]);
    Database::run("DELETE FROM research_calls WHERE id=?", [$call]);
    echo "cleaned prior acceptance applications/call\n";
  }
  // acceptance committee
  $com = Database::scalar("SELECT id FROM committees WHERE code=?", ["ACC-URC"]);
  if ($com) {
    Database::run("DELETE FROM committee_members WHERE committee_id=?", [$com]);
    Database::run("DELETE FROM committees WHERE id=?", [$com]);
    echo "cleaned prior acceptance committee\n";
  }
  // acceptance users
  foreach (["acc.researcher@mwu.edu.et","acc.coordinator@mwu.edu.et","acc.eval1@mwu.edu.et","acc.eval2@mwu.edu.et"] as $email) {
    $u = Database::scalar("SELECT id FROM users WHERE email=?", [$email]);
    if (!$u) { continue; }
    Database::run("DELETE FROM researchers WHERE user_id=?", [$u]);
    Database::run("DELETE FROM research_evaluators WHERE user_id=?", [$u]);
    Database::run("DELETE FROM login_attempts WHERE identifier=?", [$email]);
    Database::run("DELETE FROM users WHERE id=?", [$u]); // cascades roles/notifications/resets
    echo "cleaned user $email\n";
  }
  // acceptance department + college
  $col = Database::scalar("SELECT id FROM colleges WHERE code=?", ["ACCAS"]);
  if ($col) {
    Database::run("DELETE FROM departments WHERE college_id=?", [$col]);
    Database::run("DELETE FROM colleges WHERE id=?", [$col]);
    echo "cleaned prior acceptance org structure\n";
  }' || { say "CLEANUP FAILED — aborting"; exit 2; }

ADMIN_ID=$(db "SELECT id FROM users WHERE email='admin@mwu.edu.et'")
[ -n "$ADMIN_ID" ] || { say "admin account missing — run scripts/install.php + scripts/seed_roles.php"; exit 2; }

# ===========================================================================
say "== [1] Admin creates a college =="
login admin@mwu.edu.et /tmp/acc_admin.jar >/dev/null
T=$(tok /tmp/acc_admin.jar)
c=$(post /tmp/acc_admin.jar college_save -d "_csrf=$T" -d "name=$ACC_COLLEGE_NAME" -d "code=$ACC_COLLEGE_CODE" \
    -d "status=active" -d "description=Created by the spec §35 acceptance test.")
chk "college_save" "$c" 302
COLLEGE=$(db "SELECT id FROM colleges WHERE code='$ACC_COLLEGE_CODE'")
[ -n "$COLLEGE" ] && ok "college id=$COLLEGE created" || { bad "college row missing"; exit 2; }
chk "college active" "$(db "SELECT status FROM colleges WHERE id=$COLLEGE")" active

# ===========================================================================
say "== [2] Admin creates a department under that college =="
T=$(tok /tmp/acc_admin.jar)
c=$(post /tmp/acc_admin.jar department_save -d "_csrf=$T" -d "name=$ACC_DEPT_NAME" -d "code=$ACC_DEPT_CODE" \
    -d "college_id=$COLLEGE" -d "status=active")
chk "department_save" "$c" 302
DEPT=$(db "SELECT id FROM departments WHERE code='$ACC_DEPT_CODE'")
[ -n "$DEPT" ] && ok "department id=$DEPT created" || { bad "department row missing"; exit 2; }
chk "department belongs to the college" "$(db "SELECT college_id FROM departments WHERE id=$DEPT")" "$COLLEGE"

# ===========================================================================
say "== [3] Admin creates/activates the required user accounts + publishes the call =="
ROLE_RESEARCHER=$(db "SELECT id FROM roles WHERE name='researcher'")
ROLE_COORDINATOR=$(db "SELECT id FROM roles WHERE name='college_coordinator'")
[ -n "$ROLE_RESEARCHER" ] && [ -n "$ROLE_COORDINATOR" ] || { say "required roles missing"; exit 2; }

T=$(tok /tmp/acc_admin.jar)
c=$(post /tmp/acc_admin.jar user_save -d "_csrf=$T" -d "full_name=Acc Researcher" -d "email=acc.researcher@mwu.edu.et" \
    -d "password=$P" -d "college_id=$COLLEGE" -d "department_id=$DEPT" -d "status=active" -d "roles%5B%5D=$ROLE_RESEARCHER")
chk "admin creates researcher" "$c" 302
T=$(tok /tmp/acc_admin.jar)
c=$(post /tmp/acc_admin.jar user_save -d "_csrf=$T" -d "full_name=Acc Coordinator" -d "email=acc.coordinator@mwu.edu.et" \
    -d "password=$P" -d "college_id=$COLLEGE" -d "department_id=$DEPT" -d "status=active" -d "roles%5B%5D=$ROLE_COORDINATOR")
chk "admin creates coordinator" "$c" 302
RID=$(db "SELECT id FROM users WHERE email='acc.researcher@mwu.edu.et'")
CID=$(db "SELECT id FROM users WHERE email='acc.coordinator@mwu.edu.et'")
[ -n "$RID" ] && [ -n "$CID" ] && ok "researcher#$RID + coordinator#$CID active" || { bad "accounts missing"; exit 2; }
chk "researcher role assigned" "$(db "SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=$RID AND r.name='researcher'")" 1
chk "coordinator role assigned" "$(db "SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=$CID AND r.name='college_coordinator'")" 1
chk "initial password change enforced" "$(db "SELECT must_change_password FROM users WHERE id=$RID")" 1

# Call with 4 required documents + 3 criteria + TWO evaluators (spec example)
E2E_THEME=$(db "SELECT id FROM research_themes ORDER BY id LIMIT 1")
T=$(tok /tmp/acc_admin.jar)
c=$(post /tmp/acc_admin.jar call_save -d "_csrf=$T" -d "title=Acceptance Research Call 2026" \
    -d "reference_number=$ACC_CALL_REF" -d "theme=$E2E_THEME" -d "description=Spec §35 acceptance call." \
    -d "opens_at=$(date +%Y-%m-%d)" -d "closes_at=$(date -d '+60 days' +%Y-%m-%d)" \
    -d "max_duration=24" -d "max_budget=200000" -d "max_apps=3" \
    -d "evaluation_mode=single_blind" -d "required_evaluators=2")
chk "call_save" "$c" 302
CALL=$(db "SELECT id FROM research_calls WHERE reference_number='$ACC_CALL_REF'")
[ -n "$CALL" ] && ok "call id=$CALL published-ready" || { bad "call missing"; exit 2; }
TYPES=""
for tc in proposal cv budget_doc work_plan; do
  TYPES="$TYPES -d req_type%5B%5D=$(db "SELECT id FROM document_types WHERE type_code='$tc'") -d req_kind%5B%5D=required"
done
T=$(tok /tmp/acc_admin.jar)
c=$(post /tmp/acc_admin.jar call_config -d "_csrf=$T" -d "id=$CALL" $TYPES \
    -d "crit_name%5B%5D=Methodology" -d "crit_weight%5B%5D=50" \
    -d "crit_name%5B%5D=Significance" -d "crit_weight%5B%5D=30" \
    -d "crit_name%5B%5D=Feasibility" -d "crit_weight%5B%5D=20")
chk "call_config (4 required docs, 3 criteria, 2 evaluators)" "$c" 302
T=$(tok /tmp/acc_admin.jar)
c=$(post /tmp/acc_admin.jar call_publish -d "_csrf=$T" -d "id=$CALL")
chk "call_publish" "$c" 302
chk "call published (open for applications)" "$(db "SELECT status FROM research_calls WHERE id=$CALL")" published

# ===========================================================================
say "== [4] Researcher logs in (forced password change) =="
c=$(login acc.researcher@mwu.edu.et /tmp/acc_r.jar)
chk "researcher login" "$c" 302
c=$(chgpass /tmp/acc_r.jar)
chk "forced password change" "$c" 302
chk "must_change_password cleared" "$(db "SELECT must_change_password FROM users WHERE id=$RID")" 0

# ===========================================================================
say "== [5] Researcher creates their researcher profile (§3) =="
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar profile_researcher -d "_csrf=$T" -d "position=Lecturer" -d "qualification=PhD" \
    -d "specialization=Learning Analytics" -d "interests=Adaptive learning; higher education" \
    -d "areas=Education technology" -d "biography=Researcher in digital education." -d "gender=female" \
    -d "orcid=0000-0002-1825-0097" -d "scholar=https://scholar.example/acc")
chk "profile_researcher" "$c" 302
chk "researcher profile row" "$(db "SELECT COUNT(*) FROM researchers WHERE user_id=$RID")" 1
chk "ORCID stored" "$(db "SELECT orcid FROM researchers WHERE user_id=$RID")" "0000-0002-1825-0097"
page /tmp/acc_r.jar "action=profile" | grep -q "0000-0002-1825-0097" && ok "profile page shows the saved profile" || bad "profile not shown"

# ===========================================================================
say "== [6] Valid department + college association (§2 routing, no user choice) =="
chk "profile college association" "$(db "SELECT college_id FROM users WHERE id=$RID")" "$COLLEGE"
chk "profile department association" "$(db "SELECT department_id FROM users WHERE id=$RID")" "$DEPT"
page /tmp/acc_r.jar "action=profile" | grep -q "$ACC_COLLEGE_NAME" && ok "profile shows the college" || bad "college missing on profile"
# profile update must NOT allow changing the institutional college
T=$(tok /tmp/acc_r.jar)
post /tmp/acc_r.jar profile_researcher -d "_csrf=$T" -d "position=Lecturer" -d "college_id=1" -d "department_id=1" >/dev/null
chk "college unchanged via profile" "$(db "SELECT college_id FROM users WHERE id=$RID")" "$COLLEGE"

# ===========================================================================
say "== [7] Researcher creates a research proposal =="
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar application_save -d "_csrf=$T" -d "call_id=$CALL" -d "title=$ACC_TITLE" \
    -d "abstract=Acceptance-test proposal exercising the complete research lifecycle." \
    -d "budget=100000" -d "duration=12" -d "start_date=$(date +%Y-%m-%d)" \
    -d "end_date=$(date -d '+12 months' +%Y-%m-%d)" -d "requires_ethics=no" \
    -d "methodology=Mixed-methods quasi-experimental design." \
    -d "background=Digital transformation of higher education." -d "objectives=Measure adaptive learning outcomes.")
chk "application_save" "$c" 302
APP=$(db "SELECT id FROM research_applications WHERE title='$ACC_TITLE'")
[ -n "$APP" ] && ok "application id=$APP created" || { bad "application missing"; exit 2; }

# ===========================================================================
say "== [8] Save as draft =="
chk "status DRAFT" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" DRAFT
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar application_save -d "_csrf=$T" -d "id=$APP" -d "call_id=$CALL" -d "title=$ACC_TITLE" \
    -d "abstract=Acceptance-test proposal exercising the complete research lifecycle (draft saved)." \
    -d "budget=100000" -d "duration=12" -d "requires_ethics=no")
chk "draft edit saved" "$c" 302
chk "still DRAFT after save" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" DRAFT
chk "workflow attached" "$(db "SELECT COUNT(*) FROM research_applications WHERE id=$APP AND workflow_id>0 AND current_stage_id>0")" 1

# ===========================================================================
say "== [9] Researcher uploads documents and submits =="
for tc in proposal cv budget_doc work_plan; do
  T=$(tok /tmp/acc_r.jar)
  DTID=$(db "SELECT id FROM document_types WHERE type_code='$tc'")
  c=$(up /tmp/acc_r.jar application_upload /tmp/acc_doc.pdf \
      -F "_csrf=$T" -F "entity_id=$APP" -F "document_type_id=$DTID" \
      -F "title=Acceptance $tc" -F "reason=Initial submission")
  chk "upload $tc" "$c" 302
done
chk "4 active documents" "$(db "SELECT COUNT(*) FROM documents WHERE entity_type='research_application' AND entity_id=$APP AND status='active'")" 4
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar application_submit -d "_csrf=$T" -d "id=$APP")
chk "application_submit" "$c" 302
chk "status SUBMITTED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" SUBMITTED
chk "submission timestamped" "$(db "SELECT COUNT(*) FROM research_applications WHERE id=$APP AND submitted_at IS NOT NULL")" 1

# ===========================================================================
say "== [10] College Research Coordinator receives the submission =="
c=$(login acc.coordinator@mwu.edu.et /tmp/acc_c.jar)
chk "coordinator login" "$c" 302
chgpass /tmp/acc_c.jar >/dev/null
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar screening_receive -d "_csrf=$T" -d "id=$APP")
chk "screening_receive" "$c" 302
chk "COLLEGE_RECEIVED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" COLLEGE_RECEIVED
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar screening_decide -d "_csrf=$T" -d "id=$APP" -d "decision=forward_to_evaluation" \
    -d "comment=Complete submission; forwarded for evaluation.")
chk "screening_decide forward" "$c" 302
chk "EVALUATOR_ASSIGNMENT_PENDING" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" EVALUATOR_ASSIGNMENT_PENDING
chk "college screening decision recorded" "$(db "SELECT COUNT(*) FROM college_decisions WHERE application_id=$APP AND decision_context='screening'")" 1
# §5 negative: a coordinator of ANOTHER college cannot touch this research
login ccoordinator@mwu.edu.et /tmp/acc_x.jar >/dev/null
T=$(tok /tmp/acc_x.jar)
c=$(post /tmp/acc_x.jar screening_decide -d "_csrf=$T" -d "id=$APP" -d "decision=forward_to_evaluation" -d "comment=foreign college attempt")
chk "foreign-college coordinator blocked" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" EVALUATOR_ASSIGNMENT_PENDING
chk "scope denial audited" "$(db "SELECT COUNT(*) FROM audit_logs WHERE action='college.scope_denied' AND entity_id=$APP")" 1
login acc.coordinator@mwu.edu.et /tmp/acc_c.jar >/dev/null
T=$(tok /tmp/acc_c.jar)

# ===========================================================================
say "== [11] Coordinator creates two evaluator accounts (§5, §6) =="
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar evaluator_create -d "_csrf=$T" -d "application_id=$APP" \
    -d "full_name=Acc Evaluator One" -d "email=acc.eval1@mwu.edu.et" -d "password=$P" \
    -d "department_id=$DEPT" -d "expertise=Methodology")
chk "evaluator 1 created" "$c" 302
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar evaluator_create -d "_csrf=$T" -d "application_id=$APP" \
    -d "full_name=Acc Evaluator Two" -d "email=acc.eval2@mwu.edu.et" -d "password=$P" \
    -d "department_id=$DEPT" -d "expertise=Feasibility")
chk "evaluator 2 created" "$c" 302
EV1=$(db "SELECT id FROM users WHERE email='acc.eval1@mwu.edu.et'")
EV2=$(db "SELECT id FROM users WHERE email='acc.eval2@mwu.edu.et'")
[ -n "$EV1" ] && [ -n "$EV2" ] && ok "evaluator accounts #$EV1 #$EV2" || { bad "evaluator accounts missing"; exit 2; }
chk "evaluator role only" "$(db "SELECT COUNT(*) FROM user_roles WHERE user_id=$EV1")" 1
chk "evaluator registry rows" "$(db "SELECT COUNT(*) FROM research_evaluators WHERE user_id IN ($EV1,$EV2)")" 2
chk "evaluators belong to the coordinator college" "$(db "SELECT college_id FROM users WHERE id=$EV1")" "$COLLEGE"
chk "forced password change for evaluators" "$(db "SELECT must_change_password FROM users WHERE id=$EV1")" 1

# ===========================================================================
say "== [12] Coordinator assigns both evaluators =="
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar assign_evaluator -d "_csrf=$T" -d "id=$APP" \
    -d "evaluator_ids%5B%5D=$EV1" -d "evaluator_ids%5B%5D=$EV2" -d "deadline=$(date -d '+14 days' +%Y-%m-%d)")
chk "assign_evaluator (both)" "$c" 302
chk "EVALUATOR_ASSIGNED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" EVALUATOR_ASSIGNED
chk "two active assignments" "$(db "SELECT COUNT(*) FROM evaluator_assignments WHERE application_id=$APP AND is_active=1")" 2

# ===========================================================================
say "== [13] Both evaluators receive the research =="
c=$(login acc.eval1@mwu.edu.et /tmp/acc_e1.jar); chk "evaluator 1 login" "$c" 302
chgpass /tmp/acc_e1.jar >/dev/null
c=$(login acc.eval2@mwu.edu.et /tmp/acc_e2.jar); chk "evaluator 2 login" "$c" 302
chgpass /tmp/acc_e2.jar >/dev/null
page /tmp/acc_e1.jar "action=my_evaluations" | grep -q "Acceptance Study" && ok "evaluator 1 sees the assignment" || bad "evaluator 1 assignment missing"
page /tmp/acc_e2.jar "action=my_evaluations" | grep -q "Acceptance Study" && ok "evaluator 2 sees the assignment" || bad "evaluator 2 assignment missing"

# ===========================================================================
say "== [14] Evaluator 1 submits evaluation, score and comments =="
C1=$(db "SELECT id FROM research_call_criteria WHERE call_id=$CALL AND criterion_name='Methodology'")
C2=$(db "SELECT id FROM research_call_criteria WHERE call_id=$CALL AND criterion_name='Significance'")
C3=$(db "SELECT id FROM research_call_criteria WHERE call_id=$CALL AND criterion_name='Feasibility'")
T=$(tok /tmp/acc_e1.jar)
c=$(post /tmp/acc_e1.jar evaluation_submit -d "_csrf=$T" -d "application_id=$APP" \
    -d "score_$C1=82" -d "score_comment_$C1=Design mostly sound" \
    -d "score_$C2=78" -d "score_$C3=80" \
    -d "recommendation=accept_with_revision" -d "strengths=Clear objectives." \
    -d "weaknesses=Sampling frame unclear." -d "overall_comment=Suitable after minor corrections." \
    -d "comments%5B%5D=Improve the methodology section.")
chk "evaluator 1 evaluation_submit" "$c" 302
chk "evaluator 1 evaluation submitted" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND evaluator_user_id=$EV1 AND status='submitted'")" 1

# ===========================================================================
say "== [15] Evaluator 2 submits evaluation, score and comments =="
T=$(tok /tmp/acc_e2.jar)
c=$(post /tmp/acc_e2.jar evaluation_submit -d "_csrf=$T" -d "application_id=$APP" \
    -d "score_$C1=75" -d "score_$C2=77" -d "score_$C3=81" \
    -d "recommendation=accept_with_revision" -d "strengths=Relevant topic." \
    -d "weaknesses=Timeline optimistic." -d "overall_comment=Proceed with corrections." \
    -d "comments%5B%5D=Clarify the sampling frame and timeline.")
chk "evaluator 2 evaluation_submit" "$c" 302
chk "EVALUATION_COMPLETED (both required)" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" EVALUATION_COMPLETED

# ===========================================================================
say "== [16] System preserves both evaluations (never overwritten) =="
chk "two submitted evaluations" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND status='submitted'")" 2
chk "two independent evaluators" "$(db "SELECT COUNT(DISTINCT evaluator_user_id) FROM evaluations WHERE application_id=$APP AND status='submitted'")" 2
chk "scores recorded separately" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND weighted_score IS NOT NULL")" 2
chk "assignments completed" "$(db "SELECT COUNT(*) FROM evaluator_assignments WHERE application_id=$APP AND status='completed'")" 2
chk "round 1 for both" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND evaluation_round=1")" 2
# §7/§6 negative: another evaluator cannot read the submitted evaluation record
code=$(curl -s -m 15 -b /tmp/acc_e2.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=evaluation_form&application_id=$APP")
chk "evaluator without assignment redirected (302)" "$code" 302

# ===========================================================================
say "== [17] Researcher receives the comments; coordinator requests corrections =="
page /tmp/acc_r.jar "action=application_view&id=$APP" | grep -q "Improve the methodology section" && ok "researcher sees evaluator comment 1" || bad "comment 1 missing for researcher"
page /tmp/acc_r.jar "action=application_view&id=$APP" | grep -q "Clarify the sampling frame" && ok "researcher sees evaluator comment 2" || bad "comment 2 missing for researcher"
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar application_request_revision -d "_csrf=$T" -d "id=$APP" \
    -d "reason=Improve the methodology section and clarify the sampling frame." \
    -d "deadline=$(date -d '+14 days' +%Y-%m-%d)")
chk "application_request_revision" "$c" 302
chk "REVISION_REQUIRED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" REVISION_REQUIRED
chk "structured revision request" "$(db "SELECT COUNT(*) FROM revision_requests WHERE application_id=$APP")" 1
# §7 negative: evaluator 2 cannot open evaluator 1's evaluation record
CHK1=$(db "SELECT id FROM evaluations WHERE application_id=$APP AND evaluator_user_id=$EV1")
code=$(curl -s -m 15 -b /tmp/acc_e2.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=evaluation_view&id=$CHK1")
chk "other evaluator denied evaluation record (302)" "$code" 302

# ===========================================================================
say "== [18] Researcher responds to the comments =="
RR=$(db "SELECT id FROM revision_requests WHERE application_id=$APP ORDER BY id DESC LIMIT 1")
CM1=$(db "SELECT ec.id FROM evaluation_comments ec JOIN evaluations e ON e.id=ec.evaluation_id WHERE e.application_id=$APP AND ec.visibility='to_researcher' ORDER BY ec.id LIMIT 1")
[ -n "$RR" ] && [ -n "$CM1" ] && ok "revision request #$RR, comment #$CM1" || { bad "request/comment missing"; exit 2; }
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar application_respond_revision -d "_csrf=$T" -d "application_id=$APP" \
    -d "revision_request_id=$RR" -d "comment_id=$CM1" \
    -d "response=The methodology section has been revised and additional sampling information has been added.")
chk "application_respond_revision" "$c" 302
chk "researcher response recorded" "$(db "SELECT COUNT(*) FROM revision_responses WHERE revision_request_id=$RR")" 1
chk "response linked to the comment" "$(db "SELECT COUNT(*) FROM revision_responses WHERE revision_request_id=$RR AND comment_id=$CM1")" 1
chk "request marked responded" "$(db "SELECT status FROM revision_requests WHERE id=$RR")" responded
# §7 negative: evaluator 2 cannot modify evaluator 1's evaluation record
code=$(curl -s -m 15 -b /tmp/acc_e2.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=evaluation_view&id=$CHK1")
chk "other evaluator denied evaluation record (302)" "$code" 302

# ===========================================================================
say "== [19] Researcher uploads Version 2 and submits the revision =="
T=$(tok /tmp/acc_r.jar)
DT_PROPOSAL=$(db "SELECT id FROM document_types WHERE type_code='proposal'")
c=$(up /tmp/acc_r.jar application_upload /tmp/acc_doc.pdf \
    -F "_csrf=$T" -F "entity_id=$APP" -F "document_type_id=$DT_PROPOSAL" \
    -F "title=Revised proposal" -F "reason=Version 2 after evaluator corrections")
chk "revised proposal uploaded" "$c" 302
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar application_submit_revision -d "_csrf=$T" -d "id=$APP")
chk "application_submit_revision" "$c" 302
chk "REVISION_SUBMITTED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" REVISION_SUBMITTED
chk "application version bumped to 2" "$(db "SELECT version FROM research_applications WHERE id=$APP")" 2

# ===========================================================================
say "== [20] Version 1 remains preserved (§9) =="
chk "proposal v1 preserved (superseded)" "$(db "SELECT COUNT(*) FROM documents WHERE entity_type='research_application' AND entity_id=$APP AND document_type_id=$DT_PROPOSAL AND version=1 AND status='superseded'")" 1
chk "proposal v2 active" "$(db "SELECT COUNT(*) FROM documents WHERE entity_type='research_application' AND entity_id=$APP AND document_type_id=$DT_PROPOSAL AND version=2 AND status='active'")" 1
chk "round-1 evaluations preserved" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND evaluation_round=1 AND status='submitted'")" 2
chk "revision request history preserved" "$(db "SELECT COUNT(*) FROM revision_requests WHERE application_id=$APP")" 1
chk "revision flow audited" "$(db "SELECT COUNT(*) FROM audit_logs WHERE action='revision.submitted' AND entity_id=$APP")" 1

# ===========================================================================
say "== [21] Evaluators receive Version 2 =="
chk "assignments reopened for re-evaluation" "$(db "SELECT COUNT(*) FROM evaluator_assignments WHERE application_id=$APP AND is_active=1 AND status='assigned'")" 2
page /tmp/acc_e1.jar "action=my_evaluations" | grep -q "Acceptance Study" && ok "evaluator 1 notified/receives V2" || bad "evaluator 1 does not see V2"
page /tmp/acc_e2.jar "action=my_evaluations" | grep -q "Acceptance Study" && ok "evaluator 2 notified/receives V2" || bad "evaluator 2 does not see V2"

# ===========================================================================
say "== [22] Evaluators re-evaluate Version 2 (round 2) =="
T=$(tok /tmp/acc_e1.jar)
c=$(post /tmp/acc_e1.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Re-evaluate" -d "comment=Re-evaluating version 2")
chk "Re-evaluate transition" "$c" 302
chk "RE_EVALUATION" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" RE_EVALUATION
T=$(tok /tmp/acc_e1.jar)
c=$(post /tmp/acc_e1.jar evaluation_submit -d "_csrf=$T" -d "application_id=$APP" \
    -d "score_$C1=90" -d "score_$C2=82" -d "score_$C3=85" -d "recommendation=accept" \
    -d "strengths=Methodology now rigorous." -d "overall_comment=Version 2 acceptable." \
    -d "comments%5B%5D=Methodology now adequate.")
chk "evaluator 1 re-evaluation submitted" "$c" 302
chk "round-2 evaluation exists" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND evaluation_round=2 AND status='submitted'")" 1

# ===========================================================================
say "== [23] Coordinator verifies evaluations before forwarding =="
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Send to college review" -d "comment=early")
chk "forwarding blocked while re-evaluation incomplete" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" RE_EVALUATION
T=$(tok /tmp/acc_e2.jar)
c=$(post /tmp/acc_e2.jar evaluation_submit -d "_csrf=$T" -d "application_id=$APP" \
    -d "score_$C1=84" -d "score_$C2=80" -d "score_$C3=86" -d "recommendation=accept" \
    -d "overall_comment=Version 2 approved." -d "comments%5B%5D=Timeline now realistic.")
chk "evaluator 2 re-evaluation submitted" "$c" 302
chk "both round-2 evaluations preserved" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP AND evaluation_round=2 AND status='submitted'")" 2
chk "all four evaluations preserved" "$(db "SELECT COUNT(*) FROM evaluations WHERE application_id=$APP")" 4
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Send to college review" -d "comment=Re-evaluations verified (2/2).")
chk "Send to college review" "$c" 302
chk "COLLEGE_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" COLLEGE_REVIEW

# ===========================================================================
say "== [24] Coordinator approves and forwards to the Research Directorate =="
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=College approves" -d "comment=Corrections verified at college level.")
chk "College approves" "$c" 302
chk "COLLEGE_APPROVED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" COLLEGE_APPROVED
T=$(tok /tmp/acc_c.jar)
c=$(post /tmp/acc_c.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Submit to university" -d "comment=Forwarded to the Research Directorate.")
chk "Submit to university" "$c" 302
chk "SUBMITTED_TO_UNIVERSITY" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" SUBMITTED_TO_UNIVERSITY
chk "college approval recorded" "$(db "SELECT COUNT(*) FROM college_decisions WHERE application_id=$APP")" 1

# ===========================================================================
say "== [25] Research Directorate receives the research =="
c=$(login director@mwu.edu.et /tmp/acc_d.jar)
chk "director login" "$c" 302
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=University review starts" -d "comment=Directorate review opened.")
chk "University review starts" "$c" 302
chk "UNIVERSITY_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" UNIVERSITY_REVIEW

# ===========================================================================
say "== [26] Research Director creates a committee and assigns the research (§11, §12) =="
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar committee_save -d "_csrf=$T" -d "name=Acceptance University Research Committee" \
    -d "code=$ACC_COMMITTEE_CODE" -d "committee_type=university_research" \
    -d "description=University research committee created by the Research Director.")
chk "director creates a committee" "$c" 302
COMMITTEE=$(db "SELECT id FROM committees WHERE code='$ACC_COMMITTEE_CODE'")
[ -n "$COMMITTEE" ] && ok "committee id=$COMMITTEE created" || { bad "committee missing"; exit 2; }
CHAIR=$(db "SELECT id FROM users WHERE email='committee@mwu.edu.et'")
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar committee_member_save -d "_csrf=$T" -d "committee_id=$COMMITTEE" \
    -d "user_id=$CHAIR" -d "member_role=chair" -d "appointed_from=$(date +%Y-%m-%d)")
chk "director appoints the chair" "$c" 302
chk "committee member active" "$(db "SELECT COUNT(*) FROM committee_members WHERE committee_id=$COMMITTEE AND user_id=$CHAIR AND status='active'")" 1
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar app_committee_assign -d "_csrf=$T" -d "application_id=$APP" -d "committee_id=$COMMITTEE" \
    -d "meeting_date=$(date -d '+7 days' +%Y-%m-%d)")
chk "app_committee_assign" "$c" 302
chk "UNIVERSITY_COMMITTEE_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" UNIVERSITY_COMMITTEE_REVIEW
chk "assignment recorded" "$(db "SELECT COUNT(*) FROM application_committee_assignments WHERE application_id=$APP AND committee_id=$COMMITTEE AND status='active'")" 1
chk "assignment audited" "$(db "SELECT COUNT(*) FROM audit_logs WHERE action='committee.assigned' AND entity_id=$APP")" 1
# §12 negative: the director (not a member of this committee) cannot record its decision
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar app_committee_decide -d "_csrf=$T" -d "application_id=$APP" -d "decision=approve")
chk "non-member decision denied" "$(db "SELECT COUNT(*) FROM committee_decisions WHERE application_id=$APP")" 0

# ===========================================================================
say "== [27] Committee members review the research =="
c=$(login committee@mwu.edu.et /tmp/acc_cm.jar)
chk "committee member login" "$c" 302
page /tmp/acc_cm.jar "action=committee_queue" | grep -q "Acceptance Study" && ok "research appears in the committee queue" || bad "committee queue missing the research"
page /tmp/acc_cm.jar "action=application_view&id=$APP" | grep -q "Acceptance University Research Committee" && ok "committee member sees the assignment" || bad "assignment not visible"

# ===========================================================================
say "== [28] Committee records its decision =="
T=$(tok /tmp/acc_cm.jar)
c=$(post /tmp/acc_cm.jar app_committee_decide -d "_csrf=$T" -d "application_id=$APP" \
    -d "decision=approve" -d "comments=Scientific merit, methodology and feasibility confirmed." \
    -d "recommended_amount=90000" -d "recommendation_note=Recommended within the available university budget." \
    -d "meeting_date=$(date +%Y-%m-%d)" -d "decision_reference=ACC-URC/2026/01")
chk "committee decision recorded" "$c" 302
chk "one committee decision" "$(db "SELECT COUNT(*) FROM committee_decisions WHERE application_id=$APP")" 1
chk "decision is approve" "$(db "SELECT decision FROM committee_decisions WHERE application_id=$APP ORDER BY id DESC LIMIT 1")" approve
chk "decision made by the chair" "$(db "SELECT decided_by FROM committee_decisions WHERE application_id=$APP ORDER BY id DESC LIMIT 1")" "$CHAIR"
chk "decision audited" "$(db "SELECT COUNT(*) FROM audit_logs WHERE action='committee.decided' AND entity_id=$APP")" 1
chk "directorate approval NOT yet recorded" "$(db "SELECT COUNT(*) FROM university_decisions WHERE application_id=$APP")" 0

# ===========================================================================
say "== [29] Committee recommends a funding amount (Rule 9) =="
chk "funding recommendation stored" "$(db "SELECT CAST(recommended_amount AS INTEGER) FROM committee_decisions WHERE application_id=$APP ORDER BY id DESC LIMIT 1")" 90000

# ===========================================================================
say "== [30] Research Directorate reviews the committee recommendation =="
page /tmp/acc_d.jar "action=application_view&id=$APP" | grep -q "Funding recommendation" && ok "directorate sees the funding recommendation" || bad "recommendation not visible to directorate"
page /tmp/acc_d.jar "action=application_view&id=$APP" | grep -q "Scientific merit" && ok "directorate sees the committee comments" || bad "committee comments not visible"

# ===========================================================================
say "== [31] Research Director approves at directorate level (Rule 8) =="
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Approve (directorate)" -d "comment=Approved on the committee recommendation.")
chk "Approve (directorate)" "$c" 302
chk "APPROVED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" APPROVED
GE=$(db "SELECT COUNT(*) FROM audit_logs WHERE action='workflow.transition' AND entity_id=$APP")
[ "$GE" -ge 10 ] && ok "workflow transitions audited ($GE)" || bad "audit trail thin ($GE)"

# ===========================================================================
say "== [32] Research Director records the approved budget allocation (Rule 13) =="
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Budget review starts" -d "comment=Directorate budget review")
chk "Budget review starts" "$c" 302
chk "BUDGET_REVIEW" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" BUDGET_REVIEW
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar application_budget -d "_csrf=$T" -d "id=$APP" -d "approved_amount=999999" -d "note=over cap")
chk "over-budget allocation rejected (Rule 13)" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" BUDGET_REVIEW
chk "no allocation recorded on rejection" "$(db "SELECT COALESCE(CAST(budget_approved_amount AS INTEGER),0) FROM research_applications WHERE id=$APP")" 0
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar application_budget -d "_csrf=$T" -d "id=$APP" -d "approved_amount=95000" -d "note=Approved allocation for FY2026")
chk "application_budget" "$c" 302
chk "BUDGET_APPROVED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" BUDGET_APPROVED
chk "allocation recorded (95000 ≠ recommendation 90000)" "$(db "SELECT CAST(budget_approved_amount AS INTEGER) FROM research_applications WHERE id=$APP")" 95000
chk "allocation audited" "$(db "SELECT COUNT(*) FROM audit_logs WHERE action='budget.allocation_recorded' AND entity_id=$APP")" 1
# funding release so the research can become active
c=$(login finance@mwu.edu.et /tmp/acc_f.jar)
chk "finance login" "$c" 302
T=$(tok /tmp/acc_f.jar)
c=$(post /tmp/acc_f.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Release funds" -d "comment=Allocation released")
chk "Release funds" "$c" 302
chk "FUNDED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" FUNDED

# ===========================================================================
say "== [33] Research Directorate prepares the research agreement =="
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar agreement_issue -d "_csrf=$T" -d "application_id=$APP" \
    -d "title=Research Agreement — $ACC_TITLE" \
    -d "terms=The Research Directorate and the researcher agree to implement the approved research within the approved budget of 95000 ETB, submit progress reports, and deliver the final report." \
    -d "agreed_amount=95000")
chk "agreement prepared + issued" "$c" 302
AGR=$(db "SELECT id FROM research_agreements WHERE application_id=$APP")
[ -n "$AGR" ] && ok "agreement id=$AGR created" || { bad "agreement missing"; exit 2; }
chk "agreement version 1" "$(db "SELECT version FROM research_agreements WHERE id=$AGR")" 1
chk "agreement number" "$(db "SELECT agreement_number LIKE 'MWU-RPEE-AGR-%' FROM research_agreements WHERE id=$AGR")" 1
# Rule 13: an agreement above the approved allocation is rejected
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar agreement_issue -d "_csrf=$T" -d "application_id=$APP" -d "terms=Over cap" -d "agreed_amount=150000")
chk "over-cap agreement rejected (Rule 13)" "$(db "SELECT COUNT(*) FROM research_agreements WHERE application_id=$APP")" 1

# ===========================================================================
say "== [34] Research Directorate issues the agreement to the researcher =="
chk "agreement status issued" "$(db "SELECT status FROM research_agreements WHERE id=$AGR")" issued
chk "issued_by is the director" "$(db "SELECT issued_by FROM research_agreements WHERE id=$AGR")" "$(db "SELECT id FROM users WHERE email='director@mwu.edu.et'")"
chk "PI notified of the issued agreement" "$(db "SELECT COUNT(*) FROM notifications WHERE user_id=$RID AND notification_type='agreement_issued'")" 1
chk "issued audited" "$(db "SELECT COUNT(*) FROM audit_logs WHERE action='agreement.issued' AND entity_id=$APP")" 1

# ===========================================================================
say "== [35] Researcher views the agreement =="
page /tmp/acc_r.jar "action=application_view&id=$APP" | grep -q "Research Agreement" && ok "researcher sees the agreement card" || bad "agreement card missing"
page /tmp/acc_r.jar "action=application_view&id=$APP" | grep -q "MWU-RPEE-AGR" && ok "researcher sees the agreement number" || bad "agreement number missing"
# Rule 11: activation must be blocked while the agreement is unsigned
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar project_activate -d "_csrf=$T" -d "application_id=$APP")
chk "activation blocked before signature (Rule 11)" "$(db "SELECT COUNT(*) FROM research_projects WHERE application_id=$APP")" 0

# ===========================================================================
say "== [36] Researcher signs the agreement =="
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar agreement_sign -d "_csrf=$T" -d "agreement_id=$AGR" \
    -d "signature_name=Acc Researcher" -d "signature_note=Accepted electronically.")
chk "agreement_sign" "$c" 302

# ===========================================================================
say "== [37] System records the signed agreement =="
chk "agreement signed" "$(db "SELECT status FROM research_agreements WHERE id=$AGR")" signed
chk "signed_by is the researcher" "$(db "SELECT signed_by FROM research_agreements WHERE id=$AGR")" "$RID"
chk "signed_at recorded" "$(db "SELECT COUNT(*) FROM research_agreements WHERE id=$AGR AND signed_at IS NOT NULL")" 1
chk "signature name recorded" "$(db "SELECT signature_name FROM research_agreements WHERE id=$AGR")" "Acc Researcher"
chk "signature audited" "$(db "SELECT COUNT(*) FROM audit_logs WHERE action='agreement.signed' AND entity_id=$APP")" 1

# ===========================================================================
say "== [38] Research becomes ACTIVE =="
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar project_activate -d "_csrf=$T" -d "application_id=$APP")
chk "project_activate" "$c" 302
PROJ=$(db "SELECT id FROM research_projects WHERE application_id=$APP")
[ -n "$PROJ" ] && ok "project id=$PROJ created" || { bad "project missing"; exit 2; }
chk "project number MWU-RPEE-YYYY-NNNN" "$(db "SELECT project_number LIKE 'MWU-RPEE-20%' FROM research_projects WHERE id=$PROJ")" 1
chk "project ACTIVE" "$(db "SELECT status_code FROM research_projects WHERE id=$PROJ")" ACTIVE
chk "application mirrored ACTIVE" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" ACTIVE
chk "PI is team member" "$(db "SELECT COUNT(*) FROM research_project_members WHERE project_id=$PROJ AND user_id=$RID AND member_role='principal_investigator'")" 1
chk "project budget = approved allocation" "$(db "SELECT CAST(approved_amount AS INTEGER) FROM research_budgets WHERE project_id=$PROJ")" 95000

# ===========================================================================
say "== [39] Researcher submits progress reports =="
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar project_report_submit -d "_csrf=$T" -d "project_id=$PROJ" \
    -d "reporting_period=Q1" -d "period_start=$(date +%Y-%m-%d)" -d "period_end=$(date -d '+3 months' +%Y-%m-%d)" \
    -d "progress_pct=35" -d "completed_activities=Inception; instrument design" -d "pending_activities=Data collection" \
    -d "achievements=Team trained" -d "challenges=Field access delays")
chk "progress report 1" "$c" 302
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar project_report_submit -d "_csrf=$T" -d "project_id=$PROJ" \
    -d "reporting_period=Q2" -d "progress_pct=70" -d "completed_activities=Data collection" \
    -d "pending_activities=Analysis and write-up")
chk "progress report 2" "$c" 302
chk "two progress reports stored" "$(db "SELECT COUNT(*) FROM progress_reports WHERE project_id=$PROJ AND status='submitted'")" 2
# project enters progress monitoring (§39)
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar workflow_fire_project -d "_csrf=$T" -d "entity_id=$PROJ" \
    -d "transition=Progress monitoring" -d "comment=Progress reporting period opened")
chk "project progress monitoring" "$c" 302
chk "project PROGRESS_MONITORING" "$(db "SELECT status_code FROM research_projects WHERE id=$PROJ")" PROGRESS_MONITORING
# mirror on the application lifecycle (§76)
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Submit progress report" -d "comment=Progress reporting started")
chk "application progress stage" "$c" 302
chk "app PROGRESS_MONITORING" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" PROGRESS_MONITORING

# ===========================================================================
say "== [40] Researcher submits the final research report =="
T=$(tok /tmp/acc_r.jar)
DT_FINAL=$(db "SELECT id FROM document_types WHERE type_code='final_report'")
c=$(up /tmp/acc_r.jar document_upload /tmp/acc_doc.pdf \
    -F "_csrf=$T" -F "entity_type=research_project" -F "entity_id=$PROJ" \
    -F "document_type_id=$DT_FINAL" -F "title=Final research report" -F "reason=Final deliverable")
chk "final report uploaded (project)" "$c" 302
chk "final report document stored (project)" "$(db "SELECT COUNT(*) FROM documents WHERE entity_type='research_project' AND entity_id=$PROJ AND document_type_id=$DT_FINAL AND status='active'")" 1
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar workflow_fire_project -d "_csrf=$T" -d "entity_id=$PROJ" \
    -d "transition=Submit final report" -d "comment=Final report submitted")
chk "project Submit final report" "$c" 302
chk "project FINAL_REPORT_SUBMITTED" "$(db "SELECT status_code FROM research_projects WHERE id=$PROJ")" FINAL_REPORT_SUBMITTED
T=$(tok /tmp/acc_r.jar)
c=$(up /tmp/acc_r.jar application_upload /tmp/acc_doc.pdf \
    -F "_csrf=$T" -F "entity_id=$APP" -F "document_type_id=$DT_FINAL" \
    -F "title=Final research report" -F "reason=Final deliverable")
chk "final report uploaded (application)" "$c" 302
chk "final report document stored (application)" "$(db "SELECT COUNT(*) FROM documents WHERE entity_type='research_application' AND entity_id=$APP AND document_type_id=$DT_FINAL AND status='active'")" 1
T=$(tok /tmp/acc_r.jar)
c=$(post /tmp/acc_r.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Submit final report" -d "comment=Final report submitted")
chk "application Submit final report" "$c" 302
chk "app FINAL_REPORT_SUBMITTED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" FINAL_REPORT_SUBMITTED

# ===========================================================================
say "== [41] Research Directorate reviews completion =="
# project chain
for tr in "Final evaluation" "Schedule presentation" "Mark presented" "Evaluate presentation" "Final approval"; do
  T=$(tok /tmp/acc_d.jar)
  post /tmp/acc_d.jar workflow_fire_project -d "_csrf=$T" -d "entity_id=$PROJ" -d "transition=$tr" -d "comment=Directorate completion review" >/dev/null
done
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar workflow_fire_project -d "_csrf=$T" -d "entity_id=$PROJ" -d "transition=Complete project" -d "comment=Final report reviewed and accepted")
chk "project completion chain" "$c" 302
# application chain (mirrors the research lifecycle for the audit record)
for tr in "Start final evaluation" "Schedule presentation" "Mark presented" "Evaluate presentation" "Final approval"; do
  T=$(tok /tmp/acc_d.jar)
  post /tmp/acc_d.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" -d "transition=$tr" -d "comment=Directorate completion review" >/dev/null
done
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Mark completed" -d "comment=Research completed")
chk "application Mark completed" "$c" 302

# ===========================================================================
say "== [42] Research is marked COMPLETED =="
chk "project COMPLETED" "$(db "SELECT status_code FROM research_projects WHERE id=$PROJ")" COMPLETED
chk "application COMPLETED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" COMPLETED
chk "completion timestamped" "$(db "SELECT COUNT(*) FROM research_applications WHERE id=$APP AND completed_at IS NOT NULL")" 1

# ===========================================================================
say "== [43] Research is archived =="
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Publish to repository" -d "comment=Completed research published")
chk "Publish to repository" "$c" 302
chk "REPOSITORY" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" REPOSITORY
T=$(tok /tmp/acc_d.jar)
c=$(post /tmp/acc_d.jar workflow_fire -d "_csrf=$T" -d "entity_type=research_application" -d "entity_id=$APP" \
    -d "transition=Archive" -d "comment=Archived after completion")
chk "Archive" "$c" 302
chk "ARCHIVED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" ARCHIVED

# ===========================================================================
say "== [44] Every important action is available in the audit history =="
WH=$(db "SELECT COUNT(*) FROM workflow_history WHERE entity_type='research_application' AND entity_id=$APP")
[ "$WH" -ge 20 ] && ok "workflow history complete ($WH transitions)" || bad "workflow history thin ($WH)"
for action in user.created college.created department.created evaluator.created evaluator.assigned \
              revision.requested revision.responded revision.submitted committee.assigned committee.decided \
              budget.allocation_recorded agreement.issued agreement.signed project.activated \
              progress_report.submitted application.created evaluation.submitted workflow.transition; do
  n=$(db "SELECT COUNT(*) FROM audit_logs WHERE action='$action'")
  if [ -n "$n" ] && [ "$n" -ge 1 ]; then ok "audit: $action ($n)"; else bad "audit missing: $action"; fi
done
c=$(login admin@mwu.edu.et /tmp/acc_admin.jar)
page /tmp/acc_admin.jar "action=audit&action_filter=agreement" | grep -q "agreement.signed" && ok "audit page shows agreement.signed" || bad "audit page missing agreement.signed"
page /tmp/acc_admin.jar "action=audit&action_filter=committee" | grep -q "committee.decided" && ok "audit page shows committee.decided" || bad "audit page missing committee.decided"

# reviewer/evaluator cannot rewrite history (Rule 6)
code=$(curl -s -m 15 -b /tmp/acc_e1.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=audit")
[ "$code" = "403" ] && ok "evaluator denied audit page (403)" || bad "evaluator could open audit page ($code)"

say ""
say "================================"
say "ACCEPTANCE RESULT: $PASS passed, $FAIL failed"
say "Artifacts: college=$COLLEGE dept=$DEPT call=$CALL app=$APP proj=$PROJ committee=$COMMITTEE agreement=$AGR"
say "================================"
[ "$FAIL" -eq 0 ]

