<?php
function profile_apply_readiness(PDO $pdo, array $student, array $studentSkills, int $userId): array
{
    $hasBasicInfo = !empty($student['first_name']) && !empty($student['last_name']) && !empty($student['program']) && !empty($student['department']) && !empty($student['year_level']);
    $hasSkills = count($studentSkills) > 0;
    $hasResume = !empty($student['resume_file']);
    $hasPortfolio = !empty($student['profile_picture']);

    $readinessScore = 0.0;

    $basicFields = [
        !empty($student['first_name']),
        !empty($student['last_name']),
        !empty($student['program']),
        !empty($student['department']),
        !empty($student['year_level']),
    ];
    $filledBasic = 0;
    foreach ($basicFields as $ok) {
        if ($ok) $filledBasic++;
    }
    $readinessScore += $filledBasic * 6;

    if (!empty($student['preferred_industry'])) {
        $readinessScore += 10;
    }

    if (($student['availability_status'] ?? '') !== 'Unavailable') {
        $readinessScore += 10;
    }

    if ($hasResume) {
        $readinessScore += 20;
    }

    if ($hasPortfolio) {
        $readinessScore += 10;
    }

    if ($hasSkills) {
        $skillMap = [
            'Beginner' => 40,
            'Intermediate' => 70,
            'Advanced' => 90,
        ];

        $totalSkillValue = 0;
        foreach ($studentSkills as $row) {
            $value = $skillMap[$row['skill_level']] ?? 40;
            if ((int) ($row['verified'] ?? 0) === 1) {
                $value = min(100, $value + 10);
            }
            $totalSkillValue += $value;
        }

        $avgSkill = $totalSkillValue / max(1, count($studentSkills));
        $readinessScore += ($avgSkill * 0.20);
    }

    $readinessScore = round(max(0, min(100, $readinessScore)), 2);

    $storedScore = isset($student['internship_readiness_score']) ? (float) $student['internship_readiness_score'] : 0.0;
    if (abs($storedScore - $readinessScore) >= 0.01) {
        $stmt = $pdo->prepare('UPDATE student SET internship_readiness_score = ?, updated_at = NOW() WHERE student_id = ?');
        $stmt->execute([$readinessScore, $userId]);
    }

    $student['internship_readiness_score'] = $readinessScore;

    $completedChecks = 0;
    foreach ([$hasBasicInfo, $hasSkills, $hasResume, $hasPortfolio] as $ok) {
        if ($ok) $completedChecks++;
    }

    $completeness = (int) round(($completedChecks / 4) * 100);
    $dashArray = 226;
    $dashOffset = (int) round($dashArray * (1 - ($completeness / 100)));

    return [$student, $hasBasicInfo, $hasSkills, $hasResume, $hasPortfolio, $completeness, $dashArray, $dashOffset];
}
