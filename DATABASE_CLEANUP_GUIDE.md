# Database Cleanup Guide - Customer Files Panel Removal

## Overview

As part of version X.X.X, the customer files panel functionality has been completely removed from the Tabesh plugin. This guide explains how to clean up the related database tables and settings.

## What Was Removed

The following database tables are no longer used by the plugin:
- `wp_tabesh_files` - Stored uploaded file metadata
- `wp_tabesh_file_versions` - Tracked file version history
- `wp_tabesh_upload_tasks` - Managed file upload tasks
- `wp_tabesh_document_metadata` - Stored document metadata
- `wp_tabesh_file_comments` - Stored comments on files

The following settings have been removed:
- All file upload settings (size limits, retention, etc.)
- File display and error handling settings
- File backup and security settings

## Database Cleanup

### ⚠️ IMPORTANT: Backup First!

**Before performing any database cleanup, always create a full backup of your WordPress database!**

### Method 1: Automatic Cleanup (Recommended)

You can call the cleanup method programmatically:

```php
// In WordPress admin or via WP-CLI
if (class_exists('Tabesh_Install')) {
    $result = Tabesh_Install::drop_customer_files_tables();
    
    if ($result['success']) {
        echo "✅ Successfully dropped " . count($result['dropped']) . " tables\n";
        foreach ($result['dropped'] as $table) {
            echo "  - " . $table . "\n";
        }
    } else {
        echo "❌ Errors occurred:\n";
        foreach ($result['errors'] as $table) {
            echo "  - Failed to drop: " . $table . "\n";
        }
    }
}
```

### Method 2: Manual SQL Cleanup

If you prefer to manually clean up the database, you can run the following SQL commands in phpMyAdmin or via WP-CLI:

```sql
-- Drop file-related tables (adjust wp_ prefix if needed)
DROP TABLE IF EXISTS `wp_tabesh_files`;
DROP TABLE IF EXISTS `wp_tabesh_file_versions`;
DROP TABLE IF EXISTS `wp_tabesh_upload_tasks`;
DROP TABLE IF EXISTS `wp_tabesh_document_metadata`;
DROP TABLE IF EXISTS `wp_tabesh_file_comments`;

-- Clean up file-related settings
DELETE FROM `wp_tabesh_settings` WHERE `setting_key` IN (
    'file_max_size_pdf',
    'file_max_size_image',
    'file_max_size_document',
    'file_max_size_archive',
    'file_min_dpi',
    'file_retention_days',
    'file_correction_fee',
    'file_download_link_expiry',
    'file_delete_incomplete_after',
    'file_reupload_hours',
    'file_backup_location',
    'file_error_display_type',
    'file_encrypt_filenames',
    'file_enable_ip_restriction',
    'file_auto_backup_enabled',
    'file_show_progress_bar',
    'allow_reupload_approved'
);
```

### Method 3: WP-CLI

If you have WP-CLI installed, you can use the following command:

```bash
# Create a temporary PHP file to run the cleanup
wp eval 'if (class_exists("Tabesh_Install")) { $result = Tabesh_Install::drop_customer_files_tables(); var_dump($result); }'
```

## File System Cleanup (Optional)

The plugin may have created uploaded files in the following directory:
```
wp-content/uploads/tabesh-files/
```

You may optionally delete this directory and its contents if you're certain you no longer need these files. **Make sure to backup these files before deletion!**

```bash
# Backup first
tar -czf tabesh-files-backup-$(date +%Y%m%d).tar.gz wp-content/uploads/tabesh-files/

# Then remove (optional)
rm -rf wp-content/uploads/tabesh-files/
```

## Verification

After cleanup, verify that:

1. ✅ The plugin still loads without errors
2. ✅ Order forms work correctly
3. ✅ User orders display properly
4. ✅ Admin dashboard functions normally
5. ✅ Staff panel operates correctly

Check WordPress error logs for any issues:
- Location: `wp-content/debug.log` (if `WP_DEBUG_LOG` is enabled)

## Rollback

If you need to rollback the changes:

1. Restore your database backup
2. Restore any deleted files from backup
3. Revert to the previous version of the plugin that included the customer files panel

## Support

If you encounter any issues during cleanup:

1. Check the WordPress error logs (`wp-content/debug.log`)
2. Verify database user permissions
3. Ensure you're using the correct table prefix
4. Contact support with error details

## Future File Upload Implementation

This removal prepares the codebase for a new, improved file upload system in a future release. The new system will:
- Have better performance
- Include enhanced security features
- Provide improved user experience
- Support modern upload protocols

Stay tuned for updates!
