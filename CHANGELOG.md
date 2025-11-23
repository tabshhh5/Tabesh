# Changelog

All notable changes to the Tabesh plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **Post-removal cleanup from Customer Files Panel (PR #94):**
  - Removed FTP settings from admin settings handler (12 fields: host, port, username, password, path, passive, SSL, encryption, transfer delay, retention, immediate transfer)
  - Removed smart upload template settings handling from admin class (3 methods: `save_format_settings()`, `save_category_format_settings()`, `sanitize_optional_int()`)
  - Removed "File Settings" and "Smart Upload Templates" tabs from admin settings page (633 lines)
  - Fixed shortcode admin dashboard to show user orders instead of deleted customer files panel
  - Total cleanup: 756 lines removed, ensuring no orphaned UI or settings remain
  - Verified REST API endpoints are properly registered and functional

### Removed
- **Complete removal of Customer Files Panel functionality:**
  - Removed `[customer_files_panel]` shortcode
  - Removed REST API endpoints: `/upload-file`, `/validate-file`, `/order-files/{order_id}`, `/delete-file/{file_id}`, `/file-comments/{file_id}`, `/document-metadata`
  - Removed database tables: `tabesh_files`, `tabesh_file_versions`, `tabesh_upload_tasks`, `tabesh_document_metadata`, `tabesh_file_comments`
  - Removed file upload settings from admin panel (file size limits, retention, reupload settings)
  - Removed all related template files: `customer-files-panel.php`, `file-upload-content.php`, `file-upload-cover.php`, `file-upload-documents.php`, `document-item.php`
  - Removed asset files: `customer-files-panel.css`, `customer-files-panel.js`
  - Removed related documentation files
  - Removed methods: `render_customer_files_panel()`, `get_current_step()`, `is_step_completed()`, `get_steps_order()`, `get_order_files_status()` from user and file manager classes
  - Removed REST API handler methods: `rest_upload_file()`, `rest_validate_file()`, `rest_get_order_files()`, `rest_delete_file()`, `rest_add_file_comment()`, `rest_get_file_comments()`, `rest_save_document_metadata()`, `rest_get_document_metadata()`
  - Added `drop_customer_files_tables()` method to `Tabesh_Install` class for safe database cleanup

### Added
- **Modern Staff Panel UI:**
  - Complete redesign with neumorphism/soft UI design principles
  - Card-based layout with smooth animations and transitions
  - Live search functionality with relevance-based sorting
  - Collapsed/expanded card views for better information density
  - Status stepper visualization for order progress
  - Dark/light theme toggle with localStorage persistence
  - Profile header with user info, notifications, and logout button
  - AJAX-based status updates without page refresh
  - Professional loading spinners and toast notifications
  - Full responsive design for mobile, tablet, and desktop
  - Financial information hidden from staff (visible only to admins)
  - Customer information limited to name only (no contact/address details)
  - New dedicated CSS file: `assets/css/staff-panel.css`
  - New dedicated JavaScript file: `assets/js/staff-panel.js`
  - Persian (Farsi) language support with full RTL compatibility

- **Documentation:**
  - `docs/STAFF_PANEL.md` - Complete staff panel usage guide

### Changed
- Enhanced REST API with dedicated staff endpoint: `/tabesh/v1/staff/update-status`
- Updated `tabeshData` localized script to include logout URL
- Improved staff panel template with modern UI components

## [1.1.0] - 2025-11-10

### Added
- **Database Migration System:**
  - New `Tabesh_Install` class for managing database schema updates
  - Idempotent migration checks for `book_title` column
  - Database version tracking via `tabesh_db_version` option (v1.1.0)
  - Automatic migration on plugin activation and init
  - Safe column addition with `SHOW COLUMNS` check before ALTER TABLE
  - Comprehensive error logging for migration debugging

- **Order Creation Fallback:**
  - New `create_order()` method with database fallback mechanism
  - Automatic fallback to WordPress post system if table/column missing
  - Orders saved as `tabesh_order` custom post type when needed
  - Post meta storage for all order data with `_tabesh_` prefix
  - Graceful handling of database errors

- **Enhanced Script Localization:**
  - Added `TabeshSettings` JavaScript object for frontend
  - Includes `rest_url`, `nonce`, and `i18n` translations
  - Backward compatible with existing `tabeshData` object

- **Documentation:**
  - `TESTING_GUIDE_SUBMIT_ORDER_FIX.md` - Comprehensive testing procedures
  - `ROLLBACK_PLAN.md` - Detailed rollback instructions with SQL snippets
  - Testing covers 10+ scenarios including edge cases

### Fixed
- **Submit Order 400 Error:**
  - Fixed "Unknown column 'book_title'" error on legacy installations
  - Proper handling of missing database columns during order submission
  - Database queries now use prepared statements with format specifications
  - Improved error detection and fallback to post system

- **REST API Improvements:**
  - Returns proper HTTP 201 Created status for successful order creation
  - Response structure updated to include `data.order_id` for consistency
  - Better error messages with appropriate status codes (400, 403, 500)
  - Enhanced debug logging for request/response troubleshooting

- **Frontend JavaScript:**
  - Improved error parsing with JSON-first approach, HTML fallback
  - Better handling of proxy errors and upstream failures
  - Added support for TabeshSettings alongside tabeshData
  - Content-Type explicitly set to `application/json; charset=utf-8` for JSON requests
  - X-WP-Nonce header properly set for authentication
  - Enhanced error messages based on status codes (0, 400, 401, 403, 500)
  - Better logging for request debugging

### Changed
- Database version updated to 1.1.0
- Order creation now uses `create_order()` instead of direct `$wpdb->insert()`
- Migration runs automatically on plugin activation and version check
- Success response structure now nested under `data` object for REST API standards

### Security
- All database queries use prepared statements with proper format types
- Input sanitization maintained throughout order creation flow
- File uploads validated for type and size limits
- Error messages don't expose sensitive database information
- Debug logging only enabled when WP_DEBUG is true

## [1.0.2] - 2025-11-09

### Added
- **Book Title Field (عنوان کتاب):**
  - Added required `book_title` field to order form (first step in multi-step form)
  - Database column added to `wp_tabesh_orders` table (VARCHAR 255, nullable for backward compatibility)
  - Field displayed in all views:
    - Admin orders list table (new column)
    - Admin order details page
    - Staff panel order cards
    - User order history
  - Server-side validation ensures book title is provided for new orders
  - Frontend validation with HTML5 required attribute
  - Proper sanitization with `sanitize_text_field()`
  - Proper output escaping with `esc_html()`
  - Fully translatable with i18n functions

### Fixed
- **Order Submission 400 Error:**
  - Fixed AJAX submission to handle both JSON and FormData (with file uploads)
  - Updated `frontend.js` to detect files and use appropriate content type
  - Improved REST API endpoint `/submit-order` to accept both JSON and multipart/form-data
  - Added comprehensive file validation (type and size)
  - Enhanced error handling with user-friendly Persian messages
  - Better logging for debugging (only in WP_DEBUG mode)
  
- **File Upload Support:**
  - License file upload now works correctly
  - File type validation: only PDF, JPG, PNG allowed
  - File size limit: 5MB maximum
  - Secure file handling with `wp_handle_upload()`
  - Files stored in WordPress uploads directory with proper naming
  - File metadata saved in order's `files` field

### Changed
- Multi-step order form now has 12 steps (was 11)
- Progress bar calculation updated to 8.33% per step
- Database version upgraded to 1.0.2
- Form step numbers updated throughout the template
- REST route registration now uses `WP_REST_Server::CREATABLE` constant
- Improved error responses with structured `WP_Error` objects

### Security
- All input fields properly sanitized
- File uploads validated for type and size
- Nonce verification enforced for all requests
- Permission callbacks ensure user is logged in
- Database queries use proper escaping
- No sensitive information logged in production mode

### Documentation
- Added `TESTING_GUIDE_ORDER_SUBMISSION_FIX.md` with comprehensive test scenarios
- Added `SECURITY_SUMMARY_ORDER_SUBMISSION_FIX.md` with security analysis
- Sample payloads and responses documented
- Troubleshooting guide included

### Technical Details
- Migration function added to `update_database_schema()` for safe column addition
- Backward compatible: old orders without book_title display gracefully
- Follows WordPress coding standards
- RTL-compatible layout
- FormData properly handles arrays (extras) with `[]` notation
- Enhanced REST API with argument validation schema

## [1.0.0] - 2024-10-27

### Added
- Initial release of Tabesh plugin
- **Core Features:**
  - Dynamic price calculation engine for book printing
  - Multi-step order form with RTL support
  - User dashboard for order tracking
  - Admin panel with comprehensive order management
  - Staff panel for order status updates
  - Archive system for completed orders
  
- **Database Schema:**
  - `wp_tabesh_orders` table for storing order data
  - `wp_tabesh_settings` table for plugin configuration
  - `wp_tabesh_logs` table for activity logging
  
- **REST API Endpoints:**
  - `/calculate-price` - Calculate order price
  - `/submit-order` - Submit new order
  - `/update-status` - Update order status
  
- **Shortcodes:**
  - `[tabesh_order_form]` - Display order submission form
  - `[tabesh_user_orders]` - Show user's orders
  - `[tabesh_staff_panel]` - Staff management interface
  - `[tabesh_admin_dashboard]` - Admin overview dashboard
  
- **Notifications:**
  - SMS notifications via MelliPayamak API
  - Email notifications for order updates
  - Customizable notification triggers
  
- **WooCommerce Integration:**
  - Custom "My Account" tab for print orders
  - Order status synchronization
  - Product creation from orders
  - Custom product type for books
  
- **UI/UX Features:**
  - Fully responsive design
  - RTL (right-to-left) layout for Persian
  - Modern and clean interface
  - Progress indicators for order status
  - Modal dialogs for order details
  
- **Admin Features:**
  - Dashboard with order statistics
  - Active orders management
  - Archived orders view
  - Settings panel with tabs:
    - General settings
    - Product parameters
    - Pricing configuration
    - SMS settings
  
- **Security:**
  - Nonce verification for all forms
  - Input sanitization and validation
  - SQL injection prevention
  - XSS protection
  - Role-based access control
  
- **Developer Features:**
  - MVC-inspired architecture
  - WordPress coding standards compliance
  - Extensible class structure
  - Action and filter hooks
  - Comprehensive inline documentation

### Technical Specifications
- **Minimum Requirements:**
  - WordPress 6.8+
  - PHP 8.2.2+
  - WooCommerce (latest)
  - MySQL 5.7+ or MariaDB 10.2+
  
- **Compatibility:**
  - LiteSpeed servers
  - Divi Theme
  - RTL languages
  - Mobile devices

### Documentation
- Comprehensive README.md
- Installation guide (INSTALL.md)
- Contributing guidelines (CONTRIBUTING.md)
- Inline code documentation

### Files Structure
```
Tabesh/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── includes/
│   ├── class-tabesh-admin.php
│   ├── class-tabesh-notifications.php
│   ├── class-tabesh-order.php
│   ├── class-tabesh-staff.php
│   ├── class-tabesh-user.php
│   └── class-tabesh-woocommerce.php
├── templates/
│   ├── admin-archived.php
│   ├── admin-dashboard.php
│   ├── admin-orders.php
│   ├── admin-settings.php
│   ├── order-form.php
│   ├── shortcode-admin-dashboard.php
│   ├── staff-panel.php
│   └── user-orders.php
├── languages/
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── INSTALL.md
├── README.md
└── tabesh.php
```

## [Unreleased]

### Planned Features
- GUI-based pricing configuration
- Multiple product types (posters, cards, banners)
- Advanced reporting and analytics
- File upload management system
- Integration with more SMS providers
- Multi-language support (i18n)
- Payment gateway integration
- Automated invoice generation (PDF)
- Customer file requirements checker
- Bulk order processing
- Export/import settings functionality
- Order templates for repeat customers
- Staff assignment system
- Email template customization
- WhatsApp notifications
- Order tracking with QR codes
- Production schedule management
- Inventory management
- Supplier integration
- Cost analysis reports

### Potential Improvements
- Performance optimization
- Caching layer implementation
- Background job processing
- Enhanced mobile experience
- More payment options
- Advanced search and filtering
- Drag-and-drop file uploads
- Real-time notifications
- Order history analytics
- Customer satisfaction surveys

---

## Version History

### Version Numbering
- **Major.Minor.Patch** format (e.g., 1.0.0)
- **Major**: Breaking changes or major feature additions
- **Minor**: New features, backward compatible
- **Patch**: Bug fixes and minor improvements

### Release Notes
Each release includes:
- New features
- Bug fixes
- Security updates
- Performance improvements
- Breaking changes (if any)
- Upgrade instructions

---

**Note**: This is a living document and will be updated with each release.

For detailed information about specific changes, please refer to the [GitHub Releases](https://github.com/tabshhh12/Tabesh/releases) page.
