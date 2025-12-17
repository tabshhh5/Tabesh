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

// Get configured parameters from admin settings - these are used to build the form inputs
// Using reflection to call private methods - needed for template access
$reflection        = new ReflectionClass( $this );
$get_paper_types   = $reflection->getMethod( 'get_configured_paper_types' );
$get_binding_types = $reflection->getMethod( 'get_configured_binding_types' );
$get_extra_services = $reflection->getMethod( 'get_configured_extra_services' );

$get_paper_types->setAccessible( true );
$get_binding_types->setAccessible( true );
$get_extra_services->setAccessible( true );

$configured_paper_types = $get_paper_types->invoke( $this );
$configured_binding_types = $get_binding_types->invoke( $this );
$configured_extra_services = $get_extra_services->invoke( $this );

// Extract paper type names and all possible weights
$paper_types_names = array_keys( $configured_paper_types );
$all_weights = array();
foreach ( $configured_paper_types as $paper_type => $weights ) {
	$all_weights = array_unique( array_merge( $all_weights, $weights ) );
}
sort( $all_weights ); // Sort weights numerically

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
					$print_types = array( 'bw' => 'تک‌رنگ', 'color' => 'رنگی' );

					foreach ( $paper_types_names as $paper_type ) :
						// Get weights for this specific paper type
						$paper_weights = $configured_paper_types[ $paper_type ];
						?>
						<div class="paper-type-group">
							<h4><?php echo esc_html( $paper_type ); ?></h4>
							<table class="pricing-table pricing-table-with-toggles">
								<thead>
									<tr>
										<th><?php esc_html_e( 'گرماژ', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'تک‌رنگ (تومان)', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'فعال/غیرفعال', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'رنگی (تومان)', 'tabesh' ); ?></th>
										<th><?php esc_html_e( 'فعال/غیرفعال', 'tabesh' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $paper_weights as $weight ) : ?>
										<?php
										$bw_cost    = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['bw'] ?? 0;
										$color_cost = $pricing_matrix['page_costs'][ $paper_type ][ $weight ]['color'] ?? 0;
										
										// Check if this combination is forbidden
										$forbidden_prints = $pricing_matrix['restrictions']['forbidden_print_types'][ $paper_type ] ?? array();
										$bw_forbidden     = in_array( 'bw', $forbidden_prints, true );
										$color_forbidden  = in_array( 'color', $forbidden_prints, true );
										?>
										<tr>
											<td><?php echo esc_html( $weight ); ?></td>
											
											<!-- BW Price Input -->
											<td>
												<input type="number" 
													   name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][bw]" 
													   value="<?php echo esc_attr( $bw_cost ); ?>" 
													   step="10" 
													   min="0" 
													   class="small-text"
													   <?php echo $bw_forbidden ? 'disabled' : ''; ?>>
											</td>
											
											<!-- BW Enable/Disable Toggle -->
											<td class="toggle-cell">
												<label class="toggle-switch">
													<input type="checkbox" 
														   name="restrictions[forbidden_print_types][<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][bw]" 
														   value="0"
														   class="print-type-toggle"
														   data-paper="<?php echo esc_attr( $paper_type ); ?>"
														   data-weight="<?php echo esc_attr( $weight ); ?>"
														   data-print="bw"
														   <?php checked( ! $bw_forbidden ); ?>>
													<span class="toggle-slider"></span>
												</label>
												<span class="toggle-label"><?php echo $bw_forbidden ? esc_html__( 'غیرفعال', 'tabesh' ) : esc_html__( 'فعال', 'tabesh' ); ?></span>
											</td>
											
											<!-- Color Price Input -->
											<td>
												<input type="number" 
													   name="page_costs[<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][color]" 
													   value="<?php echo esc_attr( $color_cost ); ?>" 
													   step="10" 
													   min="0" 
													   class="small-text"
													   <?php echo $color_forbidden ? 'disabled' : ''; ?>>
											</td>
											
											<!-- Color Enable/Disable Toggle -->
											<td class="toggle-cell">
												<label class="toggle-switch">
													<input type="checkbox" 
														   name="restrictions[forbidden_print_types][<?php echo esc_attr( $paper_type ); ?>][<?php echo esc_attr( $weight ); ?>][color]" 
														   value="0"
														   class="print-type-toggle"
														   data-paper="<?php echo esc_attr( $paper_type ); ?>"
														   data-weight="<?php echo esc_attr( $weight ); ?>"
														   data-print="color"
														   <?php checked( ! $color_forbidden ); ?>>
													<span class="toggle-slider"></span>
												</label>
												<span class="toggle-label"><?php echo $color_forbidden ? esc_html__( 'غیرفعال', 'tabesh' ) : esc_html__( 'فعال', 'tabesh' ); ?></span>
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
						foreach ( $configured_binding_types as $binding_type ) :
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
							$config = $pricing_matrix['extras_costs'][ $service ] ?? array( 'price' => 0, 'type' => 'per_unit', 'step' => 0 );
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
			</div>

			<!-- Section 5: Profit Margin (renumbered from 6) -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۵. حاشیه سود', 'tabesh' ); ?></h3>
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

			<!-- Section 6: Quantity Constraints (renumbered from 7) -->
			<div class="pricing-section">
				<h3><?php esc_html_e( '۶. محدودیت‌های تیراژ', 'tabesh' ); ?></h3>
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
		
		// Find the corresponding price input
		var $priceInput = $('input[name="page_costs[' + paperType + '][' + weight + '][' + printType + ']"]');
		
		// Enable/disable the price input
		$priceInput.prop('disabled', !isEnabled);
		
		// Update the label text
		var $label = $toggle.closest('.toggle-cell').find('.toggle-label');
		$label.text(isEnabled ? 'فعال' : 'غیرفعال');
		
		// If disabling, optionally clear the value (or keep it for when re-enabled)
		// For now, we'll keep the value so admins don't lose their pricing
	});
	
	// Initialize toggles on page load
	$('.print-type-toggle').each(function() {
		var $toggle = $(this);
		var isEnabled = $toggle.is(':checked');
		var paperType = $toggle.data('paper');
		var weight = $toggle.data('weight');
		var printType = $toggle.data('print');
		
		// Find the corresponding price input
		var $priceInput = $('input[name="page_costs[' + paperType + '][' + weight + '][' + printType + ']"]');
		
		// Set initial state
		$priceInput.prop('disabled', !isEnabled);
	});
});
</script>

<!-- Inline CSS for toggle switches -->
<style>
.toggle-cell {
	text-align: center;
	white-space: nowrap;
}

.toggle-switch {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 24px;
	vertical-align: middle;
}

.toggle-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}

.toggle-slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: .4s;
	border-radius: 24px;
}

.toggle-slider:before {
	position: absolute;
	content: "";
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: .4s;
	border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
	background-color: #2ecc71;
}

.toggle-switch input:checked + .toggle-slider:before {
	transform: translateX(26px);
}

.toggle-label {
	display: inline-block;
	margin-right: 8px;
	font-size: 12px;
	color: #666;
	vertical-align: middle;
}

.pricing-table-with-toggles th,
.pricing-table-with-toggles td {
	padding: 8px 12px;
}

.pricing-table-with-toggles input[type="number"]:disabled {
	background-color: #f5f5f5;
	color: #999;
	cursor: not-allowed;
}
</style>

<!-- Styles loaded via enqueued CSS file (assets/css/product-pricing.css) -->
