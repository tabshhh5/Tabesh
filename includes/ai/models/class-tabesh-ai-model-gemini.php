<?php
/**
 * Gemini AI Model
 *
 * Google Gemini model implementation
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
 * Class Tabesh_AI_Model_Gemini
 */
class Tabesh_AI_Model_Gemini extends Tabesh_AI_Model_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->model_id      = 'gemini';
		$this->model_name    = 'Google Gemini';
		$this->api_endpoint  = 'https://generativelanguage.googleapis.com/v1/models/';
		$this->max_tokens    = 8192;
		$this->config_fields = array(
			'api_key' => array(
				'label'       => __( 'کلید API / API Key', 'tabesh' ),
				'type'        => 'text',
				'required'    => true,
				'description' => __( 'کلید API از Google AI Studio دریافت کنید', 'tabesh' ),
			),
			'model'   => array(
				'label'       => __( 'مدل / Model', 'tabesh' ),
				'type'        => 'select',
				'required'    => true,
				'default'     => 'gemini-pro',
				'options'     => array(
					'gemini-pro'        => 'Gemini Pro',
					'gemini-pro-vision' => 'Gemini Pro Vision',
				),
				'description' => __( 'انتخاب مدل Gemini', 'tabesh' ),
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
				'error'   => __( 'مدل Gemini پیکربندی نشده است', 'tabesh' ),
			);
		}

		$config           = $this->get_configuration();
		$model            = ! empty( $config['model'] ) ? $config['model'] : 'gemini-pro';
		$formatted_prompt = $this->format_prompt( $prompt, $context );

		// Build endpoint with model and API key
		$endpoint = $this->api_endpoint . $model . ':generateContent?key=' . $config['api_key'];

		$body = array(
			'contents'         => array(
				array(
					'parts' => array(
						array(
							'text' => $formatted_prompt,
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature'     => isset( $options['temperature'] ) ? $options['temperature'] : 0.7,
				'maxOutputTokens' => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 1000,
			),
		);

		// Add system instruction if provided (Gemini supports this differently)
		if ( ! empty( $options['system_prompt'] ) ) {
			$body['systemInstruction'] = array(
				'parts' => array(
					array(
						'text' => $options['system_prompt'],
					),
				),
			);
		}

		$response = $this->make_api_request( $endpoint, $body, array() );

		if ( ! $response['success'] ) {
			return $response;
		}

		// Extract the generated text from response
		if ( isset( $response['data']['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return array(
				'success' => true,
				'data'    => array(
					'text'   => $response['data']['candidates'][0]['content']['parts'][0]['text'],
					'model'  => $model,
					'tokens' => 0, // Gemini doesn't always return token count
				),
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'پاسخ نامعتبر از سرور Gemini', 'tabesh' ),
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
		$model    = ! empty( $credentials['model'] ) ? $credentials['model'] : 'gemini-pro';
		$endpoint = $this->api_endpoint . $model . ':generateContent?key=' . $credentials['api_key'];

		$body = array(
			'contents'         => array(
				array(
					'parts' => array(
						array(
							'text' => 'Test',
						),
					),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens' => 5,
			),
		);

		$response = $this->make_api_request( $endpoint, $body, array() );

		return $response['success'];
	}
}
