<?php
require_once __DIR__ . '/../../backend/db_connect.php';

$currentRole = (string)($role ?? ($_SESSION['role'] ?? ''));
$studentId = (int)($_SESSION['student_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($currentRole !== 'student' || $studentId <= 0) {
    header('Location: ' . $baseUrl . '/layout.php');
    exit;
}

$errors = [];

$stateStmt = $pdo->prepare(
    'SELECT must_change_password
     FROM student
     WHERE student_id = :student_id
     LIMIT 1'
);
$stateStmt->execute([':student_id' => $studentId]);
$studentState = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$studentState) {
    $errors['form'] = 'Student account not found.';
} elseif ((int)($studentState['must_change_password'] ?? 0) !== 1) {
    $_SESSION['must_change_password'] = false;
    header('Location: ' . $baseUrl . '/layout.php?page=student/dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($newPassword === '') {
        $errors['new_password'] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare(
            'UPDATE student
             SET password_hash = :password_hash,
                 must_change_password = 0,
                 updated_at = NOW()
             WHERE student_id = :student_id'
        );
        $updateStmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':student_id' => $studentId,
        ]);

        $_SESSION['must_change_password'] = false;
        $_SESSION['status'] = 'Password updated. Please complete your profile details.';

        header('Location: ' . $baseUrl . '/layout.php?page=student/profile');
        exit;
    }
}
?>

<style>
  .student-first-login-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1250;
    padding: 18px;
  }

  .student-first-login-wrap {
    width: min(560px, 100%);
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 26px 28px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.26);
  }

  .student-first-login-title {
    margin: 0;
    font-size: 1.2rem;
    color: var(--text);
  }

  .student-first-login-sub {
    margin: 8px 0 0;
    color: var(--text3);
    font-size: 0.9rem;
  }

  .student-first-login-form {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .student-first-login-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 6px;
    display: block;
  }

  .student-first-login-input-wrap {
    position: relative;
  }

  .student-first-login-input {
    width: 100%;
    height: 44px;
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 0 42px 0 12px;
    font-size: 0.9rem;
    color: var(--text);
    background: #fff;
    outline: none;
  }

  .student-first-login-input:focus {
    border-color: #111;
    box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.06);
  }

  .student-first-login-toggle {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: var(--text3);
    cursor: pointer;
  }

  .student-first-login-error {
    margin-top: 6px;
    font-size: 0.78rem;
    color: #dc2626;
  }

  .student-first-login-alert {
    margin-top: 14px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #12b3ac;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.82rem;
  }

  .student-first-login-btn {
    height: 44px;
    border: 0;
    border-radius: 999px;
    background: #111;
    color: #fff;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
  }

  @media (max-width: 640px) {
    .student-first-login-wrap {
      border-radius: 20px;
      padding: 22px 18px;
    }
  }
</style>

<div class="student-first-login-overlay">
  <div class="student-first-login-wrap" role="dialog" aria-modal="true" aria-labelledby="studentFirstLoginTitle">
    <h2 id="studentFirstLoginTitle" class="student-first-login-title">Set Your New Password</h2>
    <p class="student-first-login-sub">This is your first login. You must change your temporary password before using the student portal.</p>

    <?php if (!empty($errors['form'])): ?>
      <div class="student-first-login-alert"><?php echo htmlspecialchars((string)$errors['form']); ?></div>
    <?php endif; ?>

    <form class="student-first-login-form" method="post" action="<?php echo $baseUrl; ?>/layout.php?page=student/first-login">
      <div>
        <label class="student-first-login-label" for="newPassword">New Password</label>
        <div class="student-first-login-input-wrap">
          <input class="student-first-login-input" id="newPassword" type="password" name="new_password" required>
          <button class="student-first-login-toggle" type="button" onclick="toggleFirstLoginPassword('newPassword', this)"><i class="fas fa-eye"></i></button>
        </div>
        <?php if (!empty($errors['new_password'])): ?>
          <div class="student-first-login-error"><?php echo htmlspecialchars((string)$errors['new_password']); ?></div>
        <?php endif; ?>
      </div>

      <div>
        <label class="student-first-login-label" for="confirmPassword">Confirm Password</label>
        <div class="student-first-login-input-wrap">
          <input class="student-first-login-input" id="confirmPassword" type="password" name="confirm_password" required>
          <button class="student-first-login-toggle" type="button" onclick="toggleFirstLoginPassword('confirmPassword', this)"><i class="fas fa-eye"></i></button>
        </div>
        <?php if (!empty($errors['confirm_password'])): ?>
          <div class="student-first-login-error"><?php echo htmlspecialchars((string)$errors['confirm_password']); ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="student-first-login-btn">Update Password</button>
    </form>
  </div>
</div>

<script>
function toggleFirstLoginPassword(inputId, button) {
    var input = document.getElementById(inputId);
    if (!input) {
        return;
    }

    var isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';

    var icon = button.querySelector('i');
    if (icon) {
        icon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
    }
}
</script>
