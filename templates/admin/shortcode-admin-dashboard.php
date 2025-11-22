<?php
/**
 * Shortcode Admin Dashboard Template
 * 
 * Shows different content based on user role:
 * - Admin users (manage_woocommerce): Full dashboard with statistics and all orders
 * - Regular users (customers, subscribers): Their own orders
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
$is_admin = current_user_can('manage_woocommerce');

if ($is_admin) {
    // Admin view: Show full dashboard
    $admin = Tabesh()->admin;
    $stats = $admin->get_statistics();
    $recent_orders = $admin->get_orders('', false);
    $recent_orders = array_slice($recent_orders, 0, 5);
    ?>

    <div class="tabesh-shortcode-dashboard" dir="rtl">
        <div class="dashboard-stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-label">سفارشات فعال</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($stats['pending_orders']); ?></div>
                <div class="stat-label">در انتظار بررسی</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($stats['processing_orders']); ?></div>
                <div class="stat-label">در حال پردازش</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($stats['completed_orders']); ?></div>
                <div class="stat-label">تکمیل شده</div>
            </div>
        </div>

        <?php if (!empty($recent_orders)): ?>
            <div class="recent-orders">
                <h3>آخرین سفارشات</h3>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('شماره سفارش', 'tabesh'); ?></th>
                            <th><?php echo esc_html__('عنوان کتاب', 'tabesh'); ?></th>
                            <th><?php echo esc_html__('مشتری', 'tabesh'); ?></th>
                            <th><?php echo esc_html__('مبلغ', 'tabesh'); ?></th>
                            <th><?php echo esc_html__('وضعیت', 'tabesh'); ?></th>
                            <th><?php echo esc_html__('تاریخ', 'tabesh'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): 
                            $user = get_userdata($order->user_id);
                        ?>
                            <tr>
                                <td><?php echo esc_html($order->order_number); ?></td>
                                <td><?php echo !empty($order->book_title) ? esc_html($order->book_title) : '<span style="color: #999;">—</span>'; ?></td>
                                <td><?php echo $user ? esc_html($user->display_name) : '-'; ?></td>
                                <td><?php echo number_format($order->total_price); ?> تومان</td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($order->status); ?>">
                                        <?php 
                                        $labels = array(
                                            'pending' => 'در انتظار',
                                            'confirmed' => 'تایید شده',
                                            'processing' => 'در حال چاپ',
                                            'ready' => 'آماده تحویل',
                                            'completed' => 'تحویل شده',
                                            'cancelled' => 'لغو شده'
                                        );
                                        echo esc_html($labels[$order->status] ?? $order->status);
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date_i18n('Y/m/d', strtotime($order->created_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .tabesh-shortcode-dashboard {
        font-family: 'Vazir', 'Tahoma', Arial, sans-serif;
        padding: 20px;
    }

    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }

    .stat-number {
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 14px;
        opacity: 0.9;
    }

    .recent-orders {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .recent-orders h3 {
        margin-top: 0;
        color: #2c3e50;
    }

    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }

    .orders-table th,
    .orders-table td {
        padding: 12px;
        text-align: right;
        border-bottom: 1px solid #ecf0f1;
    }

    .orders-table th {
        background: #f8f9fa;
        font-weight: 600;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge.status-pending { background: #f39c12; color: #fff; }
    .status-badge.status-confirmed { background: #3498db; color: #fff; }
    .status-badge.status-processing { background: #9b59b6; color: #fff; }
    .status-badge.status-ready { background: #1abc9c; color: #fff; }
    .status-badge.status-completed { background: #27ae60; color: #fff; }
    .status-badge.status-cancelled { background: #e74c3c; color: #fff; }
    </style>

<?php
} else {
    // Customer view: Show their orders
    $user = Tabesh()->user;
    echo $user->render_user_orders(array());
}
?>
