<?php
/**
 * Admin Order Form Template
 * 
 * قالب فرم ثبت سفارش ویژه مدیر
 * Template for admin order form shortcode with modern UI
 *
 * @package Tabesh
 * @since 1.0.3
 */

// Exit if accessed directly / در صورت دسترسی مستقیم خارج شود
if (!defined('ABSPATH')) {
    exit;
}

// Get settings / دریافت تنظیمات
$book_sizes          = Tabesh()->get_setting('book_sizes', array());
$paper_types         = Tabesh()->get_setting('paper_types', array());
$print_types         = Tabesh()->get_setting('print_types', array());
$binding_types       = Tabesh()->get_setting('binding_types', array());
$license_types       = Tabesh()->get_setting('license_types', array());
$cover_paper_weights = Tabesh()->get_setting('cover_paper_weights', array());
$lamination_types    = Tabesh()->get_setting('lamination_types', array());
$extras              = Tabesh()->get_setting('extras', array());

// Ensure all are arrays / اطمینان از آرایه بودن
$book_sizes          = is_array($book_sizes) ? $book_sizes : array();
$paper_types         = is_array($paper_types) ? $paper_types : array();
$print_types         = is_array($print_types) ? $print_types : array();
$binding_types       = is_array($binding_types) ? $binding_types : array();
$license_types       = is_array($license_types) ? $license_types : array();
$cover_paper_weights = is_array($cover_paper_weights) ? $cover_paper_weights : array();
$lamination_types    = is_array($lamination_types) ? $lamination_types : array();
$extras              = is_array($extras) ? $extras : array();

// Sanitize extras / پاکسازی آپشن‌های اضافی
$extras = array_filter(
    array_map(
        function ($extra) {
            $extra = is_scalar($extra) ? trim(strval($extra)) : '';
            return (!empty($extra) && $extra !== 'on') ? $extra : null;
        },
        $extras
    )
);

// Get quantity settings / دریافت تنظیمات تیراژ
$min_quantity  = Tabesh()->get_setting('min_quantity', 10);
$max_quantity  = Tabesh()->get_setting('max_quantity', 10000);
$quantity_step = Tabesh()->get_setting('quantity_step', 10);

// Get title from attributes / دریافت عنوان از پارامترها
$form_title = isset($atts['title']) ? $atts['title'] : __('ثبت سفارش جدید', 'tabesh');
?>

<div class="tabesh-admin-order-form-wrapper" dir="rtl">
    <div class="tabesh-aof-container">
        <!-- 
            Header / هدر
            Compact header with title and subtitle inline
            هدر فشرده با عنوان و زیرعنوان خطی
        -->
        <div class="tabesh-aof-header">
            <h2 class="tabesh-aof-title">
                <span class="dashicons dashicons-cart"></span>
                <?php echo esc_html($form_title); ?>
            </h2>
            <p class="tabesh-aof-subtitle"><?php echo esc_html__('ثبت سفارش به نام مشتری', 'tabesh'); ?></p>
        </div>

        <!-- 
            Form / فرم
            Horizontal layout form with compact sections
            فرم با چیدمان افقی و بخش‌های فشرده
        -->
        <form id="tabesh-admin-order-form-main" class="tabesh-aof-form">
            
            <!-- 
                Section 1: Customer Selection / بخش ۱: انتخاب مشتری
                Inline customer selection with search and create options
                انتخاب مشتری خطی با گزینه‌های جستجو و ایجاد
            -->
            <div class="tabesh-aof-section">
                <h3 class="tabesh-aof-section-title">
                    <span class="section-number">۱</span>
                    <?php echo esc_html__('مشتری', 'tabesh'); ?>
                </h3>
                
                <div class="tabesh-aof-customer-selection">
                    <!-- Selection Type - Inline radio buttons / نوع انتخاب - رادیو خطی -->
                    <div class="tabesh-aof-radio-group">
                        <label class="tabesh-aof-radio">
                            <input type="radio" name="customer_type" value="existing" checked>
                            <span class="radio-custom"></span>
                            <span class="radio-label"><?php echo esc_html__('کاربر موجود', 'tabesh'); ?></span>
                        </label>
                        <label class="tabesh-aof-radio">
                            <input type="radio" name="customer_type" value="new">
                            <span class="radio-custom"></span>
                            <span class="radio-label"><?php echo esc_html__('کاربر جدید', 'tabesh'); ?></span>
                        </label>
                    </div>

                    <!-- Existing User Search - Compact inline search / جستجوی کاربر - جستجوی خطی فشرده -->
                    <div id="aof-existing-user-section" class="tabesh-aof-subsection">
                        <div class="tabesh-aof-form-group">
                            <label for="aof-user-search"><?php echo esc_html__('جستجو:', 'tabesh'); ?></label>
                            <div class="tabesh-aof-search-wrapper">
                                <input type="text" 
                                       id="aof-user-search" 
                                       class="tabesh-aof-input" 
                                       placeholder="<?php echo esc_attr__('نام، موبایل یا ایمیل...', 'tabesh'); ?>"
                                       autocomplete="off">
                                <span class="search-icon dashicons dashicons-search"></span>
                            </div>
                            <div id="aof-user-search-results" class="tabesh-aof-search-results"></div>
                            <input type="hidden" id="aof-selected-user-id" name="user_id" value="">
                            <div id="aof-selected-user-display" class="tabesh-aof-selected-user"></div>
                        </div>
                    </div>

                    <!-- New User Creation - Horizontal form / ایجاد کاربر جدید - فرم افقی -->
                    <div id="aof-new-user-section" class="tabesh-aof-subsection" style="display: none;">
                        <div class="tabesh-aof-form-row">
                            <div class="tabesh-aof-form-group">
                                <label for="aof-new-mobile">
                                    <?php echo esc_html__('موبایل:', 'tabesh'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="aof-new-mobile" 
                                       class="tabesh-aof-input" 
                                       placeholder="09xxxxxxxxx" 
                                       pattern="09[0-9]{9}"
                                       dir="ltr">
                            </div>
                            <div class="tabesh-aof-form-group">
                                <label for="aof-new-first-name">
                                    <?php echo esc_html__('نام:', 'tabesh'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" id="aof-new-first-name" class="tabesh-aof-input">
                            </div>
                            <div class="tabesh-aof-form-group">
                                <label for="aof-new-last-name">
                                    <?php echo esc_html__('نام خانوادگی:', 'tabesh'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" id="aof-new-last-name" class="tabesh-aof-input">
                            </div>
                        </div>
                        <button type="button" id="aof-create-user-btn" class="tabesh-aof-btn tabesh-aof-btn-secondary">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php echo esc_html__('ایجاد', 'tabesh'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 
                Section 2: Order Details / بخش ۲: مشخصات سفارش
                Horizontal multi-column grid for order fields
                گرید چند ستونی افقی برای فیلدهای سفارش
            -->
            <div class="tabesh-aof-section">
                <h3 class="tabesh-aof-section-title">
                    <span class="section-number">۲</span>
                    <?php echo esc_html__('مشخصات سفارش', 'tabesh'); ?>
                </h3>

                <!-- 
                    Book Title - Full width / عنوان کتاب - تمام عرض
                    Single field spanning all columns
                -->
                <div class="tabesh-aof-form-row">
                    <div class="tabesh-aof-form-group tabesh-aof-full-width">
                        <label for="aof-book-title">
                            <?php echo esc_html__('عنوان کتاب:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="aof-book-title" 
                               name="book_title" 
                               class="tabesh-aof-input" 
                               required
                               placeholder="<?php echo esc_attr__('عنوان کتاب', 'tabesh'); ?>">
                    </div>
                </div>

                <!-- 
                    Primary order fields - Horizontal row / فیلدهای اصلی - ردیف افقی
                    Book size, paper type, paper weight, print type in one row
                -->
                <div class="tabesh-aof-form-row">
                    <!-- Book Size / قطع کتاب -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-book-size">
                            <?php echo esc_html__('قطع:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-book-size" name="book_size" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                            <?php foreach ($book_sizes as $size) : ?>
                                <option value="<?php echo esc_attr($size); ?>"><?php echo esc_html($size); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Paper Type / نوع کاغذ -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-paper-type">
                            <?php echo esc_html__('نوع کاغذ:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-paper-type" name="paper_type" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                            <?php foreach ($paper_types as $paper_type_key => $weights) : ?>
                                <option value="<?php echo esc_attr($paper_type_key); ?>"><?php echo esc_html($paper_type_key); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Paper Weight / گرماژ کاغذ -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-paper-weight">
                            <?php echo esc_html__('گرماژ:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-paper-weight" name="paper_weight" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب گرماژ...', 'tabesh'); ?></option>
                        </select>
                    </div>

                    <!-- Print Type / نوع چاپ -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-print-type">
                            <?php echo esc_html__('نوع چاپ:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-print-type" name="print_type" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                            <?php foreach ($print_types as $print_type_item) : ?>
                                <option value="<?php echo esc_attr($print_type_item); ?>"><?php echo esc_html($print_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Quantity / تیراژ -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-quantity">
                            <?php echo esc_html__('تیراژ:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="aof-quantity" 
                               name="quantity" 
                               class="tabesh-aof-input" 
                               min="<?php echo esc_attr($min_quantity); ?>" 
                               max="<?php echo esc_attr($max_quantity); ?>" 
                               step="<?php echo esc_attr($quantity_step); ?>" 
                               value="<?php echo esc_attr($min_quantity); ?>" 
                               required>
                    </div>

                    <!-- Binding Type / نوع صحافی -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-binding-type">
                            <?php echo esc_html__('صحافی:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-binding-type" name="binding_type" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                            <?php foreach ($binding_types as $binding_type_item) : ?>
                                <option value="<?php echo esc_attr($binding_type_item); ?>"><?php echo esc_html($binding_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 
                    Page Count Section / بخش تعداد صفحات
                    Dynamic fields based on print type
                -->
                <div class="tabesh-aof-form-row">
                    <div class="tabesh-aof-form-group" id="aof-page-count-color-group" style="display: none;">
                        <label for="aof-page-count-color">
                            <?php echo esc_html__('صفحات رنگی:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="aof-page-count-color" 
                               name="page_count_color" 
                               class="tabesh-aof-input" 
                               min="0" 
                               value="0">
                    </div>

                    <div class="tabesh-aof-form-group" id="aof-page-count-bw-group" style="display: none;">
                        <label for="aof-page-count-bw">
                            <?php echo esc_html__('صفحات سیاه و سفید:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="aof-page-count-bw" 
                               name="page_count_bw" 
                               class="tabesh-aof-input" 
                               min="0" 
                               value="0">
                    </div>

                    <div class="tabesh-aof-form-group" id="aof-page-count-total-group">
                        <label for="aof-page-count-total">
                            <?php echo esc_html__('تعداد صفحات:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="aof-page-count-total" 
                               name="page_count_total" 
                               class="tabesh-aof-input" 
                               min="1">
                    </div>

                    <!-- License Type / نوع مجوز -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-license-type">
                            <?php echo esc_html__('مجوز:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-license-type" name="license_type" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                            <?php foreach ($license_types as $license_type_item) : ?>
                                <option value="<?php echo esc_attr($license_type_item); ?>"><?php echo esc_html($license_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cover Paper Weight / گرماژ کاغذ جلد -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-cover-paper-weight"><?php echo esc_html__('گرماژ جلد:', 'tabesh'); ?></label>
                        <select id="aof-cover-paper-weight" name="cover_paper_weight" class="tabesh-aof-select">
                            <?php foreach ($cover_paper_weights as $weight) : ?>
                                <option value="<?php echo esc_attr($weight); ?>"><?php echo esc_html($weight); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lamination Type / نوع سلفون -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-lamination-type"><?php echo esc_html__('سلفون:', 'tabesh'); ?></label>
                        <select id="aof-lamination-type" name="lamination_type" class="tabesh-aof-select">
                            <?php foreach ($lamination_types as $lamination_type_item) : ?>
                                <option value="<?php echo esc_attr($lamination_type_item); ?>"><?php echo esc_html($lamination_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 
                    Extras - Horizontal checkbox group / آپشن‌های اضافی - چک‌باکس افقی
                    Inline checkboxes for extra options
                -->
                <?php if (!empty($extras)) : ?>
                <div class="tabesh-aof-form-row">
                    <div class="tabesh-aof-form-group tabesh-aof-full-width">
                        <label><?php echo esc_html__('آپشن‌ها:', 'tabesh'); ?></label>
                        <div class="tabesh-aof-checkbox-group">
                            <?php foreach ($extras as $extra) : ?>
                                <label class="tabesh-aof-checkbox">
                                    <input type="checkbox" name="extras[]" value="<?php echo esc_attr($extra); ?>">
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-label"><?php echo esc_html($extra); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notes - Compact textarea / یادداشت - تکست‌اریای فشرده -->
                <div class="tabesh-aof-form-row">
                    <div class="tabesh-aof-form-group tabesh-aof-full-width">
                        <label for="aof-notes"><?php echo esc_html__('یادداشت:', 'tabesh'); ?></label>
                        <textarea id="aof-notes" 
                                  name="notes" 
                                  class="tabesh-aof-textarea" 
                                  rows="2" 
                                  placeholder="<?php echo esc_attr__('توضیحات (اختیاری)...', 'tabesh'); ?>"></textarea>
                    </div>
                </div>
            </div>

            <!-- 
                Section 3: Price / بخش ۳: قیمت
                Inline price display and override option
                نمایش خطی قیمت و گزینه قیمت دلخواه
            -->
            <div class="tabesh-aof-section tabesh-aof-price-section">
                <h3 class="tabesh-aof-section-title">
                    <span class="section-number">۳</span>
                    <?php echo esc_html__('قیمت', 'tabesh'); ?>
                </h3>

                <div class="tabesh-aof-price-display">
                    <!-- Calculated price - inline / قیمت محاسبه شده - خطی -->
                    <div class="tabesh-aof-price-row">
                        <span class="price-label"><?php echo esc_html__('محاسبه:', 'tabesh'); ?></span>
                        <span class="price-value" id="aof-calculated-price">-</span>
                    </div>

                    <!-- Final price - inline / قیمت نهایی - خطی -->
                    <div class="tabesh-aof-price-row tabesh-aof-final-price">
                        <span class="price-label"><?php echo esc_html__('نهایی:', 'tabesh'); ?></span>
                        <span class="price-value" id="aof-final-price">-</span>
                    </div>

                    <!-- Override price - inline / قیمت دلخواه - خطی -->
                    <div class="tabesh-aof-override-price">
                        <label class="tabesh-aof-checkbox">
                            <input type="checkbox" id="aof-override-price-check">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label"><?php echo esc_html__('قیمت دلخواه', 'tabesh'); ?></span>
                        </label>
                        <input type="number" 
                               id="aof-override-price" 
                               name="override_price" 
                               class="tabesh-aof-input" 
                               placeholder="<?php echo esc_attr__('ریال', 'tabesh'); ?>" 
                               min="0" 
                               step="1000" 
                               disabled>
                    </div>
                </div>
            </div>

            <!-- 
                Form Actions / دکمه‌های فرم
                Inline action buttons
                دکمه‌های عملیات خطی
            -->
            <div class="tabesh-aof-actions">
                <button type="button" id="aof-calculate-btn" class="tabesh-aof-btn tabesh-aof-btn-secondary">
                    <span class="dashicons dashicons-calculator"></span>
                    <?php echo esc_html__('محاسبه', 'tabesh'); ?>
                </button>
                <button type="submit" id="aof-submit-btn" class="tabesh-aof-btn tabesh-aof-btn-primary">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html__('ثبت سفارش', 'tabesh'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
