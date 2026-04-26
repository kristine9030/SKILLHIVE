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
        'student/first-login',
        'student/profile',
        'student/messaging',
        'student/marketplace',
        'student/resume-ai',
        'student/applications',
        'student/ojt-log',
        'student/ojt-log/journal',
        'student/analytics',
        'student/settings',
    ],
    'employer' => [
        'employer/dashboard',
        'employer/profile',
        'employer/messaging',
        'employer/post_internship',
        'employer/post-internship',
        'employer/candidates',
        'employer/evaluation',
    ],
    'adviser' => [
        'adviser/dashboard',
        'adviser/profile',
        'adviser/messaging',
        'adviser/endorsement',
        'adviser/monitoring',
        'adviser/journal_analytics',
        'adviser/analytics',
        'adviser/companies',
        'adviser/evaluation',
        'adviser/settings',
        'adviser/students',
    ],
    'admin' => [
        'admin/dashboard',
        'admin/messaging',
        'admin/universities',
        'admin/verify-companies',
        'admin/users',
        'admin/reports',
        'admin/audit-logs',
        'admin/settings',
    ],
];

if ($role === 'employer') {
    require_once __DIR__ . '/backend/db_connect.php';

    $resolvedEmployerId = (int)($_SESSION['employer_id'] ?? 0);
    if ($resolvedEmployerId <= 0) {
        $resolvedEmployerId = (int)$userId;
    }

    $verificationStatus = trim((string)($_SESSION['verification_status'] ?? ''));

    if ($resolvedEmployerId > 0) {
        try {
            $stmt = $pdo->prepare(
                'SELECT employer_id, company_name, verification_status
                 FROM employer
                 WHERE employer_id = :employer_id
                 LIMIT 1'
            );
            $stmt->execute([':employer_id' => $resolvedEmployerId]);
            $employerRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$employerRow && $userEmail !== '') {
                $stmt = $pdo->prepare(
                    'SELECT employer_id, company_name, verification_status
                     FROM employer
                     WHERE email = :email
                     LIMIT 1'
                );
                $stmt->execute([':email' => $userEmail]);
                $employerRow = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($employerRow) {
                $resolvedEmployerId = (int)($employerRow['employer_id'] ?? $resolvedEmployerId);
                $verificationStatus = trim((string)($employerRow['verification_status'] ?? $verificationStatus));

                $_SESSION['employer_id'] = $resolvedEmployerId;
                $_SESSION['verification_status'] = $verificationStatus;

                $companyName = trim((string)($employerRow['company_name'] ?? ''));
                if ($companyName !== '') {
                    $_SESSION['user_name'] = $companyName;
                    $userName = $companyName;
                }
            }
        } catch (Throwable $e) {
            // Non-fatal: route guard falls back to session values.
        }
    }

    $isEmployerApproved = strtolower($verificationStatus) === 'approved';
    if (!$isEmployerApproved) {
        $restrictedEmployerPages = [
            'employer/messaging',
            'employer/post_internship',
            'employer/post-internship',
            'employer/candidates',
            'employer/evaluation',
        ];

        if ($page && in_array($page, $restrictedEmployerPages, true)) {
            $_SESSION['status'] = 'Your employer account is pending admin verification. Employer tools are locked until approval.';
            header('Location: ' . $baseUrl . '/layout.php?page=employer/dashboard');
            exit;
        }
    }
}

$mustChangePassword = $role === 'student' && !empty($_SESSION['must_change_password']);
if ($mustChangePassword) {
    $defaultPage['student'] = 'student/first-login';
    if ($page !== 'student/first-login') {
        header('Location: ' . $baseUrl . '/layout.php?page=student/first-login');
        exit;
    }
} elseif ($role === 'student' && $page === 'student/first-login') {
    header('Location: ' . $baseUrl . '/layout.php?page=student/dashboard');
    exit;
}

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
var SIDEBAR_COLLAPSED_KEY = 'skillhiveSidebarCollapsed';

function applySidebarState() {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.getElementById('mobileOverlay');
    var isMobile = window.matchMedia('(max-width: 768px)').matches;

    if (!sidebar) {
        return;
    }

    if (isMobile) {
        sidebar.classList.remove('collapsed');
        if (overlay) {
            overlay.classList.toggle('active', sidebar.classList.contains('open'));
        }
        return;
    }

    sidebar.classList.remove('open');
    if (overlay) {
        overlay.classList.remove('active');
    }

    var savedCollapsed = window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
    sidebar.classList.toggle('collapsed', savedCollapsed);
}

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

    var isCollapsed = sidebar.classList.toggle('collapsed');
    window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, isCollapsed ? '1' : '0');
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
    closeTopbarNotifications();
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

function toggleTopbarNotifications(event) {
    if (event) {
        event.stopPropagation();
    }

    closeTopbarProfile();

    var menu = document.getElementById('topbarNotifMenu');
    var toggle = document.getElementById('topbarNotifToggle');
    if (!menu || !toggle) {
        return;
    }

    var isOpen = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

    if (isOpen) {
        loadTopbarNotifications();
    }
}

function closeTopbarNotifications() {
    var menu = document.getElementById('topbarNotifMenu');
    var toggle = document.getElementById('topbarNotifToggle');
    if (menu && toggle) {
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    }
}

function topbarNotificationsApi(action, options) {
    var requestOptions = options || {};
    var method = requestOptions.method || 'GET';
    var body = requestOptions.body || null;
    var apiUrl = '<?php echo $baseUrl; ?>/pages/common/notifications_api.php';
    var finalUrl = apiUrl + '?action=' + encodeURIComponent(action || 'list');

    return fetch(finalUrl, {
        method: method,
        body: body
    }).then(function (response) {
        return response.text().then(function (text) {
            var parsed = null;
            try {
                parsed = JSON.parse(text);
            } catch (error) {
                throw new Error('Invalid notifications response');
            }

            if (!response.ok || !parsed || !parsed.ok) {
                throw new Error(parsed && parsed.error ? parsed.error : 'Notifications request failed');
            }

            return parsed;
        });
    });
}

function updateTopbarNotifBadge(unreadCount) {
    var badge = document.getElementById('topbarNotifBadge');
    var toggle = document.getElementById('topbarNotifToggle');
    var unreadText = document.getElementById('topbarNotifUnreadText');
    var markAllButton = document.getElementById('topbarNotifMarkAll');
    if (!badge || !toggle) {
        return;
    }

    var count = parseInt(unreadCount || 0, 10);
    if (isNaN(count) || count <= 0) {
        badge.style.display = 'none';
        badge.textContent = '0';
        toggle.classList.remove('has-unread');
        toggle.setAttribute('aria-label', 'Notifications');
        if (unreadText) {
            unreadText.style.display = 'none';
            unreadText.textContent = '0 unread';
        }
        if (markAllButton) {
            markAllButton.disabled = true;
        }
        return;
    }

    badge.style.display = 'flex';
    badge.textContent = count > 99 ? '99+' : String(count);
    toggle.classList.add('has-unread');
    toggle.setAttribute('aria-label', 'Notifications (' + count + ' unread)');
    if (unreadText) {
        unreadText.style.display = 'inline-flex';
        unreadText.textContent = count + ' unread';
    }
    if (markAllButton) {
        markAllButton.disabled = false;
    }
}

function resolveNotificationUrl(targetUrl) {
    var raw = String(targetUrl || '').trim();
    if (raw === '') {
        return '<?php echo $baseUrl; ?>/layout.php';
    }

    if (/^https?:\/\//i.test(raw)) {
        return raw;
    }

    if (raw.charAt(0) === '/') {
        return '<?php echo $baseUrl; ?>' + raw;
    }

    return '<?php echo $baseUrl; ?>/' + raw;
}

function escapeNotificationHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function renderTopbarNotifications(items) {
    var list = document.getElementById('topbarNotifList');
    if (!list) {
        return;
    }

    var notifications = Array.isArray(items) ? items : [];
    if (notifications.length === 0) {
        list.innerHTML = '<div class="topbar-notification-empty">No notifications yet.</div>';
        return;
    }

    var html = notifications.map(function (item) {
        var id = parseInt(item.notification_id || 0, 10);
        var title = String(item.title || 'Notification');
        var message = String(item.message || '');
        var timeLabel = String(item.time_label || 'Just now');
        var isRead = !!item.is_read;
        var url = resolveNotificationUrl(item.target_url || '');

        return '<a class="topbar-notification-item ' + (isRead ? '' : 'unread') + '" '
            + 'href="' + escapeNotificationHtml(url) + '" '
            + 'data-notification-id="' + id + '">' 
            + '<div class="topbar-notification-title">' + escapeNotificationHtml(title) + '</div>'
            + '<div class="topbar-notification-message">' + escapeNotificationHtml(message) + '</div>'
            + '<div class="topbar-notification-time">' + escapeNotificationHtml(timeLabel) + '</div>'
            + '</a>';
    }).join('');

    list.innerHTML = html;
}

function loadTopbarNotifications() {
    topbarNotificationsApi('list').then(function (data) {
        updateTopbarNotifBadge(data.unread_count || 0);
        renderTopbarNotifications(data.notifications || []);
    }).catch(function () {
        renderTopbarNotifications([]);
    });
}

function refreshTopbarUnreadCount() {
    topbarNotificationsApi('unread_count').then(function (data) {
        updateTopbarNotifBadge(data.unread_count || 0);
    }).catch(function () {
        // Ignore polling errors.
    });
}

function markNotificationRead(notificationId) {
    var id = parseInt(notificationId || 0, 10);
    if (isNaN(id) || id <= 0) {
        return Promise.resolve();
    }

    var body = new FormData();
    body.append('notification_id', String(id));

    return topbarNotificationsApi('mark_read', {
        method: 'POST',
        body: body
    }).then(function (data) {
        updateTopbarNotifBadge(data.unread_count || 0);
    }).catch(function () {
        // Ignore read-mark errors and allow navigation.
    });
}

document.addEventListener('click', function (event) {
    var profileWrap = document.getElementById('topbarProfileWrap');
    if (profileWrap && !profileWrap.contains(event.target)) {
        closeTopbarProfile();
    }

    var notifWrap = document.getElementById('topbarNotifWrap');
    if (notifWrap && !notifWrap.contains(event.target)) {
        closeTopbarNotifications();
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
        closeTopbarNotifications();
    }
});

window.addEventListener('resize', function () {
    closeTopbarProfile();
    closeTopbarNotifications();
});

var topbarNotifListEl = document.getElementById('topbarNotifList');
if (topbarNotifListEl) {
    topbarNotifListEl.addEventListener('click', function (event) {
        var item = event.target.closest('.topbar-notification-item');
        if (!item) {
            return;
        }

        var notificationId = item.getAttribute('data-notification-id') || '0';
        markNotificationRead(notificationId);
    });
}

var topbarMarkAllButton = document.getElementById('topbarNotifMarkAll');
if (topbarMarkAllButton) {
    topbarMarkAllButton.addEventListener('click', function (event) {
        event.preventDefault();
        topbarNotificationsApi('mark_all_read', {
            method: 'POST',
            body: new FormData()
        }).then(function () {
            updateTopbarNotifBadge(0);
            loadTopbarNotifications();
        }).catch(function () {
            // Ignore mark-all failures.
        });
    });
}

refreshTopbarUnreadCount();
loadTopbarNotifications();
applySidebarState();
window.setInterval(refreshTopbarUnreadCount, 15000);

// Auto-dismiss flash toast
var toast = document.getElementById('flashToast');
if (toast) {
  setTimeout(function() { toast.style.opacity = '0'; toast.style.transform = 'translateY(-10px)'; }, 3000);
  setTimeout(function() { toast.remove(); }, 3500);
}
</script>
</body>
</html>
