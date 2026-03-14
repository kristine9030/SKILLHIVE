<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/applications_helpers.php';
require_once __DIR__ . '/applications_cancel.php';
require_once __DIR__ . '/applications_job.php';
require_once __DIR__ . '/applications_view.php';

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$validStatuses = applications_valid_statuses();
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) {
  $statusFilter = '';
}

applications_handle_cancel_action($pdo, (int) $userId, (string) $baseUrl);

$data = applications_load_page_data($pdo, (int) $userId, $statusFilter);
applications_render_view([
  'baseUrl' => (string) $baseUrl,
  'validStatuses' => $validStatuses,
  'statusFilter' => $statusFilter,
  'statusCounts' => $data['statusCounts'],
  'applications' => $data['applications'],
  'recentApplications' => $data['recentApplications'],
  'totalApplied' => $data['totalApplied'],
]);