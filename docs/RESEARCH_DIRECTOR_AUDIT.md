# Research Director module — architecture audit and redesign proposal

**Repository:** `biblecode123-ai/reep`  
**Audit branch:** `v0/research-director-audit-redesign`  
**Scope reviewed:** Research Director dashboard, calls/themes, evaluation/rubric, university committee, application routing, schema/migrations, authorization, and available workflow tests.  
**Status:** Phase 1 audit and Phase 2 proposal; implementation is in progress on the feature branch. The access-profile and schema changes preserve the original memberships for rollback.

## 1. Existing architecture

The app is an established PHP 8 application with PDO, a route table, controllers/models/services, session authentication, centralized `Gate` permission checks, CSRF checks, audit logging, and both SQLite and MySQL/MariaDB schema paths. Existing migration scripts are intended to be idempotent. The production design must stay within this stack; a React/Next.js rewrite would not fit the repository.

The Research Director surface is spread across `DirectorDashboardController`, `CallController`, `ThemeController`, `RubricController`, `CommitteeController`, `UniversityEvaluationController`, and multiple research views. The dashboard is a single large PHP-rendered view and a controller that assembles many unrelated operational, finance, appeal, agreement, call, and project reports. It currently uses the shared maroon application theme rather than the requested Director-specific purple visual system.

## 2. Findings and requirement conflicts

### Critical: dashboard logic is not a trustworthy executive snapshot

- Several dashboard queues use stage names that do not align with the workflow service's current stage codes; counts can therefore be misleading or empty.
- `committeesNeedingMembers` invents a minimum of three members when no quorum is configured (`COALESCE(quorum_size, 3)`), despite the requested removal of quorum-size requirements.
- The “overdue reports” query counts reports in `submitted`/`approved` states without checking whether a report is overdue; other report metrics query a `monthly_reports` table that is not part of the reviewed schema.
- The evaluations summary counts assignments as “pending assessments” without stage or due-date qualification. “Completed awaiting decision” and “awaiting activation/final report” reuse broad status buckets that do not reliably mean what their labels claim.
- One large page mixes urgent actions with finance and lifecycle summaries and has no focused queue-first hierarchy.

**Proposal:** replace the summary with database-derived stage/queue counts, visible filters for call and period, a compact evaluation progress panel, explicit urgent-work rows, and an activity feed. Define each metric from actual workflow states and due dates; remove inferred quorum/member-count alerts. Keep history and route links. No metric should be represented by hardcoded sample values.

### Critical: evaluation systems currently have two overlapping rubric sources

- `research_call_criteria` stores call-specific criteria and weights; legacy evaluation code still reads its criterion IDs and weights.
- Separately, the schema has versioned `rubric_templates`, criteria, and indicators; the controller publishes immutable versions, and university evaluation already pins a rubric snapshot.
- This means a blanket “replace per-call criteria with global rubrics” migration would orphan or reinterpret historical scores unless old criterion references and rubric snapshots are preserved.

**Proposal:** retain existing records and evaluation history. Add one database-managed, versioned framework catalogue for proposal, final report, presentation, and ethics; preserve stage-specific scoring inputs and immutable snapshots for every submitted assessment. Provide an explicit migration/backfill path before retiring legacy reads. Research Directors may create drafts, publish new versions, and set a current version only after validation; do not mutate versions used by submitted evaluations.

### Critical: roles in the brief conflict with repository-wide authorization

- The installed seed data defines 18 functional role identifiers (including office, finance, repository, ethics, evaluator, committee, coordinator, and research roles), not only the four proposed top-level roles.
- Controllers and services across the whole application use these identifiers directly through `Gate::hasRole`, role-to-permission grants, and role-targeted notifications. This is not isolated to the Research Director screen.
- Committee membership and evaluator availability/assignment already have domain tables, but some responsibilities are still represented as roles. Converting this safely requires identifying a primary user category while moving delegated duties to explicit capability grants or entity assignments.

**Proposal:** adopt exactly four primary account categories (Admin, Research Director, Coordinator, Researcher); model evaluator eligibility, committee membership/seat, reviewer assignment, and other delegated duties as explicit permissions/assignments rather than additional primary roles. Preserve old role rows and foreign-key references during the transition; map users and migrate grants in a reversible, audited migration. Do not delete legacy IDs or silently grant new privileges. This requires a repository-wide authorization pass and expanded authorization tests, not a Director-only edit.

### Critical: the current committee workflow is not a permanent single URC model

- `CommitteeController::assign()` accepts any active committee; schema permits multiple university research committees and committee assignments.
- Committee decisions are recorded on a committee assignment and can be submitted by any active member of that assigned committee. There are no dedicated tables for meeting agendas, attendance, minutes, collective evaluation, decision revisions, or their immutable history in the reviewed schema.
- University proposal scoring is also implemented as evaluator assignments and individual evaluations. This differs from the brief's required committee-only university evaluation and collective assessment, and committee quorum is currently represented in schema/dashboard logic.
- The demo seeder assigns the Director a URC secretary seat, contrary to the requested rule that Director status must not confer committee membership automatically.

**Proposal:** create one stable University Research Committee record, explicit membership/seat management, and separate meeting/agenda/attendance/minute records. Use member-submitted criterion evaluations and a distinct collective decision/evaluation record that stores the framework version snapshot. Make decisions and meeting minutes auditable and append-only; corrections create a superseding version with an actor, timestamp, and reason. Remove quorum gates. Assignment to an individual evaluator must never substitute for committee membership or collective approval.

### Critical: research themes are not yet consistently related to calls

- The schema has a legacy single `research_calls.research_theme_id` as well as call-theme association/migration work. Existing researcher application data has a single `research_theme_id`.
- Call/application code still contains legacy single-theme assumptions; application validation checks an active theme but does not, in the reviewed code path, confirm the theme is associated with the selected call.
- The schema uses `status` for research theme activation while application validation queries `is_active`; this is a likely schema/runtime mismatch that needs a cross-driver migration and tests.

**Proposal:** make a normalized many-to-many call/theme relation authoritative; allow only themes associated with the chosen call when applying; retain the application’s single selected theme if that remains the intended rule. Backfill the association from the legacy call theme without removing its column. Add driver-aware constraints/indexes and an idempotent migration, and make theme activation consistent across schema, models, forms, and validation.

### Significant: security and workflow boundaries need explicit coverage

- There is a centralized `Gate` and entity-level authorization in parts of the research workflow; these are good foundations. New Director actions must still verify role/permission, CSRF, and entity scope server-side, not just hide buttons.
- Existing assignment workflows can enforce “all active assigned evaluators completed” and the Director flow has a configurable university evaluator count. These are not equivalent to the required rule of at least two submitted college evaluations, excluding pending assignments, then a Coordinator college decision.
- Existing Director evaluator assignment includes evaluator availability, conflicts, and research-team checks. Any transition to committee evaluation must keep conflict checks, reduce sensitive information exposure, and log decisions.
- The four-role change affects notifications, document access, dashboards, coordinator scoping, and many other controllers. Tests that only inspect one role would be insufficient.

## 3. Proposed redesigned Director navigation and interface

Keep the current server-rendered PHP architecture and shared design tokens. Add a Director-only navigation group with **Overview**, **Research Calls**, **Themes**, **Evaluation Frameworks**, **University Research Committee**, and **Settings**. Use permission checks on both links and routes. Do not expose personal researcher-only tasks or unrelated admin controls in this group.

Use a calm academic purple palette with accessible contrast, white/light-neutral cards, status colors with text labels, and the existing system font. Keep the header, spacing, focus, and responsive conventions already provided by `app.css`; scope purple accents to Director screens instead of changing the entire institution brand. On narrow viewports, collapse the Director group into a keyboard-accessible menu.

The overview should prioritize a short set of factual metrics and action queues:

1. submitted proposals awaiting Director triage;
2. proposals with the required number of submitted college evaluations awaiting Coordinator decisions;
3. items awaiting committee assignment/meeting decision;
4. approved proposals awaiting a funding decision;
5. active calls and upcoming deadlines.

Use real workflow and date predicates; display the data range and a clear empty state. Avoid unsupported committee-quorum and “overdue” labels. Keep links to existing details and provide accessible chart text/data tables for any trend view.

## 4. Safe delivery sequence

1. **Audit and design (current):** record actual schema, route, authorization, workflow, dashboard, and evaluation contracts; agree on the primary-role migration scope.
2. **Compatibility layer:** create cross-driver, idempotent schema migrations first; preserve theme links, role IDs, decisions, evaluations, historical criteria, and rubric snapshots. Backfill only derivable facts and record migration provenance.
3. **Director workflows:** implement calls/themes, stage-specific framework authoring and publication, single-URC membership/meeting records, collective decisions, budget decisions, and safe submission/return actions. Maintain transactional writes, CSRF, permission/scope checks, and audit trails.
4. **Coordinator handoff:** require at least two submitted college assessments (not merely assigned/accepted evaluators) before Coordinator decision; keep Director read-only at that step. Do not allow Director to bypass the Coordinator's decision.
5. **Role conversion:** migrate to four primary categories and explicit scoped assignments/capabilities across all consuming modules, with an audited reversible mapping and regression tests. Do not remove old role identifiers before every consumer is migrated.
6. **UI and verification:** deliver responsive Director navigation, dashboard, call/theme/framework/committee screens; validate SQLite and MySQL-compatible schema paths, workflow permissions, data preservation, empty/error states, keyboard access, and browser rendering.

## 5. Verification plan

- Schema: apply fresh-install schema and each idempotent migration on SQLite; test MySQL/MariaDB syntax and rerun migrations safely.
- Data safety: fixture old single-theme calls, applications, old role grants, rubric versions, submitted evaluations, committee decisions; prove every row remains addressable and score meaning is unchanged.
- Workflow: exactly one URC; no quorum check; member-only individual/collective evaluation; decision revision preserves earlier versions; funding decision only after committee approval; at least two submitted college evaluations before coordinator decision; Director cannot perform the Coordinator decision.
- Security: CSRF, permission denial, object scope, direct-route access, and audit log assertions for every mutation.
- UI: responsive Director group, keyboard navigation, labels/contrast, real DB empty/loading/error states, and browser verification on desktop and mobile.

## 6. Review gate

The largest unresolved scope decision is the primary-role migration. It must cross all authorization consumers to meet the four-role requirement; limiting it to the Director pages would leave the system internally inconsistent. The recommended approach is a staged, reversible repository-wide conversion: exactly four primary account categories, with evaluator/committee/reviewer duties represented as scoped assignments and capabilities, preserving the legacy role rows until all consumers and user mappings are verified.
