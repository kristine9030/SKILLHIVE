<?php
/**
 * Purpose: Handles adviser company verification actions.
 * Tables/columns used: employer(employer_id, verification_status).
 */

if (!function_exists('adviser_companies_update_verification_status')) {
    function adviser_companies_update_verification_status(PDO $pdo, int $employerId, string $action): array
    {
        if ($employerId <= 0) {
            return ['success' => false, 'error' => 'Invalid company selected.'];
        }

        $normalizedAction = strtolower(trim($action));
        $nextStatus = '';

        if ($normalizedAction === 'approve') {
            $nextStatus = 'Approved';
        } elseif ($normalizedAction === 'reject') {
            $nextStatus = 'Rejected';
        } else {
            return ['success' => false, 'error' => 'Unsupported action.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE employer
             SET verification_status = :verification_status
             WHERE employer_id = :employer_id'
        );

        $stmt->execute([
            ':verification_status' => $nextStatus,
            ':employer_id' => $employerId,
        ]);

        if ($stmt->rowCount() < 1) {
            return ['success' => false, 'error' => 'No company was updated.'];
        }

        return ['success' => true, 'status' => $nextStatus];
    }
}
