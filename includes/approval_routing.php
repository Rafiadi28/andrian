<?php
/**
 * Helper untuk menentukan role tujuan berikutnya dengan fallback jika pejabat cuti/tidak aktif.
 */
require_once __DIR__ . '/functions.php';

/**
 * Cari dan kembalikan role aktif pertama berdasarkan preference awal dan fallback rules.
 * Strategy:
 * 1. Try preferred/current role
 * 2. If not active, try explicit fallback (e.g., kasubag_analis -> analis)
 * 3. If fallback not active or already checked, advance to next role in chain
 * 4. Repeat until active role found or chain exhausted
 * 
 * Key: Don't mark fallback target as "visited" unless we're going to check it immediately.
 *      If fallback is also in chain (like kadiv_bisnis), let it be checked naturally in chain traversal.
 * 
 * @param PDO $pdo
 * @param string $desiredRole
 * @return string|null role yang aktif atau null jika tak ditemukan
 */
function resolve_next_active_role(PDO $pdo, string $desiredRole): ?string
{
    $desiredRole = strtolower(trim($desiredRole));
    $chain = ['kasubag_analis', 'kepatuhan', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
    $replacementMap = [
        'kasubag_analis' => 'direktur_utama',
        'kabag_kredit' => 'direktur_utama',
        'kadiv_bisnis' => 'direktur_utama',
    ];

    if ($desiredRole === '' || $desiredRole === 'analis') {
        return isRoleActive($pdo, 'analis') ? 'analis' : null;
    }

    if (isRoleActive($pdo, $desiredRole)) {
        return $desiredRole;
    }

    if (isset($replacementMap[$desiredRole]) && isRoleActive($pdo, $replacementMap[$desiredRole])) {
        return $replacementMap[$desiredRole];
    }

    $startIndex = array_search($desiredRole, $chain, true);
    if ($startIndex === false) {
        return null;
    }

    for ($i = $startIndex + 1; $i < count($chain); $i++) {
        $role = $chain[$i];
        if (isRoleActive($pdo, $role)) {
            return $role;
        }
    }

    return isRoleActive($pdo, 'direktur_utama') ? 'direktur_utama' : null;
}

/**
 * Ambil user aktif untuk role tertentu.
 * @param PDO $pdo
 * @param string $role
 * @return array list users (id_user, nama)
 */
function get_active_users_for_role(PDO $pdo, string $role): array
{
    $stmt = $pdo->prepare("SELECT id_user, nama FROM users WHERE role = ? AND status_jabatan = 'aktif'");
    $stmt->execute([$role]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
