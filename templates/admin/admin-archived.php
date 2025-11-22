<?php
/**
 * Admin Archived Orders Template
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$admin = Tabesh()->admin;

// Handle restore action
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    if (check_admin_referer('restore_order_' . $order_id)) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        $wpdb->update($table, array('archived' => 0), array('id' => $order_id));
        echo '<div class="notice notice-success"><p>سفارش با موفقیت بازگردانی شد.</p></div>';
    }
}

$orders = $admin->get_orders('', true);
?>

<div class="wrap tabesh-admin-archived" dir="rtl">
    <h1>سفارشات بایگانی شده</h1>

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
                <th><?php echo esc_html__('تاریخ ثبت', 'tabesh'); ?></th>
                <th><?php echo esc_html__('عملیات', 'tabesh'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): 
                    $user = get_userdata($order->user_id);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                        <td><?php echo !empty($order->book_title) ? esc_html($order->book_title) : '<span style="color: #999;">—</span>'; ?></td>
                        <td>
                            <?php if ($user): ?>
                                <?php echo esc_html($user->display_name); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($order->book_size); ?></td>
                        <td><?php echo number_format($order->quantity); ?></td>
                        <td><?php echo number_format($order->total_price); ?> تومان</td>
                        <td>
                            <span class="tabesh-status-badge status-<?php echo esc_attr($order->status); ?>">
                                <?php 
                                $labels = array(
                                    'pending' => 'در انتظار بررسی',
                                    'confirmed' => 'تایید شده',
                                    'processing' => 'در حال چاپ',
                                    'ready' => 'آماده تحویل',
                                    'completed' => 'تحویل داده شده',
                                    'cancelled' => 'لغو شده'
                                );
                                echo esc_html($labels[$order->status] ?? $order->status);
                                ?>
                            </span>
                        </td>
                        <td><?php echo date_i18n('Y/m/d', strtotime($order->created_at)); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=tabesh-archived&action=restore&order_id=' . $order->id), 'restore_order_' . $order->id); ?>" 
                               class="button button-small button-primary">
                                بازگردانی
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">هیچ سفارش بایگانی شده‌ای یافت نشد</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
