<?php
function profile_following_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_follow (
            follow_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            follower_role VARCHAR(20) NOT NULL,
            follower_id INT UNSIGNED NOT NULL,
            followee_role VARCHAR(20) NOT NULL,
            followee_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_follow (follower_role, follower_id, followee_role, followee_id),
            KEY idx_follower (follower_role, follower_id),
            KEY idx_followee (followee_role, followee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function profile_following_handle_action(PDO $pdo, string $currentRole, int $currentUserId, array &$profileErrors, string &$profileSuccess): void
{
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['follow_user', 'unfollow_user'], true)) {
        return;
    }

    $targetRole = trim((string) ($_POST['target_role'] ?? ''));
    $targetId = (int) ($_POST['target_id'] ?? 0);

    $validRoles = ['student', 'employer', 'adviser'];
    if (!in_array($targetRole, $validRoles, true) || $targetId <= 0) {
        $profileErrors[] = 'Invalid follow target.';
        return;
    }

    $followerId = $currentRole === 'student' ? profile_following_resolve_student_canonical_id($pdo, $currentUserId) : $currentUserId;
    $effectiveTargetId = $targetRole === 'student' ? profile_following_resolve_student_canonical_id($pdo, $targetId) : $targetId;

    if ($targetRole === $currentRole && $effectiveTargetId === $followerId) {
        $profileErrors[] = 'You cannot follow your own account.';
        return;
    }

    if (!profile_following_target_exists($pdo, $targetRole, $effectiveTargetId)) {
        $profileErrors[] = 'Target user does not exist.';
        return;
    }

    if ($action === 'follow_user') {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO user_follow (follower_role, follower_id, followee_role, followee_id)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$currentRole, $followerId, $targetRole, $effectiveTargetId]);
        $profileSuccess = 'User followed successfully.';
        return;
    }

    $followerIds = $currentRole === 'student' ? profile_following_get_student_alias_ids($pdo, $currentUserId) : [$followerId];
    $followeeIds = $targetRole === 'student' ? profile_following_get_student_alias_ids($pdo, $effectiveTargetId) : [$effectiveTargetId];

    $followerIds = array_values(array_unique(array_map('intval', $followerIds)));
    $followeeIds = array_values(array_unique(array_map('intval', $followeeIds)));
    if (!$followerIds || !$followeeIds) {
        $profileSuccess = 'User unfollowed successfully.';
        return;
    }

    $inFollowers = implode(',', array_fill(0, count($followerIds), '?'));
    $inFollowees = implode(',', array_fill(0, count($followeeIds), '?'));
    $sql = "DELETE FROM user_follow
            WHERE follower_role = ?
              AND follower_id IN ($inFollowers)
              AND followee_role = ?
              AND followee_id IN ($inFollowees)";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$currentRole], $followerIds, [$targetRole], $followeeIds);
    $stmt->execute($params);
    $profileSuccess = 'User unfollowed successfully.';
}

function profile_following_load_data(PDO $pdo, string $currentRole, int $currentUserId): array
{
    $allUsers = profile_following_fetch_all_users($pdo);
    $selfStudentAliases = $currentRole === 'student' ? profile_following_get_student_alias_ids($pdo, $currentUserId) : [$currentUserId];
    $selfStudentAliases = array_values(array_unique(array_map('intval', $selfStudentAliases)));
    $userMap = [];
    foreach ($allUsers as $u) {
        $userMap[(string) $u['role'] . ':' . (int) $u['id']] = $u;
    }

    if ($currentRole === 'student' && $selfStudentAliases) {
        $inSelf = implode(',', array_fill(0, count($selfStudentAliases), '?'));
        $sql = "SELECT followee_role, followee_id
                FROM user_follow
                WHERE follower_role = 'student'
                  AND follower_id IN ($inSelf)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($selfStudentAliases);
        $followingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            'SELECT followee_role, followee_id
             FROM user_follow
             WHERE follower_role = ? AND follower_id = ?'
        );
        $stmt->execute([$currentRole, $currentUserId]);
        $followingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $followingMap = [];
    foreach ($followingRows as $row) {
        $followingMap[$row['followee_role'] . ':' . (int) $row['followee_id']] = true;
    }

    $discoverUsers = [];
    $followingUsers = [];

    foreach ($allUsers as $u) {
        $role = (string) ($u['role'] ?? '');
        $id = (int) ($u['id'] ?? 0);
        if ($role === 'student' && $currentRole === 'student' && in_array($id, $selfStudentAliases, true)) {
            continue;
        }
        if ($role === $currentRole && $id === $currentUserId) {
            continue;
        }

        $key = $role . ':' . $id;
        $u['is_following'] = isset($followingMap[$key]);
        $discoverUsers[] = $u;
        if ($u['is_following']) {
            $followingUsers[] = $u;
        }
    }

    usort($discoverUsers, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
    });

    usort($followingUsers, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
    });

    if ($currentRole === 'student' && $selfStudentAliases) {
        $inSelf = implode(',', array_fill(0, count($selfStudentAliases), '?'));
        $sqlCount = "SELECT COUNT(*)
                     FROM user_follow
                     WHERE followee_role = 'student'
                       AND followee_id IN ($inSelf)";
        $stmt = $pdo->prepare($sqlCount);
        $stmt->execute($selfStudentAliases);
        $followersCount = (int) $stmt->fetchColumn();

        $sqlRows = "SELECT follower_role, follower_id
                    FROM user_follow
                    WHERE followee_role = 'student'
                      AND followee_id IN ($inSelf)";
        $stmt = $pdo->prepare($sqlRows);
        $stmt->execute($selfStudentAliases);
        $followerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM user_follow
             WHERE followee_role = ? AND followee_id = ?'
        );
        $stmt->execute([$currentRole, $currentUserId]);
        $followersCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT follower_role, follower_id
             FROM user_follow
             WHERE followee_role = ? AND followee_id = ?'
        );
        $stmt->execute([$currentRole, $currentUserId]);
        $followerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $followerUsers = [];
    foreach ($followerRows as $row) {
        $key = (string) $row['follower_role'] . ':' . (int) $row['follower_id'];
        if (isset($userMap[$key])) {
            $u = $userMap[$key];
            $u['is_following'] = isset($followingMap[(string) $u['role'] . ':' . (int) $u['id']]);
            $followerUsers[] = $u;
        }
    }

    usort($followerUsers, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
    });

    return [$discoverUsers, $followingUsers, $followerUsers, count($followingUsers), $followersCount];
}

function profile_following_fetch_all_users(PDO $pdo): array
{
    $sql =
        "SELECT 'student' AS role,
                s.student_id AS id,
                TRIM(CONCAT(s.first_name, ' ', s.last_name)) AS display_name,
                COALESCE(s.program, 'Student') AS headline,
              CONCAT('SN: ', COALESCE(s.student_number, 'N/A'), ' · ', COALESCE(s.email, '')) AS subtitle,
                COALESCE(s.profile_picture, '') AS avatar_file
         FROM student s
         UNION ALL
         SELECT 'employer' AS role,
                e.employer_id AS id,
                e.company_name AS display_name,
                COALESCE(e.industry, 'Employer') AS headline,
              CONCAT(COALESCE(e.company_address, 'Company'), ' · ', COALESCE(e.email, '')) AS subtitle,
                COALESCE(e.company_logo, '') AS avatar_file
         FROM employer e
         UNION ALL
         SELECT 'adviser' AS role,
                a.adviser_id AS id,
                TRIM(CONCAT(a.first_name, ' ', a.last_name)) AS display_name,
                'Internship Adviser' AS headline,
              CONCAT(COALESCE(a.department, 'Adviser'), ' · ', COALESCE(a.email, '')) AS subtitle,
                '' AS avatar_file
         FROM internship_adviser a";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function profile_following_target_exists(PDO $pdo, string $role, int $id): bool
{
    if ($role === 'student') {
        $stmt = $pdo->prepare('SELECT 1 FROM student WHERE student_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    if ($role === 'employer') {
        $stmt = $pdo->prepare('SELECT 1 FROM employer WHERE employer_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    if ($role === 'adviser') {
        $stmt = $pdo->prepare('SELECT 1 FROM internship_adviser WHERE adviser_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    return false;
}

function profile_following_resolve_student_canonical_id(PDO $pdo, int $studentId): int
{
    if ($studentId <= 0) {
        return $studentId;
    }

    $aliases = profile_following_get_student_alias_ids($pdo, $studentId);
    if (!$aliases) {
        return $studentId;
    }

    return (int) max($aliases);
}

function profile_following_get_student_alias_ids(PDO $pdo, int $studentId): array
{
    if ($studentId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT first_name, last_name FROM student WHERE student_id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [$studentId];
    }

    $firstName = trim((string) ($row['first_name'] ?? ''));
    $lastName = trim((string) ($row['last_name'] ?? ''));
    if ($firstName === '' || $lastName === '') {
        return [$studentId];
    }

    $stmt = $pdo->prepare(
        'SELECT student_id
         FROM student
         WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(?))
           AND LOWER(TRIM(last_name)) = LOWER(TRIM(?))'
    );
    $stmt->execute([$firstName, $lastName]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!in_array($studentId, $ids, true)) {
        $ids[] = $studentId;
    }

    $ids = array_values(array_unique($ids));
    sort($ids);
    return $ids;
}
