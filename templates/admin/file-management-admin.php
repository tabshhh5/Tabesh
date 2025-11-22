<?php
/**
 * Admin File Management Template
 *
 * Displays file management interface for admin
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// $order_id variable is expected to be passed
if (!isset($order_id)) {
    return;
}

$file_manager = Tabesh()->file_manager;

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

// Get files for this order
$files = $file_manager->get_order_files($order_id);

?>

<div class="tabesh-admin-file-management" dir="rtl">
    <h2><?php _e('مدیریت فایل‌های سفارش', 'tabesh'); ?> #<?php echo esc_html($order->order_number); ?></h2>
    
    <div class="tabesh-order-summary">
        <p><strong><?php _e('مشتری:', 'tabesh'); ?></strong> <?php 
            $user = get_user_by('id', $order->user_id);
            echo $user ? esc_html($user->display_name) : __('کاربر حذف شده', 'tabesh');
        ?></p>
        <p><strong><?php _e('قطع:', 'tabesh'); ?></strong> <?php echo esc_html($order->book_size); ?></p>
        <p><strong><?php _e('تعداد صفحات:', 'tabesh'); ?></strong> <?php echo esc_html($order->page_count_total); ?></p>
    </div>

    <?php if (empty($files)): ?>
    <div class="notice notice-info">
        <p><?php _e('هیچ فایلی برای این سفارش آپلود نشده است.', 'tabesh'); ?></p>
    </div>
    <?php else: ?>
    
    <div class="tabesh-files-grid">
        <?php foreach ($files as $file): ?>
        <div class="tabesh-admin-file-item" data-file-id="<?php echo esc_attr($file->id); ?>">
            <div class="file-card">
                <div class="file-header">
                    <div class="file-icon">
                        <?php
                        $icon_class = 'dashicons-media-default';
                        $ext = strtolower($file->file_type);
                        
                        if ($ext === 'pdf') {
                            $icon_class = 'dashicons-pdf';
                        } elseif (in_array($ext, array('jpg', 'jpeg', 'png'))) {
                            $icon_class = 'dashicons-format-image';
                        } elseif ($ext === 'psd') {
                            $icon_class = 'dashicons-admin-customizer';
                        } elseif (in_array($ext, array('zip', 'rar'))) {
                            $icon_class = 'dashicons-archive';
                        }
                        ?>
                        <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                    </div>
                    
                    <div class="file-status-badge status-<?php echo esc_attr($file->status); ?>">
                        <?php
                        $status_labels = array(
                            'pending' => __('در انتظار', 'tabesh'),
                            'approved' => __('تایید شده', 'tabesh'),
                            'rejected' => __('رد شده', 'tabesh'),
                        );
                        echo esc_html(isset($status_labels[$file->status]) ? $status_labels[$file->status] : $file->status);
                        ?>
                    </div>
                </div>
                
                <div class="file-body">
                    <h4 class="file-title"><?php 
                        $category_labels = array(
                            'book_content' => __('محتوای کتاب', 'tabesh'),
                            'book_cover' => __('جلد کتاب', 'tabesh'),
                            'document' => __('مدرک', 'tabesh'),
                        );
                        echo esc_html(isset($category_labels[$file->file_category]) ? $category_labels[$file->file_category] : $file->file_category);
                    ?></h4>
                    
                    <p class="file-name"><?php echo esc_html($file->original_filename); ?></p>
                    
                    <div class="file-meta">
                        <span><?php echo size_format($file->file_size); ?></span> •
                        <span><?php echo esc_html(strtoupper($file->file_type)); ?></span> •
                        <span><?php echo sprintf(__('نسخه %d', 'tabesh'), $file->version); ?></span>
                    </div>
                    
                    <p class="file-date">
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($file->created_at)); ?>
                    </p>
                    
                    <?php if ($file->status === 'approved'): ?>
                    <div class="file-approval-info">
                        <p>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php 
                            $approver = get_user_by('id', $file->approved_by);
                            echo sprintf(
                                __('تایید شده توسط %s', 'tabesh'),
                                $approver ? esc_html($approver->display_name) : __('ادمین', 'tabesh')
                            );
                            ?>
                        </p>
                        <?php if ($file->ftp_path): ?>
                        <p class="ftp-status">
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <?php _e('منتقل شده به FTP', 'tabesh'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($file->status === 'rejected' && !empty($file->rejection_reason)): ?>
                    <div class="file-rejection-info">
                        <p><strong><?php _e('دلیل رد:', 'tabesh'); ?></strong></p>
                        <p><?php echo wp_kses_post($file->rejection_reason); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($file->validation_data)): ?>
                    <div class="file-validation-info">
                        <?php
                        $validation_data = json_decode($file->validation_data, true);
                        if (is_array($validation_data)) {
                            if (isset($validation_data['warnings']) && !empty($validation_data['warnings'])) {
                                echo '<div class="validation-warnings">';
                                echo '<h5>' . __('هشدارها:', 'tabesh') . '</h5>';
                                echo '<ul>';
                                foreach ($validation_data['warnings'] as $warning) {
                                    echo '<li>' . esc_html($warning) . '</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }
                            
                            if (isset($validation_data['errors']) && !empty($validation_data['errors'])) {
                                echo '<div class="validation-errors">';
                                echo '<h5>' . __('خطاها:', 'tabesh') . '</h5>';
                                echo '<ul>';
                                foreach ($validation_data['errors'] as $error) {
                                    echo '<li>' . esc_html($error) . '</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="file-actions">
                    <?php if ($file->status === 'pending'): ?>
                    <button type="button" class="button button-primary approve-file-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('تایید', 'tabesh'); ?>
                    </button>
                    <button type="button" class="button button-secondary reject-file-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('رد کردن', 'tabesh'); ?>
                    </button>
                    <?php endif; ?>
                    
                    <button type="button" class="button download-file-btn" data-file-id="<?php echo esc_attr($file->id); ?>" 
                            title="<?php esc_attr_e('دانلود فایل', 'tabesh'); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('دانلود', 'tabesh'); ?>
                    </button>
                    
                    <button type="button" class="button add-comment-btn" data-file-id="<?php echo esc_attr($file->id); ?>" 
                            title="<?php esc_attr_e('افزودن نظر', 'tabesh'); ?>">
                        <span class="dashicons dashicons-admin-comments"></span>
                        <?php _e('نظر دادن', 'tabesh'); ?>
                    </button>
                    
                    <button type="button" class="button view-file-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('مشاهده', 'tabesh'); ?>
                    </button>
                    
                    <?php if ($file->version > 1): ?>
                    <button type="button" class="button view-versions-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                        <span class="dashicons dashicons-backup"></span>
                        <?php echo sprintf(__('نسخه‌ها (%d)', 'tabesh'), $file->version); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</div>

<!-- Rejection Modal -->
<div id="rejection-modal" class="tabesh-modal" style="display: none;">
    <div class="tabesh-modal-overlay"></div>
    <div class="tabesh-modal-dialog">
        <div class="tabesh-modal-content">
            <div class="tabesh-modal-header">
                <h3><?php _e('رد کردن فایل', 'tabesh'); ?></h3>
                <button type="button" class="tabesh-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="tabesh-modal-body">
                <p><?php _e('لطفاً دلیل رد کردن فایل را وارد کنید:', 'tabesh'); ?></p>
                <textarea id="rejection-reason" rows="5" style="width: 100%;"></textarea>
            </div>
            <div class="tabesh-modal-footer">
                <button type="button" class="button button-secondary tabesh-modal-close">
                    <?php _e('انصراف', 'tabesh'); ?>
                </button>
                <button type="button" class="button button-primary" id="confirm-rejection">
                    <?php _e('رد کردن فایل', 'tabesh'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.tabesh-admin-file-management {
    padding: 20px;
}

.tabesh-order-summary {
    background: #f7f7f7;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.tabesh-order-summary p {
    margin: 5px 0;
}

.tabesh-files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.file-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    transition: box-shadow 0.3s ease;
}

.file-card:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.file-icon .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #666;
}

.file-status-badge {
    padding: 5px 12px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.file-body {
    margin-bottom: 15px;
}

.file-title {
    margin: 0 0 10px;
    font-size: 16px;
    color: #23282d;
}

.file-name {
    margin: 5px 0;
    font-size: 14px;
    color: #555;
    word-break: break-all;
}

.file-meta,
.file-date {
    font-size: 13px;
    color: #666;
    margin: 5px 0;
}

.file-approval-info,
.file-rejection-info,
.file-validation-info {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
    font-size: 13px;
}

.file-approval-info {
    background: #d4edda;
    color: #155724;
}

.file-rejection-info {
    background: #f8d7da;
    color: #721c24;
}

.file-validation-info {
    background: #fff3cd;
    color: #856404;
}

.file-validation-info ul {
    margin: 5px 0;
    padding-right: 20px;
}

.file-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.file-actions .button {
    font-size: 13px;
}

.file-actions .dashicons {
    vertical-align: middle;
    margin-left: 3px;
}

/* Modal Styles */
.tabesh-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999999;
}

.tabesh-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
}

.tabesh-modal-dialog {
    position: relative;
    max-width: 500px;
    margin: 50px auto;
    z-index: 1000000;
}

.tabesh-modal-content {
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.tabesh-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tabesh-modal-header h3 {
    margin: 0;
}

.tabesh-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.tabesh-modal-body {
    padding: 20px;
}

.tabesh-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: left;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentFileId = null;
    
    // Approve file
    $('.approve-file-btn').on('click', function() {
        var fileId = $(this).data('file-id');
        
        if (!confirm('<?php _e('آیا از تایید این فایل اطمینان دارید؟', 'tabesh'); ?>')) {
            return;
        }
        
        $.ajax({
            url: tabeshAdminData.restUrl + '/approve-file',
            type: 'POST',
            data: {
                file_id: fileId
            },
            headers: {
                'X-WP-Nonce': tabeshAdminData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('<?php _e('خطا در تایید فایل', 'tabesh'); ?>');
            }
        });
    });
    
    // Reject file - show modal
    $('.reject-file-btn').on('click', function() {
        currentFileId = $(this).data('file-id');
        $('#rejection-modal').fadeIn(300);
        $('#rejection-reason').val('');
    });
    
    // Close modal
    $('.tabesh-modal-close').on('click', function() {
        $('#rejection-modal').fadeOut(300);
        currentFileId = null;
    });
    
    // Confirm rejection
    $('#confirm-rejection').on('click', function() {
        var reason = $('#rejection-reason').val();
        
        if (reason) {
            reason = reason.trim();
        }
        
        if (!reason) {
            alert('<?php _e('لطفاً دلیل رد کردن را وارد کنید', 'tabesh'); ?>');
            return;
        }
        
        $.ajax({
            url: tabeshAdminData.restUrl + '/reject-file',
            type: 'POST',
            data: {
                file_id: currentFileId,
                reason: reason
            },
            headers: {
                'X-WP-Nonce': tabeshAdminData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert(response.message);
                }
                $('#rejection-modal').fadeOut(300);
            },
            error: function() {
                alert('<?php _e('خطا در رد کردن فایل', 'tabesh'); ?>');
            }
        });
    });
    
    // View/Download file
    $('.view-file-btn').on('click', function() {
        var fileId = $(this).data('file-id');
        var $btn = $(this);
        
        // Disable button during request
        $btn.prop('disabled', true).text('در حال دریافت لینک...');
        
        // Generate download token
        $.ajax({
            url: tabeshAdminData.restUrl + '/generate-download-token',
            type: 'POST',
            data: {
                file_id: fileId
            },
            headers: {
                'X-WP-Nonce': tabeshAdminData.nonce
            },
            success: function(response) {
                if (response.success && response.download_url) {
                    // Open download URL in new tab
                    window.open(response.download_url, '_blank');
                    $btn.prop('disabled', false).text('مشاهده فایل');
                } else {
                    alert(response.message || 'خطا در دریافت لینک دانلود');
                    $btn.prop('disabled', false).text('مشاهده فایل');
                }
            },
            error: function(xhr) {
                var message = 'خطا در ارتباط با سرور';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert(message);
                $btn.prop('disabled', false).text('مشاهده فایل');
            }
        });
    });
    
    // View versions (placeholder)
    $('.view-versions-btn').on('click', function() {
        var fileId = $(this).data('file-id');
        // TODO: Implement versions modal
        alert('نسخه‌های فایل: ' + fileId);
    });
});
</script>
