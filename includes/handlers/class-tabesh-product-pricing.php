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

		// CRITICAL FIX: Cleanup corrupted pricing matrices on form load
		// This ensures data integrity between product parameters and pricing matrices
		$this->cleanup_orphaned_pricing_matrices();

		// CRITICAL FIX: Migrate mismatched book_size keys on form load
		// This fixes the issue where matrices saved with descriptions (e.g., "رقعی (14×20)")
		// don't match product parameters (e.g., "رقعی"), causing critical failures
		$migration_stats = $this->pricing_engine->migrate_mismatched_book_size_keys();

		// Display migration results if any fixes were applied
		if ( $migration_stats['merged'] > 0 || $migration_stats['deleted'] > 0 ) {
			echo '<div class="tabesh-success">';
			echo '<strong>' . esc_html__( '✓ اصلاح خودکار ماتریس‌های قیمت', 'tabesh' ) . '</strong><br>';
			if ( $migration_stats['merged'] > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: %d: number of merged matrices */
						__( '• %d ماتریس با کلیدهای قدیمی ادغام شد', 'tabesh' ),
						$migration_stats['merged']
					)
				) . '<br>';
			}
			if ( $migration_stats['deleted'] > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: %d: number of deleted keys */
						__( '• %d کلید قدیمی حذف شد', 'tabesh' ),
						$migration_stats['deleted']
					)
				) . '<br>';
			}
			if ( $migration_stats['activated'] > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: %d: number of activated sizes */
						__( '• %d قطع فعال شد', 'tabesh' ),
						$migration_stats['activated']
					)
				) . '<br>';
			}
			echo '</div>';
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
					// Clear cache after enabling
					Tabesh_Pricing_Engine::clear_cache();
				} else {
					$this->disable_pricing_engine_v2();
					echo '<div class="tabesh-success">' . esc_html__( 'به موتور قدیمی بازگشت داده شد', 'tabesh' ) . '</div>';
					// Clear cache after disabling
					Tabesh_Pricing_Engine::clear_cache();
				}
			}
		}

		// Handle form submission - verify nonce first before sanitization
		if ( isset( $_POST['tabesh_pricing_nonce'] ) && isset( $_POST['book_size'] ) ) {
			// Verify nonce with raw value
			if ( wp_verify_nonce( $_POST['tabesh_pricing_nonce'], 'tabesh_save_pricing' ) ) {
				// Now sanitize after verification
				$this->handle_save_pricing();
				// Clear cache after saving pricing
				Tabesh_Pricing_Engine::clear_cache();
			}
		}

		// Display health check report at the top
		echo Tabesh_Pricing_Health_Checker::get_html_report();

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

		// CRITICAL FIX: Validate book size against product parameters (source of truth)
		// This prevents random IDs from being saved to the database
		$valid_book_sizes = $this->get_valid_book_sizes_from_settings();
		if ( ! in_array( $book_size, $valid_book_sizes, true ) ) {
			echo '<div class="tabesh-error">' . esc_html(
				sprintf(
					/* translators: 1: invalid book size, 2: comma-separated list of valid sizes */
					__( 'خطا: قطع "%1$s" معتبر نیست. قطع‌های مجاز: %2$s', 'tabesh' ),
					$book_size,
					implode( '، ', $valid_book_sizes )
				)
			) . '</div>';

			// Log this security issue
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'Tabesh Security: Attempted to save invalid book_size "%s". Valid sizes: %s',
						$book_size,
						implode( ', ', $valid_book_sizes )
					)
				);
			}

			return;
		}

		// Build pricing matrix from POST data
		$matrix = array(
			'book_size'            => $book_size,
			'page_costs'           => $this->parse_page_costs( $_POST['page_costs'] ?? array() ),
			'binding_costs'        => $this->parse_binding_costs( $_POST['binding_costs'] ?? array() ),
			'extras_costs'         => $this->parse_extras_costs( $_POST['extras_costs'] ?? array() ),
			'profit_margin'        => isset( $_POST['profit_margin'] ) ? floatval( $_POST['profit_margin'] ) / 100 : 0.0,
			'restrictions'         => $this->parse_restrictions( $_POST['restrictions'] ?? array() ),
			'quantity_constraints' => $this->parse_quantity_constraints( $_POST['quantity_constraints'] ?? array() ),
		);

		// CRITICAL VALIDATION: Check if pricing matrix has required data
		// A size is only usable if it has BOTH paper costs and binding costs
		$has_papers   = ! empty( $matrix['page_costs'] );
		$has_bindings = ! empty( $matrix['binding_costs'] );

		if ( ! $has_papers || ! $has_bindings ) {
			$missing = array();
			if ( ! $has_papers ) {
				$missing[] = __( 'قیمت صفحات (کاغذ + چاپ)', 'tabesh' );
			}
			if ( ! $has_bindings ) {
				$missing[] = __( 'قیمت صحافی', 'tabesh' );
			}

			echo '<div class="tabesh-error">' . esc_html(
				sprintf(
					/* translators: %s: comma-separated list of missing items */
					__( '⚠️ هشدار: ماتریس قیمت ناقص است! موارد زیر تنظیم نشده‌اند: %s. این قطع در فرم سفارش نمایش داده نخواهد شد.', 'tabesh' ),
					implode( '، ', $missing )
				)
			) . '</div>';
		}

		// Save to database even if incomplete (admin might want to save draft)
		$success = $this->pricing_engine->save_pricing_matrix( $book_size, $matrix );

		if ( $success ) {
			if ( $has_papers && $has_bindings ) {
				echo '<div class="tabesh-success">' . esc_html__( '✓ تنظیمات قیمت‌گذاری با موفقیت ذخیره شد', 'tabesh' ) . '</div>';
			} else {
				echo '<div class="tabesh-warning">' .
					esc_html__( '✓ ماتریس قیمت ذخیره شد، اما تا تکمیل تنظیمات در فرم سفارش نمایش داده نخواهد شد', 'tabesh' ) .
					'</div>';
			}
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
	 * Now includes cover costs per weight for each binding type:
	 * binding_costs[binding_type][cover_weight] = cost
	 *
	 * @param array $data POST data for binding costs
	 * @return array Parsed binding costs
	 */
	private function parse_binding_costs( $data ) {
		$binding_costs = array();

		if ( ! is_array( $data ) ) {
			return $binding_costs;
		}

		foreach ( $data as $binding_type => $weights_or_cost ) {
			$binding_type = sanitize_text_field( $binding_type );

			// New structure: binding_costs[binding_type][cover_weight] = cost
			if ( is_array( $weights_or_cost ) ) {
				$binding_costs[ $binding_type ] = array();
				foreach ( $weights_or_cost as $cover_weight => $cost ) {
					$cover_weight                                    = sanitize_text_field( $cover_weight );
					$binding_costs[ $binding_type ][ $cover_weight ] = floatval( $cost );
				}
			} else {
				// Legacy structure: binding_costs[binding_type] = cost
				// Keep for backward compatibility
				$binding_costs[ $binding_type ] = floatval( $weights_or_cost );
			}
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
			'forbidden_cover_weights' => array(),
			'forbidden_extras'        => array(),
		);

		if ( ! is_array( $data ) ) {
			return $restrictions;
		}

		// Parse forbidden print types from inline toggles
		// New format: restrictions[forbidden_print_types][paper_type][weight][print_type] = "0" (checked = enabled)
		// If checkbox is NOT checked (disabled), the value won't be in POST data
		if ( isset( $data['forbidden_print_types'] ) && is_array( $data['forbidden_print_types'] ) ) {
			// Get all paper types and their weights to check which ones are disabled
			// First, collect all enabled combinations
			$enabled_combinations = array();

			foreach ( $data['forbidden_print_types'] as $paper_type => $weights_data ) {
				$paper_type = sanitize_text_field( $paper_type );

				if ( ! is_array( $weights_data ) ) {
					continue;
				}

				foreach ( $weights_data as $weight => $print_types_data ) {
					if ( ! is_array( $print_types_data ) ) {
						continue;
					}

					foreach ( $print_types_data as $print_type => $value ) {
						$print_type = sanitize_text_field( $print_type );

						// If checkbox exists in POST (value = "0"), it means it's ENABLED
						// So we track enabled combinations
						if ( ! isset( $enabled_combinations[ $paper_type ] ) ) {
							$enabled_combinations[ $paper_type ] = array();
						}
						$enabled_combinations[ $paper_type ][ $print_type ] = true;
					}
				}
			}

			// Now determine which print types are forbidden for each paper type
			// If BOTH bw and color are disabled for a paper type, we mark it as forbidden
			// Otherwise, we mark specific print types as forbidden
			foreach ( $enabled_combinations as $paper_type => $enabled_prints ) {
				$bw_enabled    = isset( $enabled_prints['bw'] );
				$color_enabled = isset( $enabled_prints['color'] );

				// Build the forbidden list for this paper type
				$forbidden_for_paper = array();

				if ( ! $bw_enabled ) {
					$forbidden_for_paper[] = 'bw';
				}
				if ( ! $color_enabled ) {
					$forbidden_for_paper[] = 'color';
				}

				// Only add to restrictions if there are forbidden types
				if ( ! empty( $forbidden_for_paper ) ) {
					$restrictions['forbidden_print_types'][ $paper_type ] = $forbidden_for_paper;
				}
			}
		}

		// Parse forbidden cover weights from inline toggles
		// Format: restrictions[forbidden_cover_weights][binding_type][cover_weight] = "0" (checked = enabled)
		if ( isset( $data['forbidden_cover_weights'] ) && is_array( $data['forbidden_cover_weights'] ) ) {
			$enabled_cover_combinations = array();

			foreach ( $data['forbidden_cover_weights'] as $binding_type => $weights_data ) {
				$binding_type = sanitize_text_field( $binding_type );

				if ( ! is_array( $weights_data ) ) {
					continue;
				}

				foreach ( $weights_data as $cover_weight => $value ) {
					$cover_weight = sanitize_text_field( $cover_weight );

					// If checkbox exists in POST (value = "0"), it means it's ENABLED
					if ( ! isset( $enabled_cover_combinations[ $binding_type ] ) ) {
						$enabled_cover_combinations[ $binding_type ] = array();
					}
					$enabled_cover_combinations[ $binding_type ][ $cover_weight ] = true;
				}
			}

			// Determine forbidden cover weights for each binding type
			// We need to get all cover weights to know which ones are disabled
			$all_cover_weights = $this->get_configured_cover_weights();

			foreach ( $enabled_cover_combinations as $binding_type => $enabled_weights ) {
				$forbidden_for_binding = array();

				foreach ( $all_cover_weights as $weight ) {
					if ( ! isset( $enabled_weights[ $weight ] ) ) {
						$forbidden_for_binding[] = $weight;
					}
				}

				// Only add to restrictions if there are forbidden weights
				if ( ! empty( $forbidden_for_binding ) ) {
					$restrictions['forbidden_cover_weights'][ $binding_type ] = $forbidden_for_binding;
				}
			}
		}

		// Parse forbidden extras from inline toggles.
		// Format: restrictions[forbidden_extras][binding_type][extra_service] = "0"
		// Logic: If checkbox is CHECKED, it's in POST data (enabled for that binding type).
		// If checkbox is UNCHECKED, it's NOT in POST data (disabled/forbidden for that binding type).
		// We track which combinations are enabled, then infer which are forbidden.
		if ( isset( $data['forbidden_extras'] ) && is_array( $data['forbidden_extras'] ) ) {
			$enabled_extras_combinations = array();

			foreach ( $data['forbidden_extras'] as $binding_type => $extras_data ) {
				$binding_type = sanitize_text_field( $binding_type );

				if ( ! is_array( $extras_data ) ) {
					continue;
				}

				foreach ( $extras_data as $extra_service => $value ) {
					$extra_service = sanitize_text_field( $extra_service );

					// If checkbox exists in POST data, it means the checkbox was CHECKED (enabled).
					// The value "0" is arbitrary - we only care that the key exists in POST.
					if ( ! isset( $enabled_extras_combinations[ $binding_type ] ) ) {
						$enabled_extras_combinations[ $binding_type ] = array();
					}
					$enabled_extras_combinations[ $binding_type ][ $extra_service ] = true;
				}
			}

			// Determine forbidden extras for each binding type
			// We need to get all extra services to know which ones are disabled
			$all_extras = $this->get_configured_extra_services();

			foreach ( $enabled_extras_combinations as $binding_type => $enabled_extras ) {
				$forbidden_for_binding = array();

				foreach ( $all_extras as $extra_service ) {
					if ( ! isset( $enabled_extras[ $extra_service ] ) ) {
						$forbidden_for_binding[] = $extra_service;
					}
				}

				// Only add to restrictions if there are forbidden extras
				if ( ! empty( $forbidden_for_binding ) ) {
					$restrictions['forbidden_extras'][ $binding_type ] = $forbidden_for_binding;
				}
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
	 * CRITICAL FIX: Only return sizes from product parameters (source of truth)
	 * This prevents the broken cycle where pricing matrices exist for invalid sizes.
	 *
	 * @return array Array of book sizes
	 */
	private function get_all_book_sizes() {
		// ONLY use product parameters (book_sizes setting) as source of truth
		// This ensures consistency throughout the system
		$admin_sizes = $this->get_valid_book_sizes_from_settings();

		// Log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh Product Pricing: get_all_book_sizes returning %d sizes from product parameters',
					count( $admin_sizes )
				)
			);
		}

		return $admin_sizes;
	}

	/**
	 * Get valid book sizes from product parameters (source of truth)
	 * This is used for validation to prevent data corruption
	 *
	 * @return array Array of valid book sizes
	 */
	private function get_valid_book_sizes_from_settings() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$admin_sizes = array();
		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$admin_sizes = $decoded;
			}
		}

		// CRITICAL FIX: Do NOT return defaults!
		// Returning defaults when product parameters are empty causes the "unknown book size" problem.
		// This was the root cause of the broken pricing cycle.
		// Admin must explicitly configure book sizes in product settings FIRST.
		// This ensures:
		// 1. Single source of truth (product parameters).
		// 2. No orphaned pricing matrices.
		// 3. Clear error messages when setup is incomplete.
		// 4. Predictable system behavior.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( empty( $admin_sizes ) ) {
				error_log( 'Tabesh Product Pricing: WARNING - No book sizes configured in product parameters! Returning empty array.' );
			} else {
				error_log( 'Tabesh Product Pricing: Found ' . count( $admin_sizes ) . ' book sizes in product parameters: ' . implode( ', ', $admin_sizes ) );
			}
		}

		return $admin_sizes;
	}

	/**
	 * Get pricing matrix for a specific book size.
	 *
	 * CRITICAL FIX: Must use base64_encode() to match how save_pricing_matrix() stores keys.
	 * DO NOT use sanitize_key() as it corrupts Persian text and causes key mismatch.
	 *
	 * @param string $book_size Book size identifier.
	 * @return array Pricing matrix.
	 */
	public function get_pricing_matrix_for_size( $book_size ) {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// CRITICAL FIX: Normalize book_size BEFORE base64_encode to match save_pricing_matrix()
		// This ensures retrieval works even if product parameters contain descriptions
		// like "رقعی (14×20)" while matrix was saved as "رقعی"
		$normalized_book_size = $this->pricing_engine->normalize_book_size_key( $book_size );
		$safe_key             = base64_encode( $normalized_book_size );
		$setting_key          = 'pricing_matrix_' . $safe_key;

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $book_size !== $normalized_book_size ) {
			error_log(
				sprintf(
					'Tabesh Product Pricing: get_pricing_matrix_for_size - Original: "%s", Normalized: "%s", Key: "%s"',
					$book_size,
					$normalized_book_size,
					$setting_key
				)
			);
		}

		$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				$setting_key
			)
		);

		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'Tabesh Product Pricing: Successfully retrieved pricing matrix for "%s" (normalized: "%s")',
							$book_size,
							$normalized_book_size
						)
					);
				}
				return $decoded;
			}
		}

		// Log if matrix not found
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh Product Pricing: No pricing matrix found for "%s" (normalized: "%s"), returning default',
					$book_size,
					$normalized_book_size
				)
			);
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
			error_log(
				sprintf(
					'Tabesh: Existing pricing_engine_v2_enabled value: "%s"',
					$existing === null ? 'NULL (not found in DB)' : $existing
				)
			);
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
				error_log(
					sprintf(
						'Tabesh: UPDATE result for pricing_engine_v2_enabled: %s (rows affected: %d)',
						$result === false ? 'FAILED' : 'SUCCESS',
						$result === false ? 0 : $result
					)
				);
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
				error_log(
					sprintf(
						'Tabesh: INSERT result for pricing_engine_v2_enabled: %s',
						$result === false ? 'FAILED' : 'SUCCESS'
					)
				);
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
				error_log(
					sprintf(
						'Tabesh: VERIFICATION - Value in DB after save: "%s"',
						$verify === null ? 'NULL' : $verify
					)
				);
			}
		}

		return false !== $result;
	}

	/**
	 * Get configured paper types from admin settings
	 *
	 * @return array Paper types with weights
	 */
	private function get_configured_paper_types() {
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

		// Default paper types
		return array(
			'تحریر' => array( '60', '70', '80' ),
			'بالک'  => array( '60', '70', '80', '100' ),
			'گلاسه' => array( '80', '100', '115' ),
		);
	}

	/**
	 * Get configured binding types from admin settings
	 *
	 * @return array Binding types
	 */
	private function get_configured_binding_types() {
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

		// Default binding types
		return array( 'شومیز', 'جلد سخت', 'گالینگور', 'سیمی', 'منگنه' );
	}

	/**
	 * Get configured extra services from admin settings
	 *
	 * @return array Extra services
	 */
	private function get_configured_extra_services() {
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
				return array_values(
					array_filter(
						array_map(
							function ( $extra ) {
								$extra = is_scalar( $extra ) ? trim( strval( $extra ) ) : '';
								return ( ! empty( $extra ) && $extra !== 'on' ) ? $extra : null;
							},
							$decoded
						)
					)
				);
			}
		}

		// Default extra services
		return array( 'لب گرد', 'خط تا', 'شیرینک', 'سوراخ', 'شماره گذاری' );
	}

	/**
	 * Get configured cover paper weights from admin settings
	 *
	 * @return array Cover paper weights
	 */
	private function get_configured_cover_weights() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'cover_paper_weights'
			)
		);

		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				// Filter out invalid values and ensure numeric sort
				$weights = array_filter(
					array_map(
						function ( $weight ) {
							$weight = is_scalar( $weight ) ? trim( strval( $weight ) ) : '';
							return ( ! empty( $weight ) && is_numeric( $weight ) ) ? $weight : null;
						},
						$decoded
					)
				);
				// Sort numerically
				usort(
					$weights,
					function ( $a, $b ) {
						return intval( $a ) - intval( $b );
					}
				);
				return array_values( $weights );
			}
		}

		// Default cover paper weights
		return array( '200', '250', '300', '350' );
	}

	/**
	 * Get binding cost for a specific combination of binding type and cover weight
	 *
	 * Handles both new structure (array of weights) and legacy structure (single value).
	 *
	 * @param array  $pricing_matrix Pricing matrix data.
	 * @param string $binding_type Binding type.
	 * @param string $cover_weight Cover weight.
	 * @return float Binding cost (defaults to 0 if not found).
	 */
	public function get_binding_cost_for_weight( $pricing_matrix, $binding_type, $cover_weight ) {
		$binding_data = $pricing_matrix['binding_costs'][ $binding_type ] ?? array();

		if ( is_array( $binding_data ) ) {
			// New structure: array of weights.
			return floatval( $binding_data[ $cover_weight ] ?? 0 );
		}

		// Legacy structure: single cost value - use it as default for all weights.
		return floatval( $binding_data );
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
			error_log(
				sprintf(
					'Tabesh: UPDATE result for disabling pricing_engine_v2_enabled: %s',
					$result === false ? 'FAILED' : 'SUCCESS'
				)
			);
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

	/**
	 * Cleanup orphaned pricing matrices that don't have corresponding book size in product parameters
	 *
	 * CRITICAL FIX: This method is DISABLED to prevent deleting valid matrices.
	 * The original implementation was too aggressive and would delete matrices when:
	 * 1. book_sizes setting is empty (during initial configuration)
	 * 2. book_sizes setting is being reconfigured
	 * 3. Matrices exist for sizes that admin is about to add
	 *
	 * Instead, orphaned matrix cleanup is now handled by the migrate_mismatched_book_size_keys()
	 * method which is more careful and only removes truly orphaned entries.
	 *
	 * @return int Number of orphaned matrices removed (always 0 in current implementation)
	 */
	private function cleanup_orphaned_pricing_matrices() {
		// CRITICAL FIX: Disable aggressive cleanup that was deleting valid matrices.
		// The original logic compared matrices against book_sizes setting, but this
		// caused data loss when:
		// - Admin is configuring book sizes for the first time
		// - Admin is temporarily removing/re-adding book sizes
		// - Matrices exist from previous configurations
		//
		// Instead, rely on migrate_mismatched_book_size_keys() which is more intelligent
		// and only removes truly invalid entries (bad encoding, etc.)

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: cleanup_orphaned_pricing_matrices disabled - using migrate_mismatched_book_size_keys instead' );
		}

		return 0;
	}
}
