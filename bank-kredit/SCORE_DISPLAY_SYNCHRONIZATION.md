# Score Display Synchronization Fix

**Date**: March 4, 2025  
**Status**: ✅ COMPLETED  
**Objective**: Ensure 6C scoring display is consistent across input forms and print output

---

## Problem Analysis

The 6C scoring system had inconsistent display formats across different views:

### Score Storage in Database
- **Individual Scores**: Each of 6 categories (Character, Capacity, Capital, Collateral, Condition, Constraint) stored as INT (1-5)
- **Total Score**: Stored as DECIMAL(5,2) = **AVERAGE** of 6 categories (1-5 scale)
  - Formula: total_score = (char + cap + cap + collateral + condition + constraint) / 6
  - Range: 1.0 to 5.0

### Original Display Issues
1. **form_umum.php**: Showed "X / 5.0" (incorrect denominator)
2. **pegawai_head_raw.inc.php** (PPPK/Desa): Showed "X / 30" but without multiplying by 6
3. **form_cashcolateral.php**: Showed "X / 5.0" (incorrect denominator)
4. **print.php**: Showed raw database value "X / 30" which was incorrect

### Expected Display Format
Since 6 categories × 5 points max = 30 points total:
- Display format: **"X / 30"** where X = total_score × 6
- Example: If average = 5.0 → display = "30 / 30"
- Example: If average = 3.5 → display = "21 / 30"

---

## Changes Made

### 1. form_umum.php (lines 560-595)
**File**: `analis/form_umum.php`  
**Function**: `updateScoringSummary()`

**Before**:
```javascript
let res6c = calc6C();
var gtxt = res6c.grade || res6c.msg || '';
document.getElementById('score_summary_5c').textContent = res6c.total + " / 5.0 (" + gtxt + ")";
```

**After**:
```javascript
let res6c = calc6C();
var gtxt = res6c.grade || res6c.msg || '';
let totalOut30 = Math.round(res6c.total * 6 * 100) / 100;
document.getElementById('score_summary_5c').textContent = totalOut30 + " / 30 (" + gtxt + ")";
```

**Also Added** (line 3230):
- Call to `updateScoringSummary()` after prefill completes
- Ensures score display updates when editing existing pengajuan

### 2. pegawai_head_raw.inc.php (lines 529-540)
**File**: `analis/partials/pegawai_head_raw.inc.php`  
**Function**: `updateScoringSummary()`

**Before**:
```javascript
document.getElementById('score_summary_5c').textContent = res6c.total + " / 30 (" + gtxt + ")";
```

**After**:
```javascript
let totalOut30 = Math.round(res6c.total * 6 * 100) / 100;
document.getElementById('score_summary_5c').textContent = totalOut30 + " / 30 (" + gtxt + ")";
```

**Impact**: Fixes PPPK and Perangkat Desa form scoring display

### 3. form_cashcolateral.php (lines 349-360)
**File**: `analis/form_cashcolateral.php`  
**Function**: `updateScoringSummary()`

**Before**:
```javascript
if (ss) ss.textContent = res6c.total + " / 5.0 (" + gtxt + ")";
```

**After**:
```javascript
let totalOut30 = Math.round(res6c.total * 6 * 100) / 100;
if (ss) ss.textContent = totalOut30 + " / 30 (" + gtxt + ")";
```

**Impact**: Aligns Cash Collateral form with standard scoring display

### 4. print.php (line 1169)
**File**: `bank-kredit/print.php`  
**Section**: Analisa 6C table - Total Skor display

**Before**:
```php
<td class="value" style="font-weight:bold; font-size:13px;"><?= floatval($print_6c['total_score']??0) ?> / 30</td>
```

**After**:
```php
<td class="value" style="font-weight:bold; font-size:13px;"><?= round(floatval($print_6c['total_score']??0) * 6, 2) ?> / 30</td>
```

**Impact**: Ensures print output displays correct total score (1-30 format)

---

## Score Calculation Reference

### Backend: `helpers/credit_helper.php` - `hitung_6c()` function
```
Input: 6 individual scores (1-5 each)
↓
total = sum of all 6 scores (max 30)
rata = total / 6 (this is what gets stored in DB)
↓
Output: rata is stored as total_score in analisa_5c table
```

### Frontend: `calc6C()` function
```
Input: Form field values from dropdowns
↓
Computes average per category (1-5)
Calculates total = sum / count (1-5)
↓
Returns: { total, grade, msg }
```

### Display Conversion
```
Database Value (1-5) × 6 = Display Value (1-30)
Example: 4.5 × 6 = 27 / 30
```

---

## Verification Checklist

✅ **form_umum.php**: Score display updated with proper "/ 30" format  
✅ **form_umum.php**: updateScoringSummary() called after prefill  
✅ **pegawai_head_raw.inc.php** (PPPK/Desa): Score display now multiplies by 6  
✅ **form_cashcolateral.php**: Score display aligned to standard "/30" format  
✅ **print.php**: Total score calculation fixed to multiply by 6  
✅ **Consistency**: All forms now display scores in uniform "X / 30" format  

---

## Testing Recommendations

1. **Create New Umum Application**
   - Fill all 6C scoring fields
   - Navigate to Scoring tab
   - Verify display shows "X / 30" format

2. **Create New PPPK Application**
   - Fill all 6C scoring fields
   - Navigate to Scoring & Summary tab
   - Verify display shows "X / 30" format

3. **Create New Perangkat Desa Application**
   - Fill all 6C scoring fields
   - Navigate to Scoring & Summary tab
   - Verify display shows "X / 30" format

4. **Edit Existing Application**
   - Open any completed application
   - Verify Scoring tab displays correct value
   - Print output should match form display

5. **Print Output Validation**
   - Print various applications
   - Verify "TOTAL SKOR" row shows "X / 30" format
   - Verify value matches what's shown in input form

---

## Related Files Modified
- `analis/form_umum.php` - Line 561-569, 3230
- `analis/partials/pegawai_head_raw.inc.php` - Line 532-535
- `analis/form_cashcolateral.php` - Line 349-354
- `print.php` - Line 1169

## Database References
- `analisa_5c` table: total_score DECIMAL(5,2)
- `helpers/credit_helper.php`: `hitung_6c()` function

---

## Impact Summary
- **User Experience**: Consistent, understandable score display across all forms
- **Data Accuracy**: Scores now correctly represent 1-30 scale instead of incorrect 1-5 scale
- **Print Quality**: Output now shows accurate total scores matching input forms
- **Maintenance**: Centralized calculation logic makes future updates easier

