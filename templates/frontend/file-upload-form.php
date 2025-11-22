<?php
/**
 * File Upload Form Template
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$file_manager = Tabesh()->file_manager;

// Get order details
global $wpdb;
$order_table = $wpdb->prefix . 'tabesh_orders';
$order = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $order_table WHERE id = %d AND user_id = %d",
    $order_id,
    $user_id
));

if (!$order) {
    echo '<div class="tabesh-notice error">' . __('سفارش یافت نشد یا متعلق به شما نیست.', 'tabesh') . '</div>';
    return;
}

// Get existing files for this order
$files = $file_manager->get_order_files($order_id);

// Get allowed file types
$allowed_types = Tabesh()->get_setting('file_allowed_types', array('pdf', 'jpg', 'jpeg', 'png', 'psd', 'doc', 'docx', 'zip', 'rar'));
?>

<div class="tabesh-file-upload-container" dir="rtl">
    <h2><?php _e('آپلود فایل‌های سفارش', 'tabesh'); ?> #<?php echo esc_html($order->order_number); ?></h2>
    
    <div class="tabesh-order-info">
        <p><strong><?php _e('قطع:', 'tabesh'); ?></strong> <?php echo esc_html($order->book_size); ?></p>
        <p><strong><?php _e('تعداد صفحات:', 'tabesh'); ?></strong> <?php echo esc_html($order->page_count_total); ?></p>
        <p><strong><?php _e('نوع چاپ:', 'tabesh'); ?></strong> <?php echo esc_html($order->print_type); ?></p>
    </div>

    <!-- Upload Tasks -->
    <div class="tabesh-upload-tasks">
        <h3><?php _e('فایل‌های مورد نیاز', 'tabesh'); ?></h3>
        
        <!-- Book Content Upload -->
        <div class="tabesh-upload-task" data-category="book_content">
            <h4><?php _e('محتوای کتاب (PDF)', 'tabesh'); ?></h4>
            <p class="description">
                <?php _e('فایل PDF محتوای کتاب خود را آپلود کنید. فایل باید شامل همه صفحات کتاب باشد.', 'tabesh'); ?>
            </p>
            
            <div class="tabesh-file-upload-area">
                <input type="file" 
                       id="file-book-content" 
                       class="tabesh-file-input" 
                       accept=".pdf"
                       data-category="book_content"
                       data-order-id="<?php echo esc_attr($order_id); ?>">
                <label for="file-book-content" class="tabesh-file-label">
                    <span class="dashicons dashicons-upload"></span>
                    <span class="text"><?php _e('انتخاب فایل PDF', 'tabesh'); ?></span>
                </label>
                <div class="tabesh-file-info"></div>
            </div>
            
            <button type="button" 
                    class="button button-primary tabesh-upload-btn" 
                    data-category="book_content"
                    disabled>
                <?php _e('آپلود فایل محتوا', 'tabesh'); ?>
            </button>
            
            <div class="tabesh-upload-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">0%</p>
            </div>
            
            <div class="tabesh-upload-message"></div>
            
            <?php
            // Show existing book content files
            $content_files = array_filter($files, function($file) {
                return $file->file_category === 'book_content';
            });
            
            if (!empty($content_files)) {
                echo '<div class="tabesh-existing-files">';
                echo '<h5>' . __('فایل‌های آپلود شده:', 'tabesh') . '</h5>';
                foreach ($content_files as $file) {
                    include TABESH_PLUGIN_DIR . 'templates/frontend/file-status-customer.php';
                }
                echo '</div>';
            }
            ?>
        </div>

        <!-- Book Cover Upload -->
        <div class="tabesh-upload-task" data-category="book_cover">
            <h4><?php _e('جلد کتاب', 'tabesh'); ?></h4>
            <p class="description">
                <?php _e('فایل جلد کتاب را آپلود کنید. فرمت‌های مجاز: PSD, PDF, JPG, PNG (حداقل 300 DPI، CMYK)', 'tabesh'); ?>
            </p>
            
            <div class="tabesh-file-upload-area">
                <input type="file" 
                       id="file-book-cover" 
                       class="tabesh-file-input" 
                       accept=".psd,.pdf,.jpg,.jpeg,.png"
                       data-category="book_cover"
                       data-order-id="<?php echo esc_attr($order_id); ?>">
                <label for="file-book-cover" class="tabesh-file-label">
                    <span class="dashicons dashicons-upload"></span>
                    <span class="text"><?php _e('انتخاب فایل جلد', 'tabesh'); ?></span>
                </label>
                <div class="tabesh-file-info"></div>
            </div>
            
            <button type="button" 
                    class="button button-primary tabesh-upload-btn" 
                    data-category="book_cover"
                    disabled>
                <?php _e('آپلود فایل جلد', 'tabesh'); ?>
            </button>
            
            <div class="tabesh-upload-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">0%</p>
            </div>
            
            <div class="tabesh-upload-message"></div>
            
            <?php
            // Show existing book cover files
            $cover_files = array_filter($files, function($file) {
                return $file->file_category === 'book_cover';
            });
            
            if (!empty($cover_files)) {
                echo '<div class="tabesh-existing-files">';
                echo '<h5>' . __('فایل‌های آپلود شده:', 'tabesh') . '</h5>';
                foreach ($cover_files as $file) {
                    include TABESH_PLUGIN_DIR . 'templates/frontend/file-status-customer.php';
                }
                echo '</div>';
            }
            ?>
        </div>

        <!-- Document Upload (Optional) -->
        <div class="tabesh-upload-task" data-category="document">
            <h4><?php _e('مدارک (اختیاری)', 'tabesh'); ?></h4>
            <p class="description">
                <?php _e('در صورت نیاز، مدارک مورد نظر (شناسنامه، کارت ملی، مجوز و ...) را آپلود کنید.', 'tabesh'); ?>
            </p>
            
            <div class="tabesh-file-upload-area">
                <input type="file" 
                       id="file-document" 
                       class="tabesh-file-input" 
                       accept=".pdf,.jpg,.jpeg,.png"
                       data-category="document"
                       data-order-id="<?php echo esc_attr($order_id); ?>">
                <label for="file-document" class="tabesh-file-label">
                    <span class="dashicons dashicons-upload"></span>
                    <span class="text"><?php _e('انتخاب فایل مدرک', 'tabesh'); ?></span>
                </label>
                <div class="tabesh-file-info"></div>
            </div>
            
            <button type="button" 
                    class="button button-primary tabesh-upload-btn" 
                    data-category="document"
                    disabled>
                <?php _e('آپلود مدرک', 'tabesh'); ?>
            </button>
            
            <div class="tabesh-upload-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">0%</p>
            </div>
            
            <div class="tabesh-upload-message"></div>
            
            <?php
            // Show existing document files
            $document_files = array_filter($files, function($file) {
                return $file->file_category === 'document';
            });
            
            if (!empty($document_files)) {
                echo '<div class="tabesh-existing-files">';
                echo '<h5>' . __('فایل‌های آپلود شده:', 'tabesh') . '</h5>';
                foreach ($document_files as $file) {
                    include TABESH_PLUGIN_DIR . 'templates/frontend/file-status-customer.php';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <!-- Upload Instructions -->
    <div class="tabesh-upload-instructions">
        <h3><?php _e('راهنمای آپلود فایل', 'tabesh'); ?></h3>
        <ul>
            <li><?php _e('حداکثر حجم فایل PDF: 50 مگابایت', 'tabesh'); ?></li>
            <li><?php _e('حداکثر حجم تصاویر: 10 مگابایت', 'tabesh'); ?></li>
            <li><?php _e('فایل جلد باید با کیفیت حداقل 300 DPI و در فضای رنگی CMYK باشد', 'tabesh'); ?></li>
            <li><?php _e('تعداد صفحات فایل محتوا باید با سفارش شما مطابقت داشته باشد (±2 صفحه)', 'tabesh'); ?></li>
            <li><?php _e('پس از آپلود، فایل‌ها توسط ادمین بررسی و تایید می‌شوند', 'tabesh'); ?></li>
            <li><?php _e('در صورت رد شدن فایل، می‌توانید نسخه جدید آپلود کنید', 'tabesh'); ?></li>
        </ul>
    </div>
</div>

<!-- Loading Animation Modal -->
<div id="tabesh-upload-animation" class="tabesh-modal" style="display: none;">
    <div class="tabesh-modal-content">
        <div class="tabesh-loader"></div>
        <p><?php _e('در حال اسکن و رمزنگاری فایل‌های شما... لطفاً صبر کنید.', 'tabesh'); ?></p>
    </div>
</div>
