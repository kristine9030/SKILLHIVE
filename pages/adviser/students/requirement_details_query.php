<?php
/**
 * Purpose: Loads per-requirement checklist data for adviser students modal.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), requirement(requirement_id, name, phase, applicable_to, sort_order), student_requirement(req_submission_id, student_id, internship_id, requirement_id, status, submitted_at, reviewed_at, deadline).
 */

require_once __DIR__ . '/formatters.php';

if (!function_exists('adviser_students_format_modal_date')) {
    function adviser_students_format_modal_date(?string $rawDate): string
    {
        $value = trim((string)($rawDate ?? ''));
        if ($value === '') {
            return '';
        }

        try {
            $date = new DateTime($value);
            return $date->format('M j, Y');
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('adviser_students_ensure_requirement_storage_schema')) {
    function adviser_students_ensure_requirement_storage_schema(PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        // Allow checklist writes even when internship context does not exist yet.
        try {
            $nullableStmt = $pdo->prepare(
                'SELECT IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = "student_requirement"
                   AND COLUMN_NAME = "internship_id"
                 LIMIT 1'
            );
            $nullableStmt->execute();
            $isNullable = strtoupper((string)($nullableStmt->fetchColumn() ?: 'NO'));
            if ($isNullable !== 'YES') {
                $pdo->exec('ALTER TABLE student_requirement MODIFY internship_id INT(10) UNSIGNED NULL');
            }
        } catch (Throwable $e) {
            // Non-fatal; downstream logic will continue with best effort.
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS adviser_requirement_draft (
                    draft_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    student_id INT(10) UNSIGNED NOT NULL,
                    requirement_key VARCHAR(191) NOT NULL,
                    is_checked TINYINT(1) NOT NULL DEFAULT 0,
                    updated_by INT(10) UNSIGNED DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (draft_id),
                    UNIQUE KEY uq_ard_student_requirement (student_id, requirement_key),
                    KEY idx_ard_student (student_id),
                    CONSTRAINT fk_ard_student FOREIGN KEY (student_id)
                        REFERENCES student(student_id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // Non-fatal; draft table may already be managed outside this module.
        }

        $ensured = true;
    }
}

if (!function_exists('adviser_students_get_requirement_draft_map')) {
    function adviser_students_get_requirement_draft_map(PDO $pdo, int $studentId): array
    {
        if ($studentId <= 0) {
            return [];
        }

        adviser_students_ensure_requirement_storage_schema($pdo);

        try {
            $stmt = $pdo->prepare(
                'SELECT requirement_key, is_checked
                 FROM adviser_requirement_draft
                 WHERE student_id = :student_id'
            );
            $stmt->execute([':student_id' => $studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $normalizedRequirementKey = adviser_students_requirement_key((string)($row['requirement_key'] ?? ''));
            if ($normalizedRequirementKey === '') {
                continue;
            }

            $rawValue = $row['is_checked'] ?? 0;
            $result[$normalizedRequirementKey] = ((string)$rawValue === '1' || $rawValue === 1 || $rawValue === true);
        }

        return $result;
    }
}

if (!function_exists('adviser_students_set_requirement_draft_status')) {
    function adviser_students_set_requirement_draft_status(PDO $pdo, int $studentId, string $requirementKey, bool $isChecked, ?int $adviserId = null): void
    {
        $normalizedRequirementKey = adviser_students_requirement_key($requirementKey);
        if ($studentId <= 0 || $normalizedRequirementKey === '') {
            return;
        }

        adviser_students_ensure_requirement_storage_schema($pdo);

        $upsertStmt = $pdo->prepare(
            'INSERT INTO adviser_requirement_draft (
                student_id,
                requirement_key,
                is_checked,
                updated_by,
                created_at,
                updated_at
            ) VALUES (
                :student_id_insert,
                :requirement_key_insert,
                :is_checked_insert,
                :updated_by_insert,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                is_checked = :is_checked_update,
                updated_by = :updated_by_update,
                updated_at = NOW()'
        );
        $upsertStmt->execute([
            ':student_id_insert' => $studentId,
            ':requirement_key_insert' => $normalizedRequirementKey,
            ':is_checked_insert' => $isChecked ? 1 : 0,
            ':updated_by_insert' => ($adviserId !== null && $adviserId > 0) ? $adviserId : null,
            ':is_checked_update' => $isChecked ? 1 : 0,
            ':updated_by_update' => ($adviserId !== null && $adviserId > 0) ? $adviserId : null,
        ]);
    }
}

if (!function_exists('adviser_students_clear_requirement_draft_status')) {
    function adviser_students_clear_requirement_draft_status(PDO $pdo, int $studentId, string $requirementKey): void
    {
        $normalizedRequirementKey = adviser_students_requirement_key($requirementKey);
        if ($studentId <= 0 || $normalizedRequirementKey === '') {
            return;
        }

        adviser_students_ensure_requirement_storage_schema($pdo);

        try {
            $stmt = $pdo->prepare(
                'DELETE FROM adviser_requirement_draft
                 WHERE student_id = :student_id
                   AND requirement_key = :requirement_key'
            );
            $stmt->execute([
                ':student_id' => $studentId,
                ':requirement_key' => $normalizedRequirementKey,
            ]);
        } catch (Throwable $e) {
            // Non-fatal cleanup.
        }
    }
}

if (!function_exists('adviser_students_local_requirement_key_set')) {
    function adviser_students_local_requirement_key_set(PDO $pdo): array
    {
        $keys = [];
        foreach (array_keys(adviser_students_requirement_ids_by_key($pdo)) as $key) {
            $normalizedKey = adviser_students_requirement_key($key);
            if ($normalizedKey !== '') {
                $keys[$normalizedKey] = true;
            }
        }

        return $keys;
    }
}

if (!function_exists('adviser_students_requirement_ids_by_key')) {
    function adviser_students_requirement_ids_by_key(PDO $pdo): array
    {
        $stmt = $pdo->prepare(
            'SELECT requirement_id, name
             FROM requirement
             WHERE applicable_to = "Student"'
        );
        $stmt->execute();

        $map = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $key = adviser_students_requirement_key((string)($row['name'] ?? ''));
            if ($key === '') {
                continue;
            }
            if (!isset($map[$key])) {
                $map[$key] = (int)($row['requirement_id'] ?? 0);
            }
        }

        return $map;
    }
}

if (!function_exists('adviser_students_student_requirements')) {
    function adviser_students_student_requirements(PDO $pdo): array
    {
        $stmt = $pdo->prepare(
            'SELECT requirement_id, name, phase
             FROM requirement
             WHERE applicable_to = "Student"
             ORDER BY FIELD(phase, "Pre-OJT", "During OJT", "Post-OJT"), sort_order ASC, requirement_id ASC'
        );
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $phase = trim((string)($row['phase'] ?? 'Pre-OJT'));
            if (!in_array($phase, ['Pre-OJT', 'During OJT', 'Post-OJT'], true)) {
                $phase = 'Pre-OJT';
            }

            $result[] = [
                'requirement_id' => (int)($row['requirement_id'] ?? 0),
                'name' => $name,
                'phase' => $phase,
            ];
        }

        return $result;
    }
}

if (!function_exists('adviser_students_get_requirements_modal_data')) {
    function adviser_students_get_requirements_modal_data(PDO $pdo, int $adviserId, int $studentId, ?int $internshipId = null): array
    {
        $authStmt = $pdo->prepare(
            'SELECT 1
             FROM adviser_assignment aa
             WHERE aa.adviser_id = :adviser_id
               AND aa.student_id = :student_id
               AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             LIMIT 1'
        );
        $authStmt->execute([
            ':adviser_id' => $adviserId,
            ':student_id' => $studentId,
        ]);

        if (!$authStmt->fetchColumn()) {
            return [
                'summary' => ['submitted' => 0, 'pending' => 0, 'completion' => 0, 'total' => 0],
                'phases' => ['Pre-OJT' => [], 'During OJT' => [], 'Post-OJT' => []],
                'can_edit' => false,
                'internship_id_context' => null,
            ];
        }

        $internshipFilterId = (int)($internshipId ?? 0);
        $resolvedInternshipId = $internshipFilterId > 0
            ? $internshipFilterId
            : (int)(adviser_students_resolve_internship_id($pdo, $studentId) ?? 0);
        $effectiveInternshipFilterId = $internshipFilterId > 0
            ? $internshipFilterId
            : $resolvedInternshipId;

        adviser_students_ensure_requirement_storage_schema($pdo);

        $localRequirements = adviser_students_student_requirements($pdo);

        $localRequirementIds = [];
        foreach ($localRequirements as $localRequirement) {
            $localRequirementId = (int)($localRequirement['requirement_id'] ?? 0);
            if ($localRequirementId > 0) {
                $localRequirementIds[] = $localRequirementId;
            }
        }
        $localRequirementIds = array_values(array_unique($localRequirementIds));

        $submissionByRequirementId = [];
        if ($localRequirementIds !== []) {
            $submissionRequirementPlaceholders = [];
            $submissionParams = [
                ':student_id_latest' => $studentId,
            ];

            $submissionInternshipFilterSql = 'AND internship_id IS NULL';
            if ($effectiveInternshipFilterId > 0) {
                $submissionInternshipFilterSql = 'AND internship_id = :internship_id_match';
                $submissionParams[':internship_id_match'] = $effectiveInternshipFilterId;
            }

            foreach ($localRequirementIds as $index => $localRequirementId) {
                $placeholder = ':submission_req_id_' . $index;
                $submissionRequirementPlaceholders[] = $placeholder;
                $submissionParams[$placeholder] = (int)$localRequirementId;
            }

            $submissionStmt = $pdo->prepare(
                'SELECT
                    sr.requirement_id,
                    sr.status AS submission_status,
                    sr.submitted_at,
                    sr.reviewed_at,
                    sr.deadline
                 FROM student_requirement sr
                 INNER JOIN (
                    SELECT requirement_id, MAX(req_submission_id) AS max_submission_id
                    FROM student_requirement
                    WHERE student_id = :student_id_latest
                      AND requirement_id IN (' . implode(', ', $submissionRequirementPlaceholders) . ')
                                            ' . $submissionInternshipFilterSql . '
                    GROUP BY requirement_id
                 ) latest ON latest.max_submission_id = sr.req_submission_id'
            );
            $submissionStmt->execute($submissionParams);

            foreach (($submissionStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $submissionRow) {
                $submissionRequirementId = (int)($submissionRow['requirement_id'] ?? 0);
                if ($submissionRequirementId > 0) {
                    $submissionByRequirementId[$submissionRequirementId] = $submissionRow;
                }
            }
        }

        $draftByRequirementKey = adviser_students_get_requirement_draft_map($pdo, $studentId);

        $phases = [
            'Pre-OJT' => [],
            'During OJT' => [],
            'Post-OJT' => [],
        ];

        $submittedCount = 0;
        $totalCount = 0;

        foreach ($localRequirements as $localRequirement) {
            $name = trim((string)($localRequirement['name'] ?? 'Requirement'));
            if ($name === '') {
                continue;
            }

            $phase = trim((string)($localRequirement['phase'] ?? 'Pre-OJT'));
            if (!isset($phases[$phase])) {
                $phase = 'Pre-OJT';
            }

            $requirementKey = adviser_students_requirement_key($name);
            $requirementId = (int)($localRequirement['requirement_id'] ?? 0);
            $submissionRow = $requirementId > 0
                ? ($submissionByRequirementId[$requirementId] ?? null)
                : null;

            $status = trim((string)($submissionRow['submission_status'] ?? ''));
            if ($status === '' && $requirementKey !== '' && array_key_exists($requirementKey, $draftByRequirementKey)) {
                $status = $draftByRequirementKey[$requirementKey] ? 'Submitted' : 'Pending';
            }
            if ($status === '') {
                $status = 'Pending';
            }

            $isSubmitted = in_array($status, ['Submitted', 'Approved'], true);
            if ($isSubmitted) {
                $submittedCount++;
            }
            $totalCount++;

            $dateLabel = adviser_students_format_modal_date((string)($submissionRow['submitted_at'] ?? ''));
            if ($dateLabel === '') {
                $dateLabel = adviser_students_format_modal_date((string)($submissionRow['reviewed_at'] ?? ''));
            }
            if ($dateLabel === '') {
                $dateLabel = adviser_students_format_modal_date((string)($submissionRow['deadline'] ?? ''));
            }

            $phases[$phase][] = [
                'requirement_id' => $requirementId,
                'requirement_key' => $requirementKey,
                'name' => $name,
                'phase' => $phase,
                'status' => $status,
                'is_submitted' => $isSubmitted,
                'date_label' => $dateLabel,
                'can_toggle' => $requirementKey !== '',
            ];
        }

        $pendingCount = max(0, $totalCount - $submittedCount);
        $completion = $totalCount > 0 ? (int)round(($submittedCount / $totalCount) * 100) : 0;

        return [
            'summary' => [
                'submitted' => $submittedCount,
                'pending' => $pendingCount,
                'completion' => $completion,
                'total' => $totalCount,
            ],
            'phases' => $phases,
            'can_edit' => true,
            'internship_id_context' => $resolvedInternshipId > 0 ? $resolvedInternshipId : null,
        ];
    }
}

if (!function_exists('adviser_students_resolve_internship_id')) {
    function adviser_students_resolve_internship_id(PDO $pdo, int $studentId): ?int
    {
        $stmt = $pdo->prepare(
            'SELECT internship_id
             FROM ojt_record
             WHERE student_id = :student_id
               AND internship_id IS NOT NULL
             ORDER BY record_id DESC
             LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId]);
        $internshipId = (int)($stmt->fetchColumn() ?: 0);
        if ($internshipId > 0) {
            return $internshipId;
        }

        $stmt = $pdo->prepare(
            'SELECT internship_id
             FROM application
             WHERE student_id = :student_id
             ORDER BY application_id DESC
             LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId]);
        $fallbackInternshipId = (int)($stmt->fetchColumn() ?: 0);

        return $fallbackInternshipId > 0 ? $fallbackInternshipId : null;
    }
}

if (!function_exists('adviser_students_toggle_requirement_submission')) {
    function adviser_students_toggle_requirement_submission(PDO $pdo, int $adviserId, int $studentId, ?int $internshipId, int $requirementId, string $requirementKey, bool $isChecked): array
    {
        $authStmt = $pdo->prepare(
            'SELECT 1
             FROM adviser_assignment aa
             WHERE aa.adviser_id = :adviser_id
               AND aa.student_id = :student_id
               AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             LIMIT 1'
        );
        $authStmt->execute([
            ':adviser_id' => $adviserId,
            ':student_id' => $studentId,
        ]);

        if (!$authStmt->fetchColumn()) {
            throw new RuntimeException('Unauthorized assignment access.');
        }

        adviser_students_ensure_requirement_storage_schema($pdo);

        $normalizedRequirementKey = adviser_students_requirement_key($requirementKey);
        $localRequirementKeySet = adviser_students_local_requirement_key_set($pdo);
        if ($normalizedRequirementKey !== '' && !isset($localRequirementKeySet[$normalizedRequirementKey])) {
            throw new RuntimeException('Requirement not found.');
        }

        $effectiveRequirementId = $requirementId > 0 ? $requirementId : 0;
        if ($effectiveRequirementId > 0) {
            $reqStmt = $pdo->prepare(
                'SELECT requirement_id, name
                 FROM requirement
                 WHERE requirement_id = :requirement_id
                                     AND applicable_to = "Student"
                 LIMIT 1'
            );
            $reqStmt->execute([':requirement_id' => $effectiveRequirementId]);
            $requirementRow = $reqStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($requirementRow === null) {
                throw new RuntimeException('Requirement not found.');
            }

            $resolvedKeyFromDb = adviser_students_requirement_key((string)($requirementRow['name'] ?? ''));
            if ($resolvedKeyFromDb === '' || !isset($localRequirementKeySet[$resolvedKeyFromDb])) {
                throw new RuntimeException('Requirement not found.');
            }

            $normalizedRequirementKey = $resolvedKeyFromDb;
            $effectiveRequirementId = (int)($requirementRow['requirement_id'] ?? 0);
        } elseif ($normalizedRequirementKey !== '') {
            $requirementIdByKey = adviser_students_requirement_ids_by_key($pdo);
            $effectiveRequirementId = (int)($requirementIdByKey[$normalizedRequirementKey] ?? 0);
        }

        if ($normalizedRequirementKey === '') {
            throw new RuntimeException('Requirement not found.');
        }

        if ($effectiveRequirementId <= 0) {
            adviser_students_set_requirement_draft_status($pdo, $studentId, $normalizedRequirementKey, $isChecked, $adviserId);
            return adviser_students_get_requirements_modal_data($pdo, $adviserId, $studentId, $internshipId);
        }

        $resolvedInternshipId = (int)($internshipId ?? 0);
        if ($resolvedInternshipId <= 0) {
            $resolvedInternshipId = (int)(adviser_students_resolve_internship_id($pdo, $studentId) ?? 0);
        }

        $newStatus = $isChecked ? 'Submitted' : 'Pending';

        $pdo->beginTransaction();
        try {
            if ($resolvedInternshipId > 0) {
                $latestStmt = $pdo->prepare(
                    'SELECT req_submission_id
                     FROM student_requirement
                     WHERE student_id = :student_id
                       AND internship_id = :internship_id
                       AND requirement_id = :requirement_id
                     ORDER BY req_submission_id DESC
                     LIMIT 1'
                );
                $latestStmt->execute([
                    ':student_id' => $studentId,
                    ':internship_id' => $resolvedInternshipId,
                    ':requirement_id' => $effectiveRequirementId,
                ]);
            } else {
                $latestStmt = $pdo->prepare(
                    'SELECT req_submission_id
                     FROM student_requirement
                     WHERE student_id = :student_id
                       AND requirement_id = :requirement_id
                                             AND internship_id IS NULL
                     ORDER BY req_submission_id DESC
                     LIMIT 1'
                );
                $latestStmt->execute([
                    ':student_id' => $studentId,
                    ':requirement_id' => $effectiveRequirementId,
                ]);
            }
            $existingSubmissionId = (int)($latestStmt->fetchColumn() ?: 0);

            if ($existingSubmissionId > 0) {
                $updateStmt = $pdo->prepare(
                    'UPDATE student_requirement
                     SET status = :status,
                         submitted_at = CASE WHEN :status_submitted = 1 THEN COALESCE(submitted_at, NOW()) ELSE NULL END,
                         reviewed_at = NULL,
                         reviewed_by = NULL,
                         updated_at = NOW()
                     WHERE req_submission_id = :req_submission_id'
                );
                $updateStmt->execute([
                    ':status' => $newStatus,
                    ':status_submitted' => $isChecked ? 1 : 0,
                    ':req_submission_id' => $existingSubmissionId,
                ]);
            } else {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO student_requirement (
                        student_id,
                        internship_id,
                        requirement_id,
                        status,
                        file_path,
                        submitted_at,
                        reviewed_at,
                        reviewed_by,
                        notes,
                        deadline,
                        created_at,
                        updated_at
                    ) VALUES (
                        :student_id,
                        :internship_id,
                        :requirement_id,
                        :status,
                        NULL,
                        :submitted_at,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NOW(),
                        NOW()
                    )'
                );
                $insertStmt->execute([
                    ':student_id' => $studentId,
                    ':internship_id' => $resolvedInternshipId > 0 ? $resolvedInternshipId : null,
                    ':requirement_id' => $effectiveRequirementId,
                    ':status' => $newStatus,
                    ':submitted_at' => $isChecked ? date('Y-m-d H:i:s') : null,
                ]);
            }

            $pdo->commit();
            adviser_students_clear_requirement_draft_status($pdo, $studentId, $normalizedRequirementKey);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($resolvedInternshipId > 0) {
                throw $e;
            }

            adviser_students_set_requirement_draft_status($pdo, $studentId, $normalizedRequirementKey, $isChecked, $adviserId);
            return adviser_students_get_requirements_modal_data($pdo, $adviserId, $studentId, null);
        }

        return adviser_students_get_requirements_modal_data(
            $pdo,
            $adviserId,
            $studentId,
            $resolvedInternshipId > 0 ? $resolvedInternshipId : null
        );
    }
}
