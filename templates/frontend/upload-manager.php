<?php
/**
 * Upload Manager Template
 * Modern UI for file upload and management
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$upload_manager = new Tabesh_Upload();
$orders = $upload_manager->get_user_orders_with_files($user_id);
$required_file_categories = Tabesh_Upload::REQUIRED_FILE_CATEGORIES;
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
            <h1 class="header-title">
                <span class="title-icon">📁</span>
                <?php esc_html_e('مدیریت فایل‌های سفارش', 'tabesh'); ?>
            </h1>
            <p class="header-description">
                <?php esc_html_e('در این بخش می‌توانید فایل‌های سفارشات خود را آپلود و مدیریت کنید', 'tabesh'); ?>
            </p>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input 
                    type="text" 
                    id="upload-search-input" 
                    class="search-input" 
                    placeholder="<?php esc_attr_e('جستجو بر اساس عنوان کتاب، شماره سفارش، قطع، تیراژ...', 'tabesh'); ?>"
                    autocomplete="off"
                >
                <button class="search-clear" id="search-clear" style="display: none;">✕</button>
            </div>
            
            <!-- Live Search Results -->
            <div class="search-results" id="search-results" style="display: none;">
                <div class="search-results-header">
                    <span class="results-count"></span>
                    <span class="results-loading" style="display: none;">
                        <span class="spinner-small"></span>
                        <?php esc_html_e('در حال جستجو...', 'tabesh'); ?>
                    </span>
                </div>
                <div class="search-results-list"></div>
                <button class="search-load-more" id="search-load-more" style="display: none;">
                    <?php esc_html_e('مشاهده بیشتر', 'tabesh'); ?>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="upload-main">
        <!-- Orders List -->
        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="section-icon">📋</span>
                    <?php esc_html_e('سفارشات شما', 'tabesh'); ?>
                </h2>
                <span class="orders-count">
                    <?php 
                    // translators: %d is the number of orders
                    echo esc_html(sprintf(__('%d سفارش', 'tabesh'), count($orders))); 
                    ?>
                </span>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3><?php esc_html_e('هنوز سفارشی ثبت نشده است', 'tabesh'); ?></h3>
                    <p><?php esc_html_e('با ثبت سفارش جدید، می‌توانید فایل‌های خود را آپلود کنید', 'tabesh'); ?></p>
                    <a href="<?php echo esc_url(home_url('/order')); ?>" class="btn btn-primary">
                        <span class="btn-icon">➕</span>
                        <?php esc_html_e('ثبت سفارش جدید', 'tabesh'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-grid" id="orders-grid">
                    <?php foreach ($orders as $order): 
                        $upload_status = 'no_files';
                        $upload_progress = 0;
                        
                        if ($order->total_files > 0) {
                            if ($order->pending_files > 0) {
                                $upload_status = 'pending';
                            } elseif ($order->approved_files >= $required_file_categories) {
                                $upload_status = 'complete';
                            } else {
                                $upload_status = 'partial';
                            }
                            // Calculate progress based on required file types
                            $upload_progress = min(100, round(($order->approved_files / $required_file_categories) * 100));
                        }
                    ?>
                        <div class="order-card" data-order-id="<?php echo esc_attr($order->id); ?>">
                            <!-- Progress Bar -->
                            <div class="card-progress-bar">
                                <div class="progress-fill" style="width: <?php echo esc_attr($upload_progress); ?>%"></div>
                            </div>

                            <!-- Card Header -->
                            <div class="card-header">
                                <div class="card-title-group">
                                    <h3 class="card-title">
                                        📖 <?php echo esc_html($order->book_title ?: __('بدون عنوان', 'tabesh')); ?>
                                    </h3>
                                    <span class="order-number">#<?php echo esc_html($order->order_number); ?></span>
                                </div>
                                
                                <div class="card-badges">
                                    <span class="badge status-<?php echo esc_attr($order->status); ?>">
                                        <?php echo esc_html($upload_manager->get_order_status_label($order->status)); ?>
                                    </span>
                                    <span class="badge upload-status-<?php echo esc_attr($upload_status); ?>">
                                        <?php
                                        $upload_labels = array(
                                            'no_files' => __('در انتظار آپلود', 'tabesh'),
                                            'pending' => __('در حال بررسی', 'tabesh'),
                                            'partial' => __('ناقص', 'tabesh'),
                                            'complete' => __('کامل', 'tabesh')
                                        );
                                        echo esc_html($upload_labels[$upload_status]);
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="order-info-grid">
                                    <div class="info-item">
                                        <span class="info-icon">📖</span>
                                        <span class="info-label"><?php esc_html_e('قطع', 'tabesh'); ?></span>
                                        <span class="info-value"><?php echo esc_html($order->book_size); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">📄</span>
                                        <span class="info-label"><?php esc_html_e('صفحات', 'tabesh'); ?></span>
                                        <span class="info-value"><?php echo esc_html($order->page_count_total); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">📚</span>
                                        <span class="info-label"><?php esc_html_e('تیراژ', 'tabesh'); ?></span>
                                        <span class="info-value"><?php echo esc_html($order->quantity); ?></span>
                                    </div>
                                    <div class="info-item highlight">
                                        <span class="info-icon">📁</span>
                                        <span class="info-label"><?php esc_html_e('فایل‌ها', 'tabesh'); ?></span>
                                        <span class="info-value"><?php echo esc_html($order->approved_files); ?>/<?php echo esc_html($required_file_categories); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="card-footer">
                                <span class="order-date">
                                    📅 <?php echo esc_html(date_i18n('j F Y', strtotime($order->created_at))); ?>
                                </span>
                                <button class="btn btn-primary btn-view-order" data-order-id="<?php echo esc_attr($order->id); ?>">
                                    <span class="btn-icon">👁️</span>
                                    <?php esc_html_e('جزئیات و آپلود', 'tabesh'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Order Details Modal (Full Screen) -->
    <div class="order-modal" id="order-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-container">
            <!-- Modal Header -->
            <div class="modal-header">
                <button class="modal-back" id="modal-back">
                    <span class="back-icon">→</span>
                    <?php esc_html_e('بازگشت', 'tabesh'); ?>
                </button>
                <h2 class="modal-title" id="modal-title"><?php esc_html_e('جزئیات سفارش', 'tabesh'); ?></h2>
                <button class="modal-close" id="modal-close">✕</button>
            </div>

            <!-- Breadcrumb -->
            <nav class="modal-breadcrumb">
                <a href="#" class="breadcrumb-link" data-action="close"><?php esc_html_e('سفارشات', 'tabesh'); ?></a>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current" id="breadcrumb-order"></span>
            </nav>

            <!-- Modal Body -->
            <div class="modal-body" id="modal-body">
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p><?php esc_html_e('در حال بارگذاری...', 'tabesh'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="upload-modal" id="upload-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-container upload-modal-container">
            <div class="modal-header">
                <h2 class="modal-title" id="upload-modal-title"><?php esc_html_e('آپلود فایل', 'tabesh'); ?></h2>
                <button class="modal-close" id="upload-modal-close">✕</button>
            </div>
            <div class="modal-body upload-modal-body">
                <!-- Upload Drop Zone -->
                <div class="upload-dropzone" id="upload-dropzone">
                    <div class="dropzone-content">
                        <span class="dropzone-icon">📤</span>
                        <h3><?php esc_html_e('فایل را اینجا رها کنید', 'tabesh'); ?></h3>
                        <p><?php esc_html_e('یا کلیک کنید برای انتخاب فایل', 'tabesh'); ?></p>
                        <div class="dropzone-info" id="dropzone-info"></div>
                    </div>
                    <input type="file" id="upload-file-input" class="upload-file-input" />
                </div>

                <!-- Upload Progress -->
                <div class="upload-progress-container" id="upload-progress-container" style="display: none;">
                    <div class="upload-file-info">
                        <span class="file-name" id="upload-file-name"></span>
                        <span class="file-size" id="upload-file-size"></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="upload-progress-fill"></div>
                    </div>
                    <div class="progress-info">
                        <span class="progress-percent" id="upload-progress-percent">0%</span>
                        <span class="progress-status" id="upload-progress-status"><?php esc_html_e('در حال آپلود...', 'tabesh'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="spinner"></div>
            <p id="loading-message"><?php esc_html_e('لطفاً منتظر بمانید...', 'tabesh'); ?></p>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>
</div>

<!-- Order Details Template -->
<script type="text/template" id="order-details-template">
    <div class="order-details">
        <!-- Order Summary -->
        <div class="order-summary-card">
            <div class="summary-header">
                <h3>📋 {{book_title}}</h3>
                <span class="order-id">#{{order_number}}</span>
            </div>
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="item-label"><?php esc_html_e('قطع', 'tabesh'); ?></span>
                    <span class="item-value">{{book_size}}</span>
                </div>
                <div class="summary-item">
                    <span class="item-label"><?php esc_html_e('تعداد صفحات', 'tabesh'); ?></span>
                    <span class="item-value">{{page_count}}</span>
                </div>
                <div class="summary-item">
                    <span class="item-label"><?php esc_html_e('تیراژ', 'tabesh'); ?></span>
                    <span class="item-value">{{quantity}}</span>
                </div>
                <div class="summary-item">
                    <span class="item-label"><?php esc_html_e('وضعیت سفارش', 'tabesh'); ?></span>
                    <span class="item-value badge status-{{status}}">{{status_label}}</span>
                </div>
            </div>
        </div>

        <!-- Files Section -->
        <div class="files-section">
            <h3 class="files-section-title">
                <span class="section-icon">📁</span>
                <?php esc_html_e('فایل‌های سفارش', 'tabesh'); ?>
            </h3>

            <!-- Text Files -->
            <div class="file-category" data-category="text">
                <div class="category-header">
                    <div class="category-info">
                        <span class="category-icon">📝</span>
                        <h4><?php esc_html_e('فایل متن کتاب', 'tabesh'); ?></h4>
                        <span class="category-hint"><?php esc_html_e('(PDF)', 'tabesh'); ?></span>
                    </div>
                    <button class="btn btn-upload" data-type="text" data-order="{{order_id}}">
                        <span class="btn-icon">➕</span>
                        <?php esc_html_e('آپلود', 'tabesh'); ?>
                    </button>
                </div>
                <div class="category-requirements">
                    <p><?php esc_html_e('تعداد صفحات:', 'tabesh'); ?> <strong>{{page_count}}</strong> | <?php esc_html_e('قطع:', 'tabesh'); ?> <strong>{{book_size}}</strong></p>
                </div>
                <div class="files-list" data-type="text">
                    <!-- Files will be loaded here -->
                </div>
            </div>

            <!-- Cover Files -->
            <div class="file-category" data-category="cover">
                <div class="category-header">
                    <div class="category-info">
                        <span class="category-icon">🎨</span>
                        <h4><?php esc_html_e('فایل جلد کتاب', 'tabesh'); ?></h4>
                        <span class="category-hint"><?php esc_html_e('(PDF, JPG, PNG, PSD)', 'tabesh'); ?></span>
                    </div>
                    <button class="btn btn-upload" data-type="cover" data-order="{{order_id}}">
                        <span class="btn-icon">➕</span>
                        <?php esc_html_e('آپلود', 'tabesh'); ?>
                    </button>
                </div>
                <div class="category-requirements">
                    <p><?php esc_html_e('قطع جلد:', 'tabesh'); ?> <strong>{{book_size}}</strong></p>
                </div>
                <div class="files-list" data-type="cover">
                    <!-- Files will be loaded here -->
                </div>
            </div>

            <!-- Documents -->
            <div class="file-category" data-category="documents">
                <div class="category-header">
                    <div class="category-info">
                        <span class="category-icon">📄</span>
                        <h4><?php esc_html_e('مدارک', 'tabesh'); ?></h4>
                        <span class="category-hint"><?php esc_html_e('(اختیاری - PDF, JPG, PNG)', 'tabesh'); ?></span>
                    </div>
                    <button class="btn btn-upload" data-type="documents" data-order="{{order_id}}">
                        <span class="btn-icon">➕</span>
                        <?php esc_html_e('آپلود', 'tabesh'); ?>
                    </button>
                </div>
                <div class="files-list" data-type="documents">
                    <!-- Files will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Upload History / Progress Path -->
        <div class="progress-path-section">
            <h3 class="section-title">
                <span class="section-icon">📊</span>
                <?php esc_html_e('مسیر پیشرفت آپلود', 'tabesh'); ?>
            </h3>
            <div class="progress-stepper">
                <div class="step {{text_step_class}}" data-step="text">
                    <div class="step-indicator">
                        <span class="step-icon">📝</span>
                    </div>
                    <div class="step-content">
                        <h5><?php esc_html_e('متن کتاب', 'tabesh'); ?></h5>
                        <span class="step-status">{{text_step_status}}</span>
                    </div>
                </div>
                <div class="step-connector"></div>
                <div class="step {{cover_step_class}}" data-step="cover">
                    <div class="step-indicator">
                        <span class="step-icon">🎨</span>
                    </div>
                    <div class="step-content">
                        <h5><?php esc_html_e('جلد کتاب', 'tabesh'); ?></h5>
                        <span class="step-status">{{cover_step_status}}</span>
                    </div>
                </div>
                <div class="step-connector"></div>
                <div class="step {{documents_step_class}}" data-step="documents">
                    <div class="step-indicator">
                        <span class="step-icon">📄</span>
                    </div>
                    <div class="step-content">
                        <h5><?php esc_html_e('مدارک', 'tabesh'); ?></h5>
                        <span class="step-status">{{documents_step_status}}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</script>

<!-- File Item Template -->
<script type="text/template" id="file-item-template">
    <div class="file-item status-{{status}}" data-file-id="{{id}}">
        <div class="file-icon">
            <span class="icon">{{file_icon}}</span>
            <span class="version-badge" title="<?php esc_attr_e('نسخه', 'tabesh'); ?>">v{{version}}</span>
        </div>
        <div class="file-info">
            <h5 class="file-name">{{stored_filename}}</h5>
            <div class="file-meta">
                <span class="file-size">{{file_size_formatted}}</span>
                <span class="file-date">{{created_at_formatted}}</span>
            </div>
        </div>
        <div class="file-status">
            <span class="status-badge status-{{status}}">{{status_label}}</span>
            {{#rejection_reason}}
            <p class="rejection-reason">⚠️ {{rejection_reason}}</p>
            {{/rejection_reason}}
        </div>
        <div class="file-actions">
            <button class="btn btn-icon btn-download" data-file-id="{{id}}" title="<?php esc_attr_e('دانلود', 'tabesh'); ?>">
                <span class="icon">⬇️</span>
            </button>
        </div>
    </div>
</script>

<!-- Empty Files Template -->
<script type="text/template" id="empty-files-template">
    <div class="empty-files">
        <span class="empty-icon">📂</span>
        <p><?php esc_html_e('هنوز فایلی آپلود نشده است', 'tabesh'); ?></p>
    </div>
</script>
