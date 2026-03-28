<?php
/**
 * Purpose: Handles adviser monitoring actions.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), ojt_record(record_id, student_id, hours_required, hours_completed, updated_at), daily_log(record_id, hours_rendered).
 */

if (!function_exists('adviser_monitoring_approve_all_logs')) {
    function adviser_monitoring_approve_all_logs(PDO $pdo, int $adviserId, int $recordId): array
    {
        if ($recordId <= 0) {
            return ['success' => false, 'error' => 'Invalid OJT record selected.'];
        }

        $stmt = $pdo->prepare(
            'SELECT o.record_id, o.hours_required
             FROM ojt_record o
             INNER JOIN adviser_assignment aa ON aa.student_id = o.student_id
                AND aa.adviser_id = :adviser_id
                AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             WHERE o.record_id = :record_id
             LIMIT 1'
        );
        $stmt->execute([
            ':adviser_id' => $adviserId,
            ':record_id' => $recordId,
        ]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$record) {
            return ['success' => false, 'error' => 'OJT record not found or access denied.'];
        }

        $sumStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(hours_rendered), 0) AS total_hours
             FROM daily_log
             WHERE record_id = :record_id'
        );
        $sumStmt->execute([':record_id' => $recordId]);

        $totalHours = (float)($sumStmt->fetchColumn() ?: 0);
        $requiredHours = max(0.0, (float)($record['hours_required'] ?? 0));

        if ($requiredHours > 0 && $totalHours > $requiredHours) {
            $totalHours = $requiredHours;
        }

        $updateStmt = $pdo->prepare(
            'UPDATE ojt_record
             SET hours_completed = :hours_completed,
                 updated_at = NOW()
             WHERE record_id = :record_id'
        );
        $updateStmt->execute([
            ':hours_completed' => $totalHours,
            ':record_id' => $recordId,
        ]);

        return ['success' => true, 'error' => null];
    }
}
