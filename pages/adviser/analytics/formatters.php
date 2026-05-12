<?php
// formatters.php — Escaping and formatting helpers for analytics

function adviser_analytics_escape($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function adviser_analytics_department_label($value) {
    $label = trim((string)$value);
    $map = [
        'BSCS' => 'BS Computer Science',
        'BSIT' => 'BS Information Technology',
        'BSSE' => 'BS Software Engineering',
        'BSDS' => 'BS Data Science',
    ];

    return $map[$label] ?? ($label !== '' ? $label : 'Unassigned');
}

function adviser_analytics_section_label($value) {
    $label = trim((string)$value);
    return $label !== '' ? $label : 'Unassigned';
}

function adviser_analytics_get_gradient($percentage) {
    if ($percentage >= 90) return 'linear-gradient(90deg, #12b3ac, #12b3ac)';
    if ($percentage >= 75) return 'linear-gradient(90deg, #12b3ac, #12b3ac)';
    if ($percentage >= 50) return 'linear-gradient(90deg, #12b3ac, #12b3ac)';
    return 'linear-gradient(90deg, #12b3ac, #12b3ac)';
}

function adviser_analytics_get_trend_style($type) {
    $styles = [
        'positive' => ['bg' => '#f0fdf4', 'color' => '#12b3ac'],
        'warning' => ['bg' => '#fef3c7', 'color' => '#12b3ac'],
        'neutral' => ['bg' => '#f0f9ff', 'color' => '#12b3ac'],
        'danger' => ['bg' => '#fef2f2', 'color' => '#12b3ac']
    ];
    return $styles[$type] ?? $styles['neutral'];
}

function adviser_analytics_company_initials($companyName) {
    $name = trim((string)$companyName);
    if ($name === '') {
        return 'CO';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'CO';
}

function adviser_analytics_company_gradient($seed) {
    $gradients = [
        'linear-gradient(135deg,#12b3ac 0%,#14b8a6 100%)',
        'linear-gradient(135deg,#12b3ac 0%,#0ea5e9 100%)',
        'linear-gradient(135deg,#16a34a 0%,#22c55e 100%)',
        'linear-gradient(135deg,#12b3ac 0%,#12b3ac 100%)',
        'linear-gradient(135deg,#12b3ac 0%,#f97316 100%)',
    ];

    $index = abs((int)$seed) % count($gradients);
    return $gradients[$index];
}

function adviser_analytics_company_partner_label(array $company) {
    $verification = strtolower(trim((string)($company['verification_status'] ?? '')));
    $badge = strtolower(trim((string)($company['company_badge_status'] ?? '')));

    if ($verification === 'approved' || ($badge !== '' && $badge !== 'none')) {
        return 'Verified Partner';
    }

    return 'Partner Company';
}

function adviser_analytics_company_activity_meta(array $company) {
    $verification = strtolower(trim((string)($company['verification_status'] ?? 'pending')));
    $openPostings = (int)($company['open_postings'] ?? 0);
    $openSlots = (int)($company['open_slots'] ?? 0);

    if (in_array($verification, ['rejected', 'flagged'], true)) {
        return [
            'label' => 'Inactive',
            'detail' => 'Not cleared for BSU intern acceptance.',
            'class' => 'danger',
        ];
    }

    if ($openPostings > 0 && in_array($verification, ['approved', 'verified'], true)) {
        $postingText = $openPostings === 1 ? '1 open posting' : $openPostings . ' open postings';
        $slotText = $openSlots === 1 ? '1 listed slot' : $openSlots . ' listed slots';
        return [
            'label' => 'Active',
            'detail' => $postingText . ', ' . $slotText,
            'class' => 'success',
        ];
    }

    if ($openPostings > 0) {
        return [
            'label' => 'Pending',
            'detail' => 'Has open postings but verification is pending.',
            'class' => 'warning',
        ];
    }

    if (in_array($verification, ['approved', 'verified'], true)) {
        return [
            'label' => 'Inactive',
            'detail' => 'Verified but no open internship postings.',
            'class' => 'warning',
        ];
    }

    return [
        'label' => 'Pending',
        'detail' => 'Awaiting company verification.',
        'class' => 'warning',
    ];
}

function adviser_analytics_format_report_date($dateValue) {
    $value = trim((string)$dateValue);
    if ($value === '') {
        return 'N/A';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'N/A';
    }

    return date('M j, Y', $timestamp);
}

function adviser_analytics_clean_evaluation_comment($comment) {
    $raw = trim((string)$comment);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^\[COMM:[0-9]+(?:\.[0-9]+)?\]\[ETHIC:[0-9]+(?:\.[0-9]+)?\]\s*(.*)$/s', $raw, $matches)) {
        return trim((string)($matches[1] ?? ''));
    }

    return $raw;
}

function adviser_analytics_score_text($score) {
    if ($score === null || $score === '' || !is_numeric($score)) {
        return 'N/A';
    }

    return number_format((float)$score, 1) . '/5';
}
