<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/profile_edit.php';
require_once __DIR__ . '/profile_resume.php';
require_once __DIR__ . '/profile_skills.php';
require_once __DIR__ . '/profile_media.php';
require_once __DIR__ . '/profile_following.php';
require_once __DIR__ . '/profile_search.php';
require_once __DIR__ . '/profile_readiness.php';

$profileErrors = [];
$profileSuccess = '';

profile_ensure_link_columns($pdo);
profile_ensure_media_columns($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userName = (string) $userName;
  profile_following_ensure_schema($pdo);
  $currentRole = (string) ($role ?? ($_SESSION['role'] ?? 'student'));
  profile_following_handle_action($pdo, $currentRole, (int) $userId, $profileErrors, $profileSuccess);
    profile_handle_edit($pdo, (int) $userId, $profileErrors, $profileSuccess, $userName);
  profile_handle_media($pdo, (int) $userId, $profileErrors, $profileSuccess);
    profile_handle_resume($pdo, (int) $userId, $profileErrors, $profileSuccess);
    profile_handle_skills($pdo, (int) $userId, $profileErrors, $profileSuccess);
}

profile_following_ensure_schema($pdo);
$currentRole = (string) ($role ?? ($_SESSION['role'] ?? 'student'));

[$student, $studentSkills, $allSkills, $availableSkills] = profile_load_data($pdo, (int) $userId);
[$student, $hasBasicInfo, $hasSkills, $hasResume, $hasPortfolio, $completeness] = profile_apply_readiness($pdo, $student, $studentSkills, (int) $userId);
[$discoverUsers, $followingUsers, $followerUsers, $followingCount, $followersCount] = profile_following_load_data($pdo, $currentRole, (int) $userId);

$resumeFile = trim((string) ($student['resume_file'] ?? ''));
$resumePath = $resumeFile !== '' ? ($baseUrl . '/assets/backend/uploads/resumes/' . rawurlencode($resumeFile)) : '';
$firstName = trim((string) ($student['first_name'] ?? ''));
$lastName = trim((string) ($student['last_name'] ?? ''));
$fullName = trim($firstName . ' ' . $lastName);
if ($fullName === '') {
    $fullName = (string) $userName;
}

$profilePictureFile = trim((string) ($student['profile_picture'] ?? ''));
$profilePicturePath = $profilePictureFile !== '' ? ($baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($profilePictureFile)) : '';
$avatarPreset = trim((string) ($student['avatar_preset'] ?? ''));
$avatarPresetMap = [
  'tech-girl' => ['emoji' => '👩‍💻', 'bg' => 'linear-gradient(135deg,#A855F7,#60A5FA)'],
  'creative-boy' => ['emoji' => '🧑‍🎨', 'bg' => 'linear-gradient(135deg,#F59E0B,#FBBF24)'],
  'mentor-man' => ['emoji' => '👨‍🏫', 'bg' => 'linear-gradient(135deg,#22C55E,#38BDF8)'],
  'student-boy' => ['emoji' => '🧑‍🎓', 'bg' => 'linear-gradient(135deg,#6366F1,#A5B4FC)'],
  'astronaut' => ['emoji' => '👨‍🚀', 'bg' => 'linear-gradient(135deg,#64748B,#93C5FD)'],
  'coder-girl' => ['emoji' => '👩‍💻', 'bg' => 'linear-gradient(135deg,#84CC16,#22C55E)'],
  'leader-girl' => ['emoji' => '👩‍💼', 'bg' => 'linear-gradient(135deg,#F472B6,#A855F7)'],
  'intern-boy' => ['emoji' => '🧑‍💼', 'bg' => 'linear-gradient(135deg,#2DD4BF,#38BDF8)'],
];
$avatarPresetDisplay = $avatarPresetMap[$avatarPreset] ?? null;
$coverPhotoFile = trim((string) ($student['cover_photo'] ?? ''));
$coverPhotoPath = $coverPhotoFile !== '' ? ($baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($coverPhotoFile)) : '';
$coverGradient = trim((string) ($student['cover_gradient'] ?? ''));
if ($coverGradient === '') {
  $coverGradient = 'linear-gradient(135deg,#FDE047 0%,#38BDF8 100%)';
}
$coverIsImage = $coverPhotoPath !== '';
$coverBackgroundStyle = $coverIsImage
  ? "background-image:url('" . htmlspecialchars($coverPhotoPath) . "')"
  : 'background:' . htmlspecialchars($coverGradient);
$coverPreviewStyle = $coverIsImage
  ? "background:url('" . htmlspecialchars($coverPhotoPath) . "') center / cover no-repeat"
  : 'background:' . htmlspecialchars($coverGradient);

$googleUrl = trim((string) ($student['google_url'] ?? ''));
$gmailUrl = trim((string) ($student['gmail_url'] ?? ''));
if ($gmailUrl === '') {
  $email = trim((string) ($student['email'] ?? ''));
  $gmailUrl = $email !== '' ? 'mailto:' . $email : '';
}
$discordUrl = trim((string) ($student['discord_url'] ?? ''));
$dribbbleUrl = trim((string) ($student['dribbble_url'] ?? ''));
$behanceUrl = trim((string) ($student['behance_url'] ?? ''));
$portfolioUrl = trim((string) ($student['portfolio_url'] ?? ''));
$linkedinUrl = trim((string) ($student['linkedin_url'] ?? ''));
$githubUrl = trim((string) ($student['github_url'] ?? ''));

$aboutIntro = trim((string) ($student['about_me_intro'] ?? ''));
if ($aboutIntro === '') {
  $aboutIntro = $student['preferred_industry']
    ? 'Interested in ' . $student['preferred_industry'] . ' opportunities and building practical internship-ready skills.'
    : 'Passionate student building internship-ready skills and portfolio projects.';
}

$aboutPointsRaw = trim((string) ($student['about_me_points'] ?? ''));
$aboutPoints = [];
if ($aboutPointsRaw !== '') {
  $pointLines = preg_split('/\r\n|\r|\n/', $aboutPointsRaw) ?: [];
  foreach ($pointLines as $line) {
    $line = trim((string) $line);
    if ($line === '') {
      continue;
    }
    $aboutPoints[] = $line;
  }
}
if (!$aboutPoints) {
  $aboutPoints = [
    'Ability to work independently as well as a team member.',
    'Ability to work under pressure and meet deadlines.',
    'Dependable, organized, and highly motivated.',
  ];
}

$experienceEntries = [];
$experienceRaw = trim((string) ($student['experience_entries'] ?? ''));
if ($experienceRaw !== '') {
  $decoded = json_decode($experienceRaw, true);
  if (is_array($decoded)) {
    foreach ($decoded as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $title = trim((string) ($entry['title'] ?? ''));
      $subtitle = trim((string) ($entry['subtitle'] ?? ''));
      $date = trim((string) ($entry['date'] ?? ''));
      if ($title === '' || $subtitle === '') {
        continue;
      }
      $experienceEntries[] = [
        'title' => $title,
        'subtitle' => $subtitle,
        'date' => $date !== '' ? $date : 'Present',
      ];
    }
  }
}
if (!$experienceEntries) {
  $experienceEntries = [
    ['title' => 'Internship Candidate', 'subtitle' => (string) ($student['department'] ?? 'Department'), 'date' => 'Current'],
    ['title' => 'Student Developer', 'subtitle' => (string) ($student['program'] ?? 'Program'), 'date' => 'Academic Projects'],
  ];
}

$portfolioEntries = [];
$portfolioEntriesRaw = trim((string) ($student['portfolio_entries'] ?? ''));
if ($portfolioEntriesRaw !== '') {
  $decodedPortfolio = json_decode($portfolioEntriesRaw, true);
  if (is_array($decodedPortfolio)) {
    foreach ($decodedPortfolio as $item) {
      if (!is_array($item)) {
        continue;
      }
      $label = trim((string) ($item['label'] ?? ''));
      $emoji = trim((string) ($item['emoji'] ?? ''));
      if ($label === '') {
        continue;
      }
      $portfolioEntries[] = [
        'label' => $label,
        'emoji' => $emoji !== '' ? $emoji : '💼',
      ];
    }
  }
}
if (!$portfolioEntries) {
  $portfolioEntries = [
    ['label' => 'Home App UI', 'emoji' => '🏠'],
    ['label' => 'Design Kit', 'emoji' => '🎨'],
    ['label' => 'Case Study', 'emoji' => '🧩'],
  ];
}

$experienceTextAreaValue = implode("\n", array_map(static function (array $entry): string {
  return $entry['title'] . ' | ' . $entry['subtitle'] . ' | ' . $entry['date'];
}, $experienceEntries));

$portfolioTextAreaValue = implode("\n", array_map(static function (array $item): string {
  return $item['label'] . ' | ' . $item['emoji'];
}, $portfolioEntries));

$portfolioGradients = [
  'linear-gradient(135deg,#BAE6FD,#93C5FD)',
  'linear-gradient(135deg,#FEF08A,#F97316)',
  'linear-gradient(135deg,#CBD5E1,#94A3B8)',
  'linear-gradient(135deg,#A7F3D0,#34D399)',
  'linear-gradient(135deg,#FBCFE8,#F472B6)',
  'linear-gradient(135deg,#C4B5FD,#818CF8)',
];

$levelPercent = [
    'Beginner' => 45,
    'Intermediate' => 70,
    'Advanced' => 88,
];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&display=swap');

#page-profile { overflow-y: auto; background:#fff; color:var(--text); font-family:'Inter',sans-serif; }
.pf-cover { width:100%; height:160px; position:relative; overflow:hidden; cursor:pointer; background:linear-gradient(135deg,#FDE047 0%,#38BDF8 100%); }
.pf-cover.custom-cover { background-size:cover; background-position:center; background-repeat:no-repeat; }
.pf-cover-overlay { position:absolute; inset:0; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center; gap:8px; opacity:0; transition:.2s; font-size:.82rem; font-weight:700; color:#fff; }
.pf-cover:hover .pf-cover-overlay { opacity:1; }
.pf-cover-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:8px; margin-top:10px; }
.pf-cover-option { border:2px solid #d1d5db; border-radius:10px; height:42px; cursor:pointer; transition:.2s; }
.pf-cover-option.active { border-color:#111827; box-shadow:0 0 0 1px #111827 inset; }
.pf-cover-preview { height:74px; border-radius:12px; border:1px solid #d1d5db; margin-top:14px; background:linear-gradient(135deg,#FDE047 0%,#38BDF8 100%); }
.pf-cover-actions { display:flex; gap:8px; align-items:center; margin-top:14px; flex-wrap:wrap; }
.pf-upload-cover-btn { display:inline-flex; align-items:center; gap:7px; border:1px solid #d1d5db; padding:8px 12px; border-radius:10px; background:#fff; cursor:pointer; font-size:.78rem; font-weight:600; color:#111827; }
.pf-upload-cover-btn input { display:none; }
.pf-header { background:#fff; margin:0 20px; border-radius:22px; padding:0 24px 18px; margin-top:-24px; position:relative; z-index:2; box-shadow:0 10px 30px rgba(15,23,42,.08); }
.pf-header-top { display:flex; align-items:center; gap:16px; margin-bottom:12px; }
.pf-avatar-wrap { position:relative; flex-shrink:0; margin-top:-32px; }
.pf-avatar { width:86px; height:86px; border-radius:50%; border:5px solid #fff; background:linear-gradient(135deg,#FDE68A,#F9A8D4,#C4B5FD); display:flex; align-items:center; justify-content:center; font-size:1.25rem; font-weight:800; box-shadow:0 6px 18px rgba(15,23,42,.14); color:#111; position:relative; overflow:hidden; cursor:pointer; }
.pf-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.pf-avatar-emoji { width:100%; height:100%; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.55rem; line-height:1; }
.pf-avatar-edit-layer { position:absolute; inset:0; background:rgba(0,0,0,.4); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; font-size:.58rem; font-weight:700; color:#fff; opacity:0; transition:.2s; }
.pf-avatar:hover .pf-avatar-edit-layer { opacity:1; }
.pf-online-dot { position:absolute; bottom:7px; right:7px; width:14px; height:14px; background:#22C55E; border:3px solid #fff; border-radius:50%; }
.pf-header-info { flex:1; padding-top:16px; min-width:0; }
.pf-name { font-family:'Poppins','Inter',sans-serif; font-size:1.22rem; font-weight:700; color:#0F172A; line-height:1.2; letter-spacing:-0.01em; }
.pf-role-line { font-size:.9rem; color:#64748B; margin-top:4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.pf-role-dot { width:4px; height:4px; border-radius:50%; background:#CBD5E1; }
.pf-header-actions { display:flex; gap:10px; align-items:center; padding-top:4px; margin-left:auto; }
.pf-btn-hire { background:var(--primary); color:#fff; border:none; padding:8px 20px; border-radius:50px; font-size:.78rem; font-weight:700; cursor:pointer; }
.pf-btn-edit { background:#fff; color:var(--text); border:1.5px solid var(--border); padding:8px 18px; border-radius:50px; font-size:.78rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; }
.pf-meta-stack { padding-top:12px; border-top:1px solid #EDF2F7; display:flex; flex-direction:column; gap:12px; }
.pf-meta-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.pf-meta-item { display:flex; align-items:center; gap:7px; font-size:.85rem; color:#64748B; min-width:0; }
.pf-meta-item i { font-size:.68rem; color:var(--text2); }
.pf-meta-divider { display:none; }
.pf-meta-stat { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; padding:10px 12px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; min-height:62px; }
.pf-meta-stat.clickable { cursor:pointer; }
.pf-meta-stat.clickable:hover { border-color:#d1d5db; background:#f3f4f6; }
.pf-meta-stat.active-nav { border-color:#9ca3af; background:linear-gradient(135deg,#f9fafb,#f3f4f6); }
.pf-meta-stat.clickable:hover .pf-meta-stat-val,
.pf-meta-stat.active-nav .pf-meta-stat-val,
.pf-meta-stat.clickable:hover .pf-meta-stat-lbl { color:var(--text); }
.pf-meta-stat.active-nav .pf-meta-stat-lbl { color:var(--text); }
.pf-meta-stat-val { font-size:1.22rem; font-weight:800; color:#0F172A; line-height:1; }
.pf-meta-stat-lbl { font-size:.86rem; color:#64748B; }
.pf-social-strip { display:flex; gap:8px; padding:12px 20px 4px; flex-wrap:wrap; }
.pf-soc-chip { display:flex; align-items:center; gap:6px; padding:5px 12px; border-radius:50px; font-size:.72rem; font-weight:600; border:1.5px solid #E2E8F0; background:#fff; text-decoration:none; font-family:inherit; cursor:pointer; }
.pf-soc-chip.placeholder-link { cursor:pointer; opacity:1; }
.pf-body { display:grid; grid-template-columns:230px 1fr; gap:16px; padding:16px 20px 24px; }
.pf-col-left, .pf-col-main { display:flex; flex-direction:column; gap:12px; }
.pf-card { background:#fff; border-radius:14px; padding:16px 18px; box-shadow:0 1px 6px rgba(0,0,0,.05); border:1px solid #F1F5F9; }
.pf-card-title { font-size:.7rem; font-weight:700; color:#0F172A; text-transform:uppercase; letter-spacing:.08em; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between; }
.pf-card-title-edit { font-size:.65rem; color:var(--text); font-weight:600; cursor:pointer; text-transform:none; letter-spacing:0; }
.pf-tags { display:flex; flex-wrap:wrap; gap:5px; }
.pf-tag { padding:4px 10px; border-radius:50px; font-size:.71rem; font-weight:600; background:#F8FAFC; color:#475569; border:1px solid #E2E8F0; display:inline-flex; align-items:center; gap:6px; }
.pf-link-icons { display:flex; gap:8px; flex-wrap:wrap; }
.pf-link-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:.85rem; color:#fff; text-decoration:none; font-family:inherit; cursor:pointer; }
.pf-link-icon.placeholder-link { opacity:1; cursor:pointer; }
.pf-vlist { display:flex; flex-direction:column; gap:7px; }
.pf-vitem { display:flex; align-items:center; gap:8px; padding:7px 10px; background:#F8FAFC; border-radius:9px; }
.pf-vicon { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:.76rem; }
.pf-vlabel { flex:1; font-size:.75rem; color:#374151; font-weight:500; }
.pf-vcheck { color:#22C55E; font-size:.75rem; }
.pf-plist { display:flex; flex-direction:column; gap:9px; }
.pf-prow { display:flex; justify-content:space-between; margin-bottom:3px; }
.pf-plabel { font-size:.74rem; font-weight:600; color:#374151; }
.pf-ppct { font-size:.7rem; color:#94A3B8; }
.pf-pbar { background:#F1F5F9; border-radius:99px; height:5px; overflow:hidden; }
.pf-pfill { height:100%; border-radius:99px; background:linear-gradient(90deg,#111827,#374151); }
.pf-tab-bar { display:flex; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.05); border:1px solid #F1F5F9; padding:4px; }
.pf-tab-btn { flex:1; padding:9px 6px; font-size:.78rem; font-weight:600; cursor:pointer; border:none; background:transparent; color:#94A3B8; border-radius:10px; }
.pf-tab-btn.active { background:linear-gradient(135deg,#111827,#374151); color:#fff; }
.pf-rec-link-box { background:linear-gradient(135deg,#f8fafc,#f1f5f9); border:1.5px solid var(--border); border-radius:12px; padding:16px; display:flex; gap:14px; align-items:flex-start; }
.pf-rec-link-icon { width:42px; height:42px; border-radius:12px; flex-shrink:0; background:linear-gradient(135deg,#111827,#374151); display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; }
.pf-rec-link-copy { display:flex; align-items:center; gap:8px; background:#fff; border:1.5px solid var(--border); border-radius:9px; padding:7px 12px; margin-top:10px; }
.pf-rec-link-url { font-size:.72rem; color:#374151; flex:1; }
.pf-pending-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; border:1px solid #E2E8F0; background:#FAFAFA; }
.pf-pending-avatar { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.74rem; color:#fff; flex-shrink:0; }
.pf-status-pill { font-size:.65rem; font-weight:700; padding:3px 9px; border-radius:50px; white-space:nowrap; }
.pf-rec-card { background:#FAFAFA; border:1px solid #E2E8F0; border-radius:13px; padding:14px 16px; position:relative; }
.pf-rec-card.pending-approval { background:#FFFBEB; border:1.5px dashed #FDE68A; }
.pf-rec-author { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.pf-rec-author-av { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.8rem; color:#fff; flex-shrink:0; }
.pf-rec-name { font-size:.84rem; font-weight:700; color:#0F172A; }
.pf-rec-role { font-size:.72rem; color:#94A3B8; }
.pf-rec-stars { color:#F59E0B; font-size:.75rem; margin-left:auto; letter-spacing:1px; }
.pf-rec-text { font-size:.81rem; color:#4B5563; line-height:1.65; font-style:italic; }
.pf-rec-actions { display:flex; gap:7px; margin-top:12px; }
.pf-rec-approve { flex:1; padding:8px; border-radius:8px; border:none; background:#22C55E; color:#fff; font-size:.74rem; font-weight:700; }
.pf-rec-decline { padding:8px 14px; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#64748B; font-size:.74rem; }
.pf-search-bar { display:flex; align-items:center; gap:8px; border:1.5px solid #E2E8F0; border-radius:11px; padding:9px 14px; background:#fff; }
.pf-search-bar input { border:none; outline:none; font-family:'Inter',sans-serif; font-size:.82rem; flex:1; color:#0F172A; background:transparent; }
.pf-filter-pills { display:flex; gap:6px; margin-top:8px; }
.pf-filter-pill { padding:5px 14px; border-radius:50px; font-size:.72rem; font-weight:600; border:1.5px solid #E2E8F0; background:#fff; color:#64748B; }
.pf-filter-pill.active { background:#111827; color:#fff; border-color:#111827; }
.pf-person-row { display:flex; align-items:center; gap:10px; padding:9px 12px; border:1px solid #F1F5F9; border-radius:11px; background:#fff; }
.pf-person-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.78rem; color:#fff; }
.pf-person-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
.pf-person-name { font-size:.82rem; font-weight:700; color:#0F172A; }
.pf-person-role { font-size:.7rem; color:#94A3B8; margin-top:1px; }
.pf-type-pill { font-size:.64rem; font-weight:700; padding:2px 8px; border-radius:50px; white-space:nowrap; }
.pf-follow-btn { padding:6px 14px; border-radius:50px; font-size:.72rem; font-weight:700; border:1.5px solid #E2E8F0; background:#fff; color:#64748B; }
.pf-follow-btn.following { border-color:#111827; color:#111827; }
.pf-about-intro { font-size:.83rem; color:#475569; line-height:1.75; margin-bottom:10px; }
.pf-about-points { display:flex; flex-direction:column; gap:6px; }
.pf-about-point { display:flex; align-items:flex-start; gap:8px; font-size:.8rem; color:#64748B; line-height:1.55; }
.pf-about-point i { color:#111827; font-size:.7rem; margin-top:3px; }
.pf-exp-list { display:flex; flex-direction:column; }
.pf-exp-row { display:flex; gap:14px; align-items:flex-start; padding:12px 0; border-bottom:1px solid #F8FAFC; }
.pf-exp-logo { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.88rem; color:#fff; flex-shrink:0; }
.pf-exp-title { font-size:.84rem; font-weight:700; color:#0F172A; }
.pf-exp-sub { font-size:.75rem; color:#64748B; }
.pf-exp-date { font-size:.71rem; color:#94A3B8; margin-top:3px; }
.pf-portfolio-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:9px; }
.pf-pthumb { border-radius:11px; overflow:hidden; aspect-ratio:1; position:relative; }
.pf-pthumb-inner { width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:2.2rem; }
.pf-pthumb-label { position:absolute; bottom:0; left:0; right:0; padding:5px 8px; background:linear-gradient(to top, rgba(0,0,0,.5), transparent); font-size:.62rem; color:#fff; font-weight:600; }
.pf-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(6px); }
.pf-overlay.open { display:flex; }
.pf-modal-box { background:#fff; border-radius:22px; width:620px; max-width:95vw; max-height:90vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 30px 70px rgba(0,0,0,.22); }
.pf-mini-modal { width:420px; max-width:92vw; }
.pf-avatar-grid { display:grid; grid-template-columns:repeat(8,minmax(0,1fr)); gap:10px; margin-top:10px; }
.pf-avatar-option { border:2px solid #d1d5db; border-radius:999px; width:56px; height:56px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.4rem; background:linear-gradient(135deg,#e5e7eb,#cbd5e1); }
.pf-avatar-option.active { border-color:#111827; box-shadow:0 0 0 1px #111827 inset; }
.pf-avatar-preview { width:86px; height:86px; border-radius:999px; border:3px solid #e5e7eb; display:flex; align-items:center; justify-content:center; font-size:2rem; margin-top:14px; }
.pf-modal-head { display:flex; align-items:center; justify-content:space-between; padding:18px 24px 14px; border-bottom:1px solid #F1F5F9; }
.pf-modal-head-title { font-size:.95rem; font-weight:800; color:#0F172A; }
.pf-modal-head-sub { font-size:.72rem; color:#94A3B8; margin-top:1px; }
.pf-modal-x { width:32px; height:32px; border-radius:50%; border:none; background:#F1F5F9; cursor:pointer; }
.pf-modal-scroll { overflow-y:auto; padding:20px 24px; flex:1; }
.pf-modal-foot { display:flex; gap:8px; justify-content:flex-end; padding:14px 24px 18px; border-top:1px solid #F1F5F9; }
.pf-empty-link-copy { font-size:.84rem; color:#475569; line-height:1.7; }
.pf-field { margin-bottom:12px; }
.pf-field label { display:block; font-size:.76rem; font-weight:600; color:#374151; margin-bottom:4px; }
.pf-field input, .pf-field select, .pf-field textarea { width:100%; padding:9px 13px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:.83rem; color:#0F172A; outline:none; font-family:'Inter',sans-serif; background:#FAFAFA; }
.pf-field-2col { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.pf-cancel-btn { padding:9px 22px; border-radius:50px; border:1.5px solid #E2E8F0; background:#fff; font-size:.8rem; font-weight:600; cursor:pointer; color:#374151; }
.pf-save-btn { padding:9px 26px; border-radius:50px; border:none; background:linear-gradient(135deg,#111827,#374151); color:#fff; font-size:.8rem; font-weight:700; cursor:pointer; }
.pf-chip-action { background:none; border:none; color:#6b7280; cursor:pointer; font-size:.78rem; }
.pf-upload-input { margin-top:8px; }
@media (max-width: 980px) {
  .pf-body { grid-template-columns:1fr; }
  .pf-header { margin:0 12px; margin-top:-24px; }
  .pf-social-strip { padding:12px 12px 4px; }
  .pf-body { padding:16px 12px 24px; }
  .pf-header-top { flex-wrap:wrap; align-items:flex-start; }
  .pf-header-actions { width:100%; margin-left:0; }
  .pf-meta-row { grid-template-columns:1fr; gap:10px; }
}
</style>

<?php if ($profileErrors): ?>
  <div class="error-msg" style="margin:0 20px 12px;">
    <?php echo htmlspecialchars(implode(' ', $profileErrors)); ?>
  </div>
<?php elseif ($profileSuccess): ?>
  <div class="success-banner" style="margin:0 20px 12px;">
    <?php echo htmlspecialchars($profileSuccess); ?>
  </div>
<?php endif; ?>

<div class="pf-cover<?php echo $coverIsImage ? ' custom-cover' : ''; ?>" id="pf-cover-bg" onclick="openCoverModal()" style="<?php echo $coverBackgroundStyle; ?>">
  <div class="pf-cover-overlay"><i class="fas fa-camera"></i> Change Cover Photo</div>
</div>

<div class="pf-header">
  <div class="pf-header-top">
    <div class="pf-avatar-wrap" onclick="openAvatarModal()">
      <div class="pf-avatar">
        <?php if ($profilePicturePath !== ''): ?>
          <img src="<?php echo htmlspecialchars($profilePicturePath); ?>" alt="Profile picture">
        <?php elseif ($avatarPresetDisplay !== null): ?>
          <div class="pf-avatar-emoji" style="background:<?php echo htmlspecialchars($avatarPresetDisplay['bg']); ?>;"><?php echo htmlspecialchars($avatarPresetDisplay['emoji']); ?></div>
        <?php else: ?>
          <?php echo htmlspecialchars($initials); ?>
        <?php endif; ?>
        <div class="pf-avatar-edit-layer"><i class="fas fa-camera"></i><span>Change</span></div>
      </div>
      <div class="pf-online-dot"></div>
    </div>
    <div class="pf-header-info">
      <div class="pf-name"><?php echo htmlspecialchars($fullName); ?></div>
      <div class="pf-role-line">
        <span><?php echo htmlspecialchars(($student['headline'] ?? ($student['program'] ?? 'Student'))); ?></span>
        <div class="pf-role-dot"></div>
        <span>SkillHive</span>
        <div class="pf-role-dot"></div>
        <span style="display:flex;align-items:center;gap:4px"><i class="fas fa-map-marker-alt" style="font-size:.64rem;color:#6B7280"></i> <?php echo htmlspecialchars($student['preferred_industry'] ?? 'Quezon City, Philippines'); ?></span>
      </div>
    </div>
    <div class="pf-header-actions">
      <button class="pf-btn-edit" onclick="openEditProfile()"><i class="fas fa-pen" style="font-size:.68rem"></i> Edit Profile</button>
      <button class="pf-btn-hire" type="button">Hire Me</button>
    </div>
  </div>

  <div class="pf-meta-stack">
    <div class="pf-meta-row">
      <div class="pf-meta-item"><i class="fas fa-building"></i><span><?php echo htmlspecialchars($student['department'] ?? 'Department not set'); ?></span></div>
      <div class="pf-meta-item"><i class="fas fa-graduation-cap"></i><span><?php echo htmlspecialchars($student['program'] ?? 'Program not set'); ?></span></div>
      <div class="pf-meta-item"><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($student['student_number'] ?? 'Student number not set'); ?></span></div>
    </div>
    <div class="pf-meta-row">
      <div class="pf-meta-stat"><div class="pf-meta-stat-val"><?php echo count($studentSkills); ?></div><div class="pf-meta-stat-lbl">Skills</div></div>
      <div class="pf-meta-stat clickable" id="pfFollowingShortcut" onclick="openFollowingPanel()"><div class="pf-meta-stat-val" id="pfHeaderFollowingCount"><?php echo $followingCount; ?></div><div class="pf-meta-stat-lbl">Following</div></div>
      <div class="pf-meta-stat clickable" id="pfFollowersShortcut" onclick="openFollowersPanel()"><div class="pf-meta-stat-val" id="pfHeaderFollowersCount"><?php echo $followersCount; ?></div><div class="pf-meta-stat-lbl">Followers</div></div>
    </div>
  </div>
</div>

<div class="pf-social-strip">
  <?php if ($googleUrl !== ''): ?>
    <a class="pf-soc-chip" style="color:#4285F4;border-color:#DBEAFE" href="<?php echo htmlspecialchars($googleUrl); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-google"></i>Google</a>
  <?php else: ?>
    <button class="pf-soc-chip placeholder-link" style="color:#4285F4;border-color:#DBEAFE" type="button" onclick="openMissingLinkModal('Google')" title="Google link not added"><i class="fab fa-google"></i>Google</button>
  <?php endif; ?>
  <?php if ($gmailUrl !== ''): ?>
    <a class="pf-soc-chip" style="color:#EA4335;border-color:#FEE2E2" href="<?php echo htmlspecialchars($gmailUrl); ?>"><i class="fas fa-envelope"></i>Gmail</a>
  <?php else: ?>
    <button class="pf-soc-chip placeholder-link" style="color:#EA4335;border-color:#FEE2E2" type="button" onclick="openMissingLinkModal('Gmail')" title="Gmail link not added"><i class="fas fa-envelope"></i>Gmail</button>
  <?php endif; ?>
  <?php if ($discordUrl !== ''): ?>
    <a class="pf-soc-chip" style="color:#5865F2;border-color:#E0E7FF" href="<?php echo htmlspecialchars($discordUrl); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-discord"></i>Discord</a>
  <?php else: ?>
    <button class="pf-soc-chip placeholder-link" style="color:#5865F2;border-color:#E0E7FF" type="button" onclick="openMissingLinkModal('Discord')" title="Discord link not added"><i class="fab fa-discord"></i>Discord</button>
  <?php endif; ?>
</div>

<div class="pf-body">
  <div class="pf-col-left">
    <div class="pf-card">
      <div class="pf-card-title">Skills <span class="pf-card-title-edit" onclick="toggleSkillForm()"><i class="fas fa-pen"></i> Edit</span></div>
      <div class="pf-tags" id="pf-disp-skills">
        <?php if (!$studentSkills): ?>
          <span class="pf-tag">No skills yet</span>
        <?php else: ?>
          <?php foreach ($studentSkills as $skill): ?>
            <span class="pf-tag">
              <?php echo htmlspecialchars($skill['skill_name']); ?>
              <form method="post" style="display:inline;margin:0;">
                <input type="hidden" name="action" value="remove_skill">
                <input type="hidden" name="skill_id" value="<?php echo (int) $skill['skill_id']; ?>">
                <button class="pf-chip-action" type="submit">✕</button>
              </form>
            </span>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form method="post" id="addSkillForm" style="display:none;margin-top:10px;">
        <input type="hidden" name="action" value="add_skill">
        <div class="pf-field">
          <label>Skill</label>
          <select name="skill_id" required>
            <option value="">Select skill</option>
            <?php foreach ($availableSkills as $skill): ?>
              <option value="<?php echo (int) $skill['skill_id']; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pf-field">
          <label>Level</label>
          <select name="skill_level" required>
            <option value="Beginner">Beginner</option>
            <option value="Intermediate">Intermediate</option>
            <option value="Advanced">Advanced</option>
          </select>
        </div>
        <button class="pf-save-btn" type="submit">Add Skill</button>
      </form>
    </div>

    <div class="pf-card">
      <div class="pf-card-title">Portfolio Links</div>
      <div class="pf-link-icons">
        <?php if ($dribbbleUrl !== ''): ?>
          <a class="pf-link-icon" style="background:#E12B56" href="<?php echo htmlspecialchars($dribbbleUrl); ?>" target="_blank" rel="noopener noreferrer" title="Open Dribbble"><i class="fab fa-dribbble"></i></a>
        <?php else: ?>
          <button class="pf-link-icon placeholder-link" style="background:#E12B56;border:none" type="button" onclick="openMissingLinkModal('Dribbble')" title="Dribbble link not added"><i class="fab fa-dribbble"></i></button>
        <?php endif; ?>
        <?php if ($behanceUrl !== ''): ?>
          <a class="pf-link-icon" style="background:#0057FF" href="<?php echo htmlspecialchars($behanceUrl); ?>" target="_blank" rel="noopener noreferrer" title="Open Behance"><i class="fab fa-behance"></i></a>
        <?php else: ?>
          <button class="pf-link-icon placeholder-link" style="background:#0057FF;border:none" type="button" onclick="openMissingLinkModal('Behance')" title="Behance link not added"><i class="fab fa-behance"></i></button>
        <?php endif; ?>
        <?php if ($portfolioUrl !== ''): ?>
          <a class="pf-link-icon" style="background:#14A800" href="<?php echo htmlspecialchars($portfolioUrl); ?>" target="_blank" rel="noopener noreferrer" title="Open Portfolio"><i class="fas fa-briefcase"></i></a>
        <?php else: ?>
          <button class="pf-link-icon placeholder-link" style="background:#14A800;border:none" type="button" onclick="openMissingLinkModal('Portfolio')" title="Portfolio link not added"><i class="fas fa-briefcase"></i></button>
        <?php endif; ?>
        <?php if ($linkedinUrl !== ''): ?>
          <a class="pf-link-icon" style="background:#0A66C2" href="<?php echo htmlspecialchars($linkedinUrl); ?>" target="_blank" rel="noopener noreferrer" title="Open LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        <?php else: ?>
          <button class="pf-link-icon placeholder-link" style="background:#0A66C2;border:none" type="button" onclick="openMissingLinkModal('LinkedIn')" title="LinkedIn link not added"><i class="fab fa-linkedin-in"></i></button>
        <?php endif; ?>
        <?php if ($githubUrl !== ''): ?>
          <a class="pf-link-icon" style="background:#333" href="<?php echo htmlspecialchars($githubUrl); ?>" target="_blank" rel="noopener noreferrer" title="Open GitHub"><i class="fab fa-github"></i></a>
        <?php else: ?>
          <button class="pf-link-icon placeholder-link" style="background:#333;border:none" type="button" onclick="openMissingLinkModal('GitHub')" title="GitHub link not added"><i class="fab fa-github"></i></button>
        <?php endif; ?>
      </div>
    </div>

    <div class="pf-card">
      <div class="pf-card-title">Resume</div>
      <?php if ($resumeFile !== ''): ?>
        <div class="pf-vitem">
          <div class="pf-vicon" style="background:#FFF7ED"><i class="fas fa-file-pdf" style="color:#F97316"></i></div>
          <span class="pf-vlabel"><?php echo htmlspecialchars($resumeFile); ?></span>
          <a href="<?php echo htmlspecialchars($resumePath); ?>" target="_blank" class="pf-vcheck">View</a>
        </div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="pf-upload-input">
        <input type="hidden" name="action" value="upload_resume">
        <input type="file" name="resume" accept=".pdf,.doc,.docx" required>
        <button class="pf-save-btn" style="margin-top:8px;" type="submit">Upload Resume</button>
      </form>
    </div>

    <div class="pf-card">
      <div class="pf-card-title">Proficiency</div>
      <div class="pf-plist">
        <?php foreach ($studentSkills as $skill): ?>
          <?php $percent = $levelPercent[$skill['skill_level']] ?? 45; ?>
          <div class="pf-pitem">
            <div class="pf-prow"><span class="pf-plabel"><?php echo htmlspecialchars($skill['skill_name']); ?></span><span class="pf-ppct"><?php echo $percent; ?>%</span></div>
            <div class="pf-pbar"><div class="pf-pfill" style="width:<?php echo $percent; ?>%"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="pf-col-main">
    <div class="pf-tab-bar">
      <button class="pf-tab-btn active" onclick="pfTab(this,'pf-bg')">Background</button>
      <button class="pf-tab-btn" onclick="pfTab(this,'pf-recs')">Recommendations</button>
    </div>

    <div id="pf-bg" style="display:flex;flex-direction:column;gap:12px;">
      <div class="pf-card">
        <div class="pf-card-title">About Me <span class="pf-card-title-edit" onclick="openEditProfile()"><i class="fas fa-pen"></i> Edit</span></div>
        <p class="pf-about-intro"><?php echo htmlspecialchars($aboutIntro); ?></p>
        <div class="pf-about-points">
          <?php foreach ($aboutPoints as $point): ?>
            <div class="pf-about-point"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($point); ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pf-card">
        <div class="pf-card-title">Experience <span class="pf-card-title-edit" onclick="openEditProfile()"><i class="fas fa-pen"></i> Edit</span></div>
        <div class="pf-exp-list">
          <?php $expGradients = ['linear-gradient(135deg,#4285F4,#34A853)', 'linear-gradient(135deg,#111827,#374151)', 'linear-gradient(135deg,#0EA5E9,#6366F1)', 'linear-gradient(135deg,#10B981,#06B6D4)']; ?>
          <?php foreach ($experienceEntries as $index => $entry): ?>
            <?php $logoChar = strtoupper(substr((string) $entry['title'], 0, 1)); ?>
            <div class="pf-exp-row"><div class="pf-exp-logo" style="background:<?php echo $expGradients[$index % count($expGradients)]; ?>"><?php echo htmlspecialchars($logoChar !== '' ? $logoChar : 'E'); ?></div><div class="pf-exp-body"><div class="pf-exp-title"><?php echo htmlspecialchars($entry['title']); ?></div><div class="pf-exp-sub"><?php echo htmlspecialchars($entry['subtitle']); ?></div><div class="pf-exp-date"><?php echo htmlspecialchars($entry['date']); ?></div></div></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pf-card">
        <div class="pf-card-title">Portfolio <span class="pf-card-title-edit" onclick="openEditProfile()"><i class="fas fa-pen"></i> Edit</span></div>
        <div class="pf-portfolio-grid">
          <?php foreach ($portfolioEntries as $index => $item): ?>
            <div class="pf-pthumb"><div class="pf-pthumb-inner" style="background:<?php echo $portfolioGradients[$index % count($portfolioGradients)]; ?>"><?php echo htmlspecialchars($item['emoji']); ?></div><div class="pf-pthumb-label"><?php echo htmlspecialchars($item['label']); ?></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div id="pf-recs" style="display:none;flex-direction:column;gap:12px;">
      <div class="pf-card pf-rec-link-box">
        <div class="pf-rec-link-icon">💬</div>
        <div style="flex:1">
          <div style="font-weight:800;font-size:.88rem;color:#0F172A;margin-bottom:3px">How do others recommend you?</div>
          <div style="font-size:.77rem;color:#64748B;line-height:1.6">Share your link with supervisors, mentors, or classmates so they can send recommendations.</div>
          <div class="pf-rec-link-copy"><i class="fas fa-link" style="color:#111827;font-size:.75rem"></i><span class="pf-rec-link-url">skillhive.app/recommend/<?php echo rawurlencode(strtolower(str_replace(' ', '-', $fullName))); ?></span></div>
        </div>
      </div>

      <div class="pf-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div><div class="pf-card-title" style="margin-bottom:2px">Request a Recommendation</div><div style="font-size:.73rem;color:#94A3B8">Ask someone you've worked with</div></div>
          <button class="pf-save-btn" type="button" style="padding:7px 16px;font-size:.74rem"><i class="fas fa-paper-plane"></i> Send Request</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <div class="pf-pending-item"><div class="pf-pending-avatar" style="background:linear-gradient(135deg,#F59E0B,#EF4444)">DP</div><div style="flex:1"><div style="font-size:.82rem;font-weight:600;color:#0F172A">David Park</div><div style="font-size:.7rem;color:#94A3B8">Talent Lead, Google · 2 days ago</div></div><span class="pf-status-pill" style="background:#FEF3C7;color:#D97706">⏳ Pending</span></div>
        </div>
      </div>

      <div class="pf-card">
        <div class="pf-card-title">Received Recommendations</div>
        <div style="display:flex;flex-direction:column;gap:10px">
          <div class="pf-rec-card">
            <div class="pf-rec-author"><div class="pf-rec-author-av" style="background:linear-gradient(135deg,#4F46E5,#06B6D4)">MR</div><div><div class="pf-rec-name">Ma. Rivera</div><div class="pf-rec-role">OJT Adviser · UP Diliman</div></div><div class="pf-rec-stars">★★★★★</div></div>
            <p class="pf-rec-text">"Consistently demonstrated strong initiative and technical depth during internship preparation."</p>
          </div>
          <div class="pf-rec-card pending-approval">
            <div class="pf-rec-author"><div class="pf-rec-author-av" style="background:linear-gradient(135deg,#F59E0B,#EF4444)">DP</div><div><div class="pf-rec-name">David Park</div><div class="pf-rec-role">Talent Lead · Google PH</div></div><div class="pf-rec-stars">★★★★★</div></div>
            <p class="pf-rec-text">"An outstanding intern candidate, creative and reliable."</p>
            <div class="pf-rec-actions"><button class="pf-rec-approve" type="button">✓ Approve & Make Public</button><button class="pf-rec-decline" type="button">Decline</button></div>
          </div>
        </div>
      </div>
    </div>

    <div id="pf-following" style="display:none;flex-direction:column;gap:12px;">
      <div class="pf-card">
        <div class="pf-card-title" style="margin-bottom:10px">Find People to Follow</div>
        <div class="pf-search-bar"><i class="fas fa-search"></i><input type="text" id="pfPeopleSearch" placeholder="Search by name, skill, or school…" oninput="filterPeopleRows()"></div>
        <div class="pf-filter-pills">
          <button class="pf-filter-pill active" type="button" onclick="setPeopleFilter('all', this)">All</button>
          <button class="pf-filter-pill" type="button" onclick="setPeopleFilter('student', this)">Students</button>
          <button class="pf-filter-pill" type="button" onclick="setPeopleFilter('employer', this)">Employers</button>
          <button class="pf-filter-pill" type="button" onclick="setPeopleFilter('adviser', this)">Advisers</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:7px;margin-top:10px" id="pfPeopleRows">
          <?php foreach ($discoverUsers as $u): ?>
            <?php
              $uRole = (string) $u['role'];
              $uId = (int) $u['id'];
              $uName = (string) ($u['display_name'] ?? 'User');
              $uHeadline = (string) ($u['headline'] ?? '');
              $uSubtitle = (string) ($u['subtitle'] ?? '');
              $uAvatar = trim((string) ($u['avatar_file'] ?? ''));
              $uInitials = strtoupper(substr($uName, 0, 1));
              $avatarUrl = '';
              if ($uAvatar !== '') {
                  if ($uRole === 'student') {
                      $avatarUrl = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($uAvatar);
                  } elseif ($uRole === 'employer') {
                      $avatarUrl = $baseUrl . '/assets/backend/uploads/company/' . rawurlencode($uAvatar);
                  }
              }
            ?>
            <div class="pf-person-row" data-role="<?php echo htmlspecialchars($uRole); ?>" data-search="<?php echo htmlspecialchars(strtolower($uName . ' ' . $uHeadline . ' ' . $uSubtitle)); ?>">
              <div class="pf-person-avatar" style="background:linear-gradient(135deg,#111827,#374151)">
                <?php if ($avatarUrl !== ''): ?>
                  <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar">
                <?php else: ?>
                  <?php echo htmlspecialchars($uInitials); ?>
                <?php endif; ?>
              </div>
              <div style="flex:1">
                <div class="pf-person-name"><?php echo htmlspecialchars($uName); ?></div>
                <div class="pf-person-role"><?php echo htmlspecialchars($uHeadline); ?><?php echo $uSubtitle !== '' ? ' · ' . htmlspecialchars($uSubtitle) : ''; ?><?php echo ' · ID: ' . (int) $uId; ?></div>
              </div>
              <span class="pf-type-pill" style="background:#F3F4F6;color:#111827"><?php echo htmlspecialchars(ucfirst($uRole)); ?></span>
              <form method="post" class="js-follow-form" style="margin:0;">
                <input type="hidden" name="action" value="<?php echo !empty($u['is_following']) ? 'unfollow_user' : 'follow_user'; ?>">
                <input type="hidden" name="target_role" value="<?php echo htmlspecialchars($uRole); ?>">
                <input type="hidden" name="target_id" value="<?php echo $uId; ?>">
                <button class="pf-follow-btn<?php echo !empty($u['is_following']) ? ' following' : ''; ?>" type="submit"><?php echo !empty($u['is_following']) ? 'Following' : '+ Follow'; ?></button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pf-card" id="pfFollowingCard">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div><div class="pf-card-title" style="margin-bottom:1px">People You Follow</div><div style="font-size:.72rem;color:#94A3B8">Their updates appear in your feed</div></div>
          <span id="pfFollowingCountBadge" style="font-size:.7rem;font-weight:700;background:#F3F4F6;color:#111827;padding:3px 10px;border-radius:50px"><?php echo $followingCount; ?> following</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:7px" id="pfFollowingRows">
          <?php if (!$followingUsers): ?>
            <div class="pf-person-role">You are not following anyone yet.</div>
          <?php else: ?>
            <?php foreach ($followingUsers as $u): ?>
              <?php
                $uRole = (string) $u['role'];
                $uId = (int) $u['id'];
                $uName = (string) ($u['display_name'] ?? 'User');
                $uHeadline = (string) ($u['headline'] ?? '');
                $uSubtitle = (string) ($u['subtitle'] ?? '');
                $uAvatar = trim((string) ($u['avatar_file'] ?? ''));
                $uInitials = strtoupper(substr($uName, 0, 1));
                $avatarUrl = '';
                if ($uAvatar !== '') {
                    if ($uRole === 'student') {
                        $avatarUrl = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($uAvatar);
                    } elseif ($uRole === 'employer') {
                        $avatarUrl = $baseUrl . '/assets/backend/uploads/company/' . rawurlencode($uAvatar);
                    }
                }
              ?>
              <div class="pf-person-row">
                <div class="pf-person-avatar" style="background:linear-gradient(135deg,#10B981,#06B6D4)">
                  <?php if ($avatarUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar">
                  <?php else: ?>
                    <?php echo htmlspecialchars($uInitials); ?>
                  <?php endif; ?>
                </div>
                <div style="flex:1"><div class="pf-person-name"><?php echo htmlspecialchars($uName); ?></div><div class="pf-person-role"><?php echo htmlspecialchars($uHeadline); ?><?php echo $uSubtitle !== '' ? ' · ' . htmlspecialchars($uSubtitle) : ''; ?><?php echo ' · ID: ' . (int) $uId; ?></div></div>
                <span class="pf-type-pill" style="background:#ECFDF5;color:#059669"><?php echo htmlspecialchars(ucfirst($uRole)); ?></span>
                <form method="post" class="js-follow-form" style="margin:0;">
                  <input type="hidden" name="action" value="unfollow_user">
                  <input type="hidden" name="target_role" value="<?php echo htmlspecialchars($uRole); ?>">
                  <input type="hidden" name="target_id" value="<?php echo $uId; ?>">
                  <button class="pf-follow-btn following" type="submit">Following</button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <div id="pf-followers" style="display:none;flex-direction:column;gap:12px;">
      <div class="pf-card" id="pfFollowersCard">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div><div class="pf-card-title" style="margin-bottom:1px">People Following You</div><div style="font-size:.72rem;color:#94A3B8">These users follow your account</div></div>
          <span id="pfFollowersCountBadge" style="font-size:.7rem;font-weight:700;background:#F3F4F6;color:#111827;padding:3px 10px;border-radius:50px"><?php echo $followersCount; ?> followers</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:7px" id="pfFollowerRows">
          <?php if (!$followerUsers): ?>
            <div class="pf-person-role">No followers yet.</div>
          <?php else: ?>
            <?php foreach ($followerUsers as $u): ?>
              <?php
                $uRole = (string) $u['role'];
                $uId = (int) $u['id'];
                $uName = (string) ($u['display_name'] ?? 'User');
                $uHeadline = (string) ($u['headline'] ?? '');
                $uSubtitle = (string) ($u['subtitle'] ?? '');
                $uAvatar = trim((string) ($u['avatar_file'] ?? ''));
                $uInitials = strtoupper(substr($uName, 0, 1));
                $avatarUrl = '';
                if ($uAvatar !== '') {
                    if ($uRole === 'student') {
                        $avatarUrl = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($uAvatar);
                    } elseif ($uRole === 'employer') {
                        $avatarUrl = $baseUrl . '/assets/backend/uploads/company/' . rawurlencode($uAvatar);
                    }
                }
              ?>
              <div class="pf-person-row">
                <div class="pf-person-avatar" style="background:linear-gradient(135deg,#111827,#374151)">
                  <?php if ($avatarUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar">
                  <?php else: ?>
                    <?php echo htmlspecialchars($uInitials); ?>
                  <?php endif; ?>
                </div>
                <div style="flex:1"><div class="pf-person-name"><?php echo htmlspecialchars($uName); ?></div><div class="pf-person-role"><?php echo htmlspecialchars($uHeadline); ?><?php echo $uSubtitle !== '' ? ' · ' . htmlspecialchars($uSubtitle) : ''; ?><?php echo ' · ID: ' . (int) $uId; ?></div></div>
                <span class="pf-type-pill" style="background:#F3F4F6;color:#111827"><?php echo htmlspecialchars(ucfirst($uRole)); ?></span>
                <form method="post" class="js-follow-form" style="margin:0;">
                  <input type="hidden" name="action" value="<?php echo !empty($u['is_following']) ? 'unfollow_user' : 'follow_user'; ?>">
                  <input type="hidden" name="target_role" value="<?php echo htmlspecialchars($uRole); ?>">
                  <input type="hidden" name="target_id" value="<?php echo $uId; ?>">
                  <button class="pf-follow-btn<?php echo !empty($u['is_following']) ? ' following' : ''; ?>" type="submit"><?php echo !empty($u['is_following']) ? 'Following' : '+ Follow back'; ?></button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="pf-overlay" id="pf-avatar-overlay" onclick="if(event.target===this)closeAvatarModal()">
  <div class="pf-modal-box">
    <div class="pf-modal-head">
      <div>
        <div class="pf-modal-head-title">Change Profile Picture</div>
        <div class="pf-modal-head-sub">Select an avatar or upload an image.</div>
      </div>
      <button class="pf-modal-x" type="button" onclick="closeAvatarModal()"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <div class="pf-modal-scroll">
        <input type="hidden" name="action" value="update_avatar_style">
        <input type="hidden" id="pfAvatarPresetInput" name="avatar_preset" value="<?php echo htmlspecialchars($avatarPreset); ?>">
        <div class="pf-card-title" style="margin-bottom:8px">Select Your Avatar</div>
        <div class="pf-avatar-grid" id="pfAvatarGrid">
          <button class="pf-avatar-option" type="button" data-avatar="tech-girl" data-emoji="👩‍💻" style="background:linear-gradient(135deg,#A855F7,#60A5FA)">👩‍💻</button>
          <button class="pf-avatar-option" type="button" data-avatar="creative-boy" data-emoji="🧑‍🎨" style="background:linear-gradient(135deg,#F59E0B,#FBBF24)">🧑‍🎨</button>
          <button class="pf-avatar-option" type="button" data-avatar="mentor-man" data-emoji="👨‍🏫" style="background:linear-gradient(135deg,#22C55E,#38BDF8)">👨‍🏫</button>
          <button class="pf-avatar-option" type="button" data-avatar="student-boy" data-emoji="🧑‍🎓" style="background:linear-gradient(135deg,#6366F1,#A5B4FC)">🧑‍🎓</button>
          <button class="pf-avatar-option" type="button" data-avatar="astronaut" data-emoji="👨‍🚀" style="background:linear-gradient(135deg,#64748B,#93C5FD)">👨‍🚀</button>
          <button class="pf-avatar-option" type="button" data-avatar="coder-girl" data-emoji="👩‍💻" style="background:linear-gradient(135deg,#84CC16,#22C55E)">👩‍💻</button>
          <button class="pf-avatar-option" type="button" data-avatar="leader-girl" data-emoji="👩‍💼" style="background:linear-gradient(135deg,#F472B6,#A855F7)">👩‍💼</button>
          <button class="pf-avatar-option" type="button" data-avatar="intern-boy" data-emoji="🧑‍💼" style="background:linear-gradient(135deg,#2DD4BF,#38BDF8)">🧑‍💼</button>
        </div>

        <div class="pf-avatar-preview" id="pfAvatarPreview" style="<?php if ($avatarPresetDisplay !== null): ?>background:<?php echo htmlspecialchars($avatarPresetDisplay['bg']); ?><?php else: ?>background:linear-gradient(135deg,#E5E7EB,#CBD5E1)<?php endif; ?>"><?php echo htmlspecialchars($avatarPresetDisplay['emoji'] ?? strtoupper(substr($fullName, 0, 1))); ?></div>

        <div class="pf-cover-actions">
          <label class="pf-upload-cover-btn">
            <i class="fas fa-image"></i>
            + Image
            <input type="file" id="pfAvatarFileInput" name="profile_picture" accept=".jpg,.jpeg,.png,.webp">
          </label>
          <span style="font-size:.73rem;color:#6b7280;">Uploading an image will replace the avatar preset.</span>
        </div>
      </div>
      <div class="pf-modal-foot">
        <button type="button" class="pf-cancel-btn" onclick="closeAvatarModal()">Cancel</button>
        <button type="submit" class="pf-save-btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div class="pf-overlay" id="pf-cover-overlay" onclick="if(event.target===this)closeCoverModal()">
  <div class="pf-modal-box">
    <div class="pf-modal-head">
      <div>
        <div class="pf-modal-head-title">Change Cover Photo</div>
        <div class="pf-modal-head-sub">Pick a gradient or upload an image for your profile banner.</div>
      </div>
      <button class="pf-modal-x" type="button" onclick="closeCoverModal()"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <div class="pf-modal-scroll">
        <input type="hidden" name="action" value="update_cover_style">
        <input type="hidden" id="pfCoverGradientInput" name="cover_gradient" value="<?php echo htmlspecialchars($coverGradient); ?>">
        <div class="pf-card-title" style="margin-bottom:8px">Choose A Cover Style</div>
        <div class="pf-cover-grid" id="pfCoverGrid">
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#FDE047 0%,#38BDF8 100%)" style="background:linear-gradient(135deg,#FDE047 0%,#38BDF8 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#1D4ED8 0%,#22D3EE 100%)" style="background:linear-gradient(135deg,#1D4ED8 0%,#22D3EE 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#059669 0%,#34D399 100%)" style="background:linear-gradient(135deg,#059669 0%,#34D399 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#EF4444 0%,#F59E0B 100%)" style="background:linear-gradient(135deg,#EF4444 0%,#F59E0B 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#4338CA 0%,#6366F1 100%)" style="background:linear-gradient(135deg,#4338CA 0%,#6366F1 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#0EA5E9 0%,#38BDF8 100%)" style="background:linear-gradient(135deg,#0EA5E9 0%,#38BDF8 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#A21CAF 0%,#C084FC 100%)" style="background:linear-gradient(135deg,#A21CAF 0%,#C084FC 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#9333EA 0%,#3B82F6 100%)" style="background:linear-gradient(135deg,#9333EA 0%,#3B82F6 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#16A34A 0%,#86EFAC 100%)" style="background:linear-gradient(135deg,#16A34A 0%,#86EFAC 100%)"></button>
          <button class="pf-cover-option" type="button" data-gradient="linear-gradient(135deg,#111827 0%,#9CA3AF 100%)" style="background:linear-gradient(135deg,#111827 0%,#9CA3AF 100%)"></button>
        </div>

        <div class="pf-cover-preview" id="pfCoverPreview" style="<?php echo $coverPreviewStyle; ?>"></div>

        <div class="pf-cover-actions">
          <label class="pf-upload-cover-btn">
            <i class="fas fa-image"></i>
            + Image
            <input type="file" id="pfCoverFileInput" name="cover_photo" accept=".jpg,.jpeg,.png,.webp">
          </label>
          <span style="font-size:.73rem;color:#6b7280;">Uploading an image will replace the gradient cover.</span>
        </div>
      </div>
      <div class="pf-modal-foot">
        <button type="button" class="pf-cancel-btn" onclick="closeCoverModal()">Cancel</button>
        <button type="submit" class="pf-save-btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div class="pf-overlay" id="pf-link-overlay" onclick="if(event.target===this)closeMissingLinkModal()">
  <div class="pf-modal-box pf-mini-modal">
    <div class="pf-modal-head">
      <div>
        <div class="pf-modal-head-title" id="pfMissingLinkTitle">Link unavailable</div>
        <div class="pf-modal-head-sub">This profile does not have that link yet</div>
      </div>
      <button class="pf-modal-x" type="button" onclick="closeMissingLinkModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="pf-modal-scroll">
      <div class="pf-empty-link-copy" id="pfMissingLinkBody">This profile does not have this link yet.</div>
    </div>
    <div class="pf-modal-foot">
      <button type="button" class="pf-cancel-btn" onclick="closeMissingLinkModal()">Close</button>
      <button type="button" class="pf-save-btn" onclick="closeMissingLinkModal(); openEditProfile();">Add Link</button>
    </div>
  </div>
</div>

<div class="pf-overlay" id="pf-edit-overlay" onclick="if(event.target===this)closeEdit()">
  <div class="pf-modal-box">
    <div class="pf-modal-head">
      <div>
        <div class="pf-modal-head-title">Edit Profile</div>
        <div class="pf-modal-head-sub">Update your personal information</div>
      </div>
      <button class="pf-modal-x" onclick="closeEdit()"><i class="fas fa-times"></i></button>
    </div>

    <div class="pf-modal-scroll">
      <div class="pf-field" style="margin-bottom:16px;border-bottom:1px solid #F1F5F9;padding-bottom:14px;">
        <label style="font-weight:700;color:#0F172A;margin-bottom:8px;">Profile Media</label>
        <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <input type="hidden" name="action" value="update_media">
          <div>
            <label style="display:block;font-size:.72rem;color:#64748B;margin-bottom:4px;">Profile Picture</label>
            <input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp">
          </div>
          <div>
            <label style="display:block;font-size:.72rem;color:#64748B;margin-bottom:4px;">Cover Photo</label>
            <input type="file" name="cover_photo" accept=".jpg,.jpeg,.png,.webp">
          </div>
          <div style="grid-column:1 / span 2;">
            <button type="submit" class="pf-save-btn" style="width:auto;">Update Images</button>
          </div>
        </form>
      </div>

      <form method="post">
        <input type="hidden" name="action" value="update_profile">
        <div class="pf-field-2col">
          <div class="pf-field"><label>First Name</label><input name="first_name" value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required></div>
          <div class="pf-field"><label>Last Name</label><input name="last_name" value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required></div>
        </div>
        <div class="pf-field"><label>Program</label><input name="program" value="<?php echo htmlspecialchars($student['program'] ?? ''); ?>" required></div>
        <div class="pf-field"><label>Department</label><input name="department" value="<?php echo htmlspecialchars($student['department'] ?? ''); ?>" required></div>
        <div class="pf-field-2col">
          <div class="pf-field"><label>Year Level</label><input type="number" min="1" max="8" name="year_level" value="<?php echo (int) ($student['year_level'] ?? 1); ?>" required></div>
          <div class="pf-field">
            <label>Availability</label>
            <?php $availabilityVal = (string) ($student['availability_status'] ?? 'Available'); ?>
            <select name="availability_status" required>
              <option value="Available" <?php echo $availabilityVal === 'Available' ? 'selected' : ''; ?>>Available</option>
              <option value="Unavailable" <?php echo $availabilityVal === 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
              <option value="Currently Interning" <?php echo $availabilityVal === 'Currently Interning' ? 'selected' : ''; ?>>Currently Interning</option>
            </select>
          </div>
        </div>
        <div class="pf-field"><label>Preferred Industry</label><input name="preferred_industry" value="<?php echo htmlspecialchars($student['preferred_industry'] ?? ''); ?>"></div>
        <div class="pf-field" style="margin-top:18px;margin-bottom:8px;border-top:1px solid #F1F5F9;padding-top:16px;">
          <label style="font-weight:700;color:#0F172A;">Background</label>
        </div>
        <div class="pf-field">
          <label>About Me Intro</label>
          <textarea name="about_me_intro" rows="3" maxlength="600" placeholder="Write a short introduction about yourself."><?php echo htmlspecialchars((string) ($student['about_me_intro'] ?? '')); ?></textarea>
        </div>
        <div class="pf-field">
          <label>About Me Highlights (one per line)</label>
          <textarea name="about_me_points" rows="4" placeholder="Ability to lead projects&#10;Strong communication skills&#10;Problem-solver mindset"><?php echo htmlspecialchars((string) ($student['about_me_points'] ?? '')); ?></textarea>
        </div>
        <div class="pf-field">
          <label>Experience (one per line: Role | Organization | Date)</label>
          <textarea name="experience_entries" rows="4" placeholder="Internship Candidate | College of Informatics and Computing | Current&#10;Student Developer | BS Information Technology | Academic Projects"><?php echo htmlspecialchars($experienceTextAreaValue); ?></textarea>
        </div>
        <div class="pf-field">
          <label>Portfolio (one per line: Title | Emoji)</label>
          <textarea name="portfolio_entries" rows="4" placeholder="Home App UI | 🏠&#10;Design Kit | 🎨&#10;Case Study | 🧩"><?php echo htmlspecialchars($portfolioTextAreaValue); ?></textarea>
        </div>
        <div class="pf-field" style="margin-top:18px;margin-bottom:8px;border-top:1px solid #F1F5F9;padding-top:16px;">
          <label style="font-weight:700;color:#0F172A;">Social Links</label>
        </div>
        <div class="pf-field-2col">
          <div class="pf-field"><label>Google Link</label><input name="google_url" value="<?php echo htmlspecialchars($student['google_url'] ?? ''); ?>" placeholder="https://google.com/..." /></div>
          <div class="pf-field"><label>Gmail Address</label><input name="gmail_url" value="<?php echo htmlspecialchars(str_replace('mailto:', '', (string) ($student['gmail_url'] ?? ''))); ?>" placeholder="name@gmail.com" /></div>
        </div>
        <div class="pf-field"><label>Discord Link</label><input name="discord_url" value="<?php echo htmlspecialchars($student['discord_url'] ?? ''); ?>" placeholder="https://discord.gg/..." /></div>
        <div class="pf-field" style="margin-top:18px;margin-bottom:8px;border-top:1px solid #F1F5F9;padding-top:16px;">
          <label style="font-weight:700;color:#0F172A;">Portfolio Links</label>
        </div>
        <div class="pf-field-2col">
          <div class="pf-field"><label>Dribbble</label><input name="dribbble_url" value="<?php echo htmlspecialchars($student['dribbble_url'] ?? ''); ?>" placeholder="https://dribbble.com/..." /></div>
          <div class="pf-field"><label>Behance</label><input name="behance_url" value="<?php echo htmlspecialchars($student['behance_url'] ?? ''); ?>" placeholder="https://behance.net/..." /></div>
        </div>
        <div class="pf-field-2col">
          <div class="pf-field"><label>Portfolio Website</label><input name="portfolio_url" value="<?php echo htmlspecialchars($student['portfolio_url'] ?? ''); ?>" placeholder="https://yourportfolio.com" /></div>
          <div class="pf-field"><label>LinkedIn</label><input name="linkedin_url" value="<?php echo htmlspecialchars($student['linkedin_url'] ?? ''); ?>" placeholder="https://linkedin.com/in/..." /></div>
        </div>
        <div class="pf-field"><label>GitHub</label><input name="github_url" value="<?php echo htmlspecialchars($student['github_url'] ?? ''); ?>" placeholder="https://github.com/..." /></div>
        <div class="pf-modal-foot" style="padding:10px 0 0;border-top:none;">
          <button type="button" class="pf-cancel-btn" onclick="closeEdit()">Cancel</button>
          <button type="submit" class="pf-save-btn"><i class="fas fa-check" style="margin-right:6px"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function pfSetHeaderShortcut(panelId) {
  var followingShortcut = document.getElementById('pfFollowingShortcut');
  var followersShortcut = document.getElementById('pfFollowersShortcut');
  if (followingShortcut) followingShortcut.classList.remove('active-nav');
  if (followersShortcut) followersShortcut.classList.remove('active-nav');
  if (panelId === 'pf-following' && followingShortcut) followingShortcut.classList.add('active-nav');
  if (panelId === 'pf-followers' && followersShortcut) followersShortcut.classList.add('active-nav');
}

function pfShowPanel(id) {
  ['pf-bg','pf-recs','pf-following','pf-followers'].forEach(function (panelId) {
    var el = document.getElementById(panelId);
    if (!el) return;
    el.style.display = panelId === id ? 'flex' : 'none';
  });
  pfSetHeaderShortcut(id);
}

function pfTab(btn, id) {
  document.querySelectorAll('.pf-tab-btn').forEach(function (b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  pfShowPanel(id);
}

function applyAvatarPresetSelection(preset, emoji, bg) {
  var hidden = document.getElementById('pfAvatarPresetInput');
  var preview = document.getElementById('pfAvatarPreview');
  if (hidden) hidden.value = preset;
  if (preview) {
    preview.style.background = bg;
    preview.textContent = emoji;
  }
  document.querySelectorAll('#pfAvatarGrid .pf-avatar-option').forEach(function (opt) {
    var isActive = (opt.getAttribute('data-avatar') || '') === preset;
    opt.classList.toggle('active', isActive);
  });
}

function openAvatarModal() {
  var overlay = document.getElementById('pf-avatar-overlay');
  if (overlay) overlay.classList.add('open');
}

function closeAvatarModal() {
  var overlay = document.getElementById('pf-avatar-overlay');
  if (overlay) overlay.classList.remove('open');
}

function applyCoverGradientSelection(gradient) {
  var hidden = document.getElementById('pfCoverGradientInput');
  var preview = document.getElementById('pfCoverPreview');
  if (hidden) hidden.value = gradient;
  if (preview) preview.style.background = gradient;
  document.querySelectorAll('#pfCoverGrid .pf-cover-option').forEach(function (opt) {
    var isActive = (opt.getAttribute('data-gradient') || '') === gradient;
    opt.classList.toggle('active', isActive);
  });
}

function openCoverModal() {
  var overlay = document.getElementById('pf-cover-overlay');
  if (overlay) overlay.classList.add('open');
}

function closeCoverModal() {
  var overlay = document.getElementById('pf-cover-overlay');
  if (overlay) overlay.classList.remove('open');
}

function openEditProfile() {
  var overlay = document.getElementById('pf-edit-overlay');
  if (overlay) overlay.classList.add('open');
}

function openMissingLinkModal(label) {
  var overlay = document.getElementById('pf-link-overlay');
  var title = document.getElementById('pfMissingLinkTitle');
  var body = document.getElementById('pfMissingLinkBody');
  if (title) title.textContent = label + ' link unavailable';
  if (body) body.textContent = 'This profile does not have a ' + label + ' link yet.';
  if (overlay) overlay.classList.add('open');
}

function closeMissingLinkModal() {
  var overlay = document.getElementById('pf-link-overlay');
  if (overlay) overlay.classList.remove('open');
}

function closeEdit() {
  var overlay = document.getElementById('pf-edit-overlay');
  if (overlay) overlay.classList.remove('open');
}

document.addEventListener('DOMContentLoaded', function () {
  var avatarHidden = document.getElementById('pfAvatarPresetInput');
  var avatarCurrent = avatarHidden ? avatarHidden.value : '';
  if (avatarCurrent) {
    var activeBtn = document.querySelector('#pfAvatarGrid .pf-avatar-option[data-avatar="' + avatarCurrent + '"]');
    if (activeBtn) {
      applyAvatarPresetSelection(
        avatarCurrent,
        activeBtn.getAttribute('data-emoji') || '🙂',
        activeBtn.style.background || 'linear-gradient(135deg,#E5E7EB,#CBD5E1)'
      );
    }
  }

  document.querySelectorAll('#pfAvatarGrid .pf-avatar-option').forEach(function (btn) {
    btn.addEventListener('click', function () {
      applyAvatarPresetSelection(
        btn.getAttribute('data-avatar') || '',
        btn.getAttribute('data-emoji') || '🙂',
        btn.style.background || 'linear-gradient(135deg,#E5E7EB,#CBD5E1)'
      );
    });
  });

  var avatarFileInput = document.getElementById('pfAvatarFileInput');
  if (avatarFileInput) {
    avatarFileInput.addEventListener('change', function () {
      var preview = document.getElementById('pfAvatarPreview');
      var file = avatarFileInput.files && avatarFileInput.files[0] ? avatarFileInput.files[0] : null;
      if (!file || !preview) return;
      if (avatarHidden) avatarHidden.value = '';
      document.querySelectorAll('#pfAvatarGrid .pf-avatar-option').forEach(function (opt) {
        opt.classList.remove('active');
      });
      var reader = new FileReader();
      reader.onload = function (e) {
        preview.style.background = 'url(' + e.target.result + ') center / cover no-repeat';
        preview.textContent = '';
      };
      reader.readAsDataURL(file);
    });
  }

  var hidden = document.getElementById('pfCoverGradientInput');
  var current = hidden ? hidden.value : '';
  if (current) {
    applyCoverGradientSelection(current);
  }

  document.querySelectorAll('#pfCoverGrid .pf-cover-option').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var gradient = btn.getAttribute('data-gradient') || '';
      if (gradient) applyCoverGradientSelection(gradient);
    });
  });

  var fileInput = document.getElementById('pfCoverFileInput');
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var preview = document.getElementById('pfCoverPreview');
      var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!file || !preview) return;
      var reader = new FileReader();
      reader.onload = function (e) {
        preview.style.background = 'url(' + e.target.result + ') center / cover no-repeat';
      };
      reader.readAsDataURL(file);
    });
  }
});

function toggleSkillForm() {
  var form = document.getElementById('addSkillForm');
  if (!form) return;
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

var peopleFilter = 'all';
function setPeopleFilter(filter, button) {
  peopleFilter = filter;
  document.querySelectorAll('.pf-filter-pill').forEach(function (btn) { btn.classList.remove('active'); });
  button.classList.add('active');
  filterPeopleRows();
}

function filterPeopleRows() {
  var input = document.getElementById('pfPeopleSearch');
  var q = (input ? input.value : '').toLowerCase().trim();
  var rows = document.querySelectorAll('#pfPeopleRows .pf-person-row');
  rows.forEach(function (row) {
    var role = row.getAttribute('data-role') || '';
    var hay = row.getAttribute('data-search') || '';
    var roleOk = peopleFilter === 'all' || role === peopleFilter;
    var textOk = q === '' || hay.indexOf(q) !== -1;
    row.style.display = roleOk && textOk ? 'flex' : 'none';
  });
}

function openFollowingPanel() {
  document.querySelectorAll('.pf-tab-btn').forEach(function (b) { b.classList.remove('active'); });
  pfShowPanel('pf-following');
  var card = document.getElementById('pfFollowingCard');
  if (card) {
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function openFollowersPanel() {
  document.querySelectorAll('.pf-tab-btn').forEach(function (b) { b.classList.remove('active'); });
  pfShowPanel('pf-followers');
  var card = document.getElementById('pfFollowersCard');
  if (card) {
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function escapeHtml(text) {
  return String(text || '').replace(/[&<>"']/g, function (m) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
  });
}

function rolePillStyle(role) {
  if (role === 'student') return 'background:#F3F4F6;color:#111827';
  if (role === 'employer') return 'background:#ECFDF5;color:#059669';
  return 'background:#EFF6FF;color:#1D4ED8';
}

function avatarHtml(user, gradient) {
  var initials = (user.display_name || 'U').trim().charAt(0).toUpperCase();
  if (user.avatar_url) {
    return '<div class="pf-person-avatar" style="background:' + gradient + '"><img src="' + escapeHtml(user.avatar_url) + '" alt="Avatar"></div>';
  }
  return '<div class="pf-person-avatar" style="background:' + gradient + '">' + escapeHtml(initials) + '</div>';
}

function followFormHtml(user, forceFollowBack) {
  var action = user.is_following ? 'unfollow_user' : 'follow_user';
  var label = user.is_following ? 'Following' : (forceFollowBack ? '+ Follow back' : '+ Follow');
  var cls = 'pf-follow-btn' + (user.is_following ? ' following' : '');
  return '<form method="post" class="js-follow-form" style="margin:0;">'
    + '<input type="hidden" name="action" value="' + action + '">'
    + '<input type="hidden" name="target_role" value="' + escapeHtml(user.role) + '">'
    + '<input type="hidden" name="target_id" value="' + Number(user.id) + '">'
    + '<button class="' + cls + '" type="submit">' + label + '</button>'
    + '</form>';
}

function renderDiscoverRows(users) {
  var root = document.getElementById('pfPeopleRows');
  if (!root) return;
  if (!users.length) {
    root.innerHTML = '<div class="pf-person-role">No users found.</div>';
    return;
  }
  root.innerHTML = users.map(function (u) {
    var search = (u.display_name + ' ' + u.headline + ' ' + u.subtitle).toLowerCase();
    return '<div class="pf-person-row" data-role="' + escapeHtml(u.role) + '" data-search="' + escapeHtml(search) + '">'
      + avatarHtml(u, 'linear-gradient(135deg,#111827,#374151)')
      + '<div style="flex:1"><div class="pf-person-name">' + escapeHtml(u.display_name) + '</div><div class="pf-person-role">' + escapeHtml(u.headline) + (u.subtitle ? ' · ' + escapeHtml(u.subtitle) : '') + '</div></div>'
      + '<span class="pf-type-pill" style="' + rolePillStyle(u.role) + '">' + escapeHtml(u.role.charAt(0).toUpperCase() + u.role.slice(1)) + '</span>'
      + followFormHtml(u, false)
      + '</div>';
  }).join('');
  filterPeopleRows();
}

function renderFollowingRows(users) {
  var root = document.getElementById('pfFollowingRows');
  if (!root) return;
  if (!users.length) {
    root.innerHTML = '<div class="pf-person-role">You are not following anyone yet.</div>';
    return;
  }
  root.innerHTML = users.map(function (u) {
    return '<div class="pf-person-row">'
      + avatarHtml(u, 'linear-gradient(135deg,#10B981,#06B6D4)')
      + '<div style="flex:1"><div class="pf-person-name">' + escapeHtml(u.display_name) + '</div><div class="pf-person-role">' + escapeHtml(u.headline) + (u.subtitle ? ' · ' + escapeHtml(u.subtitle) : '') + '</div></div>'
      + '<span class="pf-type-pill" style="' + rolePillStyle(u.role) + '">' + escapeHtml(u.role.charAt(0).toUpperCase() + u.role.slice(1)) + '</span>'
      + followFormHtml(u, false)
      + '</div>';
  }).join('');
}

function renderFollowerRows(users) {
  var root = document.getElementById('pfFollowerRows');
  if (!root) return;
  if (!users.length) {
    root.innerHTML = '<div class="pf-person-role">No followers yet.</div>';
    return;
  }
  root.innerHTML = users.map(function (u) {
    return '<div class="pf-person-row">'
      + avatarHtml(u, 'linear-gradient(135deg,#111827,#374151)')
      + '<div style="flex:1"><div class="pf-person-name">' + escapeHtml(u.display_name) + '</div><div class="pf-person-role">' + escapeHtml(u.headline) + (u.subtitle ? ' · ' + escapeHtml(u.subtitle) : '') + '</div></div>'
      + '<span class="pf-type-pill" style="' + rolePillStyle(u.role) + '">' + escapeHtml(u.role.charAt(0).toUpperCase() + u.role.slice(1)) + '</span>'
      + followFormHtml(u, true)
      + '</div>';
  }).join('');
}

function updateFollowCounts(followingCount, followersCount) {
  var f1 = document.getElementById('pfFollowingCountBadge');
  var f2 = document.getElementById('pfFollowersCountBadge');
  var h1 = document.getElementById('pfHeaderFollowingCount');
  var h2 = document.getElementById('pfHeaderFollowersCount');
  if (f1) f1.textContent = followingCount + ' following';
  if (f2) f2.textContent = followersCount + ' followers';
  if (h1) h1.textContent = String(followingCount);
  if (h2) h2.textContent = String(followersCount);
}

async function refreshFollowingData() {
  var res = await fetch('<?php echo $baseUrl; ?>/pages/student/profile/profile_following_data.php', {
    credentials: 'same-origin'
  });
  var data = await res.json();
  if (!data || !data.ok) return;
  updateFollowCounts(Number(data.following_count || 0), Number(data.followers_count || 0));
  renderDiscoverRows(data.discover_users || []);
  renderFollowingRows(data.following_users || []);
  renderFollowerRows(data.follower_users || []);
}

document.addEventListener('submit', async function (e) {
  var form = e.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (!form.classList.contains('js-follow-form')) return;
  e.preventDefault();

  var fd = new FormData(form);
  var res = await fetch('<?php echo $baseUrl; ?>/pages/student/profile/profile_following_action.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  });

  var data = await res.json();
  if (!data || !data.ok) {
    alert(data && data.message ? data.message : 'Unable to update follow status.');
    return;
  }

  await refreshFollowingData();
});

<?php if ($profileErrors && isset($_POST['action']) && in_array($_POST['action'], ['update_profile', 'update_media'], true)): ?>
openEditProfile();
<?php endif; ?>
<?php if ($profileErrors && isset($_POST['action']) && $_POST['action'] === 'update_cover_style'): ?>
openCoverModal();
<?php endif; ?>
<?php if ($profileErrors && isset($_POST['action']) && $_POST['action'] === 'update_avatar_style'): ?>
openAvatarModal();
<?php endif; ?>
</script>
