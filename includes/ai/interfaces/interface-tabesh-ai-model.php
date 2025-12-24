<?php
/**
 * AI Model Interface
 *
 * Defines the contract that all AI model providers must implement.
 * This interface ensures consistency across different AI providers (Grok, Gemini, GPT, DeepSeek, etc.)
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
 * Interface Tabesh_AI_Model_Interface
 *
 * Contract for all AI model providers
 */
interface Tabesh_AI_Model_Interface {

	/**
	 * Get the unique identifier for this model
	 *
	 * @return string Model identifier (e.g., 'grok', 'gemini', 'gpt', 'deepseek')
	 */
	public function get_model_id();

	/**
	 * Get the human-readable name of the model
	 *
	 * @return string Model name
	 */
	public function get_model_name();

	/**
	 * Check if the model is properly configured and ready to use
	 *
	 * @return bool True if configured, false otherwise
	 */
	public function is_configured();

	/**
	 * Send a prompt to the AI model and get a response
	 *
	 * @param string $prompt The prompt/message to send to the AI
	 * @param array  $context Optional context data for the AI
	 * @param array  $options Optional model-specific options
	 * @return array Response array with 'success', 'data', and optional 'error' keys
	 */
	public function generate( $prompt, $context = array(), $options = array() );

	/**
	 * Validate the API credentials for this model
	 *
	 * @param array $credentials The credentials to validate
	 * @return bool True if valid, false otherwise
	 */
	public function validate_credentials( $credentials );

	/**
	 * Get the configuration fields required for this model
	 *
	 * @return array Array of configuration fields
	 */
	public function get_config_fields();

	/**
	 * Get the maximum token limit for this model
	 *
	 * @return int Maximum tokens
	 */
	public function get_max_tokens();
}
