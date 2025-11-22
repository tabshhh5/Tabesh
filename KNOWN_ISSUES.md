# Known Issues and Considerations

## File Upload Settings Conflict

### Issue Description

The removal process has identified a potential conflict between removed functionality and remaining features:

**Removed:** Customer Files Panel (`[customer_files_panel]` shortcode)
**Kept:** File Upload shortcode (`[tabesh_file_upload]`)

### The Conflict

Both shortcodes share some common file upload infrastructure:
- File size limits (`file_max_size_pdf`, `file_max_size_image`, etc.)
- File retention settings  (`file_retention_days`)
- FTP upload settings

### Current State

As per the problem statement requirements, file upload settings have been removed from the `$scalar_fields` and `$checkbox_fields` arrays in `class-tabesh-admin.php`. This means:

✅ Settings are **removed from save logic** (won't be saved when admin submits settings form)
❌ Settings are **still in admin UI template** (`templates/admin/admin-settings.php`)
⚠️ Settings are **still used by remaining code**:
  - `includes/utils/class-tabesh-upload-task-generator.php`
  - `includes/handlers/class-tabesh-file-manager.php`
  - `templates/frontend/file-upload-form.php`
  - `tabesh.php` (default values)

### Impact

**If `[tabesh_file_upload]` shortcode is still actively used:**
- Existing settings values will continue to work (read from database)
- New installations won't have these settings
- Admins won't be able to update file size limits or retention settings via UI
- File upload functionality may use hardcoded defaults

**If `[tabesh_file_upload]` shortcode is NOT actively used:**
- No impact - settings can be fully removed

### Recommendations

#### Option 1: Fully Remove File Upload Settings (Current State)
**Best if:** `[tabesh_file_upload]` is deprecated or not widely used

**Additional steps needed:**
1. Remove file settings section from `templates/admin/admin-settings.php` (lines ~612-750)
2. Remove file settings references from:
   - `includes/utils/class-tabesh-upload-task-generator.php`
   - `includes/handlers/class-tabesh-file-manager.php`
3. Use hardcoded values or remove dependent features

#### Option 2: Restore File Upload Settings
**Best if:** `[tabesh_file_upload]` is actively used and file limits need to be configurable

**Steps needed:**
1. Restore file settings to `$scalar_fields` and `$checkbox_fields` in `class-tabesh-admin.php`
2. Keep settings in admin template (already there)
3. Update documentation to clarify these settings are for `[tabesh_file_upload]` only

#### Option 3: Hybrid Approach
**Best if:** Only some settings are needed

**Steps needed:**
1. Identify which settings are actually used by `[tabesh_file_upload]`
2. Restore only those settings to `$scalar_fields`
3. Remove unused settings from template
4. Document which settings are for which feature

### Decision Required

**Project owner should decide:**
1. Is `[tabesh_file_upload]` shortcode actively used in production?
2. Do users need to configure file size limits via admin panel?
3. Should file upload settings be completely removed or partially retained?

### Temporary Workaround

Until a decision is made, existing installations will continue to work with current settings values. The settings just can't be updated via the admin UI.

If immediate configuration is needed, settings can be updated directly in the database:
```sql
UPDATE wp_tabesh_settings 
SET setting_value = '104857600' 
WHERE setting_key = 'file_max_size_pdf';
```

### Files to Review for Final Decision

1. `templates/admin/admin-settings.php` - File Settings UI section
2. `includes/handlers/class-tabesh-admin.php` - Settings save logic
3. `includes/utils/class-tabesh-upload-task-generator.php` - Uses file_max_size_pdf
4. `includes/handlers/class-tabesh-file-manager.php` - Uses file_retention_days
5. `templates/frontend/file-upload-form.php` - File upload interface

### Related Documentation

- See `DATABASE_CLEANUP_GUIDE.md` for cleanup instructions
- See `REMOVAL_SUMMARY.md` for complete removal details
- See `CHANGELOG.md` for version history
