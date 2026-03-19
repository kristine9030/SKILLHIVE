<?php
/**
 * Purpose: Shared formatting helpers for adviser dashboard rendering.
 * Tables/columns used: No database access.
 */

if (!function_exists('adviser_dashboard_escape')) {
    function adviser_dashboard_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_dashboard_initials')) {
    function adviser_dashboard_initials(?string $firstName, ?string $lastName): string
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

if (!function_exists('adviser_dashboard_progress_percent')) {
    function adviser_dashboard_progress_percent($hoursCompleted, $hoursRequired): int
    {
        $completed = (float)$hoursCompleted;
        $required = (float)$hoursRequired;

        if ($required <= 0) {
            return 0;
        }

        return max(0, min(100, (int)round(($completed / $required) * 100)));
    }
}

if (!function_exists('adviser_dashboard_activity_badge')) {
    function adviser_dashboard_activity_badge(?string $completionStatus, int $progressPercent): array
    {
        $status = strtolower(trim((string)($completionStatus ?? '')));
        if ($status === 'completed') {
            return ['label' => 'Completed', 'class' => 'status-accepted'];
        }

        if ($progressPercent >= 75) {
            return ['label' => 'On Track', 'class' => 'status-accepted'];
        }

        if ($progressPercent >= 35) {
            return ['label' => 'Progressing', 'class' => 'status-shortlisted'];
        }

        if ($progressPercent > 0) {
            return ['label' => 'Behind', 'class' => 'status-pending'];
        }

        return ['label' => 'Pending', 'class' => 'status-pending'];
    }
}

if (!function_exists('adviser_dashboard_bar_gradient')) {
    function adviser_dashboard_bar_gradient(int $index): string
    {
        $gradients = [
            'linear-gradient(90deg,#06B6D4,#10B981)',
            'linear-gradient(90deg,#F59E0B,#10B981)',
            'linear-gradient(90deg,#EF4444,#F59E0B)',
            'linear-gradient(90deg,#6F42C1,#06B6D4)',
        ];

        return $gradients[$index % count($gradients)];
    }
}