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
        $source_table = sanitize_text_field($params['source_table'] ?? 'main');

        if (!$order_id || !$status) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('پارامترهای ناقص', 'tabesh')
            ), 400);
        }

        // Validate source_table parameter
        if (!in_array($source_table, array('main', 'archived', 'cancelled'), true)) {
            $source_table = 'main';
        }

        // Get current order to track old status
        global $wpdb;
        
        // Determine which table to look in
        $table = $wpdb->prefix . 'tabesh_orders';
        if ($source_table === 'archived') {
            $table = $wpdb->prefix . 'tabesh_orders_archived';
        } elseif ($source_table === 'cancelled') {
            $table = $wpdb->prefix . 'tabesh_orders_cancelled';
        }
        
        $current_order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $order_id
        ));

        if (!$current_order) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('سفارش یافت نشد', 'tabesh')
            ), 404);
        }

        $old_status = $current_order->status;

        // Update order status (may move between tables)
        $order = Tabesh()->order;
        $result = $order->update_status($order_id, $status, $source_table);

        if ($result) {
            // Log the status change with staff information
            $current_user = wp_get_current_user();
            $staff_user_id = get_current_user_id();
            
            $logs_table = $wpdb->prefix . 'tabesh_logs';
            $wpdb->insert(
                $logs_table,
                array(
                    'order_id' => $order_id,
                    'user_id' => $current_order->user_id,
                    'staff_user_id' => $staff_user_id,
                    'action' => 'status_change',
                    'old_status' => $old_status,
                    'new_status' => $status,
                    'description' => sprintf(
                        __('وضعیت توسط %s از "%s" به "%s" تغییر کرد', 'tabesh'),
                        $current_user->display_name,
                        $old_status,
                        $status
                    )
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
            );

            // Prepare response with move info if order was moved
            $response_data = array(
                'success' => true,
                'message' => __('وضعیت با موفقیت به‌روزرسانی شد', 'tabesh'),
                'staff_name' => $current_user->display_name,
                'old_status' => $old_status,
                'new_status' => $status
            );
            
            // Add move info if order was moved between tables
            if (is_array($result) && isset($result['moved']) && $result['moved']) {
                $response_data['moved'] = true;
                $response_data['move_type'] = $result['move_type'];
                $response_data['new_order_id'] = $result['new_order_id'];
            }

            return new WP_REST_Response($response_data, 200);
        }

        return new WP_REST_Response(array(
            'success' => false,
            'message' => __('خطا در به‌روزرسانی وضعیت', 'tabesh')
        ), 400);
    }
    
    /**
     * Search orders via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function search_orders_rest($request) {
        $query = sanitize_text_field($request->get_param('q') ?? '');
        $page = intval($request->get_param('page') ?? 1);
        $per_page = intval($request->get_param('per_page') ?? 3);
        
        if (empty($query)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('عبارت جستجو خالی است', 'tabesh')
            ), 400);
        }
        
        $results = $this->search_orders($query, $page, $per_page);
        
        return new WP_REST_Response(array(
            'success' => true,
            'results' => $results['orders'],
            'total' => $results['total'],
            'page' => $page,
            'per_page' => $per_page,
            'has_more' => $results['has_more']
        ), 200);
    }
    
    /**
     * Search orders by various criteria
     *
     * @param string $query Search query
     * @param int $page Page number
     * @param int $per_page Results per page
     * @return array Search results
     */
    public function search_orders($query, $page = 1, $per_page = 3) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        $offset = ($page - 1) * $per_page;
        $query_like = '%' . $wpdb->esc_like($query) . '%';
        
        // Search in multiple fields
        $sql = $wpdb->prepare(
            "SELECT * FROM $table 
            WHERE archived = 0 
            AND (
                order_number LIKE %s 
                OR book_title LIKE %s 
                OR book_size LIKE %s
                OR paper_type LIKE %s
                OR print_type LIKE %s
                OR binding_type LIKE %s
            )
            ORDER BY 
                CASE 
                    WHEN order_number LIKE %s THEN 1
                    WHEN book_title LIKE %s THEN 2
                    WHEN book_size LIKE %s THEN 3
                    ELSE 4
                END,
                created_at DESC
            LIMIT %d OFFSET %d",
            $query_like, $query_like, $query_like, $query_like, $query_like, $query_like,
            $query_like, $query_like, $query_like,
            $per_page + 1, $offset
        );
        
        $results = $wpdb->get_results($sql);
        
        // Check if there are more results
        $has_more = count($results) > $per_page;
        if ($has_more) {
            array_pop($results); // Remove the extra result
        }
        
        // Get total count
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE archived = 0 
            AND (
                order_number LIKE %s 
                OR book_title LIKE %s 
                OR book_size LIKE %s
                OR paper_type LIKE %s
                OR print_type LIKE %s
                OR binding_type LIKE %s
            )",
            $query_like, $query_like, $query_like, $query_like, $query_like, $query_like
        );
        
        $total = $wpdb->get_var($count_sql);
        
        return array(
            'orders' => $results,
            'total' => intval($total),
            'has_more' => $has_more
        );
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
