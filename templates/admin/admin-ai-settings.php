<?php
/**
 * Admin AI Settings Template
 *
 * Separate admin page for AI configuration settings.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure plugin is properly initialized.
$tabesh = function_exists( 'Tabesh' ) ? Tabesh() : null;
if ( ! $tabesh || ! isset( $tabesh->admin ) || ! $tabesh->admin ) {
	wp_die( esc_html__( 'خطا: افزونه تابش به درستی راه‌اندازی نشده است. لطفاً از نصب صحیح WooCommerce اطمینان حاصل کنید.', 'tabesh' ) );
}

$admin = $tabesh->admin;
?>

<div class="wrap tabesh-admin-settings tabesh-ai-settings-page" dir="rtl">
	<h1>
		<span class="dashicons dashicons-admin-generic" style="font-size: 30px; margin-left: 10px;"></span>
		تنظیمات هوش مصنوعی تابش
	</h1>
	
	<?php
	// Display debug info if WP_DEBUG is enabled.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		echo '<div class="notice notice-warning">';
		echo '<p><strong>حالت دیباگ فعال است.</strong> جزئیات در کنسول مرورگر و لاگ PHP قابل مشاهده است.</p>';
		echo '</div>';
	}
	?>

	<form method="post" action="">
		<?php wp_nonce_field( 'tabesh_ai_settings' ); ?>

		<div class="tabesh-ai-settings-container">
			<!-- Main Info Box -->
			<div class="notice notice-info">
				<p><strong>🤖 درباره هوش مصنوعی تابش:</strong></p>
				<ul style="margin-right: 20px;">
					<li>✨ سیستم هوش مصنوعی تابش به مشتریان در تکمیل فرم سفارش کمک می‌کند</li>
					<li>🔑 برای استفاده از حالت مستقیم، نیاز به کلید API از Google AI Studio دارید</li>
					<li>🌐 حالت سرور: افزونه شما به عنوان سرور AI عمل می‌کند</li>
					<li>📡 حالت کلاینت: به یک سرور خارجی متصل می‌شوید</li>
					<li>🔒 دسترسی‌ها را با دقت تنظیم کنید تا امنیت داده‌ها حفظ شود</li>
				</ul>
			</div>

			<!-- AI Enable/Disable Section -->
			<div class="tabesh-ai-settings-section">
				<h2><span class="dashicons dashicons-yes-alt"></span> فعال‌سازی</h2>
				<table class="form-table">
					<tr>
						<th><label for="ai_enabled">فعالسازی هوش مصنوعی</label></th>
						<td>
							<label class="tabesh-switch">
								<input type="checkbox" id="ai_enabled" name="ai_enabled" value="1"
									<?php checked( Tabesh_AI_Config::get( 'enabled', false ), true ); ?>>
								<span class="tabesh-slider"></span>
							</label>
							<p class="description">با فعال کردن این گزینه، دستیار هوشمند تابش در دسترس کاربران قرار می‌گیرد.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- AI Mode Section -->
			<div class="tabesh-ai-settings-section">
				<h2><span class="dashicons dashicons-networking"></span> حالت عملکرد</h2>
				<table class="form-table">
					<tr>
						<th><label for="ai_mode">حالت عملکرد</label></th>
						<td>
							<?php $current_mode = Tabesh_AI_Config::get_mode(); ?>
							<select id="ai_mode" name="ai_mode" class="regular-text">
								<option value="direct" <?php selected( $current_mode, 'direct' ); ?>>
									مستقیم (Direct) - اتصال مستقیم به Gemini
								</option>
								<option value="server" <?php selected( $current_mode, 'server' ); ?>>
									سرور (Server) - ارائه سرویس به کلاینت‌های خارجی
								</option>
								<option value="client" <?php selected( $current_mode, 'client' ); ?>>
									کلاینت (Client) - اتصال به سرور خارجی
								</option>
							</select>
							<p class="description">نحوه ارتباط با سیستم هوش مصنوعی را مشخص کنید.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Gemini API Settings (Direct Mode) -->
			<div class="tabesh-ai-settings-section ai-mode-section ai-mode-direct">
				<h2><span class="dashicons dashicons-cloud"></span> تنظیمات Google Gemini</h2>
				<table class="form-table">
					<tr>
						<th><label for="ai_gemini_api_key">کلید API گوگل Gemini</label></th>
						<td>
							<input type="text" id="ai_gemini_api_key" name="ai_gemini_api_key" 
								value="<?php echo esc_attr( Tabesh_AI_Config::get( 'gemini_api_key', '' ) ); ?>" 
								class="regular-text" placeholder="AIza...">
							<p class="description">
								کلید API خود را از 
								<a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a> 
								دریافت کنید.
							</p>
							<button type="button" id="test-ai-connection" class="button">
								🔍 تست اتصال
							</button>
							<span id="test-ai-status"></span>
						</td>
					</tr>
					<tr>
						<th><label for="ai_gemini_model">مدل Gemini</label></th>
						<td>
							<select id="ai_gemini_model" name="ai_gemini_model" class="regular-text">
								<?php $current_model = Tabesh_AI_Config::get( 'gemini_model', 'gemini-2.0-flash-exp' ); ?>
								<option value="gemini-2.5-flash" <?php selected( $current_model, 'gemini-2.5-flash' ); ?>>
									Gemini 2.5 Flash (جدید - توصیه می‌شود)
								</option>
								<option value="gemini-2.5-pro-preview-05-06" <?php selected( $current_model, 'gemini-2.5-pro-preview-05-06' ); ?>>
									Gemini 2.5 Pro Preview (پرمیوم)
								</option>
								<option value="gemini-2.0-flash-exp" <?php selected( $current_model, 'gemini-2.0-flash-exp' ); ?>>
									Gemini 2.0 Flash Experimental (تجربی)
								</option>
								<option value="gemini-1.5-flash" <?php selected( $current_model, 'gemini-1.5-flash' ); ?>>
									Gemini 1.5 Flash (سریع و اقتصادی)
								</option>
								<option value="gemini-1.5-pro" <?php selected( $current_model, 'gemini-1.5-pro' ); ?>>
									Gemini 1.5 Pro (پیشرفته)
								</option>
							</select>
							<p class="description">مدل AI را انتخاب کنید. Gemini 2.5 Flash برای بهترین عملکرد توصیه می‌شود.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Server Mode Settings -->
			<div class="tabesh-ai-settings-section ai-mode-section ai-mode-server" style="display: none;">
				<h2><span class="dashicons dashicons-admin-site-alt3"></span> تنظیمات حالت سرور</h2>
				<table class="form-table">
					<tr>
						<th><label for="ai_server_api_key">کلید API سرور</label></th>
						<td>
							<input type="text" id="ai_server_api_key" name="ai_server_api_key" 
								value="<?php echo esc_attr( Tabesh_AI_Config::get( 'server_api_key', '' ) ); ?>" 
								class="regular-text">
							<button type="button" id="generate-server-key" class="button">
								🔐 تولید کلید جدید
							</button>
							<p class="description">این کلید برای احراز هویت کلاینت‌های خارجی استفاده می‌شود.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Client Mode Settings -->
			<div class="tabesh-ai-settings-section ai-mode-section ai-mode-client" style="display: none;">
				<h2><span class="dashicons dashicons-admin-links"></span> تنظیمات حالت کلاینت</h2>
				<table class="form-table">
					<tr>
						<th><label for="ai_server_url">آدرس سرور AI</label></th>
						<td>
							<input type="url" id="ai_server_url" name="ai_server_url" 
								value="<?php echo esc_attr( Tabesh_AI_Config::get( 'server_url', '' ) ); ?>" 
								class="regular-text" placeholder="https://ai-server.example.com">
							<p class="description">آدرس کامل سرور AI (مثال: https://ai-server.example.com)</p>
						</td>
					</tr>
					<tr>
						<th><label for="ai_client_api_key">کلید API کلاینت</label></th>
						<td>
							<input type="text" id="ai_client_api_key" name="ai_client_api_key" 
								value="<?php echo esc_attr( Tabesh_AI_Config::get( 'client_api_key', '' ) ); ?>" 
								class="regular-text">
							<p class="description">کلید API که از مدیر سرور دریافت کرده‌اید.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Access Control Section -->
			<div class="tabesh-ai-settings-section">
				<h2><span class="dashicons dashicons-shield"></span> کنترل دسترسی</h2>
				<table class="form-table">
					<tr>
						<th>نقش‌های مجاز</th>
						<td>
							<?php
							$allowed_roles = Tabesh_AI_Config::get( 'allowed_roles', array( 'administrator', 'shop_manager', 'customer' ) );
							if ( ! is_array( $allowed_roles ) ) {
								$allowed_roles = array( 'administrator', 'shop_manager', 'customer' );
							}
							$available_roles = wp_roles()->get_names();
							foreach ( $available_roles as $role_key => $role_name ) :
								?>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="ai_allowed_roles[]" value="<?php echo esc_attr( $role_key ); ?>"
									<?php checked( in_array( $role_key, $allowed_roles, true ) ); ?>>
								<?php echo esc_html( translate_user_role( $role_name ) ); ?>
							</label>
							<?php endforeach; ?>
							<p class="description">کاربران با این نقش‌ها می‌توانند از دستیار هوشمند استفاده کنند.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Data Access Section -->
			<div class="tabesh-ai-settings-section">
				<h2><span class="dashicons dashicons-database"></span> دسترسی به داده‌ها</h2>
				<table class="form-table">
					<tr>
						<th>اطلاعات قابل دسترسی AI</th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="ai_access_orders" value="1"
									<?php checked( Tabesh_AI_Config::get( 'access_orders', true ), true ); ?>>
								اطلاعات سفارشات (پیش‌فرض: فعال)
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="ai_access_users" value="1"
									<?php checked( Tabesh_AI_Config::get( 'access_users', false ), true ); ?>>
								اطلاعات کاربران (نیاز به احتیاط)
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="ai_access_pricing" value="1"
									<?php checked( Tabesh_AI_Config::get( 'access_pricing', true ), true ); ?>>
								اطلاعات قیمت‌گذاری (پیش‌فرض: فعال)
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="ai_access_woocommerce" value="1"
									<?php checked( Tabesh_AI_Config::get( 'access_woocommerce', false ), true ); ?>>
								اطلاعات ووکامرس (نیاز به احتیاط)
							</label>
							<p class="description">مشخص کنید AI به چه اطلاعاتی دسترسی داشته باشد.</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Advanced Settings Section -->
			<div class="tabesh-ai-settings-section">
				<h2><span class="dashicons dashicons-admin-tools"></span> تنظیمات پیشرفته</h2>
				<table class="form-table">
					<tr>
						<th><label for="ai_max_tokens">حداکثر توکن پاسخ</label></th>
						<td>
							<input type="number" id="ai_max_tokens" name="ai_max_tokens" 
								value="<?php echo esc_attr( Tabesh_AI_Config::get( 'max_tokens', 2048 ) ); ?>" 
								min="100" max="8192" class="small-text">
							<p class="description">حداکثر تعداد توکن در هر پاسخ (100-8192)</p>
						</td>
					</tr>
					<tr>
						<th><label for="ai_temperature">دما (خلاقیت)</label></th>
						<td>
							<input type="range" id="ai_temperature" name="ai_temperature" 
								value="<?php echo esc_attr( Tabesh_AI_Config::get( 'temperature', 0.7 ) ); ?>" 
								min="0" max="1" step="0.1" style="width: 200px;">
							<span id="temperature-value"><?php echo esc_html( Tabesh_AI_Config::get( 'temperature', 0.7 ) ); ?></span>
							<p class="description">مقدار کمتر = پاسخ‌های دقیق‌تر | مقدار بیشتر = پاسخ‌های خلاقانه‌تر</p>
						</td>
					</tr>
					<tr>
						<th><label for="ai_cache_enabled">کش پاسخ‌ها</label></th>
						<td>
							<label class="tabesh-switch">
								<input type="checkbox" id="ai_cache_enabled" name="ai_cache_enabled" value="1"
									<?php checked( Tabesh_AI_Config::get( 'cache_enabled', true ), true ); ?>>
								<span class="tabesh-slider"></span>
							</label>
							<p class="description">فعال کردن کش برای بهبود سرعت و کاهش هزینه API</p>
						</td>
					</tr>
					<tr>
						<th><label for="ai_cache_ttl">مدت زمان کش (ثانیه)</label></th>
						<td>
							<input type="number" id="ai_cache_ttl" name="ai_cache_ttl" 
								value="<?php echo esc_attr( Tabesh_AI_Config::get( 'cache_ttl', 3600 ) ); ?>" 
								min="60" max="86400" class="small-text">
							<p class="description">مدت زمان نگهداری پاسخ‌ها در کش (60-86400 ثانیه)</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- UI Settings Section -->
			<div class="tabesh-ai-settings-section">
				<h2><span class="dashicons dashicons-admin-appearance"></span> تنظیمات رابط کاربری</h2>
				<table class="form-table">
					<tr>
						<th><label for="ai_browser_enabled">نمایش آیکون چت‌بات</label></th>
						<td>
							<label class="tabesh-switch">
								<input type="checkbox" id="ai_browser_enabled" name="ai_browser_enabled" value="1"
									<?php checked( get_option( 'tabesh_ai_browser_enabled', true ), true ); ?>>
								<span class="tabesh-slider"></span>
							</label>
							<p class="description">نمایش آیکون شناور دستیار هوشمند در سراسر سایت</p>
						</td>
					</tr>
					<tr>
						<th><label for="ai_tracking_enabled">ردیابی رفتار کاربر</label></th>
						<td>
							<label class="tabesh-switch">
								<input type="checkbox" id="ai_tracking_enabled" name="ai_tracking_enabled" value="1"
									<?php checked( get_option( 'tabesh_ai_tracking_enabled', true ), true ); ?>>
								<span class="tabesh-slider"></span>
							</label>
							<p class="description">ردیابی رفتار کاربر برای پیشنهادات هوشمند‌تر</p>
						</td>
					</tr>
					<tr>
						<th><label for="ai_field_explainer_enabled">توضیح فیلدها</label></th>
						<td>
							<label class="tabesh-switch">
								<input type="checkbox" id="ai_field_explainer_enabled" name="ai_field_explainer_enabled" value="1"
									<?php checked( get_option( 'tabesh_ai_field_explainer_enabled', true ), true ); ?>>
								<span class="tabesh-slider"></span>
							</label>
							<p class="description">نمایش توضیحات هوشمند برای فیلدهای فرم</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Site Indexing Section -->
			<div class="tabesh-ai-settings-section">
				<h2><span class="dashicons dashicons-search"></span> ایندکس صفحات سایت</h2>
				<table class="form-table">
					<tr>
						<th>وضعیت ایندکس</th>
						<td>
							<?php
							global $wpdb;
							$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$indexed_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$last_scan = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(last_scanned) FROM %i', $table_name ) );
							?>
							<p>
								<strong>تعداد صفحات ایندکس شده:</strong> 
								<span class="tabesh-badge"><?php echo esc_html( $indexed_count ? $indexed_count : '0' ); ?></span>
							</p>
							<?php if ( $last_scan ) : ?>
							<p>
								<strong>آخرین اسکن:</strong> 
								<?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $last_scan ) ) ); ?>
							</p>
							<?php endif; ?>
							<button type="button" id="reindex-site-pages" class="button">
								🔄 بازسازی ایندکس
							</button>
							<span id="reindex-status"></span>
						</td>
					</tr>
				</table>
			</div>

		</div>

		<p class="submit">
			<input type="submit" name="tabesh_save_ai_settings" class="button button-primary button-large" value="ذخیره تنظیمات هوش مصنوعی">
		</p>
	</form>
</div>

<style>
/* AI Settings Page Styles */
.tabesh-ai-settings-page {
	max-width: 1200px;
}

.tabesh-ai-settings-page h1 {
	display: flex;
	align-items: center;
	margin-bottom: 20px;
}

.tabesh-ai-settings-container {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.tabesh-ai-settings-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 8px;
	padding: 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tabesh-ai-settings-section h2 {
	margin-top: 0;
	margin-bottom: 15px;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
	font-size: 18px;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tabesh-ai-settings-section h2 .dashicons {
	color: #667eea;
}

/* Toggle Switch */
.tabesh-switch {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 26px;
}

.tabesh-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}

.tabesh-slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: 0.4s;
	border-radius: 26px;
}

.tabesh-slider:before {
	position: absolute;
	content: "";
	height: 20px;
	width: 20px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: 0.4s;
	border-radius: 50%;
}

.tabesh-switch input:checked + .tabesh-slider {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.tabesh-switch input:checked + .tabesh-slider:before {
	transform: translateX(24px);
}

/* Badge */
.tabesh-badge {
	display: inline-block;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	padding: 2px 10px;
	border-radius: 12px;
	font-size: 14px;
}

/* Form table customization */
.tabesh-ai-settings-section .form-table th {
	width: 200px;
	padding: 15px 10px;
}

.tabesh-ai-settings-section .form-table td {
	padding: 15px 10px;
}

.tabesh-ai-settings-section .description {
	color: #666;
	font-style: italic;
	margin-top: 5px;
}

/* Buttons */
.tabesh-ai-settings-page .button {
	margin-left: 10px;
}

.tabesh-ai-settings-page .button-primary {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-color: #667eea;
	box-shadow: 0 2px 5px rgba(102, 126, 234, 0.3);
}

.tabesh-ai-settings-page .button-primary:hover {
	background: linear-gradient(135deg, #5a72d0 0%, #6a4196 100%);
	border-color: #5a72d0;
}

/* Responsive */
@media screen and (max-width: 782px) {
	.tabesh-ai-settings-section .form-table th {
		width: auto;
		display: block;
		padding-bottom: 5px;
	}
	
	.tabesh-ai-settings-section .form-table td {
		display: block;
		padding-top: 0;
	}
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// AI Mode field visibility
	function updateModeVisibility() {
		var mode = $('#ai_mode').val();
		$('.ai-mode-section').hide();
		$('.ai-mode-' + mode).show();
	}
	
	$('#ai_mode').on('change', updateModeVisibility);
	updateModeVisibility();

	// Temperature slider value display
	$('#ai_temperature').on('input', function() {
		$('#temperature-value').text($(this).val());
	});

	// Generate server API key
	$('#generate-server-key').on('click', function() {
		var key = 'tbs_' + Math.random().toString(36).substring(2, 15) + 
					Math.random().toString(36).substring(2, 15);
		$('#ai_server_api_key').val(key);
	});

	// Test AI connection
	$('#test-ai-connection').on('click', function(e) {
		e.preventDefault();
		var apiKey = $('#ai_gemini_api_key').val();
		var status = $('#test-ai-status');
		
		if (!apiKey) {
			status.html('<span style="color: #d63638;">⚠️ لطفاً ابتدا کلید API را وارد کنید</span>');
			return;
		}

		$(this).prop('disabled', true);
		status.html('<span style="color: #999;">⏳ در حال تست...</span>');

		$.ajax({
			url: '<?php echo esc_url( rest_url( TABESH_REST_NAMESPACE . '/ai/chat' ) ); ?>',
			method: 'POST',
			headers: {
				'X-WP-Nonce': '<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>'
			},
			contentType: 'application/json',
			data: JSON.stringify({
				message: 'سلام',
				context: {}
			}),
			success: function(response) {
				if (response.success) {
					status.html('<span style="color: #00a32a;">✓ اتصال موفقیت‌آمیز بود</span>');
				} else {
					status.html('<span style="color: #d63638;">✗ خطا در اتصال</span>');
				}
			},
			error: function(xhr) {
				var errorMsg = 'خطا در اتصال';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMsg = xhr.responseJSON.message;
				}
				status.html('<span style="color: #d63638;">✗ ' + errorMsg + '</span>');
			},
			complete: function() {
				$('#test-ai-connection').prop('disabled', false);
			}
		});
	});

	// Reindex site pages
	$('#reindex-site-pages').on('click', function(e) {
		e.preventDefault();
		var status = $('#reindex-status');
		
		$(this).prop('disabled', true);
		status.html('<span style="color: #999;">⏳ در حال ایندکس‌گذاری...</span>');

		$.ajax({
			url: '<?php echo esc_url( rest_url( TABESH_REST_NAMESPACE . '/ai/site/reindex' ) ); ?>',
			method: 'POST',
			headers: {
				'X-WP-Nonce': '<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					status.html('<span style="color: #00a32a;">✓ ایندکس‌گذاری کامل شد (' + (response.count || 0) + ' صفحه)</span>');
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					status.html('<span style="color: #d63638;">✗ خطا در ایندکس‌گذاری</span>');
				}
			},
			error: function(xhr) {
				status.html('<span style="color: #d63638;">✗ خطا در ایندکس‌گذاری</span>');
			},
			complete: function() {
				$('#reindex-site-pages').prop('disabled', false);
			}
		});
	});
});
</script>
