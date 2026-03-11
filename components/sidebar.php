<?php

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
