<?php
/**
 * AI Field Explainer
 *
 * Provides automatic explanations for form fields when users
 * focus or change values.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Field_Explainer
 *
 * Handles field explanations via AI
 */
class Tabesh_AI_Field_Explainer {

	/**
	 * Cached explanations to avoid redundant API calls
	 *
	 * @var array
	 */
	private $explanation_cache = array();

	/**
	 * Get field explanation from AI
	 *
	 * @param array $field_info Field information.
	 * @param array $user_profile User profile for context.
	 * @return string|WP_Error Explanation or error.
	 */
	public function get_field_explanation( $field_info, $user_profile = array() ) {
		// Validate field info.
		if ( empty( $field_info['fieldName'] ) && empty( $field_info['fieldLabel'] ) ) {
			return new WP_Error( 'invalid_field', __( 'اطلاعات فیلد نامعتبر است', 'tabesh' ) );
		}

		// Check cache first.
		$cache_key = $this->get_cache_key( $field_info );
		if ( isset( $this->explanation_cache[ $cache_key ] ) ) {
			return $this->explanation_cache[ $cache_key ];
		}

		// Check transient cache (5 minutes).
		$cached = get_transient( 'tabesh_field_explain_' . $cache_key );
		if ( false !== $cached ) {
			$this->explanation_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Get predefined explanations first.
		$predefined = $this->get_predefined_explanation( $field_info );
		if ( $predefined ) {
			$this->cache_explanation( $cache_key, $predefined );
			return $predefined;
		}

		// Get AI explanation.
		$explanation = $this->get_ai_explanation( $field_info, $user_profile );

		if ( ! is_wp_error( $explanation ) ) {
			$this->cache_explanation( $cache_key, $explanation );
		}

		return $explanation;
	}

	/**
	 * Get predefined explanation for common fields
	 *
	 * @param array $field_info Field information.
	 * @return string|false Explanation or false if not found.
	 */
	private function get_predefined_explanation( $field_info ) {
		$field_name  = isset( $field_info['fieldName'] ) ? $field_info['fieldName'] : '';
		$field_label = isset( $field_info['fieldLabel'] ) ? $field_info['fieldLabel'] : '';
		$field_value = isset( $field_info['selectedValue'] ) ? $field_info['selectedValue'] : '';

		// Paper type explanations.
		$paper_explanations = array(
			'گلاسه 80 گرم'  => 'کاغذ براق و نازک، مناسب برای جلد و کاتالوگ',
			'گلاسه 100 گرم' => 'کاغذ براق با کیفیت متوسط، مناسب برای جلد کتاب',
			'گلاسه 115 گرم' => 'کاغذ براق و مقاوم، مناسب برای جلد و صفحات رنگی',
			'گلاسه 135 گرم' => 'کاغذ براق با کیفیت بالا، بهترین گزینه برای کتاب‌های تصویری',
			'گلاسه 150 گرم' => 'کاغذ براق و ضخیم، مناسب برای کتاب‌های لوکس و آلبوم',
			'تحریر 60 گرم'  => 'کاغذ معمولی و نازک، مناسب برای کتاب‌های داستانی',
			'تحریر 70 گرم'  => 'کاغذ معمولی با کیفیت متوسط، محبوب‌ترین انتخاب',
			'تحریر 80 گرم'  => 'کاغذ معمولی و مقاوم، مناسب برای کتاب‌های درسی',
			'فانتزی کرافت'  => 'کاغذ قهوه‌ای طبیعی، مناسب برای جلد کتاب‌های هنری',
			'فانتزی سفید'   => 'کاغذ تزیینی سفید، برای جلد کتاب‌های خاص',
		);

		// Binding explanations.
		$binding_explanations = array(
			'گالینگور'   => 'صحافی با چسب گرم، مناسب برای کتاب‌های ضخیم و پرفروش',
			'سلفون براق' => 'روکش پلاستیکی براق، محافظت و زیبایی بیشتر',
			'سلفون مات'  => 'روکش پلاستیکی مات، ظاهر مدرن و شیک',
			'لمینت براق' => 'روکش پلاستیکی ضخیم براق، بسیار مقاوم',
			'لمینت مات'  => 'روکش پلاستیکی ضخیم مات، کیفیت بالا',
			'فنر'        => 'صحافی با فنر فلزی، مناسب برای دفترچه و نوت',
		);

		// Book size explanations.
		$size_explanations = array(
			'رقعی'    => 'سایز استاندارد کتاب (۲۱×۱۴.۵ سانتی‌متر)',
			'وزیری'   => 'سایز بزرگتر (۲۴×۱۷ سانتی‌متر)، مناسب برای کتاب‌های درسی',
			'رحلی'    => 'سایز بزرگ (۲۹×۲۲ سانتی‌متر)، برای کتاب‌های تصویری',
			'پالتویی' => 'سایز کوچک (۱۸×۱۱ سانتی‌متر)، مناسب برای رمان جیبی',
		);

		// Check paper type.
		if ( ( strpos( $field_name, 'paper' ) !== false || strpos( $field_label, 'کاغذ' ) !== false ) && ! empty( $field_value ) ) {
			foreach ( $paper_explanations as $key => $explanation ) {
				if ( strpos( $field_value, $key ) !== false ) {
					return $explanation;
				}
			}
		}

		// Check binding type.
		if ( ( strpos( $field_name, 'binding' ) !== false || strpos( $field_label, 'صحافی' ) !== false ) && ! empty( $field_value ) ) {
			foreach ( $binding_explanations as $key => $explanation ) {
				if ( strpos( $field_value, $key ) !== false ) {
					return $explanation;
				}
			}
		}

		// Check book size.
		if ( ( strpos( $field_name, 'size' ) !== false || strpos( $field_label, 'سایز' ) !== false ) && ! empty( $field_value ) ) {
			foreach ( $size_explanations as $key => $explanation ) {
				if ( strpos( $field_value, $key ) !== false ) {
					return $explanation;
				}
			}
		}

		return false;
	}

	/**
	 * Get AI-generated explanation
	 *
	 * @param array $field_info Field information.
	 * @param array $user_profile User profile.
	 * @return string|WP_Error Explanation or error.
	 */
	private function get_ai_explanation( $field_info, $user_profile ) {
		// Check if AI is available.
		$gemini = new Tabesh_AI_Gemini();

		// Build prompt.
		$prompt = $this->build_explanation_prompt( $field_info, $user_profile );

		// Get response from AI.
		$response = $gemini->chat( $prompt, array( 'field_explanation' => true ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Build prompt for field explanation
	 *
	 * @param array $field_info Field information.
	 * @param array $user_profile User profile.
	 * @return string Prompt text.
	 */
	private function build_explanation_prompt( $field_info, $user_profile ) {
		$field_name  = isset( $field_info['fieldName'] ) ? $field_info['fieldName'] : '';
		$field_label = isset( $field_info['fieldLabel'] ) ? $field_info['fieldLabel'] : '';
		$field_value = isset( $field_info['selectedValue'] ) ? $field_info['selectedValue'] : '';
		$field_type  = isset( $field_info['fieldType'] ) ? $field_info['fieldType'] : 'text';

		$prompt = "لطفاً این فیلد فرم را به زبان ساده و فارسی توضیح بده:\n\n";

		if ( $field_label ) {
			$prompt .= sprintf( "عنوان فیلد: %s\n", $field_label );
		}

		if ( $field_value ) {
			$prompt .= sprintf( "مقدار انتخاب شده: %s\n", $field_value );
		}

		$prompt .= sprintf( "نوع فیلد: %s\n", $field_type );

		// Add user context if available.
		if ( ! empty( $user_profile['profession'] ) ) {
			$prompt .= sprintf( "\nکاربر یک %s است.\n", $user_profile['profession'] );
		}

		$prompt .= "\nتوضیح را در یک جمله کوتاه و مفید بنویس (حداکثر ۵۰ کلمه). بدون مقدمه، فقط توضیح را بنویس.";

		return $prompt;
	}

	/**
	 * Get cache key for field
	 *
	 * @param array $field_info Field information.
	 * @return string Cache key.
	 */
	private function get_cache_key( $field_info ) {
		$key_parts = array(
			isset( $field_info['fieldName'] ) ? $field_info['fieldName'] : '',
			isset( $field_info['fieldLabel'] ) ? $field_info['fieldLabel'] : '',
			isset( $field_info['selectedValue'] ) ? $field_info['selectedValue'] : '',
		);

		return md5( implode( '_', $key_parts ) );
	}

	/**
	 * Cache explanation
	 *
	 * @param string $key Cache key.
	 * @param string $explanation Explanation text.
	 */
	private function cache_explanation( $key, $explanation ) {
		$this->explanation_cache[ $key ] = $explanation;
		set_transient( 'tabesh_field_explain_' . $key, $explanation, 5 * MINUTE_IN_SECONDS );
	}
}
