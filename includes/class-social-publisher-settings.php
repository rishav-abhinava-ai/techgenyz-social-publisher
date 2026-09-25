<?php
/**
 * Settings page.
 *
 * @package TechGenyzSocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TGSP_Settings {
	const PAGE = 'tgsp-settings';
	const OPENAI_MODEL = 'gpt-6-luna';
	const LEGACY_OPENAI_MODEL = 'gpt-5-mini';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function add_page() {
		add_menu_page(
			__( 'Social Publisher', 'techgenyz-social-publisher' ),
			__( 'Social Publisher', 'techgenyz-social-publisher' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-share',
			58
		);

		add_options_page(
			__( 'Social Publisher', 'techgenyz-social-publisher' ),
			__( 'Social Publisher', 'techgenyz-social-publisher' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting( 'tgsp_settings', 'tgsp_delivery_method', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_delivery_method' ), 'default' => 'buffer' ) );
		register_setting( 'tgsp_settings', 'tgsp_openai_api_key', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_openai_key' ), 'default' => '' ) );
		register_setting( 'tgsp_settings', 'tgsp_openai_model', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_openai_model' ), 'default' => self::OPENAI_MODEL ) );
		register_setting( 'tgsp_settings', 'tgsp_openai_caption_prompt', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_openai_caption_prompt' ), 'default' => '' ) );
		register_setting( 'tgsp_settings', 'tgsp_buffer_api_key', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_buffer_key' ), 'default' => '' ) );
		register_setting( 'tgsp_settings', 'tgsp_buffer_organization_id', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		foreach ( array( 'facebook', 'linkedin', 'x' ) as $platform ) {
			register_setting( 'tgsp_settings', 'tgsp_buffer_channel_' . $platform, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		}
		register_setting(
			'tgsp_settings',
			'tgsp_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_webhook' ),
				'default'           => '',
			)
		);
		foreach ( array( 'facebook', 'linkedin', 'x' ) as $platform ) {
			register_setting(
				'tgsp_settings',
				'tgsp_enable_' . $platform,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
					'default'           => true,
				)
			);
			register_setting(
				'tgsp_settings',
				'tgsp_' . $platform . '_caption_template',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'default'           => '',
				)
			);
		}
		register_setting(
			'tgsp_settings',
			'tgsp_message_format',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => "{title}\n\nRead more:\n{url}",
			)
		);
		register_setting(
			'tgsp_settings',
			'tgsp_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_secret' ),
				'default'           => '',
			)
		);

		register_setting(
			'tgsp_settings',
			'tgsp_debug_logging',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_section( 'tgsp_workflow', __( 'Publishing workflow', 'techgenyz-social-publisher' ), '__return_false', self::PAGE );
		add_settings_field( 'tgsp_delivery_method', __( 'Delivery method', 'techgenyz-social-publisher' ), array( __CLASS__, 'delivery_method_field' ), self::PAGE, 'tgsp_workflow' );
		add_settings_section( 'tgsp_openai', __( 'OpenAI caption generation', 'techgenyz-social-publisher' ), '__return_false', self::PAGE );
		add_settings_field( 'tgsp_openai_api_key', __( 'OpenAI API key', 'techgenyz-social-publisher' ), array( __CLASS__, 'openai_key_field' ), self::PAGE, 'tgsp_openai' );
		add_settings_field( 'tgsp_openai_model', __( 'OpenAI model', 'techgenyz-social-publisher' ), array( __CLASS__, 'openai_model_field' ), self::PAGE, 'tgsp_openai' );
		add_settings_field( 'tgsp_openai_caption_prompt', __( 'Caption generation prompt', 'techgenyz-social-publisher' ), array( __CLASS__, 'openai_caption_prompt_field' ), self::PAGE, 'tgsp_openai' );
		add_settings_section( 'tgsp_buffer', __( 'Buffer immediate publishing', 'techgenyz-social-publisher' ), array( __CLASS__, 'buffer_section' ), self::PAGE );
		add_settings_field( 'tgsp_buffer_api_key', __( 'Buffer API key', 'techgenyz-social-publisher' ), array( __CLASS__, 'buffer_key_field' ), self::PAGE, 'tgsp_buffer' );
		add_settings_field( 'tgsp_buffer_organization_id', __( 'Buffer organization ID', 'techgenyz-social-publisher' ), array( __CLASS__, 'buffer_organization_field' ), self::PAGE, 'tgsp_buffer' );
		foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X' ) as $key => $label ) {
			add_settings_field( 'tgsp_buffer_channel_' . $key, sprintf( __( '%s Buffer channel', 'techgenyz-social-publisher' ), $label ), array( __CLASS__, 'buffer_channel_field' ), self::PAGE, 'tgsp_buffer', array( 'platform' => $key ) );
		}
		add_settings_section( 'tgsp_main', __( 'Legacy webhook connection', 'techgenyz-social-publisher' ), '__return_false', self::PAGE );
		add_settings_field( 'tgsp_webhook_url', __( 'Webhook URL', 'techgenyz-social-publisher' ), array( __CLASS__, 'webhook_field' ), self::PAGE, 'tgsp_main' );
		add_settings_field( 'tgsp_webhook_secret', __( 'Webhook Secret Key', 'techgenyz-social-publisher' ), array( __CLASS__, 'secret_field' ), self::PAGE, 'tgsp_main' );
		add_settings_field( 'tgsp_message_format', __( 'Default social message', 'techgenyz-social-publisher' ), array( __CLASS__, 'message_field' ), self::PAGE, 'tgsp_main' );
		add_settings_field( 'tgsp_debug_logging', __( 'Debug logging', 'techgenyz-social-publisher' ), array( __CLASS__, 'logging_field' ), self::PAGE, 'tgsp_main' );

		add_settings_section( 'tgsp_platforms', __( 'Platforms and captions', 'techgenyz-social-publisher' ), array( __CLASS__, 'platform_section' ), self::PAGE );
		foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X' ) as $key => $label ) {
			add_settings_field( 'tgsp_enable_' . $key, sprintf( __( '%s enabled', 'techgenyz-social-publisher' ), $label ), array( __CLASS__, 'platform_enabled_field' ), self::PAGE, 'tgsp_platforms', array( 'platform' => $key, 'label' => $label ) );
			add_settings_field( 'tgsp_' . $key . '_caption_template', sprintf( __( '%s caption', 'techgenyz-social-publisher' ), $label ), array( __CLASS__, 'caption_field' ), self::PAGE, 'tgsp_platforms', array( 'platform' => $key ) );
		}
	}

	public static function sanitize_webhook( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$url    = esc_url_raw( $value, array( 'http', 'https' ) );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $url || ! $host || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			add_settings_error( 'tgsp_webhook_url', 'tgsp_invalid_url', __( 'Enter a valid HTTP or HTTPS webhook URL.', 'techgenyz-social-publisher' ) );
			return (string) get_option( 'tgsp_webhook_url', '' );
		}
		return $url;
	}

	public static function sanitize_checkbox( $value ) {
		return ! empty( $value );
	}

	public static function sanitize_delivery_method( $value ) { return in_array( $value, array( 'buffer', 'webhook' ), true ) ? $value : 'buffer'; }
	public static function sanitize_openai_key( $value ) { $value = sanitize_text_field( (string) $value ); return '' === $value ? (string) get_option( 'tgsp_openai_api_key', '' ) : $value; }
	public static function sanitize_openai_model( $value ) { $value = sanitize_text_field( (string) $value ); return '' === $value || self::LEGACY_OPENAI_MODEL === $value ? self::OPENAI_MODEL : $value; }
	public static function sanitize_openai_caption_prompt( $value ) {
		$value = wp_kses_post( (string) $value );
		return '' === TGSP_OpenAI_Client::normalize_caption_prompt( $value ) ? '' : $value;
	}
	public static function sanitize_buffer_key( $value ) { $value = sanitize_text_field( (string) $value ); return '' === $value ? (string) get_option( 'tgsp_buffer_api_key', '' ) : $value; }

	public static function sanitize_secret( $value ) {
		$value = sanitize_text_field( (string) $value );
		return '' === $value ? (string) get_option( 'tgsp_webhook_secret', '' ) : $value;
	}

	public static function webhook_field() {
		printf(
			'<input type="url" class="regular-text code" name="tgsp_webhook_url" value="%s" placeholder="https://" autocomplete="off" />',
			esc_attr( get_option( 'tgsp_webhook_url', '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Use any public HTTP or HTTPS webhook endpoint. The URL is stored server-side and never exposed on the public site. HTTPS is strongly recommended.', 'techgenyz-social-publisher' ) . '</p>';
	}

	public static function delivery_method_field() {
		$value = (string) get_option( 'tgsp_delivery_method', 'buffer' );
		printf( '<select name="tgsp_delivery_method"><option value="buffer" %1$s>%2$s</option><option value="webhook" %3$s>%4$s</option></select>', selected( $value, 'buffer', false ), esc_html__( 'Buffer API - immediate shareNow', 'techgenyz-social-publisher' ), selected( $value, 'webhook', false ), esc_html__( 'Legacy webhook', 'techgenyz-social-publisher' ) );
	}

	private static function masked_key_field( $option, $name, $description ) {
		$has = '' !== (string) get_option( $option, '' );
		printf( '<input type="password" class="regular-text code" name="%1$s" value="" placeholder="%2$s" autocomplete="new-password" /><p class="description">%3$s</p>', esc_attr( $name ), esc_attr( $has ? __( 'Saved - enter a new value to replace', 'techgenyz-social-publisher' ) : __( 'Enter API key', 'techgenyz-social-publisher' ) ), esc_html( $description ) );
	}
	public static function openai_key_field() { self::masked_key_field( 'tgsp_openai_api_key', 'tgsp_openai_api_key', __( 'Stored server-side and never sent to browser JavaScript. TGSP_OPENAI_API_KEY may be defined in wp-config.php instead.', 'techgenyz-social-publisher' ) ); }
	public static function buffer_key_field() { self::masked_key_field( 'tgsp_buffer_api_key', 'tgsp_buffer_api_key', __( 'Stored server-side and never sent to browser JavaScript. TGSP_BUFFER_API_KEY may be defined in wp-config.php instead.', 'techgenyz-social-publisher' ) ); }
	public static function openai_model_field() { printf( '<input type="text" class="regular-text code" name="tgsp_openai_model" value="%s" />', esc_attr( self::openai_model() ) ); }
	public static function openai_caption_prompt_field() {
		$value = (string) get_option( 'tgsp_openai_caption_prompt', '' );
		if ( '' === TGSP_OpenAI_Client::normalize_caption_prompt( $value ) ) {
			$value = TGSP_OpenAI_Client::default_caption_prompt();
		}

		wp_editor(
			$value,
			'tgsp_openai_caption_prompt_editor',
			array(
				'media_buttons' => false,
				'teeny'         => false,
				'textarea_name' => 'tgsp_openai_caption_prompt',
				'textarea_rows' => 18,
				'quicktags'     => true,
			)
		);
		echo '<p class="description">' . esc_html__( 'Controls how OpenAI generates Facebook, LinkedIn, and X captions from the supplied WordPress article. Leave empty to use the built-in default. The plugin continues to enforce its technical output validation.', 'techgenyz-social-publisher' ) . '</p>';
	}
	public static function openai_model() {
		$model = (string) get_option( 'tgsp_openai_model', self::OPENAI_MODEL );
		if ( self::LEGACY_OPENAI_MODEL === $model ) {
			update_option( 'tgsp_openai_model', self::OPENAI_MODEL, false );
			return self::OPENAI_MODEL;
		}
		return '' === trim( $model ) ? self::OPENAI_MODEL : $model;
	}
	public static function buffer_organization_field() { printf( '<input id="tgsp_buffer_organization_id" type="text" class="regular-text code" name="tgsp_buffer_organization_id" value="%s" />', esc_attr( get_option( 'tgsp_buffer_organization_id', '' ) ) ); }
	public static function buffer_channel_field( $args ) { $platform = sanitize_key( $args['platform'] ); $value = (string) get_option( 'tgsp_buffer_channel_' . $platform, '' ); printf( '<select id="tgsp_buffer_channel_%1$s" name="tgsp_buffer_channel_%1$s"><option value="">%2$s</option>%3$s</select>', esc_attr( $platform ), esc_html__( 'Select a channel', 'techgenyz-social-publisher' ), $value ? '<option value="' . esc_attr( $value ) . '" selected>' . esc_html( $value ) . '</option>' : '' ); }
	public static function buffer_section() { echo '<p>' . esc_html__( 'Uses Buffer GraphQL createPost with mode shareNow and automatic publishing. It never calls addToQueue.', 'techgenyz-social-publisher' ) . '</p><p><button type="button" class="button" id="tgsp-test-buffer">' . esc_html__( 'Test Buffer and load channels', 'techgenyz-social-publisher' ) . '</button> <span id="tgsp-test-buffer-result"></span></p>'; }

	public static function secret_field() {
		$has_secret = '' !== (string) get_option( 'tgsp_webhook_secret', '' );
		printf(
			'<input type="password" class="regular-text code" name="tgsp_webhook_secret" value="" placeholder="%s" autocomplete="new-password" />',
			esc_attr( $has_secret ? __( 'Saved - enter a new value to replace', 'techgenyz-social-publisher' ) : __( 'Optional secret', 'techgenyz-social-publisher' ) )
		);
		echo '<p class="description">' . esc_html__( 'Sent as X-Techgenyz-Webhook-Key. Leave blank to retain the saved secret.', 'techgenyz-social-publisher' ) . '</p>';
	}

	public static function message_field() {
		printf(
			'<textarea class="large-text code" rows="7" name="tgsp_message_format">%s</textarea>',
			esc_textarea( get_option( 'tgsp_message_format', "{title}\n\nRead more:\n{url}" ) )
		);
		echo '<p class="description">' . esc_html__( 'Placeholders: {title}, {excerpt}, {url}, {author}, {category}, {featured_image}, {site_name}', 'techgenyz-social-publisher' ) . '</p>';
	}

	public static function logging_field() {
		printf(
			'<label><input type="checkbox" name="tgsp_debug_logging" value="1" %s /> %s</label>',
			checked( (bool) get_option( 'tgsp_debug_logging', false ), true, false ),
			esc_html__( 'Store webhook result details in the plugin log table.', 'techgenyz-social-publisher' )
		);
	}

	public static function platform_section() {
		echo '<p>' . esc_html__( 'Choose which platforms the automation workflow should publish to and customize each caption.', 'techgenyz-social-publisher' ) . '</p>';
	}

	public static function platform_enabled_field( $args ) {
		$platform = sanitize_key( $args['platform'] );
		printf(
			'<input type="hidden" name="tgsp_enable_%1$s" value="0" /><label><input type="checkbox" name="tgsp_enable_%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $platform ),
			checked( (bool) get_option( 'tgsp_enable_' . $platform, true ), true, false ),
			esc_html( sprintf( __( 'Include %s in publishing requests.', 'techgenyz-social-publisher' ), $args['label'] ) )
		);
	}

	public static function caption_field( $args ) {
		$platform = sanitize_key( $args['platform'] );
		$value    = (string) get_option( 'tgsp_' . $platform . '_caption_template', '' );
		if ( '' === $value ) {
			$value = TGSP_Caption_Generator::default_template( $platform );
		}
		printf( '<textarea class="large-text code" rows="6" name="tgsp_%1$s_caption_template">%2$s</textarea>', esc_attr( $platform ), esc_textarea( $value ) );
		echo '<p class="description">' . esc_html__( 'Placeholders: {title}, {excerpt}, {url}, {author}, {category}, {featured_image}, {site_name}', 'techgenyz-social-publisher' ) . '</p>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Social Publisher', 'techgenyz-social-publisher' ); ?></h1>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'For the final workflow, select Buffer API, save both API keys, load and map the three Buffer channels, then use Share from Posts > All Posts.', 'techgenyz-social-publisher' ); ?></p></div>
			<?php $connection = get_option( 'tgsp_webhook_connection_status', array() ); ?>
			<div class="card" style="max-width:800px;padding:16px;margin-bottom:16px;">
				<h2><?php echo esc_html__( 'Webhook connection', 'techgenyz-social-publisher' ); ?></h2>
				<p>
					<button type="button" class="button" id="tgsp-test-webhook"><?php echo esc_html__( 'Test connection', 'techgenyz-social-publisher' ); ?></button>
					<span id="tgsp-test-webhook-result">
						<?php
						if ( is_array( $connection ) && ! empty( $connection['checked_at'] ) ) {
							echo esc_html( sprintf( __( 'Last check: %1$s - %2$s', 'techgenyz-social-publisher' ), $connection['checked_at'], isset( $connection['message'] ) ? $connection['message'] : '' ) );
						}
						?>
					</span>
				</p>
				<?php $last_success = (string) get_option( 'tgsp_webhook_last_success', '' ); ?>
				<?php if ( $last_success ) : ?><p><?php echo esc_html( sprintf( __( 'Last successful delivery: %s UTC', 'techgenyz-social-publisher' ), $last_success ) ); ?></p><?php endif; ?>
			</div>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'tgsp_settings' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
