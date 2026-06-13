# 📸 DOKUMENTASI FITUR UPLOAD FOTO AGUNAN MULTIPLE

## 1. Overview Fitur

Fitur **Multiple Photo Upload untuk Agunan** memungkinkan analyst/admin untuk mengupload hingga 10 foto agunan per pengajuan kredit. Setiap foto disimpan dalam tabel relasional `agunan_foto` dengan metadata lengkap (tanggal, ukuran, tipe file, keterangan).

### Requirement Spesifikasi:
- ✅ **Maximum Photos**: 10 foto per pengajuan kredit
- ✅ **Supported Formats**: JPG, JPEG, PNG
- ✅ **Max Size**: 5 MB per file
- ✅ **Relational Storage**: Tabel `agunan_foto` (bukan comma-separated values)
- ✅ **Features**: Preview grid, delete functionality, lightbox viewer
- ✅ **File Naming**: `uniqid('agunan_foto_')` untuk unik naming tanpa collision
- ✅ **Storage Location**: `assets/uploads/` dengan proper directory permissions

---

## 2. Database Schema

### Tabel: `agunan_foto`
```sql
CREATE TABLE agunan_foto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_jaminan INT NULL COMMENT 'FK: jaminan_tanah_bangunan.id_jaminan / jaminan_kendaraan.id_jaminan',
    id_pengajuan INT NOT NULL COMMENT 'FK: pengajuan_kredit.id_pengajuan',
    tipe_jaminan VARCHAR(50) NULL COMMENT 'tanah_bangunan / kendaraan / emas',
    nama_file VARCHAR(255) NOT NULL COMMENT 'Filename stored in assets/uploads/',
    ukuran INT NOT NULL COMMENT 'File size in bytes',
    tipe_file VARCHAR(50) COMMENT 'MIME type: image/jpeg, image/png',
    keterangan VARCHAR(500) COMMENT 'Optional description: Foto depan, Foto BPKB, dll',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_id_jaminan (id_jaminan),
    KEY idx_id_pengajuan (id_pengajuan),
    KEY idx_tipe_jaminan (tipe_jaminan),
    
    CONSTRAINT fk_agunan_foto_pengajuan 
        FOREIGN KEY (id_pengajuan) 
        REFERENCES pengajuan_kredit(id_pengajuan) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Indeks untuk Performa:
- `idx_id_pengajuan`: Mempercepat query filter by pengajuan
- `idx_id_jaminan`: Mempercepat query join dengan jaminan records
- `idx_tipe_jaminan`: Support filtering by collateral type

---

## 3. File Modifications

### 3.1 Database Schema - `includes/schema_realtime_migrate.php`
**Lines 350-370**: Idempotent CREATE TABLE agunan_foto

```php
// Check if agunan_foto table exists, create if not
if (!tableExists('agunan_foto')) {
    $pdo->exec("
        CREATE TABLE agunan_foto (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_jaminan INT NULL,
            id_pengajuan INT NOT NULL,
            tipe_jaminan VARCHAR(50) NULL,
            nama_file VARCHAR(255) NOT NULL,
            ukuran INT NOT NULL,
            tipe_file VARCHAR(50),
            keterangan VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_id_jaminan (id_jaminan),
            KEY idx_id_pengajuan (id_pengajuan),
            KEY idx_tipe_jaminan (tipe_jaminan),
            CONSTRAINT fk_agunan_foto_pengajuan 
                FOREIGN KEY (id_pengajuan) 
                REFERENCES pengajuan_kredit(id_pengajuan) 
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
```

### 3.2 Frontend Form - `analis/form_umum.php`
**Lines 1756-1900**: Multiple file upload UI dengan preview

```html
<div class="form-group" style="grid-column: 1 / -1;">
    <label for="agunan_foto"><strong>📸 Foto Agunan (Multiple - Max 10)</strong></label>
    
    <!-- File Input -->
    <input type="file" id="agunan_foto" name="agunan_foto" multiple 
           accept="image/jpeg,image/png"
           style="display: block; width: 100%; padding: 0.5rem;">
    <small style="color: #6b7280;">JPG/PNG | Max 5 MB per file | Max 10 foto total</small>
    
    <!-- Drag-Drop Area -->
    <div id="agunan_foto_dropzone" style="border: 2px dashed #cbd5e1; padding: 2rem; 
         text-align: center; border-radius: 8px; margin: 1rem 0; background: #f8fafc;">
        <p style="margin: 0;">Atau drag-drop file ke area ini</p>
    </div>
    
    <!-- Progress Bar -->
    <div style="margin-top: 1rem;">
        <div id="agunan_foto_progress" style="width: 0%; height: 8px; background: #0369a1; 
             border-radius: 4px; transition: width 0.3s;"></div>
    </div>
    
    <!-- Counter -->
    <div style="text-align: right; margin-top: 0.5rem; font-size: 0.85rem;">
        <span id="agunan_foto_counter">0/10 foto</span>
    </div>
    
    <!-- Preview Grid -->
    <div id="agunan_foto_preview" style="display: grid; grid-template-columns: repeat(auto-fill, 
         minmax(120px, 1fr)); gap: 1rem; margin-top: 1rem;"></div>
</div>
```

**JavaScript Functions:**
- `handleAgunanFotoSelect(event)` - Validate count, size, format; add to agunanFotoList
- `removeAgunanFoto(index)` - Remove file from list and update preview
- `updateAgunanFotoPreview()` - Render grid with FileReader previews
- Modified `saveSection()` - Use FormData for file upload to save_section.php

### 3.3 Server Upload Handler - `analis/save_section.php`
**Lines 1690-1810**: Case 'add_agunan_foto'

```php
case 'add_agunan_foto':
    // Validate pengajuan status (draft, revisi, ditolak, diajukan_ulang)
    // Validate file: extension (jpg/jpeg/png), size (5MB max), MIME type
    // Check max photo count (10)
    // Move file with uniqid() prefix
    // Insert record into agunan_foto table
    // Return JSON response
```

**Validasi Lengkap:**
- Extension: jpg, jpeg, png (case-insensitive)
- MIME type: image/jpeg, image/png (via finfo_file())
- File size: max 5 * 1024 * 1024 bytes (5MB)
- Max count: 10 total per pengajuan
- Status check: Hanya status draft/revisi/ditolak/diajukan_ulang yang bisa edit

**Lines 1810-1860**: Case 'delete_agunan_foto'

```php
case 'delete_agunan_foto':
    // Validate pengajuan status (editable statuses only)
    // Fetch foto record (verify ownership by id_pengajuan)
    // Delete file from disk (assets/uploads/)
    // Delete record from agunan_foto table
    // Return JSON response
```

### 3.4 Detail View - `detail.php`
**Lines 50-65**: Fetch agunan_foto data

```php
// Fetch Multiple Agunan Foto dengan JOIN untuk deskripsi agunan
$stmt = $pdo->prepare("
    SELECT af.*, 
           CASE 
               WHEN af.tipe_jaminan='tanah_bangunan' 
                   THEN (SELECT alamat_agunan FROM jaminan_tanah_bangunan 
                         WHERE id_jaminan=af.id_jaminan LIMIT 1)
               WHEN af.tipe_jaminan='kendaraan' 
                   THEN (SELECT CONCAT(merk,' ',tipe) FROM jaminan_kendaraan 
                         WHERE id_jaminan=af.id_jaminan LIMIT 1)
               ELSE ''
           END as agunan_desc
    FROM agunan_foto af
    WHERE af.id_pengajuan=?
    ORDER BY af.created_at DESC
");
```

**Lines 730-800**: Photo Gallery UI

```html
<div class="card" style="padding: 1rem; grid-column: 1 / -1;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <strong>📸 Foto Agunan (Multiple)</strong>
        <?php if ($can_edit): ?>
        <small style="color:#0369A1; cursor:pointer;" onclick="document.getElementById('add_agunan_foto_form').style.display = ...">
            + Tambah Foto
        </small>
        <?php endif; ?>
    </div>
    
    <!-- Add Form (Hidden) -->
    <?php if ($can_edit): ?>
    <div id="add_agunan_foto_form" style="display:none; background:#f0fdf4; ...">
        <!-- Form dengan file input, keterangan input, submit button -->
    </div>
    <?php endif; ?>
    
    <!-- Photo Grid -->
    <?php if (!empty($agunan_foto_all)): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:1rem;">
        <!-- Foreach loop render thumbnail cards dengan overlay buttons -->
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:2rem; color:#94a3b8;">
        <p>📁 Belum ada foto agunan yang diupload</p>
    </div>
    <?php endif; ?>
</div>
```

**Lines 1045-1120**: Lightbox Modal & JavaScript

```javascript
// Lightbox modal dengan close button dan caption
// Event listeners: click outside, Escape key, image click
// Delete handler: AJAX ke save_section.php dengan action=delete_agunan_foto
// Add form handler: AJAX upload dengan FormData
```

### 3.5 Print View - `print.php`
**Lines 85-92**: Fetch agunan_foto data

```php
$stmt = $pdo->prepare("SELECT * FROM agunan_foto WHERE id_pengajuan = ? 
                       ORDER BY created_at DESC");
$stmt->execute([$id]);
$agunan_foto_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

**Lines 1345-1370**: Photo Grid Display (PDF optimized)

```html
<!-- Grid 4 kolom, limit 8 foto, indicate sisa foto -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px;">
    <?php foreach (array_slice($agunan_foto_all, 0, 8) as $foto): ?>
    <div style="border: 1px solid #e5e7eb; overflow: hidden; aspect-ratio: 1;">
        <img src="<?= __DIR__ . '/assets/uploads/' . $foto['nama_file'] ?>" 
             style="width: 100%; height: 100%; object-fit: cover;" />
    </div>
    <?php endforeach; ?>
</div>
```

---

## 4. Validation Rules

### Client-Side (form_umum.php & detail.php)
- ✅ File count: max 10 sebelum submit
- ✅ File size: max 5 MB per file
- ✅ File extension: .jpg, .jpeg, .png only
- ✅ MIME type: image/jpeg, image/png (via FileReader.type)
- ✅ Real-time feedback via toast/alert messages

### Server-Side (save_section.php)
- ✅ CSRF token validation
- ✅ Authentication check (analyst role required)
- ✅ Pengajuan status validation (editable only: draft, revisi, ditolak, diajukan_ulang)
- ✅ File count check (tidak boleh > 10)
- ✅ Extension whitelist: jpg, jpeg, png
- ✅ MIME type validation via finfo_file() (double check)
- ✅ File size validation (5 MB max)
- ✅ Directory permissions check & auto-create if needed

---

## 5. File Storage & Naming

### Directory Structure:
```
/assets/uploads/
    ├── agunan_foto_507a6f23a1.jpg
    ├── agunan_foto_507a6f23c5.png
    ├── agunan_foto_507a6f240f.jpg
    └── ... (other files)
```

### Filename Convention:
- **Format**: `uniqid('agunan_foto_') . '.' . $extension`
- **Example**: `agunan_foto_507a6f23a1.jpg`
- **Collision Prevention**: `uniqid()` generates unique 13-char prefix based on time
- **Directory Permissions**: `chmod 0755` untuk uploads directory

---

## 6. Testing Checklist

### Setup:
- [ ] Database migration runs without errors (agunan_foto table created)
- [ ] assets/uploads/ directory exists with 755 permissions
- [ ] Test user has analyst role

### Form Upload:
- [ ] Form displays file input, drag-drop area, preview grid
- [ ] Upload JPG/PNG files - success
- [ ] Upload unsupported format (BMP, GIF) - show error
- [ ] Upload file > 5 MB - show error
- [ ] Add 10 photos - counter shows 10/10
- [ ] Try add 11th photo - show "Sudah mencapai batas maksimal" error
- [ ] Preview grid updates in real-time as files are selected
- [ ] Delete button removes preview immediately
- [ ] Submit form with photos - files move to assets/uploads/, records insert into agunan_foto

### Detail View:
- [ ] Photo gallery displays all uploaded photos in grid
- [ ] Hover over thumbnail - View & Delete buttons appear
- [ ] Click View - lightbox opens with full-size image
- [ ] Click thumbnail - same as View button
- [ ] Close lightbox - Escape key works, click outside works, close button works
- [ ] Click Delete - confirmation dialog appears
- [ ] Confirm delete - photo removed from grid, file deleted from disk
- [ ] Add new photo form toggle - hidden/show on "Tambah Foto" click
- [ ] Upload from add form - photo appears in gallery without page reload

### Print View:
- [ ] Generate PDF - agunan_foto section displays (if photos exist)
- [ ] Grid shows max 8 photos (4 columns)
- [ ] Text "... dan X foto lainnya" displays for photos > 8
- [ ] PDF renders without errors

### Permission & Status:
- [ ] Only analyst (input_by user) can see "Tambah Foto" & delete buttons
- [ ] Non-analyst (supervisor, cc) see read-only gallery
- [ ] Pengajuan status != draft/revisi - "Tambah Foto" button hidden
- [ ] Delete attempt on submitted pengajuan - "status tidak bisa diedit" error

### Edge Cases:
- [ ] Upload same file twice - both stored with different uniqid names
- [ ] Rapidly click upload 10 times - only 10 saved, 11th rejected
- [ ] Delete all photos - empty state shows "Belum ada foto agunan"
- [ ] Pengajuan with NULL id_jaminan - agunan_foto still records with null id_jaminan
- [ ] Network interruption during upload - error message shown, form can retry

---

## 7. Security Considerations

### Input Validation:
- ✅ CSRF token required on all requests
- ✅ Role-based access control (analyst only)
- ✅ Pengajuan ownership validation (user must be input_by)
- ✅ Status-based permission (draft/revisi/ditolak/diajukan_ulang only)

### File Security:
- ✅ MIME type double-check (extension + finfo_file())
- ✅ Filename randomization (uniqid prefix prevents guessing)
- ✅ Size limit enforced (5 MB max)
- ✅ Stored outside webroot considerations (currently in assets/uploads - HTTP accessible for display)

### SQL Injection Prevention:
- ✅ PDO prepared statements for all queries
- ✅ Parameter binding (no string concatenation)
- ✅ Foreign key constraints on database level

---

## 8. Performance Notes

### Optimization:
- Grid display limited to 8 photos in print (for PDF size)
- Lazy loading NOT implemented (images display on load)
- Indexes on id_pengajuan, id_jaminan for query optimization

### Future Improvements:
- Image compression on upload (ImageMagick/GD)
- Lazy loading in detail view (intersection observer)
- Thumbnail generation for preview (reduce main image size)
- CDN integration for assets/uploads/
- Archive old photos based on retention policy

---

## 9. Integration Notes

### Related Features:
- **Manual Valuation**: Separate feature in jaminan_tanah_bangunan/jaminan_kendaraan
- **Photo History**: Not tracked (current implementation shows all photos, no versioning)
- **Notification**: No automatic notification on photo upload

### Backward Compatibility:
- Existing single photos (foto_rumah, foto_usaha) remain untouched
- New agunan_foto table independent of legacy columns
- No migration required for existing data

---

## 10. Troubleshooting

### Issue: Files uploaded but not visible
- Check assets/uploads/ directory permissions (should be 755)
- Check file ownership (webserver user must have write permission)
- Check $_FILES empty - verify form enctype="multipart/form-data"

### Issue: "File size melebihi 5 MB" error on small files
- Check php.ini upload_max_filesize (should be >= 5 MB)
- Check post_max_size (should be > upload_max_filesize)
- Check file server limit in server configuration

### Issue: Delete button not working
- Check browser console for JavaScript errors
- Verify save_section.php called with correct action='delete_agunan_foto'
- Verify user is analyst and pengajuan status is editable
- Check file permissions on assets/uploads/ (must be writable)

### Issue: PDF/Print not showing photos
- Verify agunan_foto records exist in database
- Check file paths in print.php (file:// URI must be absolute)
- Verify image files exist in assets/uploads/
- Check PDF library compatibility (TCPDF, mPDF, etc.)

---

## 11. Future Enhancements

### Planned Features:
- [ ] Image compression on upload (reduce file size)
- [ ] Thumbnail generation (faster preview loading)
- [ ] Photo annotations (draw/mark on image)
- [ ] Video support for inspection
- [ ] Signature verification integration
- [ ] Automatic photo organization by jaminan type
- [ ] Batch photo management (select multiple, bulk delete)
- [ ] Photo metadata extraction (EXIF for location, timestamp)

---

**Last Updated**: 2025-01-24  
**Version**: 1.0  
**Status**: ✅ Production Ready
