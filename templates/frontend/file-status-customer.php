<?php
/**
 * File Status Customer Template
 *
 * Displays file status for customer view
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// $file variable is expected to be passed from parent template
if (!isset($file)) {
    return;
}

$status_classes = array(
    'pending' => 'tabesh-status-pending',
    'approved' => 'tabesh-status-approved',
    'rejected' => 'tabesh-status-rejected',
);

$status_labels = array(
    'pending' => __('در انتظار بررسی', 'tabesh'),
    'approved' => __('تایید شده', 'tabesh'),
    'rejected' => __('رد شده', 'tabesh'),
);

$status_class = isset($status_classes[$file->status]) ? $status_classes[$file->status] : 'tabesh-status-pending';
$status_label = isset($status_labels[$file->status]) ? $status_labels[$file->status] : $file->status;

// Calculate time remaining for rejected files
$time_remaining = '';
$expires_timestamp = 0;
$is_file_deleted = !empty($file->deleted_at);
if ($file->status === 'rejected' && $file->expires_at) {
    $expires_timestamp = strtotime($file->expires_at);
    $current_timestamp = current_time('timestamp');
    $time_diff = $expires_timestamp - $current_timestamp;
    
    if ($time_diff > 0 && !$is_file_deleted) {
        $days = floor($time_diff / (60 * 60 * 24));
        $hours = floor(($time_diff % (60 * 60 * 24)) / (60 * 60));
        $minutes = floor(($time_diff % (60 * 60)) / 60);
        
        if ($days > 0) {
            $time_remaining = sprintf(__('%d روز، %d ساعت و %d دقیقه', 'tabesh'), $days, $hours, $minutes);
        } elseif ($hours > 0) {
            $time_remaining = sprintf(__('%d ساعت و %d دقیقه', 'tabesh'), $hours, $minutes);
        } else {
            $time_remaining = sprintf(__('%d دقیقه', 'tabesh'), $minutes);
        }
    } else {
        $time_remaining = __('منقضی شده', 'tabesh');
    }
}
?>

<div class="tabesh-file-item <?php echo esc_attr($status_class); ?>" data-file-id="<?php echo esc_attr($file->id); ?>" 
     <?php if ($file->status === 'rejected' && $expires_timestamp > 0 && !$is_file_deleted): ?>
     data-expires-at="<?php echo esc_attr($expires_timestamp); ?>"
     <?php endif; ?>>
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
        
        <div class="file-info">
            <h5 class="file-name"><?php echo esc_html($file->original_filename); ?></h5>
            <p class="file-meta">
                <?php echo size_format($file->file_size); ?> • 
                <?php echo esc_html(strtoupper($file->file_type)); ?> •
                <?php echo sprintf(__('نسخه %d', 'tabesh'), $file->version); ?>
            </p>
            <p class="file-date">
                <?php echo sprintf(
                    __('آپلود شده در: %s', 'tabesh'),
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($file->created_at))
                ); ?>
            </p>
        </div>
        
        <div class="file-status">
            <span class="status-badge"><?php echo esc_html($status_label); ?></span>
        </div>
    </div>
    
    <?php if ($file->status === 'rejected' && !empty($file->rejection_reason)): ?>
    <div class="file-rejection">
        <p class="rejection-title"><strong><?php _e('دلیل رد:', 'tabesh'); ?></strong></p>
        <p class="rejection-reason"><?php echo wp_kses_post($file->rejection_reason); ?></p>
        
        <?php if ($is_file_deleted): ?>
        <div class="file-deleted-notice">
            <span class="dashicons dashicons-dismiss"></span>
            <p><?php _e('این فایل حذف شده است و دیگر قابل دانلود نیست. لطفاً نسخه جدید آپلود کنید.', 'tabesh'); ?></p>
        </div>
        <?php elseif (!empty($time_remaining) && $time_remaining !== __('منقضی شده', 'tabesh')): ?>
        <div class="rejection-timer" data-countdown="true">
            <span class="dashicons dashicons-clock"></span>
            <span class="timer-label"><?php _e('زمان باقی‌مانده تا حذف خودکار:', 'tabesh'); ?></span>
            <span class="timer-value" data-expires="<?php echo esc_attr($expires_timestamp); ?>">
                <?php echo esc_html($time_remaining); ?>
            </span>
        </div>
        <p class="file-deletion-warning">
            <span class="dashicons dashicons-warning"></span>
            <?php _e('پس از اتمام این زمان، فایل به طور خودکار حذف خواهد شد.', 'tabesh'); ?>
        </p>
        <?php elseif ($time_remaining === __('منقضی شده', 'tabesh')): ?>
        <div class="file-expired-notice">
            <span class="dashicons dashicons-info"></span>
            <p><?php _e('زمان نگهداری این فایل به پایان رسیده و به زودی حذف خواهد شد.', 'tabesh'); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_file_deleted): ?>
        <button type="button" class="button button-secondary tabesh-reupload-btn" data-category="<?php echo esc_attr($file->file_category); ?>">
            <?php _e('آپلود مجدد', 'tabesh'); ?>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($file->status === 'approved'): ?>
    <div class="file-approval">
        <p class="approval-date">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php echo sprintf(
                __('تایید شده در: %s', 'tabesh'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($file->approved_at))
            ); ?>
        </p>
        
        <?php if ($file->ftp_path): ?>
        <p class="ftp-status">
            <span class="dashicons dashicons-cloud-upload"></span>
            <?php _e('فایل به سرور انتقال داده شد', 'tabesh'); ?>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($file->status === 'pending'): ?>
    <div class="file-pending">
        <p class="pending-message">
            <span class="dashicons dashicons-info"></span>
            <?php _e('فایل در انتظار بررسی توسط ادمین است. به محض بررسی، از طریق پیامک و ایمیل مطلع خواهید شد.', 'tabesh'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Validation Data Display -->
    <?php if (!empty($file->validation_data)): ?>
    <div class="file-validation-data">
        <?php
        $validation_data = json_decode($file->validation_data, true);
        if (is_array($validation_data)) {
            if (isset($validation_data['warnings']) && !empty($validation_data['warnings'])) {
                echo '<div class="validation-warnings">';
                echo '<h6>' . __('هشدارها:', 'tabesh') . '</h6>';
                echo '<ul>';
                foreach ($validation_data['warnings'] as $warning) {
                    echo '<li>' . esc_html($warning) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            if (isset($validation_data['data']['requires_confirmation']) && $validation_data['data']['requires_confirmation']) {
                echo '<div class="validation-confirmation">';
                echo '<p class="confirmation-message">';
                echo '<span class="dashicons dashicons-warning"></span> ';
                echo __('این فایل نیاز به تایید شما دارد. لطفاً هشدارهای بالا را مطالعه کنید.', 'tabesh');
                echo '</p>';
                
                if (isset($validation_data['data']['correction_fee']) && $validation_data['data']['correction_fee'] > 0) {
                    echo '<p class="correction-fee">';
                    echo sprintf(
                        __('هزینه اصلاح: %s تومان', 'tabesh'),
                        number_format($validation_data['data']['correction_fee'])
                    );
                    echo '</p>';
                }
                echo '</div>';
            }
        }
        ?>
    </div>
    <?php endif; ?>
    
    <!-- View Versions -->
    <?php if ($file->version > 1): ?>
    <div class="file-versions">
        <button type="button" class="button button-small tabesh-view-versions-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
            <?php echo sprintf(__('مشاهده %d نسخه قبلی', 'tabesh'), $file->version - 1); ?>
        </button>
    </div>
    <?php endif; ?>
</div>
