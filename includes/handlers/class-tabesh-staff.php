<?php
/**
 * Staff Management Class
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_Staff {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialization
    }

    /**
     * Update order status via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_status_rest($request) {
        $params = $request->get_json_params();
        $order_id = intval($params['order_id'] ?? 0);
        $status = sanitize_text_field($params['status'] ?? '');

        if (!$order_id || !$status) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('پارامترهای ناقص', 'tabesh')
            ), 400);
        }

        $order = Tabesh()->order;
        $result = $order->update_status($order_id, $status);

        if ($result) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('وضعیت با موفقیت به‌روزرسانی شد', 'tabesh')
            ), 200);
        }

        return new WP_REST_Response(array(
            'success' => false,
            'message' => __('خطا در به‌روزرسانی وضعیت', 'tabesh')
        ), 400);
    }

    /**
     * Get assigned orders for staff
     *
     * @param int $staff_id
     * @return array
     */
    public function get_assigned_orders($staff_id = null) {
        if ($staff_id === null) {
            $staff_id = get_current_user_id();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';

        // For now, staff can see all active orders
        // In future, implement assignment system
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE archived = %d ORDER BY created_at DESC", 0
        ));
    }

    /**
     * Render staff panel shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render_staff_panel($atts) {
        if (!current_user_can('edit_shop_orders')) {
            return '<p>' . __('شما اجازه دسترسی به این بخش را ندارید.', 'tabesh') . '</p>';
        }

        ob_start();
        include TABESH_PLUGIN_DIR . 'templates/frontend/staff-panel.php';
        return ob_get_clean();
    }
}
