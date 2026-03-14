<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/profile_following.php';

header('Content-Type: application/json; charset=utf-8');

$currentRole = (string) ($_SESSION['role'] ?? '');
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$validRoles = ['student', 'employer', 'adviser'];

if ($currentUserId <= 0 || !in_array($currentRole, $validRoles, true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

profile_following_ensure_schema($pdo);
[$discoverUsers, $followingUsers, $followerUsers, $followingCount, $followersCount] = profile_following_load_data($pdo, $currentRole, $currentUserId);

$baseUrl = '/SkillHive';

$mapUser = static function (array $u) use ($baseUrl): array {
    $role = (string) ($u['role'] ?? '');
    $avatar = trim((string) ($u['avatar_file'] ?? ''));
    $avatarUrl = '';

    if ($avatar !== '') {
        if ($role === 'student') {
            $avatarUrl = $baseUrl . '/assets/backend/uploads/profile/' . rawurlencode($avatar);
        } elseif ($role === 'employer') {
            $avatarUrl = $baseUrl . '/assets/backend/uploads/company/' . rawurlencode($avatar);
        }
    }

    return [
        'role' => $role,
        'id' => (int) ($u['id'] ?? 0),
        'display_name' => (string) ($u['display_name'] ?? ''),
        'headline' => (string) ($u['headline'] ?? ''),
        'subtitle' => (string) ($u['subtitle'] ?? ''),
        'is_following' => !empty($u['is_following']),
        'avatar_url' => $avatarUrl,
    ];
};

echo json_encode([
    'ok' => true,
    'following_count' => $followingCount,
    'followers_count' => $followersCount,
    'discover_users' => array_map($mapUser, $discoverUsers),
    'following_users' => array_map($mapUser, $followingUsers),
    'follower_users' => array_map($mapUser, $followerUsers),
]);
