<?php
/**
 * WooCommerce Integration Class
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_WooCommerce {

    /**
     * Constructor
     */
    public function __construct() {
        // Add custom tab to My Account page
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_item'));
        add_action('init', array($this, 'add_endpoints'));
        add_action('woocommerce_account_tabesh-orders_endpoint', array($this, 'render_account_orders'));
        
        // Add to cart functionality (optional - for future use)
        add_action('wp_ajax_tabesh_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_tabesh_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        // Add custom product type (for future use)
        add_filter('product_type_selector', array($this, 'add_product_type'));
        
        // Flush rewrite rules after plugin activation
        add_action('woocommerce_init', array($this, 'maybe_flush_rewrite_rules'));
    }

    /**
     * Add custom menu item to My Account
     *
     * @param array $items
     * @return array
     */
    public function add_account_menu_item($items) {
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);

        $items['tabesh-orders'] = __('سفارشات چاپ', 'tabesh');
        $items['customer-logout'] = $logout;

        return $items;
    }

    /**
     * Add custom endpoint for My Account page
     */
    public function add_endpoints() {
        add_rewrite_endpoint('tabesh-orders', EP_ROOT | EP_PAGES);
    }

    /**
     * Maybe flush rewrite rules
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('tabesh_flush_rewrite_rules') === 'yes') {
            flush_rewrite_rules();
            delete_option('tabesh_flush_rewrite_rules');
        }
    }

    /**
     * Render orders in My Account page
     */
    public function render_account_orders() {
        echo do_shortcode('[tabesh_user_orders]');
    }

    /**
     * Add custom product type
     *
     * @param array $types
     * @return array
     */
    public function add_product_type($types) {
        $types['tabesh_book'] = __('کتاب (تابش)', 'tabesh');
        return $types;
    }

    /**
     * AJAX handler for adding order to cart
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!class_exists('WooCommerce')) {
            wp_send_json_error(array(
                'message' => __('WooCommerce فعال نیست', 'tabesh')
            ));
            return;
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('شناسه سفارش معتبر نیست', 'tabesh')
            ));
            return;
        }

        $order = Tabesh()->order->get_order($order_id);
        if (!$order) {
            wp_send_json_error(array(
                'message' => __('سفارش یافت نشد', 'tabesh')
            ));
            return;
        }

        // Create a custom product or use existing logic
        // This is a placeholder for future implementation
        $product_id = $this->create_or_get_custom_product($order);

        if ($product_id) {
            WC()->cart->add_to_cart($product_id, 1);
            
            wp_send_json_success(array(
                'message' => __('سفارش به سبد خرید اضافه شد', 'tabesh'),
                'cart_url' => wc_get_cart_url()
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('خطا در افزودن به سبد خرید', 'tabesh')
            ));
        }
    }

    /**
     * Create or get custom product for order
     *
     * @param object $order
     * @return int|false
     */
    private function create_or_get_custom_product($order) {
        // Check if product already exists for this order
        $existing_product = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_tabesh_order_id',
                    'value' => $order->id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));

        if (!empty($existing_product)) {
            return $existing_product[0]->ID;
        }

        // Create new product
        $product = new WC_Product_Simple();
        $product->set_name(sprintf(
            __('چاپ کتاب - سفارش %s', 'tabesh'),
            $order->order_number
        ));
        $product->set_regular_price($order->total_price);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        
        // Add order details as product meta
        $product_id = $product->save();
        
        if ($product_id) {
            update_post_meta($product_id, '_tabesh_order_id', $order->id);
            update_post_meta($product_id, '_tabesh_order_number', $order->order_number);
            update_post_meta($product_id, '_tabesh_book_size', $order->book_size);
            update_post_meta($product_id, '_tabesh_quantity', $order->quantity);
        }

        return $product_id;
    }

    /**
     * Get order details from WooCommerce order
     *
     * @param WC_Order $wc_order
     * @return int|false Tabesh order ID
     */
    public function get_tabesh_order_from_wc_order($wc_order) {
        foreach ($wc_order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $tabesh_order_id = get_post_meta($product_id, '_tabesh_order_id', true);
            
            if ($tabesh_order_id) {
                return intval($tabesh_order_id);
            }
        }
        
        return false;
    }

    /**
     * Link WooCommerce order to Tabesh order
     *
     * @param int $wc_order_id WooCommerce order ID
     * @param int $tabesh_order_id Tabesh order ID
     */
    public function link_orders($wc_order_id, $tabesh_order_id) {
        // Add meta to WooCommerce order
        update_post_meta($wc_order_id, '_tabesh_order_id', $tabesh_order_id);
        
        // Add meta to Tabesh order
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        $wpdb->update(
            $table,
            array('notes' => 'WC Order ID: ' . $wc_order_id),
            array('id' => $tabesh_order_id)
        );
    }

    /**
     * Sync order statuses
     *
     * @param int $order_id WooCommerce order ID
     * @param string $old_status
     * @param string $new_status
     */
    public function sync_order_status($order_id, $old_status, $new_status) {
        $tabesh_order_id = get_post_meta($order_id, '_tabesh_order_id', true);
        
        if (!$tabesh_order_id) {
            return;
        }

        // Map WooCommerce statuses to Tabesh statuses
        $status_map = array(
            'pending' => 'pending',
            'processing' => 'confirmed',
            'on-hold' => 'pending',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'refunded' => 'cancelled',
            'failed' => 'cancelled'
        );

        $tabesh_status = $status_map[$new_status] ?? 'pending';
        
        Tabesh()->order->update_status($tabesh_order_id, $tabesh_status);
    }
}

// Hook to sync order statuses
add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) {
    $wc_integration = new Tabesh_WooCommerce();
    $wc_integration->sync_order_status($order_id, $old_status, $new_status);
}, 10, 3);
