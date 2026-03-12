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
    <div class="topbar-user">
      <div class="topbar-avatar"><?php echo $initials; ?></div>
    </div>
  </div>
</header>
