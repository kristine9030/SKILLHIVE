<?php
/**
 * Purpose: Handles adviser endorsement status updates and notes.
 * Tables/columns used: endorsement(endorsement_id, application_id, adviser_id, status, reviewed_at, notes), application(application_id, student_id), adviser_assignment(adviser_id, student_id, status).
 */

require_once __DIR__ . '/../../../backend/functions/notifications.php';

if (!function_exists('adviser_endorsement_notify_employer_for_approval')) {
    function adviser_endorsement_notify_employer_for_approval(PDO $pdo, int $endorsementId, int $adviserId): void
    {
        if ($endorsementId <= 0 || $adviserId <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                e.application_id,
                i.employer_id,
                i.title AS internship_title,
                s.first_name,
                s.last_name
             FROM endorsement e
             INNER JOIN application a ON a.application_id = e.application_id
             INNER JOIN internship i ON i.internship_id = a.internship_id
             INNER JOIN student s ON s.student_id = a.student_id
             WHERE e.endorsement_id = :endorsement_id
               AND e.adviser_id = :adviser_id
             LIMIT 1'
        );
        $stmt->execute([
            ':endorsement_id' => $endorsementId,
            ':adviser_id' => $adviserId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }

        $applicationId = (int)($row['application_id'] ?? 0);
        $employerId = (int)($row['employer_id'] ?? 0);
        if ($applicationId <= 0 || $employerId <= 0) {
            return;
        }

        $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($studentName === '') {
            $studentName = 'the student';
        }

        $internshipTitle = trim((string)($row['internship_title'] ?? 'the internship position'));
        if ($internshipTitle === '') {
            $internshipTitle = 'the internship position';
        }

        skillhive_notifications_create($pdo, [
            'recipient_role' => 'employer',
            'recipient_id' => $employerId,
            'type' => 'endorsement_approved',
            'title' => 'Endorsement Approved',
            'message' => $studentName . ' is now approved for endorsement under ' . $internshipTitle . '. You may schedule the interview now.',
            'target_url' => 'layout.php?page=employer/candidates',
            'reference_table' => 'application',
            'reference_id' => $applicationId,
        ]);
    }
}

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

        if ($actionKey === 'approve') {
            try {
                adviser_endorsement_notify_employer_for_approval($pdo, $endorsementId, $adviserId);
            } catch (Throwable $e) {
                // Non-fatal: approval should still succeed even if notification creation fails.
            }
        }

        return ['success' => true, 'error' => null];
    }
}
