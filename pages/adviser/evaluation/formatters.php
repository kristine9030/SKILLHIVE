<?php
/**
 * Purpose: Shared helpers for adviser evaluation page.
 * Tables/columns used: No database access.
 */

if (!function_exists('adviser_evaluation_escape')) {
    function adviser_evaluation_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_evaluation_initials')) {
    function adviser_evaluation_initials(?string $firstName, ?string $lastName): string
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

if (!function_exists('adviser_evaluation_row_status')) {
    function adviser_evaluation_row_status(?string $completionStatus, bool $hasAdviserEval): array
    {
        $isCompleted = strtolower(trim((string)($completionStatus ?? ''))) === 'completed';
        if (!$isCompleted) {
            return ['label' => 'In Progress', 'class' => 'badge-pending'];
        }

        if ($hasAdviserEval) {
            return ['label' => 'Graded', 'class' => 'badge-active'];
        }

        return ['label' => 'Needs Grading', 'class' => 'badge-pending'];
    }
}

if (!function_exists('adviser_evaluation_grade_options')) {
    function adviser_evaluation_grade_options(): array
    {
        return ['1.00', '1.25', '1.50', '1.75', '2.00', '2.25', '2.50', '2.75', '3.00', '5.00'];
    }
}

if (!function_exists('adviser_evaluation_year_level_label')) {
    function adviser_evaluation_year_level_label($value): string
    {
        $year = (int)$value;

        if ($year <= 0) {
            return 'Year N/A';
        }

        if ($year === 1) {
            return '1st Year';
        }

        if ($year === 2) {
            return '2nd Year';
        }

        if ($year === 3) {
            return '3rd Year';
        }

        return $year . 'th Year';
    }
}

if (!function_exists('adviser_evaluation_avatar_gradient')) {
    function adviser_evaluation_avatar_gradient(int $index): string
    {
        $palette = [
            'linear-gradient(135deg,#4f46e5 0%,#3b82f6 100%)',
            'linear-gradient(135deg,#f97316 0%,#fb7185 100%)',
            'linear-gradient(135deg,#14b8a6 0%,#10b981 100%)',
            'linear-gradient(135deg,#7c3aed 0%,#9333ea 100%)',
            'linear-gradient(135deg,#ef4444 0%,#f97316 100%)',
        ];

        return $palette[$index % count($palette)];
    }
}

if (!function_exists('adviser_evaluation_grade_label')) {
    function adviser_evaluation_grade_label(string $grade): string
    {
        $normalized = trim($grade);
        $map = [
            '1.00' => '1.00 - Excellent',
            '1.25' => '1.25 - Outstanding',
            '1.50' => '1.50 - Very Good',
            '1.75' => '1.75 - Very Good',
            '2.00' => '2.00 - Good',
            '2.25' => '2.25 - Satisfactory',
            '2.50' => '2.50 - Fair',
            '2.75' => '2.75 - Needs Improvement',
            '3.00' => '3.00 - Passing',
            '5.00' => '5.00 - Failed',
        ];

        return $map[$normalized] ?? $normalized;
    }
}
