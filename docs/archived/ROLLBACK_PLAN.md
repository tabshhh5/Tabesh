# Rollback Plan - Submit Order 400 Error Fix

## Overview
This document provides step-by-step instructions for rolling back the submit-order fix if issues arise in production.

## When to Rollback

Consider rollback if:
- Migration fails and breaks existing functionality
- Orders cannot be submitted after upgrade
- Database corruption or data loss occurs
- Critical bugs introduced that cannot be hotfixed quickly
- Performance degradation observed

## Prerequisites

### Before Starting Rollback
1. **Stop incoming traffic** (if possible):
   - Enable maintenance mode
   - Or redirect to static page temporarily

2. **Verify backup exists:**
   ```bash
   # Check backup file
   ls -lh backup-before-submit-order-fix.sql
   
   # Verify backup is not empty
   wc -l backup-before-submit-order-fix.sql
   ```

3. **Document the issue:**
   - Take screenshots of errors
   - Save debug.log entries
   - Note any error messages users reported

## Rollback Methods

### Method 1: Git Revert (Recommended)

#### Step 1: Identify Commit to Revert
```bash
# View recent commits
git log --oneline -10

# Find the commit hash for "Add database migration system and harden order creation"
# Example: 0249b89
```

#### Step 2: Create Revert Branch
```bash
# From main or production branch
git checkout main
git pull origin main

# Create rollback branch
git checkout -b rollback/submit-order-fix

# Revert the changes
git revert 0249b89 --no-edit

# Or revert multiple commits if needed
git revert 0249b89..HEAD --no-edit
```

#### Step 3: Review Changes
```bash
# Check what will be reverted
git diff HEAD~1 HEAD

# Verify files affected
git show --name-only HEAD
```

#### Step 4: Test Locally
```bash
# Test on development/staging environment first
# Verify:
# - Plugin activates without errors
# - Order submission works (with old behavior)
# - No database errors
```

#### Step 5: Deploy Rollback
```bash
# Push rollback branch
git push origin rollback/submit-order-fix

# Or merge to main if verified
git checkout main
git merge rollback/submit-order-fix
git push origin main

# Deploy to production server
# (Method depends on your deployment process)
```

### Method 2: File Replacement (Quick Rollback)

If git is not available or urgent rollback needed:

#### Step 1: Backup Current Files
```bash
# On production server
cd /path/to/wp-content/plugins/Tabesh
tar -czf ../tabesh-current-backup.tar.gz .
```

#### Step 2: Restore Previous Version Files
Replace the following files with previous versions:

**Files to Replace:**
- `includes/class-tabesh-install.php` - **DELETE** (new file, remove entirely)
- `includes/class-tabesh-order.php` - Restore previous version
- `assets/js/frontend.js` - Restore previous version
- `tabesh.php` - Restore previous version

```bash
# Example: restore from previous plugin version
cd /path/to/wp-content/plugins/
rm -rf Tabesh
unzip tabesh-previous-version.zip
```

#### Step 3: Verify Files Restored
```bash
# Check file dates
ls -la includes/class-tabesh-install.php
# Should not exist after rollback

# Check file content
grep -n "create_order" includes/class-tabesh-order.php
# Should not show create_order method if reverted correctly
```

### Method 3: Database Rollback

If database changes cause issues and you need to restore database state:

⚠️ **WARNING:** This will lose all orders created after the migration!

#### Option A: Restore from Backup
```bash
# Using WP-CLI
wp db import backup-before-submit-order-fix.sql

# Or using MySQL command line
mysql -u username -p database_name < backup-before-submit-order-fix.sql
```

#### Option B: Selective Rollback (Remove book_title column)
⚠️ **CAUTION:** Only do this if orders are stored elsewhere or acceptable to lose

```sql
-- Connect to database
mysql -u username -p database_name

-- Check if book_title column has data
SELECT COUNT(*) FROM wp_tabesh_orders WHERE book_title IS NOT NULL;

-- If safe to remove (or data backed up):
ALTER TABLE wp_tabesh_orders DROP COLUMN IF EXISTS book_title;

-- Remove database version option
DELETE FROM wp_options WHERE option_name = 'tabesh_db_version';

-- Verify
SHOW COLUMNS FROM wp_tabesh_orders;
```

#### Option C: Keep Column but Fix Code
If data exists in book_title column, consider keeping column and only reverting code:

```sql
-- Keep the column, just revert the code files
-- This preserves data but removes new functionality

-- Verify data is safe
SELECT id, book_title FROM wp_tabesh_orders WHERE book_title IS NOT NULL LIMIT 10;
```

## Post-Rollback Verification

### Step 1: Check Plugin Status
```bash
# Via WP-CLI
wp plugin status tabesh

# Or check admin dashboard
# Navigate to: Plugins → Installed Plugins
# Verify Tabesh plugin is active and no errors shown
```

### Step 2: Test Order Submission
1. Log in as test user
2. Navigate to order form
3. Fill form with test data
4. Submit order
5. Verify order created successfully

### Step 3: Check Database
```sql
-- Verify orders table structure
DESCRIBE wp_tabesh_orders;

-- Check recent orders
SELECT * FROM wp_tabesh_orders ORDER BY id DESC LIMIT 5;

-- Verify no orphaned records
SELECT COUNT(*) FROM wp_tabesh_logs WHERE order_id NOT IN (SELECT id FROM wp_tabesh_orders);
```

### Step 4: Monitor Error Logs
```bash
# Check for errors
tail -f /path/to/wp-content/debug.log

# Or check server error logs
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

### Step 5: Verify Frontend
- Visit order form page
- Check browser console (no JavaScript errors)
- Verify all form fields visible
- Test price calculation
- Test order submission

## Cleanup After Rollback

### Remove Test Data
```sql
-- Remove test orders created during testing
DELETE FROM wp_tabesh_orders WHERE book_title LIKE '%rollback test%';

-- Clean up orphaned logs
DELETE FROM wp_tabesh_logs WHERE order_id NOT IN (SELECT id FROM wp_tabesh_orders);
```

### Disable Debug Mode
```php
// In wp-config.php, set to false or remove
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
```

### Clear Caches
```bash
# WP-CLI
wp cache flush

# Object cache (if using Redis/Memcached)
wp redis clear  # or
wp memcached flush

# Browser cache
# Clear LiteSpeed Cache (if enabled)
# Clear Cloudflare cache (if using)
```

### Re-enable Traffic
- Disable maintenance mode
- Remove any traffic redirects
- Monitor incoming requests

## Alternative: Hotfix Instead of Rollback

If issue is minor and can be fixed quickly:

### Option 1: Quick Patch
```bash
# Create hotfix branch
git checkout -b hotfix/submit-order-issue

# Make minimal fix
# Edit affected file(s)

# Test fix
# Deploy hotfix

git push origin hotfix/submit-order-issue
```

### Option 2: Feature Toggle
Add a setting to disable new functionality:

```php
// In includes/class-tabesh-install.php
public static function check_version() {
    // Add option to disable migration
    if (get_option('tabesh_disable_book_title_migration', false)) {
        return;
    }
    
    $current_db_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
    // ... rest of code
}
```

Then set option to disable:
```bash
wp option update tabesh_disable_book_title_migration 1
```

## Communication Plan

### Notify Stakeholders
1. **Inform team:**
   - Send message to development team
   - Notify project manager
   - Alert customer support team

2. **Update users (if affected):**
   - Post announcement on status page
   - Send email to affected customers
   - Update support documentation

3. **Document incident:**
   - What went wrong
   - Why rollback was needed
   - Lessons learned
   - Plan to re-implement fix

## Re-Implementation Plan

After successful rollback:

### Step 1: Analyze Root Cause
- Review error logs
- Identify specific failure point
- Determine if issue was code, data, or environment

### Step 2: Develop Fix
- Create new branch: `fix/submit-order-v2`
- Address root cause
- Add additional tests
- Test thoroughly on staging

### Step 3: Staged Re-Deployment
- Deploy to staging environment first
- Run full test suite
- Monitor for 24-48 hours
- Deploy to production with rollback plan ready

### Step 4: Monitor Closely
- Watch error logs
- Monitor order submission rate
- Check user feedback
- Be ready for quick hotfix if needed

## Emergency Contacts

**Development Team:**
- Lead Developer: [Contact Info]
- Database Administrator: [Contact Info]

**Infrastructure:**
- System Administrator: [Contact Info]
- Hosting Support: [Contact Info]

**Business:**
- Product Manager: [Contact Info]
- Customer Support Lead: [Contact Info]

## Rollback Checklist

- [ ] Backup current state before rollback
- [ ] Document the issue requiring rollback
- [ ] Stop incoming traffic (if critical)
- [ ] Revert code changes
- [ ] Restore database if needed
- [ ] Test order submission
- [ ] Verify no errors in logs
- [ ] Check frontend functionality
- [ ] Clear all caches
- [ ] Re-enable traffic
- [ ] Monitor for 1 hour minimum
- [ ] Notify team of rollback completion
- [ ] Plan re-implementation strategy

## SQL Snippets for Common Rollback Tasks

### Check Migration Status
```sql
-- Current database version
SELECT option_value FROM wp_options WHERE option_name = 'tabesh_db_version';

-- Check if book_title column exists
SHOW COLUMNS FROM wp_tabesh_orders LIKE 'book_title';

-- Count orders with book_title
SELECT 
    COUNT(*) as total,
    COUNT(book_title) as with_title,
    COUNT(*) - COUNT(book_title) as without_title
FROM wp_tabesh_orders;
```

### Safe Column Removal (if needed)
```sql
-- Backup book_title data first
CREATE TABLE wp_tabesh_orders_book_title_backup AS
SELECT id, book_title FROM wp_tabesh_orders WHERE book_title IS NOT NULL;

-- Then remove column
ALTER TABLE wp_tabesh_orders DROP COLUMN book_title;

-- If need to restore later:
-- UPDATE wp_tabesh_orders o
-- JOIN wp_tabesh_orders_book_title_backup b ON o.id = b.id
-- SET o.book_title = b.book_title;
```

### Verify Post Fallback Orders
```sql
-- Find orders created as posts
SELECT 
    p.ID, 
    p.post_title, 
    pm.meta_value as order_number
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_tabesh_order_number'
WHERE p.post_type = 'tabesh_order'
ORDER BY p.ID DESC;
```

---

**Document Version:** 1.0  
**Last Updated:** 2025-11-10  
**Related:** TESTING_GUIDE_SUBMIT_ORDER_FIX.md, MIGRATION_GUIDE.md
