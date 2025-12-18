<?php
/**
 * Constraint Manager - Dependency Engine for V2
 *
 * This class manages cross-field restrictions and provides allowed options
 * based on current user selections. It enables step-by-step form UX by
 * determining which options are valid at each stage.
 *
 * Key Features:
 * - Get allowed options based on current selection
 * - Validate parameter combinations with detailed reasons
 * - Standardized slug system for Persian labels
 * - Single source of truth from pricing matrices
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabesh Constraint Manager Class
 */
class Tabesh_Constraint_Manager {

	/**
	 * Pricing engine instance
	 *
	 * @var Tabesh_Pricing_Engine
	 */
	private $pricing_engine;

	/**
	 * Slug mapping cache
	 *
	 * @var array|null
	 */
	private static $slug_mapping_cache = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->pricing_engine = new Tabesh_Pricing_Engine();
	}

	/**
	 * Get allowed options based on current user selection
	 *
	 * This is the core method for step-by-step form implementation.
	 * Given current selections, it returns what options are valid for the next step.
	 *
	 * Example input: ['book_size' => 'A5']
	 * Example output: [
	 *   'papers' => ['tahrir-70', 'tahrir-80', 'bulk-70'],
	 *   'bindings' => ['shomiz', 'simi'],
	 *   'print_types' => ['bw', 'color']
	 * ]
	 *
	 * @param array  $current_selection Current user selections (e.g., ['book_size' => 'A5']).
	 * @param string $book_size Book size identifier.
	 * @return array Allowed options for next steps.
	 */
	public function get_allowed_options( $current_selection, $book_size ) {
		// Get pricing matrix for this book size.
		$pricing_matrix = $this->pricing_engine->get_pricing_matrix( $book_size );

		if ( ! $pricing_matrix ) {
			return array(
				'error'   => true,
				/* translators: %s: book size name */
				'message' => sprintf( __( 'قطع %s پیکربندی نشده است', 'tabesh' ), $book_size ),
			);
		}

		$restrictions  = $pricing_matrix['restrictions'] ?? array();
		$page_costs    = $pricing_matrix['page_costs'] ?? array();
		$binding_costs = $pricing_matrix['binding_costs'] ?? array();

		$result = array(
			'book_size'             => $book_size,
			'allowed_papers'        => array(),
			'allowed_bindings'      => array(),
			'allowed_print_types'   => array(),
			'allowed_cover_weights' => array(),
			'allowed_extras'        => array(),
		);

		// Get selected paper type and binding type from current selection.
		$selected_paper_type   = $current_selection['paper_type'] ?? null;
		$selected_binding_type = $current_selection['binding_type'] ?? null;

		// Determine allowed papers.
		$forbidden_papers = $restrictions['forbidden_paper_types'] ?? array();
		foreach ( $page_costs as $paper_type => $weights ) {
			if ( ! in_array( $paper_type, $forbidden_papers, true ) ) {
				// Get allowed weights for this paper type.
				$allowed_weights = array();
				foreach ( $weights as $weight => $print_types ) {
					$allowed_weights[] = array(
						'weight' => $weight,
						'slug'   => $this->slugify( $paper_type . '-' . $weight ),
					);
				}

				$result['allowed_papers'][] = array(
					'type'    => $paper_type,
					'slug'    => $this->slugify( $paper_type ),
					'weights' => $allowed_weights,
				);
			}
		}

		// Determine allowed bindings.
		$forbidden_bindings = $restrictions['forbidden_binding_types'] ?? array();
		foreach ( $binding_costs as $binding_type => $cost_data ) {
			if ( ! in_array( $binding_type, $forbidden_bindings, true ) ) {
				// Get cover weights for this binding type.
				$cover_weights = array();
				if ( is_array( $cost_data ) ) {
					foreach ( array_keys( $cost_data ) as $weight ) {
						$cover_weights[] = array(
							'weight' => $weight,
							'slug'   => $this->slugify( $weight ),
						);
					}
				}

				$result['allowed_bindings'][] = array(
					'type'          => $binding_type,
					'slug'          => $this->slugify( $binding_type ),
					'cover_weights' => $cover_weights,
				);
			}
		}

		// Determine allowed print types for selected paper.
		if ( $selected_paper_type && isset( $page_costs[ $selected_paper_type ] ) ) {
			$forbidden_prints = $restrictions['forbidden_print_types'][ $selected_paper_type ] ?? array();

			$all_print_types = array( 'bw', 'color' );
			foreach ( $all_print_types as $print_type ) {
				if ( ! in_array( $print_type, $forbidden_prints, true ) ) {
					$result['allowed_print_types'][] = array(
						'type'  => $print_type,
						'slug'  => $print_type,
						'label' => 'bw' === $print_type ? __( 'سیاه و سفید', 'tabesh' ) : __( 'رنگی', 'tabesh' ),
					);
				}
			}
		}

		// Determine allowed cover weights for selected binding.
		if ( $selected_binding_type && isset( $binding_costs[ $selected_binding_type ] ) ) {
			$binding_data            = $binding_costs[ $selected_binding_type ];
			$forbidden_cover_weights = $restrictions['forbidden_cover_weights'][ $selected_binding_type ] ?? array();

			if ( is_array( $binding_data ) ) {
				foreach ( array_keys( $binding_data ) as $weight ) {
					if ( ! in_array( $weight, $forbidden_cover_weights, true ) ) {
						$result['allowed_cover_weights'][] = array(
							'weight' => $weight,
							'slug'   => $this->slugify( $weight ),
						);
					}
				}
			}
		}

		// Determine allowed extras for selected binding.
		if ( $selected_binding_type ) {
			$all_extras       = $pricing_matrix['extras_costs'] ?? array();
			$forbidden_extras = $restrictions['forbidden_extras'][ $selected_binding_type ] ?? array();

			foreach ( $all_extras as $extra_name => $config ) {
				if ( ! in_array( $extra_name, $forbidden_extras, true ) ) {
					$result['allowed_extras'][] = array(
						'name'  => $extra_name,
						'slug'  => $this->slugify( $extra_name ),
						'price' => $config['price'] ?? 0,
						'type'  => $config['type'] ?? 'fixed',
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Validate parameter combination with detailed feedback
	 *
	 * This method validates a complete parameter combination and returns
	 * comprehensive feedback including:
	 * - Whether combination is allowed
	 * - Specific reason if not allowed
	 * - Suggested alternatives if available
	 *
	 * @param array $params Complete order parameters.
	 * @return array Validation result with status, message, and suggestions.
	 */
	public function validate_combination( $params ) {
		$book_size    = sanitize_text_field( $params['book_size'] ?? '' );
		$paper_type   = sanitize_text_field( $params['paper_type'] ?? '' );
		$paper_weight = sanitize_text_field( $params['paper_weight'] ?? '' );
		$binding_type = sanitize_text_field( $params['binding_type'] ?? '' );
		$cover_weight = sanitize_text_field( $params['cover_weight'] ?? $params['cover_paper_weight'] ?? '' );
		$extras       = $params['extras'] ?? array();

		// Get pricing matrix.
		$pricing_matrix = $this->pricing_engine->get_pricing_matrix( $book_size );

		if ( ! $pricing_matrix ) {
			return array(
				'allowed'     => false,
				'status'      => 'invalid_book_size',
				/* translators: %s: book size name */
				'message'     => sprintf( __( 'قطع %s پشتیبانی نمی‌شود', 'tabesh' ), $book_size ),
				'suggestions' => array(),
			);
		}

		$restrictions = $pricing_matrix['restrictions'] ?? array();

		// Check paper type restriction.
		$forbidden_papers = $restrictions['forbidden_paper_types'] ?? array();
		if ( in_array( $paper_type, $forbidden_papers, true ) ) {
			// Get alternative papers.
			$page_costs   = $pricing_matrix['page_costs'] ?? array();
			$alternatives = array();
			foreach ( $page_costs as $type => $weights ) {
				if ( ! in_array( $type, $forbidden_papers, true ) ) {
					$alternatives[] = $type;
				}
			}

			return array(
				'allowed'     => false,
				'status'      => 'forbidden_paper_type',
				/* translators: 1: paper type name, 2: book size name */
				'message'     => sprintf( __( 'کاغذ %1$s برای قطع %2$s مجاز نیست', 'tabesh' ), $paper_type, $book_size ),
				'suggestions' => $alternatives,
			);
		}

		// Check binding type restriction.
		$forbidden_bindings = $restrictions['forbidden_binding_types'] ?? array();
		if ( in_array( $binding_type, $forbidden_bindings, true ) ) {
			// Get alternative bindings.
			$binding_costs = $pricing_matrix['binding_costs'] ?? array();
			$alternatives  = array();
			foreach ( array_keys( $binding_costs ) as $type ) {
				if ( ! in_array( $type, $forbidden_bindings, true ) ) {
					$alternatives[] = $type;
				}
			}

			return array(
				'allowed'     => false,
				'status'      => 'forbidden_binding_type',
				/* translators: 1: binding type name, 2: book size name */
				'message'     => sprintf( __( 'صحافی %1$s برای قطع %2$s مجاز نیست', 'tabesh' ), $binding_type, $book_size ),
				'suggestions' => $alternatives,
			);
		}

		// Check cover weight restriction.
		if ( ! empty( $cover_weight ) ) {
			$forbidden_cover_weights = $restrictions['forbidden_cover_weights'][ $binding_type ] ?? array();
			if ( in_array( $cover_weight, $forbidden_cover_weights, true ) ) {
				// Get alternative weights.
				$binding_data = $pricing_matrix['binding_costs'][ $binding_type ] ?? array();
				$alternatives = array();
				if ( is_array( $binding_data ) ) {
					foreach ( array_keys( $binding_data ) as $weight ) {
						if ( ! in_array( $weight, $forbidden_cover_weights, true ) ) {
							$alternatives[] = $weight;
						}
					}
				}

				return array(
					'allowed'     => false,
					'status'      => 'forbidden_cover_weight',
					'message'     => sprintf(
						/* translators: 1: cover weight, 2: binding type name */
						__( 'گرماژ جلد %1$s برای صحافی %2$s مجاز نیست', 'tabesh' ),
						$cover_weight,
						$binding_type
					),
					'suggestions' => $alternatives,
				);
			}
		}

		// Check extras restrictions.
		if ( ! empty( $extras ) && is_array( $extras ) ) {
			$forbidden_extras = $restrictions['forbidden_extras'][ $binding_type ] ?? array();
			foreach ( $extras as $extra ) {
				if ( in_array( $extra, $forbidden_extras, true ) ) {
					// Get allowed extras.
					$all_extras   = $pricing_matrix['extras_costs'] ?? array();
					$alternatives = array();
					foreach ( array_keys( $all_extras ) as $extra_name ) {
						if ( ! in_array( $extra_name, $forbidden_extras, true ) ) {
							$alternatives[] = $extra_name;
						}
					}

					return array(
						'allowed'     => false,
						'status'      => 'forbidden_extra',
						'message'     => sprintf(
							/* translators: 1: extra service name, 2: binding type name */
							__( 'خدمت اضافی "%1$s" برای صحافی %2$s مجاز نیست', 'tabesh' ),
							$extra,
							$binding_type
						),
						'suggestions' => $alternatives,
					);
				}
			}
		}

		// All validations passed.
		return array(
			'allowed' => true,
			'status'  => 'valid',
			'message' => __( 'ترکیب معتبر است', 'tabesh' ),
		);
	}

	/**
	 * Convert Persian labels to standardized slugs
	 *
	 * This prevents issues with half-spaces, typos, and inconsistent naming.
	 *
	 * @param string $label Persian label.
	 * @return string Standardized slug.
	 */
	public function slugify( $label ) {
		// Normalize and remove extra spaces.
		$label = trim( $label );
		$label = preg_replace( '/\s+/u', ' ', $label );

		// Convert to lowercase for consistency.
		$label = mb_strtolower( $label, 'UTF-8' );

		// Create mapping for common Persian terms.
		$mapping = $this->get_slug_mapping();

		// Check if we have a predefined slug.
		if ( isset( $mapping[ $label ] ) ) {
			return $mapping[ $label ];
		}

		// Fallback: create slug from the label.
		// Replace spaces and special chars with hyphens.
		$slug = preg_replace( '/[^\p{L}\p{N}]+/u', '-', $label );
		$slug = trim( $slug, '-' );

		return $slug;
	}

	/**
	 * Convert slug back to Persian label
	 *
	 * @param string $slug Standardized slug.
	 * @return string Persian label.
	 */
	public function unslugify( $slug ) {
		$mapping = $this->get_slug_mapping();

		// Reverse lookup.
		$reversed = array_flip( $mapping );

		return $reversed[ $slug ] ?? $slug;
	}

	/**
	 * Get slug mapping for Persian terms
	 *
	 * This provides a centralized mapping between Persian labels and slugs.
	 *
	 * @return array Mapping array.
	 */
	private function get_slug_mapping() {
		// Return cached mapping if available.
		if ( null !== self::$slug_mapping_cache ) {
			return self::$slug_mapping_cache;
		}

		// Define standard slug mappings.
		$mapping = array(
			// Paper types.
			'تحریر'       => 'tahrir',
			'بالک'        => 'bulk',
			'گلاسه'       => 'glossy',

			// Binding types.
			'شومیز'       => 'shomiz',
			'جلد سخت'     => 'hard-cover',
			'گالینگور'    => 'galingoor',
			'سیمی'        => 'simi',
			'منگنه'       => 'mangane',

			// Print types.
			'سیاه و سفید' => 'bw',
			'رنگی'        => 'color',

			// Extras.
			'لب گرد'      => 'rounded-corner',
			'خط تا'       => 'creasing',
			'شیرینک'      => 'shrink',
			'سوراخ'       => 'hole-punch',
			'شماره گذاری' => 'numbering',
			'سلفون براق'  => 'glossy-lamination',
			'سلفون مات'   => 'matte-lamination',

			// Book sizes (kept as-is for now, but normalized).
			'a5'          => 'a5',
			'a4'          => 'a4',
			'b5'          => 'b5',
			'رقعی'        => 'roghei',
			'وزیری'       => 'vaziri',
			'خشتی'        => 'kheshti',
		);

		// Cache for performance.
		self::$slug_mapping_cache = $mapping;

		return $mapping;
	}

	/**
	 * Clear slug mapping cache
	 *
	 * @return void
	 */
	public static function clear_slug_cache() {
		self::$slug_mapping_cache = null;
	}

	/**
	 * Get available book sizes with their allowed options
	 *
	 * This is useful for the initial form load to show all valid book sizes.
	 *
	 * FIXED: Now returns ALL book sizes from product parameters, with pricing
	 * status indicated. This prevents the form from only showing corrupted entries.
	 *
	 * @return array Array of book sizes with their basic constraints and pricing status.
	 */
	public function get_available_book_sizes() {
		// Get ALL book sizes from product parameters (source of truth)
		$all_book_sizes = $this->get_book_sizes_from_product_parameters();

		// Get book sizes that have pricing configured
		$configured_sizes = $this->pricing_engine->get_configured_book_sizes();

		$result = array();
		foreach ( $all_book_sizes as $size ) {
			// Check if this size has pricing configured
			$has_pricing = in_array( $size, $configured_sizes, true );

			if ( $has_pricing ) {
				// Get allowed options for sizes with pricing
				$allowed_options = $this->get_allowed_options( array(), $size );

				if ( ! isset( $allowed_options['error'] ) ) {
					$result[] = array(
						'size'             => $size,
						'slug'             => $this->slugify( $size ),
						'paper_count'      => count( $allowed_options['allowed_papers'] ?? array() ),
						'binding_count'    => count( $allowed_options['allowed_bindings'] ?? array() ),
						'has_restrictions' => ! empty( $allowed_options['allowed_papers'] ) || ! empty( $allowed_options['allowed_bindings'] ),
						'has_pricing'      => true,
						'enabled'          => true,
					);
				}
			} else {
				// Include sizes without pricing but mark them as disabled
				$result[] = array(
					'size'             => $size,
					'slug'             => $this->slugify( $size ),
					'paper_count'      => 0,
					'binding_count'    => 0,
					'has_restrictions' => false,
					'has_pricing'      => false,
					'enabled'          => false,
				);
			}
		}

		return $result;
	}

	/**
	 * Get book sizes from product parameters (source of truth)
	 *
	 * This is the authoritative source for which book sizes exist in the system.
	 *
	 * @return array Array of book size names.
	 */
	private function get_book_sizes_from_product_parameters() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$book_sizes = array();
		if ( $result ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$book_sizes = $decoded;
			}
		}

		// Return configured sizes or defaults if not configured
		if ( empty( $book_sizes ) ) {
			return array( 'A5', 'A4', 'B5', 'رقعی', 'وزیری', 'خشتی' );
		}

		return $book_sizes;
	}
}
