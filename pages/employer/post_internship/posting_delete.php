<?php
/**
 * Purpose: Deletes an employer posting (and its skill requirements) when safe to remove.
 * Tables/columns used: internship(internship_id, employer_id), internship_skill(internship_id), application(application_id, internship_id).
 */

if (!function_exists('deleteEmployerInternshipPosting')) {
    function deleteEmployerInternshipPosting(PDO $pdo, int $employerId, int $internshipId): array
    {
        if ($internshipId <= 0) {
            return ['success' => false, 'error' => 'Invalid posting selected.'];
        }

        $ownershipStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM internship i
             WHERE i.internship_id = :internship_id
               AND i.employer_id = :employer_id'
        );
        $ownershipStmt->execute([
            ':internship_id' => $internshipId,
            ':employer_id' => $employerId,
        ]);

        if ((int)$ownershipStmt->fetchColumn() === 0) {
            return ['success' => false, 'error' => 'Posting not found or not owned by your account.'];
        }

        $applicationsStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM application a
             WHERE a.internship_id = :internship_id'
        );
        $applicationsStmt->execute([':internship_id' => $internshipId]);
        $applicationsCount = (int)$applicationsStmt->fetchColumn();

        if ($applicationsCount > 0) {
            return ['success' => false, 'error' => 'Cannot delete posting with existing applicants.'];
        }

        $pdo->beginTransaction();

        $deleteSkillsStmt = $pdo->prepare('DELETE FROM internship_skill WHERE internship_id = :internship_id');
        $deleteSkillsStmt->execute([':internship_id' => $internshipId]);

        $deletePostingStmt = $pdo->prepare(
            'DELETE FROM internship
             WHERE internship_id = :internship_id
               AND employer_id = :employer_id'
        );
        $deletePostingStmt->execute([
            ':internship_id' => $internshipId,
            ':employer_id' => $employerId,
        ]);

        $pdo->commit();

        return ['success' => true, 'error' => null];
    }
}
