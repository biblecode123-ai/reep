# Research Director Dashboard Implementation Documentation

## Overview
This document describes the implementation of the comprehensive Research Director Dashboard for the MWU Research Management System (REEP). The dashboard provides real-time visibility into university research governance and monitoring.

## Audit Findings

### Existing Architecture
- **Authentication**: Session-based with `Auth` class, role-based access control via `Gate` class
- **Database**: SQLite with PDO, prepared statements throughout
- **Roles**: system_admin, researcher, college_coordinator, research_director (via `research.manage` permission)
- **Workflow**: State machine with `workflow_stages`, `workflow_transitions`, `workflow_history` tables
- **Design System**: MWU maroon brand (#7a1f2b), responsive CSS grid layout, card-based UI

### Database Schema Key Tables
- `research_calls`: Research call announcements
- `research_applications`: Proposal submissions with workflow status
- `research_projects`: Approved research projects
- `research_agreements`: Research agreements
- `research_appeals`: Budget and rejection appeals
- `committees`: University and college committees
- `application_committee_assignments`: Committee assignments to proposals
- `evaluator_assignments`: Evaluator assignments
- `evaluations`: Evaluation submissions
- `committee_decisions`: Committee recommendations
- `progress_reports`: Project progress reports
- `funding_releases`: Fund disbursement records
- `research_budgets`: Project budgets
- `audit_logs`: System activity log

### Workflow Status Codes (research_applications)
- `DRAFT`: Initial proposal
- `SUBMITTED_TO_RPEE`: Submitted to university
- `AWAITING_UNIVERSITY_EVALUATION`: Awaiting committee assignment
- `UNDER_UNIVERSITY_EVALUATION`: Evaluation in progress
- `AWAITING_UNIVERSITY_DECISION`: Evaluation complete, awaiting decision
- `AWAITING_COMMITTEE_DECISION`: Committee recommendation pending
- `AWAITING_FUNDING_DECISION`: Funding decision pending
- `FUNDING_PENDING`: Funding authorization pending
- `APPROVED`: Approved
- `FUNDED`: Funded
- `REJECTED`: Rejected
- `WITHDRAWN`: Withdrawn

## Files Changed

### 1. Routes Configuration
**File**: `routes/web.php`
**Change**: Added director_dashboard route
```php
'director_dashboard' => [\App\Controllers\DirectorDashboardController::class, 'index', 'research.manage'],
```

### 2. Controller Implementation
**File**: `app/Controllers/DirectorDashboardController.php`
**Changes**: Complete rewrite with comprehensive data queries
- Added 11 summary card queries
- Added 8 priority action queue queries
- Added university evaluation management queries
- Added funding overview queries
- Added appeals overview queries
- Added agreements and project monitoring queries
- Added research calls summary queries
- Added recent activity feed queries

### 3. Dashboard View
**File**: `app/views/dashboards/director.php`
**Changes**: Complete rewrite with 8 sections
- Summary cards grid with action links
- Priority action center with 8 actionable queues
- University evaluation management section
- Funding overview with budget totals
- Appeals overview section
- Agreements and project monitoring section
- Research calls and themes section
- Recent activity and alerts feed

### 4. Navigation
**File**: `app/views/partials/header.php`
**Change**: Added director_dashboard to navigation map
```php
'director_dashboard' => ['Director Dashboard', 'research.manage'],
```

### 5. CSS Styling
**File**: `public/assets/css/app.css`
**Changes**: Added dashboard-specific styles
- `.section-title` for section headers
- `.card a.btn-sm` for card action links
- `.tbl tr.danger` for overdue item highlighting

## Dashboard Card Definitions and Status Mappings

### Summary Cards

| Card | Query | Status Mapping | Link |
|------|-------|----------------|------|
| Active Research Calls | `research_calls WHERE status IN ("open","active")` | Call status | calls_public |
| Awaiting Committee Assignment | `research_applications WHERE status_code IN ("SUBMITTED_TO_RPEE","AWAITING_UNIVERSITY_EVALUATION")` | Proposal workflow | applications |
| University Evaluations In Progress | `research_applications WHERE status_code = "UNDER_UNIVERSITY_EVALUATION"` | Proposal workflow | applications |
| Completed Awaiting Decision | `research_applications WHERE status_code IN ("AWAITING_UNIVERSITY_DECISION","AWAITING_COMMITTEE_DECISION")` | Proposal workflow | applications |
| Funding Decisions Pending | `research_applications WHERE status_code IN ("AWAITING_FUNDING_DECISION","FUNDING_PENDING")` | Proposal workflow | applications |
| Open Budget Appeals | `research_appeals WHERE appeal_type = "budget" AND status IN ("submitted","under_review")` | Appeal status | appeals |
| Open Rejected Appeals | `research_appeals WHERE appeal_type = "rejection" AND status IN ("submitted","under_review")` | Appeal status | appeals |
| Appeals Awaiting Committee | `research_appeals WHERE status = "submitted"` | Appeal status | appeals |
| Agreements Pending | `research_agreements WHERE status IN ("draft","awaiting_signature","pending_signature")` | Agreement status | agreements |
| Active Projects | `research_projects WHERE status_code = "ACTIVE"` | Project status | projects |
| Overdue Reports | `progress_reports WHERE status_code IN ("OVERDUE","LATE")` | Report status | projects |

### Priority Action Center Queues

| Queue | Query | Display Fields | Action |
|-------|-------|----------------|--------|
| Proposals Awaiting Committee Assignment | Proposals with SUBMITTED_TO_RPEE or AWAITING_UNIVERSITY_EVALUATION | Reference, Title, PI, College, Submitted | Assign Committee |
| Committees Missing Members | Committees where member_count < quorum_size | Committee, Type, Current Members, Required | Manage Members |
| Overdue Evaluations | Evaluator assignments with deadline < now and status in (assigned, accepted) | Reference, Title, Evaluator, Deadline, Status | Resolve |
| Completed Awaiting Decision | Proposals with AWAITING_UNIVERSITY_DECISION or AWAITING_COMMITTEE_DECISION | Reference, Title, PI, Completed | Record Decision |
| Funding Decisions Pending | Proposals with AWAITING_FUNDING_DECISION or FUNDING_PENDING | Reference, Title, PI, Requested, Approved | Authorize |
| Appeals Awaiting Review | Appeals with status in (submitted, under_review) | Type, Reference, Title, PI, Status, Submitted | Review |
| Agreements Awaiting Signature | Agreements with status in (draft, awaiting_signature, pending_signature) | Reference, Title, PI, Version, Status | Sign |
| Projects with Overdue Reports | Active projects with OVERDUE or LATE reports | Project, Title, Due Date, Report Status | View Project |

### University Evaluation Management

| Metric | Query |
|--------|-------|
| Proposals Received | Count proposals in university evaluation stages |
| Active Committees | Count committees with type = university_research |
| Assigned Evaluators | Count evaluator assignments with status in (assigned, accepted) |
| Completed Evaluations | Count evaluations with status = submitted |
| Pending Assessments | Count evaluator assignments with status in (assigned, accepted) |

### Funding Overview

| Metric | Query |
|--------|-------|
| Requested Budget | SUM(budget_requested) from proposals (excluding draft/withdrawn) |
| Committee Recommended | SUM(recommended_amount) from committee_decisions |
| Officially Approved | SUM(budget_approved_amount) from research_applications |
| Allocated Funds | SUM(approved_budget) from research_budgets where status = approved |
| Disbursed Funds | SUM(amount) from funding_releases |
| Reported Expenditure | SUM(expenditure_amount) from progress_reports |

### Appeals Overview

| Metric | Query |
|--------|-------|
| Budget Appeals | COUNT(*) from research_appeals where appeal_type = budget |
| Rejected Appeals | COUNT(*) from research_appeals where appeal_type = rejection |
| Pending Review | COUNT(*) from research_appeals where status in (submitted, under_review) |
| Under Review | COUNT(*) from research_appeals where status = under_review |
| Awaiting Implementation | COUNT(*) from research_appeals where status = approved |
| Closed Appeals | COUNT(*) from research_appeals where status in (rejected, closed) |

### Agreements and Project Monitoring

| Metric | Query |
|--------|-------|
| Awaiting Agreement Preparation | Count approved/funded proposals without agreements |
| Awaiting Signature | Count agreements with status in (draft, awaiting_signature, pending_signature) |
| Awaiting Activation | Count projects with status = PENDING_ACTIVATION |
| Active Projects | Count projects with status = ACTIVE |
| Extension Requests | Count extension requests with status = pending |
| Amendment Requests | Count budget revisions with status = pending |
| Awaiting Final Report | Count projects with status = FINAL_REPORT_SUBMITTED |
| Awaiting Repository | Count completed projects without repository records |

### Research Calls Summary

| Metric | Query |
|--------|-------|
| Active Calls | COUNT(*) from research_calls where status = open |
| Upcoming Calls | COUNT(*) from research_calls where status = published and opens_at > now |
| Closed Calls | COUNT(*) from research_calls where status = closed |
| Archived Calls | COUNT(*) from research_calls where status = archived |

## Role and Permission Checks

### Authorization
- **Route Permission**: `research.manage` required for director_dashboard
- **Controller Check**: `Gate::authorize('research.manage')` in DirectorDashboardController::index()
- **Navigation Visibility**: Director Dashboard link only shown to users with `research.manage` permission

### Role-Based Access
- **Research Director**: Has `research.manage` permission, can view full dashboard
- **College Coordinator**: Has `research.view` permission, cannot access director dashboard
- **Researcher**: Has `researcher.view` permission, cannot access director dashboard
- **System Administrator**: Has all permissions, can access director dashboard

### Object-Level Access
- Dashboard queries do not expose confidential committee assessments
- Audit log activity feed respects role-based visibility
- Links to detail pages enforce their own permission checks via routes

## Security and Data Integrity

### Implemented Security Measures
1. **Server-Side Authorization**: Route-level permission check before controller execution
2. **Prepared Statements**: All database queries use PDO prepared statements
3. **Output Escaping**: All user-facing data escaped with `e()` function
4. **CSRF Protection**: Inherited from middleware (POST requests)
5. **Session Management**: Existing session timeout and regeneration
6. **SQL Injection Prevention**: Parameterized queries throughout

### Data Integrity
- No hardcoded statistics, names, or counts
- All figures derived from live database queries
- Status transitions validated by existing workflow engine
- Foreign key constraints protect related records
- Audit trail preserved in audit_logs table

## Testing Instructions

### Manual Testing Steps

#### 1. Access Control Testing
```
1. Login as user without research.manage permission
2. Attempt to access index.php?action=director_dashboard
3. Expected: 403 Forbidden error
4. Login as user with research.manage permission
5. Access index.php?action=director_dashboard
6. Expected: Dashboard loads successfully
```

#### 2. Dashboard Count Verification
```
For each summary card:
1. Note the count displayed on the dashboard
2. Query the database directly to verify the count
3. Example: SELECT COUNT(*) FROM research_calls WHERE status IN ("open","active")
4. Expected: Dashboard count matches database query
```

#### 3. Link Testing
```
For each card and action link:
1. Click the link
2. Verify the target page loads
3. Verify the page is authorized for the current user
4. Verify the page shows relevant filtered data
```

#### 4. Empty State Testing
```
1. Create a test database with no data
2. Access the director dashboard
3. Expected: All counts show 0, all queues show "No [items]" messages
4. Verify no PHP errors or warnings
```

#### 5. Overdue Item Testing
```
1. Create an evaluator assignment with a past deadline
2. Access the director dashboard
3. Expected: Assignment appears in Overdue Evaluations queue with red highlighting
```

#### 6. Permission Testing
```
1. Login as Research Director
2. Verify all navigation items are visible
3. Verify all action links work
4. Login as College Coordinator
5. Verify Director Dashboard link is not visible
6. Attempt direct URL access
7. Expected: 403 Forbidden
```

### Automated Testing Recommendations

#### Database Query Validation
```php
// Test that queries return expected results
public function testDashboardCounts()
{
    $stats = $this->getDashboardStats();
    $this->assertEquals(
        Database::scalar('SELECT COUNT(*) FROM research_calls WHERE status IN ("open","active")'),
        $stats['activeCalls']
    );
}
```

#### Permission Test
```php
public function testDirectorDashboardRequiresPermission()
{
    $this->actingAs($this->coordinator)
         ->get('/index.php?action=director_dashboard')
         ->assertStatus(403);
    
    $this->actingAs($this->director)
         ->get('/index.php?action=director_dashboard')
         ->assertStatus(200);
}
```

## Unresolved University Policy Questions

### 1. Funding Authorization Authority
**Question**: Does the Research Director personally authorize funding, or is there a separate approval workflow?
**Current Implementation**: Dashboard shows "Funding Decisions Awaiting Authorization" but routes to `application_budget` action. The actual authorization logic should be verified against university policy.
**Recommendation**: Confirm with university policy whether the Director has direct budget approval authority or if this requires additional approval steps.

### 2. Appeal Committee Composition
**Question**: What are the requirements for appeal committee membership?
**Current Implementation**: Dashboard shows appeals awaiting committee appointment but does not enforce specific committee composition rules.
**Recommendation**: Define appeal committee member requirements (e.g., must not have participated in original evaluation, minimum quorum, chair appointment).

### 3. Quorum Enforcement
**Question**: Should committee quorum requirements be enforced at the database level or application level?
**Current Implementation**: Dashboard shows committees where member_count < quorum_size but does not prevent committee actions.
**Recommendation**: Decide whether to add database constraints or application-level validation for quorum requirements.

### 4. Overdue Definition
**Question**: What constitutes an overdue evaluation or report?
**Current Implementation**: Overdue defined as deadline < DATE("now"). This may need adjustment for business days vs calendar days.
**Recommendation**: Clarify overdue calculation rules (business days, grace periods, weekend considerations).

## Performance Considerations

### Query Optimization
- All dashboard queries use LIMIT clauses to prevent large result sets
- Summary counts use aggregate functions for efficiency
- Consider adding database indexes on frequently queried columns:
  - `research_applications(status_code)`
  - `research_calls(status)`
  - `research_appeals(status, appeal_type)`
  - `evaluator_assignments(status, deadline)`
  - `progress_reports(status_code, due_date)`

### Caching Opportunities
- Summary card counts could be cached for 5-10 minutes
- Recent activity feed could use a longer cache
- Funding totals could be cached with invalidation on budget changes

## Browser Compatibility

The dashboard uses:
- CSS Grid (supported in all modern browsers)
- CSS Custom Properties (supported in all modern browsers)
- Standard HTML5 elements
- No JavaScript frameworks

**Minimum Browser Requirements**:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Maintenance Notes

### When Adding New Workflow States
1. Update status mappings in this document
2. Add appropriate queries to DirectorDashboardController
3. Update dashboard view if new cards or queues needed
4. Test count accuracy with new states

### When Adding New Database Tables
1. Consider if table should appear on dashboard
2. Add summary count if relevant
3. Add action queue if requires director attention
4. Update documentation

### When Changing Permissions
1. Update route permission in web.php
2. Update navigation map in header.php
3. Test access control
4. Update documentation

## Conclusion

The Research Director Dashboard provides comprehensive, real-time visibility into university research operations. All displayed figures are backed by live database queries, and all workflow actions respect existing authorization rules. The dashboard prioritizes pending actions above decorative elements, following the principle that the Director should immediately see what requires attention.

The implementation reuses existing architecture, maintains data integrity, and follows the established MWU-RMS design system. No mock statistics or hardcoded values are used, ensuring the dashboard remains accurate as the system evolves.
