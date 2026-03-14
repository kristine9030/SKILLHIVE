<?php
/**
 * Purpose: Extracts student IDs from candidate rows and loads a capped skill list per student.
 * Tables/columns used: candidates_collect_student_ids uses in-memory candidate rows only; candidates_get_skills_by_student reads student_skill(student_id, skill_id, verified) and skill(skill_id, skill_name).
 */

if (!function_exists('candidates_collect_student_ids')) {
    function candidates_collect_student_ids(array $candidates): array
    {
        $studentIds = [];
        foreach ($candidates as $row) {
            $studentIds[] = (int)($row['student_id'] ?? 0);
        }

        return array_values(array_unique(array_filter($studentIds)));
    }
}

if (!function_exists('candidates_get_skills_by_student')) {
    function candidates_get_skills_by_student(PDO $pdo, array $studentIds, int $maxPerStudent = 3): array
    {
        $skillsByStudent = [];
        if (empty($studentIds)) {
            return $skillsByStudent;
        }

        $safeMax = max(1, min(10, $maxPerStudent));
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $skillsSql = '
            SELECT
                ss.student_id,
                s.skill_name,
                ss.verified
            FROM student_skill ss
            INNER JOIN skill s ON s.skill_id = ss.skill_id
            WHERE ss.student_id IN (' . $placeholders . ')
            ORDER BY ss.verified DESC, s.skill_name ASC
        ';

        $stmt = $pdo->prepare($skillsSql);
        $stmt->execute($studentIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $studentId = (int)$row['student_id'];
            if (!isset($skillsByStudent[$studentId])) {
                $skillsByStudent[$studentId] = [];
            }

            if (count($skillsByStudent[$studentId]) < $safeMax) {
                $skillsByStudent[$studentId][] = [
                    'skill_name' => (string)$row['skill_name'],
                    'verified' => (int)$row['verified'] === 1,
                ];
            }
        }

        return $skillsByStudent;
    }
}
