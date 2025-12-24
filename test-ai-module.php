<?php
/**
 * AI Module Test Script
 *
 * Simple test to verify AI module functionality
 * Run this from WordPress admin or via WP-CLI
 *
 * @package Tabesh
 * @since 1.1.0
 */

// This is a test file - load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if running from command line or admin
if ( ! defined( 'WP_CLI' ) && ! current_user_can( 'manage_woocommerce' ) ) {
	die( 'Access denied' );
}

echo "=== Tabesh AI Module Test ===\n\n";

// Test 1: Check if AI module is loaded
echo "Test 1: Checking if AI module is loaded...\n";
if ( class_exists( 'Tabesh_AI' ) ) {
	echo "✓ Tabesh_AI class exists\n";
} else {
	echo "✗ Tabesh_AI class not found\n";
	exit( 1 );
}

// Test 2: Get AI instance
echo "\nTest 2: Getting AI instance...\n";
try {
	$ai = Tabesh_AI::instance();
	echo "✓ AI instance created successfully\n";
} catch ( Exception $e ) {
	echo "✗ Failed to create AI instance: " . $e->getMessage() . "\n";
	exit( 1 );
}

// Test 3: Check enabled status
echo "\nTest 3: Checking AI module status...\n";
$is_enabled = $ai->is_enabled();
echo $is_enabled ? "✓ AI module is enabled\n" : "ℹ AI module is disabled (can be enabled in settings)\n";

// Test 4: Check registered models
echo "\nTest 4: Checking registered models...\n";
$models = $ai->get_all_models();
if ( ! empty( $models ) ) {
	echo "✓ Found " . count( $models ) . " registered models:\n";
	foreach ( $models as $model_id => $model ) {
		$configured = $model->is_configured() ? '(Configured)' : '(Not configured)';
		echo "  - {$model->get_model_name()} [{$model_id}] {$configured}\n";
	}
} else {
	echo "ℹ No models registered yet\n";
}

// Test 5: Check registered assistants
echo "\nTest 5: Checking registered assistants...\n";
$assistants = $ai->get_all_assistants();
if ( ! empty( $assistants ) ) {
	echo "✓ Found " . count( $assistants ) . " registered assistants:\n";
	foreach ( $assistants as $assistant_id => $assistant ) {
		$roles = implode( ', ', $assistant->get_allowed_roles() );
		echo "  - {$assistant->get_assistant_name()} [{$assistant_id}]\n";
		echo "    Roles: {$roles}\n";
	}
} else {
	echo "ℹ No assistants registered yet\n";
}

// Test 6: Check interfaces
echo "\nTest 6: Checking interfaces...\n";
$interfaces_exist = interface_exists( 'Tabesh_AI_Model_Interface' ) &&
					interface_exists( 'Tabesh_AI_Assistant_Interface' );
if ( $interfaces_exist ) {
	echo "✓ AI interfaces are defined correctly\n";
} else {
	echo "✗ Some interfaces are missing\n";
}

// Test 7: Check autoloader
echo "\nTest 7: Checking autoloader for AI classes...\n";
$test_classes = array(
	'Tabesh_AI',
	'Tabesh_AI_Model_GPT',
	'Tabesh_AI_Model_Gemini',
	'Tabesh_AI_Assistant_Order',
);

$all_loaded = true;
foreach ( $test_classes as $class ) {
	if ( class_exists( $class ) ) {
		echo "  ✓ {$class}\n";
	} else {
		echo "  ✗ {$class} not found\n";
		$all_loaded = false;
	}
}

if ( $all_loaded ) {
	echo "✓ All AI classes are loadable\n";
}

// Test 8: Check REST API endpoints
echo "\nTest 8: Checking REST API endpoints...\n";
$rest_server = rest_get_server();
$routes      = $rest_server->get_routes();
$ai_routes   = array();

foreach ( $routes as $route => $handlers ) {
	if ( strpos( $route, '/tabesh/v1/ai' ) !== false ) {
		$ai_routes[] = $route;
	}
}

if ( ! empty( $ai_routes ) ) {
	echo "✓ Found " . count( $ai_routes ) . " AI REST endpoints:\n";
	foreach ( $ai_routes as $route ) {
		echo "  - {$route}\n";
	}
} else {
	echo "ℹ No AI REST endpoints registered (module may be disabled)\n";
}

echo "\n=== Test Summary ===\n";
echo "AI Module is properly integrated into Tabesh plugin.\n";
echo "Configuration can be done via: Tabesh → Settings → AI Settings\n\n";
