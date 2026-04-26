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
    
    <div class="topbar-profile-dropdown" id="topbarProfileWrap">
      <button class="topbar-user" id="topbarProfileToggle" type="button" aria-expanded="false" onclick="toggleTopbarProfile(event)">
        <div class="topbar-avatar"><?php echo $initials; ?></div>
      </button>

      <?php 
      $profileLink = 'student/profile';
      $settingsLink = 'student/settings';
      if ($role === 'employer') {
          $profileLink = 'employer/profile';
          $settingsLink = 'employer/profile';
      } elseif ($role === 'adviser') {
          $profileLink = 'adviser/profile';
          $settingsLink = 'adviser/profile';
      } elseif ($role === 'admin') {
          $profileLink = 'admin/dashboard';
          $settingsLink = 'admin/settings';
      }
      ?>
      <div class="topbar-dropdown-menu" id="topbarProfileMenu">
        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo $profileLink; ?>" class="topbar-dropdown-item">
          <i class="fas fa-user topbar-item-icon"></i>
          <span>My Profile</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo $settingsLink; ?>" class="topbar-dropdown-item">
          <i class="fas fa-gear topbar-item-icon"></i>
          <span>Settings</span>
        </a>
        <a href="javascript:void(0)" class="topbar-dropdown-item">
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
