<?php
$navItems = [];

if ($role === 'student') {
    $navItems = [
        ['label' => 'MAIN', 'items' => [
            ['icon' => 'fas fa-house',        'text' => 'Home',         'page' => 'student/dashboard'],
            ['icon' => 'fas fa-user',         'text' => 'Profile',      'page' => 'student/profile'],
            ['icon' => 'fas fa-brain',        'text' => 'AI Matching',  'page' => 'student/matching'],
            ['icon' => 'fas fa-store',        'text' => 'Marketplace',  'page' => 'student/marketplace'],
            ['icon' => 'fas fa-file-lines',   'text' => 'Resume AI',    'page' => 'student/resume-ai'],
        ]],
        ['label' => 'TRACKING', 'items' => [
            ['icon' => 'fas fa-paper-plane',  'text' => 'Applications', 'page' => 'student/applications'],
            ['icon' => 'fas fa-clock',        'text' => 'OJT Tracker',  'page' => 'student/ojt-log'],
            ['icon' => 'fas fa-chart-bar',    'text' => 'Analytics',    'page' => 'student/analytics'],
        ]],
        ['label' => 'SYSTEM', 'items' => [
            ['icon' => 'fas fa-gear',         'text' => 'Settings',     'page' => 'student/settings'],
        ]],
    ];
} elseif ($role === 'employer') {
    $navItems = [
        ['label' => 'MAIN', 'items' => [
            ['icon' => 'fas fa-house',            'text' => 'Dashboard',   'page' => 'employer/dashboard'],
            ['icon' => 'fas fa-briefcase',        'text' => 'Postings',    'page' => 'employer/post_internship'],
            ['icon' => 'fas fa-users',            'text' => 'Candidates',  'page' => 'employer/candidates'],
            ['icon' => 'fas fa-clipboard-check',  'text' => 'Evaluations', 'page' => 'employer/evaluation'],
        ]],
    ];
} elseif ($role === 'adviser') {
    $navItems = [
        ['label' => 'MAIN', 'items' => [
            ['icon' => 'fas fa-house',            'text' => 'Dashboard',     'page' => 'adviser/dashboard'],
            ['icon' => 'fas fa-house',            'text' => 'Students',      'page' => 'adviser/students'],
            ['icon' => 'fas fa-stamp',            'text' => 'Endorsements',  'page' => 'adviser/endorsement'],
            ['icon' => 'fas fa-eye',              'text' => 'OJT Monitoring','page' => 'adviser/monitoring'],
            ['icon' => 'fas fa-chart-pie',        'text' => 'Analytics',     'page' => 'adviser/analytics'],
        ]],
        ['label' => 'MANAGEMENT', 'items' => [
            ['icon' => 'fas fa-building',         'text' => 'Companies',     'page' => 'adviser/companies'],
            ['icon' => 'fas fa-clipboard-check',  'text' => 'Evaluations',   'page' => 'adviser/evaluation'],
        ]],
         ['label' => 'ACCOUNT', 'items' => [
          ['icon' => 'fas fa-gear',             'text' => 'Settings',     'page' => 'adviser/settings'],
        ]],
    ];
} elseif ($role === 'admin') {
    $navItems = [
        ['label' => 'MAIN', 'items' => [
            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard',  'page' => 'admin/dashboard'],
      ['icon' => 'fas fa-university',     'text' => 'Universities','page' => 'admin/universities'],
            ['icon' => 'fas fa-building',       'text' => 'Companies',  'page' => 'admin/verify-companies'],
            ['icon' => 'fas fa-users',          'text' => 'Users',      'page' => 'admin/users'],
        ]],
        ['label' => 'ANALYTICS', 'items' => [
            ['icon' => 'fas fa-chart-bar',      'text' => 'Reports',    'page' => 'admin/reports'],
            ['icon' => 'fas fa-history',        'text' => 'Audit Logs', 'page' => 'admin/audit-logs'],
        ]],
        ['label' => 'SYSTEM', 'items' => [
            ['icon' => 'fas fa-gear',           'text' => 'Settings',   'page' => 'admin/settings'],
        ]],
    ];
}
?>
<aside class="sidebar">
  <div class="sb-header">
    <a href="<?php echo $baseUrl; ?>/layout.php" class="sb-logo">
      <div class="logo-icon"><i class="fas fa-hexagon-nodes"></i></div>
      <span class="sb-logo-text">SkillHive</span>
    </a>
    <button class="sb-toggle" type="button" onclick="toggleSidebar()"><i class="fas fa-chevron-left"></i></button>
  </div>

  <nav class="sb-nav">
    <?php foreach ($navItems as $section): ?>
      <div class="sb-section-label"><?php echo $section['label']; ?></div>
      <?php foreach ($section['items'] as $item): ?>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo $item['page']; ?>"
           class="sb-item <?php echo ($currentPage === $item['page']) ? 'active' : ''; ?>">
          <span class="sb-item-icon"><i class="<?php echo $item['icon']; ?>"></i></span>
          <span class="sb-item-text"><?php echo $item['text']; ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?php echo $initials; ?></div>
      <div class="sb-user-info">
        <div class="sb-user-name"><?php echo htmlspecialchars($userName); ?></div>
        <div class="sb-user-role"><?php echo ucfirst(htmlspecialchars($role)); ?></div>
      </div>
    </div>
  </div>
</aside>
