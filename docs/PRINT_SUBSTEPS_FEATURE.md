# Print Substeps Feature Documentation

## Overview

The Print Substeps feature provides detailed tracking of the printing process for orders in "processing" status. This feature allows staff to track individual stages of book printing, providing better visibility and control over the production workflow.

## Architecture

### Database Table

**Table Name:** `wp_tabesh_print_substeps`

**Structure:**
```sql
CREATE TABLE `wp_tabesh_print_substeps` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `substep_key` varchar(50) NOT NULL,
  `substep_title` varchar(255) NOT NULL,
  `substep_details` text,
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` bigint(20) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `substep_key` (`substep_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Classes

**File:** `includes/handlers/class-tabesh-print-substeps.php`

**Class:** `Tabesh_Print_Substeps`

**Methods:**
- `get_order_substeps($order_id)` - Retrieve all substeps for an order
- `generate_substeps_for_order($order_id)` - Auto-generate substeps from order specifications
- `update_substep_status($substep_id, $is_completed)` - Update completion status of a substep
- `calculate_print_progress($order_id)` - Calculate percentage of completed substeps
- `are_all_substeps_completed($order_id)` - Check if all substeps are done
- `update_substep_rest($request)` - REST API endpoint handler
- `delete_order_substeps($order_id)` - Remove substeps for an order

## Substep Types

Substeps are automatically generated based on order specifications:

1. **چاپ جلد (Cover Printing)**
   - Generated if: `cover_paper_weight` is not empty
   - Details: Shows paper weight in grams

2. **سلفون جلد (Cover Lamination)**
   - Generated if: `lamination_type` is not empty and not "بدون سلفون"
   - Details: Shows lamination type (براق/مات)

3. **چاپ متن کتاب (Book Content Printing)**
   - Generated if: Both `paper_type` and `paper_weight` are not empty
   - Details: Shows paper type and weight

4. **صحافی (Binding)**
   - Generated if: `binding_type` is not empty
   - Details: Shows binding type

5. **خدمات اضافی (Additional Services)**
   - Generated if: `extras` array is not empty
   - Details: Lists all extra services requested

## User Interface

### Staff Panel Display

The substeps section appears in the staff panel only when:
- The order status is "processing"
- At least one substep exists for the order

**Location:** After the status stepper, before the status update section

**Features:**
- Progress badge showing completion percentage
- Checkboxes for marking substeps as complete
- Visual indicators for completed substeps
- Real-time AJAX updates without page reload

### CSS Classes

**Main Classes:**
- `.print-substeps-section` - Container for the entire section
- `.print-substeps-list` - List container for substeps
- `.print-substep-item` - Individual substep card
- `.print-substep-item.completed` - Completed substep style
- `.substep-checkbox` - Checkbox input
- `.substep-title` - Substep title text
- `.substep-details` - Substep detail text
- `.progress-badge` - Progress percentage badge
- `.substep-completed-badge` - "✓ انجام شد" badge

### JavaScript Events

**File:** `assets/js/staff-panel.js`

**Event Handlers:**
- Checkbox change event on `.substep-checkbox`
- AJAX call to `/wp-json/tabesh/v1/print-substeps/update`
- Automatic page reload when all substeps completed

## REST API

### Endpoint

**URL:** `/wp-json/tabesh/v1/print-substeps/update`

**Method:** POST

**Authentication:** Requires `edit_shop_orders` capability

**Request Body:**
```json
{
  "substep_id": 123,
  "is_completed": true
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "وضعیت با موفقیت بهروزرسانی شد",
  "data": {
    "progress": 75,
    "all_completed": false
  }
}
```

**Response (All Completed):**
```json
{
  "success": true,
  "message": "وضعیت با موفقیت بهروزرسانی شد",
  "data": {
    "progress": 100,
    "all_completed": true
  }
}
```

## Automatic Status Changes

When all substeps are completed:
1. Order status automatically changes from "processing" to "ready"
2. Status change is logged in `wp_tabesh_logs` table
3. Notification is sent to the customer
4. Staff panel automatically reloads to show new status

## Installation

The feature is automatically installed when:
1. Plugin is activated (calls `Tabesh_Install::update_database_schema()`)
2. Database version is less than 1.3.0

The migration creates the `wp_tabesh_print_substeps` table if it doesn't exist.

## Security

### Input Validation
- All substep IDs are validated as integers
- Boolean values are properly sanitized
- Order ownership and permissions are verified

### Output Escaping
- All displayed text uses `esc_html()`
- All attributes use `esc_attr()`
- JSON responses are properly structured

### Permissions
- Only users with `edit_shop_orders` capability can update substeps
- REST API nonce verification required
- Order data is accessible only to authorized staff

## Customer View

**Important:** Customers do NOT see the substeps section in their orders panel.

Customers only see the main order status:
- When status is "processing" → They see "در حال چاپ"
- When all substeps complete → Status changes to "ready" → They see "آماده تحویل"

## Edge Cases

### Missing Order Data
If an order doesn't have certain fields (e.g., no `cover_paper_weight`), those substeps are not generated.

### Order Status Changes
- Substeps are only displayed when status is "processing"
- When status changes away from "processing", substeps remain in database
- Substeps can be deleted using `delete_order_substeps($order_id)` if needed

### Existing Orders
When an order in "processing" status is viewed for the first time after this feature is deployed, substeps are automatically generated on-the-fly.

## Testing Checklist

✅ PHP syntax validation passed
✅ JavaScript syntax validation passed
✅ Database table structure validated
✅ REST API endpoint registered
✅ CSS styles added without conflicts
✅ Event handlers bound correctly
✅ Security measures implemented

### Manual Testing Required
- [ ] Create a test order with all fields populated
- [ ] Change order status to "processing"
- [ ] Verify substeps appear in staff panel
- [ ] Check each substep checkbox
- [ ] Verify progress percentage updates
- [ ] Confirm automatic status change when all complete
- [ ] Test with orders missing some fields (e.g., no lamination)
- [ ] Verify customer panel shows no substeps
- [ ] Test with multiple staff members updating simultaneously

## Troubleshooting

### Substeps Not Appearing
1. Check order status is "processing"
2. Verify database table exists: `SHOW TABLES LIKE 'wp_tabesh_print_substeps'`
3. Check for JavaScript errors in browser console
4. Verify REST API endpoint is accessible

### Progress Not Updating
1. Check browser console for AJAX errors
2. Verify nonce is valid (check X-WP-Nonce header)
3. Confirm user has `edit_shop_orders` capability
4. Check WordPress error log for PHP errors

### Status Not Auto-Changing
1. Verify all substeps are checked
2. Check `wp_tabesh_logs` table for status change entry
3. Confirm notification system is working
4. Check for errors in WordPress error log

## Future Enhancements

Potential improvements for future versions:
- Allow admins to customize which substeps are generated
- Add time tracking for each substep
- Display substep history and completion times
- Add substep dependencies (e.g., can't bind before printing)
- Export substep completion data for analytics
- Add notifications when specific substeps complete

## Version History

- **v1.3.0** - Initial implementation of print substeps feature
  - Added database table
  - Created handler class
  - Updated staff panel UI
  - Added REST API endpoint
  - Implemented automatic status changes
