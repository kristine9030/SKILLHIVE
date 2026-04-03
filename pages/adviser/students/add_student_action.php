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

if (!function_exists('adviser_students_send_credentials_email')) {
    function adviser_students_send_credentials_email(array $args): array
    {
        $studentEmail = trim((string)($args['student_email'] ?? ''));
        $studentName = trim((string)($args['student_name'] ?? 'Student'));
        $studentNumber = trim((string)($args['student_number'] ?? ''));
        $temporaryPassword = (string)($args['temp_password'] ?? '');
        $loginUrl = trim((string)($args['login_url'] ?? adviser_students_build_login_url('/SkillHive')));
        $apiUrl = trim((string)(getenv('SKILLHIVE_EMAIL_API_URL') ?: ''));

        if ($apiUrl === '') {
            $apiUrl = 'http://127.0.0.1:3100/send-email';
        }

        if ($studentEmail === '' || !filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Missing or invalid student email.'];
        }

        if ($temporaryPassword === '') {
            return ['ok' => false, 'error' => 'Temporary password was not generated.'];
        }

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
                return ['ok' => false, 'error' => 'Email API is unreachable: ' . $curlError];
            }

            $decoded = json_decode($responseRaw, true);
            if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['ok'])) {
                return ['ok' => true, 'error' => ''];
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
            return ['ok' => false, 'error' => 'Email API is unreachable.'];
        }

        $decoded = json_decode($responseRaw, true);
        if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['ok'])) {
            return ['ok' => true, 'error' => ''];
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
