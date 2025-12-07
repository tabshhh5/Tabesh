<?php
/**
 * Import/Export Management Class
 *
 * Handles data import and export functionality for Tabesh plugin
 * Supports exporting/importing: orders, settings, files, users, logs
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tabesh_Import_Export {

	/**
	 * Export directory path
	 *
	 * @var string
	 */
	private $export_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->export_dir = $upload_dir['basedir'] . '/tabesh-exports/';

		// Create export directory if it doesn't exist
		if ( ! file_exists( $this->export_dir ) ) {
			wp_mkdir_p( $this->export_dir );

			// Create .htaccess to protect export files
			$htaccess_content  = "# Tabesh Exports Protection\n";
			$htaccess_content .= "Order Deny,Allow\n";
			$htaccess_content .= "Deny from all\n";
			file_put_contents( $this->export_dir . '.htaccess', $htaccess_content );
		}
	}

	/**
	 * Export data
	 *
	 * @param array $options Export options
	 * @return array Result with success status and file path or error message
	 */
	public function export_data( $options = array() ) {
		try {
			$defaults = array(
				'include_orders'         => true,
				'include_settings'       => true,
				'include_files_metadata' => true,
				'include_physical_files' => false,
				'include_users'          => false,
				'include_logs'           => false,
				'date_from'              => null,
				'date_to'                => null,
				'user_id'                => null,
				'format'                 => 'json', // json or zip
			);

			$options = wp_parse_args( $options, $defaults );

			// Prepare export data
			$export_data = array(
				'version'     => TABESH_VERSION,
				'exported_at' => current_time( 'mysql' ),
				'exported_by' => get_current_user_id(),
				'options'     => $options,
			);

			// Export orders
			if ( $options['include_orders'] ) {
				$export_data['orders'] = $this->export_orders( $options );
			}

			// Export settings
			if ( $options['include_settings'] ) {
				$export_data['settings'] = $this->export_settings();
			}

			// Export files metadata
			if ( $options['include_files_metadata'] ) {
				$export_data['files_metadata'] = $this->export_files_metadata( $options );
			}

			// Export users
			if ( $options['include_users'] ) {
				$export_data['users'] = $this->export_users( $options );
			}

			// Export logs
			if ( $options['include_logs'] ) {
				$export_data['logs'] = $this->export_logs( $options );
			}

			// Generate filename
			$timestamp = current_time( 'Y-m-d_H-i-s' );
			$filename  = 'tabesh-export-' . $timestamp;

			// If including physical files, create ZIP
			if ( $options['include_physical_files'] && $options['format'] === 'zip' ) {
				return $this->create_export_zip( $export_data, $filename );
			} else {
				// Create JSON file
				return $this->create_export_json( $export_data, $filename );
			}
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh Export Error: ' . $e->getMessage() );
			}
			return array(
				'success' => false,
				'message' => __( 'خطا در برونریزی داده‌ها: ', 'tabesh' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Export orders
	 *
	 * @param array $options Export options
	 * @return array Orders data
	 */
	private function export_orders( $options ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';

		$sql    = "SELECT * FROM $table WHERE 1=1";
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

		$sql .= ' ORDER BY id ASC';

		if ( ! empty( $params ) ) {
			$orders = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$orders = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $orders;
	}

	/**
	 * Export settings
	 *
	 * @return array Settings data
	 */
	private function export_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		return $wpdb->get_results(
			"SELECT * FROM $table ORDER BY id ASC",
			ARRAY_A
		);
	}

	/**
	 * Export files metadata
	 *
	 * @param array $options Export options
	 * @return array Files metadata
	 */
	private function export_files_metadata( $options ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_files';

		$sql    = "SELECT * FROM $table WHERE 1=1";
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

		$sql .= ' ORDER BY id ASC';

		if ( ! empty( $params ) ) {
			$files = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$files = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $files;
	}

	/**
	 * Export users
	 *
	 * @param array $options Export options
	 * @return array Users data
	 */
	private function export_users( $options ) {
		global $wpdb;

		$sql    = "SELECT ID, user_login, user_email, display_name, user_registered FROM {$wpdb->users}";
		$params = array();

		// Apply user filter
		if ( ! empty( $options['user_id'] ) ) {
			$sql     .= ' WHERE ID = %d';
			$params[] = $options['user_id'];
		}

		$sql .= ' ORDER BY ID ASC';

		if ( ! empty( $params ) ) {
			$users = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$users = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $users;
	}

	/**
	 * Export logs
	 *
	 * @param array $options Export options
	 * @return array Logs data
	 */
	private function export_logs( $options ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_logs';

		$sql    = "SELECT * FROM $table WHERE 1=1";
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

		$sql .= ' ORDER BY id ASC';

		if ( ! empty( $params ) ) {
			$logs = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$logs = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $logs;
	}

	/**
	 * Create export JSON file
	 *
	 * @param array  $data Export data
	 * @param string $filename Filename without extension
	 * @return array Result with success status and file path
	 */
	private function create_export_json( $data, $filename ) {
		$filepath = $this->export_dir . $filename . '.json';

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( $json === false ) {
			throw new Exception( __( 'خطا در ایجاد فایل JSON', 'tabesh' ) );
		}

		if ( file_put_contents( $filepath, $json ) === false ) {
			throw new Exception( __( 'خطا در نوشتن فایل', 'tabesh' ) );
		}

		return array(
			'success'  => true,
			'message'  => __( 'برونریزی با موفقیت انجام شد', 'tabesh' ),
			'filepath' => $filepath,
			'filename' => $filename . '.json',
			'filesize' => filesize( $filepath ),
		);
	}

	/**
	 * Create export ZIP file
	 *
	 * @param array  $data Export data
	 * @param string $filename Filename without extension
	 * @return array Result with success status and file path
	 */
	private function create_export_zip( $data, $filename ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( __( 'ZipArchive موجود نیست', 'tabesh' ) );
		}

		$zip_path = $this->export_dir . $filename . '.zip';
		$zip      = new ZipArchive();

		if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			throw new Exception( __( 'خطا در ایجاد فایل ZIP', 'tabesh' ) );
		}

		// Add JSON data file
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$zip->addFromString( 'data.json', $json );

		// Add physical files if requested
		if ( ! empty( $data['files_metadata'] ) ) {
			$upload_dir = wp_upload_dir();
			$files_dir  = $upload_dir['basedir'] . '/tabesh-files/';

			foreach ( $data['files_metadata'] as $file_meta ) {
				$file_path = $files_dir . $file_meta['stored_filename'];
				if ( file_exists( $file_path ) ) {
					$zip->addFile( $file_path, 'files/' . $file_meta['stored_filename'] );
				}
			}
		}

		$zip->close();

		return array(
			'success'  => true,
			'message'  => __( 'برونریزی با موفقیت انجام شد', 'tabesh' ),
			'filepath' => $zip_path,
			'filename' => $filename . '.zip',
			'filesize' => filesize( $zip_path ),
		);
	}

	/**
	 * Import data
	 *
	 * @param string $filepath Path to import file
	 * @param array  $options Import options
	 * @return array Result with success status and statistics
	 */
	public function import_data( $filepath, $options = array() ) {
		try {
			$defaults = array(
				'include_orders'         => true,
				'include_settings'       => true,
				'include_files_metadata' => true,
				'include_physical_files' => false,
				'include_users'          => false,
				'include_logs'           => false,
				'skip_existing'          => true,
				'update_existing'        => false,
			);

			$options = wp_parse_args( $options, $defaults );

			// Validate file
			if ( ! file_exists( $filepath ) ) {
				throw new Exception( __( 'فایل وجود ندارد', 'tabesh' ) );
			}

			// Extract data from file
			$data = $this->extract_import_data( $filepath );

			// Validate data structure
			if ( ! $this->validate_import_data( $data ) ) {
				throw new Exception( __( 'ساختار فایل نامعتبر است', 'tabesh' ) );
			}

			// Import statistics
			$stats = array(
				'orders_imported'   => 0,
				'settings_imported' => 0,
				'files_imported'    => 0,
				'users_imported'    => 0,
				'logs_imported'     => 0,
				'errors'            => array(),
			);

			// Import orders
			if ( $options['include_orders'] && ! empty( $data['orders'] ) ) {
				$stats['orders_imported'] = $this->import_orders( $data['orders'], $options );
			}

			// Import settings
			if ( $options['include_settings'] && ! empty( $data['settings'] ) ) {
				$stats['settings_imported'] = $this->import_settings( $data['settings'], $options );
			}

			// Import files metadata
			if ( $options['include_files_metadata'] && ! empty( $data['files_metadata'] ) ) {
				$stats['files_imported'] = $this->import_files_metadata( $data['files_metadata'], $options );
			}

			// Import users
			if ( $options['include_users'] && ! empty( $data['users'] ) ) {
				$stats['users_imported'] = $this->import_users( $data['users'], $options );
			}

			// Import logs
			if ( $options['include_logs'] && ! empty( $data['logs'] ) ) {
				$stats['logs_imported'] = $this->import_logs( $data['logs'], $options );
			}

			// Clear settings cache after import
			Tabesh::clear_settings_cache();

			return array(
				'success' => true,
				'message' => __( 'درونریزی با موفقیت انجام شد', 'tabesh' ),
				'stats'   => $stats,
			);

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh Import Error: ' . $e->getMessage() );
			}
			return array(
				'success' => false,
				'message' => __( 'خطا در درونریزی داده‌ها: ', 'tabesh' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Extract import data from file
	 *
	 * @param string $filepath Path to import file
	 * @return array Import data
	 */
	private function extract_import_data( $filepath ) {
		$extension = pathinfo( $filepath, PATHINFO_EXTENSION );

		if ( $extension === 'json' ) {
			$json = file_get_contents( $filepath );
			$data = json_decode( $json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( __( 'خطا در خواندن فایل JSON', 'tabesh' ) );
			}

			return $data;
		} elseif ( $extension === 'zip' ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				throw new Exception( __( 'ZipArchive موجود نیست', 'tabesh' ) );
			}

			$zip = new ZipArchive();
			if ( $zip->open( $filepath ) !== true ) {
				throw new Exception( __( 'خطا در باز کردن فایل ZIP', 'tabesh' ) );
			}

			// Extract data.json
			$json = $zip->getFromName( 'data.json' );
			if ( $json === false ) {
				throw new Exception( __( 'فایل data.json در ZIP یافت نشد', 'tabesh' ) );
			}

			$data = json_decode( $json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( __( 'خطا در خواندن فایل JSON از ZIP', 'tabesh' ) );
			}

			// Extract physical files if present
			$upload_dir = wp_upload_dir();
			$files_dir  = $upload_dir['basedir'] . '/tabesh-files/';

			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$filename = $zip->getNameIndex( $i );
				if ( strpos( $filename, 'files/' ) === 0 ) {
					$file_content = $zip->getFromIndex( $i );
					$target_path  = $files_dir . basename( $filename );
					file_put_contents( $target_path, $file_content );
				}
			}

			$zip->close();

			return $data;
		} else {
			throw new Exception( __( 'فرمت فایل پشتیبانی نمی‌شود', 'tabesh' ) );
		}
	}

	/**
	 * Validate import data structure
	 *
	 * @param array $data Import data
	 * @return bool True if valid, false otherwise
	 */
	private function validate_import_data( $data ) {
		// Check required fields
		if ( ! isset( $data['version'] ) || ! isset( $data['exported_at'] ) ) {
			return false;
		}

		// Check version compatibility (basic check)
		if ( version_compare( $data['version'], '1.0.0', '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Import orders
	 *
	 * @param array $orders Orders data
	 * @param array $options Import options
	 * @return int Number of imported orders
	 */
	private function import_orders( $orders, $options ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'tabesh_orders';
		$imported = 0;

		foreach ( $orders as $order ) {
			// Check if order exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE id = %d",
					$order['id']
				)
			);

			if ( $exists ) {
				if ( $options['skip_existing'] ) {
					continue;
				} elseif ( $options['update_existing'] ) {
					// Update existing order
					$order_id = $order['id'];
					unset( $order['id'] );
					$wpdb->update( $table, $order, array( 'id' => $order_id ) );
					++$imported;
				}
			} else {
				// Insert new order
				$wpdb->insert( $table, $order );
				++$imported;
			}
		}

		return $imported;
	}

	/**
	 * Import settings
	 *
	 * @param array $settings Settings data
	 * @param array $options Import options
	 * @return int Number of imported settings
	 */
	private function import_settings( $settings, $options ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'tabesh_settings';
		$imported = 0;

		foreach ( $settings as $setting ) {
			// Check if setting exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE setting_key = %s",
					$setting['setting_key']
				)
			);

			if ( $exists ) {
				if ( $options['skip_existing'] ) {
					continue;
				} elseif ( $options['update_existing'] ) {
					// Update existing setting
					$wpdb->update(
						$table,
						array( 'setting_value' => $setting['setting_value'] ),
						array( 'setting_key' => $setting['setting_key'] )
					);
					++$imported;
				}
			} else {
				// Insert new setting
				$wpdb->insert( $table, $setting );
				++$imported;
			}
		}

		return $imported;
	}

	/**
	 * Import files metadata
	 *
	 * @param array $files Files metadata
	 * @param array $options Import options
	 * @return int Number of imported files
	 */
	private function import_files_metadata( $files, $options ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'tabesh_files';
		$imported = 0;

		foreach ( $files as $file ) {
			// Check if file exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE id = %d",
					$file['id']
				)
			);

			if ( $exists ) {
				if ( $options['skip_existing'] ) {
					continue;
				} elseif ( $options['update_existing'] ) {
					// Update existing file
					$file_id = $file['id'];
					unset( $file['id'] );
					$wpdb->update( $table, $file, array( 'id' => $file_id ) );
					++$imported;
				}
			} else {
				// Insert new file
				$wpdb->insert( $table, $file );
				++$imported;
			}
		}

		return $imported;
	}

	/**
	 * Import users
	 *
	 * @param array $users Users data
	 * @param array $options Import options
	 * @return int Number of imported users
	 */
	private function import_users( $users, $options ) {
		$imported = 0;

		foreach ( $users as $user_data ) {
			// Check if user exists
			$user = get_user_by( 'login', $user_data['user_login'] );

			if ( $user ) {
				if ( $options['skip_existing'] ) {
					continue;
				} elseif ( $options['update_existing'] ) {
					// Update existing user (limited fields)
					wp_update_user(
						array(
							'ID'           => $user->ID,
							'display_name' => $user_data['display_name'],
							'user_email'   => $user_data['user_email'],
						)
					);
					++$imported;
				}
			} else {
				// Create new user with random password
				$user_id = wp_create_user(
					$user_data['user_login'],
					wp_generate_password(),
					$user_data['user_email']
				);

				if ( ! is_wp_error( $user_id ) ) {
					wp_update_user(
						array(
							'ID'           => $user_id,
							'display_name' => $user_data['display_name'],
						)
					);
					++$imported;
				}
			}
		}

		return $imported;
	}

	/**
	 * Import logs
	 *
	 * @param array $logs Logs data
	 * @param array $options Import options
	 * @return int Number of imported logs
	 */
	private function import_logs( $logs, $options ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'tabesh_logs';
		$imported = 0;

		foreach ( $logs as $log ) {
			// Check if log exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE id = %d",
					$log['id']
				)
			);

			if ( $exists ) {
				if ( $options['skip_existing'] ) {
					continue;
				}
			} else {
				// Insert new log
				$wpdb->insert( $table, $log );
				++$imported;
			}
		}

		return $imported;
	}

	/**
	 * Get export directory path
	 *
	 * @return string Export directory path
	 */
	public function get_export_dir() {
		return $this->export_dir;
	}

	/**
	 * Clean up old export files
	 *
	 * @param int $days Delete files older than this many days
	 * @return int Number of deleted files
	 */
	public function cleanup_old_exports( $days = 7 ) {
		$deleted   = 0;
		$files     = glob( $this->export_dir . 'tabesh-export-*' );
		$threshold = time() - ( $days * DAY_IN_SECONDS );

		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $threshold ) {
				if ( @unlink( $file ) ) {
					++$deleted;
				}
			}
		}

		return $deleted;
	}
}
