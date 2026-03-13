<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/marketplace_helpers.php';
require_once __DIR__ . '/marketplace_db.php';
require_once __DIR__ . '/marketplace_apply.php';
require_once __DIR__ . '/marketplace_view.php';

marketplace_ensure_application_consent_columns($pdo);

$currentFilters  = marketplace_filters_from_request();
$selectedDetailId = (int) $currentFilters['detail'];
$openApplyModal  = (int) $currentFilters['open_apply'] === 1;
$consentVersion  = 'v1.0';
$applicationSuccessModal = $_SESSION['application_success_modal'] ?? null;
unset($_SESSION['application_success_modal']);

if (is_array($applicationSuccessModal)) {
    $openApplyModal              = false;
    $currentFilters['open_apply'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'apply_internship') {
    marketplace_handle_apply($pdo, $baseUrl, (int) $userId, $currentFilters, $openApplyModal);
}

$data = marketplace_load_data($pdo, (int) $userId, $currentFilters, $selectedDetailId);

$data['currentFilters']          = $currentFilters;
$data['selectedDetailId']        = $selectedDetailId;
$data['openApplyModal']          = $openApplyModal;
$data['consentVersion']          = $consentVersion;
$data['applicationSuccessModal'] = $applicationSuccessModal;
$data['baseUrl']                 = $baseUrl;
$data['userId']                  = (int) $userId;

marketplace_render($data);
