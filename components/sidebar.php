<?php
/**
 * Sidebar component variables (defined in layout.php)
 * @var string $role - The user's role (student, employer, adviser, admin)
 * @var string $baseUrl - Base URL for the application
 * @var string $currentPage - The current page being viewed
 * @var string $userName - The logged-in user's name
 * @var array $_SESSION - PHP session superglobal
 * @var PDO $pdo - Database connection object
 */

$navItems = [];

if ($role === 'student') {
    $navItems = [
        ['label' => 'MAIN', 'items' => [
            ['icon' => 'fas fa-house',        'text' => 'Home',         'page' => 'student/dashboard'],
            ['icon' => 'fas fa-user',         'text' => 'Profile',      'page' => 'student/profile'],
            ['icon' => 'fas fa-store',        'text' => 'Marketplace',  'page' => 'student/marketplace'],
            ['icon' => 'fas fa-file-lines',   'text' => 'CV Builder',   'page' => 'student/resume-ai'],
        ]],
        ['label' => 'TRACKING', 'items' => [
            ['icon' => 'fas fa-paper-plane',  'text' => 'Applications', 'page' => 'student/applications'],
            ['icon' => 'fas fa-clock',        'text' => 'OJT Tracker',  'page' => 'student/ojt-log'],
            ['icon' => 'fas fa-clipboard-check', 'text' => 'Requirements', 'page' => 'student/requirements'],
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
            ['icon' => 'fas fa-id-card',         'text' => 'Profile',     'page' => 'employer/profile'],
            ['icon' => 'fas fa-briefcase',       'text' => 'Postings',     'page' => 'employer/post_internship'],
            ['icon' => 'fas fa-users',            'text' => 'Candidates',   'page' => 'employer/candidates'],
         ]],
        ['label' => 'MANAGEMENT', 'items' => [
            ['icon' => 'fas fa-chart-bar',       'text' => 'Analytics',    'page' => 'employer/analytics'],
            ['icon' => 'fas fa-clipboard-check',  'text' => 'Evaluations',  'page' => 'employer/evaluation'],
            ['icon' => 'fas fa-user-graduate',  'text' => 'OJT Students', 'page' => 'employer/ojt_students'],
        ]],
         ['label' => 'SYSTEM', 'items' => [
             ['icon' => 'fas fa-gear',            'text' => 'Settings',    'page' => 'employer/settings'],
         ]],
      ];
} elseif ($role === 'adviser') {
    $navItems = [
        ['label' => 'MAIN', 'items' => [
            ['icon' => 'fas fa-house',        'text' => 'Dashboard',   'page' => 'adviser/dashboard'],
            ['icon' => 'fas fa-user',         'text' => 'Profile',     'page' => 'adviser/profile'],
            ['icon' => 'fas fa-users',        'text' => 'Students',    'page' => 'adviser/students'],
            ['icon' => 'fas fa-envelope',     'text' => 'Messaging',   'page' => 'adviser/messaging'],
        ]],
        ['label' => 'MANAGEMENT', 'items' => [
            ['icon' => 'fas fa-eye',          'text' => 'Monitoring',  'page' => 'adviser/monitoring'],
            ['icon' => 'fas fa-stamp',        'text' => 'Endorsement', 'page' => 'adviser/endorsement'],
            ['icon' => 'fas fa-book',         'text' => 'Journal Analytics', 'page' => 'adviser/journal_analytics'],
            ['icon' => 'fas fa-building',     'text' => 'Companies',   'page' => 'adviser/companies'],
        ]],
        ['label' => 'SYSTEM', 'items' => [
            ['icon' => 'fas fa-chart-bar',    'text' => 'Analytics',   'page' => 'adviser/analytics'],
            ['icon' => 'fas fa-gear',         'text' => 'Settings',    'page' => 'adviser/settings'],
        ]],
    ];
}

$logoAsset = $baseUrl . '/assets/media/skillhive-logo.png';
$userAvatarImg = '';
$showUserAvatarImg = false;

if ($role === 'student') {
    $studentId = (int)($_SESSION['user_id'] ?? 0);
    if ($studentId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT profile_picture FROM student WHERE student_id = ? LIMIT 1');
            $stmt->execute([$studentId]);
            $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($studentRow && !empty($studentRow['profile_picture'])) {
                $picFile = trim((string)$studentRow['profile_picture']);
                if (!empty($picFile)) {
                    if (strpos($picFile, 'http://') === 0 || strpos($picFile, 'https://') === 0) {
                        $userAvatarImg = $picFile;
                    } else {
                        $userAvatarImg = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($picFile);
                    }
                    $showUserAvatarImg = true;
                }
            }
        } catch (Throwable $e) {}
    }
} elseif ($role === 'employer') {
    $empId = (int)($_SESSION['employer_id'] ?? 0);
    if ($empId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT company_logo FROM employer WHERE employer_id = ? LIMIT 1');
            $stmt->execute([$empId]);
            $empRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($empRow && !empty($empRow['company_logo'])) {
                $logoFile = trim((string)$empRow['company_logo']);
                if (!empty($logoFile)) {
                    if (strpos($logoFile, 'http://') === 0 || strpos($logoFile, 'https://') === 0) {
                        $userAvatarImg = $logoFile;
                    } else {
                        $userAvatarImg = $baseUrl . '/assets/backend/uploads/company/' . rawurlencode($logoFile);
                    }
                    $showUserAvatarImg = true;
                }
            }
        } catch (Throwable $e) {}
    }
} elseif ($role === 'adviser') {
    $adviserId = (int)($_SESSION['user_id'] ?? 0);
    if ($adviserId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT profile_picture FROM internship_adviser WHERE adviser_id = ? LIMIT 1');
            $stmt->execute([$adviserId]);
            $adviserRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($adviserRow && !empty($adviserRow['profile_picture'])) {
                $picFile = trim((string)$adviserRow['profile_picture']);
                if (!empty($picFile)) {
                    if (strpos($picFile, 'http://') === 0 || strpos($picFile, 'https://') === 0) {
                        $userAvatarImg = $picFile;
                    } else {
                        $userAvatarImg = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($picFile);
                    }
                    $showUserAvatarImg = true;
                }
            }
        } catch (Throwable $e) {}
    }
}
?>
<aside class="sidebar">
  <div class="sb-header">
    <a href="#" class="sb-logo" onclick="event.preventDefault(); toggleSidebar();" style="cursor:pointer;">
      <div class="logo-icon"><img src="<?php echo htmlspecialchars($logoAsset); ?>" alt="SkillHive"></div>
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
      <?php if ($showUserAvatarImg): ?>
        <img src="<?php echo htmlspecialchars($userAvatarImg); ?>" alt="Logo" class="sb-avatar" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
      <?php else: ?>
        <div class="sb-avatar"><?php echo $initials; ?></div>
      <?php endif; ?>
      <div class="sb-user-info">
        <div class="sb-user-name"><?php echo htmlspecialchars($userName); ?></div>
        <div class="sb-user-role"><?php echo ucfirst(htmlspecialchars($role)); ?></div>
      </div>
    </div>
  </div>
</aside>
