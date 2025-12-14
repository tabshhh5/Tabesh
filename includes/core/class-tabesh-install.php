<?php
/**
 * Installation and Database Migration Class
 *
 * Handles plugin installation, database schema updates, and migrations.
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tabesh_Install {

	/**
	 * Current database version
	 * Update this when schema changes are made
	 */
	const DB_VERSION = '1.5.0';

	/**
	 * Database version option name
	 */
	const DB_VERSION_OPTION = 'tabesh_db_version';

	/**
	 * Initialize installation hooks
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
	}

	/**
	 * Check database version and run updates if needed
	 */
	public static function check_version() {
		$current_db_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $current_db_version, self::DB_VERSION, '<' ) ) {
			self::update_database_schema();
		}
	}

	/**
	 * Update database schema
	 *
	 * Checks for missing columns and adds them in a safe, idempotent manner.
	 * This function is called during plugin activation and on version updates.
	 */
	public static function update_database_schema() {
		global $wpdb;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Starting database schema update check' );
		}

		// Suppress errors temporarily to check table existence
		$wpdb->suppress_errors( true );

		// Check if wp_tabesh_orders table exists
		$table_orders = $wpdb->prefix . 'tabesh_orders';
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_orders
			)
		);

		if ( ! $table_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Orders table does not exist, skipping migration' );
			}
			$wpdb->suppress_errors( false );
			return;
		}

		// Check if book_title column exists
		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_orders}` LIKE %s",
				'book_title'
			)
		);

		if ( ! $column_exists ) {
			// Add book_title column
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Adding book_title column to orders table' );
			}

			// Note: ALTER TABLE cannot use wpdb::prepare as it doesn't support DDL statements
			// The table name comes from $wpdb->prefix which is safe and not user input
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query(
				"ALTER TABLE `{$table_orders}` 
                ADD COLUMN `book_title` VARCHAR(255) DEFAULT NULL AFTER `order_number`"
			);

			if ( $result === false ) {
				error_log( 'Tabesh: ERROR - Failed to add book_title column: ' . $wpdb->last_error );
				// Log additional diagnostic information
				error_log( 'Tabesh: Table name: ' . $table_orders );
				error_log( 'Tabesh: Last query: ' . $wpdb->last_query );
			} else {
				error_log( 'Tabesh: SUCCESS - Added book_title column to orders table' );
			}
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: book_title column already exists' );
		}

		// Add staff_user_id column to logs table for tracking who made changes
		$table_logs = $wpdb->prefix . 'tabesh_logs';
		if ( self::table_exists( $table_logs ) ) {
			if ( ! self::column_exists( $table_logs, 'staff_user_id' ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh: Adding staff_user_id column to logs table' );
				}

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $wpdb->query(
					"ALTER TABLE `{$table_logs}` 
                    ADD COLUMN `staff_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `user_id`,
                    ADD KEY `staff_user_id` (`staff_user_id`)"
				);

				if ( $result === false ) {
					error_log( 'Tabesh: ERROR - Failed to add staff_user_id column: ' . $wpdb->last_error );
				} else {
					error_log( 'Tabesh: SUCCESS - Added staff_user_id column to logs table' );
				}
			}

			if ( ! self::column_exists( $table_logs, 'old_status' ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh: Adding old_status and new_status columns to logs table' );
				}

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $wpdb->query(
					"ALTER TABLE `{$table_logs}` 
                    ADD COLUMN `old_status` VARCHAR(50) DEFAULT NULL AFTER `action`,
                    ADD COLUMN `new_status` VARCHAR(50) DEFAULT NULL AFTER `old_status`"
				);

				if ( $result === false ) {
					error_log( 'Tabesh: ERROR - Failed to add status columns: ' . $wpdb->last_error );
				} else {
					error_log( 'Tabesh: SUCCESS - Added status columns to logs table' );
				}
			}
		}

		// Create print substeps table (v1.3.0)
		self::create_print_substeps_table();

		// Add archived_at column to orders table (v1.4.0)
		self::add_archived_at_column();

		// Add serial_number column to orders table (v1.5.0)
		self::add_serial_number_column();

		// Add details column to logs table (v1.5.0)
		self::add_details_column_to_logs();

		// Ensure all default settings are in database (v1.5.1)
		self::ensure_default_settings();

		// Re-enable error reporting
		$wpdb->suppress_errors( false );

		// Update database version
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Database schema update completed. Version: ' . self::DB_VERSION );
		}
	}

	/**
	 * Create print substeps table
	 *
	 * Creates the wp_tabesh_print_substeps table for tracking detailed
	 * printing process steps when orders are in "processing" status.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_print_substeps_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'tabesh_print_substeps';

		// Check if table already exists
		if ( self::table_exists( $table_name ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Print substeps table already exists' );
			}
			return true;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Creating print substeps table' );
		}

		$sql = "CREATE TABLE `{$table_name}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `order_id` bigint(20) NOT NULL,
            `substep_key` varchar(50) NOT NULL,
            `substep_title` varchar(255) NOT NULL,
            `substep_details` text,
            `is_completed` tinyint(1) DEFAULT 0,
            `completed_at` datetime DEFAULT NULL,
            `completed_by` bigint(20) DEFAULT NULL,
            `display_order` int(11) DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `order_id` (`order_id`),
            KEY `substep_key` (`substep_key`)
        ) ENGINE=InnoDB $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		if ( self::table_exists( $table_name ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: SUCCESS - Print substeps table created' );
			}
			return true;
		} else {
			error_log( 'Tabesh: ERROR - Failed to create print substeps table' );
			return false;
		}
	}

	/**
	 * Add archived_at column to orders table
	 *
	 * Creates the archived_at column for tracking when orders were archived.
	 * Part of the order archiving feature (v1.4.0).
	 *
	 * @return bool True on success, false on failure
	 */
	public static function add_archived_at_column() {
		global $wpdb;
		$table_orders = $wpdb->prefix . 'tabesh_orders';

		// Check if table exists
		if ( ! self::table_exists( $table_orders ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Orders table does not exist, skipping archived_at migration' );
			}
			return false;
		}

		// Check if column already exists
		if ( self::column_exists( $table_orders, 'archived_at' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: archived_at column already exists' );
			}
			return true;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Adding archived_at column to orders table' );
		}

		// Note: ALTER TABLE cannot use wpdb::prepare as it doesn't support DDL statements
		// The table name comes from $wpdb->prefix which is safe and not user input
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			"ALTER TABLE `{$table_orders}` 
            ADD COLUMN `archived_at` DATETIME DEFAULT NULL AFTER `archived`"
		);

		if ( $result === false ) {
			error_log( 'Tabesh: ERROR - Failed to add archived_at column: ' . $wpdb->last_error );
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: SUCCESS - Added archived_at column to orders table' );
		}

		return true;
	}

	/**
	 * Add serial_number column to orders table
	 *
	 * Creates the serial_number column for official record keeping.
	 * Part of the serial number tracking feature (v1.5.0).
	 * Assigns sequential serial numbers to existing orders.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function add_serial_number_column() {
		global $wpdb;
		$table_orders = $wpdb->prefix . 'tabesh_orders';

		// Check if table exists
		if ( ! self::table_exists( $table_orders ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Orders table does not exist, skipping serial_number migration' );
			}
			return false;
		}

		// Check if column already exists
		if ( self::column_exists( $table_orders, 'serial_number' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: serial_number column already exists' );
			}
			return true;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Adding serial_number column to orders table' );
		}

		// Step 1: Add serial_number column WITHOUT unique constraint initially
		// Note: ALTER TABLE cannot use wpdb::prepare as it doesn't support DDL statements
		// The table name comes from $wpdb->prefix which is a WordPress constant, not user input
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			"ALTER TABLE `{$table_orders}` 
            ADD COLUMN `serial_number` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `id`"
		);

		if ( $result === false ) {
			error_log( 'Tabesh: ERROR - Failed to add serial_number column: ' . $wpdb->last_error );
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: SUCCESS - Added serial_number column to orders table' );
		}

		// Step 2: Assign sequential serial numbers to existing orders (ordered by id)
		// This must be done before adding UNIQUE constraint to avoid duplicate value errors
		// Get all order IDs ordered by id (creation order)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_ids = $wpdb->get_col( "SELECT id FROM `{$table_orders}` ORDER BY id ASC" );

		if ( ! empty( $order_ids ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Assigning serial numbers to ' . count( $order_ids ) . ' existing orders' );
			}

			// Assign serial numbers sequentially starting from 1
			$serial = 1;
			foreach ( $order_ids as $order_id ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table_orders,
					array( 'serial_number' => $serial ),
					array( 'id' => $order_id ),
					array( '%d' ),
					array( '%d' )
				);
				++$serial;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: SUCCESS - Assigned serial numbers to existing orders' );
			}
		}

		// Step 3: Add UNIQUE constraint after all serial numbers are assigned
		// This ensures no duplicate values exist when constraint is created
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			"ALTER TABLE `{$table_orders}` 
            ADD UNIQUE KEY `serial_number` (`serial_number`)"
		);

		if ( $result === false ) {
			error_log( 'Tabesh: ERROR - Failed to add UNIQUE constraint on serial_number: ' . $wpdb->last_error );
			// Continue anyway - column is created and populated
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: SUCCESS - Added UNIQUE constraint on serial_number' );
		}

		return true;
	}

	/**
	 * Add details column to logs table
	 *
	 * Creates the details column in wp_tabesh_logs for storing additional
	 * information about log entries, particularly used by the firewall system.
	 * Part of the firewall logging improvement (v1.5.0).
	 *
	 * @return bool True on success, false on failure
	 */
	public static function add_details_column_to_logs() {
		global $wpdb;
		$table_logs = $wpdb->prefix . 'tabesh_logs';

		// Check if table exists
		if ( ! self::table_exists( $table_logs ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Logs table does not exist, skipping details column migration' );
			}
			return false;
		}

		// Check if column already exists
		if ( self::column_exists( $table_logs, 'details' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: details column already exists in logs table' );
			}
			return true;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Adding details column to logs table' );
		}

		// Note: ALTER TABLE cannot use wpdb::prepare as it doesn't support DDL statements
		// The table name comes from $wpdb->prefix which is safe and not user input
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			"ALTER TABLE `{$table_logs}` 
            ADD COLUMN `details` TEXT DEFAULT NULL AFTER `description`"
		);

		if ( $result === false ) {
			error_log( 'Tabesh: ERROR - Failed to add details column to logs table: ' . $wpdb->last_error );
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: SUCCESS - Added details column to logs table' );
		}

		return true;
	}

	/**
	 * Check if a table exists
	 *
	 * @param string $table_name Full table name (with prefix)
	 * @return bool True if table exists, false otherwise
	 */
	public static function table_exists( $table_name ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $result === $table_name;
	}

	/**
	 * Check if a column exists in a table
	 *
	 * @param string $table_name Full table name (with prefix)
	 * @param string $column_name Column name
	 * @return bool True if column exists, false otherwise
	 */
	public static function column_exists( $table_name, $column_name ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				$column_name
			)
		);

		return ! empty( $result );
	}

	/**
	 * Drop customer files panel related tables
	 * Migration: Remove file upload functionality
	 *
	 * This method can be called manually to clean up database tables
	 * related to the removed customer files panel feature.
	 *
	 * @return array Result with success status and message
	 */
	public static function drop_customer_files_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'tabesh_files',
			$wpdb->prefix . 'tabesh_file_versions',
			$wpdb->prefix . 'tabesh_upload_tasks',
			$wpdb->prefix . 'tabesh_document_metadata',
			$wpdb->prefix . 'tabesh_file_comments',
		);

		$dropped = array();
		$errors  = array();

		foreach ( $tables as $table ) {
			// Check if table exists before dropping
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table
				)
			);

			if ( $exists ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $wpdb->query( "DROP TABLE IF EXISTS `$table`" );

				if ( $result !== false ) {
					$dropped[] = $table;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "Tabesh: Dropped table $table" );
					}
				} else {
					$errors[] = $table;
					error_log( "Tabesh: ERROR - Failed to drop table $table: " . $wpdb->last_error );
				}
			}
		}

		// Clean up file-related settings from options/settings table
		$settings_to_remove = array(
			'file_max_size_pdf',
			'file_max_size_image',
			'file_max_size_document',
			'file_max_size_archive',
			'file_min_dpi',
			'file_retention_days',
			'file_correction_fee',
			'file_download_link_expiry',
			'file_delete_incomplete_after',
			'file_reupload_hours',
			'file_backup_location',
			'file_error_display_type',
			'file_encrypt_filenames',
			'file_enable_ip_restriction',
			'file_auto_backup_enabled',
			'file_show_progress_bar',
			'allow_reupload_approved',
		);

		$settings_table = $wpdb->prefix . 'tabesh_settings';
		foreach ( $settings_to_remove as $setting_key ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $settings_table, array( 'setting_key' => $setting_key ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Removed file-related settings from database' );
		}

		return array(
			'success' => empty( $errors ),
			'dropped' => $dropped,
			'errors'  => $errors,
			'message' => empty( $errors )
				? sprintf( __( '%d tables dropped successfully', 'tabesh' ), count( $dropped ) )
				: sprintf( __( 'Failed to drop %d tables', 'tabesh' ), count( $errors ) ),
		);
	}

	/**
	 * Ensure all default settings exist in the database
	 *
	 * This method checks if critical default settings are present in the database
	 * and inserts any missing ones. It's called during database updates to ensure
	 * that settings added in new versions are initialized without requiring
	 * plugin reactivation.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function ensure_default_settings() {
		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		// Check if settings table exists.
		if ( ! self::table_exists( $table_settings ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Settings table does not exist, skipping settings initialization' );
			}
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh: Checking for missing default settings' );
		}

		// Get the Tabesh instance to access set_default_options logic.
		// We'll replicate the critical settings here to avoid circular dependencies.
		$critical_settings = array(
			// SMS Settings (already in defaults but may be missing in DB).
			'sms_enabled'                              => '0',
			'sms_username'                             => '',
			'sms_password'                             => '',
			'sms_sender'                               => '',
			'sms_on_order_submit'                      => '1',
			'sms_on_status_change'                     => '1',
			'admin_phone'                              => '',
			'sms_admin_user_registration_enabled'      => '0',
			'sms_admin_user_registration_pattern'      => '',
			'sms_pattern_vars_admin_user_registration' => wp_json_encode( array() ),
			'sms_admin_order_created_enabled'          => '0',
			'sms_admin_order_created_pattern'          => '',
			'sms_pattern_vars_admin_order_created'     => wp_json_encode( array() ),
			'sms_pattern_vars_status_change'           => wp_json_encode( array() ),
			'sms_disable_global_for_staff'             => '0',
		);

		// Add dynamic SMS status settings.
		$order_statuses = array( 'pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled', 'archived' );
		foreach ( $order_statuses as $status ) {
			$critical_settings[ 'sms_status_' . $status . '_enabled' ] = '0';
			$critical_settings[ 'sms_status_' . $status . '_pattern' ] = '';
		}

		$inserted_count = 0;
		$skipped_count  = 0;

		foreach ( $critical_settings as $key => $value ) {
			// Check if setting exists.
			// Note: $table_settings comes from $wpdb->prefix which is safe, not user input.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_settings} WHERE setting_key = %s",
					$key
				)
			);

			if ( ! $existing ) {
				// Insert missing setting.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$result = $wpdb->insert(
					$table_settings,
					array(
						'setting_key'   => $key,
						'setting_value' => $value,
						'setting_type'  => 'string',
					)
				);

				if ( $result !== false ) {
					++$inserted_count;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "Tabesh: Inserted missing setting: $key" );
					}
				} else {
					error_log( "Tabesh: ERROR - Failed to insert setting $key: " . $wpdb->last_error );
				}
			} else {
				++$skipped_count;
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Tabesh: Settings check complete. Inserted: $inserted_count, Skipped (existing): $skipped_count" );
		}

		return true;
	}
}

// Initialize.
Tabesh_Install::init();
