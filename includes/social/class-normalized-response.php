<?php
/**
 * Normalizes webhook responses into per-platform results.
 *
 * @package TechGenyzSocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TGSP_Normalized_Response {
	/**
	 * Normalizes decoded webhook data.
	 *
	 * @param mixed $decoded Decoded JSON response.
	 * @param array $targets Requested platform keys.
	 * @param int   $http_code HTTP status code.
	 * @return array
	 */
	public static function from_webhook( $decoded, array $targets, $http_code ) {
		$source  = is_array( $decoded ) && isset( $decoded['platforms'] ) && is_array( $decoded['platforms'] ) ? $decoded['platforms'] : array();
		$results = array();

		foreach ( $targets as $platform ) {
			if ( array_key_exists( $platform, $source ) ) {
				$results[ $platform ] = self::one( $source[ $platform ], $http_code );
			} else {
				$results[ $platform ] = array(
					'success'       => true,
					'status'        => 'accepted',
					'message'       => __( 'Accepted by the automation webhook; publication was not confirmed.', 'techgenyz-social-publisher' ),
					'remote_id'     => '',
					'response_code' => absint( $http_code ),
				);
			}
		}

		return $results;
	}

	private static function one( $value, $http_code ) {
		if ( is_bool( $value ) ) {
			$value = array( 'success' => $value );
		} elseif ( is_string( $value ) ) {
			$value = array( 'status' => $value );
		} elseif ( ! is_array( $value ) ) {
			$value = array();
		}

		$status  = sanitize_key( isset( $value['status'] ) ? $value['status'] : '' );
		$success = isset( $value['success'] ) ? rest_sanitize_boolean( $value['success'] ) : in_array( $status, array( 'published', 'success', 'sent', 'accepted' ), true );
		$status  = $status ? $status : ( $success ? 'published' : 'failed' );

		return array(
			'success'       => $success,
			'status'        => $status,
			'message'       => sanitize_text_field( isset( $value['message'] ) ? $value['message'] : ( $success ? __( 'Published successfully.', 'techgenyz-social-publisher' ) : __( 'Publishing failed.', 'techgenyz-social-publisher' ) ) ),
			'remote_id'     => sanitize_text_field( isset( $value['remote_id'] ) ? $value['remote_id'] : ( isset( $value['id'] ) ? $value['id'] : '' ) ),
			'response_code' => absint( isset( $value['response_code'] ) ? $value['response_code'] : $http_code ),
		);
	}
}
