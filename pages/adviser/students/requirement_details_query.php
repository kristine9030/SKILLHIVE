<?php
/**
 * Purpose: Loads per-requirement checklist data for adviser students modal.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), requirement(requirement_id, name, phase, applicable_to, sort_order), student_requirement(req_submission_id, student_id, internship_id, requirement_id, status, submitted_at, reviewed_at, deadline).
 */

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

        $stmt = $pdo->prepare(
            'SELECT
                r.requirement_id,
                r.name,
                r.phase,
                r.sort_order,
                sr.status AS submission_status,
                sr.submitted_at,
                sr.reviewed_at,
                sr.deadline
             FROM requirement r
             LEFT JOIN (
                SELECT sr1.*
                FROM student_requirement sr1
                INNER JOIN (
                    SELECT requirement_id, MAX(req_submission_id) AS max_submission_id
                    FROM student_requirement
                    WHERE student_id = :student_id_latest
                      AND (:internship_id_latest = 0 OR internship_id = :internship_id_match)
                    GROUP BY requirement_id
                ) latest ON latest.max_submission_id = sr1.req_submission_id
             ) sr ON sr.requirement_id = r.requirement_id
             WHERE r.applicable_to IN ("Student", "Both")
             ORDER BY FIELD(r.phase, "Pre-OJT", "During OJT", "Post-OJT"), r.sort_order ASC, r.requirement_id ASC'
        );

        $stmt->execute([
            ':student_id_latest' => $studentId,
            ':internship_id_latest' => $internshipFilterId,
            ':internship_id_match' => $internshipFilterId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $phases = [
            'Pre-OJT' => [],
            'During OJT' => [],
            'Post-OJT' => [],
        ];

        $submittedCount = 0;
        $totalCount = 0;

        foreach ($rows as $row) {
            $phase = trim((string)($row['phase'] ?? ''));
            if (!isset($phases[$phase])) {
                $phase = 'Pre-OJT';
            }

            $status = trim((string)($row['submission_status'] ?? ''));
            if ($status === '') {
                $status = 'Pending';
            }

            $isSubmitted = in_array($status, ['Submitted', 'Approved'], true);
            if ($isSubmitted) {
                $submittedCount++;
            }
            $totalCount++;

            $dateLabel = adviser_students_format_modal_date((string)($row['submitted_at'] ?? ''));
            if ($dateLabel === '') {
                $dateLabel = adviser_students_format_modal_date((string)($row['reviewed_at'] ?? ''));
            }
            if ($dateLabel === '') {
                $dateLabel = adviser_students_format_modal_date((string)($row['deadline'] ?? ''));
            }

            $phases[$phase][] = [
                'requirement_id' => (int)($row['requirement_id'] ?? 0),
                'name' => (string)($row['name'] ?? 'Requirement'),
                'phase' => $phase,
                'status' => $status,
                'is_submitted' => $isSubmitted,
                'date_label' => $dateLabel,
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
            'can_edit' => $resolvedInternshipId > 0,
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
    function adviser_students_toggle_requirement_submission(PDO $pdo, int $adviserId, int $studentId, ?int $internshipId, int $requirementId, bool $isChecked): array
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

        $reqStmt = $pdo->prepare(
            'SELECT requirement_id
             FROM requirement
             WHERE requirement_id = :requirement_id
               AND applicable_to IN ("Student", "Both")
             LIMIT 1'
        );
        $reqStmt->execute([':requirement_id' => $requirementId]);
        if (!$reqStmt->fetchColumn()) {
            throw new RuntimeException('Requirement not found.');
        }

        $resolvedInternshipId = (int)($internshipId ?? 0);
        if ($resolvedInternshipId <= 0) {
            $resolvedInternshipId = (int)(adviser_students_resolve_internship_id($pdo, $studentId) ?? 0);
        }
        if ($resolvedInternshipId <= 0) {
            throw new InvalidArgumentException('No internship context found for this student.');
        }

        $newStatus = $isChecked ? 'Submitted' : 'Pending';

        $pdo->beginTransaction();
        try {
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
                ':requirement_id' => $requirementId,
            ]);
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
                    ':internship_id' => $resolvedInternshipId,
                    ':requirement_id' => $requirementId,
                    ':status' => $newStatus,
                    ':submitted_at' => $isChecked ? date('Y-m-d H:i:s') : null,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return adviser_students_get_requirements_modal_data($pdo, $adviserId, $studentId, $resolvedInternshipId);
    }
}
