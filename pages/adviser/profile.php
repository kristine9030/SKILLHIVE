<?php
require_once __DIR__ . '/../../backend/db_connect.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';
$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

if ($adviserId <= 0) {
    header('Location: ' . $baseUrl . '/pages/auth/login.php');
    exit;
}

if (!function_exists('adviser_profile_escape')) {
    function adviser_profile_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_profile_has_column')) {
    function adviser_profile_has_column(PDO $pdo, string $columnName): bool
    {
        static $cache = [];
        $key = strtolower(trim($columnName));
        if ($key === '') {
            return false;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "internship_adviser"
               AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $stmt->execute([$columnName]);
        $cache[$key] = (bool)$stmt->fetchColumn();

        return $cache[$key];
    }
}

if (!function_exists('adviser_profile_initials')) {
    function adviser_profile_initials(string $firstName, string $lastName): string
    {
        $first = trim($firstName);
        $last = trim($lastName);

        $initials = '';
        if ($first !== '') {
            $initials .= strtoupper(substr($first, 0, 1));
        }
        if ($last !== '') {
            $initials .= strtoupper(substr($last, 0, 1));
        }

        return $initials !== '' ? $initials : 'AD';
    }
}

$form = [
    'first_name' => '',
    'last_name' => '',
    'department' => '',
    'email' => '',
];

$profileError = '';
$passwordError = '';

try {
    $stmt = $pdo->prepare(
        'SELECT adviser_id, first_name, last_name, department, email
         FROM internship_adviser
         WHERE adviser_id = ?
         LIMIT 1'
    );
    $stmt->execute([$adviserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row && !empty($_SESSION['user_email'])) {
        $stmt = $pdo->prepare(
            'SELECT adviser_id, first_name, last_name, department, email
             FROM internship_adviser
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([(string)$_SESSION['user_email']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) {
        $profileError = 'Adviser profile record not found.';
    } else {
        $adviserId = (int)($row['adviser_id'] ?? $adviserId);
        $_SESSION['adviser_id'] = $adviserId;

        $form['first_name'] = trim((string)($row['first_name'] ?? ''));
        $form['last_name'] = trim((string)($row['last_name'] ?? ''));
        $form['department'] = trim((string)($row['department'] ?? ''));
        $form['email'] = trim((string)($row['email'] ?? ''));
    }
} catch (Throwable $e) {
    $profileError = 'Unable to load profile details right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profileError === '') {
    $action = trim((string)($_POST['action'] ?? 'update_profile'));

    if ($action === 'update_profile') {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));

        $form['first_name'] = $firstName;
        $form['last_name'] = $lastName;
        $form['department'] = $department;

        if ($firstName === '') {
            $profileError = 'First name is required.';
        } elseif ($lastName === '') {
            $profileError = 'Last name is required.';
        }

        if ($profileError === '') {
            try {
                $hasUpdatedAtColumn = adviser_profile_has_column($pdo, 'updated_at');
                $sql = $hasUpdatedAtColumn
                    ? 'UPDATE internship_adviser
                       SET first_name = ?,
                           last_name = ?,
                           department = ?,
                           updated_at = NOW()
                       WHERE adviser_id = ?'
                    : 'UPDATE internship_adviser
                       SET first_name = ?,
                           last_name = ?,
                           department = ?
                       WHERE adviser_id = ?';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$firstName, $lastName, $department, $adviserId]);

                $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
                $_SESSION['department'] = $department;
                $_SESSION['status'] = 'Adviser profile updated successfully.';

                header('Location: ' . $baseUrl . '/layout.php?page=adviser/profile');
                exit;
            } catch (Throwable $e) {
                $profileError = 'Unable to save profile details right now.';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $passwordError = 'All password fields are required.';
        } elseif (strlen($newPassword) < 8) {
            $passwordError = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New password and confirmation do not match.';
        }

        if ($passwordError === '') {
            try {
                $stmt = $pdo->prepare(
                    'SELECT password_hash
                     FROM internship_adviser
                     WHERE adviser_id = ?
                     LIMIT 1'
                );
                $stmt->execute([$adviserId]);
                $storedHash = (string)($stmt->fetchColumn() ?? '');

                if ($storedHash === '' || !password_verify($currentPassword, $storedHash)) {
                    $passwordError = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $hasUpdatedAtColumn = adviser_profile_has_column($pdo, 'updated_at');

                    $updateSql = $hasUpdatedAtColumn
                        ? 'UPDATE internship_adviser
                           SET password_hash = ?,
                               updated_at = NOW()
                           WHERE adviser_id = ?'
                        : 'UPDATE internship_adviser
                           SET password_hash = ?
                           WHERE adviser_id = ?';

                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$newHash, $adviserId]);

                    $_SESSION['status'] = 'Password updated successfully.';
                    header('Location: ' . $baseUrl . '/layout.php?page=adviser/profile');
                    exit;
                }
            } catch (Throwable $e) {
                $passwordError = 'Unable to update password right now.';
            }
        }
    }
}

$stats = [
    'assigned_students' => 0,
    'pending_endorsements' => 0,
];

try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT student_id)
         FROM adviser_assignment
         WHERE adviser_id = ?
           AND COALESCE(NULLIF(TRIM(status), ""), "Active") = "Active"'
    );
    $stmt->execute([$adviserId]);
    $stats['assigned_students'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

try {
  $stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM endorsement
     WHERE adviser_id = ?
       AND LOWER(COALESCE(status, "")) IN ("pending", "for review", "submitted", "reviewing")'
  );
  $stmt->execute([$adviserId]);
  $stats['pending_endorsements'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

$adviserName = trim($form['first_name'] . ' ' . $form['last_name']);
if ($adviserName === '') {
    $adviserName = trim((string)($_SESSION['user_name'] ?? 'Adviser'));
}
?>

<style>
  .adviser-profile-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
    color: var(--text);
    font-size: var(--font-size-body);
  }

  .adviser-profile-cover {
    height: 180px;
    background: linear-gradient(135deg, #0a0e27 0%, #162550 40%, #1a3a5c 70%, #0f2a45 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    margin-bottom: -60px;
    z-index: 1;
  }

  .adviser-profile-cover::before {
    content: '';
    position: absolute;
    top: -30px;
    right: -30px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    pointer-events: none;
  }

  .adviser-profile-cover::after {
    content: '';
    position: absolute;
    bottom: -40px;
    left: -20px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
  }

  .adviser-profile-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, .82fr);
    gap: 16px;
    position: relative;
    z-index: 2;
  }

  .adviser-profile-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: all var(--transition);
  }

  .adviser-profile-panel::before {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.06);
    pointer-events: none;
  }

  .adviser-profile-panel::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: -20px;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.04);
    pointer-events: none;
  }

  .adviser-profile-panel:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  }

  .adviser-profile-title {
    margin: 0 0 18px;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    position: relative;
    z-index: 1;
  }

  .adviser-profile-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .adviser-profile-label {
    display: block;
    margin: 0 0 8px;
    font-size: .82rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-profile-input {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
    color: var(--text);
    font-size: .86rem;
    padding: 12px 14px;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
  }

  .adviser-profile-input:focus {
    border-color: #8B5CF6;
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
  }

  .adviser-profile-input[readonly] {
    background: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
  }

  .adviser-profile-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 12px;
    border: none;
    min-height: 42px;
    padding: 0 20px;
    cursor: pointer;
    text-decoration: none;
    font-size: .86rem;
    font-weight: 700;
    background: #111827;
    color: #fff;
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.3);
    transition: all .2s ease;
  }

  .adviser-profile-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 24, 39, 0.4);
    background: #0f172a;
  }

  .adviser-profile-error {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: .82rem;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-card {
    text-align: center;
  }

  .adviser-profile-avatar {
    width: 100px;
    height: 100px;
    margin: 0 auto 16px;
    border-radius: 999px;
    background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 700;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
    border: 4px solid #fff;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-name {
    margin: 0 0 8px;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text);
    position: relative;
    z-index: 1;
  }

  .adviser-profile-meta {
    margin: 4px 0 0;
    color: var(--text3);
    font-size: .86rem;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-stat {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px 12px;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    transition: all .2s ease;
  }

  .adviser-profile-stat:hover {
    border-color: #d1d5db;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
  }

  .adviser-profile-stat-value {
    font-size: 1.4rem;
    font-weight: 800;
    color: #8B5CF6;
  }

  .adviser-profile-stat-label {
    margin-top: 6px;
    font-size: .75rem;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 600;
  }

  @media (max-width: 1000px) {
    .adviser-profile-layout {
      grid-template-columns: 1fr;
    }

    .adviser-profile-cover {
      height: 150px;
      margin-bottom: -50px;
    }
  }

  @media (max-width: 640px) {
    .adviser-profile-grid {
      grid-template-columns: 1fr;
    }

    .adviser-profile-stats {
      grid-template-columns: 1fr;
    }

    .adviser-profile-panel {
      padding: 18px;
    }
  }
</style>

<div class="adviser-profile-page">
  <div class="adviser-profile-cover"></div>

  <div class="adviser-profile-layout">
    <section class="adviser-profile-panel">
      <h3 class="adviser-profile-title">Profile Details</h3>

      <?php if ($profileError !== ''): ?>
        <div class="adviser-profile-error" style="margin-bottom:14px;"><?php echo adviser_profile_escape($profileError); ?></div>
      <?php endif; ?>

      <form class="adviser-profile-form" method="post" action="<?php echo $baseUrl; ?>/layout.php?page=adviser/profile">
        <input type="hidden" name="action" value="update_profile">

        <div class="adviser-profile-grid">
          <div>
            <label class="adviser-profile-label" for="adviserFirstName">First Name</label>
            <input id="adviserFirstName" class="adviser-profile-input" type="text" name="first_name" value="<?php echo adviser_profile_escape($form['first_name']); ?>" required>
          </div>

          <div>
            <label class="adviser-profile-label" for="adviserLastName">Last Name</label>
            <input id="adviserLastName" class="adviser-profile-input" type="text" name="last_name" value="<?php echo adviser_profile_escape($form['last_name']); ?>" required>
          </div>
        </div>

        <div>
          <label class="adviser-profile-label" for="adviserDepartment">Department</label>
          <input id="adviserDepartment" class="adviser-profile-input" type="text" name="department" value="<?php echo adviser_profile_escape($form['department']); ?>" placeholder="e.g. College of Informatics">
        </div>

        <div>
          <label class="adviser-profile-label" for="adviserEmail">Email</label>
          <input id="adviserEmail" class="adviser-profile-input" type="email" value="<?php echo adviser_profile_escape($form['email']); ?>" readonly>
        </div>

        <div>
          <button class="adviser-profile-btn" type="submit"><i class="fas fa-floppy-disk"></i> Save Profile</button>
        </div>
      </form>
    </section>

    <aside class="adviser-profile-panel adviser-profile-card">
      <div class="adviser-profile-avatar"><?php echo adviser_profile_escape(adviser_profile_initials($form['first_name'], $form['last_name'])); ?></div>
      <p class="adviser-profile-name"><?php echo adviser_profile_escape($adviserName); ?></p>
      <p class="adviser-profile-meta"><?php echo adviser_profile_escape($form['email']); ?></p>
      <p class="adviser-profile-meta"><?php echo adviser_profile_escape($form['department'] !== '' ? $form['department'] : 'Department not set'); ?></p>

      <div class="adviser-profile-stats">
        <div class="adviser-profile-stat">
          <div class="adviser-profile-stat-value"><?php echo (int)$stats['assigned_students']; ?></div>
          <div class="adviser-profile-stat-label">Students</div>
        </div>
        <div class="adviser-profile-stat">
          <div class="adviser-profile-stat-value"><?php echo (int)$stats['pending_endorsements']; ?></div>
          <div class="adviser-profile-stat-label">Pending Review</div>
        </div>
      </div>
    </aside>
  </div>

  <section class="adviser-profile-panel">
    <h3 class="adviser-profile-title">Security</h3>

    <?php if ($passwordError !== ''): ?>
      <div class="adviser-profile-error" style="margin-bottom:10px;"><?php echo adviser_profile_escape($passwordError); ?></div>
    <?php endif; ?>

    <form class="adviser-profile-form" method="post" action="<?php echo $baseUrl; ?>/layout.php?page=adviser/profile">
      <input type="hidden" name="action" value="change_password">

      <div class="adviser-profile-grid">
        <div>
          <label class="adviser-profile-label" for="currentPassword">Current Password</label>
          <input id="currentPassword" class="adviser-profile-input" type="password" name="current_password" required>
        </div>

        <div></div>

        <div>
          <label class="adviser-profile-label" for="newPassword">New Password</label>
          <input id="newPassword" class="adviser-profile-input" type="password" name="new_password" minlength="8" required>
        </div>

        <div>
          <label class="adviser-profile-label" for="confirmPassword">Confirm New Password</label>
          <input id="confirmPassword" class="adviser-profile-input" type="password" name="confirm_password" minlength="8" required>
        </div>
      </div>

      <div>
        <button class="adviser-profile-btn" type="submit"><i class="fas fa-key"></i> Update Password</button>
      </div>
    </form>
  </section>
</div>
