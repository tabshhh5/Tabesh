<?php
/**
 * Admin Order Creator Modal Template
 *
 * Modal for creating orders on behalf of customers
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get settings
$book_sizes          = Tabesh()->get_setting( 'book_sizes', array() );
$paper_types         = Tabesh()->get_setting( 'paper_types', array() );
$print_types         = Tabesh()->get_setting( 'print_types', array() );
$binding_types       = Tabesh()->get_setting( 'binding_types', array() );
$license_types       = Tabesh()->get_setting( 'license_types', array() );
$cover_paper_weights = Tabesh()->get_setting( 'cover_paper_weights', array() );
$lamination_types    = Tabesh()->get_setting( 'lamination_types', array() );
$extras              = Tabesh()->get_setting( 'extras', array() );

// Ensure all are arrays
$book_sizes          = is_array( $book_sizes ) ? $book_sizes : array();
$paper_types         = is_array( $paper_types ) ? $paper_types : array();
$print_types         = is_array( $print_types ) ? $print_types : array();
$binding_types       = is_array( $binding_types ) ? $binding_types : array();
$license_types       = is_array( $license_types ) ? $license_types : array();
$cover_paper_weights = is_array( $cover_paper_weights ) ? $cover_paper_weights : array();
$lamination_types    = is_array( $lamination_types ) ? $lamination_types : array();
$extras              = is_array( $extras ) ? $extras : array();

// Sanitize extras
$extras = array_filter(
	array_map(
		function ( $extra ) {
			$extra = is_scalar( $extra ) ? trim( strval( $extra ) ) : '';
			return ( ! empty( $extra ) && $extra !== 'on' ) ? $extra : null;
		},
		$extras
	)
);

$min_quantity  = Tabesh()->get_setting( 'min_quantity', 10 );
$max_quantity  = Tabesh()->get_setting( 'max_quantity', 10000 );
$quantity_step = Tabesh()->get_setting( 'quantity_step', 10 );
?>

<div id="tabesh-order-modal" class="tabesh-modal" style="display: none;">
	<div class="tabesh-modal-overlay"></div>
	<div class="tabesh-modal-content">
		<div class="tabesh-modal-header">
			<h2><?php echo esc_html__( 'ثبت سفارش جدید', 'tabesh' ); ?></h2>
			<button type="button" class="tabesh-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="tabesh-modal-body">
			<form id="tabesh-admin-order-form">
				<!-- User Selection Section -->
				<div class="tabesh-form-section">
					<h3><?php echo esc_html__( 'انتخاب یا ایجاد مشتری', 'tabesh' ); ?></h3>
					
					<div class="tabesh-user-selection">
						<div class="tabesh-radio-group">
							<label>
								<input type="radio" name="user_selection_type" value="existing" checked>
								<?php echo esc_html__( 'انتخاب کاربر موجود', 'tabesh' ); ?>
							</label>
							<label>
								<input type="radio" name="user_selection_type" value="new">
								<?php echo esc_html__( 'ایجاد کاربر جدید', 'tabesh' ); ?>
							</label>
						</div>

						<!-- Existing User Selection -->
						<div id="existing-user-section" class="user-section">
							<div class="tabesh-form-group">
								<label><?php echo esc_html__( 'جستجوی کاربر:', 'tabesh' ); ?></label>
								<input type="text" id="user-search" class="tabesh-input" placeholder="<?php echo esc_attr__( 'نام، نام کاربری، یا موبایل...', 'tabesh' ); ?>">
								<div id="user-search-results" class="tabesh-search-results"></div>
								<input type="hidden" id="selected-user-id" name="user_id">
								<div id="selected-user-display" class="selected-user-display"></div>
							</div>
						</div>

						<!-- New User Creation -->
						<div id="new-user-section" class="user-section" style="display: none;">
							<div class="tabesh-form-group">
								<label><?php echo esc_html__( 'شماره موبایل:', 'tabesh' ); ?> <span class="required">*</span></label>
								<input type="text" id="new-user-mobile" class="tabesh-input" placeholder="09xxxxxxxxx" pattern="09[0-9]{9}">
								<small><?php echo esc_html__( 'شماره موبایل به عنوان نام کاربری استفاده می‌شود', 'tabesh' ); ?></small>
							</div>
							<div class="tabesh-form-group">
								<label><?php echo esc_html__( 'نام:', 'tabesh' ); ?> <span class="required">*</span></label>
								<input type="text" id="new-user-first-name" class="tabesh-input">
							</div>
							<div class="tabesh-form-group">
								<label><?php echo esc_html__( 'نام خانوادگی:', 'tabesh' ); ?> <span class="required">*</span></label>
								<input type="text" id="new-user-last-name" class="tabesh-input">
							</div>
							<button type="button" id="create-user-btn" class="button button-secondary">
								<?php echo esc_html__( 'ایجاد کاربر', 'tabesh' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Order Details Section -->
				<div class="tabesh-form-section">
					<h3><?php echo esc_html__( 'مشخصات سفارش', 'tabesh' ); ?></h3>

					<div class="tabesh-form-grid">
						<!-- Book Title -->
						<div class="tabesh-form-group tabesh-form-grid-full">
							<label for="book_title"><?php echo esc_html__( 'عنوان کتاب:', 'tabesh' ); ?> <span class="required">*</span></label>
							<input type="text" id="book_title" name="book_title" class="tabesh-input" placeholder="<?php echo esc_attr__( 'نام کتاب را وارد کنید', 'tabesh' ); ?>" required>
						</div>

						<!-- Book Size -->
						<div class="tabesh-form-group">
							<label for="book_size"><?php echo esc_html__( 'قطع کتاب:', 'tabesh' ); ?> <span class="required">*</span></label>
							<select id="book_size" name="book_size" class="tabesh-select" required>
								<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
								<?php foreach ( $book_sizes as $size ) : ?>
									<option value="<?php echo esc_attr( $size ); ?>"><?php echo esc_html( $size ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Paper Type -->
						<div class="tabesh-form-group">
							<label for="paper_type"><?php echo esc_html__( 'نوع کاغذ:', 'tabesh' ); ?> <span class="required">*</span></label>
							<select id="paper_type" name="paper_type" class="tabesh-select" required>
								<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
								<?php foreach ( $paper_types as $type => $weights ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Paper Weight -->
						<div class="tabesh-form-group">
							<label for="paper_weight"><?php echo esc_html__( 'گرماژ کاغذ:', 'tabesh' ); ?> <span class="required">*</span></label>
							<select id="paper_weight" name="paper_weight" class="tabesh-select" required>
								<option value=""><?php echo esc_html__( 'ابتدا نوع کاغذ را انتخاب کنید', 'tabesh' ); ?></option>
							</select>
						</div>

						<!-- Print Type -->
						<div class="tabesh-form-group">
							<label for="print_type"><?php echo esc_html__( 'نوع چاپ:', 'tabesh' ); ?> <span class="required">*</span></label>
							<select id="print_type" name="print_type" class="tabesh-select" required>
								<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
								<?php foreach ( $print_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Page Count (conditional based on print type) -->
						<div class="tabesh-form-group" id="page-count-color-group" style="display: none;">
							<label for="page_count_color"><?php echo esc_html__( 'تعداد صفحات رنگی:', 'tabesh' ); ?> <span class="required">*</span></label>
							<input type="number" id="page_count_color" name="page_count_color" class="tabesh-input" placeholder="تعداد صفحات رنگی" min="1">
						</div>

						<div class="tabesh-form-group" id="page-count-bw-group" style="display: none;">
							<label for="page_count_bw"><?php echo esc_html__( 'تعداد صفحات سیاه و سفید:', 'tabesh' ); ?> <span class="required">*</span></label>
							<input type="number" id="page_count_bw" name="page_count_bw" class="tabesh-input" placeholder="تعداد صفحات سیاه و سفید" min="1">
						</div>

						<div class="tabesh-form-group" id="page-count-total-group">
							<label for="page_count_total"><?php echo esc_html__( 'تعداد کل صفحات:', 'tabesh' ); ?> <span class="required">*</span></label>
							<input type="number" id="page_count_total" name="page_count_total" class="tabesh-input" placeholder="مثال: 200" min="1">
						</div>

						<!-- Quantity -->
						<div class="tabesh-form-group">
							<label for="quantity"><?php echo esc_html__( 'تیراژ:', 'tabesh' ); ?> <span class="required">*</span></label>
							<input type="number" id="quantity" name="quantity" class="tabesh-input" 
									min="<?php echo esc_attr( $min_quantity ); ?>" 
									max="<?php echo esc_attr( $max_quantity ); ?>" 
									step="<?php echo esc_attr( $quantity_step ); ?>" 
									value="<?php echo esc_attr( $min_quantity ); ?>" 
									placeholder="<?php echo esc_attr( $min_quantity ); ?>" required>
						</div>

						<!-- Binding Type -->
						<div class="tabesh-form-group">
							<label for="binding_type"><?php echo esc_html__( 'نوع صحافی:', 'tabesh' ); ?> <span class="required">*</span></label>
							<select id="binding_type" name="binding_type" class="tabesh-select" required>
								<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
								<?php foreach ( $binding_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- License Type -->
						<div class="tabesh-form-group">
							<label for="license_type"><?php echo esc_html__( 'نوع مجوز:', 'tabesh' ); ?> <span class="required">*</span></label>
							<select id="license_type" name="license_type" class="tabesh-select" required>
								<option value=""><?php echo esc_html__( 'انتخاب کنید...', 'tabesh' ); ?></option>
								<?php foreach ( $license_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Cover Paper Weight -->
						<div class="tabesh-form-group">
							<label for="cover_paper_weight"><?php echo esc_html__( 'گرماژ کاغذ جلد:', 'tabesh' ); ?></label>
							<select id="cover_paper_weight" name="cover_paper_weight" class="tabesh-select">
								<?php foreach ( $cover_paper_weights as $weight ) : ?>
									<option value="<?php echo esc_attr( $weight ); ?>"><?php echo esc_html( $weight ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Lamination Type -->
						<div class="tabesh-form-group">
							<label for="lamination_type"><?php echo esc_html__( 'نوع سلفون:', 'tabesh' ); ?></label>
							<select id="lamination_type" name="lamination_type" class="tabesh-select">
								<?php foreach ( $lamination_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Extras -->
						<?php if ( ! empty( $extras ) ) : ?>
						<div class="tabesh-form-group tabesh-form-grid-full">
							<label><?php echo esc_html__( 'آپشن‌های اضافی:', 'tabesh' ); ?></label>
							<div class="tabesh-checkbox-group">
								<?php foreach ( $extras as $extra ) : ?>
									<label class="tabesh-checkbox-label">
										<input type="checkbox" name="extras[]" value="<?php echo esc_attr( $extra ); ?>">
										<span><?php echo esc_html( $extra ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>

						<!-- Notes -->
						<div class="tabesh-form-group tabesh-form-grid-full">
							<label for="notes"><?php echo esc_html__( 'یادداشت:', 'tabesh' ); ?></label>
							<textarea id="notes" name="notes" class="tabesh-textarea" rows="3" placeholder="<?php echo esc_attr__( 'توضیحات اضافی (اختیاری)...', 'tabesh' ); ?>"></textarea>
						</div>
					</div>
				</div>

				<!-- Price Section -->
				<div class="tabesh-form-section">
					<h3><?php echo esc_html__( 'قیمت', 'tabesh' ); ?></h3>
					
					<div class="tabesh-price-display">
						<div class="calculated-price">
							<label><?php echo esc_html__( 'قیمت محاسبه شده:', 'tabesh' ); ?></label>
							<div id="calculated-price-value">-</div>
						</div>
						
						<div class="tabesh-form-group">
							<label for="override_price">
								<input type="checkbox" id="override-price-check">
								<?php echo esc_html__( 'قیمت دلخواه (فقط سوپر ادمین)', 'tabesh' ); ?>
							</label>
							<input type="number" id="override_price" name="override_price" class="tabesh-input" 
									placeholder="<?php echo esc_attr__( 'قیمت دلخواه به ریال', 'tabesh' ); ?>" 
									min="0" step="1000" disabled>
						</div>

						<div class="final-price">
							<label><?php echo esc_html__( 'قیمت نهایی:', 'tabesh' ); ?></label>
							<div id="final-price-value">-</div>
						</div>
					</div>
				</div>

				<div class="tabesh-modal-footer">
					<button type="button" class="button" id="cancel-order-btn">
						<?php echo esc_html__( 'انصراف', 'tabesh' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="calculate-price-btn">
						<?php echo esc_html__( 'محاسبه قیمت', 'tabesh' ); ?>
					</button>
					<button type="submit" class="button button-primary" id="submit-order-btn">
						<?php echo esc_html__( 'ثبت سفارش', 'tabesh' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
