<?php
/**
 * Payload generation and webhook delivery.
 *
 * @package TechGenyzSocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TGSP_Webhook {
	/**
	 * Sends a post to the configured webhook.
	 *
	 * @param WP_Post $post Post object.
	 * @return array|WP_Error
	 */
	public static function send( WP_Post $post ) {
		$webhook_url = (string) get_option( 'tgsp_webhook_url', '' );
		if ( ! $webhook_url ) {
			return new WP_Error( 'tgsp_missing_webhook', __( 'Configure the webhook URL before sharing.', 'techgenyz-social-publisher' ) );
		}

		$payload  = self::build_payload( $post );
		$response = wp_safe_remote_post(
			$webhook_url,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array_filter( array(
					'Content-Type' => 'application/json; charset=utf-8',
					'X-Techgenyz-Webhook-Key' => (string) get_option( 'tgsp_webhook_secret', '' ),
				) ),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = substr( (string) wp_remote_retrieve_body( $response ), 0, 2000 );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'tgsp_webhook_rejected',
				__( 'The social publishing service rejected the request.', 'techgenyz-social-publisher' ),
				array(
					'status_code' => $code,
					'response'    => $body,
				)
			);
		}

		return array(
			'status_code' => $code,
			'response'    => $body,
		);
	}

	/**
	 * Builds the documented payload.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public static function build_payload( WP_Post $post ) {
		$categories     = get_the_category( $post->ID );
		$category       = $categories ? $categories[0]->name : '';
		$featured_image = get_the_post_thumbnail_url( $post->ID, 'full' );
		$excerpt        = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '…' );
		$url            = get_permalink( $post );
		$values         = array(
			'{title}'          => get_the_title( $post ),
			'{excerpt}'        => $excerpt,
			'{url}'            => $url,
			'{author}'         => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'{category}'       => $category,
			'{featured_image}' => $featured_image ? $featured_image : '',
			'{site_name}'      => get_bloginfo( 'name' ),
		);
		$format         = (string) get_option( 'tgsp_message_format', "{title}\n\nRead more:\n{url}" );

		return array(
			'title'          => $values['{title}'],
			'excerpt'        => $values['{excerpt}'],
			'url'            => $values['{url}'],
			'featured_image' => $values['{featured_image}'],
			'author'         => $values['{author}'],
			'category'       => $values['{category}'],
			'published_date' => get_post_time( DATE_ATOM, true, $post ),
			'site_name'      => $values['{site_name}'],
			'post_id'        => (int) $post->ID,
			'facebook_caption' => strtr( $format, $values ),
			'linkedin_caption' => strtr( $format, $values ),
			'x_caption'        => strtr( $format, $values ),
			'hashtags'         => array(),
			'social_message'   => strtr( $format, $values ),
		);
	}
}
