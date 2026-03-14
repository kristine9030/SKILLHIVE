<?php

function applications_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function applications_status_class(string $status): string
{
    return match ($status) {
        'Pending' => 'status-pending',
        'Shortlisted' => 'status-shortlisted',
        'Waitlisted' => 'status-waitlisted',
        'Interview Scheduled' => 'status-interview',
        'Accepted' => 'status-accepted',
        'Rejected' => 'status-rejected',
        default => 'status-pending',
    };
}

function applications_next_step(string $status): string
{
  return match ($status) {
    'Pending' => 'Wait for employer review and keep your profile updated.',
    'Shortlisted' => 'Prepare your resume highlights and monitor updates daily.',
    'Waitlisted' => 'Stay available while the employer checks slot availability.',
    'Interview Scheduled' => 'Check your schedule details and confirm attendance.',
    'Accepted' => 'Proceed with onboarding requirements and adviser coordination.',
    'Rejected' => 'Apply to similar roles and improve matching skills.',
    default => 'Monitor this application for the next update.',
  };
}

function applications_progress_step(string $status): int
{
  return match ($status) {
    'Pending' => 2,
    'Shortlisted' => 3,
    'Waitlisted' => 3,
    'Interview Scheduled' => 4,
    'Accepted', 'Rejected' => 5,
    default => 1,
  };
}

function applications_company_gradient(string $companyName): string
{
    $gradients = [
        'linear-gradient(135deg,#06B6D4,#10B981)',
        'linear-gradient(135deg,#F59E0B,#EF4444)',
        'linear-gradient(135deg,#10B981,#06B6D4)',
        'linear-gradient(135deg,#111827,#374151)',
        'linear-gradient(135deg,#4F46E5,#06B6D4)',
        'linear-gradient(135deg,#EC4899,#F59E0B)',
    ];
    return $gradients[abs(crc32(strtolower($companyName))) % count($gradients)];
}

function applications_redirect(string $baseUrl, string $statusFilter = ''): void
{
  $query = ['page' => 'student/applications'];
  if ($statusFilter !== '') {
    $query['status'] = $statusFilter;
  }
  header('Location: ' . $baseUrl . '/layout.php?' . http_build_query($query));
  exit;
}

function applications_valid_statuses(): array
{
  return ['Pending', 'Shortlisted', 'Waitlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
}
