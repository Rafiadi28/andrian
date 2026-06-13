# 📐 PANDUAN IMPLEMENTASI - MODERN FORM STYLING

**Tanggal:** 30 April 2026  
**Versi:** 1.0  
**Status:** Production Ready ✅

---

## 📋 OVERVIEW

File `form-modern-style.css` menyediakan **sistem styling modern dan profesional** untuk Form PPPK dan Perangkat Desa dengan fitur:

✅ Layout responsif (mobile/tablet/desktop)  
✅ Container dengan max-width & centered  
✅ Semantic HTML structure  
✅ Professional banking color theme  
✅ Consistent spacing & typography  
✅ Accessible form elements  
✅ Smooth transitions & animations  

---

## 🔗 CARA MENGGUNAKAN

### 1. IMPORT CSS FILE

Tambahkan di HEAD section HTML:

```html
<head>
    <!-- Existing meta tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Import Modern Form Styling -->
    <link rel="stylesheet" href="/assets/form-modern-style.css">
    
    <!-- Additional styles if needed -->
</head>
```

### 2. STRUKTUR HTML YANG BENAR

Gunakan struktur class yang sesuai:

```html
<!-- Main Container -->
<div class="form-modern-container">
    
    <!-- Card Wrapper -->
    <div class="form-modern-card">
        
        <!-- Card Header -->
        <div class="form-modern-card-header">
            <h1>📋 Form Input Pekerjaan</h1>
            <p>Sistem Kredit - Bank Kredit Wonosobo</p>
        </div>
        
        <!-- Card Content -->
        <div class="form-modern-card-content">
            
            <!-- SECTION 1 -->
            <section class="form-section">
                <div class="form-section-header">
                    <span class="form-section-icon">📋</span>
                    <h2 class="form-section-title">1. Data Pekerjaan</h2>
                </div>
                <p class="form-section-subtitle">Lengkapi informasi pekerjaan Anda.</p>
                
                <!-- Form Grid -->
                <div class="form-grid form-grid-2">
                    
                    <!-- Form Group -->
                    <div class="form-group">
                        <label class="form-label">
                            Tanggal Awal Perjanjian
                            <span class="form-label-required">*</span>
                        </label>
                        <input 
                            type="date" 
                            id="tgl_awal" 
                            class="form-input"
                            required
                        >
                        <span class="form-error-message" id="error-tgl_awal"></span>
                        <p class="form-hint">Format: DD/MM/YYYY</p>
                    </div>
                    
                    <!-- Form Group -->
                    <div class="form-group">
                        <label class="form-label">
                            Tanggal Akhir Perjanjian
                            <span class="form-label-required">*</span>
                        </label>
                        <input 
                            type="date" 
                            id="tgl_akhir" 
                            class="form-input"
                            required
                        >
                        <span class="form-error-message" id="error-tgl_akhir"></span>
                    </div>
                    
                    <!-- Display Box -->
                    <div class="form-group">
                        <label class="form-label">Sisa Masa Kerja</label>
                        <div class="form-display-box">
                            <span class="form-display-value" id="sisa_kerja_display">-</span>
                        </div>
                        <p class="form-hint">Dihitung otomatis</p>
                    </div>
                </div>
            </section>
            
            <!-- SECTION 2 - FILE UPLOAD -->
            <section class="form-section">
                <div class="form-section-header">
                    <span class="form-section-icon">📄</span>
                    <h2 class="form-section-title">2. Upload Dokumen</h2>
                </div>
                
                <div class="form-grid form-grid-1">
                    <div class="form-group">
                        <label class="form-label">
                            Upload File SK
                            <span class="form-label-required">*</span>
                        </label>
                        <div class="form-file-wrapper">
                            <input 
                                type="file" 
                                id="file_sk" 
                                class="form-file-input"
                                accept=".pdf,.jpg,.png"
                                required
                            >
                            <label for="file_sk" class="form-file-label">
                                <span class="form-file-icon">📎</span>
                                <span class="form-file-text">
                                    <span class="form-file-text-main">Pilih File</span>
                                    <span class="form-file-text-hint">PDF, JPG, PNG • Max 2MB</span>
                                </span>
                            </label>
                            <div class="form-file-preview" id="file_preview"></div>
                            <span class="form-error-message" id="error-file_sk"></span>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- SECTION 3 - DYNAMIC ITEMS -->
            <section class="form-section">
                <div class="form-section-header">
                    <span class="form-section-icon">🏦</span>
                    <h2 class="form-section-title">3. Angsuran Bank Wonosobo</h2>
                </div>
                <p class="form-section-subtitle">Tambahkan semua kredit/angsuran di Bank Wonosobo.</p>
                
                <!-- Dynamic Container -->
                <div id="angsuran_container" class="form-dynamic-container"></div>
                
                <!-- Add Button -->
                <div class="form-button-group">
                    <button type="button" class="btn btn-primary" onclick="addAngsuran()">
                        ➕ Tambah Angsuran
                    </button>
                </div>
                
                <!-- Total Box -->
                <div class="form-summary-box">
                    <div class="form-summary-row">
                        <span class="form-summary-label">Total Angsuran Bank Wonosobo:</span>
                        <span class="form-summary-value" id="total_display">Rp 0</span>
                    </div>
                    <input type="hidden" id="total_angsuran" value="0">
                </div>
            </section>
            
            <!-- BUTTONS -->
            <div class="form-button-group form-button-group-spaced">
                <button type="reset" class="btn btn-secondary">
                    🔄 Bersihkan Form
                </button>
                <button type="submit" class="btn btn-success">
                    ✓ Simpan Data
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 🎨 CLASS NAMING CONVENTION

Semua class mengikuti pattern `form-*`:

### Container & Layout
```
.form-modern-container      - Main container (max-width)
.form-modern-card          - Card wrapper
.form-modern-card-header   - Card header dengan gradient
.form-modern-card-content  - Card content area
```

### Sections
```
.form-section              - Section wrapper
.form-section-header       - Section title area
.form-section-icon        - Icon sebelum title
.form-section-title       - Section heading
.form-section-subtitle    - Description text
```

### Form Elements
```
.form-grid                - Form grid container
.form-grid-1            - 1 column
.form-grid-2            - 2 column (responsive)
.form-grid-3            - 3 column (responsive)

.form-group             - Form input group
.form-label             - Input label
.form-label-required    - Required indicator (*)
.form-input             - Text input
.form-select            - Select dropdown
.form-textarea          - Textarea
.form-error             - Error state class
```

### Display & Readonly
```
.form-display-box       - Readonly display box
.form-display-value     - Value dalam display box
```

### File Upload
```
.form-file-wrapper      - File upload container
.form-file-input        - Hidden file input
.form-file-label        - File upload label/button
.form-file-preview      - File preview area
```

### Dynamic Items
```
.form-dynamic-container     - Container untuk items
.form-dynamic-item          - Individual item
.form-dynamic-item-header   - Item header
.form-dynamic-item-number   - Item number badge
.form-dynamic-item-title    - Item title
.form-dynamic-item-delete   - Delete button
.form-dynamic-item-content  - Item content grid
```

### Buttons
```
.form-button-group              - Button group container
.form-button-group-spaced       - Space between buttons
.btn                           - Base button
.btn-primary                   - Primary action
.btn-secondary                 - Secondary action
.btn-success                   - Success action
.btn-danger                    - Danger/delete action
.btn-sm                        - Small button
```

### Messages
```
.form-hint                     - Helper text
.form-error-message            - Error message
.form-summary-box              - Total/summary box
.form-summary-row              - Summary row
.form-summary-label            - Summary label
.form-summary-value            - Summary value
```

---

## 🎯 COLOR PALETTE

Sistem menggunakan CSS variables untuk warna:

```css
--color-primary: #4f46e5          /* Biru (form focus) */
--color-primary-light: #6366f1    /* Biru muda */
--color-primary-dark: #4338ca     /* Biru tua */
--color-secondary: #10b981        /* Hijau (success) */
--color-danger: #ef4444           /* Merah (error) */
--color-warning: #f59e0b          /* Oranye (warning) */

/* Neutrals */
--color-white: #ffffff
--color-gray-50: #f9fafb
--color-gray-100: #f3f4f6
... dst
```

### Penggunaan Warna
- **Primary (Biru):** Focus state, buttons, active elements
- **Secondary (Hijau):** Success buttons, positive feedback
- **Danger (Merah):** Error messages, delete buttons
- **Neutral:** Text, backgrounds, borders

---

## 📏 SPACING SYSTEM

Menggunakan consistent spacing variables:

```css
--space-xs: 0.25rem    (4px)
--space-sm: 0.5rem     (8px)
--space-md: 1rem       (16px)
--space-lg: 1.5rem     (24px)
--space-xl: 2rem       (32px)
--space-2xl: 2.5rem    (40px)
--space-3xl: 3rem      (48px)
```

### Padding & Margin
- Form sections: `--space-xl` (24px)
- Form groups: `--space-lg` (16px)
- Input padding: `0.875rem 1rem`
- Button padding: `0.875rem 1.75rem`

---

## 🔤 TYPOGRAPHY

Font family:
```css
--font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', 'Roboto'...
```

Font sizes:
```css
--font-size-sm: 0.875rem (14px)     - Small text, hints
--font-size-base: 1rem (16px)       - Base/input
--font-size-lg: 1.125rem (18px)     - Section titles
--font-size-xl: 1.25rem (20px)      - Major headings
--font-size-2xl: 1.5rem (24px)      - Page heading
```

Font weights:
- Regular (400): Body text
- Medium (500): Labels
- Semi-bold (600): Section titles, buttons
- Bold (700): Headings, important values

---

## ✨ RESPONSIVE BREAKPOINTS

Sistem support 3 breakpoints:

### Desktop (≥1024px)
- Full layout: 2-3 column grids
- Spacing: Penuh (tidak dikurangi)
- Ideal untuk desktop monitor

### Tablet (768px - 1023px)
```css
@media (max-width: 768px) {
    Grid 2-col → 1 col
    Padding: sedikit dikurangi
    Mobile-friendly layout
}
```

### Mobile (<768px)
```css
Full responsive:
    - 1 column grid
    - Reduced padding
    - Full-width buttons
    - Stack elements vertically
    - Touch-friendly spacing (44px min-height)
```

### Small Mobile (<480px)
```css
- Smaller font sizes (14px base)
- Minimal padding
- Optimized for 320px+ screens
- Stack all elements
```

---

## 🎯 IMPLEMENTASI STEP-BY-STEP

### Step 1: Import CSS
```html
<link rel="stylesheet" href="/assets/form-modern-style.css">
```

### Step 2: Update HTML Class Names
Ganti class lama dengan pattern baru:
```html
<!-- OLD -->
<div class="pppk-form-grid">
    <input class="pppk-input">
</div>

<!-- NEW -->
<div class="form-grid form-grid-2">
    <input class="form-input">
</div>
```

### Step 3: Struktur Sections
Pastikan setiap section menggunakan:
```html
<section class="form-section">
    <div class="form-section-header">
        <span class="form-section-icon">📋</span>
        <h2 class="form-section-title">Title</h2>
    </div>
    <!-- Content -->
</section>
```

### Step 4: Form Groups
Setiap input harus dalam form-group:
```html
<div class="form-group">
    <label class="form-label">
        Label <span class="form-label-required">*</span>
    </label>
    <input class="form-input">
    <span class="form-error-message" id="error-field"></span>
    <p class="form-hint">Help text</p>
</div>
```

### Step 5: Test Responsive
- Desktop (1920px)
- Tablet (768px)
- Mobile (375px)
- Small (320px)

---

## 🔍 VISUAL GUIDELINES

### Input Fields
✅ Uniform height: 44px (accessible)  
✅ Consistent padding: 0.875rem 1rem  
✅ Border: 1.5px solid gray-300  
✅ Border radius: 8px  
✅ Focus: Blue border + shadow  
✅ Error: Red border + light red bg  

### Buttons
✅ Min-height: 44px (touch-friendly)  
✅ Padding: 0.875rem 1.75rem  
✅ Border-radius: 8px  
✅ Gradient background  
✅ Hover effect: translateY(-2px)  
✅ Disabled state: opacity 0.5  

### Sections
✅ Padding: 24px (--space-xl)  
✅ Gap between items: 24px  
✅ Border: 1px solid gray-200  
✅ Background: gray-50  
✅ Border-radius: 12px  

### Cards
✅ Background: white  
✅ Border-radius: 12px  
✅ Box-shadow: var(--shadow-sm)  
✅ Header: Gradient blue  

---

## ⚠️ COMMON MISTAKES TO AVOID

❌ **DON'T:** Mix old & new class names
```html
<!-- WRONG -->
<input class="pppk-input form-input">
```

✅ **DO:** Use consistent naming
```html
<!-- RIGHT -->
<input class="form-input">
```

---

❌ **DON'T:** Forget form-group wrapper
```html
<!-- WRONG -->
<label>Label</label>
<input class="form-input">
```

✅ **DO:** Wrap in form-group
```html
<!-- RIGHT -->
<div class="form-group">
    <label class="form-label">Label</label>
    <input class="form-input">
</div>
```

---

❌ **DON'T:** Add inline styles
```html
<!-- WRONG -->
<div style="padding: 20px; margin: 10px;">
```

✅ **DO:** Use utility classes
```html
<!-- RIGHT -->
<div class="form-section">
```

---

❌ **DON'T:** Skip responsive structure
```html
<!-- WRONG -->
<div class="form-grid" style="grid-template-columns: 1fr 1fr;">
```

✅ **DO:** Use responsive grid classes
```html
<!-- RIGHT -->
<div class="form-grid form-grid-2">
```

---

## 📱 TESTING CHECKLIST

- [ ] Desktop layout (1920px) - proper spacing
- [ ] Tablet layout (768px) - responsive grid  
- [ ] Mobile layout (375px) - single column
- [ ] Small mobile (320px) - no overflow
- [ ] Input focus states - blue border + shadow
- [ ] Input error states - red border + message
- [ ] File upload - proper preview display
- [ ] Dynamic items - add/remove working
- [ ] Buttons - hover effects smooth
- [ ] Accessibility - all inputs labeled
- [ ] Touch targets - min 44px height
- [ ] No horizontal scroll - all content fits
- [ ] Typography - readable sizes & weights
- [ ] Colors - consistent palette usage

---

## 🚀 PRODUCTION DEPLOYMENT

Sebelum go live:

1. ✅ Import CSS file di HEAD
2. ✅ Update semua class names
3. ✅ Test responsive design
4. ✅ Verify colors & spacing
5. ✅ Check accessibility
6. ✅ Test on actual devices
7. ✅ Monitor browser compatibility
8. ✅ Get approval dari team
9. ✅ Deploy to production
10. ✅ Monitor untuk issues

---

## 📞 SUPPORT & QUESTIONS

Dokumentasi ini mendukung:
- ✅ Form PPPK
- ✅ Form Perangkat Desa
- ✅ Semua form-based pages

Untuk questions tentang styling:
- Refer to CSS variables di file
- Check responsive breakpoints
- Review color palette section
- Test dengan browser DevTools

---

**Last Updated:** 30 April 2026  
**Version:** 1.0 Production Ready ✅  
**Status:** Approved for use
