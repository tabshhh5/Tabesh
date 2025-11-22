# Tabesh Settings Migration Guide

## Overview
This guide explains how to migrate your existing Tabesh settings from the old text format to the new JSON format.

## Why Migrate?
The new version of Tabesh stores all settings in a consistent JSON format, which provides:
- Better data integrity and validation
- Consistent frontend display
- Easier debugging
- Protection against data corruption

## When to Run
You should run this migration script **ONCE** after upgrading to the new version if:
- You have an existing Tabesh installation with saved settings
- You notice that book sizes or other options are not appearing in the order form
- You are experiencing issues with settings not being saved properly

## How to Run

### Option 1: Using WP-CLI (Recommended)
If you have WP-CLI installed on your server:

```bash
cd /path/to/wordpress/wp-content/plugins/Tabesh
wp eval-file migration-convert-settings-to-json.php
```

### Option 2: Via Browser (With Safety Check)
1. Navigate to: `https://your-site.com/wp-content/plugins/Tabesh/migration-convert-settings-to-json.php?confirm=yes`
2. The script will run and display the results
3. **Important**: Remove or restrict access to this file after running

### Option 3: Direct PHP Execution
If you have SSH access:

```bash
cd /path/to/wordpress/wp-content/plugins/Tabesh
php migration-convert-settings-to-json.php
```

## What It Does
The script will:
1. Check all existing settings in the database
2. Convert comma-separated or newline-separated values to JSON arrays
3. Convert key=value formatted settings to JSON objects
4. Skip settings that are already in JSON format
5. Display a summary of migrated, skipped, and error items

## Example Output
```
=== Tabesh Settings Migration Script ===
Starting migration of legacy settings to JSON format...

Migrating simple array fields...
  ✓ book_sizes: Migrated to JSON array (5 items)
  ✓ print_types: Migrated to JSON array (3 items)
  ✓ binding_types: Already in JSON format (skipped)
  
Migrating object fields (key=value format)...
  ✓ pricing_book_sizes: Migrated to JSON object (6 items)
  ✓ pricing_paper_types: Already in JSON format (skipped)

=== Migration Complete ===
Migrated: 8
Skipped:  4
Errors:   0

All settings have been successfully migrated to JSON format.
```

## After Migration
1. Go to WordPress Admin > Tabesh > Settings
2. Verify that all your settings are displaying correctly
3. Click "Save Settings" on each tab to ensure everything is working
4. Test the order form to confirm all options appear correctly

## Troubleshooting

### Error: "Could not locate WordPress"
- Make sure you're running the script from the correct directory
- Use WP-CLI instead: `wp eval-file migration-convert-settings-to-json.php`

### Error: "Settings table does not exist"
- Activate the Tabesh plugin first
- Check that the database tables were created properly

### Settings still not appearing
1. Clear your browser cache
2. Go to Settings and click "Save Settings" on each tab
3. Check the browser console for JavaScript errors
4. Contact support if issues persist

## Safety
- The script is **safe to run multiple times** - it will skip already-migrated settings
- It does not delete any data, only converts the format
- A backup is recommended but not required

## Support
If you encounter any issues during migration, please:
1. Check the WordPress debug log
2. Run the script again to see if it resolves
3. Contact support with the error output

## Technical Details
The migration converts:
- **Simple arrays**: `book_sizes`, `print_types`, `binding_types`, `license_types`, `cover_paper_weights`, `lamination_types`, `extras`
- **Object fields**: `paper_types`, `pricing_book_sizes`, `pricing_paper_types`, `pricing_lamination_costs`, `pricing_binding_costs`, `pricing_options_costs`

### Old Format Example
```
book_sizes: "A5, A4, رقعی, وزیری"
pricing_book_sizes: "A5=1.0\nA4=1.5\nرقعی=1.1"
```

### New Format Example
```json
book_sizes: ["A5","A4","رقعی","وزیری"]
pricing_book_sizes: {"A5":1,"A4":1.5,"رقعی":1.1}
```
