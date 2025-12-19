<?php
/**
 * Test Script for Pricing Key Fix
 *
 * This script tests the complete pricing key normalization and retrieval cycle
 * to ensure matrices can be saved and retrieved correctly regardless of
 * whether book_sizes have descriptions in parentheses.
 *
 * Usage: Place in plugin root and access via browser (admin only)
 * Example: /wp-content/plugins/Tabesh/test-pricing-key-fix.php
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

/**
 * Run comprehensive test suite
 */
function run_pricing_key_tests() {
	global $wpdb;
	$table_settings = $wpdb->prefix . 'tabesh_settings';
	$pricing_engine = new Tabesh_Pricing_Engine();

	$results = array(
		'tests_run'    => 0,
		'tests_passed' => 0,
		'tests_failed' => 0,
		'details'      => array(),
	);

	echo '<div style="font-family: monospace; padding: 20px; background: #f5f5f5;">';
	echo '<h1>🔍 Pricing Key Fix - Test Suite</h1>';
	echo '<p>Testing normalization and key matching across all save/retrieve paths</p>';
	echo '<hr>';

	// Test 1: Normalize function works correctly
	++$results['tests_run'];
	echo '<h2>Test 1: normalize_book_size_key() Function</h2>';

	$test_cases = array(
		array( 'input' => 'رقعی (14×20)', 'expected' => 'رقعی' ),
		array( 'input' => 'رقعی', 'expected' => 'رقعی' ),
		array( 'input' => 'وزیری (توضیحات)', 'expected' => 'وزیری' ),
		array( 'input' => 'A5 (148×210)', 'expected' => 'A5' ),
		array( 'input' => 'A5', 'expected' => 'A5' ),
	);

	$all_passed = true;
	foreach ( $test_cases as $case ) {
		$result = $pricing_engine->normalize_book_size_key( $case['input'] );
		$passed = ( $result === $case['expected'] );
		$all_passed = $all_passed && $passed;

		echo '<div style="margin: 5px 0; padding: 5px; background: ' . ( $passed ? '#d4edda' : '#f8d7da' ) . ';">';
		echo $passed ? '✓' : '✗';
		echo ' Input: "' . esc_html( $case['input'] ) . '" → ';
		echo 'Result: "' . esc_html( $result ) . '" ';
		echo '(Expected: "' . esc_html( $case['expected'] ) . '")';
		echo '</div>';
	}

	if ( $all_passed ) {
		++$results['tests_passed'];
		$results['details'][] = 'Test 1: PASSED - normalize_book_size_key() works correctly';
	} else {
		++$results['tests_failed'];
		$results['details'][] = 'Test 1: FAILED - normalize_book_size_key() has issues';
	}

	// Test 2: Save and retrieve with clean name
	++$results['tests_run'];
	echo '<h2>Test 2: Save & Retrieve with Clean Name</h2>';

	$test_size = 'TestSize_' . wp_rand( 1000, 9999 );
	$test_matrix = array(
		'book_size'     => $test_size,
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

	$save_result = $pricing_engine->save_pricing_matrix( $test_size, $test_matrix );
	$retrieve_result = $pricing_engine->get_pricing_matrix( $test_size );

	$test2_passed = $save_result && $retrieve_result && isset( $retrieve_result['page_costs'] );

	echo '<div style="margin: 5px 0; padding: 5px; background: ' . ( $test2_passed ? '#d4edda' : '#f8d7da' ) . ';">';
	echo $test2_passed ? '✓' : '✗';
	echo ' Save: ' . ( $save_result ? 'SUCCESS' : 'FAILED' );
	echo ', Retrieve: ' . ( $retrieve_result ? 'SUCCESS' : 'FAILED' );
	echo '</div>';

	if ( $test2_passed ) {
		++$results['tests_passed'];
		$results['details'][] = 'Test 2: PASSED - Save & retrieve with clean name works';
	} else {
		++$results['tests_failed'];
		$results['details'][] = 'Test 2: FAILED - Save & retrieve with clean name failed';
	}

	// Test 3: Save with description, retrieve with clean name
	++$results['tests_run'];
	echo '<h2>Test 3: Save with Description, Retrieve with Clean Name</h2>';

	$test_size_desc = 'TestSize2_' . wp_rand( 1000, 9999 );
	$test_size_with_desc = $test_size_desc . ' (test description)';

	$save_result2 = $pricing_engine->save_pricing_matrix( $test_size_with_desc, $test_matrix );
	$retrieve_result2 = $pricing_engine->get_pricing_matrix( $test_size_desc ); // Retrieve with clean name

	$test3_passed = $save_result2 && $retrieve_result2 && isset( $retrieve_result2['page_costs'] );

	echo '<div style="margin: 5px 0; padding: 5px; background: ' . ( $test3_passed ? '#d4edda' : '#f8d7da' ) . ';">';
	echo $test3_passed ? '✓' : '✗';
	echo ' Save with "' . esc_html( $test_size_with_desc ) . '": ' . ( $save_result2 ? 'SUCCESS' : 'FAILED' );
	echo '<br>Retrieve with "' . esc_html( $test_size_desc ) . '": ' . ( $retrieve_result2 ? 'SUCCESS' : 'FAILED' );
	echo '</div>';

	if ( $test3_passed ) {
		++$results['tests_passed'];
		$results['details'][] = 'Test 3: PASSED - Cross-retrieve with normalization works';
	} else {
		++$results['tests_failed'];
		$results['details'][] = 'Test 3: FAILED - Cross-retrieve failed';
	}

	// Test 4: get_configured_book_sizes() returns normalized keys
	++$results['tests_run'];
	echo '<h2>Test 4: get_configured_book_sizes() Returns Normalized Keys</h2>';

	Tabesh_Pricing_Engine::clear_cache(); // Clear cache to force fresh query
	$configured_sizes = $pricing_engine->get_configured_book_sizes();

	// Check if test sizes are in the list (should be normalized)
	$has_test_size1 = in_array( $test_size, $configured_sizes, true );
	$has_test_size2 = in_array( $test_size_desc, $configured_sizes, true ); // Should find normalized version

	$test4_passed = $has_test_size1 && $has_test_size2;

	echo '<div style="margin: 5px 0; padding: 5px; background: ' . ( $test4_passed ? '#d4edda' : '#f8d7da' ) . ';">';
	echo $test4_passed ? '✓' : '✗';
	echo ' Found "' . esc_html( $test_size ) . '": ' . ( $has_test_size1 ? 'YES' : 'NO' );
	echo '<br>Found "' . esc_html( $test_size_desc ) . '" (normalized): ' . ( $has_test_size2 ? 'YES' : 'NO' );
	echo '<br>Configured sizes: ' . esc_html( implode( ', ', $configured_sizes ) );
	echo '</div>';

	if ( $test4_passed ) {
		++$results['tests_passed'];
		$results['details'][] = 'Test 4: PASSED - get_configured_book_sizes() normalizes correctly';
	} else {
		++$results['tests_failed'];
		$results['details'][] = 'Test 4: FAILED - get_configured_book_sizes() normalization issue';
	}

	// Cleanup test data
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table_settings} WHERE setting_key LIKE %s",
			'pricing_matrix_%' . $test_size . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table_settings} WHERE setting_key LIKE %s",
			'pricing_matrix_%' . $test_size_desc . '%'
		)
	);
	Tabesh_Pricing_Engine::clear_cache();

	// Summary
	echo '<hr>';
	echo '<h2>📊 Test Summary</h2>';
	echo '<div style="font-size: 18px; padding: 10px; background: ' . ( $results['tests_failed'] > 0 ? '#f8d7da' : '#d4edda' ) . ';">';
	echo '<strong>Tests Run:</strong> ' . esc_html( $results['tests_run'] ) . '<br>';
	echo '<strong>Passed:</strong> ' . esc_html( $results['tests_passed'] ) . '<br>';
	echo '<strong>Failed:</strong> ' . esc_html( $results['tests_failed'] ) . '<br>';
	echo '<br>';
	if ( $results['tests_failed'] === 0 ) {
		echo '✅ <strong>ALL TESTS PASSED!</strong> The pricing key fix is working correctly.';
	} else {
		echo '❌ <strong>SOME TESTS FAILED.</strong> Review the details above.';
	}
	echo '</div>';

	echo '<h3>Test Details:</h3>';
	echo '<ul>';
	foreach ( $results['details'] as $detail ) {
		echo '<li>' . esc_html( $detail ) . '</li>';
	}
	echo '</ul>';

	echo '</div>';

	return $results;
}

// Run tests
run_pricing_key_tests();
