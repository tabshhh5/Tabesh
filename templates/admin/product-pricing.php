<?php
/**
 * Product Pricing Management Template
 *
 * Template for the [tabesh_product_pricing] shortcode
 * Provides a modern interface for managing matrix-based pricing
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current book size from query param or default to first available
$current_book_size = isset( $_GET['book_size'] ) ? sanitize_text_field( wp_unslash( $_GET['book_size'] ) ) : ( $book_sizes[0] ?? 'A5' );

// Get pricing matrix for current book size
$pricing_matrix = $this->get_pricing_matrix_for_size( $current_book_size );

// Check if V2 engine is enabled
$v2_enabled = $this->pricing_engine->is_enabled();
?>

<div class="tabesh-product-pricing-wrapper">
	<div class="tabesh-pricing-header">
		<h2><?php esc_html_e( 'مدیریت قیمت‌گذاری محصولات', 'tabesh' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'قیمت‌گذاری مستقل برای هر قطع کتاب - سیستم ماتریکسی پیشرفته', 'tabesh' ); ?>
		</p>
	</div>

	<!-- Engine Status Toggle -->
	<div class="tabesh-pricing-engine-status">
		<div class="engine-status-card">
			<h3><?php esc_html_e( 'وضعیت موتور قیمت‌گذاری', 'tabesh' ); ?></h3>
			<div class="status-indicator">
				<?php if ( $v2_enabled ) : ?>
					<span class="badge badge-success"><?php esc_html_e( 'موتور جدید (V2) فعال', 'tabesh' ); ?></span>
					<p><?php esc_html_e( 'سیستم ماتریکسی پیشرفته در حال استفاده است', 'tabesh' ); ?></p>
				<?php else : ?>
					<span class="badge badge-warning"><?php esc_html_e( 'موتور قدیمی (V1) فعال', 'tabesh' ); ?></span>
					<p><?php esc_html_e( 'برای استفاده از قیمت‌گذاری ماتریکسی، موتور جدید را فعال کنید', 'tabesh' ); ?></p>
				<?php endif; ?>
			</div>
			
			<form method="post" class="engine-toggle-form">
				<?php wp_nonce_field( 'tabesh_toggle_engine', 'tabesh_toggle_nonce' ); ?>
				<input type="hidden" name="action" value="toggle_pricing_engine">
				<input type="hidden" name="enable_v2" value="<?php echo $v2_enabled ? '0' : '1'; ?>">
				<button type="submit" class="button button-primary">
					<?php echo $v2_enabled ? esc_html__( 'بازگشت به موتور قدیمی', 'tabesh' ) : esc_html__( 'فعال‌سازی موتور جدید', 'tabesh' ); ?>
				</button>
			</form>
		</div>
	</div>

	<!-- Book Size Selector -->
	<div class="tabesh-book-size-selector">
		<h3><?php esc_html_e( 'انتخاب قطع کتاب', 'tabesh' ); ?></h3>
		<div class="book-size-tabs">
			<?php foreach ( $book_sizes as $size ) : ?>
				<a href="?book_size=<?php echo esc_attr( $size ); ?>" 
				   class="book-size-tab <?php echo $size === $current_book_size ? 'active' : ''; ?>">
					<?php echo esc_html( $size ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Pricing Configuration Form -->
	<form method="post" class="tabesh-pricing-form">
		<?php wp_nonce_field( 'tabesh_save_pricing', 'tabesh_pricing_nonce' ); ?>
		<input type="hidden" name="book_size" value="<?php echo esc_attr( $current_book_size ); ?>">

		<div class="pricing-sections">
			<!-- Section 1: Page Costs (Paper + Print Combined) -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۱. هزینه هر صفحه (کاغذ + چاپ)', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'قیمت نهایی هر صفحه شامل هزینه کاغذ و چاپ (بدون ضریب)', 'tabesh' ); ?>
				</p>

				<div class="page-costs-matrix">
					<?php
					$paper_types   = array( 'تحریر', 'بالک', 'گلاسه' );
					$paper_weights = array( '60', '70', '80', '100' );
					$print_types   = array( 'bw' => 'تک‌رنگ', 'color' => 'رنگی' );

					foreach ( $paper_types as $paper_type ) :
						?>
						<div class="paper-type-group">
							<h4><?php echo esc_html( $paper_type ); ?></h4>
							<table class="pricing-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'گرماژ', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'تک‌رنگ (تومان)', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'رنگی (تومان)', 'tabesh' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $paper_weights as $weight ) : ?>
										<?php
										$bw_cost    = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['bw'] ?? 0;
										$color_cost = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['color'] ?? 0;
										?>
										<tr>
											<td><?php echo esc_html( $weight ); ?></td>
											<td>
												<input type="number" 
													   name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][bw]" 
													   value="<?php echo esc_attr( $bw_cost ); ?>" 
													   step="10" 
													   min="0" 
													   class="small-text">
											</td>
											<td>
												<input type="number" 
													   name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][color]" 
													   value="<?php echo esc_attr( $color_cost ); ?>" 
													   step="10" 
													   min="0" 
													   class="small-text">
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Section 2: Binding Costs -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۲. هزینه صحافی (مخصوص این قطع)', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'هزینه صحافی برای قطع ' . $current_book_size, 'tabesh' ); ?>
				</p>

				<table class="pricing-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نوع صحافی', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'هزینه (تومان)', 'tabesh' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$binding_types = array( 'شومیز', 'جلد سخت', 'گالینگور', 'سیمی', 'منگنه' );
						foreach ( $binding_types as $binding_type ) :
							$cost = $pricing_matrix['binding_costs'][ $binding_type ] ?? 0;
							?>
							<tr>
								<td><?php echo esc_html( $binding_type ); ?></td>
								<td>
									<input type="number" 
										   name="binding_costs[<?php echo esc_attr( $binding_type ); ?>]" 
										   value="<?php echo esc_attr( $cost ); ?>" 
										   step="100" 
										   min="0" 
										   class="small-text">
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Section 3: Cover Cost -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۳. هزینه جلد (مخصوص این قطع)', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'هزینه ثابت جلد برای قطع ' . $current_book_size, 'tabesh' ); ?>
				</p>

				<table class="pricing-table">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'هزینه جلد', 'tabesh' ); ?></td>
							<td>
								<input type="number" 
									   name="cover_cost" 
									   value="<?php echo esc_attr( $pricing_matrix['cover_cost'] ?? 8000 ); ?>" 
									   step="100" 
									   min="0" 
									   class="regular-text">
								<span class="unit"><?php esc_html_e( 'تومان', 'tabesh' ); ?></span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Section 4: Extras -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۴. خدمات اضافی', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'تنظیم قیمت برای خدمات اضافی (لب گرد، شیرینک، ...)', 'tabesh' ); ?>
				</p>

				<table class="pricing-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نام خدمت', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'قیمت', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'نوع محاسبه', 'tabesh' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$extra_services = array( 'لب گرد', 'خط تا', 'شیرینک', 'سوراخ', 'شماره گذاری' );
						foreach ( $extra_services as $service ) :
							$config = $pricing_matrix['extras_costs'][ $service ] ?? array( 'price' => 0, 'type' => 'per_unit', 'step' => 0 );
							?>
							<tr>
								<td><?php echo esc_html( $service ); ?></td>
								<td>
									<input type="number" 
										   name="extras_costs[<?php echo esc_attr( $service ); ?>][price]" 
										   value="<?php echo esc_attr( $config['price'] ); ?>" 
										   step="100" 
										   min="0" 
										   class="small-text">
								</td>
								<td>
									<select name="extras_costs[<?php echo esc_attr( $service ); ?>][type]">
										<option value="fixed" <?php selected( $config['type'], 'fixed' ); ?>>
											<?php esc_html_e( 'ثابت', 'tabesh' ); ?>
										</option>
										<option value="per_unit" <?php selected( $config['type'], 'per_unit' ); ?>>
											<?php esc_html_e( 'به ازای هر جلد', 'tabesh' ); ?>
										</option>
										<option value="page_based" <?php selected( $config['type'], 'page_based' ); ?>>
											<?php esc_html_e( 'بر اساس تعداد صفحات', 'tabesh' ); ?>
										</option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Section 5: Restrictions -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۵. محدودیت‌ها (ممنوع‌سازی پارامترها)', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'تعیین کنید کدام پارامترها برای این قطع مجاز نیستند', 'tabesh' ); ?>
				</p>

				<div class="restrictions-group">
					<h4><?php esc_html_e( 'کاغذهای ممنوع', 'tabesh' ); ?></h4>
					<?php
					foreach ( $paper_types as $paper_type ) :
						$forbidden = in_array( $paper_type, $pricing_matrix['restrictions']['forbidden_paper_types'] ?? array(), true );
						?>
						<label>
							<input type="checkbox" 
								   name="restrictions[forbidden_paper_types][]" 
								   value="<?php echo esc_attr( $paper_type ); ?>"
								   <?php checked( $forbidden ); ?>>
							<?php echo esc_html( $paper_type ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="restrictions-group">
					<h4><?php esc_html_e( 'صحافی‌های ممنوع', 'tabesh' ); ?></h4>
					<?php
					foreach ( $binding_types as $binding_type ) :
						$forbidden = in_array( $binding_type, $pricing_matrix['restrictions']['forbidden_binding_types'] ?? array(), true );
						?>
						<label>
							<input type="checkbox" 
								   name="restrictions[forbidden_binding_types][]" 
								   value="<?php echo esc_attr( $binding_type ); ?>"
								   <?php checked( $forbidden ); ?>>
							<?php echo esc_html( $binding_type ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Section 6: Profit Margin -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۶. حاشیه سود', 'tabesh' ); ?></h3>
				<table class="pricing-table">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'حاشیه سود', 'tabesh' ); ?></td>
							<td>
								<input type="number" 
									   name="profit_margin" 
									   value="<?php echo esc_attr( ( $pricing_matrix['profit_margin'] ?? 0 ) * 100 ); ?>" 
									   step="1" 
									   min="0" 
									   max="100" 
									   class="small-text">
								<span class="unit">%</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Save Button -->
		<div class="pricing-form-footer">
			<button type="submit" class="button button-primary button-large">
				<?php esc_html_e( 'ذخیره تنظیمات قیمت‌گذاری', 'tabesh' ); ?>
			</button>
		</div>
	</form>
</div>

<style>
.tabesh-product-pricing-wrapper {
	max-width: 1200px;
	margin: 20px 0;
	padding: 20px;
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tabesh-pricing-header {
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 2px solid #0073aa;
}

.tabesh-pricing-header h2 {
	margin: 0 0 10px 0;
	color: #0073aa;
}

.tabesh-pricing-engine-status {
	margin-bottom: 30px;
}

.engine-status-card {
	background: #f8f9fa;
	padding: 20px;
	border-radius: 6px;
	border-right: 4px solid #0073aa;
}

.badge {
	display: inline-block;
	padding: 5px 15px;
	border-radius: 4px;
	font-weight: bold;
	font-size: 14px;
}

.badge-success {
	background: #28a745;
	color: #fff;
}

.badge-warning {
	background: #ffc107;
	color: #333;
}

.tabesh-book-size-selector {
	margin-bottom: 30px;
}

.book-size-tabs {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

.book-size-tab {
	padding: 10px 20px;
	background: #f0f0f0;
	border: 2px solid #ddd;
	border-radius: 6px;
	text-decoration: none;
	color: #333;
	font-weight: bold;
	transition: all 0.3s;
}

.book-size-tab:hover {
	background: #e0e0e0;
	border-color: #0073aa;
}

.book-size-tab.active {
	background: #0073aa;
	color: #fff;
	border-color: #0073aa;
}

.pricing-section {
	margin-bottom: 40px;
	padding: 20px;
	background: #fafafa;
	border-radius: 6px;
	border: 1px solid #e0e0e0;
}

.pricing-section h3 {
	margin-top: 0;
	color: #0073aa;
}

.pricing-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 15px;
}

.pricing-table th,
.pricing-table td {
	padding: 10px;
	text-align: right;
	border-bottom: 1px solid #ddd;
}

.pricing-table th {
	background: #f0f0f0;
	font-weight: bold;
}

.page-costs-matrix {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
}

.paper-type-group h4 {
	margin-top: 0;
	padding: 10px;
	background: #0073aa;
	color: #fff;
	border-radius: 4px;
}

.restrictions-group {
	margin: 15px 0;
	padding: 15px;
	background: #fff;
	border-radius: 4px;
}

.restrictions-group h4 {
	margin-top: 0;
}

.restrictions-group label {
	display: block;
	margin: 8px 0;
}

.pricing-form-footer {
	margin-top: 30px;
	padding-top: 20px;
	border-top: 2px solid #ddd;
	text-align: center;
}

.tabesh-success {
	padding: 15px;
	margin-bottom: 20px;
	background: #d4edda;
	border: 1px solid #c3e6cb;
	border-radius: 4px;
	color: #155724;
}

.tabesh-error {
	padding: 15px;
	margin-bottom: 20px;
	background: #f8d7da;
	border: 1px solid #f5c6cb;
	border-radius: 4px;
	color: #721c24;
}
</style>
