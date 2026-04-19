<?php
/**
 * Purpose: Handles adviser add-student workflow and advisory assignment writes.
 * Tables/columns used: student(student_id, student_number, first_name, last_name, email, program, department, year_level, password_hash, must_change_password, availability_status, preferred_industry, resume_file, internship_readiness_score, profile_picture, created_at, updated_at), adviser_assignment(adviser_id, student_id, assigned_date, status), employer(email), internship_adviser(email), admin(email).
 */

if (!function_exists('adviser_students_program_options')) {
    function adviser_students_program_options(): array
    {
        return [
            [
                'value' => 'Bachelor of Science in Information Technology',
                'label' => 'Bachelor of Science in Information Technology',
            ],
        ];
    }
}

if (!function_exists('adviser_students_static_program_label')) {
    function adviser_students_static_program_label(): string
    {
        return 'Bachelor of Science in Information Technology';
    }
}

if (!function_exists('adviser_students_static_department_label')) {
    function adviser_students_static_department_label(): string
    {
        return 'College of Informatics and Computing Sciences';
    }
}

if (!function_exists('adviser_students_default_academic_year')) {
    function adviser_students_default_academic_year(): string
    {
        $start = (int)date('Y');
        return $start . '-' . ($start + 1);
    }
}

if (!function_exists('adviser_students_build_school_email')) {
    function adviser_students_build_school_email(string $studentNumber): string
    {
        $normalized = preg_replace('/\s+/', '', trim($studentNumber));
        if ($normalized === '' || $normalized === null) {
            return '';
        }

        return strtolower($normalized) . '@g.batstate-u.edu.ph';
    }
}

if (!function_exists('adviser_students_normalize_academic_year')) {
    function adviser_students_normalize_academic_year(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $trimmed, $matches) === 1) {
            return $matches[1] . '-' . $matches[2];
        }

        return $trimmed;
    }
}

if (!function_exists('adviser_students_has_academic_year_column')) {
    function adviser_students_has_academic_year_column(PDO $pdo): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "student"
               AND COLUMN_NAME = "academic_year"
             LIMIT 1'
        );
        $stmt->execute();
        $hasColumn = (bool)$stmt->fetchColumn();

        return $hasColumn;
    }
}

if (!function_exists('adviser_students_derive_year_level')) {
    function adviser_students_derive_year_level(string $studentNumber, string $academicYear): int
    {
        $defaultLevel = 3;

        if (
            preg_match('/^(\d{2})[-]/', trim($studentNumber), $studentMatch) !== 1
            || preg_match('/^(\d{4})-\d{4}$/', trim($academicYear), $yearMatch) !== 1
        ) {
            return $defaultLevel;
        }

        $entryYear = 2000 + (int)$studentMatch[1];
        $academicStartYear = (int)$yearMatch[1];
        $computed = ($academicStartYear - $entryYear) + 1;

        if ($computed < 1) {
            return 1;
        }

        if ($computed > 5) {
            return 5;
        }

        return $computed;
    }
}

if (!function_exists('adviser_students_year_level_options')) {
    function adviser_students_year_level_options(): array
    {
        return [
            ['value' => '1', 'label' => '1st Year'],
            ['value' => '2', 'label' => '2nd Year'],
            ['value' => '3', 'label' => '3rd Year'],
            ['value' => '4', 'label' => '4th Year'],
            ['value' => '5', 'label' => '5th Year'],
        ];
    }
}

if (!function_exists('adviser_students_default_add_form')) {
    function adviser_students_default_add_form(): array
    {
        return [
            'student_number' => '',
            'first_name' => '',
            'last_name' => '',
            'program' => adviser_students_static_program_label(),
            'department' => adviser_students_static_department_label(),
            'academic_year' => adviser_students_default_academic_year(),
            'year_level' => '3',
            'email' => '',
        ];
    }
}

if (!function_exists('adviser_students_generate_temp_password')) {
    function adviser_students_generate_temp_password(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($index = 0; $index < $length; $index++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
    }
}

if (!function_exists('adviser_students_assignment_has_assigned_date')) {
    function adviser_students_assignment_has_assigned_date(PDO $pdo): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "adviser_assignment"
               AND COLUMN_NAME = "assigned_date"
             LIMIT 1'
        );
        $stmt->execute();
        $hasColumn = (bool)$stmt->fetchColumn();

        return $hasColumn;
    }
}

if (!function_exists('adviser_students_email_in_use')) {
    function adviser_students_email_in_use(PDO $pdo, string $email, int $studentId = 0): bool
    {
        $studentStmt = $pdo->prepare(
            'SELECT student_id
             FROM student
             WHERE email = :email
               AND student_id <> :student_id
             LIMIT 1'
        );
        $studentStmt->execute([
            ':email' => $email,
            ':student_id' => $studentId,
        ]);

        if ($studentStmt->fetchColumn()) {
            return true;
        }

        $checks = [
            'SELECT 1 FROM employer WHERE email = ? LIMIT 1',
            'SELECT 1 FROM internship_adviser WHERE email = ? LIMIT 1',
            'SELECT 1 FROM admin WHERE email = ? LIMIT 1',
        ];

        foreach ($checks as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('adviser_students_build_login_url')) {
    function adviser_students_build_login_url(string $baseUrl = '/SkillHive'): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $trimmedBase = trim($baseUrl, '/');
        $normalizedBase = $trimmedBase !== '' ? '/' . $trimmedBase : '';

        return $scheme . '://' . $host . $normalizedBase . '/pages/auth/login.php';
    }
}

if (!function_exists('adviser_students_brevo_smtp_config')) {
    function adviser_students_brevo_smtp_config(): array
    {
        $login = '';
        $key = '';
        $fromEmail = '';

        if (defined('BREVO_SMTP_LOGIN')) {
            $login = trim((string)BREVO_SMTP_LOGIN);
        }
        if ($login === '') {
            $login = trim((string)(getenv('BREVO_SMTP_LOGIN') ?: ''));
        }

        if (defined('BREVO_SMTP_KEY')) {
            $key = trim((string)BREVO_SMTP_KEY);
        }
        if ($key === '') {
            $key = trim((string)(getenv('BREVO_SMTP_KEY') ?: ''));
        }

        if (defined('BREVO_FROM_EMAIL')) {
            $fromEmail = trim((string)BREVO_FROM_EMAIL);
        }
        if ($fromEmail === '') {
            $fromEmail = trim((string)(getenv('BREVO_FROM_EMAIL') ?: ''));
        }
        if ($fromEmail === '') {
            $fromEmail = $login;
        }

        return [
            'enabled' => ($login !== '' && $key !== ''),
            'login' => $login,
            'key' => $key,
            'from_email' => $fromEmail,
        ];
    }
}

if (!function_exists('adviser_students_resend_api_key')) {
    function adviser_students_resend_api_key(): string
    {
        $apiKey = '';

        if (defined('RESEND_API_KEY')) {
            $apiKey = trim((string)RESEND_API_KEY);
        }

        if ($apiKey === '') {
            $apiKey = trim((string)(getenv('RESEND_API_KEY') ?: ''));
        }

        return $apiKey;
    }
}

if (!function_exists('adviser_students_send_credentials_email_via_resend')) {
    function adviser_students_send_credentials_email_via_resend(array $args): array
    {
        $apiKey = adviser_students_resend_api_key();
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'Resend API key is not configured.'];
        }

        $studentEmail = trim((string)($args['student_email'] ?? ''));
        $studentName = trim((string)($args['student_name'] ?? 'Student'));
        $studentNumber = trim((string)($args['student_number'] ?? ''));
        $temporaryPassword = (string)($args['temp_password'] ?? '');
        $loginUrl = trim((string)($args['login_url'] ?? adviser_students_build_login_url('/SkillHive')));

        $fromEmail = defined('RESEND_FROM_EMAIL')
            ? trim((string)RESEND_FROM_EMAIL)
            : trim((string)(getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev'));

        if ($fromEmail === '') {
            $fromEmail = 'onboarding@resend.dev';
        }

        $safeName = htmlspecialchars($studentName !== '' ? $studentName : 'Student', ENT_QUOTES, 'UTF-8');
        $safeStudentNumber = htmlspecialchars($studentNumber !== '' ? $studentNumber : 'N/A', ENT_QUOTES, 'UTF-8');
        $safeStudentEmail = htmlspecialchars($studentEmail, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        $html =
            '<p>Hello ' . $safeName . ',</p>'
            . '<p>Your SkillHive student account is now ready.</p>'
            . '<ul>'
            . '<li><strong>Student Number:</strong> ' . $safeStudentNumber . '</li>'
            . '<li><strong>School Email:</strong> ' . $safeStudentEmail . '</li>'
            . '<li><strong>Temporary Password:</strong> ' . $safePassword . '</li>'
            . '<li><strong>Login Link:</strong> <a href="' . $safeLoginUrl . '">' . $safeLoginUrl . '</a></li>'
            . '</ul>'
            . '<p>Please log in and change your password immediately for security.</p>';

        $payload = [
            'from' => $fromEmail,
            'to' => [$studentEmail],
            'subject' => 'Your SkillHive Student Account Credentials',
            'html' => $html,
        ];

        $body = json_encode($payload);
        if (!is_string($body)) {
            return ['ok' => false, 'error' => 'Unable to prepare Resend payload.'];
        }

        $endpoint = 'https://api.resend.com/emails';

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch === false) {
                return ['ok' => false, 'error' => 'Unable to initialize Resend request.'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $responseRaw = curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($responseRaw)) {
                $responseRaw = '';
            }

            if ($curlError !== '') {
                return ['ok' => false, 'error' => 'Resend is unreachable: ' . $curlError];
            }

            $decoded = json_decode($responseRaw, true);
            if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['id'])) {
                return ['ok' => true, 'error' => ''];
            }

            $errorMessage = is_array($decoded) && !empty($decoded['message'])
                ? (string)$decoded['message']
                : 'Resend API returned HTTP ' . $statusCode . '.';

            return ['ok' => false, 'error' => $errorMessage];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer " . $apiKey . "\r\nContent-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $responseRaw = @file_get_contents($endpoint, false, $context);
        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/[0-9.]+\s+(\d+)/i', $headerLine, $matches) === 1) {
                    $statusCode = (int)$matches[1];
                    break;
                }
            }
        }

        if (!is_string($responseRaw)) {
            return ['ok' => false, 'error' => 'Resend is unreachable.'];
        }

        $decoded = json_decode($responseRaw, true);
        if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['id'])) {
            return ['ok' => true, 'error' => ''];
        }

        $errorMessage = is_array($decoded) && !empty($decoded['message'])
            ? (string)$decoded['message']
            : 'Resend API request failed.';

        return ['ok' => false, 'error' => $errorMessage];
    }
}

if (!function_exists('adviser_students_send_credentials_email')) {
    function adviser_students_send_credentials_email(array $args): array
    {
        $studentEmail = trim((string)($args['student_email'] ?? ''));
        $studentName = trim((string)($args['student_name'] ?? 'Student'));
        $studentNumber = trim((string)($args['student_number'] ?? ''));
        $temporaryPassword = (string)($args['temp_password'] ?? '');
        $loginUrl = trim((string)($args['login_url'] ?? adviser_students_build_login_url('/SkillHive')));
        $apiUrl = '';

        if (defined('SKILLHIVE_EMAIL_API_URL')) {
            $apiUrl = trim((string)constant('SKILLHIVE_EMAIL_API_URL'));
        }
        if ($apiUrl === '') {
            $apiUrl = trim((string)(getenv('SKILLHIVE_EMAIL_API_URL') ?: ''));
        }

        if ($apiUrl === '') {
            $apiUrl = 'http://127.0.0.1:3100/send-email';
        }

        if ($studentEmail === '' || !filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Missing or invalid student email.'];
        }

        if ($temporaryPassword === '') {
            return ['ok' => false, 'error' => 'Temporary password was not generated.'];
        }

        $brevoConfig = adviser_students_brevo_smtp_config();
        $canUseResend = adviser_students_resend_api_key() !== '' && empty($brevoConfig['enabled']);

        $messageLines = [
            'Your SkillHive student account is now ready.',
            'Student Number: ' . ($studentNumber !== '' ? $studentNumber : 'N/A'),
            'School Email: ' . $studentEmail,
            'Temporary Password: ' . $temporaryPassword,
            'Login Link: ' . $loginUrl,
            'Please log in and change your password immediately for security.',
        ];

        $payload = [
            'studentEmail' => $studentEmail,
            'studentName' => ($studentName !== '' ? $studentName : 'Student'),
            'subject' => 'Your SkillHive Student Account Credentials',
            'message' => implode("\n", $messageLines),
        ];

        if (!empty($brevoConfig['enabled'])) {
            $payload['provider'] = 'brevo';
            $payload['smtpLogin'] = (string)$brevoConfig['login'];
            $payload['smtpKey'] = (string)$brevoConfig['key'];
            $payload['fromEmail'] = (string)$brevoConfig['from_email'];
        }

        $body = json_encode($payload);
        if (!is_string($body)) {
            return ['ok' => false, 'error' => 'Unable to prepare email payload.'];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            if ($ch === false) {
                return ['ok' => false, 'error' => 'Unable to initialize email request.'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $responseRaw = curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($responseRaw)) {
                $responseRaw = '';
            }

            if ($curlError !== '') {
                if ($canUseResend) {
                    return adviser_students_send_credentials_email_via_resend([
                        'student_name' => $studentName,
                        'student_email' => $studentEmail,
                        'student_number' => $studentNumber,
                        'temp_password' => $temporaryPassword,
                        'login_url' => $loginUrl,
                    ]);
                }

                return ['ok' => false, 'error' => 'Email API is unreachable: ' . $curlError];
            }

            $decoded = json_decode($responseRaw, true);
            if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['ok'])) {
                return ['ok' => true, 'error' => ''];
            }

            if ($canUseResend) {
                return adviser_students_send_credentials_email_via_resend([
                    'student_name' => $studentName,
                    'student_email' => $studentEmail,
                    'student_number' => $studentNumber,
                    'temp_password' => $temporaryPassword,
                    'login_url' => $loginUrl,
                ]);
            }

            $message = is_array($decoded) && !empty($decoded['error'])
                ? (string)$decoded['error']
                : 'Email API returned HTTP ' . $statusCode . '.';

            return ['ok' => false, 'error' => $message];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $responseRaw = @file_get_contents($apiUrl, false, $context);
        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/[0-9.]+\s+(\d+)/i', $headerLine, $matches) === 1) {
                    $statusCode = (int)$matches[1];
                    break;
                }
            }
        }

        if (!is_string($responseRaw)) {
            if ($canUseResend) {
                return adviser_students_send_credentials_email_via_resend([
                    'student_name' => $studentName,
                    'student_email' => $studentEmail,
                    'student_number' => $studentNumber,
                    'temp_password' => $temporaryPassword,
                    'login_url' => $loginUrl,
                ]);
            }

            return ['ok' => false, 'error' => 'Email API is unreachable.'];
        }

        $decoded = json_decode($responseRaw, true);
        if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['ok'])) {
            return ['ok' => true, 'error' => ''];
        }

        if ($canUseResend) {
            return adviser_students_send_credentials_email_via_resend([
                'student_name' => $studentName,
                'student_email' => $studentEmail,
                'student_number' => $studentNumber,
                'temp_password' => $temporaryPassword,
                'login_url' => $loginUrl,
            ]);
        }

        $message = is_array($decoded) && !empty($decoded['error'])
            ? (string)$decoded['error']
            : 'Email API request failed.';

        return ['ok' => false, 'error' => $message];
    }
}

if (!function_exists('adviser_students_process_add_student')) {
    function adviser_students_process_add_student(PDO $pdo, int $adviserId, array $input): array
    {
        $form = adviser_students_default_add_form();
        $form['student_number'] = trim((string)($input['student_number'] ?? ''));
        $form['first_name'] = trim((string)($input['first_name'] ?? ''));
        $form['last_name'] = trim((string)($input['last_name'] ?? ''));
        $form['program'] = adviser_students_static_program_label();
        $form['department'] = adviser_students_static_department_label();
        // Academic year is controlled by system defaults and is not user-editable.
        $form['academic_year'] = adviser_students_default_academic_year();
        $form['email'] = adviser_students_build_school_email($form['student_number']);
        $form['year_level'] = (string)adviser_students_derive_year_level($form['student_number'], $form['academic_year']);

        $errors = [];

        if ($adviserId <= 0) {
            $errors['form'] = 'Unable to identify adviser account.';
        }

        if ($form['first_name'] === '') {
            $errors['first_name'] = 'First name is required.';
        }

        if ($form['last_name'] === '') {
            $errors['last_name'] = 'Last name is required.';
        }

        if ($form['student_number'] === '') {
            $errors['student_number'] = 'Student ID is required.';
        }

        if ($form['email'] === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!isset($errors['email']) && adviser_students_email_in_use($pdo, $form['email'])) {
            $errors['email'] = 'That email address is already used by another account.';
        }

        $existingStudentByNumberStmt = $pdo->prepare(
            'SELECT
                s.student_id,
                s.first_name,
                s.last_name,
                s.email,
                (
                    SELECT COUNT(*)
                    FROM adviser_assignment aa
                    WHERE aa.student_id = s.student_id
                      AND aa.adviser_id = :adviser_id
                      AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                ) AS assigned_to_current_adviser
             FROM student s
             WHERE TRIM(s.student_number) = :student_number
             LIMIT 1'
        );
        $existingStudentByNumberStmt->execute([
            ':student_number' => $form['student_number'],
            ':adviser_id' => $adviserId,
        ]);
        $existingStudentByNumber = $existingStudentByNumberStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingStudentByNumber)) {
            $isAssignedToCurrentAdviser = (int)($existingStudentByNumber['assigned_to_current_adviser'] ?? 0) > 0;
            if ($isAssignedToCurrentAdviser) {
                $errors['student_number'] = 'Student ID already exists and is already assigned to your advisory list.';
            } else {
                $errors['student_number'] = 'Student ID already exists in the system (possibly under another adviser). If your first submit already succeeded, the account is already created.';
            }
        }

        $existingStudentByEmailStmt = $pdo->prepare(
            'SELECT student_id
             FROM student
             WHERE email = :email
             LIMIT 1'
        );
        $existingStudentByEmailStmt->execute([':email' => $form['email']]);
        if ($existingStudentByEmailStmt->fetchColumn()) {
            $errors['email'] = 'Student email already exists.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'form' => $form];
        }

        try {
            $pdo->beginTransaction();

            $temporaryPassword = adviser_students_generate_temp_password();

            $yearLevelValue = adviser_students_derive_year_level($form['student_number'], $form['academic_year']);

            if (adviser_students_has_academic_year_column($pdo)) {
                $insertStudentStmt = $pdo->prepare(
                    'INSERT INTO student
                        (student_number, first_name, last_name, email, program, department, year_level, academic_year, password_hash, must_change_password, availability_status, preferred_industry, resume_file, internship_readiness_score, profile_picture, created_at, updated_at)
                     VALUES
                        (:student_number, :first_name, :last_name, :email, :program, :department, :year_level, :academic_year, :password_hash, 1, :availability_status, NULL, NULL, 0.00, NULL, NOW(), NOW())'
                );
                $insertStudentStmt->execute([
                    ':student_number' => $form['student_number'],
                    ':first_name' => $form['first_name'],
                    ':last_name' => $form['last_name'],
                    ':email' => $form['email'],
                    ':program' => $form['program'],
                    ':department' => $form['department'],
                    ':year_level' => $yearLevelValue,
                    ':academic_year' => $form['academic_year'],
                    ':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    ':availability_status' => 'Available',
                ]);
            } else {
                $insertStudentStmt = $pdo->prepare(
                    'INSERT INTO student
                        (student_number, first_name, last_name, email, program, department, year_level, password_hash, must_change_password, availability_status, preferred_industry, resume_file, internship_readiness_score, profile_picture, created_at, updated_at)
                     VALUES
                        (:student_number, :first_name, :last_name, :email, :program, :department, :year_level, :password_hash, 1, :availability_status, NULL, NULL, 0.00, NULL, NOW(), NOW())'
                );
                $insertStudentStmt->execute([
                    ':student_number' => $form['student_number'],
                    ':first_name' => $form['first_name'],
                    ':last_name' => $form['last_name'],
                    ':email' => $form['email'],
                    ':program' => $form['program'],
                    ':department' => $form['department'],
                    ':year_level' => $yearLevelValue,
                    ':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    ':availability_status' => 'Available',
                ]);
            }

            $studentId = (int)$pdo->lastInsertId();
            if ($studentId <= 0) {
                throw new RuntimeException('Unable to create student account.');
            }

            if (adviser_students_assignment_has_assigned_date($pdo)) {
                $insertAssignmentStmt = $pdo->prepare(
                    'INSERT INTO adviser_assignment (adviser_id, student_id, assigned_date, status)
                     VALUES (:adviser_id, :student_id, CURDATE(), "Active")'
                );
            } else {
                $insertAssignmentStmt = $pdo->prepare(
                    'INSERT INTO adviser_assignment (adviser_id, student_id, status)
                     VALUES (:adviser_id, :student_id, "Active")'
                );
            }
            $insertAssignmentStmt->execute([
                ':adviser_id' => $adviserId,
                ':student_id' => $studentId,
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'errors' => [],
                'form' => adviser_students_default_add_form(),
                'temp_password' => $temporaryPassword,
                'student_name' => trim($form['first_name'] . ' ' . $form['last_name']),
                'student_number' => $form['student_number'],
                'student_email' => $form['email'],
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'errors' => ['form' => 'Unable to add student right now. Please try again.'],
                'form' => $form,
            ];
        }
    }
}

if (!function_exists('adviser_students_flatten_error_message')) {
    function adviser_students_flatten_error_message(array $errors): string
    {
        foreach ($errors as $key => $value) {
            if ($key === 'form') {
                continue;
            }

            $message = trim((string)$value);
            if ($message !== '') {
                return $message;
            }
        }

        $formMessage = trim((string)($errors['form'] ?? ''));
        return $formMessage;
    }
}

if (!function_exists('adviser_students_excel_column_to_index')) {
    function adviser_students_excel_column_to_index(string $columnLetters): int
    {
        $letters = strtoupper(trim($columnLetters));
        if ($letters === '') {
            return 0;
        }

        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $charCode = ord($letters[$i]);
            if ($charCode < 65 || $charCode > 90) {
                continue;
            }

            $index = ($index * 26) + ($charCode - 64);
        }

        return max(0, $index - 1);
    }
}

if (!function_exists('adviser_students_read_xlsx_rows')) {
    if (!function_exists('adviser_students_xpath_list')) {
        function adviser_students_xpath_list(SimpleXMLElement $node, string $query, array $namespaces = []): array
        {
            foreach ($namespaces as $prefix => $uri) {
                $node->registerXPathNamespace((string)$prefix, (string)$uri);
            }

            $result = $node->xpath($query);
            return is_array($result) ? $result : [];
        }
    }

    function adviser_students_read_xlsx_rows(string $filePath): array
    {
        if (!class_exists('ZipArchive')) {
            return [
                'ok' => false,
                'error' => 'Excel import requires the ZipArchive PHP extension.',
                'rows' => [],
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [
                'ok' => false,
                'error' => 'Unable to open the uploaded Excel workbook.',
                'rows' => [],
            ];
        }

        try {
            $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            if (!is_string($workbookXml) || trim($workbookXml) === '') {
                return [
                    'ok' => false,
                    'error' => 'Invalid Excel workbook: missing workbook metadata.',
                    'rows' => [],
                ];
            }

            $workbook = @simplexml_load_string($workbookXml);
            if (!$workbook) {
                return [
                    'ok' => false,
                    'error' => 'Invalid Excel workbook: unable to parse workbook metadata.',
                    'rows' => [],
                ];
            }

            $sheetPath = 'xl/worksheets/sheet1.xml';

            $firstSheetNodes = adviser_students_xpath_list($workbook, '/main:workbook/main:sheets/main:sheet[1]', [
                'main' => $mainNamespace,
            ]);
            if (!empty($firstSheetNodes)) {
                $sheetRidAttrs = $firstSheetNodes[0]->attributes('r', true);
                $sheetRid = isset($sheetRidAttrs['id']) ? trim((string)$sheetRidAttrs['id']) : '';

                if ($sheetRid !== '') {
                    $workbookRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
                    if (is_string($workbookRelsXml) && trim($workbookRelsXml) !== '') {
                        $rels = @simplexml_load_string($workbookRelsXml);
                        if ($rels) {
                            $relationshipNodes = adviser_students_xpath_list($rels, '/rel:Relationships/rel:Relationship[@Id="' . $sheetRid . '"]', [
                                'rel' => 'http://schemas.openxmlformats.org/package/2006/relationships',
                            ]);
                            if (!empty($relationshipNodes)) {
                                $target = trim((string)$relationshipNodes[0]['Target']);
                                if ($target !== '') {
                                    if (strpos($target, '/xl/') === 0) {
                                        $sheetPath = ltrim($target, '/');
                                    } else {
                                        $sheetPath = 'xl/' . ltrim($target, '/');
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $sharedStrings = [];
            $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($sharedStringsXml) && trim($sharedStringsXml) !== '') {
                $shared = @simplexml_load_string($sharedStringsXml);
                if ($shared) {
                    $siNodes = adviser_students_xpath_list($shared, '/main:sst/main:si', [
                        'main' => $mainNamespace,
                    ]);
                    foreach ($siNodes as $siNode) {
                        $value = '';
                        $textNodes = adviser_students_xpath_list($siNode, './/main:t', [
                            'main' => $mainNamespace,
                        ]);
                        foreach ($textNodes as $textNode) {
                            $value .= (string)$textNode;
                        }
                        $sharedStrings[] = $value;
                    }
                }
            }

            $worksheetXml = $zip->getFromName($sheetPath);
            if (!is_string($worksheetXml) || trim($worksheetXml) === '') {
                return [
                    'ok' => false,
                    'error' => 'Invalid Excel workbook: unable to read the first worksheet.',
                    'rows' => [],
                ];
            }

            $worksheet = @simplexml_load_string($worksheetXml);
            if (!$worksheet) {
                return [
                    'ok' => false,
                    'error' => 'Invalid Excel workbook: unable to parse worksheet data.',
                    'rows' => [],
                ];
            }

            $rowNodes = adviser_students_xpath_list($worksheet, '/main:worksheet/main:sheetData/main:row', [
                'main' => $mainNamespace,
            ]);
            $rows = [];

            foreach ($rowNodes as $rowNode) {
                $cellsByIndex = [];
                $cellNodes = adviser_students_xpath_list($rowNode, './main:c', [
                    'main' => $mainNamespace,
                ]);
                foreach ($cellNodes as $cellNode) {
                    $reference = strtoupper((string)$cellNode['r']);
                    $columnLetters = preg_replace('/\d+/', '', $reference);
                    if ($columnLetters === '') {
                        continue;
                    }

                    $columnIndex = adviser_students_excel_column_to_index($columnLetters);
                    $cellType = strtolower((string)$cellNode['t']);
                    $cellValue = '';

                    if ($cellType === 'inlinestr') {
                        $inlineTextNodes = adviser_students_xpath_list($cellNode, './main:is/main:t', [
                            'main' => $mainNamespace,
                        ]);
                        if (empty($inlineTextNodes)) {
                            $inlineTextNodes = adviser_students_xpath_list($cellNode, './main:is/main:r/main:t', [
                                'main' => $mainNamespace,
                            ]);
                        }

                        foreach ($inlineTextNodes as $inlineTextNode) {
                            $cellValue .= (string)$inlineTextNode;
                        }
                    } else {
                        $valueNodes = adviser_students_xpath_list($cellNode, './main:v', [
                            'main' => $mainNamespace,
                        ]);
                        $rawValue = !empty($valueNodes) ? (string)$valueNodes[0] : '';

                        if ($cellType === 's') {
                            $sharedIndex = (int)$rawValue;
                            $cellValue = (string)($sharedStrings[$sharedIndex] ?? '');
                        } elseif ($cellType === 'b') {
                            $cellValue = ($rawValue === '1') ? 'TRUE' : 'FALSE';
                        } else {
                            $cellValue = $rawValue;
                        }
                    }

                    $cellsByIndex[$columnIndex] = $cellValue;
                }

                if (empty($cellsByIndex)) {
                    continue;
                }

                ksort($cellsByIndex);
                $maxColumn = max(array_keys($cellsByIndex));
                $normalizedRow = array_fill(0, $maxColumn + 1, '');
                foreach ($cellsByIndex as $columnIndex => $value) {
                    $normalizedRow[(int)$columnIndex] = (string)$value;
                }

                $rows[] = $normalizedRow;
            }

            return [
                'ok' => true,
                'error' => '',
                'rows' => $rows,
            ];
        } finally {
            $zip->close();
        }
    }
}

if (!function_exists('adviser_students_process_bulk_add_students')) {
    function adviser_students_process_bulk_add_students(PDO $pdo, int $adviserId, array $uploadFile, string $baseUrl = '/SkillHive'): array
    {
        $result = [
            'success' => false,
            'processed_rows' => 0,
            'created' => 0,
            'failed' => 0,
            'emails_sent' => 0,
            'emails_failed' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        if ($adviserId <= 0) {
            $result['errors'][] = 'Unable to identify adviser account.';
            return $result;
        }

        $uploadError = (int)($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                $result['errors'][] = 'Please upload a CSV or XLSX file first.';
            } else {
                $result['errors'][] = 'Upload failed. Please try again with a valid CSV or XLSX file.';
            }

            return $result;
        }

        $tmpName = (string)($uploadFile['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $result['errors'][] = 'Uploaded file is invalid.';
            return $result;
        }

        $originalName = (string)($uploadFile['name'] ?? 'students.xlsx');
        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            $result['errors'][] = 'Only CSV or XLSX files are supported for bulk import.';
            return $result;
        }

        $headerRow = [];
        $dataRows = [];

        if ($extension === 'csv') {
            $handle = fopen($tmpName, 'rb');
            if ($handle === false) {
                $result['errors'][] = 'Unable to read the uploaded CSV file.';
                return $result;
            }

            $headerRow = fgetcsv($handle);
            if (is_array($headerRow)) {
                while (($row = fgetcsv($handle)) !== false) {
                    if (is_array($row)) {
                        $dataRows[] = $row;
                    }
                }
            }

            fclose($handle);

            if (!is_array($headerRow) || empty($headerRow)) {
                $result['errors'][] = 'CSV file is empty.';
                return $result;
            }
        } else {
            $xlsxRead = adviser_students_read_xlsx_rows($tmpName);
            if (empty($xlsxRead['ok'])) {
                $result['errors'][] = (string)($xlsxRead['error'] ?? 'Unable to read the uploaded Excel workbook.');
                return $result;
            }

            $rows = is_array($xlsxRead['rows'] ?? null) ? $xlsxRead['rows'] : [];
            if (empty($rows)) {
                $result['errors'][] = 'Excel workbook is empty.';
                return $result;
            }

            $headerRow = array_shift($rows);
            $dataRows = $rows;

            if (!is_array($headerRow) || empty($headerRow)) {
                $result['errors'][] = 'Excel workbook is missing a header row.';
                return $result;
            }
        }

            $normalizedHeader = [];
            foreach ($headerRow as $headerCell) {
                $cleaned = strtolower(trim((string)$headerCell));
                $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', $cleaned);
                $normalizedHeader[] = (string)$cleaned;
            }

            $headerMap = [];
            foreach ($normalizedHeader as $index => $columnName) {
                if ($columnName !== '') {
                    $headerMap[$columnName] = $index;
                }
            }

            $requiredColumns = ['student_number', 'first_name', 'last_name'];
            foreach ($requiredColumns as $requiredColumn) {
                if (!array_key_exists($requiredColumn, $headerMap)) {
                    $result['errors'][] = 'File header must include: student_number, first_name, last_name.';
                    return $result;
                }
            }

            $maxRows = 500;
            $rowNumber = 1;
            $seenStudentNumbers = [];
            $createdStudentsForEmail = [];
            $loginUrl = adviser_students_build_login_url($baseUrl);

            foreach ($dataRows as $row) {
                $rowNumber++;

                if (!is_array($row)) {
                    continue;
                }

                $studentNumber = trim((string)($row[$headerMap['student_number']] ?? ''));
                $firstName = trim((string)($row[$headerMap['first_name']] ?? ''));
                $lastName = trim((string)($row[$headerMap['last_name']] ?? ''));

                if ($studentNumber === '' && $firstName === '' && $lastName === '') {
                    continue;
                }

                if ($result['processed_rows'] >= $maxRows) {
                    $result['warnings'][] = 'Import stopped at 500 rows. Split the file and import the remaining students in another upload.';
                    break;
                }

                $result['processed_rows']++;

                if ($studentNumber === '') {
                    $result['failed']++;
                    $result['errors'][] = 'Row ' . $rowNumber . ': Student ID is required.';
                    continue;
                }

                $studentNumberKey = strtolower(preg_replace('/\s+/', '', $studentNumber));
                if ($studentNumberKey === '') {
                    $result['failed']++;
                    $result['errors'][] = 'Row ' . $rowNumber . ': Student ID is required.';
                    continue;
                }

                if (isset($seenStudentNumbers[$studentNumberKey])) {
                    $result['failed']++;
                    $result['errors'][] = 'Row ' . $rowNumber . ' (' . $studentNumber . '): Duplicate Student ID found in the uploaded file.';
                    continue;
                }
                $seenStudentNumbers[$studentNumberKey] = true;

                $createResult = adviser_students_process_add_student($pdo, $adviserId, [
                    'student_number' => $studentNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);

                if (empty($createResult['success'])) {
                    $result['failed']++;
                    $errorMessage = adviser_students_flatten_error_message((array)($createResult['errors'] ?? []));
                    if ($errorMessage === '') {
                        $errorMessage = 'Unable to create student account.';
                    }
                    $result['errors'][] = 'Row ' . $rowNumber . ' (' . $studentNumber . '): ' . $errorMessage;
                    continue;
                }

                $result['created']++;
                $createdStudentsForEmail[] = [
                    'row_number' => $rowNumber,
                    'student_number' => $studentNumber,
                    'student_name' => (string)($createResult['student_name'] ?? ''),
                    'student_email' => (string)($createResult['student_email'] ?? ''),
                    'temp_password' => (string)($createResult['temp_password'] ?? ''),
                ];
            }

            foreach ($createdStudentsForEmail as $emailRow) {
                try {
                    $emailResult = adviser_students_send_credentials_email([
                        'student_name' => (string)($emailRow['student_name'] ?? ''),
                        'student_email' => (string)($emailRow['student_email'] ?? ''),
                        'student_number' => (string)($emailRow['student_number'] ?? ''),
                        'temp_password' => (string)($emailRow['temp_password'] ?? ''),
                        'login_url' => $loginUrl,
                    ]);

                    if (!empty($emailResult['ok'])) {
                        $result['emails_sent']++;
                    } else {
                        $result['emails_failed']++;
                        $result['warnings'][] = 'Row ' . (int)($emailRow['row_number'] ?? 0) . ' (' . (string)($emailRow['student_number'] ?? '') . '): Account created but credentials email failed - ' . (string)($emailResult['error'] ?? 'Unknown email error.');
                    }
                } catch (Throwable $e) {
                    $result['emails_failed']++;
                    $result['warnings'][] = 'Row ' . (int)($emailRow['row_number'] ?? 0) . ' (' . (string)($emailRow['student_number'] ?? '') . '): Account created but credentials email failed due to an unexpected error.';
                }
            }

            if ($result['processed_rows'] === 0) {
                $result['errors'][] = 'No student rows were found in the CSV file.';
            }

            $result['success'] = $result['created'] > 0;

            return $result;
    }
}
