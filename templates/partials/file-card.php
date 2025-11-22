<?php
/**
 * File Card Partial Template
 *
 * Displays a single file card with all details and actions
 * 
 * Variables expected:
 * - $file: File object from database
 * - $order: Order object (optional)
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($file)) {
    return;
}

// Get validation data if available
$validation_data = null;
if (!empty($file->validation_data)) {
    $validation_data = json_decode($file->validation_data, true);
}

// Get document metadata if this is a document
$document_metadata = null;
if ($file->file_category === 'document') {
    global $wpdb;
    $metadata_table = $wpdb->prefix . 'tabesh_document_metadata';
    // Using esc_sql for table name safety (wpdb->prepare doesn't support %i in all WP versions)
    $metadata_table = esc_sql($metadata_table);
    $document_metadata = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$metadata_table}` WHERE file_id = %d",
        $file->id
    ));
}

// Determine file icon
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

// Get file comments count
global $wpdb;
$comments_table = esc_sql($wpdb->prefix . 'tabesh_file_comments');
$comments_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM `{$comments_table}` WHERE file_id = %d",
    $file->id
));

?>

<div class="tabesh-file-card" data-file-id="<?php echo esc_attr($file->id); ?>">
    <div class="file-card-header">
        <div class="file-icon-wrapper">
            <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
        </div>
        
        <div class="file-title-section">
            <h5 class="file-original-name"><?php echo esc_html($file->original_filename); ?></h5>
            <div class="file-meta-info">
                <span class="file-size"><?php echo size_format($file->file_size); ?></span>
                <span class="separator">•</span>
                <span class="file-type"><?php echo esc_html(strtoupper($file->file_type)); ?></span>
                <?php if ($file->version > 1): ?>
                    <span class="separator">•</span>
                    <span class="file-version"><?php echo sprintf(__('نسخه %d', 'tabesh'), $file->version); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="file-status-badge status-<?php echo esc_attr($file->status); ?>">
            <?php
            $status_labels = array(
                'pending' => __('در انتظار بررسی', 'tabesh'),
                'approved' => __('تایید شده', 'tabesh'),
                'rejected' => __('رد شده', 'tabesh'),
            );
            echo esc_html(isset($status_labels[$file->status]) ? $status_labels[$file->status] : $file->status);
            ?>
        </div>
    </div>

    <div class="file-card-body">
        <!-- Upload Date -->
        <div class="file-info-row">
            <span class="dashicons dashicons-calendar"></span>
            <strong><?php _e('تاریخ آپلود:', 'tabesh'); ?></strong>
            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($file->created_at)); ?>
        </div>

        <!-- Validation Status -->
        <?php if ($validation_data): ?>
        <div class="file-validation-section">
            <h6><span class="dashicons dashicons-yes-alt"></span> <?php _e('وضعیت اعتبارسنجی', 'tabesh'); ?></h6>
            
            <!-- Standard/Non-standard Status -->
            <?php if (isset($validation_data['data']['is_standard'])): ?>
            <div class="validation-standard-status <?php echo $validation_data['data']['is_standard'] ? 'standard' : 'non-standard'; ?>">
                <?php if ($validation_data['data']['is_standard']): ?>
                    <span class="dashicons dashicons-yes"></span>
                    <strong><?php _e('استاندارد', 'tabesh'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php _e('غیراستاندارد', 'tabesh'); ?></strong>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Errors -->
            <?php if (!empty($validation_data['errors'])): ?>
            <div class="validation-errors">
                <strong><?php _e('خطاها:', 'tabesh'); ?></strong>
                <ul>
                    <?php foreach ($validation_data['errors'] as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Warnings -->
            <?php if (!empty($validation_data['warnings'])): ?>
            <div class="validation-warnings">
                <strong><?php _e('هشدارها:', 'tabesh'); ?></strong>
                <ul>
                    <?php foreach ($validation_data['warnings'] as $warning): ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Validation Data -->
            <?php if (!empty($validation_data['data'])): ?>
            <div class="validation-data">
                <?php if (isset($validation_data['data']['page_count'])): ?>
                    <div class="data-item">
                        <strong><?php _e('تعداد صفحات:', 'tabesh'); ?></strong>
                        <?php echo intval($validation_data['data']['page_count']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($validation_data['data']['detected_size'])): ?>
                    <div class="data-item">
                        <strong><?php _e('اندازه تشخیص داده شده:', 'tabesh'); ?></strong>
                        <?php 
                        $size = $validation_data['data']['detected_size'];
                        echo esc_html(sprintf('%.0f × %.0f پیکسل', floatval($size['width']), floatval($size['height']))); 
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($validation_data['data']['dpi'])): ?>
                    <div class="data-item">
                        <strong><?php _e('وضوح (DPI):', 'tabesh'); ?></strong>
                        <?php echo intval($validation_data['data']['dpi']); ?>
                        <?php if (isset($validation_data['data']['dpi_ok']) && $validation_data['data']['dpi_ok']): ?>
                            <span class="dashicons dashicons-yes validation-ok-icon"></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($validation_data['data']['color_mode'])): ?>
                    <div class="data-item">
                        <strong><?php _e('حالت رنگی:', 'tabesh'); ?></strong>
                        <?php echo esc_html($validation_data['data']['color_mode']); ?>
                        <?php if (isset($validation_data['data']['color_mode_ok']) && $validation_data['data']['color_mode_ok']): ?>
                            <span class="dashicons dashicons-yes validation-ok-icon"></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($validation_data['data']['correction_fee'])): ?>
                    <div class="data-item correction-fee">
                        <strong><?php _e('هزینه اصلاح:', 'tabesh'); ?></strong>
                        <?php echo number_format($validation_data['data']['correction_fee']); ?> تومان
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Document Metadata -->
        <?php if ($document_metadata): ?>
        <div class="document-metadata-section">
            <h6><span class="dashicons dashicons-id-alt"></span> <?php _e('اطلاعات مدرک', 'tabesh'); ?></h6>
            
            <div class="metadata-grid">
                <?php if ($document_metadata->document_type): ?>
                    <div class="metadata-item">
                        <strong><?php _e('نوع مدرک:', 'tabesh'); ?></strong>
                        <?php
                        $doc_type_labels = array(
                            'birth_certificate' => __('شناسنامه', 'tabesh'),
                            'national_id' => __('کارت ملی', 'tabesh'),
                            'official_letter' => __('نامه اداری', 'tabesh'),
                            'license' => __('پروانه', 'tabesh'),
                        );
                        echo esc_html(isset($doc_type_labels[$document_metadata->document_type]) ? 
                            $doc_type_labels[$document_metadata->document_type] : 
                            $document_metadata->document_type);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->first_name): ?>
                    <div class="metadata-item">
                        <strong><?php _e('نام:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->first_name); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->last_name): ?>
                    <div class="metadata-item">
                        <strong><?php _e('نام خانوادگی:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->last_name); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->national_id): ?>
                    <div class="metadata-item">
                        <strong><?php _e('کد ملی:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->national_id); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->birth_certificate_number): ?>
                    <div class="metadata-item">
                        <strong><?php _e('شماره شناسنامه:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->birth_certificate_number); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->subject): ?>
                    <div class="metadata-item full-width">
                        <strong><?php _e('موضوع:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->subject); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->issuing_organization): ?>
                    <div class="metadata-item">
                        <strong><?php _e('سازمان صادرکننده:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->issuing_organization); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->recipient): ?>
                    <div class="metadata-item">
                        <strong><?php _e('گیرنده:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->recipient); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($document_metadata->licensing_authority): ?>
                    <div class="metadata-item">
                        <strong><?php _e('مرجع صدور پروانه:', 'tabesh'); ?></strong>
                        <?php echo esc_html($document_metadata->licensing_authority); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rejection Info -->
        <?php if ($file->status === 'rejected' && !empty($file->rejection_reason)): ?>
        <div class="file-rejection-section">
            <h6><span class="dashicons dashicons-dismiss"></span> <?php _e('دلیل رد', 'tabesh'); ?></h6>
            <p><?php echo wp_kses_post($file->rejection_reason); ?></p>
            
            <?php 
            // Get countdown information
            $file_manager = Tabesh()->file_manager;
            $countdown = $file_manager->get_file_deletion_countdown($file);
            ?>
            
            <?php if ($countdown['is_deleted'] ?? false): ?>
                <div class="deletion-notice deleted">
                    <span class="dashicons dashicons-warning"></span>
                    <?php echo esc_html($countdown['message']); ?>
                </div>
            <?php elseif ($countdown['has_countdown']): ?>
                <div class="deletion-countdown" 
                     data-expires-at="<?php echo esc_attr($countdown['expires_at']); ?>"
                     data-seconds="<?php echo esc_attr($countdown['seconds_remaining']); ?>">
                    <span class="dashicons dashicons-clock"></span>
                    <strong><?php _e('زمان باقیمانده تا حذف:', 'tabesh'); ?></strong>
                    <span class="countdown-timer">
                        <?php echo esc_html($countdown['message']); ?>
                    </span>
                </div>
            <?php elseif ($countdown['expired'] ?? false): ?>
                <div class="deletion-notice expired">
                    <span class="dashicons dashicons-warning"></span>
                    <?php echo esc_html($countdown['message']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Approval Info -->
        <?php if ($file->status === 'approved'): ?>
        <div class="file-approval-section">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php 
            $approver = get_user_by('id', $file->approved_by);
            echo sprintf(
                __('تایید شده توسط %s در تاریخ %s', 'tabesh'),
                $approver ? esc_html($approver->display_name) : __('ادمین', 'tabesh'),
                date_i18n(get_option('date_format'), strtotime($file->approved_at))
            );
            ?>
            <?php if ($file->ftp_path): ?>
                <br><small><?php _e('منتقل شده به FTP', 'tabesh'); ?></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Comments Section -->
        <?php if ($comments_count > 0): ?>
        <div class="file-comments-preview">
            <button type="button" class="view-comments-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                <span class="dashicons dashicons-admin-comments"></span>
                <?php echo sprintf(__('مشاهده نظرات (%d)', 'tabesh'), $comments_count); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- File Actions -->
    <div class="file-card-actions">
        <?php if ($file->status === 'approved'): ?>
            <button type="button" class="button button-primary download-file-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                <span class="dashicons dashicons-download"></span>
                <?php _e('دانلود', 'tabesh'); ?>
            </button>
        <?php endif; ?>
        
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
        
        <button type="button" class="button add-comment-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
            <span class="dashicons dashicons-admin-comments"></span>
            <?php _e('افزودن نظر', 'tabesh'); ?>
        </button>
        
        <?php if ($file->version > 1): ?>
            <button type="button" class="button view-versions-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                <span class="dashicons dashicons-backup"></span>
                <?php echo sprintf(__('نسخه‌ها (%d)', 'tabesh'), $file->version); ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<script>
// Secure file download handler
jQuery(document).ready(function($) {
    $('.download-file-btn').on('click', function() {
        var $btn = $(this);
        var fileId = $btn.data('file-id');
        
        // Disable button
        $btn.prop('disabled', true);
        var originalText = $btn.html();
        $btn.html('<span class="dashicons dashicons-update spin"></span> در حال آماده‌سازی...');
        
        // Generate download token
        $.ajax({
            url: '<?php echo rest_url('tabesh/v1/generate-download-token'); ?>',
            method: 'POST',
            data: {
                file_id: fileId
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                if (response.success && response.download_url) {
                    // Open download in new window to trigger download
                    window.location.href = response.download_url;
                    
                    // Show success message
                    $btn.html('<span class="dashicons dashicons-yes"></span> دانلود شروع شد');
                    
                    // Restore button after 3 seconds
                    setTimeout(function() {
                        $btn.html(originalText).prop('disabled', false);
                    }, 3000);
                } else {
                    alert(response.message || 'خطا در ایجاد لینک دانلود');
                    $btn.html(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
                $btn.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Countdown timer updater
    function updateCountdownTimers() {
        $('.deletion-countdown').each(function() {
            var $countdown = $(this);
            var seconds = parseInt($countdown.data('seconds'));
            
            if (seconds <= 0) {
                $countdown.find('.countdown-timer').html('<span class="countdown-expired">زمان نگهداری به پایان رسیده است</span>');
                return;
            }
            
            // Decrease seconds
            seconds--;
            $countdown.data('seconds', seconds);
            
            // Calculate time components
            var days = Math.floor(seconds / 86400);
            var hours = Math.floor((seconds % 86400) / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var secs = seconds % 60;
            
            // Build time string
            var parts = [];
            if (days > 0) {
                parts.push(days + ' روز');
            }
            if (hours > 0) {
                parts.push(hours + ' ساعت');
            }
            if (minutes > 0 && days === 0) {
                parts.push(minutes + ' دقیقه');
            }
            if (seconds < 3600 && days === 0 && hours === 0) {
                parts.push(secs + ' ثانیه');
            }
            
            var timeString = parts.length > 0 ? parts.join(' و ') : 'کمتر از یک ثانیه';
            $countdown.find('.countdown-timer').html('این فایل در ' + timeString + ' حذف خواهد شد.');
        });
    }
    
    // Update countdown every second
    if ($('.deletion-countdown').length > 0) {
        setInterval(updateCountdownTimers, 1000);
    }
});
</script>

<style>
/* Spinning animation for loading icon */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.dashicons.spin {
    animation: spin 1s linear infinite;
}

/* File Card Styles */
.tabesh-file-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    margin-bottom: 15px;
    transition: box-shadow 0.3s ease;
}

.tabesh-file-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.file-card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border-bottom: 1px solid #e5e5e5;
    background: #f9f9f9;
}

.file-icon-wrapper {
    flex-shrink: 0;
}

.file-icon-wrapper .dashicons {
    font-size: 40px;
    width: 40px;
    height: 40px;
    color: #666;
}

.file-title-section {
    flex-grow: 1;
}

.file-original-name {
    margin: 0 0 5px;
    font-size: 15px;
    color: #23282d;
    word-break: break-word;
}

.file-meta-info {
    font-size: 13px;
    color: #999;
}

.file-meta-info .separator {
    margin: 0 5px;
}

.file-status-badge {
    flex-shrink: 0;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
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

.file-card-body {
    padding: 15px;
}

.file-info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    font-size: 14px;
    color: #555;
}

.file-info-row .dashicons {
    color: #0073aa;
}

/* Validation Section */
.file-validation-section,
.document-metadata-section,
.file-rejection-section,
.file-approval-section {
    margin-top: 15px;
    padding: 12px;
    background: #f9f9f9;
    border-radius: 4px;
}

.file-validation-section h6,
.document-metadata-section h6,
.file-rejection-section h6 {
    margin: 0 0 10px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.validation-standard-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
    font-size: 13px;
}

.validation-standard-status.standard {
    background: #d4edda;
    color: #155724;
}

.validation-standard-status.non-standard {
    background: #fff3cd;
    color: #856404;
}

.validation-errors,
.validation-warnings {
    margin: 10px 0;
}

.validation-errors strong {
    color: #d32f2f;
}

.validation-warnings strong {
    color: #f57c00;
}

.validation-errors ul,
.validation-warnings ul {
    margin: 5px 0 0 20px;
    padding: 0;
    font-size: 13px;
}

.validation-data {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.validation-data .data-item {
    font-size: 13px;
}

.validation-data .data-item strong {
    display: block;
    margin-bottom: 3px;
    color: #666;
}

.correction-fee {
    grid-column: 1 / -1;
    background: #fff3cd;
    padding: 8px;
    border-radius: 3px;
    color: #856404;
}

.validation-ok-icon {
    color: #4caf50;
}

.countdown-expired {
    color: #d32f2f;
    font-weight: 600;
}

/* Document Metadata */
.metadata-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
}

.metadata-item {
    font-size: 13px;
}

.metadata-item.full-width {
    grid-column: 1 / -1;
}

.metadata-item strong {
    display: block;
    margin-bottom: 3px;
    color: #666;
}

/* Approval/Rejection */
.file-approval-section {
    background: #d4edda;
    color: #155724;
}

.file-rejection-section {
    background: #f8d7da;
    color: #721c24;
}

/* Deletion Countdown and Notices */
.deletion-countdown {
    margin-top: 12px;
    padding: 10px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    color: #856404;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.deletion-countdown .dashicons {
    flex-shrink: 0;
}

.deletion-countdown strong {
    display: inline;
}

.countdown-timer {
    font-weight: 600;
}

.deletion-notice {
    margin-top: 12px;
    padding: 10px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.deletion-notice.deleted {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.deletion-notice.expired {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
}

.deletion-notice .dashicons {
    flex-shrink: 0;
}

/* Comments */
.file-comments-preview {
    margin-top: 12px;
}

.view-comments-btn {
    background: #f0f0f0;
    border: 1px solid #ddd;
    padding: 8px 12px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.view-comments-btn:hover {
    background: #e5e5e5;
}

/* Actions */
.file-card-actions {
    padding: 15px;
    border-top: 1px solid #e5e5e5;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.file-card-actions .button {
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.file-card-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
</style>
