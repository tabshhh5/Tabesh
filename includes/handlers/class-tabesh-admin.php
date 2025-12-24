<?php
/**
 * Admin Management Class
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_tabesh_load_order_details', array($this, 'ajax_load_order_details'));
    }

    /**
     * AJAX handler: Load order details
     */
    public function ajax_load_order_details() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tabesh_order_details')) {
            wp_send_json_error(array('message' => __('امنیتی: درخواست معتبر نیست', 'tabesh')));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('شما دسترسی لازم را ندارید', 'tabesh')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if ($order_id <= 0) {
            wp_send_json_error(array('message' => __('شماره سفارش معتبر نیست', 'tabesh')));
        }

        // Capture the template output
        ob_start();
        include TABESH_PLUGIN_DIR . 'templates/admin/admin-order-details.php';
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('تابش - مدیریت سفارشات', 'tabesh'),
            __('تابش', 'tabesh'),
            'manage_woocommerce',
            'tabesh',
            array($this, 'render_dashboard'),
            'dashicons-book-alt',
            56
        );

        add_submenu_page(
            'tabesh',
            __('داشبورد', 'tabesh'),
            __('داشبورد', 'tabesh'),
            'manage_woocommerce',
            'tabesh',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'tabesh',
            __('سفارشات فعال', 'tabesh'),
            __('سفارشات فعال', 'tabesh'),
            'manage_woocommerce',
            'tabesh-orders',
            array($this, 'render_orders')
        );

        add_submenu_page(
            'tabesh',
            __('سفارشات بایگانی', 'tabesh'),
            __('سفارشات بایگانی', 'tabesh'),
            'manage_woocommerce',
            'tabesh-archived',
            array($this, 'render_archived_orders')
        );

        add_submenu_page(
            'tabesh',
            __('سفارشات لغو شده', 'tabesh'),
            __('سفارشات لغو شده', 'tabesh'),
            'manage_woocommerce',
            'tabesh-cancelled',
            array($this, 'render_cancelled_orders')
        );

        add_submenu_page(
            'tabesh',
            __('فایل‌های سفارش', 'tabesh'),
            __('فایل‌های سفارش', 'tabesh'),
            'manage_woocommerce',
            'tabesh-files',
            array($this, 'render_order_files')
        );

        add_submenu_page(
            'tabesh',
            __('تنظیمات', 'tabesh'),
            __('تنظیمات', 'tabesh'),
            'manage_woocommerce',
            'tabesh-settings',
            array($this, 'render_settings')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tabesh_settings', 'tabesh_options');
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('شما اجازه دسترسی به این صفحه را ندارید.', 'tabesh'));
        }

        include TABESH_PLUGIN_DIR . 'templates/admin/admin-dashboard.php';
    }

    /**
     * Render orders page
     */
    public function render_orders() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('شما اجازه دسترسی به این صفحه را ندارید.', 'tabesh'));
        }

        include TABESH_PLUGIN_DIR . 'templates/admin/admin-orders.php';
    }

    /**
     * Render archived orders page
     */
    public function render_archived_orders() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('شما اجازه دسترسی به این صفحه را ندارید.', 'tabesh'));
        }

        include TABESH_PLUGIN_DIR . 'templates/admin/admin-archived.php';
    }

    /**
     * Render cancelled orders page
     */
    public function render_cancelled_orders() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('شما اجازه دسترسی به این صفحه را ندارید.', 'tabesh'));
        }

        include TABESH_PLUGIN_DIR . 'templates/admin/admin-cancelled.php';
    }

    /**
     * Render order files page
     */
    public function render_order_files() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('شما اجازه دسترسی به این صفحه را ندارید.', 'tabesh'));
        }

        include TABESH_PLUGIN_DIR . 'templates/admin-files.php';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('شما اجازه دسترسی به این صفحه را ندارید.', 'tabesh'));
        }

        // Handle form submission
        if (isset($_POST['tabesh_save_settings']) && check_admin_referer('tabesh_settings')) {
            $this->save_settings($_POST);
            
            // Check if format settings were submitted
            $format_saved = isset($_POST['format']) && is_array($_POST['format']) && !empty($_POST['format']);
            
            if ($format_saved) {
                echo '<div class="notice notice-success"><p>' . __('تنظیمات با موفقیت ذخیره شد. قالب‌های هوشمند آپلود به‌روزرسانی شدند.', 'tabesh') . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . __('تنظیمات با موفقیت ذخیره شد.', 'tabesh') . '</p></div>';
            }
        }

        include TABESH_PLUGIN_DIR . 'templates/admin/admin-settings.php';
    }

    /**
     * Save settings
     *
     * @param array $post_data
     */
    private function save_settings($post_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_settings';

        // Define field types for proper handling
        $simple_array_fields = array('book_sizes', 'print_types', 'binding_types', 
                                     'license_types', 'cover_paper_weights', 'lamination_types', 'extras');
        
        // Note: pricing_book_sizes, pricing_binding_costs, pricing_lamination_costs, pricing_options_costs
        // are now handled separately as array inputs from dynamic fields (see below)
        // pricing_paper_types is replaced by pricing_paper_weights for weight-based pricing
        $json_object_fields = array('paper_types', 'pricing_quantity_discounts');
        
        $scalar_fields = array('min_quantity', 'max_quantity', 'quantity_step',
                              // New SMS settings
                              'sms_username', 'sms_password', 'sms_sender',
                              'sms_admin_user_registration_pattern', 'sms_admin_order_created_pattern');
        
        // Checkbox fields need special handling because unchecked boxes don't appear in POST
        $checkbox_fields = array('sms_enabled', 'sms_admin_user_registration_enabled', 'sms_admin_order_created_enabled');
        
        // Add dynamic SMS status checkbox fields
        $status_labels = Tabesh_SMS::get_status_labels();
        foreach ($status_labels as $status => $label) {
            $checkbox_fields[] = 'sms_status_' . $status . '_enabled';
            $scalar_fields[] = 'sms_status_' . $status . '_pattern';
        }

        // Process simple array fields - ensure they are stored as JSON arrays
        foreach ($simple_array_fields as $field) {
            if (isset($post_data[$field])) {
                $value = $post_data[$field];
                
                // Skip if the field is completely empty or contains only whitespace
                if (empty(trim($value))) {
                    error_log("Tabesh: Skipping empty field: $field");
                    continue;
                }
                
                $normalized_value = $this->normalize_to_json_array($value);
                
                // Validate the normalized value
                $decoded = json_decode($normalized_value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Tabesh: JSON decode error for $field: " . json_last_error_msg());
                    continue;
                }
                
                // Don't save empty arrays - keep existing value instead
                if (empty($decoded)) {
                    error_log("Tabesh: Skipping empty decoded value for: $field");
                    continue;
                }
                
                // Log what we're about to save for debugging
                error_log("Tabesh: Saving $field with " . count($decoded) . " entries");
                
                $result = $wpdb->replace(
                    $table,
                    array(
                        'setting_key' => $field,
                        'setting_value' => $normalized_value,
                        'setting_type' => 'string'
                    )
                );
                
                // Log errors for debugging
                if ($result === false) {
                    error_log("Tabesh: Failed to save setting: $field - Error: " . $wpdb->last_error);
                } else {
                    error_log("Tabesh: Successfully saved setting: $field");
                }
            } else {
                error_log("Tabesh: Field not present in POST data: $field");
            }
        }
        
        // Process JSON object fields (key-value pairs) - ensure they are stored as JSON objects
        foreach ($json_object_fields as $field) {
            if (isset($post_data[$field])) {
                $value = $post_data[$field];
                
                // Skip if the field is completely empty or contains only whitespace
                if (empty(trim($value))) {
                    error_log("Tabesh: Skipping empty field: $field");
                    continue;
                }
                
                // paper_types needs special handling to parse comma-separated values as arrays
                $parse_array_values = ($field === 'paper_types');
                $normalized_value = $this->normalize_to_json_object($value, $parse_array_values);
                
                // Validate the normalized value
                $decoded = json_decode($normalized_value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Tabesh: JSON decode error for $field: " . json_last_error_msg());
                    continue;
                }
                
                // Don't save empty objects - keep existing value instead
                if (empty($decoded)) {
                    error_log("Tabesh: Skipping empty decoded value for: $field");
                    continue;
                }
                
                // Log what we're about to save for debugging
                error_log("Tabesh: Saving $field with " . count($decoded) . " entries");
                
                $result = $wpdb->replace(
                    $table,
                    array(
                        'setting_key' => $field,
                        'setting_value' => $normalized_value,
                        'setting_type' => 'string'
                    )
                );
                
                // Log errors for debugging
                if ($result === false) {
                    error_log("Tabesh: Failed to save setting: $field - Error: " . $wpdb->last_error);
                } else {
                    error_log("Tabesh: Successfully saved setting: $field");
                }
            } else {
                error_log("Tabesh: Field not present in POST data: $field");
            }
        }
        
        // Process scalar fields - sanitize as plain text
        foreach ($scalar_fields as $field) {
            if (isset($post_data[$field])) {
                $value = sanitize_text_field($post_data[$field]);
                
                $result = $wpdb->replace(
                    $table,
                    array(
                        'setting_key' => $field,
                        'setting_value' => $value,
                        'setting_type' => 'string'
                    )
                );
                
                // Log errors for debugging
                if ($result === false) {
                    error_log("Failed to save setting: $field - Error: " . $wpdb->last_error);
                }
            }
        }
        
        // Handle checkbox fields - set to 0 if not present in POST
        foreach ($checkbox_fields as $field) {
            $value = isset($post_data[$field]) ? '1' : '0';
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => $field,
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            // Log errors for debugging
            if ($result === false) {
                error_log("Failed to save setting: $field - Error: " . $wpdb->last_error);
            }
        }
        
        // Handle special pricing fields that need to be combined
        if (isset($post_data['pricing_print_costs_bw']) && isset($post_data['pricing_print_costs_color'])) {
            $print_costs = array(
                'bw' => intval($post_data['pricing_print_costs_bw']),
                'color' => intval($post_data['pricing_print_costs_color'])
            );
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'pricing_print_costs',
                    'setting_value' => wp_json_encode($print_costs),
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: pricing_print_costs - Error: " . $wpdb->last_error);
            }
        }
        
        if (isset($post_data['pricing_cover_types_soft']) && isset($post_data['pricing_cover_types_hard'])) {
            $cover_types = array(
                'soft' => intval($post_data['pricing_cover_types_soft']),
                'hard' => intval($post_data['pricing_cover_types_hard'])
            );
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'pricing_cover_types',
                    'setting_value' => wp_json_encode($cover_types),
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: pricing_cover_types - Error: " . $wpdb->last_error);
            }
        }
        
        // Handle dynamic pricing fields that come as arrays (new format)
        // These are the fields that are auto-generated from product parameters
        $array_pricing_fields = array(
            'pricing_book_sizes',
            'pricing_binding_costs',
            'pricing_lamination_costs',
            'pricing_options_costs'
        );
        
        foreach ($array_pricing_fields as $field) {
            if (isset($post_data[$field]) && is_array($post_data[$field])) {
                $sanitized_data = array();
                foreach ($post_data[$field] as $key => $value) {
                    $sanitized_key = sanitize_text_field($key);
                    $sanitized_value = is_numeric($value) ? floatval($value) : sanitize_text_field($value);
                    $sanitized_data[$sanitized_key] = $sanitized_value;
                }
                
                if (!empty($sanitized_data)) {
                    $result = $wpdb->replace(
                        $table,
                        array(
                            'setting_key' => $field,
                            'setting_value' => wp_json_encode($sanitized_data, JSON_UNESCAPED_UNICODE),
                            'setting_type' => 'string'
                        )
                    );
                    
                    if ($result === false) {
                        error_log("Tabesh: Failed to save setting: $field - Error: " . $wpdb->last_error);
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Tabesh: Successfully saved dynamic pricing field: $field with " . count($sanitized_data) . " entries");
                        }
                    }
                }
            }
        }
        
        // Handle nested pricing_paper_weights structure (paper_type => [weight => price])
        if (isset($post_data['pricing_paper_weights']) && is_array($post_data['pricing_paper_weights'])) {
            $sanitized_data = array();
            foreach ($post_data['pricing_paper_weights'] as $paper_type => $weights) {
                $sanitized_type = sanitize_text_field($paper_type);
                $sanitized_data[$sanitized_type] = array();
                
                if (is_array($weights)) {
                    foreach ($weights as $weight => $price) {
                        $sanitized_weight = sanitize_text_field($weight);
                        $sanitized_price = is_numeric($price) ? floatval($price) : 0;
                        $sanitized_data[$sanitized_type][$sanitized_weight] = $sanitized_price;
                    }
                }
            }
            
            if (!empty($sanitized_data)) {
                $result = $wpdb->replace(
                    $table,
                    array(
                        'setting_key' => 'pricing_paper_weights',
                        'setting_value' => wp_json_encode($sanitized_data, JSON_UNESCAPED_UNICODE),
                        'setting_type' => 'string'
                    )
                );
                
                if ($result === false) {
                    error_log("Tabesh: Failed to save setting: pricing_paper_weights - Error: " . $wpdb->last_error);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $total_entries = 0;
                        foreach ($sanitized_data as $type => $weights) {
                            $total_entries += count($weights);
                        }
                        error_log("Tabesh: Successfully saved pricing_paper_weights with $total_entries weight entries across " . count($sanitized_data) . " paper types");
                    }
                }
            }
        }
        
        // Handle nested pricing_binding_matrix structure (binding_type => [book_size => price])
        if (isset($post_data['pricing_binding_matrix']) && is_array($post_data['pricing_binding_matrix'])) {
            $sanitized_data = array();
            foreach ($post_data['pricing_binding_matrix'] as $binding_type => $sizes) {
                $sanitized_type = sanitize_text_field($binding_type);
                $sanitized_data[$sanitized_type] = array();
                
                if (is_array($sizes)) {
                    foreach ($sizes as $book_size => $price) {
                        $sanitized_size = sanitize_text_field($book_size);
                        $sanitized_price = is_numeric($price) ? floatval($price) : 0;
                        $sanitized_data[$sanitized_type][$sanitized_size] = $sanitized_price;
                    }
                }
            }
            
            if (!empty($sanitized_data)) {
                $result = $wpdb->replace(
                    $table,
                    array(
                        'setting_key' => 'pricing_binding_matrix',
                        'setting_value' => wp_json_encode($sanitized_data, JSON_UNESCAPED_UNICODE),
                        'setting_type' => 'string'
                    )
                );
                
                if ($result === false) {
                    error_log("Tabesh: Failed to save setting: pricing_binding_matrix - Error: " . $wpdb->last_error);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $total_entries = 0;
                        foreach ($sanitized_data as $type => $sizes) {
                            $total_entries += count($sizes);
                        }
                        error_log("Tabesh: Successfully saved pricing_binding_matrix with $total_entries size entries across " . count($sanitized_data) . " binding types");
                    }
                }
            }
        }
        
        // Handle nested pricing_options_config structure (option_name => [price, type, step])
        if (isset($post_data['pricing_options_config']) && is_array($post_data['pricing_options_config'])) {
            $sanitized_data = array();
            foreach ($post_data['pricing_options_config'] as $option_name => $config) {
                $sanitized_name = sanitize_text_field($option_name);
                $sanitized_data[$sanitized_name] = array(
                    'price' => isset($config['price']) && is_numeric($config['price']) ? floatval($config['price']) : 0,
                    'type' => isset($config['type']) ? sanitize_text_field($config['type']) : 'fixed',
                    'step' => isset($config['step']) && is_numeric($config['step']) ? intval($config['step']) : 16000,
                );
            }
            
            if (!empty($sanitized_data)) {
                $result = $wpdb->replace(
                    $table,
                    array(
                        'setting_key' => 'pricing_options_config',
                        'setting_value' => wp_json_encode($sanitized_data, JSON_UNESCAPED_UNICODE),
                        'setting_type' => 'string'
                    )
                );
                
                if ($result === false) {
                    error_log("Tabesh: Failed to save setting: pricing_options_config - Error: " . $wpdb->last_error);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Tabesh: Successfully saved pricing_options_config with " . count($sanitized_data) . " options");
                    }
                }
            }
        }
        
        // Convert profit margin percentage to decimal (e.g., 10% -> 0.10)
        if (isset($post_data['pricing_profit_margin'])) {
            $profit_margin = floatval($post_data['pricing_profit_margin']) / 100;
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'pricing_profit_margin',
                    'setting_value' => $profit_margin,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: pricing_profit_margin - Error: " . $wpdb->last_error);
            }
        }
        
        // Handle file_allowed_ips textarea field
        if (isset($post_data['file_allowed_ips'])) {
            $value = sanitize_textarea_field($post_data['file_allowed_ips']);
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'file_allowed_ips',
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: file_allowed_ips - Error: " . $wpdb->last_error);
            }
        }
        
        // Handle file_admin_access_list array field (checkboxes)
        if (isset($post_data['file_admin_access_list'])) {
            $admin_list = array_map('intval', (array) $post_data['file_admin_access_list']);
            $value = wp_json_encode($admin_list, JSON_UNESCAPED_UNICODE);
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'file_admin_access_list',
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: file_admin_access_list - Error: " . $wpdb->last_error);
            }
        } else {
            // If no checkboxes selected, save empty array
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'file_admin_access_list',
                    'setting_value' => wp_json_encode(array(), JSON_UNESCAPED_UNICODE),
                    'setting_type' => 'string'
                )
            );
        }
        
        // Handle staff_allowed_users array field (comma-separated IDs from hidden input)
        if (isset($post_data['staff_allowed_users'])) {
            $user_ids_string = sanitize_text_field($post_data['staff_allowed_users']);
            $user_ids = array();
            
            if (!empty($user_ids_string)) {
                // Parse comma-separated IDs
                $ids = explode(',', $user_ids_string);
                foreach ($ids as $id) {
                    $id = intval(trim($id));
                    if ($id > 0) {
                        // Validate that user exists
                        if (get_userdata($id)) {
                            $user_ids[] = $id;
                        }
                    }
                }
            }
            
            $value = wp_json_encode(array_values(array_unique($user_ids)), JSON_UNESCAPED_UNICODE);
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'staff_allowed_users',
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: staff_allowed_users - Error: " . $wpdb->last_error);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Tabesh: Saved staff_allowed_users with " . count($user_ids) . " users");
                }
            }
        }
        
        // Handle admin_dashboard_allowed_users array field (comma-separated IDs from hidden input)
        if (isset($post_data['admin_dashboard_allowed_users'])) {
            $user_ids_string = sanitize_text_field($post_data['admin_dashboard_allowed_users']);
            $user_ids = array();
            
            if (!empty($user_ids_string)) {
                // Parse comma-separated IDs
                $ids = explode(',', $user_ids_string);
                foreach ($ids as $id) {
                    $id = intval(trim($id));
                    if ($id > 0) {
                        // Validate that user exists
                        if (get_userdata($id)) {
                            $user_ids[] = $id;
                        }
                    }
                }
            }
            
            $value = wp_json_encode(array_values(array_unique($user_ids)), JSON_UNESCAPED_UNICODE);
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'admin_dashboard_allowed_users',
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: admin_dashboard_allowed_users - Error: " . $wpdb->last_error);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Tabesh: Saved admin_dashboard_allowed_users with " . count($user_ids) . " users");
                }
            }
        }
        
        // Handle admin_order_form_allowed_roles array field (checkboxes)
        if (isset($post_data['admin_order_form_allowed_roles']) && is_array($post_data['admin_order_form_allowed_roles'])) {
            $roles = array_map('sanitize_text_field', $post_data['admin_order_form_allowed_roles']);
            // Always ensure 'administrator' is included
            if (!in_array('administrator', $roles, true)) {
                $roles[] = 'administrator';
            }
            $value = wp_json_encode(array_values(array_unique($roles)), JSON_UNESCAPED_UNICODE);
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'admin_order_form_allowed_roles',
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: admin_order_form_allowed_roles - Error: " . $wpdb->last_error);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Tabesh: Saved admin_order_form_allowed_roles with " . count($roles) . " roles");
                }
            }
        } else {
            // If no checkboxes selected, save default array with administrator
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'admin_order_form_allowed_roles',
                    'setting_value' => wp_json_encode(array('administrator'), JSON_UNESCAPED_UNICODE),
                    'setting_type' => 'string'
                )
            );
        }
        
        // Handle admin_order_form_allowed_users array field (comma-separated IDs from hidden input)
        if (isset($post_data['admin_order_form_allowed_users'])) {
            $user_ids_string = sanitize_text_field($post_data['admin_order_form_allowed_users']);
            $user_ids = array();
            
            if (!empty($user_ids_string)) {
                // Parse comma-separated IDs
                $ids = explode(',', $user_ids_string);
                foreach ($ids as $id) {
                    $id = intval(trim($id));
                    if ($id > 0) {
                        // Validate that user exists
                        if (get_userdata($id)) {
                            $user_ids[] = $id;
                        }
                    }
                }
            }
            
            $value = wp_json_encode(array_values(array_unique($user_ids)), JSON_UNESCAPED_UNICODE);
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => 'admin_order_form_allowed_users',
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: admin_order_form_allowed_users - Error: " . $wpdb->last_error);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Tabesh: Saved admin_order_form_allowed_users with " . count($user_ids) . " users");
                }
            }
        }
        
        // Handle SMS pattern variable configurations
        $pattern_types = array('admin_user_registration', 'admin_order_created', 'status_change');
        foreach ($pattern_types as $pattern_type) {
            $config_key = 'sms_pattern_vars_' . $pattern_type;
            $config = array();
            
            // Check if variable configuration was submitted for this pattern type
            if (isset($post_data[$config_key]) && is_array($post_data[$config_key])) {
                foreach ($post_data[$config_key] as $var_key => $var_config) {
                    $sanitized_var_key = sanitize_key($var_key);
                    $config[$sanitized_var_key] = array(
                        'enabled' => isset($var_config['enabled']) && $var_config['enabled'] === '1',
                        'order' => isset($var_config['order']) ? intval($var_config['order']) : 1
                    );
                }
            }
            
            // Save the configuration as JSON
            $value = wp_json_encode($config, JSON_UNESCAPED_UNICODE);
            $result = $wpdb->replace(
                $table,
                array(
                    'setting_key' => $config_key,
                    'setting_value' => $value,
                    'setting_type' => 'string'
                )
            );
            
            if ($result === false) {
                error_log("Failed to save setting: $config_key - Error: " . $wpdb->last_error);
            } else if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Tabesh: Saved $config_key with " . count($config) . " variables");
            }
        }
        
        // Save firewall settings using the firewall class
        if (isset($post_data['firewall_enabled']) || isset($post_data['firewall_secret_key'])) {
            $firewall = new Tabesh_Doomsday_Firewall();
            $firewall_settings = array();
            
            // Firewall enabled checkbox
            $firewall_settings['enabled'] = isset($post_data['firewall_enabled']) && $post_data['firewall_enabled'] === '1';
            
            // Secret key
            if (isset($post_data['firewall_secret_key'])) {
                $firewall_settings['secret_key'] = $post_data['firewall_secret_key'];
            }
            
            // Save firewall settings
            $firewall->save_settings($firewall_settings);
        }

        // Handle AI settings
        $ai_fields = array(
            'ai_enabled'           => 'checkbox',
            'ai_mode'              => 'scalar',
            'ai_gemini_api_key'    => 'scalar',
            'ai_gemini_model'      => 'scalar',
            'ai_server_url'        => 'scalar',
            'ai_server_api_key'    => 'scalar',
            'ai_access_orders'     => 'checkbox',
            'ai_access_users'      => 'checkbox',
            'ai_access_pricing'    => 'checkbox',
            'ai_access_woocommerce' => 'checkbox',
            'ai_cache_enabled'     => 'checkbox',
            'ai_cache_ttl'         => 'scalar',
            'ai_max_tokens'        => 'scalar',
            'ai_temperature'       => 'scalar',
        );

        foreach ( $ai_fields as $field => $type ) {
            if ( 'checkbox' === $type ) {
                $value = isset( $post_data[ $field ] ) ? '1' : '0';
            } else {
                $value = isset( $post_data[ $field ] ) ? sanitize_text_field( $post_data[ $field ] ) : '';
            }

            Tabesh_AI_Config::set( str_replace( 'ai_', '', $field ), $value );
        }

        // Handle AI allowed roles (array of checkboxes).
        if ( isset( $post_data['ai_allowed_roles'] ) && is_array( $post_data['ai_allowed_roles'] ) ) {
            $roles = array_map( 'sanitize_text_field', $post_data['ai_allowed_roles'] );
            Tabesh_AI_Config::set( 'allowed_roles', $roles );
        } else {
            Tabesh_AI_Config::set( 'allowed_roles', array() );
        }

        // Clear the settings cache after saving to ensure fresh data is loaded
        self::clear_settings_cache();
        
        // Clear the pricing configuration cache in the Order class
        // Check if class exists to handle cases where class hasn't been autoloaded yet
        if (class_exists('Tabesh_Order')) {
            Tabesh_Order::clear_pricing_cache();
        }
    }

    /**
     * Normalize value to JSON array
     * Handles string inputs (comma/newline separated) and ensures JSON array output
     *
     * @param mixed $value
     * @return string JSON-encoded array
     */
    private function normalize_to_json_array($value) {
        // If already a valid JSON array, validate and return
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Re-encode to ensure consistent format
                return wp_json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
            }
        }
        
        // If it's a PHP array, encode it
        if (is_array($value)) {
            return wp_json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }
        
        // Parse as comma or newline separated string
        if (is_string($value)) {
            $parts = preg_split('/[\r\n,]+/', $value);
            $parts = array_map('trim', $parts);
            $parts = array_values(array_filter($parts, 'strlen'));
            return wp_json_encode($parts, JSON_UNESCAPED_UNICODE);
        }
        
        // Fallback to empty array
        return wp_json_encode(array(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Normalize value to JSON object
     * Handles key=value format and ensures JSON object output
     *
     * @param mixed $value
     * @param bool $parse_array_values Whether to parse comma-separated values as arrays
     * @return string JSON-encoded object
     */
    private function normalize_to_json_object($value, $parse_array_values = false) {
        // If already a valid JSON object/array, validate and return
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Re-encode to ensure consistent format
                return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
        }
        
        // If it's a PHP array/object, encode it
        if (is_array($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        // Parse as key=value lines
        if (is_string($value)) {
            $lines = preg_split('/[\r\n]+/', $value);
            $obj = array();
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '=') === false) {
                    continue;
                }
                
                // Split on first equals sign only
                $equal_pos = strpos($line, '=');
                $key = trim(substr($line, 0, $equal_pos));
                $val = trim(substr($line, $equal_pos + 1));
                
                if ($key !== '' && $val !== '') {
                    // Check if we should parse comma-separated values as arrays
                    if ($parse_array_values && strpos($val, ',') !== false) {
                        // Parse as array of values
                        $parts = array_map('trim', explode(',', $val));
                        $array_values = array();
                        foreach ($parts as $part) {
                            if ($part !== '') {
                                // Convert to appropriate type
                                if (is_numeric($part)) {
                                    $array_values[] = strpos($part, '.') !== false ? floatval($part) : intval($part);
                                } else {
                                    $array_values[] = $part;
                                }
                            }
                        }
                        $obj[$key] = $array_values;
                    } else {
                        // Try to parse as number, otherwise keep as string
                        if (is_numeric($val)) {
                            $obj[$key] = strpos($val, '.') !== false ? floatval($val) : intval($val);
                        } else {
                            $obj[$key] = $val;
                        }
                    }
                }
            }
            
            return wp_json_encode($obj, JSON_UNESCAPED_UNICODE);
        }
        
        // Fallback to empty object
        return wp_json_encode(new stdClass(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get setting value
     * Delegates to main Tabesh class for consistency
     * 
     * @deprecated Use Tabesh()->get_setting() instead
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_setting($key, $default = '') {
        return Tabesh()->get_setting($key, $default);
    }

    /**
     * Clear settings cache
     * Delegates to main Tabesh class for consistency
     * 
     * @deprecated Use Tabesh::clear_settings_cache() instead
     * @param string|null $key Specific key to clear, or null to clear all
     * @return void
     */
    public static function clear_settings_cache($key = null) {
        Tabesh::clear_settings_cache($key);
    }

    /**
     * Get all orders
     *
     * @param string $status Filter by status
     * @param bool $archived Get archived orders
     * @return array
     */
    public function get_orders($status = '', $archived = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';

        // Build base query
        $query = "SELECT * FROM $table WHERE archived = %d";
        $query_params = array($archived ? 1 : 0);

        if (!empty($status)) {
            $query .= " AND status = %s";
            $query_params[] = $status;
        }

        $query .= " ORDER BY created_at DESC";
        
        $orders = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
        
        // Apply firewall filtering
        $firewall = new Tabesh_Doomsday_Firewall();
        $orders = $firewall->filter_orders_for_display($orders, get_current_user_id(), 'admin');
        
        return $orders;
    }

    /**
     * Get order statistics
     *
     * @return array
     */
    public function get_statistics() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';

        $stats = array(
            'total_orders' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE archived = %d", 0
            )),
            'pending_orders' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s AND archived = %d", 'pending', 0
            )),
            'processing_orders' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s AND archived = %d", 'processing', 0
            )),
            'completed_orders' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s AND archived = %d", 'completed', 0
            )),
            'total_revenue' => (float) ($wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_price) FROM $table WHERE status = %s", 'completed'
            )) ?? 0),
        );

        return $stats;
    }

    /**
     * Check if a user has access to admin dashboard
     * Always reads from database to prevent cache-related security issues
     * 
     * Note: This method intentionally does not use caching for security reasons.
     * It's designed to be called once per page load for critical access control checks.
     * 
     * @param int $user_id User ID to check
     * @return bool True if user has access, false otherwise
     */
    public function user_has_admin_dashboard_access($user_id) {
        // Admins always have access
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        if (user_can($user, 'manage_woocommerce')) {
            return true;
        }
        
        // Check allowed users list - read directly from database, no cache
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_settings';
        
        $allowed_users_json = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            'admin_dashboard_allowed_users'
        ));
        
        // Check for database errors
        if ($wpdb->last_error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh Security: Database error in user_has_admin_dashboard_access - ' . $wpdb->last_error);
            }
            return false;
        }
        
        if ($allowed_users_json === null) {
            return false;
        }
        
        $allowed_users = json_decode($allowed_users_json, true);
        if (!is_array($allowed_users)) {
            return false;
        }
        
        // Cast user_id to int once for comparison
        $user_id = (int) $user_id;
        return in_array($user_id, array_map('intval', $allowed_users), true);
    }

    /**
     * Render admin dashboard shortcode
     * 
     * Shows different content based on user role:
     * - Admin users: Full dashboard with statistics and all orders
     * - Regular users: Customer files panel for their own orders
     *
     * @param array $atts
     * @return string
     */
    public function render_admin_dashboard($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="tabesh-notice error">' . 
                   __('برای دسترسی به این بخش باید وارد حساب کاربری خود شوید.', 'tabesh') . 
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('ورود', 'tabesh') . '</a>' .
                   '</div>';
        }

        ob_start();
        include TABESH_PLUGIN_DIR . 'templates/admin/shortcode-admin-dashboard.php';
        return ob_get_clean();
    }

    /**
     * REST API: Search orders for admin dashboard
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function rest_search_orders($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';

        $query = sanitize_text_field($request->get_param('query') ?? '');
        $status = sanitize_text_field($request->get_param('status') ?? '');
        $sort_by = sanitize_text_field($request->get_param('sort_by') ?? 'newest');
        $page = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?? 20)));

        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where_conditions = array('archived = 0');
        $params = array();

        if (!empty($query)) {
            $query_like = '%' . $wpdb->esc_like($query) . '%';
            $where_conditions[] = "(
                o.order_number LIKE %s 
                OR o.book_title LIKE %s 
                OR o.book_size LIKE %s
                OR u.display_name LIKE %s
                OR u.ID = %s
            )";
            $params[] = $query_like;
            $params[] = $query_like;
            $params[] = $query_like;
            $params[] = $query_like;
            $params[] = $query; // Direct user ID match
        }

        if (!empty($status)) {
            $where_conditions[] = "o.status = %s";
            $params[] = $status;
        }

        $where_sql = implode(' AND ', $where_conditions);

        // Build ORDER BY clause
        switch ($sort_by) {
            case 'oldest':
                $order_sql = 'o.created_at ASC';
                break;
            case 'quantity_high':
                $order_sql = 'o.quantity DESC';
                break;
            case 'quantity_low':
                $order_sql = 'o.quantity ASC';
                break;
            case 'price_high':
                $order_sql = 'o.total_price DESC';
                break;
            case 'price_low':
                $order_sql = 'o.total_price ASC';
                break;
            case 'newest':
            default:
                $order_sql = 'o.created_at DESC';
        }

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table o LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID WHERE $where_sql";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Get orders
        $orders_sql = "SELECT o.*, u.display_name as customer_name 
                       FROM $table o 
                       LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID 
                       WHERE $where_sql 
                       ORDER BY $order_sql 
                       LIMIT %d OFFSET %d";
        
        $query_params = array_merge($params, array($per_page, $offset));
        $orders = $wpdb->get_results($wpdb->prepare($orders_sql, $query_params));
        
        // Apply firewall filtering
        $firewall = new Tabesh_Doomsday_Firewall();
        $orders = $firewall->filter_orders_for_display($orders, get_current_user_id(), 'admin');

        // Format response
        $formatted_orders = array();
        foreach ($orders as $order) {
            $formatted_orders[] = array(
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'book_title' => $order->book_title,
                'book_size' => $order->book_size,
                'quantity' => (int) $order->quantity,
                'total_price' => (float) $order->total_price,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'user_id' => (int) $order->user_id,
                'created_at' => $order->created_at,
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'orders' => $formatted_orders,
                'total' => $total,
                'total_pages' => ceil($total / $per_page),
                'current_page' => $page,
            )
        ), 200);
    }

    /**
     * REST API: Get order details for admin dashboard
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function rest_get_order_details($request) {
        $order_id = (int) $request->get_param('order_id');

        if ($order_id <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شناسه سفارش نامعتبر است', 'tabesh')
            ), 400);
        }

        ob_start();
        include TABESH_PLUGIN_DIR . 'templates/admin/partials/order-details-tabs.php';
        $html = ob_get_clean();

        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'html' => $html
            )
        ), 200);
    }

    /**
     * REST API: Update order details
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function rest_update_order($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        $order_id = (int) $request->get_param('order_id');
        $params = $request->get_json_params();

        if ($order_id <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شناسه سفارش نامعتبر است', 'tabesh')
            ), 400);
        }

        // Get current order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $order_id
        ));

        if (!$order) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('سفارش یافت نشد', 'tabesh')
            ), 404);
        }

        // Prepare update data - only update provided fields
        $update_data = array();
        $update_formats = array();

        $allowed_fields = array(
            'book_title' => '%s',
            'book_size' => '%s',
            'paper_type' => '%s',
            'paper_weight' => '%s',
            'page_count_color' => '%d',
            'page_count_bw' => '%d',
            'quantity' => '%d',
            'total_price' => '%f',
            'notes' => '%s',
        );

        foreach ($allowed_fields as $field => $format) {
            if (isset($params[$field])) {
                $value = $params[$field];
                
                // Sanitize based on field type
                if ($format === '%s') {
                    $update_data[$field] = sanitize_text_field($value);
                } elseif ($format === '%d') {
                    $update_data[$field] = intval($value);
                } elseif ($format === '%f') {
                    $update_data[$field] = floatval($value);
                }
                
                $update_formats[] = $format;
            }
        }

        // Add updated_at timestamp
        $update_data['updated_at'] = current_time('mysql');
        $update_formats[] = '%s';

        // Calculate page_count_total if pages changed
        if (isset($update_data['page_count_color']) || isset($update_data['page_count_bw'])) {
            $color = isset($update_data['page_count_color']) ? $update_data['page_count_color'] : $order->page_count_color;
            $bw = isset($update_data['page_count_bw']) ? $update_data['page_count_bw'] : $order->page_count_bw;
            $update_data['page_count_total'] = $color + $bw;
            $update_formats[] = '%d';
        }

        if (empty($update_data)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('هیچ داده‌ای برای به‌روزرسانی ارسال نشده', 'tabesh')
            ), 400);
        }

        // Perform update
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $order_id),
            $update_formats,
            array('%d')
        );

        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('خطا در به‌روزرسانی سفارش', 'tabesh')
            ), 500);
        }

        // Log the update
        $logs_table = $wpdb->prefix . 'tabesh_logs';
        $current_user = wp_get_current_user();
        
        $wpdb->insert(
            $logs_table,
            array(
                'order_id' => $order_id,
                'user_id' => $order->user_id,
                'staff_user_id' => get_current_user_id(),
                'action' => 'order_edit',
                'description' => sprintf(
                    __('سفارش توسط %s ویرایش شد', 'tabesh'),
                    $current_user->display_name
                )
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('اطلاعات سفارش با موفقیت به‌روزرسانی شد', 'tabesh')
        ), 200);
    }

    /**
     * REST API: Update customer profile
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function rest_update_customer($request) {
        $user_id = (int) $request->get_param('user_id');
        $params = $request->get_json_params();

        if ($user_id <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شناسه کاربر نامعتبر است', 'tabesh')
            ), 400);
        }

        // Check if user exists
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('کاربر یافت نشد', 'tabesh')
            ), 404);
        }

        // Define allowed fields for billing and shipping
        $billing_fields = array(
            'billing_first_name',
            'billing_last_name',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
            'billing_phone'
        );

        $shipping_fields = array(
            'shipping_first_name',
            'shipping_last_name',
            'shipping_company',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_city',
            'shipping_state',
            'shipping_postcode',
            'shipping_country',
            'shipping_phone'
        );

        $all_allowed_fields = array_merge($billing_fields, $shipping_fields);
        $updated_fields = array();

        // Fields that may contain multiple lines (addresses)
        $textarea_fields = array(
            'billing_address_1',
            'billing_address_2',
            'shipping_address_1',
            'shipping_address_2'
        );

        // Update user meta for each provided field
        foreach ($all_allowed_fields as $field) {
            if (isset($params[$field])) {
                // Use sanitize_textarea_field for address fields to preserve line breaks
                // Use sanitize_text_field for other fields
                if (in_array($field, $textarea_fields, true)) {
                    $value = sanitize_textarea_field($params[$field]);
                } else {
                    $value = sanitize_text_field($params[$field]);
                }
                update_user_meta($user_id, $field, $value);
                $updated_fields[] = $field;
            }
        }

        if (empty($updated_fields)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('هیچ داده‌ای برای به‌روزرسانی ارسال نشده', 'tabesh')
            ), 400);
        }

        // Log the update (order_id is null since this is a customer profile update, not an order update)
        global $wpdb;
        $logs_table = $wpdb->prefix . 'tabesh_logs';
        $current_user = wp_get_current_user();
        
        $wpdb->insert(
            $logs_table,
            array(
                'order_id' => null,
                'user_id' => $user_id,
                'staff_user_id' => get_current_user_id(),
                'action' => 'customer_profile_edit',
                'description' => sprintf(
                    /* translators: 1: admin display name, 2: updated fields count */
                    __('پروفایل مشتری توسط %1$s ویرایش شد (%2$d فیلد)', 'tabesh'),
                    $current_user->display_name,
                    count($updated_fields)
                )
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('پروفایل مشتری با موفقیت به‌روزرسانی شد', 'tabesh'),
            'updated_fields' => $updated_fields
        ), 200);
    }

    /**
     * Normalize Iranian mobile number to standard format
     * Detects and converts various Iranian mobile formats to 09xxxxxxxxx
     *
     * @param string $phone Phone number to normalize
     * @return string|null Normalized phone number or null if invalid
     */
    public static function normalize_iranian_mobile($phone) {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Pattern 1: 09xxxxxxxxx (11 digits) - already correct
        if (preg_match('/^09\d{9}$/', $phone)) {
            return $phone;
        }

        // Pattern 2: 9xxxxxxxxx (10 digits) - add leading 0
        if (preg_match('/^9\d{9}$/', $phone)) {
            return '0' . $phone;
        }

        // Pattern 3: 989xxxxxxxxx (12 digits with country code) - remove 98
        if (preg_match('/^989\d{9}$/', $phone)) {
            return '0' . substr($phone, 2);
        }

        // Pattern 4: 00989xxxxxxxxx (14 digits with 00 prefix) - remove 0098
        if (preg_match('/^00989\d{9}$/', $phone)) {
            return '0' . substr($phone, 4);
        }

        // Not a valid Iranian mobile number
        return null;
    }

    /**
     * Get customer's mobile number from username or billing data
     * First checks if username is a mobile number, then falls back to billing_phone
     *
     * @param WP_User $user WordPress user object
     * @return string Mobile number or empty string
     */
    public static function get_customer_mobile($user) {
        if (!$user) {
            return '';
        }

        // First, try to extract mobile from username
        $username = $user->user_login;
        $normalized = self::normalize_iranian_mobile($username);
        
        if ($normalized) {
            return $normalized;
        }

        // If username is not a mobile number, check billing_phone
        $billing_phone = get_user_meta($user->ID, 'billing_phone', true);
        if (!empty($billing_phone)) {
            $normalized = self::normalize_iranian_mobile($billing_phone);
            if ($normalized) {
                return $normalized;
            }
            // Return as-is if not Iranian mobile format
            return $billing_phone;
        }

        return '';
    }

    /**
     * Get customer's full name
     * Returns display_name if set, otherwise combines first_name and last_name
     *
     * @param WP_User $user WordPress user object
     * @return string Full name or empty string
     */
    public static function get_customer_full_name($user) {
        if (!$user) {
            return '';
        }

        // First, try display_name
        if (!empty($user->display_name) && $user->display_name !== $user->user_login) {
            return $user->display_name;
        }

        // Fall back to first_name + last_name
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);

        $full_name = trim($first_name . ' ' . $last_name);
        
        return !empty($full_name) ? $full_name : '';
    }

    /**
     * Debug log helper - only logs when WP_DEBUG is enabled
     *
     * @param string $message The message to log
     * @return void
     */
    private function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh: ' . $message);
        }
    }
}
