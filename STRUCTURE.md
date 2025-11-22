# Tabesh Plugin File Structure

This document describes the reorganized file structure of the Tabesh WordPress plugin.

## Directory Structure

```
Tabesh/
├── admin/                          # Admin-specific functionality (future expansion)
├── assets/                         # Static assets
│   ├── css/                       # Stylesheets (4 files)
│   │   ├── admin.css             # Admin area styles
│   │   ├── customer-files-panel.css
│   │   ├── file-upload.css       # File upload interface styles
│   │   └── frontend.css          # Frontend styles
│   ├── js/                        # JavaScript files (4 files)
│   │   ├── admin.js              # Admin area scripts
│   │   ├── customer-files-panel.js
│   │   ├── file-upload.js        # File upload functionality
│   │   └── frontend.js           # Frontend scripts
│   └── images/                    # Image assets (for future use)
├── docs/                          # Organized documentation
│   ├── api/                      # API documentation (1 file)
│   ├── archived/                 # Historical documentation (8 files)
│   ├── guides/                   # User and developer guides (16 files)
│   ├── implementation/           # Implementation details (32 files)
│   ├── installation/             # Installation guides (3 files)
│   ├── security/                 # Security documentation (20 files)
│   ├── troubleshooting/          # Testing and debugging (11 files)
│   └── README.md                 # Documentation index
├── includes/                      # PHP classes
│   ├── api/                      # REST API handlers (future expansion)
│   ├── core/                     # Core functionality (2 classes)
│   │   ├── class-tabesh-install.php           # Installation and migrations
│   │   └── class-tabesh-woocommerce.php       # WooCommerce integration
│   ├── handlers/                 # Business logic handlers (7 classes)
│   │   ├── class-tabesh-admin.php             # Admin operations
│   │   ├── class-tabesh-file-manager.php      # File management
│   │   ├── class-tabesh-ftp-handler.php       # FTP operations
│   │   ├── class-tabesh-notifications.php     # SMS/Email notifications
│   │   ├── class-tabesh-order.php             # Order management
│   │   ├── class-tabesh-staff.php             # Staff operations
│   │   └── class-tabesh-user.php              # User operations
│   └── utils/                    # Utility classes (3 classes)
│       ├── class-tabesh-file-security.php     # File encryption and security
│       ├── class-tabesh-file-validator.php    # File validation
│       └── class-tabesh-upload-task-generator.php # Upload task generation
├── languages/                     # Translation files (future)
├── templates/                     # Template files
│   ├── admin/                    # Admin templates (7 files)
│   │   ├── admin-archived.php
│   │   ├── admin-dashboard.php
│   │   ├── admin-order-details.php
│   │   ├── admin-orders.php
│   │   ├── admin-settings.php
│   │   ├── file-management-admin.php
│   │   └── shortcode-admin-dashboard.php
│   ├── frontend/                 # Frontend templates (7 files)
│   │   ├── customer-files-panel.php
│   │   ├── file-status-customer.php
│   │   ├── file-upload-form-dynamic.php
│   │   ├── file-upload-form.php
│   │   ├── order-form.php
│   │   ├── staff-panel.php
│   │   └── user-orders.php
│   └── partials/                 # Reusable template components (6 files)
│       ├── correction-fees-summary.php
│       ├── document-item.php
│       ├── file-card.php
│       ├── file-upload-content.php
│       ├── file-upload-cover.php
│       └── file-upload-documents.php
├── vendor/                        # Third-party dependencies (Composer)
├── .gitignore                    # Git ignore rules
├── CHANGELOG.md                  # Version history
├── composer.json                 # Composer configuration
├── CONTRIBUTING.md               # Contribution guidelines
├── migration-convert-settings-to-json.php  # Database migration script
├── phpcs.xml                     # PHP Code Sniffer configuration
├── README.md                     # Main documentation
├── tabesh-diagnostic.php         # Diagnostic tool
└── tabesh.php                    # Main plugin file
```

## File Organization Principles

### Classes (`includes/`)

Classes are organized by their primary responsibility:

1. **Core (`core/`)**: Fundamental plugin functionality
   - Installation, database migrations
   - Third-party integrations (WooCommerce)

2. **Handlers (`handlers/`)**: Business logic and operations
   - User-facing operations (admin, staff, user)
   - Order management
   - File operations
   - Notifications

3. **Utils (`utils/`)**: Helper utilities and tools
   - Validation
   - Security
   - Task generation

4. **API (`api/`)**: REST API endpoints (future expansion)

### Templates (`templates/`)

Templates are organized by their usage context:

1. **Admin (`admin/`)**: WordPress admin area pages
   - Dashboard
   - Order management
   - Settings
   - File management

2. **Frontend (`frontend/`)**: Public-facing pages
   - Order forms
   - User dashboards
   - File upload interfaces
   - Staff panels

3. **Partials (`partials/`)**: Reusable template components
   - File cards
   - Upload forms
   - Document items

### Assets (`assets/`)

Static files organized by type:

1. **CSS (`css/`)**: Stylesheets for admin and frontend
2. **JS (`js/`)**: JavaScript for functionality and interactions
3. **Images (`images/`)**: Image assets (future use)

### Documentation (`docs/`)

Documentation organized by purpose:

1. **installation/**: Setup and migration guides
2. **guides/**: Feature documentation and tutorials
3. **api/**: API reference documentation
4. **security/**: Security summaries and best practices
5. **implementation/**: Technical implementation details
6. **troubleshooting/**: Testing and debugging guides
7. **archived/**: Historical documentation

## Autoloader

The plugin uses a custom autoloader that searches for class files in multiple directories:

```php
spl_autoload_register(function ($class) {
    $prefix = 'Tabesh_';
    $base_dir = TABESH_PLUGIN_DIR . 'includes/';
    
    // Search order: core/, handlers/, utils/, api/, root
    $subdirs = array('core/', 'handlers/', 'utils/', 'api/', '');
    
    foreach ($subdirs as $subdir) {
        $file = $base_dir . $subdir . $filename;
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
```

## Template Loading

Templates use the `TABESH_PLUGIN_DIR` constant with the new subfolder structure:

```php
// Admin template
include TABESH_PLUGIN_DIR . 'templates/admin/admin-dashboard.php';

// Frontend template
include TABESH_PLUGIN_DIR . 'templates/frontend/order-form.php';

// Partial template
include TABESH_PLUGIN_DIR . 'templates/partials/file-card.php';
```

## Asset Enqueuing

Assets use the `TABESH_PLUGIN_URL` constant (unchanged):

```php
wp_enqueue_style('tabesh-frontend', 
    TABESH_PLUGIN_URL . 'assets/css/frontend.css', 
    array(), TABESH_VERSION
);

wp_enqueue_script('tabesh-frontend', 
    TABESH_PLUGIN_URL . 'assets/js/frontend.js', 
    array('jquery'), TABESH_VERSION, true
);
```

## Adding New Files

### Adding a New Class

1. Determine the appropriate category (core, handlers, utils, api)
2. Create the file in the correct subdirectory
3. Follow naming convention: `class-tabesh-{name}.php`
4. The autoloader will find it automatically

### Adding a New Template

1. Determine if it's admin, frontend, or a partial
2. Create the file in the appropriate subdirectory
3. Include using: `TABESH_PLUGIN_DIR . 'templates/{category}/{filename}.php'`

### Adding Documentation

1. Determine the appropriate category
2. Add the file to `docs/{category}/`
3. Update `docs/README.md` if adding a new category

## Benefits of This Structure

1. **Clear Separation of Concerns**: Each directory has a specific purpose
2. **Scalability**: Easy to add new features without cluttering
3. **Maintainability**: Logical organization makes code easier to find and update
4. **Professional**: Follows WordPress plugin development best practices
5. **Documentation**: Well-organized docs make it easier for contributors
6. **Standards Compliance**: Includes phpcs.xml for code quality checks
7. **Dependency Management**: composer.json ready for third-party libraries

## Migration Notes

All functionality remains unchanged. The refactoring only reorganized files without modifying their contents (except for path references).

### Backward Compatibility

The autoloader searches multiple directories, so if any third-party code directly requires class files, it should still work. However, it's recommended to use the autoloader instead.

### Path Updates

All internal template includes and class references have been updated to use the new structure. No changes are needed in theme files or other plugins that use Tabesh shortcodes or hooks.
