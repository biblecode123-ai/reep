#!/bin/bash
# MWU RPEE — Researcher-profile visibility & access-control acceptance tests
# (spec: "Researcher Profile Visibility & Access Control", §20)
#
# Covers: own-profile CRUD + photo, college-scoped coordinator directory,
# university-wide directorate directory with filters, role-matrix denials,
# direct-API denials, audit old/new values, and profile-visibility ≠
# document-access separation.
set -u
[ -f app/bootstrap.php ] || { echo "Run from the project root"; exit 2; }

B=http://127.0.0.1:8099
P='Password123!'
PASS=0; FAIL=0

say()  { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); say "  PASS: $*"; }
bad()  { FAIL=$((FAIL+1)); say "  FAIL: $*"; }
chk()  { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected $3 got $2"; fi; }

login() { # email jar -> echoes login http code
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
page()  { curl -s -m 20 -b "$1" "$B/index.php?$2"; }
wpath() { cygpath -m "$1" 2>/dev/null || echo "$1"; } # POSIX path -> native Windows path
dl() { # jar doc-id outfile -> http code
  curl -s -m 20 -b "$1" -o "$3" -w '%{http_code}' "$B/download.php?id=$2"; }
jids() { php -r '$j=json_decode(stream_get_contents(STDIN),true); foreach(($j["data"]??[]) as $r) echo (int)$r["id"]," ";'; }
chk_phone() { local jar=$1 action=$2 needle=$3 label=$4; if page "$jar" "$action" | grep -qF "$needle"; then ok "$label"; else bad "$label"; fi; }

code=$(curl -s -m 10 -o /dev/null -w '%{http_code}' "$B/login.php" || echo 000)
[ "$code" = "200" ] || { say "Dev server not running (got $code) — start: php -S 127.0.0.1:8099 -t public"; exit 2; }

# ---------- fixtures: accounts + private documents (idempotent) ----------------
say "== [0] fixture setup =="
eval "$(php -r '
require "app/bootstrap.php";
$hash = password_hash("Password123!", PASSWORD_BCRYPT, ["cost" => 12]);

$mkUser = static function (string $email, string $name, string $role, int $college, int $dept) {
  $u = Database::one("SELECT id FROM users WHERE email = ?", [$email]);
  if ($u) { return (int) $u["id"]; }
  $roleId = Database::scalar("SELECT id FROM roles WHERE name = ?", [$role]);
  return App\Models\UserModel::create([
    "full_name" => $name, "email" => $email,
    "college_id" => $college, "department_id" => $dept, "status" => "active",
    "password_hash" => password_hash("Password123!", PASSWORD_BCRYPT, ["cost" => 12]),
    "must_change_password" => 0,
  ], [(int) $roleId]);
};

$rs   = Database::one("SELECT id, college_id, department_id FROM users WHERE email = ?", ["researcher@mwu.edu.et"]);
$accR = Database::one("SELECT id, college_id, department_id FROM users WHERE email = ?", ["acc.researcher@mwu.edu.et"]);
if (!$rs || !$accR) { fwrite(STDERR, "seed researcher accounts missing\n"); exit(1); }

// Coordinator in the researcher college / in the acc college (spec §4)
$mkUser("ccoordinator.cc@mwu.edu.et", "E2E College Coordinator", "college_coordinator", (int) $rs["college_id"], (int) $rs["department_id"]);
$mkUser("acc.coordinator@mwu.edu.et", "Acc College Coordinator", "college_coordinator", (int) $accR["college_id"], (int) $accR["department_id"]);
// Fresh researcher WITHOUT a profile row (§15 create-profile path)
$new = $mkUser("rs.new@mwu.edu.et", "RSTest New Researcher", "researcher", (int) $rs["college_id"], (int) $rs["department_id"]);
Database::run("DELETE FROM researchers WHERE user_id = ?", [$new]);

// --- clean fixtures from any previous run ---
foreach (Database::all("SELECT id FROM research_applications WHERE application_number LIKE ?", ["MWU-RPEE-APP-RSTEST%"]) as $a) {
  Database::run("DELETE FROM workflow_history WHERE entity_type = ? AND entity_id = ?", ["research_application", (int) $a["id"]]);
  Database::run("DELETE FROM documents WHERE entity_type = ? AND entity_id = ?", ["research_application", (int) $a["id"]]);
  Database::run("DELETE FROM research_applications WHERE id = ?", [(int) $a["id"]]);
}
Database::run("DELETE FROM documents WHERE original_filename LIKE ?", ["rstest_%"]);
Database::run("DELETE FROM research_calls WHERE reference_number = ?", ["MWU/RPEE/CALL/2026/RSTEST"]);

// --- fixture call + one application per college + one private document each ---
$callId = Database::insert("research_calls", [
  "call_number" => "MWU-RPEE-CALL-RSTEST", "reference_number" => "MWU/RPEE/CALL/2026/RSTEST",
  "title" => "RSTest visibility call", "status" => "draft",
]);
$mkApp = static function (int $callId, int $pi, int $college, string $suffix) use ($rs): int {
  return Database::insert("research_applications", [
    "application_number" => "MWU-RPEE-APP-RSTEST-" . $suffix,
    "call_id" => $callId, "pi_user_id" => $pi, "title" => "RSTest application " . $suffix,
    "college_id" => $college, "status_code" => "SUBMITTED", "budget_requested" => 0,
  ]);
};
$app15 = $mkApp($callId, (int) $rs["id"], (int) $rs["college_id"], "15");
$app22 = $mkApp($callId, (int) $accR["id"], (int) $accR["college_id"], "22");

$mkDoc = static function (int $appId, int $uploader, string $suffix, string $text): int {
  $rel = "private/rstest_doc" . $suffix . ".txt";
  file_put_contents(STORAGE_PATH . "/" . $rel, $text);
  return Database::insert("documents", [
    "document_type_id" => (int) Database::scalar("SELECT id FROM document_types WHERE type_code = \"proposal\""),
    "entity_type" => "research_application", "entity_id" => $appId,
    "title" => "RSTest private document " . $suffix, "version" => 1,
    "file_path" => $rel, "original_filename" => "rstest_doc" . $suffix . ".txt",
    "file_size" => strlen($text), "mime_type" => "text/plain",
    "visibility" => "private", "status" => "active",
    "uploaded_by" => $uploader, "uploaded_at" => date("Y-m-d H:i:s"),
  ]);
};
$doc15 = $mkDoc($app15, (int) $rs["id"], "15", "RSTEST PRIVATE DOCUMENT FIFTEEN");
$doc22 = $mkDoc($app22, (int) $accR["id"], "22", "RSTEST PRIVATE DOCUMENT TWENTYTWO");

echo "RS_ID=", (int) $rs["id"], "\n";
echo "RS_COLLEGE=", (int) $rs["college_id"], "\n";
echo "ACC_R_ID=", (int) $accR["id"], "\n";
echo "ACC_R_COLLEGE=", (int) $accR["college_id"], "\n";
echo "ACC_R_DEPT=", (int) $accR["department_id"], "\n";
echo "NEW_ID=$new\n";
echo "DOC15=$doc15\n";
echo "DOC22=$doc22\n";
echo "CC_ID=", (int) Database::scalar("SELECT id FROM users WHERE email = ?", ["ccoordinator.cc@mwu.edu.et"]), "\n";
echo "ACC_C_ID=", (int) Database::scalar("SELECT id FROM users WHERE email = ?", ["acc.coordinator@mwu.edu.et"]), "\n";
')" || { bad "fixture setup"; exit 2; }
ok "fixtures ready (researcher#$RS_ID college$RS_COLLEGE · acc#$ACC_R_ID college$ACC_R_COLLEGE · new#$NEW_ID · docs $DOC15/$DOC22)"

# 1×1 PNG for the photo test
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > /tmp/rs_photo.png 2>/dev/null || echo "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==" | base64 -d > /tmp/rs_photo.png
[ -s /tmp/rs_photo.png ] && ok "test PNG prepared" || { bad "cannot write test PNG"; exit 2; }

RS_NAME=$(php -r 'require "app/bootstrap.php"; echo Database::scalar("SELECT full_name FROM users WHERE id=?",[(int)$argv[1]]);' "$RS_ID")
ACC_NAME=$(php -r 'require "app/bootstrap.php"; echo Database::scalar("SELECT full_name FROM users WHERE id=?",[(int)$argv[1]]);' "$ACC_R_ID")
RS_EMAIL=$(php -r 'require "app/bootstrap.php"; echo Database::scalar("SELECT email FROM users WHERE id=?",[(int)$argv[1]]);' "$RS_ID")
ACC_EMAIL=$(php -r 'require "app/bootstrap.php"; echo Database::scalar("SELECT email FROM users WHERE id=?",[(int)$argv[1]]);' "$ACC_R_ID")

# =========================== [1] Researcher: own profile ======================
say "== [1] researcher owns and maintains own profile =="
L=$(login researcher@mwu.edu.et /tmp/rs.jar)
chk "researcher login (redirect)" "$L" "302"

c=$(curl -s -m 20 -b /tmp/rs.jar -o /tmp/rs_out.html -w '%{http_code}' "$B/index.php?action=researchers")
chk "own directory page" "$c" "200"
grep -qF "action=researchers" /tmp/rs_out.html && ok "Researchers nav link rendered" || bad "Researchers nav link rendered"

c=$(curl -s -m 20 -b /tmp/rs.jar -o /tmp/rs_out.html -w '%{http_code}' "$B/index.php?action=researcher_view")
chk "own profile (no id) renders" "$c" "200"
grep -qF "$RS_NAME" /tmp/rs_out.html && ok "own name on profile" || bad "own name on profile"

c=$(curl -s -m 20 -b /tmp/rs.jar -o /tmp/rs_out.html -w '%{http_code}' "$B/index.php?action=profile")
chk "profile page" "$c" "200"
grep -q "Profile completion" /tmp/rs_out.html && ok "completion status shown" || bad "completion status shown"

# edit own profile (§15) — seed a known "old" value first so old/new audit is deterministic
php -r 'require "app/bootstrap.php"; Database::update("researchers", ["academic_position" => "Lecturer"], "user_id = ?", [(int)$argv[1]]);' "$RS_ID"
T=$(tok /tmp/rs.jar)
c=$(post /tmp/rs.jar profile_researcher \
  -d "_csrf=$T" -d "position=Senior Lecturer" -d "qualification=PhD" \
  -d "specialization=Soil Science" -d "interests=Agronomy, Soil health" \
  -d "areas=Food Security" -d "biography=Researcher biography for RSTest." \
  -d "gender=female" -d "orcid=0000-0002-1825-0097" -d "scholar=https://scholar.google.com/citations?user=rstest")
chk "own profile saved" "$c" "302"
v=$(php -r 'require "app/bootstrap.php"; echo Database::scalar("SELECT academic_position FROM researchers WHERE user_id=?",[(int)$argv[1]]);' "$RS_ID")
chk "DB has new academic rank" "$v" "Senior Lecturer"
v=$(php -r 'require "app/bootstrap.php"; echo Database::scalar("SELECT specialization FROM researchers WHERE user_id=?",[(int)$argv[1]]);' "$RS_ID")
chk "DB has specialization" "$v" "Soil Science"

# audit old/new values (§16)
v=$(php -r 'require "app/bootstrap.php";
  $r = Database::one("SELECT old_values, new_values FROM audit_logs WHERE action = ? AND entity_id = ? ORDER BY id DESC LIMIT 1",
    ["researcher.profile_updated", (int)$argv[1]]);
  $q = chr(34);
  $ok = $r
    && str_contains((string)$r["new_values"], "Senior Lecturer")
    && str_contains((string)$r["old_values"], $q . "Lecturer" . $q)
    && str_contains((string)$r["old_values"], "academic rank");
  echo $ok ? "ok" : "no:" . json_encode($r);' "$RS_ID")
chk "audit recorded old/new values" "$v" "ok"

# profile photo (§3, §15)
T=$(tok /tmp/rs.jar)
PFILE=$(wpath /tmp/rs_photo.png)
c=$(curl -s -m 20 -b /tmp/rs.jar -c /tmp/rs.jar -o /dev/null -w '%{http_code}' \
  -F "_csrf=$T" -F "photo=@$PFILE;type=image/png" "$B/index.php?action=researcher_photo_upload")
chk "photo upload" "$c" "302"
v=$(php -r 'require "app/bootstrap.php"; echo (int)(Database::scalar("SELECT profile_photo_document_id FROM researchers WHERE user_id=?",[(int)$argv[1]]) ?: 0);' "$RS_ID")
[ "$v" -gt 0 ] && ok "photo document linked ($v)" || bad "photo document linked (0)"
c=$(curl -s -m 20 -b /tmp/rs.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=researcher_photo&user_id=$RS_ID")
chk "own photo serves" "$c" "200"

# JSON API own profile (§11)
page /tmp/rs.jar "action=api_researcher_profile" > /tmp/rs_api.json
php -r '$j=json_decode(file_get_contents($argv[1]),true); exit(($j["ok"]??false) && (($j["data"]["research_statistics"]["total_projects"]??-1) >= 0) && str_contains(json_encode($j["data"]), "college") ? 0 : 1);' \
  "$(wpath /tmp/rs_api.json)" \
  && ok "api own profile (resource with college + statistics)" || bad "api own profile (resource with college + statistics)"

# ---------- create profile when none exists (rs.new) ----------
login rs.new@mwu.edu.et /tmp/new.jar >/dev/null
L=$(login rs.new@mwu.edu.et /tmp/new.jar)
chk "new researcher login (redirect)" "$L" "302"
c=$(curl -s -m 20 -b /tmp/new.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=researcher_view&id=$NEW_ID")
chk "own empty profile renders before creation" "$c" "200"
T=$(tok /tmp/new.jar)
c=$(post /tmp/new.jar profile_researcher \
  -d "_csrf=$T" -d "position=Assistant Lecturer" -d "qualification=MSc" \
  -d "specialization=Water Resources" -d "interests=Hydrology" -d "areas=Water" \
  -d "biography=" -d "gender=male" -d "orcid=" -d "scholar=")
chk "create own profile" "$c" "302"
v=$(php -r 'require "app/bootstrap.php"; echo Database::scalar("SELECT qualification FROM researchers WHERE user_id=?",[(int)$argv[1]]);' "$NEW_ID")
chk "profile row created" "$v" "MSc"

# =========================== [2] Researcher: denials ==========================
say "== [2] researcher cannot reach other profiles (URL + API) =="
c=$(curl -s -m 20 -b /tmp/rs.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=researcher_view&id=$ACC_R_ID")
chk "HTML other-college profile" "$c" "403"
c=$(curl -s -m 20 -b /tmp/rs.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=api_researcher&id=$ACC_R_ID")
chk "API other-college profile" "$c" "403"
c=$(curl -s -m 20 -b /tmp/rs.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=researcher_photo&user_id=$ACC_R_ID")
chk "other researcher's photo" "$c" "403"

ids=$(page /tmp/rs.jar "action=api_researchers" | jids)
if [ "$(echo "$ids" | tr ' ' '\n' | grep -vc '^$')" = "1" ] && [ "$(echo "$ids" | tr -d ' ')" = "$RS_ID" ]; then
  ok "API list scoped to self only ($ids)"
else
  bad "API list scoped to self only (got: $ids)"
fi
curl -s -m 20 -b /tmp/rs.jar "$B/index.php?action=researchers" > /tmp/rs_out.html
grep -qF "$ACC_NAME" /tmp/rs_out.html && bad "directory list scoped to self (leaked $ACC_NAME)" || ok "directory list scoped to self"

c=$(dl /tmp/rs.jar "$DOC22" /tmp/rs_dl.out)
chk "other college's private document" "$c" "403"

# =========================== [3] Coordinator scoping ==========================
say "== [3] coordinator sees own college only (§4) =="
login ccoordinator.cc@mwu.edu.et /tmp/cc.jar >/dev/null
c=$(curl -s -m 20 -b /tmp/cc.jar -o /tmp/cc_out.html -w '%{http_code}' "$B/index.php?action=researchers")
chk "coordinator directory" "$c" "200"
grep -qF "action=researchers" /tmp/cc_out.html && ok "nav link for coordinator" || bad "nav link for coordinator"
grep -qF "$RS_NAME" /tmp/cc_out.html && ok "own-college researcher listed" || bad "own-college researcher listed ($RS_NAME)"
grep -qF "$ACC_NAME" /tmp/cc_out.html && bad "other-college researcher hidden" || ok "other-college researcher hidden"
grep -qF "college_id" /tmp/cc_out.html && bad "no college selector for coordinator" || ok "no college selector for coordinator"

c=$(curl -s -m 20 -b /tmp/cc.jar -o /tmp/cc_out.html -w '%{http_code}' "$B/index.php?action=researcher_view&id=$RS_ID")
chk "own-college profile opens" "$c" "200"
grep -qF "$RS_EMAIL" /tmp/cc_out.html && ok "same-college contact visible (email)" || bad "same-college contact visible (email)"
c=$(curl -s -m 20 -b /tmp/cc.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=researcher_view&id=$ACC_R_ID")
chk "other-college profile by URL id" "$c" "403"
c=$(curl -s -m 20 -b /tmp/cc.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=api_researcher&id=$ACC_R_ID")
chk "API other-college profile" "$c" "403"
c=$(curl -s -m 20 -b /tmp/cc.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=api_researcher&id=$RS_ID")
chk "API own-college profile" "$c" "200"

ids=$(page /tmp/cc.jar "action=api_researchers" | jids)
case " $ids " in *" $ACC_R_ID "*) bad "API list scoped to college (leaked $ACC_R_ID)";; *) ok "API list scoped to college";; esac
case " $ids " in *" $RS_ID "*) ok "API list includes own college";; *) bad "API list includes own college";; esac

c=$(curl -s -m 20 -b /tmp/cc.jar -o /dev/null -w '%{http_code}' "$B/index.php?action=researcher_photo&user_id=$ACC_R_ID")
chk "other-college photo" "$c" "403"

# department filter from another college cannot widen scope
c=$(curl -s -m 20 -b /tmp/cc.jar -o /tmp/cc_out.html -w '%{http_code}' "$B/index.php?action=researchers&department_id=$ACC_R_DEPT")
chk "cross-college department filter" "$c" "200"
grep -qF "$ACC_NAME" /tmp/cc_out.html && bad "cross-college dept filter returns nothing" || ok "cross-college dept filter returns nothing"

# ---------- §18/§20: profile visibility ≠ document access ----------
c=$(dl /tmp/cc.jar "$DOC22" /tmp/rs_dl.out)
chk "other college's private document (coordinator)" "$c" "403"
c=$(dl /tmp/cc.jar "$DOC15" /tmp/rs_dl.out)
chk "own-college document per document policy" "$c" "200"
grep -q "RSTEST PRIVATE DOCUMENT FIFTEEN" /tmp/rs_dl.out && ok "document bytes served" || bad "document bytes served"

# =========================== [4] Directorate ==================================
say "== [4] directorate: university-wide directory + filters (§5, §9) =="
login director@mwu.edu.et /tmp/dr.jar >/dev/null
c=$(curl -s -m 20 -b /tmp/dr.jar -o /tmp/dr_out.html -w '%{http_code}' "$B/index.php?action=researchers")
chk "directorate directory" "$c" "200"
grep -qF "$RS_NAME" /tmp/dr_out.html && ok "researcher college 15 listed" || bad "researcher college 15 listed"
grep -qF "$ACC_NAME" /tmp/dr_out.html && ok "researcher college 22 listed" || bad "researcher college 22 listed"
grep -qF "college_id" /tmp/dr_out.html && ok "college filter rendered" || bad "college filter rendered"

curl -s -m 20 -b /tmp/dr.jar "$B/index.php?action=researchers&college_id=$RS_COLLEGE" > /tmp/dr_out.html
grep -qF "$RS_NAME" /tmp/dr_out.html && ok "filter college=$RS_COLLEGE includes" || bad "filter college=$RS_COLLEGE includes"
grep -qF "$ACC_NAME" /tmp/dr_out.html && bad "filter college=$RS_COLLEGE excludes" || ok "filter college=$RS_COLLEGE excludes"

curl -s -m 20 -b /tmp/dr.jar "$B/index.php?action=researchers&college_id=$ACC_R_COLLEGE" > /tmp/dr_out.html
grep -qF "$ACC_NAME" /tmp/dr_out.html && ok "filter college=$ACC_R_COLLEGE includes" || bad "filter college=$ACC_R_COLLEGE includes"
grep -qF "$RS_NAME" /tmp/dr_out.html && bad "filter college=$ACC_R_COLLEGE excludes" || ok "filter college=$ACC_R_COLLEGE excludes"

curl -s -m 20 -b /tmp/dr.jar "$B/index.php?action=researchers&department_id=$ACC_R_DEPT" > /tmp/dr_out.html
grep -qF "$ACC_NAME" /tmp/dr_out.html && ok "filter department includes" || bad "filter department includes"
grep -qF "$RS_NAME" /tmp/dr_out.html && bad "filter department excludes" || ok "filter department excludes"

curl -s -m 20 -b /tmp/dr.jar "$B/index.php?action=researchers&rank=Lecturer" > /tmp/dr_out.html
grep -qF "$ACC_NAME" /tmp/dr_out.html && ok "filter rank=Lecturer" || bad "filter rank=Lecturer"

curl -s -m 20 -b /tmp/dr.jar "$B/index.php?action=researchers&qual=MSc" > /tmp/dr_out.html
grep -qF "RSTest New Researcher" /tmp/dr_out.html && ok "filter qualification=MSc" || bad "filter qualification=MSc"

curl -s -m 20 -b /tmp/dr.jar "$B/index.php?action=researchers&status=suspended" > /tmp/dr_out.html
grep -qF "$RS_NAME" /tmp/dr_out.html && bad "filter status excludes active researchers" || ok "filter status excludes active researchers"

curl -s -m 20 -b /tmp/dr.jar "$B/index.php?action=researchers&q=Soil" > /tmp/dr_out.html
grep -qF "$RS_NAME" /tmp/dr_out.html && ok "search by specialization/interests" || bad "search by specialization/interests"

c=$(curl -s -m 20 -b /tmp/dr.jar -o /tmp/dr_out.html -w '%{http_code}' "$B/index.php?action=researcher_view&id=$ACC_R_ID")
chk "any-college profile opens" "$c" "200"
grep -qF "$ACC_EMAIL" /tmp/dr_out.html && ok "director sees contact details" || bad "director sees contact details"

page /tmp/dr.jar "action=api_researcher&id=$ACC_R_ID" > /tmp/dr_api.json
php -r '$j=json_decode(file_get_contents($argv[1]),true); $d=$j["data"]??[]; exit(($j["ok"]??false) && array_key_exists("staff_id",$d) && array_key_exists("research_statistics",$d) && (($d["college"]["id"]??0) > 0) ? 0 : 1);' \
  "$(wpath /tmp/dr_api.json)" \
  && ok "API profile resource (staff_id, college, statistics)" || bad "API profile resource (staff_id, college, statistics)"

ids=$(page /tmp/dr.jar "action=api_researchers&college_id=$ACC_R_COLLEGE" | jids)
case " $ids " in *" $RS_ID "*) bad "API college filter server-side";; *) ok "API college filter server-side";; esac
case " $ids " in *" $ACC_R_ID "*) ok "API college filter includes target";; *) bad "API college filter includes target";; esac

c=$(dl /tmp/dr.jar "$DOC15" /tmp/rs_dl.out)
chk "directorate document oversight" "$c" "200"

# =========================== [5] Role-matrix denials ==========================
say "== [5] roles without researcher.view are denied (HTML + API) =="
login evaluator@mwu.edu.et /tmp/ev.jar >/dev/null
for a in "action=researchers" "action=researcher_view&id=$RS_ID" "action=api_researchers" "action=api_researcher&id=$RS_ID" "action=researcher_photo&user_id=$RS_ID"; do
  c=$(curl -s -m 20 -b /tmp/ev.jar -o /dev/null -w '%{http_code}' "$B/index.php?$a")
  chk "evaluator denied: ${a%%&*}" "$c" "403"
done

login committee@mwu.edu.et /tmp/cm.jar >/dev/null
for a in "action=researchers" "action=researcher_view&id=$RS_ID" "action=api_researchers" "action=api_researcher&id=$RS_ID"; do
  c=$(curl -s -m 20 -b /tmp/cm.jar -o /dev/null -w '%{http_code}' "$B/index.php?$a")
  chk "committee member denied: ${a%%&*}" "$c" "403"
done

# =========================== [6] Directorate staff (officer) ==================
say "== [6] Research Directorate staff (rpee_officer) =="
login officer@mwu.edu.et /tmp/of.jar >/dev/null
c=$(curl -s -m 20 -b /tmp/of.jar -o /tmp/of_out.html -w '%{http_code}' "$B/index.php?action=researchers")
chk "officer directory" "$c" "200"
grep -qF "$RS_NAME" /tmp/of_out.html && ok "officer sees college 15" || bad "officer sees college 15"
grep -qF "$ACC_NAME" /tmp/of_out.html && ok "officer sees college 22" || bad "officer sees college 22"

# =========================== summary ==========================================
say ""
say "================================"
say "RESEARCHER VISIBILITY RESULT: $PASS passed, $FAIL failed"
say "================================"
[ "$FAIL" -eq 0 ] || exit 1
exit 0

