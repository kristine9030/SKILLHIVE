<?php
<<<<<<< HEAD
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
            ['icon' => 'fas fa-stamp',            'text' => 'Endorsements',  'page' => 'adviser/endorsement'],
            ['icon' => 'fas fa-eye',              'text' => 'OJT Monitoring','page' => 'adviser/monitoring'],
            ['icon' => 'fas fa-chart-pie',        'text' => 'Analytics',     'page' => 'adviser/analytics'],
        ]],
        ['label' => 'MANAGEMENT', 'items' => [
            ['icon' => 'fas fa-building',         'text' => 'Companies',     'page' => 'adviser/companies'],
            ['icon' => 'fas fa-clipboard-check',  'text' => 'Evaluations',   'page' => 'adviser/evaluation'],
        ]],
    ];
} elseif ($role === 'admin') {
    $navItems = [
        ['label' => 'MAIN', 'items' => [
            ['icon' => 'fas fa-house',        'text' => 'Dashboard',  'page' => 'admin/dashboard'],
            ['icon' => 'fas fa-building',     'text' => 'Companies',  'page' => 'admin/verify-companies'],
            ['icon' => 'fas fa-users',        'text' => 'Users',      'page' => 'admin/users'],
            ['icon' => 'fas fa-gear',         'text' => 'Settings',   'page' => 'admin/settings'],
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
    <button class="sb-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
  </div>

  <nav class="sb-nav">
    <?php foreach ($navItems as $section): ?>
      <div class="sb-section-label"><?php echo $section['label']; ?></div>
      <?php foreach ($section['items'] as $item): ?>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo $item['page']; ?>"
           class="sb-item <?php echo ($currentPage === $item['page']) ? 'active' : ''; ?>">
          <i class="<?php echo $item['icon']; ?>"></i>
          <span><?php echo $item['text']; ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sb-footer">
    <a href="<?php echo $baseUrl; ?>/pages/auth/logout.php" class="sb-item sb-logout">
      <i class="fas fa-right-from-bracket"></i>
      <span>Sign Out</span>
    </a>
    <div class="sb-user">
      <div class="sb-avatar"><?php echo $initials; ?></div>
      <div class="sb-user-info">
        <div class="sb-user-name"><?php echo htmlspecialchars($userName); ?></div>
        <div class="sb-user-role"><?php echo ucfirst(htmlspecialchars($role)); ?></div>
      </div>
    </div>
  </div>
</aside>
=======

$baseUrl = '/Skillhive';
$userRole = $_SESSION['user_role'] ?? 'student';
$currentPage = $_GET['page'] ?? '';

// Navigation items per role
$navItems = [
    'student' => [
        ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'page' => 'student/dashboard', 'badge' => null],
        ['icon' => 'fa-user', 'label' => 'My Profile', 'page' => 'student/profile', 'badge' => null],
        ['icon' => 'fa-briefcase', 'label' => 'Job Marketplace', 'page' => 'student/marketplace', 'badge' => null],
        ['icon' => 'fa-paper-plane', 'label' => 'Applications', 'page' => 'student/applications', 'badge' => 'badge'],
        ['icon' => 'fa-clock', 'label' => 'OJT Log', 'page' => 'student/ojt-log', 'badge' => null],
        ['icon' => 'fa-chart-line', 'label' => 'Analytics', 'page' => 'student/analytics', 'badge' => null],
    ],
    'employer' => [
        ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'page' => 'employer/dashboard', 'badge' => null],
        ['icon' => 'fa-plus-circle', 'label' => 'Post Internship', 'page' => 'employer/post_internship', 'badge' => null],
        ['icon' => 'fa-users', 'label' => 'Candidates', 'page' => 'employer/candidates', 'badge' => 'badge'],
        ['icon' => 'fa-clipboard-check', 'label' => 'Evaluation', 'page' => 'employer/evaluation', 'badge' => null],
        ['icon' => 'fa-chart-bar', 'label' => 'Analytics', 'page' => 'employer/analytics', 'badge' => null],
    ],
    'adviser' => [
        ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'page' => 'adviser/dashboard', 'badge' => null],
        ['icon' => 'fa-eye', 'label' => 'Monitoring', 'page' => 'adviser/monitoring', 'badge' => null],
        ['icon' => 'fa-stamp', 'label' => 'Endorsement', 'page' => 'adviser/endorsement', 'badge' => null],
        ['icon' => 'fa-star', 'label' => 'Grading', 'page' => 'adviser/grading', 'badge' => null],
        ['icon' => 'fa-chart-bar', 'label' => 'Analytics', 'page' => 'adviser/analytics', 'badge' => null],
    ],
    'ojt_professor' => [
        ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'page' => 'ojt_professor/dashboard', 'badge' => null],
        ['icon' => 'fa-eye', 'label' => 'Monitoring', 'page' => 'ojt_professor/monitoring', 'badge' => null],
        ['icon' => 'fa-stamp', 'label' => 'Endorsement', 'page' => 'ojt_professor/endorsement', 'badge' => null],
        ['icon' => 'fa-star', 'label' => 'Grading', 'page' => 'ojt_professor/grading', 'badge' => null],
        ['icon' => 'fa-chart-bar', 'label' => 'Analytics', 'page' => 'ojt_professor/analytics', 'badge' => null],
    ],
    'admin' => [
        ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'page' => 'admin/dashboard', 'badge' => null],
        ['icon' => 'fa-building-circle-check', 'label' => 'Verify Companies', 'page' => 'admin/verify-companies', 'badge' => 'badge gold'],
        ['icon' => 'fa-users-gear', 'label' => 'Users', 'page' => 'admin/users', 'badge' => null],
        ['icon' => 'fa-wrench', 'label' => 'Settings', 'page' => 'admin/settings', 'badge' => null],
    ]
];

$items = $navItems[$userRole] ?? $navItems['student'];
?>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-label">Navigation</div>

  <?php foreach ($items as $item): ?>
    <a href="<?php echo $baseUrl; ?>/layout.php?page=<?php echo htmlspecialchars($item['page']); ?>"
       class="nav-item <?php echo ($currentPage === $item['page']) ? 'active' : ''; ?>"
       title="<?php echo htmlspecialchars($item['label']); ?>">
      <span class="ico"><i class="fa-solid <?php echo htmlspecialchars($item['icon']); ?>"></i></span>
      <span><?php echo htmlspecialchars($item['label']); ?></span>
      <?php if ($item['badge']): ?>
        <span class="<?php echo htmlspecialchars($item['badge']); ?>">3</span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>

  <div class="sidebar-label">Account</div>
  <a href="<?php echo $baseUrl; ?>/pages/auth/logout.php" class="nav-item">
    <span class="ico"><i class="fa-solid fa-right-from-bracket"></i></span>
    <span>Sign Out</span>
  </a>

  <div style="margin-top:auto;padding:16px 18px;border-top:1px solid rgba(255,255,255,0.1);font-size:10px;color:rgba(255,255,255,0.3);">
    SkillHive v1.0
  </div>
</aside>

<style>
  .sidebar { display: flex; flex-direction: column; }
  .sidebar-label { padding:10px 18px 4px; font-size:9.5px; font-weight:700; letter-spacing:1.8px; color:rgba(255,255,255,0.3); text-transform:uppercase; margin-top:6px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:9px 18px; cursor:pointer; color:rgba(255,255,255,0.5); font-size:12.5px; font-weight:500; border-left:3px solid transparent; transition:all 0.15s; text-decoration:none; }
  .nav-item:hover { color:var(--white); background:rgba(255,255,255,0.05); }
  .nav-item.active { color:var(--white); border-left-color:var(--red3,#c8102e); background:rgba(200,16,46,0.12); }
  .nav-item .ico { font-size:15px; width:20px; text-align:center; flex-shrink:0; }
  .badge { margin-left:auto; background:var(--red); color:var(--white); font-size:9px; padding:1px 6px; border-radius:20px; font-weight:700; }
  .badge.gold { background:var(--gold); color:var(--dark); }
</style>
>>>>>>> 6eb432d25ae2206575e1e0b9f3d75894c472a1ba
