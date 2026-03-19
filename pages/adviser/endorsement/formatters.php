<?php
/**
 * Purpose: Shared formatting and status helpers for adviser endorsement page.
 * Tables/columns used: No database access.
 */

if (!function_exists('adviser_endorsement_escape')) {
    function adviser_endorsement_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_endorsement_initials')) {
    function adviser_endorsement_initials(?string $firstName, ?string $lastName): string
    {
        $first = trim((string)$firstName);
        $last = trim((string)$lastName);

        $initials = '';
        if ($first !== '') {
            $initials .= strtoupper(substr($first, 0, 1));
        }
        if ($last !== '') {
            $initials .= strtoupper(substr($last, 0, 1));
        }

        return $initials !== '' ? $initials : 'NA';
    }
}

if (!function_exists('adviser_endorsement_normalize_status')) {
    function adviser_endorsement_normalize_status(?string $status): string
    {
        $raw = strtolower(trim((string)($status ?? '')));
        $raw = str_replace(['_', '-'], ' ', $raw);

        $map = [
            'pending' => 'Pending',
            'reviewing' => 'Pending',
            'for review' => 'Pending',
            'submitted' => 'Pending',
            'endorsed' => 'Endorsed',
            'approved' => 'Endorsed',
            'declined' => 'Declined',
            'rejected' => 'Declined',
        ];

        return $map[$raw] ?? 'Pending';
    }
}

if (!function_exists('adviser_endorsement_status_class')) {
    function adviser_endorsement_status_class(?string $status): string
    {
        $normalized = adviser_endorsement_normalize_status($status);
        if ($normalized === 'Endorsed') {
            return 'status-accepted';
        }
        if ($normalized === 'Declined') {
            return 'status-rejected';
        }
        return 'status-pending';
    }
}

if (!function_exists('adviser_endorsement_format_date')) {
    function adviser_endorsement_format_date(?string $dateTime): string
    {
        if (!$dateTime) {
            return 'N/A';
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return 'N/A';
        }

        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('adviser_endorsement_duration_label')) {
    function adviser_endorsement_duration_label(?int $durationWeeks): string
    {
        $weeks = (int)$durationWeeks;
        if ($weeks <= 0) {
            return 'N/A';
        }

        if ($weeks % 4 === 0) {
            $months = (int)($weeks / 4);
            return $months . ' month' . ($months === 1 ? '' : 's');
        }

        return $weeks . ' week' . ($weeks === 1 ? '' : 's');
    }
}
