<?php
// formatters.php — Escaping and formatting helpers for analytics

function adviser_analytics_escape($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
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
