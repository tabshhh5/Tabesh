<?php
/**
 * Migration Script: Cleanup Corrupted Pricing Matrices
 * 
 * This script removes pricing matrices with invalid book size IDs that don't
 * match the product parameters defined in admin settings.
 * 
 * Issue: In some cases, random IDs (like 21145) were saved instead of actual
 * book size names (like "وزیری" or "A5"), causing data corruption.
 * 
 * This script:
 * 1. Gets valid book sizes from product parameters (source of truth)
 * 2. Finds all pricing_matrix_* entries in settings table
 * 3. Deletes entries where the book size is not in the valid list
 * 
 * Usage (WP-CLI):
 *   wp eval-file migration-cleanup-corrupted-pricing-matrices.php
 * 
 * Usage (Direct PHP with confirmation):
 *   php -d display_errors=1 migration-cleanup-corrupted-pricing-matrices.php
 * 
 * @package Tabesh
 */

// Load WordPress if running directly
if ( ! defined( 'ABSPATH' ) ) {
	// Try to locate wp-load.php
	$wp_load_paths = array(
		dirname( __FILE__ ) . '/../../../wp-load.php',
		dirname( __FILE__ ) . '/../../../../wp-load.php',
		dirname( __FILE__ ) . '/../../../../../wp-load.php',
	);
	
	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			break;
		}
	}
	
	if ( ! defined( 'ABSPATH' ) ) {
		die( "Error: Could not locate WordPress. Please run via WP-CLI or ensure WordPress is properly loaded.\n" );
	}
}

// Prevent running in production without confirmation
if ( ! defined( 'WP_CLI' ) && php_sapi_name() !== 'cli' ) {
	$confirm_param = isset( $_GET['confirm'] ) ? sanitize_text_field( wp_unslash( $_GET['confirm'] ) ) : '';
	if ( 'yes' !== $confirm_param ) {
		die( "This is a data cleanup script. Please run via WP-CLI or add ?confirm=yes to the URL if you're absolutely sure.\n" );
	}
}

echo "=== Tabesh Pricing Matrix Cleanup Script ===\n";
echo "Removing corrupted pricing matrices with invalid book size IDs...\n\n";

global $wpdb;
$table = $wpdb->prefix . 'tabesh_settings';

// Check if table exists
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

if ( ! $table_exists ) {
	die( "Error: Table {$table} does not exist. Please ensure Tabesh plugin is installed.\n" );
}

// Step 1: Get valid book sizes from product parameters (source of truth)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$result = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$table} WHERE setting_key = %s",
		'book_sizes'
	)
);

$valid_book_sizes = array();
if ( $result ) {
	$decoded = json_decode( $result, true );
	if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
		$valid_book_sizes = $decoded;
	}
}

// Fallback to defaults if no book sizes configured
if ( empty( $valid_book_sizes ) ) {
	$valid_book_sizes = array( 'A5', 'A4', 'B5', 'رقعی', 'وزیری', 'خشتی' );
	echo "Note: No book sizes configured in settings, using defaults.\n";
}

echo "Valid book sizes from product parameters:\n";
foreach ( $valid_book_sizes as $size ) {
	echo "  - {$size}\n";
}
echo "\n";

// Step 2: Find all pricing_matrix_* entries
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$results = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT id, setting_key FROM {$table} WHERE setting_key LIKE %s",
		'pricing_matrix_%'
	),
	ARRAY_A
);

if ( empty( $results ) ) {
	echo "No pricing matrices found. Nothing to clean up.\n";
	exit( 0 );
}

echo "Found " . count( $results ) . " pricing matrix entries.\n\n";

// Step 3: Check each matrix and delete invalid ones
$deleted_count = 0;
$kept_count    = 0;

foreach ( $results as $row ) {
	$setting_key = $row['setting_key'];
	$book_size   = str_replace( 'pricing_matrix_', '', $setting_key );
	
	// Check if this book size is valid
	if ( in_array( $book_size, $valid_book_sizes, true ) ) {
		echo "✓ KEEP: {$setting_key} (valid book size: {$book_size})\n";
		$kept_count++;
	} else {
		echo "✗ DELETE: {$setting_key} (invalid book size: {$book_size})\n";
		
		// Delete this corrupted entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$delete_result = $wpdb->delete(
			$table,
			array( 'id' => $row['id'] ),
			array( '%d' )
		);
		
		if ( false !== $delete_result ) {
			$deleted_count++;
		} else {
			echo "  WARNING: Failed to delete {$setting_key}\n";
		}
	}
}

echo "\n=== Cleanup Summary ===\n";
echo "Total matrices processed: " . count( $results ) . "\n";
echo "Valid matrices kept: {$kept_count}\n";
echo "Corrupted matrices deleted: {$deleted_count}\n";

if ( $deleted_count > 0 ) {
	echo "\n✓ Cleanup completed successfully!\n";
	echo "The pricing engine cache will be cleared.\n";

	// Clear pricing engine cache if class exists
	if ( class_exists( 'Tabesh_Pricing_Engine' ) ) {
		try {
			Tabesh_Pricing_Engine::clear_cache();
			echo "Pricing engine cache cleared.\n";
		} catch ( Exception $e ) {
			echo "Warning: Could not clear pricing engine cache: " . $e->getMessage() . "\n";
		}
	} else {
		echo "Note: Tabesh_Pricing_Engine class not found. Cache not cleared.\n";
	}
} else {
	echo "\n✓ No corrupted matrices found. Database is clean!\n";
}

echo "\nDone.\n";
