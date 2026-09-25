<?php
/**
 * Buffer GraphQL API client.
 *
 * @package AISocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISP_Buffer_Client {
	const ENDPOINT = 'https://api.buffer.com';

	public static function discover_channels() {
		$account = self::request( 'query AccountOrganizations { account { organizations { id name } } }' );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		$organizations = isset( $account['data']['account']['organizations'] ) ? $account['data']['account']['organizations'] : array();
		$organization_id = (string) get_option( 'aisp_buffer_organization_id', '' );
		if ( '' === $organization_id && ! empty( $organizations[0]['id'] ) ) {
			$organization_id = (string) $organizations[0]['id'];
		}
		if ( '' === $organization_id ) {
			return new WP_Error( 'aisp_buffer_no_organization', __( 'No Buffer organization was found.', 'ai-social-publisher' ) );
		}

		$query = 'query Channels($input: ChannelsInput!) { channels(input: $input) { id name displayName descriptor service organizationId isDisconnected isLocked } }';
		$data  = self::request( $query, array( 'input' => array( 'organizationId' => $organization_id, 'filter' => array( 'product' => 'publish' ) ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$channels = isset( $data['data']['channels'] ) ? $data['data']['channels'] : array();
		return array( 'organization_id' => $organization_id, 'organizations' => $organizations, 'channels' => $channels );
	}

	/**
	 * Publishes immediately to one mapped Buffer channel.
	 *
	 * @param string  $platform Platform key.
	 * @param string  $caption  Exact edited caption.
	 * @param WP_Post $post     Article.
	 * @return array|WP_Error
	 */
	public static function publish( $platform, $caption, WP_Post $post ) {
		$platform  = sanitize_key( $platform );
		$channel_id = trim( (string) get_option( 'aisp_buffer_channel_' . $platform, '' ) );
		if ( '' === $channel_id ) {
			return new WP_Error( 'aisp_buffer_channel_missing', sprintf( __( 'Map a Buffer channel for %s before sharing.', 'ai-social-publisher' ), strtoupper( $platform ) ) );
		}

		$article = AISP_Social_Payload::article_values( $post );
		if ( '' === $article['url'] ) {
			return new WP_Error( 'aisp_invalid_article_url', __( 'The article does not have a valid HTTP or HTTPS URL for social publishing.', 'ai-social-publisher' ) );
		}
		if ( 'x' === $platform ) { $caption = AISP_Caption_Generator::prepare_x_caption( $caption, $article['url'] ); }
		$input   = array(
			'text'           => (string) $caption,
			'channelId'      => $channel_id,
			'schedulingType' => 'automatic',
			'mode'           => 'shareNow',
			'needsApproval'  => false,
			'saveToDraft'    => false,
			'aiAssisted'     => true,
			'assets'         => array(),
			'source'         => 'techgenyz-wordpress',
		);
		$link = array(
			'url'         => $article['url'],
			'title'       => $article['title'],
			'description' => $article['excerpt'],
		);
		if ( $article['featured_image'] ) {
			$link['thumbnail'] = array( 'url' => $article['featured_image'] );
		}
		if ( 'facebook' === $platform ) {
			$input['metadata'] = array( 'facebook' => array( 'type' => 'post', 'linkAttachment' => $link ) );
		} elseif ( 'linkedin' === $platform ) {
			$input['metadata'] = array( 'linkedin' => array( 'linkAttachment' => $link ) );
		} elseif ( 'x' === $platform ) {
			$input['metadata'] = array( 'twitter' => array( 'isAiGenerated' => true ) );
		}

		$query = 'mutation CreatePost($input: CreatePostInput!) { createPost(input: $input) { __typename ... on PostActionSuccess { post { id text status sharedNow shareMode externalLink sentAt } } ... on MutationError { message } } }';
		$data  = self::request( $query, array( 'input' => $input ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$payload = isset( $data['data']['createPost'] ) ? $data['data']['createPost'] : array();
		if ( empty( $payload['post']['id'] ) ) {
			$message = isset( $payload['message'] ) ? sanitize_text_field( $payload['message'] ) : __( 'Buffer did not create the post.', 'ai-social-publisher' );
			return new WP_Error( 'aisp_buffer_create_failed', $message );
		}
		return $payload['post'];
	}

	public static function test_connection() {
		return self::discover_channels();
	}

	/**
	 * Retrieves one previously submitted Buffer post.
	 *
	 * @param string $remote_id Buffer post ID.
	 * @return array|WP_Error
	 */
	public static function get_post_status( $remote_id ) {
		$results = self::get_posts_status( array( 'post' => $remote_id ) );
		return is_wp_error( $results ) ? $results : $results['post'];
	}

	/** Retrieves up to one stored post per supported platform in one GraphQL request. */
	public static function get_posts_status( array $remote_ids ) {
		$allowed = array( 'facebook', 'linkedin', 'x', 'post' ); $definitions = array(); $fields = array(); $variables = array();
		foreach ( $remote_ids as $alias => $remote_id ) {
			$alias = sanitize_key( $alias ); $remote_id = trim( sanitize_text_field( (string) $remote_id ) );
			if ( ! in_array( $alias, $allowed, true ) || '' === $remote_id || strlen( $remote_id ) > 255 || ! preg_match( '/^[A-Za-z0-9_.:\-]+$/', $remote_id ) ) { return new WP_Error( 'aisp_buffer_invalid_post_id', __( 'A stored Buffer post ID is invalid.', 'ai-social-publisher' ) ); }
			$definitions[] = '$' . $alias . ': PostInput!'; $fields[] = $alias . ': post(input: $' . $alias . ') { id status externalLink sentAt error { message supportUrl } }'; $variables[ $alias ] = array( 'id' => $remote_id );
		}
		if ( ! $fields ) { return array(); }
		$data = self::request( 'query BufferPostsStatus(' . implode( ', ', $definitions ) . ') { ' . implode( ' ', $fields ) . ' }', $variables, true );
		if ( is_wp_error( $data ) ) { return $data; }
		$errors = isset( $data['_errors'] ) ? $data['_errors'] : array(); $results = array();
		foreach ( $variables as $alias => $input ) {
			$post = isset( $data['data'][ $alias ] ) ? $data['data'][ $alias ] : array(); $alias_error = '';
			foreach ( $errors as $error ) { if ( isset( $error['path'][0] ) && $alias === $error['path'][0] ) { $alias_error = isset( $error['message'] ) ? sanitize_text_field( $error['message'] ) : __( 'Buffer could not retrieve this post.', 'ai-social-publisher' ); break; } }
			if ( $alias_error ) { $results[ $alias ] = new WP_Error( 'aisp_buffer_post_query_error', $alias_error ); }
			elseif ( empty( $post['id'] ) || ! hash_equals( (string) $input['id'], (string) $post['id'] ) ) { $results[ $alias ] = new WP_Error( 'aisp_buffer_post_not_found', __( 'Buffer could not resolve the previously submitted post. No duplicate was created.', 'ai-social-publisher' ) ); }
			else { $results[ $alias ] = $post; }
		}
		return $results;
	}

	private static function request( $query, array $variables = array(), $allow_partial = false ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'aisp_buffer_not_configured', __( 'Configure the Buffer API key first.', 'ai-social-publisher' ) );
		}
		$response = wp_safe_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( array( 'query' => $query, 'variables' => (object) $variables ) ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$error_code = 429 === $code ? 'aisp_buffer_rate_limited' : 'aisp_buffer_http_error';
			return new WP_Error( $error_code, 429 === $code ? __( 'Buffer rate limit reached. Polling has stopped; final status is not confirmed.', 'ai-social-publisher' ) : sprintf( __( 'Buffer returned HTTP %d.', 'ai-social-publisher' ), $code ), array( 'status_code' => $code, 'rate_limited' => 429 === $code, 'retry_after' => wp_remote_retrieve_header( $response, 'retry-after' ) ) );
		}
		if ( ! empty( $data['errors'] ) ) {
			if ( $allow_partial && ! empty( $data['data'] ) ) { $data['_errors'] = $data['errors']; return $data; }
			$message = isset( $data['errors'][0]['message'] ) ? sanitize_text_field( $data['errors'][0]['message'] ) : __( 'Buffer returned a GraphQL error.', 'ai-social-publisher' );
			return new WP_Error( 'aisp_buffer_graphql_error', $message );
		}
		return $data;
	}

	private static function api_key() {
		if ( defined( 'AISP_BUFFER_API_KEY' ) && AISP_BUFFER_API_KEY ) {
			return trim( (string) AISP_BUFFER_API_KEY );
		}
		if ( defined( 'TGSP_BUFFER_API_KEY' ) && TGSP_BUFFER_API_KEY ) {
			return trim( (string) TGSP_BUFFER_API_KEY );
		}
		return trim( (string) get_option( 'aisp_buffer_api_key', '' ) );
	}
}
