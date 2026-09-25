<?php
/** Admin sharing UI and REST actions. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AISP_Metabox {
	const SAVED_CAPTION_META = array(
		'facebook' => '_aisp_saved_caption_facebook',
		'linkedin' => '_aisp_saved_caption_linkedin',
		'x'        => '_aisp_saved_caption_x',
	);
	public static function init() {
		add_action( 'add_meta_boxes_post', array( __CLASS__, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_footer-edit.php', array( __CLASS__, 'modal' ) );
		add_action( 'admin_footer-post.php', array( __CLASS__, 'modal' ) );
		add_filter( 'manage_post_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}
	public static function add() { add_meta_box( 'aisp-social-publisher', __( 'AI Social Publisher', 'ai-social-publisher' ), array( __CLASS__, 'render' ), 'post', 'side', 'high', array( '__block_editor_compatible_meta_box' => true ) ); }
	public static function render( WP_Post $post ) {
		$summary = self::status_summary( $post->ID );
		echo '<div id="aisp-publisher" data-post-id="' . esc_attr( $post->ID ) . '"><p>' . esc_html__( 'Generate, review and immediately publish social captions through Buffer.', 'ai-social-publisher' ) . '</p>';
		self::render_results( AISP_Social_Manager::get_statuses( $post->ID ) );
		echo self::share_button( $post, $summary, 'button button-primary button-large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all dynamic values.
		echo '</div>';
	}
	public static function columns( $columns ) {
		$result = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) { $result['aisp_social'] = __( 'Social', 'ai-social-publisher' ); }
			$result[ $key ] = $label;
		}
		if ( ! isset( $result['aisp_social'] ) ) { $result['aisp_social'] = __( 'Social', 'ai-social-publisher' ); }
		return $result;
	}
	public static function column( $column, $post_id ) {
		if ( 'aisp_social' !== $column ) { return; }
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || ! current_user_can( 'edit_post', $post_id ) ) { echo '&mdash;'; return; }
		$summary = self::status_summary( $post_id );
		printf( '<span class="aisp-column-status aisp-column-status--%1$s">%2$s</span>', esc_attr( $summary['state'] ), esc_html( $summary['status_label'] ) );
		echo self::share_button( $post, $summary, 'button button-small' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all dynamic values.
	}
	public static function row_action( $actions, WP_Post $post ) {
		if ( 'post' === $post->post_type && 'publish' === $post->post_status && current_user_can( 'edit_post', $post->ID ) ) {
			$summary = self::status_summary( $post->ID );
			$actions['aisp_share'] = sprintf( '<button type="button" class="button-link aisp-open-modal" data-post-id="%1$d" data-post-title="%2$s" data-post-url="%3$s" data-force="%4$d"><span class="dashicons dashicons-share" aria-hidden="true"></span> %5$s</button>', (int) $post->ID, esc_attr( get_the_title( $post ) ), esc_url( get_permalink( $post ) ), $summary['force'] ? 1 : 0, esc_html( $summary['action_label'] ) );
		}
		return $actions;
	}
	private static function status_summary( $post_id ) {
		$stored = AISP_Social_Manager::get_statuses( $post_id );
		$statuses = array();
		foreach ( AISP_Social_Manager::enabled_platforms() as $platform ) {
			if ( isset( $stored[ $platform ]['status'] ) ) { $statuses[] = sanitize_key( $stored[ $platform ]['status'] ); }
		}
		if ( ! $statuses ) {
			if ( 'webhook_sent' === (string) get_post_meta( $post_id, '_aisp_status', true ) ) {
				return array( 'state' => 'published', 'status_label' => __( 'Published ✓', 'ai-social-publisher' ), 'action_label' => __( 'Share Again', 'ai-social-publisher' ), 'force' => true );
			}
			return array( 'state' => 'new', 'status_label' => __( 'Never shared', 'ai-social-publisher' ), 'action_label' => __( 'Share Now', 'ai-social-publisher' ), 'force' => false );
		}
		if ( in_array( 'processing', $statuses, true ) ) { return array( 'state' => 'processing', 'status_label' => __( 'Processing…', 'ai-social-publisher' ), 'action_label' => __( 'View Status', 'ai-social-publisher' ), 'force' => false ); }
		$successful = array_intersect( $statuses, array( 'published', 'success', 'sent', 'accepted' ) );
		$failed = array_filter( $statuses, function ( $status ) { return 'failed' === $status; } );
		if ( count( $successful ) === count( $statuses ) ) { return array( 'state' => 'published', 'status_label' => __( 'Published ✓', 'ai-social-publisher' ), 'action_label' => __( 'Share Again', 'ai-social-publisher' ), 'force' => true ); }
		if ( count( $failed ) === count( $statuses ) ) { return array( 'state' => 'failed', 'status_label' => __( 'Failed', 'ai-social-publisher' ), 'action_label' => __( 'Try Again', 'ai-social-publisher' ), 'force' => false ); }
		return array( 'state' => 'partial', 'status_label' => __( 'Partial', 'ai-social-publisher' ), 'action_label' => __( 'Open / Retry', 'ai-social-publisher' ), 'force' => false );
	}
	private static function share_button( WP_Post $post, array $summary, $classes ) {
		return sprintf( '<button type="button" class="%1$s aisp-open-modal" data-post-id="%2$d" data-post-title="%3$s" data-post-url="%4$s" data-force="%5$d">%6$s</button>', esc_attr( $classes ), (int) $post->ID, esc_attr( get_the_title( $post ) ), esc_url( get_permalink( $post ) ), $summary['force'] ? 1 : 0, esc_html( $summary['action_label'] ) );
	}
	public static function modal() {
		$screen = get_current_screen(); if ( ! $screen || 'post' !== $screen->post_type ) { return; }
		$platforms = array( 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn' );
		?>
		<div id="aisp-modal" class="aisp-modal" hidden><div class="aisp-modal__backdrop" data-aisp-close></div><div class="aisp-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aisp-modal-title">
		<button type="button" class="aisp-modal__close" data-aisp-close aria-label="<?php esc_attr_e( 'Close', 'ai-social-publisher' ); ?>">&times;</button>
		<h2 id="aisp-modal-title"><?php esc_html_e( 'Share article', 'ai-social-publisher' ); ?></h2><p class="aisp-modal__article"></p>
		<fieldset class="aisp-platforms"><legend><?php esc_html_e( 'Select platforms', 'ai-social-publisher' ); ?></legend>
		<?php foreach ( $platforms as $key => $label ) : if ( (bool) get_option( 'aisp_enable_' . $key, true ) ) : ?>
		<label><input type="checkbox" value="<?php echo esc_attr( $key ); ?>" class="aisp-platform" checked> <?php echo esc_html( $label ); ?></label>
		<?php endif; endforeach; ?></fieldset>
		<div class="aisp-generate-row"><button type="button" class="button aisp-generate"><?php esc_html_e( 'Generate Social Content with OpenAI', 'ai-social-publisher' ); ?></button></div>
		<nav class="nav-tab-wrapper aisp-platform-tabs" aria-label="<?php esc_attr_e( 'Social platform caption editors', 'ai-social-publisher' ); ?>">
		<?php foreach ( $platforms as $key => $label ) : ?>
			<button type="button" class="nav-tab<?php echo 'facebook' === $key ? ' nav-tab-active' : ''; ?>" data-aisp-tab="<?php echo esc_attr( $key ); ?>" aria-controls="aisp-tab-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'facebook' === $key ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
		</nav>
		<div class="aisp-tab-panels">
		<?php foreach ( $platforms as $key => $label ) : $editor_id = 'aisp-caption-' . $key; ?>
			<section id="aisp-tab-panel-<?php echo esc_attr( $key ); ?>" class="aisp-caption aisp-tab-panel" data-platform="<?php echo esc_attr( $key ); ?>" data-editor-id="<?php echo esc_attr( $editor_id ); ?>" role="tabpanel"<?php echo 'facebook' === $key ? '' : ' hidden'; ?>>
				<div class="aisp-caption__heading">
					<label for="<?php echo esc_attr( $editor_id ); ?>"><strong><?php echo esc_html( $label . ' ' . __( 'Caption', 'ai-social-publisher' ) ); ?></strong></label>
				</div>
				<div class="aisp-classic-editor" data-caption-editor="<?php echo esc_attr( $key ); ?>">
					<textarea id="<?php echo esc_attr( $editor_id ); ?>" name="<?php echo esc_attr( $editor_id ); ?>" class="aisp-caption-field" rows="8"></textarea>
				</div>
				<div class="aisp-caption__meta" id="aisp-caption-meta-<?php echo esc_attr( $key ); ?>"><span class="aisp-ai-indicator" data-ai-indicator="<?php echo esc_attr( $key ); ?>" hidden><?php esc_html_e( 'AI generated', 'ai-social-publisher' ); ?></span><span data-caption-count="<?php echo esc_attr( $key ); ?>">0</span><button type="button" class="button button-small aisp-regenerate" data-regenerate="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( sprintf( __( 'Regenerate %s', 'ai-social-publisher' ), $label ) ); ?></button></div>
			</section>
		<?php endforeach; ?>
		</div>
		<div class="aisp-modal__status" aria-live="polite"></div><div class="aisp-modal__actions"><button type="button" class="button" data-aisp-close><?php esc_html_e( 'Cancel', 'ai-social-publisher' ); ?></button><button type="button" class="button button-primary aisp-publish"><?php esc_html_e( 'Share Now', 'ai-social-publisher' ); ?></button></div>
		</div></div><?php
	}
	private static function render_results( array $results ) {
		if ( ! $results ) { return; } echo '<div class="aisp-results"><p><strong>' . esc_html__( 'Publishing Status', 'ai-social-publisher' ) . '</strong></p><ul>';
		foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X' ) as $key => $label ) { if ( isset( $results[ $key ] ) ) { printf( '<li><strong>%1$s:</strong> %2$s%3$s</li>', esc_html( $label ), esc_html( ucfirst( $results[ $key ]['status'] ) ), ! empty( $results[ $key ]['message'] ) ? '<br><small>' . esc_html( $results[ $key ]['message'] ) . '</small>' : '' ); } }
		echo '</ul></div>';
	}
	public static function assets( $hook ) {
		$screen = get_current_screen(); $posts = in_array( $hook, array( 'edit.php', 'post.php' ), true ) && $screen && 'post' === $screen->post_type; $settings = in_array( $hook, array( 'toplevel_page_' . AISP_Settings::PAGE, 'settings_page_' . AISP_Settings::PAGE ), true ); if ( ! $posts && ! $settings ) { return; }
		if ( $posts ) { wp_enqueue_editor(); }
		wp_enqueue_script( 'aisp-admin', AISP_URL . 'assets/admin.js', array( 'wp-api-fetch', 'editor', 'quicktags' ), AISP_VERSION, true ); wp_enqueue_style( 'aisp-admin', AISP_URL . 'assets/admin.css', array(), AISP_VERSION );
		wp_localize_script( 'aisp-admin', 'aispAdmin', array( 'nonce' => wp_create_nonce( 'wp_rest' ), 'paths' => array( 'generate' => '/aisp/v1/generate/', 'share' => '/aisp/v1/share/', 'reconcile' => '/aisp/v1/buffer-status/', 'statuses' => '/aisp/v1/publishing-status/', 'testBuffer' => '/aisp/v1/test-buffer', 'channels' => '/aisp/v1/buffer-channels', 'testWebhook' => '/aisp/v1/test-webhook' ), 'labels' => array( 'generating' => __( 'Generating captions...', 'ai-social-publisher' ), 'regenerating' => __( 'Regenerating %s...', 'ai-social-publisher' ), 'generated' => __( 'Captions generated. Review and edit them before sharing.', 'ai-social-publisher' ), 'regenerated' => __( '%s caption regenerated. Other captions were preserved.', 'ai-social-publisher' ), 'publishing' => __( 'Publishing immediately through Buffer...', 'ai-social-publisher' ), 'failed' => __( 'The request failed.', 'ai-social-publisher' ), 'select' => __( 'Select at least one platform.', 'ai-social-publisher' ), 'caption' => __( 'Generate or enter a caption for every selected platform.', 'ai-social-publisher' ), 'xTooLong' => __( 'The X caption exceeds 280 weighted characters. Shorten it before sharing.', 'ai-social-publisher' ), 'characters' => __( 'characters', 'ai-social-publisher' ), 'weightedCharacters' => __( 'weighted characters', 'ai-social-publisher' ), 'done' => __( 'Publishing attempt completed.', 'ai-social-publisher' ), 'testing' => __( 'Testing...', 'ai-social-publisher' ), 'unconfirmed' => __( 'Publication accepted by Buffer; final status not yet confirmed.', 'ai-social-publisher' ), 'shareNow' => __( 'Share Now', 'ai-social-publisher' ), 'shareAgain' => __( 'Share Again', 'ai-social-publisher' ), 'shareAgainConfirm' => __( 'This post was published previously. Publish it again?', 'ai-social-publisher' ) ) ) );
	}
	public static function routes() {
		register_rest_route( 'aisp/v1', '/share/(?P<id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'share' ), 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( 'aisp/v1', '/generate/(?P<id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'generate' ), 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( 'aisp/v1', '/buffer-status/(?P<id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'buffer_status' ), 'permission_callback' => array( __CLASS__, 'buffer_status_permission' ) ) );
		register_rest_route( 'aisp/v1', '/publishing-status/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'publishing_status' ), 'permission_callback' => array( __CLASS__, 'buffer_status_permission' ) ) );
		foreach ( array( 'test-buffer' => WP_REST_Server::CREATABLE, 'buffer-channels' => WP_REST_Server::READABLE ) as $route => $method ) { register_rest_route( 'aisp/v1', '/' . $route, array( 'methods' => $method, 'callback' => array( __CLASS__, 'buffer_channels' ), 'permission_callback' => array( __CLASS__, 'admin_permission' ) ) ); }
		register_rest_route( 'aisp/v1', '/test-webhook', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'test_webhook' ), 'permission_callback' => array( __CLASS__, 'admin_permission' ) ) );
	}
	public static function permission( WP_REST_Request $request ) { return AISP_Security::authorize( absint( $request['id'] ) ); }
	public static function buffer_status_permission( WP_REST_Request $request ) { if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) { return new WP_Error( 'aisp_invalid_nonce', __( 'The security token is invalid or expired.', 'ai-social-publisher' ), array( 'status' => 403 ) ); } return AISP_Security::authorize( absint( $request['id'] ) ); }
	public static function admin_permission() { return current_user_can( 'manage_options' ); }
	public static function generate( WP_REST_Request $request ) {
		$post = self::published_post( absint( $request['id'] ) ); if ( is_wp_error( $post ) ) { return $post; } $platforms = self::sanitize_platforms( $request->get_param( 'platforms' ) ); $result = AISP_OpenAI_Client::generate( $post, $platforms );
		if ( is_wp_error( $result ) ) { return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) ); }
		self::save_captions( $post->ID, $result );
		return rest_ensure_response( array( 'success' => true, 'captions' => $result ) );
	}
	public static function share( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] ); $post = self::published_post( $post_id ); if ( is_wp_error( $post ) ) { return $post; } $platforms = self::sanitize_platforms( $request->get_param( 'platforms' ) ); $captions = self::sanitize_captions( $request->get_param( 'captions' ), $platforms );
		if ( ! $platforms || count( $captions ) !== count( $platforms ) ) { return new WP_Error( 'aisp_invalid_share_data', __( 'Select platforms and provide every final caption.', 'ai-social-publisher' ), array( 'status' => 400 ) ); }
		if ( 'webhook_sent' === (string) get_post_meta( $post_id, '_aisp_status', true ) && ! AISP_Social_Manager::get_statuses( $post_id ) && ! rest_sanitize_boolean( $request->get_param( 'force' ) ) ) { return new WP_Error( 'aisp_already_sent', __( 'This legacy post has already been shared.', 'ai-social-publisher' ), array( 'status' => 409 ) ); }
		if ( ! self::acquire_lock( $post_id ) ) { return new WP_Error( 'aisp_in_progress', __( 'This post is already being sent. Please wait.', 'ai-social-publisher' ), array( 'status' => 409 ) ); }
		try { self::save_captions( $post_id, $captions ); update_post_meta( $post_id, '_aisp_status', 'pending' ); $result = AISP_Social_Manager::publish( $post, rest_sanitize_boolean( $request->get_param( 'force' ) ), $platforms, $captions ); } finally { self::release_lock( $post_id ); }
		if ( is_wp_error( $result ) ) { update_post_meta( $post_id, '_aisp_status', 'failed' ); return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502, 'statuses' => AISP_Social_Manager::get_statuses( $post_id ) ) ); }
		$complete = self::selected_complete( $result['statuses'], $platforms ); update_post_meta( $post_id, '_aisp_status', $complete ? 'webhook_sent' : 'partial' ); update_post_meta( $post_id, '_aisp_sent_time', current_time( 'mysql', true ) ); return rest_ensure_response( array( 'success' => $complete, 'message' => $complete ? __( 'Selected platforms published successfully.', 'ai-social-publisher' ) : __( 'Buffer accepted the request; review individual platform results.', 'ai-social-publisher' ), 'results' => $result['results'], 'statuses' => $result['statuses'] ) );
	}
	public static function buffer_channels() { $result = AISP_Buffer_Client::discover_channels(); return is_wp_error( $result ) ? new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) ) : rest_ensure_response( array( 'success' => true, 'data' => $result ) ); }
	public static function buffer_status( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] ); $post = self::published_post( $post_id ); if ( is_wp_error( $post ) ) { return $post; }
		$attempts = $request->get_param( 'attempts' ); if ( ! is_array( $attempts ) ) { $platform = sanitize_key( (string) $request->get_param( 'platform' ) ); $attempts = array( $platform => sanitize_text_field( (string) $request->get_param( 'remote_id' ) ) ); }
		$clean = array(); foreach ( $attempts as $platform => $remote_id ) { $platform = sanitize_key( $platform ); if ( ! in_array( $platform, array( 'facebook', 'linkedin', 'x' ), true ) || '' === trim( (string) $remote_id ) ) { return new WP_Error( 'aisp_invalid_reconciliation', __( 'Every reconciliation target must have a valid platform and Buffer post ID.', 'ai-social-publisher' ), array( 'status' => 400 ) ); } $clean[ $platform ] = sanitize_text_field( (string) $remote_id ); }
		$result = AISP_Social_Manager::reconcile_many( $post_id, $clean );
		if ( is_wp_error( $result ) ) { return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) ); }
		update_post_meta( $post_id, '_aisp_status', AISP_Social_Manager::all_complete( $post_id ) ? 'webhook_sent' : 'partial' );
		return rest_ensure_response( array( 'success' => true, 'results' => $result['results'], 'rate_limited' => $result['rate_limited'] ) );
	}
	public static function publishing_status( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] ); $post = self::published_post( $post_id ); if ( is_wp_error( $post ) ) { return $post; } $stored = AISP_Social_Manager::get_statuses( $post_id ); $results = array();
		foreach ( AISP_Social_Manager::enabled_platforms() as $platform ) { if ( isset( $stored[ $platform ] ) && is_array( $stored[ $platform ] ) ) { $results[ $platform ] = $stored[ $platform ]; } }
		return rest_ensure_response( array( 'success' => true, 'results' => $results, 'saved_captions' => self::saved_captions( $post_id ) ) );
	}
	public static function test_webhook() { $result = AISP_Social_Webhook::test_connection(); return is_wp_error( $result ) ? new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) ) : rest_ensure_response( array( 'success' => true, 'message' => sprintf( __( 'Connection successful (HTTP %d).', 'ai-social-publisher' ), $result['status_code'] ) ) ); }
	private static function published_post( $post_id ) { $post = get_post( $post_id ); if ( ! $post || 'post' !== $post->post_type ) { return new WP_Error( 'aisp_invalid_post', __( 'The requested post was not found.', 'ai-social-publisher' ), array( 'status' => 404 ) ); } return 'publish' === $post->post_status ? $post : new WP_Error( 'aisp_not_published', __( 'Publish the post before sharing it.', 'ai-social-publisher' ), array( 'status' => 400 ) ); }
	private static function sanitize_platforms( $value ) { $value = is_array( $value ) ? array_map( 'sanitize_key', $value ) : array(); return array_values( array_unique( array_intersect( AISP_Social_Manager::enabled_platforms(), $value ) ) ); }
	private static function sanitize_captions( $value, array $platforms ) { $value = is_array( $value ) ? $value : array(); $result = array(); foreach ( $platforms as $platform ) { if ( isset( $value[ $platform ] ) ) { $caption = self::plain_text_caption( $value[ $platform ] ); if ( '' !== trim( $caption ) ) { $result[ $platform ] = $caption; } } } return $result; }
	private static function saved_captions( $post_id ) { $result = array(); foreach ( self::SAVED_CAPTION_META as $platform => $meta_key ) { $stored = get_post_meta( $post_id, $meta_key, true ); if ( ! is_string( $stored ) ) { continue; } $caption = self::plain_text_caption( $stored ); if ( '' !== trim( $caption ) ) { $result[ $platform ] = $caption; } } return $result; }
	private static function save_captions( $post_id, array $captions ) { foreach ( self::SAVED_CAPTION_META as $platform => $meta_key ) { if ( ! isset( $captions[ $platform ] ) || ! is_string( $captions[ $platform ] ) ) { continue; } $caption = self::plain_text_caption( $captions[ $platform ] ); if ( '' !== trim( $caption ) ) { update_post_meta( $post_id, $meta_key, $caption ); } } }
	private static function plain_text_caption( $value ) {
		$value = is_string( $value ) ? $value : '';
		// TinyMCE selection bookmarks are editor state, never caption content.
		$value = preg_replace( '/<span\b[^>]*(?:data-mce-type\s*=\s*["\']bookmark["\']|class\s*=\s*["\'][^"\']*mce_SELRES_(?:start|end)[^"\']*["\'])[^>]*>\s*<\/span>/i', '', $value );
		$value = preg_replace( '/<span\b[^>]*(?:data-mce-type\s*=\s*["\']bookmark["\']|class\s*=\s*["\'][^"\']*mce_SELRES_(?:start|end)[^"\']*["\'])[^>]*\/?>/i', '', $value );
		$value = preg_replace_callback(
			'/<ol\b[^>]*>(.*?)<\/ol>/is',
			function ( $matches ) {
				$index = 0;
				return preg_replace_callback(
					'/<li\b[^>]*>/i',
					function () use ( &$index ) { $index++; return $index . '. '; },
					$matches[1]
				);
			},
			$value
		);
		$value = preg_replace( '/<li\b[^>]*>/i', '• ', $value );
		$value = preg_replace( '/<br\s*\/?\s*>/i', "\n", $value );
		$value = preg_replace( '/<\/(p|div|li|h[1-6]|blockquote|ul|ol)>/i', "\n", $value );
		$value = wp_strip_all_tags( $value );
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = str_replace( array( "\r\n", "\r", "\xc2\xa0" ), array( "\n", "\n", ' ' ), $value );
		$value = preg_replace( "/[ \t]+\n/", "\n", $value );
		$value = preg_replace( "/\n{3,}/", "\n\n", $value );
		return sanitize_textarea_field( trim( $value ) );
	}
	private static function selected_complete( array $statuses, array $platforms ) { foreach ( $platforms as $platform ) { $status = isset( $statuses[ $platform ]['status'] ) ? $statuses[ $platform ]['status'] : ''; if ( ! in_array( $status, array( 'published', 'success', 'sent' ), true ) ) { return false; } } return true; }
	private static function acquire_lock( $post_id ) { $lock = (int) get_post_meta( $post_id, '_aisp_lock', true ); if ( $lock && ( time() - $lock ) > 120 ) { delete_post_meta( $post_id, '_aisp_lock' ); } return add_post_meta( $post_id, '_aisp_lock', time(), true ); }
	private static function release_lock( $post_id ) { delete_post_meta( $post_id, '_aisp_lock' ); }
}
