<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeMedic_Surgeon {

	private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    // Using gpt-4o-mini or gpt-3.5-turbo for speed/cost balance
    private $model = 'gpt-4o-mini'; 

	public function __construct() {
		$this->api_key = get_option( 'codemedic_openai_key' );
	}

	/**
	 * Send an error report to AI and get a fix.
	 */
	public function diagnose( $error_data ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'missing_key', 'OpenAI API Key is missing.' );
		}

		$prompt = $this->construct_prompt( $error_data );

		$body = array(
			'model'       => $this->model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $this->get_system_instruction()
				),
				array(
					'role'    => 'user',
					'content' => $prompt
				)
			),
			'temperature' => 0.2, // Low temperature for code accuracy
		);

		$response = wp_remote_post( $this->api_url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => json_encode( $body ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body_content = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_content, true );

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'api_error', $data['error']['message'] );
		}

        if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'invalid_response', 'Invalid response from AI' );
        }

		return $this->parse_ai_response( $data['choices'][0]['message']['content'] );
	}

	private function get_system_instruction() {
		return "You are a Senior WordPress PHP Architect. You analyze fatal errors and provide safe, syntactically correct fixes.
        
        RULES:
        1. Output MUST be valid JSON.
        2. Do not include markdown formatting (```json) in the response, just raw JSON.
        3. The JSON must have these keys: 'analysis' (string, explanation), 'fixed_code' (string, the corrected PHP code block only), 'safety_score' (int, 0-100).
        4. Do not remove security checks (nonces, permissions).
        5. Do not introduce arbitrary execution vulnerabilities.
        6. Only fix the specific function or logic causing the error.";
	}

	private function construct_prompt( $error_data ) {
		return json_encode( array(
			'error_message' => $error_data['message'],
			'file'          => $error_data['file'],
			'line'          => $error_data['line'],
			'code_context'  => $error_data['context']
		) );
	}

	private function parse_ai_response( $content ) {
        // Strip markdown if AI ignored instructions
        $content = str_replace( array('```json', '```'), '', $content );
		$json = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Failed to parse AI response: ' . $content );
		}

		return $json;
	}
}
