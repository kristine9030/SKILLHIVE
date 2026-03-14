<?php
// Test script for marketplace functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/backend/db_connect.php';

echo "=== MARKETPLACE MODULE TEST ===\n\n";

// Test 1: Check if v_internship_listings view exists and works
echo "Test 1: Checking v_internship_listings view...\n";
try {
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM v_internship_listings');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ View exists. Found {$result['count']} open internships.\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 2: Check if we can fetch internship details with description
echo "Test 2: Checking internship details query...\n";
try {
    $stmt = $pdo->query('
        SELECT v.internship_id, v.title, v.company_name, v.industry, v.company_badge_status, 
               v.duration_weeks, v.allowance, v.work_setup, v.location, v.slots_available, 
               v.status, v.posted_at, v.required_skills, i.description, i.employer_id,
               e.company_logo, e.company_address, e.website_url, e.verification_status
        FROM v_internship_listings v
        INNER JOIN internship i ON i.internship_id = v.internship_id
        INNER JOIN employer e ON e.employer_id = i.employer_id
        WHERE v.status = "Open"
        LIMIT 1
    ');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "✓ Query successful. Sample internship: {$result['title']}\n";
        echo "  - Company: {$result['company_name']}\n";
        echo "  - Description: " . substr($result['description'], 0, 50) . "...\n\n";
    } else {
        echo "✓ Query successful but no internships found.\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check application table columns
echo "Test 3: Checking application table columns...\n";
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM application');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['consented_at', 'consent_version', 'compliance_snapshot', 'resume_link_snapshot', 'profile_link_snapshot'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        echo "✓ All required columns exist in application table.\n\n";
    } else {
        echo "⚠ Missing columns: " . implode(', ', $missingColumns) . "\n";
        echo "  These will be auto-created when marketplace is accessed.\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Check student_skill and internship_skill tables
echo "Test 4: Checking skill-related tables...\n";
try {
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM student_skill');
    $studentSkills = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM internship_skill');
    $internshipSkills = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✓ student_skill table: {$studentSkills['count']} records\n";
    echo "✓ internship_skill table: {$internshipSkills['count']} records\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 5: Check filter options
echo "Test 5: Checking filter options...\n";
try {
    $stmt = $pdo->query('SELECT DISTINCT industry FROM v_internship_listings WHERE status = "Open" ORDER BY industry ASC');
    $industries = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query('SELECT DISTINCT location FROM v_internship_listings WHERE status = "Open" ORDER BY location ASC');
    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✓ Available industries: " . count($industries) . " (" . implode(', ', array_slice($industries, 0, 3)) . (count($industries) > 3 ? '...' : '') . ")\n";
    echo "✓ Available locations: " . count($locations) . " (" . implode(', ', array_slice($locations, 0, 3)) . (count($locations) > 3 ? '...' : '') . ")\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

echo "=== TEST COMPLETE ===\n";
echo "\nIf all tests passed, the marketplace module should work correctly.\n";
echo "Access it at: http://localhost/SkillHive/layout.php?page=student/marketplace\n";
?>
