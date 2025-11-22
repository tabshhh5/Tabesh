# GitHub Copilot Instructions for Tabesh

## Project Overview

Tabesh is a comprehensive WordPress plugin for managing book printing orders with full WooCommerce integration. It provides a complete order lifecycle management system with SMS notifications, dynamic price calculation, and role-based access control for Admins, Staff, and Customers.

**Key Technologies:**
- WordPress 6.8+
- PHP 8.2.2+
- WooCommerce (latest version)
- MySQL/MariaDB
- RTL (Right-to-Left) support for Persian language

## Architecture

### Directory Structure
```
Tabesh/
├── assets/
│   ├── css/          # Stylesheets (frontend.css, admin.css)
│   └── js/           # JavaScript files (frontend.js, admin.js)
├── includes/         # PHP classes
│   ├── class-tabesh-admin.php
│   ├── class-tabesh-notifications.php
│   ├── class-tabesh-order.php
│   ├── class-tabesh-staff.php
│   ├── class-tabesh-user.php
│   └── class-tabesh-woocommerce.php
├── templates/        # Template files
│   ├── admin-archived.php
│   ├── admin-dashboard.php
│   ├── admin-orders.php
│   ├── admin-settings.php
│   ├── order-form.php
│   ├── shortcode-admin-dashboard.php
│   ├── staff-panel.php
│   └── user-orders.php
├── tabesh.php        # Main plugin file
└── README.md         # Documentation
```

### Database Tables
- `wp_tabesh_orders` - Store all order data
- `wp_tabesh_settings` - Plugin configuration
- `wp_tabesh_logs` - Activity logs

## Coding Standards

### PHP Standards

1. **Follow WordPress Coding Standards**
   - Use WordPress core functions instead of PHP equivalents when available
   - Follow WordPress naming conventions (snake_case for functions, PascalCase for classes)
   - Always check for ABSPATH at the start of files: `if (!defined('ABSPATH')) { exit; }`

2. **Security First**
   - **Always sanitize input**: Use `sanitize_text_field()`, `sanitize_email()`, `intval()`, etc.
   - **Always escape output**: Use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, etc.
   - **Use nonces**: Verify nonces for all form submissions with `wp_verify_nonce()`
   - **Use prepared statements**: Always use `$wpdb->prepare()` for database queries
   - **Never use direct SQL**: Always use WordPress $wpdb class methods

3. **Code Organization**
   - One class per file
   - Class names should match filename (e.g., `class-tabesh-order.php` contains `Tabesh_Order`)
   - Group related functionality into methods
   - Use meaningful variable and function names

4. **Documentation**
   - Add PHPDoc comments for all classes and methods
   - Include `@package Tabesh` in file headers
   - Document parameters with `@param` and return values with `@return`
   - Add inline comments for complex logic

5. **Error Handling**
   - Use WordPress native logging when `WP_DEBUG_LOG` is enabled: `error_log()` writes to `wp-content/debug.log`
   - Use custom logging to `wp_tabesh_logs` table for production tracking
   - Return WP_Error objects for recoverable errors
   - Use `wp_die()` for fatal errors that should halt execution
   - Provide user-friendly error messages in Persian and English
   - Never log sensitive information (passwords, API keys, personal data)

### CSS Standards

1. **RTL Support**
   - This plugin requires full RTL (Right-to-Left) support for Persian language
   - Use logical properties where possible (e.g., `margin-inline-start` instead of `margin-left`)
   - Test all layouts in RTL mode
   - Use BEM naming convention where applicable

2. **Responsive Design**
   - Mobile-first approach
   - Test on various screen sizes
   - Use WordPress core CSS classes when appropriate

### JavaScript Standards

1. **Modern JavaScript**
   - Use ES6+ features
   - Use `const` and `let` instead of `var`
   - Use arrow functions where appropriate
   - Use template literals for string concatenation

2. **WordPress Integration**
   - Use `wp_localize_script()` to pass data from PHP to JavaScript
   - Use `wp_enqueue_script()` and `wp_enqueue_style()` for assets
   - Handle AJAX with WordPress REST API endpoints

3. **AJAX Requests**
   - All AJAX requests should go through REST API endpoints in `/wp-json/tabesh/v1/`
   - Always include nonces for authenticated requests
   - Handle errors gracefully with user-friendly messages

## API Endpoints

### REST API Routes
All routes are prefixed with `/wp-json/tabesh/v1/`

- **POST** `/calculate-price` - Calculate order price
- **POST** `/submit-order` - Submit new order (requires authentication)
- **POST** `/update-status` - Update order status (requires permission)

When creating new endpoints:
- Register routes in the main plugin file or dedicated REST controller
- Validate permissions using `current_user_can()`
- Sanitize all inputs
- Return consistent JSON responses with proper HTTP status codes

## Features and Components

### Price Calculator
- Located in `class-tabesh-order.php` → `calculate_price()` method
- Comprehensive algorithm considering: book size, paper type, page count, binding, options, quantity
- Formula: `FinalPrice = (((PaperCost + PrintCost) * PageCount) + CoverCost + BindingCost + OptionsCost) * Quantity * (1 + ProfitMargin)`

### Order Management
- Orders are stored in custom database table `wp_tabesh_orders`
- Order statuses: pending, processing, completed, cancelled, archived
- Each status change triggers notifications (SMS and email)

### Notifications
- SMS via MelliPayamak API (class-tabesh-notifications.php)
- Email notifications for all order updates
- Configurable notification triggers in settings

### Role-Based Access
- **Admin** (`manage_woocommerce` capability): Full access to all features
- **Staff** (`edit_shop_orders` capability): Can update order statuses
- **Customer** (logged-in users): Can view their own orders

### Shortcodes
- `[tabesh_order_form]` - Display order submission form
- `[tabesh_user_orders]` - Show user's orders
- `[tabesh_staff_panel]` - Display staff management panel
- `[tabesh_admin_dashboard]` - Show admin dashboard overview

## Development Guidelines

### Adding New Features

1. **Plan First**
   - Consider impact on existing functionality
   - Check if WordPress or WooCommerce already provides similar functionality
   - Plan database changes carefully (migrations may be needed)

2. **Follow the Pattern**
   - Use existing code structure as a template
   - Keep consistent with current naming conventions
   - Add proper hooks and filters for extensibility

3. **Test Thoroughly**
   - Test with different user roles
   - Test in RTL mode
   - Test responsive design on mobile
   - Test with WooCommerce integration
   - Verify all security measures

### Modifying Existing Features

1. **Minimal Changes**
   - Make the smallest possible change to achieve the goal
   - Don't refactor unrelated code unless fixing security issues
   - Keep backward compatibility when possible

2. **Database Changes**
   - Never modify existing columns
   - Add new columns if needed
   - Include migration scripts if structure changes

3. **Settings Changes**
   - Settings are stored in `wp_tabesh_settings` table
   - Always provide default values
   - Validate and sanitize settings on save

## Testing

### Manual Testing Checklist

- [ ] Test with fresh WordPress installation
- [ ] Verify WooCommerce integration works
- [ ] Test all user roles (Admin, Staff, Customer)
- [ ] Test RTL layout rendering
- [ ] Test responsive design on mobile
- [ ] Verify SMS notifications (if configured)
- [ ] Test order submission flow
- [ ] Test price calculation with various parameters
- [ ] Check security (nonces, sanitization, escaping)
- [ ] Review database queries for SQL injection vulnerabilities

### Browser Testing
- Test in Chrome, Firefox, Safari
- Test on mobile browsers
- Test with browser console open (check for JavaScript errors)

## Common Pitfalls to Avoid

1. **Don't bypass WordPress functions**
   - Use WordPress APIs instead of raw PHP functions
   - Example: Use `wp_remote_get()` instead of `file_get_contents()`

2. **Don't ignore RTL support**
   - Always consider RTL layout when adding CSS
   - Test in Persian language mode

3. **Don't hardcode values**
   - Use settings and configuration options
   - Make values translatable with `__()` or `_e()`

4. **Don't forget WooCommerce compatibility**
   - This plugin integrates with WooCommerce
   - Check WooCommerce functions availability before using

5. **Don't skip security measures**
   - Every user input must be sanitized
   - Every output must be escaped
   - Every form must use nonces

## Debugging

### Enable Debug Mode
Add to `wp-config.php` for **development environments only**:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**⚠️ SECURITY WARNING**: Never enable `WP_DEBUG` or `WP_DEBUG_LOG` in production environments as they can:
- Expose sensitive information (file paths, database queries, API calls)
- Impact performance significantly
- Fill up disk space with log files
- Reveal security vulnerabilities to potential attackers

### Debug Tools
- `error_log()` - Writes to `wp-content/debug.log` (only when `WP_DEBUG_LOG` is enabled)
- Browser console - Check JavaScript errors
- `tabesh-diagnostic.php` - **For development only**: Upload to a protected directory with `.htaccess` or use within WordPress admin interface. Never leave diagnostic tools in publicly accessible locations.

### Common Issues
- **Settings not saving**: Check database permissions and debug logs
- **Price calculation errors**: Verify all pricing fields format (`key=value`)
- **SMS not sending**: Check MelliPayamak credentials and balance

## Internationalization (i18n)

- Text domain: `tabesh`
- Language files location: `/languages/`
- Always use translation functions:
  - `__('text', 'tabesh')` - Returns translated text
  - `_e('text', 'tabesh')` - Echoes translated text
  - `esc_html__('text', 'tabesh')` - Returns escaped translated text
  - `esc_html_e('text', 'tabesh')` - Echoes escaped translated text

## Performance Considerations

- Cache expensive database queries
- Minimize database queries in loops
- Use transients for temporary data storage
- Optimize asset loading (combine and minify when possible)
- LiteSpeed compatible - avoid unnecessary dynamic content

## Support and Resources

- **Documentation**: See README.md, INSTALL.md, QUICKSTART.md
- **Troubleshooting**: See PRICING_TROUBLESHOOTING.md
- **API Reference**: See API.md
- **Contributing**: See CONTRIBUTING.md
- **WordPress Codex**: https://codex.wordpress.org/
- **WooCommerce Docs**: https://woocommerce.com/documentation/

## License

This plugin is licensed under GPL v2 or later. All contributions must be compatible with this license.
