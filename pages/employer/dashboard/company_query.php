<?php
/**
 * Purpose: Loads the employer company profile block for the dashboard sidebar.
 * Tables/columns used: employer(employer_id, company_name, verification_status, company_badge_status).
 */

if (!function_exists('dashboard_get_company_data')) {
    function dashboard_get_company_data(PDO $pdo, int $employerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT company_name, verification_status, company_badge_status
             FROM employer
             WHERE employer_id = :employer_id
             LIMIT 1'
        );
        $stmt->execute([':employer_id' => $employerId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'company_name' => 'Employer',
                'verification_status' => 'pending',
                'company_badge_status' => null,
            ];
        }

        return $row;
    }
}
