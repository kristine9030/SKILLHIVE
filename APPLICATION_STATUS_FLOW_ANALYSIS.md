# SkillHive Application Status Flow Analysis

## Executive Summary
SkillHive has a **complete application pipeline with employer-driven status management**, but **lacks real-time updates** for students. Status changes are only visible on manual page refresh.

---

## 1. FILE STRUCTURE & LOCATIONS

### Employer Candidate Management
- **Main Page**: [pages/employer/candidates.php](pages/employer/candidates.php)
- **Data Orchestrator**: [pages/employer/candidates/data.php](pages/employer/candidates/data.php)
- **Status Action Handlers**: [pages/employer/candidates/actions.php](pages/employer/candidates/actions.php)
  - `candidates_update_application_status()` - Updates application status
  - `candidates_schedule_interview()` - Schedules interviews + updates status
  - `candidates_ensure_ojt_record()` - Creates OJT record when accepted
  - `candidates_can_transition()` - Validates state transitions
- **Query Functions**:
  - [pages/employer/candidates/candidate_queries.php](pages/employer/candidates/candidate_queries.php) - Gets candidate rows
  - [pages/employer/candidates/meta_queries.php](pages/employer/candidates/meta_queries.php) - Metadata (positions, statuses)
  - [pages/employer/candidates/skill_queries.php](pages/employer/candidates/skill_queries.php) - Student skills for filtering

### Student Application Viewing
- **Main Page**: [pages/student/applications/index.php](pages/student/applications/index.php)
- **Render/Display**: [pages/student/applications/applications_view.php](pages/student/applications/applications_view.php)
- **Data Loading**: [pages/student/applications/applications_job.php](pages/student/applications/applications_job.php)
- **Helpers**: [pages/student/applications/applications_helpers.php](pages/student/applications/applications_helpers.php)
- **Actions**: [pages/student/applications/applications_actions.php](pages/student/applications/applications_actions.php)
- **Cancel Logic**: [pages/student/applications/applications_cancel.php](pages/student/applications/applications_cancel.php)

### Employer Dashboard
- **Main Page**: [pages/employer/dashboard.php](pages/employer/dashboard.php)
- **Query Functions**:
  - [pages/employer/dashboard/applicants_query.php](pages/employer/dashboard/applicants_query.php)
  - [pages/employer/dashboard/interviews_query.php](pages/employer/dashboard/interviews_query.php)
  - [pages/employer/dashboard/monthly_query.php](pages/employer/dashboard/monthly_query.php)
  - [pages/employer/dashboard/stats_query.php](pages/employer/dashboard/stats_query.php)

---

## 2. APPLICATION STATUS VALUES (Database Constants)

### Valid Statuses (6 states)
1. **Pending** - Initial submission state
2. **Shortlisted** - Candidate moved forward
3. **Waitlisted** - ❌ NOT used in employer pipeline (only in student helpers; appears in student analytics UI but not in actual transitions)
4. **Interview Scheduled** - Interview scheduled with employer
5. **Accepted** - Candidate accepted (triggers OJT record creation)
6. **Rejected** - Application rejected

**Location**: [pages/student/applications/applications_helpers.php#L71](pages/student/applications/applications_helpers.php#L71)
```php
function applications_valid_statuses(): array
{
  return ['Pending', 'Shortlisted', 'Waitlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
}
```

---

## 3. DATABASE SCHEMA (APPLICATION & INTERVIEW)

### `application` Table Columns
```
- application_id (PRIMARY KEY)
- student_id (FOREIGN KEY)
- internship_id (FOREIGN KEY)
- status (VARCHAR) - Current status value
- application_date (DATETIME)
- updated_at (DATETIME) - Last status update
- compatibility_score (DECIMAL) - % match based on skills
- cover_letter (TEXT)
- consented_at (DATETIME) - When consent given
- consent_version (VARCHAR(20)) - Version of consent form
- compliance_snapshot (LONGTEXT) - JSON snapshot of required info at time of apply
- resume_link_snapshot (VARCHAR(255)) - URL to resume as of application
- profile_link_snapshot (VARCHAR(255)) - URL to profile as of application
```

### `interview` Table Columns
```
- interview_id (PRIMARY KEY)
- application_id (FOREIGN KEY)
- interview_date (DATETIME) - Scheduled interview time
- interview_mode (VARCHAR) - 'Online' or 'In-Person'
- interview_status (VARCHAR) - 'scheduled', 'completed', etc.
- meeting_link (VARCHAR(255)) - For online interviews (Zoom, Teams, etc.)
- venue (VARCHAR(255)) - For in-person interviews
- notes (TEXT) - Interview notes
- created_at (DATETIME)
```

**Special**: Database triggers exist in [backend/db_connect.php](backend/db_connect.php):
- `trg_application_ai_create_ojt` - Creates OJT record on INSERT when status='Accepted'
- `trg_application_au_create_ojt` - Creates OJT record on UPDATE when status changes to 'Accepted'

---

## 4. EMPLOYER STATUS UPDATE FLOW

### Valid State Transitions
```
Pending → [Shortlisted, Rejected]
Shortlisted → [Interview Scheduled, Rejected]
Interview Scheduled → [Accepted, Rejected]
Accepted → (terminal)
Rejected → (terminal)
Waitlisted → (not used in transitions)
```

**Location**: [pages/employer/candidates/actions.php#L31-L41](pages/employer/candidates/actions.php#L31-L41)

### Status Update Process (Employer)
**File**: [pages/employer/candidates.php#L17-L43](pages/employer/candidates.php#L17-L43)

1. **Form Post** → `action=change_status`
   ```php
   $result = candidates_update_application_status($pdo, $employerId, $applicationId, $nextStatus);
   ```

2. **Validation** → [pages/employer/candidates/actions.php#L73-L103](pages/employer/candidates/actions.php#L73-L103)
   - Verifies application belongs to employer
   - Checks if transition is allowed
   - Normalizes status (handles 'hired'→'Accepted', 'interview'→'Interview Scheduled')
   - Updates: `application.status` and `application.updated_at`

3. **If Status = 'Accepted'** → Ensures OJT record exists:
   - Calculated `hours_required` = `internship.duration_weeks * 40`
   - Creates `ojt_record` with `completion_status='Ongoing'`
   - Sets `start_date=TODAY()`, `end_date=TODAY() + duration_weeks`

### Interview Scheduling Process
**File**: [pages/employer/candidates/actions.php#L176-L271](pages/employer/candidates/actions.php#L176-L271)

1. **Form Post** → `action=schedule_interview`
   ```php
   $result = candidates_schedule_interview($pdo, $employerId, $applicationId, $_POST);
   ```

2. **Validation**:
   - Application must be 'Shortlisted' (can't schedule on Pending)
   - Requires valid interview_date (datetime format)
   - Requires meeting_link (if Online) or venue (if In-Person)

3. **Transaction**:
   - UPSERTs `interview` row (updates if exists, inserts if not)
   - Automatically updates application status to 'Interview Scheduled'
   - Sets `interview_status='scheduled'`

4. **Side Effects**:
   - Triggers job post change notification (if listening)
   - No direct student notification yet

---

## 5. STUDENT APPLICATION STATUS VIEWING

### Student Page Display
**File**: [pages/student/applications/applications_view.php#L277-L420](pages/student/applications/applications_view.php#L277-L420)

**Current Data Shown**:
- Company name + badge status ('Verified Partner', 'Top Employer', 'None')
- Internship title + compatibility score %
- Date applied
- Current status (with CSS class for styling)
- Next step recommendation (text based on status)
- Progress modal (shows 5-step pipeline visualization)
- Recent activity timeline

**Progress Steps** (Displayed in modal):
```
Step 1: Application Submitted
Step 2: Under Review (Pending, Shortlisted, Waitlisted)
Step 3: Interview Stage (Interview Scheduled)
Step 4: Final Decision
Step 5: Complete (Accepted or Rejected)
```

**Location**: [pages/student/applications/applications_helpers.php#L34-L45](pages/student/applications/applications_helpers.php#L34-L45)

### Status Counts Summary
**File**: [pages/student/applications/applications_job.php#L3-L29](pages/student/applications/applications_job.php#L3-L29)

Displays aggregated counts by status in left sidebar:
```
Pending: X
Shortlisted: X
Waitlisted: X
Interview Scheduled: X
Accepted: X
Rejected: X
```

---

## 6. WHERE STATUS IS DISPLAYED

### Admin Dashboard
- **File**: [pages/admin/dashboard.php#L37](pages/admin/dashboard.php#L37)
- Shows breakdown chart: "Application Status Breakdown" with counts
- Query: `SELECT status, COUNT(*) FROM application GROUP BY status`

### Admin Reports
- **File**: [pages/admin/reports.php#L10](pages/admin/reports.php#L10)
- Pie chart of application statuses by count

### Adviser Dashboard (Endorsement)
- **File**: [pages/adviser/dashboard/endorsements_query.php](pages/adviser/dashboard/endorsements_query.php)
- Shows endorsement status (related but separate from application status)

### Student Analytics
- **File**: [pages/student/analytics/analytics_data.php#L42](pages/student/analytics/analytics_data.php#L42)
- Queries: `SELECT status, COUNT(*) FROM application WHERE student_id = ? GROUP BY status`
- Shows "Monthly Application Status" breakdown

---

## 7. CURRENT STATUS UPDATE MECHANISMS

### ✅ What Exists:
1. **Employer-side status changes** - Form POST with validation
2. **Interview scheduling** - Creates interview record + updates status in transaction
3. **OJT auto-provisioning** - Triggers on 'Accepted' status
4. **Status persistence** - `updated_at` timestamp tracked in database
5. **State machine enforcement** - Cannot skip states or violate transitions
6. **Audit trail hints** - `updated_at` column exists but no full audit log

### ❌ What's MISSING:
1. **Real-time student notifications** - No WebSocket, polling, or SSE
2. **Student-visible interview details** - Students don't see scheduled interview dates/links
3. **API endpoint for status polling** - Students only see data on full page reload
4. **Email notifications** - No automated "Your status changed" emails sent
5. **Student interview confirmation** - Can't confirm/decline scheduled interview
6. **Status change audit log** - No date/time of when each status change occurred
7. **Rollback/status correction** - No way to undo status changes
8. **Bulk status operations** - No batch update for multiple candidates

---

## 8. SPECIFIC IMPLEMENTATION GAPS

### Gap 1: Student Can't See Interview Details After Scheduling
- Employer sets interview via [pages/employer/candidates.php#L280-L309](pages/employer/candidates.php#L280-J309)
- Student application page doesn't query `interview` table
- Student never sees: interview_date, meeting_link, venue, interview_mode
- **Fix needed**: Query interview table in [applications_job.php](pages/student/applications/applications_job.php) and display in view

### Gap 2: No Real-Time Updates
- Student sees old data until manual page refresh
- No JavaScript polling or WebSocket listeners
- **Fix needed**: Add fetch interval (every 30-60 seconds) or WebSocket listener

### Gap 3: No Direct Notification System
- Status changes visible only on page refresh
- No email, SMS, or push notifications
- Student settings page mentions "Application Updates" toggle but it's UI-only
  - **File**: [pages/student/settings/index.php#L77](pages/student/settings/index.php#L77)
- **Fix needed**: Implement notification trigger on status update

### Gap 4: Inconsistent Status Between Pages
- Student can see status count in analytics
- Student can see detailed applications in table
- But no way to see who updated the status or when exactly
- **Fix needed**: Add "last updated" timestamp display, updated_by user tracking

### Gap 5: Employer Has No Status History
- Can see current status but not previous states
- No "Status changed from Pending→Shortlisted on 2024-03-20" trail
- **Fix needed**: Create application_status_history table with timestamp, old_status, new_status, changed_by

---

## 9. DATA FLOW DIAGRAM

```
EMPLOYER ACTION:
├─ POST to /layout.php?page=employer/candidates
│  └─ POST['action'] = 'change_status' or 'schedule_interview'
├─ candidates.php → candidates_update_application_status()
├─ Validates state transition
├─ UPDATEs application.status, application.updated_at
├─ If 'Accepted' → candidates_ensure_ojt_record()
│  └─ INSERT ojt_record (hours_required, start/end dates, completion_status='Ongoing')
└─ IF schedule_interview → candidates_schedule_interview()
   ├─ UPSERT interview row
   ├─ Sets application.status = 'Interview Scheduled'
   └─ Sets interview.interview_status = 'scheduled'

STUDENT VIEW (Current):
├─ GET /layout.php?page=student/applications
├─ applications_job.php loads page data (NO interview table JOIN)
├─ applications_view.php displays applications table
│  ├─ Shows: company, position, date_applied, status, compatibility_score
│  ├─ Shows: resume_link_snapshot, profile_link_snapshot
│  ├─ Shows: consented_at, consent_version
│  └─ Shows: "Next step" text (static based on status)
└─ NO interview date/link/mode visible
   └─ Interview record exists in DB but not queried or displayed

STUDENT VIEW (Missing):
├─ Real-time polling/WebSocket for status changes
├─ Email notification on status change
├─ Interview details display (date, time, link, mode)
├─ Interview confirmation capability
└─ Audit trail of when status changed
```

---

## 10. ACTIONABLE SUMMARY

### Current State
- ✅ Full employer workflow for managing candidates
- ✅ 6-status pipeline with enforced transitions
- ✅ Interview scheduling with auto-status update
- ✅ OJT record auto-provisioning on acceptance
- ✅ Student can see applications and statuses (on refresh)
- ❌ **Status changes invisible until student manually refreshes**
- ❌ **Interview details never shown to student**
- ❌ **No notifications for status changes**

### Priority Fixes (Low to High Impact)
| Priority | Issue | Location | Impact |
|----------|-------|----------|--------|
| 1 | Show interview details to student | [applications_job.php](pages/student/applications/applications_job.php) JOIN interview | Medium |
| 2 | Add real-time polling (30-60s) | [applications_view.php](pages/student/applications/applications_view.php) JS | High |
| 3 | Add status change email notification | [pages/employer/candidates.php](pages/employer/candidates.php) after update | High |
| 4 | Add "Updated" timestamp display | [applications_view.php](pages/student/applications/applications_view.php) | Low |
| 5 | Create status history log | New table: application_status_history | Medium |
| 6 | Add interview RSVP capability | New endpoint + UI | Medium |

### Database Changes Needed
```sql
-- For audit trail
CREATE TABLE application_status_history (
  history_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  old_status VARCHAR(50),
  new_status VARCHAR(50),
  changed_by INT, -- employer_id or admin_id
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reason TEXT,
  FOREIGN KEY (application_id) REFERENCES application(application_id)
);

-- For interview RSVP
ALTER TABLE interview ADD COLUMN (
  student_rsvp_status ENUM('pending', 'confirmed', 'declined', 'no_show') DEFAULT 'pending',
  student_rsvp_at DATETIME NULL
);
```

---

## 11. CODE REFERENCES SUMMARY

| Entity | Primary File | Key Functions |
|--------|--------------|----------------|
| **Employer Status Update** | [candidates/actions.php](pages/employer/candidates/actions.php) | `candidates_update_application_status()` |
| **Interview Scheduling** | [candidates/actions.php](pages/employer/candidates/actions.php) | `candidates_schedule_interview()` |
| **Student App Display** | [applications/applications_view.php](pages/student/applications/applications_view.php) | `applications_render_view()` |
| **Page Data Loading** | [applications/applications_job.php](pages/student/applications/applications_job.php) | `applications_load_page_data()` |
| **DB Connection** | [backend/db_connect.php](backend/db_connect.php) | PDO + trigger creation |
| **Status Helpers** | [applications/applications_helpers.php](pages/student/applications/applications_helpers.php) | All status → display logic |
| **Employer Candidates Page** | [employer/candidates.php](pages/employer/candidates.php) | Main UI + POST handler |

