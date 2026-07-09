<?php
/**
 * SIMPLIFIED TEST DATA SETUP
 * Run this once to populate test database with minimal required data
 * php insert_test_data_simple.php
 */

require_once __DIR__ . '/config/database.php';

$success = [];
$errors = [];

try {
    echo "========================================\n";
    echo "📋 STARTING TEST DATA SETUP\n";
    echo "========================================\n\n";

    // ========== SCHEMA MIGRATION ==========
    echo "🔧 Running schema migrations...\n";
    require_once __DIR__ . '/includes/schema_realtime_migrate.php';
    bankKreditEnsureSchema($pdo);
    echo "✓ Schema ready\n\n";

    // ========== TEST USERS ==========
    echo "📋 Inserting test users...\n";
    
    $test_users = [
        ['nama' => 'Analis Test', 'username' => 'analis_test', 'password' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'analis'],
        ['nama' => 'Kabag Kredit Test', 'username' => 'kabag_test', 'password' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'kabag_kredit'],
        ['nama' => 'Kadiv Bisnis Test', 'username' => 'kadiv_test', 'password' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'kadiv_bisnis'],
        ['nama' => 'Direktur Utama Test', 'username' => 'direktur_test', 'password' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'direktur_utama'],
        ['nama' => 'Kepatuhan Test', 'username' => 'kepatuhan_test', 'password' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'kepatuhan'],
    ];

    foreach ($test_users as $user) {
        $stmt = $pdo->prepare("SELECT id_user FROM users WHERE username = ?");
        $stmt->execute([$user['username']]);
        if ($stmt->rowCount() === 0) {
            $insert = $pdo->prepare("INSERT INTO users (nama, username, password, role, status_jabatan) VALUES (?, ?, ?, ?, 'aktif')");
            $insert->execute([$user['nama'], $user['username'], $user['password'], $user['role']]);
            echo "  ✓ {$user['nama']} ({$user['username']})\n";
        } else {
            echo "  ⚠ Already exists: {$user['username']}\n";
        }
    }
    echo "\n";

    // ========== TEST PENGAJUAN ==========
    echo "📋 Inserting test pengajuan kredit...\n";
    
    $test_pengajuan = [
        [
            'nama_debitur' => 'PT TEST COMPANY 1',
            'nik' => '1234567890123456',
            'npwp' => '12.345.678.9-012.000',
            'pekerjaan' => 'Perdagangan',
            'status_perkawinan' => 'menikah',
            'nama_pasangan' => 'Ibu Test',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1980-05-15',
            'no_hp' => '081234567890',
            'alamat_ktp' => 'Jl. Test No. 1, Jakarta',
            'alamat_domisili' => 'Jl. Test No. 1, Jakarta',
            'desa' => 'TEST DESA',
            'kecamatan' => 'KEMAYORAN',
            'kota_kabupaten' => 'JAKARTA PUSAT',
            'jumlah_kredit' => 250000000,
            'jangka_waktu' => 24,
            'tujuan_kredit' => 'Modal Kerja',
            'omset_per_bulan' => 150000000,
            'total_pengeluaran_tetap' => 50000000,
            'biaya_hidup' => 10000000,
            'status_pengajuan' => 'diajukan',
            'jenis_pekerjaan' => 'umum'
        ],
        [
            'nama_debitur' => 'PT TEST COMPANY 2',
            'nik' => '1234567890123457',
            'npwp' => '12.345.678.9-012.001',
            'pekerjaan' => 'Retail',
            'status_perkawinan' => 'menikah',
            'nama_pasangan' => 'Ibu Test 2',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1985-03-20',
            'no_hp' => '081234567891',
            'alamat_ktp' => 'Jl. Test No. 2, Bandung',
            'alamat_domisili' => 'Jl. Test No. 2, Bandung',
            'desa' => 'TEST DESA 2',
            'kecamatan' => 'CIBEUNYING KALER',
            'kota_kabupaten' => 'BANDUNG',
            'jumlah_kredit' => 600000000,
            'jangka_waktu' => 36,
            'tujuan_kredit' => 'Modal Kerja',
            'omset_per_bulan' => 300000000,
            'total_pengeluaran_tetap' => 100000000,
            'biaya_hidup' => 20000000,
            'status_pengajuan' => 'diajukan',
            'jenis_pekerjaan' => 'umum'
        ]
    ];

    $test_pengajuan_ids = [];
    foreach ($test_pengajuan as $pengajuan) {
        $stmt = $pdo->prepare("
            INSERT INTO pengajuan_kredit (
                nama_debitur, nik, npwp, pekerjaan, status_perkawinan, nama_pasangan, 
                tempat_lahir, tanggal_lahir, no_hp, alamat_ktp, alamat_domisili, 
                desa, kecamatan, kota_kabupaten, jumlah_kredit, 
                jangka_waktu, tujuan_kredit, omset_per_bulan, total_pengeluaran_tetap, 
                biaya_hidup, status_pengajuan, jenis_pekerjaan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $pengajuan['nama_debitur'], $pengajuan['nik'], $pengajuan['npwp'],
            $pengajuan['pekerjaan'], $pengajuan['status_perkawinan'], $pengajuan['nama_pasangan'],
            $pengajuan['tempat_lahir'], $pengajuan['tanggal_lahir'], $pengajuan['no_hp'],
            $pengajuan['alamat_ktp'], $pengajuan['alamat_domisili'],
            $pengajuan['desa'], $pengajuan['kecamatan'], $pengajuan['kota_kabupaten'],
            $pengajuan['jumlah_kredit'], $pengajuan['jangka_waktu'], $pengajuan['tujuan_kredit'], 
            $pengajuan['omset_per_bulan'], $pengajuan['total_pengeluaran_tetap'], $pengajuan['biaya_hidup'],
            $pengajuan['status_pengajuan'], $pengajuan['jenis_pekerjaan']
        ]);

        if ($result) {
            $id = $pdo->lastInsertId();
            $test_pengajuan_ids[] = $id;
            $amount = number_format($pengajuan['jumlah_kredit'], 0, ',', '.');
            echo "  ✓ Pengajuan #{$id}: {$pengajuan['nama_debitur']} (Rp {$amount})\n";
        }
    }
    echo "\n";

    // ========== TEST 5C SCORING ==========
    echo "📋 Inserting test 5C analysis...\n";
    
    foreach ($test_pengajuan_ids as $id_pengajuan) {
        $stmt = $pdo->prepare("
            INSERT INTO analisa_5c (
                id_pengajuan, character_score, capacity_score, capital_score, 
                collateral_score, condition_score, total_score
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $scores = [90, 85, 80, 85, 80];
        $total = array_sum($scores);
        
        $result = $stmt->execute([$id_pengajuan, $scores[0], $scores[1], $scores[2], $scores[3], $scores[4], $total]);
        
        if ($result) {
            echo "  ✓ 5C scores for pengajuan #{$id_pengajuan} (Total: {$total})\n";
        }
    }
    echo "\n";

    // ========== TEST NERACA ==========
    echo "📋 Inserting test neraca data...\n";
    
    foreach ($test_pengajuan_ids as $id_pengajuan) {
        $stmt = $pdo->prepare("
            INSERT INTO analisa_neraca (
                id_pengajuan, aktiva_kas, aktiva_tabungan, aktiva_tanah, aktiva_kendaraan,
                aktiva_stok, aktiva_lainnya, pasiva_hutang_bank, pasiva_hutang_lain, 
                pasiva_modal, total_aktiva, total_pasiva
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $id_pengajuan,
            50000000, 0, 250000000, 0, 200000000, 25000000, 75000000, 50000000, 400000000, 525000000, 525000000
        ]);
        
        if ($result) {
            echo "  ✓ Neraca for pengajuan #{$id_pengajuan}\n";
        }
    }
    echo "\n";

    // ========== MASTER PEJABAT ==========
    echo "📋 Inserting master pejabat (officers)...\n";
    
    $master_pejabat = [
        ['role' => 'analis', 'nama' => 'Budi Santoso', 'jabatan' => 'Analis Kredit Senior'],
        ['role' => 'kasubag_analis', 'nama' => 'Ahmad Wijaya', 'jabatan' => 'Kasubag Analisis Kredit'],
        ['role' => 'kabag_kredit', 'nama' => 'Siti Nurhaliza', 'jabatan' => 'Kabag Kredit'],
        ['role' => 'kadiv_bisnis', 'nama' => 'Rudi Hermawan', 'jabatan' => 'Kadiv Bisnis'],
        ['role' => 'direktur_utama', 'nama' => 'Bambang Suryanto', 'jabatan' => 'Direktur Utama'],
    ];

    foreach ($master_pejabat as $pejabat) {
        $stmt = $pdo->prepare("SELECT id_pejabat FROM master_pejabat WHERE role = ?");
        $stmt->execute([$pejabat['role']]);
        if ($stmt->rowCount() === 0) {
            $insert = $pdo->prepare("
                INSERT INTO master_pejabat (role, nama, jabatan, status) 
                VALUES (?, ?, ?, 'aktif')
            ");
            $insert->execute([$pejabat['role'], $pejabat['nama'], $pejabat['jabatan']]);
            echo "  ✓ {$pejabat['nama']} ({$pejabat['role']})\n";
        } else {
            echo "  ⚠ Already exists: {$pejabat['role']}\n";
        }
    }
    echo "\n";

    // ========== SUMMARY ==========
    echo "========================================\n";
    echo "✅ TEST DATA SETUP COMPLETE!\n";
    echo "========================================\n\n";
    
    echo "🔑 TEST CREDENTIALS:\n";
    echo "  Analis:     analis_test / password123\n";
    echo "  Kabag:      kabag_test / password123\n";
    echo "  Kadiv:      kadiv_test / password123\n";
    echo "  Direktur:   direktur_test / password123\n";
    echo "  Kepatuhan:  kepatuhan_test / password123\n\n";
    
    echo "📌 TEST PENGAJUAN IDs:\n";
    echo "  Pengajuan #1: 250M (4-level approval)\n";
    echo "  Pengajuan #2: 600M (5-level approval with Direktur)\n\n";
    
    echo "✨ Ready for testing!\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
