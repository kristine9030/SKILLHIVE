<?php
ob_start();
session_start();

$role = $_SESSION['role'] ?? null;
if (!$role) {
    header("Location: /SkillHive/pages/auth/login.php");
    exit;
}

$baseUrl = '/SkillHive';
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$page = $_GET['page'] ?? null;

$defaultPage = [
    'student'  => 'student/dashboard',
    'employer' => 'employer/dashboard',
    'adviser'  => 'adviser/dashboard',
    'admin'    => 'admin/dashboard',
];

$allowedPages = [
    'student' => [
        'student/dashboard',
        'student/profile',
        'student/matching',
        'student/marketplace',
        'student/resume-ai',
        'student/applications',
        'student/ojt-log',
        'student/analytics',
        'student/settings',
    ],
    'employer' => [
        'employer/dashboard',
        'employer/post_internship',
        'employer/post-internship',
        'employer/candidates',
        'employer/evaluation',
    ],
    'adviser' => [
        'adviser/dashboard',
        'adviser/endorsement',
        'adviser/monitoring',
        'adviser/analytics',
        'adviser/companies',
        'adviser/evaluation',
        'adviser/settings',
        'adviser/students',
    ],
    'admin' => [
        'admin/dashboard',
        'admin/universities',
        'admin/verify-companies',
        'admin/users',
        'admin/reports',
        'admin/audit-logs',
        'admin/settings',
    ],
];

if ($page && isset($allowedPages[$role]) && in_array($page, $allowedPages[$role])) {
    $currentPage = $page;
} else {
    $currentPage = $defaultPage[$role] ?? 'student/dashboard';
}

$currentPage = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $currentPage);

$fullPath = __DIR__ . "/pages/{$currentPage}.php";
if (!file_exists($fullPath)) {
    $altPage = str_replace('-', '_', $currentPage);
    $altPath = __DIR__ . "/pages/{$altPage}.php";
    if (file_exists($altPath)) {
        $fullPath = $altPath;
        $currentPage = $altPage;
    }
}
if (!file_exists($fullPath)) {
    $altPage = str_replace('_', '-', $currentPage);
    $altPath = __DIR__ . "/pages/{$altPage}.php";
    if (file_exists($altPath)) {
        $fullPath = $altPath;
        $currentPage = $altPage;
    }
}
if (!file_exists($fullPath)) {
    $indexPath = __DIR__ . "/pages/{$currentPage}/index.php";
    if (file_exists($indexPath)) {
        $fullPath = $indexPath;
    }
}
if (!file_exists($fullPath)) {
    $fullPath = null;
}

$nameParts = explode(' ', $userName);
$initials = '';
foreach ($nameParts as $part) {
    if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$pageTitle = ucwords(str_replace(['/', '_', '-'], [' — ', ' ', ' '], $currentPage));
$pageTitle = preg_replace('/^(Student|Employer|Adviser|Admin)\s*—?\s*/i', '', $pageTitle);
if (!$pageTitle) $pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SkillHive — <?php echo htmlspecialchars($pageTitle); ?></title>
  <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/skillhive.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="role-<?php echo htmlspecialchars((string) $role); ?>">

<div class="app-shell">
  <?php include __DIR__ . '/components/sidebar.php'; ?>

  <div class="main-area">
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <div class="page-content">
      <?php if (isset($_SESSION['status'])): ?>
        <div class="toast toast-success" id="flashToast">
          <i class="fas fa-check-circle"></i>
          <?php echo htmlspecialchars($_SESSION['status']); ?>
        </div>
        <?php unset($_SESSION['status']); ?>
      <?php endif; ?>

      <?php
      if ($fullPath) {
          include $fullPath;
      } else {
          echo '<div style="padding:60px;text-align:center;">';
          echo '<i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#ccc;margin-bottom:16px;display:block"></i>';
          echo '<h2 style="color:#111;font-weight:700;margin-bottom:8px">Page Not Found</h2>';
          echo '<p style="color:#999;margin-bottom:20px">The page <strong>' . htmlspecialchars($currentPage) . '</strong> doesn\'t exist yet.</p>';
          echo '<a href="' . $baseUrl . '/layout.php" class="btn btn-primary">Back to Dashboard</a>';
          echo '</div>';
      }
      ?>
    </div>
  </div>
</div>

<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileSidebar()"></div>

<script>
function toggleSidebar() {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.getElementById('mobileOverlay');
    var isMobile = window.matchMedia('(max-width: 768px)').matches;

    if (!sidebar || !overlay) {
        return;
    }

    if (isMobile) {
        sidebar.classList.remove('collapsed');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active', sidebar.classList.contains('open'));
        return;
    }

    sidebar.classList.toggle('collapsed');
}

function closeMobileSidebar() {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.getElementById('mobileOverlay');

    if (!sidebar || !overlay) {
        return;
    }

    sidebar.classList.remove('open');
    overlay.classList.remove('active');
}

function toggleTopbarProfile(event) {
    if (event) {
        event.stopPropagation();
    }
    var menu = document.getElementById('topbarProfileMenu');
    var toggle = document.getElementById('topbarProfileToggle');
    if (!menu || !toggle) return;

    var isOpen = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function closeTopbarProfile() {
    var menu = document.getElementById('topbarProfileMenu');
    var toggle = document.getElementById('topbarProfileToggle');
    if (menu && toggle) {
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    }
}

document.addEventListener('click', function (event) {
    var profileWrap = document.getElementById('topbarProfileWrap');
    if (profileWrap && !profileWrap.contains(event.target)) {
        closeTopbarProfile();
    }
});

document.addEventListener('click', function (event) {
    var item = event.target.closest('.topbar-dropdown-item');
    if (item) {
        closeTopbarProfile();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeTopbarProfile();
    }
});

window.addEventListener('resize', function () {
    if (!window.matchMedia('(max-width: 768px)').matches) {
        closeMobileSidebar();
    }
    closeTopbarProfile();
});

// Auto-dismiss flash toast
var toast = document.getElementById('flashToast');
if (toast) {
  setTimeout(function() { toast.style.opacity = '0'; toast.style.transform = 'translateY(-10px)'; }, 3000);
  setTimeout(function() { toast.remove(); }, 3500);
}
</script>
</body>
</html>
