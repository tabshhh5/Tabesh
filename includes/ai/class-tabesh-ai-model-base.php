<?php
/**
 * Base AI Model Class
 *
 * Abstract base class that provides common functionality for all AI models.
 * Model-specific implementations should extend this class.
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
 * Class Tabesh_AI_Model_Base
 *
 * Base implementation for AI models
 */
abstract class Tabesh_AI_Model_Base implements Tabesh_AI_Model_Interface {

	/**
	 * Model identifier
	 *
	 * @var string
	 */
	protected $model_id = '';

	/**
	 * Model name
	 *
	 * @var string
	 */
	protected $model_name = '';

	/**
	 * API endpoint URL
	 *
	 * @var string
	 */
	protected $api_endpoint = '';

	/**
	 * Maximum tokens for this model
	 *
	 * @var int
	 */
	protected $max_tokens = 4096;

	/**
	 * Configuration fields for this model
	 *
	 * @var array
	 */
	protected $config_fields = array();

	/**
	 * Get the unique identifier for this model
	 *
	 * @return string
	 */
	public function get_model_id() {
		return $this->model_id;
	}

	/**
	 * Get the human-readable name of the model
	 *
	 * @return string
	 */
	public function get_model_name() {
		return $this->model_name;
	}

	/**
	 * Get the maximum token limit for this model
	 *
	 * @return int
	 */
	public function get_max_tokens() {
		return $this->max_tokens;
	}

	/**
	 * Get the configuration fields required for this model
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return $this->config_fields;
	}

	/**
	 * Check if the model is properly configured
	 *
	 * @return bool
	 */
	public function is_configured() {
		$config = $this->get_configuration();

		foreach ( $this->config_fields as $field_key => $field_config ) {
			if ( ! empty( $field_config['required'] ) && empty( $config[ $field_key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the model configuration from settings
	 *
	 * @return array
	 */
	protected function get_configuration() {
		$config = array();
		$ai     = Tabesh_AI::instance();

		foreach ( $this->config_fields as $field_key => $field_config ) {
			$setting_key          = 'ai_model_' . $this->model_id . '_' . $field_key;
			$config[ $field_key ] = $ai->get_setting( $setting_key, '' );
		}

		return $config;
	}

	/**
	 * Make an API request to the AI service
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $body     Request body
	 * @param array  $headers  Additional headers
	 * @return array Response array with 'success' and 'data' or 'error' keys
	 */
	protected function make_api_request( $endpoint, $body, $headers = array() ) {
		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array_merge(
					array(
						'Content-Type' => 'application/json',
					),
					$headers
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			return array(
				'success' => false,
				'error'   => isset( $data['error'] ) ? $data['error'] : __( 'خطای ناشناخته از سرور', 'tabesh' ),
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Validate API credentials
	 *
	 * Default implementation - should be overridden by specific models
	 *
	 * @param array $credentials Credentials to validate
	 * @return bool
	 */
	public function validate_credentials( $credentials ) {
		// Basic validation - check that required fields are not empty
		foreach ( $this->config_fields as $field_key => $field_config ) {
			if ( ! empty( $field_config['required'] ) && empty( $credentials[ $field_key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Generate AI response
	 *
	 * This method must be implemented by child classes
	 *
	 * @param string $prompt  The prompt to send
	 * @param array  $context Context data
	 * @param array  $options Model-specific options
	 * @return array Response array
	 */
	abstract public function generate( $prompt, $context = array(), $options = array() );

	/**
	 * Format prompt with context
	 *
	 * @param string $prompt  User prompt
	 * @param array  $context Context data
	 * @return string Formatted prompt
	 */
	protected function format_prompt( $prompt, $context = array() ) {
		$formatted = $prompt;

		if ( ! empty( $context ) ) {
			$formatted .= "\n\n" . __( 'اطلاعات زمینه:', 'tabesh' ) . "\n";
			foreach ( $context as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$formatted .= sprintf( "%s: %s\n", $key, $value );
				}
			}
		}

		return $formatted;
	}
}
