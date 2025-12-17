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

		// Enqueue the product pricing JS
		wp_enqueue_script(
			'tabesh-product-pricing',
			TABESH_PLUGIN_URL . 'assets/js/product-pricing.js',
			array( 'jquery' ),
			TABESH_VERSION,
			true
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

		// Initialize pricing matrices for all book sizes if V2 is enabled but matrices don't exist
		$this->maybe_initialize_pricing_matrices( $book_sizes );

		// Get product parameters from settings for template use
		$product_paper_types   = $this->get_product_paper_types();
		$product_binding_types = $this->get_product_binding_types();
		$product_extras        = $this->get_product_extras();

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
			'book_size'            => $book_size,
			'page_costs'           => $this->parse_page_costs( $_POST['page_costs'] ?? array() ),
			'binding_costs'        => $this->parse_binding_costs( $_POST['binding_costs'] ?? array() ),
			'cover_cost'           => isset( $_POST['cover_cost'] ) ? floatval( $_POST['cover_cost'] ) : 8000.0,
			'extras_costs'         => $this->parse_extras_costs( $_POST['extras_costs'] ?? array() ),
			'profit_margin'        => isset( $_POST['profit_margin'] ) ? floatval( $_POST['profit_margin'] ) / 100 : 0.0,
			'restrictions'         => $this->parse_restrictions( $_POST['restrictions'] ?? array(), $_POST['page_costs'] ?? array() ),
			'quantity_constraints' => $this->parse_quantity_constraints( $_POST['quantity_constraints'] ?? array() ),
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
	 * @return array Parsed page costs structure with restrictions from disabled toggles
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

				foreach ( $print_types as $print_type => $value ) {
					// Skip if this is an 'enabled' field
					if ( strpos( $print_type, '_enabled' ) !== false ) {
						continue;
					}

					$print_type = sanitize_text_field( $print_type );
					$cost       = floatval( $value );

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
			$binding_type                   = sanitize_text_field( $binding_type );
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
	 * Now builds restrictions from enabled/disabled toggles in page_costs
	 *
	 * @param array $restrictions_data POST data for restrictions (legacy, will be phased out)
	 * @param array $page_costs_data POST data for page costs (contains enabled toggles)
	 * @return array Parsed restrictions structure
	 */
	private function parse_restrictions( $restrictions_data, $page_costs_data = array() ) {
		$restrictions = array(
			'forbidden_paper_types'   => array(),
			'forbidden_binding_types' => array(),
			'forbidden_print_types'   => array(),
		);

		// Build forbidden print types from disabled toggles in page_costs
		if ( is_array( $page_costs_data ) ) {
			foreach ( $page_costs_data as $paper_type => $weights ) {
				$paper_type = sanitize_text_field( $paper_type );

				if ( ! is_array( $weights ) ) {
					continue;
				}

				foreach ( $weights as $weight => $print_types ) {
					$weight = sanitize_text_field( $weight );

					if ( ! is_array( $print_types ) ) {
						continue;
					}

					// Check each print type toggle
					foreach ( array( 'bw', 'color' ) as $print_type ) {
						$enabled_key = $print_type . '_enabled';

						// If toggle is NOT set (unchecked), it means disabled/forbidden
						if ( ! isset( $print_types[ $enabled_key ] ) || '1' !== $print_types[ $enabled_key ] ) {
							// Add to forbidden list for this paper type
							if ( ! isset( $restrictions['forbidden_print_types'][ $paper_type ] ) ) {
								$restrictions['forbidden_print_types'][ $paper_type ] = array();
							}

							// Only add if not already in list
							if ( ! in_array( $print_type, $restrictions['forbidden_print_types'][ $paper_type ], true ) ) {
								$restrictions['forbidden_print_types'][ $paper_type ][] = $print_type;
							}
						}
					}
				}
			}
		}

		// Still support legacy restrictions data if provided (for backward compatibility)
		if ( ! is_array( $restrictions_data ) ) {
			return $restrictions;
		}

		// Parse forbidden paper types
		if ( isset( $restrictions_data['forbidden_paper_types'] ) && is_array( $restrictions_data['forbidden_paper_types'] ) ) {
			foreach ( $restrictions_data['forbidden_paper_types'] as $paper_type ) {
				$restrictions['forbidden_paper_types'][] = sanitize_text_field( $paper_type );
			}
		}

		// Parse forbidden binding types
		if ( isset( $restrictions_data['forbidden_binding_types'] ) && is_array( $restrictions_data['forbidden_binding_types'] ) ) {
			foreach ( $restrictions_data['forbidden_binding_types'] as $binding_type ) {
				$restrictions['forbidden_binding_types'][] = sanitize_text_field( $binding_type );
			}
		}

		return $restrictions;
	}

	/**
	 * Parse quantity constraints from POST data
	 *
	 * @param array $data POST data for quantity constraints
	 * @return array Parsed quantity constraints
	 */
	private function parse_quantity_constraints( $data ) {
		$constraints = array(
			'minimum_quantity' => 10,
			'maximum_quantity' => 10000,
			'quantity_step'    => 10,
		);

		if ( ! is_array( $data ) ) {
			return $constraints;
		}

		// Parse and validate minimum quantity
		if ( isset( $data['minimum_quantity'] ) ) {
			$min = intval( $data['minimum_quantity'] );
			if ( $min > 0 ) {
				$constraints['minimum_quantity'] = $min;
			}
		}

		// Parse and validate maximum quantity
		if ( isset( $data['maximum_quantity'] ) ) {
			$max = intval( $data['maximum_quantity'] );
			if ( $max > 0 ) {
				$constraints['maximum_quantity'] = $max;
			}
		}

		// Parse and validate quantity step
		if ( isset( $data['quantity_step'] ) ) {
			$step = intval( $data['quantity_step'] );
			if ( $step > 0 ) {
				$constraints['quantity_step'] = $step;
			}
		}

		return $constraints;
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
	 * Get product paper types from settings
	 *
	 * @return array Array of paper types with their weights
	 */
	private function get_product_paper_types() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'paper_types'
			)
		);

		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Default if not found
		return array(
			'تحریر' => array( 60, 70, 80 ),
			'بالک'  => array( 60, 70, 80, 100 ),
			'گلاسه' => array( 70, 80, 90, 100 ),
		);
	}

	/**
	 * Get product binding types from settings
	 *
	 * @return array Array of binding types
	 */
	private function get_product_binding_types() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'binding_types'
			)
		);

		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Default if not found
		return array( 'شومیز', 'جلد سخت', 'گالینگور', 'سیمی', 'منگنه' );
	}

	/**
	 * Get product extras from settings
	 *
	 * @return array Array of extra services
	 */
	private function get_product_extras() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'extras'
			)
		);

		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				// Filter out invalid values
				return array_filter(
					array_map(
						function ( $extra ) {
							$extra = is_scalar( $extra ) ? trim( strval( $extra ) ) : '';
							return ( ! empty( $extra ) && $extra !== 'on' ) ? $extra : null;
						},
						$decoded
					)
				);
			}
		}

		// Default if not found
		return array( 'لب گرد', 'خط تا', 'شیرینک', 'سوراخ', 'شماره گذاری' );
	}

	/**
	 * Initialize pricing matrices for book sizes if they don't exist
	 * Called when accessing pricing interface to ensure all book sizes have matrices
	 *
	 * @param array $book_sizes Array of book sizes
	 * @return void
	 */
	private function maybe_initialize_pricing_matrices( $book_sizes ) {
		// Only initialize if V2 is enabled
		if ( ! $this->pricing_engine->is_enabled() ) {
			return;
		}

		$configured_sizes = $this->pricing_engine->get_configured_book_sizes();

		// Get product parameters for building default matrices
		$paper_types   = $this->get_product_paper_types();
		$binding_types = $this->get_product_binding_types();
		$extras        = $this->get_product_extras();

		foreach ( $book_sizes as $book_size ) {
			// Skip if already configured
			if ( in_array( $book_size, $configured_sizes, true ) ) {
				continue;
			}

			// Initialize with default matrix using product parameters
			$default_matrix = $this->build_default_matrix( $book_size, $paper_types, $binding_types, $extras );

			// Save to database
			$this->pricing_engine->save_pricing_matrix( $book_size, $default_matrix );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Tabesh: Initialized pricing matrix for book size: $book_size" );
			}
		}
	}

	/**
	 * Build default pricing matrix for a book size using product parameters
	 *
	 * @param string $book_size Book size identifier
	 * @param array  $paper_types Paper types with weights
	 * @param array  $binding_types Binding types
	 * @param array  $extras Extra services
	 * @return array Default pricing matrix
	 */
	private function build_default_matrix( $book_size, $paper_types, $binding_types, $extras ) {
		$matrix = array(
			'book_size'            => $book_size,
			'page_costs'           => array(),
			'binding_costs'        => array(),
			'cover_cost'           => 8000,
			'extras_costs'         => array(),
			'profit_margin'        => 0.0,
			'restrictions'         => array(
				'forbidden_paper_types'   => array(),
				'forbidden_binding_types' => array(),
				'forbidden_print_types'   => array(),
			),
			'quantity_constraints' => array(
				'minimum_quantity' => 10,
				'maximum_quantity' => 10000,
				'quantity_step'    => 10,
			),
		);

		// Build page costs from paper types
		foreach ( $paper_types as $paper_type => $weights ) {
			foreach ( $weights as $weight ) {
				// Default prices - can be customized later
				$matrix['page_costs'][ $paper_type ][ $weight ] = array(
					'bw'    => 350,  // Default B&W price per page
					'color' => 950,  // Default color price per page
				);
			}
		}

		// Build binding costs from binding types
		foreach ( $binding_types as $binding_type ) {
			// Default binding costs
			$default_costs = array(
				'شومیز'    => 3000,
				'جلد سخت'  => 8000,
				'گالینگور' => 6000,
				'سیمی'     => 2000,
				'منگنه'    => 2500,
			);

			$matrix['binding_costs'][ $binding_type ] = $default_costs[ $binding_type ] ?? 3000;
		}

		// Build extras costs
		foreach ( $extras as $extra ) {
			// Default extras configuration
			$default_extras = array(
				'لب گرد'      => array( 'price' => 1000, 'type' => 'per_unit', 'step' => 0 ),
				'خط تا'       => array( 'price' => 500, 'type' => 'per_unit', 'step' => 0 ),
				'شیرینک'      => array( 'price' => 1500, 'type' => 'per_unit', 'step' => 0 ),
				'سوراخ'       => array( 'price' => 300, 'type' => 'per_unit', 'step' => 0 ),
				'شماره گذاری' => array( 'price' => 800, 'type' => 'page_based', 'step' => 16000 ),
			);

			$matrix['extras_costs'][ $extra ] = $default_extras[ $extra ] ?? array( 'price' => 1000, 'type' => 'per_unit', 'step' => 0 );
		}

		return $matrix;
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

		// Debug log before operation
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Attempting to enable Pricing Engine V2' );
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'pricing_engine_v2_enabled'
			)
		);

		// Debug log existing value
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'Tabesh: Existing pricing_engine_v2_enabled value: "%s"',
				$existing === null ? 'NULL (not found in DB)' : $existing
			) );
		}

		if ( $existing !== null ) {
			// Update existing record
			$result = $wpdb->update(
				$table_settings,
				array( 'setting_value' => '1' ),
				array( 'setting_key' => 'pricing_engine_v2_enabled' ),
				array( '%s' ),
				array( '%s' )
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'Tabesh: UPDATE result for pricing_engine_v2_enabled: %s (rows affected: %d)',
					$result === false ? 'FAILED' : 'SUCCESS',
					$result === false ? 0 : $result
				) );
				if ( $result === false ) {
					error_log( 'Tabesh: Database error: ' . $wpdb->last_error );
				}
			}
		} else {
			// Insert new record
			$result = $wpdb->insert(
				$table_settings,
				array(
					'setting_key'   => 'pricing_engine_v2_enabled',
					'setting_value' => '1',
				),
				array( '%s', '%s' )
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'Tabesh: INSERT result for pricing_engine_v2_enabled: %s',
					$result === false ? 'FAILED' : 'SUCCESS'
				) );
				if ( $result === false ) {
					error_log( 'Tabesh: Database error: ' . $wpdb->last_error );
				}
			}
		}

		// Clear pricing engine cache to ensure fresh data on reactivation
		if ( false !== $result ) {
			Tabesh_Pricing_Engine::clear_cache();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Pricing Engine cache cleared after enabling V2' );

				// Verify the value was saved correctly
				$verify = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
						'pricing_engine_v2_enabled'
					)
				);
				error_log( sprintf(
					'Tabesh: VERIFICATION - Value in DB after save: "%s"',
					$verify === null ? 'NULL' : $verify
				) );
			}
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

		// Debug log before operation
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Attempting to disable Pricing Engine V2' );
		}

		$result = $wpdb->update(
			$table_settings,
			array( 'setting_value' => '0' ),
			array( 'setting_key' => 'pricing_engine_v2_enabled' ),
			array( '%s' ),
			array( '%s' )
		);

		// Debug log result
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'Tabesh: UPDATE result for disabling pricing_engine_v2_enabled: %s',
				$result === false ? 'FAILED' : 'SUCCESS'
			) );
			if ( $result === false ) {
				error_log( 'Tabesh: Database error: ' . $wpdb->last_error );
			}
		}

		// Clear pricing engine cache when disabling
		if ( false !== $result ) {
			Tabesh_Pricing_Engine::clear_cache();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Pricing Engine cache cleared after disabling V2' );
			}
		}

		return false !== $result;
	}
}
