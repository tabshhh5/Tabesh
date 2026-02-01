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
	 * Decode book size key from database-safe format
	 *
	 * Handles both base64-encoded keys (new format) and legacy keys.
	 * Validates the decoded result to ensure it's a reasonable book size string.
	 *
	 * @param string $safe_key The database key with pricing_matrix_ prefix removed.
	 * @return string The original book size name.
	 */
	private function decode_book_size_key( $safe_key ) {
		// Try base64 decoding first (new format).
		$decoded = base64_decode( $safe_key, true );

		// Validate decoded result.
		if ( false !== $decoded && $this->is_valid_book_size_string( $decoded ) ) {
			return $decoded;
		}

		// Legacy key format - not base64 encoded.
		// Still validate it's reasonable.
		if ( $this->is_valid_book_size_string( $safe_key ) ) {
			return $safe_key;
		}

		// If neither format is valid, log warning and return as-is.
		// This allows system to continue functioning even with unexpected keys.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh WARNING: Unexpected book_size key format: "%s"',
					$safe_key
				)
			);
		}

		return $safe_key;
	}

	/**
	 * Validate that a string looks like a reasonable book size name
	 *
	 * @param string $str The string to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_book_size_string( $str ) {
		// Book size should be non-empty, reasonable length, and contain valid characters.
		// Validate format: alphanumeric, Persian/Arabic characters, spaces, and common punctuation.
		// Pattern breakdown:
		// \p{L} = Any letter (including Persian/Arabic)
		// \p{N} = Any number
		// \s = Whitespace
		// \-_.() = Common punctuation for book sizes
		// + = One or more characters
		// u = Unicode mode
		return (
			is_string( $str ) &&
			strlen( $str ) > 0 &&
			strlen( $str ) <= 50 && // Reasonable max length.
			preg_match( '/^[\p{L}\p{N}\s\-_.()]+$/u', $str )
		);
	}

	/**
	 * Normalize book size name for consistent key generation
	 *
	 * Removes descriptions and dimensions in parentheses to ensure
	 * consistent matching between product parameters and pricing matrices.
	 *
	 * Examples:
	 * - "رقعی (14×20)" → "رقعی"
	 * - "وزیری (توضیحات)" → "وزیری"
	 * - "A5 (148×210)" → "A5"
	 * - "B5" → "B5" (no change)
	 *
	 * @param string $book_size Raw book size name (may include descriptions).
	 * @return string Normalized book size name (without parenthetical content).
	 */
	public function normalize_book_size_key( $book_size ) {
		// Remove anything in parentheses including the parentheses
		// Pattern: \s*\([^)]*\) matches space + opening paren + non-closing-paren content + closing paren
		// Note: This handles simple parentheses. Nested parentheses are not expected in book size names.
		// If nested parentheses are found, only the outermost will be removed in one pass.
		$normalized = preg_replace( '/\s*\([^)]*\)/', '', $book_size );

		// Trim any leading/trailing whitespace
		$normalized = trim( $normalized );

		// Validate result is not empty
		if ( empty( $normalized ) ) {
			// If normalization results in empty string, return original
			// This prevents data loss from malformed input like "(description only)"
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'Tabesh WARNING: Normalization resulted in empty string for "%s", returning original',
						$book_size
					)
				);
			}
			return $book_size;
		}

		// Log transformation if different
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $normalized !== $book_size ) {
			error_log(
				sprintf(
					'Tabesh: Normalized book_size key - Original: "%s" → Normalized: "%s"',
					$book_size,
					$normalized
				)
			);
		}

		return $normalized;
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
		$license_type = sanitize_text_field( $params['license_type'] ?? '' );

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

		// Step 1: Validate parameter combination is allowed and exists in matrix
		$validation = $this->validate_parameters(
			$book_size,
			$paper_type,
			$paper_weight,
			$print_type,
			$binding_type,
			$cover_weight,
			$page_count_bw,
			$page_count_color
		);
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

		// Step 5.1: Get license cost if license type is provided
		$license_cost = 0;
		if ( ! empty( $license_type ) ) {
			$license_cost = floatval( $pricing_matrix['license_costs'][ $license_type ] ?? 0 );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'Tabesh Pricing Engine V2: License cost for "%s": %s',
						$license_type,
						$license_cost
					)
				);
			}
		}

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

		// Step 6: Calculate extras cost with proper separation
		// Returns array with 'fixed' (applied once) and 'variable' (already accounts for quantity)
		$extras_costs_breakdown = $this->calculate_extras_cost( $pricing_matrix, $extras, $quantity, $page_count_total );
		$fixed_extras_cost      = $extras_costs_breakdown['fixed'];
		$variable_extras_cost   = $extras_costs_breakdown['variable'];

		// Step 7: Calculate production cost per book (WITHOUT variable extras)
		// Variable extras are already calculated for total quantity, so they're added separately
		// License cost is applied once per order, not per book
		$production_cost_per_book = $total_pages_cost + $cover_cost + $binding_cost;

		// Step 8: Calculate subtotal (quantity multiplication + extras + license)
		// First multiply per-book cost by quantity, then add fixed extras, variable extras, and license cost
		$subtotal_before_extras = $production_cost_per_book * $quantity;
		$subtotal               = $subtotal_before_extras + $fixed_extras_cost + $variable_extras_cost + $license_cost;

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
				'book_size'            => $book_size,
				'pages_cost_bw'        => $pages_cost_bw,
				'pages_cost_color'     => $pages_cost_color,
				'total_pages_cost'     => $total_pages_cost,
				'cover_cost'           => $cover_cost,
				'binding_cost'         => $binding_cost,
				'license_cost'         => $license_cost,
				'fixed_extras_cost'    => $fixed_extras_cost,
				'variable_extras_cost' => $variable_extras_cost,
				'total_extras_cost'    => $fixed_extras_cost + $variable_extras_cost,
				'per_page_cost_bw'     => $per_page_cost_bw,
				'per_page_cost_color'  => $per_page_cost_color,
			),
		);
	}

	/**
	 * Validate that the complete parameter combination exists in pricing matrix
	 * and is allowed for this book size.
	 *
	 * This method performs comprehensive validation in two stages:
	 * 1. Check if combination is forbidden by restrictions.
	 * 2. Check if combination exists in pricing matrix (has configured prices).
	 *
	 * @param string $book_size Book size.
	 * @param string $paper_type Paper type.
	 * @param string $paper_weight Paper weight.
	 * @param string $print_type Print type (not currently used in V2, kept for future).
	 * @param string $binding_type Binding type.
	 * @param string $cover_weight Cover paper weight (optional).
	 * @param int    $page_count_bw Black & white page count.
	 * @param int    $page_count_color Color page count.
	 * @return array Validation result with 'allowed' and 'message' keys.
	 */
	private function validate_parameters( $book_size, $paper_type, $paper_weight, $print_type, $binding_type, $cover_weight = '', $page_count_bw = 0, $page_count_color = 0 ) {
		$pricing_matrix = $this->get_pricing_matrix( $book_size );

		if ( ! $pricing_matrix ) {
			return array(
				'allowed' => false,
				'message' => sprintf( __( 'قطع %s پشتیبانی نمی‌شود', 'tabesh' ), $book_size ),
			);
		}

		// Stage 1: Check restrictions (forbidden combinations).
		$restrictions = $pricing_matrix['restrictions'] ?? array();

		// Check if this paper type is forbidden.
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

		// Check if this print type is forbidden for this paper weight.
		// CRITICAL FIX: Check at the per-weight level, not per-paper-type level.
		$forbidden_print_for_weight = $restrictions['forbidden_print_types'][ $paper_type ][ $paper_weight ] ?? array();
		if ( in_array( $print_type, $forbidden_print_for_weight, true ) ) {
			return array(
				'allowed' => false,
				'message' => sprintf(
					__( 'چاپ %1$s برای کاغذ %2$s (%3$s گرم) در قطع %4$s مجاز نیست', 'tabesh' ),
					$print_type,
					$paper_type,
					$paper_weight,
					$book_size
				),
			);
		}

		// Check if this cover weight is forbidden for this binding type.
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

		// Stage 2: Validate combination exists in pricing matrix.
		// This prevents returning zero price when configuration is missing.
		$validation_result = $this->validate_combination_exists(
			$pricing_matrix,
			$book_size,
			$paper_type,
			$paper_weight,
			$binding_type,
			$cover_weight,
			$page_count_bw,
			$page_count_color
		);

		if ( ! $validation_result['exists'] ) {
			return array(
				'allowed' => false,
				'message' => $validation_result['message'],
			);
		}

		return array( 'allowed' => true );
	}

	/**
	 * Validate that a specific combination exists in the pricing matrix.
	 *
	 * This method checks if all required pricing data is configured for the
	 * requested combination. This prevents returning zero or incorrect prices
	 * when parameters are missing from the matrix.
	 *
	 * @param array  $pricing_matrix Pricing matrix for book size.
	 * @param string $book_size Book size.
	 * @param string $paper_type Paper type.
	 * @param string $paper_weight Paper weight.
	 * @param string $binding_type Binding type.
	 * @param string $cover_weight Cover weight (optional).
	 * @param int    $page_count_bw Black & white page count.
	 * @param int    $page_count_color Color page count.
	 * @return array Result with 'exists' boolean and 'message' string.
	 */
	private function validate_combination_exists( $pricing_matrix, $book_size, $paper_type, $paper_weight, $binding_type, $cover_weight, $page_count_bw, $page_count_color ) {
		$page_costs    = $pricing_matrix['page_costs'] ?? array();
		$binding_costs = $pricing_matrix['binding_costs'] ?? array();

		// Check if paper type exists in matrix.
		if ( ! isset( $page_costs[ $paper_type ] ) ) {
			return array(
				'exists'  => false,
				'message' => sprintf(
					/* translators: 1: paper type, 2: book size */
					__( 'نوع کاغذ "%1$s" برای قطع %2$s در سیستم قیمت‌گذاری تنظیم نشده است. لطفا با مدیر سیستم تماس بگیرید.', 'tabesh' ),
					$paper_type,
					$book_size
				),
			);
		}

		// Check if paper weight exists for this paper type.
		if ( ! isset( $page_costs[ $paper_type ][ $paper_weight ] ) ) {
			return array(
				'exists'  => false,
				'message' => sprintf(
					/* translators: 1: paper weight, 2: paper type, 3: book size */
					__( 'گرماژ %1$s برای کاغذ %2$s در قطع %3$s تنظیم نشده است. لطفا گرماژ دیگری را انتخاب کنید.', 'tabesh' ),
					$paper_weight,
					$paper_type,
					$book_size
				),
			);
		}

		// Check if required print types (bw/color) are configured.
		$weight_costs = $page_costs[ $paper_type ][ $paper_weight ];

		if ( $page_count_bw > 0 && ! isset( $weight_costs['bw'] ) ) {
			return array(
				'exists'  => false,
				'message' => sprintf(
					/* translators: 1: paper type, 2: paper weight, 3: book size */
					__( 'قیمت چاپ سیاه و سفید برای کاغذ %1$s گرماژ %2$s در قطع %3$s تنظیم نشده است.', 'tabesh' ),
					$paper_type,
					$paper_weight,
					$book_size
				),
			);
		}

		if ( $page_count_color > 0 && ! isset( $weight_costs['color'] ) ) {
			return array(
				'exists'  => false,
				'message' => sprintf(
					/* translators: 1: paper type, 2: paper weight, 3: book size */
					__( 'قیمت چاپ رنگی برای کاغذ %1$s گرماژ %2$s در قطع %3$s تنظیم نشده است.', 'tabesh' ),
					$paper_type,
					$paper_weight,
					$book_size
				),
			);
		}

		// Check if binding type exists in matrix.
		if ( ! isset( $binding_costs[ $binding_type ] ) ) {
			return array(
				'exists'  => false,
				'message' => sprintf(
					/* translators: 1: binding type, 2: book size */
					__( 'نوع صحافی "%1$s" برای قطع %2$s در سیستم قیمت‌گذاری تنظیم نشده است. لطفا نوع صحافی دیگری را انتخاب کنید.', 'tabesh' ),
					$binding_type,
					$book_size
				),
			);
		}

		// For new structure with cover weights, check if the specific weight is configured.
		$binding_data = $binding_costs[ $binding_type ];
		if ( is_array( $binding_data ) && ! empty( $cover_weight ) ) {
			if ( ! isset( $binding_data[ $cover_weight ] ) ) {
				// Get available weights for helpful error message.
				$available_weights = array_keys( $binding_data );
				return array(
					'exists'  => false,
					'message' => sprintf(
						/* translators: 1: cover weight, 2: binding type, 3: book size, 4: available weights */
						__( 'گرماژ جلد %1$s برای صحافی %2$s در قطع %3$s تنظیم نشده است. گرماژهای موجود: %4$s', 'tabesh' ),
						$cover_weight,
						$binding_type,
						$book_size,
						implode( '، ', $available_weights )
					),
				);
			}
		}

		// All validations passed.
		return array( 'exists' => true );
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
	 * This method correctly handles three types of additional services:
	 * 1. Fixed: Applied once to the entire invoice (not per book)
	 * 2. Per Unit: Multiplied by quantity (number of books)
	 * 3. Page-Based: Based on total pages with minimum 1 unit guarantee
	 *
	 * @param array $pricing_matrix Pricing matrix
	 * @param array $extras Array of extra services
	 * @param int   $quantity Quantity
	 * @param int   $page_count_total Total page count per book
	 * @return array Array with 'fixed' and 'variable' costs separated
	 */
	private function calculate_extras_cost( $pricing_matrix, $extras, $quantity, $page_count_total ) {
		$extras_config = $pricing_matrix['extras_costs'] ?? array();
		$fixed_cost    = 0;  // Applied once (not multiplied by quantity)
		$variable_cost = 0;  // Already accounts for quantity

		foreach ( $extras as $extra ) {
			if ( isset( $extras_config[ $extra ] ) ) {
				$config     = $extras_config[ $extra ];
				$price      = floatval( $config['price'] ?? 0 );
				$type       = $config['type'] ?? 'fixed';
				$extra_cost = 0;

				switch ( $type ) {
					case 'fixed':
						// Fixed cost - applied once to the entire invoice
						$extra_cost  = $price;
						$fixed_cost += $extra_cost;
						break;

					case 'per_unit':
						// Per unit cost - price multiplied by quantity
						$extra_cost     = $price * $quantity;
						$variable_cost += $extra_cost;
						break;

					case 'page_based':
						// Page-based cost calculation
						// Step represents: price is per X pages
						// For example, step=4000 means price per 4000 pages
						// Default to 100 if not set (price per 100 pages)
						$step = intval( $config['step'] ?? 100 );
						if ( $step <= 0 ) {
							$step = 100; // Fallback to prevent division by zero
						}

						// Calculate total pages across all books
						$total_pages = $page_count_total * $quantity;

						// CRITICAL FIX: Ensure minimum 1 unit even if pages < step
						// Example: If step=4000 and total_pages=2000, should charge for 1 full unit
						// Example: If step=4000 and total_pages=4500, should charge for 2 units (ceil)
						$units = max( 1, ceil( $total_pages / $step ) );

						$extra_cost     = $price * $units;
						$variable_cost += $extra_cost;
						break;
				}

				// Debug logging if WP_DEBUG is enabled
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'Tabesh Pricing Engine V2: Extra service "%s" - Type: %s, Price: %s, Cost: %s',
							$extra,
							$type,
							$price,
							$extra_cost
						)
					);
				}
			}
		}

		return array(
			'fixed'    => $fixed_cost,
			'variable' => $variable_cost,
		);
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
	 * CRITICAL FIX: Normalizes book_size before lookup to ensure consistency.
	 * This allows retrieval to work even if the input has descriptions.
	 *
	 * @param string $book_size Book size identifier (may contain descriptions).
	 * @return array|null Pricing matrix or null if not found.
	 */
	public function get_pricing_matrix( $book_size ) {
		// CRITICAL FIX: Normalize the input book_size first
		// This ensures "رقعی (14×20)" matches matrices saved as "رقعی"
		$normalized_book_size = $this->normalize_book_size_key( $book_size );

		// Return cached matrix if available (using normalized key)
		if ( null !== self::$pricing_matrix_cache ) {
			$result = self::$pricing_matrix_cache[ $normalized_book_size ] ?? null;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				if ( $result ) {
					error_log(
						sprintf(
							'Tabesh Pricing Engine V2: Cache HIT for "%s" (normalized: "%s")',
							$book_size,
							$normalized_book_size
						)
					);
				} else {
					error_log(
						sprintf(
							'Tabesh Pricing Engine V2: Cache MISS for "%s" (normalized: "%s")',
							$book_size,
							$normalized_book_size
						)
					);
				}
			}

			return $result;
		}

		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Load all pricing matrices
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value FROM {$table_settings} WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		self::$pricing_matrix_cache = array();

		foreach ( $result as $row ) {
			// Extract book size from key: pricing_matrix_<base64> -> <original>.
			$key      = $row['setting_key'];
			$safe_key = str_replace( 'pricing_matrix_', '', $key );

			// Decode base64-encoded book size to get original Persian text.
			$size = $this->decode_book_size_key( $safe_key );

			// CRITICAL FIX: Normalize the decoded book_size before caching
			// This ensures cache keys are consistent with normalized product parameters
			$normalized_size = $this->normalize_book_size_key( $size );

			$value   = $row['setting_value'];
			$decoded = json_decode( $value, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				// Store in cache using NORMALIZED key
				self::$pricing_matrix_cache[ $normalized_size ] = $decoded;

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'Tabesh: Loaded pricing matrix - DB key: "%s", Decoded: "%s", Normalized (cache key): "%s"',
							$safe_key,
							$size,
							$normalized_size
						)
					);
				}
			}
		}

		// Debug logging if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh Pricing Engine V2: get_pricing_matrix loaded ' . count( self::$pricing_matrix_cache ) . ' matrices from database' );
		}

		// Return using normalized key
		return self::$pricing_matrix_cache[ $normalized_book_size ] ?? null;
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
			'license_costs'        => array(
				'دارم'  => 0,
				'ندارم' => 0,
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

			// With per-weight restrictions, we need to check each weight individually.
			// Build a list of weights that have at least one allowed print type.
			$available_weights    = array();
			$paper_allowed_prints = array(); // Track which print types are allowed across all weights

			foreach ( $weights as $weight => $print_types ) {
				// Check which print types are allowed for this specific weight.
				$forbidden_prints_for_weight = $restrictions['forbidden_print_types'][ $paper_type ][ $weight ] ?? array();

				$weight_has_bw    = ! in_array( 'bw', $forbidden_prints_for_weight, true ) && isset( $print_types['bw'] ) && $print_types['bw'] > 0;
				$weight_has_color = ! in_array( 'color', $forbidden_prints_for_weight, true ) && isset( $print_types['color'] ) && $print_types['color'] > 0;

				// Only include this weight if it has at least one allowed print type with a price.
				if ( $weight_has_bw || $weight_has_color ) {
					$available_weights[] = $weight;

					// Track which print types are available across all weights
					if ( $weight_has_bw && ! in_array( 'bw', $paper_allowed_prints, true ) ) {
						$paper_allowed_prints[] = 'bw';
					}
					if ( $weight_has_color && ! in_array( 'color', $paper_allowed_prints, true ) ) {
						$paper_allowed_prints[] = 'color';
					}
				}
			}

			// Only include papers that have at least one available weight.
			if ( ! empty( $available_weights ) ) {
				$available_papers[ $paper_type ] = array(
					'weights'        => $available_weights,
					'allowed_prints' => $paper_allowed_prints,
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

		// CRITICAL FIX: Normalize book_size key to remove descriptions/dimensions
		// This ensures consistency between product parameters (e.g., "رقعی") and
		// pricing matrices that may have been saved with descriptions (e.g., "رقعی (14×20)")
		$normalized_book_size = $this->normalize_book_size_key( $book_size );

		// Create safe database key by base64 encoding the NORMALIZED book_size
		// Do NOT use sanitize_key() as it corrupts Persian text
		$safe_key      = base64_encode( $normalized_book_size );
		$setting_key   = 'pricing_matrix_' . $safe_key;
		$setting_value = wp_json_encode( $matrix );

		// Log the key transformation for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh: Saving pricing matrix - Original: "%s", Normalized: "%s", Base64 key: "%s"',
					$book_size,
					$normalized_book_size,
					$safe_key
				)
			);
		}

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

			// Only run cleanup if this is the first save or if corruption is suspected.
			// Use a transient to track last cleanup time to avoid running on every save.
			$last_cleanup   = get_transient( 'tabesh_pricing_last_cleanup' );
			$should_cleanup = false === $last_cleanup; // First save after transient expired.

			if ( $should_cleanup ) {
				$removed = $this->cleanup_corrupted_matrices();
				if ( $removed > 0 ) {
					// Set transient to prevent cleanup for 1 hour if corruption was found.
					set_transient( 'tabesh_pricing_last_cleanup', time(), HOUR_IN_SECONDS );
				} else {
					// Set longer transient (24 hours) if no corruption found.
					set_transient( 'tabesh_pricing_last_cleanup', time(), DAY_IN_SECONDS );
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Clean up corrupted pricing matrices
	 *
	 * CRITICAL FIX: This method now only removes matrices with invalid key encoding,
	 * NOT matrices that don't match product parameters. The product parameters comparison
	 * was causing valid matrices to be deleted when book_sizes setting was empty or
	 * being configured for the first time.
	 *
	 * This is a private method called internally by save_pricing_matrix().
	 *
	 * @since 2.0.0
	 * @return int Number of corrupted entries removed
	 */
	private function cleanup_corrupted_matrices() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Get all pricing matrix entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_matrices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key FROM {$table_settings} WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		if ( empty( $all_matrices ) ) {
			// No matrices to check.
			return 0;
		}

		// Collect corrupted keys (invalid encoding only) for bulk delete.
		$corrupted_keys = array();

		foreach ( $all_matrices as $row ) {
			$setting_key = $row['setting_key'];
			$safe_key    = str_replace( 'pricing_matrix_', '', $setting_key );

			// Try to decode book size.
			$decoded = base64_decode( $safe_key, true );

			// CRITICAL FIX: Only mark as corrupted if decoding fails completely.
			// Do NOT compare against product parameters here as that causes valid
			// matrices to be deleted when book_sizes is empty or being reconfigured.
			// Note: Check for empty string specifically, not empty() which treats '0' as empty.
			if ( false === $decoded || '' === $decoded ) {
				// Invalid base64 encoding - this is truly corrupted.
				$corrupted_keys[] = $setting_key;

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'Tabesh: Found corrupted pricing matrix with invalid encoding - Key: "%s"',
							$setting_key
						)
					);
				}
			} elseif ( ! $this->is_valid_book_size_string( $decoded ) ) {
				// Decoded successfully but result is not a valid book size string.
				$corrupted_keys[] = $setting_key;

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'Tabesh: Found corrupted pricing matrix with invalid book_size format - Key: "%s", Decoded: "%s"',
							$setting_key,
							$decoded
						)
					);
				}
			}
		}

		// Perform bulk delete if corrupted entries found.
		$removed_count = 0;
		if ( ! empty( $corrupted_keys ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $corrupted_keys ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_settings} WHERE setting_key IN ($placeholders)",
					...$corrupted_keys
				)
			);

			if ( false !== $deleted ) {
				$removed_count = $deleted;
				self::clear_cache();

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'Tabesh: Cleanup complete - Removed %d corrupted pricing %s with invalid encoding',
							$removed_count,
							$removed_count === 1 ? 'matrix' : 'matrices'
						)
					);
				}
			}
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Cleanup complete - No corrupted matrices found' );
		}

		return $removed_count;
	}

	/**
	 * Get list of all configured book sizes
	 *
	 * CRITICAL FIX: Normalizes decoded book_size keys to ensure consistency
	 * with product parameters. This prevents mismatches when product params
	 * have clean names like "رقعی" but old matrices might be stored with
	 * descriptions like "رقعی (14×20)".
	 *
	 * @return array Array of normalized book size identifiers
	 */
	public function get_configured_book_sizes() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key FROM {$table_settings} WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		$sizes = array();
		foreach ( $results as $row ) {
			$setting_key = $row['setting_key'];
			$safe_key    = str_replace( 'pricing_matrix_', '', $setting_key );

			// Decode base64-encoded book size to get original Persian text.
			$size = $this->decode_book_size_key( $safe_key );

			// CRITICAL FIX: Normalize the decoded key to match product parameters
			// This ensures "رقعی (14×20)" from old matrices matches "رقعی" in product params
			$normalized_size = $this->normalize_book_size_key( $size );

			$sizes[] = $normalized_size;
		}

		// Debug logging if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh Pricing Engine V2: get_configured_book_sizes found ' . count( $sizes ) . ' book sizes (normalized): ' . implode( ', ', $sizes ) );
		}

		return $sizes;
	}

	/**
	 * Migrate old pricing matrices with descriptive keys to normalized keys
	 *
	 * This method detects pricing matrices saved with book size descriptions
	 * (e.g., "رقعی (14×20)") and merges them into normalized keys (e.g., "رقعی").
	 * This fixes the critical issue where matrices exist but aren't recognized
	 * because the product parameter has the clean name without parenthetical content.
	 *
	 * Example migration:
	 * - Old key: pricing_matrix_<base64("رقعی (14×20)")> → Merged into pricing_matrix_<base64("رقعی")>
	 * - Old key: pricing_matrix_<base64("A5 (148×210)")> → Merged into pricing_matrix_<base64("A5")>
	 *
	 * @return array Migration statistics with 'merged', 'deleted', 'activated' counts
	 */
	public function migrate_mismatched_book_size_keys() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		$stats = array(
			'merged'    => 0,
			'deleted'   => 0,
			'activated' => 0,
			'errors'    => array(),
		);

		// Get valid book sizes from product parameters (source of truth)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$valid_sizes_result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$valid_sizes = array();
		if ( $valid_sizes_result ) {
			$decoded = json_decode( $valid_sizes_result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				// CRITICAL FIX: Normalize valid_sizes from product parameters
				// This ensures comparison works even if product params have descriptions
				foreach ( $decoded as $size ) {
					$valid_sizes[] = $this->normalize_book_size_key( $size );
				}
			}
		}

		if ( empty( $valid_sizes ) ) {
			$stats['errors'][] = __( 'هیچ قطع کتابی در تنظیمات محصول تعریف نشده', 'tabesh' );
			return $stats;
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh Migration: Valid sizes (normalized): %s',
					implode( ', ', $valid_sizes )
				)
			);
		}

		// Get all pricing matrices with their data
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_matrices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value FROM {$table_settings} WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		if ( empty( $all_matrices ) ) {
			return $stats;
		}

		// Group matrices by normalized book size
		$grouped_by_normalized = array();
		$keys_to_delete        = array();

		foreach ( $all_matrices as $row ) {
			$setting_key = $row['setting_key'];
			$safe_key    = str_replace( 'pricing_matrix_', '', $setting_key );

			// Decode book size
			$book_size = $this->decode_book_size_key( $safe_key );

			// Normalize the book size (remove parenthetical content)
			$normalized = $this->normalize_book_size_key( $book_size );

			// Check if this normalized size is in valid sizes
			if ( ! in_array( $normalized, $valid_sizes, true ) ) {
				// This is an orphaned matrix - mark for deletion
				$keys_to_delete[] = $setting_key;
				continue;
			}

			// Add to grouped array
			if ( ! isset( $grouped_by_normalized[ $normalized ] ) ) {
				$grouped_by_normalized[ $normalized ] = array();
			}

			$grouped_by_normalized[ $normalized ][] = array(
				'key'           => $setting_key,
				'original_size' => $book_size,
				'data'          => $row['setting_value'],
			);
		}

		// Process each group - merge if multiple matrices for same normalized size
		foreach ( $grouped_by_normalized as $normalized_size => $matrices ) {
			if ( count( $matrices ) > 1 ) {
				// Multiple matrices for same normalized size - need to merge
				$normalized_key     = 'pricing_matrix_' . base64_encode( $normalized_size );
				$has_normalized_key = false;
				$merged_data        = null;

				// Check if normalized key already exists
				foreach ( $matrices as $matrix_info ) {
					if ( $matrix_info['key'] === $normalized_key ) {
						$has_normalized_key = true;
						$merged_data        = json_decode( $matrix_info['data'], true );
						break;
					}
				}

				// If no normalized key exists, use the first matrix as base
				if ( ! $has_normalized_key && ! empty( $matrices ) ) {
					$merged_data = json_decode( $matrices[0]['data'], true );
				}

				// Merge data from all matrices
				foreach ( $matrices as $matrix_info ) {
					if ( $matrix_info['key'] !== $normalized_key ) {
						$matrix_data = json_decode( $matrix_info['data'], true );

						if ( is_array( $matrix_data ) && is_array( $merged_data ) ) {
							// Merge page_costs - preserve all unique combinations
							// array_merge will overwrite duplicate keys, which is acceptable here
							// as we want the most recent pricing data for each paper/weight/print combination
							if ( isset( $matrix_data['page_costs'] ) && is_array( $matrix_data['page_costs'] ) ) {
								if ( ! isset( $merged_data['page_costs'] ) ) {
									$merged_data['page_costs'] = array();
								}
								// Use array_replace_recursive to preserve nested structure and prefer newer data
								$merged_data['page_costs'] = array_replace_recursive( $merged_data['page_costs'], $matrix_data['page_costs'] );
							}

							// Merge binding_costs - preserve all unique combinations
							if ( isset( $matrix_data['binding_costs'] ) && is_array( $matrix_data['binding_costs'] ) ) {
								if ( ! isset( $merged_data['binding_costs'] ) ) {
									$merged_data['binding_costs'] = array();
								}
								// Use array_replace_recursive to preserve nested structure and prefer newer data
								$merged_data['binding_costs'] = array_replace_recursive( $merged_data['binding_costs'], $matrix_data['binding_costs'] );
							}

							// Merge extras_costs - preserve all unique extras
							if ( isset( $matrix_data['extras_costs'] ) && is_array( $matrix_data['extras_costs'] ) ) {
								if ( ! isset( $merged_data['extras_costs'] ) ) {
									$merged_data['extras_costs'] = array();
								}
								$merged_data['extras_costs'] = array_merge( $merged_data['extras_costs'], $matrix_data['extras_costs'] );
							}
						}

						// Mark old key for deletion
						$keys_to_delete[] = $matrix_info['key'];

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log(
								sprintf(
									'Tabesh Migration: Merging "%s" into normalized key for "%s"',
									$matrix_info['original_size'],
									$normalized_size
								)
							);
						}
					}
				}

				// Save merged data to normalized key
				if ( $merged_data ) {
					$this->save_pricing_matrix( $normalized_size, $merged_data );
					++$stats['merged'];
					++$stats['activated'];
				}
			}
		}

		// Delete old keys
		if ( ! empty( $keys_to_delete ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $keys_to_delete ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_settings} WHERE setting_key IN ($placeholders)",
					...$keys_to_delete
				)
			);

			if ( false !== $deleted ) {
				$stats['deleted'] = $deleted;
				self::clear_cache();
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh Migration Complete: %d merged, %d deleted, %d activated',
					$stats['merged'],
					$stats['deleted'],
					$stats['activated']
				)
			);
		}

		return $stats;
	}
}
