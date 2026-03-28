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
    if ($percentage >= 90) return 'linear-gradient(90deg, #10B981, #10B981)';
    if ($percentage >= 75) return 'linear-gradient(90deg, #06B6D4, #10B981)';
    if ($percentage >= 50) return 'linear-gradient(90deg, #F59E0B, #06B6D4)';
    return 'linear-gradient(90deg, #EF4444, #F59E0B)';
}

function adviser_analytics_get_trend_style($type) {
    $styles = [
        'positive' => ['bg' => '#f0fdf4', 'color' => '#10B981'],
        'warning' => ['bg' => '#fef3c7', 'color' => '#F59E0B'],
        'neutral' => ['bg' => '#f0f9ff', 'color' => '#06B6D4'],
        'danger' => ['bg' => '#fef2f2', 'color' => '#EF4444']
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
        'linear-gradient(135deg,#4f46e5 0%,#14b8a6 100%)',
        'linear-gradient(135deg,#2563eb 0%,#0ea5e9 100%)',
        'linear-gradient(135deg,#16a34a 0%,#22c55e 100%)',
        'linear-gradient(135deg,#7c3aed 0%,#9333ea 100%)',
        'linear-gradient(135deg,#ef4444 0%,#f97316 100%)',
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
