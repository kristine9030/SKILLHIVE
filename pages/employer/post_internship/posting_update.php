<?php
/**
 * Purpose: Updates an employer-owned internship posting status.
 * Tables/columns used: internship(internship_id, employer_id, status).
 */

if (!function_exists('updateEmployerInternshipPostingStatus')) {
    function updateEmployerInternshipPostingStatus(PDO $pdo, int $employerId, int $internshipId, string $status): array
    {
        if ($internshipId <= 0) {
            return ['success' => false, 'error' => 'Invalid posting selected.'];
        }

        $nextStatus = ucfirst(strtolower(trim($status)));
        $allowedStatuses = ['Open', 'Closed'];
        if (!in_array($nextStatus, $allowedStatuses, true)) {
            return ['success' => false, 'error' => 'Invalid status selection.'];
        }

        $ownershipStmt = $pdo->prepare(
            'SELECT status
             FROM internship
             WHERE internship_id = :internship_id
               AND employer_id = :employer_id
             LIMIT 1'
        );
        $ownershipStmt->execute([
            ':internship_id' => $internshipId,
            ':employer_id' => $employerId,
        ]);

        $ownedPosting = $ownershipStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ownedPosting) {
            return ['success' => false, 'error' => 'Posting not found or not owned by your account.'];
        }

        $currentStatus = ucfirst(strtolower(trim((string)($ownedPosting['status'] ?? ''))));
        if ($currentStatus === $nextStatus) {
            return ['success' => true, 'error' => null];
        }

        $updateStmt = $pdo->prepare(
            'UPDATE internship
             SET status = :status
             WHERE internship_id = :internship_id
               AND employer_id = :employer_id'
        );
        $updateStmt->execute([
            ':status' => $nextStatus,
            ':internship_id' => $internshipId,
            ':employer_id' => $employerId,
        ]);

        return ['success' => true, 'error' => null];
    }
}
