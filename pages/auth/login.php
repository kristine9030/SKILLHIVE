<?php
session_start();
require_once __DIR__ . '/../../backend/auth.php';

<<<<<<< HEAD
$baseUrl = '/Skillhive';

=======
// Base URL helper
$baseUrl = '/Skillhive';

// Handle form submission
>>>>>>> 6eb432d25ae2206575e1e0b9f3d75894c472a1ba
$errors = [];
$old = [];
$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
<<<<<<< HEAD
    $email    = trim($_POST['email'] ?? '');
=======
    $email = trim($_POST['email'] ?? '');
>>>>>>> 6eb432d25ae2206575e1e0b9f3d75894c472a1ba
    $password = $_POST['password'] ?? '';
    $old['email'] = $email;

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
        if (login($email, $password)) {
            header('Location: ' . $baseUrl . '/layout.php');
            exit;
        }
        $errors['email'] = 'Invalid email or password.';
    }
}

<<<<<<< HEAD
=======
// Check for status message from redirect (e.g., after registration)
>>>>>>> 6eb432d25ae2206575e1e0b9f3d75894c472a1ba
if (isset($_SESSION['status'])) {
    $statusMessage = $_SESSION['status'];
    unset($_SESSION['status']);
}

<<<<<<< HEAD
function old_val($field, $default = '') {
    global $old;
    return htmlspecialchars($old[$field] ?? $default);
}
function has_error($field) {
    global $errors;
    return isset($errors[$field]);
}
function get_error($field) {
=======
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
>>>>>>> 6eb432d25ae2206575e1e0b9f3d75894c472a1ba
    global $errors;
    return htmlspecialchars($errors[$field] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<<<<<<< HEAD
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — SkillHive</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Inter', sans-serif;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #c7d2f5 0%, #d6e4f7 25%, #ede4d4 50%, #f8edd4 75%, #f0e8da 100%);
  color: #111;
  -webkit-font-smoothing: antialiased;
  padding: 24px;
  overflow: hidden;
}

.auth-modal {
  display: flex; width: 900px; max-width: 96vw; height: 520px; max-height: 88vh;
  background: #fff; border-radius: 24px; overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.03);
  animation: authSlideUp .32s cubic-bezier(.4,0,.2,1);
}
@keyframes authSlideUp { from { transform:translateY(28px); opacity:0; } to { transform:translateY(0); opacity:1; } }

.auth-left {
  width: 340px; flex-shrink: 0;
  background: linear-gradient(160deg, #0d1b2e 0%, #111827 50%, #141a0f 100%);
  padding: 40px 32px; display: flex; align-items: center;
  position: relative; overflow: hidden;
}
.auth-left::before {
  content: ''; position: absolute;
  width: 400px; height: 400px; border-radius: 50%;
  background: radial-gradient(circle, rgba(186,230,253,.12) 0%, transparent 70%);
  top: -100px; right: -120px; pointer-events: none;
}
.auth-left::after {
  content: ''; position: absolute;
  width: 300px; height: 300px; border-radius: 50%;
  background: radial-gradient(circle, rgba(254,240,138,.08) 0%, transparent 70%);
  bottom: -80px; left: -60px; pointer-events: none;
}
.auth-left-inner { position: relative; z-index: 1; width: 100%; }
.auth-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 40px; }
.auth-logo-icon {
  width: 38px; height: 38px; border-radius: 10px;
  background: linear-gradient(135deg, #bae6fd, #fef08a);
  display: flex; align-items: center; justify-content: center;
  color: #0f1729; font-size: 1rem;
}
.auth-brand-name { font-family: 'Inter',sans-serif; font-weight: 800; font-size: 1.25rem; color: #fff; }
.auth-tagline { font-family: 'Inter',sans-serif; font-weight: 800; font-size: 1.65rem; line-height: 1.2; color: #fff; margin-bottom: 12px; }
.auth-sub { color: rgba(255,255,255,.55); font-size: .84rem; line-height: 1.6; margin-bottom: 28px; }
.auth-stats-row { display: flex; gap: 20px; margin-bottom: 28px; }
.auth-stat-num { font-family: 'Inter',sans-serif; font-weight: 800; font-size: 1.3rem; color: #fff; }
.auth-stat-lbl { font-size: .72rem; color: rgba(255,255,255,.45); margin-top: 2px; }
.auth-people-row { display: flex; align-items: center; }
.auth-people-row img {
  width: 36px; height: 36px; border-radius: 50%;
  border: 2.5px solid rgba(255,255,255,.2);
  margin-left: -8px; object-fit: cover;
}
.auth-people-row img:first-child { margin-left: 0; }
.auth-people-more {
  margin-left: 6px; font-size: .78rem; font-weight: 700;
  color: rgba(255,255,255,.6); white-space: nowrap;
}

.auth-right {
  flex: 1; overflow: hidden; padding: 40px 44px;
  position: relative; display: flex; flex-direction: column; justify-content: center;
}

.auth-form-header { margin-bottom: 22px; }
.auth-form-title { font-family: 'Inter',sans-serif; font-weight: 800; font-size: 1.5rem; color: #111; margin-bottom: 4px; }
.auth-form-sub { color: #999; font-size: .88rem; }

.auth-divider { display: flex; align-items: center; gap: 12px; margin: 14px 0; }
.auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: #EBEBEB; }
.auth-divider span { font-size: .78rem; color: #bbb; white-space: nowrap; }

.auth-field { margin-bottom: 14px; }
.auth-label {
  display: flex; justify-content: space-between; align-items: center;
  font-size: .8rem; font-weight: 600; color: #444; margin-bottom: 7px;
}
.auth-forgot { font-size: .78rem; color: #888; text-decoration: none; font-weight: 500; }
.auth-forgot:hover { color: #333; }
.auth-input-wrap { position: relative; display: flex; align-items: center; }
.auth-input-icon { position: absolute; left: 14px; color: #bbb; font-size: .85rem; pointer-events: none; }
.auth-input {
  width: 100%; padding: 11px 14px 11px 38px;
  border: 1.5px solid #E5E5E5; border-radius: 10px;
  font-family: 'Inter',sans-serif; font-size: .88rem; color: #111;
  outline: none; transition: border-color .2s, box-shadow .2s; background: #fff;
}
.auth-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(0,0,0,.05); }
.auth-input::placeholder { color: #ccc; }
.auth-input.input-error { border-color: #EF4444; }
.auth-eye-btn {
  position: absolute; right: 12px; background: none; border: none;
  cursor: pointer; color: #bbb; font-size: .85rem; padding: 4px;
  transition: color .2s;
}
.auth-eye-btn:hover { color: #555; }

.auth-remember-row { margin-bottom: 16px; }
.auth-checkbox-label {
  display: flex; align-items: center; gap: 10px;
  font-size: .82rem; color: #666; cursor: pointer; user-select: none;
}
.auth-checkbox { display: none; }
.auth-checkmark {
  width: 18px; height: 18px; border-radius: 5px;
  border: 2px solid #DDD; background: #fff; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: all .2s;
}
.auth-checkbox:checked + .auth-checkmark { background: #111; border-color: #111; }
.auth-checkbox:checked + .auth-checkmark::after { content: '\2713'; color: #fff; font-size: .7rem; font-weight: 700; }

.auth-submit-btn {
  width: 100%; padding: 12px 24px; border-radius: 12px;
  background: #111;
  color: #fff; border: none; cursor: pointer;
  font-family: 'Inter',sans-serif; font-size: .92rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  transition: all .2s; margin-bottom: 14px;
}
.auth-submit-btn:hover { background: #2e2e2e; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(0,0,0,.2); }

.auth-switch-text { text-align: center; font-size: .84rem; color: #888; }
.auth-switch-text a { color: #111; font-weight: 700; text-decoration: none; }
.auth-switch-text a:hover { text-decoration: underline; }

.error-msg { font-size: .75rem; color: #EF4444; margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.error-msg i { font-size: .7rem; }
.alert-banner { width: 100%; background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.2); color: #EF4444; padding: 10px 14px; border-radius: 10px; font-size: .82rem; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
.alert-banner i { font-size: .85rem; }
.success-banner { width: 100%; background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.2); color: #10B981; padding: 10px 14px; border-radius: 10px; font-size: .82rem; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
.success-banner i { font-size: .85rem; }

@media (max-width: 700px) {
  .auth-left { display: none; }
  .auth-right { padding: 32px 24px; }
}
</style>
</head>
<body>

<div class="auth-modal">

  <!-- LEFT — brand panel -->
  <div class="auth-left">
    <div class="auth-left-inner">
      <div class="auth-brand">
        <div class="auth-logo-icon"><i class="fas fa-hexagon-nodes"></i></div>
        <span class="auth-brand-name">SkillHive</span>
      </div>
      <h2 class="auth-tagline">Where Talent<br>Meets Opportunity</h2>
      <p class="auth-sub">Join thousands of students and companies using AI-powered matching to find the perfect internship fit.</p>
      <div class="auth-stats-row">
        <div class="auth-stat"><div class="auth-stat-num">1.2k+</div><div class="auth-stat-lbl">Students Placed</div></div>
        <div class="auth-stat"><div class="auth-stat-num">79+</div><div class="auth-stat-lbl">Partner Companies</div></div>
        <div class="auth-stat"><div class="auth-stat-num">98%</div><div class="auth-stat-lbl">Satisfaction</div></div>
      </div>
      <div class="auth-people-row">
        <img src="https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=80&q=80&auto=format&fit=crop&crop=face" alt="">
        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?w=80&q=80&auto=format&fit=crop&crop=face" alt="">
        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&q=80&auto=format&fit=crop&crop=face" alt="">
        <img src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=80&q=80&auto=format&fit=crop&crop=face" alt="">
        <span class="auth-people-more">+1.2k</span>
      </div>
    </div>
  </div>

  <!-- RIGHT — login form -->
  <div class="auth-right">
    <div class="auth-form-header">
      <h3 class="auth-form-title">Welcome back</h3>
      <p class="auth-form-sub">Sign in to your SkillHive account</p>
    </div>

    <?php if ($statusMessage): ?>
      <div class="success-banner"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($statusMessage); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert-banner"><i class="fas fa-exclamation-triangle"></i> <?php echo get_error('email') ?: 'Please fix the errors below.'; ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">

      <div class="auth-divider"><span>sign in with email</span></div>

      <div class="auth-field">
        <label class="auth-label">Email address</label>
        <div class="auth-input-wrap">
          <i class="fas fa-envelope auth-input-icon"></i>
          <input class="auth-input <?php echo has_error('email') ? 'input-error' : ''; ?>" type="email" name="email" placeholder="you@university.edu" value="<?php echo old_val('email'); ?>" required>
        </div>
      </div>

      <div class="auth-field">
        <label class="auth-label">
          Password
          <a href="#" class="auth-forgot">Forgot password?</a>
        </label>
        <div class="auth-input-wrap">
          <i class="fas fa-lock auth-input-icon"></i>
          <input class="auth-input <?php echo has_error('password') ? 'input-error' : ''; ?>" type="password" name="password" id="login-password" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required>
          <button class="auth-eye-btn" type="button" onclick="togglePwd('login-password',this)"><i class="fas fa-eye"></i></button>
        </div>
        <?php if (has_error('password')): ?>
          <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo get_error('password'); ?></div>
        <?php endif; ?>
      </div>

      <div class="auth-remember-row">
        <label class="auth-checkbox-label">
          <input type="checkbox" class="auth-checkbox" name="remember">
          <span class="auth-checkmark"></span>
          Keep me signed in
        </label>
      </div>

      <button type="submit" class="auth-submit-btn">
        <span>Sign In</span>
        <i class="fas fa-arrow-right"></i>
      </button>
    </form>

    <p class="auth-switch-text">
      Don't have an account?
      <a href="register.php">Create account</a>
    </p>
=======
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — SkillHive</title>
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

<div class="card" id="authCard">

  <!-- FORM PANELS -->
  <div class="panels">

    <!-- SIGN IN PANEL -->
    <div class="panel panel-signin">
      <div class="form-head">
        <div class="form-icon"><i class="fa-solid fa-shield-halved" style="color:var(--red-mid)"></i></div>
        <div class="form-title">Sign In</div>
        <div class="form-rule"></div>
        <div class="form-tagline">Welcome back, warrior</div>
      </div>

      <?php if (!empty($statusMessage)): ?>
        <div class="msg-status"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($statusMessage); ?></div>
      <?php endif; ?>

      <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <div class="fg">
          <label class="f-label">Email Address</label>
          <div class="iw">
            <i class="ic fa-regular fa-envelope"></i>
            <input type="email" name="email" placeholder="you@example.com" value="<?php echo old('email'); ?>" required autofocus autocomplete="username">
          </div>
          <?php if (hasError('email')): ?>
            <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo getError('email'); ?></div>
          <?php endif; ?>
        </div>
        <div class="fg">
          <label class="f-label">Password</label>
          <div class="iw">
            <i class="ic fa-solid fa-lock"></i>
            <input type="password" id="sinPwd" name="password" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="eye" onclick="togglePwd('sinPwd',this)" tabindex="-1"><i class="fa-regular fa-eye"></i></button>
          </div>
          <?php if (hasError('password')): ?>
            <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo getError('password'); ?></div>
          <?php endif; ?>
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

    <!-- SIGN UP PANEL -->
    <div class="panel panel-signup">
      <div class="form-head">
        <div class="form-icon"><i class="fa-solid fa-user-plus" style="color:var(--red-mid)"></i></div>
        <div class="form-title">Create Account</div>
        <div class="form-rule"></div>
        <div class="form-tagline">Join the ranks, warrior</div>
      </div>

      <form method="POST" action="register.php">
        <div class="fg">
          <label class="f-label">I Am A&hellip;</label>
          <div class="role-grid">
            <label class="role-card selected" onclick="pickRole('student',this)">
              <span class="ri">&#127891;</span> Student
              <input type="radio" name="role" value="student" checked style="display:none">
            </label>
            <label class="role-card" onclick="pickRole('employer',this)">
              <span class="ri">&#128188;</span> Employer
              <input type="radio" name="role" value="employer" style="display:none">
            </label>
            <label class="role-card" onclick="pickRole('ojt_professor',this)">
              <span class="ri">&#127963;</span> Professor
              <input type="radio" name="role" value="ojt_professor" style="display:none">
            </label>
          </div>
        </div>
        <div class="fg">
          <label class="f-label">Full Name</label>
          <div class="iw">
            <i class="ic fa-solid fa-user"></i>
            <input type="text" name="name" placeholder="Juan dela Cruz" required autocomplete="name">
          </div>
        </div>
        <div class="fg">
          <label class="f-label">Email Address</label>
          <div class="iw">
            <i class="ic fa-regular fa-envelope"></i>
            <input type="email" name="email" placeholder="you@example.com" required autocomplete="username">
          </div>
        </div>
        <div class="row2">
          <div class="fg">
            <label class="f-label">Password</label>
            <div class="iw">
              <i class="ic fa-solid fa-lock"></i>
              <input type="password" id="regPwd" name="password" placeholder="Min 8 chars" required autocomplete="new-password">
              <button type="button" class="eye" onclick="togglePwd('regPwd',this)" tabindex="-1"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>
          <div class="fg">
            <label class="f-label">Confirm</label>
            <div class="iw">
              <i class="ic fa-solid fa-lock"></i>
              <input type="password" id="regCon" name="password_confirmation" placeholder="Repeat" required autocomplete="new-password">
              <button type="button" class="eye" onclick="togglePwd('regCon',this)" tabindex="-1"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>
        </div>
        <button type="submit" class="btn-submit"><i class="fa-solid fa-shield-halved"></i> &nbsp;Create Account</button>
      </form>
    </div>

  </div>

  <!-- SLIDING OVERLAY -->
  <div class="overlay-wrap" id="ovWrap">
    <div class="overlay-inner">

      <!-- Left half: shown when register is active -->
      <div class="ov-half">
        <img src="<?php echo $baseUrl; ?>/images/logo.png" alt="SkillHive" class="ov-logo">
        <div class="ov-sub">BatStateU &middot; OJT Platform</div>
        <div class="ov-rule"></div>
        <div class="ov-title">Welcome Back!</div>
        <div class="ov-body">Already enlisted? Sign in and continue your Spartan OJT journey.</div>
        <button class="btn-outline" onclick="setMode(false)"><i class="fa-solid fa-arrow-right-to-bracket"></i> &nbsp;Sign In</button>
      </div>

      <!-- Right half: shown when login is active -->
      <div class="ov-half">
        <img src="<?php echo $baseUrl; ?>/images/logo.png" alt="SkillHive" class="ov-logo">
        <div class="ov-sub">BatStateU &middot; OJT Platform</div>
        <div class="ov-rule"></div>
        <div class="ov-title">Hello, Warrior!</div>
        <div class="ov-body">New to the platform? Register and forge your Spartan OJT path.</div>
        <button class="btn-outline" onclick="setMode(true)"><i class="fa-solid fa-user-plus"></i> &nbsp;Sign Up</button>
      </div>

    </div>
>>>>>>> 6eb432d25ae2206575e1e0b9f3d75894c472a1ba
  </div>

</div>

<script>
<<<<<<< HEAD
function togglePwd(inputId, btn) {
  const inp = document.getElementById(inputId);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
=======
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

  // Start in sign-in mode
  setMode(false);
</script>
</body>
</html>
>>>>>>> 6eb432d25ae2206575e1e0b9f3d75894c472a1ba
