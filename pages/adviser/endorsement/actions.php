<?php
/**
 * Purpose: Handles adviser endorsement status updates and notes.
 * Tables/columns used: endorsement(endorsement_id, application_id, adviser_id, status, reviewed_at, notes), application(application_id, student_id), adviser_assignment(adviser_id, student_id, status).
 */

if (!function_exists('adviser_endorsement_update_status')) {
    function adviser_endorsement_update_status(PDO $pdo, int $adviserId, int $endorsementId, string $action, string $notes = ''): array
    {
        if ($endorsementId <= 0) {
            return ['success' => false, 'error' => 'Invalid endorsement selected.'];
        }

        $actionKey = strtolower(trim($action));
        if (!in_array($actionKey, ['approve', 'reject', 'request_docs'], true)) {
            return ['success' => false, 'error' => 'Invalid endorsement action.'];
        }

        $notesValue = trim($notes);

        $authStmt = $pdo->prepare(
            'SELECT e.endorsement_id
             FROM endorsement e
             INNER JOIN application a ON a.application_id = e.application_id
             INNER JOIN adviser_assignment aa ON aa.student_id = a.student_id
                AND aa.adviser_id = :aa_adviser_id
                AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             WHERE e.endorsement_id = :endorsement_id
               AND e.adviser_id = :endorsement_adviser_id
             LIMIT 1'
        );
        $authStmt->execute([
            ':aa_adviser_id' => $adviserId,
            ':endorsement_id' => $endorsementId,
            ':endorsement_adviser_id' => $adviserId,
        ]);

        if (!$authStmt->fetchColumn()) {
            return ['success' => false, 'error' => 'Endorsement not found or access denied.'];
        }

        if ($actionKey === 'request_docs') {
            $stmt = $pdo->prepare(
                'UPDATE endorsement
                 SET notes = :notes
                 WHERE endorsement_id = :endorsement_id
                   AND adviser_id = :adviser_id'
            );
            $stmt->execute([
                ':notes' => $notesValue,
                ':endorsement_id' => $endorsementId,
                ':adviser_id' => $adviserId,
            ]);

            return ['success' => true, 'error' => null];
        }

        $nextStatus = $actionKey === 'approve' ? 'Approved' : 'Rejected';

        $stmt = $pdo->prepare(
            'UPDATE endorsement
             SET status = :status,
                 notes = :notes,
                 reviewed_at = NOW()
             WHERE endorsement_id = :endorsement_id
               AND adviser_id = :adviser_id'
        );
        $stmt->execute([
            ':status' => $nextStatus,
            ':notes' => $notesValue,
            ':endorsement_id' => $endorsementId,
            ':adviser_id' => $adviserId,
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Endorsement not found or already updated.'];
        }

        return ['success' => true, 'error' => null];
    }
}
