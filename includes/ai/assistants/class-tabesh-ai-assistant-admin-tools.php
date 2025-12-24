<?php
/**
 * Admin Tools Assistant
 *
 * AI assistant for administrative tasks and tools
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
 * Class Tabesh_AI_Assistant_Admin_Tools
 */
class Tabesh_AI_Assistant_Admin_Tools extends Tabesh_AI_Assistant_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->assistant_id          = 'admin_tools';
		$this->assistant_name        = __( 'دستیار مدیریت / Admin Assistant', 'tabesh' );
		$this->assistant_description = __( 'کمک به مدیران در تحلیل داده‌ها و تصمیم‌گیری', 'tabesh' );
		$this->allowed_roles         = array( 'administrator', 'shop_manager' );
		$this->capabilities          = array(
			'data_analysis',
			'statistics',
			'reporting',
			'insights',
			'optimization',
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
			'شما یک دستیار هوشمند برای مدیران سامانه چاپ کتاب تابش هستید. مسئولیت‌های شما:

1. تحلیل داده‌های سفارشات
2. ارائه گزارش‌های آماری
3. شناسایی الگوها و روندها
4. پیشنهاد بهینه‌سازی فرآیندها
5. کمک در تصمیم‌گیری‌های مدیریتی
6. تحلیل عملکرد و سودآوری

پاسخ‌های شما باید دقیق، داده‌محور و عملیاتی باشد. از زبان فارسی استفاده کنید.
در صورت نیاز به داده‌های بیشتر، از کاربر درخواست کنید.',
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
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// Add statistics
		$stats = array();

		// Total orders
		$stats['total_orders'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// Orders by status
		$status_counts             = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
			ARRAY_A
		);
		$stats['orders_by_status'] = $status_counts;

		// Total revenue
		$stats['total_revenue'] = $wpdb->get_var( "SELECT SUM(total_price) FROM {$table}" );

		// Recent orders count (last 30 days)
		$stats['recent_orders'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				30
			)
		);

		$context['statistics'] = $stats;

		return $context;
	}
}
