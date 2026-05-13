<?php
/**
 * Purpose: Validates employer evaluation submissions, verifies internship ownership, and inserts employer_evaluation rows.
 * Tables/columns used: internship(internship_id, employer_id), ojt_record(student_id, internship_id, completion_status), employer_evaluation(student_id, internship_id, employer_id, technical_score, behavioral_score, comments, recommendation_status, evaluation_date).
 */

if (!function_exists('saveEmployerEvaluation')) {
    function saveEmployerEvaluation(PDO $pdo, int $employerId, array $payload): array
    {
        $candidateKey  = trim((string)($payload['candidate_key'] ?? ''));
        $comment       = trim((string)($payload['comments'] ?? ''));
        $submittedRecommendation = trim((string)($payload['recommendation_status'] ?? ''));

        $technical     = filter_var($payload['technical_score']     ?? null, FILTER_VALIDATE_FLOAT);
        $behavioral    = filter_var($payload['behavioral_score']    ?? null, FILTER_VALIDATE_FLOAT);
        $communication = filter_var($payload['communication_score'] ?? null, FILTER_VALIDATE_FLOAT);
        $ethic         = filter_var($payload['work_ethic_score']    ?? null, FILTER_VALIDATE_FLOAT);

        if (!preg_match('/^[0-9]+:[0-9]+$/', $candidateKey)) {
            return ['success' => false, 'error' => 'Please select an intern.'];
        }

        [$studentIdRaw, $internshipIdRaw] = explode(':', $candidateKey, 2);
        $studentId    = (int)$studentIdRaw;
        $internshipId = (int)$internshipIdRaw;

        if ($studentId <= 0 || $internshipId <= 0) {
            return ['success' => false, 'error' => 'Invalid intern selection.'];
        }

        if ($behavioral === false && $communication !== false && $ethic !== false) {
            $behavioral = round((((float)$communication) + ((float)$ethic)) / 2, 1);
        }

        foreach (['technical' => $technical, 'behavioral' => $behavioral] as $label => $score) {
            if ($score === false || $score < 1 || $score > 5) {
                return ['success' => false, 'error' => 'Please provide a valid ' . $label . ' rating (1 to 5).'];
            }
        }

        $recommendationKey = strtolower($submittedRecommendation);
        if (!in_array($recommendationKey, ['recommended', 'not recommended'], true)) {
            return ['success' => false, 'error' => 'Please choose a recommendation outcome.'];
        }

        $ownershipStmt = $pdo->prepare(
                            'SELECT COUNT(*)
                         FROM internship i
                                 INNER JOIN ojt_record o ON o.internship_id = i.internship_id
                         WHERE i.internship_id = :internship_id
                             AND o.student_id    = :student_id
                                AND (
                                    LOWER(TRIM(COALESCE(o.completion_status, ""))) IN ("completed", "complete", "done")
                                    OR LOWER(TRIM(COALESCE(o.completion_status, ""))) LIKE "complete%"
                                    OR (COALESCE(o.hours_required, 0) > 0 AND COALESCE(o.hours_completed, 0) >= COALESCE(o.hours_required, 0))
                                )
                             AND i.employer_id   = :employer_id'
        );
        $ownershipStmt->execute([
            ':internship_id' => $internshipId,
            ':student_id'    => $studentId,
            ':employer_id'   => $employerId,
        ]);

        if ((int)$ownershipStmt->fetchColumn() === 0) {
            return ['success' => false, 'error' => 'Intern is not yet eligible for evaluation. Complete OJT first.'];
        }

        $behavioral = round((float)$behavioral, 1);
        $recommendationStatus = deriveEmployerEvaluationRecommendationStatus((float)$technical, $behavioral, $submittedRecommendation);
        $storedComment = composeEvaluationCommentPayload($behavioral, $behavioral, $comment);

        $existingStmt = $pdo->prepare(
            'SELECT evaluation_id
             FROM employer_evaluation
             WHERE student_id = :student_id
               AND internship_id = :internship_id
               AND employer_id = :employer_id
             ORDER BY evaluation_id DESC
             LIMIT 1'
        );
        $existingStmt->execute([
            ':student_id' => $studentId,
            ':internship_id' => $internshipId,
            ':employer_id' => $employerId,
        ]);

        $existingEvaluationId = (int)$existingStmt->fetchColumn();

        if ($existingEvaluationId > 0) {
            $updateStmt = $pdo->prepare(
                'UPDATE employer_evaluation
                 SET technical_score = :technical_score,
                     behavioral_score = :behavioral_score,
                     comments = :comments,
                     recommendation_status = :recommendation_status,
                     evaluation_date = NOW()
                 WHERE evaluation_id = :evaluation_id
                   AND employer_id = :employer_id'
            );
            $updateStmt->execute([
                ':technical_score' => (float)$technical,
                ':behavioral_score' => $behavioral,
                ':comments' => $storedComment,
                ':recommendation_status' => $recommendationStatus,
                ':evaluation_id' => $existingEvaluationId,
                ':employer_id' => $employerId,
            ]);
        } else {
            $insertStmt = $pdo->prepare(
                'INSERT INTO employer_evaluation
                    (student_id, internship_id, employer_id, technical_score, behavioral_score, comments, recommendation_status, evaluation_date)
                 VALUES
                    (:student_id, :internship_id, :employer_id, :technical_score, :behavioral_score, :comments, :recommendation_status, NOW())'
            );

            $insertStmt->execute([
                ':student_id'             => $studentId,
                ':internship_id'          => $internshipId,
                ':employer_id'            => $employerId,
                ':technical_score'        => (float)$technical,
                ':behavioral_score'       => $behavioral,
                ':comments'               => $storedComment,
                ':recommendation_status'  => $recommendationStatus,
            ]);
        }

        return ['success' => true, 'error' => null];
    }
}
