<?php
/**
 * Webhook transport for the social publishing engine.
 *
 * @package TechGenyzSocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TGSP_Social_Webhook {
	/**
	 * Sends a payload to the configured webhook.
	 *
	 * @param array $payload Payload data.
	 * @return array|WP_Error
	 */
	public static function send( array $payload, $record_delivery = true ) {
		$url = (string) get_option( 'tgsp_webhook_url', '' );
		if ( ! $url ) {
			return new WP_Error( 'tgsp_missing_webhook', __( 'Configure the webhook URL before sharing.', 'techgenyz-social-publisher' ) );
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'headers'     => array_filter(
					array(
						'Content-Type'             => 'application/json; charset=utf-8',
						'X-Techgenyz-Webhook-Key' => (string) get_option( 'tgsp_webhook_secret', '' ),
					)
				),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record_connection( false, $response->get_error_message(), $record_delivery );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = substr( (string) wp_remote_retrieve_body( $response ), 0, 10000 );
		if ( $code < 200 || $code >= 300 ) {
			self::record_connection( false, sprintf( 'HTTP %d', $code ), $record_delivery );
			return new WP_Error( 'tgsp_webhook_rejected', __( 'The social automation webhook rejected the request.', 'techgenyz-social-publisher' ), array( 'status_code' => $code, 'response' => $body ) );
		}

		self::record_connection( true, sprintf( 'HTTP %d', $code ), $record_delivery );
		$decoded = json_decode( $body, true );
		return array( 'status_code' => $code, 'body' => $body, 'decoded' => is_array( $decoded ) ? $decoded : array() );
	}

	/**
	 * Sends a connection test event.
	 *
	 * @return array|WP_Error
	 */
	public static function test_connection() {
		return self::send( array( 'event' => 'connection_test', 'site_url' => home_url( '/' ), 'timestamp' => current_time( DATE_ATOM, true ) ), false );
	}

	private static function record_connection( $success, $message, $record_delivery ) {
		update_option( 'tgsp_webhook_connection_status', array( 'success' => (bool) $success, 'message' => sanitize_text_field( $message ), 'checked_at' => current_time( 'mysql', true ) ), false );
		if ( $success && $record_delivery ) {
			update_option( 'tgsp_webhook_last_success', current_time( 'mysql', true ), false );
		}
	}
}
