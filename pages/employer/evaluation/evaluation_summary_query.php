<?php
/**
 * Purpose: Computes evaluation summary counters including total evaluations, average rating, and pending interns.
 * Tables/columns used: employer_evaluation(employer_id, student_id, technical_score, behavioral_score, comments), ojt_record(student_id, internship_id, completion_status), internship(internship_id, employer_id).
 */

if (!function_exists('evaluation_get_summary')) {
    function evaluation_get_summary(PDO $pdo, int $employerId, int $internshipId = 0): array
    {
        $evaluationSql =
            'SELECT
                ev.student_id,
                ev.internship_id,
                ev.technical_score,
                ev.behavioral_score,
                ev.comments
             FROM employer_evaluation ev
             INNER JOIN internship i ON i.internship_id = ev.internship_id
             WHERE ev.employer_id = :employer_id
               AND i.employer_id = :employer_id';

        $evaluationParams = [':employer_id' => $employerId];
        if ($internshipId > 0) {
            $evaluationSql .= ' AND ev.internship_id = :internship_id';
            $evaluationParams[':internship_id'] = $internshipId;
        }

        $evaluationStmt = $pdo->prepare($evaluationSql);
        $evaluationStmt->execute($evaluationParams);
        $evaluationRows = $evaluationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $overallTotal = 0.0;
        foreach ($evaluationRows as $row) {
            $parsed = parseEvaluationCommentPayload($row['comments'] ?? '');
            $technical = (float)($row['technical_score'] ?? 0);
            $behavioral = (float)($row['behavioral_score'] ?? 0);
            $communication = $parsed['communication'] !== null ? (float)$parsed['communication'] : $behavioral;
            $ethic = $parsed['ethic'] !== null ? (float)$parsed['ethic'] : $behavioral;
            $overallTotal += (($technical + $communication + $ethic) / 3);
        }

        $totalEvaluations = count($evaluationRows);
        $averageRating = $totalEvaluations > 0 ? round($overallTotal / $totalEvaluations, 1) : 0.0;

        $eligibleSql =
            'SELECT COUNT(DISTINCT CONCAT(o.student_id, ":", o.internship_id))
             FROM ojt_record o
             INNER JOIN internship i ON i.internship_id = o.internship_id
                         WHERE i.employer_id = :employer_id
                             AND LOWER(COALESCE(o.completion_status, "")) = "completed"';
        $eligibleParams = [':employer_id' => $employerId];
        if ($internshipId > 0) {
            $eligibleSql .= ' AND o.internship_id = :internship_id';
            $eligibleParams[':internship_id'] = $internshipId;
        }

        $eligibleStmt = $pdo->prepare($eligibleSql);
        $eligibleStmt->execute($eligibleParams);
        $eligibleInterns = (int)$eligibleStmt->fetchColumn();

        $evaluatedSql =
            'SELECT COUNT(DISTINCT CONCAT(ev.student_id, ":", ev.internship_id))
             FROM employer_evaluation ev
             INNER JOIN internship i ON i.internship_id = ev.internship_id
             WHERE ev.employer_id = :employer_id
               AND i.employer_id = :employer_id';
        $evaluatedParams = [':employer_id' => $employerId];
        if ($internshipId > 0) {
            $evaluatedSql .= ' AND ev.internship_id = :internship_id';
            $evaluatedParams[':internship_id'] = $internshipId;
        }

        $evaluatedStmt = $pdo->prepare($evaluatedSql);
        $evaluatedStmt->execute($evaluatedParams);
        $evaluatedInterns = (int)$evaluatedStmt->fetchColumn();

        return [
            'total_evaluations' => $totalEvaluations,
            'average_rating'    => $averageRating,
            'pending'           => max(0, $eligibleInterns - $evaluatedInterns),
        ];
    }
}
