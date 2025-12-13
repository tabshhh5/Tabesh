# Dynamic Pricing Test Verification

## Test Case: Add New Paper Type "کرافت"

### Step 1: User adds parameter in Product Parameters tab

**Action:** 
```
Go to: Settings → Product Parameters → انواع کاغذ و گرماژها
Add line: کرافت=60,70,80
Save
```

**Data Flow:**
1. Form submits: `paper_types = "تحریر=60,70,80\nبالک=60,70,80,100\nکرافت=60,70,80"`
2. Admin handler processes (class-tabesh-admin.php line 292-340)
3. `normalize_to_json_object()` parses it
4. Stored in DB as:
```json
{
  "تحریر": [60, 70, 80],
  "بالک": [60, 70, 80, 100],
  "کرافت": [60, 70, 80]
}
```

### Step 2: User navigates to Pricing tab

**Action:**
```
Go to: Settings → Pricing → قیمت پایه کاغذ
```

**Template Execution (admin-settings.php line 372+):**
```php
// Get product parameters
$product_paper_types = $admin->get_setting('paper_types', array());
// Returns: ['تحریر' => [60,70,80], 'بالک' => [...], 'کرافت' => [60,70,80]]

// Get existing pricing
$pricing_paper_weights = $admin->get_setting('pricing_paper_weights', array());
// Returns: ['تحریر' => ['60'=>150, ...], 'بالک' => [...]]
// Note: 'کرافت' doesn't exist yet

// Loop through product_paper_types
foreach ($product_paper_types as $paper_type => $weights):
    // When $paper_type = 'کرافت'
    foreach ($weights as $weight):
        // Generates three input fields:
        // <input name="pricing_paper_weights[کرافت][60]" value="0">
        // <input name="pricing_paper_weights[کرافت][70]" value="0">
        // <input name="pricing_paper_weights[کرافت][80]" value="0">
```

**Expected UI Output:**
```html
<tr>
    <th colspan="2" style="background-color: #f0f0f0;">
        <strong>کرافت</strong>
        <span class="description">(3 گرماژ)</span>
    </th>
</tr>
<tr>
    <th>گرماژ 60</th>
    <td>
        <input name="pricing_paper_weights[کرافت][60]" value="0" placeholder="200"> تومان / صفحه
    </td>
</tr>
<tr>
    <th>گرماژ 70</th>
    <td>
        <input name="pricing_paper_weights[کرافت][70]" value="0" placeholder="200"> تومان / صفحه
    </td>
</tr>
<tr>
    <th>گرماژ 80</th>
    <td>
        <input name="pricing_paper_weights[کرافت][80]" value="0" placeholder="200"> تومان / صفحه
    </td>
</tr>
```

### Step 3: User enters prices and saves

**Action:**
```
Enter values:
- کرافت 60 = 180
- کرافت 70 = 210
- کرافت 80 = 240
Click Save
```

**Data Flow:**
1. POST data: `pricing_paper_weights[کرافت][60] = 180`, etc.
2. Admin handler (class-tabesh-admin.php line 469-498)
3. Sanitizes nested array:
```php
$sanitized_data['کرافت']['60'] = floatval(180); // 180.0
$sanitized_data['کرافت']['70'] = floatval(210); // 210.0
$sanitized_data['کرافت']['80'] = floatval(240); // 240.0
```
4. Stored in DB:
```json
{
  "تحریر": {"60": 150, "70": 180, "80": 200},
  "بالک": {"60": 200, "70": 230, "80": 250, "100": 300},
  "کرافت": {"60": 180, "70": 210, "80": 240}
}
```

### Step 4: Customer creates order with کرافت paper

**Action:**
```
Order form:
- Paper Type: کرافت
- Paper Weight: 70
- Other parameters...
```

**Calculator Execution (class-tabesh-order.php line 124+):**
```php
// Input params
$paper_type = 'کرافت';
$paper_weight = '70';

// Get pricing config
$pricing_config = $this->get_pricing_config();
// This loads pricing_paper_weights from DB

// Look up paper cost
$paper_base_cost = 0;
if (isset($pricing_config['paper_weights'][$paper_type][$paper_weight])) {
    // This condition is TRUE
    $paper_base_cost = $pricing_config['paper_weights']['کرافت']['70'];
    // Result: 210
}

// Continue with calculation
$per_page_cost_bw = (210 + 200) * 1.0; // 410 per B&W page
// ... rest of calculation
```

**Expected Result:** Order calculates correctly using 210 تومان per page for کرافت 70g paper.

---

## Test Case: Remove Paper Weight

### Scenario: Admin removes weight 100 from بالک

**Step 1: Update product parameters**
```
Change: بالک=60,70,80,100
To: بالک=60,70,80
Save
```

**Stored:**
```json
{
  "بالک": [60, 70, 80]
}
```

**Step 2: View pricing tab**

**Template Execution:**
```php
foreach ($product_paper_types['بالک'] as $weight):
    // Only loops 3 times now (60, 70, 80)
    // Weight 100 field is NOT generated
```

**Result:** Weight 100 pricing field disappears from UI.

**Step 3: Old pricing data**

```json
// In DB, pricing_paper_weights still has:
{
  "بالک": {"60": 200, "70": 230, "80": 250, "100": 300}
}
```

**Orphan Handling:**
- The "100": 300 remains in database
- It's not displayed in UI (because not in product_paper_types)
- Calculator never uses it (customer can't select weight 100)
- It's harmless and will be overwritten on next save

---

## Test Case: Backward Compatibility

### Scenario: Old installation with pricing_paper_types

**Existing Data:**
```json
// pricing_paper_types (old format)
{
  "تحریر": 200,
  "بالک": 250
}

// pricing_paper_weights does NOT exist
```

**Calculator Execution:**
```php
$paper_type = 'تحریر';
$paper_weight = '70';

if (isset($pricing_config['paper_weights'][$paper_type][$paper_weight])) {
    // This is FALSE - pricing_paper_weights doesn't exist
} else {
    // Fallback to old format
    $paper_base_cost = $pricing_config['paper_types'][$paper_type] ?? 250;
    // Result: 200 (from old pricing_paper_types)
}
```

**Result:** Old pricing continues to work without issues.

---

## Validation Checklist

- [x] Syntax check passes for all modified files
- [x] Logic flow verified for adding new parameter
- [x] Logic flow verified for removing parameter
- [x] Orphan parameter handling confirmed safe
- [x] Backward compatibility verified
- [x] Data sanitization confirmed at all input points
- [x] Database storage uses JSON encoding
- [x] Calculator fallback logic in place
- [x] Weight-based lookup implemented correctly
- [x] UI auto-generation confirmed

## Security Verification

✅ **Input Sanitization:**
- Keys: `sanitize_text_field()`
- Values: `floatval()` or `sanitize_text_field()`
- Arrays: Validated with `is_array()`

✅ **Output Escaping:**
- HTML attributes: `esc_attr()`
- HTML content: `esc_html()`
- JSON encoding: `wp_json_encode()` with `JSON_UNESCAPED_UNICODE`

✅ **Database Operations:**
- Using `$wpdb->replace()` with proper array structure
- No raw SQL queries
- Prepared statements in query building

✅ **Access Control:**
- Settings page requires `manage_woocommerce` capability
- No direct file access protection in place (`ABSPATH` check)

## Conclusion

All test scenarios pass logical verification. The implementation:
1. ✅ Correctly generates dynamic pricing fields
2. ✅ Properly saves nested array structure
3. ✅ Calculator uses weight-based lookup
4. ✅ Handles orphan parameters safely
5. ✅ Maintains backward compatibility
6. ✅ Follows WordPress security best practices
