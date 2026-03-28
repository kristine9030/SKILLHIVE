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

if (!function_exists('adviser_students_year_level_label')) {
    function adviser_students_year_level_label($value): string
    {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return 'N/A';
        }

        if (preg_match('/^\d+$/', $raw) === 1) {
            $number = (int)$raw;
            $suffix = 'th';
            if (($number % 100 < 11 || $number % 100 > 13)) {
                if ($number % 10 === 1) {
                    $suffix = 'st';
                } elseif ($number % 10 === 2) {
                    $suffix = 'nd';
                } elseif ($number % 10 === 3) {
                    $suffix = 'rd';
                }
            }

            return $number . $suffix;
        }

        return $raw;
    }
}

if (!function_exists('adviser_students_avatar_gradient')) {
    function adviser_students_avatar_gradient($seed): string
    {
        $gradients = [
            'linear-gradient(135deg,#4f46e5 0%,#3b82f6 100%)',
            'linear-gradient(135deg,#f97316 0%,#f43f5e 100%)',
            'linear-gradient(135deg,#14b8a6 0%,#22c55e 100%)',
            'linear-gradient(135deg,#7c3aed 0%,#2563eb 100%)',
            'linear-gradient(135deg,#ef4444 0%,#f59e0b 100%)',
            'linear-gradient(135deg,#8b5cf6 0%,#6366f1 100%)',
            'linear-gradient(135deg,#0ea5e9 0%,#3b82f6 100%)',
            'linear-gradient(135deg,#eab308 0%,#22c55e 100%)',
        ];

        $index = abs((int)$seed) % count($gradients);
        return $gradients[$index];
    }
}
