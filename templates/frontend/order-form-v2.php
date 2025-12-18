<?php
/**
 * Order Form V2 Template - Dynamic Dependency Mapping
 *
 * This form uses the V2 pricing engine with matrix-based pricing and
 * dynamic option filtering based on admin-configured restrictions.
 *
 * Key Features:
 * - Step-by-step cascading form
 * - Dynamic option loading based on previous selections
 * - Matrix-based pricing (no legacy coefficients)
 * - Real-time validation and price calculation
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get constraint manager to fetch available book sizes.
// Wrap in try-catch to prevent fatal errors from breaking the entire page.
try {
	$constraint_manager = new Tabesh_Constraint_Manager();
	$available_sizes    = $constraint_manager->get_available_book_sizes();
	
	// Log for debugging if WP_DEBUG is enabled
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Tabesh Order Form V2: Available book sizes count: ' . count( $available_sizes ) );
		if ( empty( $available_sizes ) ) {
			error_log( 'Tabesh Order Form V2: WARNING - No book sizes configured in pricing matrix' );
		}
	}
} catch ( Exception $e ) {
	// Log the error for debugging.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Tabesh Order Form V2 Error: ' . $e->getMessage() );
		error_log( 'Tabesh Order Form V2 Stack trace: ' . $e->getTraceAsString() );
	}
	// Set empty array to show error message in form.
	$available_sizes = array();
}

// Scalar settings.
$min_quantity  = Tabesh()->get_setting( 'min_quantity', 10 );
$max_quantity  = Tabesh()->get_setting( 'max_quantity', 10000 );
$quantity_step = Tabesh()->get_setting( 'quantity_step', 10 );
?>

<div class="tabesh-order-form-v2" dir="rtl">
	<div class="tabesh-form-container">
		<h2 class="tabesh-form-title">
			<?php echo esc_html__( 'محاسبه قیمت چاپ کتاب (نسخه پیشرفته)', 'tabesh' ); ?>
		</h2>

		<p class="tabesh-form-subtitle">
			<?php echo esc_html__( 'فرم زیر به صورت هوشمند گزینه‌های مجاز را بر اساس انتخاب شما نمایش می‌دهد.', 'tabesh' ); ?>
		</p>

		<?php if ( empty( $available_sizes ) ) : ?>
			<div class="tabesh-message error">
				<p><strong><?php echo esc_html__( 'خطا:', 'tabesh' ); ?></strong> <?php echo esc_html__( 'هیچ قطع کتابی در سیستم قیمت‌گذاری پیکربندی نشده است.', 'tabesh' ); ?></p>
				<p><?php echo esc_html__( 'لطفاً ابتدا به', 'tabesh' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=tabesh-product-pricing' ) ); ?>"><?php echo esc_html__( 'تنظیمات قیمت‌گذاری محصول', 'tabesh' ); ?></a> <?php echo esc_html__( 'بروید و ماتریس قیمت را تنظیم کنید.', 'tabesh' ); ?></p>
			</div>
		<?php else : ?>

		<form id="tabesh-order-form-v2" class="tabesh-form-v2">

			<!-- Step 1: Book Title -->
			<div class="tabesh-form-step-v2" data-step="1">
				<h3 class="step-title"><?php echo esc_html__( '۱. عنوان کتاب', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="book_title_v2"><?php echo esc_html__( 'عنوان کتاب (نام روی جلد):', 'tabesh' ); ?> <span class="required">*</span></label>
					<input type="text" id="book_title_v2" name="book_title" required class="tabesh-input-v2" placeholder="<?php echo esc_attr__( 'عنوان کتاب را وارد کنید', 'tabesh' ); ?>">
				</div>
			</div>

			<!-- Step 2: Book Size (Primary Selection) -->
			<div class="tabesh-form-step-v2" data-step="2">
				<h3 class="step-title"><?php echo esc_html__( '۲. قطع کتاب', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="book_size_v2"><?php echo esc_html__( 'انتخاب قطع:', 'tabesh' ); ?> <span class="required">*</span></label>
					<select id="book_size_v2" name="book_size" required class="tabesh-select-v2">
						<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
						<?php foreach ( $available_sizes as $size_info ) : ?>
							<option value="<?php echo esc_attr( $size_info['size'] ); ?>">
								<?php echo esc_html( $size_info['size'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="tabesh-field-hint"><?php echo esc_html__( 'پس از انتخاب قطع، گزینه‌های مجاز نمایش داده می‌شوند.', 'tabesh' ); ?></p>
				</div>
			</div>

			<!-- Step 3: Paper Type & Weight (Dynamic - loaded via AJAX) -->
			<div class="tabesh-form-step-v2" data-step="3" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۳. نوع و گرماژ کاغذ', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="paper_type_v2"><?php echo esc_html__( 'نوع کاغذ:', 'tabesh' ); ?> <span class="required">*</span></label>
					<select id="paper_type_v2" name="paper_type" required class="tabesh-select-v2">
						<option value=""><?php echo esc_html__( 'در حال بارگذاری...', 'tabesh' ); ?></option>
					</select>
				</div>
				<div class="tabesh-form-group">
					<label for="paper_weight_v2"><?php echo esc_html__( 'گرماژ کاغذ:', 'tabesh' ); ?> <span class="required">*</span></label>
					<select id="paper_weight_v2" name="paper_weight" required class="tabesh-select-v2">
						<option value=""><?php echo esc_html__( 'ابتدا نوع کاغذ را انتخاب کنید', 'tabesh' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Step 4: Print Type (Dynamic - loaded based on paper selection) -->
			<div class="tabesh-form-step-v2" data-step="4" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۴. نوع چاپ', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="print_type_v2"><?php echo esc_html__( 'نوع چاپ:', 'tabesh' ); ?> <span class="required">*</span></label>
					<select id="print_type_v2" name="print_type" required class="tabesh-select-v2">
						<option value=""><?php echo esc_html__( 'در حال بارگذاری...', 'tabesh' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Step 5: Page Count -->
			<div class="tabesh-form-step-v2" data-step="5" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۵. تعداد صفحات', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="page_count_v2"><?php echo esc_html__( 'تعداد صفحات:', 'tabesh' ); ?> <span class="required">*</span></label>
					<input type="number" id="page_count_v2" name="page_count" min="1" value="100" required class="tabesh-input-v2">
					<p class="tabesh-field-hint"><?php echo esc_html__( 'تعداد کل صفحات کتاب (متن + جلد)', 'tabesh' ); ?></p>
				</div>
			</div>

			<!-- Step 6: Quantity (Circulation) -->
			<div class="tabesh-form-step-v2" data-step="6" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۶. تیراژ', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="quantity_v2">
						<?php
						/* translators: %d: minimum quantity */
						echo esc_html( sprintf( __( 'تعداد (حداقل %d):', 'tabesh' ), $min_quantity ) );
						?>
						<span class="required">*</span>
					</label>
					<input 
						type="number" 
						id="quantity_v2" 
						name="quantity" 
						min="<?php echo esc_attr( $min_quantity ); ?>" 
						max="<?php echo esc_attr( $max_quantity ); ?>" 
						step="<?php echo esc_attr( $quantity_step ); ?>" 
						value="<?php echo esc_attr( $min_quantity ); ?>" 
						required 
						class="tabesh-input-v2"
					>
				</div>
			</div>

			<!-- Step 7: Binding Type (Dynamic - loaded via AJAX) -->
			<div class="tabesh-form-step-v2" data-step="7" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۷. نوع صحافی', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="binding_type_v2"><?php echo esc_html__( 'نوع صحافی:', 'tabesh' ); ?> <span class="required">*</span></label>
					<select id="binding_type_v2" name="binding_type" required class="tabesh-select-v2">
						<option value=""><?php echo esc_html__( 'در حال بارگذاری...', 'tabesh' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Step 8: Cover Weight (Dynamic - loaded based on binding selection) -->
			<div class="tabesh-form-step-v2" data-step="8" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۸. گرماژ جلد', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="cover_weight_v2"><?php echo esc_html__( 'گرماژ کاغذ جلد:', 'tabesh' ); ?> <span class="required">*</span></label>
					<select id="cover_weight_v2" name="cover_weight" required class="tabesh-select-v2">
						<option value=""><?php echo esc_html__( 'در حال بارگذاری...', 'tabesh' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Step 9: Extras (Dynamic - loaded based on binding selection) -->
			<div class="tabesh-form-step-v2" data-step="9" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۹. خدمات اضافی', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<div id="extras_container_v2">
						<p class="tabesh-loading"><?php echo esc_html__( 'در حال بارگذاری خدمات اضافی...', 'tabesh' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Step 10: Notes -->
			<div class="tabesh-form-step-v2" data-step="10" style="display: none;">
				<h3 class="step-title"><?php echo esc_html__( '۱۰. توضیحات', 'tabesh' ); ?></h3>
				<div class="tabesh-form-group">
					<label for="notes_v2"><?php echo esc_html__( 'توضیحات (اختیاری):', 'tabesh' ); ?></label>
					<textarea id="notes_v2" name="notes" rows="4" class="tabesh-textarea-v2" placeholder="<?php echo esc_attr__( 'هر توضیح یا درخواست خاصی که دارید را اینجا بنویسید...', 'tabesh' ); ?>"></textarea>
				</div>
			</div>

			<!-- Loading indicator -->
			<div id="form-loading-v2" class="tabesh-loading-overlay" style="display: none;">
				<div class="spinner"></div>
				<p><?php echo esc_html__( 'در حال بارگذاری...', 'tabesh' ); ?></p>
			</div>

			<!-- Validation messages -->
			<div id="form-messages-v2" class="tabesh-form-messages"></div>

		</form>

		<!-- Price Display (always visible, updates in real-time) -->
		<div id="tabesh-price-display-v2" class="tabesh-price-display-v2">
			<h3><?php echo esc_html__( 'پیش‌فاکتور', 'tabesh' ); ?></h3>
			<div class="price-info">
				<p class="info-text"><?php echo esc_html__( 'قیمت محاسبه شده بر اساس ماتریس قیمت‌گذاری V2', 'tabesh' ); ?></p>
			</div>
			<div class="tabesh-price-details-v2">
				<div class="price-row">
					<span><?php echo esc_html__( 'قیمت هر جلد:', 'tabesh' ); ?></span>
					<span id="price-per-book-v2" class="price-value">-</span>
				</div>
				<div class="price-row">
					<span><?php echo esc_html__( 'تعداد:', 'tabesh' ); ?></span>
					<span id="price-quantity-v2" class="price-value">-</span>
				</div>
				<div class="price-row">
					<span><?php echo esc_html__( 'جمع کل:', 'tabesh' ); ?></span>
					<span id="price-total-v2" class="price-value total">-</span>
				</div>
			</div>
			<div class="price-breakdown-v2" id="price-breakdown-v2" style="display: none;">
				<h4><?php echo esc_html__( 'جزئیات قیمت', 'tabesh' ); ?></h4>
				<div id="breakdown-content-v2"></div>
			</div>
			<div class="price-actions">
				<button type="button" id="calculate-price-v2" class="tabesh-btn-v2 tabesh-btn-primary" disabled>
					<?php echo esc_html__( 'محاسبه قیمت', 'tabesh' ); ?>
				</button>
				<button type="button" id="submit-order-v2" class="tabesh-btn-v2 tabesh-btn-success" style="display: none;">
					<?php echo esc_html__( 'ثبت سفارش', 'tabesh' ); ?>
				</button>
			</div>
		</div>

		<?php endif; ?>
	</div>
</div>
