<?php

function marketplace_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function marketplace_status_badge(string $badgeStatus): string
{
    return match ($badgeStatus) {
        'Verified Partner' => '<span style="background:rgba(6,182,212,.1);color:#06B6D4;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700">Verified</span>',
        'Top Employer'     => '<span style="background:rgba(245,158,11,.12);color:#D97706;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700">Top Employer</span>',
        default            => '',
    };
}

function marketplace_work_setup_style(string $workSetup): string
{
    return match ($workSetup) {
        'On-site' => 'background:rgba(16,185,129,.1);color:#10B981;padding:2px 8px;border-radius:50px;font-size:.72rem',
        'Remote'  => 'background:rgba(245,158,11,.1);color:#F59E0B;padding:2px 8px;border-radius:50px;font-size:.72rem',
        default   => 'background:rgba(6,182,212,.1);color:#06B6D4;padding:2px 8px;border-radius:50px;font-size:.72rem',
    };
}

function marketplace_company_gradient(string $companyName): string
{
    $gradients = [
        'linear-gradient(135deg,#06B6D4,#10B981)',
        'linear-gradient(135deg,#F59E0B,#EF4444)',
        'linear-gradient(135deg,#10B981,#06B6D4)',
        'linear-gradient(135deg,#111827,#374151)',
        'linear-gradient(135deg,#4F46E5,#06B6D4)',
        'linear-gradient(135deg,#EC4899,#F59E0B)',
    ];
    $index = abs(crc32(strtolower($companyName))) % count($gradients);
    return $gradients[$index];
}

function marketplace_duration_label(int $weeks): string
{
    if ($weeks % 4 === 0) {
        $months = (int) ($weeks / 4);
        return $months . ' month' . ($months === 1 ? '' : 's');
    }

    return $weeks . ' week' . ($weeks === 1 ? '' : 's');
}

function marketplace_redirect_with_filters(string $baseUrl, array $filters): void
{
    $targetUrl = $baseUrl . '/layout.php?' . http_build_query(array_merge(['page' => 'student/marketplace'], $filters));

    if (!headers_sent()) {
        header('Location: ' . $targetUrl);
        exit;
    }

    $safeUrl = htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8');
    echo '<script>window.location.href=' . json_encode($targetUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
    exit;
}

function marketplace_redirect_to_applications(string $baseUrl): void
{
    $targetUrl = $baseUrl . '/layout.php?page=student/applications';

    if (!headers_sent()) {
        header('Location: ' . $targetUrl);
        exit;
    }

    $safeUrl = htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8');
    echo '<script>window.location.href=' . json_encode($targetUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
    exit;
}

function marketplace_filters_from_request(): array
{
    return [
        'q'          => trim((string) ($_REQUEST['q'] ?? '')),
        'industry'   => trim((string) ($_REQUEST['industry'] ?? '')),
        'location'   => trim((string) ($_REQUEST['location'] ?? '')),
        'work_setup' => trim((string) ($_REQUEST['work_setup'] ?? '')),
        'detail'     => (int) ($_REQUEST['detail'] ?? 0),
        'open_apply' => (int) ($_REQUEST['open_apply'] ?? 0),
    ];
}

function marketplace_detail_url(string $baseUrl, array $filters, int $detailId): string
{
    $query = [
        'page'       => 'student/marketplace',
        'q'          => $filters['q'],
        'industry'   => $filters['industry'],
        'location'   => $filters['location'],
        'work_setup' => $filters['work_setup'],
        'detail'     => $detailId,
    ];

    return $baseUrl . '/layout.php?' . http_build_query($query);
}
