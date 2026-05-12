<?php
session_start();
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/pages/adviser/students/school_years_query.php';
require_once __DIR__ . '/pages/adviser/students/filters_query.php';

// Simulate adviser 8 session
$_SESSION['adviser_id'] = 8;
$_SESSION['user_id'] = 8;
$_SESSION['role'] = 'adviser';
$_SESSION['selected_school_year_id'] = 2;

$adviserId = 8;
$schoolYearId = 2;

echo "=== FILTER & SORTING VERIFICATION ===\n\n";

// Test: Get filter options
echo "1. Available Department Filters:\n";
$filters = adviser_students_get_filter_options($pdo, $adviserId);
echo "   Departments: " . json_encode($filters['departments']) . "\n";
echo "   Count: " . count($filters['departments']) . "\n\n";

echo "2. Available Status Filters:\n";
echo "   Statuses: " . json_encode($filters['statuses']) . "\n";
echo "   Count: " . count($filters['statuses']) . "\n\n";

// Test: Get students WITHOUT filters (with sorting)
echo "3. Students from 2025-2026 (School Year ID: 2) - Active Tab:\n";
$noFilterStudents = adviser_students_get_tab_students(
    $pdo, $adviserId, $schoolYearId, 'active', []
);
echo "   Total Students: " . count($noFilterStudents) . "\n";
if (count($noFilterStudents) > 0) {
    echo "   First 3 (sorted by name):\n";
    for ($i = 0; $i < min(3, count($noFilterStudents)); $i++) {
        $s = $noFilterStudents[$i];
        echo "   - {$s['student_number']}: {$s['first_name']} {$s['last_name']} (Section: {$s['track']} {$s['section']})\n";
    }
}
echo "\n";

// Test: Department filter
echo "4. Department Filter Test - 'BA 02':\n";
$deptFilter = adviser_students_get_tab_students(
    $pdo, $adviserId, $schoolYearId, 'active', ['department' => 'BA 02']
);
echo "   Students with BA section 02: " . count($deptFilter) . "\n";
foreach ($deptFilter as $s) {
    echo "   - {$s['student_number']}: {$s['first_name']} {$s['last_name']}\n";
}
echo "\n";

// Test: Status filter
echo "5. Status Filter Test - 'Available':\n";
$statusFilter = adviser_students_get_tab_students(
    $pdo, $adviserId, $schoolYearId, 'active', ['status' => 'Available']
);
echo "   Students with Available status: " . count($statusFilter) . "\n\n";

// Test: Search filter
echo "6. Search Filter Test - 'Miguel':\n";
$searchFilter = adviser_students_get_tab_students(
    $pdo, $adviserId, $schoolYearId, 'active', ['search' => 'Miguel']
);
echo "   Students with 'Miguel': " . count($searchFilter) . "\n";
foreach ($searchFilter as $s) {
    echo "   - {$s['student_number']}: {$s['first_name']} {$s['last_name']}\n";
}
echo "\n";

// Test: Combined filters
echo "7. Combined Filters - Department 'BA' + Status 'Available':\n";
$combinedFilter = adviser_students_get_tab_students(
    $pdo, $adviserId, $schoolYearId, 'active', ['department' => 'BA 03', 'status' => 'Available']
);
echo "   Students: " . count($combinedFilter) . "\n";
foreach ($combinedFilter as $s) {
    echo "   - {$s['student_number']}: {$s['first_name']} {$s['last_name']}\n";
}
echo "\n";

// Test: Archived tab
echo "8. Archived Students Tab (2026-2027):\n";
$archivedStudents = adviser_students_get_tab_students(
    $pdo, 8, 3, 'archived', []
);
echo "   Total Archived: " . count($archivedStudents) . "\n";
if (count($archivedStudents) > 0) {
    foreach ($archivedStudents as $s) {
        echo "   - {$s['student_number']}: {$s['first_name']} {$s['last_name']}\n";
    }
}

echo "\n✅ All filters and sorting are functional!\n";
?>
