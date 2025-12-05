<?php
/**
 * Admin Order Form Template - Redesigned Compact Horizontal Layout
 * 
 * قالب فرم ثبت سفارش ویژه مدیر - بازطراحی چیدمان افقی فشرده
 * Template for admin order form shortcode with modern compact horizontal UI
 * Designed to fit on 1366x768 screens without scrolling
 *
 * @package Tabesh
 * @since 1.0.4
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

<div class="tabesh-aof-wrapper" dir="rtl">
    <!-- 
        Sticky Header / هدر ثابت
        Compact header that stays at top
        هدر فشرده که در بالا ثابت می‌ماند
    -->
    <header class="tabesh-aof-header">
        <div class="tabesh-aof-header-content">
            <h2 class="tabesh-aof-title">
                <span class="dashicons dashicons-cart"></span>
                <?php echo esc_html($form_title); ?>
            </h2>
            <span class="tabesh-aof-subtitle"><?php echo esc_html__('ثبت سفارش به نام مشتری', 'tabesh'); ?></span>
        </div>
        <div class="tabesh-aof-header-hint">
            <kbd>Ctrl</kbd>+<kbd>Enter</kbd> <?php echo esc_html__('ثبت سریع', 'tabesh'); ?>
        </div>
    </header>

    <!-- 
        Main Form Container / کانتینر اصلی فرم
        Compact no-scroll layout for desktop
        چیدمان فشرده بدون اسکرول برای دسکتاپ
    -->
    <form id="tabesh-admin-order-form-main" class="tabesh-aof-form">
        <!-- 
            Customer Section - Inline / بخش مشتری - خطی
            Compact inline customer selection
            انتخاب مشتری فشرده خطی
        -->
        <section class="tabesh-aof-section tabesh-aof-section-customer">
            <div class="tabesh-aof-section-header">
                <span class="section-badge">۱</span>
                <span class="section-label"><?php echo esc_html__('مشتری', 'tabesh'); ?></span>
            </div>
            
            <div class="tabesh-aof-customer-row">
                <!-- Radio toggle / تغییر رادیو -->
                <div class="tabesh-aof-toggle-group">
                    <label class="tabesh-aof-toggle">
                        <input type="radio" name="customer_type" value="existing" checked>
                        <span class="toggle-text"><?php echo esc_html__('موجود', 'tabesh'); ?></span>
                    </label>
                    <label class="tabesh-aof-toggle">
                        <input type="radio" name="customer_type" value="new">
                        <span class="toggle-text"><?php echo esc_html__('جدید', 'tabesh'); ?></span>
                    </label>
                </div>

                <!-- Existing user search / جستجوی کاربر موجود -->
                <div id="aof-existing-user-section" class="tabesh-aof-customer-fields">
                    <div class="tabesh-aof-search-box">
                        <span class="search-icon dashicons dashicons-search"></span>
                        <input type="text" 
                               id="aof-user-search" 
                               class="tabesh-aof-input" 
                               placeholder="<?php echo esc_attr__('جستجوی نام، موبایل یا ایمیل...', 'tabesh'); ?>"
                               autocomplete="off">
                        <div id="aof-user-search-results" class="tabesh-aof-search-results"></div>
                    </div>
                    <input type="hidden" id="aof-selected-user-id" name="user_id" value="">
                    <div id="aof-selected-user-display" class="tabesh-aof-selected-user"></div>
                </div>

                <!-- New user fields / فیلدهای کاربر جدید -->
                <div id="aof-new-user-section" class="tabesh-aof-customer-fields" style="display: none;">
                    <div class="tabesh-aof-inline-fields">
                        <input type="text" 
                               id="aof-new-mobile" 
                               class="tabesh-aof-input" 
                               placeholder="<?php echo esc_attr__('09xxxxxxxxx', 'tabesh'); ?>" 
                               pattern="09[0-9]{9}"
                               dir="ltr">
                        <input type="text" 
                               id="aof-new-first-name" 
                               class="tabesh-aof-input" 
                               placeholder="<?php echo esc_attr__('نام', 'tabesh'); ?>">
                        <input type="text" 
                               id="aof-new-last-name" 
                               class="tabesh-aof-input" 
                               placeholder="<?php echo esc_attr__('نام خانوادگی', 'tabesh'); ?>">
                        <button type="button" id="aof-create-user-btn" class="tabesh-aof-btn tabesh-aof-btn-sm">
                            <span class="dashicons dashicons-plus"></span>
                            <?php echo esc_html__('ایجاد', 'tabesh'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- 
            Order Details Section - Grid / بخش مشخصات سفارش - گرید
            3-column grid layout for order fields
            چیدمان گرید ۳ ستونه برای فیلدهای سفارش
        -->
        <section class="tabesh-aof-section tabesh-aof-section-order">
            <div class="tabesh-aof-section-header">
                <span class="section-badge">۲</span>
                <span class="section-label"><?php echo esc_html__('مشخصات سفارش', 'tabesh'); ?></span>
            </div>
            
            <!-- Book title - full width / عنوان کتاب - تمام عرض -->
            <div class="tabesh-aof-field tabesh-aof-field-full">
                <label for="aof-book-title"><?php echo esc_html__('عنوان کتاب', 'tabesh'); ?> <span class="req">*</span></label>
                <input type="text" 
                       id="aof-book-title" 
                       name="book_title" 
                       class="tabesh-aof-input" 
                       required
                       placeholder="<?php echo esc_attr__('عنوان کتاب را وارد کنید', 'tabesh'); ?>">
            </div>

            <!-- Grid row 1 / ردیف گرید ۱ -->
            <div class="tabesh-aof-grid">
                <div class="tabesh-aof-field">
                    <label for="aof-book-size"><?php echo esc_html__('قطع', 'tabesh'); ?> <span class="req">*</span></label>
                    <select id="aof-book-size" name="book_size" class="tabesh-aof-select" required>
                        <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                        <?php foreach ($book_sizes as $size) : ?>
                            <option value="<?php echo esc_attr($size); ?>"><?php echo esc_html($size); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tabesh-aof-field">
                    <label for="aof-paper-type"><?php echo esc_html__('نوع کاغذ', 'tabesh'); ?> <span class="req">*</span></label>
                    <select id="aof-paper-type" name="paper_type" class="tabesh-aof-select" required>
                        <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                        <?php foreach ($paper_types as $paper_type_key => $weights) : ?>
                            <option value="<?php echo esc_attr($paper_type_key); ?>"><?php echo esc_html($paper_type_key); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tabesh-aof-field">
                    <label for="aof-paper-weight"><?php echo esc_html__('گرماژ', 'tabesh'); ?> <span class="req">*</span></label>
                    <select id="aof-paper-weight" name="paper_weight" class="tabesh-aof-select" required>
                        <option value=""><?php echo esc_html__('ابتدا نوع کاغذ', 'tabesh'); ?></option>
                    </select>
                </div>

                <div class="tabesh-aof-field">
                    <label for="aof-print-type"><?php echo esc_html__('نوع چاپ', 'tabesh'); ?> <span class="req">*</span></label>
                    <select id="aof-print-type" name="print_type" class="tabesh-aof-select" required>
                        <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                        <?php foreach ($print_types as $print_type_item) : ?>
                            <option value="<?php echo esc_attr($print_type_item); ?>"><?php echo esc_html($print_type_item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tabesh-aof-field">
                    <label for="aof-binding-type"><?php echo esc_html__('صحافی', 'tabesh'); ?> <span class="req">*</span></label>
                    <select id="aof-binding-type" name="binding_type" class="tabesh-aof-select" required>
                        <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                        <?php foreach ($binding_types as $binding_type_item) : ?>
                            <option value="<?php echo esc_attr($binding_type_item); ?>"><?php echo esc_html($binding_type_item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tabesh-aof-field">
                    <label for="aof-license-type"><?php echo esc_html__('مجوز', 'tabesh'); ?> <span class="req">*</span></label>
                    <select id="aof-license-type" name="license_type" class="tabesh-aof-select" required>
                        <option value=""><?php echo esc_html__('انتخاب...', 'tabesh'); ?></option>
                        <?php foreach ($license_types as $license_type_item) : ?>
                            <option value="<?php echo esc_attr($license_type_item); ?>"><?php echo esc_html($license_type_item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Grid row 2 / ردیف گرید ۲ -->
            <div class="tabesh-aof-grid">
                <div class="tabesh-aof-field">
                    <label for="aof-quantity"><?php echo esc_html__('تیراژ', 'tabesh'); ?> <span class="req">*</span></label>
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

                <!-- Dynamic page count fields / فیلدهای پویای تعداد صفحات -->
                <div class="tabesh-aof-field" id="aof-page-count-total-group">
                    <label for="aof-page-count-total"><?php echo esc_html__('تعداد صفحات', 'tabesh'); ?> <span class="req">*</span></label>
                    <input type="number" 
                           id="aof-page-count-total" 
                           name="page_count_total" 
                           class="tabesh-aof-input" 
                           min="1" 
                           value="">
                </div>

                <div class="tabesh-aof-field" id="aof-page-count-color-group" style="display: none;">
                    <label for="aof-page-count-color"><?php echo esc_html__('صفحات رنگی', 'tabesh'); ?> <span class="req">*</span></label>
                    <input type="number" 
                           id="aof-page-count-color" 
                           name="page_count_color" 
                           class="tabesh-aof-input" 
                           min="0" 
                           value="0">
                </div>

                <div class="tabesh-aof-field" id="aof-page-count-bw-group" style="display: none;">
                    <label for="aof-page-count-bw"><?php echo esc_html__('صفحات سیاه‌وسفید', 'tabesh'); ?> <span class="req">*</span></label>
                    <input type="number" 
                           id="aof-page-count-bw" 
                           name="page_count_bw" 
                           class="tabesh-aof-input" 
                           min="0" 
                           value="0">
                </div>

                <div class="tabesh-aof-field">
                    <label for="aof-cover-paper-weight"><?php echo esc_html__('گرماژ جلد', 'tabesh'); ?></label>
                    <select id="aof-cover-paper-weight" name="cover_paper_weight" class="tabesh-aof-select">
                        <?php foreach ($cover_paper_weights as $weight) : ?>
                            <option value="<?php echo esc_attr($weight); ?>"><?php echo esc_html($weight); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tabesh-aof-field">
                    <label for="aof-lamination-type"><?php echo esc_html__('سلفون', 'tabesh'); ?></label>
                    <select id="aof-lamination-type" name="lamination_type" class="tabesh-aof-select">
                        <?php foreach ($lamination_types as $lamination_type_item) : ?>
                            <option value="<?php echo esc_attr($lamination_type_item); ?>"><?php echo esc_html($lamination_type_item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Extras and Notes Row / ردیف آپشن‌ها و یادداشت -->
            <div class="tabesh-aof-extras-row">
                <?php if (!empty($extras)) : ?>
                <div class="tabesh-aof-extras">
                    <span class="extras-label"><?php echo esc_html__('آپشن‌ها:', 'tabesh'); ?></span>
                    <div class="tabesh-aof-checkbox-group">
                        <?php foreach ($extras as $extra) : ?>
                            <label class="tabesh-aof-chip">
                                <input type="checkbox" name="extras[]" value="<?php echo esc_attr($extra); ?>">
                                <span class="chip-text"><?php echo esc_html($extra); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="tabesh-aof-notes">
                    <textarea id="aof-notes" 
                              name="notes" 
                              class="tabesh-aof-textarea" 
                              placeholder="<?php echo esc_attr__('یادداشت (اختیاری)...', 'tabesh'); ?>"></textarea>
                </div>
            </div>
        </section>
    </form>

    <!-- 
        Sticky Footer / فوتر ثابت
        Action bar with price and buttons - always visible
        نوار عملیات با قیمت و دکمه‌ها - همیشه قابل مشاهده
    -->
    <footer class="tabesh-aof-footer">
        <div class="tabesh-aof-price-bar">
            <!-- Calculated Price / قیمت محاسبه شده -->
            <div class="tabesh-aof-price-item">
                <span class="price-label"><?php echo esc_html__('محاسبه:', 'tabesh'); ?></span>
                <span class="price-value" id="aof-calculated-price">---</span>
                <span class="price-unit"><?php echo esc_html__('ریال', 'tabesh'); ?></span>
            </div>

            <!-- Final Price / قیمت نهایی -->
            <div class="tabesh-aof-price-item tabesh-aof-price-final">
                <span class="price-label"><?php echo esc_html__('نهایی:', 'tabesh'); ?></span>
                <span class="price-value" id="aof-final-price">---</span>
                <span class="price-unit"><?php echo esc_html__('ریال', 'tabesh'); ?></span>
            </div>

            <!-- Override Price / قیمت دلخواه -->
            <div class="tabesh-aof-override">
                <label class="tabesh-aof-override-toggle">
                    <input type="checkbox" id="aof-override-price-check">
                    <span class="toggle-slider"></span>
                    <span class="toggle-label"><?php echo esc_html__('قیمت دلخواه', 'tabesh'); ?></span>
                </label>
                <input type="number" 
                       id="aof-override-price" 
                       name="override_price" 
                       class="tabesh-aof-input tabesh-aof-input-sm" 
                       placeholder="<?php echo esc_attr__('ریال', 'tabesh'); ?>" 
                       min="0" 
                       step="1000" 
                       disabled>
            </div>
        </div>

        <div class="tabesh-aof-actions">
            <button type="button" id="aof-calculate-btn" class="tabesh-aof-btn tabesh-aof-btn-calc" form="tabesh-admin-order-form-main">
                <span class="dashicons dashicons-calculator"></span>
                <?php echo esc_html__('محاسبه', 'tabesh'); ?>
            </button>
            <button type="submit" id="aof-submit-btn" class="tabesh-aof-btn tabesh-aof-btn-submit" form="tabesh-admin-order-form-main">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html__('ثبت سفارش', 'tabesh'); ?>
            </button>
        </div>
    </footer>
</div>

<!-- Toast Container for Notifications / کانتینر توست برای اعلان‌ها -->
<div id="tabesh-aof-toast-container" class="tabesh-aof-toast-container"></div>
