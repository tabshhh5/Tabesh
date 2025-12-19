<?php
/**
 * Complete Pricing Cycle End-to-End Test
 *
 * This test validates the ENTIRE pricing cycle from setup to order submission.
 * It identifies any remaining broken dependencies or edge cases.
 *
 * @package Tabesh
 */

// Load WordPress
$wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	die( 'Error: Cannot find wp-load.php. This test must be run from wp-content/plugins/Tabesh/' );
}
require_once $wp_load_path;

// Security check
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied. Administrators only.' );
}

// HTML Header
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
	<meta charset="UTF-8">
	<title>Tabesh - تست چرخه کامل قیمت‌گذاری</title>
	<style>
		body {
			font-family: Tahoma, Arial, sans-serif;
			padding: 20px;
			background: #f0f0f0;
			direction: rtl;
		}
		.container {
			max-width: 1400px;
			margin: 0 auto;
			background: white;
			padding: 30px;
			border-radius: 8px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
		}
		h1 {
			color: #0073aa;
			border-bottom: 3px solid #0073aa;
			padding-bottom: 10px;
		}
		h2 {
			color: #333;
			margin-top: 30px;
			border-bottom: 2px solid #ddd;
			padding-bottom: 5px;
		}
		.test-pass {
			background: #e8f5e9;
			border-left: 4px solid #4caf50;
			padding: 15px;
			margin: 10px 0;
			color: #2e7d32;
		}
		.test-fail {
			background: #ffebee;
			border-left: 4px solid #f44336;
			padding: 15px;
			margin: 10px 0;
			color: #c62828;
		}
		.test-warn {
			background: #fff3e0;
			border-left: 4px solid #ff9800;
			padding: 15px;
			margin: 10px 0;
			color: #e65100;
		}
		.test-info {
			background: #e3f2fd;
			border-left: 4px solid #2196f3;
			padding: 15px;
			margin: 10px 0;
			color: #1976d2;
		}
		.code {
			background: #f5f5f5;
			padding: 10px;
			border-radius: 4px;
			font-family: 'Courier New', monospace;
			font-size: 12px;
			overflow: auto;
			margin: 10px 0;
		}
		.summary {
			background: #fafafa;
			border: 2px solid #ddd;
			padding: 20px;
			margin: 20px 0;
			border-radius: 4px;
		}
		.summary h3 {
			margin-top: 0;
			color: #0073aa;
		}
		ul {
			list-style-type: none;
			padding-right: 0;
		}
		li:before {
			content: "• ";
			color: #0073aa;
			font-weight: bold;
			margin-left: 5px;
		}
	</style>
</head>
<body>
<div class="container">

<h1>🔍 تست جامع چرخه قیمت‌گذاری ماتریسی</h1>
<p>این تست تمام مراحل چرخه قیمت‌گذاری را از ابتدا تا انتها بررسی می‌کند.</p>

<?php

// Test counter
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;
$warnings = 0;

function test_result( $name, $passed, $message = '', $details = '' ) {
	global $total_tests, $passed_tests, $failed_tests;
	$total_tests++;
	
	if ( $passed ) {
		$passed_tests++;
		echo '<div class="test-pass">';
		echo '<strong>✓ PASS:</strong> ' . esc_html( $name );
	} else {
		$failed_tests++;
		echo '<div class="test-fail">';
		echo '<strong>✗ FAIL:</strong> ' . esc_html( $name );
	}
	
	if ( $message ) {
		echo '<br>' . esc_html( $message );
	}
	
	if ( $details ) {
		echo '<div class="code">' . esc_html( $details ) . '</div>';
	}
	
	echo '</div>';
}

function test_warning( $name, $message ) {
	global $warnings;
	$warnings++;
	echo '<div class="test-warn">';
	echo '<strong>⚠ WARNING:</strong> ' . esc_html( $name );
	if ( $message ) {
		echo '<br>' . esc_html( $message );
	}
	echo '</div>';
}

function test_info( $name, $message = '' ) {
	echo '<div class="test-info">';
	echo '<strong>ℹ INFO:</strong> ' . esc_html( $name );
	if ( $message ) {
		echo '<br>' . esc_html( $message );
	}
	echo '</div>';
}

global $wpdb;
$table_settings = $wpdb->prefix . 'tabesh_settings';

// Phase 1: Check Database Structure
echo '<h2>مرحله ۱: بررسی ساختار دیتابیس</h2>';

$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_settings'" ) === $table_settings;
test_result(
	'جدول تنظیمات وجود دارد',
	$table_exists,
	$table_exists ? "جدول $table_settings موجود است" : "جدول $table_settings یافت نشد"
);

// Phase 2: Check Product Parameters (Source of Truth)
echo '<h2>مرحله ۲: بررسی تنظیمات محصول (منبع اصلی)</h2>';

$book_sizes_raw = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
		'book_sizes'
	)
);

$book_sizes = array();
if ( $book_sizes_raw ) {
	$decoded = json_decode( $book_sizes_raw, true );
	if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
		$book_sizes = $decoded;
	}
}

test_result(
	'تنظیمات محصول (book_sizes) تعریف شده',
	! empty( $book_sizes ),
	empty( $book_sizes ) 
		? 'هیچ قطعی در تنظیمات محصول تعریف نشده - باید ابتدا قطع‌ها را در تنظیمات تعریف کنید'
		: 'تعداد قطع‌های تعریف شده: ' . count( $book_sizes ),
	! empty( $book_sizes ) ? 'قطع‌ها: ' . implode( ', ', $book_sizes ) : ''
);

if ( empty( $book_sizes ) ) {
	test_warning(
		'توقف تست',
		'بدون تنظیمات محصول، چرخه قیمت‌گذاری نمی‌تواند کار کند. لطفا ابتدا به تنظیمات → محصولات بروید و قطع‌های کتاب را تعریف کنید.'
	);
	goto test_summary;
}

// Phase 3: Check Pricing Engine V2 Status
echo '<h2>مرحله ۳: بررسی وضعیت موتور قیمت‌گذاری V2</h2>';

$pricing_engine_enabled = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM $table_settings WHERE setting_key = %s",
		'pricing_engine_v2_enabled'
	)
);

test_result(
	'موتور قیمت‌گذاری V2 در دیتابیس',
	$pricing_engine_enabled !== null,
	$pricing_engine_enabled === null 
		? 'تنظیمات pricing_engine_v2_enabled در دیتابیس یافت نشد'
		: 'مقدار: ' . $pricing_engine_enabled
);

$is_v2_enabled = ( '1' === $pricing_engine_enabled || 'true' === $pricing_engine_enabled );
test_result(
	'موتور قیمت‌گذاری V2 فعال است',
	$is_v2_enabled,
	$is_v2_enabled ? 'موتور V2 فعال است' : 'موتور V2 غیرفعال است - باید فعال شود',
	'مقدار در DB: ' . var_export( $pricing_engine_enabled, true )
);

if ( ! $is_v2_enabled ) {
	test_warning(
		'موتور غیرفعال',
		'موتور قیمت‌گذاری V2 باید برای فرم سفارش V2 فعال باشد. به فرم ثبت قیمت بروید و موتور را فعال کنید.'
	);
}

// Phase 4: Check Pricing Matrices
echo '<h2>مرحله ۴: بررسی ماتریس‌های قیمت</h2>';

$all_matrices = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT setting_key, setting_value FROM $table_settings WHERE setting_key LIKE %s",
		'pricing_matrix_%'
	),
	ARRAY_A
);

test_info(
	'تعداد ماتریس‌های قیمت در دیتابیس',
	'تعداد: ' . count( $all_matrices )
);

// Decode and validate each matrix
$valid_matrices = array();
$invalid_matrices = array();
$orphaned_matrices = array();

foreach ( $all_matrices as $row ) {
	$setting_key = $row['setting_key'];
	$safe_key = str_replace( 'pricing_matrix_', '', $setting_key );
	
	// Decode book size
	$decoded_size = base64_decode( $safe_key, true );
	if ( false !== $decoded_size && ! empty( $decoded_size ) ) {
		$book_size = $decoded_size;
	} else {
		// Legacy format
		$book_size = $safe_key;
	}
	
	// Check if book size is in product parameters
	$is_orphaned = ! in_array( $book_size, $book_sizes, true );
	
	// Decode matrix
	$matrix = json_decode( $row['setting_value'], true );
	$is_valid_json = ( JSON_ERROR_NONE === json_last_error() && is_array( $matrix ) );
	
	if ( $is_orphaned ) {
		$orphaned_matrices[] = array(
			'key' => $setting_key,
			'size' => $book_size,
		);
	} elseif ( ! $is_valid_json ) {
		$invalid_matrices[] = array(
			'key' => $setting_key,
			'size' => $book_size,
			'error' => 'Invalid JSON',
		);
	} else {
		// Check if matrix has required data
		$has_papers = ! empty( $matrix['page_costs'] );
		$has_bindings = ! empty( $matrix['binding_costs'] );
		
		$valid_matrices[ $book_size ] = array(
			'key' => $setting_key,
			'has_papers' => $has_papers,
			'has_bindings' => $has_bindings,
			'is_complete' => $has_papers && $has_bindings,
			'matrix' => $matrix,
		);
	}
}

test_result(
	'ماتریس‌های معتبر',
	count( $valid_matrices ) > 0,
	'تعداد ماتریس‌های معتبر: ' . count( $valid_matrices ),
	implode( ', ', array_keys( $valid_matrices ) )
);

if ( ! empty( $orphaned_matrices ) ) {
	test_warning(
		'ماتریس‌های یتیم یافت شد',
		'تعداد: ' . count( $orphaned_matrices ) . ' - این ماتریس‌ها برای قطع‌هایی هستند که در تنظیمات محصول نیستند'
	);
	foreach ( $orphaned_matrices as $orphan ) {
		echo '<div class="code">قطع: ' . esc_html( $orphan['size'] ) . ' → کلید: ' . esc_html( $orphan['key'] ) . '</div>';
	}
}

if ( ! empty( $invalid_matrices ) ) {
	test_result(
		'ماتریس‌های نامعتبر',
		false,
		'تعداد: ' . count( $invalid_matrices )
	);
}

// Check completeness of valid matrices
foreach ( $valid_matrices as $size => $info ) {
	test_result(
		"ماتریس قطع '$size' کامل است",
		$info['is_complete'],
		$info['is_complete'] 
			? 'دارای paper costs و binding costs'
			: 'ناقص: ' . ( ! $info['has_papers'] ? 'فاقد paper costs ' : '' ) . ( ! $info['has_bindings'] ? 'فاقد binding costs' : '' )
	);
}

// Phase 5: Test Constraint Manager
echo '<h2>مرحله ۵: بررسی Constraint Manager</h2>';

try {
	$constraint_manager = new Tabesh_Constraint_Manager();
	$available_sizes = $constraint_manager->get_available_book_sizes();
	
	test_result(
		'Constraint Manager کار می‌کند',
		true,
		'تعداد قطع‌های قابل استفاده: ' . count( $available_sizes )
	);
	
	// Check which sizes are enabled
	$enabled_sizes = array();
	$disabled_sizes = array();
	
	foreach ( $available_sizes as $size_info ) {
		if ( $size_info['enabled'] ) {
			$enabled_sizes[] = $size_info['size'];
		} else {
			$disabled_sizes[] = array(
				'size' => $size_info['size'],
				'reason' => sprintf(
					'papers: %d, bindings: %d',
					$size_info['paper_count'],
					$size_info['binding_count']
				),
			);
		}
	}
	
	test_result(
		'قطع‌های فعال برای فرم سفارش',
		! empty( $enabled_sizes ),
		empty( $enabled_sizes ) 
			? 'هیچ قطعی برای نمایش در فرم سفارش فعال نیست'
			: 'تعداد قطع‌های فعال: ' . count( $enabled_sizes ),
		! empty( $enabled_sizes ) ? 'قطع‌های فعال: ' . implode( ', ', $enabled_sizes ) : ''
	);
	
	if ( ! empty( $disabled_sizes ) ) {
		test_warning(
			'قطع‌های غیرفعال',
			'تعداد: ' . count( $disabled_sizes )
		);
		foreach ( $disabled_sizes as $disabled ) {
			echo '<div class="code">قطع: ' . esc_html( $disabled['size'] ) . ' → دلیل: ' . esc_html( $disabled['reason'] ) . '</div>';
		}
	}
	
} catch ( Exception $e ) {
	test_result(
		'Constraint Manager کار می‌کند',
		false,
		'خطا: ' . $e->getMessage()
	);
}

// Phase 6: Test Pricing Engine
echo '<h2>مرحله ۶: بررسی Pricing Engine</h2>';

try {
	$pricing_engine = new Tabesh_Pricing_Engine();
	
	test_result(
		'Pricing Engine ایجاد شد',
		true,
		'نمونه Pricing Engine ساخته شد'
	);
	
	// Test is_enabled method
	$is_enabled = $pricing_engine->is_enabled();
	test_result(
		'متد is_enabled() کار می‌کند',
		is_bool( $is_enabled ),
		'مقدار برگشتی: ' . ( $is_enabled ? 'true' : 'false' )
	);
	
	// Test get_configured_book_sizes
	$configured_sizes = $pricing_engine->get_configured_book_sizes();
	test_result(
		'متد get_configured_book_sizes() کار می‌کند',
		is_array( $configured_sizes ),
		'تعداد قطع‌های پیکربندی شده: ' . count( $configured_sizes ),
		! empty( $configured_sizes ) ? implode( ', ', $configured_sizes ) : 'هیچ قطعی'
	);
	
	// Compare with product parameters
	$sizes_match = ( count( array_diff( $configured_sizes, $book_sizes ) ) === 0 )
		&& ( count( array_diff( $book_sizes, $configured_sizes ) ) === 0 );
	
	if ( ! $sizes_match ) {
		$only_in_product = array_diff( $book_sizes, $configured_sizes );
		$only_in_pricing = array_diff( $configured_sizes, $book_sizes );
		
		test_warning(
			'عدم تطابق قطع‌ها',
			''
		);
		if ( ! empty( $only_in_product ) ) {
			echo '<div class="code">فقط در تنظیمات محصول: ' . implode( ', ', $only_in_product ) . '</div>';
		}
		if ( ! empty( $only_in_pricing ) ) {
			echo '<div class="code">فقط در pricing matrices: ' . implode( ', ', $only_in_pricing ) . '</div>';
		}
	}
	
} catch ( Exception $e ) {
	test_result(
		'Pricing Engine کار می‌کند',
		false,
		'خطا: ' . $e->getMessage()
	);
}

// Phase 7: Test Order Form Readiness
echo '<h2>مرحله ۷: بررسی آمادگی فرم سفارش V2</h2>';

$form_can_work = $is_v2_enabled && ! empty( $enabled_sizes );
test_result(
	'فرم سفارش V2 می‌تواند کار کند',
	$form_can_work,
	$form_can_work 
		? 'تمام پیش‌نیازها برقرار است'
		: 'پیش‌نیازها برقرار نیست: ' . ( ! $is_v2_enabled ? 'موتور V2 غیرفعال، ' : '' ) . ( empty( $enabled_sizes ) ? 'هیچ قطع فعالی نیست' : '' )
);

// Test Summary
test_summary:
echo '<h2>خلاصه نتایج</h2>';

echo '<div class="summary">';
echo '<h3>نتیجه کلی تست</h3>';
echo '<ul>';
echo '<li><strong>تعداد کل تست‌ها:</strong> ' . $total_tests . '</li>';
echo '<li style="color: green;"><strong>موفق:</strong> ' . $passed_tests . '</li>';
echo '<li style="color: red;"><strong>ناموفق:</strong> ' . $failed_tests . '</li>';
echo '<li style="color: orange;"><strong>هشدارها:</strong> ' . $warnings . '</li>';
echo '</ul>';

if ( $failed_tests === 0 && $warnings === 0 ) {
	echo '<div class="test-pass" style="margin-top: 20px; font-size: 18px;">';
	echo '<strong>✓ عالی! چرخه قیمت‌گذاری کامل و آماده استفاده است.</strong>';
	echo '</div>';
} elseif ( $failed_tests === 0 ) {
	echo '<div class="test-warn" style="margin-top: 20px; font-size: 18px;">';
	echo '<strong>⚠ سیستم کار می‌کند اما چند هشدار وجود دارد که باید بررسی شوند.</strong>';
	echo '</div>';
} else {
	echo '<div class="test-fail" style="margin-top: 20px; font-size: 18px;">';
	echo '<strong>✗ مشکلاتی یافت شد که باید حل شوند.</strong>';
	echo '</div>';
}

echo '</div>';

// Recommendations
echo '<h2>توصیه‌ها</h2>';
echo '<div class="test-info">';
echo '<h3>برای رفع مشکلات:</h3>';
echo '<ol style="text-align: right; list-style-position: inside;">';
echo '<li>ابتدا به <strong>تنظیمات → محصولات</strong> بروید و قطع‌های کتاب را تعریف کنید</li>';
echo '<li>سپس به <strong>فرم ثبت قیمت [tabesh_product_pricing]</strong> بروید</li>';
echo '<li>موتور قیمت‌گذاری V2 را <strong>فعال</strong> کنید</li>';
echo '<li>برای هر قطع، <strong>ماتریس قیمت کامل</strong> (با paper costs و binding costs) تنظیم کنید</li>';
echo '<li>دوباره این تست را اجرا کنید</li>';
echo '</ol>';
echo '</div>';

?>

</div>
</body>
</html>
