<?php
// Get user info from session
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'guest';
$userId = $_SESSION['user_id'] ?? 0;
$baseUrl = '/Skillhive';

// Get user initials for avatar
$nameParts = explode(' ', $userName);
$initials = '';
foreach ($nameParts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Role-specific display names
$roleLabels = [
    'student' => '🎓 Student',
    'employer' => '💼 Employer',
    'adviser' => '🏛 Adviser',
    'ojt_professor' => '🏛 Professor',
    'admin' => '⚙️ Admin'
];
$displayRole = $roleLabels[$userRole] ?? 'User';
?>

<nav class="top-nav">
  <!-- Logo & Brand -->
  <a href="<?php echo $baseUrl; ?>/index.php" class="logo-area">
    <svg class="logo-icon" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="32" height="32" rx="6" fill="var(--red)"/>
      <path d="M16 8L10 11V18C10 22.4 13 26.2 16 27.5C19 26.2 22 22.4 22 18V11L16 8Z" fill="white" opacity="0.9"/>
      <path d="M14.5 17.5L13 16L11.6 17.4L14.5 20.3L20.5 14.3L19.1 12.9L14.5 17.5Z" fill="var(--red)"/>
    </svg>
    <span class="logo-text"><span class="skill">Skill</span><span class="hive">Hive</span></span>
  </a>

  <!-- Hamburger Menu (Mobile) -->
  <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Menu">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <!-- Right Side Nav -->
  <div class="top-nav-right">
    <!-- Search Bar (Desktop Only) -->
    <div class="search-wrap" style="display: none;" id="desktopSearch">
      <i class="fa-solid fa-magnifying-glass" style="color: var(--muted); font-size: 12px;"></i>
      <input type="text" placeholder="Search...">
    </div>

    <!-- Notifications (Optional) -->
    <button class="nav-btn" style="position: relative;" id="notificationBtn" title="Notifications">
      <i class="fa-solid fa-bell"></i>
      <span style="position: absolute; top: 2px; right: 2px; width: 6px; height: 6px; background: var(--red); border-radius: 50%;"></span>
    </button>

    <!-- User Profile Dropdown -->
    <div class="nav-user" id="userDropdown">
      <div class="avatar red" style="cursor: pointer;">
        <?php echo htmlspecialchars($initials); ?>
      </div>
      <div style="display: flex; flex-direction: column; gap: 1px; cursor: pointer;">
        <span style="font-size: 12.5px; font-weight: 600;"><?php echo htmlspecialchars(substr($userName, 0, 12)); ?></span>
        <span style="font-size: 10px; opacity: 0.6;"><?php echo htmlspecialchars($displayRole); ?></span>
      </div>

      <!-- Dropdown Menu -->
      <div style="position: absolute; top: 52px; right: 10px; background: var(--white); border: 1px solid var(--border); border-radius: 8px; min-width: 200px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); display: none; z-index: 300;" id="userMenu">
        <div style="padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 11px; color: var(--muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.8px;">Account</div>
        
        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo ($userRole === 'student' ? 'student/profile' : ($userRole === 'employer' ? 'employer/dashboard' : 'adviser/dashboard')); ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; color: var(--dark); text-decoration: none; font-size: 12.5px; transition: background 0.15s; border: none; background: none; width: 100%; text-align: left; cursor: pointer;">
          <i class="fa-solid fa-user-circle" style="color: var(--red);"></i> My Profile
        </a>

        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo ($userRole === 'student' ? 'student/profile' : ($userRole === 'employer' ? 'employer/dashboard' : 'adviser/dashboard')); ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; color: var(--dark); text-decoration: none; font-size: 12.5px; transition: background 0.15s; border: none; background: none; width: 100%; text-align: left; cursor: pointer;">
          <i class="fa-solid fa-gear" style="color: var(--grey);"></i> Settings
        </a>

        <div style="height: 1px; background: var(--border); margin: 6px 0;"></div>

        <a href="<?php echo $baseUrl; ?>/pages/auth/logout.php" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; color: var(--red); text-decoration: none; font-size: 12.5px; transition: background 0.15s; border: none; background: none; width: 100%; text-align: left; cursor: pointer;">
          <i class="fa-solid fa-right-from-bracket"></i> Sign Out
        </a>
      </div>
    </div>

    <!-- Logout Button (Mobile) -->
    <a href="<?php echo $baseUrl; ?>/pages/auth/logout.php" class="nav-btn red" style="display: none;" id="mobileLogout">
      <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
  </div>
</nav>

<style>
  .top-nav-right {
    position: relative;
  }

  #userDropdown {
    position: relative;
  }

  #userMenu {
    animation: slideDown 0.15s ease-out;
  }

  #userMenu a:hover,
  #userMenu button:hover {
    background: var(--light-grey) !important;
  }

  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @media (max-width: 600px) {
    .search-wrap {
      display: none !important;
    }
    #desktopSearch {
      display: none !important;
    }
    #mobileLogout {
      display: inline-flex !important;
    }
    .nav-user {
      display: none;
    }
  }
</style>

<script>
  // User dropdown toggle
  document.getElementById('userDropdown').addEventListener('click', function(e) {
    e.stopPropagation();
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
  });

  // Close dropdown when clicking outside
  document.addEventListener('click', function() {
    document.getElementById('userMenu').style.display = 'none';
  });

  // Hamburger menu toggle
  document.getElementById('hamburgerBtn').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.querySelector('.overlay').classList.toggle('active');
  });

  // Notification button (placeholder)
  document.getElementById('notificationBtn').addEventListener('click', function() {
    alert('No new notifications');
  });
</script>