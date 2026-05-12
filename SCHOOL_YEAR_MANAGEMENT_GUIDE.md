# SkillHive School Year Lifecycle Management System - Implementation Guide

## Overview

A complete School Year Management System has been implemented for the SkillHive Students module. This system allows advisers to manage multiple school years, archive historical data, and seamlessly track students across academic years without cluttering active records.

**Key Principle**: All functionality has been added to the existing UI without redesigning the layout, colors, or visual identity. The system preserves the verdigris + black modern dashboard appearance.

---

## Components Implemented

### 1. **Database Migration** 
**File**: `backend/migrations/migration_school_years.sql`

Creates the following tables:
- `school_years` - Tracks active/archived school years
- `student_archive_history` - Maintains historical student records
- Adds `school_year_id` and `archived_at` columns to `student` table

**To Apply Migration**:
```bash
# Option 1: Via MySQL CLI
mysql -u root skillhive < backend/migrations/migration_school_years.sql

# Option 2: Import in phpMyAdmin
# Navigate to Import tab and select the migration_school_years.sql file
```

The migration automatically:
- Creates the initial "2024-2025" school year as Active
- Assigns all existing students to the active school year
- Sets up proper foreign key relationships

### 2. **Backend APIs**
**File**: `pages/adviser/students/school_years_api.php`

Provides RESTful endpoints for school year management:

#### Available Actions:
- `get_all` - List all school years with student counts
- `get_active` - Get the currently active school year
- `create` - Create a new school year
- `set_active` - Activate a specific school year (archives current)
- `archive` - Archive a school year
- `start_new` - Start a new academic year (archives current, moves completed students)
- `select` - Select a school year for the session
- `get_selected` - Get the currently selected school year

#### Usage Example:
```javascript
// Get all school years
fetch('/pages/adviser/students/school_years_api.php?action=get_all', {
  credentials: 'same-origin'
})
.then(r => r.json())
.then(data => console.log(data));
```

### 3. **Query Helpers**
**File**: `pages/adviser/students/school_years_query.php`

Functions for querying student data by school year and tab:
- `adviser_students_get_selected_school_year()` - Get selected school year from session
- `adviser_students_get_school_year_options()` - Get dropdown options
- `adviser_students_get_tab_students()` - Get filtered students by tab (active/archived/alumni)

**Tab Filtering**:
- **Active Students**: Non-archived students in selected school year
- **Archived Students**: Archived students (view-only)
- **Alumni Interns**: Completed OJT students

### 4. **Backend Helpers**
**File**: `backend/school_year_helpers.php`

Reusable functions for all modules:
- `get_selected_school_year_id()` - Get currently selected school year
- `add_school_year_filter_to_query()` - Helper to add school year filter to SQL
- `ensure_student_school_year()` - Assign unassigned students
- `get_school_year_student_count()` - Count students in a school year
- `migrate_student_to_school_year()` - Move students between years

**Import in Other Modules**:
```php
require_once __DIR__ . '/backend/school_year_helpers.php';

$schoolYearId = get_selected_school_year_id($pdo);
// Now use $schoolYearId in queries
```

### 5. **Student Module Updates**

#### Updated Files:
- `pages/adviser/students.php` - Main page with school year UI
- `pages/adviser/students/add_student_action.php` - Auto-assigns new students to selected school year
- `pages/adviser/students/school_years_query.php` - School year filtering logic

#### New Features in Students Page:

**School Year Selector** (in banner):
```html
<select id="schoolYearSelector" onchange="selectSchoolYear(this.value)">
  <!-- Active years listed first -->
  <!-- Archived years in optgroup -->
</select>
```

**Manage School Years Button** (toolbar):
- Opens modal for school year management
- Create new school years
- Activate archived years
- Start new academic year with auto-archiving

**Student Tabs**:
- Active Students - Default view for current work
- Archived Students - View-only historical records
- Alumni Interns - Completed internship students

### 6. **JavaScript Functions**

#### School Year Management:
- `openManageSchoolYearsModal()` - Open management interface
- `closeManageSchoolYearsModal()` - Close modal
- `loadSchoolYearsList()` - Load and display school years
- `createNewSchoolYear()` - Create new school year
- `activateSchoolYear()` - Set as active (archives current)
- `startNewSchoolYear()` - Start new year with auto-archive
- `selectSchoolYear()` - Change selected school year
- `switchStudentTab()` - Switch between student tabs

All functions include error handling, loading states, and user confirmations for critical operations.

---

## How It Works

### 1. **Initial Setup**
1. Run the database migration
2. The system creates "2024-2025" as the active school year
3. All existing students are automatically assigned to this year

### 2. **Adding New Students**
- New students are automatically assigned to the currently selected school year
- School year_id is stored in the student record
- School year selection is stored in the session

### 3. **Selecting a School Year**
- Adviser selects a school year from the dropdown in the banner
- Selection is stored in session (`$_SESSION['selected_school_year_id']`)
- All data filtering automatically uses this selection
- Changes apply immediately without page reload

### 4. **Starting a New Academic Year**
When adviser clicks "Start New Year":
1. Current active school year is archived
2. Completed/dropped students are marked as archived
3. Student archive history is recorded
4. New school year becomes active
5. Advisers can begin adding new students to the new year

### 5. **Data Preservation**
- All historical data is preserved
- Archived students remain visible in "Archived Students" tab
- Employers and advisers always have read-only access to past records
- Analytics and reports can still access archived data

---

## UI/UX Integration

### Design Consistency
- Uses existing CSS classes and design patterns
- Maintains verdigris (#12b3ac) and black (#050505) color scheme
- Follows current spacing, typography, and border radius standards
- No visual redesign - seamless integration

### Responsive Behavior
- School year dropdown in banner adapts to mobile
- Tabs display properly on all screen sizes
- Modal dialogs follow existing patterns
- Touch-friendly button sizes maintained

---

## Multi-Module Support

### How to Enable School Year Filtering in Other Modules:

**Example: Monitoring Module**
```php
// At top of monitoring.php
require_once __DIR__ . '/../../backend/school_year_helpers.php';

// In your query
$schoolYearId = get_selected_school_year_id($pdo);
$sql = 'SELECT ... FROM student s WHERE s.school_year_id = :sy_id AND ...';
```

**Modules Ready for Integration**:
- Monitoring
- Endorsements
- Journals
- Analytics
- Requirements
- OJT Hours

Each module automatically filters by the selected school year once updated.

---

## API Endpoints Reference

### GET Requests
```
GET /pages/adviser/students/school_years_api.php?action=get_all
GET /pages/adviser/students/school_years_api.php?action=get_active
GET /pages/adviser/students/school_years_api.php?action=get_selected
```

### POST Requests
```
POST /pages/adviser/students/school_years_api.php
  action=create&school_year=2025-2026
  
POST /pages/adviser/students/school_years_api.php
  action=set_active&school_year_id=2
  
POST /pages/adviser/students/school_years_api.php
  action=start_new&school_year=2025-2026
  
POST /pages/adviser/students/school_years_api.php
  action=select&school_year_id=2
```

---

## Session Variables

The system uses these session variables:
```php
$_SESSION['selected_school_year_id'] // Currently selected school year ID
$_SESSION['selected_school_year']    // School year string (e.g., "2024-2025")
```

These are automatically set when:
- User selects a school year
- Page loads (defaults to active year)
- New school year is created

---

## Database Schema

### school_years Table
```sql
id              INT PRIMARY KEY
school_year     VARCHAR(9) UNIQUE  -- "2024-2025" format
status          ENUM('Active', 'Archived')
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### student Table (Updated Columns)
```sql
school_year_id  INT FOREIGN KEY -> school_years.id
archived_at     TIMESTAMP NULL
```

### student_archive_history Table (New)
```sql
id                  INT PRIMARY KEY
student_id          INT FOREIGN KEY -> student.student_id
school_year_id      INT FOREIGN KEY -> school_years.id
internship_status   VARCHAR(50)
hours_completed     DECIMAL(8,2)
completion_status   VARCHAR(50)
archived_at         TIMESTAMP
```

---

## Testing Checklist

- [ ] Database migration runs without errors
- [ ] School year dropdown appears in banner with all years
- [ ] Can create new school years via "Manage School Years" modal
- [ ] Can activate archived school years
- [ ] Selecting a school year reloads data correctly
- [ ] "Start New Year" archives current and creates new active year
- [ ] New students are assigned to selected school year
- [ ] Active/Archived/Alumni tabs show correct students
- [ ] Archived students tab is view-only
- [ ] Analytics/endorsements respect school year filter

---

## Important Notes

1. **Backward Compatibility**: Existing code continues to work. Migration adds new columns but doesn't remove old functionality.

2. **Session-Based Selection**: School year selection is stored in session, so each adviser can view different years independently.

3. **Read-Only Access**: Even when students are archived, advisers and employers retain full read-only access to all historical data.

4. **No UI Redesign**: All additions blend seamlessly with the existing interface.

5. **Scalability**: System is designed to handle unlimited school years and years' worth of historical data.

---

## Future Enhancements

1. Update monitoring, endorsements, journals, analytics, and requirements modules to support school year filtering
2. Add bulk operations for archived students
3. Create analytics dashboard showing school year trends
4. Add data export by school year
5. Implement school year templates for course/section duplication

---

## Support & Troubleshooting

### School year dropdown not showing
- Ensure migration has run: `SELECT * FROM school_years`
- Check that current user is an adviser
- Clear browser cache

### Students not filtering by school year
- Verify `school_year_id` column exists: `DESCRIBE student`
- Check that students have school_year_id assigned (not NULL)
- Ensure session variables are being set properly

### Modal not opening
- Check browser console for JavaScript errors
- Verify all required modal HTML exists in page
- Ensure CSS classes are loaded

### Import the school year helpers
- Add this line at the top of modules: 
  ```php
  require_once __DIR__ . '/../../backend/school_year_helpers.php';
  ```

---

## Files Created/Modified

### New Files Created:
1. `backend/migrations/migration_school_years.sql` - Database setup
2. `backend/school_year_helpers.php` - Reusable backend functions
3. `pages/adviser/students/school_years_api.php` - API endpoints
4. `pages/adviser/students/school_years_query.php` - Query helpers

### Files Modified:
1. `pages/adviser/students.php` - Added UI components and JavaScript
2. `pages/adviser/students/add_student_action.php` - Auto-assign school year

### Configuration:
- No additional config files needed
- Uses existing database credentials

---

## Summary

The School Year Management System is now fully integrated into the SkillHive Students module with:

✅ Database tables for tracking school years  
✅ Complete CRUD API for school year management  
✅ Seamless UI integration in students page  
✅ Active/Archived/Alumni student tabs  
✅ Automatic school year assignment for new students  
✅ Session-based school year selection  
✅ Reusable backend helpers for other modules  
✅ Archive logic with historical data preservation  
✅ No visual redesign - maintains existing aesthetics  

The system is ready for use and can be extended to other modules using the provided helper functions.
