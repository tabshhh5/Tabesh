<?php
/**
 * AI Browser Sidebar Template - Modern Redesign
 *
 * Modern, minimal chatbot interface with:
 * - Vertical control bar for minimize/close
 * - No header (minimal design)
 * - Desktop: 30% sidebar with 70% usable website
 * - Mobile: 70% chatbot coverage
 * - Unread message notification badges
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<!-- AI Browser Floating Button -->
<button class="tabesh-ai-browser-toggle" id="tabesh-ai-browser-toggle" aria-label="<?php echo esc_attr__( 'باز کردن دستیار هوشمند', 'tabesh' ); ?>">
	<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
		<circle cx="9" cy="10" r="1" fill="currentColor"></circle>
		<circle cx="15" cy="10" r="1" fill="currentColor"></circle>
		<path d="M9 15c1 1 4 1 6 0"></path>
	</svg>
	<span class="tabesh-ai-browser-notification-badge" id="tabesh-ai-badge"></span>
</button>

<!-- Overlay for mobile -->
<div class="tabesh-ai-browser-overlay" id="tabesh-ai-browser-overlay"></div>

<!-- AI Browser Sidebar -->
<div class="tabesh-ai-browser-sidebar" id="tabesh-ai-browser-sidebar" dir="rtl">
	<!-- Vertical Control Bar (Left side in RTL) -->
	<div class="tabesh-ai-control-bar">
		<!-- Minimize button -->
		<button class="tabesh-ai-control-btn tabesh-ai-btn-minimize" id="tabesh-ai-minimize" aria-label="<?php echo esc_attr__( 'کوچک کردن', 'tabesh' ); ?>" title="<?php echo esc_attr__( 'کوچک کردن', 'tabesh' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="5" y1="12" x2="19" y2="12"></line>
			</svg>
		</button>
		
		<!-- Close button -->
		<button class="tabesh-ai-control-btn tabesh-ai-btn-close" id="tabesh-ai-close" aria-label="<?php echo esc_attr__( 'بستن', 'tabesh' ); ?>" title="<?php echo esc_attr__( 'بستن کامل', 'tabesh' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="18" y1="6" x2="6" y2="18"></line>
				<line x1="6" y1="6" x2="18" y2="18"></line>
			</svg>
		</button>
		
		<!-- AI Icon for minimized state -->
		<div class="tabesh-ai-minimized-icon" id="tabesh-ai-expand" title="<?php echo esc_attr__( 'باز کردن مجدد', 'tabesh' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
			</svg>
		</div>
		
		<!-- Badge for minimized state -->
		<span class="tabesh-ai-minimized-badge" id="tabesh-ai-minimized-badge"></span>
	</div>

	<!-- Chat Container -->
	<div class="tabesh-ai-chat-container">
		<!-- Messages Container -->
		<div class="tabesh-ai-browser-messages" id="tabesh-ai-browser-messages">
			<!-- Messages will be added by JavaScript -->
		</div>

		<!-- Typing Indicator -->
		<div class="tabesh-ai-browser-typing" id="tabesh-ai-typing">
			<div class="typing-dots">
				<span></span>
				<span></span>
				<span></span>
			</div>
			<span class="typing-text"><?php echo esc_html__( 'در حال نوشتن...', 'tabesh' ); ?></span>
		</div>

		<!-- Input Area -->
		<div class="tabesh-ai-browser-input-wrapper">
			<!-- Quick Actions -->
			<div class="tabesh-ai-browser-quick-actions" id="tabesh-ai-quick-actions">
				<button class="quick-action-btn" data-action="help"><?php echo esc_html__( 'راهنما', 'tabesh' ); ?></button>
				<button class="quick-action-btn" data-action="order"><?php echo esc_html__( 'ثبت سفارش', 'tabesh' ); ?></button>
				<button class="quick-action-btn" data-action="price"><?php echo esc_html__( 'استعلام قیمت', 'tabesh' ); ?></button>
			</div>

			<!-- Input Form -->
			<form class="tabesh-ai-browser-input-form" id="tabesh-ai-browser-form">
				<textarea 
					id="tabesh-ai-browser-input" 
					class="tabesh-ai-browser-input"
					placeholder="<?php echo esc_attr__( 'پیام خود را بنویسید...', 'tabesh' ); ?>"
					rows="1"
				></textarea>
				<button type="submit" class="tabesh-ai-browser-send" aria-label="<?php echo esc_attr__( 'ارسال', 'tabesh' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="22" y1="2" x2="11" y2="13"></line>
						<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
					</svg>
				</button>
			</form>
		</div>
	</div>
</div>
