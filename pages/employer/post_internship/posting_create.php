<?php
/**
 * Purpose: Creates a new internship record and its required skill rows inside a transaction.
 * Tables/columns used: internship(employer_id, title, description, duration_weeks, allowance, work_setup, location, slots_available, status, posted_at), internship_skill(internship_id, skill_id, required_level, is_mandatory).
 */

if (!function_exists('createInternshipPosting')) {
    function createInternshipPosting(PDO $pdo, int $employerId, array $validated): bool
    {
        $old = $validated['old'];
        $rowsToInsert = $validated['rows_to_insert'];

        $pdo->beginTransaction();

        $sqlInternship = '
            INSERT INTO internship
                (employer_id, title, description, duration_weeks, allowance,
                 work_setup, location, slots_available, status, posted_at)
            VALUES
                (:employer_id, :title, :description, :duration_weeks, :allowance,
                 :work_setup, :location, :slots_available, :status, NOW())
        ';

        $stmtI = $pdo->prepare($sqlInternship);
        $stmtI->execute([
            ':employer_id' => $employerId,
            ':title' => $old['title'],
            ':description' => $old['description'],
            ':duration_weeks' => (int)$validated['duration_weeks'],
            ':allowance' => (float)$validated['allowance'],
            ':work_setup' => $old['work_setup'],
            ':location' => $old['location'],
            ':slots_available' => (int)$validated['slots_available'],
            ':status' => $old['status'],
        ]);

        $internshipId = (int)$pdo->lastInsertId();

        $sqlSkill = '
            INSERT INTO internship_skill
                (internship_id, skill_id, required_level, is_mandatory)
            VALUES
                (:internship_id, :skill_id, :required_level, :is_mandatory)
        ';

        $stmtS = $pdo->prepare($sqlSkill);
        foreach ($rowsToInsert as [$skillId, $requiredLevel, $mandatory]) {
            $stmtS->execute([
                ':internship_id' => $internshipId,
                ':skill_id' => (int)$skillId,
                ':required_level' => $requiredLevel,
                ':is_mandatory' => (int)$mandatory,
            ]);
        }

        $pdo->commit();
        return true;
    }
}
