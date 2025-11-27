<?php
/**
 * Admin Files Management Template
 *
 * Admin interface for viewing and managing order files.
 *
 * @package Tabesh
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get order ID if provided
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Get order details if order_id is set
$order = null;
if ($order_id > 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'tabesh_orders';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $order_id
    ));
}

// Get file manager
$file_manager = Tabesh()->file_manager;
?>

<div class="wrap tabesh-admin-files" dir="rtl">
    <h1>
        <span class="dashicons dashicons-media-default"></span>
        <?php esc_html_e('فایل‌های سفارش', 'tabesh'); ?>
        <?php if ($order): ?>
            - #<?php echo esc_html($order->order_number); ?>
        <?php endif; ?>
    </h1>

    <?php if ($order): ?>
        <!-- Order Info Card -->
        <div class="tabesh-card order-info-card">
            <h2><?php esc_html_e('اطلاعات سفارش', 'tabesh'); ?></h2>
            <div class="order-info-grid">
                <div class="info-item">
                    <span class="info-label"><?php esc_html_e('عنوان کتاب:', 'tabesh'); ?></span>
                    <span class="info-value"><?php echo esc_html($order->book_title ?: __('بدون عنوان', 'tabesh')); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php esc_html_e('شماره سفارش:', 'tabesh'); ?></span>
                    <span class="info-value">#<?php echo esc_html($order->order_number); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php esc_html_e('قطع:', 'tabesh'); ?></span>
                    <span class="info-value"><?php echo esc_html($order->book_size); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php esc_html_e('تعداد صفحات:', 'tabesh'); ?></span>
                    <span class="info-value"><?php echo esc_html($order->page_count_total); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php esc_html_e('تیراژ:', 'tabesh'); ?></span>
                    <span class="info-value"><?php echo esc_html($order->quantity); ?> <?php esc_html_e('نسخه', 'tabesh'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php esc_html_e('وضعیت:', 'tabesh'); ?></span>
                    <span class="info-value status-badge status-<?php echo esc_attr($order->status); ?>">
                        <?php echo esc_html(Tabesh()->user->get_status_label($order->status)); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Files Sections -->
        <?php
        $files = $file_manager->get_order_files($order_id);
        
        // Group files by category
        $grouped_files = array(
            'text' => array(),
            'cover' => array(),
            'documents' => array()
        );
        
        // Also check file_category for compatibility
        foreach ($files as $file) {
            $category = $file->file_category;
            // Map old category names to new ones if needed
            if ($category === 'book_content') {
                $category = 'text';
            } elseif ($category === 'book_cover') {
                $category = 'cover';
            } elseif ($category === 'document') {
                $category = 'documents';
            }
            
            if (isset($grouped_files[$category])) {
                $grouped_files[$category][] = $file;
            }
        }
        
        $category_labels = array(
            'text' => __('فایل متن کتاب', 'tabesh'),
            'cover' => __('فایل جلد کتاب', 'tabesh'),
            'documents' => __('مدارک', 'tabesh')
        );
        
        $category_icons = array(
            'text' => 'dashicons-media-document',
            'cover' => 'dashicons-format-image',
            'documents' => 'dashicons-portfolio'
        );
        ?>

        <div class="files-sections">
            <?php foreach ($grouped_files as $category => $category_files): ?>
                <div class="tabesh-card files-section" data-category="<?php echo esc_attr($category); ?>">
                    <h2>
                        <span class="dashicons <?php echo esc_attr($category_icons[$category]); ?>"></span>
                        <?php echo esc_html($category_labels[$category]); ?>
                        <span class="file-count">(<?php echo count($category_files); ?>)</span>
                    </h2>

                    <?php if (empty($category_files)): ?>
                        <div class="no-files">
                            <p><?php esc_html_e('هنوز فایلی در این دسته آپلود نشده است.', 'tabesh'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="files-table-container">
                            <table class="wp-list-table widefat fixed striped files-table">
                                <thead>
                                    <tr>
                                        <th class="column-filename"><?php esc_html_e('نام فایل', 'tabesh'); ?></th>
                                        <th class="column-size"><?php esc_html_e('حجم', 'tabesh'); ?></th>
                                        <th class="column-version"><?php esc_html_e('نسخه', 'tabesh'); ?></th>
                                        <th class="column-status"><?php esc_html_e('وضعیت', 'tabesh'); ?></th>
                                        <th class="column-date"><?php esc_html_e('تاریخ آپلود', 'tabesh'); ?></th>
                                        <th class="column-actions"><?php esc_html_e('عملیات', 'tabesh'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_files as $file): ?>
                                        <tr class="file-row" data-file-id="<?php echo esc_attr($file->id); ?>">
                                            <td class="column-filename">
                                                <span class="dashicons dashicons-media-default file-icon"></span>
                                                <span class="filename" title="<?php echo esc_attr($file->original_filename); ?>">
                                                    <?php echo esc_html($file->stored_filename); ?>
                                                </span>
                                            </td>
                                            <td class="column-size">
                                                <?php echo esc_html(size_format($file->file_size)); ?>
                                            </td>
                                            <td class="column-version">
                                                <span class="version-badge">v<?php echo esc_html($file->version); ?></span>
                                            </td>
                                            <td class="column-status">
                                                <?php
                                                $status_classes = array(
                                                    'pending' => 'status-pending',
                                                    'approved' => 'status-approved',
                                                    'rejected' => 'status-rejected'
                                                );
                                                $status_labels = array(
                                                    'pending' => __('در انتظار بررسی', 'tabesh'),
                                                    'approved' => __('تایید شده', 'tabesh'),
                                                    'rejected' => __('رد شده', 'tabesh')
                                                );
                                                $status_class = $status_classes[$file->status] ?? 'status-pending';
                                                $status_label = $status_labels[$file->status] ?? $file->status;
                                                ?>
                                                <span class="file-status-badge <?php echo esc_attr($status_class); ?>">
                                                    <?php echo esc_html($status_label); ?>
                                                </span>
                                            </td>
                                            <td class="column-date">
                                                <?php echo esc_html(date_i18n('j F Y - H:i', strtotime($file->created_at))); ?>
                                            </td>
                                            <td class="column-actions">
                                                <div class="action-buttons">
                                                    <a href="<?php echo esc_url(rest_url(TABESH_REST_NAMESPACE . '/download/' . $file->id)); ?>" 
                                                       class="button button-small download-btn" 
                                                       target="_blank"
                                                       title="<?php esc_attr_e('دانلود', 'tabesh'); ?>">
                                                        <span class="dashicons dashicons-download"></span>
                                                    </a>
                                                    <?php if ($file->status === 'pending'): ?>
                                                        <button type="button" 
                                                                class="button button-small button-primary approve-btn"
                                                                data-file-id="<?php echo esc_attr($file->id); ?>"
                                                                title="<?php esc_attr_e('تایید', 'tabesh'); ?>">
                                                            <span class="dashicons dashicons-yes"></span>
                                                        </button>
                                                        <button type="button" 
                                                                class="button button-small reject-btn"
                                                                data-file-id="<?php echo esc_attr($file->id); ?>"
                                                                title="<?php esc_attr_e('رد', 'tabesh'); ?>">
                                                            <span class="dashicons dashicons-no"></span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php if ($file->status === 'rejected' && !empty($file->rejection_reason)): ?>
                                            <tr class="rejection-reason-row">
                                                <td colspan="6">
                                                    <div class="rejection-reason">
                                                        <strong><?php esc_html_e('دلیل رد:', 'tabesh'); ?></strong>
                                                        <?php echo esc_html($file->rejection_reason); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Back Button -->
        <div class="page-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=tabesh-orders')); ?>" class="button button-secondary">
                <span class="dashicons dashicons-arrow-right-alt"></span>
                <?php esc_html_e('بازگشت به سفارشات', 'tabesh'); ?>
            </a>
        </div>

    <?php else: ?>
        <!-- Order Selection -->
        <div class="tabesh-card">
            <h2><?php esc_html_e('انتخاب سفارش', 'tabesh'); ?></h2>
            <p><?php esc_html_e('برای مشاهده فایل‌ها، یک سفارش را از لیست سفارشات انتخاب کنید.', 'tabesh'); ?></p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tabesh-orders')); ?>" class="button button-primary">
                    <?php esc_html_e('مشاهده سفارشات', 'tabesh'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Rejection Modal -->
<div id="rejection-modal" class="tabesh-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php esc_html_e('رد فایل', 'tabesh'); ?></h3>
            <button class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <form id="rejection-form">
                <input type="hidden" id="rejection-file-id" value="">
                <div class="form-group">
                    <label for="rejection-reason"><?php esc_html_e('دلیل رد:', 'tabesh'); ?></label>
                    <textarea id="rejection-reason" rows="4" required 
                              placeholder="<?php esc_attr_e('دلیل رد فایل را وارد کنید...', 'tabesh'); ?>"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('ثبت رد', 'tabesh'); ?>
                    </button>
                    <button type="button" class="button button-secondary modal-cancel-btn">
                        <?php esc_html_e('انصراف', 'tabesh'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Admin Files Page Styles */
.tabesh-admin-files {
    font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif;
}

.tabesh-admin-files h1 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}

.tabesh-admin-files h1 .dashicons {
    font-size: 28px;
    width: 28px;
    height: 28px;
}

.tabesh-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.tabesh-card h2 {
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
}

.tabesh-card h2 .dashicons {
    color: #2271b1;
}

.tabesh-card h2 .file-count {
    font-size: 13px;
    color: #646970;
    font-weight: normal;
}

/* Order Info Grid */
.order-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.order-info-grid .info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.order-info-grid .info-label {
    font-size: 12px;
    color: #646970;
}

.order-info-grid .info-value {
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-processing {
    background: #cce5ff;
    color: #004085;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

/* Files Table */
.files-table-container {
    overflow-x: auto;
}

.files-table .column-filename {
    width: 30%;
}

.files-table .column-size {
    width: 10%;
}

.files-table .column-version {
    width: 10%;
}

.files-table .column-status {
    width: 15%;
}

.files-table .column-date {
    width: 20%;
}

.files-table .column-actions {
    width: 15%;
}

.files-table .filename {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.files-table .file-icon {
    color: #2271b1;
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.version-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #f0f0f1;
    border-radius: 10px;
    font-size: 12px;
    color: #646970;
}

.file-status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.file-status-badge.status-pending {
    background: #fff3cd;
    color: #856404;
}

.file-status-badge.status-approved {
    background: #d4edda;
    color: #155724;
}

.file-status-badge.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

.action-buttons .button-small {
    padding: 0 6px;
    min-height: 28px;
    line-height: 26px;
}

.action-buttons .button-small .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    line-height: 26px;
}

.approve-btn:hover {
    background: #2271b1 !important;
    border-color: #2271b1 !important;
    color: #fff !important;
}

.reject-btn {
    color: #d63638;
    border-color: #d63638;
}

.reject-btn:hover {
    background: #d63638 !important;
    border-color: #d63638 !important;
    color: #fff !important;
}

/* Rejection Reason Row */
.rejection-reason-row td {
    background: #fff3cd !important;
    padding: 10px 15px !important;
}

.rejection-reason {
    color: #856404;
    font-size: 13px;
}

.rejection-reason strong {
    margin-left: 5px;
}

/* No Files */
.no-files {
    text-align: center;
    padding: 30px;
    color: #646970;
}

.no-files p {
    margin: 0;
}

/* Page Actions */
.page-actions {
    margin-top: 20px;
}

.page-actions .button .dashicons {
    margin-left: 5px;
    vertical-align: middle;
}

/* Modal Styles */
.tabesh-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tabesh-modal .modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
}

.tabesh-modal .modal-content {
    position: relative;
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
}

.tabesh-modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.tabesh-modal .modal-header h3 {
    margin: 0;
    font-size: 16px;
}

.tabesh-modal .modal-close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
    padding: 0;
    line-height: 1;
}

.tabesh-modal .modal-close-btn:hover {
    color: #1d2327;
}

.tabesh-modal .modal-body {
    padding: 20px;
}

.tabesh-modal .form-group {
    margin-bottom: 15px;
}

.tabesh-modal .form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.tabesh-modal .form-group textarea {
    width: 100%;
    resize: vertical;
}

.tabesh-modal .form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* RTL Adjustments */
[dir="rtl"] .order-info-grid .info-label {
    text-align: right;
}

[dir="rtl"] .rejection-reason strong {
    margin-left: 0;
    margin-right: 5px;
}

[dir="rtl"] .page-actions .button .dashicons {
    margin-left: 0;
    margin-right: 5px;
}

/* Responsive */
@media (max-width: 782px) {
    .order-info-grid {
        grid-template-columns: 1fr;
    }
    
    .files-table .column-date {
        display: none;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Approve file
    $('.approve-btn').on('click', function() {
        var fileId = $(this).data('file-id');
        var $row = $(this).closest('tr');
        
        if (!confirm('<?php echo esc_js(__('آیا از تایید این فایل اطمینان دارید؟', 'tabesh')); ?>')) {
            return;
        }
        
        $.ajax({
            url: '<?php echo esc_url(rest_url(TABESH_REST_NAMESPACE . '/approve-file')); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: {
                file_id: fileId
            },
            success: function(response) {
                if (response.success) {
                    // Update status badge
                    $row.find('.file-status-badge')
                        .removeClass('status-pending')
                        .addClass('status-approved')
                        .text('<?php echo esc_js(__('تایید شده', 'tabesh')); ?>');
                    
                    // Remove action buttons
                    $row.find('.approve-btn, .reject-btn').remove();
                    
                    alert('<?php echo esc_js(__('فایل با موفقیت تایید شد.', 'tabesh')); ?>');
                } else {
                    alert(response.message || '<?php echo esc_js(__('خطا در تایید فایل', 'tabesh')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('خطا در برقراری ارتباط با سرور', 'tabesh')); ?>');
            }
        });
    });
    
    // Open rejection modal
    $('.reject-btn').on('click', function() {
        var fileId = $(this).data('file-id');
        $('#rejection-file-id').val(fileId);
        $('#rejection-reason').val('');
        $('#rejection-modal').show();
    });
    
    // Close rejection modal
    $('.modal-close-btn, .modal-cancel-btn, .modal-overlay').on('click', function() {
        $('#rejection-modal').hide();
    });
    
    // Submit rejection
    $('#rejection-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileId = $('#rejection-file-id').val();
        var reason = $('#rejection-reason').val().trim();
        
        if (!reason) {
            alert('<?php echo esc_js(__('لطفاً دلیل رد را وارد کنید.', 'tabesh')); ?>');
            return;
        }
        
        $.ajax({
            url: '<?php echo esc_url(rest_url(TABESH_REST_NAMESPACE . '/reject-file')); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: {
                file_id: fileId,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    $('#rejection-modal').hide();
                    location.reload();
                } else {
                    alert(response.message || '<?php echo esc_js(__('خطا در رد فایل', 'tabesh')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('خطا در برقراری ارتباط با سرور', 'tabesh')); ?>');
            }
        });
    });
});
</script>
