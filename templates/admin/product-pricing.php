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
		<div class="pricing-help-notice">
			<strong>💡 راهنما:</strong>
			<p><?php esc_html_e( 'در این سیستم، قیمت هر صفحه شامل هزینه کاغذ + چاپ است (نه جداگانه). برای مثال: اگر کاغذ 70 گرم تحریر 100 تومان و چاپ تک‌رنگ 300 تومان باشد، عدد 400 را وارد کنید.', 'tabesh' ); ?></p>
			<p><?php esc_html_e( 'هر قطع کتاب قیمت‌گذاری کاملاً مستقل دارد و نیازی به ضریب یا محاسبه پیچیده نیست.', 'tabesh' ); ?></p>
		</div>
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
					// Use product parameters from settings instead of hardcoded values
					$print_types = array( 'bw' => 'تک‌رنگ', 'color' => 'رنگی' );

					foreach ( $product_paper_types as $paper_type => $weights ) :
						?>
						<div class="paper-type-group">
							<h4><?php echo esc_html( $paper_type ); ?></h4>
							<table class="pricing-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'گرماژ', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'تک‌رنگ (تومان)', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'فعال', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'رنگی (تومان)', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'فعال', 'tabesh' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $weights as $weight ) : ?>
										<?php
										$bw_cost           = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['bw'] ?? 0;
										$color_cost        = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['color'] ?? 0;
										$forbidden_prints  = $pricing_matrix['restrictions']['forbidden_print_types'][ $paper_type ] ?? array();
										$bw_enabled        = ! in_array( 'bw', $forbidden_prints, true );
										$color_enabled     = ! in_array( 'color', $forbidden_prints, true );
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
											<td style="text-align: center;">
												<label class="toggle-switch">
													<input type="checkbox" 
														   name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][bw_enabled]" 
														   value="1"
														   <?php checked( $bw_enabled ); ?>>
													<span class="toggle-slider"></span>
												</label>
											</td>
											<td>
												<input type="number" 
													   name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][color]" 
													   value="<?php echo esc_attr( $color_cost ); ?>" 
													   step="10" 
													   min="0" 
													   class="small-text">
											</td>
											<td style="text-align: center;">
												<label class="toggle-switch">
													<input type="checkbox" 
														   name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][color_enabled]" 
														   value="1"
														   <?php checked( $color_enabled ); ?>>
													<span class="toggle-slider"></span>
												</label>
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
					<?php
					/* translators: %s: book size name */
					echo esc_html( sprintf( __( 'هزینه صحافی برای قطع %s', 'tabesh' ), $current_book_size ) );
					?>
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
						// Use product binding types from settings instead of hardcoded values
						foreach ( $product_binding_types as $binding_type ) :
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
					<?php
					/* translators: %s: book size name */
					echo esc_html( sprintf( __( 'هزینه ثابت جلد برای قطع %s', 'tabesh' ), $current_book_size ) );
					?>
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

				<table class="pricing-table extras-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نام خدمت', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'قیمت', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'نوع محاسبه', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'گام (برای صفحات)', 'tabesh' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Use product extras from settings instead of hardcoded values
						foreach ( $product_extras as $service ) :
							$config = $pricing_matrix['extras_costs'][ $service ] ?? array( 'price' => 0, 'type' => 'per_unit', 'step' => 0 );
							$is_page_based = ( isset( $config['type'] ) && 'page_based' === $config['type'] );
							?>
							<tr class="extra-row">
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
									<select name="extras_costs[<?php echo esc_attr( $service ); ?>][type]" class="extra-type-select">
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
								<td class="step-cell">
									<input type="number" 
										   name="extras_costs[<?php echo esc_attr( $service ); ?>][step]" 
										   value="<?php echo esc_attr( $config['step'] ?? 16000 ); ?>" 
										   step="1000" 
										   min="0" 
										   class="small-text extra-step-input"
										   style="<?php echo $is_page_based ? '' : 'display:none;'; ?>">
									<span class="step-help" style="<?php echo $is_page_based ? '' : 'display:none;'; ?>">
										<?php esc_html_e( 'تعداد صفحات', 'tabesh' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Section 5: محدودیت‌های صحافی (Optional Binding Restrictions) -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۵. محدودیت‌های صحافی (اختیاری)', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'در صورت نیاز، می‌توانید برخی نوع‌های صحافی را برای این قطع ممنوع کنید', 'tabesh' ); ?>
				</p>
				<p class="help-text" style="color: #666; font-size: 13px; margin-top: 8px;">
					💡 <?php esc_html_e( 'توجه: محدودیت‌های نوع چاپ (تک‌رنگ/رنگی) از طریق کلیدهای فعال/غیرفعال در بخش ۱ تنظیم می‌شوند', 'tabesh' ); ?>
				</p>

				<div class="restrictions-group">
					<h4><?php esc_html_e( 'صحافی‌های ممنوع', 'tabesh' ); ?></h4>
					<?php
					// Use product binding types from settings
					foreach ( $product_binding_types as $binding_type ) :
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

			<!-- Section 7: Quantity Constraints -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۷. محدودیت‌های تیراژ', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'تعیین حداقل، حداکثر و گام تغییر تیراژ برای این قطع کتاب', 'tabesh' ); ?>
				</p>
				<?php
				$constraints = $pricing_matrix['quantity_constraints'] ?? array();
				$min_qty     = $constraints['minimum_quantity'] ?? 10;
				$max_qty     = $constraints['maximum_quantity'] ?? 10000;
				$step_qty    = $constraints['quantity_step'] ?? 10;
				?>
				<table class="pricing-table">
					<tbody>
						<tr>
							<td>
								<strong><?php esc_html_e( 'حداقل تیراژ', 'tabesh' ); ?></strong>
								<p class="help-text"><?php esc_html_e( 'کمترین تعداد مجاز برای سفارش این قطع', 'tabesh' ); ?></p>
							</td>
							<td>
								<input type="number" 
									   name="quantity_constraints[minimum_quantity]" 
									   value="<?php echo esc_attr( $min_qty ); ?>" 
									   step="1" 
									   min="1" 
									   class="regular-text">
								<span class="unit"><?php esc_html_e( 'عدد', 'tabesh' ); ?></span>
							</td>
						</tr>
						<tr>
							<td>
								<strong><?php esc_html_e( 'حداکثر تیراژ', 'tabesh' ); ?></strong>
								<p class="help-text"><?php esc_html_e( 'بیشترین تعداد مجاز برای سفارش این قطع', 'tabesh' ); ?></p>
							</td>
							<td>
								<input type="number" 
									   name="quantity_constraints[maximum_quantity]" 
									   value="<?php echo esc_attr( $max_qty ); ?>" 
									   step="1" 
									   min="1" 
									   class="regular-text">
								<span class="unit"><?php esc_html_e( 'عدد', 'tabesh' ); ?></span>
							</td>
						</tr>
						<tr>
							<td>
								<strong><?php esc_html_e( 'گام تغییر تیراژ', 'tabesh' ); ?></strong>
								<p class="help-text"><?php esc_html_e( 'تیراژ باید مضربی از این عدد باشد (مثال: اگر 50 باشد، فقط 50، 100، 150، ... مجاز است)', 'tabesh' ); ?></p>
							</td>
							<td>
								<input type="number" 
									   name="quantity_constraints[quantity_step]" 
									   value="<?php echo esc_attr( $step_qty ); ?>" 
									   step="1" 
									   min="1" 
									   class="regular-text">
								<span class="unit"><?php esc_html_e( 'عدد', 'tabesh' ); ?></span>
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

<!-- Styles and scripts loaded via enqueued files:
     - assets/css/product-pricing.css
     - assets/js/product-pricing.js
-->
