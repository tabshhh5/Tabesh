# Fix for Additional Services Cost Calculation

## Problem
After selecting additional services (extras) during order placement, the cost was not being calculated or displayed properly to users.

## Root Cause
The issue could occur in several scenarios:
1. **Pricing configuration not loaded properly** - If the pricing_options_costs array wasn't properly loaded from the database
2. **Silent failures** - When extras weren't found in pricing config, the code would silently default to 0 without logging
3. **No visual feedback** - The price breakdown didn't show extras cost separately, making it unclear if extras were included

## Solution Implemented

### Backend Changes (class-tabesh-order.php)
1. **Enhanced error handling:**
   - Added validation to ensure `pricing_config['options_costs']` exists and is an array
   - Added defensive checks for empty or invalid extras
   - More detailed logging for debugging (only when WP_DEBUG is enabled)

2. **Better tracking:**
   - Added `$options_breakdown` array to track individual option costs
   - Included options_breakdown in the return data for transparency

3. **Improved logging:**
   - Logs available pricing keys vs. requested extras
   - Warns when an extra is not found in pricing config
   - Logs the complete calculation breakdown

### Frontend Changes (frontend.js)
1. **Visual feedback:**
   - Added display of extras cost as a separate line item
   - Dynamically creates "هزینه خدمات اضافی" (Additional Services Cost) row
   - Shows/hides the row based on whether extras were selected

2. **Debugging:**
   - Logs extras breakdown to browser console
   - Makes it easy to verify extras cost is being calculated

## Testing
Comprehensive tests were created to verify the fix:
- ✅ No extras selected (cost should be 0)
- ✅ Single extra selected (cost should match pricing)
- ✅ Multiple extras selected (costs should sum correctly)
- ✅ All extras selected (all costs summed)
- ✅ Invalid extra name (should default to 0 with warning)

All tests passed successfully.

## Usage
After this fix:
1. Extras cost is properly calculated from pricing_options_costs configuration
2. Extras cost is included in the total price
3. Extras cost is displayed separately in the price breakdown
4. Debug logs help diagnose configuration issues

## Configuration
Ensure your pricing_options_costs is configured in **تابش > تنظیمات > قیمت‌گذاری** (Tabesh > Settings > Pricing tab):

**Format:** Each line contains one option in the format `name=cost` (no spaces around the equals sign)

```
لب گرد=1000
خط تا=500
شیرینک=1500
سوراخ=300
شماره گذاری=800
```

**Important:** The extra names in this pricing configuration MUST exactly match the extra names in the **خدمات اضافی** (Extras) setting found in **تابش > تنظیمات > محصول** (Tabesh > Settings > Product tab).

**Example:**
- If Extras setting contains: `لب گرد, خط تا, شیرینک`
- Then pricing_options_costs must have: `لب گرد=1000`, `خط تا=500`, `شیرینک=1500`
- Names must match exactly (including spaces and Persian characters)

**If names don't match:** The system will log a warning (when WP_DEBUG is enabled) and default the cost to 0 for that extra. The order will still process but without the unmatched extra's cost.

## Debugging
To enable detailed logging:
1. Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
2. Check `wp-content/debug.log` for Tabesh log messages
3. Check browser console for extras breakdown

## Backwards Compatibility
This fix is fully backwards compatible:
- Existing pricing configurations continue to work
- If pricing_options_costs is not configured, it falls back to defaults
- Empty extras arrays are handled gracefully
- Invalid extra names default to 0 without breaking calculation
