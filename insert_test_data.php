<?php
/**
 * TEST DATA SETUP - Load this once to populate test database
 * Jalankan: php insert_test_data.php
 * Atau: Copy-paste queries di phpMyAdmin
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = [];

try {
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
            $success[] = "✓ User created: {$user['nama']} ({$user['username']})";
        } else {
            $success[] = "⚠ User already exists: {$user['username']}";
        }
    }

    // ========== TEST DEBITUR & PENGAJUAN ==========
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
            'jumlah_kredit' => 250000000, // 250 juta
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
            'jumlah_kredit' => 600000000, // 600 juta (diatas threshold)
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
            $success[] = "✓ Pengajuan created: #{$id} - {$pengajuan['nama_debitur']} (Rp " . number_format($pengajuan['jumlah_kredit']) . ")";
        }
    }

    // ========== TEST SCORING 5C ==========
    echo "📋 Inserting test 5C analysis...\n";
    
    foreach ($test_pengajuan_ids as $id_pengajuan) {
        $stmt = $pdo->prepare("
            INSERT INTO analisa_5c (
                id_pengajuan, character_score, capacity_score, capital_score, 
                collateral_score, condition_score, constraint_score, total_score, rekomendasi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Skala 1-5 (6C); total_score = rata-rata (sesuai save_section.php)
        $scores = [5, 5, 4, 5, 4, 5];
        $rata = round(array_sum($scores) / count($scores), 2);
        
        $result = $stmt->execute([$id_pengajuan, $scores[0], $scores[1], $scores[2], $scores[3], $scores[4], $scores[5], $rata, 'DISETUJUI']);
        
        if ($result) {
            $success[] = "✓ 5C scoring created: ID #{$id_pengajuan} (Rata-rata: {$rata})";
        }
    }

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
            $success[] = "✓ Neraca created: ID #{$id_pengajuan}";
        }
    }

    // ========== TEST AGUNAN ==========
    echo "📋 Inserting test agunan (collateral) data...\n";
    
    foreach ($test_pengajuan_ids as $id_pengajuan) {
        // Jaminan Tanah (kolom sesuai skema jaminan_tanah_bangunan)
        $stmt = $pdo->prepare("
            INSERT INTO jaminan_tanah_bangunan (
                id_pengajuan, alamat_agunan, jenis_surat, luas_tanah, luas_bangunan,
                nilai_taksasi, nilai_pasar, kategori_agunan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id_pengajuan, 'Jl. Test Property', 'SHM', 500, 300,
            500000000, 600000000, 'rumah_tinggal'
        ]);
        
        $success[] = "✓ Jaminan Tanah created: ID #{$id_pengajuan}";
        
        // Jaminan Kendaraan (kolom sesuai skema jaminan_kendaraan)
        $stmt = $pdo->prepare("
            INSERT INTO jaminan_kendaraan (
                id_pengajuan, merk, tipe, tahun_pembuatan, no_polisi,
                nilai_taksasi, nilai_pasar
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id_pengajuan, 'Toyota', 'Avanza', 2020, 'B 1234 ABC',
            150000000, 180000000
        ]);
        
        $success[] = "✓ Jaminan Kendaraan created: ID #{$id_pengajuan}";
    }

    // ========== TEST MASTER PEJABAT ==========
    echo "📋 Inserting test master pejabat...\n";
    
    $test_pejabat = [
        ['role' => 'analis', 'nama' => 'Budi Santoso', 'jabatan' => 'Analis Kredit'],
        ['role' => 'kasubag_analis', 'nama' => 'Ahmad Wijaya', 'jabatan' => 'Kepala Subbagian Analis'],
        ['role' => 'kabag_kredit', 'nama' => 'Siti Nurhaliza', 'jabatan' => 'Kepala Bagian Kredit'],
        ['role' => 'kadiv_bisnis', 'nama' => 'Rudi Hermawan', 'jabatan' => 'Kepala Divisi Bisnis'],
        ['role' => 'direktur_utama', 'nama' => 'Bambang Suryanto', 'jabatan' => 'Direktur Utama'],
    ];

    foreach ($test_pejabat as $pj) {
        $stmt = $pdo->prepare("SELECT id_pejabat FROM master_pejabat WHERE role = ?");
        $stmt->execute([$pj['role']]);
        
        if ($stmt->rowCount() === 0) {
            $insert = $pdo->prepare("
                INSERT INTO master_pejabat (role, nama, jabatan, status) 
                VALUES (?, ?, ?, 'aktif')
            ");
            $insert->execute([$pj['role'], $pj['nama'], $pj['jabatan']]);
            $success[] = "✓ Master pejabat created: {$pj['nama']} ({$pj['role']})";
        } else {
            $update = $pdo->prepare("UPDATE master_pejabat SET nama = ?, jabatan = ? WHERE role = ?");
            $update->execute([$pj['nama'], $pj['jabatan'], $pj['role']]);
            $success[] = "✓ Master pejabat updated: {$pj['nama']} ({$pj['role']})";
        }
    }

    // ========== SUMMARY ==========
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✅ TEST DATA SETUP COMPLETE\n";
    echo str_repeat("=", 70) . "\n\n";

    echo "📊 SUMMARY:\n";
    foreach ($success as $msg) {
        echo $msg . "\n";
    }

    if (!empty($errors)) {
        echo "\n⚠️ ERRORS:\n";
        foreach ($errors as $err) {
            echo $err . "\n";
        }
    }

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🔑 TEST USER CREDENTIALS:\n";
    echo str_repeat("=", 70) . "\n";
    echo "Username: analis_test | Password: password123 | Role: Analis\n";
    echo "Username: kabag_test | Password: password123 | Role: Kabag Kredit\n";
    echo "Username: kadiv_test | Password: password123 | Role: Kadiv Bisnis\n";
    echo "Username: direktur_test | Password: password123 | Role: Direktur Utama\n";
    echo "Username: kepatuhan_test | Password: password123 | Role: Kepatuhan\n";
    echo str_repeat("=", 70) . "\n";

    if (!empty($test_pengajuan_ids)) {
        echo "\n📝 TEST PENGAJUAN IDS:\n";
        echo str_repeat("=", 70) . "\n";
        foreach ($test_pengajuan_ids as $idx => $id) {
            echo "Test Case " . ($idx + 1) . ": Pengajuan #" . $id . "\n";
        }
        echo str_repeat("=", 70) . "\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    die;
}
?>
