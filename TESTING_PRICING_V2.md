# Testing the New Pricing Engine V2

## Quick Start Testing Guide

### Step 1: Enable the New Pricing Engine

1. Create a WordPress page
2. Add the shortcode: `[tabesh_product_pricing]`
3. Visit the page (you must be logged in as an administrator)
4. Click the "فعال‌سازی موتور جدید" (Enable New Engine) button

### Step 2: Configure Pricing for a Book Size

1. Select a book size from the tabs (e.g., A5, A4, رقعی)
2. Fill in the pricing matrix:
   - **Per-page costs**: For each paper type and weight, enter costs for B&W and color printing
   - **Binding costs**: Enter costs for different binding types
   - **Cover cost**: Enter the base cover cost
   - **Extras**: Configure additional services pricing
   - **Profit margin**: Set your profit margin percentage (optional)
3. Click "ذخیره تنظیمات قیمت‌گذاری" (Save Pricing Settings)

### Step 3: Test Price Calculation

You can test the pricing calculation using the REST API or by submitting an order through the frontend form.

#### REST API Test (using browser console or curl)

```javascript
// In browser console (while logged in to WordPress):
fetch('/wp-json/tabesh/v1/calculate-price', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': tabeshData.nonce // This is available on pages with Tabesh forms
    },
    body: JSON.stringify({
        book_size: 'A5',
        paper_type: 'تحریر',
        paper_weight: '70',
        print_type: 'bw',
        page_count_bw: 100,
        page_count_color: 0,
        quantity: 50,
        binding_type: 'شومیز',
        extras: []
    })
})
.then(response => response.json())
.then(data => console.log('Price calculation result:', data));
```

#### Using curl

```bash
curl -X POST https://your-site.com/wp-json/tabesh/v1/calculate-price \
  -H "Content-Type: application/json" \
  -d '{
    "book_size": "A5",
    "paper_type": "تحریر",
    "paper_weight": "70",
    "print_type": "bw",
    "page_count_bw": 100,
    "page_count_color": 0,
    "quantity": 50,
    "binding_type": "شومیز",
    "extras": []
  }'
```

### Step 4: Verify the Response

The response should include:
- `pricing_engine`: Should be "v2_matrix" when V2 is enabled
- `total_price`: Final calculated price
- `breakdown`: Detailed breakdown of costs

Example response:
```json
{
    "price_per_book": 53000,
    "quantity": 50,
    "subtotal": 2650000,
    "discount_percent": 5,
    "discount_amount": 132500,
    "total_after_discount": 2517500,
    "profit_margin_percent": 0,
    "profit_amount": 0,
    "total_price": 2517500,
    "page_count_total": 100,
    "pricing_engine": "v2_matrix",
    "breakdown": {
        "book_size": "A5",
        "pages_cost_bw": 38000,
        "pages_cost_color": 0,
        "total_pages_cost": 38000,
        "cover_cost": 8000,
        "binding_cost": 3000,
        "extras_cost": 0,
        "per_page_cost_bw": 380,
        "per_page_cost_color": 980
    }
}
```

## Testing Parameter Restrictions

### Setup Restrictions

1. Go to the pricing configuration page
2. Select a book size
3. Scroll to "محدودیت‌ها (ممنوع‌سازی پارامترها)" section
4. Check some paper types or binding types to forbid them
5. Save the settings

### Test Restriction

Try to calculate a price with a forbidden combination:

```javascript
fetch('/wp-json/tabesh/v1/calculate-price', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': tabeshData.nonce
    },
    body: JSON.stringify({
        book_size: 'A5',
        paper_type: 'تحریر',  // If this is forbidden
        paper_weight: '70',
        print_type: 'bw',
        page_count_bw: 100,
        page_count_color: 0,
        quantity: 50,
        binding_type: 'شومیز',
        extras: []
    })
})
.then(response => response.json())
.then(data => {
    if (data.error) {
        console.log('Restriction working! Error message:', data.message);
    }
});
```

Expected response when restriction is in place:
```json
{
    "error": true,
    "message": "کاغذ تحریر برای قطع A5 مجاز نیست"
}
```

## Testing Backward Compatibility

### Disable V2 and Test V1

1. Go to the pricing configuration page
2. Click "بازگشت به موتور قدیمی" (Return to Old Engine)
3. Submit the same price calculation request
4. Verify the `pricing_engine` field in response is NOT "v2_matrix"

This ensures that existing orders and calculations still work with the legacy system.

## Checklist for Testing

- [ ] Admin page loads correctly with `[tabesh_product_pricing]` shortcode
- [ ] Engine toggle button works (enable/disable V2)
- [ ] Can configure pricing for different book sizes
- [ ] Pricing data saves to database correctly
- [ ] Price calculation returns correct values with V2 enabled
- [ ] Parameter restrictions work as expected
- [ ] Switching back to V1 engine works
- [ ] Existing orders still calculate correctly with V1
- [ ] All existing shortcodes still function
- [ ] No JavaScript errors in browser console
- [ ] No PHP errors in error log

## Debugging

### Enable WordPress Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs in `wp-content/debug.log` for any errors.

### Check Database

Verify pricing matrix is saved:
```sql
SELECT * FROM wp_tabesh_settings WHERE setting_key LIKE 'pricing_matrix_%';
```

Verify engine flag:
```sql
SELECT * FROM wp_tabesh_settings WHERE setting_key = 'pricing_engine_v2_enabled';
```

### Common Issues

**Issue: "شما دسترسی به این بخش را ندارید" (Access denied)**
- Make sure you're logged in as an administrator with `manage_woocommerce` capability

**Issue: Pricing calculation returns error**
- Check that pricing matrix is configured for the book size
- Verify all required parameters are provided
- Check debug log for detailed error messages

**Issue: Changes not saving**
- Check database write permissions
- Verify nonce is being generated correctly
- Check for JavaScript errors in console

## Next Steps

After successful testing:
1. Configure pricing for all book sizes you use
2. Test with real order scenarios
3. Train staff on the new system
4. Monitor pricing calculations for accuracy
5. Keep V1 as fallback until fully confident in V2
