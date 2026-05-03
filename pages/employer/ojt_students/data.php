<?php
/**
 * Data helper for OJT Students module
 * Fetches OJT students and related data for employer
 */

/**
 * Get all OJT students under an employer's company
 * @param PDO $pdo
 * @param int $employerId
 * @return array
 */
function getEmployerOjtStudents(PDO $pdo, int $employerId): array {
    $sql = "SELECT 
                ojr.record_id,
                ojr.student_id,
                ojr.internship_id,
                ojr.hours_required,
                ojr.hours_completed,
                ojr.start_date,
                ojr.end_date,
                ojr.completion_status,
                s.first_name,
                s.last_name,
                s.program AS school,
                i.title AS department
            FROM ojt_record ojr
            INNER JOIN internship i ON ojr.internship_id = i.internship_id
            INNER JOIN student s ON ojr.student_id = s.student_id
            WHERE i.employer_id = :employerId
            ORDER BY ojr.start_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':employerId' => $employerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all internships for an employer (for department dropdown)
 * @param PDO $pdo
 * @param int $employerId
 * @return array
 */
function getEmployerInternships(PDO $pdo, int $employerId): array {
    $sql = "SELECT internship_id, title 
            FROM internship 
            WHERE employer_id = :employerId 
            ORDER BY title ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':employerId' => $employerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
