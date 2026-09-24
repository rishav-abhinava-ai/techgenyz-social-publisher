<?php
/** Admin sharing UI and REST actions. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TGSP_Metabox {
	const SAVED_CAPTION_META = array(
		'facebook' => '_tgsp_saved_caption_facebook',
		'linkedin' => '_tgsp_saved_caption_linkedin',
		'x'        => '_tgsp_saved_caption_x',
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
	public static function add() { add_meta_box( 'tgsp-social-publisher', __( 'Social Publisher', 'techgenyz-social-publisher' ), array( __CLASS__, 'render' ), 'post', 'side', 'high', array( '__block_editor_compatible_meta_box' => true ) ); }
	public static function render( WP_Post $post ) {
		$summary = self::status_summary( $post->ID );
		echo '<div id="tgsp-publisher" data-post-id="' . esc_attr( $post->ID ) . '"><p>' . esc_html__( 'Generate, review and immediately publish social captions through Buffer.', 'techgenyz-social-publisher' ) . '</p>';
		self::render_results( TGSP_Social_Manager::get_statuses( $post->ID ) );
		echo self::share_button( $post, $summary, 'button button-primary button-large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all dynamic values.
		echo '</div>';
	}
	public static function columns( $columns ) {
		$result = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) { $result['tgsp_social'] = __( 'Social', 'techgenyz-social-publisher' ); }
			$result[ $key ] = $label;
		}
		if ( ! isset( $result['tgsp_social'] ) ) { $result['tgsp_social'] = __( 'Social', 'techgenyz-social-publisher' ); }
		return $result;
	}
	public static function column( $column, $post_id ) {
		if ( 'tgsp_social' !== $column ) { return; }
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || ! current_user_can( 'edit_post', $post_id ) ) { echo '&mdash;'; return; }
		$summary = self::status_summary( $post_id );
		printf( '<span class="tgsp-column-status tgsp-column-status--%1$s">%2$s</span>', esc_attr( $summary['state'] ), esc_html( $summary['status_label'] ) );
		echo self::share_button( $post, $summary, 'button button-small' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all dynamic values.
	}
	public static function row_action( $actions, WP_Post $post ) {
		if ( 'post' === $post->post_type && 'publish' === $post->post_status && current_user_can( 'edit_post', $post->ID ) ) {
			$summary = self::status_summary( $post->ID );
			$actions['tgsp_share'] = sprintf( '<button type="button" class="button-link tgsp-open-modal" data-post-id="%1$d" data-post-title="%2$s" data-post-url="%3$s" data-force="%4$d"><span class="dashicons dashicons-share" aria-hidden="true"></span> %5$s</button>', (int) $post->ID, esc_attr( get_the_title( $post ) ), esc_url( get_permalink( $post ) ), $summary['force'] ? 1 : 0, esc_html( $summary['action_label'] ) );
		}
		return $actions;
	}
	private static function status_summary( $post_id ) {
		$stored = TGSP_Social_Manager::get_statuses( $post_id );
		$statuses = array();
		foreach ( TGSP_Social_Manager::enabled_platforms() as $platform ) {
			if ( isset( $stored[ $platform ]['status'] ) ) { $statuses[] = sanitize_key( $stored[ $platform ]['status'] ); }
		}
		if ( ! $statuses ) {
			if ( 'webhook_sent' === (string) get_post_meta( $post_id, '_social_publisher_status', true ) ) {
				return array( 'state' => 'published', 'status_label' => __( 'Published ✓', 'techgenyz-social-publisher' ), 'action_label' => __( 'Share Again', 'techgenyz-social-publisher' ), 'force' => true );
			}
			return array( 'state' => 'new', 'status_label' => __( 'Never shared', 'techgenyz-social-publisher' ), 'action_label' => __( 'Share Now', 'techgenyz-social-publisher' ), 'force' => false );
		}
		if ( in_array( 'processing', $statuses, true ) ) { return array( 'state' => 'processing', 'status_label' => __( 'Processing…', 'techgenyz-social-publisher' ), 'action_label' => __( 'View Status', 'techgenyz-social-publisher' ), 'force' => false ); }
		$successful = array_intersect( $statuses, array( 'published', 'success', 'sent', 'accepted' ) );
		$failed = array_filter( $statuses, function ( $status ) { return 'failed' === $status; } );
		if ( count( $successful ) === count( $statuses ) ) { return array( 'state' => 'published', 'status_label' => __( 'Published ✓', 'techgenyz-social-publisher' ), 'action_label' => __( 'Share Again', 'techgenyz-social-publisher' ), 'force' => true ); }
		if ( count( $failed ) === count( $statuses ) ) { return array( 'state' => 'failed', 'status_label' => __( 'Failed', 'techgenyz-social-publisher' ), 'action_label' => __( 'Try Again', 'techgenyz-social-publisher' ), 'force' => false ); }
		return array( 'state' => 'partial', 'status_label' => __( 'Partial', 'techgenyz-social-publisher' ), 'action_label' => __( 'Open / Retry', 'techgenyz-social-publisher' ), 'force' => false );
	}
	private static function share_button( WP_Post $post, array $summary, $classes ) {
		return sprintf( '<button type="button" class="%1$s tgsp-open-modal" data-post-id="%2$d" data-post-title="%3$s" data-post-url="%4$s" data-force="%5$d">%6$s</button>', esc_attr( $classes ), (int) $post->ID, esc_attr( get_the_title( $post ) ), esc_url( get_permalink( $post ) ), $summary['force'] ? 1 : 0, esc_html( $summary['action_label'] ) );
	}
	public static function modal() {
		$screen = get_current_screen(); if ( ! $screen || 'post' !== $screen->post_type ) { return; }
		$platforms = array( 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn' );
		?>
		<div id="tgsp-modal" class="tgsp-modal" hidden><div class="tgsp-modal__backdrop" data-tgsp-close></div><div class="tgsp-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tgsp-modal-title">
		<button type="button" class="tgsp-modal__close" data-tgsp-close aria-label="<?php esc_attr_e( 'Close', 'techgenyz-social-publisher' ); ?>">&times;</button>
		<h2 id="tgsp-modal-title"><?php esc_html_e( 'Share article', 'techgenyz-social-publisher' ); ?></h2><p class="tgsp-modal__article"></p>
		<fieldset class="tgsp-platforms"><legend><?php esc_html_e( 'Select platforms', 'techgenyz-social-publisher' ); ?></legend>
		<?php foreach ( $platforms as $key => $label ) : if ( (bool) get_option( 'tgsp_enable_' . $key, true ) ) : ?>
		<label><input type="checkbox" value="<?php echo esc_attr( $key ); ?>" class="tgsp-platform" checked> <?php echo esc_html( $label ); ?></label>
		<?php endif; endforeach; ?></fieldset>
		<div class="tgsp-generate-row"><button type="button" class="button tgsp-generate"><?php esc_html_e( 'Generate Social Content with OpenAI', 'techgenyz-social-publisher' ); ?></button></div>
		<nav class="nav-tab-wrapper tgsp-platform-tabs" aria-label="<?php esc_attr_e( 'Social platform caption editors', 'techgenyz-social-publisher' ); ?>">
		<?php foreach ( $platforms as $key => $label ) : ?>
			<button type="button" class="nav-tab<?php echo 'facebook' === $key ? ' nav-tab-active' : ''; ?>" data-tgsp-tab="<?php echo esc_attr( $key ); ?>" aria-controls="tgsp-tab-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'facebook' === $key ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
		</nav>
		<div class="tgsp-tab-panels">
		<?php foreach ( $platforms as $key => $label ) : $editor_id = 'tgsp-caption-' . $key; ?>
			<section id="tgsp-tab-panel-<?php echo esc_attr( $key ); ?>" class="tgsp-caption tgsp-tab-panel" data-platform="<?php echo esc_attr( $key ); ?>" data-editor-id="<?php echo esc_attr( $editor_id ); ?>" role="tabpanel"<?php echo 'facebook' === $key ? '' : ' hidden'; ?>>
				<div class="tgsp-caption__heading">
					<label for="<?php echo esc_attr( $editor_id ); ?>"><strong><?php echo esc_html( $label . ' ' . __( 'Caption', 'techgenyz-social-publisher' ) ); ?></strong></label>
				</div>
				<div class="tgsp-classic-editor" data-caption-editor="<?php echo esc_attr( $key ); ?>">
					<textarea id="<?php echo esc_attr( $editor_id ); ?>" name="<?php echo esc_attr( $editor_id ); ?>" class="tgsp-caption-field" rows="8"></textarea>
				</div>
				<div class="tgsp-caption__meta" id="tgsp-caption-meta-<?php echo esc_attr( $key ); ?>"><span class="tgsp-ai-indicator" data-ai-indicator="<?php echo esc_attr( $key ); ?>" hidden><?php esc_html_e( 'AI generated', 'techgenyz-social-publisher' ); ?></span><span data-caption-count="<?php echo esc_attr( $key ); ?>">0</span><button type="button" class="button button-small tgsp-regenerate" data-regenerate="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( sprintf( __( 'Regenerate %s', 'techgenyz-social-publisher' ), $label ) ); ?></button></div>
			</section>
		<?php endforeach; ?>
		</div>
		<div class="tgsp-modal__status" aria-live="polite"></div><div class="tgsp-modal__actions"><button type="button" class="button" data-tgsp-close><?php esc_html_e( 'Cancel', 'techgenyz-social-publisher' ); ?></button><button type="button" class="button button-primary tgsp-publish"><?php esc_html_e( 'Share Now', 'techgenyz-social-publisher' ); ?></button></div>
		</div></div><?php
	}
	private static function render_results( array $results ) {
		if ( ! $results ) { return; } echo '<div class="tgsp-results"><p><strong>' . esc_html__( 'Publishing Status', 'techgenyz-social-publisher' ) . '</strong></p><ul>';
		foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X' ) as $key => $label ) { if ( isset( $results[ $key ] ) ) { printf( '<li><strong>%1$s:</strong> %2$s%3$s</li>', esc_html( $label ), esc_html( ucfirst( $results[ $key ]['status'] ) ), ! empty( $results[ $key ]['message'] ) ? '<br><small>' . esc_html( $results[ $key ]['message'] ) . '</small>' : '' ); } }
		echo '</ul></div>';
	}
	public static function assets( $hook ) {
		$screen = get_current_screen(); $posts = in_array( $hook, array( 'edit.php', 'post.php' ), true ) && $screen && 'post' === $screen->post_type; $settings = in_array( $hook, array( 'toplevel_page_' . TGSP_Settings::PAGE, 'settings_page_' . TGSP_Settings::PAGE ), true ); if ( ! $posts && ! $settings ) { return; }
		if ( $posts ) { wp_enqueue_editor(); }
		wp_enqueue_script( 'tgsp-admin', TGSP_URL . 'assets/admin.js', array( 'wp-api-fetch', 'editor', 'quicktags' ), TGSP_VERSION, true ); wp_enqueue_style( 'tgsp-admin', TGSP_URL . 'assets/admin.css', array(), TGSP_VERSION );
		wp_localize_script( 'tgsp-admin', 'tgspAdmin', array( 'nonce' => wp_create_nonce( 'wp_rest' ), 'paths' => array( 'generate' => '/tgsp/v1/generate/', 'share' => '/tgsp/v1/share/', 'reconcile' => '/tgsp/v1/buffer-status/', 'statuses' => '/tgsp/v1/publishing-status/', 'testBuffer' => '/tgsp/v1/test-buffer', 'channels' => '/tgsp/v1/buffer-channels', 'testWebhook' => '/tgsp/v1/test-webhook' ), 'labels' => array( 'generating' => __( 'Generating captions...', 'techgenyz-social-publisher' ), 'regenerating' => __( 'Regenerating %s...', 'techgenyz-social-publisher' ), 'generated' => __( 'Captions generated. Review and edit them before sharing.', 'techgenyz-social-publisher' ), 'regenerated' => __( '%s caption regenerated. Other captions were preserved.', 'techgenyz-social-publisher' ), 'publishing' => __( 'Publishing immediately through Buffer...', 'techgenyz-social-publisher' ), 'failed' => __( 'The request failed.', 'techgenyz-social-publisher' ), 'select' => __( 'Select at least one platform.', 'techgenyz-social-publisher' ), 'caption' => __( 'Generate or enter a caption for every selected platform.', 'techgenyz-social-publisher' ), 'xTooLong' => __( 'The X caption exceeds 280 weighted characters. Shorten it before sharing.', 'techgenyz-social-publisher' ), 'characters' => __( 'characters', 'techgenyz-social-publisher' ), 'weightedCharacters' => __( 'weighted characters', 'techgenyz-social-publisher' ), 'done' => __( 'Publishing attempt completed.', 'techgenyz-social-publisher' ), 'testing' => __( 'Testing...', 'techgenyz-social-publisher' ), 'unconfirmed' => __( 'Publication accepted by Buffer; final status not yet confirmed.', 'techgenyz-social-publisher' ), 'shareNow' => __( 'Share Now', 'techgenyz-social-publisher' ), 'shareAgain' => __( 'Share Again', 'techgenyz-social-publisher' ), 'shareAgainConfirm' => __( 'This post was published previously. Publish it again?', 'techgenyz-social-publisher' ) ) ) );
	}
	public static function routes() {
		register_rest_route( 'tgsp/v1', '/share/(?P<id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'share' ), 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( 'tgsp/v1', '/generate/(?P<id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'generate' ), 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( 'tgsp/v1', '/buffer-status/(?P<id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'buffer_status' ), 'permission_callback' => array( __CLASS__, 'buffer_status_permission' ) ) );
		register_rest_route( 'tgsp/v1', '/publishing-status/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'publishing_status' ), 'permission_callback' => array( __CLASS__, 'buffer_status_permission' ) ) );
		foreach ( array( 'test-buffer' => WP_REST_Server::CREATABLE, 'buffer-channels' => WP_REST_Server::READABLE ) as $route => $method ) { register_rest_route( 'tgsp/v1', '/' . $route, array( 'methods' => $method, 'callback' => array( __CLASS__, 'buffer_channels' ), 'permission_callback' => array( __CLASS__, 'admin_permission' ) ) ); }
		register_rest_route( 'tgsp/v1', '/test-webhook', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'test_webhook' ), 'permission_callback' => array( __CLASS__, 'admin_permission' ) ) );
	}
	public static function permission( WP_REST_Request $request ) { return TGSP_Security::authorize( absint( $request['id'] ) ); }
	public static function buffer_status_permission( WP_REST_Request $request ) { if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) { return new WP_Error( 'tgsp_invalid_nonce', __( 'The security token is invalid or expired.', 'techgenyz-social-publisher' ), array( 'status' => 403 ) ); } return TGSP_Security::authorize( absint( $request['id'] ) ); }
	public static function admin_permission() { return current_user_can( 'manage_options' ); }
	public static function generate( WP_REST_Request $request ) {
		$post = self::published_post( absint( $request['id'] ) ); if ( is_wp_error( $post ) ) { return $post; } $platforms = self::sanitize_platforms( $request->get_param( 'platforms' ) ); $result = TGSP_OpenAI_Client::generate( $post, $platforms );
		if ( is_wp_error( $result ) ) { return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) ); }
		self::save_captions( $post->ID, $result );
		return rest_ensure_response( array( 'success' => true, 'captions' => $result ) );
	}
	public static function share( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] ); $post = self::published_post( $post_id ); if ( is_wp_error( $post ) ) { return $post; } $platforms = self::sanitize_platforms( $request->get_param( 'platforms' ) ); $captions = self::sanitize_captions( $request->get_param( 'captions' ), $platforms );
		if ( ! $platforms || count( $captions ) !== count( $platforms ) ) { return new WP_Error( 'tgsp_invalid_share_data', __( 'Select platforms and provide every final caption.', 'techgenyz-social-publisher' ), array( 'status' => 400 ) ); }
		if ( 'webhook_sent' === (string) get_post_meta( $post_id, '_social_publisher_status', true ) && ! TGSP_Social_Manager::get_statuses( $post_id ) && ! rest_sanitize_boolean( $request->get_param( 'force' ) ) ) { return new WP_Error( 'tgsp_already_sent', __( 'This legacy post has already been shared.', 'techgenyz-social-publisher' ), array( 'status' => 409 ) ); }
		if ( ! self::acquire_lock( $post_id ) ) { return new WP_Error( 'tgsp_in_progress', __( 'This post is already being sent. Please wait.', 'techgenyz-social-publisher' ), array( 'status' => 409 ) ); }
		try { self::save_captions( $post_id, $captions ); update_post_meta( $post_id, '_social_publisher_status', 'pending' ); $result = TGSP_Social_Manager::publish( $post, rest_sanitize_boolean( $request->get_param( 'force' ) ), $platforms, $captions ); } finally { self::release_lock( $post_id ); }
		if ( is_wp_error( $result ) ) { update_post_meta( $post_id, '_social_publisher_status', 'failed' ); return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502, 'statuses' => TGSP_Social_Manager::get_statuses( $post_id ) ) ); }
		$complete = self::selected_complete( $result['statuses'], $platforms ); update_post_meta( $post_id, '_social_publisher_status', $complete ? 'webhook_sent' : 'partial' ); update_post_meta( $post_id, '_social_publisher_sent_time', current_time( 'mysql', true ) ); return rest_ensure_response( array( 'success' => $complete, 'message' => $complete ? __( 'Selected platforms published successfully.', 'techgenyz-social-publisher' ) : __( 'Buffer accepted the request; review individual platform results.', 'techgenyz-social-publisher' ), 'results' => $result['results'], 'statuses' => $result['statuses'] ) );
	}
	public static function buffer_channels() { $result = TGSP_Buffer_Client::discover_channels(); return is_wp_error( $result ) ? new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) ) : rest_ensure_response( array( 'success' => true, 'data' => $result ) ); }
	public static function buffer_status( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] ); $post = self::published_post( $post_id ); if ( is_wp_error( $post ) ) { return $post; }
		$attempts = $request->get_param( 'attempts' ); if ( ! is_array( $attempts ) ) { $platform = sanitize_key( (string) $request->get_param( 'platform' ) ); $attempts = array( $platform => sanitize_text_field( (string) $request->get_param( 'remote_id' ) ) ); }
		$clean = array(); foreach ( $attempts as $platform => $remote_id ) { $platform = sanitize_key( $platform ); if ( ! in_array( $platform, array( 'facebook', 'linkedin', 'x' ), true ) || '' === trim( (string) $remote_id ) ) { return new WP_Error( 'tgsp_invalid_reconciliation', __( 'Every reconciliation target must have a valid platform and Buffer post ID.', 'techgenyz-social-publisher' ), array( 'status' => 400 ) ); } $clean[ $platform ] = sanitize_text_field( (string) $remote_id ); }
		$result = TGSP_Social_Manager::reconcile_many( $post_id, $clean );
		if ( is_wp_error( $result ) ) { return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) ); }
		update_post_meta( $post_id, '_social_publisher_status', TGSP_Social_Manager::all_complete( $post_id ) ? 'webhook_sent' : 'partial' );
		return rest_ensure_response( array( 'success' => true, 'results' => $result['results'], 'rate_limited' => $result['rate_limited'] ) );
	}
	public static function publishing_status( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] ); $post = self::published_post( $post_id ); if ( is_wp_error( $post ) ) { return $post; } $stored = TGSP_Social_Manager::get_statuses( $post_id ); $results = array();
		foreach ( TGSP_Social_Manager::enabled_platforms() as $platform ) { if ( isset( $stored[ $platform ] ) && is_array( $stored[ $platform ] ) ) { $results[ $platform ] = $stored[ $platform ]; } }
		return rest_ensure_response( array( 'success' => true, 'results' => $results, 'saved_captions' => self::saved_captions( $post_id ) ) );
	}
	public static function test_webhook() { $result = TGSP_Social_Webhook::test_connection(); return is_wp_error( $result ) ? new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) ) : rest_ensure_response( array( 'success' => true, 'message' => sprintf( __( 'Connection successful (HTTP %d).', 'techgenyz-social-publisher' ), $result['status_code'] ) ) ); }
	private static function published_post( $post_id ) { $post = get_post( $post_id ); if ( ! $post || 'post' !== $post->post_type ) { return new WP_Error( 'tgsp_invalid_post', __( 'The requested post was not found.', 'techgenyz-social-publisher' ), array( 'status' => 404 ) ); } return 'publish' === $post->post_status ? $post : new WP_Error( 'tgsp_not_published', __( 'Publish the post before sharing it.', 'techgenyz-social-publisher' ), array( 'status' => 400 ) ); }
	private static function sanitize_platforms( $value ) { $value = is_array( $value ) ? array_map( 'sanitize_key', $value ) : array(); return array_values( array_unique( array_intersect( TGSP_Social_Manager::enabled_platforms(), $value ) ) ); }
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
	private static function acquire_lock( $post_id ) { $lock = (int) get_post_meta( $post_id, '_social_publisher_lock', true ); if ( $lock && ( time() - $lock ) > 120 ) { delete_post_meta( $post_id, '_social_publisher_lock' ); } return add_post_meta( $post_id, '_social_publisher_lock', time(), true ); }
	private static function release_lock( $post_id ) { delete_post_meta( $post_id, '_social_publisher_lock' ); }
}
