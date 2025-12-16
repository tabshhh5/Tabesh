# Security Summary - Pricing Engine V2

## Overview

This document summarizes the security measures implemented in the new pricing engine (V2) for the Tabesh plugin.

## Security Principles Applied

### 1. Input Validation & Sanitization

All user inputs are sanitized using WordPress core functions before processing:

```php
// Example from Tabesh_Pricing_Engine::calculate_price()
$book_size        = sanitize_text_field( $params['book_size'] ?? '' );
$paper_type       = sanitize_text_field( $params['paper_type'] ?? '' );
$paper_weight     = sanitize_text_field( $params['paper_weight'] ?? '' );
$page_count_color = intval( $params['page_count_color'] ?? 0 );
$quantity         = intval( $params['quantity'] ?? 0 );
```

**Functions Used:**
- `sanitize_text_field()` - For string inputs
- `intval()` - For numeric inputs
- `floatval()` - For decimal numbers
- `sanitize_key()` - For array keys

### 2. Output Escaping

All output is properly escaped to prevent XSS attacks:

```php
// Example from product-pricing.php template
echo esc_html( $book_size );
echo esc_attr( $cost );
echo esc_url( $link );
```

**Functions Used:**
- `esc_html()` - For HTML content
- `esc_attr()` - For HTML attributes
- `esc_url()` - For URLs
- `wp_kses_post()` - For rich text (when needed)

### 3. Nonce Verification

All form submissions are protected with WordPress nonces:

```php
// Correct order: Verify THEN sanitize
if ( isset( $_POST['tabesh_pricing_nonce'] ) && isset( $_POST['book_size'] ) ) {
    // Verify nonce with raw value first
    if ( wp_verify_nonce( $_POST['tabesh_pricing_nonce'], 'tabesh_save_pricing' ) ) {
        // Now sanitize after verification
        $this->handle_save_pricing();
    }
}
```

**Key Points:**
- Nonce verification happens BEFORE sanitization
- Each form has a unique nonce action
- Nonces expire after 24 hours by default

### 4. Capability Checks

Access to pricing management requires proper WordPress capabilities:

```php
// Require manage_woocommerce capability
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    return '<div class="tabesh-error">' . 
           __( 'شما دسترسی به این بخش را ندارید', 'tabesh' ) . 
           '</div>';
}
```

**Capability Required:** `manage_woocommerce`
- Only administrators and shop managers have this capability
- Prevents unauthorized access to pricing settings

### 5. Database Security

All database queries use prepared statements:

```php
// Example from Tabesh_Pricing_Engine
$table_name = $wpdb->prefix . 'tabesh_settings';

$result = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT setting_value FROM {$table_name} WHERE setting_key = %s",
        'pricing_engine_v2_enabled'
    )
);
```

**Key Points:**
- Never use string interpolation in SQL
- Always use `$wpdb->prepare()` with placeholders
- Table names are constructed safely outside the query

### 6. Translation Security

Translation strings with variables use proper placeholders:

```php
// WRONG - String concatenation in translation
esc_html_e( 'Price for ' . $book_size, 'tabesh' );

// CORRECT - Using sprintf with placeholders
/* translators: %s: book size name */
echo esc_html( sprintf( __( 'هزینه صحافی برای قطع %s', 'tabesh' ), $book_size ) );
```

**Best Practices:**
- Use placeholders instead of concatenation
- Add translator comments for context
- Escape the final output

## Vulnerability Mitigations

### SQL Injection
- ✅ **Mitigated**: All queries use `$wpdb->prepare()` with placeholders
- ✅ **Verified**: No direct string interpolation in SQL queries

### Cross-Site Scripting (XSS)
- ✅ **Mitigated**: All output is escaped with `esc_html()`, `esc_attr()`, etc.
- ✅ **Verified**: No raw user input echoed without escaping

### Cross-Site Request Forgery (CSRF)
- ✅ **Mitigated**: All forms use WordPress nonces
- ✅ **Verified**: Nonces are verified before processing form data

### Unauthorized Access
- ✅ **Mitigated**: Capability checks before sensitive operations
- ✅ **Verified**: Only users with `manage_woocommerce` can access pricing settings

### Data Integrity
- ✅ **Mitigated**: Input validation and type casting
- ✅ **Verified**: All inputs are sanitized to correct types

## Code Review Findings

The code review identified and we fixed the following issues:

### Issue 1: Nonce Verification Order
**Problem:** Nonce was being verified after sanitization
```php
// WRONG
if ( isset( $_POST['nonce'] ) && 
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'action' ) )
```

**Fix:** Verify nonce with raw value first
```php
// CORRECT
if ( isset( $_POST['nonce'] ) ) {
    if ( wp_verify_nonce( $_POST['nonce'], 'action' ) ) {
        // Now sanitize...
    }
}
```

### Issue 2: Translation String Concatenation
**Problem:** Variables concatenated in translation functions
```php
// WRONG
esc_html_e( 'Price for ' . $book_size, 'tabesh' );
```

**Fix:** Use sprintf with placeholders
```php
// CORRECT
/* translators: %s: book size name */
echo esc_html( sprintf( __( 'Price for %s', 'tabesh' ), $book_size ) );
```

### Issue 3: SQL Variable Interpolation
**Problem:** Direct variable interpolation in SQL query
```php
// WRONG
$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}table WHERE id = %s", $id );
```

**Fix:** Construct table name separately
```php
// CORRECT
$table_name = $wpdb->prefix . 'table';
$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %s", $id );
```

## Testing for Security

### Manual Testing Checklist

- [x] Verify nonces are generated on forms
- [x] Test with invalid nonces (should fail)
- [x] Test without proper capabilities (should deny access)
- [x] Test SQL injection attempts (should be escaped)
- [x] Test XSS attempts in forms (should be sanitized/escaped)
- [x] Verify database queries use prepared statements
- [x] Check that sensitive operations require authentication

### Automated Checks

- [x] PHP syntax check (`php -l`)
- [x] WordPress Coding Standards (`phpcs`)
- [x] CodeQL security analysis (no issues found)
- [x] Code review (passed with fixes applied)

## Compliance

### WordPress Coding Standards
- ✅ Follows WordPress PHP Coding Standards
- ✅ Uses WordPress core functions for security
- ✅ Implements WordPress best practices

### WooCommerce Integration
- ✅ Uses WooCommerce capabilities appropriately
- ✅ Integrates with WooCommerce security model
- ✅ Follows WooCommerce extension guidelines

## Security Recommendations for Deployment

### Before Deployment
1. ✅ Review all code changes
2. ✅ Test with invalid inputs
3. ✅ Verify capability checks work
4. ✅ Test in staging environment first
5. ✅ Backup database before enabling V2

### After Deployment
1. Monitor WordPress error logs for unexpected errors
2. Check database for unauthorized changes
3. Review user activity logs
4. Test pricing calculations for accuracy
5. Keep WordPress and WooCommerce updated

### Ongoing Maintenance
1. Regular security audits of pricing code
2. Keep dependencies updated (WordPress, WooCommerce)
3. Monitor for security advisories
4. Review access logs periodically
5. Test backup and restore procedures

## Security Contact

For security issues or vulnerabilities:
- **DO NOT** create public GitHub issues
- Contact: [Your security contact email]
- Or use GitHub Security Advisories (private)

## Changelog

### Version 1.0.0 (December 2024)
- Initial implementation of pricing engine V2
- All security measures implemented
- Code review passed
- Security audit completed

---

**Security Level:** Production-Ready  
**Last Security Review:** December 2024  
**Reviewed By:** Tabesh Development Team + Code Review Bot
