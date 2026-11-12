# My Evaluations — Implementation & Test Report

**Feature:** My Evaluations for Researcher and College Research Coordinator
**Date:** 24 Oct 2026
**Stack:** PHP / HTML / CSS / JS only — integrated into the existing REEP application, database, authentication, authorization and dashboard layouts. No new framework, no parallel evaluation system.

---

## 1. What was delivered

| # | Deliverable | Status |
|---|---|---|
| 1 | My Evaluations sidebar item on both dashboards (Researcher + College Coordinator), reusing the existing sidebar design and the existing `action=my_evaluations` route | Done |
| 2 | Role-aware My Evaluations page on the existing route (admin explicitly blocked with 403) | Done |
| 3 | Assigned-proposal listing with search, level/status/overdue filters, summary cards | Done |
| 4 | Secure proposal detail + document access (existing `download.php` → `DocumentService`, assignment checked server-side on every action) | Done |
| 5 | Complete evaluation form: criteria scoring, per-criterion comments, strengths, weaknesses, recommendations, overall comment, researcher-visible + committee-only comments, final recommendation (existing permitted set only) | Done (pre-existing; extended with co-investigators, submission date, evaluation level) |
| 6 | Draft save + validated submission, duplicate prevention, read-only submitted evaluations | Done (hardened with transactions + full validation) |
| 7 | Database changes only where necessary (one column + two permission grants) | Done |
| 8 | Integration with college/university evaluation workflows preserved (no individual evaluation auto-approves) | Done |
| 9 | Server-side authorization and validation on every action | Done (2 gaps closed — see §4) |
| 10 | This test report | Done |

---

## 2. Files changed

### New files
| File | Purpose |
|---|---|
| `scripts/migrate_my_evaluations.php` | Idempotent migration: `assignment_level` column + `research.evaluate` grants. Run once; already applied to the live DB. |
| `tests/verify_eval_queries.php` | Query/model verification against the live DB (13 checks). |
| `tests/smoke_my_evaluations.sh` | HTTP end-to-end smoke test (38 checks) run against a **throwaway copy** of the DB — never touches real data. |

### Modified files
| File | Change |
|---|---|
| `app/views/partials/header.php` | Coordinator nav map now includes **My Evaluations** (permission-gated on `research.evaluate`); `evaluation_form` / `evaluation_view` / `univ_evaluation_form` highlight the My Evaluations nav item as active. Admin nav map untouched (no My Evaluations for admin). |
| `app/Controllers/ScreeningController.php` | `myEvaluations()` passes status/level/search/overdue filters and an overdue count; **fixed 2 crashing queries** that referenced nonexistent `research_project_members.application_id` (now `application_team_members`). |
| `app/Controllers/EvaluationController.php` | **Fixed crashing team query** in `form()` (same missing-column bug); `saveDraft()` now persists evaluation row + scores in a transaction; `submit()` validates everything (scores, recommendation) *before* writing, then persists evaluation, scores, comments and assignment status in one transaction. |
| `app/Controllers/UniversityEvaluationController.php` | Assignments marked `assignment_level = 'university'`; `submitUniversityEvaluation()` now **requires an active assignment**, **blocks duplicate submissions**, and wraps writes in a transaction (previously any `research.evaluate` user could submit university evaluations for any proposal). |
| `app/Models/EvaluationModel.php` | `forEvaluator()` returns PI, department, submission date, level, draft indicator + supports q/level/status/overdue filters; new `overdueCountForEvaluator()`; `assign()` accepts a level and respects `UNIQUE(application_id, evaluator_user_id)`; **`nextRound()` now counts submitted evaluations only** — previously every 60-second auto-save created a *new* draft row (rounds 1,2,3…) and saved drafts were never reloaded. |
| `app/views/research/my_evaluations.php` | Rewritten: page intro, 4 summary cards (Total Assigned / Pending / Submitted / Overdue), search + level + status + overdue filters, table with Proposal, PI, College/Department, **Evaluation Level**, Assigned, Deadline, Status (incl. "Draft saved"), and status-aware actions (Accept/Decline/Conflict → Open/Continue Evaluation → View Evaluation); meaningful empty states. |
| `app/views/research/evaluation_form.php` | Fixed broken CSS on the Methodology block (missing quote); proposal header now shows co-investigators, submission date and evaluation level. |
| `database/sqlite_schema.sql`, `database/schema.sql` | `evaluator_assignments.assignment_level` column added to both fresh-install schemas. |
| `database/sqlite_seed.sql`, `database/seed.sql` | `research.evaluate` granted to `college_coordinator` and `researcher` roles for fresh installs. |

---

## 3. Database changes

Applied to the live database (`database/mwu_rpee.sqlite`) via `php scripts/migrate_my_evaluations.php`:

1. `ALTER TABLE evaluator_assignments ADD COLUMN assignment_level TEXT NOT NULL DEFAULT 'college'` — labels college vs university assignments (§4 of the feature spec). Zero rows existed at migration time, so no backfill was needed.
2. `INSERT INTO role_permissions …` — `research.evaluate` granted to roles `college_coordinator` and `researcher` so both dashboards can open My Evaluations. **This alone grants page access only**: every evaluation action additionally requires a real `evaluator_assignments` row owned by the requesting user (checked server-side in `form()`, `saveDraft()`, `submit()`, `evaluatorRespond()`, `submitUniversityEvaluation()`).

No tables created, no data rewritten, no duplicate evaluation/assignment tables introduced.

---

## 4. Security gaps found and closed

1. **`submitUniversityEvaluation()` had no authorization beyond the route permission** — any user holding `research.evaluate` could POST an `application_id` and create a submitted university evaluation (plus a budget recommendation) for *any* proposal. Now requires an active assignment for the current user, rejects duplicates, and runs in a transaction.
2. **Draft round computation spawned duplicate rows** — `nextRound()` counted drafts, so each auto-save inserted a new evaluation row and re-opening the form lost the saved draft. Now rounds advance only on *submitted* evaluations: drafts save, reload, and are reused by submission (§6 requirement: no accidental duplicates).

Pre-existing crashes fixed (same root cause: `research_project_members` has no `application_id` column — it is the *project*-stage table; proposal-stage team lives in `application_team_members`):
- `EvaluationController::form()` — the evaluation form page itself crashed.
- `ScreeningController::assignEvaluatorForm()` / `assignEvaluator()` — the coordinator's assign-evaluators page crashed.

---

## 5. Tests performed

### 5.1 Static checks
- `php -l` on every changed PHP file — **all pass** (one parse error found and fixed during development).

### 5.2 Query/model verification — `php tests/verify_eval_queries.php` → **13/13 OK**
Previously-broken queries now execute; `forEvaluator()` with all filters, overdue count, status counts; `assign()` reuses the unique (application, evaluator) row and flips level college→university inside a rolled-back transaction (zero test rows left behind); permission grants confirmed for all 4 roles.

### 5.3 HTTP end-to-end smoke — `bash tests/smoke_my_evaluations.sh` → **38/38 PASS**
Runs `php -S 127.0.0.1:8099` against a *copy* of the database (known passwords, seeded rubric); the copy is deleted afterwards. Verified against the real DB afterwards: 0 assignments, 0 evaluations, real passwords unchanged.

Covers spec §11 scenarios:

| # | Scenario | Result |
|---|---|---|
| 1 | Researcher sees assigned university-level evaluations (marara) | PASS |
| 2 | College-level assignment labelled College level (coordinator) | PASS |
| 3 | Coordinator sees proposals personally assigned to them | PASS |
| 4 | Evaluator cannot see/open another evaluator's assignments (isolation + 302 on form) | PASS |
| 5 | Assigned evaluator opens the proposal + criteria form (200) | PASS |
| 6 | Draft saves via AJAX (`"ok":true`) | PASS |
| 7 | Valid evaluation submits, persists, assignment → completed | PASS |
| 8 | Incomplete submission (no recommendation) rejected, 0 rows written | PASS |
| 9 | Duplicate submission blocked (still exactly 1 row) | PASS |
| 10 | Submitted evaluation still visible after refresh; read-only view shows score | PASS |
| 11 | PI sees `to_researcher` comment; committee-only comments not exposed | PASS |
| 13 | System admin: 403 on my_evaluations, no sidebar item | PASS |
| 15 | assign-evaluators page loads (regression for the fixed crash) | PASS |
| — | Sidebar items on both dashboards, summary cards, empty states, level/PI columns | PASS |

### 5.4 Existing project tests
- `php tests/test_coordinator_workflow.php` — passes (schema, routes, workflow, college decisions).
- Not re-run: `scripts/e2e_acceptance.sh` / `e2e_lifecycle.sh` (44-step suites) — they require a seeded full org tree and mutate the dev DB. All of their `evaluation_submit` payloads include criterion scores and a valid recommendation, which is exactly the server-side contract after my hardening, so they remain compatible (see §6 issue 1 for the one deliberate asymmetry).

---

## 6. Remaining issues / decisions needed

1. **Server vs client required-field asymmetry (needs your decision).** The form marks *Strengths*, *Weaknesses*, *Overall summary* as `required` (browser-enforced), but the server only rejects submissions with **no criterion scores** or an **invalid/missing recommendation** — the repo's own e2e scripts submit without `strengths`/`weaknesses` in two places, so tightening server validation to match the form would break `scripts/e2e_lifecycle.sh` (line ~374) and `scripts/e2e_acceptance.sh` (line ~438). I kept server validation at scores+recommendation to avoid breaking existing suites. If you want strict server-side enforcement of all required text fields, I can add it **and** update both e2e scripts' payloads.
2. **Fresh installs**: schema/seed files updated, so a rebuild from `database/sqlite_schema.sql` + `sqlite_seed.sql` gets everything without running extra migrations. The old `scripts/migrate_assignment_level.php` (corrupted/never run) is superseded by `scripts/migrate_my_evaluations.php`; consider deleting it.
3. **Overdue evaluations are warnings, not blocks** — existing behavior kept: overdue submissions are allowed with a flash warning (the code comments say policy-configurable). Spec asked only that overdue status be *visible*; it now appears as a red card count, a row highlight, and an "overdue" badge.
4. **`tests/smoke_my_evaluations.sh` is Linux/Git-Bash oriented** (`cygpath` used with a fallback); on plain Windows cmd it would need path adjustments.

---

## 7. How to run the tests

```bash
# Query/model verification (safe, rolls back its own rows)
php tests/verify_eval_queries.php

# Full HTTP smoke test (uses a deleted-after copy of the DB, port 8099)
bash tests/smoke_my_evaluations.sh

# Existing coordinator workflow test
php tests/test_coordinator_workflow.php
```

Migration (already applied to the live DB; idempotent):

```bash
php scripts/migrate_my_evaluations.php
```
