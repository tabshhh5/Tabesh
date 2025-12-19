<?php
/**
 * Migration Script: Fix Default Book Sizes Issue
 *
 * This migration handles the root cause of the broken pricing cycle:
 * - Systems that were using default book sizes implicitly
 * - Need to migrate to explicit configuration in product parameters
 * - Clean up orphaned pricing matrices
 *
 * Run this ONCE after deploying the fix.
 *
 * @package Tabesh
 */

// Load WordPress
require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

// Security check - only for admins
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied. Admins only.' );
}

global $wpdb;
$table_settings = $wpdb->prefix . 'tabesh_settings';

// Step 1: Check current state
echo '<h1>Migration: Fix Default Book Sizes Issue</h1>';
echo '<div style="font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f0f0f0;">';

// Check if book_sizes is configured
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$book_sizes_setting = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
		'book_sizes'
	)
);

$current_book_sizes = array();
if ( $book_sizes_setting ) {
	$decoded = json_decode( $book_sizes_setting, true );
	if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
		$current_book_sizes = $decoded;
	}
}

echo '<h2>Step 1: Current State Analysis</h2>';
echo '<div style="background: white; padding: 15px; margin: 10px 0; border-radius: 5px;">';

if ( empty( $current_book_sizes ) ) {
	echo '<p style="color: orange;"><strong>⚠️ Book sizes NOT configured in product parameters</strong></p>';
	echo '<p>This installation is likely using implicit defaults, which causes the pricing cycle bug.</p>';
} else {
	echo '<p style="color: green;"><strong>✓ Book sizes ARE configured in product parameters</strong></p>';
	echo '<p>Found ' . count( $current_book_sizes ) . ' configured sizes: ' . implode( ', ', $current_book_sizes ) . '</p>';
}

echo '</div>';

// Step 2: Get all pricing matrices
echo '<h2>Step 2: Existing Pricing Matrices</h2>';
echo '<div style="background: white; padding: 15px; margin: 10px 0; border-radius: 5px;">';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$all_matrices = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT setting_key FROM {$table_settings} WHERE setting_key LIKE %s",
		'pricing_matrix_%'
	),
	ARRAY_A
);

if ( empty( $all_matrices ) ) {
	echo '<p>No pricing matrices found in database.</p>';
	$matrix_book_sizes = array();
} else {
	echo '<p>Found ' . count( $all_matrices ) . ' pricing matrices</p>';
	
	// Decode book sizes from matrix keys
	$matrix_book_sizes = array();
	foreach ( $all_matrices as $row ) {
		$setting_key = $row['setting_key'];
		$safe_key    = str_replace( 'pricing_matrix_', '', $setting_key );
		
		// Try base64 decode
		$decoded = base64_decode( $safe_key, true );
		if ( false !== $decoded && ! empty( $decoded ) ) {
			$book_size = $decoded;
		} else {
			$book_size = $safe_key;
		}
		
		$matrix_book_sizes[] = $book_size;
	}
	
	echo '<p>Book sizes with pricing matrices: ' . implode( ', ', $matrix_book_sizes ) . '</p>';
}

echo '</div>';

// Step 3: Decide migration strategy
echo '<h2>Step 3: Migration Strategy</h2>';
echo '<div style="background: white; padding: 15px; margin: 10px 0; border-radius: 5px;">';

$default_sizes = array( 'A5', 'A4', 'B5', 'رقعی', 'وزیری', 'خشتی' );

if ( empty( $current_book_sizes ) && ! empty( $matrix_book_sizes ) ) {
	// Case 1: No explicit config but has matrices - migrate matrices to config
	echo '<p style="color: blue;"><strong>📋 Strategy: Migrate Existing Matrices to Product Parameters</strong></p>';
	echo '<p>Will set product parameters to match existing pricing matrices.</p>';
	
	$migrate_to = array_unique( $matrix_book_sizes );
	echo '<p>Book sizes to configure: ' . implode( ', ', $migrate_to ) . '</p>';
	
	// Perform migration
	if ( isset( $_POST['confirm_migration'] ) && wp_verify_nonce( $_POST['migration_nonce'], 'tabesh_migration' ) ) {
		$success = $wpdb->replace(
			$table_settings,
			array(
				'setting_key'   => 'book_sizes',
				'setting_value' => wp_json_encode( $migrate_to ),
				'setting_type'  => 'json',
			),
			array( '%s', '%s', '%s' )
		);
		
		if ( false !== $success ) {
			echo '<p style="color: green;"><strong>✓ Migration successful!</strong></p>';
			echo '<p>Product parameters now configured with: ' . implode( ', ', $migrate_to ) . '</p>';
		} else {
			echo '<p style="color: red;"><strong>✗ Migration failed!</strong></p>';
		}
	} else {
		// Show confirmation form
		echo '<form method="post" style="margin-top: 20px;">';
		wp_nonce_field( 'tabesh_migration', 'migration_nonce' );
		echo '<input type="hidden" name="confirm_migration" value="1">';
		echo '<button type="submit" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">';
		echo 'Execute Migration';
		echo '</button>';
		echo '</form>';
	}
	
} elseif ( empty( $current_book_sizes ) && empty( $matrix_book_sizes ) ) {
	// Case 2: Fresh installation - configure defaults
	echo '<p style="color: blue;"><strong>📋 Strategy: Fresh Installation Setup</strong></p>';
	echo '<p>No configuration or pricing matrices exist. Will configure default book sizes.</p>';
	
	// Perform setup
	if ( isset( $_POST['confirm_migration'] ) && wp_verify_nonce( $_POST['migration_nonce'], 'tabesh_migration' ) ) {
		$success = $wpdb->replace(
			$table_settings,
			array(
				'setting_key'   => 'book_sizes',
				'setting_value' => wp_json_encode( $default_sizes ),
				'setting_type'  => 'json',
			),
			array( '%s', '%s', '%s' )
		);
		
		if ( false !== $success ) {
			echo '<p style="color: green;"><strong>✓ Setup successful!</strong></p>';
			echo '<p>Product parameters configured with default sizes: ' . implode( ', ', $default_sizes ) . '</p>';
			echo '<p><strong>Next steps:</strong></p>';
			echo '<ol>';
			echo '<li>Go to <a href="' . admin_url( 'admin.php?page=tabesh-product-pricing' ) . '">Product Pricing Management</a></li>';
			echo '<li>Enable Pricing Engine V2</li>';
			echo '<li>Configure pricing matrices for each book size</li>';
			echo '</ol>';
		} else {
			echo '<p style="color: red;"><strong>✗ Setup failed!</strong></p>';
		}
	} else {
		// Show confirmation form
		echo '<p>Default sizes: ' . implode( ', ', $default_sizes ) . '</p>';
		echo '<form method="post" style="margin-top: 20px;">';
		wp_nonce_field( 'tabesh_migration', 'migration_nonce' );
		echo '<input type="hidden" name="confirm_migration" value="1">';
		echo '<button type="submit" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">';
		echo 'Execute Setup';
		echo '</button>';
		echo '</form>';
	}
	
} else {
	// Case 3: Already configured - check for orphans
	echo '<p style="color: green;"><strong>✓ Product parameters already configured</strong></p>';
	
	// Check for orphaned matrices
	$orphaned = array_diff( $matrix_book_sizes, $current_book_sizes );
	$missing  = array_diff( $current_book_sizes, $matrix_book_sizes );
	
	if ( ! empty( $orphaned ) ) {
		echo '<p style="color: orange;"><strong>⚠️ Found orphaned pricing matrices:</strong></p>';
		echo '<p>' . implode( ', ', $orphaned ) . '</p>';
		echo '<p>These matrices exist but their book sizes are not in product parameters.</p>';
		echo '<p>They will be automatically cleaned up on next pricing form load.</p>';
	}
	
	if ( ! empty( $missing ) ) {
		echo '<p style="color: orange;"><strong>⚠️ Book sizes without pricing matrices:</strong></p>';
		echo '<p>' . implode( ', ', $missing ) . '</p>';
		echo '<p>Configure pricing for these sizes in Product Pricing Management.</p>';
	}
	
	if ( empty( $orphaned ) && empty( $missing ) ) {
		echo '<p style="color: green;"><strong>✓ Everything is properly configured!</strong></p>';
		echo '<p>No migration needed.</p>';
	}
}

echo '</div>';

// Step 4: Recommendations
echo '<h2>Step 4: Post-Migration Checklist</h2>';
echo '<div style="background: white; padding: 15px; margin: 10px 0; border-radius: 5px;">';
echo '<ol style="line-height: 2;">';
echo '<li>Verify book sizes in <a href="' . admin_url( 'admin.php?page=tabesh-settings' ) . '">Product Settings</a></li>';
echo '<li>Enable Pricing Engine V2 in <a href="' . admin_url( 'admin.php?page=tabesh-product-pricing' ) . '">Product Pricing</a></li>';
echo '<li>Configure pricing matrix for each book size</li>';
echo '<li>Test the order form to ensure book sizes appear correctly</li>';
echo '<li>Run <code>diagnostic-pricing-cycle.php</code> to verify everything works</li>';
echo '</ol>';
echo '</div>';

echo '</div>';
