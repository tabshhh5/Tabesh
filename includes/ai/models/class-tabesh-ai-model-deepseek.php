<?php
/**
 * DeepSeek AI Model
 *
 * DeepSeek model implementation
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
 * Class Tabesh_AI_Model_DeepSeek
 */
class Tabesh_AI_Model_DeepSeek extends Tabesh_AI_Model_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->model_id      = 'deepseek';
		$this->model_name    = 'DeepSeek';
		$this->api_endpoint  = 'https://api.deepseek.com/v1/chat/completions';
		$this->max_tokens    = 4096;
		$this->config_fields = array(
			'api_key' => array(
				'label'       => __( 'کلید API / API Key', 'tabesh' ),
				'type'        => 'text',
				'required'    => true,
				'description' => __( 'کلید API از DeepSeek دریافت کنید', 'tabesh' ),
			),
			'model'   => array(
				'label'       => __( 'مدل / Model', 'tabesh' ),
				'type'        => 'select',
				'required'    => true,
				'default'     => 'deepseek-chat',
				'options'     => array(
					'deepseek-chat'  => 'DeepSeek Chat',
					'deepseek-coder' => 'DeepSeek Coder',
				),
				'description' => __( 'انتخاب مدل DeepSeek', 'tabesh' ),
			),
		);
	}

	/**
	 * Generate AI response
	 *
	 * @param string $prompt  The prompt to send
	 * @param array  $context Context data
	 * @param array  $options Model-specific options
	 * @return array Response array
	 */
	public function generate( $prompt, $context = array(), $options = array() ) {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'error'   => __( 'مدل DeepSeek پیکربندی نشده است', 'tabesh' ),
			);
		}

		$config           = $this->get_configuration();
		$formatted_prompt = $this->format_prompt( $prompt, $context );

		$body = array(
			'model'       => ! empty( $config['model'] ) ? $config['model'] : 'deepseek-chat',
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $formatted_prompt,
				),
			),
			'temperature' => isset( $options['temperature'] ) ? $options['temperature'] : 0.7,
			'max_tokens'  => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 1000,
		);

		// Add system message if provided
		if ( ! empty( $options['system_prompt'] ) ) {
			array_unshift(
				$body['messages'],
				array(
					'role'    => 'system',
					'content' => $options['system_prompt'],
				)
			);
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $config['api_key'],
		);

		$response = $this->make_api_request( $this->api_endpoint, $body, $headers );

		if ( ! $response['success'] ) {
			return $response;
		}

		// Extract the generated text from response
		if ( isset( $response['data']['choices'][0]['message']['content'] ) ) {
			return array(
				'success' => true,
				'data'    => array(
					'text'   => $response['data']['choices'][0]['message']['content'],
					'model'  => $config['model'],
					'tokens' => isset( $response['data']['usage']['total_tokens'] ) ? $response['data']['usage']['total_tokens'] : 0,
				),
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'پاسخ نامعتبر از سرور DeepSeek', 'tabesh' ),
		);
	}

	/**
	 * Validate credentials
	 *
	 * @param array $credentials Credentials to validate
	 * @return bool
	 */
	public function validate_credentials( $credentials ) {
		if ( empty( $credentials['api_key'] ) ) {
			return false;
		}

		// Make a simple test request
		$headers = array(
			'Authorization' => 'Bearer ' . $credentials['api_key'],
		);

		$body = array(
			'model'      => 'deepseek-chat',
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Test',
				),
			),
			'max_tokens' => 5,
		);

		$response = $this->make_api_request( $this->api_endpoint, $body, $headers );

		return $response['success'];
	}
}
