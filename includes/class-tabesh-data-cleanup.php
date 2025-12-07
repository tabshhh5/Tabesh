<?php
/**
 * Data Cleanup Management Class
 *
 * Handles data cleanup operations including factory reset, selective cleanup,
 * user data cleanup, and order cleanup with safe file deletion from disk and FTP
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tabesh_Data_Cleanup {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialization
	}

	/**
	 * Factory reset - Delete all data
	 *
	 * @param bool $keep_settings Whether to keep settings
	 * @return array Result with success status and statistics
	 */
	public function factory_reset( $keep_settings = false ) {
		global $wpdb;

		try {
			$stats = array(
				'orders_deleted'         => 0,
				'files_deleted'          => 0,
				'logs_deleted'           => 0,
				'settings_deleted'       => 0,
				'physical_files_deleted' => 0,
				'errors'                 => array(),
			);

			// Delete all physical files first
			$files = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}tabesh_files",
				ARRAY_A
			);

			foreach ( $files as $file ) {
				if ( $this->delete_physical_file( $file['file_path'] ) ) {
					++$stats['physical_files_deleted'];
				}

				// Delete from FTP if exists
				if ( ! empty( $file['ftp_path'] ) ) {
					$this->delete_ftp_file( $file['ftp_path'] );
				}
			}

			// Delete all orders
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['orders_deleted'] = $wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_orders" );

			// Delete all files metadata
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['files_deleted'] = $wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_files" );

			// Delete file versions
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_file_versions" );

			// Delete upload tasks
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_upload_tasks" );

			// Delete file comments
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_file_comments" );

			// Delete document metadata
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_document_metadata" );

			// Delete download tokens
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_download_tokens" );

			// Delete security logs
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_security_logs" );

			// Delete all logs
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['logs_deleted'] = $wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_logs" );

			// Delete settings if requested
			if ( ! $keep_settings ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$stats['settings_deleted'] = $wpdb->query( "DELETE FROM {$wpdb->prefix}tabesh_settings" );
			}

			// Reset auto-increment counters
			$this->reset_auto_increment();

			// Delete upload directory
			$this->delete_upload_directory();

			// Clear settings cache
			Tabesh::clear_settings_cache();

			// Log the factory reset
			$this->log_cleanup_action( 'factory_reset', 'Factory reset completed', $stats );

			return array(
				'success' => true,
				'message' => __( 'بازنشانی کارخانه با موفقیت انجام شد', 'tabesh' ),
				'stats'   => $stats,
			);

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh Factory Reset Error: ' . $e->getMessage() );
			}
			return array(
				'success' => false,
				'message' => __( 'خطا در بازنشانی کارخانه: ', 'tabesh' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Selective cleanup - Delete data based on filters
	 *
	 * @param array $options Cleanup options
	 * @return array Result with success status and statistics
	 */
	public function selective_cleanup( $options = array() ) {
		global $wpdb;

		try {
			$defaults = array(
				'cleanup_orders'   => false,
				'cleanup_files'    => false,
				'cleanup_logs'     => false,
				'cleanup_settings' => false,
				'date_from'        => null,
				'date_to'          => null,
				'user_id'          => null,
				'status'           => null,
				'setting_keys'     => array(),
			);

			$options = wp_parse_args( $options, $defaults );

			$stats = array(
				'orders_deleted'         => 0,
				'files_deleted'          => 0,
				'logs_deleted'           => 0,
				'settings_deleted'       => 0,
				'physical_files_deleted' => 0,
			);

			// Cleanup orders
			if ( $options['cleanup_orders'] ) {
				$result                  = $this->cleanup_orders_by_filter( $options );
				$stats['orders_deleted'] = $result['deleted'];
			}

			// Cleanup files
			if ( $options['cleanup_files'] ) {
				$result                          = $this->cleanup_files_by_filter( $options );
				$stats['files_deleted']          = $result['deleted'];
				$stats['physical_files_deleted'] = $result['physical_deleted'];
			}

			// Cleanup logs
			if ( $options['cleanup_logs'] ) {
				$result                = $this->cleanup_logs_by_filter( $options );
				$stats['logs_deleted'] = $result['deleted'];
			}

			// Cleanup settings
			if ( $options['cleanup_settings'] && ! empty( $options['setting_keys'] ) ) {
				$result                    = $this->cleanup_settings_by_keys( $options['setting_keys'] );
				$stats['settings_deleted'] = $result['deleted'];
			}

			// Clear settings cache
			Tabesh::clear_settings_cache();

			// Log the cleanup
			$this->log_cleanup_action( 'selective_cleanup', 'Selective cleanup completed', $stats );

			return array(
				'success' => true,
				'message' => __( 'پاکسازی انتخابی با موفقیت انجام شد', 'tabesh' ),
				'stats'   => $stats,
			);

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh Selective Cleanup Error: ' . $e->getMessage() );
			}
			return array(
				'success' => false,
				'message' => __( 'خطا در پاکسازی انتخابی: ', 'tabesh' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Delete user data completely
	 *
	 * @param int  $user_id User ID
	 * @param bool $delete_account Whether to delete user account
	 * @return array Result with success status and statistics
	 */
	public function delete_user_data( $user_id, $delete_account = false ) {
		global $wpdb;

		try {
			$user_id = intval( $user_id );
			if ( $user_id <= 0 ) {
				throw new Exception( __( 'شناسه کاربر نامعتبر است', 'tabesh' ) );
			}

			$stats = array(
				'orders_deleted'         => 0,
				'files_deleted'          => 0,
				'logs_deleted'           => 0,
				'physical_files_deleted' => 0,
				'account_deleted'        => false,
			);

			// Get all user files for physical deletion
			$files = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}tabesh_files WHERE user_id = %d",
					$user_id
				),
				ARRAY_A
			);

			// Delete physical files
			foreach ( $files as $file ) {
				if ( $this->delete_physical_file( $file['file_path'] ) ) {
					++$stats['physical_files_deleted'];
				}

				// Delete from FTP if exists
				if ( ! empty( $file['ftp_path'] ) ) {
					$this->delete_ftp_file( $file['ftp_path'] );
				}
			}

			// Delete user orders
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['orders_deleted'] = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_orders WHERE user_id = %d",
					$user_id
				)
			);

			// Delete user files metadata
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['files_deleted'] = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_files WHERE user_id = %d",
					$user_id
				)
			);

			// Delete user logs
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['logs_deleted'] = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_logs WHERE user_id = %d",
					$user_id
				)
			);

			// Delete user account if requested
			if ( $delete_account ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				if ( wp_delete_user( $user_id ) ) {
					$stats['account_deleted'] = true;
				}
			}

			// Log the cleanup
			$this->log_cleanup_action( 'delete_user_data', "User data deleted for user ID: $user_id", $stats );

			return array(
				'success' => true,
				'message' => __( 'دادهای کاربر با موفقیت حذف شد', 'tabesh' ),
				'stats'   => $stats,
			);

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh Delete User Data Error: ' . $e->getMessage() );
			}
			return array(
				'success' => false,
				'message' => __( 'خطا در حذف دادهای کاربر: ', 'tabesh' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Delete order completely with all associated data
	 *
	 * @param int $order_id Order ID
	 * @return array Result with success status and statistics
	 */
	public function delete_order_completely( $order_id ) {
		global $wpdb;

		try {
			$order_id = intval( $order_id );
			if ( $order_id <= 0 ) {
				throw new Exception( __( 'شناسه سفارش نامعتبر است', 'tabesh' ) );
			}

			$stats = array(
				'order_deleted'          => false,
				'files_deleted'          => 0,
				'logs_deleted'           => 0,
				'physical_files_deleted' => 0,
			);

			// Get all order files for physical deletion
			$files = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}tabesh_files WHERE order_id = %d",
					$order_id
				),
				ARRAY_A
			);

			// Delete physical files
			foreach ( $files as $file ) {
				if ( $this->delete_physical_file( $file['file_path'] ) ) {
					++$stats['physical_files_deleted'];
				}

				// Delete from FTP if exists
				if ( ! empty( $file['ftp_path'] ) ) {
					$this->delete_ftp_file( $file['ftp_path'] );
				}
			}

			// Delete order files metadata
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['files_deleted'] = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_files WHERE order_id = %d",
					$order_id
				)
			);

			// Delete file versions (two-step process to avoid MySQL subquery limitation)
			$file_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}tabesh_files WHERE order_id = %d",
					$order_id
				)
			);
			
			if ( ! empty( $file_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $file_ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}tabesh_file_versions WHERE file_id IN ($placeholders)",
						...$file_ids
					)
				);
			}

			// Delete upload tasks
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_upload_tasks WHERE order_id = %d",
					$order_id
				)
			);

			// Delete order logs
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['logs_deleted'] = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_logs WHERE order_id = %d",
					$order_id
				)
			);

			// Delete order
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_orders WHERE id = %d",
					$order_id
				)
			);

			$stats['order_deleted'] = ( $result > 0 );

			// Log the cleanup
			$this->log_cleanup_action( 'delete_order', "Order deleted: $order_id", $stats );

			return array(
				'success' => true,
				'message' => __( 'سفارش با موفقیت حذف شد', 'tabesh' ),
				'stats'   => $stats,
			);

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh Delete Order Error: ' . $e->getMessage() );
			}
			return array(
				'success' => false,
				'message' => __( 'خطا در حذف سفارش: ', 'tabesh' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Cleanup orders by filter
	 *
	 * @param array $options Filter options
	 * @return array Result with deleted count
	 */
	private function cleanup_orders_by_filter( $options ) {
		global $wpdb;

		$sql    = "DELETE FROM {$wpdb->prefix}tabesh_orders WHERE 1=1";
		$params = array();

		// Apply date filters
		if ( ! empty( $options['date_from'] ) ) {
			$sql     .= ' AND created_at >= %s';
			$params[] = $options['date_from'];
		}

		if ( ! empty( $options['date_to'] ) ) {
			$sql     .= ' AND created_at <= %s';
			$params[] = $options['date_to'];
		}

		// Apply user filter
		if ( ! empty( $options['user_id'] ) ) {
			$sql     .= ' AND user_id = %d';
			$params[] = $options['user_id'];
		}

		// Apply status filter
		if ( ! empty( $options['status'] ) ) {
			$sql     .= ' AND status = %s';
			$params[] = $options['status'];
		}

		// Execute query
		if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$deleted = $wpdb->query( $wpdb->prepare( $sql, ...$params ) );
		} else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$deleted = $wpdb->query( $sql );
		}

		return array( 'deleted' => $deleted );
	}

	/**
	 * Cleanup files by filter
	 *
	 * @param array $options Filter options
	 * @return array Result with deleted count
	 */
	private function cleanup_files_by_filter( $options ) {
		global $wpdb;

		// Get files first for physical deletion
		$sql    = "SELECT * FROM {$wpdb->prefix}tabesh_files WHERE 1=1";
		$params = array();

		// Apply date filters
		if ( ! empty( $options['date_from'] ) ) {
			$sql     .= ' AND created_at >= %s';
			$params[] = $options['date_from'];
		}

		if ( ! empty( $options['date_to'] ) ) {
			$sql     .= ' AND created_at <= %s';
			$params[] = $options['date_to'];
		}

		// Apply user filter
		if ( ! empty( $options['user_id'] ) ) {
			$sql     .= ' AND user_id = %d';
			$params[] = $options['user_id'];
		}

		// Get files
		if ( ! empty( $params ) ) {
			$files = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$files = $wpdb->get_results( $sql, ARRAY_A );
		}

		$physical_deleted = 0;

		// Delete physical files
		foreach ( $files as $file ) {
			if ( $this->delete_physical_file( $file['file_path'] ) ) {
				++$physical_deleted;
			}

			// Delete from FTP if exists
			if ( ! empty( $file['ftp_path'] ) ) {
				$this->delete_ftp_file( $file['ftp_path'] );
			}
		}

		// Delete metadata
		$delete_sql    = "DELETE FROM {$wpdb->prefix}tabesh_files WHERE 1=1";
		$delete_params = array();

		if ( ! empty( $options['date_from'] ) ) {
			$delete_sql     .= ' AND created_at >= %s';
			$delete_params[] = $options['date_from'];
		}

		if ( ! empty( $options['date_to'] ) ) {
			$delete_sql     .= ' AND created_at <= %s';
			$delete_params[] = $options['date_to'];
		}

		if ( ! empty( $options['user_id'] ) ) {
			$delete_sql     .= ' AND user_id = %d';
			$delete_params[] = $options['user_id'];
		}

		// Execute delete query
		if ( ! empty( $delete_params ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$deleted = $wpdb->query( $wpdb->prepare( $delete_sql, ...$delete_params ) );
		} else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$deleted = $wpdb->query( $delete_sql );
		}

		return array(
			'deleted'          => $deleted,
			'physical_deleted' => $physical_deleted,
		);
	}

	/**
	 * Cleanup logs by filter
	 *
	 * @param array $options Filter options
	 * @return array Result with deleted count
	 */
	private function cleanup_logs_by_filter( $options ) {
		global $wpdb;

		$sql    = "DELETE FROM {$wpdb->prefix}tabesh_logs WHERE 1=1";
		$params = array();

		// Apply date filters
		if ( ! empty( $options['date_from'] ) ) {
			$sql     .= ' AND created_at >= %s';
			$params[] = $options['date_from'];
		}

		if ( ! empty( $options['date_to'] ) ) {
			$sql     .= ' AND created_at <= %s';
			$params[] = $options['date_to'];
		}

		// Apply user filter
		if ( ! empty( $options['user_id'] ) ) {
			$sql     .= ' AND user_id = %d';
			$params[] = $options['user_id'];
		}

		// Execute query
		if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$deleted = $wpdb->query( $wpdb->prepare( $sql, ...$params ) );
		} else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$deleted = $wpdb->query( $sql );
		}

		return array( 'deleted' => $deleted );
	}

	/**
	 * Cleanup settings by keys
	 *
	 * @param array $keys Setting keys to delete
	 * @return array Result with deleted count
	 */
	private function cleanup_settings_by_keys( $keys ) {
		global $wpdb;

		$deleted = 0;
		
		// Whitelist of deletable settings to prevent accidental deletion of critical settings
		$protected_settings = array(
			'ftp_username',
			'ftp_password',
			'ftp_host',
			'sms_username',
			'sms_password',
		);
		
		foreach ( $keys as $key ) {
			$sanitized_key = sanitize_text_field( $key );
			
			// Skip if key is empty or in protected list
			if ( empty( $sanitized_key ) || in_array( $sanitized_key, $protected_settings, true ) ) {
				continue;
			}
			
			// Validate key format (alphanumeric, underscores, and hyphens only)
			if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $sanitized_key ) ) {
				continue;
			}
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}tabesh_settings WHERE setting_key = %s",
					$sanitized_key
				)
			);
			if ( $result ) {
				++$deleted;
			}
		}

		return array( 'deleted' => $deleted );
	}

	/**
	 * Delete physical file from disk
	 *
	 * @param string $file_path File path
	 * @return bool True on success, false on failure
	 */
	private function delete_physical_file( $file_path ) {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return false;
		}

		return @unlink( $file_path );
	}

	/**
	 * Delete file from FTP server
	 *
	 * @param string $ftp_path FTP file path
	 * @return bool True on success, false on failure
	 */
	private function delete_ftp_file( $ftp_path ) {
		if ( empty( $ftp_path ) ) {
			return false;
		}

		// Get FTP settings
		$ftp_enabled = Tabesh()->get_setting( 'ftp_enabled' );
		if ( ! $ftp_enabled || $ftp_enabled !== '1' ) {
			return false;
		}

		$ftp_host     = Tabesh()->get_setting( 'ftp_host' );
		$ftp_port     = intval( Tabesh()->get_setting( 'ftp_port' ) ) ?: 21;
		$ftp_username = Tabesh()->get_setting( 'ftp_username' );
		$ftp_password = Tabesh()->get_setting( 'ftp_password' );
		$ftp_ssl      = Tabesh()->get_setting( 'ftp_ssl' ) === '1';

		if ( empty( $ftp_host ) || empty( $ftp_username ) ) {
			return false;
		}

		try {
			// Connect to FTP
			$conn = $ftp_ssl ? @ftp_ssl_connect( $ftp_host, $ftp_port ) : @ftp_connect( $ftp_host, $ftp_port );

			if ( ! $conn ) {
				return false;
			}

			// Login
			if ( ! @ftp_login( $conn, $ftp_username, $ftp_password ) ) {
				@ftp_close( $conn );
				return false;
			}

			// Set passive mode
			$ftp_passive = Tabesh()->get_setting( 'ftp_passive' ) === '1';
			@ftp_pasv( $conn, $ftp_passive );

			// Delete file
			$result = @ftp_delete( $conn, $ftp_path );

			@ftp_close( $conn );

			return $result;

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh FTP Delete Error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Delete upload directory
	 *
	 * @return bool True on success, false on failure
	 */
	private function delete_upload_directory() {
		$upload_dir = wp_upload_dir();
		$tabesh_dir = $upload_dir['basedir'] . '/tabesh-files/';

		if ( ! file_exists( $tabesh_dir ) ) {
			return true;
		}

		return $this->delete_directory_recursive( $tabesh_dir );
	}

	/**
	 * Delete directory recursively
	 *
	 * @param string $dir Directory path
	 * @return bool True on success, false on failure
	 */
	private function delete_directory_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->delete_directory_recursive( $path );
			} else {
				@unlink( $path );
			}
		}

		return @rmdir( $dir );
	}

	/**
	 * Reset auto-increment counters for all tables
	 *
	 * @return void
	 */
	private function reset_auto_increment() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'tabesh_orders',
			$wpdb->prefix . 'tabesh_files',
			$wpdb->prefix . 'tabesh_file_versions',
			$wpdb->prefix . 'tabesh_upload_tasks',
			$wpdb->prefix . 'tabesh_logs',
			$wpdb->prefix . 'tabesh_file_comments',
			$wpdb->prefix . 'tabesh_document_metadata',
			$wpdb->prefix . 'tabesh_download_tokens',
			$wpdb->prefix . 'tabesh_security_logs',
		);

		foreach ( $tables as $table ) {
			// Note: ALTER TABLE cannot use wpdb::prepare for DDL statements
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table AUTO_INCREMENT = 1" );
		}
	}

	/**
	 * Log cleanup action
	 *
	 * @param string $action Action name
	 * @param string $description Action description
	 * @param array  $stats Statistics
	 * @return void
	 */
	private function log_cleanup_action( $action, $description, $stats ) {
		global $wpdb;

		// Only log if logs table still exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$wpdb->prefix . 'tabesh_logs'
			)
		);

		if ( ! $table_exists ) {
			return;
		}

		$table_logs = $wpdb->prefix . 'tabesh_logs';

		$wpdb->insert(
			$table_logs,
			array(
				'order_id'    => null,
				'user_id'     => get_current_user_id(),
				'action'      => $action,
				'description' => $description . ' - Stats: ' . wp_json_encode( $stats ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}
}
