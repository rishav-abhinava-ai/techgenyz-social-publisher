<?php
/**
 * Server-side OpenAI caption generation.
 *
 * @package TechGenyzSocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TGSP_OpenAI_Client {
	const ENDPOINT = 'https://api.openai.com/v1/responses';

	/**
	 * Generates one editable caption per requested platform.
	 *
	 * @param WP_Post $post      Article.
	 * @param array   $platforms Platform keys.
	 * @return array|WP_Error
	 */
	public static function generate( WP_Post $post, array $platforms ) {
		$api_key = self::api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'tgsp_openai_not_configured', __( 'Configure the OpenAI API key before generating captions.', 'techgenyz-social-publisher' ) );
		}

		$platforms = array_values( array_intersect( array( 'facebook', 'linkedin', 'x' ), array_map( 'sanitize_key', $platforms ) ) );
		if ( ! $platforms ) {
			return new WP_Error( 'tgsp_no_platforms', __( 'Select at least one platform.', 'techgenyz-social-publisher' ) );
		}

		$data   = TGSP_Social_Payload::article_values( $post );
		$input  = array(
			'title'     => $data['title'],
			'excerpt'   => $data['excerpt'],
			'content'   => $data['content'],
			'url'       => $data['url'],
			'author'    => $data['author'],
			'category'  => $data['category'],
			'platforms' => $platforms,
		);
		$schema = array(
			'type'                 => 'object',
			'properties'           => array_fill_keys( $platforms, array( 'type' => 'string', 'minLength' => 1 ) ),
			'required'             => $platforms,
			'additionalProperties' => false,
		);
		$body   = array(
			'model'        => TGSP_Settings::openai_model(),
			'instructions' => implode( "\n", array(
				'Act as an experienced human social editor. Write a final ready-to-publish plain-text post for each requested platform using only the supplied WordPress article as the source of truth.',
				'Choose the most interesting or useful article-supported angle for each platform. Do not merely repeat the headline, rewrite the introduction, summarize every fact, or reuse essentially the same hook and body across Facebook, LinkedIn, and X.',
				'Never invent or embellish facts, statistics, names, dates, quotes, prices, specifications, causes, claims, conclusions, or implications. If the article does not support an interpretation, omit it or use softer factual wording.',
				'Write naturally and professionally. Avoid robotic phrasing, misleading clickbait, exaggerated claims, press-release language, corporate buzzwords, and generic openings such as “In today’s rapidly evolving world”, “The future is here”, or “This groundbreaking development” unless the article genuinely justifies them.',
				'Use intentional short paragraphs separated by single blank lines. Avoid dense text, excessive blank lines, unnecessary headings, HTML, Markdown links, and quotation marks around the entire caption. Do not add labels such as “Facebook Caption:”.',
				'Include the exact supplied article URL exactly once, near the end, without changing it or adding tracking parameters. Never include the featured-image URL in visible caption text.',
				'Use only article-relevant hashtags. Facebook and LinkedIn should normally use 2–4; X should use 0–2 and preferably no more than one. Do not repeat a generic hashtag block across platforms.',
				'Use emojis naturally and sparingly, selected for the article topic rather than from a fixed pattern. Do not automatically begin Facebook with 🚀 or LinkedIn with 💡, do not decorate every line, and do not use repeated or unrelated emojis. For sensitive stories, normally use none.',
				'Facebook: create a conversational Page post optimized for quick mobile reading, approximately 350–650 characters excluding the URL when practical. Use a strong hook, a short explanation, and a separate short paragraph with one useful detail, implication, or takeaway. Use approximately 1–3 relevant emojis total. Near the end use “🔗 Read more:” followed by the exact URL on the next line, then a separate final line containing relevant hashtags.',
				'LinkedIn: create a professional Page post approximately 500–900 characters excluding the URL when practical. Open with a technology- or professionally relevant observation, explain the development concisely, and add supported context about why it matters plus a useful takeaway. Do not force industry impact or a fake engagement question. Use approximately 1–2 relevant emojis. Near the end use “🔗 Read the full story:” followed by the exact URL on the next line, then a separate final line containing relevant hashtags.',
				'X: intentionally write a compact post for a standard account, targeting approximately 180–240 weighted characters including the URL and never exceeding 280 weighted characters. Use a concise hook, one short supporting detail, the exact URL once near the end, at most one relevant emoji, and normally zero or one hashtag. Do not rely on truncation to make the draft fit.',
				'Return only the required JSON object. Each requested property must contain its complete final plain-text caption with literal line breaks and no surrounding explanation.',
			) ),
			'input'        => wp_json_encode( $input ),
			'text'         => array(
				'format' => array(
					'type'   => 'json_schema',
					'name'   => 'social_captions',
					'strict' => true,
					'schema' => $schema,
				),
			),
		);

		$response = wp_safe_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => 45,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'tgsp_openai_rejected', __( 'OpenAI could not generate captions. Check the configured API access and try again.', 'techgenyz-social-publisher' ), array( 'status_code' => $code ) );
		}

		$text = isset( $decoded['output_text'] ) ? $decoded['output_text'] : '';
		if ( '' === $text && ! empty( $decoded['output'] ) ) {
			foreach ( $decoded['output'] as $item ) {
				if ( empty( $item['content'] ) ) {
					continue;
				}
				foreach ( $item['content'] as $content ) {
					if ( isset( $content['text'] ) ) {
						$text .= $content['text'];
					}
				}
			}
		}
		$captions = json_decode( trim( (string) $text ), true );
		if ( ! is_array( $captions ) ) {
			return new WP_Error( 'tgsp_openai_invalid_response', __( 'OpenAI returned an invalid caption response.', 'techgenyz-social-publisher' ) );
		}

		$result = array();
		foreach ( $platforms as $platform ) {
			$caption = isset( $captions[ $platform ] ) ? sanitize_textarea_field( $captions[ $platform ] ) : '';
			if ( '' === trim( $caption ) ) {
				return new WP_Error( 'tgsp_openai_missing_caption', sprintf( __( 'OpenAI did not return a %s caption.', 'techgenyz-social-publisher' ), $platform ) );
			}
			if ( 'x' === $platform ) {
				$caption = TGSP_Caption_Generator::prepare_x_caption( $caption, $data['url'] );
				if ( TGSP_Caption_Generator::weighted_length( $caption ) > 280 ) {
					return new WP_Error( 'tgsp_openai_x_too_long', __( 'OpenAI returned an X caption that exceeds the weighted character limit.', 'techgenyz-social-publisher' ) );
				}
			} elseif ( 1 !== substr_count( $caption, $data['url'] ) ) {
				return new WP_Error( 'tgsp_openai_invalid_url_count', sprintf( __( 'OpenAI must return the article URL exactly once in the %s caption.', 'techgenyz-social-publisher' ), ucfirst( $platform ) ) );
			}
			if ( ! empty( $data['featured_image'] ) && false !== strpos( $caption, $data['featured_image'] ) ) {
				return new WP_Error( 'tgsp_openai_featured_image_in_caption', sprintf( __( 'OpenAI included the featured image URL in the %s caption.', 'techgenyz-social-publisher' ), ucfirst( $platform ) ) );
			}
			$result[ $platform ] = $caption;
		}
		return $result;
	}

	private static function api_key() {
		if ( defined( 'TGSP_OPENAI_API_KEY' ) && TGSP_OPENAI_API_KEY ) {
			return trim( (string) TGSP_OPENAI_API_KEY );
		}
		return trim( (string) get_option( 'tgsp_openai_api_key', '' ) );
	}
}
