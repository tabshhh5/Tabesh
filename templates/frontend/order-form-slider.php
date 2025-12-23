<?php
/**
 * Order Form Slider Integration Template
 *
 * Modern multi-step form with Revolution Slider integration via JS events.
 * This form emits 'tabesh:formStateChange' events on every field change.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get constraint manager to fetch available book sizes.
try {
	$constraint_manager = new Tabesh_Constraint_Manager();
	$available_sizes    = $constraint_manager->get_available_book_sizes();

	// Log for debugging if WP_DEBUG is enabled.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Tabesh Order Form Slider: Available book sizes count: ' . count( $available_sizes ) );
		if ( empty( $available_sizes ) ) {
			error_log( 'Tabesh Order Form Slider: WARNING - No book sizes configured in pricing matrix' );
		}
	}
} catch ( Exception $e ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Tabesh Order Form Slider Error: ' . $e->getMessage() );
	}
	$available_sizes = array();
}

// Get settings.
$min_quantity  = Tabesh()->get_setting( 'min_quantity', 10 );
$max_quantity  = Tabesh()->get_setting( 'max_quantity', 10000 );
$quantity_step = Tabesh()->get_setting( 'quantity_step', 10 );

// Apply attributes.
$theme           = isset( $theme ) ? $theme : 'light';
$animation_speed = isset( $animation_speed ) ? $animation_speed : 'normal';
$show_title      = isset( $show_title ) ? $show_title : true;
?>

<div class="tabesh-slider-form-container" 
	dir="rtl" 
	data-theme="<?php echo esc_attr( $theme ); ?>"
	data-animation="<?php echo esc_attr( $animation_speed ); ?>">
	
	<?php if ( empty( $available_sizes ) ) : ?>
		<div class="tabesh-slider-form-error">
			<div class="error-icon">⚠️</div>
			<h3><?php echo esc_html__( 'خطا در بارگذاری فرم', 'tabesh' ); ?></h3>
			<p><?php echo esc_html__( 'هیچ قطع کتابی با قیمت‌گذاری فعال در سیستم یافت نشد.', 'tabesh' ); ?></p>
			
			<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
				<div class="admin-instructions">
					<h4><?php echo esc_html__( 'راهنمای مدیر سیستم:', 'tabesh' ); ?></h4>
					<ol>
						<li>
							<?php echo esc_html__( 'به', 'tabesh' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tabesh-settings' ) ); ?>">
								<?php echo esc_html__( 'تنظیمات محصول', 'tabesh' ); ?>
							</a>
							<?php echo esc_html__( 'بروید و قطع‌های کتاب را تعریف کنید', 'tabesh' ); ?>
						</li>
						<li>
							<?php echo esc_html__( 'به', 'tabesh' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tabesh-product-pricing' ) ); ?>">
								<?php echo esc_html__( 'مدیریت قیمت‌گذاری محصولات', 'tabesh' ); ?>
							</a>
							<?php echo esc_html__( 'بروید و ماتریس قیمت را تنظیم کنید', 'tabesh' ); ?>
						</li>
					</ol>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>

	<!-- Form Header -->
		<?php if ( $show_title ) : ?>
	<div class="slider-form-header">
		<h2 class="form-main-title">
			<span class="title-icon">📖</span>
			<?php echo esc_html__( 'فرم ثبت سفارش چاپ کتاب', 'tabesh' ); ?>
		</h2>
		<p class="form-subtitle">
			<?php echo esc_html__( 'تمام مشخصات کتاب خود را وارد کنید. تغییرات به صورت لحظه‌ای اعمال می‌شود.', 'tabesh' ); ?>
		</p>
	</div>
	<?php endif; ?>

	<!-- Progress Indicator (3 steps only) -->
	<div class="slider-form-progress">
		<div class="progress-track">
			<div class="progress-bar" id="sliderProgressBar"></div>
		</div>
		<div class="progress-labels">
			<div class="progress-label active" data-step="1">
				<span class="label-number">1</span>
				<span class="label-text"><?php echo esc_html__( 'مشخصات کتاب', 'tabesh' ); ?></span>
			</div>
			<div class="progress-label" data-step="2">
				<span class="label-number">2</span>
				<span class="label-text"><?php echo esc_html__( 'جلد و صحافی', 'tabesh' ); ?></span>
			</div>
			<div class="progress-label" data-step="3">
				<span class="label-number">3</span>
				<span class="label-text"><?php echo esc_html__( 'تکمیل', 'tabesh' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Main Form -->
	<form id="tabesh-slider-form" class="slider-order-form" novalidate>
		
		<!-- Step 1: Book Specifications (title, size, paper, print, pages, quantity) -->
		<div class="slider-form-step active" data-step="1">
			<div class="step-inner">
				<h3 class="step-heading">
					<span class="step-icon">📝</span>
					<?php echo esc_html__( 'مشخصات اصلی کتاب', 'tabesh' ); ?>
				</h3>

				<!-- Book Title -->
				<div class="form-field">
					<label for="slider_book_title" class="field-label">
						<?php echo esc_html__( 'عنوان کتاب', 'tabesh' ); ?>
						<span class="required-mark">*</span>
					</label>
					<input 
						type="text" 
						id="slider_book_title" 
						name="book_title" 
						class="field-input"
						placeholder="<?php echo esc_attr__( 'مثال: کتاب من', 'tabesh' ); ?>"
						required
						data-event-field="book_title"
					>
				</div>

				<!-- Book Size (Radio Grid) -->
				<div class="form-field">
					<label class="field-label">
						<?php echo esc_html__( 'قطع کتاب', 'tabesh' ); ?>
						<span class="required-mark">*</span>
					</label>
					<div class="book-size-grid">
						<?php foreach ( $available_sizes as $size_info ) : ?>
							<?php if ( $size_info['enabled'] ) : ?>
							<label class="size-option-card">
								<input 
									type="radio" 
									name="book_size" 
									value="<?php echo esc_attr( $size_info['size'] ); ?>"
									required
									data-event-field="book_size"
								>
								<span class="size-card-inner">
									<span class="size-name"><?php echo esc_html( $size_info['size'] ); ?></span>
									<span class="size-meta"><?php echo esc_html( $size_info['paper_count'] ); ?> نوع کاغذ</span>
								</span>
							</label>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Paper Type & Weight (Side by Side) -->
				<div class="form-row">
					<div class="form-field">
						<label for="slider_paper_type" class="field-label">
							<?php echo esc_html__( 'نوع کاغذ', 'tabesh' ); ?>
							<span class="required-mark">*</span>
						</label>
						<select id="slider_paper_type" name="paper_type" class="field-select" required data-event-field="paper_type">
							<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
						</select>
					</div>

					<div class="form-field">
						<label for="slider_paper_weight" class="field-label">
							<?php echo esc_html__( 'گرماژ کاغذ', 'tabesh' ); ?>
							<span class="required-mark">*</span>
						</label>
						<select id="slider_paper_weight" name="paper_weight" class="field-select" required data-event-field="paper_weight">
							<option value=""><?php echo esc_html__( 'ابتدا نوع کاغذ را انتخاب کنید', 'tabesh' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Print Type (Radio) -->
				<div class="form-field">
					<label class="field-label">
						<?php echo esc_html__( 'نوع چاپ', 'tabesh' ); ?>
						<span class="required-mark">*</span>
					</label>
					<div class="print-type-grid">
						<label class="print-option-card">
							<input type="radio" name="print_type" value="bw" required data-event-field="print_type">
							<span class="print-card-inner">
								<span class="print-icon">⚫</span>
								<span class="print-label"><?php echo esc_html__( 'سیاه و سفید', 'tabesh' ); ?></span>
							</span>
						</label>
						<label class="print-option-card">
							<input type="radio" name="print_type" value="color" required data-event-field="print_type">
							<span class="print-card-inner">
								<span class="print-icon">🎨</span>
								<span class="print-label"><?php echo esc_html__( 'رنگی', 'tabesh' ); ?></span>
							</span>
						</label>
					</div>
				</div>

				<!-- Page Count & Quantity (Side by Side) -->
				<div class="form-row">
					<div class="form-field">
						<label for="slider_page_count" class="field-label">
							<?php echo esc_html__( 'تعداد صفحات', 'tabesh' ); ?>
							<span class="required-mark">*</span>
						</label>
						<input 
							type="number" 
							id="slider_page_count" 
							name="page_count" 
							class="field-input"
							min="1"
							value="100"
							required
							data-event-field="page_count"
						>
					</div>

					<div class="form-field">
						<label for="slider_quantity" class="field-label">
							<?php echo esc_html__( 'تیراژ (تعداد)', 'tabesh' ); ?>
							<span class="required-mark">*</span>
						</label>
						<input 
							type="number" 
							id="slider_quantity" 
							name="quantity" 
							class="field-input"
							min="<?php echo esc_attr( $min_quantity ); ?>"
							max="<?php echo esc_attr( $max_quantity ); ?>"
							step="<?php echo esc_attr( $quantity_step ); ?>"
							value="<?php echo esc_attr( $min_quantity ); ?>"
							required
							data-event-field="quantity"
						>
						<small class="field-hint">
							<?php
							/* translators: 1: minimum quantity, 2: maximum quantity */
							echo esc_html( sprintf( __( 'حداقل: %1$d، حداکثر: %2$d', 'tabesh' ), $min_quantity, $max_quantity ) );
							?>
						</small>
					</div>
				</div>
			</div>
		</div>

		<!-- Step 2: Binding & Cover (binding type, cover weight, extras) -->
		<div class="slider-form-step" data-step="2">
			<div class="step-inner">
				<h3 class="step-heading">
					<span class="step-icon">📚</span>
					<?php echo esc_html__( 'جلد و صحافی', 'tabesh' ); ?>
				</h3>

				<!-- Binding Type -->
				<div class="form-field">
					<label for="slider_binding_type" class="field-label">
						<?php echo esc_html__( 'نوع صحافی', 'tabesh' ); ?>
						<span class="required-mark">*</span>
					</label>
					<select id="slider_binding_type" name="binding_type" class="field-select" required data-event-field="binding_type">
						<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
					</select>
				</div>

				<!-- Cover Weight -->
				<div class="form-field">
					<label for="slider_cover_weight" class="field-label">
						<?php echo esc_html__( 'گرماژ جلد', 'tabesh' ); ?>
						<span class="required-mark">*</span>
					</label>
					<select id="slider_cover_weight" name="cover_weight" class="field-select" required data-event-field="cover_weight">
						<option value=""><?php echo esc_html__( 'ابتدا نوع صحافی را انتخاب کنید', 'tabesh' ); ?></option>
					</select>
				</div>

				<!-- Extras (Checkboxes) -->
				<div class="form-field">
					<label class="field-label">
						<?php echo esc_html__( 'خدمات اضافی', 'tabesh' ); ?>
					</label>
					<div id="slider_extras_container" class="extras-grid">
						<!-- Will be populated dynamically -->
					</div>
				</div>
			</div>
		</div>

		<!-- Step 3: Review & Submit (notes, price, submit) -->
		<div class="slider-form-step" data-step="3">
			<div class="step-inner">
				<h3 class="step-heading">
					<span class="step-icon">✅</span>
					<?php echo esc_html__( 'بررسی نهایی و ثبت سفارش', 'tabesh' ); ?>
				</h3>

				<!-- Order Summary -->
				<div class="order-summary">
					<h4 class="summary-title"><?php echo esc_html__( 'خلاصه سفارش', 'tabesh' ); ?></h4>
					<div id="slider_order_summary" class="summary-content">
						<!-- Will be populated dynamically -->
					</div>
				</div>

				<!-- Notes -->
				<div class="form-field">
					<label for="slider_notes" class="field-label">
						<?php echo esc_html__( 'توضیحات (اختیاری)', 'tabesh' ); ?>
					</label>
					<textarea 
						id="slider_notes" 
						name="notes" 
						class="field-textarea"
						rows="4"
						placeholder="<?php echo esc_attr__( 'توضیحات اضافی برای سفارش...', 'tabesh' ); ?>"
						data-event-field="notes"
					></textarea>
				</div>

				<!-- Price Display -->
				<div class="price-display-box">
					<div class="price-label"><?php echo esc_html__( 'قیمت کل:', 'tabesh' ); ?></div>
					<div id="slider_total_price" class="price-value">
						<?php echo esc_html__( 'محاسبه نشده', 'tabesh' ); ?>
					</div>
					<button type="button" id="slider_calculate_btn" class="btn btn-calculate">
						<span class="btn-icon">🧮</span>
						<?php echo esc_html__( 'محاسبه قیمت', 'tabesh' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Navigation Buttons -->
		<div class="slider-form-nav">
			<button type="button" id="slider_prev_btn" class="btn btn-secondary btn-nav" style="display: none;">
				<span class="btn-icon">◀</span>
				<?php echo esc_html__( 'مرحله قبل', 'tabesh' ); ?>
			</button>
			<button type="button" id="slider_next_btn" class="btn btn-primary btn-nav">
				<?php echo esc_html__( 'مرحله بعد', 'tabesh' ); ?>
				<span class="btn-icon">▶</span>
			</button>
			<button type="button" id="slider_submit_btn" class="btn btn-success btn-submit" style="display: none;">
				<span class="btn-icon">✓</span>
				<?php echo esc_html__( 'ثبت سفارش', 'tabesh' ); ?>
			</button>
		</div>

		<!-- Loading Overlay -->
		<div id="slider_loading_overlay" class="loading-overlay" style="display: none;">
			<div class="loading-spinner"></div>
			<div class="loading-text"><?php echo esc_html__( 'در حال پردازش...', 'tabesh' ); ?></div>
		</div>

		<!-- Message Container -->
		<div id="slider_message_container" class="message-container"></div>

	</form>

	<?php endif; ?>
</div>
