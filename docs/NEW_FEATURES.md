# Tabesh New Features Documentation

This document describes the three new features added to the Tabesh plugin: Import/Export, Data Cleanup, and Hidden Orders.

## Table of Contents
1. [Hidden Orders](#hidden-orders)
2. [Import/Export](#importexport)
3. [Data Cleanup](#data-cleanup)
4. [REST API Reference](#rest-api-reference)
5. [Security](#security)
6. [Usage Examples](#usage-examples)

---

## Hidden Orders

### Overview
Hidden Orders allows administrators to mark specific orders as hidden from regular users while keeping them visible to admins and staff. This is useful for internal orders, test orders, or sensitive orders that should not appear in customer panels.

### How It Works
Orders are marked as hidden by including the `@WAR#` tag in the order's `notes` field. This tag is case-insensitive and can be added anywhere in the notes.

### Features
- **Automatic Detection**: Orders with `@WAR#` tag are automatically hidden
- **Role-Based Visibility**: Only admins (`manage_woocommerce`) and staff (`tabesh_staff_panel`) can see hidden orders
- **Panel Filtering**: Hidden orders are filtered out from:
  - User orders panel
  - Upload panel (file upload interface)
- **Admin Control**: Admins can mark/unmark orders via REST API

### Class: `Tabesh_Hidden_Orders`

#### Methods

**`is_order_hidden( $order_id )`**
```php
// Check if an order is hidden
$is_hidden = Tabesh()->hidden_orders->is_order_hidden( 123 );
```

**`is_order_visible_to_user( $order_id, $user_id )`**
```php
// Check if user can see this order
$visible = Tabesh()->hidden_orders->is_order_visible_to_user( 123, 456 );
```

**`get_hidden_orders( $args )`**
```php
// Get list of hidden orders
$hidden_orders = Tabesh()->hidden_orders->get_hidden_orders( array(
    'limit' => 50,
    'offset' => 0,
    'user_id' => 123,
    'status' => 'completed'
) );
```

**`mark_order_hidden( $order_id, $note = '' )`**
```php
// Mark order as hidden
$result = Tabesh()->hidden_orders->mark_order_hidden( 123, 'Internal test order' );
```

**`unmark_order_hidden( $order_id )`**
```php
// Remove hidden marker
$result = Tabesh()->hidden_orders->unmark_order_hidden( 123 );
```

### WordPress Filters

**`tabesh_user_orders_query`**
Filters the WHERE clause for user orders query.

**`tabesh_order_visible_to_user`**
Determines if an order is visible to a specific user.

**`tabesh_upload_panel_orders`**
Filters the orders list in the upload panel.

---

## Import/Export

### Overview
The Import/Export feature allows administrators to backup and restore Tabesh data including orders, settings, files, users, and logs. Data can be exported in JSON or ZIP format with optional physical file inclusion.

### Features

#### Export Features
- **Selective Export**: Choose what to export (orders, settings, files, users, logs)
- **Date Filtering**: Export data within a specific date range
- **User Filtering**: Export data for specific users
- **Format Options**:
  - **JSON**: Lightweight, data only
  - **ZIP**: Includes physical files
- **Physical Files**: Option to include actual uploaded files
- **Auto Cleanup**: Automatic cleanup of old export files

#### Import Features
- **Format Support**: Import from JSON or ZIP files
- **Data Validation**: Validates data structure and version compatibility
- **Flexible Import**: Choose what to import
- **Conflict Resolution**:
  - **Skip Existing**: Ignore duplicate records
  - **Update Existing**: Overwrite existing records
- **Automatic Extraction**: ZIP files are automatically extracted

### Class: `Tabesh_Import_Export`

#### Export Methods

**`export_data( $options )`**
```php
// Export all orders and settings to JSON
$result = Tabesh()->import_export->export_data( array(
    'include_orders' => true,
    'include_settings' => true,
    'include_files_metadata' => true,
    'include_physical_files' => false,
    'include_users' => false,
    'include_logs' => false,
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'user_id' => null,
    'format' => 'json'
) );

// Result:
// array(
//     'success' => true,
//     'message' => 'برونریزی با موفقیت انجام شد',
//     'filepath' => '/path/to/export.json',
//     'filename' => 'tabesh-export-2024-12-07_15-30-00.json',
//     'filesize' => 1234567
// )
```

**Export with Physical Files (ZIP)**
```php
$result = Tabesh()->import_export->export_data( array(
    'include_orders' => true,
    'include_files_metadata' => true,
    'include_physical_files' => true,
    'format' => 'zip'
) );
```

#### Import Methods

**`import_data( $filepath, $options )`**
```php
// Import from JSON file
$result = Tabesh()->import_export->import_data( '/path/to/import.json', array(
    'include_orders' => true,
    'include_settings' => true,
    'skip_existing' => true,
    'update_existing' => false
) );

// Result:
// array(
//     'success' => true,
//     'message' => 'درونریزی با موفقیت انجام شد',
//     'stats' => array(
//         'orders_imported' => 150,
//         'settings_imported' => 25,
//         'files_imported' => 300,
//         'users_imported' => 0,
//         'logs_imported' => 0
//     )
// )
```

#### Utility Methods

**`cleanup_old_exports( $days = 7 )`**
```php
// Delete export files older than 7 days
$deleted = Tabesh()->import_export->cleanup_old_exports( 7 );
```

**`get_export_dir()`**
```php
// Get export directory path
$dir = Tabesh()->import_export->get_export_dir();
// Returns: /wp-content/uploads/tabesh-exports/
```

### Export File Structure

#### JSON Format
```json
{
    "version": "1.0.3",
    "exported_at": "2024-12-07 15:30:00",
    "exported_by": 1,
    "options": { ... },
    "orders": [ ... ],
    "settings": [ ... ],
    "files_metadata": [ ... ],
    "users": [ ... ],
    "logs": [ ... ]
}
```

#### ZIP Format
```
tabesh-export-2024-12-07_15-30-00.zip
├── data.json (metadata)
└── files/
    ├── file1.pdf
    ├── file2.jpg
    └── ...
```

---

## Data Cleanup

### Overview
The Data Cleanup feature provides comprehensive tools for cleaning up Tabesh data, including factory reset, selective cleanup, and safe deletion of orders and user data with support for local and FTP file deletion.

### Features

#### Factory Reset
- Delete all orders, files, logs
- Optional: Keep settings
- Reset auto-increment counters
- Delete upload directory
- Delete files from FTP

#### Selective Cleanup
- Delete by date range
- Delete by user
- Delete by order status
- Delete specific settings
- Choose what to clean (orders, files, logs, settings)

#### User Data Cleanup
- Delete all user orders
- Delete all user files (local + FTP)
- Delete user logs
- Optional: Delete user account

#### Order Cleanup
- Delete complete order
- Delete associated files (local + FTP)
- Delete order logs
- Delete upload tasks

### Class: `Tabesh_Data_Cleanup`

#### Factory Reset

**`factory_reset( $keep_settings = false )`**
```php
// Reset everything except settings
$result = Tabesh()->data_cleanup->factory_reset( true );

// Result:
// array(
//     'success' => true,
//     'message' => 'بازنشانی کارخانه با موفقیت انجام شد',
//     'stats' => array(
//         'orders_deleted' => 500,
//         'files_deleted' => 1200,
//         'logs_deleted' => 3000,
//         'settings_deleted' => 0,
//         'physical_files_deleted' => 1150
//     )
// )
```

#### Selective Cleanup

**`selective_cleanup( $options )`**
```php
// Delete old completed orders
$result = Tabesh()->data_cleanup->selective_cleanup( array(
    'cleanup_orders' => true,
    'cleanup_files' => true,
    'cleanup_logs' => true,
    'cleanup_settings' => false,
    'date_from' => '2023-01-01',
    'date_to' => '2023-12-31',
    'user_id' => null,
    'status' => 'completed',
    'setting_keys' => array()
) );
```

#### User Data Cleanup

**`delete_user_data( $user_id, $delete_account = false )`**
```php
// Delete all data for user 123, keep account
$result = Tabesh()->data_cleanup->delete_user_data( 123, false );

// Delete all data and remove account
$result = Tabesh()->data_cleanup->delete_user_data( 123, true );
```

#### Order Cleanup

**`delete_order_completely( $order_id )`**
```php
// Delete order with all associated data
$result = Tabesh()->data_cleanup->delete_order_completely( 456 );

// Result:
// array(
//     'success' => true,
//     'message' => 'سفارش با موفقیت حذف شد',
//     'stats' => array(
//         'order_deleted' => true,
//         'files_deleted' => 15,
//         'logs_deleted' => 30,
//         'physical_files_deleted' => 14
//     )
// )
```

### Protected Settings
The following settings are protected from deletion:
- `ftp_username`
- `ftp_password`
- `ftp_host`
- `sms_username`
- `sms_password`

### File Deletion
- **Local Files**: Deleted from `wp-content/uploads/tabesh-files/`
- **FTP Files**: Deleted from FTP server if FTP is enabled
- **Safe Deletion**: Uses `@unlink()` to suppress errors
- **Directory Cleanup**: Recursively deletes empty directories

---

## REST API Reference

All endpoints require admin privileges (`can_manage_admin` capability).

### Hidden Orders Endpoints

#### Get Hidden Orders
```http
GET /wp-json/tabesh/v1/hidden-orders
```

**Parameters:**
- `limit` (int, optional): Maximum results (default: 50, max: 100)
- `offset` (int, optional): Offset for pagination (default: 0)
- `user_id` (int, optional): Filter by user
- `status` (string, optional): Filter by status
- `order_by` (string, optional): Order by field (default: created_at)
- `order` (string, optional): Sort direction (ASC/DESC, default: DESC)

**Response:**
```json
{
    "success": true,
    "orders": [ ... ],
    "count": 15
}
```

#### Mark Order as Hidden
```http
POST /wp-json/tabesh/v1/hidden-orders/{order_id}/mark
Content-Type: application/json

{
    "note": "Internal test order"
}
```

#### Unmark Order
```http
POST /wp-json/tabesh/v1/hidden-orders/{order_id}/unmark
```

### Import/Export Endpoints

#### Export Data
```http
POST /wp-json/tabesh/v1/export
Content-Type: application/json

{
    "include_orders": true,
    "include_settings": true,
    "include_files_metadata": true,
    "include_physical_files": false,
    "include_users": false,
    "include_logs": false,
    "date_from": "2024-01-01",
    "date_to": "2024-12-31",
    "user_id": null,
    "format": "json"
}
```

**Response:**
```json
{
    "success": true,
    "message": "برونریزی با موفقیت انجام شد",
    "filepath": "/path/to/export.json",
    "filename": "tabesh-export-2024-12-07_15-30-00.json",
    "filesize": 1234567
}
```

#### Import Data
```http
POST /wp-json/tabesh/v1/import
Content-Type: multipart/form-data

file: <JSON or ZIP file>
include_orders: true
include_settings: true
skip_existing: true
update_existing: false
```

### Data Cleanup Endpoints

#### Factory Reset
```http
POST /wp-json/tabesh/v1/cleanup/factory-reset
Content-Type: application/json

{
    "keep_settings": false
}
```

#### Selective Cleanup
```http
POST /wp-json/tabesh/v1/cleanup/selective
Content-Type: application/json

{
    "cleanup_orders": true,
    "cleanup_files": true,
    "cleanup_logs": false,
    "date_from": "2023-01-01",
    "date_to": "2023-12-31",
    "status": "completed"
}
```

#### Delete User Data
```http
DELETE /wp-json/tabesh/v1/cleanup/user/{user_id}
Content-Type: application/json

{
    "delete_account": false
}
```

#### Delete Order
```http
DELETE /wp-json/tabesh/v1/cleanup/order/{order_id}
```

---

## Security

### Access Control
All new features are restricted to administrators only:
- Requires `manage_woocommerce` capability
- Checked via `can_manage_admin()` method
- No public access allowed

### Input Validation
- All text inputs sanitized with `sanitize_text_field()`
- Textarea inputs sanitized with `sanitize_textarea_field()`
- Numeric inputs validated with `intval()`
- Setting keys validated with regex: `/^[a-zA-Z0-9_-]+$/`
- Protected settings whitelist prevents deletion of critical settings

### Database Security
- All queries use `$wpdb->prepare()` with placeholders
- No direct user input in SQL queries
- Two-step queries avoid MySQL subquery limitations

### File Operations
- Safe deletion with `@unlink()` error suppression
- Path validation before file operations
- FTP operations wrapped in try-catch
- Directory traversal protection

### ABSPATH Protection
All class files check for WordPress environment:
```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## Usage Examples

### Example 1: Export All Data for Backup
```php
// Create full backup including files
$result = Tabesh()->import_export->export_data( array(
    'include_orders' => true,
    'include_settings' => true,
    'include_files_metadata' => true,
    'include_physical_files' => true,
    'include_users' => true,
    'include_logs' => true,
    'format' => 'zip'
) );

if ( $result['success'] ) {
    echo 'Backup created: ' . $result['filename'];
}
```

### Example 2: Hide Test Orders
```php
// Mark test orders as hidden
$test_order_ids = array( 101, 102, 103 );

foreach ( $test_order_ids as $order_id ) {
    Tabesh()->hidden_orders->mark_order_hidden( $order_id, 'Test order' );
}
```

### Example 3: Clean Up Old Data
```php
// Delete old completed orders from 2023
$result = Tabesh()->data_cleanup->selective_cleanup( array(
    'cleanup_orders' => true,
    'cleanup_files' => true,
    'cleanup_logs' => true,
    'date_from' => '2023-01-01',
    'date_to' => '2023-12-31',
    'status' => 'completed'
) );

echo 'Deleted: ' . $result['stats']['orders_deleted'] . ' orders';
```

### Example 4: Remove Customer Data (GDPR)
```php
// Delete all user data per GDPR request
$user_id = 456;

$result = Tabesh()->data_cleanup->delete_user_data( $user_id, true );

if ( $result['success'] ) {
    echo 'User data deleted successfully';
}
```

### Example 5: Monthly Export for Accounting
```php
// Export last month's completed orders
$last_month_start = date( 'Y-m-01', strtotime( '-1 month' ) );
$last_month_end = date( 'Y-m-t', strtotime( '-1 month' ) );

$result = Tabesh()->import_export->export_data( array(
    'include_orders' => true,
    'include_files_metadata' => false,
    'date_from' => $last_month_start,
    'date_to' => $last_month_end,
    'format' => 'json'
) );
```

---

## Troubleshooting

### Export Issues

**Problem**: Export file not created
- Check write permissions on `wp-content/uploads/`
- Ensure disk space is available
- Check PHP memory limit for large exports

**Problem**: ZIP export fails
- Verify ZipArchive PHP extension is installed
- Check that source files exist in upload directory

### Import Issues

**Problem**: Import fails with validation error
- Verify file format (JSON or ZIP)
- Check that export version matches plugin version
- Ensure file is not corrupted

**Problem**: Duplicate records during import
- Use `skip_existing: true` to skip duplicates
- Use `update_existing: true` to update duplicates

### Cleanup Issues

**Problem**: FTP files not deleted
- Verify FTP settings are correct
- Check FTP server is accessible
- Ensure FTP user has delete permissions

**Problem**: Some files remain after cleanup
- Check file permissions
- Verify files are not locked by another process
- Check error logs for specific failures

### Hidden Orders Issues

**Problem**: Hidden orders still visible
- Verify `@WAR#` tag is in notes field
- Check user has correct permissions
- Clear any caching plugins

---

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Enable `WP_DEBUG` and check error logs
3. Review the `wp_tabesh_logs` table for action logs
4. Contact plugin support with error details

---

## Changelog

### Version 1.0.3 (2024-12-07)
- Added Hidden Orders feature
- Added Import/Export feature
- Added Data Cleanup feature
- Added 9 new REST API endpoints
- Added comprehensive security measures
- Added bilingual support (Persian/English)
