<?php
/**
 * Plugin Name: Tabesh - سامانه جامع ثبت سفارش چاپ کتاب
 * Plugin URI: https://chapco.ir
 * Description: A comprehensive system for managing, calculating, and processing book printing orders with full WooCommerce integration.
 * Version: 1.0.4
 * Author: Chapco
 * Author URI: https://chapco.ir
 * Text Domain: tabesh
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 8.2.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'TABESH_VERSION', '1.0.4' );
define( 'TABESH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TABESH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TABESH_PLUGIN_FILE', __FILE__ );
define( 'TABESH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TABESH_REST_NAMESPACE', 'tabesh/v1' );

// Check PHP version
if ( version_compare( PHP_VERSION, '8.2.2', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="error"><p>';
			printf(
				__( 'Tabesh requires PHP version 8.2.2 or higher. You are running version %s.', 'tabesh' ),
				PHP_VERSION
			);
			echo '</p></div>';
		}
	);
	return;
}

// Check WordPress version
global $wp_version;
if ( version_compare( $wp_version, '6.8', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			global $wp_version;
			echo '<div class="error"><p>';
			printf(
				__( 'Tabesh requires WordPress version 6.8 or higher. You are running version %s.', 'tabesh' ),
				$wp_version
			);
			echo '</p></div>';
		}
	);
	return;
}

// Autoloader
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'Tabesh_';
		$base_dir = TABESH_PLUGIN_DIR . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Convert full class name to filename (e.g., Tabesh_Order -> class-tabesh-order.php)
		$filename = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

		// Search in subdirectories: core, handlers, utils, api, security, and root
		$subdirs = array( 'core/', 'handlers/', 'utils/', 'api/', 'security/', '' );

		foreach ( $subdirs as $subdir ) {
			$file = $base_dir . $subdir . $filename;
			if ( file_exists( $file ) ) {
				require $file;
				return;
			}
		}
	}
);

/**
 * Main Tabesh Plugin Class
 */
final class Tabesh {

	/**
	 * The single instance of the class
	 *
	 * @var Tabesh
	 */
	private static $instance = null;

	/**
	 * Order handler
	 *
	 * @var Tabesh_Order
	 */
	public $order;

	/**
	 * Admin handler
	 *
	 * @var Tabesh_Admin
	 */
	public $admin;

	/**
	 * Staff handler
	 *
	 * @var Tabesh_Staff
	 */
	public $staff;

	/**
	 * User handler
	 *
	 * @var Tabesh_User
	 */
	public $user;

	/**
	 * Notifications handler
	 *
	 * @var Tabesh_Notifications
	 */
	public $notifications;

	/**
	 * WooCommerce integration handler
	 *
	 * @var Tabesh_WooCommerce
	 */
	public $woocommerce;

	/**
	 * File manager handler
	 *
	 * @var Tabesh_File_Manager
	 */
	public $file_manager;

	/**
	 * FTP handler
	 *
	 * @var Tabesh_FTP_Handler
	 */
	public $ftp_handler;

	/**
	 * File validator handler
	 *
	 * @var Tabesh_File_Validator
	 */
	public $file_validator;

	/**
	 * Upload task generator handler
	 *
	 * @var Tabesh_Upload_Task_Generator
	 */
	public $upload_task_generator;

	/**
	 * Print substeps handler
	 *
	 * @var Tabesh_Print_Substeps
	 */
	public $print_substeps;

	/**
	 * Upload manager handler
	 *
	 * @var Tabesh_Upload
	 */
	public $upload;

	/**
	 * Archive handler
	 *
	 * @var Tabesh_Archive
	 */
	public $archive;

	/**
	 * SMS handler
	 *
	 * @var Tabesh_SMS
	 */
	public $sms;

	/**
	 * Admin order creator handler
	 *
	 * @var Tabesh_Admin_Order_Creator
	 */
	public $admin_order_creator;

	/**
	 * Admin order form shortcode handler
	 *
	 * @var Tabesh_Admin_Order_Form
	 */
	public $admin_order_form;

	/**
	 * Order form slider integration handler
	 *
	 * @var Tabesh_Order_Form_Slider
	 */
	public $order_form_slider;

	/**
	 * Export/Import handler
	 *
	 * @var Tabesh_Export_Import
	 */
	public $export_import;

	/**
	 * Doomsday Firewall handler
	 *
	 * @var Tabesh_Doomsday_Firewall
	 */
	public $firewall;

	/**
	 * Product Pricing handler
	 *
	 * @var Tabesh_Product_Pricing
	 */
	public $product_pricing;

	/**
	 * React Dashboard handler
	 *
	 * @var Tabesh_React_Dashboard
	 */
	public $react_dashboard;

	/**
	 * Cache for settings to avoid redundant database queries
	 *
	 * @var array
	 */
	private static $settings_cache = array();

	/**
	 * Main Tabesh Instance
	 *
	 * @return Tabesh
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Check for WooCommerce
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Initialize classes
		$this->order         = new Tabesh_Order();
		$this->admin         = new Tabesh_Admin();
		$this->staff         = new Tabesh_Staff();
		$this->user          = new Tabesh_User();
		$this->notifications = new Tabesh_Notifications();
		$this->woocommerce   = new Tabesh_WooCommerce();
		// Initialize file management handlers
		$this->file_manager          = new Tabesh_File_Manager();
		$this->ftp_handler           = new Tabesh_FTP_Handler();
		$this->file_validator        = new Tabesh_File_Validator();
		$this->upload_task_generator = new Tabesh_Upload_Task_Generator();
		// Initialize print substeps handler
		$this->print_substeps = new Tabesh_Print_Substeps();
		// Initialize upload manager
		$this->upload = new Tabesh_Upload();
		// Initialize archive handler
		$this->archive = new Tabesh_Archive();
		// Initialize SMS handler
		$this->sms = new Tabesh_SMS();
		// Initialize admin order creator handler
		$this->admin_order_creator = new Tabesh_Admin_Order_Creator();
		// Initialize admin order form shortcode handler
		$this->admin_order_form = new Tabesh_Admin_Order_Form();
		// Initialize order form slider integration handler
		$this->order_form_slider = new Tabesh_Order_Form_Slider();
		// Initialize export/import handler
		$this->export_import = new Tabesh_Export_Import();
		// Initialize Doomsday Firewall
		$this->firewall = new Tabesh_Doomsday_Firewall();
		// Initialize Product Pricing handler
		$this->product_pricing = new Tabesh_Product_Pricing();
		// Initialize React Dashboard handler
		$this->react_dashboard = new Tabesh_React_Dashboard();

		// Register REST API routes
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add filter to handle REST API cookie authentication
		add_filter( 'rest_authentication_errors', array( $this, 'rest_cookie_authentication' ), 100 );

		// Register AJAX handlers
		add_action( 'wp_ajax_tabesh_calculate', array( $this, 'ajax_calculate_price' ) );
		add_action( 'wp_ajax_nopriv_tabesh_calculate', array( $this, 'ajax_calculate_price' ) );

		// Register shortcodes
		$this->register_shortcodes();

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Handle file download requests
		add_action( 'template_redirect', array( $this, 'handle_file_download_request' ) );
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'tabesh', false, dirname( TABESH_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database tables
		$this->create_tables();

		// Update database schema if needed (existing migration)
		$this->update_database_schema();

		// Run new installation/migration checks
		Tabesh_Install::update_database_schema();

		// Set default options
		$this->set_default_options();

		// Set flag to flush rewrite rules on next load
		update_option( 'tabesh_flush_rewrite_rules', 'yes' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create database tables
	 */
	private function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Orders table
		$table_orders = $wpdb->prefix . 'tabesh_orders';
		$sql_orders   = "CREATE TABLE IF NOT EXISTS $table_orders (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            serial_number bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            user_id bigint(20) UNSIGNED NOT NULL,
            order_number varchar(50) NOT NULL,
            book_title varchar(255) DEFAULT NULL,
            book_size varchar(50) NOT NULL,
            paper_type varchar(50) NOT NULL,
            paper_weight varchar(20) NOT NULL,
            print_type varchar(50) NOT NULL,
            page_count_color int(11) DEFAULT 0,
            page_count_bw int(11) DEFAULT 0,
            page_count_total int(11) NOT NULL,
            quantity int(11) NOT NULL,
            binding_type varchar(50) NOT NULL,
            license_type varchar(50) NOT NULL,
            cover_paper_type varchar(50) DEFAULT NULL,
            cover_paper_weight varchar(20) DEFAULT NULL,
            lamination_type varchar(50) DEFAULT NULL,
            extras longtext DEFAULT NULL,
            total_price decimal(10,2) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            files longtext DEFAULT NULL,
            notes longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            archived tinyint(1) DEFAULT 0,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY serial_number (serial_number),
            KEY user_id (user_id),
            KEY order_number (order_number),
            KEY status (status),
            KEY archived (archived)
        ) $charset_collate;";

		// Settings table
		$table_settings = $wpdb->prefix . 'tabesh_settings';
		$sql_settings   = "CREATE TABLE IF NOT EXISTS $table_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext NOT NULL,
            setting_type varchar(50) NOT NULL DEFAULT 'string',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

		// Logs table
		$table_logs = $wpdb->prefix . 'tabesh_logs';
		$sql_logs   = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            action varchar(255) NOT NULL,
            description longtext DEFAULT NULL,
            details text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY user_id (user_id)
        ) $charset_collate;";

		// Files table - stores file metadata and status
		$table_files = $wpdb->prefix . 'tabesh_files';
		$sql_files   = "CREATE TABLE IF NOT EXISTS $table_files (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            upload_task_id bigint(20) UNSIGNED DEFAULT NULL,
            file_type varchar(50) NOT NULL,
            file_category varchar(50) NOT NULL,
            original_filename varchar(255) NOT NULL,
            stored_filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            ftp_path varchar(500) DEFAULT NULL,
            file_size bigint(20) UNSIGNED NOT NULL,
            mime_type varchar(100) NOT NULL,
            version int(11) NOT NULL DEFAULT 1,
            status varchar(50) NOT NULL DEFAULT 'pending',
            validation_status varchar(50) DEFAULT NULL,
            validation_data longtext DEFAULT NULL,
            rejection_reason longtext DEFAULT NULL,
            approved_by bigint(20) UNSIGNED DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            deleted_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            transfer_status varchar(50) DEFAULT NULL,
            scheduled_transfer_at datetime DEFAULT NULL,
            transferred_at datetime DEFAULT NULL,
            scheduled_deletion_at datetime DEFAULT NULL,
            local_deleted_at datetime DEFAULT NULL,
            is_encrypted tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY file_category (file_category),
            KEY transfer_status (transfer_status)
        ) $charset_collate;";

		// File versions table - tracks all versions of a file
		$table_file_versions = $wpdb->prefix . 'tabesh_file_versions';
		$sql_file_versions   = "CREATE TABLE IF NOT EXISTS $table_file_versions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_id bigint(20) UNSIGNED NOT NULL,
            version int(11) NOT NULL,
            stored_filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) UNSIGNED NOT NULL,
            status varchar(50) NOT NULL,
            uploaded_by bigint(20) UNSIGNED NOT NULL,
            uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY file_id (file_id),
            KEY version (version)
        ) $charset_collate;";

		// Upload tasks table - defines upload requirements for orders
		$table_upload_tasks = $wpdb->prefix . 'tabesh_upload_tasks';
		$sql_upload_tasks   = "CREATE TABLE IF NOT EXISTS $table_upload_tasks (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            task_title varchar(255) NOT NULL,
            task_type varchar(50) NOT NULL,
            allowed_file_types longtext NOT NULL,
            min_file_size bigint(20) UNSIGNED DEFAULT NULL,
            max_file_size bigint(20) UNSIGNED DEFAULT NULL,
            min_file_count int(11) DEFAULT 1,
            max_file_count int(11) DEFAULT 1,
            min_width int(11) DEFAULT NULL,
            max_width int(11) DEFAULT NULL,
            min_height int(11) DEFAULT NULL,
            max_height int(11) DEFAULT NULL,
            required_color_mode varchar(50) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY task_type (task_type)
        ) $charset_collate;";

		// Book format settings table - global upload settings per book format
		$table_format_settings = $wpdb->prefix . 'tabesh_book_format_settings';
		$sql_format_settings   = "CREATE TABLE IF NOT EXISTS $table_format_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            book_format varchar(50) NOT NULL,
            file_category varchar(50) NOT NULL,
            min_width int(11) DEFAULT NULL,
            max_width int(11) DEFAULT NULL,
            min_height int(11) DEFAULT NULL,
            max_height int(11) DEFAULT NULL,
            min_margin int(11) DEFAULT NULL,
            max_margin int(11) DEFAULT NULL,
            min_resolution int(11) DEFAULT NULL,
            required_color_mode varchar(50) DEFAULT NULL,
            max_file_size bigint(20) UNSIGNED DEFAULT NULL,
            allowed_file_types longtext DEFAULT NULL,
            validation_rules longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY book_format (book_format),
            KEY file_category (file_category),
            UNIQUE KEY format_category (book_format, file_category)
        ) $charset_collate;";

		// File comments table - stores admin/staff comments on files
		$table_file_comments = $wpdb->prefix . 'tabesh_file_comments';
		$sql_file_comments   = "CREATE TABLE IF NOT EXISTS $table_file_comments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            comment_text longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY file_id (file_id),
            KEY user_id (user_id)
        ) $charset_collate;";

		// Document metadata table - stores additional info for customer documents
		$table_document_metadata = $wpdb->prefix . 'tabesh_document_metadata';
		$sql_document_metadata   = "CREATE TABLE IF NOT EXISTS $table_document_metadata (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_id bigint(20) UNSIGNED NOT NULL,
            document_type varchar(50) NOT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            birth_certificate_number varchar(50) DEFAULT NULL,
            national_id varchar(20) DEFAULT NULL,
            expiry_date date DEFAULT NULL,
            subject varchar(255) DEFAULT NULL,
            issuing_organization varchar(255) DEFAULT NULL,
            recipient varchar(255) DEFAULT NULL,
            licensing_authority varchar(255) DEFAULT NULL,
            metadata_json longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY file_id (file_id),
            KEY document_type (document_type)
        ) $charset_collate;";

		// Download tokens table - stores secure download tokens
		$table_download_tokens = $wpdb->prefix . 'tabesh_download_tokens';
		$sql_download_tokens   = "CREATE TABLE IF NOT EXISTS $table_download_tokens (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            token_hash varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            used tinyint(1) DEFAULT 0,
            used_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY file_id (file_id),
            KEY user_id (user_id),
            KEY token_hash (token_hash),
            KEY expires_at (expires_at)
        ) $charset_collate;";

		// Security logs table - stores security events
		$table_security_logs = $wpdb->prefix . 'tabesh_security_logs';
		$sql_security_logs   = "CREATE TABLE IF NOT EXISTS $table_security_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            file_id bigint(20) UNSIGNED DEFAULT NULL,
            ip_address varchar(50) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            description longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY file_id (file_id),
            KEY created_at (created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_orders );
		dbDelta( $sql_settings );
		dbDelta( $sql_logs );
		dbDelta( $sql_files );
		dbDelta( $sql_file_versions );
		dbDelta( $sql_upload_tasks );
		dbDelta( $sql_format_settings );
		dbDelta( $sql_file_comments );
		dbDelta( $sql_document_metadata );
		dbDelta( $sql_download_tokens );
		dbDelta( $sql_security_logs );
	}

	/**
	 * Update database schema for existing installations
	 *
	 * This function checks for missing columns in existing tables and adds them.
	 * Called during plugin activation to ensure database compatibility.
	 */
	private function update_database_schema() {
		global $wpdb;
		$table_files = $wpdb->prefix . 'tabesh_files';

		// Check if the files table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_files
			)
		);

		if ( ! $table_exists ) {
			// Table doesn't exist yet, nothing to update
			return;
		}

		// Check if transfer_status column exists
		$transfer_status_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = %s AND table_name = %s AND column_name = 'transfer_status'",
				DB_NAME,
				$table_files
			)
		);

		if ( ! $transfer_status_exists ) {
			// Verify that the expires_at column exists before adding AFTER clause
			$expires_at_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.columns 
                 WHERE table_schema = %s AND table_name = %s AND column_name = 'expires_at'",
					DB_NAME,
					$table_files
				)
			);

			// Add transfer_status column
			$alter_sql = "ALTER TABLE $table_files ADD COLUMN transfer_status varchar(50) DEFAULT NULL";
			if ( $expires_at_exists ) {
				$alter_sql .= ' AFTER expires_at';
			}

			// Note: ALTER TABLE cannot use wpdb::prepare for DDL statements
			// Table name comes from $wpdb->prefix which is safe
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query( $alter_sql );

			if ( $result === false ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh: Failed to add transfer_status column to ' . $table_files . ' - Error: ' . $wpdb->last_error );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Added transfer_status column to ' . $table_files );
			}
		}

		// Check if scheduled_deletion_at column exists
		$scheduled_deletion_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = %s AND table_name = %s AND column_name = 'scheduled_deletion_at'",
				DB_NAME,
				$table_files
			)
		);

		if ( ! $scheduled_deletion_exists ) {
			// Verify that the transferred_at column exists before adding AFTER clause
			$transferred_at_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.columns 
                 WHERE table_schema = %s AND table_name = %s AND column_name = 'transferred_at'",
					DB_NAME,
					$table_files
				)
			);

			// Add scheduled_deletion_at column
			$alter_sql = "ALTER TABLE $table_files ADD COLUMN scheduled_deletion_at datetime DEFAULT NULL";
			if ( $transferred_at_exists ) {
				$alter_sql .= ' AFTER transferred_at';
			}

			// Note: ALTER TABLE cannot use wpdb::prepare for DDL statements
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query( $alter_sql );

			if ( $result === false ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh: Failed to add scheduled_deletion_at column to ' . $table_files . ' - Error: ' . $wpdb->last_error );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: Added scheduled_deletion_at column to ' . $table_files );
			}
		}

		// Re-check if transfer_status column exists (it should now exist)
		$transfer_status_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = %s AND table_name = %s AND column_name = 'transfer_status'",
				DB_NAME,
				$table_files
			)
		);

		// Add index for transfer_status if it doesn't exist and column exists
		if ( $transfer_status_exists ) {
			$index_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.statistics 
                 WHERE table_schema = %s AND table_name = %s AND index_name = 'transfer_status'",
					DB_NAME,
					$table_files
				)
			);

			if ( ! $index_exists ) {
				// Note: ALTER TABLE cannot use wpdb::prepare for DDL statements
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $wpdb->query( "ALTER TABLE $table_files ADD KEY transfer_status (transfer_status)" );

				if ( $result === false ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Tabesh: Failed to add transfer_status index to ' . $table_files . ' - Error: ' . $wpdb->last_error );
					}
				} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh: Added transfer_status index to ' . $table_files );
				}
			}
		}

		// Add book_title column to orders table (v1.0.2)
		$table_orders = $wpdb->prefix . 'tabesh_orders';

		// Check if orders table exists
		$orders_table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_orders
			)
		);

		if ( $orders_table_exists ) {
			// Check if book_title column exists
			$book_title_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.columns 
                 WHERE table_schema = %s AND table_name = %s AND column_name = 'book_title'",
					DB_NAME,
					$table_orders
				)
			);

			if ( ! $book_title_exists ) {
				// Verify that the order_number column exists before adding AFTER clause
				$order_number_exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM information_schema.columns 
                     WHERE table_schema = %s AND table_name = %s AND column_name = 'order_number'",
						DB_NAME,
						$table_orders
					)
				);

				// Add book_title column
				$alter_sql = "ALTER TABLE $table_orders ADD COLUMN book_title varchar(255) DEFAULT NULL";
				if ( $order_number_exists ) {
					$alter_sql .= ' AFTER order_number';
				}

				// Note: ALTER TABLE cannot use wpdb::prepare for DDL statements
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $wpdb->query( $alter_sql );

				if ( $result === false ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Tabesh: Failed to add book_title column to ' . $table_orders . ' - Error: ' . $wpdb->last_error );
					}
				} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tabesh: Added book_title column to ' . $table_orders );
				}
			}
		}
	}

	/**
	 * Set default options
	 */
	private function set_default_options() {
		$defaults = array(
			'book_sizes'                               => json_encode( array( 'A5', 'A4', 'رقعی', 'وزیری', 'خشتی' ) ),
			'paper_types'                              => json_encode(
				array(
					'تحریر' => array( 60, 70, 80 ),
					'بالک'  => array( 60, 70, 80, 100 ),
				)
			),
			'print_types'                              => json_encode( array( 'سیاه و سفید', 'رنگی', 'ترکیبی' ) ),
			'binding_types'                            => json_encode( array( 'شومیز', 'جلد سخت', 'گالینگور', 'سیمی' ) ),
			'license_types'                            => json_encode( array( 'دارم', 'انتشارات چاپکو', 'سفیر سلامت' ) ),
			'cover_paper_weights'                      => json_encode( array( '250', '300' ) ),
			'lamination_types'                         => json_encode( array( 'براق', 'مات', 'بدون سلفون' ) ),
			'extras'                                   => json_encode( array( 'لب گرد', 'خط تا', 'شیرینک', 'سوراخ', 'شماره گذاری' ) ),
			'min_quantity'                             => '10',
			'max_quantity'                             => '10000',
			'quantity_step'                            => '10',
			// Pricing configuration
			'pricing_book_sizes'                       => json_encode(
				array(
					'A5'    => 1.0,
					'A4'    => 1.5,
					'B5'    => 1.2,
					'رقعی'  => 1.1,
					'وزیری' => 1.3,
					'خشتی'  => 1.4,
				)
			),
			'pricing_paper_types'                      => json_encode(
				array(
					'glossy' => 250,
					'matte'  => 200,
					'cream'  => 180,
					'تحریر'  => 200,
					'بالک'   => 250,
				)
			),
			'pricing_print_costs'                      => json_encode(
				array(
					'bw'    => 200,
					'color' => 800,
				)
			),
			'pricing_cover_types'                      => json_encode(
				array(
					'soft' => 8000,
					'hard' => 15000,
				)
			),
			'pricing_lamination_costs'                 => json_encode(
				array(
					'براق'       => 2000,
					'مات'        => 2500,
					'بدون سلفون' => 0,
				)
			),
			'pricing_binding_costs'                    => json_encode(
				array(
					'شومیز'    => 3000,
					'جلد سخت'  => 8000,
					'گالینگور' => 6000,
					'سیمی'     => 2000,
				)
			),
			'pricing_options_costs'                    => json_encode(
				array(
					'لب گرد'            => 1000,
					'خط تا'             => 500,
					'شیرینک'            => 1500,
					'سوراخ'             => 300,
					'شماره گذاری'       => 800,
					'uv_coating'        => 3000,
					'embossing'         => 5000,
					'special_packaging' => 2000,
				)
			),
			'pricing_profit_margin'                    => '0',
			'pricing_quantity_discounts'               => json_encode(
				array(
					100 => 10,  // 10% discount for 100+ quantity
					50  => 5,    // 5% discount for 50+ quantity
				)
			),
			// File upload settings
			'file_allowed_types'                       => json_encode( array( 'pdf', 'jpg', 'jpeg', 'png', 'psd', 'doc', 'docx', 'zip', 'rar' ) ),
			'file_max_size_pdf'                        => '52428800',      // 50 MB in bytes
			'file_max_size_image'                      => '10485760',    // 10 MB in bytes
			'file_max_size_document'                   => '10485760', // 10 MB in bytes
			'file_max_size_archive'                    => '104857600', // 100 MB in bytes
			// File upload settings by category (text, cover, documents)
			'file_max_size_text'                       => '52428800',      // 50 MB in bytes for text files
			'file_max_size_cover'                      => '10485760',     // 10 MB in bytes for cover files
			'file_max_size_documents'                  => '10485760', // 10 MB in bytes for document files
			'file_allowed_types_text'                  => json_encode( array( 'pdf', 'doc', 'docx' ) ),
			'file_allowed_types_cover'                 => json_encode( array( 'jpg', 'jpeg', 'png', 'psd', 'pdf' ) ),
			'file_allowed_types_documents'             => json_encode( array( 'pdf', 'jpg', 'jpeg', 'png', 'zip', 'rar' ) ),
			'file_min_dpi'                             => '300',
			'file_retention_days'                      => '5',
			'file_correction_fee'                      => '50000',       // Fee per corrected page in Rials
			'ftp_host'                                 => '',
			'ftp_port'                                 => '21',
			'ftp_username'                             => '',
			'ftp_password'                             => '',
			'ftp_path'                                 => '/uploads/',
			'ftp_passive'                              => '1',
			'ftp_ssl'                                  => '0',
			'ftp_delete_after_transfer'                => '0',
			'ftp_enabled'                              => '1',                       // Enable/disable FTP (fallback to local)
			'ftp_transfer_delay'                       => '60',               // Minutes to wait before transferring to FTP
			'ftp_immediate_transfer'                   => '0',            // Enable immediate FTP transfer for testing
			'ftp_local_retention_minutes'              => '120',     // Minutes to keep local copy after FTP transfer
			'ftp_encrypt_files'                        => '0',                 // Encrypt files before FTP transfer
			// Security & Access
			'file_encrypt_filenames'                   => '0',
			'file_reupload_hours'                      => '48',              // Hours to allow file re-upload
			'file_enable_ip_restriction'               => '0',
			'file_allowed_ips'                         => '',
			'file_download_link_expiry'                => '24',
			'file_admin_access_list'                   => json_encode( array() ),
			// Scheduling & Auto Deletion
			'file_delete_incomplete_after'             => '30',
			'file_auto_backup_enabled'                 => '1',
			'file_backup_location'                     => '/backups/',
			// Display & Behavior
			'file_error_display_type'                  => 'modal',
			'file_show_progress_bar'                   => '1',
			// Access Control
			'staff_allowed_users'                      => json_encode( array() ),
			'admin_dashboard_allowed_users'            => json_encode( array() ),
			// Admin Order Form Shortcode Access Control
			'admin_order_form_allowed_roles'           => json_encode( array( 'administrator' ) ),
			'admin_order_form_allowed_users'           => json_encode( array() ),
			// SMS Settings
			'sms_enabled'                              => '0',
			'sms_username'                             => '',
			'sms_password'                             => '',
			'sms_sender'                               => '',
			// SMS event triggers
			'sms_on_order_submit'                      => '1',           // Send SMS when order is submitted
			'sms_on_status_change'                     => '1',          // Send SMS when order status changes
			// Admin contact
			'admin_phone'                              => '',                     // Admin phone number for notifications
			// Admin SMS notifications
			'sms_admin_user_registration_enabled'      => '0',
			'sms_admin_user_registration_pattern'      => '',
			'sms_pattern_vars_admin_user_registration' => json_encode( array() ),  // Pattern variable configuration for user registration SMS.
			'sms_admin_order_created_enabled'          => '0',
			'sms_admin_order_created_pattern'          => '',
			'sms_pattern_vars_admin_order_created'     => json_encode( array() ),  // Pattern variable configuration for admin order creation SMS.
			'sms_pattern_vars_status_change'           => json_encode( array() ),  // Pattern variable configuration for order status change SMS.
			// SMS Settings for Staff Panel.
			'sms_disable_global_for_staff'             => '0',  // Enable SMS notifications for staff panel by default.
			// General File Upload Settings.
			'file_upload_max_size'                     => '52428800',   // 50 MB in bytes (general default).
			'file_upload_allowed_types'                => json_encode( array( 'pdf', 'jpg', 'jpeg', 'png', 'psd', 'doc', 'docx', 'zip', 'rar' ) ),
		);

		// Add SMS status notifications for all order statuses dynamically
		// Order statuses: pending, confirmed, processing, ready, completed, cancelled, archived
		$order_statuses = array( 'pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled', 'archived' );
		foreach ( $order_statuses as $status ) {
			$defaults[ 'sms_status_' . $status . '_enabled' ] = '0';
			$defaults[ 'sms_status_' . $status . '_pattern' ] = '';
		}

		global $wpdb;
		$table_settings = $wpdb->prefix . 'tabesh_settings';

		foreach ( $defaults as $key => $value ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table_settings WHERE setting_key = %s",
					$key
				)
			);

			if ( ! $existing ) {
				$wpdb->insert(
					$table_settings,
					array(
						'setting_key'   => $key,
						'setting_value' => $value,
						'setting_type'  => 'string',
					)
				);
			}
		}

		// Set default book format settings
		$this->set_default_format_settings();
	}

	/**
	 * Set default book format settings
	 */
	private function set_default_format_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_book_format_settings';

		// Check if we already have settings
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // No user input, safe direct query
		if ( $existing > 0 ) {
			return; // Already initialized
		}

		// Default format settings for common book sizes
		$format_settings = array(
			// A5 Format (148 x 210 mm)
			array(
				'book_format'         => 'A5',
				'file_category'       => 'book_content',
				'min_width'           => 410,
				'max_width'           => 430,
				'min_height'          => 585,
				'max_height'          => 605,
				'min_margin'          => 10,
				'max_margin'          => 20,
				'min_resolution'      => 300,
				'required_color_mode' => 'CMYK,RGB',
				'max_file_size'       => 52428800,
				'allowed_file_types'  => json_encode( array( 'pdf' ) ),
				'validation_rules'    => json_encode(
					array(
						'check_margins'    => true,
						'check_page_count' => true,
					)
				),
			),
			array(
				'book_format'         => 'A5',
				'file_category'       => 'book_cover',
				'min_width'           => 410,
				'max_width'           => 450,
				'min_height'          => 585,
				'max_height'          => 625,
				'min_margin'          => 0,
				'max_margin'          => 10,
				'min_resolution'      => 300,
				'required_color_mode' => 'CMYK',
				'max_file_size'       => 10485760,
				'allowed_file_types'  => json_encode( array( 'psd', 'pdf', 'jpg', 'png' ) ),
				'validation_rules'    => json_encode(
					array(
						'check_bleed'      => true,
						'check_color_mode' => true,
					)
				),
			),
			// A4 Format (210 x 297 mm)
			array(
				'book_format'         => 'A4',
				'file_category'       => 'book_content',
				'min_width'           => 585,
				'max_width'           => 605,
				'min_height'          => 832,
				'max_height'          => 852,
				'min_margin'          => 10,
				'max_margin'          => 25,
				'min_resolution'      => 300,
				'required_color_mode' => 'CMYK,RGB',
				'max_file_size'       => 52428800,
				'allowed_file_types'  => json_encode( array( 'pdf' ) ),
				'validation_rules'    => json_encode(
					array(
						'check_margins'    => true,
						'check_page_count' => true,
					)
				),
			),
			array(
				'book_format'         => 'A4',
				'file_category'       => 'book_cover',
				'min_width'           => 585,
				'max_width'           => 625,
				'min_height'          => 832,
				'max_height'          => 872,
				'min_margin'          => 0,
				'max_margin'          => 10,
				'min_resolution'      => 300,
				'required_color_mode' => 'CMYK',
				'max_file_size'       => 10485760,
				'allowed_file_types'  => json_encode( array( 'psd', 'pdf', 'jpg', 'png' ) ),
				'validation_rules'    => json_encode(
					array(
						'check_bleed'      => true,
						'check_color_mode' => true,
					)
				),
			),
			// B5 Format (176 x 250 mm)
			array(
				'book_format'         => 'B5',
				'file_category'       => 'book_content',
				'min_width'           => 489,
				'max_width'           => 509,
				'min_height'          => 699,
				'max_height'          => 719,
				'min_margin'          => 10,
				'max_margin'          => 20,
				'min_resolution'      => 300,
				'required_color_mode' => 'CMYK,RGB',
				'max_file_size'       => 52428800,
				'allowed_file_types'  => json_encode( array( 'pdf' ) ),
				'validation_rules'    => json_encode(
					array(
						'check_margins'    => true,
						'check_page_count' => true,
					)
				),
			),
			array(
				'book_format'         => 'B5',
				'file_category'       => 'book_cover',
				'min_width'           => 489,
				'max_width'           => 529,
				'min_height'          => 699,
				'max_height'          => 739,
				'min_margin'          => 0,
				'max_margin'          => 10,
				'min_resolution'      => 300,
				'required_color_mode' => 'CMYK',
				'max_file_size'       => 10485760,
				'allowed_file_types'  => json_encode( array( 'psd', 'pdf', 'jpg', 'png' ) ),
				'validation_rules'    => json_encode(
					array(
						'check_bleed'      => true,
						'check_color_mode' => true,
					)
				),
			),
		);

		foreach ( $format_settings as $setting ) {
			$wpdb->insert( $table, $setting );
		}
	}

	/**
	 * WooCommerce missing notice
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>';
		echo __( 'Tabesh requires WooCommerce to be installed and activated.', 'tabesh' );
		echo '</p></div>';
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/calculate-price',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->order, 'calculate_price_rest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/available-options',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_available_options' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'book_size' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return ! empty( $param );
						},
					),
				),
			)
		);

		// New endpoint for dynamic allowed options based on current selection
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/get-allowed-options',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_get_allowed_options_dynamic' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'book_size'         => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return ! empty( $param );
						},
					),
					'current_selection' => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
				),
			)
		);

		// New endpoint to validate parameter combination
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/validate-combination',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_validate_combination' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/submit-order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this->order, 'submit_order_rest' ),
				'permission_callback' => array( $this, 'is_user_logged_in' ),
				'args'                => array(
					'book_title' => array(
						'required'          => false, // Validation moved to callback to handle both JSON and FormData
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/update-status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->staff, 'update_status_rest' ),
				'permission_callback' => array( $this, 'can_manage_orders' ),
			)
		);

		// Staff panel specific routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/staff/update-status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->staff, 'update_status_rest' ),
				'permission_callback' => array( $this, 'can_manage_orders' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/staff/search-orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->staff, 'search_orders_rest' ),
				'permission_callback' => array( $this, 'can_manage_orders' ),
			)
		);

		// Print substeps routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/print-substeps/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->print_substeps, 'update_substep_rest' ),
				'permission_callback' => array( $this, 'can_manage_orders' ),
			)
		);

		// Get correction fees for an order
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/order-correction-fees/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_correction_fees' ),
				'permission_callback' => array( $this, 'is_user_logged_in' ),
			)
		);

		// User orders panel routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/user-orders/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->user, 'search_user_orders' ),
				'permission_callback' => array( $this, 'is_user_logged_in' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/user-orders/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->user, 'get_orders_summary' ),
				'permission_callback' => array( $this, 'is_user_logged_in' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/user-orders/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->user, 'get_order_details' ),
				'permission_callback' => array( $this, 'is_user_logged_in' ),
			)
		);

		// Admin dashboard routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/search-orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin, 'rest_search_orders' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/order-details/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin, 'rest_get_order_details' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/update-order/(?P<order_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin, 'rest_update_order' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/update-customer/(?P<user_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin, 'rest_update_customer' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// Admin Order Form routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/form-config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_order_form, 'rest_get_form_config' ),
				'permission_callback' => array( $this->admin_order_form, 'user_has_access' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/search-customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_search_customers' ),
				'permission_callback' => array( $this->admin_order_form, 'user_has_access' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/create-customer',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_create_customer' ),
				'permission_callback' => array( $this->admin_order_form, 'user_has_access' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/submit-order',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_order_creator, 'rest_submit_order' ),
				'permission_callback' => array( $this->admin_order_form, 'user_has_access' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/files/generate-token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_generate_file_token' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// SMS test endpoint
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/sms/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_test_sms' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// SMS connection test endpoint
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/sms/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_test_sms_connection' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// User search endpoint for staff access control
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/users/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_search_users' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// Export/Import routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/export',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_export_data' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_import_data' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/import/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_validate_import' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/export/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_export_preview' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// Cleanup/Delete routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/cleanup/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_cleanup_preview' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/cleanup/order-preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_order_preview' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/cleanup/orders',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_cleanup_orders' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/cleanup/files',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_cleanup_files' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/cleanup/logs',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_cleanup_logs' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/cleanup/reset-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_reset_settings' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/cleanup/factory-reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_factory_reset' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// Admin order creator routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/search-users-live',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->admin_order_creator, 'rest_search_users_live' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/create-user',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_order_creator, 'rest_create_user' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/admin/create-order',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->admin_order_creator, 'rest_create_order' ),
				'permission_callback' => array( $this, 'can_manage_admin' ),
			)
		);

		// Firewall routes
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/firewall/lockdown/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_firewall_lockdown_activate' ),
				'permission_callback' => '__return_true', // Authentication via secret key in request
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/firewall/lockdown/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_firewall_lockdown_deactivate' ),
				'permission_callback' => '__return_true', // Authentication via secret key in request
			)
		);

		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/firewall/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_firewall_status' ),
				'permission_callback' => '__return_true', // Authentication via secret key in request
			)
		);
	}

	/**
	 * Check if user can manage orders
	 * Staff with edit_shop_orders capability and admins can manage orders
	 * Also checks staff allowed users list for enhanced security
	 */
	public function can_manage_orders() {
		// Admins and shop managers always have access
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}

		// Check if user is in the staff allowed list (using secure method)
		$user_id = get_current_user_id();
		if ( $user_id && isset( $this->staff ) ) {
			return $this->staff->user_has_staff_access_secure( $user_id );
		}

		return false;
	}

	/**
	 * Check if user can access admin dashboard
	 * Checks both capability and allowed users list for enhanced security
	 */
	public function can_manage_admin() {
		// Admins always have access
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		// Check if user is in the allowed list (using secure method)
		$user_id = get_current_user_id();
		if ( $user_id && isset( $this->admin ) ) {
			return $this->admin->user_has_admin_dashboard_access( $user_id );
		}

		return false;
	}

	/**
	 * REST: Generate file download token
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response object
	 */
	public function rest_generate_file_token( $request ) {
		$params  = $request->get_json_params();
		$file_id = intval( $params['file_id'] ?? 0 );
		$user_id = get_current_user_id();

		if ( $file_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شناسه فایل نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		// Use file security class to generate token
		$file_security = new Tabesh_File_Security();
		$result        = $file_security->generate_download_token( $file_id, $user_id, 24 );

		if ( ! $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result['message'],
				),
				403
			);
		}

		// Build download URL
		$download_url = add_query_arg(
			array(
				'tabesh_download' => 1,
				'file_id'         => $file_id,
				'token'           => $result['token'],
			),
			home_url()
		);

		return new WP_REST_Response(
			array(
				'success'      => true,
				'download_url' => $download_url,
				'expires_at'   => $result['expires_at'],
			),
			200
		);
	}

	/**
	 * REST: Test SMS sending
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response object
	 */
	public function rest_test_sms( $request ) {
		$params       = $request->get_json_params();
		$phone        = sanitize_text_field( $params['phone'] ?? '' );
		$pattern_code = sanitize_text_field( $params['pattern_code'] ?? '' );

		if ( empty( $phone ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شماره موبایل الزامی است', 'tabesh' ),
				),
				400
			);
		}

		if ( empty( $pattern_code ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'کد الگو الزامی است', 'tabesh' ),
				),
				400
			);
		}

		// Validate phone
		$validated_phone = $this->sms->validate_phone( $phone );
		if ( ! $validated_phone ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شماره موبایل نامعتبر است. فرمت صحیح: 09xxxxxxxxx', 'tabesh' ),
				),
				400
			);
		}

		// Send test SMS
		$result = $this->sms->send_test_sms( $validated_phone, $pattern_code );

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
				'success' => true,
				'message' => __( 'پیامک تست با موفقیت ارسال شد', 'tabesh' ),
			),
			200
		);
	}

	/**
	 * REST: Test SMS connection to MelliPayamak panel
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response object
	 */
	public function rest_test_sms_connection( $request ) {
		// Test connection using SMS handler
		$result = $this->sms->test_connection();

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
				'success' => true,
				'message' => $result['message'],
				'credit'  => $result['credit'],
			),
			200
		);
	}

	/**
	 * REST: Search users for staff access control
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response object
	 */
	public function rest_search_users( $request ) {
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$per_page = min( 50, max( 1, intval( $request->get_param( 'per_page' ) ?? 10 ) ) );

		if ( strlen( $search ) < 2 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'حداقل ۲ کاراکتر برای جستجو وارد کنید', 'tabesh' ),
				),
				400
			);
		}

		// Search users
		$user_query = new WP_User_Query(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_nicename', 'display_name', 'user_email' ),
				'number'         => $per_page,
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);

		$users           = $user_query->get_results();
		$formatted_users = array();

		foreach ( $users as $user ) {
			$formatted_users[] = array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'user_email'   => $user->user_email,
				'user_login'   => $user->user_login,
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'users'   => $formatted_users,
				'total'   => $user_query->get_total(),
			),
			200
		);
	}

	/**
	 * REST: Search customers for admin order form
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_search_customers( $request ) {
		$query = sanitize_text_field( $request->get_param( 'q' ) ?? '' );

		if ( strlen( $query ) < 2 ) {
			return new WP_REST_Response( array(), 200 );
		}

		// Search by name, email, phone, or username
		$args = array(
			'search'         => '*' . $query . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'display_name', 'user_email' ),
			'number'         => 10,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
		);

		$user_query = new WP_User_Query( $args );
		$users      = $user_query->get_results();

		// Also search by phone in user meta
		global $wpdb;
		$phone_users = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$wpdb->usermeta} 
				WHERE meta_key = 'billing_phone' 
				AND meta_value LIKE %s 
				LIMIT 10",
				'%' . $wpdb->esc_like( $query ) . '%'
			)
		);

		// Merge results
		if ( ! empty( $phone_users ) ) {
			foreach ( $phone_users as $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user && ! in_array( $user, $users, true ) ) {
					$users[] = $user;
				}
			}
		}

		$formatted_users = array();
		foreach ( $users as $user ) {
			$formatted_users[] = array(
				'ID'            => $user->ID,
				'display_name'  => $user->display_name,
				'user_email'    => $user->user_email,
				'user_login'    => $user->user_login,
				'billing_phone' => get_user_meta( $user->ID, 'billing_phone', true ),
				'billing_state' => get_user_meta( $user->ID, 'billing_state', true ),
			);
		}

		return new WP_REST_Response( $formatted_users, 200 );
	}

	/**
	 * REST: Create a new customer
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_create_customer( $request ) {
		$params = $request->get_json_params();

		// Validate required fields
		$mobile     = sanitize_text_field( $params['mobile'] ?? '' );
		$first_name = sanitize_text_field( $params['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $params['last_name'] ?? '' );

		if ( empty( $mobile ) || empty( $first_name ) || empty( $last_name ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'تمام فیلدها الزامی هستند', 'tabesh' ),
				),
				400
			);
		}

		// Validate mobile format
		if ( ! preg_match( '/^09[0-9]{9}$/', $mobile ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'فرمت شماره موبایل نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		// Check if user with this mobile already exists
		global $wpdb;
		$existing_user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' AND meta_value = %s",
				$mobile
			)
		);

		if ( $existing_user_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'کاربری با این شماره موبایل قبلاً ثبت شده است', 'tabesh' ),
				),
				400
			);
		}

		// Create username from mobile
		$username = 'user_' . $mobile;

		// Check if username exists
		if ( username_exists( $username ) ) {
			$username = $username . '_' . wp_rand( 100, 999 );
		}

		// Generate random password
		$password = wp_generate_password( 12, false );

		// Create user
		$user_id = wp_create_user( $username, $password, $mobile . '@temp.local' );

		if ( is_wp_error( $user_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $user_id->get_error_message(),
				),
				500
			);
		}

		// Update user meta
		update_user_meta( $user_id, 'first_name', $first_name );
		update_user_meta( $user_id, 'last_name', $last_name );
		update_user_meta( $user_id, 'billing_phone', $mobile );
		update_user_meta( $user_id, 'billing_first_name', $first_name );
		update_user_meta( $user_id, 'billing_last_name', $last_name );

		// Update display name
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $first_name . ' ' . $last_name,
			)
		);

		// Get created user
		$user = get_userdata( $user_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'ID'            => $user->ID,
					'display_name'  => $user->display_name,
					'user_email'    => $user->user_email,
					'user_login'    => $user->user_login,
					'billing_phone' => $mobile,
				),
			),
			201
		);
	}

	/**
	 * REST: Get available pricing options for a book size
	 *
	 * This endpoint returns the allowed paper types, bindings, and print types
	 * based on the configured restrictions for a specific book size.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_available_options( $request ) {
		$book_size = sanitize_text_field( $request->get_param( 'book_size' ) );

		// Check if V2 engine is enabled.
		$pricing_engine = new Tabesh_Pricing_Engine();

		if ( ! $pricing_engine->is_enabled() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'موتور قیمت‌گذاری جدید فعال نیست', 'tabesh' ),
				),
				400
			);
		}

		// Use constraint manager for enhanced options.
		if ( ! class_exists( 'Tabesh_Constraint_Manager' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Constraint Manager موجود نیست', 'tabesh' ),
				),
				500
			);
		}

		$constraint_manager = new Tabesh_Constraint_Manager();
		$options            = $constraint_manager->get_allowed_options( array(), $book_size );

		if ( isset( $options['error'] ) && $options['error'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $options['message'],
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success'               => true,
				'book_size'             => $options['book_size'],
				'allowed_papers'        => $options['allowed_papers'],
				'allowed_bindings'      => $options['allowed_bindings'],
				'allowed_print_types'   => $options['allowed_print_types'],
				'allowed_cover_weights' => $options['allowed_cover_weights'],
				'allowed_extras'        => $options['allowed_extras'],
			),
			200
		);
	}

	/**
	 * REST: Get allowed options dynamically based on current selection
	 *
	 * This is the key endpoint for step-by-step form UX.
	 * Returns what options are valid for next steps based on current selections.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_allowed_options_dynamic( $request ) {
		$params = $request->get_json_params();

		$book_size         = sanitize_text_field( $params['book_size'] ?? '' );
		$current_selection = $params['current_selection'] ?? array();

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh V2 API: get_allowed_options called for book_size: ' . $book_size );
			error_log( 'Tabesh V2 API: current_selection: ' . wp_json_encode( $current_selection ) );
		}

		if ( empty( $book_size ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'قطع کتاب الزامی است', 'tabesh' ),
				),
				400
			);
		}

		// Check if V2 engine is enabled.
		$pricing_engine = new Tabesh_Pricing_Engine();
		if ( ! $pricing_engine->is_enabled() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh V2 API: Pricing Engine V2 is NOT enabled' );
			}
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'موتور قیمت‌گذاری جدید فعال نیست', 'tabesh' ),
				),
				400
			);
		}

		// Use constraint manager.
		if ( ! class_exists( 'Tabesh_Constraint_Manager' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Constraint Manager موجود نیست', 'tabesh' ),
				),
				500
			);
		}

		$constraint_manager = new Tabesh_Constraint_Manager();
		$options            = $constraint_manager->get_allowed_options( $current_selection, $book_size );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Tabesh V2 API: Options returned - papers count: ' . count( $options['allowed_papers'] ?? array() ) );
			error_log( 'Tabesh V2 API: Options returned - bindings count: ' . count( $options['allowed_bindings'] ?? array() ) );
		}

		if ( isset( $options['error'] ) && $options['error'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $options['message'],
				),
				400
			);
		}

		// Cache result for performance.
		$cache_key = 'tabesh_allowed_options_' . md5( wp_json_encode( $params ) );
		wp_cache_set( $cache_key, $options, 'tabesh', 300 ); // 5 minutes cache.

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $options,
			),
			200
		);
	}

	/**
	 * REST: Validate parameter combination
	 *
	 * Validates a complete parameter combination and returns detailed feedback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_validate_combination( $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) || empty( $params ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'داده‌های نامعتبر', 'tabesh' ),
				),
				400
			);
		}

		// Check if V2 engine is enabled.
		$pricing_engine = new Tabesh_Pricing_Engine();
		if ( ! $pricing_engine->is_enabled() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'موتور قیمت‌گذاری جدید فعال نیست', 'tabesh' ),
				),
				400
			);
		}

		// Use constraint manager for validation.
		if ( ! class_exists( 'Tabesh_Constraint_Manager' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Constraint Manager موجود نیست', 'tabesh' ),
				),
				500
			);
		}

		$constraint_manager = new Tabesh_Constraint_Manager();
		$validation         = $constraint_manager->validate_combination( $params );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'allowed'     => $validation['allowed'],
				'status'      => $validation['status'],
				'message'     => $validation['message'],
				'suggestions' => $validation['suggestions'] ?? array(),
			),
			200
		);
	}

	/**
	 * Handle file download requests via template_redirect
	 *
	 * Intercepts requests with tabesh_download=1 parameter and serves the file
	 * securely using the Tabesh_File_Security class.
	 *
	 * @return void
	 */
	public function handle_file_download_request() {
		// Check if this is a download request.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token validation is done in serve_file_securely
		if ( ! isset( $_GET['tabesh_download'] ) || intval( $_GET['tabesh_download'] ) !== 1 ) {
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token validation is done in serve_file_securely
		$file_id = isset( $_GET['file_id'] ) ? intval( $_GET['file_id'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token validation is done in serve_file_securely
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( $file_id <= 0 || empty( $token ) ) {
			wp_die(
				esc_html__( 'پارامترهای نامعتبر', 'tabesh' ),
				esc_html__( 'خطا', 'tabesh' ),
				array( 'response' => 400 )
			);
		}

		// Use file security class to serve the file
		$file_security = new Tabesh_File_Security();
		$file_security->serve_file_securely( $file_id, $token );

		// serve_file_securely calls exit, but just in case
		exit;
	}

	/**
	 * Handle REST API cookie authentication
	 *
	 * This filter ensures that WordPress properly authenticates users via cookies and nonce
	 * for REST API requests. This is necessary because WordPress has security restrictions
	 * on cookie-based REST API authentication.
	 *
	 * CRITICAL FIX: This method MUST return true for all logged-in users with valid nonce,
	 * regardless of their role (admin, staff, customer, etc.). WordPress by default only
	 * allows cookie auth for users with edit_posts capability, which excludes customers.
	 * This filter bypasses that restriction for our plugin's endpoints.
	 *
	 * IMPORTANT: When nonce is invalid/missing, we must return null (not WP_Error) to allow
	 * WordPress to try other authentication methods. Returning WP_Error blocks all auth attempts!
	 *
	 * @param WP_Error|null|bool $result Error from previous authentication handler, or null.
	 * @return WP_Error|null|bool
	 */
	public function rest_cookie_authentication( $result ) {
		// If another authentication method already succeeded, use that
		if ( ! empty( $result ) ) {
			return $result;
		}

		// Check if this is a REST API request to one of our endpoints
		// Safely get and validate REQUEST_URI
		$request_uri = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		// Validate that URI contains our REST namespace
		$rest_path = '/wp-json/' . TABESH_REST_NAMESPACE . '/';

		// Early return if not our endpoint
		if ( empty( $request_uri ) || strpos( $request_uri, $rest_path ) === false ) {
			return $result;
		}

		// Check if user is already logged in via cookies
		// IMPORTANT: This check works for ALL logged-in users, regardless of role
		if ( is_user_logged_in() ) {
			// Verify nonce
			$nonce = null;
			if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
				$nonce = sanitize_text_field( $_SERVER['HTTP_X_WP_NONCE'] );
			} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
				$nonce = sanitize_text_field( $_REQUEST['_wpnonce'] );
			}

			if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				// CRITICAL: User is authenticated with valid nonce
				// Return true to authenticate this user for REST API access
				// This works for ALL roles: admin, staff, customer, subscriber, etc.
				return true;
			}

			// CRITICAL: If nonce is invalid or missing, we MUST return null (not WP_Error)
			// to allow WordPress to try other authentication methods.
			// Returning WP_Error here would block ALL authentication attempts, preventing
			// the user from being recognized as logged in.
			//
			// The permission callback (check_rest_api_permission) will handle the final
			// authentication check and return appropriate error messages.
			//
			// WordPress authentication filter pattern:
			// - return true: Successfully authenticated by this method
			// - return WP_Error: Block ALL authentication attempts (DON'T DO THIS)
			// - return null: This method doesn't apply, try other methods
		}

		// User not logged in or no valid nonce - return null to try other auth methods
		return $result;
	}

	/**
	 * Check REST API permission for authenticated endpoints
	 *
	 * This method properly handles WordPress REST API authentication which can work via:
	 * 1. Cookie authentication (requires logged-in cookies + X-WP-Nonce header)
	 * 2. Application passwords (HTTP Basic Auth)
	 * 3. OAuth tokens
	 *
	 * For cookie authentication, WordPress REST API infrastructure validates the nonce
	 * and determines the current user BEFORE calling this permission callback.
	 *
	 * CRITICAL: This permission check allows ALL logged-in users to access file upload
	 * endpoints, regardless of their role (admin, staff, customer, subscriber, etc.).
	 * Authorization for specific orders is handled separately in the upload_file() method.
	 *
	 * @return bool|WP_Error True if authenticated, WP_Error otherwise
	 */
	public function check_rest_api_permission() {
		// Check if user is authenticated via standard WordPress authentication
		// This works for cookie auth, application passwords, and other auth methods
		// IMPORTANT: Returns true for ANY logged-in user, regardless of role
		if ( is_user_logged_in() ) {
			return true;
		}

		// Fallback: Check if user ID is set directly
		// Get user ID here only when needed (after is_user_logged_in() check)
		$user_id = get_current_user_id();

		// This handles edge cases in REST API context where WordPress has set
		// the current user (e.g., via application passwords or OAuth) but
		// is_user_logged_in() hasn't been updated yet in the request lifecycle
		// IMPORTANT: Returns true for ANY user with a valid user ID > 0
		if ( $user_id > 0 ) {
			return true;
		}

		// Debug: Log authentication failure details
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$nonce   = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( $_SERVER['HTTP_X_WP_NONCE'] ) : 'not set';
			$cookies = isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) ? 'present' : 'missing';
			error_log(
				sprintf(
					'Tabesh REST API auth failed - User ID: %d, Nonce: %s, Cookie: %s',
					$user_id,
					$nonce === 'not set' ? 'not set' : 'present',
					$cookies
				)
			);
		}

		// Not authenticated - provide helpful error message
		return new WP_Error(
			'rest_forbidden',
			__( 'برای دسترسی به این منبع باید وارد سیستم شوید. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.', 'tabesh' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Check if user is logged in or has valid REST nonce (for REST API permission callback)
	 *
	 * WordPress REST API with cookie authentication requires both:
	 * 1. WordPress authentication cookies to be sent with the request
	 * 2. Valid X-WP-Nonce header
	 *
	 * However, WordPress's REST API infrastructure handles the nonce verification
	 * and sets the current user BEFORE calling permission callbacks.
	 *
	 * This means if the nonce is valid, get_current_user_id() will return the user ID
	 * even if is_user_logged_in() returns false (e.g., due to cookie issues).
	 *
	 * @return bool True if user is authenticated
	 */
	public function is_user_logged_in() {
		// Check if user is logged in via cookies (standard case)
		if ( is_user_logged_in() ) {
			return true;
		}

		// In REST API context, check if current user is set by nonce verification
		// WordPress REST API sets the current user based on valid nonce
		$user_id = get_current_user_id();
		return $user_id > 0;
	}

	/**
	 * Register shortcodes
	 */
	private function register_shortcodes() {
		add_shortcode( 'tabesh_order_form', array( $this->order, 'render_order_form' ) );
		add_shortcode( 'tabesh_order_form_v2', array( $this->order, 'render_order_form_v2' ) );
		add_shortcode( 'tabesh_order_form_slider', array( $this->order_form_slider, 'render_order_form_slider' ) );
		add_shortcode( 'tabesh_user_orders', array( $this->user, 'render_user_orders' ) );
		add_shortcode( 'tabesh_staff_panel', array( $this->staff, 'render_staff_panel' ) );
		// Use React dashboard instead of legacy PHP template
		add_shortcode( 'tabesh_admin_dashboard', array( $this->react_dashboard, 'render_dashboard' ) );
		add_shortcode( 'tabesh_file_upload', array( $this, 'render_file_upload' ) );
		add_shortcode( 'tabesh_upload_manager', array( $this->upload, 'render_upload_manager' ) );
		add_shortcode( 'tabesh_admin_order_form', array( $this->admin_order_form, 'render' ) );
		add_shortcode( 'tabesh_product_pricing', array( $this, 'render_product_pricing' ) );
	}

	/**
	 * Render file upload shortcode
	 *
	 * This is kept for backward compatibility. New implementations
	 * should use [tabesh_upload_manager] shortcode.
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function render_file_upload( $atts = array() ) {
		// Delegate to upload manager
		return $this->upload->render_upload_manager( $atts );
	}

	/**
	 * Render product pricing shortcode
	 *
	 * Shortcode: [tabesh_product_pricing]
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function render_product_pricing( $atts = array() ) {
		// Delegate to product pricing handler
		return $this->product_pricing->render();
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Ensure admin handler is initialized
		if ( ! $this->admin ) {
			return;
		}

		// Enqueue Dashicons for logged-in users to ensure icons display properly
		if ( is_user_logged_in() ) {
			wp_enqueue_style( 'dashicons' );
		}

		// Use filemtime for cache busting in development
		// Helper function to safely get file modification time
		$get_file_version = function ( $file_path ) {
			if ( WP_DEBUG && file_exists( $file_path ) ) {
				$mtime = @filemtime( $file_path );
				return $mtime !== false ? $mtime : TABESH_VERSION;
			}
			return TABESH_VERSION;
		};

		$css_version = $get_file_version( TABESH_PLUGIN_DIR . 'assets/css/frontend.css' );
		$js_version  = $get_file_version( TABESH_PLUGIN_DIR . 'assets/js/frontend.js' );

		wp_enqueue_style(
			'tabesh-frontend',
			TABESH_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$css_version
		);

		wp_enqueue_style(
			'tabesh-file-upload',
			TABESH_PLUGIN_URL . 'assets/css/file-upload.css',
			array(),
			$get_file_version( TABESH_PLUGIN_DIR . 'assets/css/file-upload.css' )
		);

		// Enqueue staff panel assets with higher specificity and cache busting
		$staff_css_version = $get_file_version( TABESH_PLUGIN_DIR . 'assets/css/staff-panel.css' );
		wp_enqueue_style(
			'tabesh-staff-panel',
			TABESH_PLUGIN_URL . 'assets/css/staff-panel.css',
			array(),
			$staff_css_version
		);

		wp_enqueue_script(
			'tabesh-frontend',
			TABESH_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			$js_version,
			true
		);

		wp_enqueue_script(
			'tabesh-file-upload',
			TABESH_PLUGIN_URL . 'assets/js/file-upload.js',
			array( 'jquery', 'tabesh-frontend' ),
			$get_file_version( TABESH_PLUGIN_DIR . 'assets/js/file-upload.js' ),
			true
		);

		// Enqueue Order Form V2 assets
		wp_enqueue_style(
			'tabesh-order-form-v2',
			TABESH_PLUGIN_URL . 'assets/css/order-form-v2.css',
			array(),
			$get_file_version( TABESH_PLUGIN_DIR . 'assets/css/order-form-v2.css' )
		);

		wp_enqueue_script(
			'tabesh-order-form-v2',
			TABESH_PLUGIN_URL . 'assets/js/order-form-v2.js',
			array( 'jquery' ),
			$get_file_version( TABESH_PLUGIN_DIR . 'assets/js/order-form-v2.js' ),
			true
		);

		// Localize script for Order Form V2
		wp_localize_script(
			'tabesh-order-form-v2',
			'tabeshOrderFormV2',
			array(
				'apiUrl'         => rest_url( TABESH_REST_NAMESPACE ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'userOrdersUrl'  => home_url( '/user-orders/' ), // Default redirect after order submission
				'i18n'           => array(
					'loading'       => __( 'در حال بارگذاری...', 'tabesh' ),
					'calculating'   => __( 'در حال محاسبه قیمت...', 'tabesh' ),
					'submitting'    => __( 'در حال ثبت سفارش...', 'tabesh' ),
					'error'         => __( 'خطا در پردازش درخواست', 'tabesh' ),
					'success'       => __( 'عملیات با موفقیت انجام شد', 'tabesh' ),
					'noOptions'     => __( 'هیچ گزینه‌ای در دسترس نیست', 'tabesh' ),
					'selectFirst'   => __( 'ابتدا گزینه قبلی را انتخاب کنید', 'tabesh' ),
					'invalidField'  => __( 'لطفاً این فیلد را پر کنید', 'tabesh' ),
					'priceEngineV2' => __( 'موتور قیمت‌گذاری V2', 'tabesh' ),
				),
			)
		);

		// Enqueue Order Form Slider Integration assets
		wp_enqueue_style(
			'tabesh-order-form-slider',
			TABESH_PLUGIN_URL . 'assets/css/order-form-slider.css',
			array(),
			$get_file_version( TABESH_PLUGIN_DIR . 'assets/css/order-form-slider.css' )
		);

		wp_enqueue_script(
			'tabesh-order-form-slider',
			TABESH_PLUGIN_URL . 'assets/js/order-form-slider.js',
			array( 'jquery' ),
			$get_file_version( TABESH_PLUGIN_DIR . 'assets/js/order-form-slider.js' ),
			true
		);

		// Localize script for Order Form Slider (same config as V2 plus slider-specific)
		wp_localize_script(
			'tabesh-order-form-slider',
			'tabeshOrderFormV2',  // Keep same name for compatibility
			array(
				'apiUrl'         => rest_url( TABESH_REST_NAMESPACE ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'userOrdersUrl'  => home_url( '/user-orders/' ),
				'i18n'           => array(
					'loading'       => __( 'در حال بارگذاری...', 'tabesh' ),
					'calculating'   => __( 'در حال محاسبه قیمت...', 'tabesh' ),
					'submitting'    => __( 'در حال ثبت سفارش...', 'tabesh' ),
					'error'         => __( 'خطا در پردازش درخواست', 'tabesh' ),
					'success'       => __( 'عملیات با موفقیت انجام شد', 'tabesh' ),
					'noOptions'     => __( 'هیچ گزینه‌ای در دسترس نیست', 'tabesh' ),
					'selectFirst'   => __( 'ابتدا گزینه قبلی را انتخاب کنید', 'tabesh' ),
					'invalidField'  => __( 'لطفاً این فیلد را پر کنید', 'tabesh' ),
					'priceEngineV2' => __( 'موتور قیمت‌گذاری V2', 'tabesh' ),
					'sliderReady'   => __( 'فرم آماده ارتباط با اسلایدر است', 'tabesh' ),
				),
			)
		);

		$staff_js_version = $get_file_version( TABESH_PLUGIN_DIR . 'assets/js/staff-panel.js' );
		wp_enqueue_script(
			'tabesh-staff-panel',
			TABESH_PLUGIN_URL . 'assets/js/staff-panel.js',
			array( 'jquery' ),
			$staff_js_version,
			true
		);

		// Add inline CSS for staff panel to ensure styles are not overridden
		// This adds critical CSS with higher specificity to prevent theme/plugin conflicts
		$staff_panel_inline_css = "
            /* Critical Staff Panel Styles - Higher Specificity to prevent theme/plugin override */
            
            /* Wrapper styles with highest priority */
            html body .tabesh-staff-panel {
                font-family: 'Vazirmatn', 'Vazir', 'Tahoma', 'Arial', sans-serif !important;
                direction: rtl !important;
                text-align: right !important;
                background: var(--bg-primary) !important;
                color: var(--text-primary) !important;
                isolation: isolate; /* Create stacking context */
            }
            
            html body .tabesh-staff-panel * {
                box-sizing: border-box !important;
            }
            
            /* Ensure CSS variables are always set correctly */
            html body .tabesh-staff-panel:not([data-theme]),
            html body .tabesh-staff-panel[data-theme='light'] {
                --bg-primary: #f0f3f7;
                --bg-secondary: #ffffff;
                --bg-card: #ffffff;
                --bg-hover: #f8f9fb;
                --text-primary: #1a202c;
                --text-secondary: #4a5568;
                --text-tertiary: #a0aec0;
                --text-muted: #cbd5e0;
                --accent-blue: #4a90e2;
                --shadow-neumorphic: 12px 12px 24px rgba(163, 177, 198, 0.6), -12px -12px 24px rgba(255, 255, 255, 0.5);
                --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.08);
                --border-radius: 16px;
                --border-radius-sm: 8px;
                --border-radius-full: 9999px;
                --transition-speed: 0.3s;
                --z-header: 1000;
            }
            
            html body .tabesh-staff-panel[data-theme='dark'] {
                --bg-primary: #1a202c;
                --bg-secondary: #2d3748;
                --bg-card: #2d3748;
                --bg-hover: #374151;
                --text-primary: #f7fafc;
                --text-secondary: #e2e8f0;
                --text-tertiary: #a0aec0;
                --text-muted: #718096;
                --shadow-neumorphic: 12px 12px 24px rgba(0, 0, 0, 0.5), -12px -12px 24px rgba(74, 85, 104, 0.1);
                --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.4);
            }
            
            /* Prevent theme/plugin button style conflicts */
            html body .tabesh-staff-panel button,
            html body .tabesh-staff-panel .tabesh-btn,
            html body .tabesh-staff-panel input[type='button'],
            html body .tabesh-staff-panel input[type='submit'] {
                font-family: 'Vazirmatn', 'Vazir', 'Tahoma', 'Arial', sans-serif !important;
                line-height: normal !important;
            }
            
            /* Prevent theme/plugin input style conflicts */
            html body .tabesh-staff-panel input,
            html body .tabesh-staff-panel select,
            html body .tabesh-staff-panel textarea {
                font-family: 'Vazirmatn', 'Vazir', 'Tahoma', 'Arial', sans-serif !important;
                direction: rtl !important;
                text-align: right !important;
            }
            
            /* Ensure header stays on top */
            html body .tabesh-staff-panel .staff-panel-header {
                position: sticky !important;
                top: 0 !important;
                z-index: 1000 !important;
            }
        ";
		wp_add_inline_style( 'tabesh-staff-panel', $staff_panel_inline_css );

		// Debug logging for asset loading (only in debug mode)
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'Tabesh: Frontend assets enqueued' );
			error_log( 'Tabesh: Staff Panel CSS version: ' . $staff_css_version );
			error_log( 'Tabesh: Staff Panel JS version: ' . $staff_js_version );
		}

		// Get all settings for frontend - always returns decoded arrays/objects
		$paper_types = $this->admin->get_setting(
			'paper_types',
			array(
				'تحریر' => array( 60, 70, 80 ),
				'بالک'  => array( 60, 70, 80, 100 ),
			)
		);

		$book_sizes          = $this->admin->get_setting( 'book_sizes', array( 'A5', 'A4', 'رقعی', 'وزیری', 'خشتی' ) );
		$print_types         = $this->admin->get_setting( 'print_types', array( 'سیاه و سفید', 'رنگی', 'ترکیبی' ) );
		$binding_types       = $this->admin->get_setting( 'binding_types', array( 'شومیز', 'جلد سخت', 'گالینگور', 'سیمی' ) );
		$license_types       = $this->admin->get_setting( 'license_types', array( 'دارم', 'انتشارات چاپکو', 'سفیر سلامت' ) );
		$cover_paper_weights = $this->admin->get_setting( 'cover_paper_weights', array( '250', '300' ) );
		$lamination_types    = $this->admin->get_setting( 'lamination_types', array( 'براق', 'مات', 'بدون سلفون' ) );
		$extras              = $this->admin->get_setting( 'extras', array( 'لب گرد', 'خط تا', 'شیرینک', 'سوراخ', 'شماره گذاری' ) );

		// Sanitize extras to ensure all values are valid non-empty strings
		$extras = is_array( $extras ) ? array_values(
			array_filter(
				array_map(
					function ( $extra ) {
						$extra = is_scalar( $extra ) ? trim( strval( $extra ) ) : '';
						return ( ! empty( $extra ) && $extra !== 'on' ) ? $extra : null;
					},
					$extras
				)
			)
		) : array();

		// Get V2 pricing engine quantity constraints and full matrices if enabled
		$pricing_engine       = new Tabesh_Pricing_Engine();
		$v2_enabled           = $pricing_engine->is_enabled();
		$quantity_constraints = array();
		$v2_pricing_matrices  = array();

		if ( $v2_enabled ) {
			// Get all configured book sizes
			$configured_sizes = $pricing_engine->get_configured_book_sizes();
			foreach ( $configured_sizes as $book_size ) {
				// Get pricing matrix directly from engine
				global $wpdb;
				$table_settings = $wpdb->prefix . 'tabesh_settings';
				
				// CRITICAL FIX: Use base64_encode to match save_pricing_matrix() method
				$safe_key    = base64_encode( $book_size );
				$setting_key = 'pricing_matrix_' . $safe_key;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
						$setting_key
					)
				);

				if ( $result ) {
					$matrix = json_decode( $result, true );
					if ( JSON_ERROR_NONE === json_last_error() && is_array( $matrix ) ) {
						// Store quantity constraints
						if ( isset( $matrix['quantity_constraints'] ) ) {
							$quantity_constraints[ $book_size ] = $matrix['quantity_constraints'];
						}

						// Store simplified matrix data for frontend - only what's needed for form population
						// Get restrictions for filtering
						$restrictions          = $matrix['restrictions'] ?? array();
						$forbidden_papers      = $restrictions['forbidden_paper_types'] ?? array();
						$forbidden_bindings    = $restrictions['forbidden_binding_types'] ?? array();
						$forbidden_print_types = $restrictions['forbidden_print_types'] ?? array();

						$v2_pricing_matrices[ $book_size ] = array(
							'paper_types'   => array(),
							'binding_types' => array(),
							'extras'        => array_keys( $matrix['extras_costs'] ?? array() ),
						);

						// Filter binding types to exclude forbidden ones
						$all_bindings = array_keys( $matrix['binding_costs'] ?? array() );
						foreach ( $all_bindings as $binding_type ) {
							if ( ! in_array( $binding_type, $forbidden_bindings, true ) ) {
								$v2_pricing_matrices[ $book_size ]['binding_types'][] = $binding_type;
							}
						}

						// Extract paper types with their weights
						// Only include paper types that aren't completely forbidden
						if ( isset( $matrix['page_costs'] ) && is_array( $matrix['page_costs'] ) ) {
							foreach ( $matrix['page_costs'] as $paper_type => $weights_data ) {
								// Skip if paper type is completely forbidden
								if ( in_array( $paper_type, $forbidden_papers, true ) ) {
									continue;
								}

								// CRITICAL FIX: With per-weight restrictions, we need to check each weight individually.
								// Only include weights that have at least one allowed print type.
								$available_weights = array();
								
								foreach ( array_keys( $weights_data ) as $weight ) {
									// Get forbidden print types for this specific weight
									$forbidden_for_weight = $forbidden_print_types[ $paper_type ][ $weight ] ?? array();

									// Check if both bw and color are forbidden for this weight
									$bw_forbidden    = in_array( 'bw', $forbidden_for_weight, true );
									$color_forbidden = in_array( 'color', $forbidden_for_weight, true );

									// Only include this weight if at least one print type is allowed
									if ( ! ( $bw_forbidden && $color_forbidden ) ) {
										$available_weights[] = $weight;
									}
								}
								
								// Only include this paper type if it has at least one available weight
								if ( ! empty( $available_weights ) ) {
									$v2_pricing_matrices[ $book_size ]['paper_types'][ $paper_type ] = $available_weights;
								}
							}
						}
					}
				}
			}

			// Override global settings with V2 data for the first book size (default)
			if ( ! empty( $v2_pricing_matrices ) && ! empty( $configured_sizes ) ) {
				$default_size = $configured_sizes[0];
				if ( isset( $v2_pricing_matrices[ $default_size ] ) ) {
					// For V2, use the first book size's parameters as defaults
					$v2_data = $v2_pricing_matrices[ $default_size ];

					// Override book sizes with configured ones
					$book_sizes = $configured_sizes;

					// Override paper types, binding types, extras with V2 data
					// Note: We keep the format compatible with V1 (paper_type => [weights])
					if ( ! empty( $v2_data['paper_types'] ) ) {
						$paper_types = $v2_data['paper_types'];
					}
					if ( ! empty( $v2_data['binding_types'] ) ) {
						$binding_types = $v2_data['binding_types'];
					}
					if ( ! empty( $v2_data['extras'] ) ) {
						$extras = $v2_data['extras'];
					}
				}
			}
		}

		wp_localize_script(
			'tabesh-frontend',
			'tabeshData',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'restUrl'             => rest_url( TABESH_REST_NAMESPACE ),
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'ajaxNonce'           => wp_create_nonce( 'tabesh_nonce' ), // For AJAX backward compatibility (field name: 'security')
				'logoutUrl'           => wp_logout_url( home_url() ),
				'debug'               => WP_DEBUG, // Add debug flag for conditional console logging
				// Settings - all decoded as arrays/objects for frontend use
				'settings'            => array(
					'paperTypes'        => $paper_types,
					'bookSizes'         => $book_sizes,
					'printTypes'        => $print_types,
					'bindingTypes'      => $binding_types,
					'licenseTypes'      => $license_types,
					'coverPaperWeights' => $cover_paper_weights,
					'laminationTypes'   => $lamination_types,
					'extras'            => $extras,
				),
				// Backwards compatibility
				'paperTypes'          => $paper_types,
				// V2 Pricing Engine data
				'v2Enabled'           => $v2_enabled,
				'v2PricingMatrices'   => $v2_pricing_matrices, // Full matrices for dynamic form population
				'quantityConstraints' => $quantity_constraints,
				'strings'             => array(
					'calculating' => __( 'در حال محاسبه...', 'tabesh' ),
					'error'       => __( 'خطا در پردازش درخواست', 'tabesh' ),
					'success'     => __( 'عملیات با موفقیت انجام شد', 'tabesh' ),
				),
			)
		);

		// Also provide TabeshSettings for compatibility
		wp_localize_script(
			'tabesh-frontend',
			'TabeshSettings',
			array(
				'rest_url' => rest_url( TABESH_REST_NAMESPACE ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'calculating'  => __( 'در حال محاسبه...', 'tabesh' ),
					'error'        => __( 'خطا در پردازش درخواست', 'tabesh' ),
					'success'      => __( 'عملیات با موفقیت انجام شد', 'tabesh' ),
					'submitting'   => __( 'در حال ثبت سفارش...', 'tabesh' ),
					'auth_error'   => __( 'خطای احراز هویت. لطفاً مجدداً وارد شوید.', 'tabesh' ),
					'server_error' => __( 'خطا در برقراری ارتباط با سرور', 'tabesh' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'tabesh' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'tabesh-admin',
			TABESH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TABESH_VERSION
		);

		wp_enqueue_script(
			'tabesh-admin',
			TABESH_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			TABESH_VERSION,
			true
		);

		wp_localize_script(
			'tabesh-admin',
			'tabeshAdminData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( TABESH_REST_NAMESPACE ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * AJAX handler for calculate price
	 * Provides backward compatibility with admin-ajax.php
	 */
	public function ajax_calculate_price() {
		// Verify nonce
		check_ajax_referer( 'tabesh_nonce', 'security' );

		// Get POST data
		$params = array(
			'book_size'          => isset( $_POST['book_size'] ) ? sanitize_text_field( $_POST['book_size'] ) : '',
			'paper_type'         => isset( $_POST['paper_type'] ) ? sanitize_text_field( $_POST['paper_type'] ) : '',
			'paper_weight'       => isset( $_POST['paper_weight'] ) ? sanitize_text_field( $_POST['paper_weight'] ) : '',
			'print_type'         => isset( $_POST['print_type'] ) ? sanitize_text_field( $_POST['print_type'] ) : '',
			'page_count_color'   => isset( $_POST['page_count_color'] ) ? intval( $_POST['page_count_color'] ) : 0,
			'page_count_bw'      => isset( $_POST['page_count_bw'] ) ? intval( $_POST['page_count_bw'] ) : 0,
			'quantity'           => isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 0,
			'binding_type'       => isset( $_POST['binding_type'] ) ? sanitize_text_field( $_POST['binding_type'] ) : '',
			'license_type'       => isset( $_POST['license_type'] ) ? sanitize_text_field( $_POST['license_type'] ) : '',
			'cover_paper_weight' => isset( $_POST['cover_paper_weight'] ) ? sanitize_text_field( $_POST['cover_paper_weight'] ) : '250',
			'lamination_type'    => isset( $_POST['lamination_type'] ) ? sanitize_text_field( $_POST['lamination_type'] ) : 'براق',
			'extras'             => isset( $_POST['extras'] ) ? (array) $_POST['extras'] : array(),
		);

		// Calculate price using existing order class method
		$result = $this->order->calculate_price( $params );

		// Send JSON response
		if ( $result ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => __( 'خطا در محاسبه قیمت', 'tabesh' ) ) );
		}
	}

	/**
	/**
	 * REST: Get correction fees for an order
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response object
	 */
	public function rest_get_correction_fees( $request ) {
		$order_id = intval( $request->get_param( 'order_id' ) );
		$user_id  = get_current_user_id();

		if ( $order_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شناسه سفارش نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		// Verify order ownership or admin permission
		global $wpdb;
		$order_table = $wpdb->prefix . 'tabesh_orders';
		$order       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM $order_table WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'سفارش یافت نشد', 'tabesh' ),
				),
				404
			);
		}

		if ( $order->user_id != $user_id && ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شما مجاز به مشاهده این اطلاعات نیستید', 'tabesh' ),
				),
				403
			);
		}

		// Calculate correction fees
		$fees = $this->file_manager->calculate_order_correction_fees( $order_id );

		return new WP_REST_Response(
			array(
				'success'         => true,
				'correction_fees' => $fees,
			),
			200
		);
	}

	/**
	 * Check if debug logging is enabled
	 *
	 * @return bool
	 */
	private static function should_log_debug() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Get a setting value from database with caching
	 *
	 * This method is accessible to all users and provides consistent
	 * access to plugin settings throughout the application.
	 *
	 * @param string $key Setting key
	 * @param mixed  $default Default value if setting not found
	 * @return mixed Setting value
	 */
	public function get_setting( $key, $default = '' ) {
		// Check if value is already cached
		if ( isset( self::$settings_cache[ $key ] ) ) {
			return self::$settings_cache[ $key ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM $table WHERE setting_key = %s",
				$key
			)
		);

		// If no value found in database, return default
		if ( $value === null ) {
			// Only log if WP_DEBUG is enabled to reduce log noise
			if ( self::should_log_debug() ) {
				error_log( "Tabesh: Setting not found in database, using default: $key" );
			}
			// Cache the default value
			self::$settings_cache[ $key ] = $default;
			return $default;
		}

		// Try to decode JSON
		$decoded = json_decode( $value, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			// Cache the decoded value
			self::$settings_cache[ $key ] = $decoded;
			return $decoded;
		}

		// If not JSON, try to parse as legacy format
		// Check if it looks like a comma-separated list
		if ( is_string( $value ) && strpos( $value, ',' ) !== false && strpos( $value, '=' ) === false ) {
			// Likely a legacy comma-separated array
			if ( self::should_log_debug() ) {
				error_log( "Tabesh: Parsing legacy comma-separated format for: $key" );
			}
			$parts  = array_map( 'trim', explode( ',', $value ) );
			$result = array_values( array_filter( $parts, 'strlen' ) );
			// Cache the parsed value
			self::$settings_cache[ $key ] = $result;
			return $result;
		}

		// Return as-is (scalar values, etc.)
		// Cache the value
		self::$settings_cache[ $key ] = $value;
		return $value;
	}

	/**
	 * Clear settings cache
	 * Should be called when settings are updated
	 *
	 * @param string|null $key Specific key to clear, or null to clear all
	 * @return void
	 */
	public static function clear_settings_cache( $key = null ) {
		if ( $key === null ) {
			self::$settings_cache = array();
		} elseif ( isset( self::$settings_cache[ $key ] ) ) {
			unset( self::$settings_cache[ $key ] );
		}
	}

	/**
	 * REST API: Export data
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_export_data( $request ) {
		$sections = $request->get_param( 'sections' );

		if ( ! is_array( $sections ) ) {
			$sections = array();
		}

		try {
			$export_data = $this->export_import->export( $sections );

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $export_data,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Import data
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_import_data( $request ) {
		$data     = $request->get_param( 'data' );
		$sections = $request->get_param( 'sections' );
		$mode     = $request->get_param( 'mode' );

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'داده‌های وارد شده نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		if ( ! is_array( $sections ) ) {
			$sections = array();
		}

		if ( ! in_array( $mode, array( 'merge', 'replace' ) ) ) {
			$mode = 'merge';
		}

		try {
			$result = $this->export_import->import( $data, $sections, $mode );

			return new WP_REST_Response( $result, $result['success'] ? 200 : 500 );

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Validate import file
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_validate_import( $request ) {
		$data = $request->get_param( 'data' );

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'داده‌های وارد شده نامعتبر است', 'tabesh' ),
				),
				400
			);
		}

		try {
			$preview = $this->export_import->get_import_preview( $data );

			return new WP_REST_Response( $preview, 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'valid'   => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Get export preview
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_export_preview( $request ) {
		$sections = $request->get_param( 'sections' );

		if ( ! is_array( $sections ) ) {
			$sections = array();
		}

		try {
			$preview = $this->export_import->get_export_preview( $sections );

			return new WP_REST_Response(
				array(
					'success' => true,
					'preview' => $preview,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Get cleanup preview
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_cleanup_preview( $request ) {
		try {
			$preview = $this->export_import->get_cleanup_preview();

			return new WP_REST_Response(
				array(
					'success' => true,
					'preview' => $preview,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Delete orders
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_cleanup_orders( $request ) {
		$options = array(
			'all'          => $request->get_param( 'all' ) ? true : false,
			'archived'     => $request->get_param( 'archived' ) ? true : false,
			'user_id'      => intval( $request->get_param( 'user_id' ) ?: 0 ),
			'older_than'   => intval( $request->get_param( 'older_than' ) ?: 0 ),
			'order_id'     => intval( $request->get_param( 'order_id' ) ?: 0 ),
			'order_number' => sanitize_text_field( $request->get_param( 'order_number' ) ?: '' ),
		);

		try {
			$result = $this->export_import->delete_orders( $options );

			return new WP_REST_Response( $result, 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Get order preview by order number
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_order_preview( $request ) {
		$order_number = sanitize_text_field( $request->get_param( 'order_number' ) ?: '' );

		if ( empty( $order_number ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'شناسه سفارش الزامی است',
				),
				400
			);
		}

		try {
			$order = $this->export_import->get_order_by_number( $order_number );

			if ( ! $order ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => sprintf( 'سفارش با شناسه %s یافت نشد', $order_number ),
					),
					404
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'order'   => $order,
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Delete files
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_cleanup_files( $request ) {
		$options = array(
			'database' => $request->get_param( 'database' ) ? true : false,
			'physical' => $request->get_param( 'physical' ) ? true : false,
			'orphans'  => $request->get_param( 'orphans' ) ? true : false,
		);

		try {
			$result = $this->export_import->delete_files( $options );

			return new WP_REST_Response( $result, 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Delete logs
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_cleanup_logs( $request ) {
		$options = array(
			'all'        => $request->get_param( 'all' ) ? true : false,
			'older_than' => intval( $request->get_param( 'older_than' ) ?: 0 ),
			'type'       => sanitize_text_field( $request->get_param( 'type' ) ?: 'all' ),
		);

		try {
			$result = $this->export_import->delete_logs( $options );

			return new WP_REST_Response( $result, 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Reset settings to default
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_reset_settings( $request ) {
		try {
			$result = $this->export_import->reset_settings();

			return new WP_REST_Response( $result, 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Factory reset (delete everything)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_factory_reset( $request ) {
		$confirm_key = sanitize_text_field( $request->get_param( 'confirm_key' ) );

		try {
			$result = $this->export_import->factory_reset( $confirm_key );

			if ( ! $result['success'] ) {
				return new WP_REST_Response( $result, 400 );
			}

			return new WP_REST_Response( $result, 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * REST API: Activate firewall lockdown mode
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_firewall_lockdown_activate( $request ) {
		// Get secret key from header or body
		$secret_key = $request->get_header( 'X-Firewall-Secret' );
		if ( empty( $secret_key ) ) {
			$secret_key = sanitize_text_field( $request->get_param( 'secret_key' ) );
		}

		if ( empty( $secret_key ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'کلید امنیتی ارسال نشده است', 'tabesh' ),
				),
				401
			);
		}

		$firewall = new Tabesh_Doomsday_Firewall();
		$result   = $firewall->activate_lockdown( $secret_key );

		if ( $result ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'حالت اضطراری فعال شد', 'tabesh' ),
				),
				200
			);
		} else {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'کلید امنیتی نامعتبر است', 'tabesh' ),
				),
				401
			);
		}
	}

	/**
	 * REST API: Deactivate firewall lockdown mode
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_firewall_lockdown_deactivate( $request ) {
		// Get secret key from header or body
		$secret_key = $request->get_header( 'X-Firewall-Secret' );
		if ( empty( $secret_key ) ) {
			$secret_key = sanitize_text_field( $request->get_param( 'secret_key' ) );
		}

		if ( empty( $secret_key ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'کلید امنیتی ارسال نشده است', 'tabesh' ),
				),
				401
			);
		}

		$firewall = new Tabesh_Doomsday_Firewall();
		$result   = $firewall->deactivate_lockdown( $secret_key );

		if ( $result ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'حالت اضطراری غیرفعال شد', 'tabesh' ),
				),
				200
			);
		} else {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'کلید امنیتی نامعتبر است', 'tabesh' ),
				),
				401
			);
		}
	}

	/**
	 * REST API: Get firewall status
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_firewall_status( $request ) {
		// Get secret key from header or query parameter
		$secret_key = $request->get_header( 'X-Firewall-Secret' );
		if ( empty( $secret_key ) ) {
			$secret_key = sanitize_text_field( $request->get_param( 'key' ) );
		}

		$firewall = new Tabesh_Doomsday_Firewall();

		// Verify secret key
		$stored_key = get_option( Tabesh_Doomsday_Firewall::SECRET_KEY_OPTION, '' );
		if ( empty( $stored_key ) || ! hash_equals( $stored_key, $secret_key ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'کلید امنیتی نامعتبر است', 'tabesh' ),
				),
				401
			);
		}

		$settings = $firewall->get_settings();

		return new WP_REST_Response(
			array(
				'success'  => true,
				'enabled'  => $settings['enabled'],
				'lockdown' => $settings['lockdown'],
			),
			200
		);
	}
}

/**
 * Returns the main instance of Tabesh
 */
function Tabesh() {
	return Tabesh::instance();
}

// Initialize the plugin
Tabesh();
