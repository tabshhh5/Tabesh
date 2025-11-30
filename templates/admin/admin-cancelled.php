<?php
/**
 * Admin Cancelled Orders Template
 *
 * Displays cancelled orders with search, filter, and reorder functionality.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure plugin is properly initialized.
$tabesh = function_exists( 'Tabesh' ) ? Tabesh() : null;
if ( ! $tabesh || ! isset( $tabesh->admin ) || ! $tabesh->admin ) {
	wp_die( esc_html__( 'خطا: افزونه تابش به درستی راه‌اندازی نشده است. لطفاً از نصب صحیح WooCommerce اطمینان حاصل کنید.', 'tabesh' ) );
}

$archive_handler = $tabesh->archive;

// Handle restore action.
if ( isset( $_GET['action'] ) && $_GET['action'] === 'restore' && isset( $_GET['order_id'] ) ) {
	$order_id = intval( $_GET['order_id'] );
	if ( check_admin_referer( 'restore_order_' . $order_id ) ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_orders';
		// Set status to pending when restoring a cancelled order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update required.
		$wpdb->update(
			$table,
			array(
				'archived'    => 0,
				'archived_at' => null,
				'status'      => 'pending',
			),
			array( 'id' => $order_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		echo '<div class="notice notice-success"><p>' . esc_html__( 'سفارش با موفقیت بازگردانی شد و به وضعیت "در انتظار بررسی" تغییر یافت.', 'tabesh' ) . '</p></div>';
	}
}

// Get cancelled orders.
$result           = $archive_handler->get_cancelled_orders( 1, 100, '' );
$cancelled_orders = $result['orders'];
?>

<div class="wrap tabesh-admin-cancelled" dir="rtl">
	<h1><?php esc_html_e( 'سفارشات لغو شده', 'tabesh' ); ?></h1>
	
	<p class="description"><?php esc_html_e( 'لیست سفارشاتی که لغو شده‌اند. می‌توانید آنها را بازگردانی کرده یا سفارش مجدد ایجاد کنید.', 'tabesh' ); ?></p>

	<!-- Search Form -->
	<div class="tabindex-search-box" style="margin: 15px 0;">
		<form method="get" action="">
			<input type="hidden" name="page" value="tabesh-cancelled">
			<input type="text" name="search" placeholder="<?php esc_attr_e( 'جستجو بر اساس شماره سفارش یا عنوان کتاب...', 'tabesh' ); ?>" 
					value="<?php echo isset( $_GET['search'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['search'] ) ) ) : ''; ?>"
					style="width: 300px; padding: 8px;">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'جستجو', 'tabesh' ); ?></button>
		</form>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'شماره سفارش', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'عنوان کتاب', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'مشتری', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'قطع', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'تیراژ', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'مبلغ', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'تاریخ ثبت', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'تاریخ لغو', 'tabesh' ); ?></th>
				<th><?php esc_html_e( 'عملیات', 'tabesh' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $cancelled_orders ) ) : ?>
				<?php
				foreach ( $cancelled_orders as $tabesh_order ) :
					$user = get_userdata( $tabesh_order->user_id );
					?>
					<tr>
						<td><strong><?php echo esc_html( $tabesh_order->order_number ); ?></strong></td>
						<td><?php echo ! empty( $tabesh_order->book_title ) ? esc_html( $tabesh_order->book_title ) : '<span style="color: #999;">—</span>'; ?></td>
						<td>
							<?php if ( $user ) : ?>
								<?php echo esc_html( $user->display_name ); ?>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $tabesh_order->book_size ); ?></td>
						<td><?php echo number_format( $tabesh_order->quantity ); ?></td>
						<td><?php echo number_format( $tabesh_order->total_price ); ?> <?php esc_html_e( 'تومان', 'tabesh' ); ?></td>
						<td><?php echo esc_html( date_i18n( 'Y/m/d', strtotime( $tabesh_order->created_at ) ) ); ?></td>
						<td><?php echo $tabesh_order->archived_at ? esc_html( date_i18n( 'Y/m/d', strtotime( $tabesh_order->archived_at ) ) ) : '—'; ?></td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tabesh-cancelled&action=restore&order_id=' . $tabesh_order->id ), 'restore_order_' . $tabesh_order->id ) ); ?>" 
								class="button button-small button-primary"
								onclick="return confirm('<?php esc_attr_e( 'آیا از بازگردانی این سفارش اطمینان دارید؟ وضعیت سفارش به "در انتظار بررسی" تغییر خواهد کرد.', 'tabesh' ); ?>');">
								<?php esc_html_e( 'بازگردانی', 'tabesh' ); ?>
							</a>
							<button type="button" class="button button-small tabesh-reorder-btn" 
									data-order-id="<?php echo esc_attr( $tabesh_order->id ); ?>"
									data-order-number="<?php echo esc_attr( $tabesh_order->order_number ); ?>"
									data-book-title="<?php echo esc_attr( $tabesh_order->book_title ); ?>">
								<?php esc_html_e( 'سفارش مجدد', 'tabesh' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="9" style="text-align: center;"><?php esc_html_e( 'هیچ سفارش لغو شده‌ای یافت نشد', 'tabesh' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Reorder Modal -->
<div id="tabesh-reorder-modal" class="tabesh-modal" style="display: none;">
	<div class="tabesh-modal-overlay"></div>
	<div class="tabesh-modal-content">
		<div class="tabesh-modal-header">
			<h2><?php esc_html_e( 'سفارش مجدد', 'tabesh' ); ?></h2>
			<button type="button" class="tabesh-modal-close">&times;</button>
		</div>
		<div class="tabesh-modal-body">
			<p class="reorder-info"></p>
			<label for="reorder-user-select"><?php esc_html_e( 'برای کدام کاربر ایجاد شود؟', 'tabesh' ); ?></label>
			<select id="reorder-user-select" style="width: 100%; margin-top: 10px; padding: 8px;">
				<option value=""><?php esc_html_e( 'انتخاب کاربر...', 'tabesh' ); ?></option>
				<?php
				$users = get_users( array( 'role__in' => array( 'customer', 'subscriber', 'administrator', 'shop_manager' ) ) );
				foreach ( $users as $u ) {
					echo '<option value="' . esc_attr( $u->ID ) . '">' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_email ) . ')</option>';
				}
				?>
			</select>
			<input type="hidden" id="reorder-source-id" value="">
		</div>
		<div class="tabesh-modal-footer">
			<button type="button" class="button button-secondary tabesh-modal-cancel"><?php esc_html_e( 'انصراف', 'tabesh' ); ?></button>
			<button type="button" class="button button-primary tabesh-reorder-submit"><?php esc_html_e( 'ایجاد سفارش', 'tabesh' ); ?></button>
		</div>
	</div>
</div>

<style>
	.tabesh-modal {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		z-index: 100000;
	}
	.tabesh-modal-overlay {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0,0,0,0.5);
	}
	.tabesh-modal-content {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		background: #fff;
		border-radius: 8px;
		width: 400px;
		max-width: 90%;
		box-shadow: 0 4px 20px rgba(0,0,0,0.2);
	}
	.tabesh-modal-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 15px 20px;
		border-bottom: 1px solid #ddd;
	}
	.tabesh-modal-header h2 {
		margin: 0;
		font-size: 18px;
	}
	.tabesh-modal-close {
		background: none;
		border: none;
		font-size: 24px;
		cursor: pointer;
		color: #666;
	}
	.tabesh-modal-body {
		padding: 20px;
	}
	.tabesh-modal-body label {
		font-weight: 600;
	}
	.tabesh-modal-footer {
		padding: 15px 20px;
		border-top: 1px solid #ddd;
		text-align: left;
	}
	.reorder-info {
		margin-bottom: 15px;
		padding: 10px;
		background: #f0f0f1;
		border-radius: 4px;
	}
	.tabesh-status-badge {
		display: inline-block;
		padding: 4px 10px;
		border-radius: 4px;
		font-size: 12px;
		font-weight: 500;
	}
	.status-cancelled {
		background: #fee2e2;
		color: #dc2626;
	}
</style>

<script>
jQuery(document).ready(function($) {
	// Open reorder modal
	$('.tabesh-reorder-btn').on('click', function() {
		var orderId = $(this).data('order-id');
		var orderNumber = $(this).data('order-number');
		var bookTitle = $(this).data('book-title');
		
		$('#reorder-source-id').val(orderId);
		$('.reorder-info').html(
			'<?php esc_html_e( 'ایجاد سفارش جدید بر اساس:', 'tabesh' ); ?><br>' +
			'<strong>' + orderNumber + '</strong><br>' +
			'<?php esc_html_e( 'کتاب:', 'tabesh' ); ?> ' + (bookTitle || '-')
		);
		$('#tabesh-reorder-modal').show();
	});
	
	// Close modal
	$('.tabesh-modal-close, .tabesh-modal-cancel, .tabesh-modal-overlay').on('click', function() {
		$('#tabesh-reorder-modal').hide();
	});
	
	// Submit reorder
	$('.tabesh-reorder-submit').on('click', function() {
		var orderId = $('#reorder-source-id').val();
		var userId = $('#reorder-user-select').val();
		
		if (!userId) {
			alert('<?php esc_html_e( 'لطفاً یک کاربر انتخاب کنید', 'tabesh' ); ?>');
			return;
		}
		
		$(this).prop('disabled', true).text('<?php esc_html_e( 'در حال پردازش...', 'tabesh' ); ?>');
		
		$.ajax({
			url: '<?php echo esc_url( rest_url( TABESH_REST_NAMESPACE . '/archive/reorder' ) ); ?>',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
			},
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: parseInt(orderId),
				user_id: parseInt(userId)
			}),
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'سفارش جدید با موفقیت ایجاد شد', 'tabesh' ); ?>');
					location.reload();
				} else {
					alert(response.message || '<?php esc_html_e( 'خطا در ایجاد سفارش', 'tabesh' ); ?>');
				}
			},
			error: function(xhr) {
				var message = '<?php esc_html_e( 'خطا در ایجاد سفارش', 'tabesh' ); ?>';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					message = xhr.responseJSON.message;
				}
				alert(message);
			},
			complete: function() {
				$('.tabesh-reorder-submit').prop('disabled', false).text('<?php esc_html_e( 'ایجاد سفارش', 'tabesh' ); ?>');
				$('#tabesh-reorder-modal').hide();
			}
		});
	});
});
</script>
