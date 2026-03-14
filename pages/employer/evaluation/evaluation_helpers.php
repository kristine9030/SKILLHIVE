<?php
/**
 * Purpose: Encodes and decodes communication/work-ethic metadata embedded in evaluation comments.
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
