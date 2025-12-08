<?php
/**
 * Export/Import Handler Class
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_Export_Import
 *
 * Handles export and import functionality for Tabesh plugin data.
 * Supports exporting and importing orders, settings, customers, logs,
 * files, and other plugin-related data with validation and preview.
 *
 * @since 1.0.3
 */
class Tabesh_Export_Import {

	/**
	 * Available sections for export/import
	 *
	 * @var array
	 */
	private $available_sections = array(
		'orders'               => 'سفارشات',
		'settings'             => 'تنظیمات',
		'customers'            => 'مشتریان',
		'logs'                 => 'تاریخچه رویدادها',
		'files'                => 'فایل‌ها',
		'file_versions'        => 'نسخه‌های فایل',
		'upload_tasks'         => 'وظایف آپلود',
		'book_format_settings' => 'تنظیمات فرمت کتاب',
		'file_comments'        => 'نظرات فایل',
		'document_metadata'    => 'متادیتای اسناد',
		'download_tokens'      => 'توکن‌های دانلود',
		'security_logs'        => 'لاگ‌های امنیتی',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// No hooks needed - methods called directly via REST API
	}

	/**
	 * Get available sections
	 *
	 * @return array
	 */
	public function get_available_sections() {
		return $this->available_sections;
	}

	/**
	 * Export data sections
	 *
	 * @param array $sections Sections to export
	 * @return array Export data
	 */
	public function export( $sections = array() ) {
		// If no sections specified, export all
		if ( empty( $sections ) ) {
			$sections = array_keys( $this->available_sections );
		}

		$export_data = array(
			'version'     => TABESH_VERSION,
			'export_date' => current_time( 'mysql' ),
			'site_url'    => get_site_url(),
			'sections'    => array(),
		);

		foreach ( $sections as $section ) {
			if ( ! isset( $this->available_sections[ $section ] ) ) {
				continue;
			}

			$method = 'export_' . $section;
			if ( method_exists( $this, $method ) ) {
				$export_data['sections'][ $section ] = $this->$method();
			}
		}

		return $export_data;
	}

	/**
	 * Import data sections
	 *
	 * @param array  $data Import data
	 * @param array  $sections Sections to import
	 * @param string $mode Import mode: 'merge' or 'replace'
	 * @return array Result with success status and message
	 */
	public function import( $data, $sections = array(), $mode = 'merge' ) {
		global $wpdb;

		// Validate import data
		$validation = $this->validate_import_data( $data );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'message' => $validation['message'],
			);
		}

		// If no sections specified, import all available in data
		if ( empty( $sections ) ) {
			$sections = array_keys( $data['sections'] );
		}

		$results = array();
		$wpdb->query( 'START TRANSACTION' );

		try {
			foreach ( $sections as $section ) {
				if ( ! isset( $data['sections'][ $section ] ) || ! isset( $this->available_sections[ $section ] ) ) {
					continue;
				}

				$method = 'import_' . $section;
				if ( method_exists( $this, $method ) ) {
					$result              = $this->$method( $data['sections'][ $section ], $mode );
					$results[ $section ] = $result;

					if ( ! $result['success'] ) {
						throw new Exception( $result['message'] );
					}
				}
			}

			$wpdb->query( 'COMMIT' );

			return array(
				'success' => true,
				'message' => __( 'درونریزی با موفقیت انجام شد', 'tabesh' ),
				'results' => $results,
			);

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return array(
				'success' => false,
				/* translators: %s: Error message */
				'message' => sprintf( __( 'خطا در درونریزی: %s', 'tabesh' ), $e->getMessage() ),
				'results' => $results,
			);
		}
	}

	/**
	 * Validate import file data
	 *
	 * @param array $data Import data
	 * @return array Validation result
	 */
	public function validate_import_data( $data ) {
		// Check if data is array
		if ( ! is_array( $data ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'فرمت فایل نامعتبر است', 'tabesh' ),
			);
		}

		// Check required fields
		if ( ! isset( $data['version'] ) || ! isset( $data['export_date'] ) || ! isset( $data['sections'] ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'فایل فاقد اطلاعات ضروری است', 'tabesh' ),
			);
		}

		// Check version compatibility.
		if ( version_compare( $data['version'], TABESH_VERSION, '>' ) ) {
			return array(
				'valid'   => false,
				/* translators: 1: File version, 2: Current plugin version */
				'message' => sprintf(
					__( 'این فایل با نسخه %1$s ساخته شده و با نسخه فعلی (%2$s) سازگار نیست', 'tabesh' ),
					$data['version'],
					TABESH_VERSION
				),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'فایل معتبر است', 'tabesh' ),
		);
	}

	/**
	 * Get export preview
	 *
	 * @param array $sections Sections to preview
	 * @return array Preview data
	 */
	public function get_export_preview( $sections = array() ) {
		global $wpdb;

		if ( empty( $sections ) ) {
			$sections = array_keys( $this->available_sections );
		}

		$preview = array();

		foreach ( $sections as $section ) {
			if ( ! isset( $this->available_sections[ $section ] ) ) {
				continue;
			}

			$table = $wpdb->prefix . 'tabesh_' . $section;

			// Special handling for customers (wp_users table)
			if ( $section === 'customers' ) {
				$preview[ $section ] = array(
					'count' => $this->get_customers_count(),
					'label' => $this->available_sections[ $section ],
				);
				continue;
			}

			// Check if table exists.
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $table_exists ) {
				$count               = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$preview[ $section ] = array(
					'count' => (int) $count,
					'label' => $this->available_sections[ $section ],
				);
			}
		}

		return $preview;
	}

	/**
	 * Get import preview
	 *
	 * @param array $data Import data
	 * @return array Preview data
	 */
	public function get_import_preview( $data ) {
		$validation = $this->validate_import_data( $data );
		if ( ! $validation['valid'] ) {
			return array(
				'valid'   => false,
				'message' => $validation['message'],
			);
		}

		$preview = array(
			'valid'       => true,
			'version'     => $data['version'],
			'export_date' => $data['export_date'],
			'site_url'    => isset( $data['site_url'] ) ? $data['site_url'] : '',
			'sections'    => array(),
		);

		foreach ( $data['sections'] as $section => $section_data ) {
			if ( ! isset( $this->available_sections[ $section ] ) ) {
				continue;
			}

			$preview['sections'][ $section ] = array(
				'label' => $this->available_sections[ $section ],
				'count' => is_array( $section_data ) ? count( $section_data ) : 0,
			);
		}

		return $preview;
	}

	// ==================== EXPORT METHODS ====================

	/**
	 * Export orders
	 *
	 * @return array Orders data
	 */
	private function export_orders() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$orders = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $orders ? $orders : array();
	}

	/**
	 * Export settings
	 *
	 * @return array Settings data
	 */
	private function export_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		$settings = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $settings ? $settings : array();
	}

	/**
	 * Export customers (users related to orders)
	 *
	 * @return array Customers data
	 */
	private function export_customers() {
		global $wpdb;

		// Get unique user IDs from orders
		$order_table = $wpdb->prefix . 'tabesh_orders';
		$user_ids    = $wpdb->get_col( "SELECT DISTINCT user_id FROM $order_table" );

		if ( empty( $user_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

		// Get user data (excluding password)
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, user_login, user_email, user_registered, display_name 
                FROM {$wpdb->users} 
                WHERE ID IN ($placeholders)",
				$user_ids
			),
			ARRAY_A
		);

		// Get user meta
		foreach ( $users as &$user ) {
			$user['meta'] = get_user_meta( $user['ID'] );
		}

		return $users ? $users : array();
	}

	/**
	 * Export logs
	 *
	 * @return array Logs data
	 */
	private function export_logs() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_logs';

		$logs = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $logs ? $logs : array();
	}

	/**
	 * Export files
	 *
	 * @return array Files data
	 */
	private function export_files() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_files';

		$files = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $files ? $files : array();
	}

	/**
	 * Export file versions
	 *
	 * @return array File versions data
	 */
	private function export_file_versions() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_file_versions';

		$versions = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $versions ? $versions : array();
	}

	/**
	 * Export upload tasks
	 *
	 * @return array Upload tasks data
	 */
	private function export_upload_tasks() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_upload_tasks';

		$tasks = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $tasks ? $tasks : array();
	}

	/**
	 * Export book format settings
	 *
	 * @return array Book format settings data
	 */
	private function export_book_format_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_book_format_settings';

		$settings = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $settings ? $settings : array();
	}

	/**
	 * Export file comments
	 *
	 * @return array File comments data
	 */
	private function export_file_comments() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_file_comments';

		$comments = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $comments ? $comments : array();
	}

	/**
	 * Export document metadata
	 *
	 * @return array Document metadata
	 */
	private function export_document_metadata() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_document_metadata';

		$metadata = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $metadata ? $metadata : array();
	}

	/**
	 * Export download tokens
	 *
	 * @return array Download tokens data
	 */
	private function export_download_tokens() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_download_tokens';

		$tokens = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $tokens ? $tokens : array();
	}

	/**
	 * Export security logs
	 *
	 * @return array Security logs data
	 */
	private function export_security_logs() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_security_logs';

		$logs = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		return $logs ? $logs : array();
	}

	// ==================== IMPORT METHODS ====================

	/**
	 * Import orders
	 *
	 * @param array  $data Orders data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_orders( $data, $mode ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		if ( $mode === 'replace' ) {
			$wpdb->query( "TRUNCATE TABLE $table" );
		}

		$imported = 0;
		foreach ( $data as $order ) {
			// Remove id for insert
			$order_id = isset( $order['id'] ) ? $order['id'] : null;
			unset( $order['id'] );

			if ( $mode === 'merge' && $order_id ) {
				// Check if order exists
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $order_id ) );

				if ( $exists ) {
					$wpdb->update( $table, $order, array( 'id' => $order_id ) );
				} else {
					$order['id'] = $order_id;
					$wpdb->insert( $table, $order );
				}
			} else {
				$wpdb->insert( $table, $order );
			}

			++$imported;
		}

		return array(
			'success' => true,
			'message' => sprintf( __( '%d سفارش وارد شد', 'tabesh' ), $imported ),
		);
	}

	/**
	 * Import settings
	 *
	 * @param array  $data Settings data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_settings( $data, $mode ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		if ( $mode === 'replace' ) {
			$wpdb->query( "TRUNCATE TABLE $table" );
		}

		$imported = 0;
		foreach ( $data as $setting ) {
			$setting_key = $setting['setting_key'];
			unset( $setting['id'] );

			if ( $mode === 'merge' ) {
				// Check if setting exists
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM $table WHERE setting_key = %s",
						$setting_key
					)
				);

				if ( $exists ) {
					$wpdb->update(
						$table,
						$setting,
						array( 'setting_key' => $setting_key )
					);
				} else {
					$wpdb->insert( $table, $setting );
				}
			} else {
				$wpdb->insert( $table, $setting );
			}

			++$imported;
		}

		return array(
			'success' => true,
			'message' => sprintf( __( '%d تنظیم وارد شد', 'tabesh' ), $imported ),
		);
	}

	/**
	 * Import customers
	 *
	 * @param array  $data Customers data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_customers( $data, $mode ) {
		$imported = 0;

		foreach ( $data as $user_data ) {
			$user_id = $user_data['ID'];
			$meta    = isset( $user_data['meta'] ) ? $user_data['meta'] : array();
			unset( $user_data['meta'] );

			// Check if user exists
			$existing_user = get_user_by( 'id', $user_id );

			if ( $existing_user && $mode === 'merge' ) {
				// Update user data (excluding password)
				wp_update_user(
					array(
						'ID'           => $user_id,
						'user_email'   => $user_data['user_email'],
						'display_name' => $user_data['display_name'],
					)
				);

				// Update meta
				foreach ( $meta as $key => $value ) {
					if ( is_array( $value ) && count( $value ) === 1 ) {
						update_user_meta( $user_id, $key, $value[0] );
					}
				}

				++$imported;
			} elseif ( ! $existing_user ) {
				// Note: We don't create new users from import for security reasons
				// Users must already exist in the system
				continue;
			}
		}

		return array(
			'success' => true,
			'message' => sprintf( __( '%d مشتری بروزرسانی شد', 'tabesh' ), $imported ),
		);
	}

	/**
	 * Import logs
	 *
	 * @param array  $data Logs data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_logs( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_logs', $mode, 'لاگ' );
	}

	/**
	 * Import files
	 *
	 * @param array  $data Files data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_files( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_files', $mode, 'فایل' );
	}

	/**
	 * Import file versions
	 *
	 * @param array  $data File versions data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_file_versions( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_file_versions', $mode, 'نسخه فایل' );
	}

	/**
	 * Import upload tasks
	 *
	 * @param array  $data Upload tasks data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_upload_tasks( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_upload_tasks', $mode, 'وظیفه آپلود' );
	}

	/**
	 * Import book format settings
	 *
	 * @param array  $data Book format settings data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_book_format_settings( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_book_format_settings', $mode, 'تنظیم فرمت کتاب' );
	}

	/**
	 * Import file comments
	 *
	 * @param array  $data File comments data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_file_comments( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_file_comments', $mode, 'نظر فایل' );
	}

	/**
	 * Import document metadata
	 *
	 * @param array  $data Document metadata
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_document_metadata( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_document_metadata', $mode, 'متادیتای سند' );
	}

	/**
	 * Import download tokens
	 *
	 * @param array  $data Download tokens data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_download_tokens( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_download_tokens', $mode, 'توکن دانلود' );
	}

	/**
	 * Import security logs
	 *
	 * @param array  $data Security logs data
	 * @param string $mode Import mode
	 * @return array Result
	 */
	private function import_security_logs( $data, $mode ) {
		return $this->import_simple_table( $data, 'tabesh_security_logs', $mode, 'لاگ امنیتی' );
	}

	// ==================== HELPER METHODS ====================

	/**
	 * Import data into a simple table
	 *
	 * @param array  $data Table data
	 * @param string $table_name Table name (without prefix)
	 * @param string $mode Import mode
	 * @param string $label Label for messages
	 * @return array Result
	 */
	private function import_simple_table( $data, $table_name, $mode, $label ) {
		global $wpdb;
		$table = $wpdb->prefix . $table_name;

		if ( $mode === 'replace' ) {
			$wpdb->query( "TRUNCATE TABLE $table" );
		}

		$imported = 0;
		foreach ( $data as $row ) {
			unset( $row['id'] );
			$wpdb->insert( $table, $row );
			++$imported;
		}

		return array(
			'success' => true,
			'message' => sprintf( __( '%1$d %2$s وارد شد', 'tabesh' ), $imported, $label ),
		);
	}

	/**
	 * Get count of customers with orders
	 *
	 * @return int
	 */
	private function get_customers_count() {
		global $wpdb;
		$order_table = $wpdb->prefix . 'tabesh_orders';

		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM $order_table" );
	}
}
