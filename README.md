# MWU RPEE — Integrated Research Management System

Madda Walabu University · Research, Publication, Ethics and Extension Office

A traditional **PHP 8 / MySQL (MariaDB) / vanilla JS** web application — no frameworks — implementing
the institutional research lifecycle: call publication → application → college screening → evaluator
assignment → evaluation → college/university approval → budget → funding, with document versioning,
audit trail and a database-driven workflow engine.

**Status: Phases 1–10 of the spec's phased plan (§116) are built and verified.** Design gate, core
infrastructure, organization, calls, applications, evaluation, revision, approval/funding, the
**University Research Committee module** (assignment → funding recommendation → decision),
**budget allocation** and the **research agreement module** (versioned issue → researcher sign)
are verified end-to-end, as are the complete **Ethics module** (screening → review → decision →
certificate → expiry → amendments) and the **Implementation module** (project activation with
MWU-RPEE-YYYY-NNNN numbers, milestones, progress reports, funding releases, deadline extensions,
suspension/cancellation). The **Repository module** (add/edit/delete completed research, attach the
final research document with byte-verified downloads, visibility + status control) is also verified
end-to-end. Finalization, publication, extension and reporting modules remain (see Roadmap).

The complete spec §35 acceptance scenario (44 steps, ~222 assertions — researcher profile →
proposal → coordinator → evaluators → evaluation → revision → re-evaluation → college approval →
directorate → university committee → funding recommendation → directorate approval → budget →
agreement issued → researcher signs → active → progress → final report → completion → archive →
audit) runs green via `bash scripts/e2e_acceptance.sh`.

---

## Requirements

- PHP 8.1+ with `pdo_sqlite` (default), `fileinfo`, `mbstring`, `session`
  (or `pdo_mysql` if using the optional MySQL backend)
- No database server needed — SQLite is the default (file: `database/mwu_rpee.sqlite`)
- Any PHP-capable web server (Apache/XAMPP verified); document root = `public/`

## Installation

```bash
# 1. Optional: override DB settings (defaults: SQLite at database/mwu_rpee.sqlite)
#    To use MySQL/MariaDB instead: DB_DRIVER=mysql DB_HOST=127.0.0.1
#    DB_NAME=mwu_rpee DB_USER=root DB_PASS=...

# 2. Run the installer (creates DB, loads database/sqlite_schema.sql + sqlite_seed.sql
#    — or schema.sql + seed.sql for MySQL — prepares storage/, creates the admin)
php scripts/install.php

# 2b. EXISTING installs: convert legacy multi-role grants to one primary category
#     and separate responsibilities; original memberships are archived for rollback.
php scripts/migrate_call_themes.php
php scripts/migrate_role_assignments.php  # idempotent; run before deploying the updated app
# If the deployment is rolled back, restore the original role links with:
php scripts/rollback_role_assignments.php

# 2c. Apply lifecycle schema/workflow additions idempotently
php scripts/migrate_lifecycle.php   # safe to re-run ("nothing to do" when up to date)

# 3a. Quick dev server:
php -S 127.0.0.1:8099 -t public
#    → http://127.0.0.1:8099/login.php

# 3b. XAMPP / Apache: copy the WHOLE project folder into htdocs/
#    e.g. C:\\xampp\\htdocs\\REEP, then simply open:
#    http://localhost/REEP/
#    The root index.php forwards to public/ (the login page). No virtual-host
#    or DocumentRoot change is needed. app/, config/, database/, storage/,
#    routes/, scripts/ and docs/ are blocked from the web by .htaccess files.
```

First login forces a password change (accounts are seeded with `must_change_password = 1`).

### Installer-provided accounts (when you answer "yes" to sample data)

| Account                  | Account category / responsibility | Initial password  |
|--------------------------|---------------------------|-------------------|
| `admin@mwu.edu.et`       | System Administrator      | (chosen at install) |
| `director@mwu.edu.et`    | RPEE Director             | `Password123!`    |
| `officer@mwu.edu.et`     | RPEE Officer              | `Password123!`    |
| `ccoordinator@mwu.edu.et`| College Coordinator (CNCS)| `Password123!`    |
| `dcoordinator@mwu.edu.et`| Dept Coordinator (CS)     | `Password123!`    |
| `researcher@mwu.edu.et`  | Researcher (CNCS/CS)      | `Password123!`    |
| `evaluator@mwu.edu.et`   | Evaluator (CAVM/ANSC)     | `Password123!`    |
| `finance@mwu.edu.et`     | Finance Officer           | `Password123!`    |
| `ethics@mwu.edu.et`      | Ethics Reviewer           | `Password123!`    |

> Change all sample passwords before any real deployment. Sample colleges/departments/users are
> demonstration data — the system itself ships with zero hardcoded institutional data (Rule 2).

### Comprehensive seeder — account categories and responsibilities (scripts/seed_roles.php)

Run `php scripts/seed_roles.php` after installing. It is idempotent (re-running updates rather than
duplicates, matched by email) and **resets every account below to the shared demo password
`Password123!`**. Each account gets one of the four primary categories; operational duties are separate responsibilities, while committee membership and evaluator assignments use their dedicated records.

| Account                       | Role                        |
|-------------------------------|-----------------------------|
| `admin@mwu.edu.et`            | System Administrator        |
| `director@mwu.edu.et`         | RPEE Director               |
| `officer@mwu.edu.et`          | RPEE Officer                |
| `committee@mwu.edu.et`        | University Research Committee |
| `ccoordinator@mwu.edu.et`     | College Coordinator (CNCS)  |
| `dcoordinator@mwu.edu.et`     | Dept Coordinator (CS)       |
| `researcher@mwu.edu.et`       | Researcher + PI (CNCS/CS)   |
| `coinvestigator@mwu.edu.et`   | Co-Investigator (CNCS/CS)   |
| `evaluator@mwu.edu.et`        | Evaluator (CAVM/ANSC)       |
| `ethics.member@mwu.edu.et`    | Ethics Committee Member (CSSH) |
| `ethics.reviewer@mwu.edu.et`  | Ethics Reviewer (CSSH)      |
| `publication@mwu.edu.et`      | Publication Officer         |
| `extension@mwu.edu.et`        | Extension Officer           |
| `extension.evaluator@mwu.edu.et` | Extension Evaluator (CAVM) |
| `panel@mwu.edu.et`            | Presentation Panel Member (CET) |
| `repository@mwu.edu.et`       | Repository Manager          |
| `finance@mwu.edu.et`          | Finance Officer             |
| `readonly@mwu.edu.et`         | Read-Only Management        |

The seeder also creates the demo dataset so every dashboard has content: an open grant call with
criteria and document requirements, a FUNDED application with an ACTIVE project (team members,
milestones, approved budget, one funding release), an APPROVED ethics application (completed
review, decision, certificate), a SUBMITTED application awaiting college screening, three
demonstration publications (published / accepted / under review), a scheduled final presentation
with a four-seat panel (chair, member, external expert, secretary), an open extension call with an
approved extension project (activities and a government partner), a published repository record,
plus the organizational structure (6 colleges, 8 departments) and three committees with seat
assignments (University Research Committee, CNCS College Research Committee, Research Ethics
Committee).

---

## Architecture (spec §4, §91, §119)

```
public/            ← only web-exposed directory
   index.php         front controller: auth → CSRF → permission → controller
   login.php         authentication entry point
   download.php      THE single file-serving endpoint (full re-authorization)
   assets/           css + vanilla js
app/
   Core/             Database (PDO singleton), Auth, Gate (RBAC), Csrf, Session,
                     Settings, Validator, Audit, Router, Logger
   Middleware/       AuthMiddleware (login + forced password change), CsrfMiddleware
   Controllers/      thin HTTP layer (validate → authorize → service → view)
   Models/           SQL query classes only
   Services/         WorkflowService (engine), ConditionEvaluator, DocumentService,
                     NotificationService, NumberingService
   Views/            layouts/partials + page templates (all output escaped via e())
routes/web.php      action → [Controller::method, permission] table
config/             app.php, database.php (sqlite default, mysql optional), security.php
database/           schema.sql (77 tables, MySQL) + sqlite_schema.sql (generated),
                    seed.sql + sqlite_seed.sql, make_sqlite.php (regenerates the sqlite files)
storage/            NEVER web-served: research/, ethics/, publication/, extension/,
                    repository/, private/logs/ (+ .htaccess deny)
scripts/            install.php + migrate_lifecycle.php (CLI),
                    e2e_lifecycle.sh / e2e_acceptance.sh (HTTP end-to-end suites)
docs/PHASE1_DESIGN.md  architecture, ERD, permission matrix, state machine, open policy decisions
```

### Key guarantees

- **Authorization is centralized** (`Gate` + per-route permission + per-transition permission in
  `WorkflowService`) — verified: researchers get 403 on admin pages, non-assigned evaluators get
  403 on documents, unauthorized workflow transitions are rejected and logged (Rule 5).
- **One file path in, one file path out** (`DocumentService`): uploads are extension-allowlisted +
  real-MIME-checked + size-capped + randomly named; downloads re-check auth/permission/entity
  link/visibility on every request (Rule 6, §64, §114).
- **Documents version, never overwrite** (§26): verified v1 → superseded, v2 → active.
- **Every transition is transactional and audited** (§94, §111): entity update + `workflow_history`
  row + `audit_logs` row + notifications, rolled back on any failure (Rules 7, 9).
- **Workflow is database-driven** (§92–§96): 3 workflows / 60 stages / 69 transitions seeded;
  disabling a transition in Admin → Workflows removes the path without code changes. The research
  lifecycle includes the University Research Committee stage (sort 165, between university review
  and approval) with `committee_ready` / `committee_approved` / `committee_rejected` /
  `committee_wants_revision`, `budget_recorded`, `agreement_signed` and `evaluationsComplete`
  (post-revision) transition conditions.
- **Numbering from `number_sequences`** (§35): `MWU-RPEE-APP-2026-0001` style, atomic per year.

---

## Verified test results

Two HTTP end-to-end suites (real sessions/CSRF, fresh artifacts per run, idempotent cleanup):

- `bash scripts/e2e_lifecycle.sh` → **122 passed, 0 failed** — full application lifecycle incl.
  ethics, committee review, budget allocation, agreement issue/sign, activation, progress, final
  report, completion and archive, each step performed by the appropriate role.
- `bash scripts/e2e_acceptance.sh` → **222 passed, 0 failed** — the spec §35 44-step acceptance
  scenario with audit-history assertions (workflow transitions + audit actions), college scoping
  (a coordinator cannot manage another college's research) and RBAC denial checks.

Additional QA:

- All changed PHP files lint clean (`php -l`).
- Full lifecycle executed over HTTP with real sessions/CSRF:
  DRAFT → SUBMITTED → COLLEGE_RECEIVED → UNDER_COLLEGE_SCREENING →
  EVALUATOR_ASSIGNMENT_PENDING → EVALUATOR_ASSIGNED → EVALUATOR_ACCEPTED →
  UNDER_EVALUATION → EVALUATION_COMPLETED → COLLEGE_REVIEW → COLLEGE_APPROVED →
  SUBMITTED_TO_UNIVERSITY → UNIVERSITY_REVIEW → APPROVED → BUDGET_REVIEW →
  BUDGET_APPROVED → FUNDED — each step performed by the appropriate user.
- Weighted evaluation scoring (85×50% + 75×50% = 80.00) computed and locked.
- Security: CSRF 419 on missing token; login lockout after configured attempts; 401/403/404 on
  download edge cases; IDOR attempts rejected; forced initial-password change enforced.

---

## Roadmap (spec §116 phases remaining)

| Phase | Scope | Status |
|-------|-------|--------|
| 1 — System design | ERD, permissions, state machine, policy decisions | ✅ `docs/PHASE1_DESIGN.md` |
| 2 — Core infrastructure | auth, RBAC, workflow engine, documents, audit, notifications | ✅ |
| 3 — Organization | colleges, departments, users, committees, classifications | ✅ |
| 4 — Calls | create/publish/configure + eligibility & criteria checks | ✅ core |
| 5 — Applications | proposal, work plan, routing, submission locking | ✅ core |
| 6 — Evaluation | assignment, COI, blind modes, scoring | ✅ core |
| 7 — Revision | versioned request → researcher response → new version → re-evaluation | ✅ verified end-to-end |
| 8 — University approval | directorate, University Research Committee + funding recommendation, budget allocation, research agreement (issue/sign), funding | ✅ verified end-to-end |
| 9 — Ethics | screening, reviews, certificates, expiry, amendments | ✅ verified end-to-end |
| 10 — Implementation | projects, milestones, progress reports, extensions | ✅ verified end-to-end |
| 11 — Finalization | final evaluation, presentations, panels | ⏳ schema ready |
| 12 — Publication | outputs, publication workflow | ⏳ schema ready |
| 13 — Extension | extension module end-to-end | ⏳ schema ready |
| 14 — Repository | repository records CRUD, document upload/download, visibility & status, admin/repository-manager management UI | ✅ verified end-to-end |
| 15 — Reporting | dashboards, exports (CSV/Excel/PDF) | ⏳ |
| 16 — Security & QA | full test matrix, backups | 🔶 core security verified |

All remaining modules' tables already exist in `database/schema.sql` — building them is additive.

## Open policy decisions (Rule 1)

Unresolved institutional choices are flagged in `docs/PHASE1_DESIGN.md` §9
(e.g. WF-2 required evaluator default, WF-4 final-approval authority, RET-1 retention periods,
DEL-1 default deadlines — all currently safe DB-configurable defaults).
