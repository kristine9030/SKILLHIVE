<?php
// auth.php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('set_last_login_error')) {
    function set_last_login_error(string $message): void
    {
        $_SESSION['login_error'] = $message;
    }
}

if (!function_exists('get_last_login_error')) {
    function get_last_login_error(): string
    {
        $message = trim((string)($_SESSION['login_error'] ?? ''));
        unset($_SESSION['login_error']);
        return $message;
    }
}

function upgrade_user_password_hash($user, $password) {
    global $pdo;

    $role = strtolower((string)($user['role'] ?? ''));
    $id = (int)($user['id'] ?? 0);
    if ($id <= 0) {
        return;
    }

    $map = [
        'admin' => ['table' => 'admin', 'id_column' => 'admin_id'],
        'employer' => ['table' => 'employer', 'id_column' => 'employer_id'],
        'student' => ['table' => 'student', 'id_column' => 'student_id'],
        'adviser' => ['table' => 'internship_adviser', 'id_column' => 'adviser_id'],
    ];

    if (!isset($map[$role])) {
        return;
    }

    try {
        $newHash = password_hash((string)$password, PASSWORD_DEFAULT);
        $sql = "UPDATE {$map[$role]['table']} SET password_hash = ?, updated_at = NOW() WHERE {$map[$role]['id_column']} = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newHash, $id]);
    } catch (Throwable $e) {
        // Non-fatal: login should continue even if rehash upgrade cannot be persisted.
    }
}

function verify_and_upgrade_password($user, $password) {
    $stored = (string)($user['password_hash'] ?? '');
    if ($stored === '') {
        return false;
    }

    if (password_verify((string)$password, $stored)) {
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            upgrade_user_password_hash($user, $password);
        }
        return true;
    }

    // Backward compatibility for legacy plaintext and md5/sha1 stored passwords.
    $legacyMatched = false;
    if (hash_equals($stored, (string)$password)) {
        $legacyMatched = true;
    } else {
        $storedLower = strtolower($stored);
        if (preg_match('/^[a-f0-9]{32}$/', $storedLower) && hash_equals($storedLower, md5((string)$password))) {
            $legacyMatched = true;
        } elseif (preg_match('/^[a-f0-9]{40}$/', $storedLower) && hash_equals($storedLower, sha1((string)$password))) {
            $legacyMatched = true;
        }
    }

    if ($legacyMatched) {
        upgrade_user_password_hash($user, $password);
        return true;
    }

    return false;
}

// Login function
function login($email, $password) {
    global $pdo;

    unset($_SESSION['login_error']);

    $user = null;

    // 1️⃣ Check Admin table
    $stmt = $pdo->prepare("SELECT admin_id AS id, first_name, last_name, email, password_hash, role_level, 'admin' AS role FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2️⃣ Check Employer table
    if (!$user) {
        $stmt = $pdo->prepare("SELECT employer_id AS id, company_name AS first_name, '' AS last_name, email, password_hash, verification_status, 'employer' AS role FROM employer WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3️⃣ Check Student table
    if (!$user) {
        try {
            $stmt = $pdo->prepare("SELECT student_id AS id, first_name, last_name, email, password_hash, student_number, program, department, year_level, COALESCE(must_change_password, 0) AS must_change_password, 'student' AS role FROM student WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // Backward compatibility if column is missing or migration could not run.
            $stmt = $pdo->prepare("SELECT student_id AS id, first_name, last_name, email, password_hash, student_number, program, department, year_level, 'student' AS role FROM student WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user['must_change_password'] = 0;
            }
        }
    }

    // 4️⃣ Check Internship Adviser table
    if (!$user) {
        $stmt = $pdo->prepare("SELECT adviser_id AS id, first_name, last_name, department, email, password_hash, 'adviser' AS role FROM internship_adviser WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($user && verify_and_upgrade_password($user, $password)) {
        if (($user['role'] ?? '') === 'employer') {
            $verificationStatus = strtolower(trim((string)($user['verification_status'] ?? '')));
            if ($verificationStatus !== 'approved') {
                set_last_login_error('Employer account is pending admin verification. Login is allowed after approval.');
                return false;
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['role'] = $user['role']; // compatibility with layout.php
        unset(
            $_SESSION['student_id'],
            $_SESSION['adviser_id'],
            $_SESSION['must_change_password'],
            $_SESSION['employer_id']
        );

        switch ($user['role']) {
            case 'admin':
                $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $_SESSION['role_level'] = $user['role_level'] ?? null;
                break;

            case 'employer':
                $_SESSION['user_name'] = $user['first_name'] ?? 'Employer';
                $_SESSION['employer_id'] = $user['id'];
                $_SESSION['verification_status'] = $user['verification_status'] ?? null;
                break;

            case 'student':
                $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['student_number'] = $user['student_number'] ?? null;
                $_SESSION['program'] = $user['program'] ?? null;
                $_SESSION['department'] = $user['department'] ?? null;
                $_SESSION['year_level'] = $user['year_level'] ?? null;
                $_SESSION['must_change_password'] = ((int)($user['must_change_password'] ?? 0)) === 1;
                break;

            case 'adviser':
                $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $_SESSION['adviser_id'] = $user['id'];
                $_SESSION['department'] = $user['department'] ?? null;
                break;
        }

        return true;
    }

    return false;
}

// Check if logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Logout
function logout() {
    session_unset();
    session_destroy();
}
?>