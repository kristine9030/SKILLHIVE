<?php
/**
 * Purpose: Encodes/decodes legacy evaluation metadata and derives recommendation text.
 * Tables/columns used: No direct database access. Defines the storage format used in employer_evaluation.comments.
 */

if (!function_exists('parseEvaluationCommentPayload')) {
    function parseEvaluationCommentPayload(?string $comment): array
    {
        $raw = (string)($comment ?? '');
        $comm = null;
        $ethic = null;
        $clean = $raw;

        if (preg_match('/^\[COMM:([0-9]+(?:\.[0-9]+)?)\]\[ETHIC:([0-9]+(?:\.[0-9]+)?)\]\s*(.*)$/s', $raw, $matches)) {
            $comm = (float)$matches[1];
            $ethic = (float)$matches[2];
            $clean = trim((string)$matches[3]);
        }

        return [
            'communication' => $comm,
            'ethic'         => $ethic,
            'clean_comment' => $clean,
        ];
    }
}

if (!function_exists('composeEvaluationCommentPayload')) {
    function composeEvaluationCommentPayload(float $communication, float $ethic, string $comment): string
    {
        $prefix  = '[COMM:' . number_format($communication, 1, '.', '') . '][ETHIC:' . number_format($ethic, 1, '.', '') . ']';
        $trimmed = trim($comment);
        return $trimmed !== '' ? $prefix . ' ' . $trimmed : $prefix;
    }
}

if (!function_exists('deriveEmployerEvaluationRecommendationStatus')) {
    function deriveEmployerEvaluationRecommendationStatus(float $technical, float $behavioral, ?string $storedStatus = null): string
    {
        $status = strtolower(trim((string)($storedStatus ?? '')));

        if (in_array($status, ['not recommended', 'not-recommended', 'not_recommended', 'do not recommend'], true)) {
            return 'Not Recommended';
        }

        if (in_array($status, ['recommended', 'recommend'], true)) {
            return 'Recommended';
        }

        $overall = round(($technical + $behavioral) / 2, 1);
        if (($behavioral > 0 && $behavioral < 3.0) || ($technical > 0 && $technical < 3.0) || ($overall > 0 && $overall < 3.0)) {
            return 'Not Recommended';
        }

        return 'Recommended';
    }
}

if (!function_exists('buildEmployerEvaluationConcernReasons')) {
    function buildEmployerEvaluationConcernReasons(float $technical, float $behavioral, ?string $storedStatus = null): array
    {
        $recommendation = deriveEmployerEvaluationRecommendationStatus($technical, $behavioral, $storedStatus);
        $overall = round(($technical + $behavioral) / 2, 1);
        $reasons = [];

        if (strtolower($recommendation) === 'not recommended') {
            $reasons[] = 'Employer marked Not Recommended';
        }
        if ($behavioral > 0 && $behavioral < 3.0) {
            $reasons[] = 'Low behavioral score (' . number_format($behavioral, 1) . '/5)';
        }
        if ($technical > 0 && $technical < 3.0) {
            $reasons[] = 'Low technical score (' . number_format($technical, 1) . '/5)';
        }
        if ($overall > 0 && $overall < 3.0 && empty($reasons)) {
            $reasons[] = 'Low overall evaluation (' . number_format($overall, 1) . '/5)';
        }

        return $reasons;
    }
}

if (!function_exists('buildEmployerEvaluationSummaryText')) {
    function buildEmployerEvaluationSummaryText(float $technical, float $behavioral, ?string $storedStatus = null): string
    {
        $reasons = buildEmployerEvaluationConcernReasons($technical, $behavioral, $storedStatus);
        if (!empty($reasons)) {
            return implode('; ', $reasons);
        }

        return 'Employer marked ' . deriveEmployerEvaluationRecommendationStatus($technical, $behavioral, $storedStatus);
    }
}
