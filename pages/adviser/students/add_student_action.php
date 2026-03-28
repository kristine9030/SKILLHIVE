<?php
/**
 * Purpose: Handles adviser add-student workflow and advisory assignment writes.
 * Tables/columns used: student(student_id, student_number, first_name, last_name, email, program, department, year_level, password_hash, availability_status, preferred_industry, resume_file, internship_readiness_score, profile_picture, created_at, updated_at), adviser_assignment(adviser_id, student_id, status), employer(email), internship_adviser(email), admin(email).
 */

if (!function_exists('adviser_students_program_options')) {
    function adviser_students_program_options(): array
    {
        return [
            ['value' => 'BSCS', 'label' => 'BS Computer Science'],
            ['value' => 'BSIT', 'label' => 'BS Information Technology'],
            ['value' => 'BSSE', 'label' => 'BS Software Engineering'],
            ['value' => 'BSDS', 'label' => 'BS Data Science'],
        ];
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
            'student_name' => '',
            'student_number' => '',
            'department' => 'BSCS',
            'year_level' => '3',
            'email' => '',
        ];
    }
}

if (!function_exists('adviser_students_split_name')) {
    function adviser_students_split_name(string $fullName): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($fullName));
        if ($normalized === '') {
            return ['', ''];
        }

        $parts = explode(' ', $normalized);
        $firstName = array_shift($parts) ?: '';
        $lastName = trim(implode(' ', $parts));

        if ($lastName === '') {
            $lastName = 'Student';
        }

        return [$firstName, $lastName];
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

if (!function_exists('adviser_students_process_add_student')) {
    function adviser_students_process_add_student(PDO $pdo, int $adviserId, array $input): array
    {
        $form = adviser_students_default_add_form();
        $form['student_name'] = trim((string)($input['student_name'] ?? ''));
        $form['student_number'] = trim((string)($input['student_number'] ?? ''));
        $form['department'] = trim((string)($input['department'] ?? $form['department']));
        $form['year_level'] = trim((string)($input['year_level'] ?? $form['year_level']));
        $form['email'] = trim((string)($input['email'] ?? ''));

        $errors = [];

        $validPrograms = array_column(adviser_students_program_options(), 'value');
        $validYearLevels = array_column(adviser_students_year_level_options(), 'value');

        if ($form['student_name'] === '') {
            $errors['student_name'] = 'Student name is required.';
        }

        if ($form['student_number'] === '') {
            $errors['student_number'] = 'Student ID is required.';
        }

        if (!in_array($form['department'], $validPrograms, true)) {
            $errors['department'] = 'Please choose a valid department.';
        }

        if (!in_array($form['year_level'], $validYearLevels, true)) {
            $errors['year_level'] = 'Please choose a valid year level.';
        }

        if ($form['email'] === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'form' => $form];
        }

        [$firstName, $lastName] = adviser_students_split_name($form['student_name']);

        try {
            $pdo->beginTransaction();

            $byStudentNumberStmt = $pdo->prepare(
                'SELECT student_id, email
                 FROM student
                 WHERE student_number = :student_number
                 LIMIT 1'
            );
            $byStudentNumberStmt->execute([':student_number' => $form['student_number']]);
            $studentByNumber = $byStudentNumberStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $byEmailStmt = $pdo->prepare(
                'SELECT student_id, student_number
                 FROM student
                 WHERE email = :email
                 LIMIT 1'
            );
            $byEmailStmt->execute([':email' => $form['email']]);
            $studentByEmail = $byEmailStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($studentByNumber && $studentByEmail && (int)$studentByNumber['student_id'] !== (int)$studentByEmail['student_id']) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'errors' => ['student_number' => 'Student ID and email belong to different student records.'],
                    'form' => $form,
                ];
            }

            $studentId = 0;
            if ($studentByNumber) {
                $studentId = (int)$studentByNumber['student_id'];
            } elseif ($studentByEmail) {
                $studentId = (int)$studentByEmail['student_id'];
            }

            if ($studentId > 0) {
                if (adviser_students_email_in_use($pdo, $form['email'], $studentId)) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'errors' => ['email' => 'That email address is already used by another account.'],
                        'form' => $form,
                    ];
                }

                $updateStmt = $pdo->prepare(
                    'UPDATE student
                     SET student_number = :student_number,
                         first_name = :first_name,
                         last_name = :last_name,
                         email = :email,
                         program = :program,
                         department = :department,
                         year_level = :year_level,
                         updated_at = NOW()
                     WHERE student_id = :student_id'
                );
                $updateStmt->execute([
                    ':student_number' => $form['student_number'],
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':email' => $form['email'],
                    ':program' => $form['department'],
                    ':department' => $form['department'],
                    ':year_level' => (int)$form['year_level'],
                    ':student_id' => $studentId,
                ]);
            } else {
                if (adviser_students_email_in_use($pdo, $form['email'])) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'errors' => ['email' => 'That email address is already used by another account.'],
                        'form' => $form,
                    ];
                }

                $insertStudentStmt = $pdo->prepare(
                    'INSERT INTO student
                        (student_number, first_name, last_name, email, program, department, year_level, password_hash, availability_status, preferred_industry, resume_file, internship_readiness_score, profile_picture, created_at, updated_at)
                     VALUES
                        (:student_number, :first_name, :last_name, :email, :program, :department, :year_level, :password_hash, :availability_status, NULL, NULL, 0.00, NULL, NOW(), NOW())'
                );
                $insertStudentStmt->execute([
                    ':student_number' => $form['student_number'],
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':email' => $form['email'],
                    ':program' => $form['department'],
                    ':department' => $form['department'],
                    ':year_level' => (int)$form['year_level'],
                    ':password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    ':availability_status' => 'Available',
                ]);

                $studentId = (int)$pdo->lastInsertId();
            }

            $otherAdviserStmt = $pdo->prepare(
                'SELECT adviser_id
                 FROM adviser_assignment
                 WHERE student_id = :student_id
                   AND adviser_id <> :adviser_id
                   AND COALESCE(NULLIF(TRIM(status), ""), "Active") = "Active"
                 LIMIT 1'
            );
            $otherAdviserStmt->execute([
                ':student_id' => $studentId,
                ':adviser_id' => $adviserId,
            ]);

            if ($otherAdviserStmt->fetchColumn()) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'errors' => ['student_number' => 'This student is already assigned to another adviser.'],
                    'form' => $form,
                ];
            }

            $assignmentStmt = $pdo->prepare(
                'SELECT status
                 FROM adviser_assignment
                 WHERE adviser_id = :adviser_id
                   AND student_id = :student_id
                 LIMIT 1'
            );
            $assignmentStmt->execute([
                ':adviser_id' => $adviserId,
                ':student_id' => $studentId,
            ]);
            $existingAssignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($existingAssignment) {
                $currentStatus = trim((string)($existingAssignment['status'] ?? ''));
                if ($currentStatus === '' || strcasecmp($currentStatus, 'Active') === 0) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'errors' => ['student_number' => 'This student is already in your advisory list.'],
                        'form' => $form,
                    ];
                }

                $reactivateStmt = $pdo->prepare(
                    'UPDATE adviser_assignment
                     SET status = "Active"
                     WHERE adviser_id = :adviser_id
                       AND student_id = :student_id'
                );
                $reactivateStmt->execute([
                    ':adviser_id' => $adviserId,
                    ':student_id' => $studentId,
                ]);
            } else {
                $insertAssignmentStmt = $pdo->prepare(
                    'INSERT INTO adviser_assignment (adviser_id, student_id, status)
                     VALUES (:adviser_id, :student_id, "Active")'
                );
                $insertAssignmentStmt->execute([
                    ':adviser_id' => $adviserId,
                    ':student_id' => $studentId,
                ]);
            }

            $pdo->commit();

            return ['success' => true, 'errors' => [], 'form' => adviser_students_default_add_form()];
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
