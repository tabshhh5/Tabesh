<?php
/**
 * Order Form Slider Integration Handler
 *
 * Provides seamless, real-time integration between the cascading order form
 * and Revolution Slider. This shortcode extends the V2 form functionality
 * with enhanced JavaScript event dispatching for slider communication.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_Order_Form_Slider
 *
 * Handles the Revolution Slider integration shortcode for order form.
 * Maintains all business logic from V2 form while adding slider communication.
 */
class Tabesh_Order_Form_Slider {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialization placeholder.
	}

	/**
	 * Render order form with Revolution Slider integration
	 *
	 * This shortcode provides the same functionality as tabesh_order_form_v2
	 * but adds enhanced JavaScript event dispatching for Revolution Slider.
	 * Works standalone without requiring the slider.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output for the order form.
	 */
	public function render_order_form_slider( $atts ) {
		// Check if V2 pricing engine is enabled.
		$pricing_engine = new Tabesh_Pricing_Engine();
		if ( ! $pricing_engine->is_enabled() ) {
			return '<div class="tabesh-message error" dir="rtl"><p><strong>' .
				esc_html__( 'خطا:', 'tabesh' ) .
				'</strong> ' .
				esc_html__( 'موتور قیمت‌گذاری نسخه ۲ فعال نیست. لطفاً ابتدا از پنل تنظیمات، موتور قیمت‌گذاری جدید را فعال کنید.', 'tabesh' ) .
				'</p></div>';
		}

		// Parse shortcode attributes.
		$atts = shortcode_atts(
			array(
				'enable_slider_events' => 'true',  // Enable/disable slider event dispatching.
				'slider_id'            => '',       // Optional: Revolution Slider ID for targeting.
			),
			$atts,
			'tabesh_order_form_slider'
		);

		// Pass attributes to template.
		$enable_slider_events = filter_var( $atts['enable_slider_events'], FILTER_VALIDATE_BOOLEAN );
		$slider_id            = sanitize_text_field( $atts['slider_id'] );

		ob_start();
		include TABESH_PLUGIN_DIR . 'templates/frontend/order-form-slider.php';
		return ob_get_clean();
	}
}
