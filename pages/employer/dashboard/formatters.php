<?php
/**
 * Purpose: Shared presentation helpers for employer dashboard-related pages.
 * Tables/columns used: No database access. These helpers only format status, names, durations, and timestamps for rendered values.
 */

if (!function_exists('dashboard_escape')) {
    function dashboard_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashboard_initials')) {
    function dashboard_initials(?string $firstName, ?string $lastName): string
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

if (!function_exists('dashboard_status_label')) {
    function dashboard_status_label(?string $status): string
    {
        $raw = trim((string)($status ?? ''));
        if ($raw === '') {
            return 'N/A';
        }

        $clean = str_replace(['_', '-'], ' ', strtolower($raw));
        return ucwords($clean);
    }
}

if (!function_exists('dashboard_status_class')) {
    function dashboard_status_class(?string $status): string
    {
        $normalized = strtolower(trim((string)($status ?? '')));

        $shortlistedStatuses = ['shortlisted', 'reviewed'];
        $interviewStatuses = ['interview', 'interviewing', 'for interview', 'scheduled'];
        $acceptedStatuses = ['accepted', 'hired', 'open', 'verified', 'approved'];
        $rejectedStatuses = ['rejected', 'declined', 'closed', 'cancelled', 'canceled'];

        if (in_array($normalized, $shortlistedStatuses, true)) {
            return 'status-shortlisted';
        }

        if (in_array($normalized, $interviewStatuses, true)) {
            return 'status-interview';
        }

        if (in_array($normalized, $acceptedStatuses, true)) {
            return 'status-accepted';
        }

        if (in_array($normalized, $rejectedStatuses, true)) {
            return 'status-rejected';
        }

        return 'status-pending';
    }
}

if (!function_exists('dashboard_time_ago')) {
    function dashboard_time_ago(?string $datetime): string
    {
        if (!$datetime) {
            return 'Posted recently';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'Posted recently';
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Posted just now';
        }

        $minutes = (int)floor($diff / 60);
        if ($minutes < 60) {
            return 'Posted ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
        }

        $hours = (int)floor($minutes / 60);
        if ($hours < 24) {
            return 'Posted ' . $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
        }

        $days = (int)floor($hours / 24);
        if ($days < 30) {
            return 'Posted ' . $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }

        $months = (int)floor($days / 30);
        if ($months < 12) {
            return 'Posted ' . $months . ' month' . ($months === 1 ? '' : 's') . ' ago';
        }

        $years = (int)floor($months / 12);
        return 'Posted ' . $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
    }
}

if (!function_exists('dashboard_duration_label')) {
    function dashboard_duration_label(?int $durationWeeks): string
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

if (!function_exists('dashboard_format_interview_datetime')) {
    function dashboard_format_interview_datetime(?string $datetime): string
    {
        if (!$datetime) {
            return 'Schedule to be announced';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'Schedule to be announced';
        }

        return date('M j, g:i A', $timestamp);
    }
}
