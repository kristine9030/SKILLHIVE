<?php
function profile_ensure_link_columns(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $requiredColumns = [
        'google_url' => 'VARCHAR(255) NULL',
        'gmail_url' => 'VARCHAR(255) NULL',
        'discord_url' => 'VARCHAR(255) NULL',
        'dribbble_url' => 'VARCHAR(255) NULL',
        'behance_url' => 'VARCHAR(255) NULL',
        'portfolio_url' => 'VARCHAR(255) NULL',
        'linkedin_url' => 'VARCHAR(255) NULL',
        'github_url' => 'VARCHAR(255) NULL',
        'about_me_intro' => 'TEXT NULL',
        'about_me_points' => 'TEXT NULL',
        'experience_entries' => 'LONGTEXT NULL',
        'portfolio_entries' => 'LONGTEXT NULL',
    ];

    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM student') as $column) {
        $existing[(string) $column['Field']] = true;
    }

    foreach ($requiredColumns as $column => $definition) {
        if (isset($existing[$column])) {
            continue;
        }

        $pdo->exec("ALTER TABLE student ADD COLUMN $column $definition");
    }

    $ensured = true;
}

function profile_normalize_link_input(string $value, string $type = 'url'): array
{
    $value = trim($value);
    if ($value === '') {
        return [null, null];
    }

    if ($type === 'email') {
        if (str_starts_with(strtolower($value), 'mailto:')) {
            $email = substr($value, 7);
            return filter_var($email, FILTER_VALIDATE_EMAIL)
                ? ['mailto:' . $email, null]
                : [null, 'Enter a valid Gmail address or mailto link.'];
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL)
            ? ['mailto:' . $value, null]
            : [null, 'Enter a valid Gmail address.'];
    }

    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
        $value = 'https://' . $value;
    }

    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return [null, 'Enter a valid URL including domain name.'];
    }

    return [$value, null];
}

function profile_handle_edit(PDO $pdo, int $userId, array &$profileErrors, string &$profileSuccess, string &$userName): void
{
    profile_ensure_link_columns($pdo);

    if (($_POST['action'] ?? '') !== 'update_profile') {
        return;
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $yearLevel = (int) ($_POST['year_level'] ?? 0);
    $availability = trim($_POST['availability_status'] ?? 'Available');
    $preferredIndustry = trim($_POST['preferred_industry'] ?? '');
    $aboutMeIntro = trim((string) ($_POST['about_me_intro'] ?? ''));
    $aboutMePointsRaw = trim((string) ($_POST['about_me_points'] ?? ''));
    $experienceEntriesRaw = trim((string) ($_POST['experience_entries'] ?? ''));
    $portfolioEntriesRaw = trim((string) ($_POST['portfolio_entries'] ?? ''));
    [$googleUrl, $googleError] = profile_normalize_link_input((string) ($_POST['google_url'] ?? ''));
    [$gmailUrl, $gmailError] = profile_normalize_link_input((string) ($_POST['gmail_url'] ?? ''), 'email');
    [$discordUrl, $discordError] = profile_normalize_link_input((string) ($_POST['discord_url'] ?? ''));
    [$dribbbleUrl, $dribbbleError] = profile_normalize_link_input((string) ($_POST['dribbble_url'] ?? ''));
    [$behanceUrl, $behanceError] = profile_normalize_link_input((string) ($_POST['behance_url'] ?? ''));
    [$portfolioUrl, $portfolioError] = profile_normalize_link_input((string) ($_POST['portfolio_url'] ?? ''));
    [$linkedinUrl, $linkedinError] = profile_normalize_link_input((string) ($_POST['linkedin_url'] ?? ''));
    [$githubUrl, $githubError] = profile_normalize_link_input((string) ($_POST['github_url'] ?? ''));

    $validAvailability = ['Available', 'Unavailable', 'Currently Interning'];

    if ($firstName === '') $profileErrors[] = 'First name is required.';
    if ($lastName === '') $profileErrors[] = 'Last name is required.';
    if ($program === '') $profileErrors[] = 'Program is required.';
    if ($department === '') $profileErrors[] = 'Department is required.';
    if ($yearLevel < 1 || $yearLevel > 8) $profileErrors[] = 'Year level must be between 1 and 8.';
    if (!in_array($availability, $validAvailability, true)) $profileErrors[] = 'Invalid availability status.';

    if (mb_strlen($aboutMeIntro) > 600) {
        $profileErrors[] = 'About me intro must be 600 characters or less.';
    }

    $aboutMePoints = [];
    if ($aboutMePointsRaw !== '') {
        $pointLines = preg_split('/\r\n|\r|\n/', $aboutMePointsRaw) ?: [];
        foreach ($pointLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > 180) {
                $profileErrors[] = 'Each about me point must be 180 characters or less.';
                break;
            }
            $aboutMePoints[] = $line;
            if (count($aboutMePoints) >= 8) {
                break;
            }
        }
    }

    $experienceEntries = [];
    if ($experienceEntriesRaw !== '') {
        $expLines = preg_split('/\r\n|\r|\n/', $experienceEntriesRaw) ?: [];
        foreach ($expLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $title = (string) ($parts[0] ?? '');
            $subtitle = (string) ($parts[1] ?? '');
            $date = (string) ($parts[2] ?? '');

            if ($title === '' || $subtitle === '') {
                $profileErrors[] = 'Each experience line must follow: Role | Organization | Date.';
                break;
            }

            if (mb_strlen($title) > 80 || mb_strlen($subtitle) > 120 || mb_strlen($date) > 60) {
                $profileErrors[] = 'Experience fields are too long.';
                break;
            }

            $experienceEntries[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'date' => $date !== '' ? $date : 'Present',
            ];

            if (count($experienceEntries) >= 8) {
                break;
            }
        }
    }

    $portfolioEntries = [];
    if ($portfolioEntriesRaw !== '') {
        $portfolioLines = preg_split('/\r\n|\r|\n/', $portfolioEntriesRaw) ?: [];
        foreach ($portfolioLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $label = (string) ($parts[0] ?? '');
            $emoji = (string) ($parts[1] ?? '');

            if ($label === '') {
                $profileErrors[] = 'Each portfolio line must include a title.';
                break;
            }

            if (mb_strlen($label) > 80 || mb_strlen($emoji) > 8) {
                $profileErrors[] = 'Portfolio fields are too long.';
                break;
            }

            $portfolioEntries[] = [
                'label' => $label,
                'emoji' => $emoji !== '' ? $emoji : '💼',
            ];

            if (count($portfolioEntries) >= 9) {
                break;
            }
        }
    }

    foreach ([$googleError, $gmailError, $discordError, $dribbbleError, $behanceError, $portfolioError, $linkedinError, $githubError] as $linkError) {
        if ($linkError !== null) {
            $profileErrors[] = $linkError;
        }
    }

    if ($profileErrors) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE student
         SET first_name = ?,
             last_name = ?,
             program = ?,
             department = ?,
             year_level = ?,
             availability_status = ?,
             preferred_industry = ?,
             about_me_intro = ?,
             about_me_points = ?,
             experience_entries = ?,
             portfolio_entries = ?,
             google_url = ?,
             gmail_url = ?,
             discord_url = ?,
             dribbble_url = ?,
             behance_url = ?,
             portfolio_url = ?,
             linkedin_url = ?,
             github_url = ?,
             updated_at = NOW()
         WHERE student_id = ?'
    );

    $stmt->execute([
        $firstName,
        $lastName,
        $program,
        $department,
        $yearLevel,
        $availability,
        $preferredIndustry !== '' ? $preferredIndustry : null,
        $aboutMeIntro !== '' ? $aboutMeIntro : null,
        !empty($aboutMePoints) ? implode("\n", $aboutMePoints) : null,
        !empty($experienceEntries) ? json_encode($experienceEntries, JSON_UNESCAPED_UNICODE) : null,
        !empty($portfolioEntries) ? json_encode($portfolioEntries, JSON_UNESCAPED_UNICODE) : null,
        $googleUrl,
        $gmailUrl,
        $discordUrl,
        $dribbbleUrl,
        $behanceUrl,
        $portfolioUrl,
        $linkedinUrl,
        $githubUrl,
        $userId,
    ]);

    $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
    $_SESSION['program'] = $program;
    $_SESSION['department'] = $department;
    $_SESSION['year_level'] = $yearLevel;

    $userName = $_SESSION['user_name'];
    $profileSuccess = 'Profile updated successfully.';
}
