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
