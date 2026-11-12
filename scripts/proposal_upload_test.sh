#!/bin/bash
# MWU RPEE — Proposal creation + document attachment E2E (§12–§16, §87, §96)
# Director publishes a call -> researcher drafts a proposal (work plan included),
# attaches every required document, submits (gate verified both ways), and the
# college coordinator receives it into screening. File integrity is checked on
# disk (storage/) and through the authorized download endpoint.
set -u
[ -f app/bootstrap.php ] || { echo "Run from the project root"; exit 2; }

B=http://127.0.0.1:8099
P='Password123!'
PASS=0; FAIL=0
REF="MWU/RPEE/CALL/2026/UPDOC"

say()  { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); say "  PASS: $*"; }
bad()  { FAIL=$((FAIL+1)); say "  FAIL: $*"; }
chk()  { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected $3 got $2"; fi; }

login() { # email jar -> http code
  local jar=$2 tok
  rm -f "$jar"
  tok=$(curl -s -m 15 -c "$jar" "$B/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -m 20 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -d "identifier=$1&password=$P&_csrf=$tok" "$B/login.php?action=do_login"
}
tok() { curl -s -m 15 -b "$1" -c "$1" "$B/index.php" | grep -o 'name="csrf-token" content="[^"]*"\|name="_csrf" value="[^"]*"' | head -1 | sed 's/.*content="//;s/.*value="//;s/"//'; }
post() { local jar=$1 action=$2; shift 2; curl -s -m 25 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' "$B/index.php?action=$action" "$@"; }
up() { # jar action file extra... (multipart; ALL fields as -F — curl 8.17 in Git Bash
  # fatals when -F and -d are mixed in one request)
  local jar=$1 action=$2 f=$3; shift 3
  local wf; wf=$(cygpath -m "$f" 2>/dev/null || echo "$f")
  curl -s -m 25 -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' "$B/index.php?action=$action" -F "document=@$wf;type=application/pdf" "$@"
}
get() { curl -s -m 25 -b "$1" -o /dev/null -w '%{http_code}' "$B/index.php?$2"; }
db() { timeout 10 php -r 'require "app/bootstrap.php"; echo (string) (Database::scalar($argv[1]) ?? "");' "$1"; }

code=$(curl -s -m 10 -o /dev/null -w '%{http_code}' "$B/login.php" || echo 000)
[ "$code" = "200" ] || { say "Dev server not running (got $code)"; exit 2; }

# ---- unique PDF/PNG fixtures ------------------------------------------------
FIX=/tmp/propdocs; mkdir -p "$FIX"
printf '%%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\ntrailer<</Size 2/Root 1 0 R>>\n%%%%EOF\n' > "$FIX/proposal.pdf"
printf '\x89PNG\r\n\x1a\nFAKESIG\x00\x01\x02' > "$FIX/cv.png"
[ -s "$FIX/proposal.pdf" ] && [ -s "$FIX/cv.png" ] && ok "fixtures prepared" || { bad "fixture prep"; exit 2; }

# helper: verify uploaded rows exist on disk (column-safe for sqlite/mysql)
cat > /tmp/doccheck.php <<'EOF'
<?php
require "app/bootstrap.php";
$appId = (int) ($argv[1] ?? 0);
$rows = Database::all(
    'SELECT d.id, d.file_path, d.original_filename AS original_name, d.file_size FROM documents d
     WHERE d.entity_type = "research_application" AND d.entity_id = ? AND d.status = "active" ORDER BY d.id',
    [$appId]
);
foreach ($rows as $r) {
    $onDisk = is_file(STORAGE_PATH . '/' . $r['file_path']) ? 'disk-ok' : 'disk-MISSING';
    echo $r['id'], '|', $onDisk, '|', $r['original_name'], '|', $r['file_size'], "\n";
}
EOF

# ---- cleanup prior run ------------------------------------------------------
php -r 'require "app/bootstrap.php";
  $ref = "MWU/RPEE/CALL/2026/UPDOC";
  $call = Database::scalar("SELECT id FROM research_calls WHERE reference_number=?", [$ref]);
  if ($call) {
    $appIds = array_map("intval", array_column(Database::all("SELECT id FROM research_applications WHERE call_id=?", [$call]), "id"));
    $tables = array_column(Database::all("SELECT name FROM sqlite_master WHERE type=\"table\""), "name");
    foreach ($tables as $t) {
      if ($t === "research_calls" || str_starts_with($t, "sqlite_")) continue;
      $cols = array_column(Database::all("PRAGMA table_info($t)"), "name");
      if (in_array("call_id", $cols, true)) Database::run("DELETE FROM $t WHERE call_id = ?", [$call]);
      if (in_array("application_id", $cols, true) && $appIds) {
        $in = implode(",", array_fill(0, count($appIds), "?"));
        Database::run("DELETE FROM $t WHERE application_id IN ($in)", $appIds);
      }
    }
    foreach ($appIds as $aid) Database::run("DELETE FROM research_applications WHERE id=?", [$aid]);
    Database::run("DELETE FROM research_calls WHERE id=?", [$call]);
    echo "cleaned\n";
  }' || exit 2

say "== [1] DIRECTOR creates, configures and publishes the call =="
login director@mwu.edu.et /tmp/pd.jar > /dev/null
chk "director login" "$(get /tmp/pd.jar 'action=')" 200
T=$(tok /tmp/pd.jar)
THEME_ID=$(db "SELECT id FROM research_themes WHERE name='Agriculture and Food Security'")
c=$(post /tmp/pd.jar call_save -d "_csrf=$T" -d "title=Proposal Upload Verification Call" -d "reference_number=$REF" \
  -d "theme=$THEME_ID" \
  -d "description=Verifies proposal creation and document attachment end to end." \
  -d "opens_at=$(date +%Y-%m-%d)" -d "closes_at=$(date -d '+30 days' +%Y-%m-%d)" \
  -d "max_duration=12" -d "max_budget=300000" -d "max_apps=2" \
  -d "evaluation_mode=single_blind" -d "required_evaluators=1")
chk "director call_save" "$c" 302
CALL=$(db "SELECT id FROM research_calls WHERE reference_number='$REF'")
[ -n "$CALL" ] && ok "call id=$CALL created by DIRECTOR" || { bad "call row missing"; exit 2; }

T=$(tok /tmp/pd.jar)
TYPES=""; for tc in proposal cv budget_doc work_plan; do
  TYPES="$TYPES -d req_type%5B%5D=$(db "SELECT id FROM document_types WHERE type_code='$tc'") -d req_kind%5B%5D=required"
done
c=$(post /tmp/pd.jar call_config -d "_csrf=$T" -d "id=$CALL" $TYPES \
  -d "crit_name%5B%5D=Methodology" -d "crit_weight%5B%5D=60" \
  -d "crit_name%5B%5D=Feasibility" -d "crit_weight%5B%5D=40")
chk "call_config (4 required docs, 2 criteria)" "$c" 302

T=$(tok /tmp/pd.jar)
c=$(post /tmp/pd.jar call_publish -d "_csrf=$T" -d "id=$CALL")
chk "call_publish" "$c" 302
chk "call status" "$(db "SELECT status FROM research_calls WHERE id=$CALL")" "published"

say "== [2] RESEARCHER creates the proposal (draft + work plan) =="
login researcher@mwu.edu.et /tmp/pr.jar > /dev/null
chk "researcher login" "$(get /tmp/pr.jar 'action=')" 200
# researcher must NOT be able to create calls
T=$(tok /tmp/pr.jar)
c=$(post /tmp/pr.jar call_save -d "_csrf=$T" -d "title=hack" -d "reference_number=HACK/1")
chk "researcher call_save denied" "$c" 403

T=$(tok /tmp/pr.jar)
c=$(post /tmp/pr.jar application_save -d "_csrf=$T" -d "id=0" -d "call_id=$CALL" \
  -d "title=Soil Fertility Mapping with Drone Spectrometry" \
  -d "abstract=High-resolution soil fertility mapping to guide targeted lime and fertilizer application." \
  -d "budget=180000" -d "duration=10" -d "start_date=$(date -d '+1 month' +%Y-%m-%d)" \
  -d "end_date=$(date -d '+11 month' +%Y-%m-%d)" -d "requires_ethics=no" \
  -d "methodology=Drone multispectral survey with ground-truth soil sampling." \
  -d "wp_activity%5B0%5D=Procure drone and sensors" -d "wp_start%5B0%5D=$(date -d '+1 month' +%Y-%m-%d)" -d "wp_end%5B0%5D=$(date -d '+2 month' +%Y-%m-%d)" -d "wp_resp%5B0%5D=PI" -d "wp_out%5B0%5D=Equipment ready" \
  -d "wp_activity%5B1%5D=Field survey campaign" -d "wp_start%5B1%5D=$(date -d '+2 month' +%Y-%m-%d)" -d "wp_end%5B1%5D=$(date -d '+6 month' +%Y-%m-%d)" -d "wp_resp%5B1%5D=Field team" -d "wp_out%5B1%5D=Soil dataset" \
  -d "wp_activity%5B2%5D=Analysis and maps" -d "wp_start%5B2%5D=$(date -d '+6 month' +%Y-%m-%d)" -d "wp_end%5B2%5D=$(date -d '+10 month' +%Y-%m-%d)" -d "wp_resp%2B%5B2%5D=Analyst" -d "wp_out%5B2%5D=Fertility maps")
chk "application_save (draft)" "$c" 302
APP=$(db "SELECT id FROM research_applications WHERE title='Soil Fertility Mapping with Drone Spectrometry' AND call_id=$CALL ORDER BY id DESC LIMIT 1")
[ -n "$APP" ] && ok "draft application id=$APP" || { bad "draft application missing"; exit 2; }
chk "application owned by researcher" "$(db "SELECT pi_user_id FROM research_applications WHERE id=$APP")" "$(db "SELECT id FROM users WHERE email='researcher@mwu.edu.et'")"
chk "application starts DRAFT" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" "DRAFT"
chk "work plan saved (3 rows)" "$(db "SELECT COUNT(*) FROM research_work_plan_items WHERE application_id=$APP")" "3"

say "== [3] submit is BLOCKED until every required document is attached =="
T=$(tok /tmp/pr.jar)
c=$(post /tmp/pr.jar application_submit -d "_csrf=$T" -d "id=$APP")
chk "submit blocked with no docs" "$c" 302
chk "still DRAFT after blocked submit" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" "DRAFT"

say "== [4] researcher attaches required documents =="
PT=$(db "SELECT id FROM document_types WHERE type_code='proposal'")
CV=$(db "SELECT id FROM document_types WHERE type_code='cv'")
BD=$(db "SELECT id FROM document_types WHERE type_code='budget_doc'")
WP=$(db "SELECT id FROM document_types WHERE type_code='work_plan'")
c=$(up /tmp/pr.jar application_upload "$FIX/proposal.pdf" -F "_csrf=$(tok /tmp/pr.jar)" -F "entity_id=$APP" -F "document_type_id=$PT" -F "title=Full proposal" -F "reason=Initial submission")
chk "upload proposal.pdf" "$c" 302
c=$(up /tmp/pr.jar application_upload "$FIX/cv.png" -F "_csrf=$(tok /tmp/pr.jar)" -F "entity_id=$APP" -F "document_type_id=$CV" -F "title=PI CV" -F "reason=Credentials")
chk "upload cv" "$c" 302
c=$(up /tmp/pr.jar application_upload "$FIX/proposal.pdf" -F "_csrf=$(tok /tmp/pr.jar)" -F "entity_id=$APP" -F "document_type_id=$BD" -F "title=Budget breakdown" -F "reason=Costs")
chk "upload budget_doc" "$c" 302
c=$(up /tmp/pr.jar application_upload "$FIX/proposal.pdf" -F "_csrf=$(tok /tmp/pr.jar)" -F "entity_id=$APP" -F "document_type_id=$WP" -F "title=Work plan" -F "reason=Schedule")
chk "upload work_plan" "$c" 302
chk "4 documents recorded" "$(db "SELECT COUNT(*) FROM documents WHERE entity_type='research_application' AND entity_id=$APP AND status='active'")" "4"

say "== [5] file integrity on disk + authorized download =="
PHPCHK=$(php /tmp/doccheck.php "$APP" 2>/dev/null)
echo "$PHPCHK" | grep '|' | while IFS='|' read -r did st name sz; do
  [ "$st" = "disk-ok" ] && ok "file on disk: $name ($sz bytes, doc #$did)" || bad "file missing on disk: $name"
done
STORAGE_COUNT=$(find storage -type f \( -name '*.pdf' -o -name '*.png' \) -newer "$FIX/proposal.pdf" 2>/dev/null | wc -l)
[ "$STORAGE_COUNT" -ge 4 ] && ok ">=4 fresh files in storage/" || bad "storage/ files not found ($STORAGE_COUNT)"

DLID=$(echo "$PHPCHK" | head -1 | cut -d'|' -f1)
hc=$(curl -s -m 25 -b /tmp/pr.jar -o /tmp/dl_ok.bin -w '%{http_code}' "$B/index.php?action=document_download&id=$DLID")
chk "PI download 200" "$hc" 200
cmp -s /tmp/dl_ok.bin "$FIX/proposal.pdf" && ok "downloaded bytes identical to uploaded fixture" || bad "downloaded content differs"
hc=$(curl -s -m 25 -o /dev/null -w '%{http_code}' "$B/index.php?action=document_download&id=$DLID")
chk "guest download blocked" "$hc" 302
hc=$(curl -s -m 25 -b /tmp/pr.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=document_download&id=999999")
chk "missing document 404" "$hc" 404
# another researcher must NOT download (entity_team visibility)
login extension.evaluator@mwu.edu.et /tmp/px.jar > /dev/null
hc=$(curl -s -m 25 -b /tmp/px.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=document_download&id=$DLID")
[ "$hc" = "403" ] || [ "$hc" = "302" ] && ok "outsider download blocked ($hc)" || bad "outsider download leaked ($hc)"
say "== [6] submit now succeeds (documents_complete) =="
T=$(tok /tmp/pr.jar)
c=$(post /tmp/pr.jar application_submit -d "_csrf=$T" -d "id=$APP")
chk "application_submit" "$c" 302
chk "application SUBMITTED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" "SUBMITTED"
chk "workflow history row written" "$(db "SELECT COUNT(*) FROM workflow_history WHERE entity_type='research_application' AND entity_id=$APP")" "1"
chk "workflow at SUBMITTED" "$(db "SELECT to_status FROM workflow_history WHERE entity_type='research_application' AND entity_id=$APP ORDER BY id DESC LIMIT 1")" "SUBMITTED"

say "== [7] coordinator receives it (screening) =="
login ccoordinator@mwu.edu.et /tmp/pc.jar > /dev/null
T=$(tok /tmp/pc.jar)
c=$(post /tmp/pc.jar screening_receive -d "_csrf=$T" -d "id=$APP")
chk "screening_receive" "$c" 302
chk "application COLLEGE_RECEIVED" "$(db "SELECT status_code FROM research_applications WHERE id=$APP")" "COLLEGE_RECEIVED"
# documents still readable by the coordinator (college visibility layer)
hc=$(curl -s -m 25 -b /tmp/pc.jar -o /tmp/dl_cc.bin -w '%{http_code}' "$B/index.php?action=document_download&id=$DLID")
chk "coordinator can download PI's document" "$hc" 200
cmp -s /tmp/dl_cc.bin "$FIX/proposal.pdf" && ok "coordinator download identical" || bad "coordinator download differs"

say ""
say "RESULT: $PASS passed, $FAIL failed"
say "Artifacts: call=$CALL app=$APP (title: Soil Fertility Mapping with Drone Spectrometry)"
[ "$FAIL" = "0" ] || exit 1
