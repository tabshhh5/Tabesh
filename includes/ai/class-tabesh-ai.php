<?php
/**
 * Main AI Controller Class
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI
 *
 * Main AI system controller
 */
class Tabesh_AI {

	/**
	 * Gemini API instance
	 *
	 * @var Tabesh_AI_Gemini
	 */
	private $gemini;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();

		// Initialize Gemini driver if in direct mode.
		if ( Tabesh_AI_Config::get_mode() === Tabesh_AI_Config::MODE_DIRECT ) {
			$this->gemini = new Tabesh_AI_Gemini();
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Register shortcode.
		add_shortcode( 'tabesh_ai_chat', array( $this, 'render_chat_interface' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Chat endpoint.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_chat' ),
				'permission_callback' => array( $this, 'check_ai_permission' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context' => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
				),
			)
		);

		// Get form data endpoint.
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/form-data',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_form_data' ),
				'permission_callback' => array( $this, 'check_ai_permission' ),
			)
		);

		// Forward request endpoint (for client mode).
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/forward',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_forward_request' ),
				'permission_callback' => array( $this, 'check_ai_permission' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context' => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
				),
			)
		);
	}

	/**
	 * Check AI permission for REST API
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function check_ai_permission( $request ) {
		if ( ! Tabesh_AI_Config::is_enabled() ) {
			return new WP_Error(
				'ai_disabled',
				__( 'سیستم هوش مصنوعی غیرفعال است', 'tabesh' ),
				array( 'status' => 403 )
			);
		}

		if ( ! Tabesh_AI_Config::user_has_access() ) {
			return new WP_Error(
				'no_permission',
				__( 'شما دسترسی به سیستم هوش مصنوعی ندارید', 'tabesh' ),
				array( 'status' => 403 )
			);
		}

		// Verify nonce for logged-in users.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( is_user_logged_in() && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'کد امنیتی نامعتبر است', 'tabesh' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * REST API: Chat endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_chat( $request ) {
		$message = $request->get_param( 'message' );
		$context = $request->get_param( 'context' );

		// Sanitize context data.
		$context = $this->sanitize_context( $context );

		// Add user information to context.
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->exists() ) {
			$context['user_name'] = $current_user->display_name;
			$context['user_id']   = $current_user->ID;
		}

		// Get response based on mode.
		$mode = Tabesh_AI_Config::get_mode();

		switch ( $mode ) {
			case Tabesh_AI_Config::MODE_DIRECT:
				$response = $this->handle_direct_request( $message, $context );
				break;

			case Tabesh_AI_Config::MODE_SERVER:
				$response = $this->handle_server_request( $message, $context );
				break;

			case Tabesh_AI_Config::MODE_CLIENT:
				$response = $this->handle_client_request( $message, $context );
				break;

			default:
				return new WP_Error( 'invalid_mode', __( 'حالت AI نامعتبر است', 'tabesh' ) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Handle direct request to Gemini
	 *
	 * @param string $message User message.
	 * @param array  $context Context data.
	 * @return array|WP_Error Response or error.
	 */
	private function handle_direct_request( $message, $context ) {
		if ( ! $this->gemini ) {
			$this->gemini = new Tabesh_AI_Gemini();
		}

		return $this->gemini->chat( $message, $context );
	}

	/**
	 * Handle server request (act as server for external clients)
	 *
	 * @param string $message User message.
	 * @param array  $context Context data.
	 * @return array|WP_Error Response or error.
	 */
	private function handle_server_request( $message, $context ) {
		// When acting as server, process request directly.
		return $this->handle_direct_request( $message, $context );
	}

	/**
	 * Handle client request (forward to external server)
	 *
	 * @param string $message User message.
	 * @param array  $context Context data.
	 * @return array|WP_Error Response or error.
	 */
	private function handle_client_request( $message, $context ) {
		$server_url     = Tabesh_AI_Config::get( 'server_url', '' );
		$server_api_key = Tabesh_AI_Config::get( 'server_api_key', '' );

		if ( empty( $server_url ) ) {
			return new WP_Error( 'no_server_url', __( 'آدرس سرور تنظیم نشده است', 'tabesh' ) );
		}

		// Forward request to external server.
		$response = wp_remote_post(
			trailingslashit( $server_url ) . 'wp-json/tabesh/v1/ai/chat',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $server_api_key,
				),
				'body'    => wp_json_encode(
					array(
						'message' => $message,
						'context' => $context,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error(
				'server_error',
				sprintf(
					/* translators: %d: HTTP response code */
					__( 'خطای سرور: کد %d', 'tabesh' ),
					$response_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * REST API: Get form data endpoint
	 *
	 * @param WP_REST_Request $request Request object (unused but required by REST API).
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_get_form_data( $request ) {
		// Suppress unused parameter warning - required by REST API signature.
		unset( $request );

		$form_data = array();

		// Get available options.
		$form_data['book_sizes']    = $this->get_setting_array( 'book_sizes' );
		$form_data['paper_types']   = $this->get_setting_array( 'paper_types' );
		$form_data['print_types']   = $this->get_setting_array( 'print_types' );
		$form_data['binding_types'] = $this->get_setting_array( 'binding_types' );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $form_data,
			)
		);
	}

	/**
	 * REST API: Forward request endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_forward_request( $request ) {
		// This is the same as chat endpoint but explicitly for client mode.
		return $this->rest_chat( $request );
	}

	/**
	 * Render chat interface shortcode
	 *
	 * @param array $atts Shortcode attributes (unused but required by shortcode API).
	 * @return string Chat interface HTML.
	 */
	public function render_chat_interface( $atts ) {
		// Suppress unused parameter warning - required by shortcode API signature.
		unset( $atts );

		if ( ! Tabesh_AI_Config::is_enabled() ) {
			return '<div class="tabesh-ai-disabled">' . esc_html__( 'سیستم هوش مصنوعی غیرفعال است', 'tabesh' ) . '</div>';
		}

		if ( ! Tabesh_AI_Config::user_has_access() ) {
			return '<div class="tabesh-ai-no-access">' . esc_html__( 'شما دسترسی به سیستم هوش مصنوعی ندارید', 'tabesh' ) . '</div>';
		}

		// Enqueue scripts and styles.
		$this->enqueue_chat_assets();

		// Get current user info.
		$current_user = wp_get_current_user();
		$user_name    = $current_user && $current_user->exists() ? $current_user->display_name : __( 'مهمان', 'tabesh' );

		ob_start();
		include TABESH_PLUGIN_DIR . 'templates/frontend/ai-chat.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue chat assets
	 */
	private function enqueue_chat_assets() {
		wp_enqueue_style(
			'tabesh-ai-chat',
			TABESH_PLUGIN_URL . 'assets/css/ai-chat.css',
			array(),
			TABESH_VERSION
		);

		wp_enqueue_script(
			'tabesh-ai-chat',
			TABESH_PLUGIN_URL . 'assets/js/ai-chat.js',
			array( 'jquery' ),
			TABESH_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'tabesh-ai-chat',
			'tabeshAI',
			array(
				'ajaxUrl' => rest_url( TABESH_REST_NAMESPACE . '/ai' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'sendButton'     => __( 'ارسال', 'tabesh' ),
					'placeholder'    => __( 'پیام خود را بنویسید...', 'tabesh' ),
					'errorMessage'   => __( 'خطا در ارسال پیام', 'tabesh' ),
					'connecting'     => __( 'در حال اتصال...', 'tabesh' ),
					'welcomeMessage' => __( 'سلام! چطور می‌تونم کمکتون کنم؟', 'tabesh' ),
				),
			)
		);
	}

	/**
	 * Get setting as array
	 *
	 * @param string $key Setting key.
	 * @return array Setting value as array.
	 */
	private function get_setting_array( $key ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				// Note: Table name comes from $wpdb->prefix which is safe.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT setting_value FROM $table WHERE setting_key = %s",
				$key
			)
		);

		if ( null === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Sanitize context data
	 *
	 * @param array $context Context data to sanitize.
	 * @return array Sanitized context.
	 */
	private function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return array();
		}

		$sanitized = array();

		// Sanitize form data.
		if ( isset( $context['form_data'] ) && is_array( $context['form_data'] ) ) {
			$sanitized['form_data'] = array_map( 'sanitize_text_field', $context['form_data'] );
		}

		// Filter based on permissions.
		return Tabesh_AI_Permissions::filter_data( $sanitized );
	}
}
