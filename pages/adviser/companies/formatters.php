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
            'linear-gradient(135deg,#06B6D4,#10B981)',
            'linear-gradient(135deg,#6F42C1,#EC4899)',
            'linear-gradient(135deg,#F59E0B,#EF4444)',
            'linear-gradient(135deg,#10B981,#06B6D4)',
            'linear-gradient(135deg,#3B82F6,#8B5CF6)',
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
