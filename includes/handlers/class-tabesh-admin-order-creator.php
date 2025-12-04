<?php
/**
 * Admin Order Creator Class
 * 
 * Independent class for creating orders on behalf of customers.
 * Allows administrators to place orders without customer presence.
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_Admin_Order_Creator {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize actions
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue frontend assets for the modal
     */
    public function enqueue_assets() {
        // Only enqueue on pages with admin dashboard shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'tabesh_admin_dashboard')) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'tabesh-admin-order-creator',
            TABESH_PLUGIN_URL . 'assets/css/admin-order-creator.css',
            array(),
            TABESH_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'tabesh-admin-order-creator',
            TABESH_PLUGIN_URL . 'assets/js/admin-order-creator.js',
            array('jquery'),
            TABESH_VERSION,
            true
        );

        // Get settings for frontend
        $paper_types = Tabesh()->get_setting('paper_types', array());
        
        // Localize script with necessary data
        wp_localize_script('tabesh-admin-order-creator', 'tabeshAdminOrderCreator', array(
            'restUrl' => rest_url(TABESH_REST_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => array(
                'paperTypes' => $paper_types
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
            )
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only on Tabesh admin pages
        if (strpos($hook, 'tabesh') === false) {
            return;
        }

        wp_enqueue_style(
            'tabesh-admin-order-creator',
            TABESH_PLUGIN_URL . 'assets/css/admin-order-creator.css',
            array(),
            TABESH_VERSION
        );

        wp_enqueue_script(
            'tabesh-admin-order-creator',
            TABESH_PLUGIN_URL . 'assets/js/admin-order-creator.js',
            array('jquery'),
            TABESH_VERSION,
            true
        );
    }

    /**
     * REST API: Search users with live search
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_search_users_live($request) {
        $search = sanitize_text_field($request->get_param('search') ?? '');
        
        if (strlen($search) < 2) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('حداقل ۲ کاراکتر برای جستجو وارد کنید', 'tabesh')
            ), 400);
        }

        // Search users by login, name, or mobile
        $args = array(
            'search' => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_nicename', 'display_name', 'user_email'),
            'number' => 20,
            'orderby' => 'display_name',
            'order' => 'ASC',
        );

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        $formatted_users = array();
        foreach ($users as $user) {
            $formatted_users[] = array(
                'id' => $user->ID,
                'user_login' => $user->user_login,
                'display_name' => $user->display_name,
                'first_name' => get_user_meta($user->ID, 'first_name', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'users' => $formatted_users
        ), 200);
    }

    /**
     * REST API: Create new user
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_create_user($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $mobile = sanitize_text_field($params['mobile'] ?? '');
        $first_name = sanitize_text_field($params['first_name'] ?? '');
        $last_name = sanitize_text_field($params['last_name'] ?? '');

        if (empty($mobile) || empty($first_name) || empty($last_name)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('تمام فیلدها الزامی هستند', 'tabesh')
            ), 400);
        }

        // Validate mobile format (Iranian mobile: 09xxxxxxxxx)
        if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فرمت شماره موبایل نامعتبر است. فرمت صحیح: 09xxxxxxxxx', 'tabesh')
            ), 400);
        }

        // Check if user already exists
        $existing_user = get_user_by('login', $mobile);
        if ($existing_user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('کاربری با این شماره موبایل قبلاً ثبت شده است', 'tabesh')
            ), 400);
        }

        // Generate secure random password
        $password = wp_generate_password(16, true, true);

        // Create user
        $user_id = wp_create_user($mobile, $password, ''); // No email

        if (is_wp_error($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $user_id->get_error_message()
            ), 400);
        }

        // Set user meta
        $display_name = $first_name . ' ' . $last_name;
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'nickname' => $first_name,
        ));

        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);

        return new WP_REST_Response(array(
            'success' => true,
            'user_id' => $user_id,
            'user' => array(
                'id' => $user_id,
                'user_login' => $mobile,
                'display_name' => $display_name,
                'first_name' => $first_name,
                'last_name' => $last_name,
            ),
            'message' => __('کاربر با موفقیت ایجاد شد', 'tabesh')
        ), 201);
    }

    /**
     * REST API: Create order on behalf of customer
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_create_order($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $user_id = intval($params['user_id'] ?? 0);
        if ($user_id <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شناسه کاربر نامعتبر است', 'tabesh')
            ), 400);
        }

        // Verify user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('کاربر یافت نشد', 'tabesh')
            ), 404);
        }

        // Validate book_title
        if (empty($params['book_title']) || trim($params['book_title']) === '') {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('عنوان کتاب الزامی است', 'tabesh')
            ), 400);
        }

        // Check if price override is provided
        $override_price = isset($params['override_price']) && !empty($params['override_price']) 
            ? floatval($params['override_price']) 
            : null;

        // Generate order number
        $order_number = 'TB-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Calculate price using existing method (unless overridden)
        if ($override_price === null) {
            $order_handler = Tabesh()->order;
            $price_data = $order_handler->calculate_price($params);
            $total_price = $price_data['total_price'];
            $page_count_total = $price_data['page_count_total'];
        } else {
            // Use override price
            $total_price = $override_price;
            $page_count_total = intval($params['page_count_color'] ?? 0) + intval($params['page_count_bw'] ?? 0);
            if ($page_count_total % 2 !== 0) {
                $page_count_total++;
            }
        }

        // Sanitize extras array
        $extras = array();
        if (isset($params['extras']) && is_array($params['extras'])) {
            foreach ($params['extras'] as $extra) {
                $sanitized = sanitize_text_field($extra);
                if (!empty($sanitized)) {
                    $extras[] = $sanitized;
                }
            }
        }

        // Prepare order data
        $order_data = array(
            'user_id' => $user_id,
            'order_number' => $order_number,
            'book_title' => sanitize_text_field($params['book_title']),
            'book_size' => sanitize_text_field($params['book_size'] ?? ''),
            'paper_type' => sanitize_text_field($params['paper_type'] ?? ''),
            'paper_weight' => sanitize_text_field($params['paper_weight'] ?? ''),
            'print_type' => sanitize_text_field($params['print_type'] ?? ''),
            'page_count_color' => intval($params['page_count_color'] ?? 0),
            'page_count_bw' => intval($params['page_count_bw'] ?? 0),
            'page_count_total' => $page_count_total,
            'quantity' => intval($params['quantity'] ?? 0),
            'binding_type' => sanitize_text_field($params['binding_type'] ?? ''),
            'license_type' => sanitize_text_field($params['license_type'] ?? ''),
            'cover_paper_type' => sanitize_text_field($params['cover_paper_type'] ?? ''),
            'cover_paper_weight' => sanitize_text_field($params['cover_paper_weight'] ?? '250'),
            'lamination_type' => sanitize_text_field($params['lamination_type'] ?? 'براق'),
            'extras' => maybe_serialize($extras),
            'files' => null,
            'total_price' => $total_price,
            'status' => 'pending',
            'notes' => sanitize_textarea_field($params['notes'] ?? '')
        );

        // Create order using existing method
        $order_handler = Tabesh()->order;
        $order_id = $order_handler->create_order($order_data);

        if (is_wp_error($order_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $order_id->get_error_message()
            ), 400);
        }

        // Store metadata about order creation
        global $wpdb;
        $current_user_id = get_current_user_id();
        update_post_meta($order_id, '_created_by_admin', $current_user_id);
        
        // Also store in orders table as a note if possible
        $table_orders = $wpdb->prefix . 'tabesh_orders';
        $current_notes = $order_data['notes'];
        $admin_note = sprintf(
            __('سفارش توسط مدیر (کاربر #%d) ثبت شد', 'tabesh'),
            $current_user_id
        );
        $updated_notes = !empty($current_notes) 
            ? $current_notes . "\n\n" . $admin_note 
            : $admin_note;
        
        $wpdb->update(
            $table_orders,
            array('notes' => $updated_notes),
            array('id' => $order_id),
            array('%s'),
            array('%d')
        );

        // Fire action hook for notifications
        do_action('tabesh_order_submitted', $order_id, $order_data);

        // Log action
        $wpdb->insert(
            $wpdb->prefix . 'tabesh_logs',
            array(
                'order_id' => $order_id,
                'user_id' => $current_user_id,
                'action' => 'admin_order_created',
                'description' => sprintf(
                    __('سفارش #%s برای کاربر %s توسط مدیر ایجاد شد', 'tabesh'),
                    $order_number,
                    $user->display_name
                )
            )
        );

        return new WP_REST_Response(array(
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'message' => __('سفارش با موفقیت ثبت شد', 'tabesh')
        ), 201);
    }

    /**
     * Render the "New Order" button for admin dashboard
     */
    public function render_new_order_button() {
        ?>
        <button type="button" class="button button-primary" id="tabesh-open-order-modal">
            <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
            <?php echo esc_html__('ثبت سفارش جدید', 'tabesh'); ?>
        </button>
        <?php
    }

    /**
     * Render the order creator modal
     */
    public function render_order_modal() {
        include TABESH_PLUGIN_DIR . 'templates/admin/admin-order-creator-modal.php';
    }
}
