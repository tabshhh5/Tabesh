/**
 * Modern User Orders Panel JavaScript
 * Complete redesign with interactive features
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Main class for User Orders Panel
    class TabeshUserOrdersModern {
        constructor() {
            this.init();
        }

        init() {
            this.cacheDom();
            this.bindEvents();
            this.loadSummary();
            this.initTheme();
        }

        cacheDom() {
            this.$container = $('.tabesh-user-orders-modern');
            this.$themeToggle = $('#theme-toggle');
            this.$searchInput = $('#order-search-input');
            this.$searchClear = $('#search-clear-btn');
            this.$searchResults = $('#search-results');
            this.$orderModal = $('#order-modal');
            this.$modalBody = $('#modal-body');
            this.$modalClose = $('#modal-close');
            this.$supportModal = $('#support-modal');
            this.$supportModalClose = $('#support-modal-close');
            this.$loadingOverlay = $('#loading-overlay');
        }

        bindEvents() {
            // Theme toggle
            this.$themeToggle.on('click', () => this.toggleTheme());

            // Search
            this.$searchInput.on('input', (e) => this.handleSearch(e));
            this.$searchClear.on('click', () => this.clearSearch());

            // Order details buttons
            $(document).on('click', '.btn-details', (e) => {
                const orderId = $(e.currentTarget).data('order-id');
                this.showOrderDetails(orderId);
            });

            // Support buttons
            $(document).on('click', '.btn-support', (e) => {
                const $btn = $(e.currentTarget);
                this.showSupport({
                    orderId: $btn.data('order-id'),
                    orderNumber: $btn.data('order-number'),
                    bookTitle: $btn.data('book-title')
                });
            });

            // Modal close
            this.$modalClose.on('click', () => this.closeModal());
            this.$supportModalClose.on('click', () => this.closeSupportModal());
            
            // Close on overlay click
            $('.modal-overlay').on('click', (e) => {
                if ($(e.target).hasClass('modal-overlay')) {
                    this.closeModal();
                    this.closeSupportModal();
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeModal();
                    this.closeSupportModal();
                }
            });
        }

        // Theme Management
        initTheme() {
            const savedTheme = localStorage.getItem('tabesh-theme') || 'light';
            this.$container.attr('data-theme', savedTheme);
        }

        toggleTheme() {
            const currentTheme = this.$container.attr('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            this.$container.attr('data-theme', newTheme);
            localStorage.setItem('tabesh-theme', newTheme);
            
            // Add animation class
            this.$container.addClass('theme-transitioning');
            setTimeout(() => {
                this.$container.removeClass('theme-transitioning');
            }, 300);
        }

        // Load summary data
        loadSummary() {
            $.ajax({
                url: tabeshData.restUrl + '/user-orders/summary',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': tabeshData.nonce
                },
                success: (response) => {
                    this.updateSummary(response);
                },
                error: (xhr) => {
                    console.error('Error loading summary:', xhr);
                }
            });
        }

        updateSummary(data) {
            $('#total-orders').text(data.total_orders || 0);
            $('#completed-orders').text(data.completed_orders || 0);
            $('#active-orders').text(data.active_orders || 0);
            $('#total-price').text(this.formatPrice(data.total_price || 0));
            
            // Animate numbers
            this.animateNumbers();
        }

        animateNumbers() {
            $('.summary-value').each(function() {
                const $this = $(this);
                $this.addClass('animate-pulse');
                setTimeout(() => {
                    $this.removeClass('animate-pulse');
                }, 600);
            });
        }

        // Search functionality
        handleSearch(e) {
            const query = $(e.target).val().trim();
            
            if (query.length === 0) {
                this.clearSearch();
                return;
            }

            this.$searchClear.show();

            // Debounce search
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 500);
        }

        performSearch(query) {
            this.showSearchLoading();

            $.ajax({
                url: tabeshData.restUrl + '/user-orders/search',
                method: 'GET',
                data: { q: query },
                headers: {
                    'X-WP-Nonce': tabeshData.nonce
                },
                success: (response) => {
                    this.displaySearchResults(response.orders);
                },
                error: (xhr) => {
                    console.error('Search error:', xhr);
                    this.showSearchError();
                }
            });
        }

        showSearchLoading() {
            this.$searchResults.show();
            this.$searchResults.find('.search-results-content').html(`
                <div class="search-loading">
                    <div class="spinner" style="width: 40px; height: 40px; margin: 20px auto;"></div>
                    <p style="text-align: center; color: var(--text-secondary);">در حال جستجو...</p>
                </div>
            `);
        }

        displaySearchResults(orders) {
            if (orders.length === 0) {
                this.$searchResults.find('.search-results-content').html(`
                    <div class="search-no-results">
                        <div class="search-no-results-icon">🔍</div>
                        <p>نتیجه‌ای یافت نشد</p>
                    </div>
                `);
                return;
            }

            // Show only first 3 results initially
            const displayOrders = orders.slice(0, 3);
            const hasMore = orders.length > 3;

            let html = '';
            displayOrders.forEach(order => {
                html += `
                    <div class="search-result-item" data-order-id="${order.id}">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="color: var(--text-primary); font-size: 16px;">📖 ${this.escapeHtml(order.book_title || 'بدون عنوان')}</strong>
                            <span class="order-status status-${order.status}" style="font-size: 12px; padding: 4px 12px;">${order.status_label}</span>
                        </div>
                        <div style="display: flex; gap: 16px; font-size: 13px; color: var(--text-secondary);">
                            <span>شماره: #${order.order_number}</span>
                            <span>📐 ${order.book_size}</span>
                            <span>💵 ${this.formatPrice(order.total_price)} تومان</span>
                        </div>
                    </div>
                `;
            });

            if (hasMore) {
                html += `
                    <div style="text-align: center; padding: 16px;">
                        <p style="color: var(--text-secondary); font-size: 14px;">
                            ${orders.length - 3} مورد دیگر یافت شد
                        </p>
                    </div>
                `;
            }

            this.$searchResults.find('.search-results-content').html(html);

            // Bind click events to search results
            $('.search-result-item').on('click', (e) => {
                const orderId = $(e.currentTarget).data('order-id');
                this.showOrderDetails(orderId);
                this.clearSearch();
            });
        }

        showSearchError() {
            this.$searchResults.find('.search-results-content').html(`
                <div class="search-no-results">
                    <div class="search-no-results-icon">⚠️</div>
                    <p>خطا در جستجو. لطفاً دوباره تلاش کنید.</p>
                </div>
            `);
        }

        clearSearch() {
            this.$searchInput.val('');
            this.$searchClear.hide();
            this.$searchResults.hide();
        }

        // Order details modal
        showOrderDetails(orderId) {
            this.$orderModal.fadeIn(300);
            this.$modalBody.html(`
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>در حال بارگذاری...</p>
                </div>
            `);

            $.ajax({
                url: tabeshData.restUrl + '/user-orders/' + orderId,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': tabeshData.nonce
                },
                success: (response) => {
                    this.renderOrderDetails(response.order);
                },
                error: (xhr) => {
                    console.error('Error loading order:', xhr);
                    this.$modalBody.html(`
                        <div class="search-no-results">
                            <div class="search-no-results-icon">⚠️</div>
                            <p>خطا در بارگذاری اطلاعات سفارش</p>
                        </div>
                    `);
                }
            });
        }

        renderOrderDetails(order) {
            const extrasHtml = Array.isArray(order.extras) && order.extras.length > 0
                ? order.extras.map(extra => `<span style="display: inline-block; padding: 4px 12px; background: var(--bg-primary); border-radius: 8px; margin: 4px; font-size: 13px;">✓ ${this.escapeHtml(extra)}</span>`).join('')
                : '<span style="color: var(--text-tertiary);">ندارد</span>';

            const html = `
                <div class="detail-section">
                    <h3 class="detail-section-title">
                        <span>📖</span>
                        اطلاعات اصلی
                    </h3>
                    <div class="order-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">عنوان کتاب</div>
                            <div class="detail-value">${this.escapeHtml(order.book_title || 'بدون عنوان')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">شماره سفارش</div>
                            <div class="detail-value">#${order.order_number}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">وضعیت</div>
                            <div class="detail-value">
                                <span class="order-status status-${order.status}">${order.status_label}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h3 class="detail-section-title">
                        <span>📐</span>
                        مشخصات فنی
                    </h3>
                    <div class="order-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">قطع کتاب</div>
                            <div class="detail-value">${this.escapeHtml(order.book_size)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">نوع کاغذ</div>
                            <div class="detail-value">${this.escapeHtml(order.paper_type)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">گرماژ کاغذ</div>
                            <div class="detail-value">${this.escapeHtml(order.paper_weight)}g</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">نوع چاپ</div>
                            <div class="detail-value">${this.escapeHtml(order.print_type)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">صفحات رنگی</div>
                            <div class="detail-value">${order.page_count_color} صفحه</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">صفحات سیاه و سفید</div>
                            <div class="detail-value">${order.page_count_bw} صفحه</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">مجموع صفحات</div>
                            <div class="detail-value">${order.page_count_total} صفحه</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">تیراژ</div>
                            <div class="detail-value">${order.quantity} نسخه</div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h3 class="detail-section-title">
                        <span>📚</span>
                        جلد و صحافی
                    </h3>
                    <div class="order-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">نوع صحافی</div>
                            <div class="detail-value">${this.escapeHtml(order.binding_type)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">نوع مجوز</div>
                            <div class="detail-value">${this.escapeHtml(order.license_type)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">گرماژ جلد</div>
                            <div class="detail-value">${this.escapeHtml(order.cover_paper_weight)}g</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">نوع سلفون</div>
                            <div class="detail-value">${this.escapeHtml(order.lamination_type)}</div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h3 class="detail-section-title">
                        <span>✨</span>
                        خدمات اضافی
                    </h3>
                    <div style="padding: 16px; background: var(--bg-primary); border-radius: 12px; border: 1px solid var(--border-color);">
                        ${extrasHtml}
                    </div>
                </div>

                <div class="detail-section">
                    <h3 class="detail-section-title">
                        <span>💰</span>
                        اطلاعات مالی
                    </h3>
                    <div style="padding: 24px; background: linear-gradient(135deg, var(--color-primary), var(--color-secondary)); border-radius: 16px; color: white; text-align: center;">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">مبلغ کل سفارش</div>
                        <div style="font-size: 32px; font-weight: 700;">${this.formatPrice(order.total_price)} تومان</div>
                    </div>
                </div>

                <div class="detail-section">
                    <h3 class="detail-section-title">
                        <span>🕐</span>
                        تاریخچه
                    </h3>
                    <div class="order-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">تاریخ ثبت</div>
                            <div class="detail-value">${this.formatDate(order.created_at)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">آخرین بروزرسانی</div>
                            <div class="detail-value">${this.formatDate(order.updated_at)}</div>
                        </div>
                    </div>
                </div>

                ${order.notes ? `
                    <div class="detail-section">
                        <h3 class="detail-section-title">
                            <span>📝</span>
                            یادداشت‌ها
                        </h3>
                        <div style="padding: 16px; background: var(--bg-primary); border-radius: 12px; border: 1px solid var(--border-color); color: var(--text-primary); line-height: 1.6;">
                            ${this.escapeHtml(order.notes)}
                        </div>
                    </div>
                ` : ''}

                <div class="detail-section">
                    <h3 class="detail-section-title">
                        <span>📊</span>
                        مسیر پیشرفت سفارش
                    </h3>
                    <div class="progress-stepper" style="background: transparent; padding: 32px 16px;">
                        ${this.renderStatusSteps(order.status_steps, order.status)}
                    </div>
                </div>
            `;

            this.$modalBody.html(html);
        }

        renderStatusSteps(steps, currentStatus) {
            if (!steps || typeof steps !== 'object') {
                return '<p style="color: var(--text-secondary); text-align: center;">اطلاعات وضعیت در دسترس نیست</p>';
            }

            let html = '';
            let stepIndex = 0;
            
            for (const [status, step] of Object.entries(steps)) {
                stepIndex++;
                const isCompleted = step.completed;
                const isCurrent = status === currentStatus;
                
                html += `
                    <div class="progress-step ${isCompleted ? 'completed' : ''} ${isCurrent ? 'current' : ''}">
                        <div class="step-connector"></div>
                        <div class="step-circle">
                            ${isCompleted ? '<span class="step-check">✓</span>' : `<span class="step-number">${stepIndex}</span>`}
                        </div>
                        <div class="step-label">${this.escapeHtml(step.label)}</div>
                    </div>
                `;
            }
            
            return html;
        }

        closeModal() {
            this.$orderModal.fadeOut(300);
        }

        // Support modal
        showSupport(orderInfo) {
            this.$supportModal.fadeIn(300);
            
            const html = `
                <div style="display: grid; gap: 12px; margin-bottom: 16px;">
                    <div style="display: flex; gap: 8px;">
                        <strong style="color: var(--text-primary); min-width: 100px;">شماره سفارش:</strong>
                        <span style="color: var(--text-secondary);">#${orderInfo.orderNumber}</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <strong style="color: var(--text-primary); min-width: 100px;">عنوان کتاب:</strong>
                        <span style="color: var(--text-secondary);">${this.escapeHtml(orderInfo.bookTitle || 'بدون عنوان')}</span>
                    </div>
                </div>
            `;
            
            $('#support-order-info').html(html);
        }

        closeSupportModal() {
            this.$supportModal.fadeOut(300);
        }

        // Utility functions
        formatPrice(price) {
            return new Intl.NumberFormat('fa-IR').format(price);
        }

        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return new Intl.DateTimeFormat('fa-IR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        showLoading() {
            this.$loadingOverlay.fadeIn(300);
        }

        hideLoading() {
            this.$loadingOverlay.fadeOut(300);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.tabesh-user-orders-modern').length) {
            new TabeshUserOrdersModern();
        }
    });

})(jQuery);
