<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see https://github.com/OriginalityAI/chrome_extension/blob/master/popup.js
 */
class OriginalityAIAPI {

	/**
	 * The endpoint used for login in the Originality.ai API.
	 *
	 * @var string LOGIN_ENDPOINT
	 */
	const LOGIN_ENDPOINT = 'https://core.originality.ai/api/v1/wordpress/login';

	/**
	 * AI Scan method
	 */
	const AI_SCAN_ENDPOINT = 'https://api.originality.ai/api/wordpress/scan/ai';

	/**
	 * Sign-up API URL
	 */
	const SIGNUP_ENDPOINT = 'https://core.originality.ai/api/v1/extension/signup';

	/**
	 * The URL to check the balance.
	 *
	 * @var string CHECK_BALANCE_ENDPOINT
	 * @since 1.0.0
	 */
	const CHECK_BALANCE_ENDPOINT = 'https://api.originality.ai/api/v1/account/credits/balance';
	/**
	 * Error message displayed when user tries to sign in or use certain features without a subscription
	 *
	 * @var string ERROR_MESSAGE
	 *
	 * @link https://app.originality.ai/credits/subscription Link to subscribe to the plan
	 */
	const ERROR_MESSAGE = 'Please subscribe to our plan <a href=\'https://app.originality.ai/credits/subscription\' target=\'_blank\'>here</a> to signin and use all features of the extension. The playback writing feature does not need an account is 100% free!';

	/**
	 * Models list
	 *
	 * @see https://docs.originality.ai/api-v1-0-reference/scan/ai-scan
	 */
	const AI_SCAN_MODELS = [
		'1' => 'Lite',
		'2' => '3.0 Turbo Model',
		'3' => 'Multi Language',
	];

	/**
	 * Default AI Scan model ID
	 *
	 * @see https://docs.originality.ai/api-v1-0-reference/scan/ai-scan
	 */
	const ORIGINALITYAI_AI_SCAN_ID_DEFAULT = 1;

	/**
	 * Authorize a user with their email and password.
	 *
	 * @param string $email    The user's email address.
	 * @param string $password The user's password.
	 *
	 * @return array|void Returns an array with the authorization result and data.
	 *                   If the authorization is successful, the array will include
	 *                   the user's API key, name, and email address. If the
	 *                   authorization fails, the array will include an error message.
	 */
	public static function authorize( $email, $password ) {
		$api_endpoint = self::LOGIN_ENDPOINT;
		$body         = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( array( 'email' => $email, 'password' => $password ) ),
		);

		$response = wp_remote_post( $api_endpoint, $body );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			return array(
				'result' => false,
				'data'   => $error_message
			);
		} else {
			$result = json_decode( $response['body'], true );
			if ( 422 != wp_remote_retrieve_response_code( $response ) ) {
				if ( ( isset( $result['grandfathered'] ) || isset( $result['subscribed'] ) ) && isset( $result['api_key'] ) ) {
					return [
						'result' => true,
						'data'   => array(
							'apikey' => $result['api_key'],
							'name'   => $result['user']['name'],
							'email'  => $result['user']['email']
						)
					];
				}
			} else {
				return array(
					'result' => false,
					'data'   => self::ERROR_MESSAGE
				);
			}
		}
	}

	/**
	 * Scan the provided AI content using the Originality.ai API.
	 *
	 * @see https://docs.originality.ai/api-v1-0-reference/scan/ai-scan
	 *
	 * @param string      $api_key          The API key for authentication.
	 * @param string      $content          The AI content to be scanned.
	 * @param string|null $title            The title of the AI content (optional).
	 * @param string|null $ai_model_version The version of the AI model to be used (optional).
	 * @param string|null $store_scan       Whether to store the scan results (optional).
	 *
	 * @return array|mixed                 The scan result array or an error message array.
	 */
	public static function scan_ai_content( $api_key, $content, $title = null, $ai_model_version = 1, $store_scan = false ) {
		$api_endpoint = self::AI_SCAN_ENDPOINT;

		// TODO: add support for multilingual content via $ai_model_version where this method called

		$headers = array(
			'X-OAI-API-KEY'        => $api_key,
			'OAI-CHROME-EXTENSION' => 1,
			'Accept'               => 'application/json',
			'Content-Type'         => 'application/json',
		);

		$body = array( 'content' => $content );
		if ( isset( $title ) ) {
			$body['title'] = $title;
		}
		if ( isset( $ai_model_version ) ) {
			$body['aiModelVersion'] = $ai_model_version;
		}
		if ( isset( $store_scan ) ) {
			$body['storeScan'] = $store_scan;
		}

		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_post( $api_endpoint, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			return array(
				'result' => false,
				'data'   => "Something went wrong: {$error_message}"
			);
		} else {
			$result = json_decode( $response['body'], true );
			if ( $response['response']['code'] == 200 ) {
				return array(
					'result' => true,
					'data'   => $result
				);
			} else {
				return array(
					'result' => false,
					'data'   => $result['error']
				);
			}
		}
	}

}