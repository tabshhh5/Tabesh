<?php
/**
 * AI Behavior Tracker
 *
 * Tracks user behavior including scroll, clicks, form interactions,
 * page views, and referrers for AI-powered assistance.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Tracker
 *
 * Handles user behavior tracking
 */
class Tabesh_AI_Tracker {

	/**
	 * Log user behavior
	 *
	 * @param int    $user_id User ID (0 for guests).
	 * @param string $guest_uuid Guest UUID.
	 * @param string $event_type Event type.
	 * @param array  $event_data Event data.
	 * @return bool True on success, false on failure.
	 */
	public function log_behavior( $user_id, $guest_uuid, $event_type, $event_data ) {
		global $wpdb;

		// Check if tracking is enabled.
		if ( ! get_option( 'tabesh_ai_tracking_enabled', true ) ) {
			return false;
		}

		// Sanitize event type.
		$event_type = sanitize_text_field( $event_type );

		// Sanitize event data.
		$event_data = $this->sanitize_event_data( $event_data );

		// Get page URL and referrer from event data.
		$page_url = isset( $event_data['page_url'] ) ? esc_url_raw( $event_data['page_url'] ) : '';
		$referrer = isset( $event_data['referrer'] ) ? esc_url_raw( $event_data['referrer'] ) : '';

		// Prepare data for insertion.
		$data = array(
			'user_id'    => $user_id ? absint( $user_id ) : null,
			'guest_uuid' => $guest_uuid ? sanitize_text_field( $guest_uuid ) : null,
			'page_url'   => $page_url,
			'event_type' => $event_type,
			'event_data' => wp_json_encode( $event_data ),
			'referrer'   => $referrer,
			'created_at' => current_time( 'mysql' ),
		);

		$table_name = $wpdb->prefix . 'tabesh_ai_behavior_logs';

		// Insert into database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_name,
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result !== false;
	}

	/**
	 * Get user behavior history
	 *
	 * @param int    $user_id User ID.
	 * @param string $guest_uuid Guest UUID.
	 * @param int    $limit Number of records to retrieve.
	 * @return array Behavior history.
	 */
	public function get_behavior_history( $user_id = 0, $guest_uuid = '', $limit = 100 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_behavior_logs';

		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
					$user_id,
					$limit
				),
				ARRAY_A
			);
		} elseif ( $guest_uuid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE guest_uuid = %s ORDER BY created_at DESC LIMIT %d",
					$guest_uuid,
					$limit
				),
				ARRAY_A
			);
		} else {
			return array();
		}

		// Decode event_data JSON.
		if ( $results ) {
			foreach ( $results as &$result ) {
				$result['event_data'] = json_decode( $result['event_data'], true );
			}
		}

		return $results ? $results : array();
	}

	/**
	 * Get page visit count for user
	 *
	 * @param int    $user_id User ID.
	 * @param string $guest_uuid Guest UUID.
	 * @param string $page_url Page URL to count (optional).
	 * @return int Visit count.
	 */
	public function get_page_visit_count( $user_id = 0, $guest_uuid = '', $page_url = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_behavior_logs';

		if ( $user_id ) {
			if ( $page_url ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND event_type = 'page_view' AND page_url = %s",
						$user_id,
						$page_url
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND event_type = 'page_view'",
						$user_id
					)
				);
			}
		} elseif ( $guest_uuid ) {
			if ( $page_url ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table_name} WHERE guest_uuid = %s AND event_type = 'page_view' AND page_url = %s",
						$guest_uuid,
						$page_url
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table_name} WHERE guest_uuid = %s AND event_type = 'page_view'",
						$guest_uuid
					)
				);
			}
		} else {
			return 0;
		}

		return $count ? absint( $count ) : 0;
	}

	/**
	 * Clean up old behavior logs
	 *
	 * Removes logs older than specified days.
	 *
	 * @param int $days Days to keep.
	 * @return int Number of deleted rows.
	 */
	public function cleanup_old_logs( $days = 90 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_behavior_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $deleted ? absint( $deleted ) : 0;
	}

	/**
	 * Sanitize event data
	 *
	 * @param array $data Event data.
	 * @return array Sanitized data.
	 */
	private function sanitize_event_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_event_data( $value );
			} elseif ( is_string( $value ) ) {
				// Check if it's a URL.
				if ( in_array( $key, array( 'page_url', 'referrer', 'url' ), true ) ) {
					$sanitized[ $key ] = esc_url_raw( $value );
				} else {
					$sanitized[ $key ] = sanitize_text_field( $value );
				}
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = is_float( $value ) ? floatval( $value ) : absint( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = (bool) $value;
			}
		}

		return $sanitized;
	}
}
