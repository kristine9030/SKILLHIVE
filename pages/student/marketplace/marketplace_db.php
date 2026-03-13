<?php

function marketplace_ensure_application_consent_columns(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM application') as $column) {
        $existing[(string) $column['Field']] = true;
    }

    if (!isset($existing['consented_at'])) {
        $pdo->exec('ALTER TABLE application ADD COLUMN consented_at DATETIME NULL AFTER cover_letter');
    }
    if (!isset($existing['consent_version'])) {
        $pdo->exec('ALTER TABLE application ADD COLUMN consent_version VARCHAR(20) NULL AFTER consented_at');
    }
    if (!isset($existing['compliance_snapshot'])) {
        $pdo->exec('ALTER TABLE application ADD COLUMN compliance_snapshot LONGTEXT NULL AFTER consent_version');
    }
    if (!isset($existing['resume_link_snapshot'])) {
        $pdo->exec('ALTER TABLE application ADD COLUMN resume_link_snapshot VARCHAR(255) NULL AFTER compliance_snapshot');
    }
    if (!isset($existing['profile_link_snapshot'])) {
        $pdo->exec('ALTER TABLE application ADD COLUMN profile_link_snapshot VARCHAR(255) NULL AFTER resume_link_snapshot');
    }

    $ensured = true;
}

function marketplace_load_data(PDO $pdo, int $userId, array $currentFilters, int $selectedDetailId): array
{
    $studentSkillNames = [];
    $stmt = $pdo->prepare(
        'SELECT s.skill_name
         FROM student_skill ss
         INNER JOIN skill s ON s.skill_id = ss.skill_id
         WHERE ss.student_id = ?'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $skillRow) {
        $studentSkillNames[strtolower(trim((string) $skillRow['skill_name']))] = true;
    }

    $baseQuery  = 'FROM v_internship_listings v INNER JOIN internship i ON i.internship_id = v.internship_id WHERE v.status = "Open"';
    $whereParts = [];
    $params     = [];

    if ($currentFilters['q'] !== '') {
        $whereParts[] = '(v.title LIKE ? OR v.company_name LIKE ? OR v.industry LIKE ? OR i.description LIKE ? OR v.required_skills LIKE ?)';
        $searchValue  = '%' . $currentFilters['q'] . '%';
        array_push($params, $searchValue, $searchValue, $searchValue, $searchValue, $searchValue);
    }
    if ($currentFilters['industry'] !== '') {
        $whereParts[] = 'v.industry = ?';
        $params[]     = $currentFilters['industry'];
    }
    if ($currentFilters['location'] !== '') {
        $whereParts[] = 'v.location = ?';
        $params[]     = $currentFilters['location'];
    }
    if ($currentFilters['work_setup'] !== '') {
        $whereParts[] = 'v.work_setup = ?';
        $params[]     = $currentFilters['work_setup'];
    }

    $whereSql = $whereParts ? (' AND ' . implode(' AND ', $whereParts)) : '';

    $stmt       = $pdo->query('SELECT DISTINCT industry FROM v_internship_listings WHERE status = "Open" ORDER BY industry ASC');
    $industries = array_values(array_filter(array_map(static fn($row) => trim((string) $row['industry']), $stmt->fetchAll(PDO::FETCH_ASSOC))));

    $stmt      = $pdo->query('SELECT DISTINCT location FROM v_internship_listings WHERE status = "Open" ORDER BY location ASC');
    $locations = array_values(array_filter(array_map(static fn($row) => trim((string) $row['location']), $stmt->fetchAll(PDO::FETCH_ASSOC))));

    $stmt = $pdo->prepare('SELECT internship_id FROM application WHERE student_id = ?');
    $stmt->execute([$userId]);
    $appliedInternshipIds = array_fill_keys(array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'internship_id')), true);

    $sql  = 'SELECT v.internship_id, v.title, v.company_name, v.industry, v.company_badge_status, v.duration_weeks, v.allowance, v.work_setup, v.location, v.slots_available, v.status, v.posted_at, v.required_skills, i.description '
           . $baseQuery . $whereSql . ' ORDER BY v.posted_at DESC, v.internship_id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $detailListing      = null;
    $detailRequirements = [];
    $detailMatchCount   = 0;
    $detailRequiredCount = 0;

    $stmt = $pdo->prepare('SELECT resume_file FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $resumeRow        = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $studentHasResume = trim((string) ($resumeRow['resume_file'] ?? '')) !== '';

    if ($selectedDetailId > 0) {
        $stmt = $pdo->prepare(
            'SELECT v.internship_id, v.title, v.company_name, v.industry, v.company_badge_status, v.duration_weeks, v.allowance, v.work_setup, v.location, v.slots_available, v.status, v.posted_at, v.required_skills, i.description, i.employer_id,
                    e.company_logo, e.company_address, e.website_url, e.verification_status
             FROM v_internship_listings v
             INNER JOIN internship i ON i.internship_id = v.internship_id
             INNER JOIN employer e ON e.employer_id = i.employer_id
             WHERE v.internship_id = ? AND v.status = "Open"
             LIMIT 1'
        );
        $stmt->execute([$selectedDetailId]);
        $detailListing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($detailListing !== null) {
            $_SESSION['marketplace_viewed'][$selectedDetailId] = time();

            $stmt = $pdo->prepare(
                'SELECT s.skill_name, ins.required_level, ins.is_mandatory
                 FROM internship_skill ins
                 INNER JOIN skill s ON s.skill_id = ins.skill_id
                 WHERE ins.internship_id = ?
                 ORDER BY ins.is_mandatory DESC, s.skill_name ASC'
            );
            $stmt->execute([$selectedDetailId]);
            $detailRequirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($detailRequirements as $row) {
                $detailRequiredCount++;
                $skillLower = strtolower(trim((string) ($row['skill_name'] ?? '')));
                if ($skillLower !== '' && isset($studentSkillNames[$skillLower])) {
                    $detailMatchCount++;
                }
            }
        }
    }

    return [
        'studentSkillNames'    => $studentSkillNames,
        'listings'             => $listings,
        'industries'           => $industries,
        'locations'            => $locations,
        'appliedInternshipIds' => $appliedInternshipIds,
        'detailListing'        => $detailListing,
        'detailRequirements'   => $detailRequirements,
        'detailMatchCount'     => $detailMatchCount,
        'detailRequiredCount'  => $detailRequiredCount,
        'studentHasResume'     => $studentHasResume,
        'resumeRow'            => $resumeRow,
    ];
}
