<?php
/**
 * Request authorization helpers.
 *
 * @package AISocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISP_Security {
	const NONCE_ACTION = 'aisp_share_post';

	/**
	 * Confirms that the current user can share a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return true|WP_Error
	 */
	public static function authorize( $post_id ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'aisp_not_logged_in', __( 'You must be logged in to share posts.', 'ai-social-publisher' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'aisp_forbidden', __( 'You are not allowed to share this post.', 'ai-social-publisher' ), array( 'status' => 403 ) );
		}

		return true;
	}
}

