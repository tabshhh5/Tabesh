<?php
/**
 * AI Permissions Management Class
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Permissions
 *
 * Manages AI system permissions and access control
 */
class Tabesh_AI_Permissions {

	/**
	 * Permission types
	 */
	const PERM_ORDERS      = 'access_orders';
	const PERM_USERS       = 'access_users';
	const PERM_PRICING     = 'access_pricing';
	const PERM_WOOCOMMERCE = 'access_woocommerce';

	/**
	 * Check if user has specific AI permission
	 *
	 * @param string $permission Permission to check.
	 * @param int    $user_id    User ID (optional, defaults to current user).
	 * @return bool True if user has permission, false otherwise.
	 */
	public static function user_can( $permission, $user_id = null ) {
		if ( ! Tabesh_AI_Config::is_enabled() ) {
			return false;
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		// Administrators always have access.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return true;
		}

		// Check global permission setting.
		$enabled = Tabesh_AI_Config::get( $permission, false );
		if ( ! $enabled ) {
			return false;
		}

		// Check role-specific permissions.
		$allowed_roles = Tabesh_AI_Config::get( 'allowed_roles', array() );
		if ( ! is_array( $allowed_roles ) ) {
			return false;
		}

		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $allowed_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user can access orders data
	 *
	 * @param int $user_id User ID (optional).
	 * @return bool True if user can access, false otherwise.
	 */
	public static function can_access_orders( $user_id = null ) {
		return self::user_can( self::PERM_ORDERS, $user_id );
	}

	/**
	 * Check if user can access users data
	 *
	 * @param int $user_id User ID (optional).
	 * @return bool True if user can access, false otherwise.
	 */
	public static function can_access_users( $user_id = null ) {
		return self::user_can( self::PERM_USERS, $user_id );
	}

	/**
	 * Check if user can access pricing data
	 *
	 * @param int $user_id User ID (optional).
	 * @return bool True if user can access, false otherwise.
	 */
	public static function can_access_pricing( $user_id = null ) {
		return self::user_can( self::PERM_PRICING, $user_id );
	}

	/**
	 * Check if user can access WooCommerce data
	 *
	 * @param int $user_id User ID (optional).
	 * @return bool True if user can access, false otherwise.
	 */
	public static function can_access_woocommerce( $user_id = null ) {
		return self::user_can( self::PERM_WOOCOMMERCE, $user_id );
	}

	/**
	 * Filter data based on user permissions
	 *
	 * @param array $data    Data to filter.
	 * @param int   $user_id User ID (optional).
	 * @return array Filtered data.
	 */
	public static function filter_data( $data, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$filtered = array();

		// Filter orders data.
		if ( isset( $data['orders'] ) && self::can_access_orders( $user_id ) ) {
			$filtered['orders'] = $data['orders'];
		}

		// Filter users data.
		if ( isset( $data['users'] ) && self::can_access_users( $user_id ) ) {
			$filtered['users'] = $data['users'];
		}

		// Filter pricing data.
		if ( isset( $data['pricing'] ) && self::can_access_pricing( $user_id ) ) {
			$filtered['pricing'] = $data['pricing'];
		}

		// Filter WooCommerce data.
		if ( isset( $data['woocommerce'] ) && self::can_access_woocommerce( $user_id ) ) {
			$filtered['woocommerce'] = $data['woocommerce'];
		}

		return $filtered;
	}

	/**
	 * Get user's accessible data types
	 *
	 * @param int $user_id User ID (optional).
	 * @return array Array of accessible data types.
	 */
	public static function get_accessible_data_types( $user_id = null ) {
		$accessible = array();

		if ( self::can_access_orders( $user_id ) ) {
			$accessible[] = 'orders';
		}

		if ( self::can_access_users( $user_id ) ) {
			$accessible[] = 'users';
		}

		if ( self::can_access_pricing( $user_id ) ) {
			$accessible[] = 'pricing';
		}

		if ( self::can_access_woocommerce( $user_id ) ) {
			$accessible[] = 'woocommerce';
		}

		return $accessible;
	}

	/**
	 * Log permission check for auditing
	 *
	 * @param string $permission Permission checked.
	 * @param int    $user_id    User ID.
	 * @param bool   $granted    Whether permission was granted.
	 * @return void
	 */
	public static function log_permission_check( $permission, $user_id, $granted ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		$username = $user ? $user->user_login : 'unknown';
		$status = $granted ? 'GRANTED' : 'DENIED';

		error_log(
			sprintf(
				'Tabesh AI Permission: %s - User: %s (ID: %d) - Permission: %s',
				$status,
				$username,
				$user_id,
				$permission
			)
		);
	}
}
