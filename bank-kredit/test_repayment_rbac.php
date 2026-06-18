<?php
session_start();
require_once __DIR__ . '/helpers/repayment_rbac.php';

function test_scenario($role, $row, $expected_can_view_only, $expected_can_edit, $expected_can_submit, $expected_can_approve_kadiv, $expected_can_approve_final) {
    $_SESSION['role'] = $role;
    
    $view_only = isRepaymentParameterViewOnly();
    $can_edit = canEditRepaymentDraft($row);
    $can_submit = canSubmitRepaymentProposal($row);
    $can_approve_kadiv = canApproveRepaymentKadiv($row);
    $can_approve_final = canApproveRepaymentFinal($row);
    
    $passed = true;
    if ($view_only !== $expected_can_view_only) { $passed = false; echo "  [FAIL] View Only: expected " . ($expected_can_view_only ? 'true' : 'false') . ", got " . ($view_only ? 'true' : 'false') . "\n"; }
    if ($can_edit !== $expected_can_edit) { $passed = false; echo "  [FAIL] Edit Draft: expected " . ($expected_can_edit ? 'true' : 'false') . ", got " . ($can_edit ? 'true' : 'false') . "\n"; }
    if ($can_submit !== $expected_can_submit) { $passed = false; echo "  [FAIL] Submit: expected " . ($expected_can_submit ? 'true' : 'false') . ", got " . ($can_submit ? 'true' : 'false') . "\n"; }
    if ($can_approve_kadiv !== $expected_can_approve_kadiv) { $passed = false; echo "  [FAIL] Approve Kadiv: expected " . ($expected_can_approve_kadiv ? 'true' : 'false') . ", got " . ($can_approve_kadiv ? 'true' : 'false') . "\n"; }
    if ($can_approve_final !== $expected_can_approve_final) { $passed = false; echo "  [FAIL] Approve Final: expected " . ($expected_can_approve_final ? 'true' : 'false') . ", got " . ($can_approve_final ? 'true' : 'false') . "\n"; }
    
    if ($passed) {
        echo "[PASS] Role: $role (Status: {$row['status_approval']})\n";
    } else {
        echo "[FAIL] Role: $role (Status: {$row['status_approval']})\n";
    }
}

echo "=== PENGUJIAN HAK AKSES REPAYMENT ===\n";

$draft_row = ['status_approval' => 'draft'];
$menunggu_row = ['status_approval' => 'menunggu'];
$kadiv_row = ['status_approval' => 'disetujui_kadiv'];

// Analis -> Tidak dapat mengubah parameter.
test_scenario('analis', $draft_row, true, false, false, false, false);

// Admin Kredit (kasubag/kabag analis) -> Tidak dapat mengubah parameter.
test_scenario('kasubag_analis', $draft_row, true, false, false, false, false);
test_scenario('kabag_analis', $draft_row, true, false, false, false, false);

// Kabag -> Dapat membuat usulan (draft).
test_scenario('kabag_kredit', $draft_row, false, true, true, false, false);

// Kadiv -> Dapat mereview dan menyetujui tahap pertama (menunggu).
test_scenario('kadiv_kredit', $menunggu_row, false, false, false, true, false);

// Direksi -> Dapat melakukan approval final (disetujui_kadiv).
test_scenario('direksi', $kadiv_row, false, false, false, false, true);

// Direksi -> Dapat melakukan override (draft atau menunggu).
test_scenario('direksi', $draft_row, false, false, false, false, true);
test_scenario('direksi', $menunggu_row, false, false, false, false, true);

// IT (Superadmin) -> Tidak dapat mengubah kebijakan kredit.
test_scenario('Superadmin', $draft_row, true, false, false, false, false);

echo "=====================================\n";
