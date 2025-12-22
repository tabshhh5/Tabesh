<?php
/**
 * Test script for verifying additional services calculation fix
 *
 * This script tests the three types of additional services:
 * 1. Fixed: Applied once to entire invoice
 * 2. Per Unit: Multiplied by quantity
 * 3. Page-Based: Based on total pages with minimum 1 unit guarantee
 *
 * @package Tabesh
 */

// Simulate WordPress environment for testing
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/includes/handlers/class-tabesh-pricing-engine.php';

echo "<!DOCTYPE html>\n";
echo "<html dir='rtl' lang='fa'>\n";
echo "<head>\n";
echo "    <meta charset='UTF-8'>\n";
echo "    <title>اختبار محاسبة الخدمات الإضافية</title>\n";
echo "    <style>\n";
echo "        body { font-family: Tahoma, Arial, sans-serif; margin: 20px; direction: rtl; }\n";
echo "        table { border-collapse: collapse; width: 100%; margin: 20px 0; }\n";
echo "        th, td { border: 1px solid #ddd; padding: 12px; text-align: right; }\n";
echo "        th { background-color: #4CAF50; color: white; }\n";
echo "        .test-case { margin: 30px 0; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; }\n";
echo "        .success { color: green; font-weight: bold; }\n";
echo "        .error { color: red; font-weight: bold; }\n";
echo "        .expected { background-color: #e7f3e7; }\n";
echo "        .actual { background-color: #fff3e7; }\n";
echo "        h1 { color: #333; }\n";
echo "        h2 { color: #666; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }\n";
echo "        .formula { background: #f0f0f0; padding: 10px; margin: 10px 0; border-right: 3px solid #4CAF50; }\n";
echo "    </style>\n";
echo "</head>\n";
echo "<body>\n";

echo "<h1>🧪 اختبار محاسبة الخدمات الإضافية</h1>\n";
echo "<p>این اسکریپت سه نوع محاسبه خدمات اضافی را آزمایش می‌کند:</p>\n";
echo "<ol>\n";
echo "    <li><strong>ثابت (Fixed)</strong>: یکبار در کل فاکتور</li>\n";
echo "    <li><strong>بر اساس جلد (Per Unit)</strong>: ضرب در تعداد جلدها</li>\n";
echo "    <li><strong>بر اساس صفحه (Page-Based)</strong>: بر اساس مجموع صفحات با حداقل ۱ واحد</li>\n";
echo "</ol>\n";

// Mock the WordPress __ function for testing
if ( ! function_exists( '__' ) ) {
	/**
	 * Mock translation function
	 *
	 * @param string $text Text to translate.
	 * @param string $domain Text domain.
	 * @return string Original text.
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

/**
 * Run a test case
 *
 * @param string $test_name Test case name.
 * @param array  $pricing_matrix Pricing matrix configuration.
 * @param array  $params Order parameters.
 * @param float  $expected_fixed Expected fixed cost.
 * @param float  $expected_variable Expected variable cost.
 * @return bool True if test passes.
 */
function run_test_case( $test_name, $pricing_matrix, $params, $expected_fixed, $expected_variable ) {
	echo "<div class='test-case'>\n";
	echo "<h2>$test_name</h2>\n";

	$engine = new Tabesh_Pricing_Engine();

	// Use reflection to access private method
	$reflection = new ReflectionClass( $engine );
	$method     = $reflection->getMethod( 'calculate_extras_cost' );
	$method->setAccessible( true );

	$extras           = $params['extras'] ?? array();
	$quantity         = $params['quantity'];
	$page_count_total = $params['page_count_total'];

	$result         = $method->invoke( $engine, $pricing_matrix, $extras, $quantity, $page_count_total );
	$actual_fixed   = $result['fixed'];
	$actual_variable = $result['variable'];

	$fixed_pass    = abs( $actual_fixed - $expected_fixed ) < 0.01;
	$variable_pass = abs( $actual_variable - $expected_variable ) < 0.01;
	$pass          = $fixed_pass && $variable_pass;

	echo "<table>\n";
	echo "<tr><th>پارامتر</th><th>مقدار</th></tr>\n";
	echo "<tr><td>تعداد صفحات هر کتاب</td><td>{$page_count_total}</td></tr>\n";
	echo "<tr><td>تیراژ (تعداد جلدها)</td><td>{$quantity}</td></tr>\n";
	echo "<tr><td>خدمات انتخاب شده</td><td>" . implode( '، ', $extras ) . "</td></tr>\n";
	echo "</table>\n";

	echo "<table>\n";
	echo "<tr><th>نوع هزینه</th><th>مقدار مورد انتظار</th><th>مقدار محاسبه شده</th><th>نتیجه</th></tr>\n";

	// Fixed cost row
	$fixed_status = $fixed_pass ? "<span class='success'>✓ صحیح</span>" : "<span class='error'>✗ خطا</span>";
	echo "<tr class='" . ( $fixed_pass ? 'expected' : 'actual' ) . "'>\n";
	echo "<td>هزینه ثابت (Fixed)</td>\n";
	echo "<td>" . number_format( $expected_fixed, 0 ) . " تومان</td>\n";
	echo "<td>" . number_format( $actual_fixed, 0 ) . " تومان</td>\n";
	echo "<td>$fixed_status</td>\n";
	echo "</tr>\n";

	// Variable cost row
	$variable_status = $variable_pass ? "<span class='success'>✓ صحیح</span>" : "<span class='error'>✗ خطا</span>";
	echo "<tr class='" . ( $variable_pass ? 'expected' : 'actual' ) . "'>\n";
	echo "<td>هزینه متغیر (Variable)</td>\n";
	echo "<td>" . number_format( $expected_variable, 0 ) . " تومان</td>\n";
	echo "<td>" . number_format( $actual_variable, 0 ) . " تومان</td>\n";
	echo "<td>$variable_status</td>\n";
	echo "</tr>\n";

	// Total row
	$expected_total = $expected_fixed + $expected_variable;
	$actual_total   = $actual_fixed + $actual_variable;
	echo "<tr>\n";
	echo "<td><strong>مجموع</strong></td>\n";
	echo "<td><strong>" . number_format( $expected_total, 0 ) . " تومان</strong></td>\n";
	echo "<td><strong>" . number_format( $actual_total, 0 ) . " تومان</strong></td>\n";
	echo "<td>" . ( $pass ? "<span class='success'>✓ موفق</span>" : "<span class='error'>✗ ناموفق</span>" ) . "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "</div>\n";

	return $pass;
}

// Test Case 1: Fixed Type Service
echo "<hr>\n";
$pricing_matrix = array(
	'extras_costs' => array(
		'سلفون' => array(
			'price' => 50000,
			'type'  => 'fixed',
			'step'  => 0,
		),
	),
);

$params = array(
	'extras'           => array( 'سلفون' ),
	'quantity'         => 10,
	'page_count_total' => 200,
);

$test1 = run_test_case(
	'آزمون ۱: خدمت ثابت (Fixed)',
	$pricing_matrix,
	$params,
	50000,  // Expected fixed: 50,000 (applied once)
	0       // Expected variable: 0
);

echo "<div class='formula'>\n";
echo "<strong>فرمول:</strong> هزینه ثابت = قیمت (یکبار در کل فاکتور)<br>\n";
echo "هزینه ثابت = ۵۰٬۰۰۰ تومان<br>\n";
echo "<em>توجه: این هزینه فقط یکبار محاسبه می‌شود، صرف‌نظر از تیراژ</em>\n";
echo "</div>\n";

// Test Case 2: Per Unit Type Service
echo "<hr>\n";
$pricing_matrix = array(
	'extras_costs' => array(
		'لب گرد' => array(
			'price' => 2000,
			'type'  => 'per_unit',
			'step'  => 0,
		),
	),
);

$params = array(
	'extras'           => array( 'لب گرد' ),
	'quantity'         => 10,
	'page_count_total' => 200,
);

$test2 = run_test_case(
	'آزمون ۲: خدمت بر اساس جلد (Per Unit)',
	$pricing_matrix,
	$params,
	0,      // Expected fixed: 0
	20000   // Expected variable: 2,000 × 10 = 20,000
);

echo "<div class='formula'>\n";
echo "<strong>فرمول:</strong> هزینه = قیمت × تیراژ<br>\n";
echo "هزینه = ۲٬۰۰۰ × ۱۰ = ۲۰٬۰۰۰ تومان<br>\n";
echo "<em>این هزینه یکبار محاسبه شده و در تیراژ ضرب شده است</em>\n";
echo "</div>\n";

// Test Case 3: Page-Based Service (Pages < Step)
echo "<hr>\n";
$pricing_matrix = array(
	'extras_costs' => array(
		'طراحی' => array(
			'price' => 100000,
			'type'  => 'page_based',
			'step'  => 4000,
		),
	),
);

$params = array(
	'extras'           => array( 'طراحی' ),
	'quantity'         => 10,
	'page_count_total' => 200, // Total: 200 × 10 = 2000 pages
);

$test3 = run_test_case(
	'آزمون ۳: خدمت بر اساس صفحه - کمتر از حد (Page-Based)',
	$pricing_matrix,
	$params,
	0,       // Expected fixed: 0
	100000   // Expected variable: 100,000 × max(1, ceil(2000/4000)) = 100,000 × 1
);

echo "<div class='formula'>\n";
echo "<strong>فرمول:</strong> هزینه = قیمت × max(1, ceil(مجموع_صفحات / گام))<br>\n";
echo "مجموع صفحات = ۲۰۰ × ۱۰ = ۲٬۰۰۰ صفحه<br>\n";
echo "واحدها = max(1, ceil(۲٬۰۰۰ / ۴٬۰۰۰)) = max(1, ceil(۰.۵)) = max(1, 1) = ۱<br>\n";
echo "هزینه = ۱۰۰٬۰۰۰ × ۱ = ۱۰۰٬۰۰۰ تومان<br>\n";
echo "<em class='success'>✓ حتی اگر صفحات کمتر از گام باشد، حداقل ۱ واحد کامل محاسبه می‌شود</em>\n";
echo "</div>\n";

// Test Case 4: Page-Based Service (Pages > Step)
echo "<hr>\n";
$pricing_matrix = array(
	'extras_costs' => array(
		'طراحی' => array(
			'price' => 100000,
			'type'  => 'page_based',
			'step'  => 4000,
		),
	),
);

$params = array(
	'extras'           => array( 'طراحی' ),
	'quantity'         => 10,
	'page_count_total' => 450, // Total: 450 × 10 = 4500 pages
);

$test4 = run_test_case(
	'آزمون ۴: خدمت بر اساس صفحه - بیشتر از حد (Page-Based)',
	$pricing_matrix,
	$params,
	0,       // Expected fixed: 0
	200000   // Expected variable: 100,000 × ceil(4500/4000) = 100,000 × 2
);

echo "<div class='formula'>\n";
echo "<strong>فرمول:</strong> هزینه = قیمت × max(1, ceil(مجموع_صفحات / گام))<br>\n";
echo "مجموع صفحات = ۴۵۰ × ۱۰ = ۴٬۵۰۰ صفحه<br>\n";
echo "واحدها = max(1, ceil(۴٬۵۰۰ / ۴٬۰۰۰)) = max(1, ceil(۱.۱۲۵)) = max(1, 2) = ۲<br>\n";
echo "هزینه = ۱۰۰٬۰۰۰ × ۲ = ۲۰۰٬۰۰۰ تومان<br>\n";
echo "<em>صفحات بیشتر از گام است، پس ۲ واحد کامل محاسبه می‌شود (round up)</em>\n";
echo "</div>\n";

// Test Case 5: Mixed Services
echo "<hr>\n";
$pricing_matrix = array(
	'extras_costs' => array(
		'سلفون'  => array(
			'price' => 50000,
			'type'  => 'fixed',
			'step'  => 0,
		),
		'لب گرد' => array(
			'price' => 2000,
			'type'  => 'per_unit',
			'step'  => 0,
		),
		'طراحی'  => array(
			'price' => 100000,
			'type'  => 'page_based',
			'step'  => 4000,
		),
	),
);

$params = array(
	'extras'           => array( 'سلفون', 'لب گرد', 'طراحی' ),
	'quantity'         => 10,
	'page_count_total' => 200, // Total: 200 × 10 = 2000 pages
);

$test5 = run_test_case(
	'آزمون ۵: خدمات ترکیبی (همه انواع)',
	$pricing_matrix,
	$params,
	50000,   // Expected fixed: 50,000 (سلفون)
	120000   // Expected variable: 20,000 (لب گرد) + 100,000 (طراحی)
);

echo "<div class='formula'>\n";
echo "<strong>فرمول‌ها:</strong><br>\n";
echo "• سلفون (ثابت) = ۵۰٬۰۰۰ تومان<br>\n";
echo "• لب گرد (بر اساس جلد) = ۲٬۰۰۰ × ۱۰ = ۲۰٬۰۰۰ تومان<br>\n";
echo "• طراحی (بر اساس صفحه) = ۱۰۰٬۰۰۰ × max(1, ceil(۲۰۰۰/۴۰۰۰)) = ۱۰۰٬۰۰۰ × ۱ = ۱۰۰٬۰۰۰ تومان<br>\n";
echo "<strong>مجموع:</strong> ۵۰٬۰۰۰ + ۲۰٬۰۰۰ + ۱۰۰٬۰۰۰ = ۱۷۰٬۰۰۰ تومان\n";
echo "</div>\n";

// Summary
echo "<hr>\n";
echo "<h2>📊 خلاصه نتایج</h2>\n";
$total_tests = 5;
$passed      = ( $test1 ? 1 : 0 ) + ( $test2 ? 1 : 0 ) + ( $test3 ? 1 : 0 ) + ( $test4 ? 1 : 0 ) + ( $test5 ? 1 : 0 );

echo "<table>\n";
echo "<tr><th>شاخص</th><th>مقدار</th></tr>\n";
echo "<tr><td>تعداد کل تست‌ها</td><td>$total_tests</td></tr>\n";
echo "<tr class='" . ( $passed === $total_tests ? 'expected' : 'actual' ) . "'>\n";
echo "<td>تست‌های موفق</td><td><strong>$passed</strong></td></tr>\n";
echo "<tr><td>تست‌های ناموفق</td><td><strong>" . ( $total_tests - $passed ) . "</strong></td></tr>\n";
echo "</table>\n";

if ( $passed === $total_tests ) {
	echo "<p class='success'>✅ <strong>همه تست‌ها موفق بودند!</strong> محاسبه خدمات اضافی به درستی کار می‌کند.</p>\n";
} else {
	echo "<p class='error'>❌ <strong>برخی تست‌ها ناموفق بودند.</strong> لطفا کد را بررسی کنید.</p>\n";
}

echo "</body>\n";
echo "</html>\n";
