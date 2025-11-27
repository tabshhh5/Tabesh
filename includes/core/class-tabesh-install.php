<?php
/**
 * Installation and Database Migration Class
 *
 * Handles plugin installation, database schema updates, and migrations.
 * 
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_Install {

    /**
     * Current database version
     * Update this when schema changes are made
     */
    const DB_VERSION = '1.3.0';

    /**
     * Database version option name
     */
    const DB_VERSION_OPTION = 'tabesh_db_version';

    /**
     * Initialize installation hooks
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'check_version'), 5);
    }

    /**
     * Check database version and run updates if needed
     */
    public static function check_version() {
        $current_db_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        
        if (version_compare($current_db_version, self::DB_VERSION, '<')) {
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh: Starting database schema update check');
        }
        
        // Suppress errors temporarily to check table existence
        $wpdb->suppress_errors(true);
        
        // Check if wp_tabesh_orders table exists
        $table_orders = $wpdb->prefix . 'tabesh_orders';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_orders
        ));
        
        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh: Orders table does not exist, skipping migration');
            }
            $wpdb->suppress_errors(false);
            return;
        }
        
        // Check if book_title column exists
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_orders}` LIKE %s",
            'book_title'
        ));
        
        if (!$column_exists) {
            // Add book_title column
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh: Adding book_title column to orders table');
            }
            
            // Note: ALTER TABLE cannot use wpdb::prepare as it doesn't support DDL statements
            // The table name comes from $wpdb->prefix which is safe and not user input
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $result = $wpdb->query(
                "ALTER TABLE `{$table_orders}` 
                ADD COLUMN `book_title` VARCHAR(255) DEFAULT NULL AFTER `order_number`"
            );
            
            if ($result === false) {
                error_log('Tabesh: ERROR - Failed to add book_title column: ' . $wpdb->last_error);
                // Log additional diagnostic information
                error_log('Tabesh: Table name: ' . $table_orders);
                error_log('Tabesh: Last query: ' . $wpdb->last_query);
            } else {
                error_log('Tabesh: SUCCESS - Added book_title column to orders table');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh: book_title column already exists');
            }
        }
        
        // Add staff_user_id column to logs table for tracking who made changes
        $table_logs = $wpdb->prefix . 'tabesh_logs';
        if (self::table_exists($table_logs)) {
            if (!self::column_exists($table_logs, 'staff_user_id')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tabesh: Adding staff_user_id column to logs table');
                }
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $result = $wpdb->query(
                    "ALTER TABLE `{$table_logs}` 
                    ADD COLUMN `staff_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `user_id`,
                    ADD KEY `staff_user_id` (`staff_user_id`)"
                );
                
                if ($result === false) {
                    error_log('Tabesh: ERROR - Failed to add staff_user_id column: ' . $wpdb->last_error);
                } else {
                    error_log('Tabesh: SUCCESS - Added staff_user_id column to logs table');
                }
            }
            
            if (!self::column_exists($table_logs, 'old_status')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tabesh: Adding old_status and new_status columns to logs table');
                }
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $result = $wpdb->query(
                    "ALTER TABLE `{$table_logs}` 
                    ADD COLUMN `old_status` VARCHAR(50) DEFAULT NULL AFTER `action`,
                    ADD COLUMN `new_status` VARCHAR(50) DEFAULT NULL AFTER `old_status`"
                );
                
                if ($result === false) {
                    error_log('Tabesh: ERROR - Failed to add status columns: ' . $wpdb->last_error);
                } else {
                    error_log('Tabesh: SUCCESS - Added status columns to logs table');
                }
            }
        }
        
        // Create print substeps table (v1.3.0)
        self::create_print_substeps_table();
        
        // Re-enable error reporting
        $wpdb->suppress_errors(false);
        
        // Update database version
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh: Database schema update completed. Version: ' . self::DB_VERSION);
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
        $table_name = $wpdb->prefix . 'tabesh_print_substeps';
        
        // Check if table already exists
        if (self::table_exists($table_name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh: Print substeps table already exists');
            }
            return true;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh: Creating print substeps table');
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verify table was created
        if (self::table_exists($table_name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh: SUCCESS - Print substeps table created');
            }
            return true;
        } else {
            error_log('Tabesh: ERROR - Failed to create print substeps table');
            return false;
        }
    }

    /**
     * Check if a table exists
     * 
     * @param string $table_name Full table name (with prefix)
     * @return bool True if table exists, false otherwise
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        return $result === $table_name;
    }

    /**
     * Check if a column exists in a table
     * 
     * @param string $table_name Full table name (with prefix)
     * @param string $column_name Column name
     * @return bool True if column exists, false otherwise
     */
    public static function column_exists($table_name, $column_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            $column_name
        ));
        
        return !empty($result);
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
            $wpdb->prefix . 'tabesh_file_comments'
        );
        
        $dropped = array();
        $errors = array();
        
        foreach ($tables as $table) {
            // Check if table exists before dropping
            $exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
            
            if ($exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $result = $wpdb->query("DROP TABLE IF EXISTS `$table`");
                
                if ($result !== false) {
                    $dropped[] = $table;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Tabesh: Dropped table $table");
                    }
                } else {
                    $errors[] = $table;
                    error_log("Tabesh: ERROR - Failed to drop table $table: " . $wpdb->last_error);
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
            'allow_reupload_approved'
        );
        
        $settings_table = $wpdb->prefix . 'tabesh_settings';
        foreach ($settings_to_remove as $setting_key) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($settings_table, array('setting_key' => $setting_key));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh: Removed file-related settings from database');
        }
        
        return array(
            'success' => empty($errors),
            'dropped' => $dropped,
            'errors' => $errors,
            'message' => empty($errors) 
                ? sprintf(__('%d tables dropped successfully', 'tabesh'), count($dropped))
                : sprintf(__('Failed to drop %d tables', 'tabesh'), count($errors))
        );
    }
}

// Initialize
Tabesh_Install::init();
