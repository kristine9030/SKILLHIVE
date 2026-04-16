<?php
/**
 * Purpose: Resolves the active employer ID from session data for employer-only page access.
 * Tables/columns used: No database access. Uses session keys employer_id, role, user_role, and user_id.
 */

if (!function_exists('resolveEmployerId')) {
    function resolveEmployerId(array $session, ?int $fallbackUserId = null): ?int
    {
        $employerId = isset($session['employer_id']) ? (int)$session['employer_id'] : 0;
        if ($employerId > 0) {
            return $employerId;
        }

        $role = (string)($session['role'] ?? ($session['user_role'] ?? ''));
        if ($role === 'employer') {
            $userId = isset($session['user_id']) ? (int)$session['user_id'] : (int)($fallbackUserId ?? 0);
            if ($userId > 0) {
                return $userId;
            }
        }

        return null;
    }
}

if (!function_exists('getEmployerVerificationStatus')) {
    function getEmployerVerificationStatus(PDO $pdo, int $employerId): ?string
    {
        if ($employerId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT verification_status
             FROM employer
             WHERE employer_id = :employer_id
             LIMIT 1'
        );
        $stmt->execute([':employer_id' => $employerId]);
        $status = $stmt->fetchColumn();

        if ($status === false) {
            return null;
        }

        return trim((string)$status);
    }
}

if (!function_exists('isEmployerApproved')) {
    function isEmployerApproved(?string $verificationStatus): bool
    {
        return strtolower(trim((string)$verificationStatus)) === 'approved';
    }
}
