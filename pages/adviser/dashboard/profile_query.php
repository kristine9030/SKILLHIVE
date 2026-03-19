<?php
/**
 * Purpose: Loads adviser profile details for dashboard-scoped data requests.
 * Tables/columns used: internship_adviser(adviser_id, first_name, last_name, department, email).
 */

if (!function_exists('adviser_dashboard_get_profile')) {
    function adviser_dashboard_get_profile(PDO $pdo, int $adviserId): array
    {
        $stmt = $pdo->prepare(
            'SELECT adviser_id, first_name, last_name, department, email
             FROM internship_adviser
             WHERE adviser_id = :adviser_id
             LIMIT 1'
        );
        $stmt->execute([':adviser_id' => $adviserId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'adviser_id' => $adviserId,
                'first_name' => '',
                'last_name' => '',
                'department' => '',
                'email' => '',
            ];
        }

        return $row;
    }
}