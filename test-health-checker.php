<?php
<?php
/**
 * Test script for Pricing Health Checker
 *
 * This script tests the health checker functionality and displays the results.
 * 
 * SECURITY: This file should only be used in development/staging environments.
 * Delete or move outside web root in production.
 *
 * @package Tabesh
 */

// Security: Prevent direct execution
if ( ! defined( 'ABSPATH' ) ) {
	// Try to load WordPress
	$wp_load_paths = array(
		dirname( dirname( __DIR__ ) ) . '/wp-load.php', // Standard plugin location
		dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php', // Alternative location
	);

	$loaded = false;
	foreach ( $wp_load_paths as $wp_load ) {
		if ( file_exists( $wp_load ) ) {
			require_once $wp_load;
			$loaded = true;
			break;
		}
	}

	if ( ! $loaded ) {
		wp_die( 'Could not load WordPress. Please ensure this file is in the correct location.' );
	}
}

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'شما دسترسی به این صفحه را ندارید / You do not have access to this page.' );
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>تست Health Checker - Tabesh</title>
	<style>
		body {
			font-family: Tahoma, Arial, sans-serif;
			background: #f5f5f5;
			margin: 0;
			padding: 20px;
		}
		.container {
			max-width: 1200px;
			margin: 0 auto;
			background: white;
			padding: 30px;
			border-radius: 8px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		h1 {
			color: #333;
			border-bottom: 3px solid #0073aa;
			padding-bottom: 10px;
		}
		.section {
			margin: 30px 0;
			padding: 20px;
			background: #f9f9f9;
			border-radius: 4px;
		}
		.section h2 {
			margin-top: 0;
			color: #0073aa;
		}
		pre {
			background: #282c34;
			color: #abb2bf;
			padding: 15px;
			border-radius: 4px;
			overflow-x: auto;
			direction: ltr;
			text-align: left;
		}
		.back-link {
			display: inline-block;
			margin-top: 20px;
			padding: 10px 20px;
			background: #0073aa;
			color: white;
			text-decoration: none;
			border-radius: 4px;
		}
		.back-link:hover {
			background: #005a87;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>🔍 تست Health Checker موتور قیمت‌گذاری V2</h1>
		<p>این صفحه برای تست عملکرد Health Checker طراحی شده است.</p>

		<div class="section">
			<h2>📊 گزارش سلامت HTML</h2>
			<?php
			// Display HTML health report
			echo Tabesh_Pricing_Health_Checker::get_html_report();
			?>
		</div>

		<div class="section">
			<h2>🔢 داده‌های خام Health Check (JSON)</h2>
			<p>این داده‌ها برای دیباگ و توسعه مفید هستند:</p>
			<pre><?php
			$health_data = Tabesh_Pricing_Health_Checker::run_health_check();
			echo wp_json_encode( $health_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			?></pre>
		</div>

		<div class="section">
			<h2>📝 نتیجه بررسی</h2>
			<?php
			$status       = $health_data['overall_status'];
			$status_text  = '';
			$status_color = '';

			switch ( $status ) {
				case 'healthy':
					$status_text  = 'سیستم سالم است ✓';
					$status_color = 'green';
					break;
				case 'warning':
					$status_text  = 'هشدارهایی وجود دارد ⚠';
					$status_color = 'orange';
					break;
				case 'critical':
					$status_text  = 'خطاهای حیاتی وجود دارد ✗';
					$status_color = 'red';
					break;
			}
			?>
			<p style="font-size: 24px; color: <?php echo esc_attr( $status_color ); ?>; font-weight: bold;">
				<?php echo esc_html( $status_text ); ?>
			</p>
			
			<?php if ( ! empty( $health_data['errors'] ) ) : ?>
				<h3 style="color: red;">خطاها:</h3>
				<ul>
					<?php foreach ( $health_data['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $health_data['warnings'] ) ) : ?>
				<h3 style="color: orange;">هشدارها:</h3>
				<ul>
					<?php foreach ( $health_data['warnings'] as $warning ) : ?>
						<li><?php echo esc_html( $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $health_data['recommendations'] ) ) : ?>
				<h3 style="color: blue;">توصیه‌ها:</h3>
				<ol>
					<?php
					$unique_recommendations = array_unique( $health_data['recommendations'] );
					foreach ( $unique_recommendations as $recommendation ) :
						?>
						<li><?php echo esc_html( $recommendation ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>

		<div class="section">
			<h2>ℹ️ اطلاعات سیستم</h2>
			<ul>
				<li><strong>نسخه WordPress:</strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?></li>
				<li><strong>نسخه PHP:</strong> <?php echo esc_html( PHP_VERSION ); ?></li>
				<li><strong>نسخه Tabesh:</strong> <?php echo esc_html( TABESH_VERSION ); ?></li>
				<li><strong>موتور قیمت‌گذاری V2:</strong> 
					<?php echo Tabesh_Pricing_Engine::is_v2_active() ? '<span style="color: green;">✓ فعال</span>' : '<span style="color: red;">✗ غیرفعال</span>'; ?>
				</li>
				<li><strong>WP_DEBUG:</strong> 
					<?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span style="color: orange;">فعال</span>' : '<span style="color: gray;">غیرفعال</span>'; ?>
				</li>
			</ul>
		</div>

		<a href="<?php echo esc_url( admin_url() ); ?>" class="back-link">← بازگشت به پیشخوان</a>
	</div>
</body>
</html>
