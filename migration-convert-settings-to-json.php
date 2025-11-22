<?php
/**
 * Migration Script: Convert Legacy Settings to JSON Format
 * 
 * This script converts old text-based settings to the new JSON format.
 * Run this script once on staging/production before deploying the new code.
 * 
 * Usage (WP-CLI):
 *   wp eval-file migration-convert-settings-to-json.php
 * 
 * Usage (Direct PHP):
 *   php -d display_errors=1 migration-convert-settings-to-json.php
 * 
 * @package Tabesh
 */

// Load WordPress if running directly
if (!defined('ABSPATH')) {
    // Try to locate wp-load.php
    $wp_load_paths = array(
        dirname(__FILE__) . '/../../../wp-load.php',
        dirname(__FILE__) . '/../../../../wp-load.php',
        dirname(__FILE__) . '/../../../../../wp-load.php',
    );
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die("Error: Could not locate WordPress. Please run via WP-CLI or ensure WordPress is properly loaded.\n");
    }
}

// Prevent running in production without confirmation
if (!defined('WP_CLI') && !isset($_GET['confirm']) && php_sapi_name() !== 'cli') {
    die("This is a data migration script. Please run via WP-CLI or add ?confirm=yes to the URL if you're absolutely sure.\n");
}

echo "=== Tabesh Settings Migration Script ===\n";
echo "Starting migration of legacy settings to JSON format...\n\n";

global $wpdb;
$table = $wpdb->prefix . 'tabesh_settings';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if (!$table_exists) {
    die("Error: Settings table does not exist. Please activate the Tabesh plugin first.\n");
}

// Define settings to migrate
$simple_array_keys = array(
    'book_sizes',
    'print_types', 
    'binding_types',
    'license_types',
    'cover_paper_weights',
    'lamination_types',
    'extras',
);

$object_keys = array(
    'paper_types',
    'pricing_book_sizes',
    'pricing_paper_types',
    'pricing_lamination_costs',
    'pricing_binding_costs',
    'pricing_options_costs',
);

$migrated_count = 0;
$skipped_count = 0;
$error_count = 0;

// Migrate simple array fields
echo "Migrating simple array fields...\n";
foreach ($simple_array_keys as $key) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", $key));
    
    if (!$row) {
        echo "  ⊘ $key: Not found in database (skipped)\n";
        $skipped_count++;
        continue;
    }
    
    $val = $row->setting_value;
    
    // Skip if already valid JSON
    $decoded = json_decode($val, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        echo "  ✓ $key: Already in JSON format (skipped)\n";
        $skipped_count++;
        continue;
    }
    
    // Parse as comma or newline separated string
    if (is_string($val)) {
        $parts = preg_split('/[\r\n,]+/', $val);
        $parts = array_map('trim', $parts);
        $parts = array_values(array_filter($parts, 'strlen'));
        
        if (empty($parts)) {
            echo "  ⊘ $key: Empty value (skipped)\n";
            $skipped_count++;
            continue;
        }
        
        $new_value = wp_json_encode($parts, JSON_UNESCAPED_UNICODE);
        
        $result = $wpdb->update(
            $table,
            array('setting_value' => $new_value),
            array('setting_key' => $key),
            array('%s'),
            array('%s')
        );
        
        if ($result !== false) {
            echo "  ✓ $key: Migrated to JSON array (", count($parts), " items)\n";
            $migrated_count++;
        } else {
            echo "  ✗ $key: Database update failed\n";
            $error_count++;
        }
    }
}

// Migrate object fields (key=value format)
echo "\nMigrating object fields (key=value format)...\n";
foreach ($object_keys as $key) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", $key));
    
    if (!$row) {
        echo "  ⊘ $key: Not found in database (skipped)\n";
        $skipped_count++;
        continue;
    }
    
    $val = $row->setting_value;
    
    // Skip if already valid JSON
    $decoded = json_decode($val, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "  ✓ $key: Already in JSON format (skipped)\n";
        $skipped_count++;
        continue;
    }
    
    // Parse as key=value lines
    if (is_string($val)) {
        $lines = preg_split('/[\r\n]+/', $val);
        $obj = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=') === false) {
                continue;
            }
            
            $equal_pos = strpos($line, '=');
            $k = trim(substr($line, 0, $equal_pos));
            $v = trim(substr($line, $equal_pos + 1));
            
            if ($k !== '' && $v !== '') {
                // Try to parse as number
                if (is_numeric($v)) {
                    $obj[$k] = strpos($v, '.') !== false ? floatval($v) : intval($v);
                } else {
                    // For paper_types, check if value is a comma-separated list of numbers
                    if ($key === 'paper_types' && strpos($v, ',') !== false) {
                        $weights = array_map('intval', array_map('trim', explode(',', $v)));
                        $obj[$k] = $weights;
                    } else {
                        $obj[$k] = $v;
                    }
                }
            }
        }
        
        if (empty($obj)) {
            echo "  ⊘ $key: Could not parse value (skipped)\n";
            $skipped_count++;
            continue;
        }
        
        $new_value = wp_json_encode($obj, JSON_UNESCAPED_UNICODE);
        
        $result = $wpdb->update(
            $table,
            array('setting_value' => $new_value),
            array('setting_key' => $key),
            array('%s'),
            array('%s')
        );
        
        if ($result !== false) {
            echo "  ✓ $key: Migrated to JSON object (", count($obj), " items)\n";
            $migrated_count++;
        } else {
            echo "  ✗ $key: Database update failed\n";
            $error_count++;
        }
    }
}

// Summary
echo "\n=== Migration Complete ===\n";
echo "Migrated: $migrated_count\n";
echo "Skipped:  $skipped_count\n";
echo "Errors:   $error_count\n";

if ($error_count > 0) {
    echo "\nWARNING: Some settings failed to migrate. Please review the errors above.\n";
    exit(1);
} else {
    echo "\nAll settings have been successfully migrated to JSON format.\n";
    exit(0);
}
