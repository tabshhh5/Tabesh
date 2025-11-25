<?php
/**
 * Staff Panel Template - Complete Redesign
 * Modern, mobile-app-like interface with enhanced functionality
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$staff        = Tabesh()->staff;
$orders       = $staff->get_assigned_orders();
$current_user = wp_get_current_user();
$avatar_url   = get_avatar_url( $current_user->ID );
$is_admin     = current_user_can( 'manage_woocommerce' );

// Status labels
$status_labels = array(
	'pending'    => 'در انتظار بررسی',
	'confirmed'  => 'تایید شده',
	'processing' => 'در حال چاپ',
	'ready'      => 'آماده تحویل',
	'completed'  => 'تحویل داده شده',
	'cancelled'  => 'لغو شده',
);

// Status display order for stepper
$status_order = array( 'pending', 'confirmed', 'processing', 'ready', 'completed' );
?>

<div class="tabesh-staff-panel" dir="rtl" data-theme="light">
	<!-- Header Section -->
	<div class="staff-panel-header">
		<div class="staff-profile-section">
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $current_user->display_name ); ?>" class="staff-avatar">
			<div class="staff-info">
				<h2><?php echo esc_html( $current_user->display_name ); ?></h2>
				<p><?php _e( 'خوش آمدید به پنل مدیریت سفارشات', 'tabesh' ); ?></p>
			</div>
		</div>
		<div class="header-actions">
			<button class="theme-toggle-btn" aria-label="<?php esc_attr_e( 'تغییر تم', 'tabesh' ); ?>">
				<span class="theme-icon">🌙</span>
				<span class="theme-text"><?php _e( 'حالت تاریک', 'tabesh' ); ?></span>
			</button>
			<button class="notification-btn" aria-label="<?php esc_attr_e( 'اعلان‌ها', 'tabesh' ); ?>">
				<span class="notification-icon">🔔</span>
				<span class="notification-badge" style="display: none;">0</span>
			</button>
			<button class="logout-btn" onclick="window.location.href='<?php echo esc_url( wp_logout_url( home_url() ) ); ?>'" aria-label="<?php esc_attr_e( 'خروج', 'tabesh' ); ?>">
				<span class="logout-icon">🚪</span>
				<span class="logout-text"><?php _e( 'خروج', 'tabesh' ); ?></span>
			</button>
		</div>
	</div>

	<!-- Breadcrumb Navigation -->
	<div class="breadcrumb-nav" style="display: none;">
		<button class="back-button">
			<span class="back-icon">←</span>
			<span><?php _e( 'بازگشت', 'tabesh' ); ?></span>
		</button>
		<div class="breadcrumb-path">
			<span class="breadcrumb-item active"><?php _e( 'لیست سفارشات', 'tabesh' ); ?></span>
		</div>
	</div>

	<!-- Search Bar -->
	<div class="search-container">
		<div class="search-bar">
			<input type="text" 
					class="search-input" 
					placeholder="<?php esc_attr_e( 'جستجو در سفارشات (عنوان کتاب، شماره سفارش، قطع، مشخصات...)', 'tabesh' ); ?>"
					aria-label="<?php esc_attr_e( 'جستجوی سفارشات', 'tabesh' ); ?>">
			<span class="search-icon">🔍</span>
		</div>
		<div class="search-results-info" style="display: none;">
			<span class="results-count"></span>
		</div>
	</div>

	<!-- Orders Container -->
	<div class="tabesh-panel-container">
		<?php if ( empty( $orders ) ) : ?>
			<div class="no-orders">
				<div class="no-orders-icon">📦</div>
				<p><?php _e( 'هیچ سفارش فعالی برای پردازش وجود ندارد.', 'tabesh' ); ?></p>
			</div>
		<?php else : ?>
			<div class="tabesh-orders-grid">
				<?php
				foreach ( $orders as $order ) :
					$user          = get_userdata( $order->user_id );
					$customer_name = $user ? $user->display_name : 'نامشخص';
					$extras        = maybe_unserialize( $order->extras );
					if ( ! is_array( $extras ) ) {
						$extras = array();
					}
					?>
					<div class="tabesh-staff-order-card" 
						data-order-id="<?php echo esc_attr( $order->id ); ?>"
						data-order-number="<?php echo esc_attr( $order->order_number ); ?>"
						data-book-title="<?php echo esc_attr( $order->book_title ); ?>"
						data-book-size="<?php echo esc_attr( $order->book_size ); ?>"
						data-status="<?php echo esc_attr( $order->status ); ?>"
						data-customer-name="<?php echo esc_attr( $customer_name ); ?>">
						
						<!-- Card Header (Collapsed State) -->
						<div class="order-card-header">
							<div class="order-header-top">
								<div class="order-number-container">
									<span class="order-label"><?php _e( 'سفارش:', 'tabesh' ); ?></span>
									<h3 class="order-number"><?php echo esc_html( $order->order_number ); ?></h3>
								</div>
								<span class="expand-icon" aria-label="<?php esc_attr_e( 'نمایش جزئیات', 'tabesh' ); ?>">▼</span>
							</div>
							
							<?php if ( ! empty( $order->book_title ) ) : ?>
								<div class="book-title">
									<span class="book-icon">📖</span>
									<span><?php echo esc_html( $order->book_title ); ?></span>
								</div>
							<?php endif; ?>
							
							<div class="card-quick-info">
								<div class="quick-info-item">
									<span class="info-icon">📏</span>
									<span class="info-text"><?php echo esc_html( $order->book_size ); ?></span>
								</div>
								<div class="quick-info-item">
									<span class="info-icon">📊</span>
									<span class="info-text"><?php echo number_format( $order->quantity ); ?> <?php _e( 'عدد', 'tabesh' ); ?></span>
								</div>
								<div class="quick-info-item">
									<span class="status-badge status-<?php echo esc_attr( $order->status ); ?>">
										<?php echo esc_html( $status_labels[ $order->status ] ?? $order->status ); ?>
									</span>
								</div>
							</div>
						</div>

						<!-- Card Body (Expanded State) -->
						<div class="order-card-body">
							<!-- Customer Info -->
							<div class="customer-section">
								<div class="section-header">
									<span class="section-icon">👤</span>
									<h4 class="section-title"><?php _e( 'اطلاعات مشتری', 'tabesh' ); ?></h4>
								</div>
								<div class="customer-info">
									<span class="customer-name"><?php echo esc_html( $customer_name ); ?></span>
								</div>
							</div>

							<!-- Order Details Grid -->
							<div class="order-details-section">
								<div class="section-header">
									<span class="section-icon">📋</span>
									<h4 class="section-title"><?php _e( 'مشخصات سفارش', 'tabesh' ); ?></h4>
								</div>
								<div class="order-info-grid">
									<div class="info-item">
										<span class="label"><?php _e( 'تاریخ ثبت:', 'tabesh' ); ?></span>
										<span class="value"><?php echo date_i18n( 'Y/m/d - H:i', strtotime( $order->created_at ) ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'قطع کتاب:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->book_size ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'نوع کاغذ:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->paper_type ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'گرماژ کاغذ:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->paper_weight ); ?>g</span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'نوع چاپ:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->print_type ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'صفحات رنگی:', 'tabesh' ); ?></span>
										<span class="value"><?php echo number_format( $order->page_count_color ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'صفحات سیاه:', 'tabesh' ); ?></span>
										<span class="value"><?php echo number_format( $order->page_count_bw ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'کل صفحات:', 'tabesh' ); ?></span>
										<span class="value"><?php echo number_format( $order->page_count_total ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'تیراژ:', 'tabesh' ); ?></span>
										<span class="value"><?php echo number_format( $order->quantity ); ?> <?php _e( 'عدد', 'tabesh' ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'نوع صحافی:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->binding_type ); ?></span>
									</div>
									<div class="info-item">
										<span class="label"><?php _e( 'نوع سلفون:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->lamination_type ?: 'ندارد' ); ?></span>
									</div>
									<?php if ( ! empty( $order->cover_paper_type ) ) : ?>
									<div class="info-item">
										<span class="label"><?php _e( 'نوع کاغذ جلد:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->cover_paper_type ); ?></span>
									</div>
									<?php endif; ?>
									<?php if ( ! empty( $order->cover_paper_weight ) ) : ?>
									<div class="info-item">
										<span class="label"><?php _e( 'گرماژ جلد:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->cover_paper_weight ); ?>g</span>
									</div>
									<?php endif; ?>
									<div class="info-item">
										<span class="label"><?php _e( 'نوع مجوز:', 'tabesh' ); ?></span>
										<span class="value"><?php echo esc_html( $order->license_type ); ?></span>
									</div>
									<?php if ( $is_admin ) : ?>
									<div class="info-item price-item">
										<span class="label"><?php _e( 'مبلغ کل:', 'tabesh' ); ?></span>
										<span class="value price-value">
											<?php echo number_format( $order->total_price ); ?> <?php _e( 'تومان', 'tabesh' ); ?>
										</span>
									</div>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( ! empty( $extras ) ) : ?>
								<div class="extras-section">
									<div class="section-header">
										<span class="section-icon">✨</span>
										<h4 class="section-title"><?php _e( 'خدمات اضافی', 'tabesh' ); ?></h4>
									</div>
									<div class="extras-list">
										<?php foreach ( $extras as $extra ) : ?>
											<span class="extra-item"><?php echo esc_html( $extra ); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $order->notes ) ) : ?>
								<div class="notes-section">
									<div class="section-header">
										<span class="section-icon">📝</span>
										<h4 class="section-title"><?php _e( 'توضیحات سفارش', 'tabesh' ); ?></h4>
									</div>
									<div class="notes-content"><?php echo nl2br( esc_html( $order->notes ) ); ?></div>
								</div>
							<?php endif; ?>

							<!-- Status Stepper -->
							<div class="status-stepper">
								<div class="section-header">
									<span class="section-icon">🔄</span>
									<h4 class="section-title"><?php _e( 'مراحل انجام سفارش', 'tabesh' ); ?></h4>
								</div>
								<div class="stepper-container">
									<?php
									$statuses       = array(
										'pending'    => array(
											'label' => 'در انتظار',
											'icon'  => '⏳',
										),
										'confirmed'  => array(
											'label' => 'تایید شده',
											'icon'  => '✅',
										),
										'processing' => array(
											'label' => 'در حال چاپ',
											'icon'  => '🖨️',
										),
										'ready'      => array(
											'label' => 'آماده',
											'icon'  => '📦',
										),
										'completed'  => array(
											'label' => 'تحویل',
											'icon'  => '🎉',
										),
									);
									$current_status = $order->status;
									$status_keys    = array_keys( $statuses );
									$current_index  = array_search( $current_status, $status_keys );
									if ( $current_index === false ) {
										$current_index = 0;
									}

									foreach ( $statuses as $key => $status_data ) :
										$index = array_search( $key, $status_keys );
										$class = '';
										if ( $index < $current_index ) {
											$class = 'completed';
										} elseif ( $index === $current_index ) {
											$class = 'active';
										}
										?>
										<div class="stepper-step <?php echo $class; ?>" 
											data-status="<?php echo esc_attr( $key ); ?>"
											aria-label="<?php echo esc_attr( $status_data['label'] ); ?>">
											<div class="step-circle">
												<span class="step-icon"><?php echo $status_data['icon']; ?></span>
												<span class="step-number"><?php echo $index + 1; ?></span>
											</div>
											<div class="step-label"><?php echo esc_html( $status_data['label'] ); ?></div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>

							<!-- Print Subtasks Section - Only visible when status is 'processing' -->
							<?php if ( $order->status === 'processing' ) : ?>
								<div class="print-subtasks-section" data-order-id="<?php echo esc_attr( $order->id ); ?>">
									<div class="section-header">
										<span class="section-icon">📝</span>
										<h4 class="section-title"><?php _e( 'مراحل فرآیند چاپ', 'tabesh' ); ?></h4>
									</div>
									
									<div class="subtasks-progress">
										<div class="progress-bar">
											<div class="progress-fill" data-order-id="<?php echo esc_attr( $order->id ); ?>"></div>
										</div>
										<span class="progress-text">0%</span>
									</div>
									
									<div class="subtasks-list" data-order-id="<?php echo esc_attr( $order->id ); ?>">
										<div class="subtasks-loading">
											<div class="loading-spinner-small"></div>
											<span><?php _e( 'در حال بارگذاری...', 'tabesh' ); ?></span>
										</div>
									</div>
								</div>
							<?php endif; ?>

							<!-- Status Update Section -->
							<div class="status-update-section">
								<div class="section-header">
									<span class="section-icon">⚙️</span>
									<h4 class="section-title"><?php _e( 'به‌روزرسانی وضعیت', 'tabesh' ); ?></h4>
								</div>
								<div class="status-select-wrapper">
									<select class="status-update-select" aria-label="<?php esc_attr_e( 'انتخاب وضعیت جدید', 'tabesh' ); ?>">
										<option value=""><?php _e( 'انتخاب وضعیت جدید...', 'tabesh' ); ?></option>
										<option value="pending"><?php _e( 'در انتظار بررسی', 'tabesh' ); ?></option>
										<option value="confirmed"><?php _e( 'تایید شده', 'tabesh' ); ?></option>
										<option value="processing"><?php _e( 'در حال چاپ', 'tabesh' ); ?></option>
										<option value="ready"><?php _e( 'آماده تحویل', 'tabesh' ); ?></option>
										<option value="completed"><?php _e( 'تحویل داده شده', 'tabesh' ); ?></option>
										<option value="cancelled"><?php _e( 'لغو شده', 'tabesh' ); ?></option>
									</select>
									<button class="status-update-btn">
										<span class="btn-icon">💾</span>
										<span class="btn-text"><?php _e( 'ذخیره تغییرات', 'tabesh' ); ?></span>
									</button>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			
			<!-- Load More Button (for search results) -->
			<div class="load-more-container" style="display: none;">
				<button class="load-more-btn">
					<span class="btn-icon">⬇️</span>
					<span class="btn-text"><?php _e( 'نمایش بیشتر', 'tabesh' ); ?></span>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<!-- Full Screen Modal (for future use) -->
	<div class="fullscreen-modal" style="display: none;">
		<div class="modal-header">
			<button class="modal-close-btn" aria-label="<?php esc_attr_e( 'بستن', 'tabesh' ); ?>">✕</button>
			<h3 class="modal-title"></h3>
		</div>
		<div class="modal-body"></div>
	</div>
</div>
