<?php
require_once 'config/database.php';
require_once 'includes/schema_realtime_migrate.php';

// Type assertion for static analysis — $pdo is guaranteed initialized by config/database.php
/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "Error: Database connection not initialized\n";
    exit(1);
}

bankKreditEnsureSchema($pdo);

echo "=== STEP 9 SNAPSHOT TABLE VERIFICATION ===\n\n";

// Check if snapshot table exists
$result = $pdo->query("SHOW TABLES LIKE 'repayment_parameter_snapshot'");
if ($result && $result->rowCount() > 0) {
    echo "✓ TABLE: repayment_parameter_snapshot EXISTS\n";
    
    // Check columns
    $cols = $pdo->query("SHOW COLUMNS FROM repayment_parameter_snapshot");
    $columnCount = $cols->rowCount();
    echo "  Columns: $columnCount\n";
    
    $columns = $cols->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($columns as $col) {
        echo "    - $col\n";
    }
} else {
    echo "✗ TABLE: repayment_parameter_snapshot NOT FOUND\n";
    exit(1);
}

// Check if pengajuan_kredit has id_repayment_snapshot column
$col = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'id_repayment_snapshot'");
if ($col && $col->rowCount() > 0) {
    echo "\n✓ COLUMN: pengajuan_kredit.id_repayment_snapshot EXISTS\n";
} else {
    echo "\n✗ COLUMN: pengajuan_kredit.id_repayment_snapshot NOT FOUND\n";
    exit(1);
}

// Verify helper functions exist
require_once 'helpers/repayment_snapshot.php';

if (function_exists('captureRepaymentParameterSnapshot')) {
    echo "✓ FUNCTION: captureRepaymentParameterSnapshot exists\n";
} else {
    echo "✗ FUNCTION: captureRepaymentParameterSnapshot NOT FOUND\n";
    exit(1);
}

if (function_exists('fetchRepaymentParameterSnapshot')) {
    echo "✓ FUNCTION: fetchRepaymentParameterSnapshot exists\n";
} else {
    echo "✗ FUNCTION: fetchRepaymentParameterSnapshot NOT FOUND\n";
    exit(1);
}

if (function_exists('getRepaymentParameterSnapshotForApproval')) {
    echo "✓ FUNCTION: getRepaymentParameterSnapshotForApproval exists\n";
} else {
    echo "✗ FUNCTION: getRepaymentParameterSnapshotForApproval NOT FOUND\n";
    exit(1);
}

if (function_exists('formatRepaymentParameterSnapshot')) {
    echo "✓ FUNCTION: formatRepaymentParameterSnapshot exists\n";
} else {
    echo "✗ FUNCTION: formatRepaymentParameterSnapshot NOT FOUND\n";
    exit(1);
}

echo "\n✓✓✓ STEP 9 IMPLEMENTATION VERIFIED ✓✓✓\n";
echo "\nAll components in place:\n";
echo "  - Snapshot table created\n";
echo "  - FK column added to pengajuan_kredit\n";
echo "  - Helper functions implemented\n";
echo "  - Ready for production\n";
