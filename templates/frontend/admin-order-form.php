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
        <!-- Header / هدر -->
        <div class="tabesh-aof-header">
            <h2 class="tabesh-aof-title">
                <span class="dashicons dashicons-cart"></span>
                <?php echo esc_html($form_title); ?>
            </h2>
            <p class="tabesh-aof-subtitle"><?php echo esc_html__('ثبت سفارش به نام مشتری', 'tabesh'); ?></p>
        </div>

        <!-- Form / فرم -->
        <form id="tabesh-admin-order-form-main" class="tabesh-aof-form">
            
            <!-- Section 1: Customer Selection / بخش ۱: انتخاب مشتری -->
            <div class="tabesh-aof-section">
                <h3 class="tabesh-aof-section-title">
                    <span class="section-number">۱</span>
                    <?php echo esc_html__('انتخاب یا ایجاد مشتری', 'tabesh'); ?>
                </h3>
                
                <div class="tabesh-aof-customer-selection">
                    <!-- Selection Type / نوع انتخاب -->
                    <div class="tabesh-aof-radio-group">
                        <label class="tabesh-aof-radio">
                            <input type="radio" name="customer_type" value="existing" checked>
                            <span class="radio-custom"></span>
                            <span class="radio-label"><?php echo esc_html__('انتخاب کاربر موجود', 'tabesh'); ?></span>
                        </label>
                        <label class="tabesh-aof-radio">
                            <input type="radio" name="customer_type" value="new">
                            <span class="radio-custom"></span>
                            <span class="radio-label"><?php echo esc_html__('ایجاد کاربر جدید', 'tabesh'); ?></span>
                        </label>
                    </div>

                    <!-- Existing User Search / جستجوی کاربر موجود -->
                    <div id="aof-existing-user-section" class="tabesh-aof-subsection">
                        <div class="tabesh-aof-form-group">
                            <label for="aof-user-search"><?php echo esc_html__('جستجوی مشتری:', 'tabesh'); ?></label>
                            <div class="tabesh-aof-search-wrapper">
                                <input type="text" 
                                       id="aof-user-search" 
                                       class="tabesh-aof-input" 
                                       placeholder="<?php echo esc_attr__('نام، شماره موبایل یا ایمیل...', 'tabesh'); ?>"
                                       autocomplete="off">
                                <span class="search-icon dashicons dashicons-search"></span>
                            </div>
                            <div id="aof-user-search-results" class="tabesh-aof-search-results"></div>
                            <input type="hidden" id="aof-selected-user-id" name="user_id" value="">
                            <div id="aof-selected-user-display" class="tabesh-aof-selected-user"></div>
                        </div>
                    </div>

                    <!-- New User Creation / ایجاد کاربر جدید -->
                    <div id="aof-new-user-section" class="tabesh-aof-subsection" style="display: none;">
                        <div class="tabesh-aof-form-row">
                            <div class="tabesh-aof-form-group">
                                <label for="aof-new-mobile">
                                    <?php echo esc_html__('شماره موبایل:', 'tabesh'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="aof-new-mobile" 
                                       class="tabesh-aof-input" 
                                       placeholder="09xxxxxxxxx" 
                                       pattern="09[0-9]{9}"
                                       dir="ltr">
                                <small class="field-hint"><?php echo esc_html__('این شماره به عنوان نام کاربری استفاده می‌شود', 'tabesh'); ?></small>
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
                            <?php echo esc_html__('ایجاد کاربر', 'tabesh'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Section 2: Order Details / بخش ۲: مشخصات سفارش -->
            <div class="tabesh-aof-section">
                <h3 class="tabesh-aof-section-title">
                    <span class="section-number">۲</span>
                    <?php echo esc_html__('مشخصات سفارش', 'tabesh'); ?>
                </h3>

                <!-- Book Title / عنوان کتاب -->
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
                           placeholder="<?php echo esc_attr__('عنوان کتاب را وارد کنید', 'tabesh'); ?>">
                </div>

                <div class="tabesh-aof-form-row">
                    <!-- Book Size / قطع کتاب -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-book-size">
                            <?php echo esc_html__('قطع کتاب:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-book-size" name="book_size" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب کنید...', 'tabesh'); ?></option>
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
                            <option value=""><?php echo esc_html__('انتخاب کنید...', 'tabesh'); ?></option>
                            <?php foreach ($paper_types as $paper_type_key => $weights) : ?>
                                <option value="<?php echo esc_attr($paper_type_key); ?>"><?php echo esc_html($paper_type_key); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Paper Weight / گرماژ کاغذ -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-paper-weight">
                            <?php echo esc_html__('گرماژ کاغذ:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-paper-weight" name="paper_weight" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('ابتدا نوع کاغذ را انتخاب کنید', 'tabesh'); ?></option>
                        </select>
                    </div>

                    <!-- Print Type / نوع چاپ -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-print-type">
                            <?php echo esc_html__('نوع چاپ:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-print-type" name="print_type" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب کنید...', 'tabesh'); ?></option>
                            <?php foreach ($print_types as $print_type_item) : ?>
                                <option value="<?php echo esc_attr($print_type_item); ?>"><?php echo esc_html($print_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Page Count Section / بخش تعداد صفحات -->
                <div class="tabesh-aof-form-row">
                    <div class="tabesh-aof-form-group" id="aof-page-count-color-group" style="display: none;">
                        <label for="aof-page-count-color">
                            <?php echo esc_html__('تعداد صفحات رنگی:', 'tabesh'); ?>
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
                            <?php echo esc_html__('تعداد صفحات سیاه و سفید:', 'tabesh'); ?>
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
                            <?php echo esc_html__('تعداد کل صفحات:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="aof-page-count-total" 
                               name="page_count_total" 
                               class="tabesh-aof-input" 
                               min="1" 
                               required>
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
                </div>

                <div class="tabesh-aof-form-row">
                    <!-- Binding Type / نوع صحافی -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-binding-type">
                            <?php echo esc_html__('نوع صحافی:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-binding-type" name="binding_type" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب کنید...', 'tabesh'); ?></option>
                            <?php foreach ($binding_types as $binding_type_item) : ?>
                                <option value="<?php echo esc_attr($binding_type_item); ?>"><?php echo esc_html($binding_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- License Type / نوع مجوز -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-license-type">
                            <?php echo esc_html__('نوع مجوز:', 'tabesh'); ?>
                            <span class="required">*</span>
                        </label>
                        <select id="aof-license-type" name="license_type" class="tabesh-aof-select" required>
                            <option value=""><?php echo esc_html__('انتخاب کنید...', 'tabesh'); ?></option>
                            <?php foreach ($license_types as $license_type_item) : ?>
                                <option value="<?php echo esc_attr($license_type_item); ?>"><?php echo esc_html($license_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cover Paper Weight / گرماژ کاغذ جلد -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-cover-paper-weight"><?php echo esc_html__('گرماژ کاغذ جلد:', 'tabesh'); ?></label>
                        <select id="aof-cover-paper-weight" name="cover_paper_weight" class="tabesh-aof-select">
                            <?php foreach ($cover_paper_weights as $weight) : ?>
                                <option value="<?php echo esc_attr($weight); ?>"><?php echo esc_html($weight); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lamination Type / نوع سلفون -->
                    <div class="tabesh-aof-form-group">
                        <label for="aof-lamination-type"><?php echo esc_html__('نوع سلفون:', 'tabesh'); ?></label>
                        <select id="aof-lamination-type" name="lamination_type" class="tabesh-aof-select">
                            <?php foreach ($lamination_types as $lamination_type_item) : ?>
                                <option value="<?php echo esc_attr($lamination_type_item); ?>"><?php echo esc_html($lamination_type_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Extras / آپشن‌های اضافی -->
                <?php if (!empty($extras)) : ?>
                <div class="tabesh-aof-form-group tabesh-aof-full-width">
                    <label><?php echo esc_html__('آپشن‌های اضافی:', 'tabesh'); ?></label>
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
                <?php endif; ?>

                <!-- Notes / یادداشت -->
                <div class="tabesh-aof-form-group tabesh-aof-full-width">
                    <label for="aof-notes"><?php echo esc_html__('یادداشت:', 'tabesh'); ?></label>
                    <textarea id="aof-notes" 
                              name="notes" 
                              class="tabesh-aof-textarea" 
                              rows="3" 
                              placeholder="<?php echo esc_attr__('توضیحات اضافی (اختیاری)...', 'tabesh'); ?>"></textarea>
                </div>
            </div>

            <!-- Section 3: Price / بخش ۳: قیمت -->
            <div class="tabesh-aof-section tabesh-aof-price-section">
                <h3 class="tabesh-aof-section-title">
                    <span class="section-number">۳</span>
                    <?php echo esc_html__('قیمت', 'tabesh'); ?>
                </h3>

                <div class="tabesh-aof-price-display">
                    <div class="tabesh-aof-price-row">
                        <span class="price-label"><?php echo esc_html__('قیمت محاسبه شده:', 'tabesh'); ?></span>
                        <span class="price-value" id="aof-calculated-price">-</span>
                    </div>

                    <div class="tabesh-aof-override-price">
                        <label class="tabesh-aof-checkbox">
                            <input type="checkbox" id="aof-override-price-check">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label"><?php echo esc_html__('تعیین قیمت دلخواه', 'tabesh'); ?></span>
                        </label>
                        <input type="number" 
                               id="aof-override-price" 
                               name="override_price" 
                               class="tabesh-aof-input" 
                               placeholder="<?php echo esc_attr__('قیمت دلخواه به ریال', 'tabesh'); ?>" 
                               min="0" 
                               step="1000" 
                               disabled>
                    </div>

                    <div class="tabesh-aof-price-row tabesh-aof-final-price">
                        <span class="price-label"><?php echo esc_html__('قیمت نهایی:', 'tabesh'); ?></span>
                        <span class="price-value" id="aof-final-price">-</span>
                    </div>
                </div>
            </div>

            <!-- Form Actions / دکمه‌های فرم -->
            <div class="tabesh-aof-actions">
                <button type="button" id="aof-calculate-btn" class="tabesh-aof-btn tabesh-aof-btn-secondary">
                    <span class="dashicons dashicons-calculator"></span>
                    <?php echo esc_html__('محاسبه قیمت', 'tabesh'); ?>
                </button>
                <button type="submit" id="aof-submit-btn" class="tabesh-aof-btn tabesh-aof-btn-primary">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html__('ثبت سفارش', 'tabesh'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
