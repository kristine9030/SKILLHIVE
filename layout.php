<?php

session_start();

// Check if user is logged in
$role = $_SESSION['role'] ?? null;
if (!$role) {
    header("Location: /Skillhive/pages/auth/login.php");
    exit;
}

// Base URL helper
$baseUrl = '/Skillhive';

// User info from session
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

// Get requested page
$page = $_GET['page'] ?? null;

// Role-based default dashboard
$defaultPage = [
    'student'  => 'student/dashboard',
    'employer' => 'employer/dashboard',
    'adviser'  => 'adviser/dashboard',
    'admin'    => 'admin/dashboard',
];

// Allowed pages per role
$allowedPages = [
    'student' => [
        'student/dashboard',
        'student/profile',
        'student/marketplace',
        'student/applications',
        'student/ojt-log',
        'student/analytics',
    ],
    'employer' => [
        'employer/dashboard',
        'employer/post_internship',
        'employer/post-internship',
        'employer/candidates',
        'employer/evaluation',
        'employer/analytics',
    ],
    'adviser' => [
        'adviser/dashboard',
        'adviser/monitoring',
        'adviser/endorsement',
        'adviser/grading',
        'adviser/analytics',
    ],
    'admin' => [
        'admin/dashboard',
        'admin/verify-companies',
        'admin/users',
        'admin/settings',
    ],
];

// Determine page to load
if ($page && isset($allowedPages[$role]) && in_array($page, $allowedPages[$role])) {
    $currentPage = $page;
} else {
    $currentPage = $defaultPage[$role] ?? 'student/dashboard';
}

// Sanitize path — allow letters, numbers, underscores, hyphens, slashes
$currentPage = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $currentPage);

// Try multiple file name patterns (hyphen vs underscore)
$fullPath = __DIR__ . "/pages/{$currentPage}.php";
if (!file_exists($fullPath)) {
    // Try converting hyphens to underscores
    $altPage = str_replace('-', '_', $currentPage);
    $altPath = __DIR__ . "/pages/{$altPage}.php";
    if (file_exists($altPath)) {
        $fullPath = $altPath;
        $currentPage = $altPage;
    }
}
// Try converting underscores to hyphens
if (!file_exists($fullPath)) {
    $altPage = str_replace('_', '-', $currentPage);
    $altPath = __DIR__ . "/pages/{$altPage}.php";
    if (file_exists($altPath)) {
        $fullPath = $altPath;
        $currentPage = $altPage;
    }
}

// Final fallback if file doesn't exist
if (!file_exists($fullPath)) {
    $fullPath = __DIR__ . "/pages/404.php";
    if (!file_exists($fullPath)) {
        $fullPath = null; // Will show inline 404
    }
}

// Get user initials for avatar
$nameParts = explode(' ', $userName);
$initials = '';
foreach ($nameParts as $part) {
    if ($part !== '') {
        $initials .= strtoupper(substr($part, 0, 1));
    }
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SkillHive — <?php echo htmlspecialchars(ucwords(str_replace(['/', '_', '-'], [' — ', ' ', ' '], $currentPage))); ?></title>
  <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/skillhive.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    /* Mobile overlay for sidebar */
    .overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      z-index: 49;
    }
    .overlay.active {
      display: block;
    }
    /* Hamburger button */
    .hamburger {
      display: none;
      flex-direction: column;
      gap: 4px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 6px;
    }
    .hamburger span {
      display: block;
      width: 22px;
      height: 2.5px;
      background: var(--dark, #1a1a1a);
      border-radius: 2px;
      transition: all 0.2s;
    }
    @media (max-width: 768px) {
      .hamburger { display: flex; }
      .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
      .sidebar.open { transform: translateX(0); z-index: 50; }
    }
    /* Status flash message */
    .flash-status {
      max-width: 980px;
      margin: 12px auto;
      padding: 12px 16px;
      background: rgba(34,139,34,0.08);
      border: 1px solid rgba(34,139,34,0.2);
      color: #228b22;
      border-radius: 8px;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/components/navbar.php'; ?>

<div class="shell">
  <div class="overlay" id="overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('active');"></div>
  <?php include __DIR__ . '/components/sidebar.php'; ?>

  <div class="content">
    <?php
    // Show flash status if set
    if (isset($_SESSION['status'])):
    ?>
      <div class="flash-status">
        <i class="fa-solid fa-circle-check"></i>
        <?php echo htmlspecialchars($_SESSION['status']); ?>
      </div>
    <?php
      unset($_SESSION['status']);
    endif;
    ?>

    <?php
    if ($fullPath) {
        include $fullPath;
    } else {
        echo '<div style="padding:40px;text-align:center;">';
        echo '<h2 style="color:var(--red,#8b0000);">404 — Page Not Found</h2>';
        echo '<p style="margin-top:10px;color:#888;">The page <strong>' . htmlspecialchars($currentPage) . '</strong> does not exist.</p>';
        echo '<a href="' . $baseUrl . '/layout.php" class="btn btn-red" style="margin-top:16px;display:inline-block;">Back to Dashboard</a>';
        echo '</div>';
    }
    ?>
  </div>
</div>

<!-- Global JS -->
<?php if (file_exists(__DIR__ . '/assets/js/utils.js')): ?>
  <script src="<?php echo $baseUrl; ?>/assets/js/utils.js"></script>
<?php endif; ?>
<?php if (file_exists(__DIR__ . '/assets/js/components.js')): ?>
  <script src="<?php echo $baseUrl; ?>/assets/js/components.js"></script>
<?php endif; ?>

<?php
// Page-specific JS
$jsFile = basename($currentPage) . '.js';
$jsPath = __DIR__ . "/assets/js/{$jsFile}";
if (file_exists($jsPath)): ?>
  <script src="<?php echo $baseUrl; ?>/assets/js/<?php echo $jsFile; ?>"></script>
<?php endif; ?>

</body>
</html>