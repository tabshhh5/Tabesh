<?php
/**
 * Staff Panel Template - Modern UI
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$staff = Tabesh()->staff;
$orders = $staff->get_assigned_orders();
$current_user = wp_get_current_user();
$avatar_url = get_avatar_url($current_user->ID);

// Status labels
$status_labels = array(
    'pending' => 'در انتظار بررسی',
    'confirmed' => 'تایید شده',
    'processing' => 'در حال چاپ',
    'ready' => 'آماده تحویل',
    'completed' => 'تحویل داده شده',
    'cancelled' => 'لغو شده'
);
?>

<div class="tabesh-staff-panel" dir="rtl">
    <!-- Header Section -->
    <div class="staff-panel-header">
        <div class="staff-profile-section">
            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($current_user->display_name); ?>" class="staff-avatar">
            <div class="staff-info">
                <h2><?php echo esc_html($current_user->display_name); ?></h2>
                <p>خوش آمدید به پنل کارمندان</p>
            </div>
        </div>
        <div class="header-actions">
            <button class="theme-toggle-btn" aria-label="تغییر تم">
                🌙 <span>حالت تاریک</span>
            </button>
            <button class="notification-btn" aria-label="اعلان‌ها">
                🔔
                <span class="notification-badge" style="display: none;">0</span>
            </button>
            <button class="logout-btn" aria-label="خروج">
                🚪 <span>خروج</span>
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-container">
        <div class="search-bar">
            <input type="text" class="search-input" placeholder="جستجو در سفارشات (عنوان کتاب، شماره سفارش، قطع، مشخصات...)">
            <span class="search-icon">🔍</span>
        </div>
    </div>

    <!-- Orders Container -->
    <div class="tabesh-panel-container">
        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <div class="no-orders-icon">📦</div>
                <p>هیچ سفارش فعالی برای پردازش وجود ندارد.</p>
            </div>
        <?php else: ?>
            <div class="tabesh-orders-grid">
                <?php foreach ($orders as $order): 
                    $user = get_userdata($order->user_id);
                    $customer_name = $user ? $user->display_name : 'نامشخص';
                    $extras = maybe_unserialize($order->extras);
                    $is_admin = current_user_can('manage_woocommerce');
                ?>
                    <div class="tabesh-staff-order-card" data-order-id="<?php echo esc_attr($order->id); ?>">
                        <!-- Card Header (Collapsed State) -->
                        <div class="order-card-header">
                            <div class="order-header-top">
                                <h3 class="order-number"><?php echo esc_html($order->order_number); ?></h3>
                                <span class="expand-icon">▼</span>
                            </div>
                            <?php if (!empty($order->book_title)): ?>
                                <div class="book-title"><?php echo esc_html($order->book_title); ?></div>
                            <?php endif; ?>
                            <div class="card-quick-info">
                                <div class="quick-info-item" data-search-size="<?php echo esc_attr($order->book_size); ?>">
                                    📏 <span><?php echo esc_html($order->book_size); ?></span>
                                </div>
                                <div class="quick-info-item">
                                    📊 <span><?php echo number_format($order->quantity); ?> عدد</span>
                                </div>
                                <div class="quick-info-item">
                                    <span class="status-badge status-<?php echo esc_attr($order->status); ?>" data-status="<?php echo esc_attr($order->status); ?>">
                                        <?php echo esc_html($status_labels[$order->status] ?? $order->status); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body (Expanded State) -->
                        <div class="order-card-body">
                            <div class="order-info-grid">
                                <div class="info-item" data-search-customer="<?php echo esc_attr($customer_name); ?>">
                                    <span class="label">مشتری:</span>
                                    <span class="value"><?php echo esc_html($customer_name); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">تاریخ ثبت:</span>
                                    <span class="value"><?php echo date_i18n('Y/m/d - H:i', strtotime($order->created_at)); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">قطع کتاب:</span>
                                    <span class="value"><?php echo esc_html($order->book_size); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">نوع کاغذ:</span>
                                    <span class="value"><?php echo esc_html($order->paper_type); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">گرماژ کاغذ:</span>
                                    <span class="value"><?php echo esc_html($order->paper_weight); ?>g</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">نوع چاپ:</span>
                                    <span class="value"><?php echo esc_html($order->print_type); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">صفحات رنگی:</span>
                                    <span class="value"><?php echo number_format($order->page_count_color); ?> صفحه</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">صفحات سیاه:</span>
                                    <span class="value"><?php echo number_format($order->page_count_bw); ?> صفحه</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">کل صفحات:</span>
                                    <span class="value"><?php echo number_format($order->page_count_total); ?> صفحه</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">تیراژ:</span>
                                    <span class="value"><?php echo number_format($order->quantity); ?> عدد</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">نوع صحافی:</span>
                                    <span class="value"><?php echo esc_html($order->binding_type); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">نوع سلفون:</span>
                                    <span class="value"><?php echo esc_html($order->lamination_type ?: 'ندارد'); ?></span>
                                </div>
                                <?php if (!empty($order->cover_paper_type)): ?>
                                <div class="info-item">
                                    <span class="label">نوع کاغذ جلد:</span>
                                    <span class="value"><?php echo esc_html($order->cover_paper_type); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($order->cover_paper_weight)): ?>
                                <div class="info-item">
                                    <span class="label">گرماژ جلد:</span>
                                    <span class="value"><?php echo esc_html($order->cover_paper_weight); ?>g</span>
                                </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <span class="label">نوع مجوز:</span>
                                    <span class="value"><?php echo esc_html($order->license_type); ?></span>
                                </div>
                                <!-- Show price only to admins -->
                                <?php if ($is_admin): ?>
                                <div class="info-item">
                                    <span class="label">مبلغ کل:</span>
                                    <span class="value" style="color: var(--accent-gold); font-size: 17px;">
                                        <?php echo number_format($order->total_price); ?> تومان
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($extras) && is_array($extras)): ?>
                                <div class="extras-section">
                                    <span class="section-label">خدمات اضافی:</span>
                                    <div class="extras-list">
                                        <?php foreach ($extras as $extra): ?>
                                            <span class="extra-item"><?php echo esc_html($extra); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order->notes)): ?>
                                <div class="notes-section">
                                    <span class="section-label">توضیحات سفارش:</span>
                                    <div class="notes-content"><?php echo nl2br(esc_html($order->notes)); ?></div>
                                </div>
                            <?php endif; ?>

                            <!-- Status Stepper -->
                            <div class="status-stepper">
                                <div class="stepper-header">مراحل سفارش</div>
                                <div class="stepper-container">
                                    <?php 
                                    $statuses = array(
                                        'pending' => 'در انتظار',
                                        'confirmed' => 'تایید شده',
                                        'processing' => 'در حال چاپ',
                                        'ready' => 'آماده',
                                        'completed' => 'تحویل'
                                    );
                                    $current_status = $order->status;
                                    $status_keys = array_keys($statuses);
                                    $current_index = array_search($current_status, $status_keys);
                                    if ($current_index === false) $current_index = 0;
                                    
                                    foreach ($statuses as $key => $label):
                                        $index = array_search($key, $status_keys);
                                        $class = '';
                                        if ($index < $current_index) {
                                            $class = 'completed';
                                        } elseif ($index === $current_index) {
                                            $class = 'active';
                                        }
                                    ?>
                                        <div class="stepper-step <?php echo $class; ?>" data-status="<?php echo esc_attr($key); ?>">
                                            <div class="step-circle"><?php echo $index + 1; ?></div>
                                            <div class="step-label"><?php echo esc_html($label); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Status Update Section -->
                            <div class="status-update-section">
                                <div class="status-select-wrapper">
                                    <select class="status-update-select">
                                        <option value="">انتخاب وضعیت جدید...</option>
                                        <option value="pending">در انتظار بررسی</option>
                                        <option value="confirmed">تایید شده</option>
                                        <option value="processing">در حال چاپ</option>
                                        <option value="ready">آماده تحویل</option>
                                        <option value="completed">تحویل داده شده</option>
                                        <option value="cancelled">لغو شده</option>
                                    </select>
                                    <button class="status-update-btn">به‌روزرسانی وضعیت</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
