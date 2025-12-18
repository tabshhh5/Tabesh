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
	 * Cache for V2 enabled status to avoid redundant database queries
	 *
	 * @var bool|null
	 */
	private static $v2_enabled_cache = null;

	/**
	 * Clear pricing matrix cache
	 * Should be called when pricing settings are updated
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$pricing_matrix_cache = null;
		self::$v2_enabled_cache     = null;
	}

	/**
	 * Static helper to check if V2 is active without instantiating the class
	 * This is the recommended way to check pricing engine status
	 *
	 * @return bool
	 */
	public static function is_v2_active() {
		$instance = new self();
		return $instance->is_enabled();
	}

	/**
	 * Get diagnostic information about pricing engine status
	 * Useful for debugging activation issues
	 *
	 * @return array Diagnostic information
	 */
	public static function get_diagnostic_info() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'tabesh_settings';

		// Query the database directly
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_name} WHERE setting_key = %s",
				'pricing_engine_v2_enabled'
			)
		);

		$is_active = self::is_v2_active();

		return array(
			'database_value'      => $result,
			'database_value_type' => gettype( $result ),
			'is_null'             => null === $result,
			'is_v2_active'        => $is_active,
			'cache_status'        => null === self::$v2_enabled_cache ? 'empty' : 'populated',
			'cached_value'        => self::$v2_enabled_cache,
			'table_name'          => $table_name,
		);
	}

	/**
	 * Check if new pricing engine is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		// Return cached status if available
		if ( null !== self::$v2_enabled_cache ) {
			return self::$v2_enabled_cache;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_name} WHERE setting_key = %s",
				'pricing_engine_v2_enabled'
			)
		);

		// Debug logging if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh Pricing Engine V2: Checking enabled status - DB value: "%s", Type: %s',
					$result === null ? 'NULL' : $result,
					gettype( $result )
				)
			);
		}

		// Check for both string '1' and string 'true'
		// Note: Database stores as string, not boolean
		$is_enabled = ( '1' === $result || 'true' === $result );

		// Cache the result
		self::$v2_enabled_cache = $is_enabled;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh Pricing Engine V2: Status determination - Enabled: %s',
					$is_enabled ? 'YES' : 'NO'
				)
			);
		}

		return $is_enabled;
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

		// Sanitize and extract input parameters with strict validation
		$book_size    = sanitize_text_field( $params['book_size'] ?? '' );
		$paper_type   = sanitize_text_field( $params['paper_type'] ?? '' );
		$paper_weight = sanitize_text_field( $params['paper_weight'] ?? '' );
		$print_type   = sanitize_text_field( $params['print_type'] ?? '' );
		$binding_type = sanitize_text_field( $params['binding_type'] ?? '' );
		$cover_weight = sanitize_text_field( $params['cover_paper_weight'] ?? $params['cover_weight'] ?? '' );

		// Validate and sanitize numeric inputs - prevent null/NaN
		$page_count_color = intval( $params['page_count_color'] ?? 0 );
		$page_count_bw    = intval( $params['page_count_bw'] ?? 0 );
		$quantity         = intval( $params['quantity'] ?? 0 );

		// Ensure non-negative values
		$page_count_color = max( 0, $page_count_color );
		$page_count_bw    = max( 0, $page_count_bw );
		$quantity         = max( 0, $quantity );

		// Validate required fields
		if ( empty( $book_size ) || empty( $paper_type ) || empty( $binding_type ) ) {
			return array(
				'error'   => true,
				'message' => __( 'اطلاعات ناقص: قطع کتاب، نوع کاغذ و نوع صحافی الزامی است', 'tabesh' ),
			);
		}

		// Validate quantity is positive
		if ( $quantity <= 0 ) {
			return array(
				'error'   => true,
				'message' => __( 'تعداد (تیراژ) باید بیشتر از صفر باشد', 'tabesh' ),
			);
		}

		// Validate total page count
		$page_count_total = $page_count_color + $page_count_bw;
		if ( $page_count_total <= 0 ) {
			return array(
				'error'   => true,
				'message' => __( 'تعداد صفحات باید بیشتر از صفر باشد', 'tabesh' ),
			);
		}

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

		// Validate quantity constraints for this book size
		$quantity_constraints = $pricing_matrix['quantity_constraints'] ?? array();
		$min_quantity         = isset( $quantity_constraints['minimum_quantity'] ) ? intval( $quantity_constraints['minimum_quantity'] ) : 0;
		$max_quantity         = isset( $quantity_constraints['maximum_quantity'] ) ? intval( $quantity_constraints['maximum_quantity'] ) : 0;
		$quantity_step        = isset( $quantity_constraints['quantity_step'] ) ? intval( $quantity_constraints['quantity_step'] ) : 0;

		// Check minimum quantity
		if ( $min_quantity > 0 && $quantity < $min_quantity ) {
			return array(
				'error'   => true,
				/* translators: 1: minimum quantity, 2: book size */
				'message' => sprintf(
					__( 'حداقل تیراژ مجاز برای قطع %2$s، %1$d عدد است', 'tabesh' ),
					$min_quantity,
					$book_size
				),
			);
		}

		// Check maximum quantity
		if ( $max_quantity > 0 && $quantity > $max_quantity ) {
			return array(
				'error'   => true,
				/* translators: 1: maximum quantity, 2: book size */
				'message' => sprintf(
					__( 'حداکثر تیراژ مجاز برای قطع %2$s، %1$d عدد است', 'tabesh' ),
					$max_quantity,
					$book_size
				),
			);
		}

		// Check quantity step
		if ( $quantity_step > 0 && ( $quantity % $quantity_step ) !== 0 ) {
			return array(
				'error'   => true,
				/* translators: 1: quantity step, 2: book size */
				'message' => sprintf(
					__( 'تیراژ باید بر اساس گام %1$d برای قطع %2$s باشد (مثال: %1$d، %3$d، %4$d)', 'tabesh' ),
					$quantity_step,
					$book_size,
					$quantity_step * 2,
					$quantity_step * 3
				),
			);
		}

		// Step 1: Validate parameter combination is allowed
		$validation = $this->validate_parameters( $book_size, $paper_type, $paper_weight, $print_type, $binding_type, $cover_weight );
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

		// Step 4: Get binding cost for this book size (includes cover cost in new structure).
		$binding_cost = $this->get_binding_cost( $pricing_matrix, $binding_type, $cover_weight );

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

		// Step 5.5: Validate extras are allowed for this binding type
		$forbidden_extras = $pricing_matrix['restrictions']['forbidden_extras'][ $binding_type ] ?? array();
		foreach ( $extras as $extra ) {
			if ( in_array( $extra, $forbidden_extras, true ) ) {
				return array(
					'error'   => true,
					/* translators: 1: extra service name, 2: binding type, 3: book size */
					'message' => sprintf(
						__( 'خدمت اضافی "%1$s" برای صحافی %2$s در قطع %3$s مجاز نیست', 'tabesh' ),
						$extra,
						$binding_type,
						$book_size
					),
				);
			}
		}

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

		// Final validation: ensure no NaN or null values
		$validate_numbers = array(
			'price_per_book'       => $production_cost_per_book,
			'quantity'             => $quantity,
			'subtotal'             => $subtotal,
			'total_price'          => $total_price,
			'total_after_discount' => $total_after_discount,
		);

		foreach ( $validate_numbers as $key => $value ) {
			if ( ! is_numeric( $value ) || is_nan( $value ) || is_infinite( $value ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Tabesh Pricing Engine V2 ERROR: Invalid numeric value for {$key}: " . var_export( $value, true ) );
				}
				return array(
					'error'   => true,
					'message' => __( 'خطا در محاسبه قیمت. لطفا تنظیمات قیمت‌گذاری را بررسی کنید.', 'tabesh' ),
				);
			}
		}

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
	 * @param string $cover_weight Cover paper weight (optional)
	 * @return array Validation result with 'allowed' and 'message' keys
	 */
	private function validate_parameters( $book_size, $paper_type, $paper_weight, $print_type, $binding_type, $cover_weight = '' ) {
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

		// Check if this cover weight is forbidden for this binding type
		if ( ! empty( $cover_weight ) ) {
			$forbidden_cover_weights = $restrictions['forbidden_cover_weights'][ $binding_type ] ?? array();
			if ( in_array( $cover_weight, $forbidden_cover_weights, true ) ) {
				return array(
					'allowed' => false,
					'message' => sprintf(
						__( 'گرماژ جلد %1$s برای صحافی %2$s در قطع %3$s مجاز نیست', 'tabesh' ),
						$cover_weight,
						$binding_type,
						$book_size
					),
				);
			}
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
	 * Get binding and cover cost for this book size
	 *
	 * New structure supports per-weight pricing: binding_costs[binding_type][cover_weight]
	 * Legacy structure fallback: binding_costs[binding_type] (single value)
	 *
	 * @param array  $pricing_matrix Pricing matrix for book size.
	 * @param string $binding_type Binding type.
	 * @param string $cover_weight Cover paper weight (optional, for new structure).
	 * @return float|null Binding+cover cost or null if not configured.
	 */
	private function get_binding_cost( $pricing_matrix, $binding_type, $cover_weight = null ) {
		$binding_costs = $pricing_matrix['binding_costs'] ?? array();

		if ( isset( $binding_costs[ $binding_type ] ) ) {
			$binding_data = $binding_costs[ $binding_type ];

			// New structure: array of weights
			if ( is_array( $binding_data ) ) {
				// If cover_weight is provided and exists, use it
				if ( null !== $cover_weight && isset( $binding_data[ $cover_weight ] ) ) {
					return floatval( $binding_data[ $cover_weight ] );
				}

				// Otherwise, try to find any available weight (first one)
				if ( ! empty( $binding_data ) ) {
					return floatval( reset( $binding_data ) );
				}

				// No weights configured
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh Pricing Engine V2 WARNING: No cover weights configured for binding type=' . sanitize_text_field( $binding_type ) );
				}
				return null;
			}

			// Legacy structure: single cost value
			return floatval( $binding_data );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh Pricing Engine V2 ERROR: Binding cost not configured for type=' . sanitize_text_field( $binding_type ) );
		}

		return null;
	}

	/**
	 * Get cover cost for this book size (Legacy compatibility)
	 *
	 * This method is deprecated. Cover costs are now part of binding_costs.
	 * Kept for backward compatibility with old pricing matrices.
	 *
	 * @param array $pricing_matrix Pricing matrix for book size
	 * @return float Cover cost (defaults to 0 for new structure)
	 */
	private function get_cover_cost( $pricing_matrix ) {
		// For legacy matrices that still have cover_cost
		if ( isset( $pricing_matrix['cover_cost'] ) ) {
			return floatval( $pricing_matrix['cover_cost'] );
		}

		// For new structure, cover cost is included in binding_costs
		// Return 0 to avoid double-counting
		return 0.0;
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
						// Step represents: price is per X pages
						// For example, step=100 means price per 100 pages
						// Default to 100 if not set (price per 100 pages)
						$step = intval( $config['step'] ?? 100 );
						if ( $step <= 0 ) {
							$step = 100; // Fallback to prevent division by zero
						}
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
			'book_size'            => $book_size,
			'page_costs'           => array(
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
			'binding_costs'        => array(
				'شومیز'    => array(
					'200' => 5000,
					'250' => 5500,
					'300' => 6000,
					'350' => 6500,
				),
				'جلد سخت'  => array(
					'200' => 10000,
					'250' => 11000,
					'300' => 12000,
					'350' => 13000,
				),
				'گالینگور' => array(
					'200' => 8000,
					'250' => 8500,
					'300' => 9000,
					'350' => 9500,
				),
				'سیمی'     => array(
					'200' => 3000,
					'250' => 3500,
					'300' => 4000,
					'350' => 4500,
				),
			),
			'extras_costs'         => array(
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
			'profit_margin'        => 0.0,
			'restrictions'         => array(
				'forbidden_paper_types'   => array(),
				'forbidden_binding_types' => array(),
				'forbidden_print_types'   => array(),
				'forbidden_cover_weights' => array(),
				'forbidden_extras'        => array(),
			),
			'quantity_constraints' => array(
				'minimum_quantity' => 10,
				'maximum_quantity' => 10000,
				'quantity_step'    => 10,
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
