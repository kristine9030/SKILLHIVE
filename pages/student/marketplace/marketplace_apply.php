<?php

function marketplace_handle_apply(PDO $pdo, string $baseUrl, int $userId, array &$currentFilters, bool &$openApplyModal): void
{
    $internshipId = (int) ($_POST['internship_id'] ?? 0);
    $coverLetter  = trim((string) ($_POST['cover_letter'] ?? ''));

    if ($internshipId <= 0) {
        $_SESSION['status'] = 'Invalid internship selected.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => 0]));
    }

    if ($coverLetter === '') {
        $coverLetter = 'Internship application submitted via SkillHive.';
    }

    if (strlen($coverLetter) > 5000) {
        $_SESSION['status'] = 'Cover letter is too long.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    $viewedMap = $_SESSION['marketplace_viewed'] ?? [];
    $viewedAt  = (int) ($viewedMap[$internshipId] ?? 0);
    if ($viewedAt <= 0) {
        $_SESSION['marketplace_viewed'][$internshipId] = time();
    }

    $stmt = $pdo->prepare('SELECT student_id FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['status'] = 'Student account not found for this session.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT internship_id, status FROM internship WHERE internship_id = ? LIMIT 1');
        $stmt->execute([$internshipId]);
        $internshipRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$internshipRow || strtolower(trim((string) $internshipRow['status'])) !== 'open') {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['status'] = 'This internship is no longer open for applications.';
            marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => 0]));
        }

        $stmt = $pdo->prepare('SELECT 1 FROM application WHERE student_id = ? AND internship_id = ? LIMIT 1');
        $stmt->execute([$userId, $internshipId]);
        if ($stmt->fetchColumn()) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['status'] = 'You already applied to this internship.';
            marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 0]));
        }

        $stmt = $pdo->prepare('SELECT skill_id FROM student_skill WHERE student_id = ?');
        $stmt->execute([$userId]);
        $studentSkillIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'skill_id'));

        $stmt = $pdo->prepare('SELECT skill_id FROM internship_skill WHERE internship_id = ?');
        $stmt->execute([$internshipId]);
        $requiredSkillIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'skill_id'));

        $compatibilityScore = 0.0;
        if ($requiredSkillIds) {
            $matchedSkills      = array_intersect($requiredSkillIds, $studentSkillIds);
            $compatibilityScore = round((count($matchedSkills) / count($requiredSkillIds)) * 100, 2);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO application (student_id, internship_id, application_date, status, compatibility_score, cover_letter, updated_at)
             VALUES (?, ?, CURDATE(), ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $internshipId,
            'Pending',
            $compatibilityScore,
            $coverLetter,
        ]);

        $pdo->commit();

        $_SESSION['status'] = 'Application submitted successfully.';
        marketplace_redirect_to_applications($baseUrl);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $isLocal = in_array((string) ($_SERVER['SERVER_NAME'] ?? ''), ['localhost', '127.0.0.1'], true);
        $_SESSION['status'] = $isLocal
            ? 'Application could not be submitted: ' . $e->getMessage()
            : 'Application could not be submitted right now. Please try again.';

        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }
}
