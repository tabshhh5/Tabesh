<?php
/**
 * Gemini API Driver Class
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Gemini
 *
 * Handles communication with Google Gemini AI API
 */
class Tabesh_AI_Gemini {

	/**
	 * Gemini API endpoint
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model name
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_key = Tabesh_AI_Config::get_gemini_api_key();
		$this->model   = Tabesh_AI_Config::get( 'gemini_model', 'gemini-2.0-flash-exp' );
	}

	/**
	 * Send chat message to Gemini
	 *
	 * @param string $message User message.
	 * @param array  $context Additional context data.
	 * @return array|WP_Error Response or error.
	 */
	public function chat( $message, $context = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'کلید API تنظیم نشده است', 'tabesh' ) );
		}

		// Build system prompt with enriched context.
		$system_prompt = $this->build_system_prompt( $context );

		// Get user persona if available.
		$persona_context = '';
		if ( ! empty( $context['user_id'] ) || ! empty( $context['guest_uuid'] ) ) {
			$persona_builder = new Tabesh_AI_Persona_Builder();
			$persona = $persona_builder->build_persona(
				! empty( $context['user_id'] ) ? $context['user_id'] : 0,
				! empty( $context['guest_uuid'] ) ? $context['guest_uuid'] : ''
			);
			$persona_context = "\n\n" . $persona_builder->get_persona_summary( $persona );
		}

		// Get page context if available.
		$page_context = '';
		if ( ! empty( $context['page_context'] ) ) {
			$analyzer = new Tabesh_AI_Page_Analyzer();
			$page_context = "\n\n" . $analyzer->build_gemini_context(
				$context['page_context'],
				! empty( $context['user_profile'] ) ? $context['user_profile'] : array()
			);
		}

		// Build request body.
		$body = array(
			'contents'         => array(
				array(
					'parts' => array(
						array(
							'text' => $system_prompt . $persona_context . $page_context . "\n\n" . $message,
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature'     => (float) Tabesh_AI_Config::get( 'temperature', 0.7 ),
				'maxOutputTokens' => (int) Tabesh_AI_Config::get( 'max_tokens', 2048 ),
			),
		);

		// Make API request.
		$url = $this->api_endpoint . $this->model . ':generateContent?key=' . $this->api_key;

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			return new WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP response code */
					__( 'خطای API: کد %d', 'tabesh' ),
					$response_code
				),
				array( 'response' => $response_body )
			);
		}

		$data = json_decode( $response_body, true );

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new WP_Error( 'invalid_response', __( 'پاسخ نامعتبر از API', 'tabesh' ) );
		}

		return array(
			'success' => true,
			'message' => $data['candidates'][0]['content']['parts'][0]['text'],
			'usage'   => isset( $data['usageMetadata'] ) ? $data['usageMetadata'] : array(),
		);
	}

	/**
	 * Build system prompt based on context
	 *
	 * @param array $context Context data.
	 * @return string System prompt.
	 */
	private function build_system_prompt( $context ) {
		$prompt = "شما دستیار هوشمند سامانه تابش هستید که برای کمک به مشتریان در ثبت سفارش چاپ کتاب طراحی شده‌اید.\n\n";

		$prompt .= "وظایف شما:\n";
		$prompt .= "- کمک به مشتریان در انتخاب مشخصات کتاب (قطع، نوع کاغذ، صحافی و ...)\n";
		$prompt .= "- ارائه راهنمایی درباره قیمت‌گذاری\n";
		$prompt .= "- پاسخ به سوالات درباره فرآیند چاپ و تحویل\n";
		$prompt .= "- کمک در تکمیل فرم سفارش\n\n";

		$prompt .= "نکات مهم:\n";
		$prompt .= "- همیشه با احترام و صبوری پاسخ دهید\n";
		$prompt .= "- پاسخ‌ها را کوتاه و مفید ارائه دهید\n";
		$prompt .= "- از زبان فارسی روان و ساده استفاده کنید\n";
		$prompt .= "- در صورت نیاز به اطلاعات بیشتر، از کاربر سوال کنید\n\n";

		// Add user context.
		if ( ! empty( $context['user_name'] ) ) {
			$prompt .= sprintf( "نام کاربر: %s\n", $context['user_name'] );
		}

		// Add form data context.
		if ( ! empty( $context['form_data'] ) ) {
			$prompt .= "\nاطلاعات فعلی فرم سفارش:\n";
			foreach ( $context['form_data'] as $key => $value ) {
				if ( ! empty( $value ) ) {
					$prompt .= sprintf( "- %s: %s\n", $this->translate_field_name( $key ), $value );
				}
			}
		}

		// Add pricing data if available.
		if ( ! empty( $context['pricing_data'] ) && Tabesh_AI_Permissions::can_access_pricing() ) {
			$prompt .= "\nاطلاعات قیمت‌گذاری در دسترس است.\n";
		}

		return $prompt;
	}

	/**
	 * Translate field names to Persian
	 *
	 * @param string $field_name Field name in English.
	 * @return string Field name in Persian.
	 */
	private function translate_field_name( $field_name ) {
		$translations = array(
			'book_title'         => 'عنوان کتاب',
			'book_size'          => 'قطع کتاب',
			'paper_type'         => 'نوع کاغذ',
			'paper_weight'       => 'گرماژ کاغذ',
			'print_type'         => 'نوع چاپ',
			'page_count'         => 'تعداد صفحات',
			'quantity'           => 'تیراژ',
			'binding_type'       => 'نوع صحافی',
			'license_type'       => 'نوع مجوز',
			'cover_paper_type'   => 'نوع کاغذ جلد',
			'cover_paper_weight' => 'گرماژ کاغذ جلد',
			'lamination_type'    => 'نوع سلفون',
		);

		return isset( $translations[ $field_name ] ) ? $translations[ $field_name ] : $field_name;
	}

	/**
	 * Test API connection
	 *
	 * @return array|WP_Error Test result or error.
	 */
	public function test_connection() {
		$test_message = 'سلام، این یک تست اتصال است.';
		$response     = $this->chat( $test_message );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success' => true,
			'message' => __( 'اتصال به API موفقیت‌آمیز بود', 'tabesh' ),
		);
	}

	/**
	 * Get cache key for a message
	 *
	 * @param string $message Message text.
	 * @param array  $context Context data.
	 * @return string Cache key.
	 */
	private function get_cache_key( $message, $context ) {
		return 'tabesh_ai_' . md5( $message . wp_json_encode( $context ) );
	}

	/**
	 * Get cached response
	 *
	 * @param string $message Message text.
	 * @param array  $context Context data.
	 * @return mixed|false Cached response or false if not found.
	 */
	private function get_cached_response( $message, $context ) {
		if ( ! Tabesh_AI_Config::get( 'cache_enabled', true ) ) {
			return false;
		}

		$cache_key = $this->get_cache_key( $message, $context );
		return get_transient( $cache_key );
	}

	/**
	 * Cache response
	 *
	 * @param string $message  Message text.
	 * @param array  $context  Context data.
	 * @param mixed  $response Response to cache.
	 * @return bool True on success, false on failure.
	 */
	private function cache_response( $message, $context, $response ) {
		if ( ! Tabesh_AI_Config::get( 'cache_enabled', true ) ) {
			return false;
		}

		$cache_key = $this->get_cache_key( $message, $context );
		$cache_ttl = (int) Tabesh_AI_Config::get( 'cache_ttl', 3600 );

		return set_transient( $cache_key, $response, $cache_ttl );
	}
}
