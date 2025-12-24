<?php
/**
 * AI User Profile Manager
 *
 * Manages user and guest profiles for AI-powered personalization.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_User_Profile
 *
 * Handles user and guest profile management
 */
class Tabesh_AI_User_Profile {

	/**
	 * Get user profile
	 *
	 * @param int $user_id User ID.
	 * @return array User profile data.
	 */
	public function get_user_profile( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_user_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $profile ) {
			// Create new profile.
			$profile = $this->create_user_profile( $user_id );
		} else {
			// Decode JSON fields.
			$profile = $this->decode_profile_json_fields( $profile );
		}

		return $profile;
	}

	/**
	 * Get guest profile
	 *
	 * @param string $guest_uuid Guest UUID.
	 * @return array Guest profile data.
	 */
	public function get_guest_profile( $guest_uuid ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_guest_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE guest_uuid = %s",
				$guest_uuid
			),
			ARRAY_A
		);

		if ( ! $profile ) {
			// Create new profile.
			$profile = $this->create_guest_profile( $guest_uuid );
		} else {
			// Decode JSON fields.
			$profile = $this->decode_profile_json_fields( $profile );
		}

		return $profile;
	}

	/**
	 * Create user profile
	 *
	 * @param int $user_id User ID.
	 * @return array Created profile.
	 */
	private function create_user_profile( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_user_profiles';
		$user       = get_userdata( $user_id );

		$data = array(
			'user_id'       => $user_id,
			'first_name'    => $user ? $user->first_name : '',
			'profession'    => null,
			'interests'     => wp_json_encode( array() ),
			'preferences'   => wp_json_encode( array() ),
			'behavior_data' => wp_json_encode( array() ),
			'chat_history'  => wp_json_encode( array() ),
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_name,
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $this->decode_profile_json_fields( $data );
	}

	/**
	 * Create guest profile
	 *
	 * @param string $guest_uuid Guest UUID.
	 * @return array Created profile.
	 */
	private function create_guest_profile( $guest_uuid ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_guest_profiles';

		// Set expiration to 90 days from now.
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+90 days' ) );

		$data = array(
			'guest_uuid'    => sanitize_text_field( $guest_uuid ),
			'name'          => null,
			'mobile'        => null,
			'profession'    => null,
			'interests'     => wp_json_encode( array() ),
			'preferences'   => wp_json_encode( array() ),
			'behavior_data' => wp_json_encode( array() ),
			'chat_history'  => wp_json_encode( array() ),
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
			'expires_at'    => $expires_at,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_name,
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $this->decode_profile_json_fields( $data );
	}

	/**
	 * Update user profession
	 *
	 * @param int    $user_id User ID.
	 * @param string $profession Profession.
	 * @return bool True on success, false on failure.
	 */
	public function update_user_profession( $user_id, $profession ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_user_profiles';

		// Ensure profile exists.
		$this->get_user_profile( $user_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array(
				'profession' => sanitize_text_field( $profession ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Update guest profession
	 *
	 * @param string $guest_uuid Guest UUID.
	 * @param string $profession Profession.
	 * @return bool True on success, false on failure.
	 */
	public function update_guest_profession( $guest_uuid, $profession ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_guest_profiles';

		// Ensure profile exists.
		$this->get_guest_profile( $guest_uuid );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array(
				'profession' => sanitize_text_field( $profession ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'guest_uuid' => $guest_uuid ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * Update chat history
	 *
	 * @param int    $user_id User ID.
	 * @param string $guest_uuid Guest UUID.
	 * @param array  $message Chat message.
	 * @return bool True on success, false on failure.
	 */
	public function add_chat_message( $user_id, $guest_uuid, $message ) {
		global $wpdb;

		if ( $user_id ) {
			$profile    = $this->get_user_profile( $user_id );
			$table_name = $wpdb->prefix . 'tabesh_ai_user_profiles';
			$where      = array( 'user_id' => $user_id );
			$where_fmt  = array( '%d' );
		} elseif ( $guest_uuid ) {
			$profile    = $this->get_guest_profile( $guest_uuid );
			$table_name = $wpdb->prefix . 'tabesh_ai_guest_profiles';
			$where      = array( 'guest_uuid' => $guest_uuid );
			$where_fmt  = array( '%s' );
		} else {
			return false;
		}

		// Add timestamp to message.
		$message['timestamp'] = current_time( 'mysql' );

		// Get existing chat history.
		$chat_history = isset( $profile['chat_history'] ) && is_array( $profile['chat_history'] )
			? $profile['chat_history']
			: array();

		// Add new message.
		$chat_history[] = $message;

		// Limit chat history to last 100 messages.
		if ( count( $chat_history ) > 100 ) {
			$chat_history = array_slice( $chat_history, -100 );
		}

		// Update profile.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array(
				'chat_history' => wp_json_encode( $chat_history ),
				'updated_at'   => current_time( 'mysql' ),
			),
			$where,
			array( '%s', '%s' ),
			$where_fmt
		);

		return $result !== false;
	}

	/**
	 * Clean up expired guest profiles
	 *
	 * @return int Number of deleted profiles.
	 */
	public function cleanup_expired_guests() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_guest_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			"DELETE FROM {$table_name} WHERE expires_at < NOW()"
		);

		return $deleted ? absint( $deleted ) : 0;
	}

	/**
	 * Decode JSON fields in profile
	 *
	 * @param array $profile Profile data.
	 * @return array Profile with decoded JSON fields.
	 */
	private function decode_profile_json_fields( $profile ) {
		$json_fields = array( 'interests', 'preferences', 'behavior_data', 'chat_history' );

		foreach ( $json_fields as $field ) {
			if ( isset( $profile[ $field ] ) && is_string( $profile[ $field ] ) ) {
				$decoded           = json_decode( $profile[ $field ], true );
				$profile[ $field ] = is_array( $decoded ) ? $decoded : array();
			} elseif ( ! isset( $profile[ $field ] ) ) {
				$profile[ $field ] = array();
			}
		}

		return $profile;
	}
}
