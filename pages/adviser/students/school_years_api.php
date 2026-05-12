<?php
/**
 * School Years Management API
 * Purpose: Handle CRUD operations for school years and archiving logic
 * Access: Adviser role required
 */

// Start output buffering to prevent any stray output
ob_start();

// Set error reporting to not display errors (they'll be logged instead)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Set error handler to prevent output of errors
set_error_handler(function($errno, $errstr) {
    http_response_code(500);
    ob_clean();
    error_log('School Years API Error: ' . $errstr);
    echo json_encode(['success' => false, 'message' => 'An error occurred', 'debug' => $errstr]);
    exit;
});

// Set exception handler for uncaught exceptions
set_exception_handler(function($e) {
    http_response_code(500);
    ob_clean();
    error_log('School Years API Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred', 'debug' => $e->getMessage()]);
    exit;
});

require_once __DIR__ . '/../../../backend/db_connect.php';

$action = trim((string)($_REQUEST['action'] ?? ''));

// Log the request for debugging
error_log('School Years API called: action=' . $action . ', method=' . $_SERVER['REQUEST_METHOD']);

// Simple health check
if (($action === 'health' || $action === '') && !isset($_POST)) {
    echo json_encode(['success' => true, 'message' => 'API is healthy']);
    exit;
}

$role = (string)($_SESSION['role'] ?? '');
$adviserId = (int)($_SESSION['adviser_id'] ?? ($_SESSION['user_id'] ?? 0));
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'adviser' || $adviserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ============================================================================
// GET SCHOOL YEARS
// ============================================================================
if ($action === 'get_all') {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, school_year, status, created_at, updated_at
             FROM school_years
             ORDER BY school_year DESC'
        );
        $stmt->execute();
        $schoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Get student counts for each school year
        foreach ($schoolYears as &$year) {
            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) as count FROM student WHERE school_year_id = :id'
            );
            $countStmt->execute([':id' => (int)$year['id']]);
            $year['student_count'] = (int)($countStmt->fetchColumn());
        }
        unset($year);

        echo json_encode(['success' => true, 'data' => $schoolYears]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch school years']);
    }
    exit;
}

// ============================================================================
// GET ACTIVE SCHOOL YEAR
// ============================================================================
if ($action === 'get_active') {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, school_year, status FROM school_years WHERE status = "Active" LIMIT 1'
        );
        $stmt->execute();
        $activeYear = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$activeYear) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No active school year found']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $activeYear]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch active school year']);
    }
    exit;
}

// ============================================================================
// CREATE NEW SCHOOL YEAR
// ============================================================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolYear = trim((string)($_POST['school_year'] ?? ''));
    error_log('Create school year: input=' . $schoolYear);
    
    if (!preg_match('/^\d{4}-\d{4}$/', $schoolYear)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid school year format. Use YYYY-YYYY format (e.g., 2024-2025)']);
        exit;
    }

    try {
        $checkStmt = $pdo->prepare('SELECT id FROM school_years WHERE school_year = :year');
        $checkStmt->execute([':year' => $schoolYear]);
        if ($checkStmt->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'School year already exists']);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO school_years (school_year, status) VALUES (:year, :status)'
        );
        $result = $stmt->execute([':year' => $schoolYear, ':status' => 'Archived']);
        error_log('Insert result: ' . ($result ? 'success' : 'failed'));
        
        $newId = (int)$pdo->lastInsertId();
        error_log('New school year ID: ' . $newId);

        echo json_encode(['success' => true, 'message' => 'School year created', 'id' => $newId]);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('Failed to create school year: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create school year', 'debug' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// SET ACTIVE SCHOOL YEAR
// ============================================================================
if ($action === 'set_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newActiveId = (int)($_POST['school_year_id'] ?? 0);

    if ($newActiveId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid school year ID']);
        exit;
    }

    try {
        // Verify the school year exists
        $checkStmt = $pdo->prepare('SELECT school_year FROM school_years WHERE id = :id');
        $checkStmt->execute([':id' => $newActiveId]);
        $schoolYearRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$schoolYearRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'School year not found']);
            exit;
        }

        // Start transaction
        $pdo->beginTransaction();

        // Archive all currently active school years
        $archiveStmt = $pdo->prepare('UPDATE school_years SET status = "Archived" WHERE status = "Active"');
        $archiveStmt->execute();

        // Set the new active school year
        $activateStmt = $pdo->prepare('UPDATE school_years SET status = "Active" WHERE id = :id');
        $activateStmt->execute([':id' => $newActiveId]);

        // Store in session
        $_SESSION['selected_school_year_id'] = $newActiveId;
        $_SESSION['selected_school_year'] = $schoolYearRow['school_year'];

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'School year activated',
            'school_year' => $schoolYearRow['school_year']
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to set active school year']);
    }
    exit;
}

// ============================================================================
// ARCHIVE SCHOOL YEAR
// ============================================================================
if ($action === 'archive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $archiveId = (int)($_POST['school_year_id'] ?? 0);

    if ($archiveId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid school year ID']);
        exit;
    }

    try {
        // Check if school year is active
        $checkStmt = $pdo->prepare('SELECT status FROM school_years WHERE id = :id');
        $checkStmt->execute([':id' => $archiveId]);
        $statusRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$statusRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'School year not found']);
            exit;
        }

        if ($statusRow['status'] === 'Active') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Cannot archive active school year. Activate another year first.']);
            exit;
        }

        // Archive the school year
        $stmt = $pdo->prepare('UPDATE school_years SET status = "Archived" WHERE id = :id');
        $stmt->execute([':id' => $archiveId]);

        echo json_encode(['success' => true, 'message' => 'School year archived']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to archive school year']);
    }
    exit;
}

// ============================================================================
// START NEW SCHOOL YEAR
// ============================================================================
if ($action === 'start_new' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSchoolYear = trim((string)($_POST['school_year'] ?? ''));

    if (!preg_match('/^\d{4}-\d{4}$/', $newSchoolYear)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid school year format']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get current active school year
        $currentStmt = $pdo->prepare('SELECT id FROM school_years WHERE status = "Active" LIMIT 1');
        $currentStmt->execute();
        $currentId = (int)($currentStmt->fetchColumn());

        // Archive all completed and dropped students from current school year
        if ($currentId > 0) {
            // Step 1: Find and archive completed/dropped students
            $archiveStudentsStmt = $pdo->prepare(
                'UPDATE student 
                 SET archived_at = NOW()
                 WHERE school_year_id = :school_year_id
                   AND student_id IN (
                     SELECT DISTINCT o.student_id 
                     FROM ojt_record o
                     WHERE o.completion_status IN ("Completed", "Dropped")
                   )'
            );
            $archiveStudentsStmt->execute([':school_year_id' => $currentId]);

            // Step 2: Store archive history for completed/dropped students
            // Note: We use a UNION approach to avoid parameter in SELECT list
            $historyStmt = $pdo->prepare(
                'INSERT INTO student_archive_history 
                 (student_id, school_year_id, internship_status, hours_completed, completion_status)
                 SELECT 
                   o.student_id,
                   ? as school_year_id,
                   s.availability_status,
                   o.hours_completed,
                   o.completion_status
                 FROM ojt_record o
                 JOIN student s ON s.student_id = o.student_id
                 WHERE s.school_year_id = ?
                   AND o.completion_status IN ("Completed", "Dropped")
                   AND o.record_id = (
                     SELECT MAX(record_id) FROM ojt_record o2 
                     WHERE o2.student_id = o.student_id
                   )'
            );
            $historyStmt->execute([$currentId, $currentId]);

            // Step 3: Archive the current school year
            $archiveYearStmt = $pdo->prepare('UPDATE school_years SET status = "Archived" WHERE id = :id');
            $archiveYearStmt->execute([':id' => $currentId]);
        }

        // Check if new school year exists
        $checkStmt = $pdo->prepare('SELECT id FROM school_years WHERE school_year = :year');
        $checkStmt->execute([':year' => $newSchoolYear]);
        $existingId = (int)($checkStmt->fetchColumn());

        if ($existingId > 0) {
            // Use existing school year
            $activateStmt = $pdo->prepare('UPDATE school_years SET status = "Active" WHERE id = :id');
            $activateStmt->execute([':id' => $existingId]);
            $newId = $existingId;
        } else {
            // Create new school year and activate it
            $createStmt = $pdo->prepare('INSERT INTO school_years (school_year, status) VALUES (:year, :status)');
            $createStmt->execute([':year' => $newSchoolYear, ':status' => 'Active']);
            $newId = (int)$pdo->lastInsertId();
        }

        // Store in session
        $_SESSION['selected_school_year_id'] = $newId;
        $_SESSION['selected_school_year'] = $newSchoolYear;

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'New school year started',
            'school_year' => $newSchoolYear,
            'id' => $newId
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to start new school year: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// SELECT SCHOOL YEAR (Session-based)
// ============================================================================
if ($action === 'select' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolYearId = (int)($_POST['school_year_id'] ?? 0);

    if ($schoolYearId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid school year ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT school_year FROM school_years WHERE id = :id');
        $stmt->execute([':id' => $schoolYearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'School year not found']);
            exit;
        }

        $_SESSION['selected_school_year_id'] = $schoolYearId;
        $_SESSION['selected_school_year'] = $row['school_year'];

        echo json_encode([
            'success' => true,
            'message' => 'School year selected',
            'school_year' => $row['school_year']
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to select school year']);
    }
    exit;
}

// ============================================================================
// GET SELECTED SCHOOL YEAR
// ============================================================================
if ($action === 'get_selected') {
    try {
        $selectedId = (int)($_SESSION['selected_school_year_id'] ?? 0);

        if ($selectedId <= 0) {
            // Default to active school year
            $stmt = $pdo->prepare(
                'SELECT id, school_year FROM school_years WHERE status = "Active" LIMIT 1'
            );
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $_SESSION['selected_school_year_id'] = (int)$row['id'];
                $_SESSION['selected_school_year'] = $row['school_year'];
            }
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, school_year FROM school_years WHERE id = :id'
            );
            $stmt->execute([':id' => $selectedId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No school year selected']);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch selected school year']);
    }
    exit;
}

// Invalid action
http_response_code(422);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
?>
