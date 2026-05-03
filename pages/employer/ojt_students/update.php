<?php
/**
 * Update helper for OJT Students module
 * Handles updating OJT student details with employer ownership validation
 */

/**
 * Update OJT student details
 * Validates employer ownership before updating
 * 
 * @param PDO $pdo
 * @param int $employerId
 * @param int $recordId
 * @param array $data - keys: internship_id, completion_status, start_date, end_date
 * @return array ['success' => bool, 'error' => string]
 */
function updateOjtStudentDetails(PDO $pdo, int $employerId, int $recordId, array $data): array {
    // First validate that the record belongs to the employer
    $checkSql = "SELECT ojr.record_id 
                 FROM ojt_record ojr
                 INNER JOIN internship i ON ojr.internship_id = i.internship_id
                 WHERE ojr.record_id = :recordId 
                 AND i.employer_id = :employerId
                 LIMIT 1";
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':recordId' => $recordId,
        ':employerId' => $employerId
    ]);
    
    if (!$checkStmt->fetch()) {
        return ['success' => false, 'error' => 'Record not found or access denied.'];
    }
    
    // Build update query with allowed fields only
    $updateFields = [];
    $params = [':recordId' => $recordId];
    
    // Department (internship assignment) - validate it belongs to employer
    if (isset($data['internship_id'])) {
        $internshipSql = "SELECT internship_id FROM internship WHERE internship_id = :internshipId AND employer_id = :employerId LIMIT 1";
        $internshipStmt = $pdo->prepare($internshipSql);
        $internshipStmt->execute([
            ':internshipId' => (int)$data['internship_id'],
            ':employerId' => $employerId
        ]);
        
        if (!$internshipStmt->fetch()) {
            return ['success' => false, 'error' => 'Invalid department selection.'];
        }
        
        $updateFields[] = "internship_id = :internship_id";
        $params[':internship_id'] = (int)$data['internship_id'];
    }
    
    // Completion status
    if (isset($data['completion_status'])) {
        $allowedStatuses = ['Ongoing', 'Completed', 'Pending'];
        if (!in_array($data['completion_status'], $allowedStatuses)) {
            return ['success' => false, 'error' => 'Invalid completion status.'];
        }
        $updateFields[] = "completion_status = :completion_status";
        $params[':completion_status'] = $data['completion_status'];
    }
    
    // Start date
    if (isset($data['start_date'])) {
        $updateFields[] = "start_date = :start_date";
        $params[':start_date'] = $data['start_date'];
    }
    
    // End date
    if (isset($data['end_date'])) {
        $updateFields[] = "end_date = :end_date";
        $params[':end_date'] = $data['end_date'];
    }
    
    if (empty($updateFields)) {
        return ['success' => false, 'error' => 'No fields to update.'];
    }
    
    $sql = "UPDATE ojt_record SET " . implode(', ', $updateFields) . " WHERE record_id = :recordId";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['success' => true, 'error' => ''];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to update record.'];
    }
}
