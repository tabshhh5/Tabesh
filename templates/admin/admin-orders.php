<?php
/**
 * Admin Orders Template
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$admin = Tabesh()->admin;

// Handle actions
if (isset($_GET['action']) && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    $action = sanitize_text_field($_GET['action']);

    if ($action === 'archive' && check_admin_referer('archive_order_' . $order_id)) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        $wpdb->update($table, array('archived' => 1), array('id' => $order_id));
        echo '<div class="notice notice-success"><p>سفارش با موفقیت بایگانی شد.</p></div>';
    }
}

$orders = $admin->get_orders('', false);
?>

<div class="wrap tabesh-admin-orders" dir="rtl">
    <h1>سفارشات فعال</h1>

    <!-- Filter Bar -->
    <div class="tabesh-filter-bar">
        <select id="status-filter" class="tabesh-filter">
            <option value="">همه وضعیت‌ها</option>
            <option value="pending">در انتظار بررسی</option>
            <option value="confirmed">تایید شده</option>
            <option value="processing">در حال چاپ</option>
            <option value="ready">آماده تحویل</option>
            <option value="completed">تحویل داده شده</option>
        </select>
    </div>

    <!-- Orders Table -->
    <table class="wp-list-table widefat fixed striped tabesh-orders-table">
        <thead>
            <tr>
                <th><?php echo esc_html__('شماره سفارش', 'tabesh'); ?></th>
                <th><?php echo esc_html__('عنوان کتاب', 'tabesh'); ?></th>
                <th><?php echo esc_html__('مشتری', 'tabesh'); ?></th>
                <th><?php echo esc_html__('قطع', 'tabesh'); ?></th>
                <th><?php echo esc_html__('صفحات', 'tabesh'); ?></th>
                <th><?php echo esc_html__('تیراژ', 'tabesh'); ?></th>
                <th><?php echo esc_html__('صحافی', 'tabesh'); ?></th>
                <th><?php echo esc_html__('مبلغ', 'tabesh'); ?></th>
                <th><?php echo esc_html__('وضعیت', 'tabesh'); ?></th>
                <th><?php echo esc_html__('تاریخ', 'tabesh'); ?></th>
                <th><?php echo esc_html__('عملیات', 'tabesh'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): 
                    $user = get_userdata($order->user_id);
                ?>
                    <tr data-status="<?php echo esc_attr($order->status); ?>">
                        <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                        <td><?php echo !empty($order->book_title) ? esc_html($order->book_title) : '<span style="color: #999;">—</span>'; ?></td>
                        <td>
                            <?php if ($user): ?>
                                <a href="<?php echo get_edit_user_link($user->ID); ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($order->book_size); ?></td>
                        <td><?php echo number_format($order->page_count_total); ?></td>
                        <td><?php echo number_format($order->quantity); ?></td>
                        <td><?php echo esc_html($order->binding_type); ?></td>
                        <td><?php echo number_format($order->total_price); ?> تومان</td>
                        <td>
                            <select class="tabesh-status-select" data-order-id="<?php echo $order->id; ?>">
                                <option value="pending" <?php selected($order->status, 'pending'); ?>>در انتظار بررسی</option>
                                <option value="confirmed" <?php selected($order->status, 'confirmed'); ?>>تایید شده</option>
                                <option value="processing" <?php selected($order->status, 'processing'); ?>>در حال چاپ</option>
                                <option value="ready" <?php selected($order->status, 'ready'); ?>>آماده تحویل</option>
                                <option value="completed" <?php selected($order->status, 'completed'); ?>>تحویل داده شده</option>
                                <option value="cancelled" <?php selected($order->status, 'cancelled'); ?>>لغو شده</option>
                            </select>
                        </td>
                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($order->created_at)); ?></td>
                        <td>
                            <button type="button" class="button button-small view-order-details" data-order-id="<?php echo $order->id; ?>">
                                مشاهده جزئیات
                            </button>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=tabesh-orders&action=archive&order_id=' . $order->id), 'archive_order_' . $order->id); ?>" 
                               class="button button-small" 
                               onclick="return confirm('آیا از بایگانی این سفارش اطمینان دارید؟');">
                                بایگانی
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center;">هیچ سفارشی یافت نشد</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Order Details Modal -->
<div id="order-details-modal" class="tabesh-modal" style="display: none;">
    <div class="tabesh-modal-overlay"></div>
    <div class="tabesh-modal-dialog" style="max-width: 95%; width: 1200px;">
        <div class="tabesh-modal-content">
            <div class="tabesh-modal-header">
                <h2>جزئیات سفارش</h2>
                <button type="button" class="tabesh-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="tabesh-modal-body" id="order-details-content">
                <p style="text-align: center; padding: 40px;">
                    <span class="spinner is-active" style="float: none;"></span>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // View order details
    $('.view-order-details').on('click', function() {
        var orderId = $(this).data('order-id');
        loadOrderDetails(orderId);
    });

    // Close modal
    $(document).on('click', '.tabesh-modal-close', function() {
        $('#order-details-modal').fadeOut(300);
    });

    // Close modal on overlay click
    $(document).on('click', '.tabesh-modal-overlay', function() {
        $('#order-details-modal').fadeOut(300);
    });

    function loadOrderDetails(orderId) {
        $('#order-details-modal').fadeIn(300);
        $('#order-details-content').html('<p style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none;"></span></p>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tabesh_load_order_details',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('tabesh_order_details'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#order-details-content').html(response.data.html);
                } else {
                    $('#order-details-content').html('<p style="text-align: center; color: #d32f2f;">خطا در بارگذاری اطلاعات سفارش</p>');
                }
            },
            error: function() {
                $('#order-details-content').html('<p style="text-align: center; color: #d32f2f;">خطا در ارتباط با سرور</p>');
            }
        });
    }
});
</script>
