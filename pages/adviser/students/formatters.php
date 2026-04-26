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

if (!function_exists('adviser_students_normalize_requirement_name')) {
    function adviser_students_normalize_requirement_name(?string $name): string
    {
        return strtolower(trim((string)($name ?? '')));
    }
}

if (!function_exists('adviser_students_requirement_key')) {
    function adviser_students_requirement_key(?string $name): string
    {
        $normalized = adviser_students_normalize_requirement_name($name);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
        return trim((string)$normalized, '-');
    }
}

if (!function_exists('adviser_students_local_ojt_requirements')) {
    function adviser_students_local_ojt_requirements(): array
    {
        return [
            ['name' => 'Internship Training Agreement', 'phase' => 'Pre-OJT'],
            ['name' => "Notarized Parent/Guardian's Consent", 'phase' => 'Pre-OJT'],
            ['name' => 'Personal History Statement/Resume', 'phase' => 'Pre-OJT'],
            ['name' => 'Photocopy of Enrollment/Registration Form', 'phase' => 'Pre-OJT'],
            ['name' => 'Photocopy of Insurance Certificate', 'phase' => 'Pre-OJT'],
            ['name' => 'Medical Certificate', 'phase' => 'Pre-OJT'],
            ['name' => 'Received Copy of Endorsement Letter', 'phase' => 'Pre-OJT'],
            ['name' => 'Copy of Acceptance Letter from Training Establishment', 'phase' => 'Pre-OJT'],
            ['name' => 'OJT Time Frame', 'phase' => 'Pre-OJT'],
            ['name' => 'Copy of Internship Plan', 'phase' => 'Pre-OJT'],
        ];
    }
}

if (!function_exists('adviser_students_local_ojt_requirement_names')) {
    function adviser_students_local_ojt_requirement_names(): array
    {
        $names = [];
        foreach (adviser_students_local_ojt_requirements() as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
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
            'linear-gradient(135deg,#12b3ac 0%,#12b3ac 100%)',
            'linear-gradient(135deg,#f97316 0%,#f43f5e 100%)',
            'linear-gradient(135deg,#14b8a6 0%,#22c55e 100%)',
            'linear-gradient(135deg,#12b3ac 0%,#12b3ac 100%)',
            'linear-gradient(135deg,#12b3ac 0%,#12b3ac 100%)',
            'linear-gradient(135deg,#12b3ac 0%,#2a8b8d 100%)',
            'linear-gradient(135deg,#0ea5e9 0%,#12b3ac 100%)',
            'linear-gradient(135deg,#eab308 0%,#22c55e 100%)',
        ];

        $index = abs((int)$seed) % count($gradients);
        return $gradients[$index];
    }
}

if (!function_exists('adviser_students_moa_label')) {
    function adviser_students_moa_label(?string $moaStatus, ?string $companyName = null, ?string $applicationStatus = null): string
    {
        $company = trim((string)($companyName ?? ''));
        $raw = trim((string)($moaStatus ?? ''));
        $normalized = strtolower($raw);
        $applicationRaw = trim((string)($applicationStatus ?? ''));
        $applicationNormalized = strtolower($applicationRaw);

        if (
            ($applicationNormalized !== '' && strpos($applicationNormalized, 'accepted') !== false)
            || in_array($applicationNormalized, ['approved', 'hired'], true)
        ) {
            return 'MOA Signed';
        }

        if ($company === '') {
            return 'No company assigned';
        }

        if ($normalized === '' || in_array($normalized, ['not started', 'pending'], true)) {
            return 'MOA Not Started';
        }

        if (in_array($normalized, ['signed', 'moa signed', 'completed', 'complete', 'approved'], true)) {
            return 'MOA Signed';
        }

        if (in_array($normalized, ['in progress', 'processing', 'for signing'], true)) {
            return 'MOA In Progress';
        }

        if (preg_match('/^moa\s+/i', $raw) === 1) {
            return $raw;
        }

        return 'MOA ' . ucwords($raw);
    }
}
