<?php


function marketplace_findwork_credentials(): array
{
    $apiKey = trim((string) (
        getenv('FINDWORK_API_KEY')
        ?: ($_ENV['FINDWORK_API_KEY'] ?? $_SERVER['FINDWORK_API_KEY'] ?? (defined('FINDWORK_API_KEY') ? FINDWORK_API_KEY : ''))
    ));
    return [
        'api_key' => $apiKey,
        'configured' => $apiKey !== '',
    ];
}

function marketplace_http_request_json(string $url, string $method = 'GET', array $headers = [], ?string $body = null, int $timeoutSeconds = 8): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $requestHeaders = array_merge(['User-Agent: SkillHive/1.0'], $headers);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        if (strtoupper($method) !== 'GET' && $body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return [
                'ok' => false,
                'status' => $httpCode,
                'body' => is_string($response) ? $response : '',
                'error' => $error,
            ];
        }

        $decoded = json_decode((string) $response, true);
        return [
            'ok' => is_array($decoded),
            'status' => $httpCode,
            'body' => (string) $response,
            'error' => is_array($decoded) ? '' : 'Invalid JSON response from external jobs API.',
            'data' => is_array($decoded) ? $decoded : null,
        ];
    }

    $headerText = "User-Agent: SkillHive/1.0\r\n";
    foreach ($headers as $headerLine) {
        $headerText .= trim((string) $headerLine) . "\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'timeout' => $timeoutSeconds,
            'header' => $headerText,
            'content' => strtoupper($method) === 'GET' ? '' : (string) ($body ?? ''),
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/HTTP\/\S+\s+(\d+)/i', (string) $headerLine, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }

        return [
            'ok' => false,
            'status' => $httpCode,
            'body' => '',
            'error' => 'HTTP request failed while contacting external jobs API.',
        ];
    }

    $decoded = json_decode((string) $response, true);
    $httpCode = 200;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/HTTP\/\S+\s+(\d+)/i', (string) $headerLine, $matches)) {
                $httpCode = (int) $matches[1];
                break;
            }
        }
    }

    return [
        'ok' => is_array($decoded),
        'status' => $httpCode,
        'body' => (string) $response,
        'error' => is_array($decoded) ? '' : 'Invalid JSON response from external jobs API.',
        'data' => is_array($decoded) ? $decoded : null,
    ];
}

function marketplace_external_api_error_notice(array $result, string $sourceName): string
{
    $status = (int) ($result['status'] ?? 0);
    $error = trim((string) ($result['error'] ?? ''));
    $body = trim((string) ($result['body'] ?? ''));

    $message = '';
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? ''));
        }
        if ($message === '') {
            $message = trim(substr(preg_replace('/\s+/', ' ', strip_tags($body)), 0, 180));
        }
    }

    $parts = ['Unable to load external listings from ' . $sourceName];
    if ($status > 0) {
        $parts[] = '(HTTP ' . $status . ')';
    }
    if ($message !== '') {
        $parts[] = '- ' . $message;
    } elseif ($error !== '') {
        $parts[] = '- ' . $error;
    }

    return implode(' ', $parts) . '.';
}

function marketplace_external_work_setup(string $locationText): string
{
    $haystack = strtolower($locationText);
    if (str_contains($haystack, 'remote')) {
        return 'Remote';
    }
    if (str_contains($haystack, 'hybrid')) {
        return 'Hybrid';
    }
    return 'On-site';
}

function marketplace_extract_external_results($payload): array
{
    if (isset($payload['results']) && is_array($payload['results'])) {
        return $payload['results'];
    }
    if (isset($payload['jobs']) && is_array($payload['jobs'])) {
        return $payload['jobs'];
    }
    if (isset($payload['data']) && is_array($payload['data'])) {
        return $payload['data'];
    }
    if (array_is_list($payload)) {
        return $payload;
    }
    return [];
}

function marketplace_external_cache_key(array $currentFilters, int $maxResults): string
{
    $cachePayload = [
        'q' => trim((string) ($currentFilters['q'] ?? '')),
        'industry' => trim((string) ($currentFilters['industry'] ?? '')),
        'location' => trim((string) ($currentFilters['location'] ?? '')),
        'max_results' => max(1, $maxResults),
    ];

    return 'findwork:' . sha1(json_encode($cachePayload, JSON_UNESCAPED_UNICODE));
}

function marketplace_external_cache_get(string $cacheKey, int $ttlSeconds = 600): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $bucket = $_SESSION['marketplace_external_cache'] ?? null;
    if (!is_array($bucket)) {
        return null;
    }

    $entry = $bucket[$cacheKey] ?? null;
    if (!is_array($entry)) {
        return null;
    }

    $cachedAt = (int) ($entry['cached_at'] ?? 0);
    if ($cachedAt <= 0 || (time() - $cachedAt) > $ttlSeconds) {
        unset($_SESSION['marketplace_external_cache'][$cacheKey]);
        return null;
    }

    $data = $entry['data'] ?? null;
    return is_array($data) ? $data : null;
}

function marketplace_external_cache_set(string $cacheKey, array $payload): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (!isset($_SESSION['marketplace_external_cache']) || !is_array($_SESSION['marketplace_external_cache'])) {
        $_SESSION['marketplace_external_cache'] = [];
    }

    $_SESSION['marketplace_external_cache'][$cacheKey] = [
        'cached_at' => time(),
        'data' => $payload,
    ];

    if (count($_SESSION['marketplace_external_cache']) > 20) {
        uasort($_SESSION['marketplace_external_cache'], static function (array $a, array $b): int {
            return ((int) ($a['cached_at'] ?? 0)) <=> ((int) ($b['cached_at'] ?? 0));
        });
        $_SESSION['marketplace_external_cache'] = array_slice($_SESSION['marketplace_external_cache'], -20, null, true);
    }
}


function marketplace_fetch_findwork_listings(array $currentFilters, int $maxResults = 100): array
{
    $creds = marketplace_findwork_credentials();
    if (!$creds['configured']) {
        return [
            'rows' => [],
            'notice' => 'Findwork API is not configured. Set FINDWORK_API_KEY to enable external listings.',
            'count' => 0,
        ];
    }

    $query = trim((string) ($currentFilters['q'] ?? 'intern'));
    $industryFilter = trim((string) ($currentFilters['industry'] ?? ''));
    $locationFilter = trim((string) ($currentFilters['location'] ?? ''));

    $params = [];
    if ($query !== '') {
        $params['search'] = $query;
    }
    if ($locationFilter !== '') {
        $params['location'] = $locationFilter;
    }
    if ($industryFilter !== '') {
        $params['category'] = $industryFilter;
    }
    $params['limit'] = max(1, min(100, $maxResults));

    $cacheKey = marketplace_external_cache_key($currentFilters, $maxResults);
    $cached = marketplace_external_cache_get($cacheKey, 600);
    if (is_array($cached)) {
        return $cached;
    }

    $endpoint = 'https://findwork.dev/api/jobs/?' . http_build_query($params);
    $response = marketplace_http_request_json($endpoint, 'GET', [
        'Authorization: Token ' . $creds['api_key'],
        'Accept: application/json',
    ], null, 10);
    if (!(bool) ($response['ok'] ?? false)) {
        $result = [
            'rows' => [],
            'notice' => marketplace_external_api_error_notice($response, 'Findwork'),
            'count' => 0,
        ];
        marketplace_external_cache_set($cacheKey, $result);
        return $result;
    }

    $payload = $response['data'] ?? null;
    if (!is_array($payload) || !isset($payload['results']) || !is_array($payload['results'])) {
        $result = [
            'rows' => [],
            'notice' => 'Unable to load external listings from Findwork - invalid API response.',
            'count' => 0,
        ];
        marketplace_external_cache_set($cacheKey, $result);
        return $result;
    }

    $rows = [];
    foreach ($payload['results'] as $idx => $job) {
        if (!is_array($job)) continue;
        $title = trim((string) ($job['role'] ?? $job['title'] ?? ''));
        $description = trim((string) ($job['text'] ?? $job['description'] ?? ''));
        // Only include jobs with 'intern' in the title or description
        if (!preg_match('/intern/i', $title . ' ' . $description)) continue;
        if ($title === '') continue;
        $companyName = trim((string) ($job['company_name'] ?? 'External Employer'));
        $industry = trim((string) ($job['category'] ?? 'General'));
        $location = trim((string) ($job['location'] ?? 'Location not set'));
        $monthlyAllowance = 0; // Findwork does not provide salary info
        $postedAt = trim((string) ($job['date_posted'] ?? $job['created'] ?? ''));
        $externalUrl = trim((string) ($job['url'] ?? $job['job_url'] ?? ''));

        // Clean description: remove HTML tags, decode entities, remove React/JSX, trim whitespace
        $desc = $description;
        // Remove HTML tags
        $desc = preg_replace('/<[^>]+>/', ' ', $desc);
        // Remove React/JSX fragments
        $desc = preg_replace('/<\/?[a-zA-Z][^>]*>/', ' ', $desc);
        // Remove class=, data-*, etc.
        $desc = preg_replace('/\s(class|data-[a-zA-Z0-9_-]+)="[^"]*"/', '', $desc);
        // Decode HTML entities
        $desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse whitespace
        $desc = preg_replace('/\s+/', ' ', $desc);
        $desc = trim($desc);
        // Truncate for card preview
        if (strlen($desc) > 220) {
            $desc = mb_substr($desc, 0, 217) . '...';
        }

        $syntheticId = -1 * (100000 + $idx + (int) (abs(crc32($title . '|' . $companyName)) % 900000));
        $rows[] = [
            'internship_id' => $syntheticId,
            'title' => $title,
            'company_name' => $companyName,
            'industry' => $industry,
            'company_badge_status' => 'None',
            'duration_weeks' => 12,
            'allowance' => $monthlyAllowance,
            'work_setup' => marketplace_external_work_setup($location),
            'location' => $location,
            'slots_available' => 1,
            'status' => 'Open',
            'posted_at' => $postedAt !== '' ? $postedAt : date('Y-m-d H:i:s'),
            'required_skills' => '',
            'description' => $desc,
            'is_external' => 1,
            'external_source' => 'Findwork',
            'external_url' => $externalUrl,
        ];
    }
    $result = [
        'rows' => $rows,
        'notice' => count($rows) > 0 ? ('Showing ' . count($rows) . ' external listing' . (count($rows) === 1 ? '' : 's') . ' from Findwork.') : 'No external Findwork listings found for this filter.',
        'count' => count($rows),
    ];
    marketplace_external_cache_set($cacheKey, $result);
    return $result;
}

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
        try {
            $pdo->exec('ALTER TABLE application ADD COLUMN consented_at DATETIME NULL AFTER cover_letter');
        } catch (Throwable $e) {
            // Non-fatal: keep marketplace functional even without schema migration privileges.
        }
    }
    if (!isset($existing['consent_version'])) {
        try {
            $pdo->exec('ALTER TABLE application ADD COLUMN consent_version VARCHAR(20) NULL AFTER consented_at');
        } catch (Throwable $e) {
            // Non-fatal: keep marketplace functional even without schema migration privileges.
        }
    }
    if (!isset($existing['compliance_snapshot'])) {
        try {
            $pdo->exec('ALTER TABLE application ADD COLUMN compliance_snapshot LONGTEXT NULL AFTER consent_version');
        } catch (Throwable $e) {
            // Non-fatal: keep marketplace functional even without schema migration privileges.
        }
    }
    if (!isset($existing['resume_link_snapshot'])) {
        try {
            $pdo->exec('ALTER TABLE application ADD COLUMN resume_link_snapshot VARCHAR(255) NULL AFTER compliance_snapshot');
        } catch (Throwable $e) {
            // Non-fatal: keep marketplace functional even without schema migration privileges.
        }
    }
    if (!isset($existing['profile_link_snapshot'])) {
        try {
            $pdo->exec('ALTER TABLE application ADD COLUMN profile_link_snapshot VARCHAR(255) NULL AFTER resume_link_snapshot');
        } catch (Throwable $e) {
            // Non-fatal: keep marketplace functional even without schema migration privileges.
        }
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

    $skillsAggregateJoin = '
        LEFT JOIN (
            SELECT
                ins.internship_id,
                GROUP_CONCAT(
                    DISTINCT sk.skill_name
                    ORDER BY ins.is_mandatory DESC, sk.skill_name ASC
                    SEPARATOR ", "
                ) AS required_skills
            FROM internship_skill ins
            LEFT JOIN skill sk ON sk.skill_id = ins.skill_id
            GROUP BY ins.internship_id
        ) rs ON rs.internship_id = i.internship_id
    ';

    $baseQuery  = 'FROM internship i
                   INNER JOIN employer e ON e.employer_id = i.employer_id
                   ' . $skillsAggregateJoin . '
                   WHERE LOWER(COALESCE(i.status, "")) = "open"';
    $whereParts = [];
    $params     = [];

    if ($currentFilters['q'] !== '') {
        $whereParts[] = '(i.title LIKE ? OR e.company_name LIKE ? OR e.industry LIKE ? OR i.description LIKE ? OR COALESCE(rs.required_skills, "") LIKE ?)';
        $searchValue  = '%' . $currentFilters['q'] . '%';
        array_push($params, $searchValue, $searchValue, $searchValue, $searchValue, $searchValue);
    }
    if ($currentFilters['industry'] !== '') {
        $whereParts[] = 'e.industry LIKE ?';
        $params[]     = '%' . $currentFilters['industry'] . '%';
    }
    if ($currentFilters['location'] !== '') {
        $whereParts[] = 'i.location LIKE ?';
        $params[]     = '%' . $currentFilters['location'] . '%';
    }
    if (($currentFilters['work_setup'] ?? '') !== '') {
        $whereParts[] = 'i.work_setup = ?';
        $params[]     = $currentFilters['work_setup'];
    }
    if (($currentFilters['duration'] ?? '') !== '') {
        if ($currentFilters['duration'] === 'short') {
            $whereParts[] = 'i.duration_weeks BETWEEN 1 AND 8';
        } elseif ($currentFilters['duration'] === 'medium') {
            $whereParts[] = 'i.duration_weeks BETWEEN 9 AND 16';
        } elseif ($currentFilters['duration'] === 'long') {
            $whereParts[] = 'i.duration_weeks >= 17';
        }
    }
    if (($currentFilters['allowance_range'] ?? '') !== '') {
        if ($currentFilters['allowance_range'] === 'unpaid') {
            $whereParts[] = 'COALESCE(i.allowance, 0) = 0';
        } elseif ($currentFilters['allowance_range'] === 'low') {
            $whereParts[] = 'COALESCE(i.allowance, 0) BETWEEN 1 AND 3000';
        } elseif ($currentFilters['allowance_range'] === 'mid') {
            $whereParts[] = 'COALESCE(i.allowance, 0) BETWEEN 3001 AND 6000';
        } elseif ($currentFilters['allowance_range'] === 'high') {
            $whereParts[] = 'COALESCE(i.allowance, 0) > 6000';
        }
    }

    $whereSql = $whereParts ? (' AND ' . implode(' AND ', $whereParts)) : '';

    $stmt       = $pdo->query('SELECT DISTINCT e.industry AS industry
                               FROM internship i
                               INNER JOIN employer e ON e.employer_id = i.employer_id
                               WHERE LOWER(COALESCE(i.status, "")) = "open"
                               ORDER BY e.industry ASC');
    $industries = array_values(array_filter(array_map(static fn($row) => trim((string) $row['industry']), $stmt->fetchAll(PDO::FETCH_ASSOC))));

    $stmt      = $pdo->query('SELECT DISTINCT i.location AS location
                              FROM internship i
                              WHERE LOWER(COALESCE(i.status, "")) = "open"
                              ORDER BY i.location ASC');
    $locations = array_values(array_filter(array_map(static fn($row) => trim((string) $row['location']), $stmt->fetchAll(PDO::FETCH_ASSOC))));

    $stmt = $pdo->prepare('SELECT internship_id FROM application WHERE student_id = ?');
    $stmt->execute([$userId]);
    $appliedInternshipIds = array_fill_keys(array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'internship_id')), true);

    $sql  = 'SELECT
                i.internship_id,
                i.title,
                e.company_name,
                e.industry,
                e.company_badge_status,
                i.duration_weeks,
                i.allowance,
                i.work_setup,
                i.location,
                i.slots_available,
                i.status,
                COALESCE(i.posted_at, i.created_at) AS posted_at,
                COALESCE(rs.required_skills, "") AS required_skills,
                i.description
            ' . $baseQuery . $whereSql . ' ORDER BY posted_at DESC, i.internship_id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $localListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($localListings as &$localListing) {
        $localListing['is_external'] = 0;
        $localListing['external_source'] = '';
        $localListing['external_url'] = '';
    }
    unset($localListing);

    $includeExternal = (int) ($currentFilters['include_external'] ?? 0) === 1;
    $externalResult = [
        'rows' => [],
        'notice' => '',
        'count' => 0,
    ];
    if ($includeExternal) {
        $externalResult = marketplace_fetch_findwork_listings($currentFilters, 100);
    }
    $externalListings = $externalResult['rows'] ?? [];

    $listings = array_merge($localListings, $externalListings);

    usort($listings, static function (array $a, array $b): int {
        return strcmp((string) ($b['posted_at'] ?? ''), (string) ($a['posted_at'] ?? ''));
    });

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
            'SELECT
                i.internship_id,
                i.title,
                e.company_name,
                e.industry,
                e.company_badge_status,
                i.duration_weeks,
                i.allowance,
                i.work_setup,
                i.location,
                i.slots_available,
                i.status,
                COALESCE(i.posted_at, i.created_at) AS posted_at,
                COALESCE(rs.required_skills, "") AS required_skills,
                i.description,
                i.employer_id,
                e.company_logo,
                e.company_address,
                e.website_url,
                e.verification_status
             FROM internship i
             INNER JOIN employer e ON e.employer_id = i.employer_id
             ' . $skillsAggregateJoin . '
             WHERE i.internship_id = ? AND LOWER(COALESCE(i.status, "")) = "open"
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
        'externalListingsCount' => (int) ($externalResult['count'] ?? 0),
        'externalListingsNotice' => (string) ($externalResult['notice'] ?? ''),
    ];
}
