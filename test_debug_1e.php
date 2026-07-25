<?php
/**
 * Debug resolution for Test Case 1e
 */

function simulate_resolve_debug($desired, $active_roles) {
    $chain = ['kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
    $fallback = [
        'kasubag_analis' => 'analis',
        'kabag_kredit' => 'kadiv_bisnis',
        'kadiv_bisnis' => 'direktur_utama'
    ];

    $visited = [];
    $role = $desired;
    $iter = 0;

    echo "Starting resolution for desired=$desired\n";
    echo "Active roles: [" . implode(', ', $active_roles) . "]\n\n";

    while ($role !== null && !isset($visited[$role])) {
        $iter++;
        echo "=== Iteration $iter ===\n";
        echo "Current role: $role\n";
        $visited[$role] = true;
        echo "Mark visited[$role]=true\n";

        // Check if role has active users
        $is_active = in_array($role, $active_roles);
        echo "Is $role active? " . ($is_active ? 'YES' : 'NO') . "\n";
        if ($is_active) {
            echo "✓ FOUND: $role\n";
            return $role;
        }

        // Role not active; try explicit fallback first
        if (isset($fallback[$role])) {
            $fallback_role = $fallback[$role];
            echo "Fallback for $role: $fallback_role\n";
            
            // Check if fallback role exists and is active
            if (!isset($visited[$fallback_role])) {
                $is_fallback_active = in_array($fallback_role, $active_roles);
                echo "  Is $fallback_role active? " . ($is_fallback_active ? 'YES' : 'NO') . "\n";
                if ($is_fallback_active) {
                    echo "  ✓ FOUND via fallback: $fallback_role\n";
                    return $fallback_role;
                }
                echo "  Mark visited[$fallback_role]=true\n";
                $visited[$fallback_role] = true;
            } else {
                echo "  $fallback_role already visited, skip\n";
            }
        }

        // Advance to next role in chain
        $pos = array_search($role, $chain, true);
        echo "Position of $role in chain: " . ($pos !== false ? $pos : 'NOT FOUND') . "\n";
        if ($pos !== false && $pos + 1 < count($chain)) {
            $next_role = $chain[$pos + 1];
            echo "Next in chain: $next_role\n";
            $role = $next_role;
        } else {
            echo "No next in chain, stopping\n";
            $role = null;
        }
        echo "\n";
    }

    echo "LOOP EXITED\n";
    echo "Final role: " . ($role ?? 'NULL') . "\n";
    echo "Loop condition: role !== null && !isset(visited[$role])\n";
    echo "  role !== null: " . ($role !== null ? 'true' : 'false') . "\n";
    echo "  !isset(visited[\$role]): " . (!isset($visited[$role]) ? 'true' : 'false') . "\n";
    return null;
}

echo "=== TEST CASE 1e: All except direktur_utama cuti ===\n";
echo "Desired: kasubag_analis\n";
echo "Active: [direktur_utama]\n\n";

$result = simulate_resolve_debug('kasubag_analis', ['direktur_utama']);
echo "\n✗ Result: " . ($result ?? 'NULL') . " (expected: direktur_utama)\n";
?>
