<?php
/**
 * Purpose: Loads recent employer evaluations and derives adviser-style recommendation display text.
 * Tables/columns used: employer_evaluation(evaluation_id, student_id, internship_id, employer_id, technical_score, behavioral_score, comments, recommendation_status, evaluation_date), student(student_id, first_name, last_name), internship(internship_id, employer_id).
 */

if (!function_exists('evaluation_get_history')) {
    function evaluation_get_history(PDO $pdo, int $employerId, int $internshipId = 0): array
    {
        $sql =
            'SELECT
                ev.evaluation_id,
                ev.student_id,
                ev.internship_id,
                ev.technical_score,
                ev.behavioral_score,
                ev.comments,
                ev.recommendation_status,
                ev.evaluation_date,
                s.first_name,
                s.last_name,
                i.title AS internship_title
             FROM employer_evaluation ev
             INNER JOIN student s ON s.student_id = ev.student_id
             INNER JOIN internship i ON i.internship_id = ev.internship_id
                         WHERE ev.employer_id = :employer_id_1
                             AND i.employer_id  = :employer_id_2';

                $params = [
                        ':employer_id_1' => $employerId,
                        ':employer_id_2' => $employerId,
                ];
        if ($internshipId > 0) {
            $sql .= ' AND ev.internship_id = :internship_id';
            $params[':internship_id'] = $internshipId;
        }

        $sql .= ' ORDER BY ev.evaluation_date DESC, ev.evaluation_id DESC
             LIMIT 20';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $history = [];
        foreach ($rows as $row) {
            $technical     = (float)($row['technical_score']  ?? 0);
            $behavioral    = (float)($row['behavioral_score'] ?? 0);
            $overall       = round(($technical + $behavioral) / 2, 1);
            $parsed        = parseEvaluationCommentPayload($row['comments'] ?? '');
            $storedStatus  = trim((string)($row['recommendation_status'] ?? ''));
            $recommendationStatus = deriveEmployerEvaluationRecommendationStatus($technical, $behavioral, $storedStatus);
            $concernReasons = buildEmployerEvaluationConcernReasons($technical, $behavioral, $recommendationStatus);
            $summaryText = buildEmployerEvaluationSummaryText($technical, $behavioral, $recommendationStatus);

            $recommendationClass = strtolower(str_replace(' ', '-', $recommendationStatus));

            $history[] = [
                'intern'          => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
                'internship_id'   => (int)($row['internship_id'] ?? 0),
                'internship_title'=> (string)($row['internship_title'] ?? 'Internship'),
                'period'          => $recommendationStatus,
                'recommendation_status' => $recommendationStatus,
                'recommendation_class' => $recommendationClass,
                'summary_text'    => $summaryText,
                'has_concerns'    => !empty($concernReasons),
                'technical'       => round($technical, 1),
                'behavioral'      => round($behavioral, 1),
                'communication'   => round($behavioral, 1),
                'ethic'           => round($behavioral, 1),
                'overall'         => $overall,
                'comment'         => (string)($parsed['clean_comment'] ?? ''),
                'evaluation_date' => (string)($row['evaluation_date'] ?? ''),
            ];
        }

        return $history;
    }
}
