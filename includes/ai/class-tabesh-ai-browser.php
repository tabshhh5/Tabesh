<?php
/**
 * AI Browser Controller
 *
 * Main controller for the AI Browser sidebar feature that provides
 * intelligent navigation assistance throughout the website.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Browser
 *
 * Manages the AI Browser sidebar functionality
 */
class Tabesh_AI_Browser {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Enqueue scripts and styles for the browser.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add browser sidebar to footer.
		add_action( 'wp_footer', array( $this, 'render_browser_sidebar' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Track user behavior.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/browser/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_track_behavior' ),
				'permission_callback' => '__return_true', // Allow all users including guests.
				'args'                => array(
					'event_type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'event_data' => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
					'guest_uuid' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Analyze page context.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/page/analyze',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_analyze_page' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'client_data' => array(
						'required' => true,
						'type'     => 'object',
					),
					'guest_uuid'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get field explanation.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/field/explain',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_explain_field' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'field_info' => array(
						'required' => true,
						'type'     => 'object',
					),
					'guest_uuid' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get user persona.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/persona/build',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_build_persona' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'guest_uuid' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get user profile.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/browser/profile',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_profile' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'guest_uuid' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Navigate to target page.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/browser/navigate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_navigate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'profession' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'context'    => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
					'guest_uuid' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Start tour guide.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/browser/tour',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_start_tour' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'target' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get AI suggestions.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/browser/suggest',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_get_suggestions' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'context'    => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
					'guest_uuid' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Save chat history.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/browser/save-history',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save_chat_history' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'guest_uuid'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'chat_history' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
	}

	/**
	 * REST API: Track user behavior
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function rest_track_behavior( $request ) {
		$event_type = $request->get_param( 'event_type' );
		$event_data = $request->get_param( 'event_data' );
		$guest_uuid = $request->get_param( 'guest_uuid' );

		// Get user ID if logged in.
		$user_id = get_current_user_id();

		// Track behavior.
		$tracker = new Tabesh_AI_Tracker();
		$result  = $tracker->log_behavior( $user_id, $guest_uuid, $event_type, $event_data );

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'رفتار ثبت شد', 'tabesh' ),
				)
			);
		}

		return new WP_Error(
			'tracking_failed',
			__( 'خطا در ثبت رفتار', 'tabesh' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * REST API: Analyze page context
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_analyze_page( $request ) {
		$client_data = $request->get_param( 'client_data' );
		$guest_uuid  = $request->get_param( 'guest_uuid' );

		$analyzer = new Tabesh_AI_Page_Analyzer();
		$context  = $analyzer->extract_page_context( $client_data );

		// Get user profile for enriched context.
		$user_id = get_current_user_id();
		$profile_manager = new Tabesh_AI_User_Profile();

		if ( $user_id ) {
			$profile = $profile_manager->get_user_profile( $user_id );
		} elseif ( $guest_uuid ) {
			$profile = $profile_manager->get_guest_profile( $guest_uuid );
		} else {
			$profile = array();
		}

		// Build Gemini context.
		$gemini_context = $analyzer->build_gemini_context( $context, $profile );

		return rest_ensure_response(
			array(
				'success'        => true,
				'context'        => $context,
				'gemini_context' => $gemini_context,
			)
		);
	}

	/**
	 * REST API: Explain field
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_explain_field( $request ) {
		$field_info = $request->get_param( 'field_info' );
		$guest_uuid = $request->get_param( 'guest_uuid' );

		// Get user profile.
		$user_id = get_current_user_id();
		$profile_manager = new Tabesh_AI_User_Profile();

		if ( $user_id ) {
			$profile = $profile_manager->get_user_profile( $user_id );
		} elseif ( $guest_uuid ) {
			$profile = $profile_manager->get_guest_profile( $guest_uuid );
		} else {
			$profile = array();
		}

		// Get explanation.
		$explainer = new Tabesh_AI_Field_Explainer();
		$explanation = $explainer->get_field_explanation( $field_info, $profile );

		if ( is_wp_error( $explanation ) ) {
			return $explanation;
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'explanation' => $explanation,
			)
		);
	}

	/**
	 * REST API: Build user persona
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_build_persona( $request ) {
		$guest_uuid = $request->get_param( 'guest_uuid' );
		$user_id    = get_current_user_id();

		$persona_builder = new Tabesh_AI_Persona_Builder();
		$persona = $persona_builder->build_persona( $user_id, $guest_uuid );

		return rest_ensure_response(
			array(
				'success' => true,
				'persona' => $persona,
				'summary' => $persona_builder->get_persona_summary( $persona ),
			)
		);
	}

	/**
	 * REST API: Get user profile
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_get_profile( $request ) {
		$guest_uuid = $request->get_param( 'guest_uuid' );
		$user_id    = get_current_user_id();

		$profile_manager = new Tabesh_AI_User_Profile();

		if ( $user_id ) {
			$profile = $profile_manager->get_user_profile( $user_id );
		} elseif ( $guest_uuid ) {
			$profile = $profile_manager->get_guest_profile( $guest_uuid );
		} else {
			return new WP_Error(
				'no_identifier',
				__( 'شناسه کاربر یافت نشد', 'tabesh' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'profile' => $profile,
			)
		);
	}

	/**
	 * REST API: Navigate to target page
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function rest_navigate( $request ) {
		$profession = $request->get_param( 'profession' );
		$context    = $request->get_param( 'context' );
		$guest_uuid = $request->get_param( 'guest_uuid' );
		$user_id    = get_current_user_id();

		// Save user profession.
		$profile_manager = new Tabesh_AI_User_Profile();
		if ( $user_id ) {
			$profile_manager->update_user_profession( $user_id, $profession );
		} elseif ( $guest_uuid ) {
			$profile_manager->update_guest_profession( $guest_uuid, $profession );
		}

		// Get target URL based on profession.
		$target_url = $this->get_target_url_for_profession( $profession, $context );

		return rest_ensure_response(
			array(
				'success'    => true,
				'target_url' => $target_url,
				'message'    => __( 'در حال هدایت شما...', 'tabesh' ),
			)
		);
	}

	/**
	 * REST API: Start tour guide
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function rest_start_tour( $request ) {
		$target = $request->get_param( 'target' );

		$tour_guide = new Tabesh_AI_Tour_Guide();
		$tour_steps = $tour_guide->get_tour_steps( $target );

		return rest_ensure_response(
			array(
				'success' => true,
				'steps'   => $tour_steps,
			)
		);
	}

	/**
	 * REST API: Get AI suggestions
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function rest_get_suggestions( $request ) {
		$context    = $request->get_param( 'context' );
		$guest_uuid = $request->get_param( 'guest_uuid' );
		$user_id    = get_current_user_id();

		// Get user profile.
		$profile_manager = new Tabesh_AI_User_Profile();
		if ( $user_id ) {
			$profile = $profile_manager->get_user_profile( $user_id );
		} elseif ( $guest_uuid ) {
			$profile = $profile_manager->get_guest_profile( $guest_uuid );
		} else {
			$profile = array();
		}

		// Generate context-aware suggestions.
		$suggestions = $this->generate_suggestions( $context, $profile );

		return rest_ensure_response(
			array(
				'success'     => true,
				'suggestions' => $suggestions,
			)
		);
	}

	/**
	 * REST API: Save chat history
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function rest_save_chat_history( $request ) {
		$guest_uuid   = $request->get_param( 'guest_uuid' );
		$chat_history = $request->get_param( 'chat_history' );
		$user_id      = get_current_user_id();

		// Sanitize chat history.
		$sanitized_history = array();
		if ( is_array( $chat_history ) ) {
			foreach ( $chat_history as $message ) {
				if ( isset( $message['content'] ) && isset( $message['role'] ) ) {
					$sanitized_history[] = array(
						'content'   => sanitize_textarea_field( $message['content'] ),
						'role'      => sanitize_text_field( $message['role'] ),
						'timestamp' => isset( $message['timestamp'] ) ? absint( $message['timestamp'] ) : time(),
					);
				}
			}
		}

		// Save to profile.
		$profile_manager = new Tabesh_AI_User_Profile();
		if ( $user_id ) {
			$result = $profile_manager->update_chat_history( $user_id, $sanitized_history );
		} elseif ( $guest_uuid ) {
			$result = $profile_manager->update_guest_chat_history( $guest_uuid, $sanitized_history );
		} else {
			return new WP_Error(
				'no_identifier',
				__( 'شناسه کاربر یافت نشد', 'tabesh' ),
				array( 'status' => 400 )
			);
		}

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'تاریخچه ذخیره شد', 'tabesh' ),
				)
			);
		}

		return new WP_Error(
			'save_failed',
			__( 'خطا در ذخیره تاریخچه', 'tabesh' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Get target URL based on user profession
	 *
	 * @param string $profession User profession.
	 * @param array  $context Additional context (unused but kept for future extensibility).
	 * @return string Target URL.
	 */
	private function get_target_url_for_profession( $profession, $context = array() ) {
		// Get settings for profession routing.
		$routes = get_option(
			'tabesh_ai_profession_routes',
			array(
				'buyer'     => home_url( '/order-form/' ),
				'author'    => home_url( '/author-services/' ),
				'publisher' => home_url( '/publisher-services/' ),
				'printer'   => home_url( '/printer-services/' ),
			)
		);

		// Return configured route or default to order form.
		return isset( $routes[ $profession ] ) ? $routes[ $profession ] : home_url( '/order-form/' );
	}

	/**
	 * Generate context-aware suggestions
	 *
	 * @param array $context Page context.
	 * @param array $profile User profile.
	 * @return array Suggestions.
	 */
	private function generate_suggestions( $context, $profile ) {
		$suggestions = array();

		// Detect current page type.
		$page_url = isset( $context['page_url'] ) ? $context['page_url'] : '';

		// Home page suggestions.
		if ( empty( $page_url ) || strpos( $page_url, home_url() ) === 0 ) {
			$suggestions[] = array(
				'text'   => __( 'مشاهده نمونه کارها', 'tabesh' ),
				'action' => 'navigate',
				'target' => home_url( '/portfolio/' ),
			);
			$suggestions[] = array(
				'text'   => __( 'محاسبه قیمت چاپ کتاب', 'tabesh' ),
				'action' => 'navigate',
				'target' => home_url( '/order-form/' ),
			);
		}

		// Order form page suggestions.
		if ( strpos( $page_url, 'order-form' ) !== false ) {
			$suggestions[] = array(
				'text'   => __( 'راهنمای تکمیل فرم', 'tabesh' ),
				'action' => 'tour',
				'target' => 'order-form',
			);
			$suggestions[] = array(
				'text'   => __( 'تماس با پشتیبانی', 'tabesh' ),
				'action' => 'chat',
				'target' => 'support',
			);
		}

		// Add profession-based suggestions.
		if ( ! empty( $profile['profession'] ) ) {
			switch ( $profile['profession'] ) {
				case 'author':
					$suggestions[] = array(
						'text'   => __( 'خدمات ویژه نویسندگان', 'tabesh' ),
						'action' => 'navigate',
						'target' => home_url( '/author-services/' ),
					);
					break;
				case 'publisher':
					$suggestions[] = array(
						'text'   => __( 'خدمات ویژه ناشران', 'tabesh' ),
						'action' => 'navigate',
						'target' => home_url( '/publisher-services/' ),
					);
					break;
			}
		}

		return $suggestions;
	}

	/**
	 * Enqueue browser assets
	 */
	public function enqueue_assets() {
		// Check if AI browser is enabled.
		if ( ! get_option( 'tabesh_ai_browser_enabled', true ) ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'tabesh-ai-browser',
			TABESH_PLUGIN_URL . 'assets/css/ai-browser.css',
			array(),
			TABESH_VERSION
		);

		wp_enqueue_style(
			'tabesh-ai-instant-highlight',
			TABESH_PLUGIN_URL . 'assets/css/ai-instant-highlight.css',
			array(),
			TABESH_VERSION
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'tabesh-ai-page-analyzer',
			TABESH_PLUGIN_URL . 'assets/js/ai-page-analyzer.js',
			array( 'jquery' ),
			TABESH_VERSION,
			true
		);

		wp_enqueue_script(
			'tabesh-ai-field-explainer',
			TABESH_PLUGIN_URL . 'assets/js/ai-field-explainer.js',
			array( 'jquery', 'tabesh-ai-page-analyzer' ),
			TABESH_VERSION,
			true
		);

		wp_enqueue_script(
			'tabesh-ai-browser',
			TABESH_PLUGIN_URL . 'assets/js/ai-browser.js',
			array( 'jquery', 'tabesh-ai-page-analyzer' ),
			TABESH_VERSION,
			true
		);

		wp_enqueue_script(
			'tabesh-ai-tracker',
			TABESH_PLUGIN_URL . 'assets/js/ai-tracker.js',
			array( 'jquery', 'tabesh-ai-browser' ),
			TABESH_VERSION,
			true
		);

		wp_enqueue_script(
			'tabesh-ai-tour-guide',
			TABESH_PLUGIN_URL . 'assets/js/ai-tour-guide.js',
			array( 'jquery', 'tabesh-ai-browser' ),
			TABESH_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'tabesh-ai-browser',
			'tabeshAIBrowser',
			array(
				'ajaxUrl'              => rest_url( TABESH_REST_NAMESPACE ),
				'nonce'                => wp_create_nonce( 'wp_rest' ),
				'isLoggedIn'           => is_user_logged_in(),
				'userId'               => get_current_user_id(),
				'trackingEnabled'      => get_option( 'tabesh_ai_tracking_enabled', true ),
				'fieldExplainerEnabled' => get_option( 'tabesh_ai_field_explainer_enabled', true ),
				'strings'              => array(
					'greeting'             => __( 'سلام! من دستیار هوشمند تابش هستم. اجازه میدید کمکتون کنم؟', 'tabesh' ),
					'profession_buyer'     => __( 'آیا خریدار کتاب هستید؟', 'tabesh' ),
					'profession_author'    => __( 'آیا نویسنده هستید؟', 'tabesh' ),
					'profession_publisher' => __( 'آیا ناشر هستید؟', 'tabesh' ),
					'profession_printer'   => __( 'آیا چاپخانه‌دار هستید؟', 'tabesh' ),
					'show_target'          => __( 'اجازه میدید چیزی را به شما نشان دهم که شاید به دنبالش میگردید؟', 'tabesh' ),
					'yes'                  => __( 'بله', 'tabesh' ),
					'no'                   => __( 'خیر', 'tabesh' ),
					'close'                => __( 'بستن', 'tabesh' ),
					'minimize'             => __( 'کوچک کردن', 'tabesh' ),
					'open_chat'            => __( 'باز کردن گفتگو', 'tabesh' ),
					'error'                => __( 'خطایی رخ داده است', 'tabesh' ),
				),
			)
		);
	}

	/**
	 * Render browser sidebar in footer
	 */
	public function render_browser_sidebar() {
		// Check if AI browser is enabled.
		if ( ! get_option( 'tabesh_ai_browser_enabled', true ) ) {
			return;
		}

		// Check if user has access.
		if ( ! Tabesh_AI_Config::user_has_access() ) {
			return;
		}

		// Load template.
		$template_path = TABESH_PLUGIN_DIR . 'templates/frontend/ai-browser-sidebar.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}
}
