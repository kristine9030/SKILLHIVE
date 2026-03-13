<?php
function profile_load_data(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM student WHERE student_id = ?');
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        'SELECT ss.skill_id, s.skill_name, ss.skill_level, ss.verified
         FROM student_skill ss
         INNER JOIN skill s ON s.skill_id = ss.skill_id
         WHERE ss.student_id = ?
         ORDER BY s.skill_name ASC'
    );
    $stmt->execute([$userId]);
    $studentSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query('SELECT skill_id, skill_name FROM skill ORDER BY skill_name ASC');
    $allSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $selectedSkillIds = array_map(static function ($row) {
        return (int) $row['skill_id'];
    }, $studentSkills);

    $availableSkills = array_values(array_filter($allSkills, static function ($row) use ($selectedSkillIds) {
        return !in_array((int) $row['skill_id'], $selectedSkillIds, true);
    }));

    return [$student, $studentSkills, $allSkills, $availableSkills];
}
