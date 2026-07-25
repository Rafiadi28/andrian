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
    // Ordered chain for escalation
    $chain = ['kasubag_analis', 'kepatuhan', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];

    // Explicit fallback mapping when role is unavailable
    $fallback = [
        'kasubag_analis' => 'kepatuhan',
        'kepatuhan' => 'kabag_kredit',
        'kabag_kredit' => 'kadiv_bisnis',
        'kadiv_bisnis' => 'direktur_utama'
    ];

    $visited = [];
    $role = $desiredRole;

    while ($role !== null && !isset($visited[$role])) {
        $visited[$role] = true;

        // Check if any active user exists for this role
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND status_jabatan = 'aktif'");
        $stmt->execute([$role]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            return $role;
        }

        // Role not active; try explicit fallback first
        if (isset($fallback[$role])) {
            $fallback_role = $fallback[$role];
            // Check if fallback role exists and is active
            if (!isset($visited[$fallback_role])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND status_jabatan = 'aktif'");
                $stmt->execute([$fallback_role]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return $fallback_role;
                }
                // Fallback role NOT active and NOT in chain: mark as visited to avoid revisit
                if (!in_array($fallback_role, $chain, true)) {
                    $visited[$fallback_role] = true;
                }
            }
        }

        // Advance to next role in chain
        $pos = array_search($role, $chain, true);
        if ($pos !== false && $pos + 1 < count($chain)) {
            $role = $chain[$pos + 1];
            continue;
        }

        // No more roles in chain -> stop
        $role = null;
    }

    return null;
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
