<?php
/**
 * User Management Class
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_User {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialization
    }

    /**
     * Get user orders
     *
     * @param int $user_id
     * @return array
     */
    public function get_user_orders($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND archived = 0 ORDER BY created_at DESC",
            $user_id
        ));
    }

    /**
     * Get user archived orders
     *
     * @param int $user_id
     * @return array
     */
    public function get_user_archived_orders($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND archived = 1 ORDER BY created_at DESC",
            $user_id
        ));
    }

    /**
     * Get order status steps
     *
     * @param string $current_status
     * @return array
     */
    public function get_status_steps($current_status) {
        $steps = array(
            'pending' => array(
                'label' => __('در انتظار بررسی', 'tabesh'),
                'icon' => 'clock',
                'completed' => false
            ),
            'confirmed' => array(
                'label' => __('تایید شده', 'tabesh'),
                'icon' => 'check',
                'completed' => false
            ),
            'processing' => array(
                'label' => __('در حال چاپ', 'tabesh'),
                'icon' => 'printer',
                'completed' => false
            ),
            'ready' => array(
                'label' => __('آماده تحویل', 'tabesh'),
                'icon' => 'box',
                'completed' => false
            ),
            'completed' => array(
                'label' => __('تحویل داده شده', 'tabesh'),
                'icon' => 'check-circle',
                'completed' => false
            )
        );

        $status_order = array('pending', 'confirmed', 'processing', 'ready', 'completed');
        $current_index = array_search($current_status, $status_order);

        if ($current_index !== false) {
            foreach ($status_order as $index => $status) {
                if ($index <= $current_index) {
                    $steps[$status]['completed'] = true;
                }
            }
        }

        return $steps;
    }

    /**
     * Render user orders shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render_user_orders($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('لطفا برای مشاهده سفارشات خود وارد شوید.', 'tabesh') . ' <a href="' . wp_login_url(get_permalink()) . '">' . __('ورود', 'tabesh') . '</a></p>';
        }

        // Enqueue modern user orders CSS and JS only when shortcode is used
        wp_enqueue_style(
            'tabesh-user-orders-modern',
            TABESH_PLUGIN_URL . 'assets/css/user-orders-modern.css',
            array(),
            TABESH_VERSION
        );

        wp_enqueue_script(
            'tabesh-user-orders-modern',
            TABESH_PLUGIN_URL . 'assets/js/user-orders-modern.js',
            array('jquery'),
            TABESH_VERSION,
            true
        );

        ob_start();
        include TABESH_PLUGIN_DIR . 'templates/frontend/user-orders.php';
        return ob_get_clean();
    }

    /**
     * Get time remaining until expiration
     *
     * @param string $expires_at Expiration datetime
     * @return array Array with 'expired' bool and 'text' string
     */
    public function get_time_remaining($expires_at) {
        if (empty($expires_at)) {
            return array('expired' => false, 'text' => '');
        }

        $expires_timestamp = strtotime($expires_at);
        $current_timestamp = current_time('timestamp');
        $time_diff = $expires_timestamp - $current_timestamp;
        
        if ($time_diff <= 0) {
            return array('expired' => true, 'text' => __('منقضی شده', 'tabesh'));
        }

        $days = floor($time_diff / (60 * 60 * 24));
        $hours = floor(($time_diff % (60 * 60 * 24)) / (60 * 60));
        $minutes = floor(($time_diff % (60 * 60)) / 60);
        
        if ($days > 0) {
            $text = sprintf(__('%d روز و %d ساعت', 'tabesh'), $days, $hours);
        } elseif ($hours > 0) {
            $text = sprintf(__('%d ساعت و %d دقیقه', 'tabesh'), $hours, $minutes);
        } else {
            $text = sprintf(__('%d دقیقه', 'tabesh'), $minutes);
        }

        return array('expired' => false, 'text' => $text);
    }

    /**
     * Check if user can reupload approved file
     *
     * @param object $file File object
     * @param object $order Order object
     * @return bool
     */
    public function can_reupload_approved_file($file, $order) {
        // Check if admin has allowed reupload
        $allow_reupload = Tabesh()->get_setting('allow_reupload_approved', false);
        
        if ($allow_reupload) {
            return true;
        }

        // Otherwise, uploads are blocked after approval
        return false;
    }

    /**
     * Render status badge HTML
     *
     * @param string $status File status
     * @return string HTML for status badge
     */
    public function render_status_badge($status) {
        $badges = array(
            'pending' => array(
                'class' => 'status-pending',
                'icon' => 'dashicons-clock',
                'label' => __('در انتظار بررسی', 'tabesh')
            ),
            'approved' => array(
                'class' => 'status-approved',
                'icon' => 'dashicons-yes',
                'label' => __('تایید شده', 'tabesh')
            ),
            'rejected' => array(
                'class' => 'status-rejected',
                'icon' => 'dashicons-dismiss',
                'label' => __('رد شده', 'tabesh')
            )
        );

        $badge = isset($badges[$status]) ? $badges[$status] : $badges['pending'];
        
        return sprintf(
            '<span class="status-badge %s"><span class="dashicons %s"></span> %s</span>',
            esc_attr($badge['class']),
            esc_attr($badge['icon']),
            esc_html($badge['label'])
        );
    }

    /**
     * Get status label
     *
     * @param string $status Status key
     * @return string Status label
     */
    public function get_status_label($status) {
        $labels = array(
            'pending' => __('در انتظار بررسی', 'tabesh'),
            'confirmed' => __('تایید شده', 'tabesh'),
            'processing' => __('در حال چاپ', 'tabesh'),
            'ready' => __('آماده تحویل', 'tabesh'),
            'completed' => __('تحویل داده شده', 'tabesh'),
            'cancelled' => __('لغو شده', 'tabesh')
        );
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * REST API: Search user orders
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function search_user_orders($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(array('error' => 'Unauthorized'), 401);
        }

        $search = $request->get_param('q');
        
        if (empty($search)) {
            return new WP_REST_Response(array('orders' => array()), 200);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        $search_term = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE user_id = %d 
            AND archived = 0 
            AND (
                book_title LIKE %s 
                OR order_number LIKE %s 
                OR book_size LIKE %s
                OR CAST(id AS CHAR) LIKE %s
            )
            ORDER BY created_at DESC
            LIMIT 20",
            $user_id,
            $search_term,
            $search_term,
            $search_term,
            $search_term
        ));

        // Format results
        $formatted = array_map(function($order) {
            return array(
                'id' => $order->id,
                'order_number' => $order->order_number,
                'book_title' => $order->book_title,
                'book_size' => $order->book_size,
                'page_count' => $order->page_count_total,
                'quantity' => $order->quantity,
                'total_price' => $order->total_price,
                'status' => $order->status,
                'status_label' => $this->get_status_label($order->status),
                'created_at' => $order->created_at
            );
        }, $results);

        return new WP_REST_Response(array('orders' => $formatted), 200);
    }

    /**
     * REST API: Get orders summary (total count, total price)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_orders_summary($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(array('error' => 'Unauthorized'), 401);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(total_price) as total_price,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status IN ('pending', 'confirmed', 'processing', 'ready') THEN 1 ELSE 0 END) as active_orders
            FROM $table 
            WHERE user_id = %d AND archived = 0",
            $user_id
        ));

        return new WP_REST_Response(array(
            'total_orders' => intval($summary->total_orders),
            'total_price' => floatval($summary->total_price),
            'completed_orders' => intval($summary->completed_orders),
            'active_orders' => intval($summary->active_orders)
        ), 200);
    }

    /**
     * REST API: Get full order details
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_order_details($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(array('error' => 'Unauthorized'), 401);
        }

        $order_id = intval($request->get_param('order_id'));
        
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $order_id,
            $user_id
        ));

        if (!$order) {
            return new WP_REST_Response(array('error' => 'Order not found'), 404);
        }

        // Get status steps
        $status_steps = $this->get_status_steps($order->status);

        // Parse extras
        $extras = maybe_unserialize($order->extras);
        if (!is_array($extras)) {
            $extras = array();
        }

        // Format order details
        $details = array(
            'id' => $order->id,
            'order_number' => $order->order_number,
            'book_title' => $order->book_title,
            'book_size' => $order->book_size,
            'paper_type' => $order->paper_type,
            'paper_weight' => $order->paper_weight,
            'print_type' => $order->print_type,
            'page_count_color' => $order->page_count_color,
            'page_count_bw' => $order->page_count_bw,
            'page_count_total' => $order->page_count_total,
            'quantity' => $order->quantity,
            'binding_type' => $order->binding_type,
            'license_type' => $order->license_type,
            'cover_paper_weight' => $order->cover_paper_weight,
            'lamination_type' => $order->lamination_type,
            'extras' => $extras,
            'total_price' => $order->total_price,
            'status' => $order->status,
            'status_label' => $this->get_status_label($order->status),
            'status_steps' => $status_steps,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'notes' => $order->notes
        );

        return new WP_REST_Response(array('order' => $details), 200);
    }
}
