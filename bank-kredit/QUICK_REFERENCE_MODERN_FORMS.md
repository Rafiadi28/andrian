# 🎨 MODERN FORM STYLING - QUICK REFERENCE

**Purpose:** Fast lookup guide for developers during integration  
**Version:** 1.0  
**Date:** 30 April 2026

---

## 📋 CLASS NAMING QUICK REFERENCE

### Container & Layout
```css
.form-modern-container        Main wrapper (max-width: 1024px, centered)
.form-modern-card             Card wrapper (white bg, shadow)
.form-modern-card-header      Card header (gradient blue background)
.form-modern-card-content     Card content area (padding)
```

### Sections
```css
.form-section                 Section container (gray-50 bg, border)
.form-section-header          Section header (flex, border-bottom)
.form-section-icon            Icon before title
.form-section-title           Section heading (h2 size)
.form-section-subtitle        Description text (gray-500)
```

### Form Grid
```css
.form-grid                    Grid container (gap: 1.5rem)
.form-grid-1                  1 column (full width)
.form-grid-2                  2 columns responsive (300px min)
.form-grid-3                  3 columns responsive (250px min)
```

### Form Groups
```css
.form-group                   Input wrapper (flex, column, gap)
.form-label                   Label (gray-700, font-weight: 600)
.form-label-required          Required indicator (red, bold *)
.form-hint                    Help text below input (gray-500, smaller)
```

### Input Fields
```css
.form-input                   Text/number input (44px height)
.form-select                  Select dropdown (with arrow)
.form-textarea                Text area

.form-input:focus             Blue border + shadow
.form-input.form-error        Red border + light red bg
.form-input:disabled          Gray bg, disabled cursor
```

### Display Box (Readonly)
```css
.form-display-box             Readonly display (green-50 bg, green border)
.form-display-value           Value inside box (bold, green text)
```

### Buttons
```css
.btn                          Base button (44px height, padding)
.btn-primary                  Blue gradient button (main action)
.btn-secondary                Gray button (secondary action)
.btn-success                  Green gradient button (confirm)
.btn-danger                   Red gradient button (delete)
.btn-sm                       Small button (padding reduced)

.form-button-group           Button container (flex, gap)
.form-button-group-spaced    Buttons spread apart (justify-between)
```

### File Upload
```css
.form-file-wrapper           File upload container
.form-file-input             Hidden file input
.form-file-label             Clickable upload area (dashed border)
.form-file-preview           File preview after upload
```

### Dynamic Items
```css
.form-dynamic-container      Container for repeatable items
.form-dynamic-item           Individual item (border, padding)
.form-dynamic-item-header    Item header (flex, justify-between)
.form-dynamic-item-number    Item number badge (circle, blue)
.form-dynamic-item-delete    Delete button
.form-dynamic-item-content   Item content grid
```

### Messages & Boxes
```css
.form-error-message          Error text (red, hidden by default)
.form-error-message.show     Error visible (animation)

.form-summary-box            Total/summary box (yellow-50 bg)
.form-summary-row            Summary row (flex, space-between)
.form-summary-label          Summary label text
.form-summary-value          Summary value (bold, larger)
```

---

## 🎯 COMMON PATTERNS

### Basic Input Field
```html
<div class="form-group">
    <label class="form-label">
        Label <span class="form-label-required">*</span>
    </label>
    <input type="text" id="field_id" class="form-input">
    <span class="form-error-message" id="error-field_id"></span>
    <p class="form-hint">Help text</p>
</div>
```

### Select Dropdown
```html
<div class="form-group">
    <label class="form-label">Choose <span class="form-label-required">*</span></label>
    <select id="select_id" class="form-select">
        <option value="">-- Select --</option>
        <option value="1">Option 1</option>
    </select>
    <span class="form-error-message" id="error-select_id"></span>
</div>
```

### Form Grid (2 columns)
```html
<div class="form-grid form-grid-2">
    <div class="form-group">...</div>
    <div class="form-group">...</div>
</div>
```

### Readonly Display Box
```html
<div class="form-group">
    <label class="form-label">Display Value</label>
    <div class="form-display-box">
        <span class="form-display-value" id="value_id">-</span>
    </div>
</div>
```

### File Upload
```html
<div class="form-group">
    <label class="form-label">Upload File <span class="form-label-required">*</span></label>
    <div class="form-file-wrapper">
        <input type="file" id="file_id" class="form-file-input" accept=".pdf,.jpg,.png">
        <label for="file_id" class="form-file-label">
            <span class="form-file-icon">📎</span>
            <span class="form-file-text">
                <span class="form-file-text-main">Click or drag file</span>
                <span class="form-file-text-hint">PDF, JPG, PNG • Max 2MB</span>
            </span>
        </label>
        <div class="form-file-preview" id="file_preview_id"></div>
        <span class="form-error-message" id="error-file_id"></span>
    </div>
</div>
```

### Dynamic Items Container
```html
<div id="items_container" class="form-dynamic-container">
    <!-- Items generated by JavaScript -->
</div>

<button type="button" class="btn btn-primary" onclick="addItem()">
    ➕ Add Item
</button>

<div class="form-summary-box">
    <div class="form-summary-row">
        <span class="form-summary-label">Total:</span>
        <span class="form-summary-value" id="total_id">Rp 0</span>
    </div>
</div>
```

### Dynamic Item Template
```html
<div class="form-dynamic-item" data-index="1">
    <div class="form-dynamic-item-header">
        <span class="form-dynamic-item-number">1</span>
        <span class="form-dynamic-item-title">Item Title</span>
        <button type="button" class="form-dynamic-item-delete" onclick="removeItem(1)">
            🗑️ Delete
        </button>
    </div>
    <div class="form-dynamic-item-content">
        <div class="form-group">
            <!-- Item fields -->
        </div>
    </div>
</div>
```

### Section Structure
```html
<section class="form-section">
    <div class="form-section-header">
        <span class="form-section-icon">📋</span>
        <h2 class="form-section-title">Section Title</h2>
    </div>
    <p class="form-section-subtitle">Description text</p>
    
    <div class="form-grid form-grid-2">
        <!-- Form fields -->
    </div>
</section>
```

### Button Group
```html
<!-- Buttons in center -->
<div class="form-button-group">
    <button type="submit" class="btn btn-primary">Submit</button>
</div>

<!-- Buttons spread apart -->
<div class="form-button-group form-button-group-spaced">
    <button type="reset" class="btn btn-secondary">Cancel</button>
    <button type="submit" class="btn btn-success">Save</button>
</div>
```

---

## 🎨 COLOR & SPACING QUICK LOOKUP

### Colors (CSS Variables)
```css
--color-primary: #4f46e5          Blue
--color-primary-light: #6366f1    Light blue
--color-primary-dark: #4338ca     Dark blue
--color-secondary: #10b981        Green
--color-danger: #ef4444           Red
--color-warning: #f59e0b          Orange

--color-white: #ffffff            White
--color-gray-50: #f9fafb          Very light gray
--color-gray-100: #f3f4f6         Light gray
--color-gray-500: #6b7280         Medium gray
--color-gray-700: #374151         Dark gray
--color-gray-900: #111827         Almost black
```

### Spacing (CSS Variables)
```css
--space-xs: 0.25rem      4px
--space-sm: 0.5rem       8px
--space-md: 1rem         16px
--space-lg: 1.5rem       24px
--space-xl: 2rem         32px
--space-2xl: 2.5rem      40px
--space-3xl: 3rem        48px
```

### Border Radius
```css
--radius-sm: 4px         Small (inputs, badges)
--radius-md: 8px         Medium (cards, sections)
--radius-lg: 12px        Large (card container)
--radius-full: 9999px    Full circle (badges)
```

---

## 📐 RESPONSIVE BREAKPOINTS

| Device | Breakpoint | Grid Behavior |
|--------|-----------|---|
| Desktop | ≥1024px | Normal 2-3 columns |
| Tablet | 768px-1023px | 1-2 columns |
| Mobile | 480px-767px | 1 column (stacked) |
| Small | <480px | 1 column, minimal padding |

```css
/* Tablet breakpoint */
@media (max-width: 768px) {
    .form-grid-2,
    .form-grid-3 {
        grid-template-columns: 1fr;
    }
}

/* Mobile breakpoint */
@media (max-width: 480px) {
    /* Reduced font sizes, padding */
}
```

---

## 🚀 INTEGRATION STEPS (5 MINUTES)

### Step 1: Add CSS Link (30 seconds)
```html
<head>
    <link rel="stylesheet" href="/assets/form-modern-style.css">
</head>
```

### Step 2: Wrap Form in Container (1 minute)
```html
<!-- WRAP ENTIRE FORM -->
<div class="form-modern-container">
    <div class="form-modern-card">
        <div class="form-modern-card-header">
            <h1>Form Title</h1>
            <p>Subtitle</p>
        </div>
        <div class="form-modern-card-content">
            <!-- Your form content -->
        </div>
    </div>
</div>
```

### Step 3: Update Section Classes (2 minutes)
```
OLD                  →  NEW
.pppk-section       →  .form-section
.pppk-input         →  .form-input
.pppk-select        →  .form-select
.pppk-button        →  .btn .btn-primary
.pppk-error         →  .form-error-message
```

### Step 4: Update Grid Classes (1 minute)
```
OLD                  →  NEW
.pppk-grid          →  .form-grid .form-grid-2
.pppk-grid-1        →  .form-grid .form-grid-1
```

### Step 5: Test (1 minute)
```
✅ Check layout on desktop
✅ Check layout on mobile
✅ Click buttons, type inputs
✅ Check browser console for errors
```

---

## ⚠️ COMMON MISTAKES

❌ **Mixing old & new classes**
```html
<input class="pppk-input form-input">  <!-- WRONG -->
<input class="form-input">              <!-- RIGHT -->
```

❌ **Forgetting form-group wrapper**
```html
<label>Label</label>                    <!-- WRONG -->
<input class="form-input">

<div class="form-group">                <!-- RIGHT -->
    <label class="form-label">Label</label>
    <input class="form-input">
</div>
```

❌ **Using inline styles**
```html
<div style="padding: 20px; margin: 10px;">  <!-- WRONG -->
<div class="form-section">                  <!-- RIGHT -->
```

❌ **Not wrapping in container**
```html
<form>                              <!-- WRONG -->
    <!-- form fields -->
</form>

<div class="form-modern-container"> <!-- RIGHT -->
    <form>
        <!-- form fields -->
    </form>
</div>
```

---

## 🔍 DEBUGGING CHECKLIST

❌ Form looks old/unstyled?
→ Check CSS link is loaded (DevTools → Network tab)
→ Verify path: `/assets/form-modern-style.css`

❌ Inputs are huge/tiny?
→ Check class is `form-input` not old class name
→ Verify no inline styles override

❌ Layout is broken on mobile?
→ Check form-grid class used (not custom grid)
→ Verify breakpoint media queries load
→ Test at 375px width

❌ Buttons don't work?
→ Verify button classes: `btn btn-primary`
→ Check onclick handlers still work
→ Look for JavaScript errors (DevTools → Console)

❌ Spacing looks wrong?
→ Check form-group wrapper exists
→ Verify proper spacing utility classes used
→ Inspect CSS variables loaded correctly

---

## 📱 RESPONSIVE TEST CHECKLIST

At each breakpoint, verify:
- [ ] All text readable (not too small/large)
- [ ] Inputs fully clickable (44px height minimum)
- [ ] Buttons aligned properly
- [ ] No horizontal scrolling
- [ ] Grid items stack correctly
- [ ] File upload visible
- [ ] Error messages display
- [ ] Spacing proportional

**Test sizes:**
- 1920px (desktop)
- 1366px (desktop)
- 768px (tablet)
- 375px (mobile)
- 320px (small mobile)

---

## 🎯 CLASS SELECTION DECISION TREE

**Is it a text input?** → `.form-input`
**Is it a select?** → `.form-select`
**Is it a display-only box?** → `.form-display-box`
**Is it a button?**
- Main action → `.btn .btn-primary`
- Secondary → `.btn .btn-secondary`
- Confirm/Success → `.btn .btn-success`
- Delete → `.btn .btn-danger`

**Is it a container?**
- Main form → `.form-modern-container`
- Card → `.form-modern-card`
- Section → `.form-section`
- Input group → `.form-group`
- Buttons → `.form-button-group`
- Repeatable items → `.form-dynamic-container`

**Is it a label?**
- Normal → `.form-label`
- Required indicator → `.form-label-required`
- Helper text → `.form-hint`
- Error message → `.form-error-message`

---

## 📞 FAQ

**Q: Can I use old classes alongside new classes?**
A: No. Remove old classes completely, use only new form-* classes.

**Q: Will old CSS still load?**
A: Yes. Make sure to remove old CSS imports or use new form-modern-style.css only.

**Q: What about IE11 support?**
A: CSS uses Grid, Variables, Flexbox (not IE11 compatible). Use modern browsers.

**Q: Can I customize colors?**
A: Yes. Edit CSS variables in `:root` section of form-modern-style.css.

**Q: Do I need JavaScript?**
A: No. CSS is standalone. JavaScript is optional for form functionality.

**Q: Is it mobile-first?**
A: Yes. Mobile styles are default, desktop styles override at breakpoints.

---

## 📖 FULL DOCUMENTATION

For complete details, see: `PANDUAN_MODERN_FORM_STYLING.md`

For working example: `form-desa-modern-example.html`

For integration guide: `MODERN_FORM_INTEGRATION_SUMMARY.md`

---

**Last Updated:** 30 April 2026  
**Version:** 1.0  
**Status:** Ready to use ✅
