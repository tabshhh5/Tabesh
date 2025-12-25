<?php
/**
 * AI Chat Interface Template
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="tabesh-ai-chat-container" dir="rtl">
	<div class="tabesh-ai-chat-header">
		<div class="tabesh-ai-chat-avatar">
			<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<rect x="4" y="4" width="16" height="16" rx="2"></rect>
				<circle cx="9" cy="10" r="1"></circle>
				<circle cx="15" cy="10" r="1"></circle>
				<path d="M9 15h6"></path>
			</svg>
		</div>
		<div class="tabesh-ai-chat-info">
			<h3><?php echo esc_html__( 'دستیار هوشمند تابش', 'tabesh' ); ?></h3>
			<p class="tabesh-ai-status"><?php echo esc_html__( 'آنلاین', 'tabesh' ); ?></p>
		</div>
		<button class="tabesh-ai-minimize" aria-label="<?php echo esc_attr__( 'کوچک کردن', 'tabesh' ); ?>" title="<?php echo esc_attr__( 'کوچک کردن', 'tabesh' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="5" y1="12" x2="19" y2="12"></line>
			</svg>
		</button>
		<button class="tabesh-ai-close" aria-label="<?php echo esc_attr__( 'بستن', 'tabesh' ); ?>" title="<?php echo esc_attr__( 'بستن', 'tabesh' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="18" y1="6" x2="6" y2="18"></line>
				<line x1="6" y1="6" x2="18" y2="18"></line>
			</svg>
		</button>
	</div>

	<div class="tabesh-ai-chat-messages" id="tabesh-ai-messages">
		<div class="tabesh-ai-message tabesh-ai-message-bot">
			<div class="tabesh-ai-message-content">
				<p><?php echo esc_html__( 'سلام! من دستیار هوشمند تابش هستم. چطور می‌تونم کمکتون کنم؟', 'tabesh' ); ?></p>
			</div>
			<div class="tabesh-ai-message-time">
				<?php echo esc_html( current_time( 'H:i' ) ); ?>
			</div>
		</div>
	</div>

	<div class="tabesh-ai-chat-input-wrapper">
		<div class="tabesh-ai-typing-indicator" style="display: none;">
			<span></span>
			<span></span>
			<span></span>
		</div>
		<form class="tabesh-ai-chat-input" id="tabesh-ai-chat-form">
			<textarea 
				id="tabesh-ai-input" 
				placeholder="<?php echo esc_attr__( 'پیام خود را بنویسید...', 'tabesh' ); ?>"
				rows="1"
			></textarea>
			<button type="submit" class="tabesh-ai-send-button" aria-label="<?php echo esc_attr__( 'ارسال', 'tabesh' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="22" y1="2" x2="11" y2="13"></line>
					<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
				</svg>
			</button>
		</form>
	</div>

	<div class="tabesh-ai-suggestions">
		<button class="tabesh-ai-suggestion" data-message="<?php echo esc_attr__( 'قیمت چاپ یک کتاب چقدر است؟', 'tabesh' ); ?>">
			<?php echo esc_html__( 'قیمت چاپ', 'tabesh' ); ?>
		</button>
		<button class="tabesh-ai-suggestion" data-message="<?php echo esc_attr__( 'انواع صحافی چه تفاوتی با هم دارند؟', 'tabesh' ); ?>">
			<?php echo esc_html__( 'انواع صحافی', 'tabesh' ); ?>
		</button>
		<button class="tabesh-ai-suggestion" data-message="<?php echo esc_attr__( 'برای ثبت سفارش چه اطلاعاتی نیاز است؟', 'tabesh' ); ?>">
			<?php echo esc_html__( 'ثبت سفارش', 'tabesh' ); ?>
		</button>
	</div>
</div>

<button class="tabesh-ai-chat-toggle" id="tabesh-ai-toggle" aria-label="<?php echo esc_attr__( 'باز کردن گفتگو', 'tabesh' ); ?>">
	<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
		<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
	</svg>
	<span class="tabesh-ai-toggle-text"><?php echo esc_html__( 'گفتگو', 'tabesh' ); ?></span>
	<span class="tabesh-ai-notification-badge" style="display: none;"></span>
</button>
