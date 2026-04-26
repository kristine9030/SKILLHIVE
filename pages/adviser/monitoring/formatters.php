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
    function adviser_monitoring_status_badge(
        ?string $completionStatus,
        int $progressPercent,
        ?string $latestLogDate = null,
        ?string $ojtStartDate = null,
        ?string $ojtCreatedAt = null
    ): array
    {
        $status = strtolower(trim((string)($completionStatus ?? '')));
        $daysSinceLog = null;
        $daysSinceOjtStart = null;

        if ($latestLogDate) {
            $timestamp = strtotime($latestLogDate);
            if ($timestamp !== false) {
                $daysSinceLog = (int)floor((time() - $timestamp) / 86400);
            }
        }

        $ojtAnchorDate = trim((string)($ojtStartDate ?? ''));
        if ($ojtAnchorDate === '') {
            $ojtAnchorDate = trim((string)($ojtCreatedAt ?? ''));
        }

        if ($ojtAnchorDate !== '') {
            $ojtStartTimestamp = strtotime($ojtAnchorDate);
            if ($ojtStartTimestamp !== false) {
                $daysSinceOjtStart = (int)floor((time() - $ojtStartTimestamp) / 86400);
            }
        }

        if (in_array($status, ['completed', 'complete', 'done'], true) || $progressPercent >= 100) {
            return ['label' => 'Completed', 'class' => 'monitoring-status-completed'];
        }

        // New interns should start as On Track during an initial grace period.
        if ($daysSinceOjtStart !== null && $daysSinceOjtStart <= 14) {
            return ['label' => 'On Track', 'class' => 'monitoring-status-ontrack'];
        }

        // Missing logs should not be marked At Risk immediately.
        if ($daysSinceLog === null) {
            if ($daysSinceOjtStart === null) {
                return ['label' => 'On Track', 'class' => 'monitoring-status-ontrack'];
            }

            if ($daysSinceOjtStart <= 21) {
                return ['label' => 'Warning', 'class' => 'monitoring-status-warning'];
            }

            return ['label' => 'At Risk', 'class' => 'monitoring-status-risk'];
        }

        if ($daysSinceLog > 21) {
            return ['label' => 'At Risk', 'class' => 'monitoring-status-risk'];
        }

        if ($progressPercent <= 15) {
            if ($daysSinceOjtStart !== null && $daysSinceOjtStart <= 14) {
                return ['label' => 'On Track', 'class' => 'monitoring-status-ontrack'];
            }

            if ($daysSinceOjtStart !== null && $daysSinceOjtStart <= 21) {
                return ['label' => 'Warning', 'class' => 'monitoring-status-warning'];
            }

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
        if ($statusLabel === 'Completed') {
            return 'linear-gradient(90deg,#12b3ac,#12b3ac)';
        }

        if ($statusLabel === 'On Track') {
            return 'linear-gradient(90deg,#12b3ac,#12b3ac)';
        }

        if ($statusLabel === 'Warning') {
            return 'linear-gradient(90deg,#12b3ac,#12b3ac)';
        }

        return 'linear-gradient(90deg,#12b3ac,#12b3ac)';
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
