<header class="topbar">
  <div class="topbar-left">
    <button class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </button>
    <h1 class="topbar-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
  </div>
  <div class="topbar-right">
    <div class="topbar-search">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search...">
    </div>
    <button class="topbar-btn" title="Notifications"><i class="fas fa-bell"></i></button>
    <button class="topbar-btn" title="Messages"><i class="fas fa-comment-dots"></i></button>
    
    <div class="topbar-profile-dropdown" id="topbarProfileWrap">
      <button class="topbar-user" id="topbarProfileToggle" type="button" aria-expanded="false" onclick="toggleTopbarProfile(event)">
        <div class="topbar-avatar"><?php echo $initials; ?></div>
      </button>

      <?php 
      $profileLink = 'student/profile';
      $settingsLink = 'student/settings';
      if ($role === 'employer') {
          $profileLink = 'employer/dashboard';
          $settingsLink = 'employer/dashboard';
      } elseif ($role === 'adviser') {
          $profileLink = 'adviser/dashboard';
          $settingsLink = 'adviser/dashboard';
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
