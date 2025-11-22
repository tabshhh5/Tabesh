<?php
/**
 * Tabesh Settings Diagnostic Tool
 * 
 * This script helps diagnose issues with pricing configuration settings.
 * Place this file in the WordPress root directory and access it via browser.
 * 
 * Example: http://yoursite.com/tabesh-diagnostic.php
 * 
 * IMPORTANT: Remove this file after debugging!
 * SECURITY: This file will automatically expire after 24 hours.
 */

// Auto-expire after 24 hours
$creation_time = filectime(__FILE__);
$current_time = time();
$expiry_hours = 24;

if (($current_time - $creation_time) > ($expiry_hours * 3600)) {
    die('<h1>This diagnostic tool has expired</h1><p>For security reasons, this tool expires after ' . $expiry_hours . ' hours. Please delete this file or update its timestamp if you need to use it again.</p>');
}

// Check if wp-load.php exists before loading
if (!file_exists('wp-load.php')) {
    die('Error: wp-load.php not found. Make sure this file is in the WordPress root directory.');
}

// Load WordPress
require_once('wp-load.php');

// Verify WordPress is loaded
if (!function_exists('current_user_can')) {
    die('Error: WordPress did not load properly.');
}

// Check if user has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this diagnostic tool.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tabesh Settings Diagnostic Tool</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 30px;
        }
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        h2 {
            color: #2271b1;
            margin-top: 30px;
        }
        .test {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #2271b1;
            background: #f6f7f7;
        }
        .success {
            border-left-color: #00a32a;
            background: #edfaef;
        }
        .warning {
            border-left-color: #dba617;
            background: #fcf9e8;
        }
        .error {
            border-left-color: #d63638;
            background: #fcf0f1;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
        .status {
            font-weight: bold;
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            margin-right: 10px;
        }
        .status.pass { background: #00a32a; color: white; }
        .status.fail { background: #d63638; color: white; }
        .status.warn { background: #dba617; color: #1e1e1e; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            text-align: left;
            padding: 10px;
            border: 1px solid #c3c4c7;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Tabesh Settings Diagnostic Tool</h1>
        <p><strong>Warning:</strong> This tool is for debugging purposes only. Delete after use.</p>

        <?php
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_settings';
        
        // Test 1: Database Table Existence
        echo '<h2>1. Database Table Check</h2>';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        $status_class = $table_exists ? 'success' : 'error';
        $status_text = $table_exists ? 'PASS' : 'FAIL';
        echo "<div class='test $status_class'>";
        echo "<span class='status " . strtolower($status_text) . "'>$status_text</span>";
        echo "Table <code>$table</code> " . ($table_exists ? "exists" : "does NOT exist");
        echo "</div>";
        
        if (!$table_exists) {
            echo "<div class='test error'>";
            echo "<strong>ERROR:</strong> The settings table does not exist. The plugin may not be activated properly.";
            echo "<br><strong>Solution:</strong> Deactivate and reactivate the Tabesh plugin.";
            echo "</div>";
        }
        
        // Test 2: Pricing Settings in Database
        echo '<h2>2. Pricing Settings in Database</h2>';
        $pricing_fields = array(
            'pricing_book_sizes' => 'Book Cutting Coefficients',
            'pricing_paper_types' => 'Paper Type Base Costs',
            'pricing_lamination_costs' => 'Lamination Costs',
            'pricing_binding_costs' => 'Binding Costs',
            'pricing_options_costs' => 'Additional Options Costs'
        );
        
        echo "<table>";
        echo "<tr><th>Field</th><th>Description</th><th>Status</th><th>Entries</th><th>Sample</th></tr>";
        
        foreach ($pricing_fields as $field => $description) {
            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT setting_value FROM $table WHERE setting_key = %s",
                $field
            ));
            
            if ($value === null) {
                echo "<tr>";
                echo "<td><code>$field</code></td>";
                echo "<td>$description</td>";
                echo "<td><span class='status fail'>NOT FOUND</span></td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "</tr>";
            } else {
                $decoded = json_decode($value, true);
                $is_valid_json = json_last_error() === JSON_ERROR_NONE;
                $entry_count = is_array($decoded) ? count($decoded) : 0;
                $status = $is_valid_json && $entry_count > 0 ? 'pass' : 'warn';
                
                $sample = '';
                if (is_array($decoded) && !empty($decoded)) {
                    // Get first key - compatible with PHP 7.2+
                    reset($decoded);
                    $first_key = key($decoded);
                    $first_value = current($decoded);
                    $sample = $first_key . ' = ' . (is_array($first_value) ? '[' . implode(', ', $first_value) . ']' : $first_value);
                }
                
                echo "<tr>";
                echo "<td><code>$field</code></td>";
                echo "<td>$description</td>";
                echo "<td><span class='status $status'>" . strtoupper($status) . "</span></td>";
                echo "<td>$entry_count</td>";
                echo "<td><code>" . esc_html($sample) . "</code></td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        // Test 3: Raw Data Dump
        echo '<h2>3. Raw Database Values</h2>';
        echo "<p>This shows the actual data stored in the database for pricing fields:</p>";
        
        foreach ($pricing_fields as $field => $description) {
            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT setting_value FROM $table WHERE setting_key = %s",
                $field
            ));
            
            echo "<div class='test'>";
            echo "<strong>$field</strong> ($description)<br>";
            if ($value === null) {
                echo "<span class='status fail'>NULL</span> (Not in database)";
            } else {
                echo "<pre>" . esc_html($value) . "</pre>";
                
                // Try to decode and show structure
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "<strong>Decoded Structure:</strong><pre>" . print_r($decoded, true) . "</pre>";
                } else {
                    echo "<span class='status fail'>JSON ERROR:</span> " . json_last_error_msg();
                }
            }
            echo "</div>";
        }
        
        // Test 4: get_setting() Method Test
        echo '<h2>4. get_setting() Method Test</h2>';
        echo "<p>This tests if the Tabesh_Admin class can retrieve settings correctly:</p>";
        
        if (class_exists('Tabesh_Admin')) {
            $admin = new Tabesh_Admin();
            
            echo "<table>";
            echo "<tr><th>Field</th><th>Returned Type</th><th>Entry Count</th><th>Status</th></tr>";
            
            foreach ($pricing_fields as $field => $description) {
                $result = $admin->get_setting($field, array());
                $type = gettype($result);
                $count = is_array($result) ? count($result) : 'N/A';
                $status = (is_array($result) && count($result) > 0) ? 'pass' : 'warn';
                
                echo "<tr>";
                echo "<td><code>$field</code></td>";
                echo "<td>$type</td>";
                echo "<td>$count</td>";
                echo "<td><span class='status $status'>" . strtoupper($status) . "</span></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='test error'>";
            echo "<strong>ERROR:</strong> Tabesh_Admin class not found. Plugin may not be loaded.";
            echo "</div>";
        }
        
        // Test 5: WordPress Environment
        echo '<h2>5. WordPress Environment</h2>';
        echo "<table>";
        echo "<tr><td><strong>WordPress Version</strong></td><td>" . get_bloginfo('version') . "</td></tr>";
        echo "<tr><td><strong>PHP Version</strong></td><td>" . PHP_VERSION . "</td></tr>";
        echo "<tr><td><strong>WP_DEBUG</strong></td><td>" . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled ✓' : 'Disabled') . "</td></tr>";
        echo "<tr><td><strong>WP_DEBUG_LOG</strong></td><td>" . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled ✓' : 'Disabled') . "</td></tr>";
        echo "<tr><td><strong>Database Prefix</strong></td><td>" . $wpdb->prefix . "</td></tr>";
        echo "</table>";
        
        // Test 6: Recommendations
        echo '<h2>6. Recommendations</h2>';
        
        $all_present = true;
        foreach ($pricing_fields as $field => $description) {
            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT setting_value FROM $table WHERE setting_key = %s",
                $field
            ));
            if ($value === null) {
                $all_present = false;
                break;
            }
        }
        
        if (!$all_present) {
            echo "<div class='test warning'>";
            echo "<h3>⚠️ Missing Settings Detected</h3>";
            echo "<p><strong>Action Required:</strong></p>";
            echo "<ol>";
            echo "<li>Go to <strong>WordPress Admin → تابش → تنظیمات</strong></li>";
            echo "<li>Click on the <strong>قیمت‌گذاری (Pricing)</strong> tab</li>";
            echo "<li>Fill in any empty fields with appropriate values</li>";
            echo "<li>Click <strong>ذخیره تنظیمات (Save Settings)</strong></li>";
            echo "<li>Refresh this page to verify</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='test success'>";
            echo "<h3>✅ All Pricing Settings Present</h3>";
            echo "<p>All required pricing configuration fields are in the database.</p>";
            echo "</div>";
        }
        
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            echo "<div class='test warning'>";
            echo "<h3>💡 Enable Debug Mode</h3>";
            echo "<p>To see detailed error messages, add these lines to <code>wp-config.php</code>:</p>";
            echo "<pre>";
            echo "define('WP_DEBUG', true);\n";
            echo "define('WP_DEBUG_LOG', true);\n";
            echo "define('WP_DEBUG_DISPLAY', false);";
            echo "</pre>";
            echo "<p>Errors will be logged to <code>wp-content/debug.log</code></p>";
            echo "</div>";
        }
        
        ?>
        
        <h2>7. Testing Instructions</h2>
        <div class="test">
            <h3>How to Test Pricing Configuration</h3>
            <ol>
                <li><strong>Access Admin Settings:</strong> Go to WordPress Admin → تابش → تنظیمات</li>
                <li><strong>Open Pricing Tab:</strong> Click on "قیمت‌گذاری" tab</li>
                <li><strong>Enter Test Data:</strong> In "ضرایب قطع کتاب" field, enter:
                    <pre>A5=1
A4=1.5
Test=2.0</pre>
                </li>
                <li><strong>Open Browser Console:</strong> Press F12 and go to Console tab</li>
                <li><strong>Save Settings:</strong> Click "ذخیره تنظیمات"</li>
                <li><strong>Check Console:</strong> Look for messages starting with "Tabesh:"</li>
                <li><strong>Refresh Page:</strong> Reload the settings page</li>
                <li><strong>Verify:</strong> The test data should still be visible in the field</li>
                <li><strong>Run This Diagnostic Again:</strong> Refresh this page to see updated values</li>
            </ol>
        </div>
        
        <div style="margin-top: 40px; padding: 20px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;">
            <strong>⚠️ SECURITY WARNING:</strong> Delete this file after debugging!<br>
            File location: <code><?php echo __FILE__; ?></code><br>
            <strong>Auto-Expiry:</strong> This file will automatically expire <?php 
            $remaining = $expiry_hours * 3600 - ($current_time - $creation_time);
            echo floor($remaining / 3600) . ' hours and ' . floor(($remaining % 3600) / 60) . ' minutes';
            ?> from now.
        </div>
    </div>
</body>
</html>
