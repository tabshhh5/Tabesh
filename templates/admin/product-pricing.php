<?php
/**
 * Product Pricing Management Template
 *
 * Template for the [tabesh_product_pricing] shortcode
 * Provides a modern interface for managing matrix-based pricing
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// CRITICAL CHECK: Ensure book sizes are configured before allowing access
if ( empty( $book_sizes ) ) {
	?>
	<div class="tabesh-product-pricing-wrapper">
		<div class="tabesh-setup-required" style="padding: 30px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; margin: 20px 0;">
			<div style="text-align: center; margin-bottom: 20px;">
				<span style="font-size: 48px;">⚠️</span>
			</div>
			<h2 style="color: #856404; margin-top: 0;"><?php esc_html_e( 'تنظیمات اولیه مورد نیاز است', 'tabesh' ); ?></h2>
			<p style="font-size: 16px; line-height: 1.8;">
				<?php esc_html_e( 'برای استفاده از سیستم قیمت‌گذاری، ابتدا باید قطع‌های کتاب را در تنظیمات محصول پیکربندی کنید.', 'tabesh' ); ?>
			</p>
			
			<div style="background: white; padding: 20px; border-radius: 6px; margin: 20px 0;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'مراحل تنظیمات:', 'tabesh' ); ?></h3>
				<ol style="text-align: right; line-height: 2;">
					<li><?php esc_html_e( 'به صفحه', 'tabesh' ); ?> 
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tabesh-settings' ) ); ?>" style="font-weight: bold; color: #0073aa;">
							<?php esc_html_e( 'تنظیمات محصول', 'tabesh' ); ?>
						</a> 
						<?php esc_html_e( 'بروید', 'tabesh' ); ?>
					</li>
					<li><?php esc_html_e( 'بخش "قطع‌های کتاب" را پیدا کنید', 'tabesh' ); ?></li>
					<li><?php esc_html_e( 'قطع‌های مورد نیاز خود را اضافه کنید (مثلاً: A5، A4، رقعی، وزیری)', 'tabesh' ); ?></li>
					<li><?php esc_html_e( 'تنظیمات را ذخیره کنید', 'tabesh' ); ?></li>
					<li><?php esc_html_e( 'به این صفحه برگردید و برای هر قطع، قیمت‌گذاری را تنظیم کنید', 'tabesh' ); ?></li>
				</ol>
			</div>
			
			<div style="background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 20px 0;">
				<strong><?php esc_html_e( '💡 چرا این مهم است؟', 'tabesh' ); ?></strong>
				<p style="margin: 10px 0 0 0;">
					<?php esc_html_e( 'تنظیمات محصول "منبع اصلی" (Source of Truth) برای تمام سیستم هستند. این تضمین می‌کند که همه بخش‌های افزونه از یک مجموعه یکسان قطع‌های کتاب استفاده کنند و هیچ تداخل یا ناسازگاری در داده‌ها رخ ندهد.', 'tabesh' ); ?>
				</p>
			</div>
			
			<div style="text-align: center; margin-top: 30px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tabesh-settings' ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'رفتن به تنظیمات محصول', 'tabesh' ); ?>
				</a>
			</div>
		</div>
	</div>
	<?php
	return; // Stop rendering the rest of the template
}

// Get current book size from query param or default to first available.
// CRITICAL: Validate that the book size is in the allowed list to prevent data corruption.
$requested_book_size = isset( $_GET['book_size'] ) ? sanitize_text_field( wp_unslash( $_GET['book_size'] ) ) : '';
$current_book_size   = ( ! empty( $requested_book_size ) && in_array( $requested_book_size, $book_sizes, true ) )
	? $requested_book_size
	: ( $book_sizes[0] ?? 'A5' );

// Get pricing matrix for current book size.
$pricing_matrix = $this->get_pricing_matrix_for_size( $current_book_size );

// Get configured parameters from admin settings - these are used to build the form inputs.
// Using reflection to call private methods - needed for template access.
$reflection         = new ReflectionClass( $this );
$get_paper_types    = $reflection->getMethod( 'get_configured_paper_types' );
$get_binding_types  = $reflection->getMethod( 'get_configured_binding_types' );
$get_extra_services = $reflection->getMethod( 'get_configured_extra_services' );
$get_cover_weights  = $reflection->getMethod( 'get_configured_cover_weights' );

$get_paper_types->setAccessible( true );
$get_binding_types->setAccessible( true );
$get_extra_services->setAccessible( true );
$get_cover_weights->setAccessible( true );

$configured_paper_types    = $get_paper_types->invoke( $this );
$configured_binding_types  = $get_binding_types->invoke( $this );
$configured_extra_services = $get_extra_services->invoke( $this );
$configured_cover_weights  = $get_cover_weights->invoke( $this );

// Extract paper type names and all possible weights.
$paper_types_names = array_keys( $configured_paper_types );
$all_weights       = array();
foreach ( $configured_paper_types as $paper_type => $weights ) {
	$all_weights = array_unique( array_merge( $all_weights, $weights ) );
}
sort( $all_weights ); // Sort weights numerically.

// Check if V2 engine is enabled.
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
					$print_types = array(
						'bw'    => 'تک‌رنگ',
						'color' => 'رنگی',
					);

					foreach ( $paper_types_names as $paper_type ) :
						// Get weights for this specific paper type.
						$paper_weights = $configured_paper_types[ $paper_type ];
						?>
						<div class="paper-type-group">
							<h4><?php echo esc_html( $paper_type ); ?></h4>
							<table class="pricing-table pricing-table-compact">
								<thead>
									<tr>
										<th class="col-weight"><?php esc_html_e( 'گرماژ', 'tabesh' ); ?></th>
										<th class="col-print-type"><?php esc_html_e( 'قیمت تک‌رنگ (تومان)', 'tabesh' ); ?></th>
										<th class="col-print-type"><?php esc_html_e( 'قیمت رنگی (تومان)', 'tabesh' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $paper_weights as $weight ) : ?>
										<?php
										$bw_cost    = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['bw'] ?? 0;
										$color_cost = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['color'] ?? 0;

										// Check if this specific weight combination is forbidden.
										// CRITICAL FIX: Check at the per-weight level, not per-paper-type level.
										$forbidden_prints_for_weight = $pricing_matrix['restrictions']['forbidden_print_types'][ $paper_type ][ $weight ] ?? array();
										$bw_forbidden                = in_array( 'bw', $forbidden_prints_for_weight, true );
										$color_forbidden             = in_array( 'color', $forbidden_prints_for_weight, true );
										?>
										<tr>
											<td class="weight-cell">
												<span class="weight-badge"><?php echo esc_html( $weight ); ?></span>
											</td>
											
											<!-- BW Price Input with Toggle -->
											<td class="price-input-cell">
												<div class="price-input-wrapper">
													<input type="number" 
															name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][bw]" 
															value="<?php echo esc_attr( $bw_cost ); ?>" 
															step="10" 
															min="0" 
															class="price-input"
															<?php echo $bw_forbidden ? 'disabled' : ''; ?>>
													<label class="toggle-switch-inline">
														<input type="checkbox" 
																name="restrictions[forbidden_print_types][<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][bw]" 
																value="0"
																class="print-type-toggle"
																data-paper="<?php echo esc_attr( $paper_type ); ?>"
																data-weight="<?php echo esc_attr( $weight ); ?>"
																data-print="bw"
																<?php checked( ! $bw_forbidden ); ?>>
														<span class="toggle-slider-inline"></span>
													</label>
													<span class="status-badge <?php echo $bw_forbidden ? 'status-disabled' : 'status-enabled'; ?>">
														<?php echo $bw_forbidden ? esc_html__( 'غیرفعال', 'tabesh' ) : esc_html__( 'فعال', 'tabesh' ); ?>
													</span>
												</div>
											</td>
											
											<!-- Color Price Input with Toggle -->
											<td class="price-input-cell">
												<div class="price-input-wrapper">
													<input type="number" 
															name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][color]" 
															value="<?php echo esc_attr( $color_cost ); ?>" 
															step="10" 
															min="0" 
															class="price-input"
															<?php echo $color_forbidden ? 'disabled' : ''; ?>>
													<label class="toggle-switch-inline">
														<input type="checkbox" 
																name="restrictions[forbidden_print_types][<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][color]" 
																value="0"
																class="print-type-toggle"
																data-paper="<?php echo esc_attr( $paper_type ); ?>"
																data-weight="<?php echo esc_attr( $weight ); ?>"
																data-print="color"
																<?php checked( ! $color_forbidden ); ?>>
														<span class="toggle-slider-inline"></span>
													</label>
													<span class="status-badge <?php echo $color_forbidden ? 'status-disabled' : 'status-enabled'; ?>">
														<?php echo $color_forbidden ? esc_html__( 'غیرفعال', 'tabesh' ) : esc_html__( 'فعال', 'tabesh' ); ?>
													</span>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Section 2: Binding Costs & Cover Costs (Merged) -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۲. هزینه صحافی و جلد (مخصوص این قطع)', 'tabesh' ); ?></h3>
				<p class="description">
					<?php
					/* translators: %s: book size name */
					echo esc_html( sprintf( __( 'هزینه صحافی و جلد برای قطع %s - برای هر ترکیب صحافی و گرماژ جلد', 'tabesh' ), $current_book_size ) );
					?>
				</p>

				<div class="binding-cover-matrix">
					<?php foreach ( $configured_binding_types as $binding_type ) : ?>
						<div class="binding-type-group">
							<h4><?php echo esc_html( $binding_type ); ?></h4>
							<table class="pricing-table pricing-table-compact">
								<thead>
									<tr>
										<th class="col-weight"><?php esc_html_e( 'گرماژ جلد', 'tabesh' ); ?></th>
										<th class="col-cover-price"><?php esc_html_e( 'هزینه جلد (تومان)', 'tabesh' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $configured_cover_weights as $cover_weight ) : ?>
										<?php
										// Get binding cost for this combination using helper method.
										$cover_cost = $this->get_binding_cost_for_weight( $pricing_matrix, $binding_type, $cover_weight );

										// Check if this combination is forbidden.
										$forbidden_weights = $pricing_matrix['restrictions']['forbidden_cover_weights'][ $binding_type ] ?? array();
										$is_forbidden      = in_array( $cover_weight, $forbidden_weights, true );
										?>
										<tr>
											<td class="weight-cell">
												<span class="weight-badge"><?php echo esc_html( $cover_weight ); ?></span>
											</td>
											
											<!-- Cover Price Input with Toggle -->
											<td class="price-input-cell">
												<div class="price-input-wrapper">
													<input type="number" 
															name="binding_costs[<?php echo esc_attr( $binding_type ); ?>][<?php echo esc_attr( $cover_weight ); ?>]" 
															value="<?php echo esc_attr( $cover_cost ); ?>" 
															step="100" 
															min="0" 
															class="price-input"
															<?php echo $is_forbidden ? 'disabled' : ''; ?>>
													<label class="toggle-switch-inline">
														<input type="checkbox" 
																name="restrictions[forbidden_cover_weights][<?php echo esc_attr( $binding_type ); ?>][<?php echo esc_attr( $cover_weight ); ?>]" 
																value="0"
																class="cover-weight-toggle"
																data-binding="<?php echo esc_attr( $binding_type ); ?>"
																data-weight="<?php echo esc_attr( $cover_weight ); ?>"
																<?php checked( ! $is_forbidden ); ?>>
														<span class="toggle-slider-inline"></span>
													</label>
													<span class="status-badge <?php echo $is_forbidden ? 'status-disabled' : 'status-enabled'; ?>">
														<?php echo $is_forbidden ? esc_html__( 'غیرفعال', 'tabesh' ) : esc_html__( 'فعال', 'tabesh' ); ?>
													</span>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>
				</div>
			</div>


			<!-- Section 3: Extras -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۳. خدمات اضافی', 'tabesh' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'تنظیم قیمت برای خدمات اضافی و مجاز بودن آن‌ها برای هر نوع صحافی', 'tabesh' ); ?>
				</p>

				<table class="pricing-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نام خدمت', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'قیمت', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'نوع محاسبه', 'tabesh' ); ?></th>
							<th><?php esc_html_e( 'تعداد صفحات (برای نوع بر اساس صفحات)', 'tabesh' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $configured_extra_services as $service ) :
							$config       = $pricing_matrix['extras_costs'][ $service ] ?? array(
								'price' => 0,
								'type'  => 'per_unit',
								'step'  => 0,
							);
							$service_type = $config['type'] ?? 'per_unit';
							$service_step = $config['step'] ?? 0;
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
									<select name="extras_costs[<?php echo esc_attr( $service ); ?>][type]" 
											class="extra-service-type" 
											data-service="<?php echo esc_attr( $service ); ?>">
										<option value="fixed" <?php selected( $service_type, 'fixed' ); ?>>
											<?php esc_html_e( 'ثابت', 'tabesh' ); ?>
										</option>
										<option value="per_unit" <?php selected( $service_type, 'per_unit' ); ?>>
											<?php esc_html_e( 'به ازای هر جلد', 'tabesh' ); ?>
										</option>
										<option value="page_based" <?php selected( $service_type, 'page_based' ); ?>>
											<?php esc_html_e( 'بر اساس تعداد صفحات', 'tabesh' ); ?>
										</option>
									</select>
								</td>
								<td>
									<input type="number" 
											name="extras_costs[<?php echo esc_attr( $service ); ?>][step]" 
											value="<?php echo esc_attr( $service_step ); ?>" 
											step="1" 
											min="1" 
											class="small-text extra-service-step" 
											data-service="<?php echo esc_attr( $service ); ?>"
											<?php echo ( $service_type !== 'page_based' ) ? 'disabled' : ''; ?>
											placeholder="مثال: 100">
									<small class="help-text"><?php esc_html_e( 'قیمت به ازای هر چند صفحه؟ (مثلاً 100 = قیمت به ازای هر 100 صفحه)', 'tabesh' ); ?></small>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Extra Services Restrictions per Binding Type -->
				<div class="extras-restrictions-section">
					<h4><?php esc_html_e( 'محدودیت‌های خدمات اضافی بر اساس نوع صحافی', 'tabesh' ); ?></h4>
					<p class="description">
						<?php esc_html_e( 'برای هر خدمت اضافی مشخص کنید که برای کدام نوع صحافی مجاز است. برای مثال، می‌توانید خدمت "لب گرد" را برای صحافی "جلد سخت" غیرفعال کنید.', 'tabesh' ); ?>
					</p>
					
					<div class="extras-binding-matrix">
						<?php foreach ( $configured_extra_services as $service ) : ?>
							<div class="extra-service-restrictions">
								<h5 class="extra-service-name"><?php echo esc_html( $service ); ?></h5>
								<div class="binding-toggles-grid">
									<?php
									foreach ( $configured_binding_types as $binding_type ) :
										// Check if this extra is forbidden for this binding type
										$forbidden_extras = $pricing_matrix['restrictions']['forbidden_extras'][ $binding_type ] ?? array();
										$is_forbidden     = in_array( $service, $forbidden_extras, true );
										?>
										<div class="binding-toggle-item">
											<label class="binding-toggle-label">
												<span class="binding-name"><?php echo esc_html( $binding_type ); ?></span>
												<span class="toggle-switch-inline">
													<input type="checkbox" 
															name="restrictions[forbidden_extras][<?php echo esc_attr( $binding_type ); ?>][<?php echo esc_attr( $service ); ?>]" 
															value="0"
															class="extra-binding-toggle"
															data-binding="<?php echo esc_attr( $binding_type ); ?>"
															data-extra="<?php echo esc_attr( $service ); ?>"
															<?php checked( ! $is_forbidden ); ?>>
													<span class="toggle-slider-inline"></span>
												</span>
												<span class="status-badge <?php echo $is_forbidden ? 'status-disabled' : 'status-enabled'; ?>">
													<?php echo $is_forbidden ? esc_html__( 'غیرفعال', 'tabesh' ) : esc_html__( 'فعال', 'tabesh' ); ?>
												</span>
											</label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Section 4: Profit Margin -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۴. حاشیه سود', 'tabesh' ); ?></h3>
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

			<!-- Section 5: Quantity Constraints -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۵. محدودیت‌های تیراژ', 'tabesh' ); ?></h3>
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

<!-- JavaScript for extra services step field visibility -->
<script type="text/javascript">
jQuery(document).ready(function($) {
	// Handle extra service type change - show/hide step field
	$('.extra-service-type').on('change', function() {
		var service = $(this).data('service');
		var type = $(this).val();
		var $stepInput = $('input.extra-service-step[data-service="' + service + '"]');
		
		if (type === 'page_based') {
			$stepInput.prop('disabled', false).closest('td').show();
		} else {
			$stepInput.prop('disabled', true).val(0);
		}
	});
	
	// Initialize on page load
	$('.extra-service-type').each(function() {
		var service = $(this).data('service');
		var type = $(this).val();
		var $stepInput = $('input.extra-service-step[data-service="' + service + '"]');
		
		if (type !== 'page_based') {
			$stepInput.prop('disabled', true);
		}
	});
	
	// Handle print type toggle switches
	$('.print-type-toggle').on('change', function() {
		var $toggle = $(this);
		var paperType = $toggle.data('paper');
		var weight = $toggle.data('weight');
		var printType = $toggle.data('print');
		var isEnabled = $toggle.is(':checked');
		
		// Find the corresponding price input (sibling in the same wrapper)
		var $wrapper = $toggle.closest('.price-input-wrapper');
		var $priceInput = $wrapper.find('.price-input');
		var $statusBadge = $wrapper.find('.status-badge');
		
		// Enable/disable the price input
		$priceInput.prop('disabled', !isEnabled);
		
		// Update the status badge
		if (isEnabled) {
			$statusBadge.removeClass('status-disabled').addClass('status-enabled').text('فعال');
		} else {
			$statusBadge.removeClass('status-enabled').addClass('status-disabled').text('غیرفعال');
		}
		
		// If disabling, optionally clear the value (or keep it for when re-enabled)
		// For now, we'll keep the value so admins don't lose their pricing
	});
	
	// Initialize toggles on page load
	$('.print-type-toggle').each(function() {
		var $toggle = $(this);
		var isEnabled = $toggle.is(':checked');
		
		// Find the corresponding price input (sibling in the same wrapper)
		var $wrapper = $toggle.closest('.price-input-wrapper');
		var $priceInput = $wrapper.find('.price-input');
		
		// Set initial state
		$priceInput.prop('disabled', !isEnabled);
	});
	
	// Handle cover weight toggle switches (same pattern as print type toggles)
	$('.cover-weight-toggle').on('change', function() {
		var $toggle = $(this);
		var bindingType = $toggle.data('binding');
		var weight = $toggle.data('weight');
		var isEnabled = $toggle.is(':checked');
		
		// Find the corresponding price input (sibling in the same wrapper)
		var $wrapper = $toggle.closest('.price-input-wrapper');
		var $priceInput = $wrapper.find('.price-input');
		var $statusBadge = $wrapper.find('.status-badge');
		
		// Enable/disable the price input
		$priceInput.prop('disabled', !isEnabled);
		
		// Update the status badge
		if (isEnabled) {
			$statusBadge.removeClass('status-disabled').addClass('status-enabled').text('فعال');
		} else {
			$statusBadge.removeClass('status-enabled').addClass('status-disabled').text('غیرفعال');
		}
		
		// Keep the value so admins don't lose their pricing when toggling
	});
	
	// Initialize cover weight toggles on page load
	$('.cover-weight-toggle').each(function() {
		var $toggle = $(this);
		var isEnabled = $toggle.is(':checked');
		
		// Find the corresponding price input (sibling in the same wrapper)
		var $wrapper = $toggle.closest('.price-input-wrapper');
		var $priceInput = $wrapper.find('.price-input');
		
		// Set initial state
		$priceInput.prop('disabled', !isEnabled);
	});
	
	// Handle extra service binding type toggle switches
	$('.extra-binding-toggle').on('change', function() {
		var $toggle = $(this);
		var bindingType = $toggle.data('binding');
		var extraService = $toggle.data('extra');
		var isEnabled = $toggle.is(':checked');
		
		// Find the corresponding status badge in the same label
		var $label = $toggle.closest('.binding-toggle-label');
		var $statusBadge = $label.find('.status-badge');
		
		// Update the status badge
		if (isEnabled) {
			$statusBadge.removeClass('status-disabled').addClass('status-enabled').text('فعال');
		} else {
			$statusBadge.removeClass('status-enabled').addClass('status-disabled').text('غیرفعال');
		}
	});
	
	// Initialize extra binding toggles on page load
	$('.extra-binding-toggle').each(function() {
		var $toggle = $(this);
		var isEnabled = $toggle.is(':checked');
		
		// Find the corresponding status badge in the same label
		var $label = $toggle.closest('.binding-toggle-label');
		var $statusBadge = $label.find('.status-badge');
		
		// Set initial state
		if (isEnabled) {
			$statusBadge.removeClass('status-disabled').addClass('status-enabled').text('فعال');
		} else {
			$statusBadge.removeClass('status-enabled').addClass('status-disabled').text('غیرفعال');
		}
	});
});
</script>

<!-- Inline CSS for modern compact design -->
<style>
/* Binding-Cover Matrix Grid Layout */
.binding-cover-matrix {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 24px;
	margin-top: 16px;
}

.binding-type-group {
	background: #ffffff;
	border: 2px solid #e2e8f0;
	border-radius: 12px;
	padding: 20px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
	transition: all 0.2s ease;
}

.binding-type-group:hover {
	border-color: #cbd5e1;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.binding-type-group h4 {
	margin: 0 0 16px 0;
	padding: 12px 16px;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #ffffff;
	border-radius: 8px;
	font-size: 16px;
	font-weight: 600;
	text-align: center;
	box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
}

/* Compact table design */
.pricing-table-compact {
	table-layout: fixed;
}

.pricing-table-compact .col-weight {
	width: 100px;
}

.pricing-table-compact .col-cover-price {
	width: auto;
	min-width: 250px;
}

.pricing-table-compact .col-print-type {
	width: auto;
	min-width: 200px;
}

/* Weight cell styling */
.weight-cell {
	text-align: center;
}

.weight-badge {
	display: inline-block;
	padding: 6px 14px;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #ffffff;
	border-radius: 20px;
	font-weight: 600;
	font-size: 13px;
	box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
}

/* Price input cell */
.price-input-cell {
	padding: 10px 12px !important;
}

.price-input-wrapper {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
}

.price-input {
	flex: 1;
	min-width: 100px;
	max-width: 150px;
	padding: 10px 14px;
	border: 2px solid #e2e8f0;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 500;
	transition: all 0.2s ease;
	background: #ffffff;
}

.price-input:focus {
	outline: none;
	border-color: #0073aa;
	box-shadow: 0 0 0 4px rgba(0, 115, 170, 0.1);
}

.price-input:disabled {
	background-color: #f8fafc;
	color: #94a3b8;
	cursor: not-allowed;
	border-color: #e2e8f0;
}

/* Inline toggle switch - modern compact design */
.toggle-switch-inline {
	position: relative;
	display: inline-block;
	width: 48px;
	height: 26px;
	flex-shrink: 0;
}

.toggle-switch-inline input {
	opacity: 0;
	width: 0;
	height: 0;
}

.toggle-slider-inline {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
	transition: all 0.3s ease;
	border-radius: 26px;
	box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.toggle-slider-inline:before {
	position: absolute;
	content: "";
	height: 20px;
	width: 20px;
	left: 3px;
	bottom: 3px;
	background: #ffffff;
	transition: all 0.3s ease;
	border-radius: 50%;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch-inline input:checked + .toggle-slider-inline {
	background: linear-gradient(135deg, #10b981 0%, #059669 100%);
	box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.15);
}

.toggle-switch-inline input:checked + .toggle-slider-inline:before {
	transform: translateX(22px);
}

.toggle-switch-inline:hover {
	opacity: 0.9;
}

/* Status badge */
.status-badge {
	display: inline-flex;
	align-items: center;
	padding: 6px 12px;
	border-radius: 6px;
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;
	transition: all 0.2s ease;
}

.status-badge.status-enabled {
	background: #d1fae5;
	color: #065f46;
	border: 1px solid #a7f3d0;
}

.status-badge.status-disabled {
	background: #fee2e2;
	color: #991b1b;
	border: 1px solid #fecaca;
}

/* Extra Services Restrictions Section */
.extras-restrictions-section {
	margin-top: 32px;
	padding: 24px;
	background: #f8fafc;
	border-radius: 12px;
	border: 2px solid #e2e8f0;
}

.extras-restrictions-section h4 {
	margin: 0 0 8px 0;
	color: #1e293b;
	font-size: 18px;
	font-weight: 600;
}

.extras-restrictions-section .description {
	margin-bottom: 20px;
	color: #64748b;
	font-size: 14px;
}

.extras-binding-matrix {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.extra-service-restrictions {
	background: #ffffff;
	border: 2px solid #e2e8f0;
	border-radius: 12px;
	padding: 20px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
	transition: all 0.2s ease;
}

.extra-service-restrictions:hover {
	border-color: #cbd5e1;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.extra-service-name {
	margin: 0 0 16px 0;
	padding: 10px 16px;
	background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
	color: #ffffff;
	border-radius: 8px;
	font-size: 15px;
	font-weight: 600;
	text-align: center;
	box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
}

.binding-toggles-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
}

.binding-toggle-item {
	display: flex;
	align-items: center;
}

.binding-toggle-label {
	display: flex;
	align-items: center;
	gap: 12px;
	width: 100%;
	padding: 12px 16px;
	background: #f8fafc;
	border: 2px solid #e2e8f0;
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.2s ease;
}

.binding-toggle-label:hover {
	background: #f1f5f9;
	border-color: #cbd5e1;
}

.binding-toggle-label .binding-name {
	flex: 1;
	font-weight: 500;
	color: #334155;
	font-size: 14px;
}

/* Responsive adjustments for compact design */
@media (max-width: 768px) {
	.pricing-table-compact .col-weight {
		width: 60px;
	}
	
	.price-input-wrapper {
		flex-direction: column;
		align-items: stretch;
		gap: 8px;
	}
	
	.price-input {
		max-width: 100%;
	}
	
	.toggle-switch-inline {
		align-self: center;
	}
	
	.status-badge {
		text-align: center;
	}
	
	.weight-badge {
		padding: 4px 10px;
		font-size: 12px;
	}
	
	.binding-toggles-grid {
		grid-template-columns: 1fr;
	}
	
	.binding-toggle-label {
		padding: 10px 12px;
		gap: 8px;
	}
}

/* Additional responsive handling */
@media (max-width: 480px) {
	.page-costs-matrix {
		grid-template-columns: 1fr;
	}
	
	.paper-type-group {
		font-size: 13px;
	}
	
	.pricing-table-compact th,
	.pricing-table-compact td {
		padding: 8px 6px;
		font-size: 12px;
	}
	
	.extra-service-name {
		font-size: 13px;
		padding: 8px 12px;
	}
}
</style>

<!-- Styles loaded via enqueued CSS file (assets/css/product-pricing.css) -->
