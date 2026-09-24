<?php
/**
 * Social publishing payload builder.
 *
 * @package TechGenyzSocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TGSP_Social_Payload {
	/**
	 * Builds the webhook payload for a post and selected platforms.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $platforms Enabled platform keys.
	 * @param string  $attempt_id Unique delivery attempt ID.
	 * @return array
	 */
	public static function build( WP_Post $post, array $platforms, $attempt_id ) {
		$values = self::article_values( $post );

		$enabled = array_fill_keys( array( 'facebook', 'linkedin', 'x' ), false );
		foreach ( $platforms as $platform ) {
			if ( array_key_exists( $platform, $enabled ) ) {
				$enabled[ $platform ] = true;
			}
		}

		return array(
			'event'          => 'social_publish',
			'attempt_id'     => sanitize_key( $attempt_id ),
			'post_id'        => (int) $post->ID,
			'title'          => $values['title'],
			'excerpt'        => $values['excerpt'],
			'url'            => $values['url'],
			'featured_image' => $values['featured_image'],
			'author'         => $values['author'],
			'category'       => $values['category'],
			'published_date' => get_post_time( DATE_ATOM, true, $post ),
			'site_name'      => $values['site_name'],
			'captions'       => array(
				'facebook' => TGSP_Caption_Generator::generate( 'facebook', $values ),
				'linkedin' => TGSP_Caption_Generator::generate( 'linkedin', $values ),
				'x'        => TGSP_Caption_Generator::generate( 'x', $values ),
			),
			'platforms'      => $enabled,
		);
	}

	/**
	 * Extracts reusable, sanitized article values.
	 *
	 * @param WP_Post $post Article.
	 * @return array
	 */
	public static function article_values( WP_Post $post ) {
		$categories     = get_the_category( $post->ID );
		$category       = $categories ? $categories[0]->name : '';
		$featured_image = self::web_url( get_the_post_thumbnail_url( $post->ID, 'full' ) );
		$excerpt        = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '…' );
		return array(
			'title'          => get_the_title( $post ),
			'excerpt'        => $excerpt,
			'content'        => wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 1200, '...' ),
			'url'            => self::web_url( get_permalink( $post ) ),
			'author'         => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'category'       => $category,
			'featured_image' => $featured_image ? $featured_image : '',
			'site_name'      => get_bloginfo( 'name' ),
		);

	}

	/**
	 * Accepts only structurally valid HTTP(S) URLs for outbound social payloads.
	 * Public reachability remains an external crawler/platform concern.
	 *
	 * @param string|false $url Candidate URL.
	 * @return string
	 */
	private static function web_url( $url ) {
		$url = esc_url_raw( html_entity_decode( trim( (string) $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $url ) { return ''; }
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) { return ''; }
		return $url;
	}
}
