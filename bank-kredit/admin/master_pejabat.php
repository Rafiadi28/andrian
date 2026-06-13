<?php
/**
 * Master Pejabat Management Interface
 * Admin page to manage officer signatures and stamps
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../helpers/functions.php';

// Check authorization
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'direksi', 'kadiv_kredit'])) {
    header('Location: ../login_page.html');
    exit;
}

// Get all pejabat
$stmt = $pdo->prepare("
    SELECT id_pejabat, role, nama, jabatan, tanda_tangan, stempel, status, updated_at
    FROM master_pejabat
    ORDER BY FIELD(role, 'analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'), created_at ASC
");
$stmt->execute();
$pejabat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$role_labels = [
    'analis' => 'Analis Kredit',
    'kasubag_analis' => 'Kepala Subbagian Analis',
    'kabag_kredit' => 'Kepala Bagian Kredit',
    'kadiv_bisnis' => 'Kepala Divisi Bisnis',
    'direktur_utama' => 'Direktur Utama'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Pejabat - Manajemen Tanda Tangan & Stempel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 24px;
            color: #1f2937;
        }

        .btn-add {
            padding: 10px 20px;
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .btn-add:hover {
            background-color: #059669;
        }

        .table-responsive {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f3f4f6;
            border-bottom: 2px solid #e5e7eb;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        tbody tr:hover {
            background-color: #f9fafb;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #e0f2fe;
            color: #0369a1;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.aktif {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-badge.nonaktif {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .file-preview {
            width: 40px;
            height: 40px;
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }

        .file-preview.has-file {
            background-color: #d1fae5;
            color: #065f46;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-edit {
            background-color: #3b82f6;
            color: white;
        }

        .btn-edit:hover {
            background-color: #2563eb;
        }

        .btn-delete {
            background-color: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background-color: #dc2626;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h2 {
            font-size: 20px;
            color: #1f2937;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        input[type="text"],
        input[type="file"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus,
        input[type="file"]:focus,
        select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        input[type="file"] {
            padding: 8px;
        }

        .file-info {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        .preview-image {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-submit {
            flex: 1;
            padding: 12px;
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .btn-submit:hover {
            background-color: #059669;
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            background-color: #e5e7eb;
            color: #374151;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .btn-cancel:hover {
            background-color: #d1d5db;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert.success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert.error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 Master Pejabat</h1>
                <p style="color: #6b7280; font-size: 14px; margin-top: 5px;">Manajemen tanda tangan dan stempel pejabat bank</p>
            </div>
            <button class="btn-add" onclick="openFormModal()">+ Tambah Pejabat</button>
        </div>

        <?php if (empty($pejabat_list)): ?>
        <div class="table-responsive">
            <div class="empty-state">
                <p>Belum ada data pejabat</p>
                <p style="font-size: 12px; margin-top: 10px;">Klik tombol "Tambah Pejabat" untuk memulai</p>
            </div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Nama Pejabat</th>
                        <th>Jabatan</th>
                        <th style="text-align: center;">Tanda Tangan</th>
                        <th style="text-align: center;">Stempel</th>
                        <th>Status</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pejabat_list as $p): ?>
                    <tr>
                        <td>
                            <span class="role-badge"><?= htmlspecialchars($role_labels[$p['role']] ?? $p['role']) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($p['nama']) ?></strong>
                        </td>
                        <td>
                            <?= htmlspecialchars($p['jabatan']) ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!empty($p['tanda_tangan'])): ?>
                            <div class="file-preview has-file" onclick="previewImage('<?= htmlspecialchars('assets/uploads/' . $p['tanda_tangan']) ?>', '<?= htmlspecialchars($p['nama']) ?> - Tanda Tangan')">
                                ✓
                            </div>
                            <?php else: ?>
                            <span style="color: #9ca3af;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!empty($p['stempel'])): ?>
                            <div class="file-preview has-file" onclick="previewImage('<?= htmlspecialchars('assets/uploads/' . $p['stempel']) ?>', '<?= htmlspecialchars($p['nama']) ?> - Stempel')">
                                ✓
                            </div>
                            <?php else: ?>
                            <span style="color: #9ca3af;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $p['status'] ?>">
                                <?= $p['status'] === 'aktif' ? '✓ Aktif' : '✗ Nonaktif' ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <div class="actions">
                                <button class="btn-action btn-edit" onclick="editPejabat(<?= intval($p['id_pejabat']) ?>)">Edit</button>
                                <button class="btn-action btn-delete" onclick="deletePejabat(<?= intval($p['id_pejabat']) ?>, '<?= htmlspecialchars($p['nama']) ?>')">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Modal -->
    <div id="formModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Tambah Pejabat Baru</h2>
                <span class="close" onclick="closeFormModal()">&times;</span>
            </div>
            <div id="alertContainer"></div>
            <form id="pejabatForm" onsubmit="handleFormSubmit(event)">
                <input type="hidden" id="id_pejabat" name="id_pejabat" value="">

                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" name="role" required>
                        <option value="">- Pilih Role -</option>
                        <option value="analis">Analis Kredit</option>
                        <option value="kasubag_analis">Kepala Subbagian Analis</option>
                        <option value="kabag_kredit">Kepala Bagian Kredit</option>
                        <option value="kadiv_bisnis">Kepala Divisi Bisnis</option>
                        <option value="direktur_utama">Direktur Utama</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nama">Nama Pejabat *</label>
                    <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="form-group">
                    <label for="jabatan">Jabatan Resmi *</label>
                    <input type="text" id="jabatan" name="jabatan" placeholder="Misalnya: Kepala Subbagian Analis" required>
                </div>

                <div class="form-group">
                    <label for="tanda_tangan">Tanda Tangan (JPG/PNG, max 5MB)</label>
                    <input type="file" id="tanda_tangan" name="tanda_tangan" accept="image/*">
                    <div class="file-info">Unggah gambar tanda tangan pejabat</div>
                    <img id="preview_tanda_tangan" class="preview-image" style="display:none;">
                </div>

                <div class="form-group">
                    <label for="stempel">Stempel/Cap (JPG/PNG, max 5MB)</label>
                    <input type="file" id="stempel" name="stempel" accept="image/*">
                    <div class="file-info">Unggah gambar stempel atau cap jabatan</div>
                    <img id="preview_stempel" class="preview-image" style="display:none;">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="aktif">✓ Aktif</option>
                        <option value="nonaktif">✗ Nonaktif</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="submitBtn">Simpan Pejabat</button>
                    <button type="button" class="btn-cancel" onclick="closeFormModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="previewModal" class="modal" onclick="this.style.display='none'">
        <div style="position: relative; display: flex; align-items: center; justify-content: center; height: 100vh;">
            <span class="close" style="position: absolute; top: 20px; right: 30px;" onclick="document.getElementById('previewModal').style.display='none'">&times;</span>
            <img id="previewImage" style="max-height: 80vh; max-width: 90%; border-radius: 8px;" alt="Preview">
        </div>
    </div>

    <script>
        const modal = document.getElementById('formModal');
        const form = document.getElementById('pejabatForm');

        function openFormModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Pejabat Baru';
            document.getElementById('id_pejabat').value = '';
            form.reset();
            document.getElementById('alertContainer').innerHTML = '';
            document.getElementById('role').disabled = false;
            document.getElementById('submitBtn').textContent = 'Tambah Pejabat';
            document.getElementById('preview_tanda_tangan').style.display = 'none';
            document.getElementById('preview_stempel').style.display = 'none';
            modal.style.display = 'block';
        }

        function closeFormModal() {
            modal.style.display = 'none';
        }

        function editPejabat(id) {
            fetch(`../api/master_pejabat.php?action=detail&id=${id}`)
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        const data = result.data;
                        document.getElementById('modalTitle').textContent = 'Edit Pejabat';
                        document.getElementById('id_pejabat').value = data.id_pejabat;
                        document.getElementById('role').value = data.role;
                        document.getElementById('role').disabled = true;
                        document.getElementById('nama').value = data.nama;
                        document.getElementById('jabatan').value = data.jabatan;
                        document.getElementById('status').value = data.status;
                        document.getElementById('submitBtn').textContent = 'Perbarui Pejabat';
                        document.getElementById('alertContainer').innerHTML = '';
                        modal.style.display = 'block';
                    }
                })
                .catch(e => console.error(e));
        }

        function deletePejabat(id, nama) {
            if (confirm(`Hapus pejabat "${nama}"? Data ini tidak dapat dikembalikan.`)) {
                fetch(`../api/master_pejabat.php?action=delete&id=${id}`, { method: 'DELETE' })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            alert('Pejabat berhasil dihapus');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    })
                    .catch(e => alert('Terjadi kesalahan: ' + e.message));
            }
        }

        function handleFormSubmit(event) {
            event.preventDefault();
            const id = document.getElementById('id_pejabat').value;
            const formData = new FormData(form);
            const action = id ? 'update' : 'create';

            fetch(`../api/master_pejabat.php?action=${action}`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(result => {
                const alertDiv = document.getElementById('alertContainer');
                if (result.success) {
                    alertDiv.innerHTML = `<div class="alert success">✓ ${result.message}</div>`;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alertDiv.innerHTML = `<div class="alert error">✗ ${result.message}</div>`;
                }
            })
            .catch(e => {
                document.getElementById('alertContainer').innerHTML = `<div class="alert error">✗ Terjadi kesalahan: ${e.message}</div>`;
            });
        }

        function previewImage(src, title) {
            document.getElementById('previewImage').src = src;
            document.getElementById('previewImage').alt = title;
            document.getElementById('previewModal').style.display = 'block';
        }

        // File preview
        document.getElementById('tanda_tangan').addEventListener('change', (e) => {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const img = document.getElementById('preview_tanda_tangan');
                    img.src = event.target.result;
                    img.style.display = 'block';
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        document.getElementById('stempel').addEventListener('change', (e) => {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const img = document.getElementById('preview_stempel');
                    img.src = event.target.result;
                    img.style.display = 'block';
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        // Close modal on outside click
        window.onclick = (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
    </script>
</body>
</html>
