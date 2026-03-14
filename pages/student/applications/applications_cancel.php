<?php

function applications_handle_cancel_action(PDO $pdo, int $userId, string $baseUrl): void
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (string) ($_POST['action'] ?? '') !== 'cancel_application') {
    return;
  }

  $applicationId = (int) ($_POST['application_id'] ?? 0);
  $statusFilter = trim((string) ($_POST['status_filter'] ?? ''));

  if ($applicationId <= 0) {
    $_SESSION['status'] = 'Invalid application selected.';
    applications_redirect($baseUrl, $statusFilter);
  }

  $stmt = $pdo->prepare('SELECT status FROM application WHERE application_id = ? AND student_id = ? LIMIT 1');
  $stmt->execute([$applicationId, $userId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $_SESSION['status'] = 'Application not found.';
    applications_redirect($baseUrl, $statusFilter);
  }

  $currentStatus = (string) ($row['status'] ?? '');
  $cancelableStatuses = ['Pending', 'Shortlisted', 'Waitlisted', 'Interview Scheduled'];
  if (!in_array($currentStatus, $cancelableStatuses, true)) {
    $_SESSION['status'] = 'This application can no longer be canceled.';
    applications_redirect($baseUrl, $statusFilter);
  }

  try {
    $stmt = $pdo->prepare('DELETE FROM application WHERE application_id = ? AND student_id = ? LIMIT 1');
    $stmt->execute([$applicationId, $userId]);

    $_SESSION['status'] = $stmt->rowCount() > 0
      ? 'Application canceled successfully.'
      : 'No application was canceled.';
  } catch (Throwable $e) {
    $_SESSION['status'] = 'Unable to cancel application right now. Please try again.';
  }

  applications_redirect($baseUrl, $statusFilter);
}
