<?php
/**
 * Doomsday Firewall Class
 * کلاس فایروال روز رستاخیز
 *
 * High-level security system for managing confidential orders.
 * سیستم محافظت سطح بالا برای مدیریت سفارشات محرمانه
 *
 * @package Tabesh
 * @since 1.0.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Tabesh_Doomsday_Firewall
 *
 * Manages confidential orders marked with @WAR# tag and provides
 * emergency lockdown capabilities.
 */
class Tabesh_Doomsday_Firewall {

	/**
	 * War order tag identifier
	 *
	 * @var string
	 */
	const WAR_TAG = '@WAR#';

	/**
	 * Lockdown mode option name
	 *
	 * @var string
	 */
	const LOCKDOWN_OPTION = 'tabesh_firewall_lockdown_mode';

	/**
	 * Firewall enabled option name
	 *
	 * @var string
	 */
	const FIREWALL_ENABLED_OPTION = 'tabesh_firewall_enabled';

	/**
	 * Secret key option name
	 *
	 * @var string
	 */
	const SECRET_KEY_OPTION = 'tabesh_firewall_secret_key';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize hooks
		add_action('init', array($this, 'check_emergency_actions'));
	}

	/**
	 * Check if firewall is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) get_option(self::FIREWALL_ENABLED_OPTION, false);
	}

	/**
	 * Check if order is a WAR order (contains @WAR# tag)
	 *
	 * @param int|object $order Order ID or order object
	 * @return bool
	 */
	public function is_war_order($order) {
		// If firewall is not enabled, no orders are considered WAR orders
		if (!$this->is_enabled()) {
			return false;
		}

		// Get order object if ID is provided
		if (is_numeric($order)) {
			global $wpdb;
			$table = $wpdb->prefix . 'tabesh_orders';
			$order = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$order
			));
		}

		// Check if order exists and has notes
		if (!$order || empty($order->notes)) {
			return false;
		}

		// Case-insensitive search for @WAR# tag
		return (stripos($order->notes, self::WAR_TAG) !== false);
	}

	/**
	 * Check if lockdown mode is active
	 *
	 * @return bool
	 */
	public function is_lockdown_mode() {
		return (bool) get_option(self::LOCKDOWN_OPTION, false);
	}

	/**
	 * Activate lockdown mode
	 *
	 * @param string $secret_key Security key for authentication
	 * @return bool Success status
	 */
	public function activate_lockdown($secret_key) {
		// Verify secret key
		if (!$this->verify_secret_key($secret_key)) {
			$this->log_firewall_action('lockdown_activation_failed', 'Invalid secret key');
			return false;
		}

		// Activate lockdown
		$result = update_option(self::LOCKDOWN_OPTION, true);

		if ($result) {
			$this->log_firewall_action('lockdown_activated', 'Emergency lockdown mode activated');
		}

		return $result;
	}

	/**
	 * Deactivate lockdown mode
	 *
	 * @param string $secret_key Security key for authentication
	 * @return bool Success status
	 */
	public function deactivate_lockdown($secret_key) {
		// Verify secret key
		if (!$this->verify_secret_key($secret_key)) {
			$this->log_firewall_action('lockdown_deactivation_failed', 'Invalid secret key');
			return false;
		}

		// Deactivate lockdown
		$result = update_option(self::LOCKDOWN_OPTION, false);

		if ($result) {
			$this->log_firewall_action('lockdown_deactivated', 'Emergency lockdown mode deactivated');
		}

		return $result;
	}

	/**
	 * Filter orders for display based on context and firewall rules
	 *
	 * @param array  $orders   Array of order objects
	 * @param int    $user_id  User ID
	 * @param string $context  Context: 'customer', 'admin', or 'staff'
	 * @return array Filtered orders
	 */
	public function filter_orders_for_display($orders, $user_id, $context = 'customer') {
		// If firewall is not enabled, return all orders
		if (!$this->is_enabled()) {
			return $orders;
		}

		// If not array, return as is
		if (!is_array($orders)) {
			return $orders;
		}

		// Check if lockdown mode is active
		$is_lockdown = $this->is_lockdown_mode();

		// Filter orders based on context
		$filtered_orders = array();

		foreach ($orders as $order) {
			$is_war = $this->is_war_order($order);

			// Apply filtering rules
			if ($context === 'customer') {
				// Customers never see WAR orders
				if (!$is_war) {
					$filtered_orders[] = $order;
				}
			} elseif ($context === 'admin' || $context === 'staff') {
				// In lockdown mode, even admin/staff cannot see WAR orders
				if ($is_lockdown && $is_war) {
					continue;
				}
				// In normal mode, admin/staff see all orders
				$filtered_orders[] = $order;
			} else {
				// Unknown context, don't filter
				$filtered_orders[] = $order;
			}
		}

		return $filtered_orders;
	}

	/**
	 * Check if notifications should be sent for this order
	 *
	 * @param int $order_id Order ID
	 * @return bool True if notification should be sent, false otherwise
	 */
	public function should_send_notification($order_id) {
		// If firewall is not enabled, always send notifications
		if (!$this->is_enabled()) {
			return true;
		}

		// Don't send notifications for WAR orders
		return !$this->is_war_order($order_id);
	}

	/**
	 * Get firewall settings
	 *
	 * @return array Firewall settings
	 */
	public function get_settings() {
		return array(
			'enabled'    => $this->is_enabled(),
			'lockdown'   => $this->is_lockdown_mode(),
			'secret_key' => get_option(self::SECRET_KEY_OPTION, ''),
		);
	}

	/**
	 * Save firewall settings
	 *
	 * @param array $settings Settings to save
	 * @return bool Success status
	 */
	public function save_settings($settings) {
		$success = true;

		// Save enabled status
		if (isset($settings['enabled'])) {
			$enabled = (bool) $settings['enabled'];
			$result  = update_option(self::FIREWALL_ENABLED_OPTION, $enabled);
			$success = $success && $result;

			$this->log_firewall_action(
				'settings_updated',
				$enabled ? 'Firewall enabled' : 'Firewall disabled'
			);
		}

		// Save secret key (must be at least 32 characters)
		if (isset($settings['secret_key'])) {
			$secret_key = sanitize_text_field($settings['secret_key']);

			// Validate key length
			if (!empty($secret_key) && strlen($secret_key) < 32) {
				return false;
			}

			$result  = update_option(self::SECRET_KEY_OPTION, $secret_key);
			$success = $success && $result;

			$this->log_firewall_action('settings_updated', 'Secret key updated');
		}

		return $success;
	}

	/**
	 * Verify secret key
	 *
	 * @param string $key Key to verify
	 * @return bool True if key is valid
	 */
	private function verify_secret_key($key) {
		$stored_key = get_option(self::SECRET_KEY_OPTION, '');

		// If no key is set, deny access
		if (empty($stored_key)) {
			return false;
		}

		// Use hash_equals to prevent timing attacks
		return hash_equals($stored_key, $key);
	}

	/**
	 * Log firewall action
	 *
	 * @param string $action Action type
	 * @param string $details Action details
	 * @return void
	 */
	private function log_firewall_action($action, $details) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_logs';

		// Check if logs table exists
		if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
			return;
		}

		// Get current user ID
		$user_id = get_current_user_id();

		// Insert log entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id ? $user_id : null,
				'action'     => 'firewall_' . sanitize_key($action),
				'order_id'   => null,
				'details'    => sanitize_text_field($details),
				'created_at' => current_time('mysql'),
			),
			array('%d', '%s', '%d', '%s', '%s')
		);
	}

	/**
	 * Check for emergency actions via URL parameters
	 * Allows cron jobs to trigger lockdown without API
	 *
	 * @return void
	 */
	public function check_emergency_actions() {
		// Check if action is requested
		if (!isset($_GET['tabesh_firewall_action']) || !isset($_GET['key'])) {
			return;
		}

		// Sanitize inputs
		$action = sanitize_text_field($_GET['tabesh_firewall_action']);
		$key    = sanitize_text_field($_GET['key']);

		// Process action
		$result = false;
		if ($action === 'lockdown') {
			$result = $this->activate_lockdown($key);
		} elseif ($action === 'unlock') {
			$result = $this->deactivate_lockdown($key);
		}

		// Send response
		if ($result) {
			wp_die(
				esc_html__('Firewall action completed successfully', 'tabesh'),
				esc_html__('Firewall Action', 'tabesh'),
				array('response' => 200)
			);
		} else {
			wp_die(
				esc_html__('Firewall action failed - Invalid key or error', 'tabesh'),
				esc_html__('Firewall Action Failed', 'tabesh'),
				array('response' => 401)
			);
		}
	}

	/**
	 * Get recent firewall logs
	 *
	 * @param int $limit Number of logs to retrieve
	 * @return array Array of log entries
	 */
	public function get_recent_logs($limit = 50) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_logs';

		// Check if logs table exists
		if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
			return array();
		}

		// Get firewall logs
		$logs = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $table 
			WHERE action LIKE 'firewall_%' 
			ORDER BY created_at DESC 
			LIMIT %d",
			$limit
		));

		return $logs ? $logs : array();
	}

	/**
	 * Generate a secure random secret key
	 *
	 * @return string 32-character random key
	 */
	public static function generate_secret_key() {
		return bin2hex(random_bytes(16)); // 32 hex characters
	}
}
