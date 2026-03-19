<?php
/**
 * Purpose: Shared formatting helpers for adviser students page.
 * Tables/columns used: No database access.
 */

if (!function_exists('adviser_students_escape')) {
    function adviser_students_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_students_initials')) {
    function adviser_students_initials(?string $firstName, ?string $lastName): string
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

if (!function_exists('adviser_students_progress_percent')) {
    function adviser_students_progress_percent($hoursCompleted, $hoursRequired): int
    {
        $completed = (float)$hoursCompleted;
        $required = (float)$hoursRequired;

        if ($required <= 0) {
            return 0;
        }

        return max(0, min(100, (int)round(($completed / $required) * 100)));
    }
}

if (!function_exists('adviser_students_status_label')) {
    function adviser_students_status_label(?string $completionStatus, ?string $availabilityStatus = null): string
    {
        $status = strtolower(trim((string)($completionStatus ?? '')));
        if ($status === 'completed') {
            return 'Completed';
        }
        if ($status === 'ongoing') {
            return 'Ongoing';
        }
        if ($status === 'dropped') {
            return 'Dropped';
        }

        $availability = strtolower(trim((string)($availabilityStatus ?? '')));
        if ($availability === 'currently interning') {
            return 'Currently Interning';
        }
        if ($availability === 'unavailable') {
            return 'Unavailable';
        }
        if ($availability === 'available') {
            return 'Available';
        }

        return 'No OJT';
    }
}

if (!function_exists('adviser_students_status_class')) {
    function adviser_students_status_class(string $label): string
    {
        if ($label === 'Completed' || $label === 'Ongoing' || $label === 'Currently Interning') {
            return 'status-accepted';
        }
        if ($label === 'Available') {
            return 'status-shortlisted';
        }
        return 'status-pending';
    }
}

if (!function_exists('adviser_students_requirements_summary')) {
    function adviser_students_requirements_summary(int $submitted, int $total): array
    {
        $submitted = max(0, min($total, $submitted));
        $pending = max(0, $total - $submitted);
        $completion = $total > 0 ? (int)round(($submitted / $total) * 100) : 0;

        return [
            'submitted' => $submitted,
            'pending' => $pending,
            'completion' => $completion,
        ];
    }
}
