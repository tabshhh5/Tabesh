<?php
/**
 * Hidden Orders Management Class
 *
 * Manages orders with @WAR# tag in notes field.
 * These orders are hidden from regular users but visible to admins and staff.
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tabesh_Hidden_Orders {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into WordPress filters
		add_filter( 'tabesh_user_orders_query', array( $this, 'filter_user_orders' ), 10, 2 );
		add_filter( 'tabesh_order_visible_to_user', array( $this, 'is_order_visible_to_user' ), 10, 2 );
		add_filter( 'tabesh_upload_panel_orders', array( $this, 'filter_upload_orders' ), 10, 2 );
	}

	/**
	 * Check if order is marked as hidden
	 *
	 * An order is hidden if its notes field contains @WAR# tag (case-insensitive)
	 *
	 * @param int $order_id Order ID
	 * @return bool True if order is hidden, false otherwise
	 */
	public function is_order_hidden( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT notes FROM $table WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return false;
		}

		// Check if notes contain @WAR# tag (case-insensitive)
		return ( stripos( $order->notes, '@WAR#' ) !== false );
	}

	/**
	 * Check if order is visible to user
	 *
	 * @param int $order_id Order ID
	 * @param int $user_id User ID
	 * @return bool True if visible, false if hidden
	 */
	public function is_order_visible_to_user( $order_id, $user_id ) {
		// If order is not hidden, it's visible to everyone
		if ( ! $this->is_order_hidden( $order_id ) ) {
			return true;
		}

		// Hidden orders are only visible to admins and staff
		return $this->user_can_see_hidden_orders( $user_id );
	}

	/**
	 * Check if user can see hidden orders
	 *
	 * @param int $user_id User ID
	 * @return bool True if user can see hidden orders
	 */
	public function user_can_see_hidden_orders( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		// Admins can see hidden orders
		if ( user_can( $user_id, 'manage_woocommerce' ) ) {
			return true;
		}

		// Staff with tabesh_staff_panel capability can see hidden orders
		if ( user_can( $user_id, 'tabesh_staff_panel' ) ) {
			return true;
		}

		// Check if user is in staff allowed list
		$staff_allowed = Tabesh()->get_setting( 'staff_allowed_users' );
		if ( ! empty( $staff_allowed ) ) {
			$staff_allowed_array = json_decode( $staff_allowed, true );
			if ( is_array( $staff_allowed_array ) && in_array( $user_id, $staff_allowed_array ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Filter user orders query to exclude hidden orders
	 *
	 * @param string $where WHERE clause
	 * @param int    $user_id User ID
	 * @return string Modified WHERE clause
	 */
	public function filter_user_orders( $where, $user_id ) {
		// If user can see hidden orders, don't filter
		if ( $this->user_can_see_hidden_orders( $user_id ) ) {
			return $where;
		}

		// Add condition to exclude orders with @WAR# in notes
		$where .= " AND (notes NOT LIKE '%@WAR#%' OR notes IS NULL)";

		return $where;
	}

	/**
	 * Filter orders list for upload panel
	 *
	 * @param array $orders Array of order objects
	 * @param int   $user_id User ID
	 * @return array Filtered orders array
	 */
	public function filter_upload_orders( $orders, $user_id ) {
		// If user can see hidden orders, don't filter
		if ( $this->user_can_see_hidden_orders( $user_id ) ) {
			return $orders;
		}

		// Filter out hidden orders
		return array_filter(
			$orders,
			function ( $order ) {
				return ! $this->is_order_hidden( $order->id );
			}
		);
	}

	/**
	 * Get list of hidden orders
	 *
	 * @param array $args Query arguments (limit, offset, user_id, status)
	 * @return array Array of hidden orders
	 */
	public function get_hidden_orders( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$defaults = array(
			'limit'    => 50,
			'offset'   => 0,
			'user_id'  => null,
			'status'   => null,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$sql    = "SELECT * FROM $table WHERE notes LIKE %s";
		$params = array( '%@WAR#%' );

		// Add user_id filter
		if ( $args['user_id'] ) {
			$sql     .= ' AND user_id = %d';
			$params[] = $args['user_id'];
		}

		// Add status filter
		if ( $args['status'] ) {
			$sql     .= ' AND status = %s';
			$params[] = $args['status'];
		}

		// Add ordering
		$allowed_order_by = array( 'id', 'created_at', 'updated_at', 'order_number' );
		$order_by         = in_array( $args['order_by'], $allowed_order_by ) ? $args['order_by'] : 'created_at';
		$order            = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$sql             .= " ORDER BY $order_by $order";

		// Add limit and offset
		$sql     .= ' LIMIT %d OFFSET %d';
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
	}

	/**
	 * Mark order as hidden by adding @WAR# tag to notes
	 *
	 * @param int    $order_id Order ID
	 * @param string $note Optional note to add with the tag
	 * @return bool True on success, false on failure
	 */
	public function mark_order_hidden( $order_id, $note = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// Get current notes
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT notes FROM $table WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return false;
		}

		// If already hidden, don't add tag again
		if ( $this->is_order_hidden( $order_id ) ) {
			return true;
		}

		// Add @WAR# tag to notes
		$new_notes = trim( $order->notes );
		if ( ! empty( $note ) ) {
			$new_notes .= ( $new_notes ? "\n" : '' ) . '@WAR# ' . sanitize_textarea_field( $note );
		} else {
			$new_notes .= ( $new_notes ? "\n" : '' ) . '@WAR#';
		}

		// Update notes
		$result = $wpdb->update(
			$table,
			array( 'notes' => $new_notes ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Log the action
		if ( $result !== false ) {
			$this->log_action( $order_id, 'marked_hidden', 'Order marked as hidden with @WAR# tag' );
		}

		return $result !== false;
	}

	/**
	 * Remove hidden marker from order
	 *
	 * @param int $order_id Order ID
	 * @return bool True on success, false on failure
	 */
	public function unmark_order_hidden( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		// Get current notes
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT notes FROM $table WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return false;
		}

		// Remove @WAR# tag and its line
		$notes_lines    = explode( "\n", $order->notes );
		$filtered_lines = array_filter(
			$notes_lines,
			function ( $line ) {
				return stripos( $line, '@WAR#' ) === false;
			}
		);
		$new_notes      = implode( "\n", $filtered_lines );

		// Update notes
		$result = $wpdb->update(
			$table,
			array( 'notes' => trim( $new_notes ) ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Log the action
		if ( $result !== false ) {
			$this->log_action( $order_id, 'unmarked_hidden', 'Order unmarked as hidden - @WAR# tag removed' );
		}

		return $result !== false;
	}

	/**
	 * Log action to database
	 *
	 * @param int    $order_id Order ID
	 * @param string $action Action name
	 * @param string $description Action description
	 * @return void
	 */
	private function log_action( $order_id, $action, $description ) {
		global $wpdb;
		$table_logs = $wpdb->prefix . 'tabesh_logs';

		$wpdb->insert(
			$table_logs,
			array(
				'order_id'    => $order_id,
				'user_id'     => get_current_user_id(),
				'action'      => $action,
				'description' => $description,
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}
}
