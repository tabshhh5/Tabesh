<?php
/**
 * Admin Settings Template
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure plugin is properly initialized
$tabesh = function_exists('Tabesh') ? Tabesh() : null;
if (!$tabesh || !isset($tabesh->admin) || !$tabesh->admin) {
    wp_die(__('خطا: افزونه تابش به درستی راه‌اندازی نشده است. لطفاً از نصب صحیح WooCommerce اطمینان حاصل کنید.', 'tabesh'));
}

$admin = $tabesh->admin;
?>

<div class="wrap tabesh-admin-settings" dir="rtl">
    <h1>تنظیمات تابش</h1>
    
    <?php
    // Display debug info if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>حالت دیباگ فعال است.</strong> جزئیات در کنسول مرورگر و لاگ PHP قابل مشاهده است.</p>';
        echo '</div>';
    }
    ?>

    <form method="post" action="">
        <?php wp_nonce_field('tabesh_settings'); ?>

        <div class="tabesh-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#tab-general" class="nav-tab nav-tab-active">تنظیمات عمومی</a>
                <a href="#tab-product" class="nav-tab">پارامترهای محصول</a>
                <a href="#tab-pricing" class="nav-tab">قیمت‌گذاری</a>
                <a href="#tab-sms" class="nav-tab">پیامک</a>
                <a href="#tab-staff-access" class="nav-tab">دسترسی کارمندان</a>
                <a href="#tab-export-import" class="nav-tab">برونبری و درونریزی</a>
            </nav>

            <!-- General Settings -->
            <div id="tab-general" class="tabesh-tab-content active">
                <h2>تنظیمات عمومی</h2>

                <table class="form-table">
                    <tr>
                        <th><label for="min_quantity">حداقل تیراژ</label></th>
                        <td>
                            <input type="number" id="min_quantity" name="min_quantity" 
                                   value="<?php echo esc_attr($admin->get_setting('min_quantity', 10)); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="max_quantity">حداکثر تیراژ</label></th>
                        <td>
                            <input type="number" id="max_quantity" name="max_quantity" 
                                   value="<?php echo esc_attr($admin->get_setting('max_quantity', 10000)); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="quantity_step">گام تیراژ</label></th>
                        <td>
                            <input type="number" id="quantity_step" name="quantity_step" 
                                   value="<?php echo esc_attr($admin->get_setting('quantity_step', 10)); ?>" 
                                   class="regular-text">
                            <p class="description">تیراژ باید مضربی از این عدد باشد</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Product Parameters -->
            <div id="tab-product" class="tabesh-tab-content">
                <h2>پارامترهای محصول</h2>

                <div class="notice notice-info">
                    <p><strong>🎯 راهنما:</strong> در این بخش پارامترهای اصلی محصول را تعریف کنید.</p>
                    <ul style="margin-right: 20px;">
                        <li>✨ از دکمه <strong>"افزودن +"</strong> برای اضافه کردن پارامتر جدید استفاده کنید</li>
                        <li>🗑️ برای حذف، روی دکمه <strong>"حذف"</strong> کنار هر پارامتر کلیک کنید</li>
                        <li>💡 پس از ذخیره، پارامترها به صورت <strong>خودکار</strong> در بخش قیمت‌گذاری بارگذاری می‌شوند</li>
                    </ul>
                </div>

                <table class="form-table">
                    <tr>
                        <th><label for="book_sizes">قطع‌های کتاب</label></th>
                        <td>
                            <div class="tabesh-param-manager" data-field="book_sizes">
                                <div class="tabesh-param-list">
                                    <?php 
                                        $sizes = $admin->get_setting('book_sizes', array());
                                        if (is_array($sizes) && !empty($sizes)) {
                                            foreach ($sizes as $size) {
                                                echo '<div class="tabesh-param-item">';
                                                echo '<input type="text" class="tabesh-param-input" value="' . esc_attr($size) . '" placeholder="مثال: A5">';
                                                echo '<button type="button" class="button tabesh-param-remove" title="حذف این پارامتر">×</button>';
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </div>
                                <button type="button" class="button button-secondary tabesh-param-add" data-placeholder="مثال: A5">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن قطع جدید
                                </button>
                                <textarea id="book_sizes" name="book_sizes" class="tabesh-param-hidden" style="display:none;"></textarea>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span> 
                                    تعداد قطع‌ها: <strong><span class="param-count"><?php echo is_array($sizes) ? count($sizes) : 0; ?></span></strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="paper_types">انواع کاغذ و گرماژها</label></th>
                        <td>
                            <div class="notice notice-warning inline tabesh-paper-types-notice">
                                <p><strong>⚠️ توجه:</strong> این فیلد فرمت پیشرفته دارد و در حال حاضر از رابط کاربری جدید پشتیبانی نمی‌کند.</p>
                                <p>برای ویرایش، از همان روش قبلی استفاده کنید.</p>
                            </div>
                            <textarea id="paper_types" name="paper_types" rows="4" class="large-text" dir="ltr" placeholder="تحریر=60,70,80&#10;بالک=60,70,80,100"><?php 
                                $paper_types_data = $admin->get_setting('paper_types', array());
                                if (is_array($paper_types_data)) {
                                    foreach ($paper_types_data as $type => $weights) {
                                        if (is_array($weights)) {
                                            echo esc_attr($type) . '=' . implode(',', $weights) . "\n";
                                        }
                                    }
                                }
                            ?></textarea>
                            <p class="description">
                                <span class="dashicons dashicons-info"></span> 
                                هر خط یک نوع کاغذ با گرماژهای مجاز (فرمت: نوع=گرماژ1,گرماژ2,گرماژ3). 
                                تعداد فعلی: <strong><span class="param-count"><?php echo is_array($paper_types_data) ? count($paper_types_data) : 0; ?></span></strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="print_types">انواع چاپ</label></th>
                        <td>
                            <div class="tabesh-param-manager" data-field="print_types">
                                <div class="tabesh-param-list">
                                    <?php 
                                        $types = $admin->get_setting('print_types', array());
                                        if (is_array($types) && !empty($types)) {
                                            foreach ($types as $type) {
                                                echo '<div class="tabesh-param-item">';
                                                echo '<input type="text" class="tabesh-param-input" value="' . esc_attr($type) . '" placeholder="مثال: سیاه و سفید">';
                                                echo '<button type="button" class="button tabesh-param-remove" title="حذف این پارامتر">×</button>';
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </div>
                                <button type="button" class="button button-secondary tabesh-param-add" data-placeholder="مثال: سیاه و سفید">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن نوع چاپ
                                </button>
                                <textarea id="print_types" name="print_types" class="tabesh-param-hidden" style="display:none;"></textarea>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span> 
                                    تعداد انواع چاپ: <strong><span class="param-count"><?php echo is_array($types) ? count($types) : 0; ?></span></strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="binding_types">انواع صحافی</label></th>
                        <td>
                            <div class="tabesh-param-manager" data-field="binding_types">
                                <div class="tabesh-param-list">
                                    <?php 
                                        $types = $admin->get_setting('binding_types', array());
                                        if (is_array($types) && !empty($types)) {
                                            foreach ($types as $type) {
                                                echo '<div class="tabesh-param-item">';
                                                echo '<input type="text" class="tabesh-param-input" value="' . esc_attr($type) . '" placeholder="مثال: شومیز">';
                                                echo '<button type="button" class="button tabesh-param-remove" title="حذف این پارامتر">×</button>';
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </div>
                                <button type="button" class="button button-secondary tabesh-param-add" data-placeholder="مثال: شومیز">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن نوع صحافی
                                </button>
                                <textarea id="binding_types" name="binding_types" class="tabesh-param-hidden" style="display:none;"></textarea>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span> 
                                    تعداد انواع صحافی: <strong><span class="param-count"><?php echo is_array($types) ? count($types) : 0; ?></span></strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="license_types">انواع مجوز</label></th>
                        <td>
                            <div class="tabesh-param-manager" data-field="license_types">
                                <div class="tabesh-param-list">
                                    <?php 
                                        $types = $admin->get_setting('license_types', array());
                                        if (is_array($types) && !empty($types)) {
                                            foreach ($types as $type) {
                                                echo '<div class="tabesh-param-item">';
                                                echo '<input type="text" class="tabesh-param-input" value="' . esc_attr($type) . '" placeholder="مثال: دارم">';
                                                echo '<button type="button" class="button tabesh-param-remove" title="حذف این پارامتر">×</button>';
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </div>
                                <button type="button" class="button button-secondary tabesh-param-add" data-placeholder="مثال: دارم">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن نوع مجوز
                                </button>
                                <textarea id="license_types" name="license_types" class="tabesh-param-hidden" style="display:none;"></textarea>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span> 
                                    تعداد انواع مجوز: <strong><span class="param-count"><?php echo is_array($types) ? count($types) : 0; ?></span></strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cover_paper_weights">گرماژ کاغذ جلد</label></th>
                        <td>
                            <div class="tabesh-param-manager" data-field="cover_paper_weights">
                                <div class="tabesh-param-list">
                                    <?php 
                                        $weights = $admin->get_setting('cover_paper_weights', array());
                                        if (is_array($weights) && !empty($weights)) {
                                            foreach ($weights as $weight) {
                                                echo '<div class="tabesh-param-item">';
                                                echo '<input type="text" class="tabesh-param-input" value="' . esc_attr($weight) . '" placeholder="مثال: 250">';
                                                echo '<button type="button" class="button tabesh-param-remove" title="حذف این پارامتر">×</button>';
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </div>
                                <button type="button" class="button button-secondary tabesh-param-add" data-placeholder="مثال: 250">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن گرماژ
                                </button>
                                <textarea id="cover_paper_weights" name="cover_paper_weights" class="tabesh-param-hidden" style="display:none;"></textarea>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span> 
                                    تعداد گرماژها: <strong><span class="param-count"><?php echo is_array($weights) ? count($weights) : 0; ?></span></strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lamination_types">انواع سلفون</label></th>
                        <td>
                            <div class="tabesh-param-manager" data-field="lamination_types">
                                <div class="tabesh-param-list">
                                    <?php 
                                        $types = $admin->get_setting('lamination_types', array());
                                        if (is_array($types) && !empty($types)) {
                                            foreach ($types as $type) {
                                                echo '<div class="tabesh-param-item">';
                                                echo '<input type="text" class="tabesh-param-input" value="' . esc_attr($type) . '" placeholder="مثال: براق">';
                                                echo '<button type="button" class="button tabesh-param-remove" title="حذف این پارامتر">×</button>';
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </div>
                                <button type="button" class="button button-secondary tabesh-param-add" data-placeholder="مثال: براق">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن نوع سلفون
                                </button>
                                <textarea id="lamination_types" name="lamination_types" class="tabesh-param-hidden" style="display:none;"></textarea>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span> 
                                    تعداد انواع سلفون: <strong><span class="param-count"><?php echo is_array($types) ? count($types) : 0; ?></span></strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="extras">خدمات اضافی</label></th>
                        <td>
                            <div class="tabesh-param-manager" data-field="extras">
                                <div class="tabesh-param-list">
                                    <?php 
                                        $extras = $admin->get_setting('extras', array());
                                        if (is_array($extras) && !empty($extras)) {
                                            foreach ($extras as $extra) {
                                                echo '<div class="tabesh-param-item">';
                                                echo '<input type="text" class="tabesh-param-input" value="' . esc_attr($extra) . '" placeholder="مثال: لب گرد">';
                                                echo '<button type="button" class="button tabesh-param-remove" title="حذف این پارامتر">×</button>';
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </div>
                                <button type="button" class="button button-secondary tabesh-param-add" data-placeholder="مثال: لب گرد">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن خدمت اضافی
                                </button>
                                <textarea id="extras" name="extras" class="tabesh-param-hidden" style="display:none;"></textarea>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span> 
                                    تعداد خدمات اضافی: <strong><span class="param-count"><?php echo is_array($extras) ? count($extras) : 0; ?></span></strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Pricing Settings -->
            <div id="tab-pricing" class="tabesh-tab-content">
                <h2>تنظیمات قیمت‌گذاری</h2>
                
                <div class="notice notice-success">
                    <p>
                        <strong>✨ قابلیت هوشمند:</strong> این بخش به صورت خودکار از پارامترهای تعریف شده در تب "پارامترهای محصول" استفاده می‌کند.
                    </p>
                    <p>
                        <strong>🔄 نحوه کار:</strong>
                    </p>
                    <ul style="margin-right: 20px;">
                        <li>پارامترهای محصول را در تب قبل تعریف و ذخیره کنید</li>
                        <li>سپس به این تب بازگردید و قیمت‌های مربوطه را وارد کنید</li>
                        <li>نیازی به تعریف مجدد پارامترها نیست - فقط قیمت‌گذاری کنید!</li>
                    </ul>
                </div>

                <div class="notice notice-info">
                    <p>
                        <strong>🎯 راهنما:</strong> در این بخش می‌توانید قیمت‌های مختلف محاسبه چاپ کتاب را تنظیم کنید.
                        تمام قیمت‌ها به تومان هستند.
                    </p>
                    <p>
                        <strong>📋 فرمت:</strong> هر خط باید به صورت <code>نام=مقدار</code> باشد.
                        مقادیر عددی می‌توانند اعشار داشته باشند (مثال: <code>A5=1.5</code>).
                    </p>
                </div>

                <h3>ضریب قطع کتاب (Book Size Multipliers)</h3>
                <p class="description">ضریب هر قطع بر هزینه کاغذ و چاپ تأثیر می‌گذارد. فرمت: نام=ضریب (مثال: A5=1, A4=1.5)</p>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_book_sizes">ضرایب قطع کتاب</label></th>
                        <td>
                            <textarea id="pricing_book_sizes" name="pricing_book_sizes" rows="4" class="large-text" dir="ltr" placeholder="A5=1&#10;A4=1.5&#10;رقعی=1.1"><?php 
                                $book_sizes = $admin->get_setting('pricing_book_sizes', array());
                                if (is_array($book_sizes) && !empty($book_sizes)) {
                                    foreach ($book_sizes as $size => $multiplier) {
                                        echo esc_attr($size) . '=' . esc_attr($multiplier) . "\n";
                                    }
                                } else {
                                    echo "A5=1\nA4=1.5\nرقعی=1.1\nوزیری=1.3\nخشتی=1.4";
                                }
                            ?></textarea>
                            <p class="description">
                                ✓ هر خط یک قطع (مثال: <code>A5=1</code> یا <code>وزیری=1.3</code>)<br>
                                ✓ مقادیر می‌توانند اعشار داشته باشند (مثال: <code>1.5</code>)<br>
                                ✓ تعداد فیلدها: <span id="pricing_book_sizes_count"><?php echo is_array($book_sizes) ? count($book_sizes) : 0; ?></span>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3>قیمت پایه کاغذ (Paper Type Base Costs)</h3>
                <p class="description">هزینه پایه هر صفحه برای هر نوع کاغذ (به تومان)</p>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_paper_types">قیمت انواع کاغذ</label></th>
                        <td>
                            <textarea id="pricing_paper_types" name="pricing_paper_types" rows="5" class="large-text" dir="ltr" placeholder="تحریر=200&#10;بالک=250"><?php 
                                $paper_types = $admin->get_setting('pricing_paper_types', array());
                                if (is_array($paper_types) && !empty($paper_types)) {
                                    foreach ($paper_types as $type => $cost) {
                                        echo esc_attr($type) . '=' . esc_attr($cost) . "\n";
                                    }
                                } else {
                                    echo "تحریر=200\nبالک=250\nglossy=250\nmatte=200";
                                }
                            ?></textarea>
                            <p class="description">
                                ✓ هر خط یک نوع کاغذ (مثال: <code>glossy=250</code> یا <code>تحریر=200</code>)<br>
                                ✓ قیمت به تومان برای هر صفحه<br>
                                ✓ تعداد فیلدها: <span id="pricing_paper_types_count"><?php echo is_array($paper_types) ? count($paper_types) : 0; ?></span>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3>هزینه چاپ (Print Costs per Page)</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_print_costs_bw">چاپ سیاه و سفید (هر صفحه)</label></th>
                        <td>
                            <input type="number" id="pricing_print_costs_bw" name="pricing_print_costs_bw" 
                                   value="<?php 
                                       $print_costs = $admin->get_setting('pricing_print_costs', array('bw' => 200, 'color' => 800));
                                       echo esc_attr($print_costs['bw'] ?? 200); 
                                   ?>" 
                                   class="regular-text"> تومان
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pricing_print_costs_color">چاپ رنگی (هر صفحه)</label></th>
                        <td>
                            <input type="number" id="pricing_print_costs_color" name="pricing_print_costs_color" 
                                   value="<?php echo esc_attr($print_costs['color'] ?? 800); ?>" 
                                   class="regular-text"> تومان
                        </td>
                    </tr>
                </table>

                <h3>هزینه جلد (Cover Costs)</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_cover_types_soft">جلد نرم (شومیز)</label></th>
                        <td>
                            <input type="number" id="pricing_cover_types_soft" name="pricing_cover_types_soft" 
                                   value="<?php 
                                       $cover_types = $admin->get_setting('pricing_cover_types', array('soft' => 8000, 'hard' => 15000));
                                       echo esc_attr($cover_types['soft'] ?? 8000); 
                                   ?>" 
                                   class="regular-text"> تومان
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pricing_cover_types_hard">جلد سخت</label></th>
                        <td>
                            <input type="number" id="pricing_cover_types_hard" name="pricing_cover_types_hard" 
                                   value="<?php echo esc_attr($cover_types['hard'] ?? 15000); ?>" 
                                   class="regular-text"> تومان
                        </td>
                    </tr>
                </table>

                <h3>هزینه سلفون کاری (Lamination Costs)</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_lamination_costs">قیمت انواع سلفون</label></th>
                        <td>
                            <textarea id="pricing_lamination_costs" name="pricing_lamination_costs" rows="3" class="large-text" dir="ltr" placeholder="براق=2000&#10;مات=2500"><?php 
                                $lamination = $admin->get_setting('pricing_lamination_costs', array());
                                if (is_array($lamination) && !empty($lamination)) {
                                    foreach ($lamination as $type => $cost) {
                                        echo esc_attr($type) . '=' . esc_attr($cost) . "\n";
                                    }
                                } else {
                                    echo "براق=2000\nمات=2500\nبدون سلفون=0";
                                }
                            ?></textarea>
                            <p class="description">
                                ✓ هر خط یک نوع سلفون (مثال: <code>براق=2000</code> یا <code>مات=2500</code>)<br>
                                ✓ تعداد فیلدها: <span id="pricing_lamination_costs_count"><?php echo is_array($lamination) ? count($lamination) : 0; ?></span>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3>هزینه صحافی (Binding Costs)</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_binding_costs">قیمت انواع صحافی</label></th>
                        <td>
                            <textarea id="pricing_binding_costs" name="pricing_binding_costs" rows="4" class="large-text" dir="ltr" placeholder="شومیز=3000&#10;جلد سخت=8000"><?php 
                                $binding = $admin->get_setting('pricing_binding_costs', array());
                                if (is_array($binding) && !empty($binding)) {
                                    foreach ($binding as $type => $cost) {
                                        echo esc_attr($type) . '=' . esc_attr($cost) . "\n";
                                    }
                                } else {
                                    echo "شومیز=3000\nجلد سخت=8000\nگالینگور=6000\nسیمی=2000";
                                }
                            ?></textarea>
                            <p class="description">
                                ✓ هر خط یک نوع صحافی (مثال: <code>شومیز=3000</code> یا <code>جلد سخت=8000</code>)<br>
                                ✓ تعداد فیلدها: <span id="pricing_binding_costs_count"><?php echo is_array($binding) ? count($binding) : 0; ?></span>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3>هزینه آپشن‌های اضافی (Additional Options)</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_options_costs">قیمت آپشن‌ها</label></th>
                        <td>
                            <textarea id="pricing_options_costs" name="pricing_options_costs" rows="6" class="large-text" dir="ltr" placeholder="لب گرد=1000&#10;خط تا=500"><?php 
                                $options = $admin->get_setting('pricing_options_costs', array());
                                if (is_array($options) && !empty($options)) {
                                    foreach ($options as $option => $cost) {
                                        echo esc_attr($option) . '=' . esc_attr($cost) . "\n";
                                    }
                                } else {
                                    echo "لب گرد=1000\nخط تا=500\nشیرینک=1500\nسوراخ=300\nشماره گذاری=800";
                                }
                            ?></textarea>
                            <p class="description">
                                ✓ هر خط یک آپشن (مثال: <code>لب گرد=1000</code> یا <code>uv_coating=3000</code>)<br>
                                ✓ تعداد فیلدها: <span id="pricing_options_costs_count"><?php echo is_array($options) ? count($options) : 0; ?></span>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3>حاشیه سود (Profit Margin)</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_profit_margin">درصد حاشیه سود</label></th>
                        <td>
                            <input type="number" id="pricing_profit_margin" name="pricing_profit_margin" 
                                   value="<?php 
                                       $margin = $admin->get_setting('pricing_profit_margin', '0');
                                       // Convert from decimal to percentage for display
                                       echo esc_attr(floatval($margin) * 100); 
                                   ?>" 
                                   step="0.01" min="0" max="100"
                                   class="regular-text"> %
                            <p class="description">مثال: 0 برای 0%، 10 برای 10%، 15 برای 15%</p>
                        </td>
                    </tr>
                </table>

                <h3>تخفیفات کمی (Quantity Discounts)</h3>
                <p class="description">تعریف تخفیف بر اساس تیراژ - تیراژهای بالاتر تخفیف بیشتری دریافت می‌کنند</p>
                <table class="form-table">
                    <tr>
                        <th><label for="pricing_quantity_discounts">تخفیفات تیراژ</label></th>
                        <td>
                            <textarea id="pricing_quantity_discounts" name="pricing_quantity_discounts" rows="5" class="large-text" dir="ltr" placeholder="100=10&#10;50=5"><?php 
                                $discounts = $admin->get_setting('pricing_quantity_discounts', array());
                                if (is_array($discounts) && !empty($discounts)) {
                                    foreach ($discounts as $qty => $discount) {
                                        echo esc_attr($qty) . '=' . esc_attr($discount) . "\n";
                                    }
                                } else {
                                    echo "100=10\n50=5";
                                }
                            ?></textarea>
                            <p class="description">
                                ✓ هر خط یک قاعده تخفیف (مثال: <code>100=10</code> یعنی 10% تخفیف برای تیراژ 100 و بیشتر)<br>
                                ✓ تیراژ=درصد تخفیف (تیراژ به عدد، تخفیف به درصد)<br>
                                ✓ تخفیفات بر اساس تیراژ نزولی اعمال می‌شود (بالاترین تخفیف اول بررسی می‌شود)<br>
                                ✓ برای حذف همه تخفیفات، همه خطوط را پاک کنید<br>
                                ✓ تعداد فیلدها: <span id="pricing_quantity_discounts_count"><?php echo is_array($discounts) ? count($discounts) : 0; ?></span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SMS Settings -->
            <div id="tab-sms" class="tabesh-tab-content">
                <h2>تنظیمات پیامک (ملی پیامک - ارسال الگومحور)</h2>

                <div class="notice notice-info">
                    <p>
                        <strong>📱 درباره سرویس:</strong> این بخش از متد <code>SendByBaseNumber2</code> در API الگومحور (Pattern/Template) ملی‌پیامک استفاده می‌کند.
                    </p>
                    <p>
                        <strong>🔑 مراحل تنظیم:</strong>
                    </p>
                    <ol style="margin-right: 20px;">
                        <li>در پنل ملی‌پیامک (<a href="https://panel.melipayamak.com" target="_blank">panel.melipayamak.com</a>)، الگوهای پیامک خود را تعریف کنید</li>
                        <li>کد عددی الگو (bodyId) را از پنل کپی کنید - <strong>توجه:</strong> کد الگو باید عدد خالص باشد (مثال: 12345)</li>
                        <li>نام کاربری و رمز عبور پنل را در اینجا وارد کنید</li>
                        <li>برای هر وضعیت سفارش، کد الگوی مربوطه را وارد کنید</li>
                        <li>با دکمه "بررسی اتصال" صحت تنظیمات را بررسی کنید</li>
                    </ol>
                    <p>
                        <strong>📌 متغیرهای الگو (به ترتیب ارسال):</strong><br>
                        1️⃣ <code>order_number</code> - شماره سفارش (مثال: TB-00001)<br>
                        2️⃣ <code>customer_name</code> - نام مشتری<br>
                        3️⃣ <code>status</code> - وضعیت سفارش به فارسی<br>
                        4️⃣ <code>date</code> - تاریخ (فرمت: 1402/12/15)
                    </p>
                    <p>
                        <strong>⚠️ نکات مهم:</strong>
                    </p>
                    <ul style="margin-right: 20px;">
                        <li>کد الگو (bodyId) باید عدد صحیح باشد، نه متن</li>
                        <li>ترتیب متغیرها در الگوی ملی‌پیامک مهم است و باید با ترتیب بالا مطابقت داشته باشد</li>
                        <li>برای مشاهده مستندات کامل API: <a href="https://github.com/melipayamak" target="_blank">github.com/melipayamak</a></li>
                    </ul>
                </div>

                <h3>تنظیمات اتصال به سامانه ملی پیامک</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="sms_enabled">فعال‌سازی سیستم پیامک</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="sms_enabled" name="sms_enabled" value="1" 
                                       <?php checked($admin->get_setting('sms_enabled', '0'), '1'); ?>>
                                فعال
                            </label>
                            <p class="description">فعال کردن ارسال پیامک الگومحور برای تغییر وضعیت سفارشات</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sms_username">نام کاربری سامانه ملی</label></th>
                        <td>
                            <input type="text" id="sms_username" name="sms_username" 
                                   value="<?php echo esc_attr($admin->get_setting('sms_username')); ?>" 
                                   class="regular-text" dir="ltr">
                            <p class="description">نام کاربری پنل ملی‌پیامک (معمولاً شماره موبایل شما)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sms_password">رمز عبور سامانه ملی</label></th>
                        <td>
                            <input type="password" id="sms_password" name="sms_password" 
                                   value="<?php echo esc_attr($admin->get_setting('sms_password')); ?>" 
                                   class="regular-text" dir="ltr">
                            <p class="description">رمز عبور پنل ملی‌پیامک (ذخیره امن می‌شود)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>تست اتصال به پنل</label></th>
                        <td>
                            <button type="button" id="test_connection_btn" class="button button-secondary">
                                <span class="dashicons dashicons-admin-plugins" style="vertical-align: middle;"></span>
                                بررسی اتصال و اعتبار
                            </button>
                            <span id="test_connection_result" style="margin-right: 10px;"></span>
                            <p class="description">
                                با کلیک بر این دکمه، صحت نام کاربری و رمز عبور بررسی می‌شود و اعتبار باقیمانده نمایش داده می‌شود.<br>
                                <strong>توجه:</strong> برای استفاده از پیامک الگومحور، نیاز به شماره فرستنده ندارید.
                            </p>
                        </td>
                    </tr>
                </table>

                <h3>تنظیمات الگوی پیامک برای هر وضعیت</h3>
                <p class="description">برای هر وضعیت سفارش که می‌خواهید پیامک ارسال شود، تیک فعال را بزنید و کد الگو را وارد کنید.</p>
                
                <table class="form-table widefat" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 120px;">وضعیت سفارش</th>
                            <th style="width: 80px;">فعال</th>
                            <th>کد الگو (bodyId)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get status labels from SMS class
                        $status_labels = Tabesh_SMS::get_status_labels();
                        foreach ($status_labels as $status => $label) :
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td>
                                <input type="checkbox" 
                                       id="sms_status_<?php echo esc_attr($status); ?>_enabled" 
                                       name="sms_status_<?php echo esc_attr($status); ?>_enabled" 
                                       value="1" 
                                       <?php checked($admin->get_setting('sms_status_' . $status . '_enabled', '0'), '1'); ?>>
                            </td>
                            <td>
                                <input type="text" 
                                       id="sms_status_<?php echo esc_attr($status); ?>_pattern" 
                                       name="sms_status_<?php echo esc_attr($status); ?>_pattern" 
                                       value="<?php echo esc_attr($admin->get_setting('sms_status_' . $status . '_pattern')); ?>" 
                                       class="regular-text sms-pattern-input" 
                                       dir="ltr"
                                       pattern="[0-9]+"
                                       title="کد الگو باید فقط شامل اعداد باشد"
                                       placeholder="مثال: 12345 (فقط عدد)">
                                <p class="description" style="margin-top: 5px; color: #666;">
                                    <small>⚠️ فقط عدد وارد کنید، نه حروف یا کاراکترهای دیگر</small>
                                </p>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>تست ارسال پیامک</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="test_sms_phone">شماره موبایل تست</label></th>
                        <td>
                            <input type="text" id="test_sms_phone" class="regular-text" dir="ltr" placeholder="09123456789">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="test_sms_pattern">کد الگوی تست</label></th>
                        <td>
                            <input type="text" id="test_sms_pattern" class="regular-text sms-pattern-input" dir="ltr" 
                                   pattern="[0-9]+" 
                                   title="کد الگو باید فقط شامل اعداد باشد"
                                   placeholder="12345 (فقط عدد)">
                            <p class="description">کد الگویی که در بالا تعریف کرده‌اید را وارد کنید</p>
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <button type="button" id="test_sms_btn" class="button button-secondary">
                                <span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span>
                                ارسال پیامک تست
                            </button>
                            <span id="test_sms_result" style="margin-right: 10px;"></span>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3>پیامک ثبت‌نام کاربر توسط مدیر</h3>
                <p class="description">زمانی که مدیر کاربر جدیدی ایجاد می‌کند، این پیامک به کاربر ارسال می‌شود.</p>
                
                <table class="form-table">
                    <tr>
                        <th><label for="sms_admin_user_registration_enabled">فعال‌سازی</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="sms_admin_user_registration_enabled" 
                                       name="sms_admin_user_registration_enabled" value="1" 
                                       <?php checked($admin->get_setting('sms_admin_user_registration_enabled', '0'), '1'); ?>>
                                ارسال پیامک ثبت‌نام به کاربر جدید
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sms_admin_user_registration_pattern">کد الگو</label></th>
                        <td>
                            <input type="text" id="sms_admin_user_registration_pattern" 
                                   name="sms_admin_user_registration_pattern" 
                                   value="<?php echo esc_attr($admin->get_setting('sms_admin_user_registration_pattern')); ?>" 
                                   class="regular-text sms-pattern-input" 
                                   dir="ltr"
                                   pattern="[0-9]+"
                                   title="کد الگو باید فقط شامل اعداد باشد"
                                   placeholder="مثال: 12345 (فقط عدد)">
                        </td>
                    </tr>
                </table>

                <h4>تنظیم متغیرهای الگو</h4>
                <p class="description">متغیرهای مورد نیاز را انتخاب کنید و ترتیب آن‌ها را مشخص کنید (1، 2، 3، ...)</p>
                
                <div class="sms-variables-config">
                    <?php
                    $pattern_type = 'admin_user_registration';
                    $available_vars = Tabesh_SMS::get_available_variables($pattern_type);
                    $config = $admin->get_setting('sms_pattern_vars_' . $pattern_type, array());
                    $config = is_array($config) ? $config : json_decode($config, true);
                    $config = is_array($config) ? $config : array();
                    
                    foreach ($available_vars as $var_key => $var_info) :
                        $is_enabled = isset($config[$var_key]['enabled']) ? $config[$var_key]['enabled'] : true;
                        $order = isset($config[$var_key]['order']) ? $config[$var_key]['order'] : 1;
                    ?>
                    <div class="sms-variable-item">
                        <label>
                            <input type="checkbox" 
                                   name="sms_pattern_vars_<?php echo esc_attr($pattern_type); ?>[<?php echo esc_attr($var_key); ?>][enabled]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>>
                            <strong><?php echo esc_html($var_info['label']); ?></strong>
                            <code>%<?php echo esc_html($var_info['placeholder']); ?>%</code>
                        </label>
                        <input type="number" 
                               name="sms_pattern_vars_<?php echo esc_attr($pattern_type); ?>[<?php echo esc_attr($var_key); ?>][order]" 
                               value="<?php echo esc_attr($order); ?>" 
                               min="1" 
                               max="10" 
                               class="small-text"
                               placeholder="ترتیب">
                        <span class="description"><?php echo esc_html($var_info['description']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <hr style="margin: 30px 0;">

                <h3>پیامک ثبت سفارش توسط مدیر</h3>
                <p class="description">زمانی که مدیر سفارش جدیدی برای مشتری ثبت می‌کند، این پیامک به مشتری ارسال می‌شود.</p>
                
                <table class="form-table">
                    <tr>
                        <th><label for="sms_admin_order_created_enabled">فعال‌سازی</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="sms_admin_order_created_enabled" 
                                       name="sms_admin_order_created_enabled" value="1" 
                                       <?php checked($admin->get_setting('sms_admin_order_created_enabled', '0'), '1'); ?>>
                                ارسال پیامک ثبت سفارش به مشتری
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sms_admin_order_created_pattern">کد الگو</label></th>
                        <td>
                            <input type="text" id="sms_admin_order_created_pattern" 
                                   name="sms_admin_order_created_pattern" 
                                   value="<?php echo esc_attr($admin->get_setting('sms_admin_order_created_pattern')); ?>" 
                                   class="regular-text sms-pattern-input" 
                                   dir="ltr"
                                   pattern="[0-9]+"
                                   title="کد الگو باید فقط شامل اعداد باشد"
                                   placeholder="مثال: 12345 (فقط عدد)">
                        </td>
                    </tr>
                </table>

                <h4>تنظیم متغیرهای الگو</h4>
                <p class="description">متغیرهای مورد نیاز را انتخاب کنید و ترتیب آن‌ها را مشخص کنید (1، 2، 3، ...)</p>
                
                <div class="sms-variables-config">
                    <?php
                    $pattern_type = 'admin_order_created';
                    $available_vars = Tabesh_SMS::get_available_variables($pattern_type);
                    $config = $admin->get_setting('sms_pattern_vars_' . $pattern_type, array());
                    $config = is_array($config) ? $config : json_decode($config, true);
                    $config = is_array($config) ? $config : array();
                    
                    foreach ($available_vars as $var_key => $var_info) :
                        $is_enabled = isset($config[$var_key]['enabled']) ? $config[$var_key]['enabled'] : true;
                        $order = isset($config[$var_key]['order']) ? $config[$var_key]['order'] : 1;
                    ?>
                    <div class="sms-variable-item">
                        <label>
                            <input type="checkbox" 
                                   name="sms_pattern_vars_<?php echo esc_attr($pattern_type); ?>[<?php echo esc_attr($var_key); ?>][enabled]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>>
                            <strong><?php echo esc_html($var_info['label']); ?></strong>
                            <code>%<?php echo esc_html($var_info['placeholder']); ?>%</code>
                        </label>
                        <input type="number" 
                               name="sms_pattern_vars_<?php echo esc_attr($pattern_type); ?>[<?php echo esc_attr($var_key); ?>][order]" 
                               value="<?php echo esc_attr($order); ?>" 
                               min="1" 
                               max="10" 
                               class="small-text"
                               placeholder="ترتیب">
                        <span class="description"><?php echo esc_html($var_info['description']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <hr style="margin: 30px 0;">

                <h3>تنظیم متغیرهای الگوی تغییر وضعیت</h3>
                <p class="description">برای الگوهای تغییر وضعیت سفارش، می‌توانید متغیرها و ترتیب آن‌ها را مشخص کنید.</p>
                
                <div class="sms-variables-config">
                    <?php
                    $pattern_type = 'status_change';
                    $available_vars = Tabesh_SMS::get_available_variables($pattern_type);
                    $config = $admin->get_setting('sms_pattern_vars_' . $pattern_type, array());
                    $config = is_array($config) ? $config : json_decode($config, true);
                    $config = is_array($config) ? $config : array();
                    
                    foreach ($available_vars as $var_key => $var_info) :
                        $is_enabled = isset($config[$var_key]['enabled']) ? $config[$var_key]['enabled'] : true;
                        $order = isset($config[$var_key]['order']) ? $config[$var_key]['order'] : 1;
                    ?>
                    <div class="sms-variable-item">
                        <label>
                            <input type="checkbox" 
                                   name="sms_pattern_vars_<?php echo esc_attr($pattern_type); ?>[<?php echo esc_attr($var_key); ?>][enabled]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>>
                            <strong><?php echo esc_html($var_info['label']); ?></strong>
                            <code>%<?php echo esc_html($var_info['placeholder']); ?>%</code>
                        </label>
                        <input type="number" 
                               name="sms_pattern_vars_<?php echo esc_attr($pattern_type); ?>[<?php echo esc_attr($var_key); ?>][order]" 
                               value="<?php echo esc_attr($order); ?>" 
                               min="1" 
                               max="10" 
                               class="small-text"
                               placeholder="ترتیب">
                        <span class="description"><?php echo esc_html($var_info['description']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <hr style="margin: 30px 0;">

                <div class="notice notice-info">
                    <p><strong>📱 راهنمای متغیرهای الگو:</strong></p>
                    <p>الگوی شما در ملیپیامک باید شامل متغیرهای انتخاب شده باشد (به ترتیبی که مشخص کرده‌اید):</p>
                    <ul style="margin-right: 20px;">
                        <li>✅ متغیرهای فعال شده را در الگوی خود قرار دهید</li>
                        <li>🔢 ترتیب متغیرها در الگوی ملیپیامک باید با شماره‌های تعیین شده مطابقت داشته باشد</li>
                        <li>⚠️ اگر متغیری را غیرفعال کردید، آن را در الگوی ملیپیامک قرار ندهید</li>
                    </ul>
                    <p><strong>نمونه الگوی تغییر وضعیت:</strong> <code>سفارش شماره %order_number% برای %customer_name% به وضعیت %status% تغییر کرد. تاریخ: %date%</code></p>
                    <p><strong>نمونه الگوی ثبت‌نام:</strong> <code>%user_name% عزیز، ثبت‌نام شما با موفقیت انجام شد. شماره موبایل: %mobile%</code></p>
                    <p><strong>نمونه الگوی ثبت سفارش:</strong> <code>سفارش شماره %order_number% برای کتاب "%book_title%" با تیراژ %quantity% ثبت شد. قیمت: %total_price% ریال</code></p>
                </div>
            </div>

            <!-- Staff Access Control Settings -->
            <div id="tab-staff-access" class="tabesh-tab-content">
                <h2>دسترسی پنل کارمندان</h2>

                <div class="notice notice-info">
                    <p>
                        <strong>👥 راهنما:</strong> در این بخش می‌توانید کاربرانی که مجاز به مشاهده و استفاده از شورت‌کد 
                        <code>[tabesh_staff_panel]</code> هستند را تعیین کنید.
                    </p>
                    <p>
                        <strong>⚠️ توجه:</strong> اگر هیچ کاربری انتخاب نشده باشد، فقط مدیران سایت به پنل دسترسی خواهند داشت (رفتار پیش‌فرض).
                    </p>
                </div>

                <h3>جستجو و افزودن کاربر</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="staff_user_search">جستجوی کاربران</label></th>
                        <td>
                            <input type="text" id="staff_user_search" class="regular-text" placeholder="نام کاربری، نام نمایشی یا ایمیل...">
                            <button type="button" id="staff_user_search_btn" class="button button-secondary">
                                <span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
                                جستجو
                            </button>
                            <div id="staff_user_search_results" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>

                <h3>کاربران دارای دسترسی</h3>
                <div id="staff_allowed_users_list">
                    <?php
                    $allowed_users = $admin->get_setting('staff_allowed_users', array());
                    if (!is_array($allowed_users)) {
                        $allowed_users = array();
                    }
                    
                    if (empty($allowed_users)) :
                    ?>
                    <p class="description" id="no_staff_users_msg">هنوز هیچ کاربری انتخاب نشده است. فقط مدیران سایت به پنل کارمندان دسترسی دارند.</p>
                    <?php else : ?>
                    <table class="widefat striped" id="staff_users_table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>نام نمایشی</th>
                                <th>ایمیل</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allowed_users as $user_id) :
                                $user = get_userdata($user_id);
                                if (!$user) continue;
                            ?>
                            <tr data-user-id="<?php echo esc_attr($user_id); ?>">
                                <td><?php echo esc_html($user_id); ?></td>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <button type="button" class="button button-small staff-remove-user" data-user-id="<?php echo esc_attr($user_id); ?>">
                                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                                        حذف
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Hidden input to store selected user IDs -->
                <input type="hidden" id="staff_allowed_users" name="staff_allowed_users" 
                       value="<?php echo esc_attr(implode(',', $allowed_users)); ?>">

                <hr style="margin: 30px 0;">

                <h2>دسترسی شورتکد مدیریت سفارشات ادمین</h2>

                <div class="notice notice-info">
                    <p>
                        <strong>👥 راهنما:</strong> در این بخش می‌توانید کاربرانی که مجاز به مشاهده و استفاده از شورت‌کد 
                        <code>[tabesh_admin_dashboard]</code> هستند را تعیین کنید.
                    </p>
                    <p>
                        <strong>⚠️ توجه:</strong> اگر هیچ کاربری انتخاب نشده باشد، فقط مدیران سایت (با دسترسی <code>manage_woocommerce</code>) به این پنل دسترسی خواهند داشت. سایر کاربران فقط سفارشات خود را میبینند.
                    </p>
                </div>

                <h3>جستجو و افزودن کاربر</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="admin_dashboard_user_search">جستجوی کاربران</label></th>
                        <td>
                            <input type="text" id="admin_dashboard_user_search" class="regular-text" placeholder="نام کاربری، نام نمایشی یا ایمیل...">
                            <button type="button" id="admin_dashboard_user_search_btn" class="button button-secondary">
                                <span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
                                جستجو
                            </button>
                            <div id="admin_dashboard_user_search_results" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>

                <h3>کاربران دارای دسترسی</h3>
                <div id="admin_dashboard_allowed_users_list">
                    <?php
                    $admin_dashboard_allowed_users = $admin->get_setting('admin_dashboard_allowed_users', array());
                    if (!is_array($admin_dashboard_allowed_users)) {
                        $admin_dashboard_allowed_users = array();
                    }
                    
                    if (empty($admin_dashboard_allowed_users)) :
                    ?>
                    <p class="description" id="no_admin_dashboard_users_msg">هنوز هیچ کاربری انتخاب نشده است. فقط مدیران سایت به شورتکد مدیریت سفارشات ادمین دسترسی دارند.</p>
                    <?php else : ?>
                    <table class="widefat striped" id="admin_dashboard_users_table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>نام نمایشی</th>
                                <th>ایمیل</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admin_dashboard_allowed_users as $user_id) :
                                $user = get_userdata($user_id);
                                if (!$user) continue;
                            ?>
                            <tr data-user-id="<?php echo esc_attr($user_id); ?>">
                                <td><?php echo esc_html($user_id); ?></td>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <button type="button" class="button button-small admin-dashboard-remove-user" data-user-id="<?php echo esc_attr($user_id); ?>">
                                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                                        حذف
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Hidden input to store selected user IDs -->
                <input type="hidden" id="admin_dashboard_allowed_users" name="admin_dashboard_allowed_users" 
                       value="<?php echo esc_attr(implode(',', $admin_dashboard_allowed_users)); ?>">

                <hr style="margin: 30px 0;">

                <h2>دسترسی شورتکد فرم سفارش مدیر</h2>

                <div class="notice notice-info">
                    <p>
                        <strong>📝 راهنما:</strong> در این بخش می‌توانید کاربرانی که مجاز به مشاهده و استفاده از شورت‌کد 
                        <code>[tabesh_admin_order_form]</code> هستند را تعیین کنید.
                    </p>
                    <p>
                        <strong>⚠️ توجه:</strong> کاربرانی با نقش مدیر (administrator) همیشه دسترسی دارند. شما می‌توانید نقش‌های دیگر یا کاربران خاص را نیز اضافه کنید.
                    </p>
                </div>

                <h3>نقش‌های مجاز</h3>
                <table class="form-table">
                    <tr>
                        <th><label>انتخاب نقش‌های مجاز</label></th>
                        <td>
                            <div class="tabesh-roles-checkboxes">
                                <?php
                                $admin_order_form_allowed_roles = $admin->get_setting('admin_order_form_allowed_roles', array('administrator'));
                                if (!is_array($admin_order_form_allowed_roles)) {
                                    $admin_order_form_allowed_roles = array('administrator');
                                }
                                $all_roles = Tabesh_Admin_Order_Form::get_available_roles();
                                foreach ($all_roles as $role_key => $role_name) :
                                ?>
                                <label style="display: block; margin-bottom: 10px;">
                                    <input type="checkbox" 
                                           name="admin_order_form_allowed_roles[]" 
                                           value="<?php echo esc_attr($role_key); ?>"
                                           <?php checked(in_array($role_key, $admin_order_form_allowed_roles, true)); ?>>
                                    <?php echo esc_html($role_name); ?>
                                    <?php if ($role_key === 'administrator') : ?>
                                        <em style="color: #6b7280;">(همیشه دسترسی دارد)</em>
                                    <?php endif; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <h3>جستجو و افزودن کاربر</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="admin_order_form_user_search">جستجوی کاربران</label></th>
                        <td>
                            <input type="text" id="admin_order_form_user_search" class="regular-text" placeholder="نام کاربری، نام نمایشی یا ایمیل...">
                            <button type="button" id="admin_order_form_user_search_btn" class="button button-secondary">
                                <span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
                                جستجو
                            </button>
                            <div id="admin_order_form_user_search_results" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>

                <h3>کاربران دارای دسترسی</h3>
                <div id="admin_order_form_allowed_users_list">
                    <?php
                    $admin_order_form_allowed_users = $admin->get_setting('admin_order_form_allowed_users', array());
                    if (!is_array($admin_order_form_allowed_users)) {
                        $admin_order_form_allowed_users = array();
                    }
                    
                    if (empty($admin_order_form_allowed_users)) :
                    ?>
                    <p class="description" id="no_admin_order_form_users_msg">هنوز هیچ کاربر خاصی انتخاب نشده است. فقط کاربران با نقش‌های مجاز بالا به فرم سفارش مدیر دسترسی دارند.</p>
                    <?php else : ?>
                    <table class="widefat striped" id="admin_order_form_users_table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>نام نمایشی</th>
                                <th>ایمیل</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admin_order_form_allowed_users as $user_id) :
                                $user = get_userdata($user_id);
                                if (!$user) continue;
                            ?>
                            <tr data-user-id="<?php echo esc_attr($user_id); ?>">
                                <td><?php echo esc_html($user_id); ?></td>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <button type="button" class="button button-small admin-order-form-remove-user" data-user-id="<?php echo esc_attr($user_id); ?>">
                                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                                        حذف
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Hidden input to store selected user IDs -->
                <input type="hidden" id="admin_order_form_allowed_users" name="admin_order_form_allowed_users" 
                       value="<?php echo esc_attr(implode(',', $admin_order_form_allowed_users)); ?>">
            </div>

            <!-- Export/Import Tab -->
            <div id="tab-export-import" class="tabesh-tab-content">
                <h2>برونبری و درونریزی</h2>

                <div class="notice notice-info">
                    <p><strong>ℹ️ راهنما:</strong></p>
                    <ul style="margin-right: 20px;">
                        <li>✅ از این بخش می‌توانید پشتیبان کامل از اطلاعات افزونه تهیه کنید</li>
                        <li>📥 برونبری: بخش‌های مورد نظر را انتخاب کرده و دکمه برونبری را کلیک کنید</li>
                        <li>📤 درونریزی: فایل پشتیبان را انتخاب کرده و حالت درونریزی را مشخص کنید</li>
                        <li>🔀 حالت ادغام: داده‌های جدید را با داده‌های موجود ترکیب می‌کند</li>
                        <li>🔄 حالت جایگزینی: داده‌های موجود را حذف کرده و فقط داده‌های جدید را وارد می‌کند</li>
                        <li>⚠️ توجه: قبل از درونریزی با حالت جایگزینی، حتماً پشتیبان تهیه کنید</li>
                    </ul>
                </div>

                <!-- Export Section -->
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <h3>📥 برونبری (Export)</h3>
                    
                    <p><strong>بخش‌های مورد نظر برای برونبری را انتخاب کنید:</strong></p>
                    
                    <div style="margin: 15px 0;">
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" id="export_all_sections" style="margin-left: 5px;">
                            <strong>انتخاب همه</strong>
                        </label>
                        
                        <?php
                        $available_sections = $tabesh->export_import->get_available_sections();
                        foreach ($available_sections as $key => $label) :
                        ?>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="export-section-checkbox" 
                                   name="export_sections[]" 
                                   value="<?php echo esc_attr($key); ?>" 
                                   style="margin-left: 5px;">
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div id="export-preview" style="margin: 15px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; display: none;">
                        <strong>پیش‌نمایش:</strong>
                        <div id="export-preview-content" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button type="button" id="show-export-preview" class="button">
                            👁️ نمایش پیش‌نمایش
                        </button>
                        <button type="button" id="export-data-btn" class="button button-primary">
                            📥 برونبری داده‌ها
                        </button>
                        <span id="export-status" style="margin-right: 10px;"></span>
                    </div>
                </div>

                <!-- Import Section -->
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <h3>📤 درونریزی (Import)</h3>
                    
                    <div style="margin: 15px 0;">
                        <label for="import-file" style="display: block; margin-bottom: 10px;">
                            <strong>فایل پشتیبان را انتخاب کنید:</strong>
                        </label>
                        <input type="file" id="import-file" accept=".json,application/json" 
                               style="margin-bottom: 15px;">
                    </div>
                    
                    <div id="import-preview" style="margin: 15px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; display: none;">
                        <strong>اطلاعات فایل:</strong>
                        <div id="import-preview-content" style="margin-top: 10px;"></div>
                        
                        <div style="margin-top: 15px;">
                            <p><strong>بخش‌های موجود در فایل - انتخاب کنید کدام‌ها درونریزی شوند:</strong></p>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="checkbox" id="import_all_sections" style="margin-left: 5px;">
                                <strong>انتخاب همه</strong>
                            </label>
                            <div id="import-sections-list"></div>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <p><strong>حالت درونریزی:</strong></p>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="import_mode" value="merge" checked style="margin-left: 5px;">
                                ادغام با داده‌های موجود
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="import_mode" value="replace" style="margin-left: 5px;">
                                جایگزینی کامل (حذف داده‌های موجود)
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button type="button" id="validate-import-btn" class="button">
                            🔍 بررسی فایل
                        </button>
                        <button type="button" id="import-data-btn" class="button button-primary" disabled>
                            📤 درونریزی داده‌ها
                        </button>
                        <span id="import-status" style="margin-right: 10px;"></span>
                    </div>
                </div>

                <!-- Cleanup Section -->
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <h3>🗑️ حذف و پاکسازی</h3>
                    
                    <div class="notice notice-warning" style="margin: 15px 0;">
                        <p><strong>⚠️ هشدار:</strong> عملیات‌های این بخش قابل بازگشت نیستند. قبل از انجام هر عملیات، حتماً از داده‌های خود پشتیبان تهیه کنید.</p>
                    </div>

                    <!-- Preview Statistics -->
                    <div id="cleanup-preview" style="margin: 15px 0; padding: 15px; background: #f5f5f5; border-radius: 4px; display: none;">
                        <strong>آمار فعلی:</strong>
                        <div id="cleanup-preview-content" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <button type="button" id="show-cleanup-preview" class="button">
                            📊 نمایش آمار
                        </button>
                    </div>

                    <!-- Orders Cleanup -->
                    <div style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
                        <h4>حذف سفارشات</h4>
                        <div style="margin: 10px 0;">
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="cleanup_orders_all" style="margin-left: 5px;">
                                حذف همه سفارشات
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="cleanup_orders_archived" style="margin-left: 5px;">
                                حذف فقط سفارشات بایگانی شده
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                حذف سفارشات قدیمی‌تر از 
                                <input type="number" id="cleanup_orders_days" min="0" placeholder="روز" style="width: 80px; margin: 0 5px;">
                                روز
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                حذف سفارشات کاربر با شناسه:
                                <input type="number" id="cleanup_orders_user_id" min="0" placeholder="User ID" style="width: 100px; margin-right: 5px;">
                            </label>
                        </div>
                        <button type="button" id="cleanup-orders-btn" class="button">
                            🗑️ حذف سفارشات
                        </button>
                        <span id="cleanup-orders-status" style="margin-right: 10px;"></span>
                    </div>

                    <!-- Files Cleanup -->
                    <div style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
                        <h4>حذف فایل‌ها</h4>
                        <div style="margin: 10px 0;">
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="cleanup_files_database" style="margin-left: 5px;">
                                حذف رکوردهای فایل از دیتابیس
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="cleanup_files_physical" style="margin-left: 5px;">
                                حذف فایل‌های فیزیکی از سرور
                            </label>
                        </div>
                        <button type="button" id="cleanup-files-btn" class="button">
                            🗑️ حذف فایل‌ها
                        </button>
                        <button type="button" id="cleanup-orphan-files-btn" class="button">
                            🧹 حذف فایل‌های یتیم
                        </button>
                        <span id="cleanup-files-status" style="margin-right: 10px;"></span>
                    </div>

                    <!-- Logs Cleanup -->
                    <div style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
                        <h4>حذف لاگ‌ها</h4>
                        <div style="margin: 10px 0;">
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="cleanup_logs_type" value="all" checked style="margin-left: 5px;">
                                همه لاگ‌ها (عادی و امنیتی)
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="cleanup_logs_type" value="regular" style="margin-left: 5px;">
                                فقط لاگ‌های عادی
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="cleanup_logs_type" value="security" style="margin-left: 5px;">
                                فقط لاگ‌های امنیتی
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                حذف لاگ‌های قدیمی‌تر از 
                                <input type="number" id="cleanup_logs_days" min="0" placeholder="روز" style="width: 80px; margin: 0 5px;">
                                روز
                            </label>
                        </div>
                        <button type="button" id="cleanup-logs-btn" class="button">
                            🗑️ حذف لاگ‌ها
                        </button>
                        <span id="cleanup-logs-status" style="margin-right: 10px;"></span>
                    </div>

                    <!-- Settings Reset -->
                    <div style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
                        <h4>بازگردانی تنظیمات</h4>
                        <p>تنظیمات افزونه را به حالت پیش‌فرض بازگردانی می‌کند (سفارشات و فایل‌ها حفظ می‌شوند).</p>
                        <button type="button" id="reset-settings-btn" class="button">
                            🔄 بازگردانی تنظیمات
                        </button>
                        <span id="reset-settings-status" style="margin-right: 10px;"></span>
                    </div>

                    <!-- Factory Reset -->
                    <div style="padding: 15px; border: 2px solid #dc3232; border-radius: 4px; margin-bottom: 15px; background: #fff8f8;">
                        <h4 style="color: #dc3232;">💀 ریست کامل (Factory Reset)</h4>
                        <div class="notice notice-error" style="margin: 10px 0;">
                            <p><strong>⛔ خطر:</strong> این عملیات تمام داده‌های افزونه را حذف می‌کند:</p>
                            <ul style="margin-right: 20px;">
                                <li>✖ تمام سفارشات</li>
                                <li>✖ تمام فایل‌ها (دیتابیس و فیزیکی)</li>
                                <li>✖ تمام لاگ‌ها</li>
                                <li>✖ تمام تنظیمات</li>
                            </ul>
                            <p><strong>این عملیات قابل بازگشت نیست!</strong></p>
                        </div>
                        <div style="margin: 15px 0;">
                            <label style="display: block; margin-bottom: 10px;">
                                <strong>برای تأیید، کلمه <code style="background: #dc3232; color: white; padding: 2px 6px;">RESET</code> را تایپ کنید:</strong>
                            </label>
                            <input type="text" id="factory-reset-confirm" placeholder="RESET" style="width: 150px; margin-left: 10px;">
                        </div>
                        <button type="button" id="factory-reset-btn" class="button button-danger" style="background: #dc3232; border-color: #dc3232; color: white;">
                            💀 ریست کامل
                        </button>
                        <span id="factory-reset-status" style="margin-right: 10px;"></span>
                    </div>
                </div>
            </div>

        <p class="submit">
            <input type="submit" name="tabesh_save_settings" class="button button-primary" value="ذخیره تنظیمات">
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Configuration passed from PHP
    var tabeshAdminConfig = {
        smsTestUrl: <?php echo wp_json_encode(esc_url_raw(rest_url(TABESH_REST_NAMESPACE . '/sms/test'))); ?>,
        smsTestConnectionUrl: <?php echo wp_json_encode(esc_url_raw(rest_url(TABESH_REST_NAMESPACE . '/sms/test-connection'))); ?>,
        usersSearchUrl: <?php echo wp_json_encode(esc_url_raw(rest_url(TABESH_REST_NAMESPACE . '/users/search'))); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>
    };
    
    // Helper function to escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Test SMS connection functionality
    $('#test_connection_btn').on('click', function() {
        var $result = $('#test_connection_result');
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $result.html('<span style="color: #666;">در حال بررسی اتصال...</span>');
        
        $.ajax({
            url: tabeshAdminConfig.smsTestConnectionUrl,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': tabeshAdminConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: green; font-weight: bold;">✓ ' + escapeHtml(response.message) + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + escapeHtml(response.message) + '</span>');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'خطا در اتصال به سرور';
                $result.html('<span style="color: red;">✗ ' + escapeHtml(msg) + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Test SMS functionality
    $('#test_sms_btn').on('click', function() {
        var phone = $('#test_sms_phone').val().trim();
        var pattern = $('#test_sms_pattern').val().trim();
        var $result = $('#test_sms_result');
        var $btn = $(this);
        
        if (!phone || !pattern) {
            $result.html('<span style="color: red;">لطفاً شماره موبایل و کد الگو را وارد کنید</span>');
            return;
        }
        
        $btn.prop('disabled', true);
        $result.html('<span style="color: #666;">در حال ارسال...</span>');
        
        $.ajax({
            url: tabeshAdminConfig.smsTestUrl,
            method: 'POST',
            data: JSON.stringify({
                phone: phone,
                pattern_code: pattern
            }),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': tabeshAdminConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response.message + '</span>');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'خطا در ارسال پیامک';
                $result.html('<span style="color: red;">✗ ' + msg + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Staff access control functionality
    var allowedUsers = $('#staff_allowed_users').val() ? $('#staff_allowed_users').val().split(',').map(Number).filter(Boolean) : [];
    
    // Search users
    $('#staff_user_search_btn').on('click', function() {
        var search = $('#staff_user_search').val().trim();
        var $results = $('#staff_user_search_results');
        
        if (search.length < 2) {
            $results.html('<p style="color: red;">حداقل ۲ کاراکتر وارد کنید</p>');
            return;
        }
        
        $results.html('<p style="color: #666;">در حال جستجو...</p>');
        
        $.ajax({
            url: tabeshAdminConfig.usersSearchUrl,
            method: 'GET',
            data: { search: search },
            headers: {
                'X-WP-Nonce': tabeshAdminConfig.nonce
            },
            success: function(response) {
                if (response.success && response.users.length > 0) {
                    var html = '<ul style="list-style: none; padding: 0; margin: 0;">';
                    response.users.forEach(function(user) {
                        var isAdded = allowedUsers.indexOf(user.id) !== -1;
                        html += '<li style="padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">';
                        html += '<span><strong>' + escapeHtml(user.display_name) + '</strong> (' + escapeHtml(user.user_email) + ')</span>';
                        if (isAdded) {
                            html += '<span style="color: green;">✓ افزوده شده</span>';
                        } else {
                            html += '<button type="button" class="button button-small staff-add-user" data-user-id="' + user.id + '" data-user-name="' + escapeHtml(user.display_name) + '" data-user-email="' + escapeHtml(user.user_email) + '">افزودن</button>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                    $results.html(html);
                } else {
                    $results.html('<p>کاربری یافت نشد</p>');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'خطا در جستجو';
                $results.html('<p style="color: red;">' + escapeHtml(msg) + '</p>');
            }
        });
    });
    
    // Enter key to search
    $('#staff_user_search').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#staff_user_search_btn').click();
        }
    });
    
    // Add user to allowed list
    $(document).on('click', '.staff-add-user', function() {
        var userId = parseInt($(this).data('user-id'));
        var userName = $(this).data('user-name');
        var userEmail = $(this).data('user-email');
        
        if (allowedUsers.indexOf(userId) === -1) {
            allowedUsers.push(userId);
            updateAllowedUsersList();
            addUserToTable(userId, userName, userEmail);
        }
        
        $(this).replaceWith('<span style="color: green;">✓ افزوده شده</span>');
    });
    
    // Remove user from allowed list
    $(document).on('click', '.staff-remove-user', function() {
        var userId = parseInt($(this).data('user-id'));
        var index = allowedUsers.indexOf(userId);
        
        if (index !== -1) {
            allowedUsers.splice(index, 1);
            updateAllowedUsersList();
        }
        
        $(this).closest('tr').fadeOut(300, function() {
            $(this).remove();
            if ($('#staff_users_table tbody tr').length === 0) {
                $('#staff_users_table').remove();
                $('#staff_allowed_users_list').html('<p class="description" id="no_staff_users_msg">هنوز هیچ کاربری انتخاب نشده است. فقط مدیران سایت به پنل کارمندان دسترسی دارند.</p>');
            }
        });
    });
    
    function updateAllowedUsersList() {
        $('#staff_allowed_users').val(allowedUsers.join(','));
    }
    
    function addUserToTable(userId, userName, userEmail) {
        var $table = $('#staff_users_table');
        var $noMsg = $('#no_staff_users_msg');
        
        if ($table.length === 0) {
            $noMsg.remove();
            var tableHtml = '<table class="widefat striped" id="staff_users_table">' +
                '<thead><tr><th>شناسه</th><th>نام نمایشی</th><th>ایمیل</th><th>عملیات</th></tr></thead>' +
                '<tbody></tbody></table>';
            $('#staff_allowed_users_list').html(tableHtml);
            $table = $('#staff_users_table');
        }
        
        var rowHtml = '<tr data-user-id="' + userId + '">' +
            '<td>' + userId + '</td>' +
            '<td>' + escapeHtml(userName) + '</td>' +
            '<td>' + escapeHtml(userEmail) + '</td>' +
            '<td><button type="button" class="button button-small staff-remove-user" data-user-id="' + userId + '">' +
            '<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> حذف</button></td>' +
            '</tr>';
        
        $table.find('tbody').append(rowHtml);
    }

    // Admin Dashboard access control functionality
    var adminDashboardAllowedUsers = $('#admin_dashboard_allowed_users').val() ? $('#admin_dashboard_allowed_users').val().split(',').map(Number).filter(Boolean) : [];
    
    // Search users for admin dashboard
    $('#admin_dashboard_user_search_btn').on('click', function() {
        var search = $('#admin_dashboard_user_search').val().trim();
        var $results = $('#admin_dashboard_user_search_results');
        
        if (search.length < 2) {
            $results.html('<p style="color: red;">حداقل ۲ کاراکتر وارد کنید</p>');
            return;
        }
        
        $results.html('<p style="color: #666;">در حال جستجو...</p>');
        
        $.ajax({
            url: tabeshAdminConfig.usersSearchUrl,
            method: 'GET',
            data: { search: search },
            headers: {
                'X-WP-Nonce': tabeshAdminConfig.nonce
            },
            success: function(response) {
                if (response.success && response.users.length > 0) {
                    var html = '<ul style="list-style: none; padding: 0; margin: 0;">';
                    response.users.forEach(function(user) {
                        var isAdded = adminDashboardAllowedUsers.indexOf(user.id) !== -1;
                        html += '<li style="padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">';
                        html += '<span><strong>' + escapeHtml(user.display_name) + '</strong> (' + escapeHtml(user.user_email) + ')</span>';
                        if (isAdded) {
                            html += '<span style="color: green;">✓ افزوده شده</span>';
                        } else {
                            html += '<button type="button" class="button button-small admin-dashboard-add-user" data-user-id="' + user.id + '" data-user-name="' + escapeHtml(user.display_name) + '" data-user-email="' + escapeHtml(user.user_email) + '">افزودن</button>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                    $results.html(html);
                } else {
                    $results.html('<p>کاربری یافت نشد</p>');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'خطا در جستجو';
                $results.html('<p style="color: red;">' + escapeHtml(msg) + '</p>');
            }
        });
    });
    
    // Enter key to search for admin dashboard users
    $('#admin_dashboard_user_search').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#admin_dashboard_user_search_btn').click();
        }
    });
    
    // Add user to admin dashboard allowed list
    $(document).on('click', '.admin-dashboard-add-user', function() {
        var userId = parseInt($(this).data('user-id'));
        var userName = $(this).data('user-name');
        var userEmail = $(this).data('user-email');
        
        if (adminDashboardAllowedUsers.indexOf(userId) === -1) {
            adminDashboardAllowedUsers.push(userId);
            updateAdminDashboardAllowedUsersList();
            addUserToAdminDashboardTable(userId, userName, userEmail);
        }
        
        $(this).replaceWith('<span style="color: green;">✓ افزوده شده</span>');
    });
    
    // Remove user from admin dashboard allowed list
    $(document).on('click', '.admin-dashboard-remove-user', function() {
        var userId = parseInt($(this).data('user-id'));
        var index = adminDashboardAllowedUsers.indexOf(userId);
        
        if (index !== -1) {
            adminDashboardAllowedUsers.splice(index, 1);
            updateAdminDashboardAllowedUsersList();
        }
        
        $(this).closest('tr').fadeOut(300, function() {
            $(this).remove();
            if ($('#admin_dashboard_users_table tbody tr').length === 0) {
                $('#admin_dashboard_users_table').remove();
                $('#admin_dashboard_allowed_users_list').html('<p class="description" id="no_admin_dashboard_users_msg">هنوز هیچ کاربری انتخاب نشده است. فقط مدیران سایت به شورتکد مدیریت سفارشات ادمین دسترسی دارند.</p>');
            }
        });
    });
    
    function updateAdminDashboardAllowedUsersList() {
        $('#admin_dashboard_allowed_users').val(adminDashboardAllowedUsers.join(','));
    }
    
    function addUserToAdminDashboardTable(userId, userName, userEmail) {
        var $table = $('#admin_dashboard_users_table');
        var $noMsg = $('#no_admin_dashboard_users_msg');
        
        if ($table.length === 0) {
            $noMsg.remove();
            var tableHtml = '<table class="widefat striped" id="admin_dashboard_users_table">' +
                '<thead><tr><th>شناسه</th><th>نام نمایشی</th><th>ایمیل</th><th>عملیات</th></tr></thead>' +
                '<tbody></tbody></table>';
            $('#admin_dashboard_allowed_users_list').html(tableHtml);
            $table = $('#admin_dashboard_users_table');
        }
        
        var rowHtml = '<tr data-user-id="' + userId + '">' +
            '<td>' + userId + '</td>' +
            '<td>' + escapeHtml(userName) + '</td>' +
            '<td>' + escapeHtml(userEmail) + '</td>' +
            '<td><button type="button" class="button button-small admin-dashboard-remove-user" data-user-id="' + userId + '">' +
            '<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> حذف</button></td>' +
            '</tr>';
        
        $table.find('tbody').append(rowHtml);
    }

    // Admin Order Form access control functionality
    var adminOrderFormAllowedUsers = $('#admin_order_form_allowed_users').val() ? $('#admin_order_form_allowed_users').val().split(',').map(Number).filter(Boolean) : [];
    
    // Search users for admin order form
    $('#admin_order_form_user_search_btn').on('click', function() {
        var search = $('#admin_order_form_user_search').val().trim();
        var $results = $('#admin_order_form_user_search_results');
        
        if (search.length < 2) {
            $results.html('<p style="color: red;">حداقل ۲ کاراکتر وارد کنید</p>');
            return;
        }
        
        $results.html('<p style="color: #666;">در حال جستجو...</p>');
        
        $.ajax({
            url: tabeshAdminConfig.usersSearchUrl,
            method: 'GET',
            data: { search: search },
            headers: {
                'X-WP-Nonce': tabeshAdminConfig.nonce
            },
            success: function(response) {
                if (response.success && response.users.length > 0) {
                    var html = '<ul style="list-style: none; padding: 0; margin: 0;">';
                    response.users.forEach(function(user) {
                        var isAdded = adminOrderFormAllowedUsers.indexOf(user.id) !== -1;
                        html += '<li style="padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">';
                        html += '<span><strong>' + escapeHtml(user.display_name) + '</strong> (' + escapeHtml(user.user_email) + ')</span>';
                        if (isAdded) {
                            html += '<span style="color: green;">✓ افزوده شده</span>';
                        } else {
                            html += '<button type="button" class="button button-small admin-order-form-add-user" data-user-id="' + user.id + '" data-user-name="' + escapeHtml(user.display_name) + '" data-user-email="' + escapeHtml(user.user_email) + '">افزودن</button>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                    $results.html(html);
                } else {
                    $results.html('<p>کاربری یافت نشد</p>');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'خطا در جستجو';
                $results.html('<p style="color: red;">' + escapeHtml(msg) + '</p>');
            }
        });
    });
    
    // Enter key to search for admin order form users
    $('#admin_order_form_user_search').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#admin_order_form_user_search_btn').click();
        }
    });
    
    // Add user to admin order form allowed list
    $(document).on('click', '.admin-order-form-add-user', function() {
        var userId = parseInt($(this).data('user-id'));
        var userName = $(this).data('user-name');
        var userEmail = $(this).data('user-email');
        
        if (adminOrderFormAllowedUsers.indexOf(userId) === -1) {
            adminOrderFormAllowedUsers.push(userId);
            updateAdminOrderFormAllowedUsersList();
            addUserToAdminOrderFormTable(userId, userName, userEmail);
        }
        
        $(this).replaceWith('<span style="color: green;">✓ افزوده شده</span>');
    });
    
    // Remove user from admin order form allowed list
    $(document).on('click', '.admin-order-form-remove-user', function() {
        var userId = parseInt($(this).data('user-id'));
        var index = adminOrderFormAllowedUsers.indexOf(userId);
        
        if (index !== -1) {
            adminOrderFormAllowedUsers.splice(index, 1);
            updateAdminOrderFormAllowedUsersList();
        }
        
        $(this).closest('tr').fadeOut(300, function() {
            $(this).remove();
            if ($('#admin_order_form_users_table tbody tr').length === 0) {
                $('#admin_order_form_users_table').remove();
                $('#admin_order_form_allowed_users_list').html('<p class="description" id="no_admin_order_form_users_msg">هنوز هیچ کاربر خاصی انتخاب نشده است. فقط کاربران با نقش‌های مجاز بالا به فرم سفارش مدیر دسترسی دارند.</p>');
            }
        });
    });
    
    function updateAdminOrderFormAllowedUsersList() {
        $('#admin_order_form_allowed_users').val(adminOrderFormAllowedUsers.join(','));
    }
    
    function addUserToAdminOrderFormTable(userId, userName, userEmail) {
        var $table = $('#admin_order_form_users_table');
        var $noMsg = $('#no_admin_order_form_users_msg');
        
        if ($table.length === 0) {
            $noMsg.remove();
            var tableHtml = '<table class="widefat striped" id="admin_order_form_users_table">' +
                '<thead><tr><th>شناسه</th><th>نام نمایشی</th><th>ایمیل</th><th>عملیات</th></tr></thead>' +
                '<tbody></tbody></table>';
            $('#admin_order_form_allowed_users_list').html(tableHtml);
            $table = $('#admin_order_form_users_table');
        }
        
        var rowHtml = '<tr data-user-id="' + userId + '">' +
            '<td>' + userId + '</td>' +
            '<td>' + escapeHtml(userName) + '</td>' +
            '<td>' + escapeHtml(userEmail) + '</td>' +
            '<td><button type="button" class="button button-small admin-order-form-remove-user" data-user-id="' + userId + '">' +
            '<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> حذف</button></td>' +
            '</tr>';
        
        $table.find('tbody').append(rowHtml);
    }
});
</script>
