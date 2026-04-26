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
