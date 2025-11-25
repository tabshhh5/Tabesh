<?php
/**
 * Print Subtasks Management Class
 *
 * Handles the generation, tracking, and management of print process subtasks.
 * This allows staff to track individual steps of the printing process.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_Print_Subtasks
 *
 * Manages print process subtasks for orders.
 * Generates, tracks, and updates individual printing steps.
 */
class Tabesh_Print_Subtasks {

	/**
	 * Subtask types with their display names and icons.
	 *
	 * @var array
	 */
	private static $subtask_types = array(
		'cover_print'      => array(
			'title' => 'چاپ جلد',
			'icon'  => '📕',
		),
		'cover_lamination' => array(
			'title' => 'سلفون جلد',
			'icon'  => '✨',
		),
		'content_print'    => array(
			'title' => 'چاپ متن کتاب',
			'icon'  => '📄',
		),
		'binding'          => array(
			'title' => 'صحافی',
			'icon'  => '📚',
		),
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into status change to auto-generate subtasks.
		add_action( 'tabesh_order_status_changed', array( $this, 'handle_status_change' ), 10, 2 );
	}

	/**
	 * Generate subtasks from order data
	 *
	 * Creates a subtasks structure based on order specifications.
	 * Called when order status changes to 'processing'.
	 *
	 * @param object $order Order object from database.
	 * @return array Subtasks array.
	 */
	public function generate_subtasks( $order ) {
		$subtasks = array();

		// 1. Cover Print - based on cover paper weight.
		if ( ! empty( $order->cover_paper_weight ) ) {
			$subtasks['cover_print'] = array(
				'completed' => false,
				'details'   => sprintf( '%s گرم', esc_html( $order->cover_paper_weight ) ),
				'title'     => self::$subtask_types['cover_print']['title'],
				'icon'      => self::$subtask_types['cover_print']['icon'],
			);
		}

		// 2. Cover Lamination - based on lamination type.
		if ( ! empty( $order->lamination_type ) && $order->lamination_type !== 'بدون سلفون' ) {
			$subtasks['cover_lamination'] = array(
				'completed' => false,
				'details'   => sprintf( 'سلفون %s', esc_html( $order->lamination_type ) ),
				'title'     => self::$subtask_types['cover_lamination']['title'],
				'icon'      => self::$subtask_types['cover_lamination']['icon'],
			);
		}

		// 3. Content Print - based on paper type and weight.
		if ( ! empty( $order->paper_type ) ) {
			$details = esc_html( $order->paper_type );
			if ( ! empty( $order->paper_weight ) ) {
				$details .= sprintf( ' %s گرم', esc_html( $order->paper_weight ) );
			}
			$subtasks['content_print'] = array(
				'completed' => false,
				'details'   => $details,
				'title'     => self::$subtask_types['content_print']['title'],
				'icon'      => self::$subtask_types['content_print']['icon'],
			);
		}

		// 4. Binding - based on binding type.
		if ( ! empty( $order->binding_type ) ) {
			$subtasks['binding'] = array(
				'completed' => false,
				'details'   => esc_html( $order->binding_type ),
				'title'     => self::$subtask_types['binding']['title'],
				'icon'      => self::$subtask_types['binding']['icon'],
			);
		}

		// 5. Extras - each extra as a separate subtask.
		$extras = maybe_unserialize( $order->extras );
		if ( is_array( $extras ) && ! empty( $extras ) ) {
			$subtasks['extras'] = array();
			foreach ( $extras as $extra ) {
				if ( ! empty( $extra ) ) {
					$subtasks['extras'][] = array(
						'name'      => esc_html( $extra ),
						'completed' => false,
					);
				}
			}
		}

		// Calculate initial progress.
		$progress = $this->calculate_progress( $subtasks );

		return array(
			'subtasks'         => $subtasks,
			'progress_percent' => $progress,
			'completed_at'     => null,
			'generated_at'     => current_time( 'mysql' ),
		);
	}

	/**
	 * Update subtask status
	 *
	 * Updates a specific subtask's completion status and recalculates progress.
	 *
	 * @param int    $order_id    Order ID.
	 * @param string $subtask_key Subtask key (e.g., 'cover_print', 'binding', or 'extras_0').
	 * @param bool   $completed   Completion status.
	 * @return array|WP_Error Updated subtasks data or error.
	 */
	public function update_subtask( $order_id, $subtask_key, $completed ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// Get current order from database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $order_id ) );

		if ( ! $order ) {
			return new WP_Error( 'order_not_found', __( 'سفارش یافت نشد', 'tabesh' ) );
		}

		// Get current subtasks from JSON field.
		$subtasks_data = json_decode( $order->print_subtasks, true );
		if ( ! $subtasks_data || ! isset( $subtasks_data['subtasks'] ) ) {
			return new WP_Error( 'no_subtasks', __( 'زیرمجموعه‌های چاپ یافت نشد', 'tabesh' ) );
		}

		// Update the specific subtask based on key.
		if ( strpos( $subtask_key, 'extras_' ) === 0 ) {
			// Handle extras array items.
			$index = intval( str_replace( 'extras_', '', $subtask_key ) );
			if ( isset( $subtasks_data['subtasks']['extras'][ $index ] ) ) {
				$subtasks_data['subtasks']['extras'][ $index ]['completed'] = (bool) $completed;
			}
		} elseif ( isset( $subtasks_data['subtasks'][ $subtask_key ] ) ) {
			$subtasks_data['subtasks'][ $subtask_key ]['completed'] = (bool) $completed;
		} else {
			return new WP_Error( 'subtask_not_found', __( 'زیرمجموعه یافت نشد', 'tabesh' ) );
		}

		// Recalculate progress percentage.
		$subtasks_data['progress_percent'] = $this->calculate_progress( $subtasks_data['subtasks'] );

		// Check if all subtasks are completed.
		$all_completed = $this->all_completed( $subtasks_data['subtasks'] );
		if ( $all_completed && empty( $subtasks_data['completed_at'] ) ) {
			$subtasks_data['completed_at'] = current_time( 'mysql' );
		} elseif ( ! $all_completed ) {
			$subtasks_data['completed_at'] = null;
		}

		// Save to database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array( 'print_subtasks' => wp_json_encode( $subtasks_data, JSON_UNESCAPED_UNICODE ) ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'خطا در به‌روزرسانی دیتابیس', 'tabesh' ) );
		}

		// Auto-change status to 'ready' if all completed.
		if ( $all_completed && $order->status === 'processing' ) {
			Tabesh()->order->update_status( $order_id, 'ready' );
			$subtasks_data['auto_status_changed'] = true;
		}

		return $subtasks_data;
	}

	/**
	 * Calculate progress percentage
	 *
	 * Calculates the completion percentage based on completed subtasks.
	 *
	 * @param array $subtasks Subtasks array.
	 * @return int Progress percentage (0-100).
	 */
	public function calculate_progress( $subtasks ) {
		$total     = 0;
		$completed = 0;

		foreach ( $subtasks as $key => $subtask ) {
			if ( $key === 'extras' && is_array( $subtask ) ) {
				foreach ( $subtask as $extra ) {
					++$total;
					if ( ! empty( $extra['completed'] ) ) {
						++$completed;
					}
				}
			} elseif ( is_array( $subtask ) && isset( $subtask['completed'] ) ) {
				++$total;
				if ( $subtask['completed'] ) {
					++$completed;
				}
			}
		}

		if ( $total === 0 ) {
			return 0;
		}

		return intval( round( ( $completed / $total ) * 100 ) );
	}

	/**
	 * Check if all subtasks completed
	 *
	 * Determines if every subtask in the array is marked as completed.
	 *
	 * @param array $subtasks Subtasks array.
	 * @return bool All completed.
	 */
	public function all_completed( $subtasks ) {
		foreach ( $subtasks as $key => $subtask ) {
			if ( $key === 'extras' && is_array( $subtask ) ) {
				foreach ( $subtask as $extra ) {
					if ( empty( $extra['completed'] ) ) {
						return false;
					}
				}
			} elseif ( is_array( $subtask ) && isset( $subtask['completed'] ) ) {
				if ( ! $subtask['completed'] ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Handle order status change
	 *
	 * When status changes to 'processing', auto-generate subtasks if not exists.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 */
	public function handle_status_change( $order_id, $new_status ) {
		if ( $new_status !== 'processing' ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// Get current order from database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $order_id ) );

		if ( ! $order ) {
			return;
		}

		// Only generate if subtasks don't exist yet.
		if ( ! empty( $order->print_subtasks ) ) {
			return;
		}

		// Generate subtasks from order data.
		$subtasks_data = $this->generate_subtasks( $order );

		// Save to database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'print_subtasks' => wp_json_encode( $subtasks_data, JSON_UNESCAPED_UNICODE ) ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( 'Tabesh: Generated print subtasks for order ' . $order_id );
		}
	}

	/**
	 * Get subtasks for an order
	 *
	 * Retrieves the subtasks data for a specific order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null Subtasks data or null if not found.
	 */
	public function get_order_subtasks( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT print_subtasks, status FROM $table WHERE id = %d", $order_id ) );

		if ( ! $order ) {
			return null;
		}

		// Return null if not in processing status.
		if ( $order->status !== 'processing' ) {
			return null;
		}

		$subtasks_data = json_decode( $order->print_subtasks, true );
		return $subtasks_data;
	}

	/**
	 * REST API: Get subtasks for an order
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function get_subtasks_rest( $request ) {
		$order_id = intval( $request->get_param( 'order_id' ) );

		if ( ! $order_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شناسه سفارش نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		$subtasks_data = $this->get_order_subtasks( $order_id );

		if ( $subtasks_data === null ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'زیرمجموعه‌ای یافت نشد', 'tabesh' ),
				),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'success'          => true,
				'subtasks'         => $subtasks_data['subtasks'] ?? array(),
				'progress_percent' => $subtasks_data['progress_percent'] ?? 0,
				'completed_at'     => $subtasks_data['completed_at'] ?? null,
			),
			200
		);
	}

	/**
	 * REST API: Update subtask status
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function update_subtask_rest( $request ) {
		$params      = $request->get_json_params();
		$order_id    = intval( $params['order_id'] ?? 0 );
		$subtask_key = sanitize_text_field( $params['subtask_key'] ?? '' );
		$completed   = (bool) ( $params['completed'] ?? false );

		if ( ! $order_id || ! $subtask_key ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'پارامترهای ناقص', 'tabesh' ),
				),
				400
			);
		}

		$result = $this->update_subtask( $order_id, $subtask_key, $completed );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		$response_data = array(
			'success'          => true,
			'message'          => __( 'وضعیت به‌روزرسانی شد', 'tabesh' ),
			'subtasks'         => $result['subtasks'] ?? array(),
			'progress_percent' => $result['progress_percent'] ?? 0,
			'completed_at'     => $result['completed_at'] ?? null,
		);

		// Include auto status change info if applicable.
		if ( ! empty( $result['auto_status_changed'] ) ) {
			$response_data['status_changed'] = true;
			$response_data['new_status']     = 'ready';
			$response_data['status_message'] = __( 'تمام مراحل تکمیل شد. وضعیت سفارش به "آماده تحویل" تغییر کرد.', 'tabesh' );
		}

		return new WP_REST_Response( $response_data, 200 );
	}
}
