<?php
/**
 * Purpose: Shared formatting helpers for adviser companies page.
 * Tables/columns used: No database access.
 */

if (!function_exists('adviser_companies_escape')) {
    function adviser_companies_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_companies_initial')) {
    function adviser_companies_initial(?string $companyName): string
    {
        $name = trim((string)$companyName);
        return $name !== '' ? strtoupper(substr($name, 0, 1)) : 'C';
    }
}

if (!function_exists('adviser_companies_verification_label')) {
    function adviser_companies_verification_label(?string $verificationStatus): string
    {
        $status = strtolower(trim((string)($verificationStatus ?? 'pending')));
        if ($status === 'approved') {
            return 'Verified';
        }
        if ($status === 'rejected') {
            return 'Rejected';
        }
        if ($status === 'flagged') {
            return 'Flagged';
        }
        return 'Pending';
    }
}

if (!function_exists('adviser_companies_verification_badge_class')) {
    function adviser_companies_verification_badge_class(?string $verificationStatus): string
    {
        $status = strtolower(trim((string)($verificationStatus ?? 'pending')));
        if ($status === 'approved') {
            return 'badge-active';
        }
        return 'badge-pending';
    }
}

if (!function_exists('adviser_companies_gradient')) {
    function adviser_companies_gradient(int $index): string
    {
        $palette = [
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
            'linear-gradient(135deg,#12b3ac,#EC4899)',
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
            'linear-gradient(135deg,#12b3ac,#12b3ac)',
        ];

        return $palette[$index % count($palette)];
    }
}

if (!function_exists('adviser_companies_rating_text')) {
    function adviser_companies_rating_text($avgRating): string
    {
        if ($avgRating === null || $avgRating === '' || !is_numeric($avgRating)) {
            return 'N/A';
        }

        return number_format((float)$avgRating, 1);
    }
}

if (!function_exists('adviser_companies_format_date')) {
    function adviser_companies_format_date(?string $dateValue): string
    {
        if (!$dateValue) {
            return 'N/A';
        }

        $timestamp = strtotime($dateValue);
        if ($timestamp === false) {
            return 'N/A';
        }

        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('adviser_companies_documents_meta')) {
    function adviser_companies_documents_meta(array $row): array
    {
        $filled = 0;
        $fields = [
            trim((string)($row['website_url'] ?? '')),
            trim((string)($row['email'] ?? '')),
            trim((string)($row['contact_number'] ?? '')),
            trim((string)($row['company_address'] ?? '')),
        ];

        foreach ($fields as $field) {
            if ($field !== '') {
                $filled++;
            }
        }

        if ($filled >= 4) {
            return ['label' => 'Complete', 'class' => 'is-success'];
        }

        if ($filled >= 2) {
            return ['label' => 'Partial', 'class' => 'is-warning'];
        }

        return ['label' => 'Incomplete', 'class' => 'is-danger'];
    }
}

if (!function_exists('adviser_companies_risk_meta')) {
    function adviser_companies_risk_meta(array $row): array
    {
        $status = strtolower(trim((string)($row['verification_status'] ?? 'pending')));
        $documents = adviser_companies_documents_meta($row);

        if ($status === 'rejected' || $status === 'flagged' || $documents['label'] === 'Incomplete') {
            return ['label' => 'High', 'class' => 'is-danger'];
        }

        if ($status === 'approved' || $documents['label'] === 'Complete') {
            return ['label' => 'Low', 'class' => 'is-success'];
        }

        return ['label' => 'Medium', 'class' => 'is-warning'];
    }
}

if (!function_exists('adviser_companies_action_meta')) {
    function adviser_companies_action_meta(array $row): array
    {
        $documents = adviser_companies_documents_meta($row);
        $risk = adviser_companies_risk_meta($row);
        $email = trim((string)($row['email'] ?? ''));

        if ($documents['label'] === 'Complete' && $risk['label'] === 'Low') {
            return ['label' => 'Approve', 'class' => 'primary', 'action' => 'approve'];
        }

        if ($documents['label'] === 'Incomplete' || $risk['label'] === 'High') {
            return ['label' => 'Reject', 'class' => 'danger', 'action' => 'reject'];
        }

        if ($email !== '') {
            return ['label' => 'Request Docs', 'class' => 'secondary', 'action' => 'mailto'];
        }

        return ['label' => 'Review', 'class' => 'secondary', 'action' => 'modal'];
    }
}
