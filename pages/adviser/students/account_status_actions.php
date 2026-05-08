<?php
/**
 * Purpose: Handles adviser-managed student account status changes.
 *
 * Supported statuses:
 *   Active   – normal login allowed (re-activates a previously blocked account)
 *   Inactive – login blocked; used for drop requests or program shifts
 *   Archived – login blocked; used when OJT hours are completed or OJT is done
 *
 * Adviser and employer retain full read-only access to past records regardless
 * of account_status — this is a soft block on login and timesheet submission only.
 *
 * Tables/columns used:
 *   student(student_id, account_status, account_status_reason,
 *           account_status_changed_at, account_status_changed_by)
 *   adviser_assignment(adviser_id, student_id, status)
 *   ojt_record(student_id, completion_status, hours_required, hours_completed)
 */

if (!function_exists('adviser_account_status_update')) {
    /**
     * Change a student's account_status.
     *
     * @param  PDO    $pdo
     * @param  int    $adviserId    Must own the student via adviser_assignment.
     * @param  int    $studentId
     * @param  string $newStatus    'Active' | 'Inactive' | 'Archived'
     * @param  string $reason       Optional adviser-supplied reason.
     * @return array  ['success' => bool, 'error' => string|null, 'new_status' => string]
     */
    function adviser_account_status_update(
        PDO    $pdo,
        int    $adviserId,
        int    $studentId,
        string $newStatus,
        string $reason = ''
    ): array {
        $allowed = ['Active', 'Inactive', 'Archived'];
        if (!in_array($newStatus, $allowed, true)) {
            return ['success' => false, 'error' => 'Invalid account status value.', 'new_status' => ''];
        }

        if ($adviserId <= 0 || $studentId <= 0) {
            return ['success' => false, 'error' => 'Invalid adviser or student ID.', 'new_status' => ''];
        }

        // Verify the student belongs to this adviser.
        $checkStmt = $pdo->prepare(
            'SELECT 1
             FROM adviser_assignment
             WHERE adviser_id = :adviser_id
               AND student_id  = :student_id
             LIMIT 1'
        );
        $checkStmt->execute([':adviser_id' => $adviserId, ':student_id' => $studentId]);
        if (!$checkStmt->fetchColumn()) {
            return ['success' => false, 'error' => 'Student not found or not assigned to you.', 'new_status' => ''];
        }

        // Check whether the account_status columns exist (pre-migration guard).
        if (!adviser_account_status_columns_exist($pdo)) {
            return [
                'success' => false,
                'error'   => 'Database migration required. Please run migration_account_status.sql first.',
                'new_status' => '',
            ];
        }

        $cleanReason = trim(substr($reason, 0, 255));

        $updateStmt = $pdo->prepare(
            'UPDATE student
             SET account_status             = :status,
                 account_status_reason      = :reason,
                 account_status_changed_at  = NOW(),
                 account_status_changed_by  = :adviser_id,
                 updated_at                 = NOW()
             WHERE student_id = :student_id'
        );
        $updateStmt->execute([
            ':status'     => $newStatus,
            ':reason'     => $cleanReason !== '' ? $cleanReason : null,
            ':adviser_id' => $adviserId,
            ':student_id' => $studentId,
        ]);

        return ['success' => true, 'error' => null, 'new_status' => $newStatus];
    }
}

if (!function_exists('adviser_account_status_columns_exist')) {
    function adviser_account_status_columns_exist(PDO $pdo): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = \'student\'
                   AND COLUMN_NAME  = \'account_status\''
            );
            $stmt->execute();
            $exists = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $exists = false;
        }

        return $exists;
    }
}

if (!function_exists('adviser_account_status_get')) {
    /**
     * Retrieve the current account_status and reason for a student.
     * Returns defaults gracefully if columns don't exist yet.
     */
    function adviser_account_status_get(PDO $pdo, int $studentId): array
    {
        if (!adviser_account_status_columns_exist($pdo)) {
            return [
                'account_status'        => 'Active',
                'account_status_reason' => '',
                'account_status_changed_at' => null,
            ];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT account_status, account_status_reason, account_status_changed_at
                 FROM student
                 WHERE student_id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => $studentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['account_status' => 'Active', 'account_status_reason' => '', 'account_status_changed_at' => null];
            }
            return [
                'account_status'            => $row['account_status'] ?? 'Active',
                'account_status_reason'     => $row['account_status_reason'] ?? '',
                'account_status_changed_at' => $row['account_status_changed_at'] ?? null,
            ];
        } catch (Throwable $e) {
            return ['account_status' => 'Active', 'account_status_reason' => '', 'account_status_changed_at' => null];
        }
    }
}

if (!function_exists('adviser_account_status_suggest')) {
    /**
     * Suggest the appropriate account_status based on OJT context.
     *
     * Completed OJT → 'Archived'
     * Dropped OJT   → 'Inactive'
     * Otherwise     → 'Active'
     *
     * @param  string $completionStatus  ojt_record.completion_status
     * @return string
     */
    function adviser_account_status_suggest(string $completionStatus): string
    {
        $normalized = strtolower(trim($completionStatus));
        if ($normalized === 'completed') {
            return 'Archived';
        }
        if ($normalized === 'dropped') {
            return 'Inactive';
        }
        return 'Active';
    }
}
