# MWU RPEE — Phase 1 System Design

**Research, Publication, Ethics and Extension Office — Integrated Research Management System**

Version 1.0 · Phase 1 deliverable (spec §116) · Stop-for-review artifact

---

This document records the system design produced **before** application code, per spec §116. It is the
review gate: the build that follows implements **this** design. Institutional decisions that are not
defined by the specification are listed in §9 as `POLICY DECISION REQUIRED` and are never invented
(Rule 1). All institutional data lives in the database, never in code (Rule 2).

---

## 1. Technology Stack (spec §2)

| Layer     | Choice                                                        |
|-----------|---------------------------------------------------------------|
| Language  | PHP 8.x (no frameworks)                                       |
| Database  | MySQL / MariaDB via **PDO + prepared statements**             |
| Auth      | PHP sessions, `password_hash()` / `password_verify()`         |
| Frontend  | HTML5, CSS3, vanilla JS, `fetch()` AJAX where useful          |
| Storage   | Files **outside** the web root, streamed by `download.php`    |
| Web       | Any PHP-capable server; document root = `/public`             |

Explicitly excluded (per spec): Laravel, Symfony, CodeIgniter, React, Vue, Angular, Next.js,
Node.js backend, any PHP framework.

Verified environment: PHP 8.2.12 (pdo_mysql, fileinfo, mbstring, session), MariaDB 10.4.32.

---

## 2. High-Level Architecture

```
/public  ← only web-exposed directory (front controller, assets, download endpoint)
/app
  /controllers   thin HTTP layer: validate → authorize → call service → render
  /models        query classes (SQL + row mapping only, no business rules)
  /services      business logic, transactions, workflow, numbering
  /repositories  (reserved; heavy query modules may live here)
  /middleware    AuthMiddleware, CsrfMiddleware, PermissionMiddleware
  /helpers       functions.php (e(), csrf_field(), money(), badge helpers…)
  /views         PHP templates: layouts/, auth/, dashboards/, admin/, errors/
/config          database.php, app.php, security.php
/routes/web.php  route table (method, pattern, handler, permission)
/database        schema.sql, seed.sql
/storage         NEVER web-served: research/, ethics/, publication/, extension/,
                 repository/, private/ (logs)
/scripts         install.php, (future: backup.php, cron.php)
/docs            this design document
```

### Request lifecycle

```
index.php → autoload → session boot → Router
  → AuthMiddleware (session validity, timeout, regeneration, throttling state)
  → CsrfMiddleware (POST/PUT/DELETE token check)
  → PermissionMiddleware (Route declares required permission; Gate decides)
  → Controller → Service (transaction) → Model (PDO)
  → View render (all output escaped via e())
```

### Key design decisions

1. **Front controller + route table** — every page is routed through `index.php`; `login.php`,
   `logout.php` and `download.php` exist as thin public entry points per spec §91 but share the
   same bootstrap. No page embeds its own auth checks (Rules 4, 5).
2. **Authorization is centralized in `Gate`** (RBAC + permission cache per request). Controllers
   never hand-roll permission logic (Rule 5).
3. **File access is centralized** in `DocumentService` + `download.php`. Only one code path serves
   files; it re-checks auth, permission, entity link and visibility on every request (Rule 6).
4. **Workflow engine is generic**: `workflows` → `workflow_stages` → `workflow_transitions`
   (DB-driven). Entities reference a workflow + current stage. Transitions are validated by
   `WorkflowService::canTransition()` (from-stage, to-stage, role permission, conditions) and every
   fired transition writes `workflow_history` (Rules 4, 9).
5. **Documents are versioned, never overwritten** (§26): `documents` rows are append-only;
   each new upload creates a new row linked to the same entity + doc type, superseding the old.
6. **Audit is write-only from the app**: `AuditService::log()` on every state-changing action
   (Rule 7); no UI offers edit/delete of audit rows.
7. **Numbering** (`MWU-RPEE-YYYY-NNNN` for projects etc.) comes from `number_sequences`
   (per-year atomic counters), not hardcoded formats (§35, §103).

---

## 3. Entity Model (§5)

```
Research Call ─1:N→ Research Application ─1:1→ Research Project ─1:N→ Research Outputs
                                        │                │
                                        │                ├─1:N→ Members, Budget, Milestones,
                                        │                │       Progress Reports, Presentations
                                        ├─1:1→ Ethics Application ─1:N→ Reviews/Amendments/Certificate
                                        ├─1:N→ Evaluators/Evaluations
                                        └─1:N→ Documents (versioned)
```

The **application ≠ project** distinction is enforced physically: a research project row is only
created when the university approves an application (§35), and carries the generated project
number. Outputs always reference the project, never the application.

Extension is a parallel, independent module (own calls/applications/projects) that *may* link to a
research project.

---

## 4. ERD — Implemented Core (Phase 1–3)

Implemented now (56 tables):

```
users, roles, permissions, role_permissions, user_roles, password_resets, login_attempts,
colleges, departments, committees, committee_members,
researchers,
research_calls, research_call_eligibility, research_call_requirements, research_call_criteria,
research_applications,
research_evaluator_assignments, evaluator_conflicts,
evaluations, evaluation_scores, evaluation_comments,
revision_requests, revision_responses,
college_decisions, university_decisions,
research_budgets, research_budget_items, budget_revisions, funding_releases,
research_milestones, progress_reports,
research_outputs, publications, publication_authors,
ethics_applications, ethics_reviews, ethics_decisions, ethics_amendments, ethics_certificates,
presentations, presentation_panels, presentation_evaluations,
extension_calls, extension_applications, extension_partners, extension_projects,
extension_activities, extension_impact_reports,
research_datasets, dataset_access_requests,
repository_records, repository_embargoes,
documents, document_access_log,
research_integrity_cases, appeals,
notifications, audit_logs,
system_settings, workflows, workflow_stages, workflow_transitions, workflow_history,
research_project_members (shared with future research_projects), document_types, number_sequences,
research_projects
```

Reserved-but-not-yet-populated by later phases: workflow definitions are seeded for the research
pipeline now; module services (evaluation, ethics, …) activate them phase by phase.

### ERD summary (cardinalities)

```
colleges 1─N departments
departments 1─N users (optional college/department on users)
users N─N roles (user_roles) ; roles N─N permissions (role_permissions)
committees 1─N committee_members N─1 users
researchers 1─1 users (profile fields)
research_calls 1─N research_applications
research_calls 1─N call_eligibility / call_requirements / call_criteria
research_applications 1─1 research_projects (project created at university approval)
research_projects 1─N project_members (member_type: PI / Co-I / RA, §73)
research_applications 1─N evaluator_assignments N─1 users
evaluator_assignments 1─N evaluator_conflicts
research_applications 1─N evaluations 1─N evaluation_scores / evaluation_comments
evaluations N─1 evaluation_criteria (per-call criteria, §14)
research_applications 1─N revision_requests 1─N revision_responses
research_projects 1─1 research_budgets 1─N research_budget_items
research_budgets 1─N budget_revisions ; 1─N funding_releases
research_projects 1─N research_milestones ; 1─N progress_reports
research_projects 1─N presentations 1─1 presentation_panels 1─N presentation_evaluations
research_projects 1─N research_outputs ; outputs 1─1 publications 1─N publication_authors
research_applications 1─1 ethics_applications 1─N ethics_reviews / ethics_decisions /
                       ethics_amendments / ethics_certificates
extension_calls 1─N extension_applications 1─1 extension_projects
extension_projects 1─N extension_activities / extension_partners / extension_impact_reports
research_projects 1─N research_datasets 1─N dataset_access_requests
research_projects 1─1 repository_records 1─N repository_embargoes
documents: polymorphic (entity_type, entity_id) + document_types; 1 document row = 1 version;
           superseded_by_id chains versions (§26); document_access_log = §105
workflows 1─N workflow_stages 1─N workflow_transitions ; 1─N workflow_history (§94, append-only)
number_sequences: (key, year, next_value) for MWU-RPEE-YYYY-NNNN etc. (§35, §103)
```

### Integrity rules (§89)

- InnoDB, `utf8mb4_unicode_ci`; FKs with `ON DELETE RESTRICT` (protect official records, Rule 8)
  except pure link tables (`CASCADE`) and user-owned soft references (`SET NULL`).
- Unique keys: `users.email`, `users.employee_id`, `colleges.code`, `departments(code,college)`,
  `research_calls.reference_number`, `research_projects.project_number`,
  `ethics_applications.application_number`, `number_sequences(key,year)`, role/permission names.
- `CHECK`-style constraints enforced in `Validator` + services (MariaDB 10.4 CHECK is parsed but
  ignored, so logic lives in PHP).
- Timestamps: `created_at`/`updated_at` everywhere; workflow timestamps on applications (§76) are
  maintained by services in transactions (§111).

---

## 5. Permission Matrix (§8)

Granular, DB-stored, checked via `Gate::allows()` in middleware — never by hiding buttons.
Seeded permissions (grouped):

| Group        | Permissions |
|--------------|-------------|
| research     | view, create, edit, submit, screen, assign, evaluate, review, approve, reject, request_revision, manage |
| ethics       | view, submit, screen, review, decide, manage |
| publication  | view, create, edit, review, approve, manage |
| extension    | view, create, review, approve, manage |
| budget       | view, create, approve, release, modify |
| presentation | view, manage, evaluate |
| document     | view, upload, download, delete, archive |
| repository   | view, publish, archive, manage |
| project      | view, activate, report, finalize |
| users        | view, manage, roles, delegate |
| org          | colleges, departments, committees — view/manage |
| committee    | manage (University Research Committee assignment + decisions; `committee.manage`) |
| system       | workflow.manage, settings.manage, reports.view, audit.view, integrity.view, integrity.manage |

Role → permission assignments are seeded (see `database/seed.sql`); a user may hold multiple roles;
effective permission = union. Assignment to a *specific entity* (e.g. "is this proposal's assigned
evaluator?") is ownership logic inside services, layered on top of RBAC.

`POLICY DECISION REQUIRED — PM-1`: exact per-role grant matrix beyond the seed defaults (spec lists
roles but not their full permission sets).

---

## 6. Workflow State Machine (§18, §92, §94)

Seeded workflow: **Research Application Lifecycle** with the 30 stages of §18 plus terminal states
(§18 "Additional terminal/exception states"), stored in `workflow_stages` with `stage_type`
(start / normal / terminal). Transitions stored with required permission and condition key. The research lifecycle's committee stage sits between
university review and approval; transitions "university_approve" (UNIVERSITY_REVIEW → APPROVED)
and "skip to funded" were removed so every approval is gated by a committee decision
(committee decision rows carry the Rule 9 `recommended_amount`).

```
DRAFT ──submit──▶ SUBMITTED ──college_receive──▶ COLLEGE_RECEIVED
  ──start_screening──▶ UNDER_COLLEGE_SCREENING
  ──screen_forward──▶ EVALUATOR_ASSIGNMENT_PENDING
  ──assign──▶ EVALUATOR_ASSIGNED ──accept──▶ EVALUATOR_ACCEPTED
  ──start_evaluation──▶ UNDER_EVALUATION ──submit_evaluation──▶ EVALUATION_COMPLETED(?)
  ──request_revision──▶ REVISION_REQUIRED ──submit_revision──▶ REVISION_SUBMITTED
  ──re_evaluate──▶ RE_EVALUATION ──college_review──▶ COLLEGE_REVIEW
  ──college_approve──▶ COLLEGE_APPROVED ──send_to_university──▶ SUBMITTED_TO_UNIVERSITY
  ──university_review──▶ UNIVERSITY_REVIEW ──ethics_cleared──▶ … (ETHICS_REVIEW path)
  ──submit_to_committee──▶ UNIVERSITY_COMMITTEE_REVIEW (sort 165)
       conditions: committee_ready / committee_approved / committee_rejected /
       committee_wants_revision (→ back into the revision cycle)
  ──committee_approve──▶ APPROVED
  ──budget_review──▶ BUDGET_REVIEW ──budget_approve──▶ BUDGET_APPROVED
       (gated by condition budget_recorded — allocation recorded, capped by call max)
  ──release_funds──▶ FUNDED
  ──issue_agreement──▶ AGREEMENT (versions in research_agreements)
  ──sign_agreement──▶ (gated by agreement_signed)
  ──activate──▶ ACTIVE ──progress_report──▶ PROGRESS_MONITORING
  ──submit_final──▶ FINAL_REPORT_SUBMITTED ──final_evaluate──▶ FINAL_EVALUATION
  ──schedule_presentation──▶ PRESENTATION_SCHEDULED ──present──▶ PRESENTED
  ──evaluate_presentation──▶ PRESENTATION_EVALUATION
  ──final_approval──▶ FINAL_APPROVAL ──complete──▶ COMPLETED
  ──publish──▶ REPOSITORY ──archive──▶ ARCHIVED
(any non-terminal ──reject/withdraw/cancel/suspend/terminate/expire──▶ terminal exception states)
```

Transition security (§95): `WorkflowService::fire()` validates current stage, transition existence,
required permission, conditions (`ethics_required`, `budget_required`, `documents_complete`,
`evaluations_complete` — counts only evaluations submitted **after** the latest revision —
`committee_*`, `budget_recorded`, `agreement_signed`…) and then, **inside a DB transaction**, writes: entity status update +
`workflow_history` row (from, to, user, comment) + audit log + notifications. History is never
deleted (Rule 9). Ethics stage auto-skips when the application declares no ethics requirement (§93).

`POLICY DECISION REQUIRED — WF-1..4`: who may fire college_receive/screen_forward (coordinator vs
department coordinator); required-evaluator default count; whether ETHICS_REVIEW is a separate
stage in the main pipeline or a parallel track; who fires final_approval (RPEE vs committee).

---

## 7. Document Architecture (§26, §63, §64, §90)

- Central `documents` table (polymorphic entity link, doc type, version chain, visibility, status,
  hash, original filename). Physical files: `storage/{module}/YYYY/{uuid}.bin` — never public.
- `DocumentService::upload()` validates extension+MIME+size against DB-configured allowlists
  (§103 settings), generates safe names (§90), preserves original filename in metadata.
- `download.php?id=N` is the **only** file endpoint: auth → `document.view` → entity-link ACL →
  visibility/embargo check → stream with correct MIME. Changing IDs gains nothing (§114).
- Optional sensitive-access logging via `document_access_log` (§105).
- Retention (§100): `status` lifecycle active→archived; deletion only by admin with reason + audit.

## 8. Security Architecture (§87, §109, §110)

- Sessions: strict mode, `httponly`+`samesite=Lax` cookies, secure flag when HTTPS, 30-min idle
  timeout (config), regeneration on login and privilege change, fingerprint binding (UA hash).
- CSRF: per-session token + rotating token, `csrf_field()` helper, verified in middleware for all
  unsafe methods; same-site cookies as second layer.
- Output escaping: `e()` everywhere in views; JSON via `json_encode` with flags.
- Errors: never shown to users — friendly page + `ERR-YYYY-NNNNN` reference ID; details to
  `storage/private/logs/` (app.log, security.log, error.log) — separate from DB audit trail (§110).
- Login throttling: `login_attempts` per identifier+IP with lockout window.
- Passwords: `password_hash()` bcrypt cost 12; `password_resets` with single-use expiring tokens.
- Uploads: deny-by-default allowlist, `finfo` MIME check, size cap, random names, no execution.

## 9. Open Policy Decisions (Rule 1 — not invented, only flagged)

| ID    | Decision required |
|-------|-------------------|
| PM-1  | Full role→permission matrix confirmation (beyond seeded defaults) |
| WF-1  | Which coordinator level fires "college received" and "screening forwarded" |
| WF-2  | Default required-evaluator count per call (configurable per call; default 2) |
| WF-3  | Ethics as parallel track vs inline pipeline stage |
| WF-4  | Who fires FINAL_APPROVAL (RPEE director vs college committee) |
| NUM-1 | Reference-number formats beyond project number (calls, ethics, repository) |
| RET-1 | Retention periods per record class before Retention Review |
| SEC-1 | Session timeout length and lockout thresholds (defaults: 30 min / 5 attempts / 15 min) |
| DEL-1 | Default deadline values for evaluation/revision/final report (settings placeholders) |
| REP-1 | Quorum rules for committees (spec §29: only if configured) |

None of these block the core build; each has a safe DB-configurable default and is listed for the
institution to decide.

