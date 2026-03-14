<?php
/**
 * Purpose: Loads the master skill list for the internship posting form.
 * Tables/columns used: skill(skill_id, skill_name).
 */

if (!function_exists('getSkillMasterList')) {
    function getSkillMasterList(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT skill_id, skill_name FROM skill ORDER BY skill_name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
