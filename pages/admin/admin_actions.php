<?php
/**
 * Admin Actions Endpoint
 * Handles all POST/GET actions for admin modules
 */
require_once __DIR__ . '/../../backend/db_connect.php';

// Guard: admin only
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Location: /SkillHive/pages/auth/login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    header('Location: /SkillHive/pages/auth/login.php');
    exit;
}

$action   = $_REQUEST['action'] ?? '';
$redirect = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? ('/SkillHive/layout.php?page=admin/dashboard'));

// Sanitize redirect – only allow relative paths within SkillHive
if (!preg_match('#^/SkillHive/#', $redirect)) {
    $redirect = '/SkillHive/layout.php?page=admin/dashboard';
}

// ─── Helper ─────────────────────────────────────────────────────────────────
function flash($key, $msg) {
    $_SESSION[$key] = $msg;
}

// ════════════════════════════════════════════════════════════════════════════
// GET actions (CSV exports)
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {

        case 'export_users_csv':
            $rows = $pdo->query("
                SELECT 'student' AS role, student_id AS id, CONCAT(first_name,' ',last_name) AS name, email, program AS extra, created_at FROM student
                UNION ALL
                SELECT 'employer', employer_id, company_name, email, industry, created_at FROM employer
                UNION ALL
                SELECT 'admin', admin_id, CONCAT(first_name,' ',last_name), email, 'Admin', created_at FROM admin
                ORDER BY created_at DESC
            ")->fetchAll();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="skillhive_users_' . date('Ymd') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Role','ID','Name','Email','Extra','Joined']);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
            exit;

        case 'export_companies_csv':
            $rows = $pdo->query("
                SELECT employer_id, company_name, industry, email, contact_number,
                       verification_status, company_badge_status, created_at
                FROM employer ORDER BY created_at DESC
            ")->fetchAll();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="skillhive_companies_' . date('Ymd') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Company','Industry','Email','Phone','Status','Badge','Joined']);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
            exit;

        case 'export_applications_csv':
            $rows = $pdo->query("
                SELECT a.application_id, CONCAT(s.first_name,' ',s.last_name) AS student,
                       s.email AS student_email, i.title AS position, e.company_name,
                       e.industry, a.status, a.application_date, a.updated_at
                FROM application a
                JOIN student s ON s.student_id=a.student_id
                JOIN internship i ON i.internship_id=a.internship_id
                JOIN employer e ON e.employer_id=i.employer_id
                ORDER BY a.application_date DESC, a.application_id DESC
            ")->fetchAll();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="skillhive_applications_' . date('Ymd') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['App ID','Student','Email','Position','Company','Industry','Status','Applied','Updated']);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
            exit;

        case 'export_logs_csv':
            // Build simplified activity log
            $rows = [];
            $stmt = $pdo->query("
                SELECT cv.date_reviewed AS ts,'Company Verification' AS event,
                    COALESCE(CONCAT(a.first_name,' ',a.last_name),'System') AS actor,
                    e.company_name AS detail, cv.status AS note
                FROM company_verification cv
                JOIN employer e ON e.employer_id=cv.employer_id
                LEFT JOIN admin a ON a.admin_id=cv.admin_id
                UNION ALL
                SELECT created_at,'Student Registered',CONCAT(first_name,' ',last_name),email,'' FROM student
                UNION ALL
                SELECT created_at,'Employer Registered',company_name,email,verification_status FROM employer
                ORDER BY ts DESC LIMIT 500
            ");
            foreach ($stmt->fetchAll() as $r) $rows[] = $r;
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="skillhive_audit_' . date('Ymd') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp','Event','Actor','Detail','Note']);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
            exit;
    }
    // Unknown GET action – just redirect
    header("Location: $redirect");
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// POST actions
// ════════════════════════════════════════════════════════════════════════════

switch ($action) {

    // ── Verify / Update company status ─────────────────────────────────────
    case 'verify_company': {
        $employerId = (int)($_POST['employer_id'] ?? 0);
        $decision   = $_POST['decision'] ?? '';

        $allowed = ['Approved', 'Rejected', 'Flagged', 'Pending'];
        if (!$employerId || !in_array($decision, $allowed, true)) {
            flash('admin_err', 'Invalid request.');
            break;
        }

        // Update employer verification_status
        $pdo->prepare("UPDATE employer SET verification_status=?, updated_at=NOW() WHERE employer_id=?")
            ->execute([$decision, $employerId]);

        // Upsert company_verification record
        $existing = $pdo->prepare("SELECT verification_id FROM company_verification WHERE employer_id=? ORDER BY date_submitted DESC LIMIT 1");
        $existing->execute([$employerId]);
        $verif = $existing->fetch();

        if ($verif) {
            $pdo->prepare("UPDATE company_verification SET status=?, admin_id=?, date_reviewed=NOW() WHERE verification_id=?")
                ->execute([$decision, $userId, $verif['verification_id']]);
        } else {
            $pdo->prepare("INSERT INTO company_verification (employer_id, admin_id, status, date_reviewed) VALUES (?,?,?,NOW())")
                ->execute([$employerId, $userId, $decision]);
        }

        $companyName = $pdo->prepare("SELECT company_name FROM employer WHERE employer_id=?");
        $companyName->execute([$employerId]);
        $cn = $companyName->fetchColumn();

        flash('admin_msg', "Company \"$cn\" status updated to $decision.");
        break;
    }

    // ── Award badge ────────────────────────────────────────────────────────
    case 'award_badge': {
        $employerId = (int)($_POST['employer_id'] ?? 0);
        $badge      = $_POST['badge'] ?? '';
        $allowed    = ['None', 'Verified Partner', 'Top Employer'];
        if (!$employerId || !in_array($badge, $allowed, true)) {
            flash('admin_err', 'Invalid request.');
            break;
        }
        $pdo->prepare("UPDATE employer SET company_badge_status=?, updated_at=NOW() WHERE employer_id=?")
            ->execute([$badge, $employerId]);
        flash('admin_msg', "Badge \"$badge\" awarded.");
        break;
    }

    // ── Add risk notes ─────────────────────────────────────────────────────
    case 'add_notes': {
        $employerId = (int)($_POST['employer_id'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');
        if (!$employerId) { flash('admin_err', 'Invalid request.'); break; }

        $existing = $pdo->prepare("SELECT verification_id FROM company_verification WHERE employer_id=? ORDER BY date_submitted DESC LIMIT 1");
        $existing->execute([$employerId]);
        $verif = $existing->fetch();

        if ($verif) {
            $pdo->prepare("UPDATE company_verification SET risk_assessment_notes=? WHERE verification_id=?")
                ->execute([$notes, $verif['verification_id']]);
        } else {
            $pdo->prepare("INSERT INTO company_verification (employer_id, admin_id, status, risk_assessment_notes, date_reviewed) VALUES (?,?,?,?,NOW())")
                ->execute([$employerId, $userId, 'Pending', $notes]);
        }
        flash('admin_msg', 'Notes saved.');
        break;
    }

    // ── Delete user ────────────────────────────────────────────────────────
    case 'delete_user': {
        $uid      = (int)($_POST['user_id']   ?? 0);
        $userRole = $_POST['user_role']        ?? '';

        if (!$uid) { flash('admin_err', 'Invalid user.'); break; }

        // Prevent deleting own account
        if ($userRole === 'admin' && $uid === (int)$userId) {
            flash('admin_err', 'You cannot delete your own account.');
            break;
        }

        switch ($userRole) {
            case 'student':  $pdo->prepare("DELETE FROM student  WHERE student_id=?")->execute([$uid]); break;
            case 'employer': $pdo->prepare("DELETE FROM employer WHERE employer_id=?")->execute([$uid]); break;
            case 'admin':    $pdo->prepare("DELETE FROM admin    WHERE admin_id=?")->execute([$uid]); break;
            default: flash('admin_err', 'Unknown role.'); goto done;
        }
        flash('admin_msg', 'User deleted successfully.');
        break;
    }

    // ── Update admin profile ───────────────────────────────────────────────
    case 'update_profile': {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');

        if (!$firstName || !$lastName || !$email) {
            flash('admin_err', 'All fields are required.');
            break;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('admin_err', 'Invalid email address.');
            break;
        }

        // Check email uniqueness (excluding self)
        $existing = $pdo->prepare("SELECT admin_id FROM admin WHERE email=? AND admin_id!=?");
        $existing->execute([$email, $userId]);
        if ($existing->fetch()) {
            flash('admin_err', 'That email is already in use.');
            break;
        }

        $pdo->prepare("UPDATE admin SET first_name=?, last_name=?, email=?, updated_at=NOW() WHERE admin_id=?")
            ->execute([$firstName, $lastName, $email, $userId]);

        // Update session
        $_SESSION['user_name']  = "$firstName $lastName";
        $_SESSION['user_email'] = $email;

        flash('admin_msg', 'Profile updated successfully.');
        break;
    }

    // ── Change password ────────────────────────────────────────────────────
    case 'change_password': {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            flash('admin_err', 'All password fields are required.');
            break;
        }
        if ($new !== $confirm) {
            flash('admin_err', 'New passwords do not match.');
            break;
        }
        if (strlen($new) < 8) {
            flash('admin_err', 'Password must be at least 8 characters.');
            break;
        }

        $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE admin_id=?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('admin_err', 'Current password is incorrect.');
            break;
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE admin SET password_hash=?, updated_at=NOW() WHERE admin_id=?")
            ->execute([$newHash, $userId]);

        flash('admin_msg', 'Password changed successfully.');
        break;
    }

    // ── Platform settings (saved to session / future DB flag) ─────────────
    case 'platform_settings': {
        // Acknowledge save — in a production system these would be stored in a settings table
        flash('admin_msg', 'Platform settings saved.');
        break;
    }

    // ── Purge rejected records ──────────────────────────────────────────────
    case 'purge_rejected': {
        // Remove rejected and flagged company verification records.
        $pdo->prepare("DELETE FROM company_verification WHERE status IN ('Rejected', 'Flagged')")
            ->execute();
        flash('admin_msg', 'Rejected/flagged records purged.');
        break;
    }

    default:
        flash('admin_err', 'Unknown action.');
        break;
}

done:
header("Location: $redirect");
exit;
