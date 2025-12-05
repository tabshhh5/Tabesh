<?php
/**
 * Admin Order Form Shortcode Handler
 * 
 * کلاس مدیریت شورتکد فرم ثبت سفارش ویژه مدیر
 * Class for managing admin order form shortcode with access control
 *
 * Provides a dedicated shortcode [tabesh_admin_order_form] for administrators
 * and authorized users to create orders on behalf of customers directly
 * on the frontend with modern UI and customer search functionality.
 *
 * @package Tabesh
 * @since 1.0.3
 */

// Exit if accessed directly / در صورت دسترسی مستقیم خارج شود
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tabesh Admin Order Form Class
 * 
 * کلاس فرم سفارش ویژه مدیر تابش
 * Handles the admin order form shortcode with advanced access control
 *
 * Features:
 * - Access control via user roles (administrator, etc.)
 * - Access control via allowed users list
 * - Modern UI with customer search/autocomplete
 * - Full form validation
 * - Price calculation and override
 *
 * @since 1.0.3
 */
class Tabesh_Admin_Order_Form {

    /**
     * Settings key for allowed roles
     * کلید تنظیمات برای نقش‌های مجاز
     *
     * @var string
     */
    const SETTINGS_KEY_ALLOWED_ROLES = 'admin_order_form_allowed_roles';

    /**
     * Settings key for allowed users
     * کلید تنظیمات برای کاربران مجاز
     *
     * @var string
     */
    const SETTINGS_KEY_ALLOWED_USERS = 'admin_order_form_allowed_users';

    /**
     * Default allowed roles
     * نقش‌های مجاز پیش‌فرض
     *
     * @var array
     */
    private static $default_allowed_roles = array('administrator');

    /**
     * Constructor
     * سازنده
     */
    public function __construct() {
        // Enqueue assets / بارگذاری فایل‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue frontend assets
     * بارگذاری فایل‌های CSS و JavaScript برای فرانت‌اند
     */
    public function enqueue_assets() {
        // Only enqueue on pages with admin order form shortcode or admin dashboard shortcode (which includes the form via modal)
        // فقط در صفحاتی که شورتکد دارند بارگذاری شود (شامل داشبورد مدیر که فرم را در مودال نمایش می‌دهد)
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        
        // Check for direct shortcode usage or admin dashboard shortcode (which uses the form in a modal)
        if (!has_shortcode($post->post_content, 'tabesh_admin_order_form') && 
            !has_shortcode($post->post_content, 'tabesh_admin_dashboard')) {
            return;
        }

        // Check if user has access before loading assets
        // بررسی دسترسی کاربر قبل از بارگذاری فایل‌ها
        if (!$this->user_has_access()) {
            return;
        }

        // Get file version for cache busting
        $get_file_version = function($file_path) {
            if (WP_DEBUG && file_exists($file_path)) {
                $mtime = @filemtime($file_path);
                return $mtime !== false ? $mtime : TABESH_VERSION;
            }
            return TABESH_VERSION;
        };

        // Enqueue CSS
        // بارگذاری CSS
        wp_enqueue_style(
            'tabesh-admin-order-form',
            TABESH_PLUGIN_URL . 'assets/css/admin-order-form.css',
            array(),
            $get_file_version(TABESH_PLUGIN_DIR . 'assets/css/admin-order-form.css')
        );

        // Enqueue JS
        // بارگذاری جاوااسکریپت
        wp_enqueue_script(
            'tabesh-admin-order-form',
            TABESH_PLUGIN_URL . 'assets/js/admin-order-form.js',
            array('jquery'),
            $get_file_version(TABESH_PLUGIN_DIR . 'assets/js/admin-order-form.js'),
            true
        );

        // Get settings for frontend
        // دریافت تنظیمات برای فرانت‌اند
        $paper_types = Tabesh()->get_setting('paper_types', array());
        $book_sizes = Tabesh()->get_setting('book_sizes', array());
        $print_types = Tabesh()->get_setting('print_types', array());
        $binding_types = Tabesh()->get_setting('binding_types', array());
        $license_types = Tabesh()->get_setting('license_types', array());
        $cover_paper_weights = Tabesh()->get_setting('cover_paper_weights', array());
        $lamination_types = Tabesh()->get_setting('lamination_types', array());
        $extras = Tabesh()->get_setting('extras', array());

        // Sanitize extras
        // پاکسازی آپشن‌های اضافی
        $extras = is_array($extras) ? array_values(array_filter(array_map(function($extra) {
            $extra = is_scalar($extra) ? trim(strval($extra)) : '';
            return (!empty($extra) && $extra !== 'on') ? $extra : null;
        }, $extras))) : array();

        // Localize script with necessary data
        // ارسال داده‌ها به جاوااسکریپت
        wp_localize_script('tabesh-admin-order-form', 'tabeshAdminOrderForm', array(
            'restUrl' => rest_url(TABESH_REST_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => array(
                'paperTypes' => $paper_types,
                'bookSizes' => is_array($book_sizes) ? $book_sizes : array(),
                'printTypes' => is_array($print_types) ? $print_types : array(),
                'bindingTypes' => is_array($binding_types) ? $binding_types : array(),
                'licenseTypes' => is_array($license_types) ? $license_types : array(),
                'coverPaperWeights' => is_array($cover_paper_weights) ? $cover_paper_weights : array(),
                'laminationTypes' => is_array($lamination_types) ? $lamination_types : array(),
                'extras' => $extras,
                'minQuantity' => intval(Tabesh()->get_setting('min_quantity', 10)),
                'maxQuantity' => intval(Tabesh()->get_setting('max_quantity', 10000)),
                'quantityStep' => intval(Tabesh()->get_setting('quantity_step', 10)),
            ),
            'strings' => array(
                'selectUser' => __('انتخاب کاربر', 'tabesh'),
                'createNewUser' => __('ایجاد کاربر جدید', 'tabesh'),
                'searchUsers' => __('جستجوی کاربران...', 'tabesh'),
                'noResults' => __('کاربری یافت نشد', 'tabesh'),
                'calculating' => __('در حال محاسبه قیمت...', 'tabesh'),
                'submitting' => __('در حال ثبت سفارش...', 'tabesh'),
                'success' => __('سفارش با موفقیت ثبت شد', 'tabesh'),
                'error' => __('خطا در ثبت سفارش', 'tabesh'),
                'fillAllFields' => __('لطفاً تمام فیلدهای الزامی را پر کنید', 'tabesh'),
                'selectCustomer' => __('لطفاً یک مشتری را انتخاب یا ایجاد کنید', 'tabesh'),
                'createUserFirst' => __('لطفاً ابتدا کاربر جدید را ایجاد کنید', 'tabesh'),
                'invalidMobile' => __('فرمت شماره موبایل نامعتبر است', 'tabesh'),
                'fillAllUserFields' => __('لطفاً تمام فیلدها را پر کنید', 'tabesh'),
                'userCreated' => __('کاربر با موفقیت ایجاد شد', 'tabesh'),
                'searching' => __('در حال جستجو...', 'tabesh'),
                'searchError' => __('خطا در جستجو', 'tabesh'),
                'calculatePrice' => __('محاسبه قیمت', 'tabesh'),
                'submitOrder' => __('ثبت سفارش', 'tabesh'),
                'selectPaperFirst' => __('ابتدا نوع کاغذ را انتخاب کنید', 'tabesh'),
                'selectOption' => __('انتخاب کنید...', 'tabesh'),
            )
        ));
    }

    /**
     * Render the admin order form shortcode
     * رندر شورتکد فرم سفارش ویژه مدیر
     *
     * @param array $atts Shortcode attributes / پارامترهای شورتکد
     * @return string HTML output / خروجی HTML
     */
    public function render($atts = array()) {
        // Parse shortcode attributes
        // پردازش پارامترهای شورتکد
        $atts = shortcode_atts(array(
            'title' => __('ثبت سفارش جدید', 'tabesh'),
        ), $atts, 'tabesh_admin_order_form');

        // Check if user is logged in
        // بررسی ورود کاربر
        if (!is_user_logged_in()) {
            return $this->render_login_required_message();
        }

        // Check access permission
        // بررسی دسترسی
        if (!$this->user_has_access()) {
            return $this->render_access_denied_message();
        }

        // Output buffer for template
        // بافر خروجی برای قالب
        ob_start();
        include TABESH_PLUGIN_DIR . 'templates/frontend/admin-order-form.php';
        return ob_get_clean();
    }

    /**
     * Check if current user has access to admin order form
     * بررسی دسترسی کاربر فعلی به فرم سفارش مدیر
     * 
     * Access is granted if:
     * 1. User has manage_woocommerce capability (admins always have access)
     * 2. User's role is in allowed roles list
     * 3. User's ID is in allowed users list
     *
     * @return bool True if user has access / درست اگر کاربر دسترسی داشته باشد
     */
    public function user_has_access() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Admins always have access
        // مدیران همیشه دسترسی دارند
        if (user_can($user, 'manage_woocommerce')) {
            return true;
        }

        // Check if user role is in allowed roles
        // بررسی نقش کاربر در لیست نقش‌های مجاز
        $allowed_roles = $this->get_allowed_roles();
        if (!empty($allowed_roles)) {
            foreach ($user->roles as $role) {
                if (in_array($role, $allowed_roles, true)) {
                    return true;
                }
            }
        }

        // Check if user ID is in allowed users list
        // بررسی شناسه کاربر در لیست کاربران مجاز
        $allowed_users = $this->get_allowed_users();
        if (!empty($allowed_users) && in_array($user_id, $allowed_users, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get allowed roles from settings
     * دریافت نقش‌های مجاز از تنظیمات
     *
     * @return array Allowed roles / نقش‌های مجاز
     */
    public function get_allowed_roles() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_settings';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            self::SETTINGS_KEY_ALLOWED_ROLES
        ));
        
        if ($value === null) {
            return self::$default_allowed_roles;
        }
        
        $roles = json_decode($value, true);
        if (!is_array($roles)) {
            return self::$default_allowed_roles;
        }
        
        return $roles;
    }

    /**
     * Get allowed users from settings
     * دریافت کاربران مجاز از تنظیمات
     *
     * @return array Allowed user IDs / شناسه کاربران مجاز
     */
    public function get_allowed_users() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_settings';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            self::SETTINGS_KEY_ALLOWED_USERS
        ));
        
        if ($value === null) {
            return array();
        }
        
        $users = json_decode($value, true);
        if (!is_array($users)) {
            return array();
        }
        
        return array_map('intval', $users);
    }

    /**
     * Render login required message
     * نمایش پیام نیاز به ورود
     *
     * @return string HTML message / پیام HTML
     */
    private function render_login_required_message() {
        return sprintf(
            '<div class="tabesh-notice tabesh-notice-warning" dir="rtl">
                <div class="tabesh-notice-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="tabesh-notice-content">
                    <h3>%s</h3>
                    <p>%s</p>
                    <a href="%s" class="tabesh-btn tabesh-btn-primary">%s</a>
                </div>
            </div>',
            esc_html__('نیاز به ورود', 'tabesh'),
            esc_html__('برای دسترسی به این بخش باید وارد حساب کاربری خود شوید.', 'tabesh'),
            esc_url(wp_login_url(get_permalink())),
            esc_html__('ورود به سیستم', 'tabesh')
        );
    }

    /**
     * Render access denied message
     * نمایش پیام عدم دسترسی
     *
     * @return string HTML message / پیام HTML
     */
    private function render_access_denied_message() {
        return sprintf(
            '<div class="tabesh-notice tabesh-notice-error" dir="rtl">
                <div class="tabesh-notice-icon">
                    <span class="dashicons dashicons-lock"></span>
                </div>
                <div class="tabesh-notice-content">
                    <h3>%s</h3>
                    <p>%s</p>
                </div>
            </div>',
            esc_html__('عدم دسترسی', 'tabesh'),
            esc_html__('شما مجاز به دسترسی به این فرم نیستید. لطفاً با مدیر سایت تماس بگیرید.', 'tabesh')
        );
    }

    /**
     * Get all available WordPress roles
     * دریافت تمام نقش‌های موجود در وردپرس
     *
     * @return array Role name => Role display name / نام نقش => نام نمایشی
     */
    public static function get_available_roles() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $roles = array();
        foreach ($wp_roles->role_names as $role => $name) {
            $roles[$role] = translate_user_role($name);
        }
        
        return $roles;
    }
}
