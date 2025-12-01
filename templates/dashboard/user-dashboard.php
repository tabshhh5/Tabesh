<?php
/**
 * User Dashboard Template
 *
 * Main template for the unified user dashboard with tab navigation.
 * Combines order form, file upload, and order tracking in a SPA-like interface.
 *
 * @package Tabesh
 * @since 1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get user data
$user_id = get_current_user_id();
$user = wp_get_current_user();
$summary = $this->get_user_summary();

// Get default tab (passed from render_dashboard method, fallback to order-form).
$default_tab = isset( $default_tab ) ? $default_tab : 'order-form';
?>

<div class="tabesh-dashboard" dir="rtl" data-theme="light" data-default-tab="<?php echo esc_attr($default_tab); ?>">
    
    <!-- Header Section -->
    <header class="dashboard-header">
        <div class="header-content">
            <div class="header-title">
                <h1 class="dashboard-title">
                    <span class="title-icon">📋</span>
                    <?php esc_html_e('داشبورد مدیریت سفارش', 'tabesh'); ?>
                </h1>
                <p class="dashboard-subtitle">
                    <?php printf(esc_html__('خوش آمدید، %s', 'tabesh'), esc_html($user->display_name)); ?>
                </p>
            </div>
            
            <!-- Theme Toggle -->
            <button class="theme-toggle" id="theme-toggle" aria-label="<?php esc_attr_e('تغییر تم', 'tabesh'); ?>">
                <span class="theme-icon-light">☀️</span>
                <span class="theme-icon-dark">🌙</span>
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon">📊</div>
                <div class="summary-content">
                    <div class="summary-value"><?php echo esc_html($summary['total_orders']); ?></div>
                    <div class="summary-label"><?php esc_html_e('کل سفارشات', 'tabesh'); ?></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">⏳</div>
                <div class="summary-content">
                    <div class="summary-value"><?php echo esc_html($summary['active_orders']); ?></div>
                    <div class="summary-label"><?php esc_html_e('در حال انجام', 'tabesh'); ?></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">📤</div>
                <div class="summary-content">
                    <div class="summary-value"><?php echo esc_html($summary['pending_uploads']); ?></div>
                    <div class="summary-label"><?php esc_html_e('در انتظار آپلود', 'tabesh'); ?></div>
                </div>
            </div>
            <div class="summary-card highlight">
                <div class="summary-icon">✅</div>
                <div class="summary-content">
                    <div class="summary-value"><?php echo esc_html($summary['completed_orders']); ?></div>
                    <div class="summary-label"><?php esc_html_e('تکمیل شده', 'tabesh'); ?></div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <nav class="tab-navigation" role="tablist">
            <button class="tab-button <?php echo $default_tab === 'order-form' ? 'active' : ''; ?>" 
                    data-tab="order-form" 
                    role="tab" 
                    aria-selected="<?php echo $default_tab === 'order-form' ? 'true' : 'false'; ?>"
                    aria-controls="tab-content-order-form">
                <span class="tab-icon">📝</span>
                <span class="tab-label"><?php esc_html_e('ثبت سفارش', 'tabesh'); ?></span>
            </button>
            <button class="tab-button <?php echo $default_tab === 'upload-manager' ? 'active' : ''; ?>" 
                    data-tab="upload-manager" 
                    role="tab" 
                    aria-selected="<?php echo $default_tab === 'upload-manager' ? 'true' : 'false'; ?>"
                    aria-controls="tab-content-upload-manager">
                <span class="tab-icon">📁</span>
                <span class="tab-label"><?php esc_html_e('مدیریت فایل', 'tabesh'); ?></span>
                <?php if ($summary['pending_uploads'] > 0): ?>
                    <span class="tab-badge"><?php echo esc_html($summary['pending_uploads']); ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-button <?php echo $default_tab === 'user-orders' ? 'active' : ''; ?>" 
                    data-tab="user-orders" 
                    role="tab" 
                    aria-selected="<?php echo $default_tab === 'user-orders' ? 'true' : 'false'; ?>"
                    aria-controls="tab-content-user-orders">
                <span class="tab-icon">📦</span>
                <span class="tab-label"><?php esc_html_e('پیگیری سفارش', 'tabesh'); ?></span>
            </button>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main class="dashboard-content">
        <!-- Order Form Tab -->
        <div class="tab-content <?php echo $default_tab === 'order-form' ? 'active' : ''; ?>" 
             id="tab-content-order-form" 
             role="tabpanel"
             aria-labelledby="tab-order-form">
            <?php include TABESH_PLUGIN_DIR . 'templates/dashboard/partials/tab-order-form.php'; ?>
        </div>

        <!-- Upload Manager Tab -->
        <div class="tab-content <?php echo $default_tab === 'upload-manager' ? 'active' : ''; ?>" 
             id="tab-content-upload-manager" 
             role="tabpanel"
             aria-labelledby="tab-upload-manager">
            <?php include TABESH_PLUGIN_DIR . 'templates/dashboard/partials/tab-upload-manager.php'; ?>
        </div>

        <!-- User Orders Tab -->
        <div class="tab-content <?php echo $default_tab === 'user-orders' ? 'active' : ''; ?>" 
             id="tab-content-user-orders" 
             role="tabpanel"
             aria-labelledby="tab-user-orders">
            <?php include TABESH_PLUGIN_DIR . 'templates/dashboard/partials/tab-user-orders.php'; ?>
        </div>
    </main>

    <!-- Footer with Support Info -->
    <footer class="dashboard-footer">
        <div class="footer-content">
            <div class="support-section">
                <h4 class="support-title">
                    <span class="support-icon">📞</span>
                    <?php esc_html_e('پشتیبانی', 'tabesh'); ?>
                    <button class="help-button" id="help-toggle" aria-label="<?php esc_attr_e('راهنما', 'tabesh'); ?>">
                        <span class="help-icon">❓</span>
                    </button>
                </h4>
                <div class="support-contacts">
                    <a href="tel:+989929828425" class="contact-link">
                        <span class="contact-icon">📱</span>
                        0992-982-8425
                    </a>
                    <a href="tel:+989125538967" class="contact-link">
                        <span class="contact-icon">📱</span>
                        0912-553-8967
                    </a>
                    <a href="tel:+982537237301" class="contact-link">
                        <span class="contact-icon">☎️</span>
                        025-3723-7301
                    </a>
                    <a href="https://pchapco.com/panel/?p=send-ticket" target="_blank" rel="noopener" class="ticket-link">
                        <span class="ticket-icon">🎫</span>
                        <?php esc_html_e('ارسال تیکت پشتیبانی', 'tabesh'); ?>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Help Modal -->
    <div class="help-modal" id="help-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><?php esc_html_e('راهنمای استفاده', 'tabesh'); ?></h2>
                <button class="modal-close" id="help-modal-close">✕</button>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3><span class="help-step-icon">📝</span> <?php esc_html_e('ثبت سفارش', 'tabesh'); ?></h3>
                    <p><?php esc_html_e('در این بخش می‌توانید مشخصات کتاب خود را وارد کرده و قیمت را محاسبه کنید. پس از تایید قیمت، سفارش شما ثبت می‌شود.', 'tabesh'); ?></p>
                </div>
                <div class="help-section">
                    <h3><span class="help-step-icon">📁</span> <?php esc_html_e('مدیریت فایل', 'tabesh'); ?></h3>
                    <p><?php esc_html_e('پس از ثبت سفارش، باید فایل‌های کتاب (متن و جلد) را در این بخش آپلود کنید. می‌توانید نسخه‌های جدید را جایگزین کنید.', 'tabesh'); ?></p>
                </div>
                <div class="help-section">
                    <h3><span class="help-step-icon">📦</span> <?php esc_html_e('پیگیری سفارش', 'tabesh'); ?></h3>
                    <p><?php esc_html_e('وضعیت سفارشات خود را در این بخش پیگیری کنید. از ثبت تا تحویل، همه مراحل را مشاهده خواهید کرد.', 'tabesh'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="loading-spinner">
                <div class="spinner"></div>
            </div>
            <p class="loading-text"><?php esc_html_e('در حال پردازش...', 'tabesh'); ?></p>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>
</div>
