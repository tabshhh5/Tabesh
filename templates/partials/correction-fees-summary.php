<?php
/**
 * Correction Fees Summary Partial
 *
 * Displays total correction fees for an order
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// $order_id variable is expected to be passed from parent template
if (!isset($order_id) || !$order_id) {
    return;
}

$file_manager = Tabesh()->file_manager;
$fees_data = $file_manager->calculate_order_correction_fees($order_id);

// Only display if there are correction fees
if ($fees_data['total_fee'] <= 0) {
    return;
}
?>

<div class="tabesh-correction-fees-summary">
    <div class="fees-header">
        <span class="dashicons dashicons-warning"></span>
        <h4><?php _e('هزینه‌های اصلاح فایل', 'tabesh'); ?></h4>
    </div>
    
    <div class="fees-content">
        <p class="fees-description">
            <?php echo sprintf(
                _n(
                    'تعداد %d فایل نیاز به اصلاح دارد:',
                    'تعداد %d فایل نیاز به اصلاح دارند:',
                    $fees_data['file_count'],
                    'tabesh'
                ),
                $fees_data['file_count']
            ); ?>
        </p>
        
        <div class="fees-breakdown">
            <?php foreach ($fees_data['breakdown'] as $item): ?>
            <div class="fee-item">
                <div class="fee-file-info">
                    <span class="file-icon">
                        <?php
                        $icon = 'dashicons-media-default';
                        if ($item['category'] === 'book_content') {
                            $icon = 'dashicons-pdf';
                        } elseif ($item['category'] === 'book_cover') {
                            $icon = 'dashicons-format-image';
                        }
                        ?>
                        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                    </span>
                    <div class="file-details">
                        <strong><?php echo esc_html($item['filename']); ?></strong>
                        <?php if (!empty($item['issues'])): ?>
                        <span class="file-issues">
                            (<?php echo esc_html(implode('، ', $item['issues'])); ?>)
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="fee-amount">
                    <?php echo number_format($item['fee'] / 10); ?> <?php _e('تومان', 'tabesh'); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="fees-total">
            <span class="total-label"><?php _e('جمع هزینه‌های اصلاح:', 'tabesh'); ?></span>
            <span class="total-amount">
                <?php echo number_format($fees_data['total_fee_toman']); ?> <?php _e('تومان', 'tabesh'); ?>
            </span>
        </div>
        
        <div class="fees-notice">
            <span class="dashicons dashicons-info"></span>
            <p><?php _e('این هزینه‌ها به مبلغ نهایی سفارش شما اضافه خواهند شد و باید در زمان پرداخت نهایی پرداخت شوند.', 'tabesh'); ?></p>
        </div>
    </div>
</div>
