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
	 * @return array Health check results with status and recommendations
	 */
	public static function run_health_check() {
		$results = array(
			'overall_status' => 'healthy',
			'checks'         => array(),
			'errors'         => array(),
			'warnings'       => array(),
			'recommendations' => array(),
		);

		// Check 1: Database tables exist
		$db_check = self::check_database();
		$results['checks']['database'] = $db_check;
		if ( ! $db_check['status'] ) {
			$results['overall_status'] = 'critical';
			$results['errors'][] = $db_check['message'];
		}

		// Check 2: Product parameters configured
		$product_check = self::check_product_parameters();
		$results['checks']['product_parameters'] = $product_check;
		if ( ! $product_check['status'] ) {
			$results['overall_status'] = 'critical';
			$results['errors'][] = $product_check['message'];
			$results['recommendations'][] = __( 'ابتدا به تنظیمات → محصولات بروید و قطع‌های کتاب را تعریف کنید', 'tabesh' );
		}

		// Check 3: Pricing Engine V2 status
		$engine_check = self::check_pricing_engine();
		$results['checks']['pricing_engine'] = $engine_check;
		if ( ! $engine_check['status'] ) {
			if ( 'warning' === $engine_check['level'] ) {
				$results['warnings'][] = $engine_check['message'];
				if ( 'healthy' === $results['overall_status'] ) {
					$results['overall_status'] = 'warning';
				}
			} else {
				$results['overall_status'] = 'critical';
				$results['errors'][] = $engine_check['message'];
			}
			$results['recommendations'][] = __( 'موتور قیمت‌گذاری V2 را از فرم ثبت قیمت فعال کنید', 'tabesh' );
		}

		// Check 4: Pricing matrices exist and are valid
		$matrices_check = self::check_pricing_matrices();
		$results['checks']['pricing_matrices'] = $matrices_check;
		if ( ! $matrices_check['status'] ) {
			if ( 'warning' === $matrices_check['level'] ) {
				$results['warnings'][] = $matrices_check['message'];
				if ( 'healthy' === $results['overall_status'] ) {
					$results['overall_status'] = 'warning';
				}
			} else {
				$results['overall_status'] = 'critical';
				$results['errors'][] = $matrices_check['message'];
			}
			if ( ! empty( $matrices_check['recommendations'] ) ) {
				$results['recommendations'] = array_merge( $results['recommendations'], $matrices_check['recommendations'] );
			}
		}

		// Check 5: Orphaned matrices
		$orphaned_check = self::check_orphaned_matrices();
		$results['checks']['orphaned_matrices'] = $orphaned_check;
		if ( $orphaned_check['count'] > 0 ) {
			$results['warnings'][] = $orphaned_check['message'];
			if ( 'healthy' === $results['overall_status'] ) {
				$results['overall_status'] = 'warning';
			}
			$results['recommendations'][] = __( 'از فرم ثبت قیمت، ماتریس‌های یتیم پاک می‌شوند', 'tabesh' );
		}

		// Check 6: Available sizes for order form
		$availability_check = self::check_order_form_availability();
		$results['checks']['order_form'] = $availability_check;
		if ( ! $availability_check['status'] ) {
			$results['overall_status'] = 'critical';
			$results['errors'][] = $availability_check['message'];
			if ( ! empty( $availability_check['recommendations'] ) ) {
				$results['recommendations'] = array_merge( $results['recommendations'], $availability_check['recommendations'] );
			}
		}

		// Check 7: Cache status
		$cache_check = self::check_cache_status();
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
		$is_enabled = $pricing_engine->is_enabled();

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
				'status'           => false,
				'message'          => __( 'نمی‌توان ماتریس‌ها را بررسی کرد: قطع‌ها تعریف نشده‌اند', 'tabesh' ),
				'level'            => 'critical',
				'recommendations'  => array(),
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

		$valid_complete_matrices = 0;
		$valid_incomplete_matrices = 0;
		$invalid_matrices = 0;
		$incomplete_sizes = array();

		foreach ( $all_matrices as $row ) {
			$setting_key = $row['setting_key'];
			$safe_key = str_replace( 'pricing_matrix_', '', $setting_key );

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
			$has_papers = ! empty( $matrix['page_costs'] );
			$has_bindings = ! empty( $matrix['binding_costs'] );

			if ( $has_papers && $has_bindings ) {
				++$valid_complete_matrices;
			} else {
				++$valid_incomplete_matrices;
				$incomplete_sizes[] = $book_size;
			}
		}

		$total_valid = $valid_complete_matrices + $valid_incomplete_matrices;
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
				'status'           => false,
				'message'          => __( 'هیچ ماتریس قیمت کاملی وجود ندارد', 'tabesh' ),
				'level'            => 'critical',
				'data'             => array(
					'complete'   => $valid_complete_matrices,
					'incomplete' => $valid_incomplete_matrices,
					'invalid'    => $invalid_matrices,
					'missing'    => $missing_matrices,
				),
				'recommendations'  => $recommendations,
			);
		}

		if ( $valid_incomplete_matrices > 0 || $missing_matrices > 0 ) {
			return array(
				'status'           => true,
				'message'          => sprintf(
					/* translators: 1: number of complete matrices, 2: number of incomplete matrices, 3: number of missing matrices */
					__( '%1$d ماتریس کامل، %2$d ناقص، %3$d مفقود', 'tabesh' ),
					$valid_complete_matrices,
					$valid_incomplete_matrices,
					$missing_matrices
				),
				'level'            => 'warning',
				'data'             => array(
					'complete'   => $valid_complete_matrices,
					'incomplete' => $valid_incomplete_matrices,
					'invalid'    => $invalid_matrices,
					'missing'    => $missing_matrices,
				),
				'recommendations'  => $recommendations,
			);
		}

		return array(
			'status'           => true,
			'message'          => sprintf(
				/* translators: %d: number of complete pricing matrices */
				__( '%d ماتریس قیمت کامل', 'tabesh' ),
				$valid_complete_matrices
			),
			'level'            => 'success',
			'data'             => array(
				'complete'   => $valid_complete_matrices,
				'incomplete' => 0,
				'invalid'    => $invalid_matrices,
				'missing'    => 0,
			),
			'recommendations'  => array(),
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
			$safe_key = str_replace( 'pricing_matrix_', '', $setting_key );

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
			$available_sizes = $constraint_manager->get_available_book_sizes();

			$enabled_count = 0;
			$enabled_sizes = array();
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
					'status'           => false,
					'message'          => __( 'فرم سفارش V2 نمی‌تواند کار کند: هیچ قطع فعالی نیست', 'tabesh' ),
					'level'            => 'critical',
					'data'             => array(
						'enabled'  => $enabled_count,
						'disabled' => $disabled_count,
					),
					'recommendations'  => $recommendations,
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
	 * Get a formatted health report
	 *
	 * @return string HTML formatted health report
	 */
	public static function get_health_report() {
		$health = self::run_health_check();

		ob_start();
		?>
		<div class="tabesh-health-report">
			<h3>
				<?php
				if ( 'healthy' === $health['overall_status'] ) {
					echo '✓ ';
				} elseif ( 'warning' === $health['overall_status'] ) {
					echo '⚠ ';
				} else {
					echo '✗ ';
				}
				echo esc_html__( 'وضعیت سلامت سیستم قیمت‌گذاری', 'tabesh' );
				?>
			</h3>

			<?php if ( ! empty( $health['errors'] ) ) : ?>
				<div class="tabesh-health-errors">
					<h4><?php echo esc_html__( 'خطاهای حیاتی:', 'tabesh' ); ?></h4>
					<ul>
						<?php foreach ( $health['errors'] as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $health['warnings'] ) ) : ?>
				<div class="tabesh-health-warnings">
					<h4><?php echo esc_html__( 'هشدارها:', 'tabesh' ); ?></h4>
					<ul>
						<?php foreach ( $health['warnings'] as $warning ) : ?>
							<li><?php echo esc_html( $warning ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $health['recommendations'] ) ) : ?>
				<div class="tabesh-health-recommendations">
					<h4><?php echo esc_html__( 'توصیه‌ها:', 'tabesh' ); ?></h4>
					<ol>
						<?php foreach ( $health['recommendations'] as $recommendation ) : ?>
							<li><?php echo esc_html( $recommendation ); ?></li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
