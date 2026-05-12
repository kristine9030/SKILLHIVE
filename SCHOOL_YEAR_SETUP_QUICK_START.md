# School Year Management System - Quick Setup Guide

## Step 1: Run the Database Migration

Execute the migration to create the necessary tables:

```bash
# Via command line (from Skillhive root directory)
mysql -u root skillhive < backend/migrations/migration_school_years.sql
```

Or using phpMyAdmin:
1. Go to phpMyAdmin → skillhive database
2. Click "Import" tab
3. Select `backend/migrations/migration_school_years.sql`
4. Click "Go"

**Verify Success**:
```sql
SELECT * FROM school_years;
-- Should show: 1 | 2024-2025 | Active | [timestamp] | [timestamp]

SELECT COUNT(*) FROM student WHERE school_year_id IS NOT NULL;
-- Should show the number of your students
```

---

## Step 2: Test the UI Components

1. Log in as an adviser
2. Navigate to "Students" page
3. You should see:
   - **School year selector** in the banner (top section)
   - **"Manage School Years"** button in the toolbar
   - **Three tabs** above the student table: "Active Students", "Archived Students", "Alumni Interns"

---

## Step 3: Test Core Functionality

### Create a New School Year
1. Click "Manage School Years" button
2. Enter "2025-2026" in the "Create New School Year" field
3. Click "Create"
4. You should see the new year in the list (marked as "Archived")

### Activate a School Year
1. In the "Manage School Years" modal
2. Find the "2025-2026" year you just created
3. Click "Activate"
4. Confirm the prompt
5. The year should now be marked as "Active"
6. You should be redirected to view this new school year

### Start a New Academic Year
1. Click "Manage School Years"
2. In the "Start New School Year" section
3. Enter "2026-2027"
4. Click "Start New Year"
5. Confirm the prompt
6. The system will:
   - Archive the old school year
   - Move completed students to archived records
   - Create and activate the new year

---

## Step 4: Add a Student to Current School Year

1. Click "Add Student" button
2. Fill in student details:
   - Student ID: 2024-11111
   - First Name: Test
   - Last Name: Student
   - Track: Business Analytics
   - Section: 01
3. Click "Add Student"
4. Student is automatically assigned to current school year (2024-2025)

---

## Step 5: View Students by Tab

### Active Students Tab (Default)
- Shows all non-archived students in selected school year
- Use this for daily management
- All filtering, editing, and actions work normally

### Archived Students Tab
- Shows all archived students from the selected school year
- View-only (no editing)
- Useful for historical records

### Alumni Interns Tab
- Shows only completed internship students (with OJT marked as "Completed")
- Great for tracking graduation

---

## Step 6: Switch Between School Years

1. Click the school year dropdown in the banner
2. Select a different school year
3. The page reloads with data for that school year
4. All tabs, filters, and student lists update automatically

---

## Step 7: Extend to Other Modules (Optional)

To make other modules (monitoring, endorsements, etc.) respect school year selection:

1. Open the module file (e.g., `pages/adviser/monitoring.php`)
2. Add at the top:
   ```php
   require_once __DIR__ . '/../../backend/school_year_helpers.php';
   ```
3. In your database query, add:
   ```php
   $schoolYearId = get_selected_school_year_id($pdo);
   // Then in WHERE clause:
   // AND student.school_year_id = :sy_id
   ```

---

## Troubleshooting

### Issue: "School Year Selector" doesn't appear
**Solution**: 
- Check migration ran: `SELECT * FROM school_years;`
- Hard refresh browser (Ctrl+F5)
- Check browser console for JavaScript errors

### Issue: New students not assigned to school year
**Solution**:
- Ensure migration added school_year_id column
- Verify selected school year in banner
- Check student record: `SELECT school_year_id FROM student WHERE student_id = X;`

### Issue: Students appearing in wrong school year
**Solution**:
- Verify selected school year in banner
- Check student's school_year_id: `SELECT * FROM student WHERE school_year_id = X;`
- Clear browser session/cache

### Issue: Can't activate a school year
**Solution**:
- Can only activate one year at a time (others auto-archive)
- Current active year must be different
- Check for JavaScript errors in console

---

## Database Verification Commands

Run these SQL queries to verify everything is set up correctly:

```sql
-- Check school_years table
SELECT COUNT(*) as school_years_count FROM school_years;

-- Check students have school_year_id
SELECT COUNT(*) as students_with_school_year 
FROM student 
WHERE school_year_id IS NOT NULL;

-- Check that all are assigned
SELECT COUNT(*) as students_without_school_year 
FROM student 
WHERE school_year_id IS NULL;
-- This should be 0 (all students assigned)

-- View all school years
SELECT id, school_year, status, COUNT(s.student_id) as student_count
FROM school_years sy
LEFT JOIN student s ON s.school_year_id = sy.id
GROUP BY sy.id
ORDER BY sy.school_year DESC;

-- Check archive history
SELECT COUNT(*) as archived_records FROM student_archive_history;
```

---

## Common Scenarios

### Scenario 1: Mid-Year New Student
1. Ensure "2024-2025" is selected in banner
2. Click "Add Student"
3. Student is added to 2024-2025
4. Done - no manual assignment needed

### Scenario 2: Reviewing Last Year's Data
1. Click school year dropdown
2. Select "2023-2024" (if archived)
3. All students, endorsements, monitoring data for that year load
4. Click back to current year when done

### Scenario 3: End of Year Cleanup
1. Click "Manage School Years"
2. Click "Start New School Year"
3. Enter "2025-2026"
4. System automatically:
   - Completes current year
   - Preserves all data
   - Creates new active year
   - Ready for next batch of students

---

## What's Preserved

When archiving a school year:
- ✅ All student records
- ✅ OJT hours and completion data
- ✅ Endorsements and MOA status
- ✅ Requirements and submissions
- ✅ Evaluations and grades
- ✅ Monitoring and journals
- ✅ Analytics and reports

Archived data remains:
- **Visible** to advisers and employers
- **Readable** but not editable
- **Queryable** for reports
- **Safe** in archive history table

---

## Next Steps

After confirming everything works:

1. **Notify advisers** about the new school year selector
2. **Update documentation** for your team
3. **Consider extending** to other modules
4. **Set up regular backups** of school_years and student_archive_history tables
5. **Train staff** on the new workflow

---

## Support

If you encounter any issues:

1. Check troubleshooting section above
2. Verify database migration ran
3. Check browser console for errors
4. Review `SCHOOL_YEAR_MANAGEMENT_GUIDE.md` for detailed documentation
5. Verify all files are in place:
   - `backend/migrations/migration_school_years.sql` ✓
   - `backend/school_year_helpers.php` ✓
   - `pages/adviser/students/school_years_api.php` ✓
   - `pages/adviser/students/school_years_query.php` ✓
   - Updated `pages/adviser/students.php` ✓
   - Updated `pages/adviser/students/add_student_action.php` ✓

All files are in place and ready to use!
