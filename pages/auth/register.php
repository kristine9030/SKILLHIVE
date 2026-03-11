<?php

session_start();

require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/../../backend/auth.php';

// Base URL helper
$baseUrl = '/Skillhive';

// Handle form submission
$errors = [];
$old = [];
$statusMessage = '';

function normalizeRole(string $role): string {
    $role = trim($role);
    if ($role === 'ojt_professor') return 'adviser';
    return $role;
}

function splitName(string $name): array {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') return ['', ''];
    $parts = explode(' ', $name);
    $first = array_shift($parts);
    $last = count($parts) ? implode(' ', $parts) : '';
    return [$first, $last];
}

function emailExists(PDO $pdo, string $email): bool {
    $checks = [
        "SELECT 1 FROM employer WHERE email = ? LIMIT 1",
        "SELECT 1 FROM student WHERE email = ? LIMIT 1",
        "SELECT 1 FROM internship_adviser WHERE email = ? LIMIT 1",
        "SELECT 1 FROM admin WHERE email = ? LIMIT 1",
    ];
    foreach ($checks as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawRole = $_POST['role'] ?? 'student';
    $role = normalizeRole($rawRole);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';

    $old['role'] = $rawRole;
    $old['name'] = $name;
    $old['email'] = $email;

    $validRoles = ['student', 'employer', 'adviser'];
    if (!in_array($role, $validRoles, true)) {
        $errors['role'] = 'Please select a valid role.';
    }

    if ($name === '') {
        $errors['name'] = 'Full name is required.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($password !== $passwordConfirmation) {
        $errors['password_confirmation'] = 'Passwords do not match.';
    }

    if (empty($errors) && emailExists($pdo, $email)) {
        $errors['email'] = 'This email is already registered.';
    }

    if (empty($errors)) {
        [$firstName, $lastName] = splitName($name);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            if ($role === 'employer') {
                $stmt = $pdo->prepare("
                    INSERT INTO employer
                    (company_name, industry, company_address, email, contact_number, password_hash, verification_status, company_badge_status, company_logo, website_url, created_at, updated_at)
                    VALUES
                    (?, '', '', ?, '', ?, 'pending', 'none', NULL, NULL, NOW(), NOW())
                ");
                $stmt->execute([$name, $email, $hash]);

            } elseif ($role === 'adviser') {
                $stmt = $pdo->prepare("
                    INSERT INTO internship_adviser
                    (first_name, last_name, department, email, password_hash, created_at, updated_at)
                    VALUES
                    (?, ?, '', ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$firstName, $lastName, $email, $hash]);

            } else { // student
                $stmt = $pdo->prepare("
                    INSERT INTO student
                    (student_number, first_name, last_name, email, program, department, year_level, password_hash, availability_status, preferred_industry, resume_file, internship_readiness_score, profile_picture, created_at, updated_at)
                    VALUES
                    ('', ?, ?, ?, '', '', 1, ?, 'available', '', NULL, 0, NULL, NOW(), NOW())
                ");
                $stmt->execute([$firstName, $lastName, $email, $hash]);
            }

            $pdo->commit();

            if (login($email, $password)) {
                header('Location: ' . $baseUrl . '/layout.php');
                exit;
            }

            $_SESSION['status'] = 'Account created successfully. Please sign in.';
            header('Location: login.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['email'] = 'Registration failed. Please try again.';
        }
    }
}

// Helper functions
function old($field, $default = '') {
    global $old;
    return htmlspecialchars($old[$field] ?? $default);
}

function hasError($field) {
    global $errors;
    return isset($errors[$field]);
}

function getError($field) {
    global $errors;
    return htmlspecialchars($errors[$field] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — SkillHive</title>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --red:       #6b0000;
      --red-mid:   #8b0000;
      --red-lit:   #b22222;
      --red-glow:  rgba(139,0,0,.28);
      --panel:     linear-gradient(150deg,#2b2626 0%,#3d0000 35%,#7a0000 75%,#5a0000 100%);
      --white:     #fff;
      --bg:        #0a0000;
      --text:      #1a1a1a;
      --muted:     #888;
      --border:    #e0e0e0;
      --shadow:    0 32px 80px rgba(0,0,0,.80), 0 4px 32px rgba(80,0,0,.45);
    }

    html, body { height: 100%; }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: linear-gradient(145deg, #0a0000 0%, #2a0000 22%, #5c0000 48%, #3a0000 72%, #150000 100%);
      font-family: 'Poppins', sans-serif;
      color: var(--text);
      overflow: hidden;
    }

    .bubbles { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .bubbles span { position: absolute; bottom: -150px; border-radius: 50%; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.06); opacity: 0; animation: rise linear infinite; }
    .bubbles span:nth-child(1)  { width:  60px; height:  60px; left:  4%; animation-duration: 20s; animation-delay:   0s; }
    .bubbles span:nth-child(2)  { width: 100px; height: 100px; left: 12%; animation-duration: 27s; animation-delay:   4s; }
    .bubbles span:nth-child(3)  { width:  40px; height:  40px; left: 24%; animation-duration: 16s; animation-delay: 1.5s; }
    .bubbles span:nth-child(4)  { width: 160px; height: 160px; left: 38%; animation-duration: 34s; animation-delay:   7s; }
    .bubbles span:nth-child(5)  { width:  55px; height:  55px; left: 56%; animation-duration: 19s; animation-delay:   2s; }
    .bubbles span:nth-child(6)  { width:  90px; height:  90px; left: 68%; animation-duration: 24s; animation-delay:   5s; }
    .bubbles span:nth-child(7)  { width:  35px; height:  35px; left: 80%; animation-duration: 15s; animation-delay: 0.5s; }
    .bubbles span:nth-child(8)  { width:  75px; height:  75px; left: 91%; animation-duration: 22s; animation-delay:   8s; }
    @keyframes rise {
      0%   { transform: translateY(0) scale(.9); opacity: 0; }
      10%  { opacity: .04; }
      90%  { opacity: .04; }
      100% { transform: translateY(-110vh) scale(1.1); opacity: 0; }
    }

    .card {
      position: relative;
      z-index: 1;
      width: 880px;
      max-width: 96vw;
      height: 560px;
      border-radius: 22px;
      overflow: hidden;
      background: var(--white);
      box-shadow: var(--shadow);
      animation: appear .65s cubic-bezier(.22,.61,.36,1) both;
    }
    @keyframes appear {
      from { opacity: 0; transform: translateY(30px) scale(.96); }
      to   { opacity: 1; transform: none; }
    }

    .panels { position: absolute; inset: 0; display: flex; }

    .panel {
      width: 50%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 36px 44px;
      overflow-y: auto;
      overflow-x: hidden;
      background: var(--white);
      transition: opacity .3s .1s;
    }
    .panel::-webkit-scrollbar { width: 3px; }
    .panel::-webkit-scrollbar-thumb { background: #f0e8e8; border-radius: 3px; }

    .panel-signup { opacity: 0; pointer-events: none; }
    .card.active .panel-signin { opacity: 0; pointer-events: none; }
    .card.active .panel-signup { opacity: 1; pointer-events: all; }

    .overlay-wrap {
      position: absolute;
      inset: 0;
      z-index: 10;
      clip-path: inset(0 0 0 50%);
      transition: clip-path .68s cubic-bezier(.77,0,.175,1);
    }
    .card.active .overlay-wrap { clip-path: inset(0 50% 0 0); }

    .overlay-inner { position: absolute; inset: 0; display: flex; }

    .ov-half {
      width: 50%;
      height: 100%;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 48px 40px;
      background: var(--panel);
      position: relative;
      overflow: hidden;
    }
    .ov-half::before, .ov-half::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,.09);
      animation: pulse 6s ease-in-out infinite;
      pointer-events: none;
    }
    .ov-half::before { width: 440px; height: 440px; top: -120px; right: -120px; animation-delay: 0s; }
    .ov-half::after  { width: 300px; height: 300px; bottom: -80px; left: -80px;  animation-delay: 3s; }
    @keyframes pulse {
      0%, 100% { opacity: .09; transform: scale(1); }
      50%       { opacity: .18; transform: scale(1.05); }
    }

    .ov-logo { width: 180px; max-width: 78%; margin-bottom: 14px; filter: drop-shadow(0 3px 16px rgba(0,0,0,.45)) brightness(1.08); }
    .ov-badge  { font-size: 26px; color: rgba(255,255,255,.75); margin-bottom: 8px; }
    .ov-brand  { font-family: 'Poppins',sans-serif; font-size: 20px; font-weight: 800; letter-spacing: 3px; color: #fff; text-transform: uppercase; margin-bottom: 3px; }
    .ov-sub    { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,.38); margin-bottom: 20px; }
    .ov-rule   { width: 36px; height: 1.5px; background: linear-gradient(90deg, transparent, rgba(255,255,255,.4), transparent); margin: 0 auto 20px; }
    .ov-title  { font-family: 'Poppins',sans-serif; font-size: 22px; font-weight: 700; color: #fff; letter-spacing: .3px; margin-bottom: 10px; text-shadow: 0 2px 14px rgba(0,0,0,.28); }
    .ov-body   { font-size: 13px; color: rgba(255,255,255,.58); line-height: 1.7; margin-bottom: 28px; max-width: 195px; }

    .btn-outline {
      padding: 11px 32px;
      border: 1.5px solid rgba(255,255,255,.65);
      background: transparent;
      color: #fff;
      font-family: 'Poppins',sans-serif;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      border-radius: 50px;
      cursor: pointer;
      transition: background .25s, border-color .25s, box-shadow .25s;
    }
    .btn-outline:hover { background: rgba(255,255,255,.12); border-color: #fff; box-shadow: 0 0 18px rgba(255,255,255,.1); }

    .form-head { text-align: center; margin-bottom: 20px; width: 100%; }
    .form-icon { width: 46px; height: 46px; border-radius: 13px; background: linear-gradient(135deg,rgba(139,26,26,.09),rgba(220,53,69,.07)); border: 1px solid rgba(178,34,34,.13); display: inline-flex; align-items: center; justify-content: center; font-size: 19px; margin-bottom: 10px; }
    .form-title { font-family: 'Poppins',sans-serif; font-size: 20px; font-weight: 700; color: var(--text); letter-spacing: .2px; }
    .form-rule  { width: 28px; height: 2.5px; background: linear-gradient(90deg, transparent, var(--red-mid), transparent); margin: 7px auto 0; border-radius: 2px; }
    .form-tagline { font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); margin-top: 6px; }

    .f-label { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: #bbb; margin-bottom: 5px; }

    form { width: 100%; }
    .fg { margin-bottom: 12px; }

    .iw { position: relative; display: flex; align-items: center; }
    .iw .ic { position: absolute; left: 13px; font-size: 12.5px; color: #ccc; pointer-events: none; transition: color .2s; }
    .iw:focus-within .ic { color: var(--red-mid); }
    .iw input {
      width: 100%;
      padding: 10.5px 36px 10.5px 36px;
      border: 1.5px solid var(--border);
      border-radius: 9px;
      background: #fdfafa;
      color: var(--text);
      font-family: 'Poppins',sans-serif;
      font-size: 13.5px;
      font-weight: 500;
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .iw input:focus { border-color: var(--red-mid); box-shadow: 0 0 0 3px var(--red-glow); background: #fff; }
    .iw input::placeholder { color: #d0c8c8; font-size: 13px; font-weight: 400; }

    .eye { position: absolute; right: 11px; border: none; background: none; cursor: pointer; color: #ccc; font-size: 12.5px; padding: 3px; transition: color .2s; }
    .eye:hover { color: var(--red-mid); }

    .err { font-size: 11px; color: var(--red-lit); margin-top: 4px; display: flex; align-items: center; gap: 4px; }

    .row2 { display: flex; gap: 10px; }
    .row2 .fg { flex: 1; }

    .meta-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
    .chk-wrap { display: flex; align-items: center; gap: 7px; }
    .chk-wrap input { accent-color: var(--red-mid); width: 13px; height: 13px; }
    .chk-wrap label { font-size: 12px; color: var(--muted); cursor: pointer; }
    .fp-link { font-size: 11.5px; font-weight: 600; color: var(--muted); text-decoration: none; transition: color .2s; }
    .fp-link:hover { color: var(--red-mid); }

    .btn-submit {
      width: 100%;
      padding: 12.5px;
      border: none;
      border-radius: 9px;
      background: linear-gradient(135deg, #4a0000, #8b0000 50%, #b22222);
      color: #fff;
      font-family: 'Poppins',sans-serif;
      font-size: 12.5px;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(139,26,26,.3);
      transition: box-shadow .25s, transform .15s;
    }
    .btn-submit::after { content: ''; position: absolute; top: 0; left: -100%; width: 55%; height: 100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,.2), transparent); transform: skewX(-18deg); animation: shim 2.8s ease-in-out infinite; }
    @keyframes shim { 0%{left:-100%} 60%,100%{left:150%} }
    .btn-submit:hover { box-shadow: 0 6px 26px rgba(139,26,26,.48); transform: translateY(-1px); }
    .btn-submit:active { transform: none; }

    .role-grid { display: flex; gap: 8px; margin-bottom: 4px; }
    .role-card { flex: 1; text-align: center; padding: 9px 4px; border: 1.5px solid var(--border); border-radius: 9px; cursor: pointer; background: #fdfafa; transition: border-color .2s, background .2s, color .2s, box-shadow .2s; font-size: 11px; font-weight: 700; color: var(--muted); letter-spacing: .3px; user-select: none; }
    .role-card .ri { font-size: 18px; display: block; margin-bottom: 3px; }
    .role-card:hover { border-color: rgba(178,34,34,.3); }
    .role-card.selected { border-color: var(--red-mid); background: rgba(178,34,34,.055); color: var(--red-mid); box-shadow: 0 0 0 3px rgba(178,34,34,.08); }

    .msg-status { width: 100%; background: rgba(178,34,34,.06); border: 1px solid rgba(178,34,34,.18); color: var(--red-mid); padding: 9px 13px; border-radius: 8px; font-size: 12px; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }

    .demo { width: 100%; margin-top: 14px; padding: 11px 14px; background: #fdf7f7; border: 1px solid rgba(178,34,34,.1); border-left: 3px solid var(--red-mid); border-radius: 8px; font-size: 11.5px; color: var(--muted); line-height: 1.85; }
    .demo-hd { font-size: 10.5px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--red-mid); margin-bottom: 7px; display: flex; align-items: center; gap: 5px; }
    .demo ul { list-style: none; padding: 0; }
    .demo ul li { display: flex; align-items: center; gap: 7px; padding: 1.5px 0; }
    .demo ul li::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: rgba(178,34,34,.3); flex-shrink: 0; }
    .demo span { color: var(--red-mid); font-weight: 600; }

    @media (max-width: 700px) {
      .card { height: auto; }
      .overlay-wrap { display: none !important; }
      .panels { position: static; flex-direction: column; }
      .panel { width: 100%; height: auto; opacity: 1 !important; pointer-events: all !important; }
      .panel-signup { display: none; }
      .card.active .panel-signin { display: none; }
      .card.active .panel-signup { display: flex; }
      body { overflow-y: auto; align-items: flex-start; padding: 18px 0; }
      .bubbles { display: none; }
    }
  </style>
</head>
<body>

<div class="bubbles" aria-hidden="true">
  <span></span><span></span><span></span><span></span>
  <span></span><span></span><span></span><span></span>
</div>

<div class="card active" id="authCard">

  <!-- FORM PANELS -->
  <div class="panels">

    <!-- SIGN IN PANEL (hidden by default on register page) -->
    <div class="panel panel-signin">
      <div class="form-head">
        <div class="form-icon"><i class="fa-solid fa-shield-halved" style="color:var(--red-mid)"></i></div>
        <div class="form-title">Sign In</div>
        <div class="form-rule"></div>
        <div class="form-tagline">Welcome back, warrior</div>
      </div>

      <form method="POST" action="login.php">
        <div class="fg">
          <label class="f-label">Email Address</label>
          <div class="iw">
            <i class="ic fa-regular fa-envelope"></i>
            <input type="email" name="email" placeholder="you@example.com" required autocomplete="username">
          </div>
        </div>
        <div class="fg">
          <label class="f-label">Password</label>
          <div class="iw">
            <i class="ic fa-solid fa-lock"></i>
            <input type="password" id="sinPwd" name="password" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="eye" onclick="togglePwd('sinPwd',this)" tabindex="-1"><i class="fa-regular fa-eye"></i></button>
          </div>
        </div>
        <div class="meta-row">
          <div class="chk-wrap">
            <input type="checkbox" name="remember" id="remember">
            <label for="remember">Keep me signed in</label>
          </div>
          <a href="#" class="fp-link">Forgot password?</a>
        </div>
        <button type="submit" class="btn-submit"><i class="fa-solid fa-arrow-right-to-bracket"></i> &nbsp;Sign In</button>
      </form>

      <div class="demo">
        <div class="demo-hd"><i class="fa-solid fa-key"></i> Demo Accounts &mdash; password: <span>password</span></div>
        <ul>
          <li>&#127891; Student &mdash; <span>student@example.com</span></li>
          <li>&#128188; Employer &mdash; <span>employer@example.com</span></li>
          <li>&#127963; Professor &mdash; <span>professor@example.com</span></li>
        </ul>
      </div>
    </div>

    <!-- SIGN UP PANEL (visible by default on register page) -->
    <div class="panel panel-signup">
      <div class="form-head">
        <div class="form-icon"><i class="fa-solid fa-user-plus" style="color:var(--red-mid)"></i></div>
        <div class="form-title">Create Account</div>
        <div class="form-rule"></div>
        <div class="form-tagline">Join the ranks, warrior</div>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="msg-status"><i class="fa-solid fa-triangle-exclamation"></i> Please fix the errors below.</div>
      <?php endif; ?>

      <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <div class="fg">
          <label class="f-label">I Am A&hellip;</label>
          <div class="role-grid">
            <label class="role-card <?php echo (old('role', 'student') === 'student') ? 'selected' : ''; ?>" onclick="pickRole('student',this)">
              <span class="ri">&#127891;</span> Student
              <input type="radio" name="role" value="student" <?php echo (old('role', 'student') === 'student') ? 'checked' : ''; ?> style="display:none">
            </label>
            <label class="role-card <?php echo (old('role') === 'employer') ? 'selected' : ''; ?>" onclick="pickRole('employer',this)">
              <span class="ri">&#128188;</span> Employer
              <input type="radio" name="role" value="employer" <?php echo (old('role') === 'employer') ? 'checked' : ''; ?> style="display:none">
            </label>
            <label class="role-card <?php echo (old('role') === 'ojt_professor') ? 'selected' : ''; ?>" onclick="pickRole('ojt_professor',this)">
              <span class="ri">&#127963;</span> Professor
              <input type="radio" name="role" value="ojt_professor" <?php echo (old('role') === 'ojt_professor') ? 'checked' : ''; ?> style="display:none">
            </label>
          </div>
          <?php if (hasError('role')): ?>
            <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo getError('role'); ?></div>
          <?php endif; ?>
        </div>
        <div class="fg">
          <label class="f-label">Full Name</label>
          <div class="iw">
            <i class="ic fa-solid fa-user"></i>
            <input type="text" name="name" placeholder="Juan dela Cruz" value="<?php echo old('name'); ?>" required autocomplete="name">
          </div>
          <?php if (hasError('name')): ?>
            <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo getError('name'); ?></div>
          <?php endif; ?>
        </div>
        <div class="fg">
          <label class="f-label">Email Address</label>
          <div class="iw">
            <i class="ic fa-regular fa-envelope"></i>
            <input type="email" name="email" placeholder="you@example.com" value="<?php echo old('email'); ?>" required autocomplete="username">
          </div>
          <?php if (hasError('email')): ?>
            <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo getError('email'); ?></div>
          <?php endif; ?>
        </div>
        <div class="row2">
          <div class="fg">
            <label class="f-label">Password</label>
            <div class="iw">
              <i class="ic fa-solid fa-lock"></i>
              <input type="password" id="regPwd" name="password" placeholder="Min 8 chars" required autocomplete="new-password">
              <button type="button" class="eye" onclick="togglePwd('regPwd',this)" tabindex="-1"><i class="fa-regular fa-eye"></i></button>
            </div>
            <?php if (hasError('password')): ?>
              <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo getError('password'); ?></div>
            <?php endif; ?>
          </div>
          <div class="fg">
            <label class="f-label">Confirm</label>
            <div class="iw">
              <i class="ic fa-solid fa-lock"></i>
              <input type="password" id="regCon" name="password_confirmation" placeholder="Repeat" required autocomplete="new-password">
              <button type="button" class="eye" onclick="togglePwd('regCon',this)" tabindex="-1"><i class="fa-regular fa-eye"></i></button>
            </div>
            <?php if (hasError('password_confirmation')): ?>
              <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo getError('password_confirmation'); ?></div>
            <?php endif; ?>
          </div>
        </div>
        <button type="submit" class="btn-submit"><i class="fa-solid fa-shield-halved"></i> &nbsp;Create Account</button>
      </form>
    </div>

  </div>

  <!-- SLIDING OVERLAY -->
  <div class="overlay-wrap" id="ovWrap">
    <div class="overlay-inner">

      <!-- Left half: shown when register is active (overlay slides left) -->
      <div class="ov-half">
        <img src="<?php echo $baseUrl; ?>/images/logo.png" alt="SkillHive" class="ov-logo">
        <div class="ov-sub">BatStateU &middot; OJT Platform</div>
        <div class="ov-rule"></div>
        <div class="ov-title">Welcome Back!</div>
        <div class="ov-body">Already enlisted? Sign in and continue your Spartan OJT journey.</div>
        <button class="btn-outline" onclick="setMode(false)"><i class="fa-solid fa-arrow-right-to-bracket"></i> &nbsp;Sign In</button>
      </div>

      <!-- Right half: shown when login is active (overlay slides right) -->
      <div class="ov-half">
        <img src="<?php echo $baseUrl; ?>/images/logo.png" alt="SkillHive" class="ov-logo">
        <div class="ov-sub">BatStateU &middot; OJT Platform</div>
        <div class="ov-rule"></div>
        <div class="ov-title">Hello, Warrior!</div>
        <div class="ov-body">New to the platform? Register and forge your Spartan OJT path.</div>
        <button class="btn-outline" onclick="setMode(true)"><i class="fa-solid fa-user-plus"></i> &nbsp;Sign Up</button>
      </div>

    </div>
  </div>

</div>

<script>
  const card = document.getElementById('authCard');

  function setMode(register) {
    card.classList.toggle('active', register);
    history.replaceState(null, '', register ? 'register.php' : 'login.php');
  }

  function pickRole(val, el) {
    el.closest('.role-grid').querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
  }

  function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
  }

  // Start in register mode (this page is register.php)
  setMode(true);
</script>
</body>
</html>

