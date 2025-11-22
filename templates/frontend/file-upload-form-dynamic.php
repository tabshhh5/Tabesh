<?php
/**
 * Dynamic File Upload Form Template
 *
 * Uses smart upload task generator to create dynamic upload requirements
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$file_manager = Tabesh()->file_manager;
$task_generator = Tabesh()->upload_task_generator;

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

// Generate dynamic upload tasks
$upload_tasks = $task_generator->generate_tasks($order);

// Get existing files for this order
$files = $file_manager->get_order_files($order_id);
?>

<div class="tabesh-file-upload-container tabesh-dynamic-upload" dir="rtl">
    <h2><?php _e('آپلود فایل‌های سفارش', 'tabesh'); ?> #<?php echo esc_html($order->order_number); ?></h2>
    
    <div class="tabesh-order-summary">
        <div class="summary-header">
            <span class="dashicons dashicons-info"></span>
            <h3><?php _e('خلاصه سفارش', 'tabesh'); ?></h3>
        </div>
        <div class="summary-content">
            <div class="summary-item">
                <strong><?php _e('قطع:', 'tabesh'); ?></strong>
                <span><?php echo esc_html($order->book_size); ?></span>
            </div>
            <div class="summary-item">
                <strong><?php _e('تعداد صفحات:', 'tabesh'); ?></strong>
                <span><?php echo esc_html($order->page_count_total); ?></span>
            </div>
            <div class="summary-item">
                <strong><?php _e('نوع چاپ:', 'tabesh'); ?></strong>
                <span><?php echo esc_html($order->print_type); ?></span>
            </div>
            <div class="summary-item">
                <strong><?php _e('صحافی:', 'tabesh'); ?></strong>
                <span><?php echo esc_html($order->binding_type); ?></span>
            </div>
        </div>
    </div>

    <!-- Dynamic Upload Tasks -->
    <div class="tabesh-upload-tasks">
        <div class="tasks-header">
            <span class="dashicons dashicons-upload"></span>
            <h3><?php _e('فایل‌های مورد نیاز', 'tabesh'); ?></h3>
        </div>
        
        <?php foreach ($upload_tasks as $task): ?>
        <div class="tabesh-upload-task <?php echo $task['required'] ? 'required-task' : 'optional-task'; ?>" 
             data-task-id="<?php echo esc_attr($task['id']); ?>"
             data-category="<?php echo esc_attr($task['category']); ?>">
            
            <div class="task-header">
                <h4>
                    <?php echo esc_html($task['title']); ?>
                    <?php if ($task['required']): ?>
                    <span class="required-badge"><?php _e('اجباری', 'tabesh'); ?></span>
                    <?php else: ?>
                    <span class="optional-badge"><?php _e('اختیاری', 'tabesh'); ?></span>
                    <?php endif; ?>
                </h4>
            </div>
            
            <p class="task-description">
                <?php echo esc_html($task['description']); ?>
            </p>
            
            <div class="task-requirements">
                <h5><?php _e('الزامات فایل:', 'tabesh'); ?></h5>
                <ul>
                    <?php foreach ($task['requirements'] as $requirement): ?>
                    <li><?php echo esc_html($requirement); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="tabesh-file-upload-area">
                <input type="file" 
                       id="file-<?php echo esc_attr($task['id']); ?>" 
                       class="tabesh-file-input" 
                       accept="<?php echo esc_attr('.' . implode(',.', $task['allowed_types'])); ?>"
                       data-category="<?php echo esc_attr($task['category']); ?>"
                       data-task-id="<?php echo esc_attr($task['id']); ?>"
                       data-order-id="<?php echo esc_attr($order_id); ?>"
                       data-max-size="<?php echo esc_attr($task['max_file_size']); ?>"
                       <?php if (isset($task['max_files']) && $task['max_files'] > 1): ?>
                       multiple
                       <?php endif; ?>>
                <label for="file-<?php echo esc_attr($task['id']); ?>" class="tabesh-file-label">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <span class="text"><?php _e('انتخاب فایل', 'tabesh'); ?></span>
                    <span class="allowed-types">(<?php echo implode(', ', array_map('strtoupper', $task['allowed_types'])); ?>)</span>
                </label>
                <div class="tabesh-file-info"></div>
            </div>
            
            <button type="button" 
                    class="button button-primary tabesh-upload-btn" 
                    data-category="<?php echo esc_attr($task['category']); ?>"
                    data-task-id="<?php echo esc_attr($task['id']); ?>"
                    disabled>
                <?php _e('آپلود فایل', 'tabesh'); ?>
            </button>
            
            <div class="tabesh-upload-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">0%</p>
            </div>
            
            <div class="tabesh-upload-message"></div>
            
            <?php
            // Show existing files for this task category
            $task_files = array_filter($files, function($file) use ($task) {
                return $file->file_category === $task['category'];
            });
            
            if (!empty($task_files)) {
                echo '<div class="tabesh-existing-files">';
                echo '<h5>' . __('فایل‌های آپلود شده:', 'tabesh') . '</h5>';
                foreach ($task_files as $file) {
                    // Security: Validate template path before including
                    $template_path = TABESH_PLUGIN_DIR . 'templates/frontend/file-status-customer.php';
                    if (file_exists($template_path)) {
                        include $template_path;
                    }
                }
                echo '</div>';
            }
            ?>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($upload_tasks)): ?>
        <div class="no-tasks-message">
            <span class="dashicons dashicons-info"></span>
            <p><?php _e('در حال حاضر فایلی برای آپلود مورد نیاز نیست.', 'tabesh'); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- General Instructions -->
    <div class="tabesh-upload-instructions">
        <h3><?php _e('راهنمای کلی آپلود فایل', 'tabesh'); ?></h3>
        <ul>
            <li><?php _e('پس از آپلود، فایل‌ها توسط ادمین بررسی و تایید می‌شوند', 'tabesh'); ?></li>
            <li><?php _e('در صورت رد شدن فایل، می‌توانید نسخه جدید آپلود کنید', 'tabesh'); ?></li>
            <li><?php _e('فایل‌های رد شده پس از 5 روز به طور خودکار حذف می‌شوند', 'tabesh'); ?></li>
            <li><?php _e('در صورت عدم تطابق فایل با استانداردها، هزینه اصلاح به مبلغ سفارش اضافه خواهد شد', 'tabesh'); ?></li>
        </ul>
    </div>
    
    <?php
    // Include correction fees summary if there are any
    // Security: Validate template path before including
    $fees_template_path = TABESH_PLUGIN_DIR . 'templates/partials/correction-fees-summary.php';
    if (file_exists($fees_template_path)) {
        include $fees_template_path;
    }
    ?>
</div>

<!-- Loading Animation Modal -->
<div id="tabesh-upload-animation" class="tabesh-modal" style="display: none;">
    <div class="tabesh-modal-content">
        <div class="tabesh-loader"></div>
        <p><?php _e('در حال اسکن و بررسی فایل‌های شما... لطفاً صبر کنید.', 'tabesh'); ?></p>
    </div>
</div>
