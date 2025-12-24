<?php
/**
 * AI Configuration Management Class
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Config
 *
 * Manages AI system configuration and settings
 */
class Tabesh_AI_Config {

	/**
	 * AI mode constants
	 */
	const MODE_DIRECT = 'direct';
	const MODE_SERVER = 'server';
	const MODE_CLIENT = 'client';

	/**
	 * Default settings
	 *
	 * @var array
	 */
	private static $defaults = array(
		'enabled'                => false,
		'mode'                   => self::MODE_DIRECT,
		'gemini_api_key'         => '',
		'gemini_model'           => 'gemini-2.0-flash-exp',
		'server_url'             => '',
		'server_api_key'         => '',
		'access_orders'          => true,
		'access_users'           => false,
		'access_pricing'         => true,
		'access_woocommerce'     => false,
		'cache_enabled'          => true,
		'cache_ttl'              => 3600,
		'max_tokens'             => 2048,
		'temperature'            => 0.7,
		'allowed_roles'          => array( 'administrator', 'shop_manager', 'customer' ),
	);

	/**
	 * Get AI configuration setting
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting not found.
	 * @return mixed Setting value.
	 */
	public static function get( $key, $default = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		$setting_key = 'ai_' . $key;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table WHERE setting_key = %s",
				$setting_key
			)
		);

		if ( null !== $result ) {
			// Decode JSON if it's a JSON string.
			$decoded = json_decode( $result, true );
			return ( null !== $decoded ) ? $decoded : $result;
		}

		// Return default if provided, otherwise from defaults array.
		if ( null !== $default ) {
			return $default;
		}

		return isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : null;
	}

	/**
	 * Set AI configuration setting
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public static function set( $key, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		$setting_key = 'ai_' . $key;

		// Encode arrays and objects as JSON.
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		}

		// Check if setting exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE setting_key = %s",
				$setting_key
			)
		);

		if ( $exists ) {
			// Update existing setting.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				array(
					'setting_value' => $value,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'setting_key' => $setting_key ),
				array( '%s', '%s' ),
				array( '%s' )
			);
		} else {
			// Insert new setting.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->insert(
				$table,
				array(
					'setting_key'   => $setting_key,
					'setting_value' => $value,
					'setting_type'  => 'string',
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Check if AI system is enabled
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_enabled() {
		return (bool) self::get( 'enabled', false );
	}

	/**
	 * Get current AI mode
	 *
	 * @return string AI mode (direct, server, or client).
	 */
	public static function get_mode() {
		$mode = self::get( 'mode', self::MODE_DIRECT );
		
		// Validate mode.
		$valid_modes = array( self::MODE_DIRECT, self::MODE_SERVER, self::MODE_CLIENT );
		if ( ! in_array( $mode, $valid_modes, true ) ) {
			return self::MODE_DIRECT;
		}

		return $mode;
	}

	/**
	 * Check if current user has access to AI
	 *
	 * @return bool True if user has access, false otherwise.
	 */
	public static function user_has_access() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$allowed_roles = self::get( 'allowed_roles', self::$defaults['allowed_roles'] );
		if ( ! is_array( $allowed_roles ) ) {
			$allowed_roles = self::$defaults['allowed_roles'];
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		// Check if user has any of the allowed roles.
		$user_roles = $user->roles;
		foreach ( $user_roles as $role ) {
			if ( in_array( $role, $allowed_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get Gemini API key
	 *
	 * @return string API key or empty string.
	 */
	public static function get_gemini_api_key() {
		return self::get( 'gemini_api_key', '' );
	}

	/**
	 * Get all settings as array
	 *
	 * @return array All AI settings.
	 */
	public static function get_all() {
		$settings = array();
		foreach ( self::$defaults as $key => $default ) {
			$settings[ $key ] = self::get( $key, $default );
		}
		return $settings;
	}

	/**
	 * Reset settings to defaults
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function reset() {
		foreach ( self::$defaults as $key => $value ) {
			self::set( $key, $value );
		}
		return true;
	}

	/**
	 * Validate API key format
	 *
	 * @param string $api_key API key to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_api_key( $api_key ) {
		// Gemini API keys typically start with "AIza" and are 39 characters long.
		if ( empty( $api_key ) ) {
			return false;
		}

		// Basic format validation.
		if ( strlen( $api_key ) < 20 ) {
			return false;
		}

		return true;
	}
}
