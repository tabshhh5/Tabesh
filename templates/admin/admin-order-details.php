<?php
/**
 * Admin Order Details Template
 *
 * Displays comprehensive order details including file management
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// $order_id variable is expected to be passed
if (!isset($order_id)) {
    echo '<div class="notice notice-error"><p>' . __('شماره سفارش مشخص نشده است', 'tabesh') . '</p></div>';
    return;
}

$file_manager = Tabesh()->file_manager;
$file_validator = Tabesh()->file_validator;

// Get order details
global $wpdb;
$order_table = $wpdb->prefix . 'tabesh_orders';
$order = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $order_table WHERE id = %d",
    $order_id
));

if (!$order) {
    echo '<div class="notice notice-error"><p>' . __('سفارش یافت نشد', 'tabesh') . '</p></div>';
    return;
}

// Get user info
$user = get_user_by('id', $order->user_id);

// Get files for this order
$files = $file_manager->get_order_files($order_id);

// Group files by category
$files_by_category = array(
    'book_content' => array(),
    'book_cover' => array(),
    'document' => array()
);

foreach ($files as $file) {
    $files_by_category[$file->file_category][] = $file;
}

?>

<div class="tabesh-order-details-container" dir="rtl">
    <!-- Navigation Tabs -->
    <div class="tabesh-tabs">
        <button class="tabesh-tab-button active" data-tab="order-info">
            <span class="dashicons dashicons-info"></span>
            اطلاعات سفارش
        </button>
        <button class="tabesh-tab-button" data-tab="file-management">
            <span class="dashicons dashicons-media-document"></span>
            مدیریت فایل‌ها
            <?php if (count($files) > 0): ?>
                <span class="tab-badge"><?php echo count($files); ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Tab Content: Order Info -->
    <div class="tabesh-tab-content active" id="order-info">
        <div class="order-info-grid">
            <div class="info-card">
                <h3><span class="dashicons dashicons-cart"></span> اطلاعات عمومی</h3>
                <table class="order-info-table">
                    <tr>
                        <th><?php echo esc_html__('شماره سفارش:', 'tabesh'); ?></th>
                        <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                    </tr>
                    <?php if (!empty($order->book_title)): ?>
                    <tr>
                        <th><?php echo esc_html__('عنوان کتاب:', 'tabesh'); ?></th>
                        <td><strong><?php echo esc_html($order->book_title); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php echo esc_html__('مشتری:', 'tabesh'); ?></th>
                        <td><?php echo $user ? esc_html($user->display_name) : __('کاربر حذف شده', 'tabesh'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('وضعیت:', 'tabesh'); ?></th>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($order->status); ?>">
                                <?php
                                $status_labels = array(
                                    'pending' => 'در انتظار بررسی',
                                    'confirmed' => 'تایید شده',
                                    'processing' => 'در حال چاپ',
                                    'ready' => 'آماده تحویل',
                                    'completed' => 'تحویل داده شده',
                                    'cancelled' => 'لغو شده'
                                );
                                echo esc_html(isset($status_labels[$order->status]) ? $status_labels[$order->status] : $order->status);
                                ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>تاریخ ثبت:</th>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order->created_at)); ?></td>
                    </tr>
                </table>
            </div>

            <div class="info-card">
                <h3><span class="dashicons dashicons-book-alt"></span> مشخصات چاپ</h3>
                <table class="order-info-table">
                    <tr>
                        <th>قطع کتاب:</th>
                        <td><?php echo esc_html($order->book_size); ?></td>
                    </tr>
                    <tr>
                        <th>نوع کاغذ:</th>
                        <td><?php echo esc_html($order->paper_type); ?> (<?php echo esc_html($order->paper_weight); ?> گرم)</td>
                    </tr>
                    <tr>
                        <th>نوع چاپ:</th>
                        <td><?php echo esc_html($order->print_type); ?></td>
                    </tr>
                    <tr>
                        <th>تعداد صفحات:</th>
                        <td><?php echo number_format($order->page_count_total); ?> صفحه
                            <?php if ($order->page_count_color > 0): ?>
                                (<?php echo number_format($order->page_count_color); ?> رنگی)
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>تیراژ:</th>
                        <td><?php echo number_format($order->quantity); ?></td>
                    </tr>
                </table>
            </div>

            <div class="info-card">
                <h3><span class="dashicons dashicons-admin-settings"></span> صحافی و نهایی‌سازی</h3>
                <table class="order-info-table">
                    <tr>
                        <th>نوع صحافی:</th>
                        <td><?php echo esc_html($order->binding_type); ?></td>
                    </tr>
                    <tr>
                        <th>نوع مجوز:</th>
                        <td><?php echo esc_html($order->license_type); ?></td>
                    </tr>
                    <?php if ($order->cover_paper_type): ?>
                    <tr>
                        <th>کاغذ جلد:</th>
                        <td><?php echo esc_html($order->cover_paper_type); ?> (<?php echo esc_html($order->cover_paper_weight); ?> گرم)</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($order->lamination_type): ?>
                    <tr>
                        <th>سلفون:</th>
                        <td><?php echo esc_html($order->lamination_type); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($order->extras): ?>
                    <tr>
                        <th>امکانات اضافی:</th>
                        <td>
                            <?php
                            $extras = json_decode($order->extras, true);
                            if (is_array($extras)) {
                                echo esc_html(implode('، ', array_keys($extras)));
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="info-card highlight">
                <h3><span class="dashicons dashicons-money-alt"></span> اطلاعات مالی</h3>
                <table class="order-info-table">
                    <tr>
                        <th>مبلغ کل:</th>
                        <td class="price-amount"><strong><?php echo number_format($order->total_price); ?> تومان</strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ($order->notes): ?>
        <div class="info-card">
            <h3><span class="dashicons dashicons-editor-alignleft"></span> یادداشت‌ها</h3>
            <p><?php echo wp_kses_post($order->notes); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab Content: File Management -->
    <div class="tabesh-tab-content" id="file-management">
        <div class="file-management-header">
            <h3>مدیریت فایل‌های سفارش</h3>
            <p class="description">در این بخش می‌توانید فایل‌های آپلود شده توسط مشتری را مشاهده، تأیید، رد یا نظر دهید.</p>
        </div>

        <?php if (empty($files)): ?>
        <div class="empty-state">
            <span class="dashicons dashicons-media-default"></span>
            <p>هیچ فایلی برای این سفارش آپلود نشده است.</p>
        </div>
        <?php else: ?>

        <!-- File Categories -->
        <div class="file-categories">
            <!-- 1. Book Content File -->
            <div class="file-category-section">
                <h4 class="category-title">
                    <span class="dashicons dashicons-media-document"></span>
                    ۱. فایل محتوای کتاب
                </h4>
                <?php if (!empty($files_by_category['book_content'])): ?>
                    <?php foreach ($files_by_category['book_content'] as $file): ?>
                        <?php include TABESH_PLUGIN_DIR . 'templates/partials/file-card.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-files">فایلی آپلود نشده است</p>
                <?php endif; ?>
            </div>

            <!-- 2. Book Cover File -->
            <div class="file-category-section">
                <h4 class="category-title">
                    <span class="dashicons dashicons-format-image"></span>
                    ۲. فایل جلد کتاب
                </h4>
                <?php if (!empty($files_by_category['book_cover'])): ?>
                    <?php foreach ($files_by_category['book_cover'] as $file): ?>
                        <?php include TABESH_PLUGIN_DIR . 'templates/partials/file-card.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-files">فایلی آپلود نشده است</p>
                <?php endif; ?>
            </div>

            <!-- 3. Customer Documents -->
            <div class="file-category-section">
                <h4 class="category-title">
                    <span class="dashicons dashicons-id-alt"></span>
                    ۳. مدارک مشتری
                </h4>
                <?php if (!empty($files_by_category['document'])): ?>
                    <?php foreach ($files_by_category['document'] as $file): ?>
                        <?php include TABESH_PLUGIN_DIR . 'templates/partials/file-card.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-files">مدرکی آپلود نشده است</p>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
.tabesh-order-details-container {
    padding: 20px;
    background: #f9f9f9;
}

/* Tabs */
.tabesh-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #ddd;
}

.tabesh-tab-button {
    background: #fff;
    border: 1px solid #ddd;
    border-bottom: none;
    padding: 12px 20px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #666;
    transition: all 0.3s ease;
    border-radius: 4px 4px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tabesh-tab-button:hover {
    background: #f5f5f5;
    color: #333;
}

.tabesh-tab-button.active {
    background: #0073aa;
    color: #fff;
    border-color: #0073aa;
}

.tabesh-tab-button .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.tab-badge {
    background: rgba(255, 255, 255, 0.3);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    margin-right: 5px;
}

.tabesh-tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.tabesh-tab-content.active {
    display: block;
}

/* Order Info Grid */
.order-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-card {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 20px;
}

.info-card.highlight {
    background: #e7f5fe;
    border-color: #0073aa;
}

.info-card h3 {
    margin: 0 0 15px;
    font-size: 16px;
    color: #23282d;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card h3 .dashicons {
    color: #0073aa;
}

.order-info-table {
    width: 100%;
    font-size: 14px;
}

.order-info-table tr {
    border-bottom: 1px solid #e5e5e5;
}

.order-info-table tr:last-child {
    border-bottom: none;
}

.order-info-table th {
    text-align: right;
    padding: 10px 10px 10px 0;
    font-weight: 600;
    color: #666;
    width: 40%;
}

.order-info-table td {
    padding: 10px 0;
    color: #333;
}

.price-amount {
    font-size: 18px;
    color: #27ae60;
}

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-confirmed {
    background: #d1ecf1;
    color: #0c5460;
}

.status-processing {
    background: #e2e3e5;
    color: #383d41;
}

.status-ready {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

/* File Management */
.file-management-header {
    margin-bottom: 25px;
}

.file-management-header h3 {
    margin: 0 0 10px;
    font-size: 18px;
}

.file-management-header .description {
    color: #666;
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    opacity: 0.3;
}

.empty-state p {
    margin-top: 15px;
    font-size: 16px;
}

.file-categories {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.file-category-section {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 20px;
}

.category-title {
    margin: 0 0 15px;
    font-size: 16px;
    color: #23282d;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 10px;
    border-bottom: 2px solid #0073aa;
}

.category-title .dashicons {
    color: #0073aa;
    font-size: 20px;
}

.no-files {
    color: #999;
    font-style: italic;
    margin: 10px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.tabesh-tab-button').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Update buttons
        $('.tabesh-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update content
        $('.tabesh-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });
});
</script>
