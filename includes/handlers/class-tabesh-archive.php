<?php
/**
 * Archive Management Class
 *
 * Manages archived and cancelled orders functionality.
 * Provides auto-archiving on status change and reorder capabilities.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_Archive
 *
 * Handles order archiving and cancellation management.
 */
class Tabesh_Archive {

	/**
	 * Active order statuses that should NOT be archived.
	 *
	 * @var array
	 */
	const ACTIVE_STATUSES = array( 'pending', 'confirmed', 'printing', 'ready' );

	/**
	 * Statuses that trigger automatic archiving.
	 *
	 * @var array
	 */
	const ARCHIVE_STATUSES = array( 'delivered', 'completed', 'cancelled' );

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into status changes for auto-archiving.
		add_action( 'tabesh_order_status_changed', array( $this, 'maybe_auto_archive' ), 10, 2 );

		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/archive/archived',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_archived_orders' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/archive/cancelled',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_cancelled_orders' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/archive/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_reorder' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Check if user has admin permission
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Auto-archive order when status changes to delivered or cancelled
	 *
	 * @param int    $order_id Order ID.
	 * @param string $new_status New status.
	 * @return void
	 */
	public function maybe_auto_archive( $order_id, $new_status ) {
		// Normalize status names (completed = delivered for archiving purposes).
		$archive_trigger_statuses = array( 'delivered', 'completed', 'cancelled' );

		if ( in_array( $new_status, $archive_trigger_statuses, true ) ) {
			$this->archive_order( $order_id );
		} elseif ( in_array( $new_status, self::ACTIVE_STATUSES, true ) ) {
			// If status is changed back to active, unarchive the order.
			$this->unarchive_order( $order_id );
		}
	}

	/**
	 * Archive an order
	 *
	 * @param int $order_id Order ID.
	 * @return bool True on success, false on failure.
	 */
	public function archive_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update required for order archiving.
		$result = $wpdb->update(
			$table,
			array(
				'archived'    => 1,
				'archived_at' => current_time( 'mysql' ),
			),
			array( 'id' => intval( $order_id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Log the archive action.
			$this->log_archive_action( $order_id, 'archived' );
			return true;
		}

		return false;
	}

	/**
	 * Unarchive an order (restore to active)
	 *
	 * @param int $order_id Order ID.
	 * @return bool True on success, false on failure.
	 */
	public function unarchive_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update required for order unarchiving.
		$result = $wpdb->update(
			$table,
			array(
				'archived'    => 0,
				'archived_at' => null,
			),
			array( 'id' => intval( $order_id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Log the unarchive action.
			$this->log_archive_action( $order_id, 'unarchived' );
			return true;
		}

		return false;
	}

	/**
	 * Log archive/unarchive action
	 *
	 * @param int    $order_id Order ID.
	 * @param string $action Action type (archived/unarchived).
	 * @return void
	 */
	private function log_archive_action( $order_id, $action ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'tabesh_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for logging.
		$wpdb->insert(
			$logs_table,
			array(
				'order_id'    => intval( $order_id ),
				'user_id'     => get_current_user_id(),
				'action'      => 'order_' . $action,
				'description' => sprintf(
					/* translators: %s: Order ID */
					__( 'سفارش شماره %s بایگانی/بازگردانی شد', 'tabesh' ),
					$order_id
				),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get archived (delivered) orders
	 *
	 * @param int    $page Page number.
	 * @param int    $per_page Items per page.
	 * @param string $search Search term.
	 * @return array Orders and pagination info.
	 */
	public function get_archived_orders( $page = 1, $per_page = 20, $search = '' ) {
		return $this->get_orders_by_archive_status( true, array( 'delivered', 'completed' ), $page, $per_page, $search );
	}

	/**
	 * Get cancelled orders
	 *
	 * @param int    $page Page number.
	 * @param int    $per_page Items per page.
	 * @param string $search Search term.
	 * @return array Orders and pagination info.
	 */
	public function get_cancelled_orders( $page = 1, $per_page = 20, $search = '' ) {
		return $this->get_orders_by_archive_status( true, array( 'cancelled' ), $page, $per_page, $search );
	}

	/**
	 * Get orders by archive status and order status
	 *
	 * @param bool   $archived Whether to get archived orders.
	 * @param array  $statuses Order statuses to filter.
	 * @param int    $page Page number.
	 * @param int    $per_page Items per page.
	 * @param string $search Search term.
	 * @return array Orders and pagination info.
	 */
	private function get_orders_by_archive_status( $archived, $statuses, $page = 1, $per_page = 20, $search = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$where_clauses = array();
		$params        = array();

		// Archive status filter.
		$where_clauses[] = 'archived = %d';
		$params[]        = $archived ? 1 : 0;

		// Status filter.
		if ( ! empty( $statuses ) ) {
			$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$where_clauses[]     = "status IN ($status_placeholders)";
			$params              = array_merge( $params, $statuses );
		}

		// Search filter.
		if ( ! empty( $search ) ) {
			$search_like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(order_number LIKE %s OR book_title LIKE %s)';
			$params[]        = $search_like;
			$params[]        = $search_like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is safe, direct query required.
		$count_query = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared below.
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $params ) );

		// Calculate pagination.
		$offset      = ( $page - 1 ) * $per_page;
		$total_pages = ceil( $total / $per_page );

		// Get orders with pagination.
		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is safe, direct query required.
		$query = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared below.
		$orders = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		return array(
			'orders'       => $orders,
			'total'        => $total,
			'total_pages'  => $total_pages,
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Create a new order based on an existing order (reorder)
	 *
	 * @param int $source_order_id Source order ID to copy from.
	 * @param int $new_user_id User ID for the new order.
	 * @return int|WP_Error New order ID or error.
	 */
	public function reorder( $source_order_id, $new_user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// Get the source order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required.
		$source_order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				intval( $source_order_id )
			)
		);

		if ( ! $source_order ) {
			return new WP_Error( 'order_not_found', __( 'سفارش مورد نظر یافت نشد', 'tabesh' ) );
		}

		// Validate the new user.
		$user = get_userdata( $new_user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'کاربر مورد نظر یافت نشد', 'tabesh' ) );
		}

		// Generate new order number.
		$order_number = 'TB-' . gmdate( 'Ymd' ) . '-' . str_pad( wp_rand( 1, 9999 ), 4, '0', STR_PAD_LEFT );

		// Prepare new order data.
		$new_order_data = array(
			'user_id'            => intval( $new_user_id ),
			'order_number'       => $order_number,
			'book_title'         => $source_order->book_title,
			'book_size'          => $source_order->book_size,
			'paper_type'         => $source_order->paper_type,
			'paper_weight'       => $source_order->paper_weight,
			'print_type'         => $source_order->print_type,
			'page_count_color'   => $source_order->page_count_color,
			'page_count_bw'      => $source_order->page_count_bw,
			'page_count_total'   => $source_order->page_count_total,
			'quantity'           => $source_order->quantity,
			'binding_type'       => $source_order->binding_type,
			'license_type'       => $source_order->license_type,
			'cover_paper_type'   => $source_order->cover_paper_type,
			'cover_paper_weight' => $source_order->cover_paper_weight,
			'lamination_type'    => $source_order->lamination_type,
			'extras'             => $source_order->extras,
			'total_price'        => $source_order->total_price,
			'status'             => 'pending',
			'notes'              => sprintf(
				/* translators: %s: Source order number */
				__( 'سفارش مجدد از سفارش شماره %s', 'tabesh' ),
				$source_order->order_number
			),
			'archived'           => 0,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required.
		$result = $wpdb->insert( $table, $new_order_data );

		if ( $result === false ) {
			return new WP_Error( 'insert_failed', __( 'خطا در ایجاد سفارش جدید', 'tabesh' ) );
		}

		$new_order_id = $wpdb->insert_id;

		// Log the reorder action.
		$logs_table = $wpdb->prefix . 'tabesh_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for logging.
		$wpdb->insert(
			$logs_table,
			array(
				'order_id'    => $new_order_id,
				'user_id'     => $new_user_id,
				'action'      => 'order_reorder',
				'description' => sprintf(
					/* translators: 1: New order ID, 2: Source order number */
					__( 'سفارش جدید %1$s از سفارش %2$s ایجاد شد', 'tabesh' ),
					$new_order_id,
					$source_order->order_number
				),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return $new_order_id;
	}

	/**
	 * REST API: Get archived orders
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_archived_orders( $request ) {
		$page     = max( 1, intval( $request->get_param( 'page' ) ?? 1 ) );
		$per_page = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		$result = $this->get_archived_orders( $page, $per_page, $search );

		// Format orders with user info.
		$formatted_orders = $this->format_orders_with_user_info( $result['orders'] );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'orders'       => $formatted_orders,
					'total'        => $result['total'],
					'total_pages'  => $result['total_pages'],
					'current_page' => $result['current_page'],
				),
			),
			200
		);
	}

	/**
	 * REST API: Get cancelled orders
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_cancelled_orders( $request ) {
		$page     = max( 1, intval( $request->get_param( 'page' ) ?? 1 ) );
		$per_page = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		$result = $this->get_cancelled_orders( $page, $per_page, $search );

		// Format orders with user info.
		$formatted_orders = $this->format_orders_with_user_info( $result['orders'] );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'orders'       => $formatted_orders,
					'total'        => $result['total'],
					'total_pages'  => $result['total_pages'],
					'current_page' => $result['current_page'],
				),
			),
			200
		);
	}

	/**
	 * REST API: Create reorder
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_reorder( $request ) {
		$params          = $request->get_json_params();
		$source_order_id = intval( $params['order_id'] ?? 0 );
		$new_user_id     = intval( $params['user_id'] ?? 0 );

		if ( $source_order_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شناسه سفارش نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		if ( $new_user_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شناسه کاربر نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		$result = $this->reorder( $source_order_id, $new_user_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'message'      => __( 'سفارش جدید با موفقیت ایجاد شد', 'tabesh' ),
				'new_order_id' => $result,
			),
			201
		);
	}

	/**
	 * Format orders with user information
	 *
	 * @param array $orders Array of order objects.
	 * @return array Formatted orders.
	 */
	private function format_orders_with_user_info( $orders ) {
		$formatted = array();

		foreach ( $orders as $order ) {
			$user        = get_userdata( $order->user_id );
			$formatted[] = array(
				'id'            => (int) $order->id,
				'order_number'  => $order->order_number,
				'book_title'    => $order->book_title,
				'book_size'     => $order->book_size,
				'quantity'      => (int) $order->quantity,
				'total_price'   => (float) $order->total_price,
				'status'        => $order->status,
				'customer_name' => $user ? $user->display_name : __( 'نامشخص', 'tabesh' ),
				'user_id'       => (int) $order->user_id,
				'created_at'    => $order->created_at,
				'archived_at'   => $order->archived_at ?? null,
			);
		}

		return $formatted;
	}

	/**
	 * Archive existing delivered/cancelled orders (migration)
	 *
	 * This method archives all orders that have delivered or cancelled status
	 * but are not yet marked as archived.
	 *
	 * @return int Number of orders archived.
	 */
	public function archive_existing_orders() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// Archive all delivered/completed/cancelled orders that aren't archived yet.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for bulk update.
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"UPDATE $table SET archived = 1, archived_at = %s WHERE archived = 0 AND status IN ('delivered', 'completed', 'cancelled')",
				current_time( 'mysql' )
			)
		);

		return $result !== false ? $result : 0;
	}
}
