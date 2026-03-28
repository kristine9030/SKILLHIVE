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

if (!function_exists('adviser_dashboard_pill_class')) {
    function adviser_dashboard_pill_class(?string $label): string
    {
        $normalized = strtolower(trim((string)($label ?? '')));

        if (in_array($normalized, ['at risk', 'danger', 'pending'], true)) {
            return 'is-danger';
        }

        if (in_array($normalized, ['warning', 'for review', 'reviewing'], true)) {
            return 'is-warning';
        }

        if (in_array($normalized, ['approved', 'endorsed', 'placed', 'completed', 'on track'], true)) {
            return 'is-success';
        }

        return 'is-neutral';
    }
}

if (!function_exists('adviser_dashboard_endorsement_meta')) {
    function adviser_dashboard_endorsement_meta(?string $status): array
    {
        $normalized = strtolower(trim((string)($status ?? '')));

        if (in_array($normalized, ['for review', 'reviewing', 'submitted'], true)) {
            return [
                'label' => 'For Review',
                'pill_class' => adviser_dashboard_pill_class('For Review'),
                'action_label' => 'Review',
            ];
        }

        if (in_array($normalized, ['approved', 'endorsed'], true)) {
            return [
                'label' => 'Approved',
                'pill_class' => adviser_dashboard_pill_class('Approved'),
                'action_label' => 'View',
            ];
        }

        return [
            'label' => 'Pending',
            'pill_class' => adviser_dashboard_pill_class('Pending'),
            'action_label' => 'Approve',
        ];
    }
}

if (!function_exists('adviser_dashboard_days_since')) {
    function adviser_dashboard_days_since(?string $date): ?int
    {
        $value = trim((string)($date ?? ''));
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return max(0, (int)floor((time() - $timestamp) / 86400));
    }
}

if (!function_exists('adviser_dashboard_days_label')) {
    function adviser_dashboard_days_label(int $days): string
    {
        if ($days < 7) {
            return $days . ' day' . ($days === 1 ? '' : 's');
        }

        $weeks = (int)floor($days / 7);
        return $weeks . ' week' . ($weeks === 1 ? '' : 's');
    }
}

if (!function_exists('adviser_dashboard_risk_summary')) {
    function adviser_dashboard_risk_summary(array $row): string
    {
        $completed = (float)($row['hours_completed'] ?? 0);
        $required = (float)($row['hours_required'] ?? 0);
        $remaining = max(0, $required - $completed);
        $daysSinceLog = adviser_dashboard_days_since((string)($row['latest_log_date'] ?? ''));

        if ($daysSinceLog === null) {
            if ($completed > 0) {
                return 'Only ' . number_format($completed, 0) . ' hours logged with no recent daily log on file.';
            }

            return 'No logs submitted yet and no OJT hours recorded so far.';
        }

        if ($daysSinceLog > 21) {
            return 'No logs submitted in the past ' . adviser_dashboard_days_label($daysSinceLog) . '.';
        }

        if ($remaining > 0) {
            return 'Only ' . number_format($completed, 0) . ' hours logged - ' . number_format($remaining, 0) . ' remaining.';
        }

        return 'Latest log was submitted ' . adviser_dashboard_days_label($daysSinceLog) . ' ago.';
    }
}
