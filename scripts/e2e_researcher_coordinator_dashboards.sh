#!/bin/bash
# Researcher and College Coordinator Dashboard E2E Test Suite
set -u
B=http://127.0.0.1:8099
PASS=0; FAIL=0
say()  { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); say "  PASS: $*"; }
bad()  { FAIL=$((FAIL+1)); say "  FAIL: $*"; }

# Basic test to verify dashboard loading and data consistency
say "== Starting Researcher and Coordinator Dashboard E2E Test =="

# 1. Login as Researcher
login_researcher() { curl -s -c /tmp/er.jar -d "identifier=researcher@mwu.edu.et&password=Password123!" "$B/login.php?action=do_login" >/dev/null; }
login_researcher
code=$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/er.jar "$B/index.php?action=researcher_dashboard")
if [ "$code" = "200" ]; then ok "Researcher dashboard accessible"; else bad "Researcher dashboard returned $code"; fi

# 2. Login as Coordinator
login_coordinator() { curl -s -c /tmp/cc.jar -d "identifier=ccoordinator.cc@mwu.edu.et&password=Password123!" "$B/login.php?action=do_login" >/dev/null; }
login_coordinator
code=$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/cc.jar "$B/index.php?action=coordinator_dashboard")
if [ "$code" = "200" ]; then ok "Coordinator dashboard accessible"; else bad "Coordinator dashboard returned $code"; fi

# 3. Check College Isolation (Negative test)
# Attempt to access coordinator A's dashboard as coordinator B (not possible with current setup, 
# but check direct URL manipulation to a different college ID)
# Assuming there's a way to manipulate parameters to access another college's application
# This is a placeholder for the negative tests.

say "== E2E Test Result: $PASS passed, $FAIL failed =="
exit $FAIL
