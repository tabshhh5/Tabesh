<?php
/**
 * New Matrix-Based Pricing Engine
 *
 * This class implements a clean, industry-standard pricing calculation
 * based on book size as the primary dimension. Each book size has its own
 * independent pricing matrix for all parameters.
 *
 * Key principles:
 * - No multipliers - direct pricing per book size
 * - Unified per-page cost (print + paper combined)
 * - Size-specific binding and cover costs
 * - Parameter restriction support (forbid certain combinations)
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabesh Pricing Engine Class
 */
class Tabesh_Pricing_Engine {

	/**
	 * Cache for pricing matrix to avoid redundant database queries
	 *
	 * @var array|null
	 */
	private static $pricing_matrix_cache = null;

	/**
	 * Clear pricing matrix cache
	 * Should be called when pricing settings are updated
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$pricing_matrix_cache = null;
	}

	/**
	 * Check if new pricing engine is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_name} WHERE setting_key = %s",
				'pricing_engine_v2_enabled'
			)
		);

		return '1' === $result || 'true' === $result;
	}

	/**
	 * Calculate book printing price using new matrix-based engine
	 *
	 * Formula: FinalPrice = (PageCost + CoverCost + BindingCost) * Quantity * (1 + ProfitMargin) - Discount
	 *
	 * @param array $params Order parameters
	 * @return array Price breakdown
	 */
	public function calculate_price( $params ) {
		// Log incoming parameters for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh Pricing Engine V2: calculate_price called with params: ' . print_r( $params, true ) );
		}

		// Sanitize and extract input parameters
		$book_size        = sanitize_text_field( $params['book_size'] ?? '' );
		$paper_type       = sanitize_text_field( $params['paper_type'] ?? '' );
		$paper_weight     = sanitize_text_field( $params['paper_weight'] ?? '' );
		$print_type       = sanitize_text_field( $params['print_type'] ?? '' );
		$page_count_color = intval( $params['page_count_color'] ?? 0 );
		$page_count_bw    = intval( $params['page_count_bw'] ?? 0 );
		$quantity         = intval( $params['quantity'] ?? 0 );
		$binding_type     = sanitize_text_field( $params['binding_type'] ?? '' );

		// Sanitize extras array
		$extras = array();
		if ( isset( $params['extras'] ) && is_array( $params['extras'] ) ) {
			foreach ( $params['extras'] as $extra ) {
				$sanitized_extra = sanitize_text_field( $extra );
				if ( ! empty( $sanitized_extra ) ) {
					$extras[] = $sanitized_extra;
				}
			}
		}

		// Round pages to even number
		$page_count_total = $page_count_color + $page_count_bw;
		if ( $page_count_total % 2 !== 0 ) {
			++$page_count_total;
		}

		// Get pricing matrix for this book size
		$pricing_matrix = $this->get_pricing_matrix( $book_size );

		if ( ! $pricing_matrix ) {
			// Fallback to default or return error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Tabesh Pricing Engine V2 ERROR: No pricing matrix found for book size: $book_size" );
			}
			return array(
				'error'   => true,
				'message' => sprintf( __( 'قیمت‌گذاری برای قطع %s تنظیم نشده است', 'tabesh' ), $book_size ),
			);
		}

		// Step 1: Validate parameter combination is allowed
		$validation = $this->validate_parameters( $book_size, $paper_type, $paper_weight, $print_type, $binding_type );
		if ( ! $validation['allowed'] ) {
			return array(
				'error'   => true,
				'message' => $validation['message'],
			);
		}

		// Step 2: Calculate per-page cost (unified: print + paper).
		// Get per-page cost for this combination: paper_type, paper_weight, print_type.
		$per_page_cost_bw    = $this->get_page_cost( $pricing_matrix, $paper_type, $paper_weight, 'bw' );
		$per_page_cost_color = $this->get_page_cost( $pricing_matrix, $paper_type, $paper_weight, 'color' );

		// Check if pricing is configured for the selected parameters.
		if ( null === $per_page_cost_bw && $page_count_bw > 0 ) {
			return array(
				'error'   => true,
				/* translators: 1: paper type, 2: paper weight, 3: book size */
				'message' => sprintf(
					__( 'قیمت چاپ تک‌رنگ برای کاغذ %1$s گرماژ %2$s در قطع %3$s تنظیم نشده است', 'tabesh' ),
					$paper_type,
					$paper_weight,
					$book_size
				),
			);
		}

		if ( null === $per_page_cost_color && $page_count_color > 0 ) {
			return array(
				'error'   => true,
				/* translators: 1: paper type, 2: paper weight, 3: book size */
				'message' => sprintf(
					__( 'قیمت چاپ رنگی برای کاغذ %1$s گرماژ %2$s در قطع %3$s تنظیم نشده است', 'tabesh' ),
					$paper_type,
					$paper_weight,
					$book_size
				),
			);
		}

		// Use 0 if null (for unused print types).
		$per_page_cost_bw    = $per_page_cost_bw ?? 0.0;
		$per_page_cost_color = $per_page_cost_color ?? 0.0;

		// Step 3: Calculate total pages cost.
		$pages_cost_bw    = $per_page_cost_bw * $page_count_bw;
		$pages_cost_color = $per_page_cost_color * $page_count_color;
		$total_pages_cost = $pages_cost_bw + $pages_cost_color;

		// Step 4: Get binding cost for this book size.
		$binding_cost = $this->get_binding_cost( $pricing_matrix, $binding_type );

		if ( null === $binding_cost ) {
			return array(
				'error'   => true,
				/* translators: 1: binding type, 2: book size */
				'message' => sprintf(
					__( 'قیمت صحافی %1$s برای قطع %2$s تنظیم نشده است', 'tabesh' ),
					$binding_type,
					$book_size
				),
			);
		}

		// Step 5: Get cover cost for this book size
		$cover_cost = $this->get_cover_cost( $pricing_matrix );

		// Step 6: Calculate extras cost (no change from old system)
		$extras_cost = $this->calculate_extras_cost( $pricing_matrix, $extras, $quantity, $page_count_total );

		// Step 7: Calculate production cost per book
		$production_cost_per_book = $total_pages_cost + $cover_cost + $binding_cost + $extras_cost;

		// Step 8: Calculate subtotal (quantity multiplication)
		$subtotal = $production_cost_per_book * $quantity;

		// Step 9: Apply quantity discounts
		$discount_info        = $this->calculate_discount( $quantity, $subtotal );
		$discount_percent     = $discount_info['percent'];
		$discount_amount      = $discount_info['amount'];
		$total_after_discount = $subtotal - $discount_amount;

		// Step 10: Apply profit margin
		$profit_margin = floatval( $pricing_matrix['profit_margin'] ?? 0.0 );
		$profit_amount = $total_after_discount * $profit_margin;
		$total_price   = $total_after_discount + $profit_amount;

		// Return comprehensive breakdown
		return array(
			'price_per_book'        => $production_cost_per_book,
			'quantity'              => $quantity,
			'subtotal'              => $subtotal,
			'discount_percent'      => $discount_percent,
			'discount_amount'       => $discount_amount,
			'total_after_discount'  => $total_after_discount,
			'profit_margin_percent' => $profit_margin * 100,
			'profit_amount'         => $profit_amount,
			'total_price'           => $total_price,
			'page_count_total'      => $page_count_total,
			'pricing_engine'        => 'v2_matrix',
			// Detailed breakdown for transparency
			'breakdown'             => array(
				'book_size'           => $book_size,
				'pages_cost_bw'       => $pages_cost_bw,
				'pages_cost_color'    => $pages_cost_color,
				'total_pages_cost'    => $total_pages_cost,
				'cover_cost'          => $cover_cost,
				'binding_cost'        => $binding_cost,
				'extras_cost'         => $extras_cost,
				'per_page_cost_bw'    => $per_page_cost_bw,
				'per_page_cost_color' => $per_page_cost_color,
			),
		);
	}

	/**
	 * Validate that parameter combination is allowed for this book size
	 *
	 * @param string $book_size Book size
	 * @param string $paper_type Paper type
	 * @param string $paper_weight Paper weight
	 * @param string $print_type Print type
	 * @param string $binding_type Binding type
	 * @return array Validation result with 'allowed' and 'message' keys
	 */
	private function validate_parameters( $book_size, $paper_type, $paper_weight, $print_type, $binding_type ) {
		$pricing_matrix = $this->get_pricing_matrix( $book_size );

		if ( ! $pricing_matrix ) {
			return array(
				'allowed' => false,
				'message' => sprintf( __( 'قطع %s پشتیبانی نمی‌شود', 'tabesh' ), $book_size ),
			);
		}

		// Check restrictions
		$restrictions = $pricing_matrix['restrictions'] ?? array();

		// Check if this paper type is forbidden
		$forbidden_papers = $restrictions['forbidden_paper_types'] ?? array();
		if ( in_array( $paper_type, $forbidden_papers, true ) ) {
			return array(
				'allowed' => false,
				'message' => sprintf( __( 'کاغذ %1$s برای قطع %2$s مجاز نیست', 'tabesh' ), $paper_type, $book_size ),
			);
		}

		// Check if this binding type is forbidden
		$forbidden_bindings = $restrictions['forbidden_binding_types'] ?? array();
		if ( in_array( $binding_type, $forbidden_bindings, true ) ) {
			return array(
				'allowed' => false,
				'message' => sprintf( __( 'صحافی %1$s برای قطع %2$s مجاز نیست', 'tabesh' ), $binding_type, $book_size ),
			);
		}

		// Check if this print type is forbidden for this paper
		$forbidden_print_for_paper = $restrictions['forbidden_print_types'][ $paper_type ] ?? array();
		if ( in_array( $print_type, $forbidden_print_for_paper, true ) ) {
			return array(
				'allowed' => false,
				'message' => sprintf(
					__( 'چاپ %1$s برای کاغذ %2$s در قطع %3$s مجاز نیست', 'tabesh' ),
					$print_type,
					$paper_type,
					$book_size
				),
			);
		}

		return array( 'allowed' => true );
	}

	/**
	 * Get per-page cost for specific paper and print combination
	 *
	 * @param array  $pricing_matrix Pricing matrix for book size.
	 * @param string $paper_type Paper type.
	 * @param string $paper_weight Paper weight.
	 * @param string $print_type Print type (bw or color).
	 * @return float|null Per-page cost or null if not configured.
	 */
	private function get_page_cost( $pricing_matrix, $paper_type, $paper_weight, $print_type ) {
		// New structure: page_costs[paper_type][paper_weight][print_type]
		$page_costs = $pricing_matrix['page_costs'] ?? array();

		if ( isset( $page_costs[ $paper_type ][ $paper_weight ][ $print_type ] ) ) {
			return floatval( $page_costs[ $paper_type ][ $paper_weight ][ $print_type ] );
		}

		// Fallback: try without weight specification
		if ( isset( $page_costs[ $paper_type ][ $print_type ] ) ) {
			return floatval( $page_costs[ $paper_type ][ $print_type ] );
		}

		// Return null if not found - caller must handle
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh Pricing Engine V2 ERROR: Page cost not configured for paper=%s, weight=%s, print=%s',
					$paper_type,
					$paper_weight,
					$print_type
				)
			);
		}

		return null;
	}

	/**
	 * Get binding cost for this book size
	 *
	 * @param array  $pricing_matrix Pricing matrix for book size.
	 * @param string $binding_type Binding type.
	 * @return float|null Binding cost or null if not configured.
	 */
	private function get_binding_cost( $pricing_matrix, $binding_type ) {
		$binding_costs = $pricing_matrix['binding_costs'] ?? array();

		if ( isset( $binding_costs[ $binding_type ] ) ) {
			return floatval( $binding_costs[ $binding_type ] );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Tabesh Pricing Engine V2 ERROR: Binding cost not configured for type=$binding_type" );
		}

		return null;
	}

	/**
	 * Get cover cost for this book size
	 *
	 * @param array $pricing_matrix Pricing matrix for book size
	 * @return float Cover cost
	 */
	private function get_cover_cost( $pricing_matrix ) {
		return floatval( $pricing_matrix['cover_cost'] ?? 8000.0 );
	}

	/**
	 * Calculate extras cost
	 *
	 * @param array $pricing_matrix Pricing matrix
	 * @param array $extras Array of extra services
	 * @param int   $quantity Quantity
	 * @param int   $page_count_total Total page count
	 * @return float Total extras cost
	 */
	private function calculate_extras_cost( $pricing_matrix, $extras, $quantity, $page_count_total ) {
		$extras_config = $pricing_matrix['extras_costs'] ?? array();
		$total_cost    = 0;

		foreach ( $extras as $extra ) {
			if ( isset( $extras_config[ $extra ] ) ) {
				$config     = $extras_config[ $extra ];
				$price      = floatval( $config['price'] ?? 0 );
				$type       = $config['type'] ?? 'fixed';
				$extra_cost = 0;

				switch ( $type ) {
					case 'fixed':
						$extra_cost = $price;
						break;
					case 'per_unit':
						$extra_cost = $price * $quantity;
						break;
					case 'page_based':
						$step        = intval( $config['step'] ?? 16000 );
						$total_pages = $page_count_total * $quantity;
						$units       = ceil( $total_pages / $step );
						$extra_cost  = $price * $units;
						break;
				}

				$total_cost += $extra_cost;
			}
		}

		return $total_cost;
	}

	/**
	 * Calculate quantity discount
	 *
	 * @param int   $quantity Order quantity
	 * @param float $subtotal Subtotal before discount
	 * @return array Discount info with 'percent' and 'amount' keys
	 */
	private function calculate_discount( $quantity, $subtotal ) {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Get quantity discounts
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'pricing_quantity_discounts'
			)
		);

		$discounts = array();
		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$discounts = $decoded;
			}
		}

		// Default discounts if not configured
		if ( empty( $discounts ) ) {
			$discounts = array(
				100 => 10,
				50  => 5,
			);
		}

		// Sort by quantity descending to find highest applicable discount
		krsort( $discounts, SORT_NUMERIC );

		$discount_percent = 0;
		foreach ( $discounts as $threshold => $percent ) {
			if ( $quantity >= $threshold ) {
				$discount_percent = floatval( $percent );
				break;
			}
		}

		$discount_amount = ( $subtotal * $discount_percent ) / 100;

		return array(
			'percent' => $discount_percent,
			'amount'  => $discount_amount,
		);
	}

	/**
	 * Get pricing matrix for a specific book size
	 *
	 * @param string $book_size Book size identifier
	 * @return array|null Pricing matrix or null if not found
	 */
	private function get_pricing_matrix( $book_size ) {
		// Return cached matrix if available
		if ( null !== self::$pricing_matrix_cache ) {
			return self::$pricing_matrix_cache[ $book_size ] ?? null;
		}

		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Load all pricing matrices
		$result = $wpdb->get_results(
			"SELECT setting_key, setting_value FROM $table_settings WHERE setting_key LIKE 'pricing_matrix_%'",
			ARRAY_A
		);

		self::$pricing_matrix_cache = array();

		foreach ( $result as $row ) {
			// Extract book size from key: pricing_matrix_A5 -> A5
			$key     = $row['setting_key'];
			$size    = str_replace( 'pricing_matrix_', '', $key );
			$value   = $row['setting_value'];
			$decoded = json_decode( $value, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				self::$pricing_matrix_cache[ $size ] = $decoded;
			}
		}

		return self::$pricing_matrix_cache[ $book_size ] ?? null;
	}

	/**
	 * Get default pricing matrix for a book size
	 *
	 * @param string $book_size Book size identifier
	 * @return array Default pricing matrix
	 */
	public function get_default_pricing_matrix( $book_size ) {
		return array(
			'book_size'     => $book_size,
			'page_costs'    => array(
				'تحریر' => array(
					'60' => array(
						'bw'    => 350,
						'color' => 950,
					),
					'70' => array(
						'bw'    => 380,
						'color' => 980,
					),
					'80' => array(
						'bw'    => 400,
						'color' => 1000,
					),
				),
				'بالک'  => array(
					'60'  => array(
						'bw'    => 400,
						'color' => 1000,
					),
					'70'  => array(
						'bw'    => 430,
						'color' => 1030,
					),
					'80'  => array(
						'bw'    => 450,
						'color' => 1050,
					),
					'100' => array(
						'bw'    => 500,
						'color' => 1100,
					),
				),
			),
			'binding_costs' => array(
				'شومیز'    => 3000,
				'جلد سخت'  => 8000,
				'گالینگور' => 6000,
				'سیمی'     => 2000,
			),
			'cover_cost'    => 8000,
			'extras_costs'  => array(
				'لب گرد' => array(
					'price' => 1000,
					'type'  => 'per_unit',
					'step'  => 0,
				),
				'خط تا'  => array(
					'price' => 500,
					'type'  => 'per_unit',
					'step'  => 0,
				),
				'شیرینک' => array(
					'price' => 1500,
					'type'  => 'per_unit',
					'step'  => 0,
				),
			),
			'profit_margin' => 0.0,
			'restrictions'  => array(
				'forbidden_paper_types'   => array(),
				'forbidden_binding_types' => array(),
				'forbidden_print_types'   => array(),
			),
		);
	}

	/**
	 * Get available options for a book size (considering restrictions)
	 *
	 * This method provides the allowed options for forms to prevent
	 * users from selecting forbidden combinations.
	 *
	 * @param string $book_size Book size identifier.
	 * @return array Available options structure.
	 */
	public function get_available_options( $book_size ) {
		$pricing_matrix = $this->get_pricing_matrix( $book_size );

		if ( ! $pricing_matrix ) {
			return array(
				'error'   => true,
				'message' => sprintf( __( 'قطع %s پیکربندی نشده است', 'tabesh' ), $book_size ),
			);
		}

		$restrictions = $pricing_matrix['restrictions'] ?? array();
		$page_costs   = $pricing_matrix['page_costs'] ?? array();

		$available_papers   = array();
		$available_bindings = array();

		// Get all configured paper types.
		foreach ( $page_costs as $paper_type => $weights ) {
			// Check if paper is completely forbidden.
			if ( in_array( $paper_type, $restrictions['forbidden_paper_types'] ?? array(), true ) ) {
				continue;
			}

			// Check which print types are allowed for this paper.
			$forbidden_prints = $restrictions['forbidden_print_types'][ $paper_type ] ?? array();
			$allowed_prints   = array();

			if ( ! in_array( 'bw', $forbidden_prints, true ) ) {
				$allowed_prints[] = 'bw';
			}
			if ( ! in_array( 'color', $forbidden_prints, true ) ) {
				$allowed_prints[] = 'color';
			}

			// Only include papers that have at least one allowed print type.
			if ( ! empty( $allowed_prints ) ) {
				$available_papers[ $paper_type ] = array(
					'weights'        => array_keys( $weights ),
					'allowed_prints' => $allowed_prints,
				);
			}
		}

		// Get all configured binding types.
		$binding_costs      = $pricing_matrix['binding_costs'] ?? array();
		$forbidden_bindings = $restrictions['forbidden_binding_types'] ?? array();

		foreach ( $binding_costs as $binding_type => $cost ) {
			if ( ! in_array( $binding_type, $forbidden_bindings, true ) ) {
				$available_bindings[] = $binding_type;
			}
		}

		return array(
			'book_size'          => $book_size,
			'available_papers'   => $available_papers,
			'available_bindings' => $available_bindings,
			'has_restrictions'   => ! empty( $restrictions['forbidden_paper_types'] )
									|| ! empty( $restrictions['forbidden_binding_types'] )
									|| ! empty( $restrictions['forbidden_print_types'] ),
		);
	}

	/**
	 * Save pricing matrix for a book size
	 *
	 * @param string $book_size Book size identifier
	 * @param array  $matrix Pricing matrix data
	 * @return bool Success status
	 */
	public function save_pricing_matrix( $book_size, $matrix ) {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		$setting_key   = 'pricing_matrix_' . sanitize_key( $book_size );
		$setting_value = wp_json_encode( $matrix );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				$setting_key
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$table_settings,
				array( 'setting_value' => $setting_value ),
				array( 'setting_key' => $setting_key ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			$result = $wpdb->insert(
				$table_settings,
				array(
					'setting_key'   => $setting_key,
					'setting_value' => $setting_value,
				),
				array( '%s', '%s' )
			);
		}

		if ( false !== $result ) {
			self::clear_cache();
			return true;
		}

		return false;
	}

	/**
	 * Get list of all configured book sizes
	 *
	 * @return array Array of book size identifiers
	 */
	public function get_configured_book_sizes() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		$results = $wpdb->get_results(
			"SELECT setting_key FROM $table_settings WHERE setting_key LIKE 'pricing_matrix_%'",
			ARRAY_A
		);

		$sizes = array();
		foreach ( $results as $row ) {
			$size    = str_replace( 'pricing_matrix_', '', $row['setting_key'] );
			$sizes[] = $size;
		}

		return $sizes;
	}
}
