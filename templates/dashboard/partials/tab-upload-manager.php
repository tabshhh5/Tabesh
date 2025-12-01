<?php
/**
 * Dashboard - Upload Manager Tab Partial
 *
 * Contains the file upload manager content for the user dashboard.
 * Provides interface for uploading and managing order files.
 *
 * @package Tabesh
 * @since 1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
?>

<div class="dashboard-tab-content" id="upload-manager-content">
    <div class="tab-header">
        <h2 class="tab-title">
            <span class="tab-title-icon">📁</span>
            <?php esc_html_e('مدیریت فایل سفارشات', 'tabesh'); ?>
        </h2>
        <p class="tab-description"><?php esc_html_e('فایل‌های متن و جلد کتاب را برای سفارشات خود آپلود کنید.', 'tabesh'); ?></p>
    </div>

    <!-- Search Bar -->
    <div class="search-container compact">
        <div class="search-input-wrapper">
            <span class="search-icon">🔍</span>
            <input 
                type="text" 
                id="upload-search-input" 
                class="search-input" 
                placeholder="<?php esc_attr_e('جستجو بر اساس عنوان کتاب، شماره سفارش...', 'tabesh'); ?>"
                autocomplete="off"
            >
            <button class="search-clear" id="upload-search-clear" style="display: none;" aria-label="<?php esc_attr_e('پاک کردن', 'tabesh'); ?>">✕</button>
        </div>
    </div>

    <!-- Orders List -->
    <div class="upload-orders-section">
        <div class="section-header">
            <h3 class="section-title">
                <span class="section-icon">📋</span>
                <?php esc_html_e('سفارشات شما', 'tabesh'); ?>
            </h3>
            <span class="order-count" id="upload-order-count"></span>
        </div>

        <!-- Orders Container -->
        <div class="orders-container" id="upload-orders-container">
            <div class="loading-state">
                <div class="loading-spinner"><div class="spinner"></div></div>
                <p><?php esc_html_e('در حال بارگذاری سفارشات...', 'tabesh'); ?></p>
            </div>
        </div>

        <!-- Load More -->
        <div class="load-more-container" id="upload-load-more" style="display: none;">
            <button class="btn btn-secondary btn-load-more" id="upload-load-more-btn">
                <span class="btn-icon">📄</span>
                <?php esc_html_e('مشاهده بیشتر', 'tabesh'); ?>
            </button>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="upload-empty-state" style="display: none;">
            <div class="empty-icon">📭</div>
            <h3><?php esc_html_e('سفارشی یافت نشد', 'tabesh'); ?></h3>
            <p><?php esc_html_e('ابتدا از تب "ثبت سفارش" یک سفارش جدید ثبت کنید.', 'tabesh'); ?></p>
            <button class="btn btn-primary go-to-order-form" data-target="order-form">
                <span class="btn-icon">📝</span>
                <?php esc_html_e('ثبت سفارش جدید', 'tabesh'); ?>
            </button>
        </div>
    </div>

    <!-- Order Detail Modal (Inline) -->
    <div class="upload-detail-panel" id="upload-detail-panel" style="display: none;">
        <div class="panel-header">
            <button class="panel-back-btn" id="upload-panel-back">
                <span class="back-icon">→</span>
                <?php esc_html_e('بازگشت', 'tabesh'); ?>
            </button>
            <div class="panel-title" id="upload-panel-title"></div>
        </div>

        <!-- Order Info -->
        <div class="order-info-card" id="upload-order-info"></div>

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
                            <h4 class="card-title">
                                <span class="card-icon">📄</span>
                                <?php esc_html_e('فایل متن کتاب', 'tabesh'); ?>
                            </h4>
                            <p class="card-description"><?php esc_html_e('فایل PDF محتوای کتاب', 'tabesh'); ?></p>
                        </div>
                        <span class="requirement-badge required"><?php esc_html_e('الزامی', 'tabesh'); ?></span>
                    </div>

                    <!-- Upload Zone -->
                    <div class="upload-zone" id="text-upload-zone" data-type="text">
                        <input type="file" class="file-input" id="text-file-input" accept=".pdf" data-type="text">
                        <label for="text-file-input" class="upload-label">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">
                                <span class="primary-text"><?php esc_html_e('فایل را اینجا رها کنید یا کلیک کنید', 'tabesh'); ?></span>
                                <span class="secondary-text"><?php esc_html_e('فرمت: PDF - حداکثر 50 مگابایت', 'tabesh'); ?></span>
                            </div>
                        </label>
                    </div>

                    <!-- Upload Progress -->
                    <div class="upload-progress" id="text-progress" style="display: none;">
                        <div class="progress-header">
                            <span class="progress-filename" id="text-filename"></span>
                            <span class="progress-percent" id="text-percent">0%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" id="text-fill"></div></div>
                    </div>

                    <!-- Uploaded Files -->
                    <div class="uploaded-files" id="text-files-list"></div>
                </div>
            </div>

            <!-- Cover File Section -->
            <div class="file-section" data-type="cover">
                <div class="section-card">
                    <div class="card-header">
                        <div class="header-info">
                            <h4 class="card-title">
                                <span class="card-icon">🖼️</span>
                                <?php esc_html_e('فایل جلد کتاب', 'tabesh'); ?>
                            </h4>
                            <p class="card-description"><?php esc_html_e('طرح جلد (PDF, JPG, PNG, PSD)', 'tabesh'); ?></p>
                        </div>
                        <span class="requirement-badge required"><?php esc_html_e('الزامی', 'tabesh'); ?></span>
                    </div>

                    <div class="upload-zone" id="cover-upload-zone" data-type="cover">
                        <input type="file" class="file-input" id="cover-file-input" accept=".pdf,.jpg,.jpeg,.png,.psd" data-type="cover">
                        <label for="cover-file-input" class="upload-label">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">
                                <span class="primary-text"><?php esc_html_e('فایل را اینجا رها کنید یا کلیک کنید', 'tabesh'); ?></span>
                                <span class="secondary-text"><?php esc_html_e('فرمت: PDF, JPG, PNG, PSD - حداکثر 20 مگابایت', 'tabesh'); ?></span>
                            </div>
                        </label>
                    </div>

                    <div class="upload-progress" id="cover-progress" style="display: none;">
                        <div class="progress-header">
                            <span class="progress-filename" id="cover-filename"></span>
                            <span class="progress-percent" id="cover-percent">0%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" id="cover-fill"></div></div>
                    </div>

                    <div class="uploaded-files" id="cover-files-list"></div>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="file-section" data-type="documents">
                <div class="section-card">
                    <div class="card-header">
                        <div class="header-info">
                            <h4 class="card-title">
                                <span class="card-icon">📑</span>
                                <?php esc_html_e('مدارک', 'tabesh'); ?>
                            </h4>
                            <p class="card-description"><?php esc_html_e('مدارک شناسایی، مجوز و...', 'tabesh'); ?></p>
                        </div>
                        <span class="requirement-badge optional"><?php esc_html_e('اختیاری', 'tabesh'); ?></span>
                    </div>

                    <div class="upload-zone" id="documents-upload-zone" data-type="documents">
                        <input type="file" class="file-input" id="documents-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-type="documents" multiple>
                        <label for="documents-file-input" class="upload-label">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">
                                <span class="primary-text"><?php esc_html_e('فایل‌ها را اینجا رها کنید یا کلیک کنید', 'tabesh'); ?></span>
                                <span class="secondary-text"><?php esc_html_e('فرمت: PDF, JPG, PNG, DOC - حداکثر 10 مگابایت', 'tabesh'); ?></span>
                            </div>
                        </label>
                    </div>

                    <div class="upload-progress" id="documents-progress" style="display: none;">
                        <div class="progress-header">
                            <span class="progress-filename" id="documents-filename"></span>
                            <span class="progress-percent" id="documents-percent">0%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" id="documents-fill"></div></div>
                    </div>

                    <div class="uploaded-files" id="documents-files-list"></div>
                </div>
            </div>
        </div>
    </div>
</div>
