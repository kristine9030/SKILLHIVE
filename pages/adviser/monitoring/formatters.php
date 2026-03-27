<?php
/**
 * Purpose: Shared formatting/status helpers for adviser monitoring page.
 * Tables/columns used: No database access.
 */

if (!function_exists('adviser_monitoring_escape')) {
    function adviser_monitoring_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_monitoring_initials')) {
    function adviser_monitoring_initials(?string $firstName, ?string $lastName): string
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

if (!function_exists('adviser_monitoring_progress_percent')) {
    function adviser_monitoring_progress_percent($hoursCompleted, $hoursRequired): int
    {
        $completed = (float)$hoursCompleted;
        $required = (float)$hoursRequired;

        if ($required <= 0) {
            return 0;
        }

        return max(0, min(100, (int)round(($completed / $required) * 100)));
    }
}

if (!function_exists('adviser_monitoring_status_badge')) {
    function adviser_monitoring_status_badge(?string $completionStatus, int $progressPercent, ?string $latestLogDate = null): array
    {
        $status = strtolower(trim((string)($completionStatus ?? '')));
        $daysSinceLog = null;

        if ($latestLogDate) {
            $timestamp = strtotime($latestLogDate);
            if ($timestamp !== false) {
                $daysSinceLog = (int)floor((time() - $timestamp) / 86400);
            }
        }

        if ($status === 'completed') {
            return ['label' => 'On Track', 'class' => 'monitoring-status-ontrack'];
        }

        if ($progressPercent <= 15 || $daysSinceLog === null || $daysSinceLog > 21) {
            return ['label' => 'At Risk', 'class' => 'monitoring-status-risk'];
        }

        if ($progressPercent < 40 || $daysSinceLog > 10) {
            return ['label' => 'Warning', 'class' => 'monitoring-status-warning'];
        }

        return ['label' => 'On Track', 'class' => 'monitoring-status-ontrack'];
    }
}

if (!function_exists('adviser_monitoring_progress_gradient')) {
    function adviser_monitoring_progress_gradient(string $statusLabel): string
    {
        if ($statusLabel === 'On Track') {
            return 'linear-gradient(90deg,#06B6D4,#10B981)';
        }

        if ($statusLabel === 'Warning') {
            return 'linear-gradient(90deg,#F59E0B,#10B981)';
        }

        return 'linear-gradient(90deg,#EF4444,#F59E0B)';
    }
}

if (!function_exists('adviser_monitoring_format_log_date')) {
    function adviser_monitoring_format_log_date(?string $logDate): string
    {
        if (!$logDate) {
            return 'No date';
        }

        $timestamp = strtotime($logDate);
        if ($timestamp === false) {
            return 'No date';
        }

        return date('M j, Y', $timestamp);
    }
}
