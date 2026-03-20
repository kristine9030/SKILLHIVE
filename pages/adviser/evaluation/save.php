<?php
/**
 * Purpose: Handles adviser evaluation upsert for eligible completed OJT records.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), ojt_record(student_id, internship_id, completion_status), adviser_evaluation(adviser_eval_id, adviser_id, student_id, internship_id, final_grade, comments, evaluation_date).
 */

if (!function_exists('adviser_evaluation_save_grade')) {
    function adviser_evaluation_save_grade(PDO $pdo, int $adviserId, int $studentId, int $internshipId, string $finalGrade, string $comments): array
    {
        if ($studentId <= 0 || $internshipId <= 0) {
            return ['success' => false, 'error' => 'Invalid student/internship selection.'];
        }

        $allowedGrades = adviser_evaluation_grade_options();
        if (!in_array($finalGrade, $allowedGrades, true)) {
            return ['success' => false, 'error' => 'Invalid final grade.'];
        }

        $comments = trim($comments);
        if ($comments === '') {
            return ['success' => false, 'error' => 'Performance summary is required.'];
        }

        $eligibilityStmt = $pdo->prepare(
            'SELECT 1
             FROM adviser_assignment aa
             INNER JOIN ojt_record o ON o.student_id = aa.student_id
             WHERE aa.adviser_id = :adviser_id
               AND aa.student_id = :student_id
               AND o.internship_id = :internship_id
               AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
               AND LOWER(COALESCE(o.completion_status, "")) = "completed"
             LIMIT 1'
        );
        $eligibilityStmt->execute([
            ':adviser_id' => $adviserId,
            ':student_id' => $studentId,
            ':internship_id' => $internshipId,
        ]);

        if (!$eligibilityStmt->fetchColumn()) {
            return ['success' => false, 'error' => 'Student is not yet eligible for adviser evaluation.'];
        }

        $existingStmt = $pdo->prepare(
            'SELECT adviser_eval_id
             FROM adviser_evaluation
             WHERE adviser_id = :adviser_id
               AND student_id = :student_id
               AND internship_id = :internship_id
             ORDER BY adviser_eval_id DESC
             LIMIT 1'
        );
        $existingStmt->execute([
            ':adviser_id' => $adviserId,
            ':student_id' => $studentId,
            ':internship_id' => $internshipId,
        ]);
        $existingId = (int)($existingStmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $updateStmt = $pdo->prepare(
                'UPDATE adviser_evaluation
                 SET final_grade = :final_grade,
                     comments = :comments,
                     evaluation_date = CURDATE()
                 WHERE adviser_eval_id = :adviser_eval_id'
            );
            $updateStmt->execute([
                ':final_grade' => $finalGrade,
                ':comments' => $comments,
                ':adviser_eval_id' => $existingId,
            ]);
        } else {
            $insertStmt = $pdo->prepare(
                'INSERT INTO adviser_evaluation (adviser_id, student_id, internship_id, final_grade, comments, evaluation_date)
                 VALUES (:adviser_id, :student_id, :internship_id, :final_grade, :comments, CURDATE())'
            );
            $insertStmt->execute([
                ':adviser_id' => $adviserId,
                ':student_id' => $studentId,
                ':internship_id' => $internshipId,
                ':final_grade' => $finalGrade,
                ':comments' => $comments,
            ]);
        }

        return ['success' => true, 'error' => ''];
    }
}
