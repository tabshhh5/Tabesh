<?php
/**
 * User Help Assistant
 *
 * AI assistant for general user support and help
 *
 * @package Tabesh
 * @subpackage AI
 * @since 1.1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Assistant_User_Help
 */
class Tabesh_AI_Assistant_User_Help extends Tabesh_AI_Assistant_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->assistant_id          = 'user_help';
		$this->assistant_name        = __( 'دستیار پشتیبانی / User Support', 'tabesh' );
		$this->assistant_description = __( 'راهنمایی و پشتیبانی عمومی کاربران', 'tabesh' );
		$this->allowed_roles         = array( 'administrator', 'shop_manager', 'customer', 'subscriber' );
		$this->capabilities          = array(
			'general_help',
			'faq',
			'troubleshooting',
			'account_help',
		);
		$this->system_prompt         = $this->build_system_prompt();
		$this->preferred_model       = 'gemini';
	}

	/**
	 * Build the system prompt for this assistant
	 *
	 * @return string
	 */
	private function build_system_prompt() {
		return __(
			'شما یک دستیار پشتیبانی برای سامانه چاپ کتاب تابش هستید. وظایف شما:

1. پاسخ به سوالات عمومی کاربران
2. راهنمایی در استفاده از سامانه
3. رفع مشکلات رایج
4. کمک در مدیریت حساب کاربری
5. ارائه اطلاعات کلی درباره خدمات

به صورت دوستانه، حرفه‌ای و راهنما پاسخ دهید. از زبان فارسی استفاده کنید.
اگر سوالی خارج از حوزه صلاحیت شماست، کاربر را به بخش مناسب راهنمایی کنید.',
			'tabesh'
		);
	}

	/**
	 * Prepare context for the AI request
	 *
	 * @param array $context Base context
	 * @return array Enhanced context
	 */
	protected function prepare_context( $context ) {
		// Add user information
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$context['user_role']         = implode( ', ', $user->roles );
				$context['user_display_name'] = $user->display_name;
			}
		}

		// Add system information
		if ( function_exists( 'Tabesh' ) ) {
			$context['plugin_version'] = defined( 'TABESH_VERSION' ) ? TABESH_VERSION : '1.0';
		}

		return $context;
	}
}
