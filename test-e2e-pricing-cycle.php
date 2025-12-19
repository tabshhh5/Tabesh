<?php
/**
 * End-to-End Test for Complete Pricing Cycle
 *
 * Tests the complete flow from saving pricing to order form availability
 *
 * @package Tabesh
 */

// Load WordPress.
require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

// Security check - only for admins.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied. Admins only.' );
}

// Load plugin classes.
require_once TABESH_PLUGIN_DIR . 'includes/handlers/class-tabesh-pricing-engine.php';
require_once TABESH_PLUGIN_DIR . 'includes/handlers/class-tabesh-constraint-manager.php';

/**
 * Run end-to-end test
 */
function run_e2e_test() {
	global $wpdb;
	$table_settings = $wpdb->prefix . 'tabesh_settings';
	$pricing_engine = new Tabesh_Pricing_Engine();
	$constraint_manager = new Tabesh_Constraint_Manager();

	echo '<div style="font-family: monospace; padding: 20px; background: #f5f5f5;">';
	echo '<h1>🔄 End-to-End Pricing Cycle Test</h1>';
	echo '<p>Testing complete flow: Product Params → Pricing Matrix → Constraint Manager → Order Form</p>';
	echo '<hr>';

	// Step 1: Setup product parameters with descriptions
	echo '<h2>Step 1: Setup Product Parameters</h2>';
	$test_sizes = array(
		'TestA5 (148×210)',
		'TestRoghei (14×20)',
		'TestClean',
	);

	$wpdb->replace(
		$table_settings,
		array(
			'setting_key'   => 'book_sizes',
			'setting_value' => wp_json_encode( $test_sizes ),
		),
		array( '%s', '%s' )
	);

	echo '<div style="background: #e3f2fd; padding: 10px; margin: 10px 0;">';
	echo 'Product parameters set: ' . esc_html( implode( ', ', $test_sizes ) );
	echo '</div>';

	// Step 2: Save pricing matrices
	echo '<h2>Step 2: Save Pricing Matrices</h2>';

	$base_matrix = array(
		'page_costs'    => array(
			'تحریر' => array(
				'70' => array(
					'bw'    => 350,
					'color' => 950,
				),
			),
		),
		'binding_costs' => array(
			'شومیز' => array(
				'200' => 5000,
			),
		),
	);

	$save_results = array();
	foreach ( $test_sizes as $size ) {
		$matrix = $base_matrix;
		$matrix['book_size'] = $size;
		$result = $pricing_engine->save_pricing_matrix( $size, $matrix );
		$save_results[ $size ] = $result;

		echo '<div style="background: ' . ( $result ? '#d4edda' : '#f8d7da' ) . '; padding: 5px; margin: 5px 0;">';
		echo ( $result ? '✓' : '✗' ) . ' Saved matrix for "' . esc_html( $size ) . '"';
		echo '</div>';
	}

	// Step 3: Verify retrieval
	echo '<h2>Step 3: Verify Matrix Retrieval</h2>';

	Tabesh_Pricing_Engine::clear_cache();
	$retrieve_results = array();

	// Try retrieving with original names (with descriptions)
	echo '<h3>3a. Retrieve with Original Names (with descriptions)</h3>';
	foreach ( $test_sizes as $size ) {
		$matrix = $pricing_engine->get_pricing_matrix( $size );
		$retrieved = $matrix && isset( $matrix['page_costs'] );
		$retrieve_results[ $size ] = $retrieved;

		echo '<div style="background: ' . ( $retrieved ? '#d4edda' : '#f8d7da' ) . '; padding: 5px; margin: 5px 0;">';
		echo ( $retrieved ? '✓' : '✗' ) . ' Retrieved matrix for "' . esc_html( $size ) . '"';
		echo '</div>';
	}

	// Try retrieving with normalized names (without descriptions)
	echo '<h3>3b. Retrieve with Normalized Names (without descriptions)</h3>';
	$normalized_test_sizes = array(
		'TestA5',
		'TestRoghei',
		'TestClean',
	);

	$cross_retrieve_results = array();
	foreach ( $normalized_test_sizes as $idx => $size ) {
		$matrix = $pricing_engine->get_pricing_matrix( $size );
		$retrieved = $matrix && isset( $matrix['page_costs'] );
		$cross_retrieve_results[ $size ] = $retrieved;

		echo '<div style="background: ' . ( $retrieved ? '#d4edda' : '#f8d7da' ) . '; padding: 5px; margin: 5px 0;">';
		echo ( $retrieved ? '✓' : '✗' ) . ' Cross-retrieved matrix for "' . esc_html( $size ) . '"';
		echo ' (from "' . esc_html( $test_sizes[ $idx ] ) . '")';
		echo '</div>';
	}

	// Step 4: Check configured sizes
	echo '<h2>Step 4: Check Configured Sizes</h2>';

	Tabesh_Pricing_Engine::clear_cache();
	$configured_sizes = $pricing_engine->get_configured_book_sizes();

	echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0;">';
	echo 'Configured sizes (should be normalized): ' . esc_html( implode( ', ', $configured_sizes ) );
	echo '</div>';

	// Step 5: Check available sizes (enabled for order form)
	echo '<h2>Step 5: Check Available Sizes (Order Form)</h2>';

	$available_sizes = $constraint_manager->get_available_book_sizes();

	echo '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
	echo '<tr style="background: #ddd;"><th style="border: 1px solid #999; padding: 5px;">Size</th><th style="border: 1px solid #999; padding: 5px;">Has Pricing</th><th style="border: 1px solid #999; padding: 5px;">Enabled</th><th style="border: 1px solid #999; padding: 5px;">Papers</th><th style="border: 1px solid #999; padding: 5px;">Bindings</th></tr>';

	$enabled_count = 0;
	foreach ( $available_sizes as $size_info ) {
		$is_enabled = $size_info['enabled'];
		if ( $is_enabled ) {
			++$enabled_count;
		}

		echo '<tr style="background: ' . ( $is_enabled ? '#d4edda' : '#f8d7da' ) . ';">';
		echo '<td style="border: 1px solid #999; padding: 5px;">' . esc_html( $size_info['size'] ) . '</td>';
		echo '<td style="border: 1px solid #999; padding: 5px; text-align: center;">' . ( $size_info['has_pricing'] ? '✓' : '✗' ) . '</td>';
		echo '<td style="border: 1px solid #999; padding: 5px; text-align: center;">' . ( $is_enabled ? '✓' : '✗' ) . '</td>';
		echo '<td style="border: 1px solid #999; padding: 5px; text-align: center;">' . esc_html( $size_info['paper_count'] ) . '</td>';
		echo '<td style="border: 1px solid #999; padding: 5px; text-align: center;">' . esc_html( $size_info['binding_count'] ) . '</td>';
		echo '</tr>';
	}

	echo '</table>';

	// Summary
	echo '<hr>';
	echo '<h2>📊 End-to-End Test Summary</h2>';

	$all_saved = count( array_filter( $save_results ) ) === count( $test_sizes );
	$all_retrieved = count( array_filter( $retrieve_results ) ) === count( $test_sizes );
	$all_cross_retrieved = count( array_filter( $cross_retrieve_results ) ) === count( $normalized_test_sizes );
	$all_enabled = $enabled_count === count( $test_sizes );

	$all_passed = $all_saved && $all_retrieved && $all_cross_retrieved && $all_enabled;

	echo '<div style="font-size: 18px; padding: 15px; background: ' . ( $all_passed ? '#d4edda' : '#f8d7da' ) . '; border: 2px solid ' . ( $all_passed ? '#28a745' : '#dc3545' ) . ';">';
	echo '<div style="margin: 5px 0;">' . ( $all_saved ? '✅' : '❌' ) . ' <strong>Save:</strong> ' . count( array_filter( $save_results ) ) . '/' . count( $test_sizes ) . ' matrices saved successfully</div>';
	echo '<div style="margin: 5px 0;">' . ( $all_retrieved ? '✅' : '❌' ) . ' <strong>Retrieve:</strong> ' . count( array_filter( $retrieve_results ) ) . '/' . count( $test_sizes ) . ' matrices retrieved with original names</div>';
	echo '<div style="margin: 5px 0;">' . ( $all_cross_retrieved ? '✅' : '❌' ) . ' <strong>Cross-Retrieve:</strong> ' . count( array_filter( $cross_retrieve_results ) ) . '/' . count( $normalized_test_sizes ) . ' matrices retrieved with normalized names</div>';
	echo '<div style="margin: 5px 0;">' . ( $all_enabled ? '✅' : '❌' ) . ' <strong>Enabled:</strong> ' . $enabled_count . '/' . count( $test_sizes ) . ' sizes enabled for order form</div>';
	echo '<hr>';
	if ( $all_passed ) {
		echo '<div style="font-size: 24px; color: #28a745; font-weight: bold;">✅ ALL TESTS PASSED!</div>';
		echo '<p>The complete pricing cycle is working correctly. Matrices can be saved and retrieved regardless of whether book_sizes have descriptions.</p>';
	} else {
		echo '<div style="font-size: 24px; color: #dc3545; font-weight: bold;">❌ SOME TESTS FAILED</div>';
		echo '<p>Review the details above to identify issues.</p>';
	}
	echo '</div>';

	// Cleanup
	echo '<h2>Cleanup</h2>';
	foreach ( $test_sizes as $size ) {
		$normalized = $pricing_engine->normalize_book_size_key( $size );
		$safe_key = base64_encode( $normalized );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_settings} WHERE setting_key = %s",
				'pricing_matrix_' . $safe_key
			)
		);
	}

	// Restore original book_sizes or clear
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table_settings} WHERE setting_key = %s",
			'book_sizes'
		)
	);

	Tabesh_Pricing_Engine::clear_cache();

	echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0;">';
	echo 'Test data cleaned up. Product parameters and test matrices removed.';
	echo '</div>';

	echo '</div>';
}

// Run test
run_e2e_test();
