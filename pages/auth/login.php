<?php
session_start();
require_once __DIR__ . '/../../backend/auth.php';

$baseUrl = '/Skillhive';

$errors = [];
$old = [];
$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
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

if (isset($_SESSION['status'])) {
    $statusMessage = $_SESSION['status'];
    unset($_SESSION['status']);
}

function old_val($field, $default = '') {
    global $old;
    return htmlspecialchars($old[$field] ?? $default);
}
function has_error($field) {
    global $errors;
    return isset($errors[$field]);
}
function get_error($field) {
    global $errors;
    return htmlspecialchars($errors[$field] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
  </div>

</div>

<script>
function togglePwd(inputId, btn) {
  const inp = document.getElementById(inputId);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
