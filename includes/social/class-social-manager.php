<?php
/**
 * Coordinates social publishing attempts.
 *
 * @package TechGenyzSocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TGSP_Social_Manager {
	const STATUS_META = '_social_publisher_platform_statuses';

	/**
	 * Publishes a post through the configured automation webhook.
	 *
	 * @param WP_Post $post Post object.
	 * @param bool    $force Whether successful platforms should be sent again.
	 * @param array   $requested_platforms Platforms selected in the popup.
	 * @param array   $captions Exact edited captions keyed by platform.
	 * @return array|WP_Error
	 */
	public static function publish( WP_Post $post, $force = false, array $requested_platforms = array(), array $captions = array() ) {
		$enabled  = self::enabled_platforms();
		if ( $requested_platforms ) {
			$enabled = array_values( array_intersect( $enabled, array_map( 'sanitize_key', $requested_platforms ) ) );
		}
		$existing = self::get_statuses( $post->ID );
		$targets  = array();
		if ( ! $enabled ) {
			return new WP_Error( 'tgsp_no_platforms', __( 'Enable at least one social platform in Social Publisher settings.', 'techgenyz-social-publisher' ), array( 'status' => 400 ) );
		}

		foreach ( $enabled as $platform ) {
			$status = isset( $existing[ $platform ]['status'] ) ? $existing[ $platform ]['status'] : '';
			if ( 'buffer' === get_option( 'tgsp_delivery_method', 'buffer' ) && 'processing' === $status ) {
				$reconciled = self::reconcile( $post->ID, $platform, isset( $existing[ $platform ]['remote_id'] ) ? $existing[ $platform ]['remote_id'] : '' );
				if ( is_wp_error( $reconciled ) ) {
					$existing[ $platform ]['message'] = sprintf( __( 'Publication is still marked processing but cannot be reconciled: %s No duplicate was created.', 'techgenyz-social-publisher' ), $reconciled->get_error_message() );
					$existing[ $platform ]['reconciliation_error'] = $reconciled->get_error_code(); update_post_meta( $post->ID, self::STATUS_META, $existing );
				}
				$existing = self::get_statuses( $post->ID );
				$status   = isset( $existing[ $platform ]['status'] ) ? $existing[ $platform ]['status'] : '';
				if ( in_array( $status, array( 'processing', 'published' ), true ) ) {
					continue;
				}
			}
			if ( $force || ! in_array( $status, array( 'published', 'success', 'sent', 'accepted', 'processing' ), true ) ) {
				$targets[] = $platform;
			}
		}

		if ( ! $targets ) {
			$processing = array_intersect_key( $existing, array_flip( $enabled ) );
			$processing = array_filter( $processing, function ( $item ) { return isset( $item['status'] ) && 'processing' === $item['status']; } );
			if ( $processing ) {
				return array( 'attempt_id' => '', 'results' => $processing, 'statuses' => $existing );
			}
			return new WP_Error( 'tgsp_already_sent', __( 'All enabled platforms have already been shared.', 'techgenyz-social-publisher' ) );
		}

		$attempt_id = strtolower( wp_generate_uuid4() );
		if ( 'buffer' === get_option( 'tgsp_delivery_method', 'buffer' ) ) {
			return self::publish_to_buffer( $post, $targets, $captions, $existing, $attempt_id );
		}
		$payload    = TGSP_Social_Payload::build( $post, $targets, $attempt_id );
		if ( $captions ) {
			foreach ( $targets as $platform ) {
				if ( isset( $captions[ $platform ] ) ) {
					$caption = sanitize_textarea_field( $captions[ $platform ] );
					if ( 'x' === $platform ) {
						$caption = TGSP_Caption_Generator::prepare_x_caption( $caption, $payload['url'] );
						if ( TGSP_Caption_Generator::weighted_length( $caption ) > 280 ) {
							return new WP_Error( 'tgsp_x_caption_too_long', __( 'The final X caption exceeds the weighted character limit.', 'techgenyz-social-publisher' ) );
						}
					}
					$payload['captions'][ $platform ] = $caption;
				}
			}
		}
		$transport  = TGSP_Social_Webhook::send( $payload );

		if ( is_wp_error( $transport ) ) {
			foreach ( $targets as $platform ) {
				$result = array( 'success' => false, 'status' => 'failed', 'message' => $transport->get_error_message(), 'remote_id' => '', 'response_code' => absint( self::error_http_code( $transport ) ), 'attempt_id' => $attempt_id, 'timestamp' => current_time( 'mysql', true ) );
				$existing[ $platform ] = $result;
				$log_details = $result; $log_details['submitted_text'] = isset( $payload['captions'][ $platform ] ) ? $payload['captions'][ $platform ] : '';
				TGSP_Logger::add_platform( $post->ID, $platform, 'failed', array( 'code' => $transport->get_error_code(), 'message' => $transport->get_error_message(), 'data' => $transport->get_error_data() ), $log_details, true );
			}
			update_post_meta( $post->ID, self::STATUS_META, $existing );
			return $transport;
		}

		$results = TGSP_Normalized_Response::from_webhook( $transport['decoded'], $targets, $transport['status_code'] );
		foreach ( $results as $platform => $result ) {
			$result['attempt_id'] = $attempt_id;
			$result['timestamp']  = current_time( 'mysql', true );
			$existing[ $platform ] = $result;
			$log_details = $result; $log_details['submitted_text'] = isset( $payload['captions'][ $platform ] ) ? $payload['captions'][ $platform ] : '';
			TGSP_Logger::add_platform( $post->ID, $platform, $result['status'], $result, $log_details, true );
		}
		update_post_meta( $post->ID, self::STATUS_META, $existing );

		return array( 'attempt_id' => $attempt_id, 'results' => $results, 'statuses' => $existing );
	}

	private static function publish_to_buffer( WP_Post $post, array $targets, array $captions, array $existing, $attempt_id ) {
		$results = array();
		$values  = TGSP_Social_Payload::article_values( $post );
		foreach ( $targets as $platform ) {
			$caption = isset( $captions[ $platform ] ) ? sanitize_textarea_field( $captions[ $platform ] ) : TGSP_Caption_Generator::generate( $platform, $values );
			$submitted_text = 'x' === $platform ? TGSP_Caption_Generator::prepare_x_caption( $caption, $values['url'] ) : $caption;
			if ( '' === trim( $caption ) ) {
				$result = array( 'success' => false, 'status' => 'failed', 'message' => __( 'The final caption cannot be empty.', 'techgenyz-social-publisher' ), 'remote_id' => '', 'response_code' => 0 );
			} elseif ( 'x' === $platform && TGSP_Caption_Generator::weighted_length( $submitted_text ) > 280 ) {
				$result = array( 'success' => false, 'status' => 'failed', 'message' => __( 'The final X caption exceeds the weighted character limit.', 'techgenyz-social-publisher' ), 'remote_id' => '', 'response_code' => 0 );
			} else {
				$published = TGSP_Buffer_Client::publish( $platform, $submitted_text, $post );
				if ( is_wp_error( $published ) ) {
					$result = array( 'success' => false, 'status' => 'failed', 'message' => $published->get_error_message(), 'remote_id' => '', 'response_code' => 0 );
				} else {
					$status  = isset( $published['status'] ) ? sanitize_key( $published['status'] ) : '';
					$success = in_array( $status, array( 'sent', 'published' ), true );
					$result  = array(
						'success'       => $success,
						'status'        => $success ? 'published' : 'processing',
						'message'       => $success ? __( 'Published through Buffer.', 'techgenyz-social-publisher' ) : __( 'Buffer accepted shareNow and is publishing the post.', 'techgenyz-social-publisher' ),
						'remote_id'     => sanitize_text_field( $published['id'] ),
						'response_code' => 200,
						'external_url'  => isset( $published['externalLink'] ) ? esc_url_raw( $published['externalLink'] ) : '',
						'share_mode'    => isset( $published['shareMode'] ) ? sanitize_key( $published['shareMode'] ) : '',
					);
				}
			}
			$result['attempt_id'] = $attempt_id;
			$result['timestamp']  = current_time( 'mysql', true );
			$results[ $platform ] = $result;
			$existing[ $platform ] = $result;
			$log_details = $result; $log_details['submitted_text'] = $submitted_text;
			TGSP_Logger::add_platform( $post->ID, $platform, $result['status'], $result, $log_details, true );
		}
		update_post_meta( $post->ID, self::STATUS_META, $existing );
		return array( 'attempt_id' => $attempt_id, 'results' => $results, 'statuses' => $existing );
	}

	public static function enabled_platforms() {
		$platforms = array();
		foreach ( array( 'facebook', 'linkedin', 'x' ) as $platform ) {
			if ( (bool) get_option( 'tgsp_enable_' . $platform, true ) ) {
				$platforms[] = $platform;
			}
		}
		return $platforms;
	}

	public static function get_statuses( $post_id ) {
		$value = get_post_meta( $post_id, self::STATUS_META, true );
		return is_array( $value ) ? $value : array();
	}

	/** Reconciles one stored processing attempt without creating a new post. */
	public static function reconcile( $post_id, $platform, $remote_id ) {
		$batch = self::reconcile_many( $post_id, array( sanitize_key( $platform ) => $remote_id ) );
		return is_wp_error( $batch ) ? $batch : $batch['results'][ sanitize_key( $platform ) ];
	}

	/** Reconciles validated processing attempts in one Buffer GraphQL HTTP request. */
	public static function reconcile_many( $post_id, array $attempts ) {
		$post_id = absint( $post_id ); $statuses = self::get_statuses( $post_id ); $validated = array();
		foreach ( $attempts as $platform => $remote_id ) {
			$platform = sanitize_key( $platform ); $remote_id = trim( sanitize_text_field( (string) $remote_id ) ); $current = isset( $statuses[ $platform ] ) && is_array( $statuses[ $platform ] ) ? $statuses[ $platform ] : array();
			if ( ! in_array( $platform, array( 'facebook', 'linkedin', 'x' ), true ) || 'processing' !== ( isset( $current['status'] ) ? $current['status'] : '' ) || empty( $current['remote_id'] ) || ! hash_equals( (string) $current['remote_id'], $remote_id ) ) { return new WP_Error( 'tgsp_reconcile_mismatch', __( 'A Buffer post does not match its active publishing attempt.', 'techgenyz-social-publisher' ) ); }
			$validated[ $platform ] = $remote_id;
		}
		if ( ! $validated ) { return new WP_Error( 'tgsp_no_reconciliation_targets', __( 'No processing platforms were supplied.', 'techgenyz-social-publisher' ) ); }
		$buffer_results = TGSP_Buffer_Client::get_posts_status( $validated ); $rate_limited = is_wp_error( $buffer_results ) && 'tgsp_buffer_rate_limited' === $buffer_results->get_error_code(); $results = array();
		foreach ( $validated as $platform => $remote_id ) {
			$buffer = is_wp_error( $buffer_results ) ? $buffer_results : $buffer_results[ $platform ]; $current = $statuses[ $platform ];
			if ( is_wp_error( $buffer ) ) {
				$current['message'] = sprintf( __( 'Publication accepted by Buffer; final status not confirmed: %s', 'techgenyz-social-publisher' ), $buffer->get_error_message() ); $current['reconciliation_error'] = $buffer->get_error_code(); $current['reconciled_at'] = current_time( 'mysql', true );
			} else {
		$buffer_status = sanitize_key( isset( $buffer['status'] ) ? $buffer['status'] : '' );
		if ( 'sent' === $buffer_status ) {
			$current['success'] = true; $current['status'] = 'published'; $current['message'] = __( 'Published through Buffer.', 'techgenyz-social-publisher' );
		} elseif ( in_array( $buffer_status, array( 'error', 'draft', 'needs_approval' ), true ) ) {
			$current['success'] = false; $current['status'] = 'failed'; $current['message'] = ! empty( $buffer['error']['message'] ) ? sanitize_text_field( $buffer['error']['message'] ) : sprintf( __( 'Buffer finished with status: %s.', 'techgenyz-social-publisher' ), $buffer_status );
		} else {
			$current['success'] = false; $current['status'] = 'processing'; $current['message'] = __( 'Buffer is still processing this publication.', 'techgenyz-social-publisher' );
		}
				$current['buffer_status'] = $buffer_status; $current['reconciled_at'] = current_time( 'mysql', true ); unset( $current['reconciliation_error'] ); if ( ! empty( $buffer['externalLink'] ) ) { $current['external_url'] = esc_url_raw( $buffer['externalLink'] ); }
			}
			$statuses[ $platform ] = $current; $results[ $platform ] = $current; TGSP_Logger::update_platform_attempt( $post_id, $platform, isset( $current['attempt_id'] ) ? $current['attempt_id'] : '', $remote_id, $current['status'], $current, $current );
		}
		update_post_meta( $post_id, self::STATUS_META, $statuses );
		return array( 'results' => $results, 'rate_limited' => $rate_limited );
	}

	public static function all_complete( $post_id ) {
		$statuses = self::get_statuses( $post_id );
		foreach ( self::enabled_platforms() as $platform ) {
			$status = isset( $statuses[ $platform ]['status'] ) ? $statuses[ $platform ]['status'] : '';
			if ( ! in_array( $status, array( 'published', 'success', 'sent', 'accepted' ), true ) ) {
				return false;
			}
		}
		return ! empty( self::enabled_platforms() );
	}

	private static function error_http_code( WP_Error $error ) {
		$data = $error->get_error_data();
		return is_array( $data ) && isset( $data['status_code'] ) ? $data['status_code'] : 0;
	}
}
