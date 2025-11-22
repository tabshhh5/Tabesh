<?php
/**
 * Modern User Orders Template
 * Complete redesign with modern UI/UX
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user = Tabesh()->user;
$orders = $user->get_user_orders();
$archived_orders = $user->get_user_archived_orders();
?>

<div class="tabesh-user-orders-modern" dir="rtl" data-theme="light">
    <!-- Theme Toggle -->
    <button class="theme-toggle" id="theme-toggle" aria-label="تغییر تم">
        <span class="theme-icon-light">☀️</span>
        <span class="theme-icon-dark">🌙</span>
    </button>

    <!-- Header Section -->
    <div class="orders-header">
        <div class="orders-header-content">
            <h1 class="orders-title">
                <span class="title-icon">📦</span>
                سفارشات من
            </h1>
            
            <!-- Search Bar -->
            <div class="search-container">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <input 
                        type="text" 
                        id="order-search-input" 
                        class="search-input" 
                        placeholder="جستجو بر اساس عنوان کتاب، شماره سفارش، قطع..."
                        autocomplete="off"
                    >
                    <button class="search-clear" id="search-clear-btn" style="display: none;">✕</button>
                </div>
                <div class="search-results" id="search-results" style="display: none;">
                    <div class="search-results-content"></div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards" id="summary-cards">
            <div class="summary-card">
                <div class="summary-icon">📊</div>
                <div class="summary-content">
                    <div class="summary-label">کل سفارشات</div>
                    <div class="summary-value" id="total-orders">-</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">✅</div>
                <div class="summary-content">
                    <div class="summary-label">تکمیل شده</div>
                    <div class="summary-value" id="completed-orders">-</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">⏳</div>
                <div class="summary-content">
                    <div class="summary-label">در حال انجام</div>
                    <div class="summary-value" id="active-orders">-</div>
                </div>
            </div>
            <div class="summary-card primary">
                <div class="summary-icon">💰</div>
                <div class="summary-content">
                    <div class="summary-label">مجموع مبلغ</div>
                    <div class="summary-value" id="total-price">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="orders-container">
        <?php if (empty($orders) && empty($archived_orders)): ?>
            <div class="no-orders">
                <div class="no-orders-icon">📭</div>
                <h3>هنوز سفارشی ثبت نشده است</h3>
                <p>با ثبت سفارش جدید، می‌توانید پروژه‌های خود را پیگیری کنید</p>
                <a href="<?php echo esc_url(home_url('/order')); ?>" class="btn btn-primary btn-new-order">
                    <span class="btn-icon">➕</span>
                    ثبت سفارش جدید
                </a>
            </div>
        <?php else: ?>
            <!-- Orders List -->
            <div class="orders-list" id="orders-list">
                <?php if (!empty($orders)): ?>
                    <div class="orders-section">
                        <h2 class="section-title">
                            <span class="section-icon">📋</span>
                            سفارشات فعال
                        </h2>
                        
                        <?php foreach ($orders as $order): 
                            $extras = maybe_unserialize($order->extras);
                            $status_steps = $user->get_status_steps($order->status);
                        ?>
                            <div class="order-card" data-order-id="<?php echo esc_attr($order->id); ?>">
                                <div class="order-card-header">
                                    <div class="order-card-title">
                                        <h3 class="order-book-title">
                                            📖 <?php echo esc_html($order->book_title ?: 'بدون عنوان'); ?>
                                        </h3>
                                        <span class="order-number">#<?php echo esc_html($order->order_number); ?></span>
                                    </div>
                                    <span class="order-status status-<?php echo esc_attr($order->status); ?>">
                                        <?php echo esc_html($user->get_status_label($order->status)); ?>
                                    </span>
                                </div>

                                <div class="order-card-body">
                                    <div class="order-quick-info">
                                        <div class="info-item">
                                            <span class="info-icon">📄</span>
                                            <span class="info-text"><?php echo esc_html($order->page_count_total); ?> صفحه</span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-icon">📚</span>
                                            <span class="info-text"><?php echo esc_html($order->quantity); ?> نسخه</span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-icon">📐</span>
                                            <span class="info-text"><?php echo esc_html($order->book_size); ?></span>
                                        </div>
                                        <div class="info-item primary">
                                            <span class="info-icon">💵</span>
                                            <span class="info-text"><?php echo number_format($order->total_price); ?> تومان</span>
                                        </div>
                                    </div>

                                    <!-- Progress Stepper -->
                                    <div class="progress-stepper">
                                        <?php 
                                        $step_index = 0;
                                        foreach ($status_steps as $status => $step): 
                                            $step_index++;
                                            $is_completed = $step['completed'];
                                            $is_current = ($status === $order->status);
                                        ?>
                                            <div class="progress-step <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>">
                                                <div class="step-connector"></div>
                                                <div class="step-circle">
                                                    <?php if ($is_completed): ?>
                                                        <span class="step-check">✓</span>
                                                    <?php else: ?>
                                                        <span class="step-number"><?php echo $step_index; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="step-label"><?php echo esc_html($step['label']); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="order-card-footer">
                                        <span class="order-date">
                                            <span class="date-icon">📅</span>
                                            <?php echo esc_html(date_i18n('j F Y', strtotime($order->created_at))); ?>
                                        </span>
                                        <div class="order-actions">
                                            <button class="btn btn-secondary btn-support" data-order-id="<?php echo esc_attr($order->id); ?>" data-order-number="<?php echo esc_attr($order->order_number); ?>" data-book-title="<?php echo esc_attr($order->book_title); ?>">
                                                <span class="btn-icon">📞</span>
                                                پشتیبانی
                                            </button>
                                            <button class="btn btn-primary btn-details" data-order-id="<?php echo esc_attr($order->id); ?>">
                                                <span class="btn-icon">👁️</span>
                                                جزئیات بیشتر
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Archived Orders -->
                <?php if (!empty($archived_orders)): ?>
                    <div class="orders-section archived-section">
                        <h2 class="section-title">
                            <span class="section-icon">🗄️</span>
                            سفارشات بایگانی شده
                        </h2>
                        
                        <?php foreach ($archived_orders as $order): ?>
                            <div class="order-card archived" data-order-id="<?php echo esc_attr($order->id); ?>">
                                <div class="order-card-header">
                                    <div class="order-card-title">
                                        <h3 class="order-book-title">
                                            📖 <?php echo esc_html($order->book_title ?: 'بدون عنوان'); ?>
                                        </h3>
                                        <span class="order-number">#<?php echo esc_html($order->order_number); ?></span>
                                    </div>
                                    <span class="order-status status-<?php echo esc_attr($order->status); ?>">
                                        <?php echo esc_html($user->get_status_label($order->status)); ?>
                                    </span>
                                </div>

                                <div class="order-card-body">
                                    <div class="order-quick-info">
                                        <div class="info-item">
                                            <span class="info-icon">📅</span>
                                            <span class="info-text"><?php echo esc_html(date_i18n('Y/m/d', strtotime($order->created_at))); ?></span>
                                        </div>
                                        <div class="info-item primary">
                                            <span class="info-icon">💵</span>
                                            <span class="info-text"><?php echo number_format($order->total_price); ?> تومان</span>
                                        </div>
                                    </div>

                                    <div class="order-card-footer">
                                        <button class="btn btn-primary btn-details" data-order-id="<?php echo esc_attr($order->id); ?>">
                                            <span class="btn-icon">👁️</span>
                                            مشاهده جزئیات
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Details Modal (Full Screen) -->
    <div class="order-modal" id="order-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-title">جزئیات سفارش</h2>
                <button class="modal-close" id="modal-close">✕</button>
            </div>
            <div class="modal-body" id="modal-body">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>در حال بارگذاری...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Modal -->
    <div class="support-modal" id="support-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">درخواست پشتیبانی</h2>
                <button class="modal-close" id="support-modal-close">✕</button>
            </div>
            <div class="modal-body">
                <div class="support-order-info" id="support-order-info">
                    <!-- Order info will be inserted here -->
                </div>
                
                <div class="support-options">
                    <h3>روش‌های تماس:</h3>
                    
                    <div class="support-option">
                        <div class="support-option-icon">📞</div>
                        <div class="support-option-content">
                            <h4>تماس تلفنی</h4>
                            <div class="phone-numbers">
                                <a href="tel:+989929828425" class="phone-link">0992-982-8425</a>
                                <a href="tel:+989125538967" class="phone-link">0912-553-8967</a>
                                <a href="tel:+982537237301" class="phone-link">025-3723-7301</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="support-option">
                        <div class="support-option-icon">🎫</div>
                        <div class="support-option-content">
                            <h4>ارسال تیکت</h4>
                            <a href="https://pchapco.com/panel/?p=send-ticket" target="_blank" class="btn btn-primary">
                                <span class="btn-icon">📝</span>
                                ارسال تیکت پشتیبانی
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>در حال پردازش...</p>
        </div>
    </div>
</div>
