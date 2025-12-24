<?php
/**
 * Tabesh AI Module
 *
 * Main AI module class that manages AI models and assistants.
 * This class is completely modular and isolated from the core plugin.
 * It can be disabled without affecting any core functionality.
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
 * Class Tabesh_AI
 *
 * Central AI module controller
 */
class Tabesh_AI {

	/**
	 * Singleton instance
	 *
	 * @var Tabesh_AI
	 */
	private static $instance = null;

	/**
	 * Registered AI models
	 *
	 * @var array
	 */
	private $models = array();

	/**
	 * Registered AI assistants
	 *
	 * @var array
	 */
	private $assistants = array();

	/**
	 * AI module enabled status
	 *
	 * @var bool
	 */
	private $enabled = false;

	/**
	 * Settings cache
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get singleton instance
	 *
	 * @return Tabesh_AI
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_settings();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Only initialize if AI module is enabled
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Register models and assistants
		add_action( 'init', array( $this, 'register_default_models' ), 5 );
		add_action( 'init', array( $this, 'register_default_assistants' ), 6 );

		// REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Admin hooks
		if ( is_admin() ) {
			add_filter( 'tabesh_settings_tabs', array( $this, 'add_settings_tab' ), 10, 1 );
			add_action( 'tabesh_settings_tab_content_ai', array( $this, 'render_settings_tab' ) );
			add_action( 'tabesh_save_settings', array( $this, 'save_settings' ), 10, 1 );
		}

		/**
		 * Hook: AI module initialized
		 *
		 * Allows other plugins/modules to extend AI functionality
		 *
		 * @param Tabesh_AI $ai_instance The AI module instance
		 */
		do_action( 'tabesh_ai_initialized', $this );
	}

	/**
	 * Load settings from database
	 */
	private function load_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		// Load all AI-related settings
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value FROM {$table} WHERE setting_key LIKE %s",
				'ai_%'
			),
			ARRAY_A
		);

		if ( $results ) {
			foreach ( $results as $row ) {
				$value                                 = maybe_unserialize( $row['setting_value'] );
				$this->settings[ $row['setting_key'] ] = $value;
			}
		}

		// Check if AI module is enabled
		$this->enabled = isset( $this->settings['ai_enabled'] ) && $this->settings['ai_enabled'] === 'yes';
	}

	/**
	 * Check if AI module is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		/**
		 * Filter: AI module enabled status
		 *
		 * @param bool $enabled Whether AI module is enabled
		 */
		return apply_filters( 'tabesh_ai_is_enabled', $this->enabled );
	}

	/**
	 * Register a new AI model
	 *
	 * @param Tabesh_AI_Model_Interface $model The model instance
	 * @return bool True on success, false on failure
	 */
	public function register_model( Tabesh_AI_Model_Interface $model ) {
		$model_id = $model->get_model_id();

		if ( isset( $this->models[ $model_id ] ) ) {
			error_log( sprintf( 'Tabesh AI: Model "%s" is already registered', $model_id ) );
			return false;
		}

		$this->models[ $model_id ] = $model;

		/**
		 * Action: AI model registered
		 *
		 * @param string                    $model_id Model identifier
		 * @param Tabesh_AI_Model_Interface $model    Model instance
		 */
		do_action( 'tabesh_ai_model_registered', $model_id, $model );

		return true;
	}

	/**
	 * Register a new AI assistant
	 *
	 * @param Tabesh_AI_Assistant_Interface $assistant The assistant instance
	 * @return bool True on success, false on failure
	 */
	public function register_assistant( Tabesh_AI_Assistant_Interface $assistant ) {
		$assistant_id = $assistant->get_assistant_id();

		if ( isset( $this->assistants[ $assistant_id ] ) ) {
			error_log( sprintf( 'Tabesh AI: Assistant "%s" is already registered', $assistant_id ) );
			return false;
		}

		$this->assistants[ $assistant_id ] = $assistant;

		/**
		 * Action: AI assistant registered
		 *
		 * @param string                        $assistant_id Assistant identifier
		 * @param Tabesh_AI_Assistant_Interface $assistant    Assistant instance
		 */
		do_action( 'tabesh_ai_assistant_registered', $assistant_id, $assistant );

		return true;
	}

	/**
	 * Get a registered AI model
	 *
	 * @param string $model_id Model identifier
	 * @return Tabesh_AI_Model_Interface|null Model instance or null if not found
	 */
	public function get_model( $model_id ) {
		return isset( $this->models[ $model_id ] ) ? $this->models[ $model_id ] : null;
	}

	/**
	 * Get a registered AI assistant
	 *
	 * @param string $assistant_id Assistant identifier
	 * @return Tabesh_AI_Assistant_Interface|null Assistant instance or null if not found
	 */
	public function get_assistant( $assistant_id ) {
		return isset( $this->assistants[ $assistant_id ] ) ? $this->assistants[ $assistant_id ] : null;
	}

	/**
	 * Get all registered models
	 *
	 * @return array Array of model instances
	 */
	public function get_all_models() {
		/**
		 * Filter: Registered AI models
		 *
		 * @param array $models Array of model instances
		 */
		return apply_filters( 'tabesh_ai_models', $this->models );
	}

	/**
	 * Get all registered assistants
	 *
	 * @return array Array of assistant instances
	 */
	public function get_all_assistants() {
		/**
		 * Filter: Registered AI assistants
		 *
		 * @param array $assistants Array of assistant instances
		 */
		return apply_filters( 'tabesh_ai_assistants', $this->assistants );
	}

	/**
	 * Register default AI models
	 *
	 * These can be extended via hooks
	 */
	public function register_default_models() {
		// Models will be auto-loaded via the autoloader
		// Register them here if they exist

		$default_models = array(
			'Tabesh_AI_Model_GPT',
			'Tabesh_AI_Model_Gemini',
			'Tabesh_AI_Model_Grok',
			'Tabesh_AI_Model_DeepSeek',
		);

		foreach ( $default_models as $model_class ) {
			if ( class_exists( $model_class ) ) {
				try {
					$model = new $model_class();
					$this->register_model( $model );
				} catch ( Exception $e ) {
					error_log( sprintf( 'Tabesh AI: Failed to instantiate model "%s": %s', $model_class, $e->getMessage() ) );
				}
			}
		}

		/**
		 * Action: Register custom AI models
		 *
		 * Allows plugins to register additional AI models
		 *
		 * @param Tabesh_AI $ai_instance The AI module instance
		 */
		do_action( 'tabesh_ai_register_models', $this );
	}

	/**
	 * Register default AI assistants
	 */
	public function register_default_assistants() {
		// Assistants will be auto-loaded via the autoloader
		$default_assistants = array(
			'Tabesh_AI_Assistant_Order',
			'Tabesh_AI_Assistant_User_Help',
			'Tabesh_AI_Assistant_Admin_Tools',
		);

		foreach ( $default_assistants as $assistant_class ) {
			if ( class_exists( $assistant_class ) ) {
				try {
					$assistant = new $assistant_class();
					$this->register_assistant( $assistant );
				} catch ( Exception $e ) {
					error_log( sprintf( 'Tabesh AI: Failed to instantiate assistant "%s": %s', $assistant_class, $e->getMessage() ) );
				}
			}
		}

		/**
		 * Action: Register custom AI assistants
		 *
		 * Allows plugins to register additional AI assistants
		 *
		 * @param Tabesh_AI $ai_instance The AI module instance
		 */
		do_action( 'tabesh_ai_register_assistants', $this );
	}

	/**
	 * Get a setting value
	 *
	 * @param string $key     Setting key
	 * @param mixed  $default Default value
	 * @return mixed Setting value
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Add AI settings tab to admin settings
	 *
	 * @param array $tabs Existing tabs
	 * @return array Modified tabs
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['ai'] = array(
			'title' => __( 'تنظیمات هوش مصنوعی', 'tabesh' ) . ' / AI Settings',
			'icon'  => 'dashicons-admin-generic',
		);
		return $tabs;
	}

	/**
	 * Render AI settings tab content
	 */
	public function render_settings_tab() {
		include TABESH_PLUGIN_DIR . 'templates/admin/partials/admin-settings-ai.php';
	}

	/**
	 * Save AI settings
	 *
	 * @param array $post_data POST data from settings form
	 */
	public function save_settings( $post_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_settings';

		// AI enabled/disabled
		if ( isset( $post_data['ai_enabled'] ) ) {
			$wpdb->replace(
				$table,
				array(
					'setting_key'   => 'ai_enabled',
					'setting_value' => sanitize_text_field( $post_data['ai_enabled'] ),
					'setting_type'  => 'string',
				)
			);
		}

		// Active models
		if ( isset( $post_data['ai_active_models'] ) && is_array( $post_data['ai_active_models'] ) ) {
			$wpdb->replace(
				$table,
				array(
					'setting_key'   => 'ai_active_models',
					'setting_value' => wp_json_encode( array_map( 'sanitize_text_field', $post_data['ai_active_models'] ) ),
					'setting_type'  => 'string',
				)
			);
		}

		// Model configurations (API keys, etc.)
		foreach ( $this->get_all_models() as $model_id => $model ) {
			$prefix = 'ai_model_' . $model_id . '_';
			foreach ( $model->get_config_fields() as $field_key => $field_config ) {
				$post_key = $prefix . $field_key;
				if ( isset( $post_data[ $post_key ] ) ) {
					$value = sanitize_text_field( $post_data[ $post_key ] );
					$wpdb->replace(
						$table,
						array(
							'setting_key'   => $post_key,
							'setting_value' => $value,
							'setting_type'  => 'string',
						)
					);
				}
			}
		}

		// Assistant configurations
		if ( isset( $post_data['ai_assistants_config'] ) && is_array( $post_data['ai_assistants_config'] ) ) {
			foreach ( $post_data['ai_assistants_config'] as $assistant_id => $config ) {
				$setting_key = 'ai_assistant_' . sanitize_text_field( $assistant_id ) . '_config';
				$wpdb->replace(
					$table,
					array(
						'setting_key'   => $setting_key,
						'setting_value' => wp_json_encode( $config ),
						'setting_type'  => 'string',
					)
				);
			}
		}

		// Reload settings
		$this->load_settings();
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// AI query endpoint
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/query',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_query_ai' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);

		// Get assistants endpoint
		register_rest_route(
			TABESH_REST_NAMESPACE,
			'/ai/assistants',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_assistants' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * REST API: Query AI assistant
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response
	 */
	public function rest_query_ai( $request ) {
		$assistant_id = sanitize_text_field( $request->get_param( 'assistant_id' ) );
		$query        = sanitize_text_field( $request->get_param( 'query' ) );
		$context      = $request->get_param( 'context' );

		if ( empty( $assistant_id ) || empty( $query ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'پارامترهای الزامی ارسال نشده است', 'tabesh' ),
				),
				400
			);
		}

		$assistant = $this->get_assistant( $assistant_id );
		if ( ! $assistant ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'دستیار مورد نظر یافت نشد', 'tabesh' ),
				),
				404
			);
		}

		// Check user access
		if ( ! $assistant->can_user_access() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'شما دسترسی به این دستیار را ندارید', 'tabesh' ),
				),
				403
			);
		}

		// Process the request
		$response = $assistant->process_request( $query, is_array( $context ) ? $context : array() );

		return new WP_REST_Response( $response, $response['success'] ? 200 : 500 );
	}

	/**
	 * REST API: Get available assistants for current user
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response
	 */
	public function rest_get_assistants( $request ) {
		$assistants = array();

		foreach ( $this->get_all_assistants() as $assistant_id => $assistant ) {
			if ( $assistant->can_user_access() ) {
				$assistants[] = array(
					'id'           => $assistant->get_assistant_id(),
					'name'         => $assistant->get_assistant_name(),
					'description'  => $assistant->get_assistant_description(),
					'roles'        => $assistant->get_allowed_roles(),
					'capabilities' => $assistant->get_capabilities(),
				);
			}
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'assistants' => $assistants,
			),
			200
		);
	}
}
