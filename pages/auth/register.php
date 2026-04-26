<?php
session_start();
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/../../backend/auth.php';

$baseUrl = '/Skillhive';
$logoAsset = $baseUrl . '/assets/media/skillhive-logo.png';

$errors = [];
$old = [];

function normalizeRole(string $role): string {
    $role = trim($role);
    if ($role === 'ojt_professor') return 'adviser';
    return $role;
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
  $rawRole   = $_POST['role'] ?? 'employer';
    $role      = normalizeRole($rawRole);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $org       = trim($_POST['organization'] ?? '');
    $password  = $_POST['password'] ?? '';

    $old = [
        'role'         => $rawRole,
        'first_name'   => $firstName,
        'last_name'    => $lastName,
        'email'        => $email,
        'organization' => $org,
    ];

    $validRoles = ['employer', 'adviser'];
    if (!in_array($role, $validRoles, true)) {
      $errors['role'] = 'Student accounts are created by advisers. Please select Employer or Adviser.';
    }

    if ($firstName === '') $errors['first_name'] = 'First name is required.';
    if ($lastName === '')  $errors['last_name']  = 'Last name is required.';

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

    if (empty($errors) && emailExists($pdo, $email)) {
        $errors['email'] = 'This email is already registered.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo->beginTransaction();

            if ($role === 'employer') {
                $companyName = trim($firstName . ' ' . $lastName);
                if ($org !== '') $companyName = $org;
                $stmt = $pdo->prepare("
                    INSERT INTO employer
                    (company_name, industry, company_address, email, contact_number, password_hash, verification_status, company_badge_status, company_logo, website_url, created_at, updated_at)
                    VALUES (?, '', '', ?, '', ?, 'Pending', 'None', NULL, NULL, NOW(), NOW())
                ");
                $stmt->execute([$companyName, $email, $hash]);
            } elseif ($role === 'adviser') {
                $dept = $org !== '' ? $org : '';
                $stmt = $pdo->prepare("
                    INSERT INTO internship_adviser
                    (first_name, last_name, department, email, password_hash, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$firstName, $lastName, $dept, $email, $hash]);
            }

            $pdo->commit();

            if ($role === 'employer') {
              $_SESSION['status'] = 'Employer account created. Please wait for admin approval before signing in.';
              header('Location: login.php');
              exit;
            }

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
<title>Create Account — SkillHive</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --auth-banner-color: #1f6f6b;
  --auth-banner-color-dark: #195a56;
  --auth-banner-color-soft: #2b8a84;
  --auth-banner-gradient: linear-gradient(160deg, #050505 0%, #1f6f6b 52%, #050505 100%);
  --auth-action-gradient: linear-gradient(135deg, #050505 0%, #1f6f6b 100%);
  --auth-action-gradient-hover: linear-gradient(135deg, #1f6f6b 0%, #050505 100%);
}
body {
  font-family: 'Poppins', sans-serif;
  font-size: 15px;
  line-height: 1.5;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #c7d2f5 0%, #d6e4f7 25%, #ede4d4 50%, #f8edd4 75%, #f0e8da 100%);
  color: #111;
  -webkit-font-smoothing: antialiased;
  padding: 20px;
  overflow: hidden;
}

/* ===== AUTH MODAL (from mockup) ===== */
.auth-modal {
  display: flex; width: 920px; max-width: 96vw; height: auto; max-height: 92vh;
  background: #fff; border-radius: 24px; overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.03);
  animation: authSlideUp .32s cubic-bezier(.4,0,.2,1);
}
@keyframes authSlideUp { from { transform:translateY(28px); opacity:0; } to { transform:translateY(0); opacity:1; } }

/* LEFT — brand panel */
.auth-left {
  width: 340px; flex-shrink: 0;
  background: var(--auth-banner-gradient);
  padding: 36px 30px; display: flex; align-items: center;
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
.auth-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
.auth-logo-icon {
  width: 38px; height: 38px; border-radius: 10px;
  background: transparent;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
}
.auth-logo-icon img {
  width: 100%; height: 100%; display: block; object-fit: cover;
}
.auth-brand-name { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 1.2rem; color: #fff; }
.auth-tagline { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 1.55rem; line-height: 1.4; color: #fff; margin-bottom: 12px; }
.auth-sub { color: rgba(255,255,255,.55); font-size: .82rem; line-height: 1.6; margin-bottom: 24px; }
.auth-stats-row { display: flex; gap: 18px; margin-bottom: 24px; }
.auth-stat-num { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 1.3rem; color: #fff; }
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

/* RIGHT — form panel */
.auth-right {
  flex: 1; overflow-y: auto; padding: 28px 36px;
  position: relative;
}
.auth-right::-webkit-scrollbar { width: 4px; }
.auth-right::-webkit-scrollbar-track { background: transparent; }
.auth-right::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
.auth-right::-webkit-scrollbar-thumb:hover { background: #bbb; }

.auth-form-header { margin-bottom: 16px; }
.auth-form-title { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 1.4rem; color: #111; margin-bottom: 3px; }
.auth-form-sub { color: #999; font-size: .85rem; font-weight: 500; }

/* Role selector */
.auth-role-selector { display: flex; gap: 8px; margin-bottom: 14px; }
.auth-role-btn {
  flex: 1; padding: 10px 8px; border-radius: 12px;
  border: 1.5px solid #ffffff; background: #fff;
  cursor: pointer; display: flex; flex-direction: column;
  align-items: center; gap: 5px; transition: all .2s;
  font-family: 'Poppins', sans-serif;
}
.auth-role-btn i { font-size: 1rem; color: #aaa; transition: color .2s; }
.auth-role-btn span { font-size: .75rem; font-weight: 500; color: #888; transition: color .2s; }
.auth-role-btn.active { border-color: var(--auth-banner-color); background: var(--auth-action-gradient); box-shadow: 0 4px 14px rgba(0,0,0,.15); }
.auth-role-btn.active i, .auth-role-btn.active span { color: #fff; }
.auth-role-btn:not(.active):hover { border-color: #c7c7c7; background: #ffffff; }

/* Divider */
.auth-divider { display: flex; align-items: center; gap: 12px; margin: 10px 0 14px; }
.auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: #ffffff; }
.auth-divider span { font-size: .78rem; color: #bbb; white-space: nowrap; }

/* Fields */
.auth-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.auth-field { margin-bottom: 12px; }
.auth-label {
  display: flex; justify-content: space-between; align-items: center;
  font-size: .8rem; font-weight: 500; color: #444; margin-bottom: 7px;
}
.auth-input-wrap { position: relative; display: flex; align-items: center; }
.auth-input-icon { position: absolute; left: 14px; color: #bbb; font-size: .85rem; pointer-events: none; }
.auth-input {
  width: 100%; padding: 10px 14px 10px 38px;
  border: 1.5px solid #ffffff; border-radius: 10px;
  font-family: 'Poppins', sans-serif; font-size: .85rem; color: #111;
  outline: none; transition: border-color .2s, box-shadow .2s; background: #ffffff;
}
.auth-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(0,0,0,.05); background: #fff; }
.auth-input::placeholder { color: #ccc; }
.auth-input.input-error { border-color: #138b84; }
.auth-eye-btn {
  position: absolute; right: 12px; background: none; border: none;
  cursor: pointer; color: #bbb; font-size: .85rem; padding: 4px;
  transition: color .2s;
}
.auth-eye-btn:hover { color: #555; }

/* Password strength */
.auth-password-strength {
  height: 4px; background: #ffffff; border-radius: 50px;
  margin-top: 8px; overflow: hidden;
}
.pwd-strength-fill { height: 100%; border-radius: 50px; width: 0; transition: width .3s, background .3s; }
.auth-pwd-hint { font-size: .72rem; color: #aaa; margin-top: 5px; height: 16px; }

/* Terms */
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
.auth-checkbox:checked + .auth-checkmark { background: var(--auth-banner-color); border-color: var(--auth-banner-color); }
.auth-checkbox:checked + .auth-checkmark::after { content: '\2713'; color: #fff; font-size: .7rem; font-weight: 700; }
.auth-terms { margin-bottom: 14px; line-height: 1.5; }
.auth-terms a { color: var(--auth-banner-color); font-weight: 600; text-decoration: none; }

/* Submit button */
.auth-submit-btn {
  width: 100%; padding: 12px 24px; border-radius: 12px;
  background: var(--auth-action-gradient); color: #fff; border: none; cursor: pointer;
  font-family: 'Poppins', sans-serif; font-size: .9rem; font-weight: 600;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  transition: all .2s; margin-bottom: 12px;
}
.auth-submit-btn:hover { background: var(--auth-action-gradient-hover); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(0,0,0,.2); }

.auth-switch-text { text-align: center; font-size: .82rem; color: #888; }
.auth-switch-text a { color: var(--auth-banner-color); font-weight: 700; text-decoration: none; }
.auth-switch-text a:hover { text-decoration: underline; }

/* Error message */
.error-msg { font-size: .75rem; color: #138b84; margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.error-msg i { font-size: .7rem; }
.alert-banner { width: 100%; background: rgba(19,120,115,.12); border: 1px solid rgba(19,120,115,.3); color: #138b84; padding: 10px 14px; border-radius: 10px; font-size: .82rem; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
.alert-banner i { font-size: .85rem; }

@keyframes shake {
  0%,100% { transform: translateX(0); }
  20% { transform: translateX(-8px); }
  40% { transform: translateX(8px); }
  60% { transform: translateX(-5px); }
  80% { transform: translateX(5px); }
}

@media (max-width: 700px) {
  .auth-left { display: none; }
  .auth-right { padding: 32px 24px; }
  .auth-fields-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="auth-modal">

  <!-- LEFT — brand panel -->
  <div class="auth-left">
    <div class="auth-left-inner">
      <div class="auth-brand">
        <div class="auth-logo-icon"><img src="<?php echo htmlspecialchars($logoAsset); ?>" alt="SkillHive"></div>
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

  <!-- RIGHT — register form -->
  <div class="auth-right">
    <div class="auth-form-header">
      <h3 class="auth-form-title">Create your account</h3>
      <p class="auth-form-sub">Start your journey with SkillHive today</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert-banner"><i class="fas fa-exclamation-triangle"></i> Please fix the errors below.</div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="register-form">

      <!-- Role selector -->
      <div class="auth-role-selector">
        <label class="auth-role-btn <?php echo (old_val('role','employer') === 'employer') ? 'active' : ''; ?>" onclick="selectRole('employer',this)">
          <i class="fas fa-building"></i>
          <span>Employer</span>
          <input type="radio" name="role" value="employer" <?php echo (old_val('role','employer') === 'employer') ? 'checked' : ''; ?> style="display:none">
        </label>
        <label class="auth-role-btn <?php echo (old_val('role') === 'adviser') ? 'active' : ''; ?>" onclick="selectRole('adviser',this)">
          <i class="fas fa-chalkboard-teacher"></i>
          <span>Adviser</span>
          <input type="radio" name="role" value="adviser" <?php echo (old_val('role') === 'adviser') ? 'checked' : ''; ?> style="display:none">
        </label>
      </div>
      <?php if (has_error('role')): ?>
        <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo get_error('role'); ?></div>
      <?php endif; ?>

      <div class="auth-divider"><span>register with email</span></div>

      <!-- Name fields -->
      <div class="auth-fields-grid">
        <div class="auth-field">
          <label class="auth-label" id="label-first-name">First name</label>
          <div class="auth-input-wrap">
            <i class="fas fa-user auth-input-icon"></i>
            <input class="auth-input <?php echo has_error('first_name') ? 'input-error' : ''; ?>" type="text" name="first_name" id="reg-first-name" placeholder="Juan" value="<?php echo old_val('first_name'); ?>" required>
          </div>
          <?php if (has_error('first_name')): ?>
            <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo get_error('first_name'); ?></div>
          <?php endif; ?>
        </div>
        <div class="auth-field">
          <label class="auth-label" id="label-last-name">Last name</label>
          <div class="auth-input-wrap">
            <i class="fas fa-user auth-input-icon"></i>
            <input class="auth-input <?php echo has_error('last_name') ? 'input-error' : ''; ?>" type="text" name="last_name" id="reg-last-name" placeholder="dela Cruz" value="<?php echo old_val('last_name'); ?>" required>
          </div>
          <?php if (has_error('last_name')): ?>
            <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo get_error('last_name'); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Email -->
      <div class="auth-field">
        <label class="auth-label">Email address</label>
        <div class="auth-input-wrap">
          <i class="fas fa-envelope auth-input-icon"></i>
          <input class="auth-input <?php echo has_error('email') ? 'input-error' : ''; ?>" type="email" name="email" placeholder="you@university.edu" value="<?php echo old_val('email'); ?>" required>
        </div>
        <?php if (has_error('email')): ?>
          <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo get_error('email'); ?></div>
        <?php endif; ?>
      </div>

      <!-- Dynamic org field -->
      <?php
        $r = old_val('role', 'employer');
        if (!in_array($r, ['employer', 'adviser'], true)) {
          $r = 'employer';
        }
      ?>
      <div class="auth-field" id="reg-org-field">
        <label class="auth-label" id="reg-org-label"><?php
          if ($r === 'employer') echo 'Company Name';
          elseif ($r === 'adviser') echo 'University / Department';
          else echo 'Company Name';
        ?></label>
        <div class="auth-input-wrap">
          <i class="fas <?php echo ($r === 'employer') ? 'fa-building' : 'fa-university'; ?> auth-input-icon" id="reg-org-icon"></i>
          <input class="auth-input" type="text" name="organization" id="reg-org-input"
            placeholder="<?php
              if ($r === 'employer') echo 'Acme Technologies Inc.';
              elseif ($r === 'adviser') echo 'UP College of Engineering';
              else echo 'Acme Technologies Inc.';
            ?>"
            value="<?php echo old_val('organization'); ?>">
        </div>
      </div>

      <!-- Password -->
      <div class="auth-field">
        <label class="auth-label">Password</label>
        <div class="auth-input-wrap">
          <i class="fas fa-lock auth-input-icon"></i>
          <input class="auth-input <?php echo has_error('password') ? 'input-error' : ''; ?>" type="password" name="password" id="reg-password" placeholder="Min. 8 characters" required>
          <button class="auth-eye-btn" type="button" onclick="togglePwd('reg-password',this)"><i class="fas fa-eye"></i></button>
        </div>
        <div class="auth-password-strength" id="pwd-strength-bar">
          <div class="pwd-strength-fill" id="pwd-fill"></div>
        </div>
        <div class="auth-pwd-hint" id="pwd-hint-text"></div>
        <?php if (has_error('password')): ?>
          <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo get_error('password'); ?></div>
        <?php endif; ?>
      </div>

      <!-- Terms -->
      <label class="auth-checkbox-label auth-terms">
        <input type="checkbox" class="auth-checkbox" name="terms" id="terms-check" required>
        <span class="auth-checkmark"></span>
        I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
      </label>

      <!-- Submit -->
      <button type="submit" class="auth-submit-btn">
        <span>Create Account</span>
        <i class="fas fa-arrow-right"></i>
      </button>
    </form>

    <p class="auth-switch-text">
      Already have an account?
      <a href="login.php">Sign in</a>
    </p>
  </div>

</div>

<script>
function selectRole(role, el) {
  document.querySelectorAll('.auth-role-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  el.querySelector('input[type=radio]').checked = true;

  const orgLabels = {
    employer: ['Company Name',             'fa-building',   'Acme Technologies Inc.',         'Contact Person First Name', 'Contact Person Last Name', 'Maria', 'Santos'],
    adviser:  ['University / Department',  'fa-university', 'UP College of Engineering',      'First name', 'Last name', 'Juan', 'dela Cruz']
  };
  const [lbl, icon, ph, fnLabel, lnLabel, fnPh, lnPh] = orgLabels[role] || orgLabels.employer;
  document.getElementById('reg-org-label').textContent = lbl;
  document.getElementById('reg-org-icon').className = 'fas ' + icon + ' auth-input-icon';
  document.getElementById('reg-org-input').placeholder = ph;
  document.getElementById('label-first-name').textContent = fnLabel;
  document.getElementById('label-last-name').textContent = lnLabel;
  document.getElementById('reg-first-name').placeholder = fnPh;
  document.getElementById('reg-last-name').placeholder = lnPh;
}

function togglePwd(inputId, btn) {
  const inp = document.getElementById(inputId);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// Password strength indicator
document.addEventListener('DOMContentLoaded', function() {
  const pwdInp = document.getElementById('reg-password');
  if (pwdInp) {
    pwdInp.addEventListener('input', function() {
      const val = this.value;
      const fill = document.getElementById('pwd-fill');
      const hint = document.getElementById('pwd-hint-text');
      let score = 0;
      if (val.length >= 8) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;
      const levels = [
        { w: '0%', bg: '#ffffff', txt: '' },
        { w: '25%', bg: '#138b84', txt: 'Weak' },
        { w: '50%', bg: '#138b84', txt: 'Fair' },
        { w: '75%', bg: '#138b84', txt: 'Good' },
        { w: '100%', bg: '#138b84', txt: 'Strong \u2713' },
      ];
      const l = levels[val.length === 0 ? 0 : score];
      fill.style.width = l.w;
      fill.style.background = l.bg;
      hint.textContent = l.txt;
      hint.style.color = l.bg;
    });
  }

  // Restore role-specific labels on page reload with errors
  const checkedRole = document.querySelector('input[name="role"]:checked');
  if (checkedRole) {
    const btn = checkedRole.closest('.auth-role-btn');
    if (btn) selectRole(checkedRole.value, btn);
  } else {
    const defaultBtn = document.querySelector('.auth-role-btn');
    if (defaultBtn) selectRole('employer', defaultBtn);
  }
});
</script>
</body>
</html>

