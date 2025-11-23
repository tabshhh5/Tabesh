<?php
/**
 * Admin Dashboard Template
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Helper function for status labels
if (!function_exists('get_status_label')) {
    function get_status_label($status) {
        $labels = array(
            'pending' => 'در انتظار بررسی',
            'confirmed' => 'تایید شده',
            'processing' => 'در حال چاپ',
            'ready' => 'آماده تحویل',
            'completed' => 'تحویل داده شده',
            'cancelled' => 'لغو شده'
        );
        return $labels[$status] ?? $status;
    }
}

$admin = Tabesh()->admin;
$stats = $admin->get_statistics();
?>

<div class="wrap tabesh-admin-dashboard" dir="rtl">
    <h1>داشبورد تابش</h1>

    <!-- FTP Connection Status -->
    <?php
    $ftp_handler = Tabesh()->ftp_handler;
    $ftp_status = $ftp_handler->get_connection_status();
    $status_class = $ftp_status['connected'] ? 'ftp-status-connected' : 'ftp-status-disconnected';
    $status_icon = $ftp_status['connected'] ? 'yes-alt' : 'dismiss';
    ?>
    <div class="notice notice-info tabesh-ftp-status <?php echo esc_attr($status_class); ?>" style="display: flex; align-items: center; padding: 12px; margin-bottom: 20px;">
        <span class="dashicons dashicons-<?php echo esc_attr($status_icon); ?>" style="font-size: 24px; margin-left: 10px;"></span>
        <div style="flex: 1;">
            <strong>وضعیت اتصال FTP:</strong>
            <?php echo esc_html($ftp_status['message']); ?>
            <?php if ($ftp_status['connected'] && $ftp_status['uptime']): ?>
                <span style="margin-right: 15px;">| مدت فعالیت: <?php echo esc_html($ftp_status['uptime']); ?></span>
            <?php endif; ?>
            <?php if (!empty($ftp_status['last_success'])): ?>
                <span style="margin-right: 15px;">| آخرین اتصال موفق: <?php echo esc_html(date_i18n('Y/m/d H:i', strtotime($ftp_status['last_success']))); ?></span>
            <?php endif; ?>
        </div>
        <button type="button" class="button button-small" id="refresh-ftp-status">بروزرسانی</button>
    </div>

    <!-- Statistics Cards -->
    <div class="tabesh-stats-grid">
        <div class="tabesh-stat-card">
            <div class="stat-icon" style="background: #3498db;">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">کل سفارشات فعال</div>
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
            </div>
        </div>

        <div class="tabesh-stat-card">
            <div class="stat-icon" style="background: #f39c12;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">در انتظار بررسی</div>
                <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
            </div>
        </div>

        <div class="tabesh-stat-card">
            <div class="stat-icon" style="background: #9b59b6;">
                <span class="dashicons dashicons-admin-customizer"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">در حال پردازش</div>
                <div class="stat-value"><?php echo number_format($stats['processing_orders']); ?></div>
            </div>
        </div>

        <div class="tabesh-stat-card">
            <div class="stat-icon" style="background: #27ae60;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">تکمیل شده</div>
                <div class="stat-value"><?php echo number_format($stats['completed_orders']); ?></div>
            </div>
        </div>

        <div class="tabesh-stat-card revenue">
            <div class="stat-icon" style="background: #16a085;">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">کل درآمد</div>
                <div class="stat-value"><?php echo number_format($stats['total_revenue']); ?> تومان</div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="tabesh-recent-orders">
        <h2>آخرین سفارشات</h2>
        <?php
        $recent_orders = $admin->get_orders('', false);
        $recent_orders = array_slice($recent_orders, 0, 10);
        ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('شماره سفارش', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('عنوان کتاب', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('مشتری', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('قطع', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('تیراژ', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('مبلغ', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('وضعیت', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('تاریخ', 'tabesh'); ?></th>
                    <th><?php echo esc_html__('عملیات', 'tabesh'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_orders)): ?>
                    <?php foreach ($recent_orders as $order): 
                        $user = get_userdata($order->user_id);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                            <td><?php echo !empty($order->book_title) ? esc_html($order->book_title) : '<span style="color: #999;">—</span>'; ?></td>
                            <td><?php echo $user ? esc_html($user->display_name) : '-'; ?></td>
                            <td><?php echo esc_html($order->book_size); ?></td>
                            <td><?php echo number_format($order->quantity); ?></td>
                            <td><?php echo number_format($order->total_price); ?> تومان</td>
                            <td>
                                <span class="tabesh-status-badge status-<?php echo esc_attr($order->status); ?>">
                                    <?php echo esc_html(get_status_label($order->status)); ?>
                                </span>
                                <?php if ($order->status === 'processing'): 
                                    // Get printing substatus progress
                                    $substatus = Tabesh()->printing_substatus->get_printing_substatus($order->id);
                                    if ($substatus):
                                        $percentage = Tabesh()->printing_substatus->get_completion_percentage($order->id);
                                ?>
                                    <div class="printing-progress-mini" title="<?php echo esc_attr(sprintf(__('پیشرفت چاپ: %d%%', 'tabesh'), $percentage)); ?>">
                                        <div class="progress-bar-mini">
                                            <div class="progress-fill-mini" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                        </div>
                                        <span class="progress-text-mini"><?php echo esc_html($percentage); ?>%</span>
                                    </div>
                                <?php 
                                    endif;
                                endif; 
                                ?>
                            </td>
                            <td><?php echo date_i18n('Y/m/d H:i', strtotime($order->created_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=tabesh-orders&action=view&order_id=' . $order->id); ?>" class="button button-small">مشاهده</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;"><?php echo esc_html__('هیچ سفارشی یافت نشد', 'tabesh'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p>
            <a href="<?php echo admin_url('admin.php?page=tabesh-orders'); ?>" class="button button-primary">مشاهده همه سفارشات</a>
        </p>
    </div>
</div>

<style>
.tabesh-ftp-status {
    border-left: 4px solid #ccc;
}
.tabesh-ftp-status.ftp-status-connected {
    border-left-color: #46b450;
    background-color: #ecf7ed;
}
.tabesh-ftp-status.ftp-status-disconnected {
    border-left-color: #dc3232;
    background-color: #f9e9e9;
}
.tabesh-ftp-status .dashicons {
    color: inherit;
}
.ftp-status-connected .dashicons {
    color: #46b450;
}
.ftp-status-disconnected .dashicons {
    color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#refresh-ftp-status').on('click', function() {
        var $button = $(this);
        var $statusDiv = $('.tabesh-ftp-status');
        
        $button.prop('disabled', true).text('در حال بروزرسانی...');
        
        $.ajax({
            url: '<?php echo rest_url('tabesh/v1/ftp-status'); ?>',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                if (response.success && response.ftp_status) {
                    var status = response.ftp_status;
                    var statusClass = status.connected ? 'ftp-status-connected' : 'ftp-status-disconnected';
                    var statusIcon = status.connected ? 'yes-alt' : 'dismiss';
                    
                    var html = '<span class="dashicons dashicons-' + statusIcon + '" style="font-size: 24px; margin-left: 10px;"></span>';
                    html += '<div style="flex: 1;">';
                    html += '<strong>وضعیت اتصال FTP:</strong> ' + status.message;
                    
                    if (status.connected && status.uptime) {
                        html += '<span style="margin-right: 15px;">| مدت فعالیت: ' + status.uptime + '</span>';
                    }
                    
                    if (status.last_success) {
                        var date = new Date(status.last_success);
                        html += '<span style="margin-right: 15px;">| آخرین اتصال موفق: ' + date.toLocaleString('fa-IR') + '</span>';
                    }
                    
                    html += '</div>';
                    html += '<button type="button" class="button button-small" id="refresh-ftp-status">بروزرسانی</button>';
                    
                    $statusDiv.removeClass('ftp-status-connected ftp-status-disconnected').addClass(statusClass);
                    $statusDiv.html(html);
                } else {
                    alert('خطا در دریافت وضعیت FTP');
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
            },
            complete: function() {
                $button.prop('disabled', false).text('بروزرسانی');
            }
        });
    });
});
</script>

<style>
/* Mini Printing Progress Indicator */
.printing-progress-mini {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    padding: 4px 8px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 11px;
}

.progress-bar-mini {
    flex: 1;
    height: 12px;
    background: #e9ecef;
    border-radius: 6px;
    overflow: hidden;
}

.progress-fill-mini {
    height: 100%;
    background: linear-gradient(90deg, #4a90e2 0%, #67b26f 100%);
    border-radius: 6px;
    transition: width 0.3s ease;
}

.progress-text-mini {
    font-weight: 600;
    color: #4a90e2;
    min-width: 32px;
    text-align: left;
}
</style>
