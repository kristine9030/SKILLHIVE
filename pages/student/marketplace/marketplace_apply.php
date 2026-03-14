<?php

function marketplace_handle_apply(PDO $pdo, string $baseUrl, int $userId, array &$currentFilters, bool &$openApplyModal): void
{
    $internshipId   = (int) ($_POST['internship_id'] ?? 0);
    $consentVersion = 'v1.0';

    $attestAccuracy          = (string) ($_POST['attest_accuracy'] ?? '') === '1';
    $consentPrivacy          = (string) ($_POST['consent_privacy'] ?? '') === '1';
    $coverLetter             = trim((string) ($_POST['cover_letter'] ?? ''));
    $preferredStartDate      = trim((string) ($_POST['preferred_start_date'] ?? ''));
    $emergencyName           = trim((string) ($_POST['emergency_contact_name'] ?? ''));
    $emergencyPhone          = trim((string) ($_POST['emergency_contact_phone'] ?? ''));
    $confirmMoa              = (string) ($_POST['confirm_moa'] ?? '') === '1';
    $confirmEndorsement      = (string) ($_POST['confirm_endorsement'] ?? '') === '1';
    $confirmMedical          = (string) ($_POST['confirm_medical'] ?? '') === '1';
    $confirmInsurance        = (string) ($_POST['confirm_insurance'] ?? '') === '1';
    $confirmUniversityPolicy = (string) ($_POST['confirm_university_policy'] ?? '') === '1';

    if ($internshipId <= 0) {
        $_SESSION['status'] = 'Invalid internship selected.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => 0]));
    }

    $viewedMap = $_SESSION['marketplace_viewed'] ?? [];
    $viewedAt  = (int) ($viewedMap[$internshipId] ?? 0);
    if ($viewedAt <= 0) {
        $_SESSION['marketplace_viewed'][$internshipId] = time();
    }

    $stmt = $pdo->prepare('SELECT resume_file FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $studentRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumeFile = trim((string) ($studentRow['resume_file'] ?? ''));
    if ($resumeFile === '') {
        $_SESSION['status'] = 'Upload your resume first before applying.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    if (!$attestAccuracy || !$consentPrivacy) {
        $_SESSION['status'] = 'Please confirm both legal consent checkboxes before applying.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    if (!$confirmMoa || !$confirmEndorsement || !$confirmMedical || !$confirmInsurance || !$confirmUniversityPolicy) {
        $_SESSION['status'] = 'Please complete all university internship requirement confirmations before applying.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    if ($preferredStartDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredStartDate)) {
        $_SESSION['status'] = 'Please provide a valid preferred start date.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    if ($emergencyName === '' || mb_strlen($emergencyName) > 120) {
        $_SESSION['status'] = 'Please provide a valid emergency contact name.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    if ($emergencyPhone === '' || mb_strlen($emergencyPhone) > 40) {
        $_SESSION['status'] = 'Please provide a valid emergency contact number.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    if (mb_strlen($coverLetter) < 120) {
        $_SESSION['status'] = 'Please provide a short cover letter (at least 120 characters).';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    if (mb_strlen($coverLetter) > 5000) {
        $_SESSION['status'] = 'Cover letter is too long.';
        marketplace_redirect_with_filters($baseUrl, array_merge($currentFilters, ['detail' => $internshipId, 'open_apply' => 1]));
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT internship_id, status FROM internship WHERE internship_id = ? LIMIT 1');
        $stmt->execute([$internshipId]);
        $internshipRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$internshipRow || (string) $internshipRow['status'] !== 'Open') {
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

        $compatibilityScore = null;
        if ($requiredSkillIds) {
            $matchedSkills      = array_intersect($requiredSkillIds, $studentSkillIds);
            $compatibilityScore = round((count($matchedSkills) / count($requiredSkillIds)) * 100, 2);
        }

        $complianceSnapshot = [
            'preferred_start_date'    => $preferredStartDate,
            'emergency_contact_name'  => $emergencyName,
            'emergency_contact_phone' => $emergencyPhone,
            'resume_file'             => $resumeFile,
            'resume_link'             => $baseUrl . '/assets/backend/uploads/resumes/' . rawurlencode($resumeFile),
            'profile_link'            => $baseUrl . '/layout.php?page=student/profile&student_id=' . $userId,
            'confirmations'           => [
                'moa'               => $confirmMoa,
                'endorsement'       => $confirmEndorsement,
                'medical'           => $confirmMedical,
                'insurance'         => $confirmInsurance,
                'university_policy' => $confirmUniversityPolicy,
                'attest_accuracy'   => $attestAccuracy,
                'consent_privacy'   => $consentPrivacy,
            ],
        ];

        $resumeLinkSnapshot  = $baseUrl . '/assets/backend/uploads/resumes/' . rawurlencode($resumeFile);
        $profileLinkSnapshot = $baseUrl . '/layout.php?page=student/profile&student_id=' . $userId;

        $stmt = $pdo->prepare(
            'INSERT INTO application (student_id, internship_id, application_date, status, compatibility_score, cover_letter, consented_at, consent_version, compliance_snapshot, resume_link_snapshot, profile_link_snapshot)
             VALUES (?, ?, CURDATE(), ?, ?, ?, NOW(), ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $internshipId,
            'Pending',
            $compatibilityScore,
            $coverLetter,
            $consentVersion,
            json_encode($complianceSnapshot, JSON_UNESCAPED_UNICODE),
            $resumeLinkSnapshot,
            $profileLinkSnapshot,
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
