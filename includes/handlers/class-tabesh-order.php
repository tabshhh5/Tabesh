<?php
/**
 * Order Management Class
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tabesh_Order {

	/**
	 * Cache for pricing configuration to avoid redundant database queries
	 *
	 * @var array|null
	 */
	private static $pricing_config_cache = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialization
	}

	/**
	 * Clear pricing configuration cache
	 * Should be called when pricing settings are updated
	 *
	 * @return void
	 */
	public static function clear_pricing_cache() {
		self::$pricing_config_cache = null;
	}

	/**
	 * Sanitize extras array
	 *
	 * Sanitizes each element in the extras array and filters out empty values.
	 *
	 * @param mixed $extras_raw Raw extras data (should be array)
	 * @return array Sanitized extras array
	 */
	private function sanitize_extras_array( $extras_raw ) {
		$extras = array();

		if ( is_array( $extras_raw ) ) {
			foreach ( $extras_raw as $extra ) {
				$sanitized_extra = sanitize_text_field( $extra );
				if ( ! empty( $sanitized_extra ) ) {
					$extras[] = $sanitized_extra;
				}
			}
		}

		return $extras;
	}

	/**
	 * Calculate book printing price
	 *
	 * Implements a comprehensive pricing algorithm with:
	 * - Book size multipliers (قطع کتاب) [DEPRECATED in V2]
	 * - Paper type base costs [DEPRECATED in V2]
	 * - Separate B&W and color page calculations
	 * - Cover and binding costs
	 * - Additional options (UV, embossing, etc.)
	 * - Quantity multipliers
	 * - Profit margin
	 *
	 * Formula: FinalPrice = (((PaperCost + PrintCost) * PageCount) + CoverCost + BindingCost + OptionsCost) * Quantity * (1 + ProfitMargin)
	 *
	 * @param array $params Order parameters
	 * @return array Price breakdown
	 */
	public function calculate_price( $params ) {
		// Check if new pricing engine V2 is enabled using the static helper method
		// This is the single source of truth for pricing engine status
		if ( Tabesh_Pricing_Engine::is_v2_active() ) {
			// Use new matrix-based pricing engine
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh Order: Using Pricing Engine V2 (Matrix-based)' );
			}

			$pricing_engine = new Tabesh_Pricing_Engine();
			return $pricing_engine->calculate_price( $params );
		}

		// Fall back to legacy pricing engine (V1)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh Order: Using Legacy Pricing Engine V1' );
		}

		// Log incoming parameters for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: calculate_price called with params: ' . print_r( $params, true ) );
		}

		// Sanitize and extract input parameters
		$book_size          = sanitize_text_field( $params['book_size'] ?? '' );
		$paper_type         = sanitize_text_field( $params['paper_type'] ?? '' );
		$paper_weight       = sanitize_text_field( $params['paper_weight'] ?? '' );
		$print_type         = sanitize_text_field( $params['print_type'] ?? '' );
		$page_count_color   = intval( $params['page_count_color'] ?? 0 );
		$page_count_bw      = intval( $params['page_count_bw'] ?? 0 );
		$quantity           = intval( $params['quantity'] ?? 0 );
		$binding_type       = sanitize_text_field( $params['binding_type'] ?? '' );
		$license_type       = sanitize_text_field( $params['license_type'] ?? '' );
		$cover_type         = sanitize_text_field( $params['cover_type'] ?? 'soft' ); // soft or hard
		$cover_paper_weight = sanitize_text_field( $params['cover_paper_weight'] ?? '250' );
		$lamination_type    = sanitize_text_field( $params['lamination_type'] ?? 'براق' );

		// Sanitize extras array
		$extras = $this->sanitize_extras_array( $params['extras'] ?? array() );

		// Log extras for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Extras received: ' . print_r( $extras, true ) );
			error_log( 'Tabesh: Extras is_array: ' . ( is_array( $extras ) ? 'yes' : 'no' ) );
			error_log( 'Tabesh: Extras count: ' . ( is_array( $extras ) ? count( $extras ) : 0 ) );
		}

		// Round pages to even number
		$page_count_total = $page_count_color + $page_count_bw;
		if ( $page_count_total % 2 !== 0 ) {
			++$page_count_total;
		}

		// Get pricing configuration
		// In future versions, these will be loaded from database settings table
		// or configured via admin panel GUI
		$pricing_config = $this->get_pricing_config();

		// Step 1: Book Size Multiplier (قطع کتاب)
		// Determines paper usage factor and print cost multiplier
		$size_multiplier = $pricing_config['book_sizes'][ $book_size ] ?? 1.0;

		// Step 2: Paper Type Base Cost (نوع کاغذ و گرماژ)
		// Each paper type + weight combination has a specific cost per page
		// New dynamic pricing: pricing_paper_weights[paper_type][weight]
		$paper_base_cost = 0;
		if ( isset( $pricing_config['paper_weights'][ $paper_type ][ $paper_weight ] ) ) {
			$paper_base_cost = $pricing_config['paper_weights'][ $paper_type ][ $paper_weight ];
		} else {
			// Fallback: check old pricing_paper_types structure (backward compatibility)
			$paper_base_cost = $pricing_config['paper_types'][ $paper_type ] ?? 250;

			// Only log once per unique combination to avoid log spam
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				static $logged_missing = array();
				$lookup_key            = $paper_type . '_' . $paper_weight;
				if ( ! isset( $logged_missing[ $lookup_key ] ) ) {
					error_log(
						sprintf(
							'Tabesh WARNING: Weight-based pricing not found for paper "%s" weight "%s", using fallback cost: %s',
							$paper_type,
							$paper_weight,
							$paper_base_cost
						)
					);
					$logged_missing[ $lookup_key ] = true;
				}
			}
		}

		// Step 3: Print Cost per Page (هزینه چاپ هر صفحه)
		// Different costs for B&W vs Color printing
		$print_cost_bw    = $pricing_config['print_costs']['bw'] ?? 200;
		$print_cost_color = $pricing_config['print_costs']['color'] ?? 800;

		// Step 4: Calculate Per-Page Cost
		// PerPageCost = PaperCost + PrintCost
		$per_page_cost_bw    = ( $paper_base_cost + $print_cost_bw ) * $size_multiplier;
		$per_page_cost_color = ( $paper_base_cost + $print_cost_color ) * $size_multiplier;

		// Step 5: Calculate Total Pages Cost
		// TotalPagesCost = (PerPageCost_BW * BW_PageCount) + (PerPageCost_Color * Color_PageCount)
		$pages_cost_bw    = $per_page_cost_bw * $page_count_bw;
		$pages_cost_color = $per_page_cost_color * $page_count_color;
		$total_pages_cost = $pages_cost_bw + $pages_cost_color;

		// Step 6: Cover Cost (جلد)
		// Different base costs for soft vs hard cover
		$cover_base = $pricing_config['cover_types'][ $cover_type ] ?? 8000;

		// Add lamination cost
		$lamination_cost = $pricing_config['lamination_costs'][ $lamination_type ] ?? 0;

		$cover_cost = $cover_base + $lamination_cost;

		// Step 7: Binding Cost (صحافی)
		// Cost depends on binding type AND book size (matrix-based pricing)
		// New matrix format: pricing_binding_matrix[binding_type][book_size]
		$binding_cost = 0;
		if ( isset( $pricing_config['binding_matrix'][ $binding_type ][ $book_size ] ) ) {
			$binding_cost = $pricing_config['binding_matrix'][ $binding_type ][ $book_size ];
		} else {
			// Fallback: check old pricing_binding_costs structure (backward compatibility)
			$binding_cost = $pricing_config['binding_costs'][ $binding_type ] ?? 0;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				static $logged_binding_missing = array();
				$binding_lookup_key            = $binding_type . '_' . $book_size;
				if ( ! isset( $logged_binding_missing[ $binding_lookup_key ] ) ) {
					error_log(
						sprintf(
							'Tabesh WARNING: Matrix-based binding pricing not found for binding "%s" size "%s", using fallback cost: %s',
							$binding_type,
							$book_size,
							$binding_cost
						)
					);
					$logged_binding_missing[ $binding_lookup_key ] = true;
				}
			}
		}

		// Step 8: Additional Options Cost (آپشنها - با منطق سه‌گانه)
		// Three calculation types with different formulas:
		// 1. Fixed: One-time cost regardless of volume/pages (added once to order)
		// 2. Per Unit (Per Volume): Cost per book/volume - Formula: Price_per_unit × Quantity
		// 3. Page-Based (Per Page): Cost based on total pages - Formula: Price_per_page × TotalPages
		//
		// IMPORTANT: To prevent double multiplication, we separate:
		// - fixed_options_cost: Added to per-book cost (will be multiplied by quantity later)
		// - variable_options_cost: Already accounts for quantity/pages (added after quantity multiplication)
		$fixed_options_cost    = 0; // Options that are per-book costs
		$variable_options_cost = 0; // Options that already account for volume/pages
		$options_breakdown     = array(); // Track individual option costs for transparency

		if ( is_array( $extras ) && ! empty( $extras ) ) {
			// Ensure options_config exists
			if ( ! isset( $pricing_config['options_config'] ) || ! is_array( $pricing_config['options_config'] ) ) {
				// Fallback to old options_costs if new config doesn't exist
				$pricing_config['options_config'] = array();
				if ( isset( $pricing_config['options_costs'] ) && is_array( $pricing_config['options_costs'] ) ) {
					// Convert old format to new format (all as 'fixed' type)
					foreach ( $pricing_config['options_costs'] as $opt_name => $opt_price ) {
						$pricing_config['options_config'][ $opt_name ] = array(
							'price' => $opt_price,
							'type'  => 'fixed',
							'step'  => 0,
						);
					}
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Processing ' . count( $extras ) . ' extras with three-tier logic' );
			}

			foreach ( $extras as $extra ) {
				// Defensive check - ensure extra is a string and not empty
				if ( empty( $extra ) || ! is_string( $extra ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Tabesh: Skipping invalid extra: ' . print_r( $extra, true ) );
					}
					continue;
				}

				// Get option configuration
				$option_config = $pricing_config['options_config'][ $extra ] ?? null;

				if ( ! $option_config ) {
					// Try fallback to old format
					if ( isset( $pricing_config['options_costs'][ $extra ] ) ) {
						$option_config = array(
							'price' => $pricing_config['options_costs'][ $extra ],
							'type'  => 'fixed',
							'step'  => 0,
						);
					} else {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( sprintf( 'Tabesh WARNING: Option "%s" not found in pricing config', $extra ) );
						}
						continue;
					}
				}

				$option_price = floatval( $option_config['price'] ?? 0 );
				$option_type  = $option_config['type'] ?? 'fixed';
				$option_step  = intval( $option_config['step'] ?? 16000 );
				$extra_cost   = 0;

				// Calculate based on option type - using correct formulas to prevent double multiplication
				switch ( $option_type ) {
					case 'fixed':
						// Fixed cost - one-time cost added to per-book production cost
						// Will be multiplied by quantity when calculating subtotal
						$extra_cost          = $option_price;
						$fixed_options_cost += $extra_cost;
						break;

					case 'per_unit':
						// Per unit (per volume) cost - Formula: Price_per_unit × Quantity
						// Already accounts for volume, so added to variable costs (NOT multiplied again)
						$extra_cost             = $option_price * $quantity;
						$variable_options_cost += $extra_cost;
						break;

					case 'page_based':
						// Page-based (per page) cost - Formula: Price_per_page × TotalPages
						// Calculate total pages across all volumes and apply step-based pricing
						// CRITICAL FIX: Ensure minimum 1 unit even if pages < step
						if ( $option_step > 0 ) {
							$total_pages = $page_count_total * $quantity;
							$units       = max( 1, ceil( $total_pages / $option_step ) );
							$extra_cost  = $option_price * $units;
						} else {
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( sprintf( 'Tabesh WARNING: Page-based option "%s" has invalid step: %d', $extra, $option_step ) );
							}
							$extra_cost = $option_price; // Fallback to fixed
						}
						// Already accounts for total pages, so added to variable costs (NOT multiplied again)
						$variable_options_cost += $extra_cost;
						break;

					default:
						// Unknown type, treat as fixed
						$extra_cost          = $option_price;
						$fixed_options_cost += $extra_cost;
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( sprintf( 'Tabesh WARNING: Unknown option type "%s" for "%s", treating as fixed', $option_type, $extra ) );
						}
						break;
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'Tabesh: Option "%s" - Type: %s, Base Price: %s, Calculated Cost: %s',
							$extra,
							$option_type,
							$option_price,
							$extra_cost
						)
					);
				}

				$options_breakdown[ $extra ] = array(
					'type'  => $option_type,
					'price' => $option_price,
					'cost'  => $extra_cost,
				);
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Fixed options cost (per-book): ' . $fixed_options_cost );
				error_log( 'Tabesh: Variable options cost (total): ' . $variable_options_cost );
				error_log( 'Tabesh: Options breakdown: ' . print_r( $options_breakdown, true ) );
			}
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( ! is_array( $extras ) ) {
				error_log( 'Tabesh: Extras is not an array, skipping options cost calculation' );
				error_log( 'Tabesh: Extras type: ' . gettype( $extras ) );
			} else {
				error_log( 'Tabesh: Extras is empty array, no additional options selected' );
			}
		}

		// Step 9: Calculate Production Cost per Book
		// ProductionCost = PagesCost + CoverCost + BindingCost + FixedOptionsCost
		// Note: Only fixed-type options are included here, as they apply per book
		// Variable options (per_unit and page_based) are added after quantity multiplication
		$production_cost_per_book = $total_pages_cost + $cover_cost + $binding_cost + $fixed_options_cost;

		// Step 10: Apply Quantity Multiplier (تیراژ)
		// Multiply per-book cost by quantity, then add variable options cost
		$subtotal_before_variable_options = $production_cost_per_book * $quantity;
		$subtotal                         = $subtotal_before_variable_options + $variable_options_cost;

		// Step 11: Apply Quantity Discounts (if any)
		// Get configurable discount rules from settings
		// Discount rules are stored as quantity => discount_percent pairs
		// Example: array(100 => 10, 50 => 5) means 10% off for 100+, 5% off for 50+
		$discount_rules = $pricing_config['quantity_discounts'] ?? array();

		$discount_percent = 0;
		if ( is_array( $discount_rules ) && ! empty( $discount_rules ) ) {
			// Sort discount rules by quantity in descending order to apply highest discount first
			krsort( $discount_rules, SORT_NUMERIC );

			foreach ( $discount_rules as $min_qty => $discount ) {
				if ( $quantity >= intval( $min_qty ) ) {
					$discount_percent = floatval( $discount );
					break; // Apply the first matching discount (highest quantity threshold)
				}
			}
		}

		$discount_amount = ( $subtotal * $discount_percent ) / 100;

		// Step 12: Calculate Total after Discount
		$total_after_discount = $subtotal - $discount_amount;

		// Step 13: Apply Profit Margin (حاشیه سود)
		// Add markup or profit margin (configurable in future admin panel)
		$profit_margin = $pricing_config['profit_margin'] ?? 0.0; // 0.0 = 0%, 0.15 = 15%
		$profit_amount = $total_after_discount * $profit_margin;

		// Step 14: Final Invoice Price (فاکتور نهایی)
		// FinalPrice = TotalCost * (1 + ProfitMargin)
		$total_price = $total_after_discount + $profit_amount;

		// Calculate total options cost for logging and display
		$total_options_cost = $fixed_options_cost + $variable_options_cost;

		// Log final calculation result
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Calculation complete - Total price: ' . $total_price );
			error_log( 'Tabesh: Price breakdown - Pages: ' . $total_pages_cost . ', Cover: ' . $cover_cost . ', Binding: ' . $binding_cost . ', Fixed Options: ' . $fixed_options_cost . ', Variable Options: ' . $variable_options_cost );
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
			// Detailed breakdown for transparency
			'breakdown'             => array(
				'book_size'             => $book_size,
				'size_multiplier'       => $size_multiplier,
				'pages_cost_bw'         => $pages_cost_bw,
				'pages_cost_color'      => $pages_cost_color,
				'total_pages_cost'      => $total_pages_cost,
				'cover_cost'            => $cover_cost,
				'binding_cost'          => $binding_cost,
				'options_cost'          => $total_options_cost, // Total of all options for backward compatibility
				'fixed_options_cost'    => $fixed_options_cost,     // Fixed options (per-book)
				'variable_options_cost' => $variable_options_cost,  // Variable options (per-unit + page-based)
				'options_breakdown'     => $options_breakdown,      // Individual option costs
				'per_page_cost_bw'      => $per_page_cost_bw,
				'per_page_cost_color'   => $per_page_cost_color,
			),
		);
	}

	/**
	 * Get pricing configuration
	 *
	 * Returns the pricing structure for all book printing components.
	 * Loads configuration from the database settings table.
	 * Falls back to default values if not set.
	 * Uses static cache to avoid redundant database queries.
	 *
	 * @return array Pricing configuration
	 */
	private function get_pricing_config() {
		// Return cached config if available
		if ( self::$pricing_config_cache !== null ) {
			return self::$pricing_config_cache;
		}

		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Fetch all pricing settings in a single query for performance
		$pricing_keys = array(
			'pricing_book_sizes',
			'pricing_paper_types',      // Old format (backward compatibility)
			'pricing_paper_weights',    // New weight-based format
			'pricing_print_costs',
			'pricing_cover_types',
			'pricing_lamination_costs',
			'pricing_binding_costs',    // Old format (backward compatibility)
			'pricing_binding_matrix',   // New matrix format (binding_type + book_size)
			'pricing_options_costs',    // Old format (backward compatibility)
			'pricing_options_config',   // New three-tier format
			'pricing_profit_margin',
			'pricing_quantity_discounts',
		);

		$placeholders = implode( ',', array_fill( 0, count( $pricing_keys ), '%s' ) );
		$query        = $wpdb->prepare(
			"SELECT setting_key, setting_value FROM $table_settings WHERE setting_key IN ($placeholders)",
			...$pricing_keys
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Build settings array from results
		$settings = array();
		foreach ( $results as $row ) {
			$value                           = $row['setting_value'];
			$decoded                         = json_decode( $value, true );
			$settings[ $row['setting_key'] ] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $value;
		}

		// Default values as fallback
		$defaults = array(
			'book_sizes'         => array(
				'A5'    => 1.0,
				'A4'    => 1.5,
				'B5'    => 1.2,
				'رقعی'  => 1.1,
				'وزیری' => 1.3,
				'خشتی'  => 1.4,
			),
			'paper_types'        => array(
				'glossy' => 250,
				'matte'  => 200,
				'cream'  => 180,
				'تحریر'  => 200,
				'بالک'   => 250,
			),
			'paper_weights'      => array(
				// New weight-based pricing structure (paper_type => [weight => price])
				'تحریر' => array(
					'60' => 150,
					'70' => 180,
					'80' => 200,
				),
				'بالک'  => array(
					'60'  => 200,
					'70'  => 230,
					'80'  => 250,
					'100' => 300,
				),
			),
			'print_costs'        => array(
				'bw'    => 200,
				'color' => 800,
			),
			'cover_types'        => array(
				'soft' => 8000,
				'hard' => 15000,
			),
			'lamination_costs'   => array(
				'براق'       => 2000,
				'مات'        => 2500,
				'بدون سلفون' => 0,
			),
			'binding_costs'      => array(
				'شومیز'    => 3000,
				'جلد سخت'  => 8000,
				'گالینگور' => 6000,
				'سیمی'     => 2000,
			),
			'binding_matrix'     => array(
				// New matrix format: binding_type => [book_size => price]
				'شومیز'   => array(
					'A5'    => 3000,
					'A4'    => 4500,
					'رقعی'  => 3500,
					'وزیری' => 4000,
				),
				'جلد سخت' => array(
					'A5'    => 8000,
					'A4'    => 12000,
					'رقعی'  => 9000,
					'وزیری' => 10000,
				),
			),
			'options_costs'      => array(
				'لب گرد'            => 1000,
				'خط تا'             => 500,
				'شیرینک'            => 1500,
				'سوراخ'             => 300,
				'شماره گذاری'       => 800,
				'uv_coating'        => 3000,
				'embossing'         => 5000,
				'special_packaging' => 2000,
			),
			'options_config'     => array(
				// New three-tier format: option_name => [price, type, step]
				'لب گرد'          => array(
					'price' => 1000,
					'type'  => 'per_unit',
					'step'  => 0,
				),
				'خط تا'           => array(
					'price' => 500,
					'type'  => 'per_unit',
					'step'  => 0,
				),
				'شیرینک'          => array(
					'price' => 1500,
					'type'  => 'per_unit',
					'step'  => 0,
				),
				'سوراخ'           => array(
					'price' => 300,
					'type'  => 'per_unit',
					'step'  => 0,
				),
				'شماره گذاری'     => array(
					'price' => 800,
					'type'  => 'per_unit',
					'step'  => 0,
				),
				'بسته‌بندی کارتن' => array(
					'price' => 50000,
					'type'  => 'page_based',
					'step'  => 16000,
				),
			),
			'profit_margin'      => 0.0,
			'quantity_discounts' => array(
				100 => 10,  // 10% discount for 100+ quantity
				50  => 5,    // 5% discount for 50+ quantity
			),
		);

		// Build configuration into a local variable first to ensure atomicity
		// This prevents partial cache corruption if array construction is interrupted
		$config = array(
			'book_sizes'         => $settings['pricing_book_sizes'] ?? $defaults['book_sizes'],
			'paper_types'        => $settings['pricing_paper_types'] ?? $defaults['paper_types'],
			'paper_weights'      => $settings['pricing_paper_weights'] ?? $defaults['paper_weights'],
			'print_costs'        => $settings['pricing_print_costs'] ?? $defaults['print_costs'],
			'cover_types'        => $settings['pricing_cover_types'] ?? $defaults['cover_types'],
			'lamination_costs'   => $settings['pricing_lamination_costs'] ?? $defaults['lamination_costs'],
			'binding_costs'      => $settings['pricing_binding_costs'] ?? $defaults['binding_costs'],
			'binding_matrix'     => $settings['pricing_binding_matrix'] ?? $defaults['binding_matrix'],
			'options_costs'      => $settings['pricing_options_costs'] ?? $defaults['options_costs'],
			'options_config'     => $settings['pricing_options_config'] ?? $defaults['options_config'],
			'profit_margin'      => floatval( $settings['pricing_profit_margin'] ?? $defaults['profit_margin'] ),
			'quantity_discounts' => $settings['pricing_quantity_discounts'] ?? $defaults['quantity_discounts'],
		);

		// Cache the successfully built configuration
		self::$pricing_config_cache = $config;

		return $config;
	}

	/**
	 * REST API endpoint for price calculation
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function calculate_price_rest( $request ) {
		$params = $request->get_json_params();

		// Log the request if debug is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh REST: calculate_price_rest called' );
			error_log( 'Tabesh REST: Request params keys: ' . implode( ', ', array_keys( $params ?: array() ) ) );
		}

		// Validate params - must be array with required fields
		if ( ! is_array( $params ) || empty( $params ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh REST ERROR: Invalid params - not an array or empty' );
			}
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'داده‌های نامعتبر', 'tabesh' ),
				),
				400
			);
		}

		// Check for required fields
		$required_fields = array( 'book_size', 'paper_type', 'quantity', 'binding_type' );
		$missing_fields  = array();
		foreach ( $required_fields as $field ) {
			if ( empty( $params[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}

		if ( ! empty( $missing_fields ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh REST ERROR: Missing required fields: ' . implode( ', ', $missing_fields ) );
			}
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'لطفا تمام فیلدهای الزامی را پر کنید', 'tabesh' ),
				),
				400
			);
		}

		try {
			$result = $this->calculate_price( $params );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh REST: Calculation successful' );
				if ( isset( $result['total_price'] ) ) {
					error_log( 'Tabesh REST: Total price: ' . $result['total_price'] );
				}
			}

			// Enhanced response structure for V2 with dependency engine support.
			$response = array(
				'success' => true,
				'data'    => $result,
			);

			// If calculation was successful (no error), add allowed next options.
			if ( ! isset( $result['error'] ) || ! $result['error'] ) {
				// Check if V2 is active and constraint manager exists.
				if ( Tabesh_Pricing_Engine::is_v2_active() &&
					! empty( $params['book_size'] ) &&
					class_exists( 'Tabesh_Constraint_Manager' ) ) {
					$constraint_manager = new Tabesh_Constraint_Manager();

					// Build current selection from params for next step filtering.
					$current_selection = array(
						'book_size'    => $params['book_size'] ?? '',
						'paper_type'   => $params['paper_type'] ?? '',
						'paper_weight' => $params['paper_weight'] ?? '',
						'binding_type' => $params['binding_type'] ?? '',
					);

					$allowed_options = $constraint_manager->get_allowed_options(
						$current_selection,
						$params['book_size']
					);

					if ( ! isset( $allowed_options['error'] ) ) {
						$response['allowed_next_options'] = $allowed_options;
					}
				}

				$response['status']  = 'valid';
				$response['message'] = __( 'محاسبه با موفقیت انجام شد', 'tabesh' );
			} else {
				// Calculation returned an error.
				$response['status']  = 'invalid';
				$response['message'] = $result['message'] ?? __( 'خطا در محاسبه', 'tabesh' );
			}

			return new WP_REST_Response( $response, 200 );
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// Only log detailed error info in debug mode.
				error_log( 'Tabesh REST ERROR: Exception in calculate_price' );
				error_log( 'Tabesh REST ERROR: ' . $e->getMessage() );
				error_log( 'Tabesh REST ERROR: File: ' . $e->getFile() . ' Line: ' . $e->getLine() );
			}

			// Return generic error message in production, detailed in debug mode.
			$error_message = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				? $e->getMessage()
				: __( 'خطا در محاسبه قیمت. لطفا دوباره تلاش کنید.', 'tabesh' );

			return new WP_REST_Response(
				array(
					'success' => false,
					'status'  => 'error',
					'message' => $error_message,
				),
				400
			);
		}
	}

	/**
	 * Create order with database fallback
	 *
	 * Attempts to create order in custom table. If table or column is missing,
	 * falls back to WordPress post system.
	 *
	 * @param array $data Sanitized order data
	 * @return int|WP_Error Order ID (database or post ID) or error
	 */
	public function create_order( $data ) {
		global $wpdb;

		$table_orders = $wpdb->prefix . 'tabesh_orders';

		// Debug: Log order creation attempt
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: create_order called' );
			error_log( 'Tabesh: Order data: ' . print_r( $data, true ) );
		}

		// Check if table exists
		$table_exists = Tabesh_Install::table_exists( $table_orders );

		if ( ! $table_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Orders table does not exist, using post fallback' );
			}
			return $this->create_order_as_post( $data );
		}

		// Check if book_title column exists
		$column_exists = Tabesh_Install::column_exists( $table_orders, 'book_title' );

		if ( ! $column_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: book_title column missing, using post fallback' );
			}
			return $this->create_order_as_post( $data );
		}

		// Use prepared statement with proper format specification
		// Build formats array to match the exact order of fields in $data
		$formats = array(
			'%d', // user_id
			'%s', // order_number
			'%s', // book_title
			'%s', // book_size
			'%s', // paper_type
			'%s', // paper_weight
			'%s', // print_type
			'%d', // page_count_color
			'%d', // page_count_bw
			'%d', // page_count_total
			'%d', // quantity
			'%s', // binding_type
			'%s', // license_type
			'%s', // cover_paper_type
			'%s', // cover_paper_weight
			'%s', // lamination_type
			'%s', // extras
			'%s', // files
			'%f', // total_price
			'%s', // status
			'%s',  // notes
		);

		// Add serial_number at the END (matching its position in $data)
		if ( Tabesh_Install::column_exists( $table_orders, 'serial_number' ) ) {
			// Get the maximum serial number
			// The UNIQUE constraint on serial_number will prevent duplicates
			// In the rare case of concurrent inserts, MySQL will reject duplicates
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$max_serial  = $wpdb->get_var( "SELECT MAX(serial_number) FROM `{$table_orders}`" );
			$next_serial = $max_serial ? ( $max_serial + 1 ) : 1;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Assigning serial number: ' . $next_serial );
			}

			// Add serial_number to the END of $data to match format position
			$data['serial_number'] = $next_serial;
			// Add format for serial_number at the END
			$formats[] = '%d'; // serial_number
		}

		// Debug: Log before insert
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Attempting to insert order into ' . $table_orders );
		}

		$result = $wpdb->insert( $table_orders, $data, $formats );

		if ( $result === false ) {
			// Comprehensive error logging
			error_log( 'Tabesh: Database insert failed' );
			error_log( 'Tabesh: Error message: ' . $wpdb->last_error );
			error_log( 'Tabesh: Last query: ' . $wpdb->last_query );
			error_log( 'Tabesh: Table: ' . $table_orders );

			// Check if error is due to missing column
			if ( strpos( $wpdb->last_error, 'book_title' ) !== false ) {
				error_log( 'Tabesh: book_title column error detected, using post fallback' );
				return $this->create_order_as_post( $data );
			}

			return new WP_Error( 'db_error', __( 'خطا در ثبت سفارش', 'tabesh' ) . ': ' . $wpdb->last_error );
		}

		$insert_id = $wpdb->insert_id;

		// Debug: Confirm successful insert
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Order successfully inserted with ID: ' . $insert_id );
		}

		return $insert_id;
	}

	/**
	 * Create order as WordPress post (fallback)
	 *
	 * Used when custom table or required columns are missing.
	 *
	 * @param array $data Order data
	 * @return int|WP_Error Post ID or error
	 */
	private function create_order_as_post( $data ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'tabesh_order',
				'post_status' => 'publish',
				'post_title'  => $data['book_title'] . ' - ' . $data['order_number'],
				'post_author' => $data['user_id'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Store all order data as post meta
		foreach ( $data as $key => $value ) {
			update_post_meta( $post_id, '_tabesh_' . $key, $value );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Order created as post ID: ' . $post_id );
		}

		return $post_id;
	}

	/**
	 * Submit order
	 *
	 * @param array $params Order parameters
	 * @return int|WP_Error Order ID or error
	 */
	public function submit_order( $params ) {
		global $wpdb;

		// Debug: Log submission attempt
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: submit_order called' );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			error_log( 'Tabesh: Order submission failed - user not logged in' );
			return new WP_Error( 'not_logged_in', __( 'شما باید وارد حساب کاربری خود شوید.', 'tabesh' ) );
		}

		// Validate book_title (required field)
		if ( empty( $params['book_title'] ) || trim( $params['book_title'] ) === '' ) {
			error_log( 'Tabesh: Order submission failed - book_title missing' );
			return new WP_Error( 'missing_book_title', __( 'عنوان کتاب الزامی است.', 'tabesh' ) );
		}

		// Generate order number
		$order_number = 'TB-' . date( 'Ymd' ) . '-' . str_pad( rand( 1, 9999 ), 4, '0', STR_PAD_LEFT );

		// Debug: Log order number generated
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Generated order number: ' . $order_number );
		}

		// Calculate price (this also sanitizes all inputs including extras)
		$price_data = $this->calculate_price( $params );

		// Get sanitized extras from params (sanitize each element in array)
		// Get sanitized extras from params
		$extras_sanitized = $this->sanitize_extras_array( $params['extras'] ?? array() );

		// Prepare files data if license file was uploaded
		$files_data = array();
		if ( ! empty( $params['license_file_url'] ) ) {
			$files_data['license'] = array(
				'url'         => esc_url_raw( $params['license_file_url'] ),
				'path'        => sanitize_text_field( $params['license_file_path'] ?? '' ),
				'uploaded_at' => current_time( 'mysql' ),
			);
		}

		// Prepare data
		$data = array(
			'user_id'            => $user_id,
			'order_number'       => $order_number,
			'book_title'         => sanitize_text_field( $params['book_title'] ),
			'book_size'          => sanitize_text_field( $params['book_size'] ),
			'paper_type'         => sanitize_text_field( $params['paper_type'] ),
			'paper_weight'       => sanitize_text_field( $params['paper_weight'] ),
			'print_type'         => sanitize_text_field( $params['print_type'] ),
			'page_count_color'   => intval( $params['page_count_color'] ?? 0 ),
			'page_count_bw'      => intval( $params['page_count_bw'] ?? 0 ),
			'page_count_total'   => $price_data['page_count_total'],
			'quantity'           => intval( $params['quantity'] ),
			'binding_type'       => sanitize_text_field( $params['binding_type'] ),
			'license_type'       => sanitize_text_field( $params['license_type'] ),
			'cover_paper_type'   => sanitize_text_field( $params['cover_paper_type'] ?? '' ),
			'cover_paper_weight' => sanitize_text_field( $params['cover_paper_weight'] ?? '250' ),
			'lamination_type'    => sanitize_text_field( $params['lamination_type'] ?? 'براق' ),
			'extras'             => maybe_serialize( $extras_sanitized ),
			'files'              => ! empty( $files_data ) ? maybe_serialize( $files_data ) : null,
			'total_price'        => $price_data['total_price'],
			'status'             => 'pending',
			'notes'              => sanitize_textarea_field( $params['notes'] ?? '' ),
		);

		// Debug: Log prepared data
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Order data prepared, calling create_order' );
		}

		// Use create_order with fallback mechanism
		$order_id = $this->create_order( $data );

		if ( is_wp_error( $order_id ) ) {
			error_log( 'Tabesh: create_order returned error: ' . $order_id->get_error_message() );
			return $order_id;
		}

		// Debug: Confirm order created
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Order created successfully with ID: ' . $order_id );
		}

		// Log the action
		$this->log_action( $order_id, $user_id, 'order_created', 'سفارش جدید ثبت شد' );

		// Send notifications
		do_action( 'tabesh_order_submitted', $order_id, $data );

		return $order_id;
	}

	/**
	 * REST API endpoint for order submission
	 *
	 * Handles both JSON and FormData (with files) submissions.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_order_rest( $request ) {
		// Log request for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: submit_order_rest called' );
			$content_type_arr   = $request->get_content_type();
			$content_type_value = is_array( $content_type_arr ) ? ( $content_type_arr['value'] ?? 'unknown' ) : 'unknown';
			error_log( 'Tabesh: Content-Type: ' . $content_type_value );
		}

		// Get parameters from either JSON or form data
		$content_type       = $request->get_content_type();
		$content_type_value = is_array( $content_type ) ? ( $content_type['value'] ?? '' ) : '';

		if ( $content_type_value === 'application/json' ) {
			// JSON request
			$params = $request->get_json_params();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Processing JSON request' );
				error_log( 'Tabesh: JSON params: ' . print_r( $params, true ) );
			}
		} else {
			// FormData request (multipart/form-data or application/x-www-form-urlencoded)
			$params = $request->get_body_params();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Processing FormData request' );
				error_log( 'Tabesh: Body params: ' . print_r( $params, true ) );
			}

			// Handle file upload if present
			$files = $request->get_file_params();
			if ( ! empty( $files['license_file'] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh: License file detected' );
				}

				// Validate file
				$file          = $files['license_file'];
				$allowed_types = array( 'application/pdf', 'image/jpeg', 'image/jpg', 'image/png' );

				if ( ! in_array( $file['type'], $allowed_types ) ) {
					return new WP_Error(
						'invalid_file_type',
						__( 'فرمت فایل مجاز نیست. فقط PDF, JPG, PNG مجاز است.', 'tabesh' ),
						array( 'status' => 400 )
					);
				}

				// Check file size (max 5MB)
				$max_size = 5 * 1024 * 1024;
				if ( $file['size'] > $max_size ) {
					return new WP_Error(
						'file_too_large',
						__( 'حجم فایل بیش از حد مجاز (5MB) است.', 'tabesh' ),
						array( 'status' => 400 )
					);
				}

				// Handle file upload using WordPress functions
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

				if ( isset( $upload['error'] ) ) {
					return new WP_Error(
						'upload_failed',
						$upload['error'],
						array( 'status' => 500 )
					);
				}

				// Store file URL in params
				$params['license_file_url']  = $upload['url'];
				$params['license_file_path'] = $upload['file'];
			}
		}

		// Ensure params is an array
		if ( ! is_array( $params ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Params is not an array, initializing empty array' );
			}
			$params = array();
		}

		// Handle extras array - convert from FormData format if needed
		// When sent as extras[], PHP automatically creates an array
		if ( ! isset( $params['extras'] ) ) {
			$params['extras'] = array();
		} elseif ( ! is_array( $params['extras'] ) ) {
			// If extras is a string or single value, convert to array
			$params['extras'] = array( $params['extras'] );
		}

		// Validate required parameters
		if ( empty( $params['book_title'] ) || trim( $params['book_title'] ) === '' ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Missing or empty book_title' );
				error_log( 'Tabesh: All params: ' . print_r( $params, true ) );
			}
			return new WP_Error(
				'missing_book_title',
				__( 'عنوان کتاب الزامی است.', 'tabesh' ),
				array( 'status' => 400 )
			);
		}

		// Submit order
		$result = $this->submit_order( $params );

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Order submission failed: ' . $result->get_error_message() );
				error_log( 'Tabesh: Error code: ' . $result->get_error_code() );
			}
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Order submitted successfully with ID: ' . $result );
		}

		// Return 201 Created for successful resource creation
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'order_id' => $result,
				),
				'message' => __( 'سفارش با موفقیت ثبت شد', 'tabesh' ),
			),
			201
		);
	}

	/**
	 * Get order by ID
	 *
	 * @param int $order_id
	 * @return object|null
	 */
	public function get_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$order_id
			)
		);
	}

	/**
	 * Update order status
	 *
	 * @param int    $order_id
	 * @param string $status
	 * @return bool
	 */
	public function update_status( $order_id, $status ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$result = $wpdb->update(
			$table,
			array( 'status' => sanitize_text_field( $status ) ),
			array( 'id' => $order_id )
		);

		if ( $result !== false ) {
			$this->log_action( $order_id, get_current_user_id(), 'status_changed', "وضعیت به $status تغییر کرد" );
			do_action( 'tabesh_order_status_changed', $order_id, $status );
		}

		return $result !== false;
	}

	/**
	 * Log action
	 *
	 * @param int    $order_id
	 * @param int    $user_id
	 * @param string $action
	 * @param string $description
	 */
	private function log_action( $order_id, $user_id, $action, $description ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_logs';

		$wpdb->insert(
			$table,
			array(
				'order_id'    => $order_id,
				'user_id'     => $user_id,
				'action'      => $action,
				'description' => $description,
			)
		);
	}

	/**
	 * Render order form shortcode
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_order_form( $atts ) {
		ob_start();
		include TABESH_PLUGIN_DIR . 'templates/frontend/order-form.php';
		return ob_get_clean();
	}

	/**
	 * Render order form V2 shortcode with Dynamic Dependency Mapping
	 *
	 * This form uses the V2 pricing engine with matrix-based pricing and
	 * dynamic option filtering based on admin-configured restrictions.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_order_form_v2( $atts ) {
		// Check if V2 pricing engine is enabled.
		$pricing_engine = new Tabesh_Pricing_Engine();
		if ( ! $pricing_engine->is_enabled() ) {
			return '<div class="tabesh-message error" dir="rtl"><p><strong>' .
				esc_html__( 'خطا:', 'tabesh' ) .
				'</strong> ' .
				esc_html__( 'موتور قیمت‌گذاری نسخه ۲ فعال نیست. لطفاً ابتدا از پنل تنظیمات، موتور قیمت‌گذاری جدید را فعال کنید.', 'tabesh' ) .
				'</p></div>';
		}

		ob_start();
		include TABESH_PLUGIN_DIR . 'templates/frontend/order-form-v2.php';
		return ob_get_clean();
	}
}
