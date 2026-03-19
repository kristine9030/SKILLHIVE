<?php
/**
 * Purpose: Handles adviser endorsement status updates.
 * Tables/columns used: endorsement(endorsement_id, adviser_id, status, reviewed_at).
 */

if (!function_exists('adviser_endorsement_update_status')) {
    function adviser_endorsement_update_status(PDO $pdo, int $adviserId, int $endorsementId, string $nextStatus): array
    {
        if ($endorsementId <= 0) {
            return ['success' => false, 'error' => 'Invalid endorsement selected.'];
        }

        $normalized = adviser_endorsement_normalize_status($nextStatus);
        if (!in_array($normalized, ['Endorsed', 'Declined'], true)) {
            return ['success' => false, 'error' => 'Invalid endorsement action.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE endorsement
             SET status = :status,
                 reviewed_at = NOW()
             WHERE endorsement_id = :endorsement_id
               AND adviser_id = :adviser_id'
        );
        $stmt->execute([
            ':status' => $normalized,
            ':endorsement_id' => $endorsementId,
            ':adviser_id' => $adviserId,
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Endorsement not found or already updated.'];
        }

        return ['success' => true, 'error' => null];
    }
}
