<?php
/**
 * Order Form V2 Modern Template - Step-by-Step Wizard
 *
 * Modern single-page wizard with next/previous navigation
 * Professional UI/UX with smooth transitions
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

	// Log for debugging if WP_DEBUG is enabled
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Tabesh Order Form V2 Modern: Available book sizes count: ' . count( $available_sizes ) );
		if ( empty( $available_sizes ) ) {
			error_log( 'Tabesh Order Form V2 Modern: WARNING - No book sizes configured in pricing matrix' );
		}
	}
} catch ( Exception $e ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Tabesh Order Form V2 Modern Error: ' . $e->getMessage() );
	}
	$available_sizes = array();
}

// Scalar settings.
$min_quantity  = Tabesh()->get_setting( 'min_quantity', 10 );
$max_quantity  = Tabesh()->get_setting( 'max_quantity', 10000 );
$quantity_step = Tabesh()->get_setting( 'quantity_step', 10 );
?>

<div class="tabesh-wizard-container" dir="rtl">
	<?php if ( empty( $available_sizes ) ) : ?>
		<div class="tabesh-wizard-error">
			<div class="error-icon">⚠️</div>
			<h3><?php echo esc_html__( 'خطا در بارگذاری فرم', 'tabesh' ); ?></h3>
			<p><?php echo esc_html__( 'هیچ قطع کتابی در سیستم قیمت‌گذاری پیکربندی نشده است.', 'tabesh' ); ?></p>
			<p><?php echo esc_html__( 'لطفاً ابتدا به', 'tabesh' ); ?> 
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tabesh-product-pricing' ) ); ?>" class="error-link">
					<?php echo esc_html__( 'تنظیمات قیمت‌گذاری محصول', 'tabesh' ); ?>
				</a> 
				<?php echo esc_html__( 'بروید و ماتریس قیمت را تنظیم کنید.', 'tabesh' ); ?>
			</p>
		</div>
	<?php else : ?>

	<!-- Progress Bar -->
	<div class="wizard-progress">
		<div class="progress-bar">
			<div class="progress-fill" id="progressBar"></div>
		</div>
		<div class="progress-steps">
			<div class="progress-step active" data-step="1">
				<div class="step-circle">1</div>
				<span class="step-label"><?php echo esc_html__( 'عنوان کتاب', 'tabesh' ); ?></span>
			</div>
			<div class="progress-step" data-step="2">
				<div class="step-circle">2</div>
				<span class="step-label"><?php echo esc_html__( 'قطع و مشخصات', 'tabesh' ); ?></span>
			</div>
			<div class="progress-step" data-step="3">
				<div class="step-circle">3</div>
				<span class="step-label"><?php echo esc_html__( 'صحافی و جلد', 'tabesh' ); ?></span>
			</div>
			<div class="progress-step" data-step="4">
				<div class="step-circle">4</div>
				<span class="step-label"><?php echo esc_html__( 'تکمیل سفارش', 'tabesh' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Wizard Form -->
	<div class="wizard-form-wrapper">
		<form id="tabesh-wizard-form" class="wizard-form">

			<!-- Step 1: Book Title & Basic Info -->
			<div class="wizard-step active" data-step="1">
				<div class="step-header">
					<h2 class="step-title">
						<span class="step-icon">📖</span>
						<?php echo esc_html__( 'اطلاعات اولیه کتاب', 'tabesh' ); ?>
					</h2>
					<p class="step-description">
						<?php echo esc_html__( 'عنوان کتاب و قطع مورد نظر خود را انتخاب کنید', 'tabesh' ); ?>
					</p>
				</div>
				<div class="step-content">
					<div class="form-group">
						<label for="book_title_wizard" class="form-label">
							<?php echo esc_html__( 'عنوان کتاب', 'tabesh' ); ?>
							<span class="required">*</span>
						</label>
						<input 
							type="text" 
							id="book_title_wizard" 
							name="book_title" 
							class="form-control"
							placeholder="<?php echo esc_attr__( 'نام کتابی که می‌خواهید چاپ کنید...', 'tabesh' ); ?>"
							required
						>
						<span class="form-hint"><?php echo esc_html__( 'این عنوان روی جلد کتاب چاپ می‌شود', 'tabesh' ); ?></span>
					</div>

					<div class="form-group">
						<label for="book_size_wizard" class="form-label">
							<?php echo esc_html__( 'قطع کتاب', 'tabesh' ); ?>
							<span class="required">*</span>
						</label>
						<div class="book-size-grid">
							<?php foreach ( $available_sizes as $size_info ) : ?>
								<?php if ( $size_info['enabled'] ) : ?>
								<label class="size-option">
									<input 
										type="radio" 
										name="book_size" 
										value="<?php echo esc_attr( $size_info['size'] ); ?>"
										data-size="<?php echo esc_attr( $size_info['size'] ); ?>"
										required
									>
									<span class="size-card">
										<span class="size-name"><?php echo esc_html( $size_info['size'] ); ?></span>
										<span class="size-info">
											<?php echo esc_html( $size_info['paper_count'] ); ?> نوع کاغذ
										</span>
									</span>
								</label>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Step 2: Paper & Print Specifications -->
			<div class="wizard-step" data-step="2">
				<div class="step-header">
					<h2 class="step-title">
						<span class="step-icon">📄</span>
						<?php echo esc_html__( 'مشخصات کاغذ و چاپ', 'tabesh' ); ?>
					</h2>
					<p class="step-description">
						<?php echo esc_html__( 'نوع کاغذ، گرماژ و نوع چاپ را انتخاب کنید', 'tabesh' ); ?>
					</p>
				</div>
				<div class="step-content">
					<div class="form-row">
						<div class="form-group">
							<label for="paper_type_wizard" class="form-label">
								<?php echo esc_html__( 'نوع کاغذ', 'tabesh' ); ?>
								<span class="required">*</span>
							</label>
							<select id="paper_type_wizard" name="paper_type" class="form-control" required>
								<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
							</select>
						</div>

						<div class="form-group">
							<label for="paper_weight_wizard" class="form-label">
								<?php echo esc_html__( 'گرماژ کاغذ', 'tabesh' ); ?>
								<span class="required">*</span>
							</label>
							<select id="paper_weight_wizard" name="paper_weight" class="form-control" required>
								<option value=""><?php echo esc_html__( 'ابتدا نوع کاغذ را انتخاب کنید', 'tabesh' ); ?></option>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label class="form-label">
							<?php echo esc_html__( 'نوع چاپ', 'tabesh' ); ?>
							<span class="required">*</span>
						</label>
						<div class="print-type-options">
							<label class="print-option">
								<input type="radio" name="print_type" value="bw" required>
								<span class="print-card">
									<span class="print-icon">⚫</span>
									<span class="print-name"><?php echo esc_html__( 'سیاه و سفید', 'tabesh' ); ?></span>
								</span>
							</label>
							<label class="print-option">
								<input type="radio" name="print_type" value="color" required>
								<span class="print-card">
									<span class="print-icon">🎨</span>
									<span class="print-name"><?php echo esc_html__( 'رنگی', 'tabesh' ); ?></span>
								</span>
							</label>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group">
							<label for="page_count_wizard" class="form-label">
								<?php echo esc_html__( 'تعداد صفحات', 'tabesh' ); ?>
								<span class="required">*</span>
							</label>
							<input 
								type="number" 
								id="page_count_wizard" 
								name="page_count" 
								class="form-control"
								min="1"
								value="100"
								required
							>
							<span class="form-hint"><?php echo esc_html__( 'تعداد کل صفحات کتاب', 'tabesh' ); ?></span>
						</div>

						<div class="form-group">
							<label for="quantity_wizard" class="form-label">
								<?php echo esc_html__( 'تیراژ (تعداد)', 'tabesh' ); ?>
								<span class="required">*</span>
							</label>
							<input 
								type="number" 
								id="quantity_wizard" 
								name="quantity" 
								class="form-control"
								min="<?php echo esc_attr( $min_quantity ); ?>"
								max="<?php echo esc_attr( $max_quantity ); ?>"
								step="<?php echo esc_attr( $quantity_step ); ?>"
								value="<?php echo esc_attr( $min_quantity ); ?>"
								required
							>
							<span class="form-hint">
								<?php echo esc_html( sprintf( __( 'حداقل: %d', 'tabesh' ), $min_quantity ) ); ?>
							</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Step 3: Binding & Cover -->
			<div class="wizard-step" data-step="3">
				<div class="step-header">
					<h2 class="step-title">
						<span class="step-icon">📚</span>
						<?php echo esc_html__( 'صحافی و جلد', 'tabesh' ); ?>
					</h2>
					<p class="step-description">
						<?php echo esc_html__( 'نوع صحافی، گرماژ جلد و خدمات اضافی را انتخاب کنید', 'tabesh' ); ?>
					</p>
				</div>
				<div class="step-content">
					<div class="form-group">
						<label for="binding_type_wizard" class="form-label">
							<?php echo esc_html__( 'نوع صحافی', 'tabesh' ); ?>
							<span class="required">*</span>
						</label>
						<select id="binding_type_wizard" name="binding_type" class="form-control" required>
							<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
						</select>
					</div>

					<div class="form-group">
						<label for="cover_weight_wizard" class="form-label">
							<?php echo esc_html__( 'گرماژ کاغذ جلد', 'tabesh' ); ?>
							<span class="required">*</span>
						</label>
						<select id="cover_weight_wizard" name="cover_weight" class="form-control" required>
							<option value=""><?php echo esc_html__( 'در حال بارگذاری...', 'tabesh' ); ?></option>
						</select>
					</div>

					<div class="form-group">
						<label class="form-label">
							<?php echo esc_html__( 'خدمات اضافی', 'tabesh' ); ?>
						</label>
						<div id="extras_container_wizard" class="extras-grid">
							<p class="loading-text"><?php echo esc_html__( 'در حال بارگذاری...', 'tabesh' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<!-- Step 4: Review & Submit -->
			<div class="wizard-step" data-step="4">
				<div class="step-header">
					<h2 class="step-title">
						<span class="step-icon">✅</span>
						<?php echo esc_html__( 'بررسی نهایی و ثبت سفارش', 'tabesh' ); ?>
					</h2>
					<p class="step-description">
						<?php echo esc_html__( 'مشخصات سفارش خود را بررسی کنید', 'tabesh' ); ?>
					</p>
				</div>
				<div class="step-content">
					<!-- Price Summary -->
					<div class="price-summary">
						<h3 class="summary-title"><?php echo esc_html__( 'خلاصه قیمت', 'tabesh' ); ?></h3>
						<div class="summary-content">
							<div class="summary-row">
								<span class="summary-label"><?php echo esc_html__( 'قیمت هر جلد:', 'tabesh' ); ?></span>
								<span class="summary-value" id="price_per_book">-</span>
							</div>
							<div class="summary-row">
								<span class="summary-label"><?php echo esc_html__( 'تعداد:', 'tabesh' ); ?></span>
								<span class="summary-value" id="price_quantity">-</span>
							</div>
							<div class="summary-row total">
								<span class="summary-label"><?php echo esc_html__( 'جمع کل:', 'tabesh' ); ?></span>
								<span class="summary-value" id="price_total">-</span>
							</div>
						</div>
						<button type="button" id="calculate_price_btn" class="btn btn-secondary btn-block">
							<?php echo esc_html__( 'محاسبه قیمت', 'tabesh' ); ?>
						</button>
					</div>

					<!-- Order Summary -->
					<div class="order-summary">
						<h3 class="summary-title"><?php echo esc_html__( 'جزئیات سفارش', 'tabesh' ); ?></h3>
						<div id="order_review" class="review-content"></div>
					</div>

					<!-- Notes -->
					<div class="form-group">
						<label for="notes_wizard" class="form-label">
							<?php echo esc_html__( 'توضیحات (اختیاری)', 'tabesh' ); ?>
						</label>
						<textarea 
							id="notes_wizard" 
							name="notes" 
							class="form-control"
							rows="4"
							placeholder="<?php echo esc_attr__( 'توضیحات یا درخواست‌های خاص خود را اینجا بنویسید...', 'tabesh' ); ?>"
						></textarea>
					</div>
				</div>
			</div>

		</form>

		<!-- Navigation Buttons -->
		<div class="wizard-navigation">
			<button type="button" id="prevBtn" class="btn btn-secondary" style="display: none;">
				<span class="btn-icon">←</span>
				<?php echo esc_html__( 'قبلی', 'tabesh' ); ?>
			</button>
			<button type="button" id="nextBtn" class="btn btn-primary">
				<?php echo esc_html__( 'بعدی', 'tabesh' ); ?>
				<span class="btn-icon">→</span>
			</button>
			<button type="button" id="submitBtn" class="btn btn-success" style="display: none;">
				<span class="btn-icon">✓</span>
				<?php echo esc_html__( 'ثبت سفارش', 'tabesh' ); ?>
			</button>
		</div>
	</div>

	<!-- Loading Overlay -->
	<div id="wizard-loading" class="wizard-loading" style="display: none;">
		<div class="loading-spinner"></div>
		<p class="loading-text"><?php echo esc_html__( 'در حال پردازش...', 'tabesh' ); ?></p>
	</div>

	<!-- Messages -->
	<div id="wizard-messages" class="wizard-messages"></div>

	<?php endif; ?>
</div>
