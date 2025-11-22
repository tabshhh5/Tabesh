<?php
/**
 * Order Form Template
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get settings - ensure they are always arrays
$book_sizes = Tabesh()->get_setting('book_sizes', array());
$paper_types = Tabesh()->get_setting('paper_types', array());
$print_types = Tabesh()->get_setting('print_types', array());
$binding_types = Tabesh()->get_setting('binding_types', array());
$license_types = Tabesh()->get_setting('license_types', array());
$cover_paper_weights = Tabesh()->get_setting('cover_paper_weights', array());
$lamination_types = Tabesh()->get_setting('lamination_types', array());
$extras = Tabesh()->get_setting('extras', array());

// Ensure all array settings are actually arrays (defensive programming)
$book_sizes = is_array($book_sizes) ? $book_sizes : array();
$paper_types = is_array($paper_types) ? $paper_types : array();
$print_types = is_array($print_types) ? $print_types : array();
$binding_types = is_array($binding_types) ? $binding_types : array();
$license_types = is_array($license_types) ? $license_types : array();
$cover_paper_weights = is_array($cover_paper_weights) ? $cover_paper_weights : array();
$lamination_types = is_array($lamination_types) ? $lamination_types : array();
$extras = is_array($extras) ? $extras : array();

// Additional sanitization for extras - ensure all values are valid non-empty strings
$extras = array_filter(array_map(function($extra) {
    // Convert to string and trim
    $extra = is_scalar($extra) ? trim(strval($extra)) : '';
    // Return only non-empty strings
    return (!empty($extra) && $extra !== 'on') ? $extra : null;
}, $extras));

// Scalar settings
$min_quantity = Tabesh()->get_setting('min_quantity', 10);
$max_quantity = Tabesh()->get_setting('max_quantity', 10000);
$quantity_step = Tabesh()->get_setting('quantity_step', 10);
?>

<div class="tabesh-order-form" dir="rtl">
    <div class="tabesh-form-container">
        <h2 class="tabesh-form-title">محاسبه قیمت چاپ کتاب</h2>
        
        <?php if (empty($book_sizes) || empty($paper_types)): ?>
            <div class="tabesh-message error">
                <p><strong>خطا:</strong> تنظیمات محصول تکمیل نشده است.</p>
                <p>لطفاً ابتدا به <a href="<?php echo esc_url(admin_url('admin.php?page=tabesh-settings')); ?>">تنظیمات تابش</a> بروید و پارامترهای محصول را تنظیم کنید.</p>
            </div>
        <?php else: ?>
        
        <form id="tabesh-order-form" class="tabesh-form">
            <!-- Step 1: Book Title -->
            <div class="tabesh-form-step active" data-step="1">
                <h3><?php echo esc_html__('عنوان کتاب', 'tabesh'); ?></h3>
                <div class="tabesh-form-group">
                    <label for="book_title"><?php echo esc_html__('عنوان کتاب (نام روی جلد):', 'tabesh'); ?></label>
                    <input type="text" id="book_title" name="book_title" required class="tabesh-input" placeholder="<?php echo esc_attr__('عنوان کتاب را وارد کنید', 'tabesh'); ?>">
                </div>
            </div>

            <!-- Step 2: Book Size -->
            <div class="tabesh-form-step" data-step="2">
                <h3><?php echo esc_html__('قطع کتاب', 'tabesh'); ?></h3>
                <div class="tabesh-form-group">
                    <label for="book_size"><?php echo esc_html__('انتخاب قطع:', 'tabesh'); ?></label>
                    <select id="book_size" name="book_size" required class="tabesh-select">
                        <option value=""><?php echo esc_html__('انتخاب کنید...', 'tabesh'); ?></option>
                        <?php foreach ($book_sizes as $size): ?>
                            <option value="<?php echo esc_attr($size); ?>"><?php echo esc_html($size); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Step 3: Paper Type -->
            <div class="tabesh-form-step" data-step="3">
                <h3>نوع کاغذ</h3>
                <div class="tabesh-form-group">
                    <label for="paper_type">نوع کاغذ:</label>
                    <select id="paper_type" name="paper_type" required class="tabesh-select">
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($paper_types as $type => $weights): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Step 4: Paper Weight -->
            <div class="tabesh-form-step" data-step="4">
                <h3>گرماژ کاغذ</h3>
                <div class="tabesh-form-group">
                    <label for="paper_weight">گرماژ:</label>
                    <select id="paper_weight" name="paper_weight" required class="tabesh-select">
                        <option value="">ابتدا نوع کاغذ را انتخاب کنید</option>
                    </select>
                </div>
            </div>

            <!-- Step 5: Print Type -->
            <div class="tabesh-form-step" data-step="5">
                <h3>نوع چاپ</h3>
                <div class="tabesh-form-group">
                    <label for="print_type">نوع چاپ:</label>
                    <select id="print_type" name="print_type" required class="tabesh-select">
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($print_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Step 6: Page Count -->
            <div class="tabesh-form-step" data-step="6">
                <h3>تعداد صفحات</h3>
                <div class="tabesh-form-group">
                    <label for="page_count_bw">صفحات سیاه و سفید:</label>
                    <input type="number" id="page_count_bw" name="page_count_bw" min="0" value="0" class="tabesh-input">
                </div>
                <div class="tabesh-form-group">
                    <label for="page_count_color">صفحات رنگی:</label>
                    <input type="number" id="page_count_color" name="page_count_color" min="0" value="0" class="tabesh-input">
                </div>
            </div>

            <!-- Step 7: Quantity -->
            <div class="tabesh-form-step" data-step="7">
                <h3>تیراژ</h3>
                <div class="tabesh-form-group">
                    <label for="quantity">تعداد (حداقل <?php echo $min_quantity; ?>):</label>
                    <input type="number" id="quantity" name="quantity" 
                           min="<?php echo $min_quantity; ?>" 
                           max="<?php echo $max_quantity; ?>" 
                           step="<?php echo $quantity_step; ?>" 
                           value="<?php echo $min_quantity; ?>" 
                           required class="tabesh-input">
                </div>
            </div>

            <!-- Step 8: Binding -->
            <div class="tabesh-form-step" data-step="8">
                <h3>نوع صحافی</h3>
                <div class="tabesh-form-group">
                    <label for="binding_type">نوع صحافی:</label>
                    <select id="binding_type" name="binding_type" required class="tabesh-select">
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($binding_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Step 9: Cover Options -->
            <div class="tabesh-form-step" data-step="9">
                <h3>گزینه‌های جلد</h3>
                <div class="tabesh-form-group">
                    <label for="cover_paper_weight">گرماژ کاغذ جلد:</label>
                    <select id="cover_paper_weight" name="cover_paper_weight" class="tabesh-select">
                        <?php if (empty($cover_paper_weights)): ?>
                            <option value="250">250g</option>
                            <option value="300">300g</option>
                        <?php else: ?>
                            <?php foreach ($cover_paper_weights as $weight): ?>
                                <option value="<?php echo esc_attr($weight); ?>"><?php echo esc_html($weight); ?>g</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="tabesh-form-group">
                    <label for="lamination_type">نوع سلفون:</label>
                    <select id="lamination_type" name="lamination_type" class="tabesh-select">
                        <?php if (empty($lamination_types)): ?>
                            <option value="براق">براق</option>
                            <option value="مات">مات</option>
                            <option value="بدون سلفون">بدون سلفون</option>
                        <?php else: ?>
                            <?php foreach ($lamination_types as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Step 10: License -->
            <div class="tabesh-form-step" data-step="10">
                <h3>نوع مجوز</h3>
                <div class="tabesh-form-group">
                    <label for="license_type">مجوز:</label>
                    <select id="license_type" name="license_type" required class="tabesh-select">
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($license_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="license_upload" class="tabesh-form-group" style="display:none;">
                    <label>بارگذاری مجوز:</label>
                    <input type="file" id="license_file" name="license_file" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>

            <!-- Step 11: Extras -->
            <div class="tabesh-form-step" data-step="11">
                <h3>خدمات اضافی</h3>
                <div class="tabesh-form-group">
                    <?php if (empty($extras)): ?>
                        <p>هیچ خدمات اضافی تنظیم نشده است.</p>
                    <?php else: ?>
                        <?php foreach ($extras as $extra): ?>
                            <?php 
                            // Ensure $extra is a valid string (defensive programming)
                            if (is_string($extra) && !empty(trim($extra))): 
                                $extra_value = trim($extra);
                            ?>
                            <label class="tabesh-checkbox">
                                <input type="checkbox" name="extras[]" value="<?php echo esc_attr($extra_value); ?>">
                                <span><?php echo esc_html($extra_value); ?></span>
                            </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Step 12: Notes -->
            <div class="tabesh-form-step" data-step="12">
                <h3>توضیحات</h3>
                <div class="tabesh-form-group">
                    <label for="notes">توضیحات (اختیاری):</label>
                    <textarea id="notes" name="notes" rows="4" class="tabesh-textarea"></textarea>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="tabesh-form-navigation">
                <button type="button" id="prev-btn" class="tabesh-btn tabesh-btn-secondary" style="display:none;">قبلی</button>
                <button type="button" id="next-btn" class="tabesh-btn tabesh-btn-primary">بعدی</button>
                <button type="button" id="calculate-btn" class="tabesh-btn tabesh-btn-success" style="display:none;">محاسبه قیمت</button>
            </div>

            <!-- Progress indicator -->
            <div class="tabesh-progress">
                <div class="tabesh-progress-bar" style="width: 8.33%"></div>
            </div>
        </form>

        <!-- Price Result -->
        <div id="tabesh-price-result" class="tabesh-price-result" style="display:none;">
            <h3>پیش‌فاکتور</h3>
            <div class="tabesh-price-details">
                <div class="price-row">
                    <span>قیمت هر جلد:</span>
                    <span id="price-per-book"></span>
                </div>
                <div class="price-row">
                    <span>تعداد:</span>
                    <span id="price-quantity"></span>
                </div>
                <div class="price-row">
                    <span>جمع:</span>
                    <span id="price-subtotal"></span>
                </div>
                <div class="price-row extras" id="extras-row" style="display:none;">
                    <span>هزینه خدمات اضافی:</span>
                    <span id="price-extras"></span>
                </div>
                <div class="price-row discount" id="discount-row" style="display:none;">
                    <span>تخفیف:</span>
                    <span id="price-discount"></span>
                </div>
                <div class="price-row total">
                    <span>مبلغ نهایی:</span>
                    <span id="price-total"></span>
                </div>
            </div>
            <div class="tabesh-actions">
                <button type="button" id="edit-order-btn" class="tabesh-btn tabesh-btn-secondary">ویرایش</button>
                <button type="button" id="submit-order-btn" class="tabesh-btn tabesh-btn-success">ثبت سفارش</button>
            </div>
        </div>
    
    <?php endif; ?>
    </div>
</div>
