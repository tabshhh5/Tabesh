<?php
/**
 * AI Settings Tab Template
 *
 * Settings interface for AI module configuration
 *
 * @package Tabesh
 * @since 1.1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get AI instance
$ai = Tabesh_AI::instance();

// Get current settings
$ai_enabled     = $ai->get_setting( 'ai_enabled', 'no' );
$active_models  = $ai->get_setting( 'ai_active_models', array() );
$all_models     = $ai->get_all_models();
$all_assistants = $ai->get_all_assistants();
?>

<div id="tab-ai" class="tabesh-tab-content">
	<h2><?php esc_html_e( 'تنظیمات هوش مصنوعی / AI Settings', 'tabesh' ); ?></h2>

	<div class="notice notice-info">
		<p><strong><?php esc_html_e( 'ℹ️ راهنما:', 'tabesh' ); ?></strong></p>
		<p><?php esc_html_e( 'ماژول هوش مصنوعی به صورت کاملاً مستقل طراحی شده و هیچ تأثیری بر عملکرد اصلی افزونه ندارد.', 'tabesh' ); ?></p>
		<ul style="margin-right: 20px;">
			<li><?php esc_html_e( '✅ می‌توانید آن را در هر زمان فعال یا غیرفعال کنید', 'tabesh' ); ?></li>
			<li><?php esc_html_e( '🔌 از مدل‌های مختلف هوش مصنوعی پشتیبانی می‌کند', 'tabesh' ); ?></li>
			<li><?php esc_html_e( '🤖 دستیارهای تخصصی با سطوح دسترسی قابل تنظیم', 'tabesh' ); ?></li>
			<li><?php esc_html_e( '🔐 کاملاً ایمن و مبتنی بر نقش‌های وردپرس', 'tabesh' ); ?></li>
		</ul>
		<p style="direction: ltr; text-align: left;">
			<strong>ℹ️ Guide:</strong> The AI module is completely independent and has no impact on the plugin's core functionality.
			You can enable/disable it at any time. Supports multiple AI models and specialized assistants with role-based access control.
		</p>
	</div>

	<!-- Enable/Disable AI Module -->
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="ai_enabled">
					<?php esc_html_e( 'فعال‌سازی ماژول هوش مصنوعی', 'tabesh' ); ?>
					<br><small style="font-weight: normal;">Enable AI Module</small>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="ai_enabled" name="ai_enabled" value="yes" 
						<?php checked( $ai_enabled, 'yes' ); ?>>
					<?php esc_html_e( 'ماژول هوش مصنوعی را فعال کن', 'tabesh' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'با فعال کردن این گزینه، قابلیت‌های هوش مصنوعی در سامانه فعال می‌شوند.', 'tabesh' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<hr>

	<!-- AI Models Configuration -->
	<h3><?php esc_html_e( '🤖 مدل‌های هوش مصنوعی / AI Models', 'tabesh' ); ?></h3>

	<p><?php esc_html_e( 'مدل‌های هوش مصنوعی مورد استفاده خود را انتخاب و پیکربندی کنید:', 'tabesh' ); ?></p>

	<?php if ( empty( $all_models ) ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( '⚠️ هیچ مدل هوش مصنوعی‌ای ثبت نشده است.', 'tabesh' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $all_models as $model_id => $model ) : ?>
			<div class="tabesh-ai-model-config" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
				<h4 style="margin-top: 0;">
					<label>
						<input type="checkbox" name="ai_active_models[]" value="<?php echo esc_attr( $model_id ); ?>" 
							<?php checked( in_array( $model_id, (array) $active_models, true ) ); ?>>
						<strong><?php echo esc_html( $model->get_model_name() ); ?></strong>
						<?php if ( $model->is_configured() ) : ?>
							<span style="color: green;">✓ <?php esc_html_e( 'پیکربندی شده', 'tabesh' ); ?></span>
						<?php else : ?>
							<span style="color: orange;">⚠ <?php esc_html_e( 'نیاز به پیکربندی', 'tabesh' ); ?></span>
						<?php endif; ?>
					</label>
				</h4>

				<table class="form-table">
					<?php foreach ( $model->get_config_fields() as $field_key => $field_config ) : ?>
						<?php
						$field_name  = 'ai_model_' . $model_id . '_' . $field_key;
						$field_value = $ai->get_setting( $field_name, '' );
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_name ); ?>">
									<?php echo esc_html( $field_config['label'] ); ?>
									<?php if ( ! empty( $field_config['required'] ) ) : ?>
										<span style="color: red;">*</span>
									<?php endif; ?>
								</label>
							</th>
							<td>
								<?php if ( $field_config['type'] === 'select' ) : ?>
									<select id="<?php echo esc_attr( $field_name ); ?>" 
										name="<?php echo esc_attr( $field_name ); ?>" 
										class="regular-text">
										<?php foreach ( $field_config['options'] as $opt_value => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt_value ); ?>" 
												<?php selected( $field_value, $opt_value ); ?>>
												<?php echo esc_html( $opt_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input type="<?php echo esc_attr( $field_config['type'] ); ?>" 
										id="<?php echo esc_attr( $field_name ); ?>" 
										name="<?php echo esc_attr( $field_name ); ?>" 
										value="<?php echo esc_attr( $field_value ); ?>" 
										class="regular-text">
								<?php endif; ?>
								<?php if ( ! empty( $field_config['description'] ) ) : ?>
									<p class="description"><?php echo esc_html( $field_config['description'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<hr>

	<!-- AI Assistants Configuration -->
	<h3><?php esc_html_e( '🎯 دستیارهای هوش مصنوعی / AI Assistants', 'tabesh' ); ?></h3>

	<p><?php esc_html_e( 'دستیارهای تخصصی با قابلیت‌ها و سطوح دسترسی مختلف:', 'tabesh' ); ?></p>

	<?php if ( empty( $all_assistants ) ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( '⚠️ هیچ دستیاری ثبت نشده است.', 'tabesh' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'نام دستیار / Assistant', 'tabesh' ); ?></th>
					<th><?php esc_html_e( 'توضیحات / Description', 'tabesh' ); ?></th>
					<th><?php esc_html_e( 'نقش‌های مجاز / Allowed Roles', 'tabesh' ); ?></th>
					<th><?php esc_html_e( 'قابلیت‌ها / Capabilities', 'tabesh' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_assistants as $assistant_id => $assistant ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $assistant->get_assistant_name() ); ?></strong></td>
						<td><?php echo esc_html( $assistant->get_assistant_description() ); ?></td>
						<td>
							<?php
							$roles = $assistant->get_allowed_roles();
							echo esc_html( implode( ', ', $roles ) );
							?>
						</td>
						<td>
							<?php
							$capabilities = $assistant->get_capabilities();
							echo esc_html( implode( ', ', $capabilities ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div style="margin-top: 20px;">
			<h4><?php esc_html_e( 'پیکربندی پیشرفته دستیارها', 'tabesh' ); ?></h4>
			<p class="description">
				<?php esc_html_e( '💡 برای پیکربندی پیشرفته دستیارها (تغییر نقش‌ها، قابلیت‌ها، دستور سیستم)، از فیلترهای وردپرس استفاده کنید:', 'tabesh' ); ?>
			</p>
			<pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; direction: ltr; text-align: left;"><code>add_filter( 'tabesh_ai_assistant_can_access', function( $has_access, $user_id, $assistant_id ) {
	// Custom access logic
	return $has_access;
}, 10, 3 );</code></pre>
		</div>
	<?php endif; ?>

	<hr>

	<!-- Documentation -->
	<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 20px;">
		<h3><?php esc_html_e( '📚 مستندات / Documentation', 'tabesh' ); ?></h3>
		
		<h4><?php esc_html_e( 'نحوه استفاده از API', 'tabesh' ); ?></h4>
		<p><?php esc_html_e( 'برای ارسال درخواست به دستیارهای هوش مصنوعی:', 'tabesh' ); ?></p>
		<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; direction: ltr; text-align: left;"><code>POST /wp-json/tabesh/v1/ai/query

{
	"assistant_id": "order",
	"query": "How do I calculate the price?",
	"context": {}
}</code></pre>

		<h4><?php esc_html_e( 'افزودن مدل سفارشی', 'tabesh' ); ?> / Adding Custom Model</h4>
		<p><?php esc_html_e( 'برای افزودن مدل هوش مصنوعی جدید:', 'tabesh' ); ?></p>
		<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; direction: ltr; text-align: left;"><code>add_action( 'tabesh_ai_register_models', function( $ai ) {
	$custom_model = new My_Custom_AI_Model();
	$ai->register_model( $custom_model );
} );</code></pre>

		<h4><?php esc_html_e( 'افزودن دستیار سفارشی', 'tabesh' ); ?> / Adding Custom Assistant</h4>
		<p><?php esc_html_e( 'برای افزودن دستیار جدید:', 'tabesh' ); ?></p>
		<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; direction: ltr; text-align: left;"><code>add_action( 'tabesh_ai_register_assistants', function( $ai ) {
	$custom_assistant = new My_Custom_Assistant();
	$ai->register_assistant( $custom_assistant );
} );</code></pre>
	</div>
</div>

<style>
.tabesh-ai-model-config h4 {
	border-bottom: 1px solid #eee;
	padding-bottom: 10px;
}

.tabesh-ai-model-config .form-table th {
	width: 200px;
}
</style>
