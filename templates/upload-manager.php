<?php
/**
 * Upload Manager Template
 *
 * Modern, responsive upload interface for managing order files.
 * Features Neumorphism/Soft UI design with RTL support.
 *
 * @package Tabesh
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$user = wp_get_current_user();
?>

<div class="tabesh-upload-manager" dir="rtl" data-theme="light">
    <!-- Theme Toggle -->
    <button class="theme-toggle" id="theme-toggle" aria-label="<?php esc_attr_e('تغییر تم', 'tabesh'); ?>">
        <span class="theme-icon-light">☀️</span>
        <span class="theme-icon-dark">🌙</span>
    </button>

    <!-- Header Section -->
    <header class="upload-header">
        <div class="header-content">
            <h1 class="page-title">
                <span class="title-icon">📁</span>
                <?php esc_html_e('مدیریت آپلود فایل', 'tabesh'); ?>
            </h1>
            <p class="page-subtitle"><?php esc_html_e('فایل‌های سفارشات خود را آپلود و مدیریت کنید', 'tabesh'); ?></p>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
            <div class="search-input-wrapper">
                <span class="search-icon">🔍</span>
                <input 
                    type="text" 
                    id="order-search-input" 
                    class="search-input" 
                    placeholder="<?php esc_attr_e('جستجو بر اساس عنوان کتاب، شماره سفارش، قطع، تیراژ، تعداد صفحه...', 'tabesh'); ?>"
                    autocomplete="off"
                >
                <button class="search-clear" id="search-clear-btn" style="display: none;" aria-label="<?php esc_attr_e('پاک کردن جستجو', 'tabesh'); ?>">✕</button>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="upload-content">
        <!-- Orders List -->
        <div class="orders-list-section">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="section-icon">📋</span>
                    <?php esc_html_e('سفارشات شما', 'tabesh'); ?>
                </h2>
                <div class="section-actions">
                    <span class="order-count" id="order-count"></span>
                </div>
            </div>

            <!-- Orders Container (loaded via JS) -->
            <div class="orders-container" id="orders-container">
                <div class="loading-state">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                    <p><?php esc_html_e('در حال بارگذاری سفارشات...', 'tabesh'); ?></p>
                </div>
            </div>

            <!-- Load More Button -->
            <div class="load-more-container" id="load-more-container" style="display: none;">
                <button class="btn btn-secondary btn-load-more" id="load-more-btn">
                    <span class="btn-icon">📄</span>
                    <?php esc_html_e('مشاهده بیشتر', 'tabesh'); ?>
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state" style="display: none;">
            <div class="empty-icon">📭</div>
            <h3><?php esc_html_e('سفارشی یافت نشد', 'tabesh'); ?></h3>
            <p id="empty-message"><?php esc_html_e('هنوز سفارشی ثبت نشده است.', 'tabesh'); ?></p>
        </div>

        <!-- No Results State -->
        <div class="no-results-state" id="no-results-state" style="display: none;">
            <div class="no-results-icon">🔍</div>
            <h3><?php esc_html_e('نتیجه‌ای یافت نشد', 'tabesh'); ?></h3>
            <p><?php esc_html_e('عبارت جستجوی دیگری امتحان کنید.', 'tabesh'); ?></p>
        </div>
    </main>

    <!-- Order Details Modal (Full Screen) -->
    <div class="upload-modal" id="order-detail-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <!-- Modal Header with Back Button -->
            <div class="modal-header">
                <button class="modal-back-btn" id="modal-back-btn">
                    <span class="back-icon">→</span>
                    <?php esc_html_e('بازگشت', 'tabesh'); ?>
                </button>
                <div class="modal-breadcrumb" id="modal-breadcrumb">
                    <span class="breadcrumb-item"><?php esc_html_e('سفارشات', 'tabesh'); ?></span>
                    <span class="breadcrumb-separator">›</span>
                    <span class="breadcrumb-item active" id="breadcrumb-order-number"></span>
                </div>
                <button class="modal-close" id="modal-close-btn" aria-label="<?php esc_attr_e('بستن', 'tabesh'); ?>">✕</button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body" id="modal-body">
                <!-- Order Info Section -->
                <div class="order-info-section" id="order-info-section">
                    <!-- Filled by JavaScript -->
                </div>

                <!-- Upload Progress Stepper -->
                <div class="upload-stepper" id="upload-stepper">
                    <div class="stepper-step" data-type="text">
                        <div class="step-indicator">
                            <span class="step-icon">📄</span>
                        </div>
                        <span class="step-label"><?php esc_html_e('فایل متن', 'tabesh'); ?></span>
                    </div>
                    <div class="stepper-connector"></div>
                    <div class="stepper-step" data-type="cover">
                        <div class="step-indicator">
                            <span class="step-icon">🖼️</span>
                        </div>
                        <span class="step-label"><?php esc_html_e('فایل جلد', 'tabesh'); ?></span>
                    </div>
                    <div class="stepper-connector"></div>
                    <div class="stepper-step" data-type="documents">
                        <div class="step-indicator">
                            <span class="step-icon">📑</span>
                        </div>
                        <span class="step-label"><?php esc_html_e('مدارک', 'tabesh'); ?></span>
                    </div>
                </div>

                <!-- File Upload Sections -->
                <div class="file-sections">
                    <!-- Text File Section -->
                    <div class="file-section" data-type="text">
                        <div class="section-card">
                            <div class="card-header">
                                <div class="header-info">
                                    <h3 class="card-title">
                                        <span class="card-icon">📄</span>
                                        <?php esc_html_e('فایل متن کتاب', 'tabesh'); ?>
                                    </h3>
                                    <p class="card-description"><?php esc_html_e('فایل PDF محتوای کتاب را آپلود کنید.', 'tabesh'); ?></p>
                                </div>
                                <div class="file-requirements">
                                    <span class="requirement-badge"><?php esc_html_e('الزامی', 'tabesh'); ?></span>
                                </div>
                            </div>

                            <!-- File Info Display -->
                            <div class="order-file-info" id="text-file-info">
                                <div class="info-item">
                                    <span class="info-label"><?php esc_html_e('تعداد صفحات:', 'tabesh'); ?></span>
                                    <span class="info-value" id="text-page-count">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><?php esc_html_e('قطع کتاب:', 'tabesh'); ?></span>
                                    <span class="info-value" id="text-book-size">-</span>
                                </div>
                            </div>

                            <!-- Upload Area -->
                            <div class="upload-zone" id="text-upload-zone" data-type="text">
                                <input type="file" class="file-input" id="text-file-input" accept=".pdf" data-type="text">
                                <label for="text-file-input" class="upload-label">
                                    <div class="upload-icon">📤</div>
                                    <div class="upload-text">
                                        <span class="primary-text"><?php esc_html_e('فایل را اینجا رها کنید یا کلیک کنید', 'tabesh'); ?></span>
                                        <span class="secondary-text"><?php esc_html_e('فرمت مجاز: PDF - حداکثر 50 مگابایت', 'tabesh'); ?></span>
                                    </div>
                                </label>
                            </div>

                            <!-- Upload Progress -->
                            <div class="upload-progress" id="text-upload-progress" style="display: none;">
                                <div class="progress-header">
                                    <span class="progress-filename" id="text-progress-filename"></span>
                                    <span class="progress-percent" id="text-progress-percent">0%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="text-progress-fill"></div>
                                </div>
                                <div class="progress-eta" id="text-progress-eta"></div>
                            </div>

                            <!-- Uploaded Files List -->
                            <div class="uploaded-files" id="text-files-list">
                                <!-- Files will be populated by JavaScript -->
                            </div>

                            <!-- Add More Button -->
                            <button class="btn-add-more" id="text-add-more-btn" style="display: none;">
                                <span class="add-icon">+</span>
                                <?php esc_html_e('آپلود نسخه جدید', 'tabesh'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Cover File Section -->
                    <div class="file-section" data-type="cover">
                        <div class="section-card">
                            <div class="card-header">
                                <div class="header-info">
                                    <h3 class="card-title">
                                        <span class="card-icon">🖼️</span>
                                        <?php esc_html_e('فایل جلد کتاب', 'tabesh'); ?>
                                    </h3>
                                    <p class="card-description"><?php esc_html_e('فایل طرح جلد کتاب را آپلود کنید.', 'tabesh'); ?></p>
                                </div>
                                <div class="file-requirements">
                                    <span class="requirement-badge"><?php esc_html_e('الزامی', 'tabesh'); ?></span>
                                </div>
                            </div>

                            <!-- File Info Display -->
                            <div class="order-file-info" id="cover-file-info">
                                <div class="info-item">
                                    <span class="info-label"><?php esc_html_e('قطع جلد:', 'tabesh'); ?></span>
                                    <span class="info-value" id="cover-book-size">-</span>
                                </div>
                            </div>

                            <!-- Upload Area -->
                            <div class="upload-zone" id="cover-upload-zone" data-type="cover">
                                <input type="file" class="file-input" id="cover-file-input" accept=".pdf,.jpg,.jpeg,.png,.psd" data-type="cover">
                                <label for="cover-file-input" class="upload-label">
                                    <div class="upload-icon">📤</div>
                                    <div class="upload-text">
                                        <span class="primary-text"><?php esc_html_e('فایل را اینجا رها کنید یا کلیک کنید', 'tabesh'); ?></span>
                                        <span class="secondary-text"><?php esc_html_e('فرمت‌های مجاز: PDF, JPG, PNG, PSD - حداکثر 20 مگابایت', 'tabesh'); ?></span>
                                    </div>
                                </label>
                            </div>

                            <!-- Upload Progress -->
                            <div class="upload-progress" id="cover-upload-progress" style="display: none;">
                                <div class="progress-header">
                                    <span class="progress-filename" id="cover-progress-filename"></span>
                                    <span class="progress-percent" id="cover-progress-percent">0%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="cover-progress-fill"></div>
                                </div>
                                <div class="progress-eta" id="cover-progress-eta"></div>
                            </div>

                            <!-- Uploaded Files List -->
                            <div class="uploaded-files" id="cover-files-list">
                                <!-- Files will be populated by JavaScript -->
                            </div>

                            <!-- Add More Button -->
                            <button class="btn-add-more" id="cover-add-more-btn" style="display: none;">
                                <span class="add-icon">+</span>
                                <?php esc_html_e('آپلود نسخه جدید', 'tabesh'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Documents Section -->
                    <div class="file-section" data-type="documents">
                        <div class="section-card">
                            <div class="card-header">
                                <div class="header-info">
                                    <h3 class="card-title">
                                        <span class="card-icon">📑</span>
                                        <?php esc_html_e('مدارک', 'tabesh'); ?>
                                    </h3>
                                    <p class="card-description"><?php esc_html_e('مدارک مورد نیاز (شناسنامه، کارت ملی، مجوز و...) را آپلود کنید.', 'tabesh'); ?></p>
                                </div>
                                <div class="file-requirements">
                                    <span class="requirement-badge optional"><?php esc_html_e('اختیاری', 'tabesh'); ?></span>
                                </div>
                            </div>

                            <!-- Upload Area -->
                            <div class="upload-zone" id="documents-upload-zone" data-type="documents">
                                <input type="file" class="file-input" id="documents-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-type="documents" multiple>
                                <label for="documents-file-input" class="upload-label">
                                    <div class="upload-icon">📤</div>
                                    <div class="upload-text">
                                        <span class="primary-text"><?php esc_html_e('فایل‌ها را اینجا رها کنید یا کلیک کنید', 'tabesh'); ?></span>
                                        <span class="secondary-text"><?php esc_html_e('فرمت‌های مجاز: PDF, JPG, PNG, DOC - حداکثر 10 مگابایت', 'tabesh'); ?></span>
                                    </div>
                                </label>
                            </div>

                            <!-- Upload Progress -->
                            <div class="upload-progress" id="documents-upload-progress" style="display: none;">
                                <div class="progress-header">
                                    <span class="progress-filename" id="documents-progress-filename"></span>
                                    <span class="progress-percent" id="documents-progress-percent">0%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="documents-progress-fill"></div>
                                </div>
                                <div class="progress-eta" id="documents-progress-eta"></div>
                            </div>

                            <!-- Uploaded Files List -->
                            <div class="uploaded-files" id="documents-files-list">
                                <!-- Files will be populated by JavaScript -->
                            </div>

                            <!-- Add More Button -->
                            <button class="btn-add-more" id="documents-add-more-btn" style="display: none;">
                                <span class="add-icon">+</span>
                                <?php esc_html_e('آپلود فایل بیشتر', 'tabesh'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="loading-spinner">
                <div class="spinner"></div>
            </div>
            <p class="loading-text"><?php esc_html_e('لطفاً منتظر بمانید...', 'tabesh'); ?></p>
        </div>
    </div>
</div>
