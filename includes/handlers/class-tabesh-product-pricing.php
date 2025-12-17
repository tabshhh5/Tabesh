<?php
/**
 * Product Pricing Management Interface
 *
 * Provides a modern admin interface for managing pricing parameters
 * using the new matrix-based pricing engine.
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabesh Product Pricing Class
 */
class Tabesh_Product_Pricing {

	/**
	 * Pricing engine instance
	 *
	 * @var Tabesh_Pricing_Engine
	 */
	private $pricing_engine;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->pricing_engine = new Tabesh_Pricing_Engine();
	}

	/**
	 * Render the product pricing management interface
	 *
	 * Shortcode: [tabesh_product_pricing]
	 *
	 * @return string HTML output
	 */
	public function render() {
		// Enqueue the product pricing CSS
		wp_enqueue_style(
			'tabesh-product-pricing',
			TABESH_PLUGIN_URL . 'assets/css/product-pricing.css',
			array(),
			TABESH_VERSION
		);

		// Check access control settings
		$allowed_capability = $this->get_pricing_access_capability();
		
		if ( ! current_user_can( $allowed_capability ) ) {
			return '<div class="tabesh-error">' . __( 'شما دسترسی به این بخش را ندارید', 'tabesh' ) . '</div>';
		}

		// Handle engine toggle - verify nonce first before sanitization
		if ( isset( $_POST['tabesh_toggle_nonce'] ) && isset( $_POST['action'] ) ) {
			// Verify nonce with raw value
			if ( wp_verify_nonce( $_POST['tabesh_toggle_nonce'], 'tabesh_toggle_engine' ) ) {
				// Now sanitize after verification
				$enable_v2 = isset( $_POST['enable_v2'] ) ? sanitize_text_field( wp_unslash( $_POST['enable_v2'] ) ) : '0';
				if ( '1' === $enable_v2 ) {
					$this->enable_pricing_engine_v2();
					echo '<div class="tabesh-success">' . esc_html__( 'موتور قیمت‌گذاری جدید فعال شد', 'tabesh' ) . '</div>';
				} else {
					$this->disable_pricing_engine_v2();
					echo '<div class="tabesh-success">' . esc_html__( 'به موتور قدیمی بازگشت داده شد', 'tabesh' ) . '</div>';
				}
			}
		}

		// Handle form submission - verify nonce first before sanitization
		if ( isset( $_POST['tabesh_pricing_nonce'] ) && isset( $_POST['book_size'] ) ) {
			// Verify nonce with raw value
			if ( wp_verify_nonce( $_POST['tabesh_pricing_nonce'], 'tabesh_save_pricing' ) ) {
				// Now sanitize after verification
				$this->handle_save_pricing();
			}
		}

		// Get list of configured book sizes
		$book_sizes = $this->get_all_book_sizes();

		// Start output buffering
		ob_start();

		// Include template
		include TABESH_PLUGIN_DIR . 'templates/admin/product-pricing.php';

		return ob_get_clean();
	}

	/**
	 * Handle saving pricing configuration
	 *
	 * @return void
	 */
	private function handle_save_pricing() {
		// Get book size from POST
		$book_size = isset( $_POST['book_size'] ) ? sanitize_text_field( wp_unslash( $_POST['book_size'] ) ) : '';

		if ( empty( $book_size ) ) {
			return;
		}

		// Build pricing matrix from POST data
		$matrix = array(
			'book_size'     => $book_size,
			'page_costs'    => $this->parse_page_costs( $_POST['page_costs'] ?? array() ),
			'binding_costs' => $this->parse_binding_costs( $_POST['binding_costs'] ?? array() ),
			'cover_cost'    => isset( $_POST['cover_cost'] ) ? floatval( $_POST['cover_cost'] ) : 8000.0,
			'extras_costs'  => $this->parse_extras_costs( $_POST['extras_costs'] ?? array() ),
			'profit_margin' => isset( $_POST['profit_margin'] ) ? floatval( $_POST['profit_margin'] ) / 100 : 0.0,
			'restrictions'  => $this->parse_restrictions( $_POST['restrictions'] ?? array() ),
		);

		// Save to database
		$success = $this->pricing_engine->save_pricing_matrix( $book_size, $matrix );

		if ( $success ) {
			echo '<div class="tabesh-success">' . esc_html__( 'تنظیمات قیمت‌گذاری با موفقیت ذخیره شد', 'tabesh' ) . '</div>';
		} else {
			echo '<div class="tabesh-error">' . esc_html__( 'خطا در ذخیره تنظیمات', 'tabesh' ) . '</div>';
		}
	}

	/**
	 * Parse page costs from POST data
	 *
	 * @param array $data POST data for page costs
	 * @return array Parsed page costs structure
	 */
	private function parse_page_costs( $data ) {
		$page_costs = array();

		if ( ! is_array( $data ) ) {
			return $page_costs;
		}

		foreach ( $data as $paper_type => $weights ) {
			$paper_type = sanitize_text_field( $paper_type );

			if ( ! is_array( $weights ) ) {
				continue;
			}

			foreach ( $weights as $weight => $print_types ) {
				$weight = sanitize_text_field( $weight );

				if ( ! is_array( $print_types ) ) {
					continue;
				}

				foreach ( $print_types as $print_type => $cost ) {
					$print_type = sanitize_text_field( $print_type );
					$cost       = floatval( $cost );

					$page_costs[ $paper_type ][ $weight ][ $print_type ] = $cost;
				}
			}
		}

		return $page_costs;
	}

	/**
	 * Parse binding costs from POST data
	 *
	 * @param array $data POST data for binding costs
	 * @return array Parsed binding costs
	 */
	private function parse_binding_costs( $data ) {
		$binding_costs = array();

		if ( ! is_array( $data ) ) {
			return $binding_costs;
		}

		foreach ( $data as $binding_type => $cost ) {
			$binding_type             = sanitize_text_field( $binding_type );
			$binding_costs[ $binding_type ] = floatval( $cost );
		}

		return $binding_costs;
	}

	/**
	 * Parse extras costs from POST data
	 *
	 * @param array $data POST data for extras costs
	 * @return array Parsed extras costs structure
	 */
	private function parse_extras_costs( $data ) {
		$extras_costs = array();

		if ( ! is_array( $data ) ) {
			return $extras_costs;
		}

		foreach ( $data as $extra_name => $config ) {
			$extra_name = sanitize_text_field( $extra_name );

			if ( ! is_array( $config ) ) {
				continue;
			}

			$extras_costs[ $extra_name ] = array(
				'price' => isset( $config['price'] ) ? floatval( $config['price'] ) : 0.0,
				'type'  => isset( $config['type'] ) ? sanitize_text_field( $config['type'] ) : 'fixed',
				'step'  => isset( $config['step'] ) ? intval( $config['step'] ) : 0,
			);
		}

		return $extras_costs;
	}

	/**
	 * Parse restrictions from POST data
	 *
	 * @param array $data POST data for restrictions
	 * @return array Parsed restrictions structure
	 */
	private function parse_restrictions( $data ) {
		$restrictions = array(
			'forbidden_paper_types'   => array(),
			'forbidden_binding_types' => array(),
			'forbidden_print_types'   => array(),
		);

		if ( ! is_array( $data ) ) {
			return $restrictions;
		}

		// Parse forbidden paper types
		if ( isset( $data['forbidden_paper_types'] ) && is_array( $data['forbidden_paper_types'] ) ) {
			foreach ( $data['forbidden_paper_types'] as $paper_type ) {
				$restrictions['forbidden_paper_types'][] = sanitize_text_field( $paper_type );
			}
		}

		// Parse forbidden binding types
		if ( isset( $data['forbidden_binding_types'] ) && is_array( $data['forbidden_binding_types'] ) ) {
			foreach ( $data['forbidden_binding_types'] as $binding_type ) {
				$restrictions['forbidden_binding_types'][] = sanitize_text_field( $binding_type );
			}
		}

		// Parse forbidden print types per paper
		if ( isset( $data['forbidden_print_types'] ) && is_array( $data['forbidden_print_types'] ) ) {
			foreach ( $data['forbidden_print_types'] as $paper_type => $print_types ) {
				$paper_type = sanitize_text_field( $paper_type );

				if ( ! is_array( $print_types ) ) {
					continue;
				}

				$restrictions['forbidden_print_types'][ $paper_type ] = array();
				foreach ( $print_types as $print_type ) {
					$restrictions['forbidden_print_types'][ $paper_type ][] = sanitize_text_field( $print_type );
				}
			}
		}

		return $restrictions;
	}

	/**
	 * Get all available book sizes (from settings + defaults)
	 *
	 * @return array Array of book sizes
	 */
	private function get_all_book_sizes() {
		// Get configured sizes from new pricing engine
		$configured_sizes = $this->pricing_engine->get_configured_book_sizes();

		// Get default sizes from legacy system
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'pricing_book_sizes'
			)
		);

		$legacy_sizes = array();
		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$legacy_sizes = array_keys( $decoded );
			}
		}

		// Default sizes if nothing configured
		if ( empty( $configured_sizes ) && empty( $legacy_sizes ) ) {
			return array( 'A5', 'A4', 'B5', 'رقعی', 'وزیری', 'خشتی' );
		}

		// Merge configured and legacy sizes
		$all_sizes = array_unique( array_merge( $configured_sizes, $legacy_sizes ) );

		return $all_sizes;
	}

	/**
	 * Get pricing matrix for a specific book size
	 *
	 * @param string $book_size Book size identifier
	 * @return array Pricing matrix
	 */
	public function get_pricing_matrix_for_size( $book_size ) {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		$setting_key = 'pricing_matrix_' . sanitize_key( $book_size );

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				$setting_key
			)
		);

		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Return default matrix if not configured
		return $this->pricing_engine->get_default_pricing_matrix( $book_size );
	}

	/**
	 * Enable the new pricing engine
	 *
	 * @return bool Success status
	 */
	public function enable_pricing_engine_v2() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'pricing_engine_v2_enabled'
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$table_settings,
				array( 'setting_value' => '1' ),
				array( 'setting_key' => 'pricing_engine_v2_enabled' ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			$result = $wpdb->insert(
				$table_settings,
				array(
					'setting_key'   => 'pricing_engine_v2_enabled',
					'setting_value' => '1',
				),
				array( '%s', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Get the required capability for accessing pricing management
	 *
	 * @return string Required capability
	 */
	private function get_pricing_access_capability() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'pricing_access_capability'
			)
		);

		// Default to manage_woocommerce if not set
		return ! empty( $result ) ? sanitize_text_field( $result ) : 'manage_woocommerce';
	}

	/**
	 * Save the required capability for accessing pricing management
	 *
	 * @param string $capability Capability to require
	 * @return bool Success status
	 */
	public function save_pricing_access_capability( $capability ) {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Get valid capabilities - filterable for extensibility
		$valid_capabilities = apply_filters(
			'tabesh_pricing_access_capabilities',
			array(
				'manage_woocommerce',
				'manage_options',
				'edit_shop_orders',
			)
		);

		if ( ! in_array( $capability, $valid_capabilities, true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'pricing_access_capability'
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table_settings,
				array( 'setting_value' => $capability ),
				array( 'setting_key' => 'pricing_access_capability' ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->insert(
				$table_settings,
				array(
					'setting_key'   => 'pricing_access_capability',
					'setting_value' => $capability,
				),
				array( '%s', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Disable the new pricing engine (revert to legacy)
	 *
	 * @return bool Success status
	 */
	public function disable_pricing_engine_v2() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		$result = $wpdb->update(
			$table_settings,
			array( 'setting_value' => '0' ),
			array( 'setting_key' => 'pricing_engine_v2_enabled' ),
			array( '%s' ),
			array( '%s' )
		);

		return false !== $result;
	}
}
