# گرماژ جلد - نمودار فیلترینگ Cascade

## نمودار فارسی

```
انتخاب قطع کتاب
    ↓
API فراخوانی می‌شود: /get-allowed-options
    ↓
داده‌های مجاز دریافت می‌شود:
  - انواع کاغذ مجاز
  - انواع صحافی مجاز (با گرماژهای جلد مربوطه)
  - سایر پارامترها
    ↓
کاربر نوع صحافی را انتخاب می‌کند
    ↓
updateCoverWeightsAvailability() فراخوانی می‌شود
    ↓
API فراخوانی می‌شود با پارامترها:
  - book_size: "رقعی"
  - binding_type: "شومیز"
    ↓
constraint manager فیلتر می‌کند:
  - همه گرماژهای موجود برای این صحافی
  - منهای forbidden_cover_weights
    ↓
updateCoverWeightsDropdown() فراخوانی می‌شود
    ↓
منوی کشویی بازسازی می‌شود:
  ✓ فقط گرماژهای مجاز نمایش داده می‌شوند
  ✓ انتخاب قبلی حفظ می‌شود (اگر مجاز باشد)
  ✓ اگر انتخاب قبلی غیرمجاز است، اولین گزینه انتخاب می‌شود
```

## مثال عملی

### پیکربندی ماتریس قیمت:
```json
{
  "binding_costs": {
    "شومیز": {
      "250": 5000,
      "300": 6000,
      "350": 7000
    },
    "گالینگور": {
      "200": 8000,
      "250": 9000,
      "300": 10000
    }
  },
  "restrictions": {
    "forbidden_cover_weights": {
      "شومیز": ["350"],
      "گالینگور": ["200"]
    }
  }
}
```

### سناریو 1: انتخاب صحافی "شومیز"
```
کاربر انتخاب می‌کند: صحافی = "شومیز"
    ↓
API پاسخ می‌دهد: allowed_cover_weights = [
  { weight: "250", slug: "250" },
  { weight: "300", slug: "300" }
]
(350 حذف شده چون در forbidden_cover_weights است)
    ↓
منوی کشویی گرماژ جلد:
  - 250 گرم ✅
  - 300 گرم ✅
  (350 نمایش داده نمی‌شود)
```

### سناریو 2: انتخاب صحافی "گالینگور"
```
کاربر انتخاب می‌کند: صحافی = "گالینگور"
    ↓
API پاسخ می‌دهد: allowed_cover_weights = [
  { weight: "250", slug: "250" },
  { weight: "300", slug: "300" }
]
(200 حذف شده چون در forbidden_cover_weights است)
    ↓
منوی کشویی گرماژ جلد:
  - 250 گرم ✅
  - 300 گرم ✅
  (200 نمایش داده نمی‌شود)
```

### سناریو 3: تغییر صحافی با حفظ انتخاب
```
وضعیت اولیه:
  - صحافی: "شومیز"
  - گرماژ جلد: "250"

کاربر صحافی را به "گالینگور" تغییر می‌دهد
    ↓
بررسی: آیا "250" در گرماژهای مجاز "گالینگور" است؟
  ✅ بله، "250" مجاز است
    ↓
نتیجه: انتخاب "250" حفظ می‌شود
```

### سناریو 4: تغییر صحافی بدون حفظ انتخاب
```
وضعیت اولیه:
  - صحافی: "شومیز"
  - گرماژ جلد: "300"

کاربر صحافی را به یک نوع جدید تغییر می‌دهد که فقط "250" مجاز است
    ↓
بررسی: آیا "300" در گرماژهای مجاز است؟
  ❌ خیر، "300" مجاز نیست
    ↓
نتیجه: اولین گزینه ("250") به صورت خودکار انتخاب می‌شود
```

---

## Cascade Flow Diagram (English)

```
User selects book size
    ↓
API called: /get-allowed-options
    ↓
Allowed data received:
  - Allowed paper types
  - Allowed binding types (with associated cover weights)
  - Other parameters
    ↓
User selects binding type
    ↓
updateCoverWeightsAvailability() called
    ↓
API called with parameters:
  - book_size: "A5"
  - binding_type: "Perfect Binding"
    ↓
Constraint manager filters:
  - All weights available for this binding
  - Minus forbidden_cover_weights
    ↓
updateCoverWeightsDropdown() called
    ↓
Dropdown rebuilt:
  ✓ Only allowed weights shown
  ✓ Previous selection preserved (if allowed)
  ✓ First option auto-selected if previous invalid
```

## Implementation Flow

```javascript
// Event binding in initFormFields()
$('#aof-binding-type').on('change', function() {
    if (tabeshAdminOrderForm.v2Enabled) {
        updateExtrasAvailability();
        updateCoverWeightsAvailability();  // ← NEW
    }
});

// Function 1: Fetch allowed weights
function updateCoverWeightsAvailability() {
    const bookSize = $('#aof-book-size').val();
    const bindingType = $('#aof-binding-type').val();
    
    $.ajax({
        url: '.../get-allowed-options',
        data: { book_size: bookSize, current_selection: { binding_type: bindingType } },
        success: function(response) {
            updateCoverWeightsDropdown(response.data.allowed_cover_weights);
        }
    });
}

// Function 2: Update dropdown
function updateCoverWeightsDropdown(allowedCoverWeights) {
    const $select = $('#aof-cover-paper-weight');
    const currentValue = $select.val();
    
    $select.empty();
    
    allowedCoverWeights.forEach(function(weightInfo) {
        const weight = weightInfo.weight;
        $select.append('<option>' + weight + ' گرم</option>');
    });
    
    // Restore selection if still valid
    if (isStillValid(currentValue, allowedCoverWeights)) {
        $select.val(currentValue);
    } else {
        // Auto-select first option
        $select.val(allowedCoverWeights[0].weight);
    }
}
```

## Backend Support (Already Exists)

```php
// class-tabesh-constraint-manager.php (lines 216-231)
if ( $selected_binding_type && isset( $binding_costs[ $selected_binding_type ] ) ) {
    $binding_data = $binding_costs[ $selected_binding_type ];
    $forbidden_cover_weights = $restrictions['forbidden_cover_weights'][ $selected_binding_type ] ?? array();
    
    if ( is_array( $binding_data ) ) {
        foreach ( array_keys( $binding_data ) as $weight ) {
            if ( ! in_array( $weight, $forbidden_cover_weights, true ) ) {
                $result['allowed_cover_weights'][] = array(
                    'weight' => $weight,
                    'slug'   => $this->slugify( $weight ),
                );
            }
        }
    }
}
```

## Key Points

### ✅ What Works
- Dynamic filtering based on binding type
- Preserves user selection when valid
- Auto-selects first option when needed
- Consistent with other cascade filters
- Works with V2 pricing engine

### ⚠️ Important Notes
- Only applies to admin order form shortcode
- Requires V2 pricing engine to be enabled
- Modal form (admin-order-creator.js) not affected yet
- No backend changes needed (already had logic)

### 🧪 Testing Checklist
- [ ] Configure pricing matrix with forbidden_cover_weights
- [ ] Select book size in form
- [ ] Select binding type
- [ ] Verify only allowed weights appear in dropdown
- [ ] Change binding type
- [ ] Verify weights update correctly
- [ ] Check if selection is preserved when valid
- [ ] Check if first option auto-selected when invalid

---

## Files Changed Summary

| File | Lines Changed | Purpose |
|------|---------------|---------|
| `assets/js/admin-order-form.js` | +88 | Added cascade filtering functions |
| `ADMIN_ORDER_FORM_V2_INTEGRATION.md` | +37 | Updated documentation |
| `COVER_WEIGHT_CASCADE_FIX.md` | +150 | Comprehensive summary |
| **Total** | **+275** | **Complete implementation** |

## Version Update

- **From**: v1.0 (Basic V2 integration)
- **To**: v1.1 (Cover weight cascade complete)
- **Status**: ✅ Ready for testing
