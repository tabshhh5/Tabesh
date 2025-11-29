<?php
/**
 * Order Details Tabs Partial Template
 * 
 * Shows detailed information about an order in tabbed sections:
 * - Financial Information
 * - Files
 * - Order Details
 * - Customer Profile
 * - Status Management
 * - Contact Information
 *
 * @package Tabesh
 * @var int $order_id The order ID
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_woocommerce')) {
    return;
}

global $wpdb;

// Get order details - search in all tables
$orders_table = $wpdb->prefix . 'tabesh_orders';
$archived_table = $wpdb->prefix . 'tabesh_orders_archived';
$cancelled_table = $wpdb->prefix . 'tabesh_orders_cancelled';

$order = null;
$source_table = 'main';

// First check main table
$order = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $orders_table WHERE id = %d",
    $order_id
));

// If not found, check archived table
if (!$order && Tabesh_Install::table_exists($archived_table)) {
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $archived_table WHERE id = %d",
        $order_id
    ));
    if ($order) {
        $source_table = 'archived';
    }
}

// If not found, check cancelled table
if (!$order && Tabesh_Install::table_exists($cancelled_table)) {
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $cancelled_table WHERE id = %d",
        $order_id
    ));
    if ($order) {
        $source_table = 'cancelled';
    }
}

if (!$order) {
    echo '<div style="padding: 40px; text-align: center; color: var(--admin-error);">' . esc_html__('سفارش یافت نشد', 'tabesh') . '</div>';
    return;
}

// Get user info
$user = get_userdata($order->user_id);
$customer_name = $user ? $user->display_name : __('نامشخص', 'tabesh');
$customer_email = $user ? $user->user_email : '';
$customer_phone = $user ? get_user_meta($order->user_id, 'billing_phone', true) : '';
$customer_address = $user ? get_user_meta($order->user_id, 'billing_address_1', true) : '';
$customer_address_2 = $user ? get_user_meta($order->user_id, 'billing_address_2', true) : '';
$customer_city = $user ? get_user_meta($order->user_id, 'billing_city', true) : '';
$customer_state = $user ? get_user_meta($order->user_id, 'billing_state', true) : '';
$customer_postcode = $user ? get_user_meta($order->user_id, 'billing_postcode', true) : '';
$customer_country = $user ? get_user_meta($order->user_id, 'billing_country', true) : '';
$customer_company = $user ? get_user_meta($order->user_id, 'billing_company', true) : '';
$customer_registered = $user ? date_i18n('Y/m/d', strtotime($user->user_registered)) : '';

// Shipping address fields
$shipping_first_name = $user ? get_user_meta($order->user_id, 'shipping_first_name', true) : '';
$shipping_last_name = $user ? get_user_meta($order->user_id, 'shipping_last_name', true) : '';
$shipping_company = $user ? get_user_meta($order->user_id, 'shipping_company', true) : '';
$shipping_address_1 = $user ? get_user_meta($order->user_id, 'shipping_address_1', true) : '';
$shipping_address_2 = $user ? get_user_meta($order->user_id, 'shipping_address_2', true) : '';
$shipping_city = $user ? get_user_meta($order->user_id, 'shipping_city', true) : '';
$shipping_state = $user ? get_user_meta($order->user_id, 'shipping_state', true) : '';
$shipping_postcode = $user ? get_user_meta($order->user_id, 'shipping_postcode', true) : '';
$shipping_country = $user ? get_user_meta($order->user_id, 'shipping_country', true) : '';
$shipping_phone = $user ? get_user_meta($order->user_id, 'shipping_phone', true) : '';

// Get user order statistics
$user_total_orders = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $orders_table WHERE user_id = %d",
    $order->user_id
));
$user_completed_orders = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $orders_table WHERE user_id = %d AND status = 'completed'",
    $order->user_id
));
$user_open_orders = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $orders_table WHERE user_id = %d AND status NOT IN ('completed', 'cancelled')",
    $order->user_id
));
$user_total_spent = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(total_price) FROM $orders_table WHERE user_id = %d AND status = 'completed'",
    $order->user_id
));

// Get order files - sorted by category, version, and upload date
$files_table = $wpdb->prefix . 'tabesh_files';
$order_files = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $files_table WHERE order_id = %d AND deleted_at IS NULL ORDER BY file_category ASC, version ASC, created_at ASC",
    $order_id
));

// Group files by category for organized display
$file_categories = array(
    'book_cover' => array('label' => __('فایل‌های جلد', 'tabesh'), 'icon' => '📕', 'files' => array()),
    'book_content' => array('label' => __('فایل‌های متن', 'tabesh'), 'icon' => '📄', 'files' => array()),
    'documents' => array('label' => __('مدارک', 'tabesh'), 'icon' => '📋', 'files' => array()),
    'other' => array('label' => __('سایر', 'tabesh'), 'icon' => '📎', 'files' => array()),
);

foreach ($order_files as $file) {
    $category = $file->file_category ?: 'other';
    if (!isset($file_categories[$category])) {
        $category = 'other';
    }
    $file_categories[$category]['files'][] = $file;
}

// Get status history
$logs_table = $wpdb->prefix . 'tabesh_logs';
$status_history = $wpdb->get_results($wpdb->prepare(
    "SELECT l.*, u.display_name as staff_name 
     FROM $logs_table l 
     LEFT JOIN {$wpdb->users} u ON l.staff_user_id = u.ID
     WHERE l.order_id = %d AND l.action = 'status_change'
     ORDER BY l.created_at DESC
     LIMIT 20",
    $order_id
));

// Get print substeps - show for all orders that have substeps (not limited to processing status)
$substeps = array();
$substeps_progress = 0;
if (isset(Tabesh()->print_substeps) && method_exists(Tabesh()->print_substeps, 'get_order_substeps')) {
    $substeps = Tabesh()->print_substeps->get_order_substeps($order_id);
    if (!empty($substeps)) {
        $substeps_progress = Tabesh()->print_substeps->calculate_print_progress($order_id);
    }
}

// Parse extras
$extras = maybe_unserialize($order->extras);
if (!is_array($extras)) {
    $extras = array();
}

// Calculate unit price
$unit_price = $order->quantity > 0 ? $order->total_price / $order->quantity : 0;

// Status labels
$status_labels = array(
    'pending' => __('در انتظار', 'tabesh'),
    'confirmed' => __('تایید شده', 'tabesh'),
    'processing' => __('در حال چاپ', 'tabesh'),
    'ready' => __('آماده تحویل', 'tabesh'),
    'completed' => __('تحویل شده', 'tabesh'),
    'cancelled' => __('لغو شده', 'tabesh')
);
?>

<!-- Tabs Navigation -->
<div class="details-tabs">
    <button class="details-tab active" data-tab="financial">
        <span class="tab-icon">💰</span>
        <span><?php esc_html_e('اطلاعات مالی', 'tabesh'); ?></span>
    </button>
    <button class="details-tab" data-tab="files">
        <span class="tab-icon">📁</span>
        <span><?php esc_html_e('فایل‌ها', 'tabesh'); ?></span>
    </button>
    <button class="details-tab" data-tab="details">
        <span class="tab-icon">📋</span>
        <span><?php esc_html_e('جزئیات سفارش', 'tabesh'); ?></span>
    </button>
    <button class="details-tab" data-tab="customer">
        <span class="tab-icon">👤</span>
        <span><?php esc_html_e('پروفایل مشتری', 'tabesh'); ?></span>
    </button>
    <button class="details-tab" data-tab="status">
        <span class="tab-icon">⚙️</span>
        <span><?php esc_html_e('مدیریت وضعیت', 'tabesh'); ?></span>
    </button>
    <button class="details-tab" data-tab="contact">
        <span class="tab-icon">📞</span>
        <span><?php esc_html_e('اطلاعات تماس', 'tabesh'); ?></span>
    </button>
    <button class="details-tab" data-tab="edit">
        <span class="tab-icon">✏️</span>
        <span><?php esc_html_e('ویرایش', 'tabesh'); ?></span>
    </button>
</div>

<!-- Tab Content: Financial -->
<div class="details-tab-content active" data-tab="financial">
    <div class="financial-grid">
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('قیمت کل سفارش', 'tabesh'); ?></div>
            <div class="financial-card-value"><?php echo number_format($order->total_price); ?> <?php esc_html_e('تومان', 'tabesh'); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('تیراژ', 'tabesh'); ?></div>
            <div class="financial-card-value"><?php echo number_format($order->quantity); ?> <?php esc_html_e('نسخه', 'tabesh'); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('قیمت هر جلد', 'tabesh'); ?></div>
            <div class="financial-card-value"><?php echo number_format($unit_price); ?> <?php esc_html_e('تومان', 'tabesh'); ?></div>
        </div>
    </div>
    
    <div class="financial-breakdown">
        <h4 style="margin-bottom: 15px; color: var(--admin-text-primary);"><?php esc_html_e('ریز محاسبات', 'tabesh'); ?></h4>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('نوع کاغذ متن:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo esc_html($order->paper_type . ' - ' . $order->paper_weight . 'g'); ?></span>
        </div>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('نوع چاپ:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo esc_html($order->print_type); ?></span>
        </div>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('تعداد صفحات رنگی:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo number_format($order->page_count_color); ?></span>
        </div>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('تعداد صفحات سیاه و سفید:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo number_format($order->page_count_bw); ?></span>
        </div>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('نوع صحافی:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo esc_html($order->binding_type); ?></span>
        </div>
        <?php if (!empty($order->lamination_type)): ?>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('نوع سلفون:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo esc_html($order->lamination_type); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($extras)): ?>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('خدمات اضافی:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo esc_html(implode(', ', $extras)); ?></span>
        </div>
        <?php endif; ?>
        <div class="breakdown-row">
            <span class="breakdown-label"><?php esc_html_e('جمع کل:', 'tabesh'); ?></span>
            <span class="breakdown-value"><?php echo number_format($order->total_price); ?> <?php esc_html_e('تومان', 'tabesh'); ?></span>
        </div>
    </div>
</div>

<!-- Tab Content: Files -->
<div class="details-tab-content" data-tab="files">
    <?php if (empty($order_files)): ?>
        <div class="no-files">
            <div class="no-files-icon">📂</div>
            <p><?php esc_html_e('هیچ فایلی برای این سفارش آپلود نشده است.', 'tabesh'); ?></p>
        </div>
    <?php else: ?>
        <?php 
        // Category labels for file numbering - defined outside the loop.
        $category_labels_map = array(
            'book_cover' => __('جلد', 'tabesh'),
            'book_content' => __('متن', 'tabesh'),
            'documents' => __('مدرک', 'tabesh'),
            'other' => __('فایل', 'tabesh'),
        );
        ?>
        <?php foreach ($file_categories as $category_key => $category): ?>
            <?php if (!empty($category['files'])): ?>
                <div class="file-category-section">
                    <div class="file-category-header">
                        <span class="file-category-icon"><?php echo esc_html($category['icon']); ?></span>
                        <span class="file-category-title"><?php echo esc_html($category['label']); ?></span>
                        <span class="file-category-count">(<?php echo count($category['files']); ?> <?php esc_html_e('فایل', 'tabesh'); ?>)</span>
                    </div>
                    <div class="files-grid">
                        <?php 
                        $file_index = 0;
                        foreach ($category['files'] as $file): 
                            $file_index++;
                            $file_icon = '📄';
                            $mime_parts = explode('/', $file->mime_type);
                            $type = $mime_parts[0] ?? '';
                            if ($type === 'image') $file_icon = '🖼️';
                            elseif ($file->mime_type === 'application/pdf') $file_icon = '📕';
                            elseif (strpos($file->mime_type, 'zip') !== false || strpos($file->mime_type, 'rar') !== false) $file_icon = '🗜️';
                            
                            $file_size = $file->file_size;
                            if ($file_size >= 1048576) {
                                $file_size_display = number_format($file_size / 1048576, 1) . ' MB';
                            } else {
                                $file_size_display = number_format($file_size / 1024, 1) . ' KB';
                            }
                            
                            $category_label = isset($category_labels_map[$category_key]) ? $category_labels_map[$category_key] : __('فایل', 'tabesh');
                        ?>
                            <div class="file-card">
                                <div class="file-icon"><?php echo $file_icon; ?></div>
                                <div class="file-info">
                                    <div class="file-number"><?php echo esc_html($category_label . ' #' . $file_index); ?></div>
                                    <div class="file-name" title="<?php echo esc_attr($file->original_filename); ?>"><?php echo esc_html($file->original_filename); ?></div>
                                    <div class="file-meta">
                                        <?php echo esc_html($file_size_display); ?>
                                        <?php if (!empty($file->version)): ?>
                                            • <span class="file-version"><?php echo esc_html__('نسخه', 'tabesh') . ' ' . esc_html($file->version); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($file->status)): ?>
                                            <br><small><?php echo esc_html__('وضعیت:', 'tabesh') . ' ' . esc_html($file->status); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button class="file-download-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                                    ⬇️ <?php esc_html_e('دانلود', 'tabesh'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Tab Content: Order Details -->
<div class="details-tab-content" data-tab="details">
    <div class="financial-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('شماره سفارش', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->order_number); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('عنوان کتاب', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->book_title ?: '—'); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('قطع کتاب', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->book_size); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('نوع کاغذ', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->paper_type); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('گرماژ کاغذ', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->paper_weight); ?>g</div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('نوع چاپ', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->print_type); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('کل صفحات', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo number_format($order->page_count_total); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('نوع صحافی', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->binding_type); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('نوع مجوز', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->license_type); ?></div>
        </div>
        <?php if (!empty($order->cover_paper_type)): ?>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('نوع کاغذ جلد', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->cover_paper_type); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($order->cover_paper_weight)): ?>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('گرماژ جلد', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->cover_paper_weight); ?>g</div>
        </div>
        <?php endif; ?>
        <?php if (!empty($order->lamination_type)): ?>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('سلفون', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo esc_html($order->lamination_type); ?></div>
        </div>
        <?php endif; ?>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('تاریخ ثبت', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo date_i18n('Y/m/d H:i', strtotime($order->created_at)); ?></div>
        </div>
        <div class="financial-card">
            <div class="financial-card-title"><?php esc_html_e('آخرین به‌روزرسانی', 'tabesh'); ?></div>
            <div class="financial-card-value" style="font-size: 18px;"><?php echo date_i18n('Y/m/d H:i', strtotime($order->updated_at)); ?></div>
        </div>
    </div>
    
    <?php if (!empty($extras)): ?>
    <div style="margin-top: 25px;">
        <h4 style="margin-bottom: 15px; color: var(--admin-text-primary);"><?php esc_html_e('خدمات اضافی', 'tabesh'); ?></h4>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php foreach ($extras as $extra): ?>
                <span style="background: var(--admin-bg-primary); padding: 8px 16px; border-radius: 20px; font-size: 14px;"><?php echo esc_html($extra); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($order->notes)): ?>
    <div style="margin-top: 25px;">
        <h4 style="margin-bottom: 15px; color: var(--admin-text-primary);"><?php esc_html_e('یادداشت‌ها', 'tabesh'); ?></h4>
        <div style="background: var(--admin-bg-primary); padding: 15px; border-radius: 8px; white-space: pre-wrap;"><?php echo nl2br(esc_html($order->notes)); ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Tab Content: Customer Profile -->
<div class="details-tab-content" data-tab="customer">
    <div class="customer-profile-grid">
        <div class="customer-avatar-section">
            <img src="<?php echo esc_url(get_avatar_url($order->user_id, array('size' => 120))); ?>" 
                 alt="<?php echo esc_attr($customer_name); ?>" 
                 class="customer-avatar-large">
            <div class="customer-display-name"><?php echo esc_html($customer_name); ?></div>
            <div class="customer-since"><?php esc_html_e('عضو از:', 'tabesh'); ?> <?php echo esc_html($customer_registered); ?></div>
            
            <div class="customer-stats">
                <div class="customer-stat">
                    <div class="customer-stat-value"><?php echo number_format($user_total_orders); ?></div>
                    <div class="customer-stat-label"><?php esc_html_e('کل سفارشات', 'tabesh'); ?></div>
                </div>
                <div class="customer-stat">
                    <div class="customer-stat-value"><?php echo number_format($user_completed_orders); ?></div>
                    <div class="customer-stat-label"><?php esc_html_e('تکمیل شده', 'tabesh'); ?></div>
                </div>
                <div class="customer-stat">
                    <div class="customer-stat-value"><?php echo number_format($user_open_orders); ?></div>
                    <div class="customer-stat-label"><?php esc_html_e('در حال پردازش', 'tabesh'); ?></div>
                </div>
                <div class="customer-stat">
                    <div class="customer-stat-value"><?php echo number_format($user_total_spent ?: 0); ?></div>
                    <div class="customer-stat-label"><?php esc_html_e('مجموع خرید (تومان)', 'tabesh'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="customer-details-section">
            <!-- Billing Information Section -->
            <h4 class="customer-section-title" style="grid-column: 1 / -1; margin-bottom: 10px; color: var(--admin-text-primary);"><?php esc_html_e('اطلاعات صورتحساب', 'tabesh'); ?></h4>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('شناسه کاربر', 'tabesh'); ?></div>
                <div class="customer-detail-value">#<?php echo esc_html($order->user_id); ?></div>
            </div>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('ایمیل', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_email ?: '—'); ?></div>
            </div>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('شماره تماس', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_phone ?: '—'); ?></div>
            </div>
            <?php if (!empty($customer_company)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('شرکت', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_company); ?></div>
            </div>
            <?php endif; ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('استان', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_state ?: '—'); ?></div>
            </div>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('شهر', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_city ?: '—'); ?></div>
            </div>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('کد پستی', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_postcode ?: '—'); ?></div>
            </div>
            <?php if (!empty($customer_country)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('کشور', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_country); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($customer_address)): ?>
            <div class="customer-detail-card" style="grid-column: 1 / -1;">
                <div class="customer-detail-label"><?php esc_html_e('آدرس', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_address); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($customer_address_2)): ?>
            <div class="customer-detail-card" style="grid-column: 1 / -1;">
                <div class="customer-detail-label"><?php esc_html_e('آدرس (ادامه)', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($customer_address_2); ?></div>
            </div>
            <?php endif; ?>
            
            <?php 
            // Check if any shipping info exists
            $has_shipping_info = !empty($shipping_first_name) || !empty($shipping_last_name) || 
                                 !empty($shipping_address_1) || !empty($shipping_city) || 
                                 !empty($shipping_state) || !empty($shipping_postcode);
            if ($has_shipping_info): 
            ?>
            <!-- Shipping Information Section -->
            <h4 class="customer-section-title" style="grid-column: 1 / -1; margin-top: 20px; margin-bottom: 10px; color: var(--admin-text-primary);"><?php esc_html_e('اطلاعات ارسال', 'tabesh'); ?></h4>
            <?php if (!empty($shipping_first_name) || !empty($shipping_last_name)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('نام گیرنده', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html(trim($shipping_first_name . ' ' . $shipping_last_name)); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_company)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('شرکت', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_company); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_phone)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('شماره تماس گیرنده', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_phone); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_state)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('استان (ارسال)', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_state); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_city)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('شهر (ارسال)', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_city); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_postcode)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('کد پستی (ارسال)', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_postcode); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_country)): ?>
            <div class="customer-detail-card">
                <div class="customer-detail-label"><?php esc_html_e('کشور (ارسال)', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_country); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_address_1)): ?>
            <div class="customer-detail-card" style="grid-column: 1 / -1;">
                <div class="customer-detail-label"><?php esc_html_e('آدرس ارسال', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_address_1); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipping_address_2)): ?>
            <div class="customer-detail-card" style="grid-column: 1 / -1;">
                <div class="customer-detail-label"><?php esc_html_e('آدرس ارسال (ادامه)', 'tabesh'); ?></div>
                <div class="customer-detail-value"><?php echo esc_html($shipping_address_2); ?></div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tab Content: Status Management -->
<div class="details-tab-content" data-tab="status">
    <div class="status-update-container" data-order-id="<?php echo esc_attr($order_id); ?>" data-source-table="<?php echo esc_attr($source_table); ?>">
        <h4 style="margin-bottom: 15px; color: var(--admin-text-primary);"><?php esc_html_e('تغییر وضعیت سفارش', 'tabesh'); ?></h4>
        <div class="status-select-wrapper">
            <select class="status-select">
                <option value=""><?php esc_html_e('انتخاب وضعیت جدید...', 'tabesh'); ?></option>
                <?php foreach ($status_labels as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($order->status, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="status-update-btn">
                💾 <?php esc_html_e('ذخیره تغییرات', 'tabesh'); ?>
            </button>
        </div>
        
        <?php if (!empty($substeps)): ?>
        <div class="print-substeps-container">
            <div class="substeps-title">
                🖨️ <?php esc_html_e('جزئیات فرآیند چاپ', 'tabesh'); ?>
                <span class="substep-badge" style="margin-right: 10px;"><?php echo esc_html($substeps_progress); ?>%</span>
            </div>
            <div class="substeps-list">
                <?php foreach ($substeps as $substep): ?>
                    <div class="substep-item <?php echo $substep->is_completed ? 'completed' : ''; ?>" data-substep-id="<?php echo esc_attr($substep->id); ?>">
                        <input type="checkbox" 
                               class="substep-checkbox" 
                               data-substep-id="<?php echo esc_attr($substep->id); ?>"
                               <?php checked($substep->is_completed, 1); ?>>
                        <div class="substep-content">
                            <div class="substep-title"><?php echo esc_html($substep->substep_title); ?></div>
                            <div class="substep-details"><?php echo esc_html($substep->substep_details); ?></div>
                        </div>
                        <?php if ($substep->is_completed): ?>
                            <span class="substep-badge">✓ <?php esc_html_e('انجام شد', 'tabesh'); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($status_history)): ?>
        <div class="status-history-container">
            <div class="history-title">
                📜 <?php esc_html_e('تاریخچه تغییرات وضعیت', 'tabesh'); ?>
            </div>
            <div class="history-list">
                <?php foreach ($status_history as $log): ?>
                    <div class="history-item">
                        <div class="history-icon">🔄</div>
                        <div class="history-content">
                            <div class="history-status-change">
                                <?php 
                                $old_label = $status_labels[$log->old_status] ?? $log->old_status;
                                $new_label = $status_labels[$log->new_status] ?? $log->new_status;
                                printf(
                                    esc_html__('از «%1$s» به «%2$s»', 'tabesh'),
                                    $old_label,
                                    $new_label
                                );
                                ?>
                            </div>
                            <div class="history-meta">
                                <?php if (!empty($log->staff_name)): ?>
                                <span class="history-user">👤 <?php echo esc_html($log->staff_name); ?></span>
                                <?php endif; ?>
                                <span class="history-date">🕐 <?php echo date_i18n('Y/m/d H:i', strtotime($log->created_at)); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Content: Contact Information -->
<div class="details-tab-content" data-tab="contact">
    <div class="contact-grid">
        <div class="contact-card">
            <div class="contact-icon">📧</div>
            <div class="contact-info">
                <div class="contact-label"><?php esc_html_e('ایمیل', 'tabesh'); ?></div>
                <div class="contact-value"><?php echo esc_html($customer_email ?: '—'); ?></div>
            </div>
            <?php if (!empty($customer_email)): ?>
            <a href="mailto:<?php echo esc_attr($customer_email); ?>" class="contact-action-btn"><?php esc_html_e('ارسال ایمیل', 'tabesh'); ?></a>
            <?php endif; ?>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">📱</div>
            <div class="contact-info">
                <div class="contact-label"><?php esc_html_e('شماره موبایل', 'tabesh'); ?></div>
                <div class="contact-value"><?php echo esc_html($customer_phone ?: '—'); ?></div>
            </div>
            <?php if (!empty($customer_phone)): ?>
            <a href="tel:<?php echo esc_attr($customer_phone); ?>" class="contact-action-btn"><?php esc_html_e('تماس', 'tabesh'); ?></a>
            <?php endif; ?>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">📍</div>
            <div class="contact-info">
                <div class="contact-label"><?php esc_html_e('موقعیت', 'tabesh'); ?></div>
                <div class="contact-value"><?php echo esc_html(trim($customer_state . ', ' . $customer_city, ', ') ?: '—'); ?></div>
            </div>
        </div>
        
        <div class="contact-card" style="grid-column: 1 / -1;">
            <div class="contact-icon">🏠</div>
            <div class="contact-info">
                <div class="contact-label"><?php esc_html_e('آدرس کامل', 'tabesh'); ?></div>
                <div class="contact-value"><?php echo esc_html($customer_address ?: '—'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Content: Edit Order -->
<div class="details-tab-content" data-tab="edit">
    <div class="edit-form-grid" data-order-id="<?php echo esc_attr($order_id); ?>">
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('عنوان کتاب', 'tabesh'); ?></label>
            <input type="text" name="book_title" class="edit-input" value="<?php echo esc_attr($order->book_title); ?>">
        </div>
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('قطع کتاب', 'tabesh'); ?></label>
            <input type="text" name="book_size" class="edit-input" value="<?php echo esc_attr($order->book_size); ?>">
        </div>
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('نوع کاغذ', 'tabesh'); ?></label>
            <input type="text" name="paper_type" class="edit-input" value="<?php echo esc_attr($order->paper_type); ?>">
        </div>
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('گرماژ کاغذ', 'tabesh'); ?></label>
            <input type="text" name="paper_weight" class="edit-input" value="<?php echo esc_attr($order->paper_weight); ?>">
        </div>
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('صفحات رنگی', 'tabesh'); ?></label>
            <input type="number" name="page_count_color" class="edit-input" value="<?php echo esc_attr($order->page_count_color); ?>">
        </div>
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('صفحات سیاه و سفید', 'tabesh'); ?></label>
            <input type="number" name="page_count_bw" class="edit-input" value="<?php echo esc_attr($order->page_count_bw); ?>">
        </div>
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('تیراژ', 'tabesh'); ?></label>
            <input type="number" name="quantity" class="edit-input" value="<?php echo esc_attr($order->quantity); ?>">
        </div>
        <div class="edit-field">
            <label class="edit-label"><?php esc_html_e('مبلغ کل (تومان)', 'tabesh'); ?></label>
            <input type="number" name="total_price" class="edit-input" value="<?php echo esc_attr($order->total_price); ?>">
        </div>
        <div class="edit-field" style="grid-column: 1 / -1;">
            <label class="edit-label"><?php esc_html_e('یادداشت‌ها', 'tabesh'); ?></label>
            <textarea name="notes" class="edit-textarea"><?php echo esc_textarea($order->notes); ?></textarea>
        </div>
        <div class="edit-actions">
            <button class="edit-cancel-btn"><?php esc_html_e('انصراف', 'tabesh'); ?></button>
            <button class="edit-save-btn"><?php esc_html_e('ذخیره تغییرات', 'tabesh'); ?></button>
        </div>
    </div>
</div>
