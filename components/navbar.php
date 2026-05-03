<?php
/**
 * Navbar component variables (defined in layout.php)
 * @var string $pageTitle - The current page title
 * @var string $role - The user's role (student, employer, adviser, admin)
 * @var string $baseUrl - Base URL for the application
 * @var string $userName - The logged-in user's name
 * @var string $userEmail - The logged-in user's email
 * @var int $userId - The logged-in user's ID
 * @var array $_SESSION - PHP session superglobal
 */
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </button>
    <h1 class="topbar-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
  </div>
  <div class="topbar-right">
    <?php
    $messagesLink = 'student/messaging';
    if ($role === 'employer') {
      $messagesLink = 'employer/messaging';
    } elseif ($role === 'adviser') {
      $messagesLink = 'adviser/messaging';
    } elseif ($role === 'admin') {
      $messagesLink = 'admin/messaging';
    }
    ?>

    <div class="topbar-search">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search...">
    </div>

    <div class="topbar-notification-dropdown" id="topbarNotifWrap">
      <button class="topbar-btn topbar-notification-toggle" id="topbarNotifToggle" type="button" aria-expanded="false" aria-label="Notifications" title="Notifications" onclick="toggleTopbarNotifications(event)">
        <i class="fas fa-bell"></i>
        <span class="notif-badge topbar-notif-badge" id="topbarNotifBadge" style="display:none">0</span>
      </button>

      <div class="topbar-dropdown-menu topbar-notification-menu" id="topbarNotifMenu">
        <div class="topbar-notification-head">
          <div class="topbar-notification-head-main">
            <strong>Notifications</strong>
            <span class="topbar-notification-unread-text" id="topbarNotifUnreadText" style="display:none">0 unread</span>
          </div>
          <button type="button" class="topbar-notification-mark-read" id="topbarNotifMarkAll">Mark all read</button>
        </div>
        <div class="topbar-notification-list" id="topbarNotifList">
          <div class="topbar-notification-empty">No notifications yet.</div>
        </div>
      </div>
    </div>

    <a class="topbar-btn" href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo $messagesLink; ?>" title="Messages" aria-label="Messages">
      <i class="fas fa-comment-dots"></i>
    </a>
    
    <?php 
    $profileLink = 'student/profile';
    $settingsLink = 'student/settings';
    $userAvatar = $initials;
    $showLogoInNav = false;

    if ($role === 'student') {
        $profileLink = 'student/profile';
        $settingsLink = 'student/settings';
        $studentId = (int)($userId ?? 0);
        if ($studentId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT profile_picture FROM student WHERE student_id = ? LIMIT 1');
                $stmt->execute([$studentId]);
                $sRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($sRow && !empty($sRow['profile_picture'])) {
                    $picFile = trim((string)$sRow['profile_picture']);
                    if (!empty($picFile)) {
                        if (strpos($picFile, 'http://') === 0 || strpos($picFile, 'https://') === 0) {
                            $userAvatar = $picFile;
                        } else {
                            $userAvatar = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($picFile);
                        }
                        $showLogoInNav = true;
                    }
                }
            } catch (Throwable $e) {}
        }
    } elseif ($role === 'employer') {
        $profileLink = 'employer/profile';
        $settingsLink = 'employer/settings';
        $empId = (int)($_SESSION['employer_id'] ?? 0);
        if ($empId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT company_logo, company_name FROM employer WHERE employer_id = ? LIMIT 1');
                $stmt->execute([$empId]);
                $empRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($empRow && !empty($empRow['company_logo'])) {
                    $companyLogoPath = trim((string)$empRow['company_logo']);
                    if (!empty($companyLogoPath) && strpos($companyLogoPath, 'http') === 0) {
                        $userAvatar = $companyLogoPath;
                    } elseif (!empty($companyLogoPath)) {
                        $userAvatar = $baseUrl . '/assets/backend/uploads/company/' . rawurlencode($companyLogoPath);
                    }
                    $showLogoInNav = !empty($companyLogoPath);
                }
            } catch (Throwable $e) {}
        }
    } elseif ($role === 'adviser') {
        $profileLink = 'adviser/profile';
        $settingsLink = 'adviser/profile';
        $adviserId = (int)($userId ?? 0);
        if ($adviserId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT profile_picture FROM internship_adviser WHERE adviser_id = ? LIMIT 1');
                $stmt->execute([$adviserId]);
                $aRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($aRow && !empty($aRow['profile_picture'])) {
                    $picFile = trim((string)$aRow['profile_picture']);
                    if (!empty($picFile)) {
                        if (strpos($picFile, 'http://') === 0 || strpos($picFile, 'https://') === 0) {
                            $userAvatar = $picFile;
                        } else {
                            $userAvatar = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($picFile);
                        }
                        $showLogoInNav = true;
                    }
                }
            } catch (Throwable $e) {}
        }
    } elseif ($role === 'admin') {
        $profileLink = 'admin/dashboard';
        $settingsLink = 'admin/settings';
    }
    ?>

    <div class="topbar-profile-dropdown" id="topbarProfileWrap">
      <button class="topbar-user" id="topbarProfileToggle" type="button" aria-expanded="false" onclick="toggleTopbarProfile(event)">
        <?php if ($showLogoInNav): ?>
          <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        <?php else: ?>
          <div class="topbar-avatar"><?php echo $initials; ?></div>
        <?php endif; ?>
      </button>

      <div class="topbar-dropdown-menu" id="topbarProfileMenu">
        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo $profileLink; ?>" class="topbar-dropdown-item">
          <i class="fas fa-user topbar-item-icon"></i>
          <span>My Profile</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo $settingsLink; ?>" class="topbar-dropdown-item">
          <i class="fas fa-gear topbar-item-icon"></i>
          <span>Settings</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=help" class="topbar-dropdown-item">
          <i class="fas fa-circle-question topbar-item-icon"></i>
          <span>Help</span>
        </a>
        <div class="topbar-dropdown-divider"></div>
        <a href="<?php echo $baseUrl; ?>/pages/auth/logout.php" class="topbar-dropdown-item text-danger">
          <i class="fas fa-right-from-bracket topbar-item-icon"></i>
          <span>Sign out</span>
        </a>
      </div>
    </div>

  </div>
</header>
