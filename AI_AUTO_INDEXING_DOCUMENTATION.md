# AI Auto-Indexing Documentation

## Overview

The AI system in Tabesh now features automatic page discovery and indexing. This enhancement allows the AI assistant to automatically discover all pages, posts, and custom post types in WordPress and suggest them to users without manual configuration.

## What's New

### 1. Automatic WordPress Content Discovery

The system now automatically discovers and indexes:
- **All published pages** via `get_pages()`
- **All published posts** via `get_posts()`
- **All custom post types** (if any exist)

### 2. Intelligent Page Type Detection

Each page is automatically categorized based on:
- **Post slug** (e.g., `order-form`, `cart`, `contact`)
- **Page title** (Persian and English keywords)
- **Content analysis** (first 1000 characters)
- **Post type** (page, post, custom post types)

Supported page types:
- `order-form` - Order submission forms
- `cart` - Shopping cart pages
- `checkout` - Checkout pages
- `account` - User account pages
- `about` - About us pages
- `contact` - Contact pages
- `portfolio` - Portfolio/gallery pages
- `page` - General pages
- `blog-post` - Blog posts
- `product` - WooCommerce products

### 3. Automatic Scheduling

The system automatically runs daily indexing via WordPress Cron:
- **Cron Job**: `tabesh_ai_index_site_pages`
- **Frequency**: Daily
- **Action**: Indexes all WordPress content

### 4. Manual Indexing

Administrators can manually trigger indexing from the admin settings:
- Go to **Settings → Tabesh → AI Settings**
- Click the **"ایندکس کردن همه صفحات اکنون"** button
- The system will index all pages and display results

### 5. AI Context Enhancement

The AI assistant now receives a complete list of indexed pages in every conversation:
- Pages are grouped by type
- Each page includes title and URL
- The AI can suggest relevant pages to users
- Users can ask "کدام صفحات دارید؟" (What pages do you have?)

## Technical Implementation

### Database Schema

Table: `wp_tabesh_ai_site_pages`

```sql
CREATE TABLE wp_tabesh_ai_site_pages (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    page_url varchar(500) NOT NULL,
    page_title varchar(255) DEFAULT NULL,
    page_content_summary text DEFAULT NULL,
    page_keywords longtext DEFAULT NULL,
    page_type varchar(50) DEFAULT NULL,
    last_scanned datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY page_url (page_url(191)),
    KEY page_type (page_type),
    KEY last_scanned (last_scanned)
);
```

### Key Classes

1. **Tabesh_AI_Site_Indexer** (`includes/ai/class-tabesh-ai-site-indexer.php`)
   - `index_wordpress_content()` - Main indexing method
   - `get_all_pages()` - Retrieve all indexed pages
   - `get_page_list_for_ai()` - Format pages for AI context
   - `get_pages_by_type()` - Filter pages by type

2. **Tabesh_AI_Gemini** (`includes/ai/class-tabesh-ai-gemini.php`)
   - `build_system_prompt()` - Enhanced to include page list

3. **Tabesh_AI** (`includes/ai/class-tabesh-ai.php`)
   - `rest_index_site()` - REST API endpoint for manual indexing
   - `check_admin_permission()` - Permission check for admin-only operations

### REST API Endpoints

**Manual Indexing Endpoint:**
```
POST /wp-json/tabesh/v1/ai/index-site
```

**Headers:**
```
X-WP-Nonce: {wp_rest_nonce}
```

**Response:**
```json
{
  "success": true,
  "message": "ایندکس کردن تکمیل شد: 25 صفحه موفق، 0 صفحه ناموفق از 25 صفحه کل",
  "data": {
    "success": true,
    "indexed": 25,
    "failed": 0,
    "total": 25
  }
}
```

## Usage Examples

### For Users

When chatting with the AI assistant, users can:

```
User: کدام صفحات دارید؟
AI: ما صفحات زیر را داریم:
    - فرم سفارش: http://example.com/order-form/
    - درباره ما: http://example.com/about/
    - تماس با ما: http://example.com/contact/
    ...
```

### For Administrators

**Manual Indexing:**
1. Navigate to WordPress Admin → Settings → Tabesh
2. Click on "تنظیمات هوش مصنوعی" tab
3. Scroll to "ایندکس خودکار صفحات" section
4. Click "ایندکس کردن همه صفحات اکنون" button
5. Wait for completion message

**Viewing Indexed Pages:**
1. Go to the same section
2. Click "نمایش صفحات ایندکس شده" to expand the list
3. View all indexed pages with their types and URLs

### For Developers

**Programmatic Indexing:**
```php
$indexer = new Tabesh_AI_Site_Indexer();
$result = $indexer->index_wordpress_content();

if ($result['success']) {
    echo sprintf(
        'Indexed %d pages, %d failed out of %d total',
        $result['indexed'],
        $result['failed'],
        $result['total']
    );
}
```

**Get All Indexed Pages:**
```php
$indexer = new Tabesh_AI_Site_Indexer();
$pages = $indexer->get_all_pages(100); // Get up to 100 pages

foreach ($pages as $page) {
    echo $page['page_title'] . ' - ' . $page['page_url'] . "\n";
}
```

**Get Pages by Type:**
```php
$indexer = new Tabesh_AI_Site_Indexer();
$order_forms = $indexer->get_pages_by_type('order-form', 10);
```

## Security Considerations

1. **Input Sanitization:**
   - All URLs are sanitized with `esc_url_raw()`
   - All text fields are sanitized with `sanitize_text_field()`
   - Page content is stripped of all HTML tags

2. **Output Escaping:**
   - All URLs are escaped with `esc_url()`
   - All HTML content is escaped with `esc_html()`
   - JSON data is properly encoded with `wp_json_encode()`

3. **Permission Checks:**
   - Manual indexing requires `manage_options` capability
   - REST API endpoints verify WordPress nonces
   - Database queries use `$wpdb->prepare()`

4. **Database Security:**
   - All queries use prepared statements
   - Table names use WordPress prefix for safety
   - Proper phpcs ignore comments for safe interpolations

## Performance Optimization

1. **Efficient Queries:**
   - Uses WordPress native functions (`get_pages()`, `get_posts()`)
   - Indexes with proper keys on frequently queried columns
   - Limits results to prevent memory issues

2. **Caching:**
   - AI context is built once per request
   - Page list is cached in database
   - Daily updates prevent stale data

3. **Async Processing:**
   - Uses WordPress Cron for automatic updates
   - Manual indexing runs in background via AJAX
   - Small delays between indexing operations to prevent server overload

## Troubleshooting

### Pages Not Being Indexed

**Problem:** New pages don't appear in indexed list.

**Solutions:**
1. Manually trigger indexing from admin settings
2. Check if pages are published (not draft/private)
3. Verify WordPress Cron is running properly
4. Check error logs for any issues

### AI Not Suggesting Pages

**Problem:** AI doesn't suggest pages to users.

**Solutions:**
1. Ensure AI system is enabled in settings
2. Verify pages are indexed (check admin settings)
3. Check Gemini API key is valid
4. Review AI conversation logs for context

### Database Issues

**Problem:** Table doesn't exist or queries fail.

**Solutions:**
1. Deactivate and reactivate the plugin
2. Check database user permissions
3. Manually create table using SQL from schema above
4. Contact support if issue persists

## Future Enhancements

Possible improvements for future versions:
1. **Real-time indexing** on page publish/update
2. **Category and tag indexing** for better organization
3. **Multilingual support** for WPML/Polylang
4. **Search relevance scoring** based on page popularity
5. **Custom post type configuration** for selective indexing

## Changelog

### Version 1.0.4 - Current
- ✅ Automatic WordPress content discovery
- ✅ Intelligent page type detection
- ✅ Manual indexing button in admin
- ✅ REST API endpoint for indexing
- ✅ AI context enhancement with page list
- ✅ Daily automatic indexing via Cron
- ✅ Removed manual page configuration fields

## Support

For issues or questions:
1. Check WordPress debug logs: `wp-content/debug.log`
2. Review database table: `wp_tabesh_ai_site_pages`
3. Test REST API endpoint manually
4. Contact plugin support at https://chapco.ir
