<?php
function profile_handle_skills(PDO $pdo, int $userId, array &$profileErrors, string &$profileSuccess): void
{
    $action = $_POST['action'] ?? '';

    if ($action === 'add_skill') {
        $skillId = (int) ($_POST['skill_id'] ?? 0);
        $skillLevel = trim($_POST['skill_level'] ?? 'Beginner');
        $validLevels = ['Beginner', 'Intermediate', 'Advanced'];

        if ($skillId <= 0) {
            $profileErrors[] = 'Please choose a skill.';
        }
        if (!in_array($skillLevel, $validLevels, true)) {
            $profileErrors[] = 'Invalid skill level.';
        }

        if ($profileErrors) {
            return;
        }

        $stmt = $pdo->prepare('SELECT skill_id FROM skill WHERE skill_id = ? LIMIT 1');
        $stmt->execute([$skillId]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $profileErrors[] = 'Selected skill does not exist.';
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO student_skill (student_id, skill_id, skill_level, verified)
             VALUES (?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE skill_level = VALUES(skill_level)'
        );
        $stmt->execute([$userId, $skillId, $skillLevel]);
        $profileSuccess = 'Skill saved successfully.';
        return;
    }

    if ($action === 'remove_skill') {
        $skillId = (int) ($_POST['skill_id'] ?? 0);
        if ($skillId > 0) {
            $stmt = $pdo->prepare('DELETE FROM student_skill WHERE student_id = ? AND skill_id = ?');
            $stmt->execute([$userId, $skillId]);
            $profileSuccess = 'Skill removed.';
        }
    }
}
