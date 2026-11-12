# Madda Walabu University Research Management System
# Role Interaction Workflow Documentation

## Table of Contents
1. [End-to-End Workflow](#1-end-to-end-workflow)
2. [Swimlane Activity Diagram](#2-swimlane-activity-diagram)
3. [Role Interaction Matrix](#3-role-interaction-matrix)
4. [Proposal Status Lifecycle](#4-proposal-status-lifecycle)
5. [Screen-by-Screen Dashboard Explanations](#5-screen-by-screen-dashboard-explanations)
6. [Notification and Task Assignment Flow](#6-notification-and-task-assignment-flow)
7. [Unresolved University Policy Decisions](#7-unresolved-university-policy-decisions)

---

## 1. End-to-End Workflow

### Complete Story: From System Setup to Project Closure

#### Step 1 — System Setup (System Administrator)

**User:** System Administrator  
**Dashboard:** System Administration Dashboard  
**Actions:**
1. Logs in via `/index.php?action=login`
2. Opens System Administration Dashboard
3. Navigates to **Users** → **Create User**
4. Creates user accounts for:
   - Research Director (role: `research_director`)
   - College Research Coordinators (role: `college_coordinator`, assigned to specific colleges)
   - Researchers (role: `researcher`, assigned to specific colleges and departments)
5. Navigates to **Organizations** → **Colleges**
6. Creates colleges (e.g., College of Natural Sciences, College of Social Sciences)
7. Navigates to **Organizations** → **Departments**
8. Creates departments within each college
9. Navigates to **Settings** → **Workflow Configuration**
10. Configures evaluation templates, required document types, and permission rules
11. Activates all accounts

**System Response:**
- User accounts created with correct roles and permissions
- Organizational structure established
- Workflow settings saved
- Audit log entries created for each administrative action

**Result:** The platform is ready for research operations. All other roles can log in and perform their authorized functions.

---

#### Step 2 — Research Call Publication (Research Director)

**User:** Research Director  
**Dashboard:** Research Director Dashboard (`/index.php?action=director_dashboard`)  
**Actions:**
1. Logs in and opens Research Director Dashboard
2. Clicks **Create Research Call** (navigates to `/index.php?action=call_form`)
3. Enters call details:
   - Title: "2024 Research Call for Climate Change Studies"
   - Reference number: "MWU-RC-2024-001"
   - Description: "Proposals addressing climate adaptation in Ethiopian highlands"
   - Research priorities: "Drought resilience, water management, agricultural adaptation"
   - Research theme: Selects "Environmental Science"
   - Opening date: 2024-01-15
   - Closing date: 2024-03-15
   - Max budget: 500,000 ETB
   - Max duration: 24 months
4. Clicks **Save call**
5. System saves as draft and redirects to `/index.php?action=call_view&id={new_id}`
6. On the call view page, configures eligibility:
   - Clicks **Select All Colleges** checkbox
   - Clicks **Select All Themes** checkbox
   - Clicks **Save configuration**
7. Configures required documents:
   - Adds "Research Proposal Document" (required)
   - Adds "Budget Justification" (required)
   - Adds "CV of Principal Investigator" (required)
   - Adds "Ethics Clearance Certificate" (conditional)
8. Configures evaluation criteria:
   - Adds "Scientific Merit" (weight: 30%)
   - Adds "Feasibility" (weight: 25%)
   - Adds "Relevance to Call" (weight: 25%)
   - Adds "Budget Justification" (weight: 20%)
9. Sets minimum passing score: 70%
10. Clicks **Publish** button (form submits to `/index.php?action=call_publish`)

**System Response:**
- Call status changes from `draft` to `published`
- `published_at` timestamp recorded
- Notification sent to all users with `researcher` role
- Call becomes visible in public calls list
- Audit log entry: `call.published`

**Result:** Researchers can now view the call and prepare proposals. Eligible researchers receive notification.

---

#### Step 3 — Proposal Submission (Researcher)

**User:** Researcher (Principal Investigator)  
**Dashboard:** Researcher Dashboard (`/index.php?action=researcher_dashboard`)  
**Actions:**
1. Logs in and opens Researcher Dashboard
2. Sees notification: "New research call: 2024 Research Call for Climate Change Studies"
3. Clicks notification or navigates to **Research Calls** (`/index.php?action=calls_public`)
4. Opens the published call (`/index.php?action=call_view&id=3`)
5. Reviews call requirements, eligibility, documents, and evaluation criteria
6. Clicks **Create Proposal** (navigates to `/index.php?action=application_form&call_id=3`)
7. Enters proposal details:
   - Title: "Drought Resilience Strategies in Bale Zone Smallholder Farming"
   - Abstract: "This study investigates..."
   - Objectives: "1) Assess current drought vulnerability..."
   - Methodology: "Mixed methods approach with surveys..."
   - Timeline: 24 months with quarterly milestones
   - Proposed budget: 450,000 ETB
8. Uploads required documents:
   - Research proposal PDF
   - Budget justification Excel
   - CV of PI
   - Ethics clearance certificate (conditional document)
9. Adds co-investigators:
   - Searches for "Dr. Alemu Bekele"
   - Sends invitation
   - Dr. Alemu receives email notification
   - Dr. Alemu logs in, accepts invitation
10. Reviews completed proposal
11. Clicks **Submit Proposal**

**System Response:**
- Proposal created with application number: "MWU-APP-2024-0042"
- Status set to `DRAFT`
- After submission, status changes to `SUBMITTED_TO_RPEE`
- `submitted_at` timestamp recorded
- Proposal routed to College Research Coordinator based on PI's college assignment
- Notification sent to assigned College Research Coordinator
- Audit log entry: `application.submitted`

**Result:** Proposal appears in the College Research Coordinator's dashboard for screening.

---

#### Step 4 — College Screening (College Research Coordinator)

**User:** College Research Coordinator (College of Natural Sciences)  
**Dashboard:** College Research Dashboard (`/index.php?action=applications&status=SUBMITTED_TO_RPEE`)  
**Actions:**
1. Logs in and opens College Research Dashboard
2. Sees new proposal: "MWU-APP-2024-0042 - Drought Resilience Strategies..."
3. Opens proposal (`/index.php?action=application_view&id=42`)
4. Reviews submission:
   - Checks all required documents are uploaded ✓
   - Verifies PI belongs to assigned college ✓
   - Checks proposal completeness ✓
   - Notes: Ethics clearance certificate is present (conditional requirement met)
5. Identifies issue: Budget justification lacks detailed breakdown for equipment costs
6. Clicks **Request Revision**
7. Enters revision reason: "Please provide detailed breakdown of equipment costs in budget justification"
8. Clicks **Submit Revision Request**

**System Response:**
- Proposal status changes to `AWAITING_COLLEGE_REVISION`
- Revision request recorded in `revision_requests` table
- Notification sent to Principal Investigator
- Audit log entry: `application.revision_requested`

**Researcher Response:**
1. Receives notification: "Your proposal requires revision"
2. Opens proposal and reads coordinator's comments
3. Edits budget justification, adds detailed equipment breakdown
4. Uploads revised document
5. Clicks **Resubmit Proposal**

**System Response:**
- New version of proposal created (version 2)
- Previous version preserved in version history
- Status changes to `SUBMITTED_TO_RPEE` (returns to screening)
- Notification sent to College Research Coordinator
- Audit log entry: `application.resubmitted`

**Coordinator Continues:**
1. Reopens proposal, reviews revised budget justification
2. Confirms all requirements are met
3. Clicks **Forward to College Evaluation**

**System Response:**
- Status changes to `UNDER_COLLEGE_EVALUATION`
- `college_received_at` timestamp recorded
- Notification sent to coordinator: "Ready for evaluator assignment"

**Result:** Proposal is ready for college-level evaluation.

---

#### Step 5 — College Evaluation (College Research Coordinator)

**User:** College Research Coordinator  
**Dashboard:** College Research Dashboard  
**Actions:**
1. Opens proposal (`/index.php?action=application_view&id=42`)
2. Navigates to **Assign Evaluators** tab
3. Searches for eligible evaluators within College of Natural Sciences
4. Filters by: availability status = "available", not in same department as PI
5. Selects three evaluators:
   - Dr. Zenebe Woldemariam (Plant Science)
   - Dr. Hirut Alemu (Environmental Science)
   - Dr. Kefyalew Desta (Agricultural Economics)
6. Clicks **Assign Evaluators**
7. Sets evaluation deadline: 2024-04-01
8. Clicks **Confirm Assignments**

**System Response:**
- Three evaluator assignments created in `evaluator_assignments` table
- Each assignment status set to `assigned`
- `assigned_at` timestamp recorded
- Deadline set for 2024-04-01
- Notifications sent to all three evaluators
- Conflict of interest check performed (no conflicts detected)
- Audit log entries: `evaluator.assigned` (x3)

**Evaluator Actions (Dr. Zenebe Woldemariam):**
1. Receives notification: "You have been assigned to evaluate proposal MWU-APP-2024-0042"
2. Logs in, opens **My Evaluations** (`/index.php?action=my_evaluations`)
3. Opens assignment
4. Reviews proposal and evaluation criteria
5. Checks for conflicts of interest: none
6. Scores each criterion:
   - Scientific Merit: 28/30
   - Feasibility: 22/25
   - Relevance to Call: 24/25
   - Budget Justification: 18/20
   - Total: 92/100
7. Adds comments: "Strong methodology with realistic timeline. Budget is well-justified."
8. Selects recommendation: "accept"
9. Clicks **Submit Evaluation**

**System Response:**
- Evaluation created in `evaluations` table
- Scores recorded in `evaluation_scores` table
- Comments recorded in `evaluation_comments` table
- Assignment status changes to `completed`
- `completed_at` timestamp recorded
- Notification sent to College Research Coordinator
- Audit log entry: `evaluation.submitted`

**Evaluator Actions (Dr. Hirut Alemu):**
1. Similar process, submits evaluation with score: 88/100, recommendation: "accept"

**Evaluator Actions (Dr. Kefyalew Desta):**
1. Similar process, submits evaluation with score: 85/100, recommendation: "accept"

**Coordinator Review:**
1. Opens proposal, views **Evaluations** tab
2. Sees all three evaluations completed
3. Average score: 88.3/100 (above minimum 70%)
4. All recommendations: "accept"
5. Clicks **Record College Decision**

**System Response:**
- College decision recorded in `college_decisions` table
- Decision: "approve"
- Committee recommendation amount: 450,000 ETB
- `decided_at` timestamp recorded
- Status changes to `AWAITING_UNIVERSITY_SUBMISSION`
- Notification sent to Research Director
- Audit log entry: `college.decision_recorded`

**Result:** Proposal forwarded to Research Director for university-level evaluation.

---

#### Step 6 — University Committee Assignment (Research Director)

**User:** Research Director  
**Dashboard:** Research Director Dashboard  
**Actions:**
1. Logs in and opens Research Director Dashboard
2. Sees proposal in "Proposals Awaiting University Evaluation" queue
3. Opens proposal (`/index.php?action=application_view&id=42`)
4. Reviews college evaluation results:
   - Average score: 88.3/100
   - College recommendation: "approve"
   - Recommended budget: 450,000 ETB
5. Navigates to **University Evaluation** tab
6. Clicks **Form University Committee**
7. Searches for eligible committee members across all colleges
8. Selects five members:
   - Dr. Abebe Tekle (Natural Sciences) - Chair
   - Dr. Tigist Mekonnen (Social Sciences)
   - Dr. Birhanu Asfaw (Engineering)
   - Dr. Selamawit Haile (Health Sciences)
   - Dr. Dawit Kebede (Agriculture)
9. Clicks **Assign Committee**
10. Sets evaluation deadline: 2024-04-30
11. Clicks **Confirm**

**System Response:**
- University committee assignment created in `application_committee_assignments` table
- Committee status: `active`
- `assigned_at` timestamp recorded
- Five evaluator assignments created in `evaluator_assignments` table
- Notifications sent to all committee members
- Conflict of interest check performed (no conflicts)
- Audit log entries: `university_committee.assigned`, `evaluator.assigned` (x5)

**Result:** University committee members can now evaluate the proposal.

---

#### Step 7 — University Committee Evaluation (Committee Members)

**User:** Dr. Abebe Tekle (Committee Chair)  
**Dashboard:** Researcher Dashboard (as evaluator)  
**Actions:**
1. Receives notification: "You have been assigned to university committee for MWU-APP-2024-0042"
2. Opens **My Evaluations**
3. Opens committee assignment
4. Reviews proposal, college evaluations, and evaluation criteria
5. Declares no conflict of interest
6. Scores each criterion:
   - Scientific Merit: 27/30
   - Feasibility: 23/25
   - Relevance to Call: 25/25
   - Budget Justification: 19/20
   - Total: 94/100
7. Adds comments: "Excellent proposal with strong potential for impact. Budget is reasonable."
8. Selects recommendation: "accept"
9. Clicks **Submit Evaluation**

**System Response:**
- Evaluation recorded in `evaluations` table
- Scores and comments saved
- Assignment status changes to `completed`
- Notification sent to Research Director

**Other Committee Members:**
- Dr. Tigist Mekonnen: Score 90/100, recommendation "accept"
- Dr. Birhanu Asfaw: Score 89/100, recommendation "accept"
- Dr. Selamawit Haile: Score 91/100, recommendation "accept"
- Dr. Dawit Kebede: Score 87/100, recommendation "accept"

**Research Director Review:**
1. Opens proposal, views **University Evaluation** tab
2. Sees all five committee evaluations completed
3. Average score: 90.2/100
4. All recommendations: "accept"
5. Committee chair's summary: "Excellent proposal with strong potential for impact"

**Result:** Committee evaluation complete, ready for official decision.

---

#### Step 8 — Official Decision (Research Director)

**User:** Research Director  
**Dashboard:** Research Director Dashboard  
**Actions:**
1. Opens proposal (`/index.php?action=application_view&id=42`)
2. Navigates to **Official Decision** tab
3. Reviews:
   - University committee average score: 90.2/100
   - Committee recommendation: "accept"
   - College recommendation: 450,000 ETB
   - Proposed budget: 450,000 ETB
   - Available university research budget: Sufficient
4. Clicks **Record Official Decision**
5. Selects decision: "Approved"
6. Enters approved budget: 450,000 ETB
7. Adds decision comments: "Proposal approved based on university committee recommendation. Budget approved as recommended."
8. Clicks **Confirm Decision**

**System Response:**
- Status changes to `APPROVED`
- `university_approved_at` timestamp recorded
- `budget_approved_amount` set to 450,000 ETB
- `budget_approved_at` timestamp recorded
- Decision recorded in audit log
- Notification sent to Principal Investigator
- Notification sent to College Research Coordinator
- Audit log entry: `application.approved`

**Researcher Response:**
1. Receives notification: "Your proposal has been approved. Budget: 450,000 ETB"
2. Opens proposal to view decision details

**Result:** Proposal is officially approved. Researcher can proceed to agreement phase.

---

#### Step 9 — Agreement Process (Researcher & Research Director)

**User:** Researcher  
**Dashboard:** Researcher Dashboard  
**Actions:**
1. Opens approved proposal
2. Navigates to **Agreement** tab
3. Clicks **Initiate Agreement**
4. Reviews agreement template with project details, budget, and terms
5. Uploads signed agreement document
6. Clicks **Submit Agreement**

**System Response:**
- Agreement record created in `research_agreements` table
- Agreement version: 1
- Status: `draft`
- Notification sent to Research Director

**User:** Research Director  
**Actions:**
1. Receives notification: "Agreement submitted for MWU-APP-2024-0042"
2. Opens proposal, views **Agreement** tab
3. Reviews submitted agreement document
4. Clicks **Approve Agreement**
5. System generates official agreement reference: "MWU-AGR-2024-0015"
6. Status changes to `issued`

**System Response:**
- Agreement status: `issued`
- Agreement reference assigned
- Notification sent to Researcher
- Project record created in `research_projects` table
- Project number: "MWU-PRJ-2024-0042"
- Project status: `APPROVED`

**Result:** Agreement approved, project created, ready for activation.

---

#### Step 10 — Project Implementation (Researcher)

**User:** Researcher  
**Dashboard:** Researcher Dashboard  
**Actions:**
1. Receives notification: "Your project MWU-PRJ-2024-0042 is ready to start"
2. Opens project (`/index.php?action=project_view&id=42`)
3. Navigates to **Project Activation**
4. Confirms start date: 2024-05-01
5. Clicks **Activate Project**

**System Response:**
- Project status changes to `ACTIVE`
- `started_at` timestamp recorded
- Project milestones created based on proposal timeline
- First progress report deadline set

**Research Activities:**
1. Conducts research according to approved methodology
2. Collects data, performs analysis
3. Prepares quarterly progress reports
4. Submits first progress report on 2024-08-01

**Progress Report Submission:**
1. Opens project, navigates to **Progress Reports**
2. Clicks **Submit Progress Report**
3. Enters:
   - Reporting period: Q1 2024 (May-July)
   - Progress percentage: 25%
   - Completed activities: "Literature review completed, field sites identified"
   - Pending activities: "Data collection, preliminary analysis"
   - Challenges: "Rainfall delayed field work by 2 weeks"
   - Achievements: "Established partnerships with 3 farming communities"
   - Financial progress: 20% of budget spent
   - Amount spent to date: 90,000 ETB
4. Uploads supporting documents
5. Clicks **Submit Report**

**System Response:**
- Progress report created in `progress_reports` table
- Report number: "MWU-PRJ-2024-0042-R1"
- Status: `submitted`
- `submitted_at` timestamp recorded
- Notification sent to College Research Coordinator

**Coordinator Review:**
1. Receives notification: "Progress report submitted for MWU-PRJ-2024-0042"
2. Opens project, views progress report
3. Reviews content and documents
4. Approves report
5. Status changes to `approved`

**Result:** Project progresses through quarterly reports. All subsequent reports follow same pattern.

---

#### Step 11 — Final Report Submission (Researcher)

**User:** Researcher  
**Actions:**
1. After 24 months, project completes
2. Opens project, navigates to **Final Report**
3. Clicks **Submit Final Report**
4. Enters:
   - Final progress: 100%
   - All completed activities listed
   - Research outcomes and findings
   - Publications produced
   - Total expenditure: 445,000 ETB
5. Uploads:
   - Final research report
   - Publication manuscripts
   - Dataset (if applicable)
6. Clicks **Submit Final Report**

**System Response:**
- Final report created in `progress_reports` table
- Status: `submitted`
- `final_submitted_at` timestamp recorded
- Notification sent to College Research Coordinator and Research Director

**Final Evaluation:**
1. Coordinator reviews final report
2. Forwards to Research Director for final approval
3. Research Director reviews outcomes, outputs, and expenditure
4. Approves final report
5. Status changes to `approved`

**Result:** Project ready for closure.

---

#### Step 12 — Project Closure and Archiving (Research Director)

**User:** Research Director  
**Actions:**
1. Opens project (`/index.php?action=project_view&id=42`)
2. Navigates to **Project Closure**
3. Reviews:
   - All progress reports approved
   - Final report approved
   - Expenditure within approved budget (445,000/450,000 ETB)
   - Required outputs delivered
4. Clicks **Close Project**
5. System prompts for repository submission
6. Researcher submits to institutional repository
7. Repository record created: "MWU-REP-2026-0042"
8. Research Director confirms closure

**System Response:**
- Project status changes to `COMPLETED`
- `completed_at` timestamp recorded
- `repository_at` timestamp recorded
- Project archived
- All documents preserved in repository
- Audit log entry: `project.completed`

**Result:** Project lifecycle complete. Records available to authorized users.

---

### Exception Path: Appeal Scenario

#### Appeal Submission (Researcher)

**Scenario:** Researcher's proposal was rejected by university committee due to insufficient budget justification. Researcher believes decision was unfair.

**User:** Researcher  
**Actions:**
1. Receives rejection notification
2. Opens proposal, views decision
3. Navigates to **Appeal** tab
4. Clicks **Submit Appeal**
5. Selects appeal type: "rejection"
6. Enters appeal reason: "Budget justification was comprehensive. Committee overlooked detailed equipment breakdown provided in supplementary document."
7. Uploads supplementary evidence
8. Clicks **Submit Appeal**

**System Response:**
- Appeal created in `appeals` table
- Appeal number: "MWU-APL-2024-0003"
- Status: `submitted`
- `submitted_at` timestamp recorded
- Linked to original proposal and decision
- Notification sent to Research Director
- Audit log entry: `appeal.submitted`

**Appeal Review (Research Director):**
1. Receives notification: "Appeal submitted for MWU-APP-2024-0042"
2. Opens appeal (reviewed from the application view: `/index.php?action=application_view&id=42` - no dedicated appeal_view route exists)
3. Reviews appeal reason and evidence
4. Reviews original decision and committee comments
5. Determines appeal has merit
6. Clicks **Appoint Appeal Committee**
7. Selects three committee members (different from original committee)
8. Sets appeal deadline: 2024-05-15
9. Clicks **Confirm**

**System Response:**
- Appeal committee assigned
- Notifications sent to committee members
- Appeal status: `under_review`

**Appeal Committee Evaluation:**
1. Committee reviews appeal, original proposal, and original decision
2. Committee finds original evaluation missed supplementary document
3. Committee recommends: "upheld" (appeal successful)
4. Committee submits recommendation

**Final Appeal Decision (Research Director):**
1. Reviews committee recommendation
2. Agrees with appeal committee
3. Records final decision: "upheld"
4. Original decision overturned
5. Proposal returned to university evaluation stage for reconsideration

**System Response:**
- Appeal status: `upheld`
- Original decision preserved in audit trail
- Proposal status: `AWAITING_UNIVERSITY_EVALUATION`
- New university committee formed
- Notification sent to Researcher
- Audit log entry: `appeal.upheld`, `application.reopened`

**Result:** Appeal successful, proposal reconsidered by new committee.

---

### Exception Path: Evaluator Declines Assignment

**Scenario:** Evaluator assigned to evaluate proposal but has conflict of interest.

**User:** Evaluator (Dr. Zenebe Woldemariam)  
**Actions:**
1. Receives evaluation assignment notification
2. Opens assignment
3. Recognizes PI is former PhD student (conflict of interest)
4. Clicks **Decline Assignment**
5. Selects reason: "Conflict of interest"
6. Adds explanation: "PI was my former PhD student"
7. Clicks **Confirm Decline**

**System Response:**
- Assignment status changes to `conflict_declared`
- Conflict recorded in `evaluator_conflicts` table
- Notification sent to College Research Coordinator
- Audit log entry: `evaluator.conflict_declared`

**Coordinator Response:**
1. Receives notification: "Evaluator declined due to conflict of interest"
2. Opens proposal, evaluator assignments
3. Sees Dr. Zenebe's assignment status: `conflict_declared`
4. Searches for replacement evaluator
5. Assigns Dr. Almaz Tefera (same college, no conflict)
6. New assignment created, notification sent

**Result:** Evaluation continues with new evaluator, conflict properly documented.

---

### Exception Path: Proposal Fails Minimum Score

**Scenario:** Proposal receives average score of 65/100, below minimum 70%.

**User:** College Research Coordinator  
**Actions:**
1. Reviews completed evaluations
2. Average score: 65/100
3. Below minimum passing score: 70%
4. Clicks **Record College Decision**
5. Selects decision: "reject"
6. Enters reason: "Proposal does not meet minimum score requirement (65/100 vs 70% threshold)"
7. Clicks **Confirm**

**System Response:**
- College decision: "reject"
- Status changes to `REJECTED_BY_COLLEGE`
- Notification sent to Principal Investigator
- Audit log entry: `college.rejection_recorded`

**Researcher Response:**
1. Receives rejection notification
2. Can submit appeal if believes decision was unfair
3. Or can revise and resubmit to future call

**Result:** Proposal rejected, researcher can appeal or resubmit.

---

## 2. Swimlane Activity Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                           MWU RESEARCH MANAGEMENT SYSTEM                             │
│                              SWIMLANE ACTIVITY DIAGRAM                               │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────┐  ┌──────────────────────┐  ┌─────────────────────────────────┐
│ SYSTEM ADMINISTRATOR│  │  RESEARCH DIRECTOR    │  │ COLLEGE RESEARCH COORDINATOR   │
└─────────────────────┘  └──────────────────────┘  └─────────────────────────────────┘
         │                          │                              │
         │ 1. Create accounts       │                              │
         │ 2. Setup colleges       │                              │
         │ 3. Configure workflow   │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │ 1. Create research call      │
         │                          │ 2. Configure eligibility     │
         │                          │ 3. Set evaluation criteria  │
         │                          │ 4. Publish call             │
         │                          │──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │                              │
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              RESEARCHER                                              │
└─────────────────────────────────────────────────────────────────────────────────────┘
         │                          │                              │
         │ 1. View call             │                              │
         │ 2. Create proposal       │                              │
         │ 3. Submit proposal       │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │                              │ 1. Receive proposal
         │                          │                              │ 2. Screen submission
         │                          │                              │ 3. Request revision (if needed)
         │                          │                              │    OR
         │                          │                              │ 4. Forward to evaluation
         │                          │                              │──────────────────────────│
         │                          │                              │
         │ ← Revision request       │                              │
         │ 1. Revise proposal       │                              │
         │ 2. Resubmit              │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │                              │ 1. Assign evaluators
         │                          │                              │ 2. Set deadlines
         │                          │                              │──────────────────────────│
         │                          │                              │
         │ ← Evaluation assignment  │                              │
         │ 1. Review proposal       │                              │
         │ 2. Score criteria        │                              │
         │ 3. Submit evaluation     │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │                              │ 1. Review evaluations
         │                          │                              │ 2. Record decision
         │                          │                              │ 3. Forward to university
         │                          │                              │    OR reject/revision
         │                          │                              │──────────────────────────│
         │                          │                              │
         │                          │ 1. Receive proposal          │
         │                          │ 2. Form university committee │
         │                          │ 3. Assign committee members   │
         │                          │──────────────────────────────│──────────────────────│
         │                          │                              │
         │ ← Committee assignment  │                              │
         │ 1. Review proposal       │                              │
         │ 2. Score criteria        │                              │
         │ 3. Submit evaluation     │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │ 1. Review committee results  │
         │                          │ 2. Record official decision  │
         │                          │ 3. Approve budget           │
         │                          │──────────────────────────────│──────────────────────│
         │                          │                              │
         │ ← Approval notification  │                              │
         │ 1. Submit agreement      │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │ 1. Review agreement          │
         │                          │ 2. Approve agreement         │
         │                          │──────────────────────────────│──────────────────────│
         │                          │                              │
         │ ← Agreement approved     │                              │
         │ 1. Activate project      │                              │
         │ 2. Conduct research      │                              │
         │ 3. Submit progress reports│                             │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │                              │ 1. Review progress reports
         │                          │                              │ 2. Approve reports
         │                          │                              │──────────────────────────│
         │                          │                              │
         │ 1. Submit final report   │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │
         │                          │ 1. Review final report       │
         │                          │ 2. Approve closure           │
         │                          │──────────────────────────────│──────────────────────│
         │                          │                              │
         │ 1. Submit to repository  │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │ 1. Archive project           │
         │                          │──────────────────────────────│──────────────────────│

EXCEPTION PATHS:

APPEAL:
         │ ← Rejection              │                              │
         │ 1. Submit appeal         │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │ 1. Review appeal             │
         │                          │ 2. Appoint appeal committee  │
         │                          │──────────────────────────────│──────────────────────│
         │ ← Appeal committee       │                              │
         │ 1. Review appeal         │                              │
         │ 2. Submit recommendation  │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │ 1. Record appeal decision    │
         │                          │ 2. Uphold or reject         │
         │                          │──────────────────────────────│──────────────────────│

EVALUATOR DECLINE:
         │ ← Assignment             │                              │
         │ 1. Decline (conflict)    │                              │
         │──────────────────────────│──────────────────────────────│──────────────────────│
         │                          │                              │ 1. Assign replacement
         │                          │                              │──────────────────────────│
```

---

## 3. Role Interaction Matrix

| Initiating User | Action | Target Record | Receiving User | System Response | Next Status |
|-----------------|--------|---------------|----------------|-----------------|-------------|
| **System Administrator** | Create user account | users table | N/A | Account created, role assigned | Account active |
| System Administrator | Create college | colleges table | N/A | College record created | College active |
| System Administrator | Configure workflow | settings table | N/A | Workflow settings saved | Settings active |
| **Research Director** | Create research call | research_calls table | N/A | Call created as draft | DRAFT |
| Research Director | Configure eligibility | call_eligibility table | N/A | Eligibility rules saved | DRAFT |
| Research Director | Publish call | research_calls table | Researchers | Call visible, notifications sent | PUBLISHED |
| **Researcher** | Create proposal | research_applications table | College Coordinator | Proposal created, routed to college | DRAFT |
| Researcher | Submit proposal | research_applications table | College Coordinator | Status updated, notification sent | COLLEGE_SCREENING |
| **College Coordinator** | Request revision | revision_requests table | Researcher | Revision request created, notification sent | AWAITING_REVISION |
| **Researcher** | Resubmit proposal | research_applications table | College Coordinator | New version created, status reset | COLLEGE_SCREENING |
| **College Coordinator** | Forward to evaluation | research_applications table | N/A | Status updated, notification sent | COLLEGE_EVALUATION |
| **College Coordinator** | Assign evaluator | evaluator_assignments table | Evaluator | Assignment created, notification sent | ASSIGNED |
| **Evaluator** | Submit evaluation | evaluations table | College Coordinator | Evaluation recorded, notification sent | COMPLETED |
| **College Coordinator** | Record college decision | college_decisions table | Research Director | Decision recorded, status updated | UNIVERSITY_SUBMISSION |
| **Research Director** | Form university committee | application_committee_assignments table | Committee Members | Committee created, assignments made | COMMITTEE_ASSIGNED |
| **Committee Member** | Submit evaluation | evaluations table | Research Director | Evaluation recorded, notification sent | COMPLETED |
| **Research Director** | Record official decision | research_applications table | Researcher | Decision recorded, budget approved | APPROVED |
| **Researcher** | Submit agreement | research_agreements table | Research Director | Agreement created, notification sent | AGREEMENT_DRAFT |
| **Research Director** | Approve agreement | research_agreements table | Researcher | Agreement approved, project created | AGREEMENT_ISSUED |
| **Researcher** | Activate project | research_projects table | College Coordinator | Project activated | ACTIVE |
| **Researcher** | Submit progress report | progress_reports table | College Coordinator | Report submitted, notification sent | SUBMITTED |
| **College Coordinator** | Approve progress report | progress_reports table | Researcher | Report approved | APPROVED |
| **Researcher** | Submit final report | progress_reports table | Research Director | Final report submitted, notification sent | SUBMITTED |
| **Research Director** | Approve final report | progress_reports table | Researcher | Final report approved | APPROVED |
| **Researcher** | Submit to repository | repository_records table | Research Director | Repository record created | PUBLISHED |
| **Research Director** | Close project | research_projects table | N/A | Project archived | COMPLETED |
| **Researcher** | Submit appeal | appeals table | Research Director | Appeal created, notification sent | SUBMITTED |
| **Research Director** | Appoint appeal committee | appeals table | Appeal Committee | Committee assigned | UNDER_REVIEW |
| **Appeal Committee** | Submit recommendation | appeals table | Research Director | Recommendation recorded | PENDING_DECISION |
| **Research Director** | Record appeal decision | appeals table | Researcher | Decision recorded, status updated | UPHELD/REJECTED |
| **Evaluator** | Decline assignment | evaluator_assignments table | College Coordinator | Conflict recorded, notification sent | CONFLICT_DECLINED |
| **College Coordinator** | Assign replacement evaluator | evaluator_assignments table | Evaluator | New assignment created, notification sent | ASSIGNED |

---

## 4. Proposal Status Lifecycle

### Normal Path

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                        PROPOSAL STATUS LIFECYCLE                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘

DRAFT
  │
  │ [Researcher submits proposal]
  ▼
SUBMITTED_TO_RPEE (Submitted to Research Proposal Evaluation Office)
  │
  │ [College Coordinator screens submission]
  ├─────────────────┐
  │                 │
  │ [Complete]      │ [Incomplete/Issues]
  ▼                 ▼
UNDER_COLLEGE_EVALUATION    AWAITING_COLLEGE_REVISION
  │                                 │
  │ [Coordinator assigns evaluators] │ [Researcher revises and resubmits]
  ▼                                 │
UNDER_COLLEGE_EVALUATION             │
  │                                 │
  │ [Evaluators submit assessments]  │
  ▼                                 │
AWAITING_COLLEGE_DECISION            │
  │                                 │
  │ [Coordinator reviews results]    │
  │                                 └─────────────────┐
  │                                                   │
  ├─────────────────┐                                 │
  │                 │                                 │
  │ [Pass]          │ [Fail]                          │
  ▼                 ▼                                 │
AWAITING_UNIVERSITY_SUBMISSION    REJECTED_BY_COLLEGE  │
  │                                                   │
  │ [Coordinator forwards to university]             │
  ▼                                                   │
AWAITING_UNIVERSITY_EVALUATION                        │
  │                                                   │
  │ [Research Director forms committee]               │
  ▼                                                   │
UNDER_UNIVERSITY_EVALUATION                           │
  │                                                   │
  │ [Committee members evaluate]                      │
  ▼                                                   │
AWAITING_UNIVERSITY_DECISION                          │
  │                                                   │
  │ [Research Director reviews committee]             │
  │                                                   │
  ├─────────────────┐                                 │
  │                 │                                 │
  │ [Approve]       │ [Reject/Revision]               │
  ▼                 ▼                                 │
AWAITING_FUNDING_DECISION    REJECTED_BY_UNIVERSITY    │
  │                                                   │
  │ [Research Director approves budget]               │
  ▼                                                   │
APPROVED                                             │
  │                                                   │
  │ [Researcher submits agreement]                    │
  ▼                                                   │
AWAITING_AGREEMENT_SIGNATURE                                    │
  │                                                   │
  │ [Research Director approves agreement]            │
  ▼                                                   │
FUNDED                                               │
  │                                                   │
  │ [Project created and activated]                  │
  ▼                                                   │
[Project Lifecycle - see below]                      │
                                                      │
                                                      └─────────────────┘
```

### Exception Path: Revision

```
AWAITING_COLLEGE_REVISION
  │
  │ [Researcher resubmits]
  ▼
SUBMITTED_TO_RPEE (returns to screening)
  │
  │ [Coordinator reviews resubmission]
  ▼
UNDER_COLLEGE_EVALUATION (continues normal path)
```

### Exception Path: Appeal

```
REJECTED_BY_UNIVERSITY
  │
  │ [Researcher submits appeal]
  ▼
APPEAL_SUBMITTED
  │
  │ [Research Director appoints appeal committee]
  ▼
APPEAL_UNDER_REVIEW
  │
  │ [Appeal committee submits recommendation]
  ▼
APPEAL_PENDING_DECISION
  │
  │ [Research Director records decision]
  │
  ├─────────────────┐
  │                 │
  │ [Upheld]        │ [Rejected]
  ▼                 ▼
AWAITING_UNIVERSITY_EVALUATION    APPEAL_CLOSED
  │                              │
  │ [Re-evaluation by new committee] │
  ▼                              │
[Continues normal path]          │
                                 │
                                 └─ [Appeal rejected, original decision stands]
```

### Project Status Lifecycle

```
APPROVED (Project created from approved proposal)
  │
  │ [Researcher activates project]
  ▼
ACTIVE
  │
  │ [Progress reporting cycle]
  │
  ├─────────────────┐
  │                 │
  │ [Report due]    │ [Extension requested]
  ▼                 ▼
REPORT_SUBMITTED    EXTENSION_PENDING
  │                 │
  │ [Coordinator approves] │ [Research Director decides]
  ▼                 │
REPORT_APPROVED      ├─────────────────┐
  │                 │                 │
  │                 │ [Approved]      │ [Rejected]
  │                 ▼                 ▼
  │         EXTENSION_GRANTED    EXTENSION_REJECTED
  │                 │                 │
  │                 └─────────────────┘
  │                                   │
  └───────────────────────────────────┘
  │
  │ [All reports complete, final report submitted]
  ▼
FINAL_REPORT_SUBMITTED
  │
  │ [Research Director approves final report]
  ▼
FINAL_REPORT_APPROVED
  │
  │ [Researcher submits to repository]
  ▼
REPOSITORY_SUBMITTED
  │
  │ [Repository record published]
  ▼
COMPLETED
  │
  │ [Research Director archives project]
  ▼
ARCHIVED
```

### Status Code Mapping (Database)

| Display Name | Status Code (research_applications.status_code) | Workflow Stage | Table |
|--------------|--------------------------------------------------|----------------|-------|
| Draft | DRAFT | draft | research_applications |
| Submitted | SUBMITTED | submitted | research_applications |
| College Received | COLLEGE_RECEIVED | college_receive | research_applications |
| Under College Screening | UNDER_COLLEGE_SCREENING | college_screening | research_applications |
| Evaluator Assignment Pending | EVALUATOR_ASSIGNMENT_PENDING | evaluator_assignment | research_applications |
| Evaluators Assigned | EVALUATOR_ASSIGNED | evaluator_assignment | research_applications |
| Evaluator Accepted | EVALUATOR_ACCEPTED | evaluator_assignment | research_applications |
| Under Evaluation | UNDER_EVALUATION | evaluation | research_applications |
| Evaluation Completed | EVALUATION_COMPLETED | evaluation | research_applications |
| Revision Required | REVISION_REQUIRED | revision | research_applications |
| Revision Submitted | REVISION_SUBMITTED | revision | research_applications |
| Re-evaluation | RE_EVALUATION | re_evaluation | research_applications |
| College Review | COLLEGE_REVIEW | college_review | research_applications |
| College Approved | COLLEGE_APPROVED | college_approval | research_applications |
| Submitted to University | SUBMITTED_TO_UNIVERSITY | university_submission | research_applications |
| University Review | UNIVERSITY_REVIEW | university_review | research_applications |
| University Committee Review | UNIVERSITY_COMMITTEE_REVIEW | university_committee | research_applications |
| Approved | APPROVED | approval | research_applications |
| Budget Review | BUDGET_REVIEW | budget | research_applications |
| Budget Approved | BUDGET_APPROVED | budget | research_applications |
| Funded | FUNDED | funding | research_applications |
| Active | ACTIVE | active | research_applications |
| Progress Monitoring | PROGRESS_MONITORING | progress | research_applications |
| Final Report Submitted | FINAL_REPORT_SUBMITTED | final_report | research_applications |
| Final Evaluation | FINAL_EVALUATION | final_evaluation | research_applications |
| Rejected | REJECTED | rejected | research_applications |
| Withdrawn | WITHDRAWN | withdrawn | research_applications |

---

## 5. Screen-by-Screen Dashboard Explanations

### System Administrator Dashboard

**URL:** No single admin dashboard route. The admin lands on the user directory (`/index.php?action=users`) after login; the full admin surface is reached from there: `users`, `user_form`, `user_roles`, `colleges`, `college_save`, `departments`, `department_save`, `committees`, `committee_save`, `committee_members`, `committee_member_save`, `committee_member_remove`, `classifications`, `classification_save`, `settings`, `settings_save`, `audit`, `health`.

**Key Sections:**

1. **User Management** (`/index.php?action=users`)
   - List of all users with roles, colleges, departments
   - Search by name, email, role
   - Filter by role, college, status
   - Actions: Create, Edit, Deactivate, Reset Password
   - **Create User Form:**
     - Full name, email, username
     - Role selection (dropdown): System Administrator, Research Director, College Research Coordinator, Researcher
     - College assignment (if applicable)
     - Department assignment (if applicable)
     - Account status (active/inactive)
     - **Button:** Save User

2. **Organization Management** (`/index.php?action=colleges`)
   - List of colleges
   - Actions: Create College, Edit College
   - **Create College Form:**
     - College name, code
     - Description
     - Status (active/inactive)
   - **Departments:** Each college shows linked departments
     - Create Department: name, code, parent college

3. **Settings** (`/index.php?action=settings`)
   - Workflow configuration
   - Evaluation templates
   - Document type definitions
   - Permission rules
   - System-wide settings

4. **Audit Logs** (`/index.php?action=audit`)
   - Filterable log of all system actions
   - Search by user, action, date range
   - View detailed action records

---

### Research Director Dashboard

**URL:** `/index.php?action=director_dashboard`

**Key Sections:**

1. **Summary Cards**
   - Active Research Calls: Count of open/published calls
   - Proposals Awaiting Committee Assignment: Proposals ready for university committee
   - University Evaluations in Progress: Currently under committee review
   - Completed Evaluations Awaiting Decision: Committee done, awaiting official decision
   - Funding Decisions Pending: Approved proposals awaiting budget approval
   - Open Budget Appeals: Budget-related appeals submitted
   - Open Rejection Appeals: Rejection-related appeals submitted
   - Appeals Awaiting Committee: Appeals needing committee assignment
   - Agreements Pending: Draft agreements awaiting signature
   - Active Projects Requiring Attention: Active projects with issues
   - Overdue Reports: Progress reports past deadline

2. **Priority Action Center**
   - **Proposals Awaiting Committee Assignment:**
     - Table: Application number, title, PI, college, submitted date
     - Action: "Assign Committee" button → opens committee assignment form
   - **Committees Missing Members:**
     - Table: Committee name, type, current members, required members
     - Action: "Add Members" button → opens member assignment form
   - **Overdue Evaluations:**
     - Table: Application number, evaluator, deadline, days overdue
     - Action: "Send Reminder" or "Reassign" button
   - **Completed Evaluations Awaiting Decision:**
     - Table: Application number, committee chair, average score, recommendation
     - Action: "Review & Decide" button → opens decision form
   - **Funding Decisions Pending:**
     - Table: Application number, title, PI, requested budget, recommended budget
     - Action: "Approve Budget" button → opens budget approval form
   - **Appeals Awaiting Review:**
     - Table: Appeal number, type, appellant, submitted date
     - Action: "Review Appeal" button → opens appeal details
   - **Agreements Awaiting Signature:**
     - Table: Agreement number, application number, PI, version
     - Action: "Review & Sign" button → opens agreement document
   - **Projects with Overdue Reports:**
     - Table: Project number, title, PI, report due date, days overdue
     - Action: "View Project" button → opens project details

3. **University Evaluation Management**
   - **Summary Statistics:**
     - Proposals received this cycle
     - Active university committees
     - Assigned evaluators
     - Completed evaluations
     - Pending assessments
   - **Recent Committee Assignments:**
     - Table: Application number, committee name, assigned date, deadline
     - Action: "View Committee" button

4. **Funding Overview**
   - **Budget Totals:**
     - Requested budget (sum of all proposals)
     - Committee recommended budget
     - Officially approved budget
     - Allocated funds (approved budgets)
     - Disbursed funds (actual releases)
     - Reported expenditure (from progress reports)
   - **Action:** "View Budget Details" button

5. **Appeals Overview**
   - **Appeals by Type:**
     - Budget appeals count
     - Rejection appeals count
   - **Appeals by Status:**
     - Pending review
     - Under review
     - Awaiting implementation (upheld)
     - Closed (rejected/withdrawn)
   - **Recent Appeals:**
     - Table: Appeal number, type, status, submitted date
     - Action: "View Appeal" button

6. **Agreements and Project Monitoring**
   - **Agreement Summary:**
     - Awaiting preparation (approved but no agreement)
     - Awaiting signature
     - Awaiting activation (signed but not started)
     - Active projects
     - Extension requests pending
     - Amendment requests pending
     - Awaiting final report
     - Awaiting repository submission
   - **Recent Projects:**
     - Table: Project number, title, PI, status, start date
     - Action: "View Project" button

7. **Research Calls and Themes**
   - **Active Calls:**
     - Table: Call number, title, closing date, applications received
     - Action: "View Call" button
   - **Upcoming Calls:**
     - Table: Call number, title, opening date
     - Action: "View Call" button
   - **Research Themes:**
     - List of all themes with project counts
     - Action: "Manage Themes" button

8. **Recent Activity and Alerts**
   - **Audit Log Feed:**
     - Recent system actions with timestamps
     - Filter by action type, user
   - **Alerts:**
     - Overdue items
     - System notifications
     - Security alerts

**Key Actions:**
- **Create Research Call:** Button → `/index.php?action=call_form`
- **View All Calls:** Link → `/index.php?action=calls`
- **View All Proposals:** Link → `/index.php?action=applications`
- **View All Projects:** Link → `/index.php?action=projects`

---

### College Research Coordinator Dashboard

**URL:** `/index.php?action=coordinator_dashboard` (college-scoped; proposals are filtered server-side to the coordinator's assigned college)

**Key Sections:**

1. **Proposals by Status (Tabs)**
   - **New Submissions:** Proposals awaiting screening
     - Table: Application number, title, PI, submitted date
     - Action: "Screen" button → opens proposal for review
   - **Awaiting Revision:** Proposals returned for correction
     - Table: Application number, title, PI, revision reason
     - Action: "View" button → opens proposal
   - **Under Evaluation:** Proposals being evaluated
     - Table: Application number, title, evaluators assigned, deadline
     - Action: "View Evaluations" button
   - **Awaiting Decision:** Evaluations complete, awaiting coordinator decision
     - Table: Application number, title, average score, deadline
     - Action: "Record Decision" button → opens decision form
   - **Forwarded to University:** Proposals sent to Research Director
     - Table: Application number, title, forwarded date
     - Action: "View" button (read-only)
   - **College Rejected:** Proposals rejected at college level
     - Table: Application number, title, rejection reason
     - Action: "View" button (read-only)

2. **Evaluator Management**
   - **My Evaluators:**
     - List of eligible evaluators in coordinator's college
     - Table: Name, department, availability status, current assignments
     - Action: "View Profile" button
   - **Pending Assignments:**
     - Evaluators who have not yet accepted assignments
     - Action: "Send Reminder" button

3. **College Projects**
   - **Active Projects:**
     - Table: Project number, title, PI, start date, progress
     - Action: "View Project" button
   - **Progress Reports:**
     - **Submitted:** Reports awaiting review
       - Table: Project number, report number, submitted date
       - Action: "Review" button
     - **Overdue:** Reports past deadline
       - Table: Project number, report number, due date, days overdue
       - Action: "Send Reminder" button
   - **Project Completion:**
     - Projects nearing completion
     - Table: Project number, title, expected completion date
     - Action: "View Project" button

**Key Actions:**
- **View All Proposals:** Link → `/index.php?action=applications`
- **View College Projects:** Link → `/index.php?action=projects` (college-scoped server-side)
- **Manage Evaluators:** Link → `/index.php?action=assign_evaluators`

---

### Researcher Dashboard

**URL:** `/index.php?action=researcher_dashboard`

**Key Sections:**

1. **My Proposals**
   - **Draft Proposals:**
     - Table: Title, call, created date
     - Action: "Edit" button → opens proposal form
     - Action: "Submit" button → submits proposal
   - **Submitted Proposals:**
     - Table: Application number, title, status, submitted date
     - Action: "View" button → opens proposal details
   - **Under Revision:**
     - Table: Application number, title, revision reason
     - Action: "Revise" button → opens proposal for editing
   - **Approved Proposals:**
     - Table: Application number, title, approved budget, approved date
     - Action: "View" button → opens proposal details

2. **My Projects**
   - **Active Projects:**
     - Table: Project number, title, start date, progress percentage
     - Action: "View Project" button → opens project details
   - **Pending Agreements:**
     - Table: Project number, title, agreement status
     - Action: "Submit Agreement" button
   - **Projects Awaiting Activation:**
     - Table: Project number, title
     - Action: "Activate Project" button

3. **Progress Reports**
   - **Due Soon:**
     - Table: Project number, report due date
     - Action: "Submit Report" button
   - **Overdue:**
     - Table: Project number, report due date, days overdue
     - Action: "Submit Report" button (highlighted)
   - **Submitted Reports:**
     - Table: Project number, report number, submitted date, status
     - Action: "View" button

4. **My Evaluations**
   - **Pending Assignments:**
     - Table: Application number, title, proposal PI, deadline
     - Action: "Evaluate" button → opens evaluation form
     - Action: "Decline" button → opens decline form
   - **Completed Evaluations:**
     - Table: Application number, title, score, submitted date
     - Action: "View" button (read-only)

5. **Research Calls**
   - **Open Calls:**
     - Table: Call number, title, closing date
     - Action: "View Call" button → opens call details
     - Action: "Create Proposal" button → opens proposal form
   - **Upcoming Calls:**
     - Table: Call number, title, opening date
     - Action: "View Call" button

6. **Notifications**
   - **Unread Notifications:**
     - List of recent notifications
     - Action: Click notification → navigates to relevant page
   - **All Notifications:**
     - Full notification history

**Key Actions:**
- **View All Proposals:** Link → `/index.php?action=applications` (researcher-scoped server-side)
- **View All Projects:** Link → `/index.php?action=projects` (researcher-scoped server-side)
- **View Open Calls:** Link → `/index.php?action=calls_public`

---

## 6. Notification and Task Assignment Flow

### Notification Types

| Notification Type | Trigger | Recipients | Content | Action Link |
|-------------------|---------|------------|---------|-------------|
| `new_call` | Research call published | All researchers | "New research call: {title}" | `/index.php?action=call_view&id={id}` |
| `application.submitted` | Proposal submitted | College Coordinator | "New proposal submitted: {application_number}" | `/index.php?action=application_view&id={id}` |
| `application.revision_requested` | Revision requested | Researcher (PI) | "Your proposal requires revision: {reason}" | `/index.php?action=application_view&id={id}` |
| `application.resubmitted` | Proposal resubmitted | College Coordinator | "Proposal resubmitted: {application_number}" | `/index.php?action=application_view&id={id}` |
| `evaluator.assigned` | Evaluator assigned | Evaluator | "You have been assigned to evaluate: {application_number}" | `/index.php?action=my_evaluations` |
| `evaluation.submitted` | Evaluation submitted | College Coordinator / Research Director | "Evaluation submitted for: {application_number}" | `/index.php?action=application_view&id={id}` |
| `evaluator.conflict_declined` | Evaluator declines (conflict) | College Coordinator | "Evaluator declined due to conflict: {evaluator_name}" | `/index.php?action=application_view&id={id}` |
| `college.decision_recorded` | College decision recorded | Research Director | "College decision recorded: {application_number}" | `/index.php?action=application_view&id={id}` |
| `university_committee.assigned` | University committee formed | Committee Members | "You have been assigned to university committee: {application_number}" | `/index.php?action=my_evaluations` |
| `application.approved` | Proposal approved | Researcher (PI), College Coordinator | "Your proposal has been approved. Budget: {amount}" | `/index.php?action=application_view&id={id}` |
| `application.rejected` | Proposal rejected | Researcher (PI), College Coordinator | "Your proposal has been rejected. Reason: {reason}" | `/index.php?action=application_view&id={id}` |
| `agreement.submitted` | Agreement submitted | Research Director | "Agreement submitted: {agreement_number}" | `/index.php?action=application_view&id={id}` |
| `agreement.approved` | Agreement approved | Researcher (PI) | "Your agreement has been approved. Project ready to start." | `/index.php?action=project_view&id={id}` |
| `project.activated` | Project activated | College Coordinator | "Project activated: {project_number}" | `/index.php?action=project_view&id={id}` |
| `progress_report.submitted` | Progress report submitted | College Coordinator | "Progress report submitted: {report_number}" | `/index.php?action=project_view&id={id}` |
| `progress_report.overdue` | Progress report overdue | Researcher (PI), College Coordinator | "Progress report overdue: {report_number}" | `/index.php?action=project_view&id={id}` |
| `final_report.submitted` | Final report submitted | Research Director | "Final report submitted: {project_number}" | `/index.php?action=project_view&id={id}` |
| `project.completed` | Project completed | Researcher (PI), College Coordinator | "Project completed: {project_number}" | `/index.php?action=project_view&id={id}` |
| `appeal.submitted` | Appeal submitted | Research Director | "Appeal submitted: {appeal_number}" | `/index.php?action=application_view&id={id}` (appeals are reviewed from the application view; no dedicated appeal_view route exists) |
| `appeal.under_review` | Appeal committee assigned | Appeal Committee | "You have been assigned to appeal committee: {appeal_number}" | `/index.php?action=application_view&id={id}` (appeals are reviewed from the application view; no dedicated appeal_view route exists) |
| `appeal.upheld` | Appeal upheld | Researcher (PI) | "Your appeal has been upheld. Proposal will be reconsidered." | `/index.php?action=application_view&id={id}` |
| `appeal.rejected` | Appeal rejected | Researcher (PI) | "Your appeal has been rejected. Original decision stands." | `/index.php?action=application_view&id={id}` |

### Task Assignment Flow

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          TASK ASSIGNMENT FLOW                                        │
└─────────────────────────────────────────────────────────────────────────────────────┘

1. PROPOSAL SUBMISSION
   Researcher submits proposal
   → Task created for College Coordinator: "Screen proposal"
   → Notification sent to College Coordinator

2. COLLEGE SCREENING
   College Coordinator screens proposal
   → If complete: Task created: "Assign evaluators"
   → If incomplete: Task created for Researcher: "Revise proposal"

3. EVALUATOR ASSIGNMENT
   College Coordinator assigns evaluators
   → Task created for each Evaluator: "Evaluate proposal"
   → Notification sent to each Evaluator

4. EVALUATION SUBMISSION
   Evaluator submits evaluation
   → Task updated for College Coordinator: "Review evaluations"
   → Notification sent to College Coordinator

5. COLLEGE DECISION
   College Coordinator records decision
   → If approved: Task created for Research Director: "Review for university evaluation"
   → If rejected: Task created for Researcher: "View rejection" (informational)

6. UNIVERSITY COMMITTEE ASSIGNMENT
   Research Director forms committee
   → Task created for each Committee Member: "Evaluate proposal"
   → Notification sent to each Committee Member

7. COMMITTEE EVALUATION
   Committee Member submits evaluation
   → Task updated for Research Director: "Review committee results"
   → Notification sent to Research Director

8. OFFICIAL DECISION
   Research Director records official decision
   → If approved: Task created for Researcher: "Submit agreement"
   → If rejected: Task created for Researcher: "View rejection" (informational)

9. AGREEMENT PROCESS
   Researcher submits agreement
   → Task created for Research Director: "Review and approve agreement"

10. PROJECT ACTIVATION
    Research Director approves agreement
    → Task created for Researcher: "Activate project"
    → Task created for College Coordinator: "Monitor project"

11. PROGRESS REPORTING
    System generates recurring task: "Submit progress report" (quarterly)
    → Task created for Researcher before each deadline
    → Notification sent: "Progress report due in 7 days"

12. PROGRESS REPORT SUBMISSION
    Researcher submits report
    → Task created for College Coordinator: "Review progress report"
    → Notification sent to College Coordinator

13. FINAL REPORT
    Researcher submits final report
    → Task created for Research Director: "Review final report"
    → Notification sent to Research Director

14. PROJECT CLOSURE
    Research Director approves final report
    → Task created for Researcher: "Submit to repository"
    → Task created for Research Director: "Archive project"

15. APPEAL PROCESS
    Researcher submits appeal
    → Task created for Research Director: "Review appeal"
    → Notification sent to Research Director

16. APPEAL COMMITTEE
    Research Director appoints appeal committee
    → Task created for Appeal Committee: "Review appeal"
    → Notification sent to Appeal Committee

17. APPEAL DECISION
    Appeal Committee submits recommendation
    → Task created for Research Director: "Record appeal decision"
    → If upheld: Task created: "Re-evaluate proposal" (returns to step 6)
```

### Task Priority Levels

| Priority | Use Cases | Display |
|----------|-----------|---------|
| `urgent` | Overdue reports, urgent appeals, security issues | Red badge |
| `high` | Evaluation deadlines approaching, funding decisions pending | Orange badge |
| `normal` | Standard tasks, new submissions | Blue badge |
| `low` | Informational notifications, completed items | Gray badge |

---

## 7. Unresolved University Policy Decisions

The following policy decisions must be confirmed by university administration before full implementation:

### 1. Default Eligibility Policy
**Question:** If no colleges are selected in a research call's eligibility configuration, should the call be:
- **Option A:** Open to all colleges (default)
- **Option B:** Restricted to no colleges (must explicitly select)

**Current Implementation:** Option A (empty = all eligible)

**Recommendation:** Confirm with university policy. Option A is more flexible but Option B is more explicit.

---

### 2. Committee Quorum Requirements
**Question:** Should university evaluation committees require a minimum number of members to make a valid decision?
- **Option A:** No quorum requirement (any number of evaluators can decide)
- **Option B:** Fixed quorum (e.g., minimum 3 members required)
- **Option C:** Percentage-based quorum (e.g., 75% of assigned members must complete)

**Current Implementation:** No quorum enforcement (database has `quorum_size` field but not enforced)

**Recommendation:** Implement Option B with minimum 3 members for university committees, 2 members for college evaluations.

---

### 3. Minimum Passing Score Policy
**Question:** What is the minimum passing score for research proposals?
- **Option A:** Fixed percentage (e.g., 70%)
- **Option B:** Variable per call (set by Research Director)
- **Option C:** No minimum score (qualitative review only)

**Current Implementation:** Variable per call (set in call configuration)

**Recommendation:** Keep Option B but establish university-wide minimum of 70% as default.

---

### 4. Appeal Timeline Policy
**Question:** What is the deadline for submitting an appeal after a rejection decision?
- **Option A:** 7 calendar days
- **Option B:** 14 calendar days
- **Option C:** 30 calendar days
- **Option D:** No deadline

**Current Implementation:** No deadline enforced

**Recommendation:** Implement Option B (14 days) with system enforcement.

---

### 5. Appeal Committee Composition
**Question:** Should appeal committee members be:
- **Option A:** Completely different from original evaluation committee
- **Option B:** Can include some original members if no conflict
- **Option C:** Same committee re-evaluates

**Current Implementation:** No enforcement (Research Director manually selects)

**Recommendation:** Implement Option A with system validation preventing original committee members from being assigned to appeal committee.

---

### 6. Progress Report Frequency
**Question:** How often should progress reports be required?
- **Option A:** Quarterly (every 3 months)
- **Option B:** Semi-annually (every 6 months)
- **Option C:** Annually
- **Option D:** Variable per project (set in agreement)

**Current Implementation:** Not specified (depends on project timeline)

**Recommendation:** Implement Option D with quarterly as default for projects >12 months, semi-annual for projects 12-24 months.

---

### 7. Overdue Report Consequences
**Question:** What happens when a progress report is overdue?
- **Option A:** Automatic warning only (no action)
- **Option B:** Automatic budget hold after X days overdue
- **Option C:** Project suspension after X days overdue
- **Option D:** Manual review by coordinator

**Current Implementation:** Warning notification only

**Recommendation:** Implement Option D with Option B after 30 days overdue (coordinator can release hold).

---

### 8. Final Report Evaluation
**Question:** Who should evaluate the final research report?
- **Option A:** College Research Coordinator
- **Option B:** Original university evaluation committee
- **Option C:** Separate final evaluation committee
- **Option D:** Research Director only

**Current Implementation:** Not specified

**Recommendation:** Implement Option A (College Coordinator) with Option D (Research Director) for final approval.

---

### 9. Repository Access Policy
**Question:** Who should have access to the institutional repository?
- **Option A:** All university staff and students (public within university)
- **Option B:** Only researchers and faculty
- **Option C:** Public access (open access)
- **Option D:** Embargoed for X years, then public

**Current Implementation:** Not specified

**Recommendation:** Implement Option D with 2-year embargo default, configurable per project.

---

### 10. Budget Carryover Policy
**Question:** Can unspent budget be carried over to the next fiscal year?
- **Option A:** Yes, full carryover allowed
- **Option B:** Yes, but limited to X% of original budget
- **Option C:** No, use it or lose it
- **Option D:** Case-by-case approval required

**Current Implementation:** Not specified

**Recommendation:** Implement Option D with Option B (10% carryover allowed without approval, more requires Research Director approval).

---

### 11. Co-Investigator Rights
**Question:** What rights do co-investigators have in the system?
- **Option A:** Full access to proposal and project (same as PI)
- **Option B:** View-only access
- **Option C:** Can edit proposal but not project
- **Option D:** No system access (managed by PI only)

**Current Implementation:** Co-investigators can view but not edit

**Recommendation:** Keep current implementation (Option B) but allow PI to grant edit permissions to specific co-investigators.

---

### 12. Extension Request Limits
**Question:** How many project extensions can be requested?
- **Option A:** Unlimited (subject to approval)
- **Option B:** Maximum 1 extension
- **Option C:** Maximum 2 extensions
- **Option D:** Maximum total extension duration (e.g., 6 months)

**Current Implementation:** Not specified

**Recommendation:** Implement Option D with maximum 6-month total extension, requiring Research Director approval for any extension.

---

### 13. Evaluation Conflict of Interest Rules
**Question:** What constitutes a conflict of interest for evaluators?
- **Option A:** Same department only
- **Option B:** Same department or former supervisor/student relationship
- **Option C:** Same college
- **Option D:** Defined by university policy (manual declaration)

**Current Implementation:** Manual declaration only

**Recommendation:** Implement Option B with system detection for same department, manual declaration for other relationships.

---

### 14. Publication Requirements
**Question:** Are researchers required to publish their work?
- **Option A:** Yes, minimum number of publications required
- **Option B:** Yes, but no minimum (at least one publication)
- **Option C:** No, publication optional
- **Option D:** Depends on funding source

**Current Implementation:** Not specified

**Recommendation:** Implement Option B with requirement to submit at least one publication or final report to repository.

---

### 15. Data Retention Policy
**Question:** How long should research data and documents be retained?
- **Option A:** 5 years after project completion
- **Option B:** 10 years after project completion
- **Option C:** Permanent retention
- **Option D:** Per project (set in agreement)

**Current Implementation:** Not specified

**Recommendation:** Implement Option C (permanent retention) with Option D option for sensitive data.

---

## Summary

This workflow documentation provides a complete, practical guide to how the four roles in the Madda Walabu University Research Management System interact through real actions. The workflow covers:

1. **End-to-end process** from system setup to project closure
2. **Exception handling** for revisions, appeals, evaluator declines, and failed proposals
3. **Clear handoffs** between roles with specific actions and system responses
4. **Status lifecycle** showing all normal and exceptional transitions
5. **Screen-by-screen explanations** for each role's dashboard
6. **Notification flow** ensuring all relevant users are informed
7. **Policy decisions** that require university administration confirmation

The workflow is designed to be:
- **Practical:** Based on actual system capabilities and database schema
- **Complete:** Covers all major paths and exceptions
- **Clear:** Each action specifies user, record, and result
- **Implementable:** All steps can be coded using existing system architecture

Before implementation begins, the unresolved policy decisions in Section 7 should be reviewed and confirmed by university administration.







