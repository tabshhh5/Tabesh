<?php
/**
 * Pricing System Health Checker
 *
 * This class provides comprehensive health checks for the pricing cycle
 * to identify and diagnose issues before they break the order form.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabesh Pricing Health Checker Class
 */
class Tabesh_Pricing_Health_Checker {

	/**
	 * Run complete health check of the pricing system
	 *
	 * Enhanced version with comprehensive validation of all pricing parameters
	 * and detailed diagnostic information for troubleshooting.
	 *
	 * @return array Health check results with status and recommendations
	 */
	public static function run_health_check() {
		$results = array(
			'overall_status'  => 'healthy',
			'checks'          => array(),
			'errors'          => array(),
			'warnings'        => array(),
			'recommendations' => array(),
			'timestamp'       => current_time( 'mysql' ),
		);

		// Check 1: Database tables exist
		$db_check                      = self::check_database();
		$results['checks']['database'] = $db_check;
		if ( ! $db_check['status'] ) {
			$results['overall_status'] = 'critical';
			$results['errors'][]       = $db_check['message'];
		}

		// Check 2: Product parameters configured (source of truth)
		$product_check                           = self::check_product_parameters();
		$results['checks']['product_parameters'] = $product_check;
		if ( ! $product_check['status'] ) {
			$results['overall_status']    = 'critical';
			$results['errors'][]          = $product_check['message'];
			$results['recommendations'][] = __( 'ابتدا به تنظیمات → محصولات بروید و قطع‌های کتاب را تعریف کنید', 'tabesh' );
		}

		// Check 3: Pricing Engine V2 status
		$engine_check                        = self::check_pricing_engine();
		$results['checks']['pricing_engine'] = $engine_check;
		if ( ! $engine_check['status'] ) {
			if ( 'warning' === $engine_check['level'] ) {
				$results['warnings'][] = $engine_check['message'];
				if ( 'healthy' === $results['overall_status'] ) {
					$results['overall_status'] = 'warning';
				}
			} else {
				$results['overall_status'] = 'critical';
				$results['errors'][]       = $engine_check['message'];
			}
			$results['recommendations'][] = __( 'موتور قیمت‌گذاری V2 را از فرم ثبت قیمت فعال کنید', 'tabesh' );
		}

		// Check 4: Pricing matrices exist and are complete
		$matrices_check                        = self::check_pricing_matrices();
		$results['checks']['pricing_matrices'] = $matrices_check;
		if ( ! $matrices_check['status'] ) {
			if ( 'warning' === $matrices_check['level'] ) {
				$results['warnings'][] = $matrices_check['message'];
				if ( 'healthy' === $results['overall_status'] ) {
					$results['overall_status'] = 'warning';
				}
			} else {
				$results['overall_status'] = 'critical';
				$results['errors'][]       = $matrices_check['message'];
			}
			if ( ! empty( $matrices_check['recommendations'] ) ) {
				$results['recommendations'] = array_merge( $results['recommendations'], $matrices_check['recommendations'] );
			}
		}

		// Check 5: Orphaned matrices (matrices without matching product parameters)
		$orphaned_check                         = self::check_orphaned_matrices();
		$results['checks']['orphaned_matrices'] = $orphaned_check;
		if ( $orphaned_check['count'] > 0 ) {
			$results['warnings'][] = $orphaned_check['message'];
			if ( 'healthy' === $results['overall_status'] ) {
				$results['overall_status'] = 'warning';
			}
			$results['recommendations'][] = __( 'ماتریس‌های یتیم هنگام ذخیره فرم ثبت قیمت به صورت خودکار پاک می‌شوند', 'tabesh' );
		}

		// Check 6: Parameter consistency (NEW)
		$consistency_check                          = self::check_parameter_consistency();
		$results['checks']['parameter_consistency'] = $consistency_check;
		if ( ! $consistency_check['status'] ) {
			if ( 'warning' === $consistency_check['level'] ) {
				$results['warnings'][] = $consistency_check['message'];
				if ( 'healthy' === $results['overall_status'] ) {
					$results['overall_status'] = 'warning';
				}
			} else {
				$results['overall_status'] = 'critical';
				$results['errors'][]       = $consistency_check['message'];
			}
			if ( ! empty( $consistency_check['recommendations'] ) ) {
				$results['recommendations'] = array_merge( $results['recommendations'], $consistency_check['recommendations'] );
			}
		}

		// Check 7: Matrix completeness (NEW - detailed check for each size)
		$completeness_check                       = self::check_matrix_completeness();
		$results['checks']['matrix_completeness'] = $completeness_check;
		if ( ! $completeness_check['status'] ) {
			if ( 'warning' === $completeness_check['level'] ) {
				$results['warnings'][] = $completeness_check['message'];
				if ( 'healthy' === $results['overall_status'] ) {
					$results['overall_status'] = 'warning';
				}
			}
			if ( ! empty( $completeness_check['recommendations'] ) ) {
				$results['recommendations'] = array_merge( $results['recommendations'], $completeness_check['recommendations'] );
			}
		}

		// Check 8: Available sizes for order form
		$availability_check              = self::check_order_form_availability();
		$results['checks']['order_form'] = $availability_check;
		if ( ! $availability_check['status'] ) {
			$results['overall_status'] = 'critical';
			$results['errors'][]       = $availability_check['message'];
			if ( ! empty( $availability_check['recommendations'] ) ) {
				$results['recommendations'] = array_merge( $results['recommendations'], $availability_check['recommendations'] );
			}
		}

		// Check 9: Cache status
		$cache_check                = self::check_cache_status();
		$results['checks']['cache'] = $cache_check;
		if ( $cache_check['stale'] ) {
			$results['warnings'][] = $cache_check['message'];
			if ( 'healthy' === $results['overall_status'] ) {
				$results['overall_status'] = 'warning';
			}
		}

		return $results;
	}

	/**
	 * Check if database tables exist
	 *
	 * @return array Check result
	 */
	private static function check_database() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_settings'" ) === $table_settings;

		return array(
			'status'  => $exists,
			'message' => $exists
				? __( 'جدول تنظیمات موجود است', 'tabesh' )
				: __( 'جدول تنظیمات یافت نشد', 'tabesh' ),
			'level'   => $exists ? 'success' : 'critical',
		);
	}

	/**
	 * Check product parameters configuration
	 *
	 * @return array Check result
	 */
	private static function check_product_parameters() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$book_sizes_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$book_sizes = array();
		if ( $book_sizes_raw ) {
			$decoded = json_decode( $book_sizes_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$book_sizes = $decoded;
			}
		}

		$has_sizes = ! empty( $book_sizes );

		return array(
			'status'  => $has_sizes,
			'message' => $has_sizes
				? sprintf( __( '%d قطع کتاب تعریف شده', 'tabesh' ), count( $book_sizes ) )
				: __( 'هیچ قطع کتابی در تنظیمات محصول تعریف نشده', 'tabesh' ),
			'level'   => $has_sizes ? 'success' : 'critical',
			'data'    => $book_sizes,
		);
	}

	/**
	 * Check pricing engine V2 status
	 *
	 * @return array Check result
	 */
	private static function check_pricing_engine() {
		$pricing_engine = new Tabesh_Pricing_Engine();
		$is_enabled     = $pricing_engine->is_enabled();

		return array(
			'status'  => $is_enabled,
			'message' => $is_enabled
				? __( 'موتور قیمت‌گذاری V2 فعال است', 'tabesh' )
				: __( 'موتور قیمت‌گذاری V2 غیرفعال است', 'tabesh' ),
			'level'   => $is_enabled ? 'success' : 'warning',
		);
	}

	/**
	 * Check pricing matrices
	 *
	 * @return array Check result
	 */
	private static function check_pricing_matrices() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Get product parameters
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$book_sizes_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$book_sizes = array();
		if ( $book_sizes_raw ) {
			$decoded = json_decode( $book_sizes_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$book_sizes = $decoded;
			}
		}

		if ( empty( $book_sizes ) ) {
			return array(
				'status'          => false,
				'message'         => __( 'نمی‌توان ماتریس‌ها را بررسی کرد: قطع‌ها تعریف نشده‌اند', 'tabesh' ),
				'level'           => 'critical',
				'recommendations' => array(),
			);
		}

		// Get all pricing matrices
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_matrices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value FROM $table_settings WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		$valid_complete_matrices   = 0;
		$valid_incomplete_matrices = 0;
		$invalid_matrices          = 0;
		$incomplete_sizes          = array();

		foreach ( $all_matrices as $row ) {
			$setting_key = $row['setting_key'];
			$safe_key    = str_replace( 'pricing_matrix_', '', $setting_key );

			// Decode book size.
			$decoded_size = base64_decode( $safe_key, true );
			if ( false !== $decoded_size && ! empty( $decoded_size ) ) {
				$book_size = $decoded_size;
			} else {
				$book_size = $safe_key;
			}

			// Check if book size is valid.
			if ( ! in_array( $book_size, $book_sizes, true ) ) {
				continue; // Orphaned matrix, skip.
			}

			// Decode matrix.
			$matrix = json_decode( $row['setting_value'], true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $matrix ) ) {
				++$invalid_matrices;
				continue;
			}

			// Check completeness.
			$has_papers   = ! empty( $matrix['page_costs'] );
			$has_bindings = ! empty( $matrix['binding_costs'] );

			if ( $has_papers && $has_bindings ) {
				++$valid_complete_matrices;
			} else {
				++$valid_incomplete_matrices;
				$incomplete_sizes[] = $book_size;
			}
		}

		$total_valid      = $valid_complete_matrices + $valid_incomplete_matrices;
		$missing_matrices = count( $book_sizes ) - $total_valid;

		$recommendations = array();
		if ( $missing_matrices > 0 ) {
			$recommendations[] = sprintf(
				/* translators: %d: number of book sizes without pricing matrices */
				__( '%d قطع بدون ماتریس قیمت: به فرم ثبت قیمت بروید و برای این قطع‌ها قیمت تعریف کنید', 'tabesh' ),
				$missing_matrices
			);
		}
		if ( ! empty( $incomplete_sizes ) ) {
			$recommendations[] = sprintf(
				/* translators: %s: comma-separated list of incomplete book sizes */
				__( 'ماتریس‌های ناقص برای: %s - باید paper costs و binding costs تعریف شوند', 'tabesh' ),
				implode( '، ', $incomplete_sizes )
			);
		}

		if ( $valid_complete_matrices === 0 ) {
			return array(
				'status'          => false,
				'message'         => __( 'هیچ ماتریس قیمت کاملی وجود ندارد', 'tabesh' ),
				'level'           => 'critical',
				'data'            => array(
					'complete'   => $valid_complete_matrices,
					'incomplete' => $valid_incomplete_matrices,
					'invalid'    => $invalid_matrices,
					'missing'    => $missing_matrices,
				),
				'recommendations' => $recommendations,
			);
		}

		if ( $valid_incomplete_matrices > 0 || $missing_matrices > 0 ) {
			return array(
				'status'          => true,
				'message'         => sprintf(
					/* translators: 1: number of complete matrices, 2: number of incomplete matrices, 3: number of missing matrices */
					__( '%1$d ماتریس کامل، %2$d ناقص، %3$d مفقود', 'tabesh' ),
					$valid_complete_matrices,
					$valid_incomplete_matrices,
					$missing_matrices
				),
				'level'           => 'warning',
				'data'            => array(
					'complete'   => $valid_complete_matrices,
					'incomplete' => $valid_incomplete_matrices,
					'invalid'    => $invalid_matrices,
					'missing'    => $missing_matrices,
				),
				'recommendations' => $recommendations,
			);
		}

		return array(
			'status'          => true,
			'message'         => sprintf(
				/* translators: %d: number of complete pricing matrices */
				__( '%d ماتریس قیمت کامل', 'tabesh' ),
				$valid_complete_matrices
			),
			'level'           => 'success',
			'data'            => array(
				'complete'   => $valid_complete_matrices,
				'incomplete' => 0,
				'invalid'    => $invalid_matrices,
				'missing'    => 0,
			),
			'recommendations' => array(),
		);
	}

	/**
	 * Check for orphaned pricing matrices
	 *
	 * @return array Check result
	 */
	private static function check_orphaned_matrices() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Get product parameters.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$book_sizes_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$book_sizes = array();
		if ( $book_sizes_raw ) {
			$decoded = json_decode( $book_sizes_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$book_sizes = $decoded;
			}
		}

		// Get all pricing matrices.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_matrices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key FROM $table_settings WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		$orphaned_count = 0;
		$orphaned_sizes = array();

		foreach ( $all_matrices as $row ) {
			$setting_key = $row['setting_key'];
			$safe_key    = str_replace( 'pricing_matrix_', '', $setting_key );

			// Decode book size.
			$decoded_size = base64_decode( $safe_key, true );
			if ( false !== $decoded_size && ! empty( $decoded_size ) ) {
				$book_size = $decoded_size;
			} else {
				$book_size = $safe_key;
			}

			// Check if orphaned.
			if ( ! in_array( $book_size, $book_sizes, true ) ) {
				++$orphaned_count;
				$orphaned_sizes[] = $book_size;
			}
		}

		return array(
			'count'   => $orphaned_count,
			'message' => $orphaned_count > 0
				? sprintf(
					/* translators: 1: number of orphaned matrices, 2: comma-separated list of orphaned sizes */
					__( '%1$d ماتریس یتیم برای قطع‌های: %2$s', 'tabesh' ),
					$orphaned_count,
					implode( '، ', $orphaned_sizes )
				)
				: __( 'هیچ ماتریس یتیمی وجود ندارد', 'tabesh' ),
			'level'   => $orphaned_count > 0 ? 'warning' : 'success',
			'data'    => $orphaned_sizes,
		);
	}

	/**
	 * Check order form availability
	 *
	 * @return array Check result
	 */
	private static function check_order_form_availability() {
		try {
			$constraint_manager = new Tabesh_Constraint_Manager();
			$available_sizes    = $constraint_manager->get_available_book_sizes();

			$enabled_count  = 0;
			$enabled_sizes  = array();
			$disabled_count = 0;
			$disabled_sizes = array();

			foreach ( $available_sizes as $size_info ) {
				if ( $size_info['enabled'] ) {
					++$enabled_count;
					$enabled_sizes[] = $size_info['size'];
				} else {
					++$disabled_count;
					$disabled_sizes[] = sprintf(
						'%s (papers: %d, bindings: %d)',
						$size_info['size'],
						$size_info['paper_count'],
						$size_info['binding_count']
					);
				}
			}

			if ( $enabled_count === 0 ) {
				$recommendations = array(
					__( 'هیچ قطعی برای فرم سفارش فعال نیست', 'tabesh' ),
					__( 'برای هر قطع، ماتریس قیمت کامل (با paper costs و binding costs) تنظیم کنید', 'tabesh' ),
				);

				return array(
					'status'          => false,
					'message'         => __( 'فرم سفارش V2 نمی‌تواند کار کند: هیچ قطع فعالی نیست', 'tabesh' ),
					'level'           => 'critical',
					'data'            => array(
						'enabled'  => $enabled_count,
						'disabled' => $disabled_count,
					),
					'recommendations' => $recommendations,
				);
			}

			if ( $disabled_count > 0 ) {
				return array(
					'status'  => true,
					'message' => sprintf(
						/* translators: 1: number of enabled sizes, 2: number of disabled sizes */
						__( '%1$d قطع فعال، %2$d غیرفعال', 'tabesh' ),
						$enabled_count,
						$disabled_count
					),
					'level'   => 'warning',
					'data'    => array(
						'enabled'          => $enabled_count,
						'enabled_sizes'    => $enabled_sizes,
						'disabled'         => $disabled_count,
						'disabled_details' => $disabled_sizes,
					),
				);
			}

			return array(
				'status'  => true,
				'message' => sprintf(
					/* translators: %d: number of enabled sizes */
					__( '%d قطع برای فرم سفارش فعال است', 'tabesh' ),
					$enabled_count
				),
				'level'   => 'success',
				'data'    => array(
					'enabled'       => $enabled_count,
					'enabled_sizes' => $enabled_sizes,
					'disabled'      => 0,
				),
			);

		} catch ( Exception $e ) {
			return array(
				'status'  => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'خطا در بررسی Constraint Manager: %s', 'tabesh' ),
					$e->getMessage()
				),
				'level'   => 'critical',
			);
		}
	}

	/**
	 * Check cache status
	 *
	 * @return array Check result
	 */
	private static function check_cache_status() {
		// Since cache is private static in Pricing Engine, we can't directly check it
		// But we can check if cache needs refresh by comparing timestamps.

		// For now, return basic info.
		return array(
			'stale'   => false,
			'message' => __( 'Cache در حالت عادی', 'tabesh' ),
			'level'   => 'success',
		);
	}

	/**
	 * Check parameter consistency across the system
	 *
	 * Validates that all configured parameters (paper types, binding types, extras)
	 * are consistently defined and match between product settings and pricing matrices.
	 *
	 * @return array Check result
	 */
	private static function check_parameter_consistency() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Get product parameters
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$book_sizes_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$book_sizes = array();
		if ( $book_sizes_raw ) {
			$decoded = json_decode( $book_sizes_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$book_sizes = $decoded;
			}
		}

		if ( empty( $book_sizes ) ) {
			return array(
				'status'          => false,
				'message'         => __( 'قطع‌های کتاب تعریف نشده‌اند', 'tabesh' ),
				'level'           => 'critical',
				'recommendations' => array(
					__( 'از تنظیمات محصول، قطع‌های کتاب را تعریف کنید', 'tabesh' ),
				),
			);
		}

		// Get all pricing matrices
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_matrices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value FROM $table_settings WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		$issues             = array();
		$recommendations    = array();
		$total_sizes        = count( $book_sizes );
		$configured_sizes   = 0;
		$unconfigured_sizes = array();

		foreach ( $book_sizes as $book_size ) {
			$safe_key    = base64_encode( $book_size );
			$setting_key = 'pricing_matrix_' . $safe_key;
			$has_pricing = false;

			foreach ( $all_matrices as $row ) {
				if ( $row['setting_key'] === $setting_key ) {
					$has_pricing = true;
					++$configured_sizes;
					break;
				}
			}

			if ( ! $has_pricing ) {
				$unconfigured_sizes[] = $book_size;
			}
		}

		if ( ! empty( $unconfigured_sizes ) ) {
			$issues[] = sprintf(
				/* translators: %d: number of unconfigured sizes */
				__( '%1$d قطع بدون ماتریس قیمت: %2$s', 'tabesh' ),
				count( $unconfigured_sizes ),
				implode( '، ', $unconfigured_sizes )
			);
			$recommendations[] = __( 'از فرم ثبت قیمت برای هر قطع، ماتریس قیمت تعریف کنید', 'tabesh' );
		}

		if ( $configured_sizes === 0 ) {
			return array(
				'status'          => false,
				'message'         => __( 'هیچ قطعی قیمت‌گذاری نشده است', 'tabesh' ),
				'level'           => 'critical',
				'data'            => array(
					'total_sizes'        => $total_sizes,
					'configured_sizes'   => 0,
					'unconfigured_sizes' => $unconfigured_sizes,
				),
				'recommendations' => $recommendations,
			);
		}

		if ( ! empty( $issues ) ) {
			return array(
				'status'          => true,
				'message'         => sprintf(
					/* translators: 1: configured sizes, 2: total sizes */
					__( '⚠️ %1$d از %2$d قطع قیمت‌گذاری شده', 'tabesh' ),
					$configured_sizes,
					$total_sizes
				),
				'level'           => 'warning',
				'data'            => array(
					'total_sizes'        => $total_sizes,
					'configured_sizes'   => $configured_sizes,
					'unconfigured_sizes' => $unconfigured_sizes,
				),
				'recommendations' => $recommendations,
			);
		}

		return array(
			'status'  => true,
			'message' => sprintf(
				/* translators: %d: number of configured sizes */
				__( '✓ همه %d قطع قیمت‌گذاری شده', 'tabesh' ),
				$total_sizes
			),
			'level'   => 'success',
			'data'    => array(
				'total_sizes'        => $total_sizes,
				'configured_sizes'   => $configured_sizes,
				'unconfigured_sizes' => array(),
			),
		);
	}

	/**
	 * Check matrix completeness for each book size
	 *
	 * Validates that each pricing matrix has all required components:
	 * - page_costs (paper types with weights and print types)
	 * - binding_costs (binding types with cover weights)
	 *
	 * @return array Check result
	 */
	private static function check_matrix_completeness() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Get book sizes
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$book_sizes_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
				'book_sizes'
			)
		);

		$book_sizes = array();
		if ( $book_sizes_raw ) {
			$decoded = json_decode( $book_sizes_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$book_sizes = $decoded;
			}
		}

		if ( empty( $book_sizes ) ) {
			return array(
				'status'  => true,
				'message' => __( 'بررسی اکمال لغو شد: قطعی تعریف نشده', 'tabesh' ),
				'level'   => 'success',
			);
		}

		// Get all matrices
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_matrices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value FROM $table_settings WHERE setting_key LIKE %s",
				'pricing_matrix_%'
			),
			ARRAY_A
		);

		$incomplete_details = array();
		$complete_count     = 0;
		$incomplete_count   = 0;

		foreach ( $all_matrices as $row ) {
			$setting_key = $row['setting_key'];
			$safe_key    = str_replace( 'pricing_matrix_', '', $setting_key );

			// Decode book size
			$decoded_size = base64_decode( $safe_key, true );
			if ( false !== $decoded_size && ! empty( $decoded_size ) ) {
				$book_size = $decoded_size;
			} else {
				$book_size = $safe_key;
			}

			// Check if this is a valid book size
			if ( ! in_array( $book_size, $book_sizes, true ) ) {
				continue; // Skip orphaned matrices
			}

			// Decode matrix
			$matrix = json_decode( $row['setting_value'], true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $matrix ) ) {
				$incomplete_details[] = array(
					'size'   => $book_size,
					'issues' => array( __( 'ماتریس خراب (JSON نامعتبر)', 'tabesh' ) ),
				);
				++$incomplete_count;
				continue;
			}

			// Check completeness
			$issues = array();

			// Check page_costs
			if ( empty( $matrix['page_costs'] ) ) {
				$issues[] = __( 'page_costs خالی است', 'tabesh' );
			} else {
				// Validate structure
				$paper_count = 0;
				foreach ( $matrix['page_costs'] as $paper_type => $weights ) {
					if ( is_array( $weights ) && ! empty( $weights ) ) {
						++$paper_count;
					}
				}
				if ( 0 === $paper_count ) {
					$issues[] = __( 'هیچ نوع کاغذی تعریف نشده', 'tabesh' );
				}
			}

			// Check binding_costs
			if ( empty( $matrix['binding_costs'] ) ) {
				$issues[] = __( 'binding_costs خالی است', 'tabesh' );
			} else {
				// Validate structure
				$binding_count = 0;
				foreach ( $matrix['binding_costs'] as $binding_type => $cost_data ) {
					if ( ! empty( $cost_data ) ) {
						++$binding_count;
					}
				}
				if ( 0 === $binding_count ) {
					$issues[] = __( 'هیچ نوع صحافی تعریف نشده', 'tabesh' );
				}
			}

			if ( ! empty( $issues ) ) {
				$incomplete_details[] = array(
					'size'   => $book_size,
					'issues' => $issues,
				);
				++$incomplete_count;
			} else {
				++$complete_count;
			}
		}

		if ( $incomplete_count > 0 ) {
			// Build detailed message
			$message_parts = array();
			foreach ( $incomplete_details as $detail ) {
				$message_parts[] = sprintf(
					'%s: %s',
					$detail['size'],
					implode( '، ', $detail['issues'] )
				);
			}

			$recommendations = array(
				__( 'از فرم ثبت قیمت، ماتریس‌های ناقص را تکمیل کنید', 'tabesh' ),
				__( 'هر ماتریس باید حداقل یک نوع کاغذ و یک نوع صحافی داشته باشد', 'tabesh' ),
			);

			return array(
				'status'          => true,
				'message'         => sprintf(
					/* translators: 1: incomplete count, 2: complete count */
					__( '⚠️ %1$d ماتریس ناقص، %2$d کامل', 'tabesh' ),
					$incomplete_count,
					$complete_count
				),
				'level'           => 'warning',
				'data'            => array(
					'complete_count'     => $complete_count,
					'incomplete_count'   => $incomplete_count,
					'incomplete_details' => $incomplete_details,
				),
				'recommendations' => $recommendations,
			);
		}

		return array(
			'status'  => true,
			'message' => sprintf(
				/* translators: %d: number of complete matrices */
				__( '✓ همه %d ماتریس کامل هستند', 'tabesh' ),
				$complete_count
			),
			'level'   => 'success',
			'data'    => array(
				'complete_count'   => $complete_count,
				'incomplete_count' => 0,
			),
		);
	}

	/**
	 * Get a formatted health report (HTML)
	 *
	 * Enhanced version with detailed diagnostics, severity indicators,
	 * and actionable recommendations.
	 *
	 * @param bool $detailed Whether to include detailed check results. Default true.
	 * @return string HTML formatted health report
	 */
	public static function get_health_report( $detailed = true ) {
		$health = self::run_health_check();

		ob_start();
		?>
		<style>
			.tabesh-health-report {
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 4px;
				padding: 20px;
				margin: 20px 0;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.tabesh-health-report h3 {
				margin-top: 0;
				font-size: 18px;
				border-bottom: 2px solid #eee;
				padding-bottom: 10px;
			}
			.tabesh-health-status {
				display: inline-block;
				padding: 5px 12px;
				border-radius: 3px;
				font-weight: bold;
				font-size: 14px;
				margin-inline-start: 10px;
			}
			.tabesh-status-healthy {
				background: #d4edda;
				color: #155724;
			}
			.tabesh-status-warning {
				background: #fff3cd;
				color: #856404;
			}
			.tabesh-status-critical {
				background: #f8d7da;
				color: #721c24;
			}
			.tabesh-health-errors,
			.tabesh-health-warnings,
			.tabesh-health-recommendations,
			.tabesh-health-details {
				margin: 15px 0;
				padding: 15px;
				border-radius: 3px;
			}
			.tabesh-health-errors {
				background: #f8d7da;
				border: 1px solid #f5c6cb;
			}
			.tabesh-health-errors h4 {
				color: #721c24;
				margin-top: 0;
			}
			.tabesh-health-warnings {
				background: #fff3cd;
				border: 1px solid #ffeeba;
			}
			.tabesh-health-warnings h4 {
				color: #856404;
				margin-top: 0;
			}
			.tabesh-health-recommendations {
				background: #d1ecf1;
				border: 1px solid #bee5eb;
			}
			.tabesh-health-recommendations h4 {
				color: #0c5460;
				margin-top: 0;
			}
			.tabesh-health-details {
				background: #f8f9fa;
				border: 1px solid #dee2e6;
			}
			.tabesh-health-details h4 {
				color: #495057;
				margin-top: 0;
			}
			.tabesh-health-check-item {
				margin: 10px 0;
				padding: 10px;
				background: #fff;
				border: 1px solid #e9ecef;
				border-radius: 3px;
			}
			.tabesh-health-check-item .check-name {
				font-weight: bold;
				margin-bottom: 5px;
			}
			.tabesh-health-check-item .check-status {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 2px;
				font-size: 12px;
				margin-inline-start: 10px;
			}
			.check-status-success {
				background: #d4edda;
				color: #155724;
			}
			.check-status-warning {
				background: #fff3cd;
				color: #856404;
			}
			.check-status-critical {
				background: #f8d7da;
				color: #721c24;
			}
			.tabesh-health-timestamp {
				font-size: 12px;
				color: #6c757d;
				margin-top: 15px;
				padding-top: 15px;
				border-top: 1px solid #eee;
			}
		</style>
		<div class="tabesh-health-report">
			<h3>
				<?php
				$status_class = 'tabesh-status-' . $health['overall_status'];
				$status_text  = '';
				$status_icon  = '';

				if ( 'healthy' === $health['overall_status'] ) {
					$status_text = __( 'سلامت', 'tabesh' );
					$status_icon = '✓';
				} elseif ( 'warning' === $health['overall_status'] ) {
					$status_text = __( 'هشدار', 'tabesh' );
					$status_icon = '⚠';
				} else {
					$status_text = __( 'خطای حیاتی', 'tabesh' );
					$status_icon = '✗';
				}

				echo esc_html__( 'گزارش سلامت سیستم قیمت‌گذاری V2', 'tabesh' );
				echo '<span class="tabesh-health-status ' . esc_attr( $status_class ) . '">';
				echo esc_html( $status_icon . ' ' . $status_text );
				echo '</span>';
				?>
			</h3>

			<?php if ( ! empty( $health['errors'] ) ) : ?>
				<div class="tabesh-health-errors">
					<h4><?php echo esc_html__( '🚨 خطاهای حیاتی (نیاز به رفع فوری)', 'tabesh' ); ?></h4>
					<ul>
						<?php foreach ( $health['errors'] as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $health['warnings'] ) ) : ?>
				<div class="tabesh-health-warnings">
					<h4><?php echo esc_html__( '⚠️ هشدارها (توصیه به رفع)', 'tabesh' ); ?></h4>
					<ul>
						<?php foreach ( $health['warnings'] as $warning ) : ?>
							<li><?php echo esc_html( $warning ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $health['recommendations'] ) ) : ?>
				<div class="tabesh-health-recommendations">
					<h4><?php echo esc_html__( '💡 توصیه‌های اصلاحی', 'tabesh' ); ?></h4>
					<ol>
						<?php
						$unique_recommendations = array_unique( $health['recommendations'] );
						foreach ( $unique_recommendations as $recommendation ) :
							?>
							<li><?php echo esc_html( $recommendation ); ?></li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

			<?php if ( $detailed && ! empty( $health['checks'] ) ) : ?>
				<div class="tabesh-health-details">
					<h4><?php echo esc_html__( '📊 جزئیات بررسی‌ها', 'tabesh' ); ?></h4>
					<?php
					$check_labels = array(
						'database'              => __( 'دیتابیس', 'tabesh' ),
						'product_parameters'    => __( 'پارامترهای محصول', 'tabesh' ),
						'pricing_engine'        => __( 'موتور قیمت‌گذاری V2', 'tabesh' ),
						'pricing_matrices'      => __( 'ماتریس‌های قیمت', 'tabesh' ),
						'orphaned_matrices'     => __( 'ماتریس‌های یتیم', 'tabesh' ),
						'parameter_consistency' => __( 'سازگاری پارامترها', 'tabesh' ),
						'matrix_completeness'   => __( 'کامل بودن ماتریس‌ها', 'tabesh' ),
						'order_form'            => __( 'فرم سفارش', 'tabesh' ),
						'cache'                 => __( 'کش', 'tabesh' ),
					);

					foreach ( $health['checks'] as $check_key => $check_data ) :
						$check_label  = $check_labels[ $check_key ] ?? $check_key;
						$check_level  = $check_data['level'] ?? 'success';
						$check_status = $check_data['status'] ?? true;
						?>
						<div class="tabesh-health-check-item">
							<div class="check-name">
								<?php echo esc_html( $check_label ); ?>
								<span class="check-status check-status-<?php echo esc_attr( $check_level ); ?>">
									<?php
									if ( 'success' === $check_level ) {
										echo '✓ ';
									} elseif ( 'warning' === $check_level ) {
										echo '⚠ ';
									} else {
										echo '✗ ';
									}
									echo esc_html( $check_data['message'] ?? '' );
									?>
								</span>
							</div>
							<?php if ( ! empty( $check_data['data'] ) && is_array( $check_data['data'] ) ) : ?>
								<div class="check-details" style="font-size: 12px; color: #6c757d; margin-top: 5px;">
									<?php
									foreach ( $check_data['data'] as $data_key => $data_value ) :
										if ( is_array( $data_value ) && ! empty( $data_value ) ) :
											echo '<strong>' . esc_html( $data_key ) . ':</strong> ';
											echo esc_html( implode( '، ', array_slice( $data_value, 0, 5 ) ) );
											if ( count( $data_value ) > 5 ) {
												echo ' و ' . esc_html( count( $data_value ) - 5 ) . ' مورد دیگر';
											}
											echo '<br>';
										elseif ( ! is_array( $data_value ) ) :
											echo '<strong>' . esc_html( $data_key ) . ':</strong> ' . esc_html( $data_value ) . '<br>';
										endif;
									endforeach;
									?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( isset( $health['timestamp'] ) ) : ?>
				<div class="tabesh-health-timestamp">
					<?php
					printf(
						/* translators: %s: timestamp */
						esc_html__( '🕐 زمان بررسی: %s', 'tabesh' ),
						esc_html( $health['timestamp'] )
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get an HTML report suitable for modal/dashlet display
	 *
	 * @return string Compact HTML formatted health report
	 */
	public static function get_html_report() {
		return self::get_health_report( true );
	}
}
