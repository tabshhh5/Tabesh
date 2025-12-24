<?php
/**
 * Order Assistant
 *
 * AI assistant specialized in helping with order-related queries
 *
 * @package Tabesh
 * @subpackage AI
 * @since 1.1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Assistant_Order
 */
class Tabesh_AI_Assistant_Order extends Tabesh_AI_Assistant_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->assistant_id          = 'order';
		$this->assistant_name        = __( 'دستیار سفارشات / Order Assistant', 'tabesh' );
		$this->assistant_description = __( 'کمک به ثبت، پیگیری و مدیریت سفارشات چاپ کتاب', 'tabesh' );
		$this->allowed_roles         = array( 'administrator', 'shop_manager', 'customer' );
		$this->capabilities          = array(
			'order_information',
			'price_calculation',
			'order_status',
			'product_parameters',
		);
		$this->system_prompt         = $this->build_system_prompt();
		$this->preferred_model       = 'gpt';
	}

	/**
	 * Build the system prompt for this assistant
	 *
	 * @return string
	 */
	private function build_system_prompt() {
		return __(
			'شما یک دستیار هوشمند برای سامانه ثبت سفارش چاپ کتاب هستید. وظیفه شما کمک به کاربران در موارد زیر است:

1. ارائه اطلاعات درباره فرآیند ثبت سفارش
2. محاسبه تقریبی قیمت بر اساس پارامترهای کتاب
3. پیگیری وضعیت سفارشات
4. توضیح پارامترهای مختلف محصول (قطع، نوع کاغذ، صحافی و...)
5. راهنمایی در انتخاب بهترین گزینه‌ها برای چاپ کتاب

لطفاً به صورت واضح، دقیق و کوتاه پاسخ دهید. از زبان فارسی استفاده کنید.
اگر سوال خارج از حوزه کاری شما باشد، به کاربر اطلاع دهید که فقط می‌توانید در زمینه سفارشات کتاب کمک کنید.',
			'tabesh'
		);
	}

	/**
	 * Prepare context for the AI request
	 *
	 * @param array $context Base context
	 * @return array Enhanced context
	 */
	protected function prepare_context( $context ) {
		// Add order-specific context
		if ( ! empty( $context['order_id'] ) ) {
			$order = $this->get_order_info( $context['order_id'] );
			if ( $order ) {
				$context['order_details'] = $order;
			}
		}

		// Add user's orders if available
		$user_id = get_current_user_id();
		if ( $user_id && empty( $context['orders'] ) ) {
			$context['user_orders_count'] = $this->get_user_orders_count( $user_id );
		}

		// Add product parameters info
		if ( function_exists( 'Tabesh' ) ) {
			$tabesh = Tabesh();
			if ( $tabesh && isset( $tabesh->admin ) ) {
				$context['available_book_sizes']    = $tabesh->admin->get_setting( 'book_sizes', array() );
				$context['available_binding_types'] = $tabesh->admin->get_setting( 'binding_types', array() );
			}
		}

		return $context;
	}

	/**
	 * Get order information
	 *
	 * @param int $order_id Order ID
	 * @return array|null Order data or null if not found
	 */
	private function get_order_info( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT order_number, status, book_title, book_size, paper_type, 
				        binding_type, quantity, total_price, created_at 
				 FROM {$table} WHERE id = %d",
				$order_id
			),
			ARRAY_A
		);

		return $order;
	}

	/**
	 * Get user orders count
	 *
	 * @param int $user_id User ID
	 * @return int Orders count
	 */
	private function get_user_orders_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		return (int) $count;
	}
}
