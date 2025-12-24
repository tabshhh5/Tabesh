<?php
/**
 * Base AI Assistant Class
 *
 * Abstract base class for AI assistants with role-based access control
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
 * Class Tabesh_AI_Assistant_Base
 */
abstract class Tabesh_AI_Assistant_Base implements Tabesh_AI_Assistant_Interface {

	/**
	 * Assistant identifier
	 *
	 * @var string
	 */
	protected $assistant_id = '';

	/**
	 * Assistant name
	 *
	 * @var string
	 */
	protected $assistant_name = '';

	/**
	 * Assistant description
	 *
	 * @var string
	 */
	protected $assistant_description = '';

	/**
	 * Allowed roles for this assistant
	 *
	 * @var array
	 */
	protected $allowed_roles = array( 'administrator' );

	/**
	 * Assistant capabilities
	 *
	 * @var array
	 */
	protected $capabilities = array();

	/**
	 * System prompt for this assistant
	 *
	 * @var string
	 */
	protected $system_prompt = '';

	/**
	 * Preferred AI model
	 *
	 * @var string
	 */
	protected $preferred_model = 'gpt';

	/**
	 * Get the unique identifier for this assistant
	 *
	 * @return string
	 */
	public function get_assistant_id() {
		return $this->assistant_id;
	}

	/**
	 * Get the human-readable name of the assistant
	 *
	 * @return string
	 */
	public function get_assistant_name() {
		return $this->assistant_name;
	}

	/**
	 * Get the description of what this assistant does
	 *
	 * @return string
	 */
	public function get_assistant_description() {
		return $this->assistant_description;
	}

	/**
	 * Get the allowed roles for this assistant
	 *
	 * @return array
	 */
	public function get_allowed_roles() {
		// Allow configuration override
		$ai     = Tabesh_AI::instance();
		$config = $ai->get_setting( 'ai_assistant_' . $this->assistant_id . '_config', array() );

		if ( ! empty( $config['allowed_roles'] ) && is_array( $config['allowed_roles'] ) ) {
			return $config['allowed_roles'];
		}

		return $this->allowed_roles;
	}

	/**
	 * Get the capabilities this assistant has
	 *
	 * @return array
	 */
	public function get_capabilities() {
		// Allow configuration override
		$ai     = Tabesh_AI::instance();
		$config = $ai->get_setting( 'ai_assistant_' . $this->assistant_id . '_config', array() );

		if ( ! empty( $config['capabilities'] ) && is_array( $config['capabilities'] ) ) {
			return $config['capabilities'];
		}

		return $this->capabilities;
	}

	/**
	 * Check if the current user can use this assistant
	 *
	 * @param int $user_id Optional user ID, defaults to current user
	 * @return bool
	 */
	public function can_user_access( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		$user          = get_userdata( $user_id );
		$user_roles    = $user ? $user->roles : array();
		$allowed_roles = $this->get_allowed_roles();

		// Check if user has any of the allowed roles
		$has_access = ! empty( array_intersect( $user_roles, $allowed_roles ) );

		/**
		 * Filter: Assistant access check
		 *
		 * @param bool   $has_access   Whether user has access
		 * @param int    $user_id      User ID
		 * @param string $assistant_id Assistant identifier
		 */
		return apply_filters( 'tabesh_ai_assistant_can_access', $has_access, $user_id, $this->assistant_id );
	}

	/**
	 * Get the system prompt for this assistant
	 *
	 * @return string
	 */
	public function get_system_prompt() {
		// Allow configuration override
		$ai     = Tabesh_AI::instance();
		$config = $ai->get_setting( 'ai_assistant_' . $this->assistant_id . '_config', array() );

		if ( ! empty( $config['system_prompt'] ) ) {
			return $config['system_prompt'];
		}

		return $this->system_prompt;
	}

	/**
	 * Process a request to this assistant
	 *
	 * @param string $request The user's request
	 * @param array  $context Additional context data
	 * @return array Response array
	 */
	public function process_request( $request, $context = array() ) {
		// Get AI instance
		$ai = Tabesh_AI::instance();

		// Get active models
		$active_models = $ai->get_setting( 'ai_active_models', array() );

		if ( empty( $active_models ) || ! is_array( $active_models ) ) {
			return array(
				'success' => false,
				'message' => __( 'هیچ مدل هوش مصنوعی فعالی وجود ندارد', 'tabesh' ),
			);
		}

		// Try preferred model first
		$model = null;
		if ( in_array( $this->preferred_model, $active_models, true ) ) {
			$model = $ai->get_model( $this->preferred_model );
		}

		// If preferred model not available, use first active model
		if ( ! $model || ! $model->is_configured() ) {
			foreach ( $active_models as $model_id ) {
				$temp_model = $ai->get_model( $model_id );
				if ( $temp_model && $temp_model->is_configured() ) {
					$model = $temp_model;
					break;
				}
			}
		}

		if ( ! $model ) {
			return array(
				'success' => false,
				'message' => __( 'هیچ مدل هوش مصنوعی پیکربندی شده‌ای در دسترس نیست', 'tabesh' ),
			);
		}

		// Prepare context with assistant-specific data
		$enhanced_context = $this->prepare_context( $context );

		// Generate response
		$response = $model->generate(
			$request,
			$enhanced_context,
			array(
				'system_prompt' => $this->get_system_prompt(),
			)
		);

		if ( ! $response['success'] ) {
			return array(
				'success' => false,
				'message' => $response['error'],
			);
		}

		return array(
			'success' => true,
			'message' => $response['data']['text'],
			'data'    => array(
				'model'       => $response['data']['model'],
				'tokens_used' => $response['data']['tokens'],
			),
		);
	}

	/**
	 * Prepare context for the AI request
	 *
	 * Override this in child classes to add assistant-specific context
	 *
	 * @param array $context Base context
	 * @return array Enhanced context
	 */
	protected function prepare_context( $context ) {
		return $context;
	}
}
