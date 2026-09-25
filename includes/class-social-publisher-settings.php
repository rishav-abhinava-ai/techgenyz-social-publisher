<?php
/**
 * Settings page.
 *
 * @package AISocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISP_Settings {
	const PAGE = 'aisp-settings';
	const OPENAI_MODEL = 'gpt-6-luna';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function add_page() {
		add_menu_page(
			__( 'AI Social Publisher', 'ai-social-publisher' ),
			__( 'AI Social Publisher', 'ai-social-publisher' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-share',
			58
		);

		add_options_page(
			__( 'AI Social Publisher', 'ai-social-publisher' ),
			__( 'AI Social Publisher', 'ai-social-publisher' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting( 'aisp_settings', 'aisp_delivery_method', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_delivery_method' ), 'default' => 'buffer' ) );
		register_setting( 'aisp_settings', 'aisp_openai_api_key', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_openai_key' ), 'default' => '' ) );
		register_setting( 'aisp_settings', 'aisp_openai_model', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_openai_model' ), 'default' => self::OPENAI_MODEL ) );
		register_setting( 'aisp_settings', 'aisp_openai_caption_prompt', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_openai_caption_prompt' ), 'default' => '' ) );
		register_setting( 'aisp_settings', 'aisp_buffer_api_key', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_buffer_key' ), 'default' => '' ) );
		register_setting( 'aisp_settings', 'aisp_buffer_organization_id', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		foreach ( array( 'facebook', 'linkedin', 'x' ) as $platform ) {
			register_setting( 'aisp_settings', 'aisp_buffer_channel_' . $platform, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		}
		register_setting(
			'aisp_settings',
			'aisp_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_webhook' ),
				'default'           => '',
			)
		);
		foreach ( array( 'facebook', 'linkedin', 'x' ) as $platform ) {
			register_setting(
				'aisp_settings',
				'aisp_enable_' . $platform,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
					'default'           => true,
				)
			);
			register_setting(
				'aisp_settings',
				'aisp_' . $platform . '_caption_template',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'default'           => '',
				)
			);
		}
		register_setting(
			'aisp_settings',
			'aisp_message_format',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => "{title}\n\nRead more:\n{url}",
			)
		);
		register_setting(
			'aisp_settings',
			'aisp_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_secret' ),
				'default'           => '',
			)
		);

		register_setting(
			'aisp_settings',
			'aisp_debug_logging',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_section( 'aisp_workflow', __( 'Publishing workflow', 'ai-social-publisher' ), '__return_false', self::PAGE );
		add_settings_field( 'aisp_delivery_method', __( 'Delivery method', 'ai-social-publisher' ), array( __CLASS__, 'delivery_method_field' ), self::PAGE, 'aisp_workflow' );
		add_settings_section( 'aisp_openai', __( 'OpenAI caption generation', 'ai-social-publisher' ), '__return_false', self::PAGE );
		add_settings_field( 'aisp_openai_api_key', __( 'OpenAI API key', 'ai-social-publisher' ), array( __CLASS__, 'openai_key_field' ), self::PAGE, 'aisp_openai' );
		add_settings_field( 'aisp_openai_model', __( 'OpenAI model', 'ai-social-publisher' ), array( __CLASS__, 'openai_model_field' ), self::PAGE, 'aisp_openai' );
		add_settings_field( 'aisp_openai_caption_prompt', __( 'Caption generation prompt', 'ai-social-publisher' ), array( __CLASS__, 'openai_caption_prompt_field' ), self::PAGE, 'aisp_openai' );
		add_settings_section( 'aisp_buffer', __( 'Buffer immediate publishing', 'ai-social-publisher' ), array( __CLASS__, 'buffer_section' ), self::PAGE );
		add_settings_field( 'aisp_buffer_api_key', __( 'Buffer API key', 'ai-social-publisher' ), array( __CLASS__, 'buffer_key_field' ), self::PAGE, 'aisp_buffer' );
		add_settings_field( 'aisp_buffer_organization_id', __( 'Buffer organization ID', 'ai-social-publisher' ), array( __CLASS__, 'buffer_organization_field' ), self::PAGE, 'aisp_buffer' );
		foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X' ) as $key => $label ) {
			add_settings_field( 'aisp_buffer_channel_' . $key, sprintf( __( '%s Buffer channel', 'ai-social-publisher' ), $label ), array( __CLASS__, 'buffer_channel_field' ), self::PAGE, 'aisp_buffer', array( 'platform' => $key ) );
		}
		add_settings_section( 'aisp_main', __( 'Legacy webhook connection', 'ai-social-publisher' ), '__return_false', self::PAGE );
		add_settings_field( 'aisp_webhook_url', __( 'Webhook URL', 'ai-social-publisher' ), array( __CLASS__, 'webhook_field' ), self::PAGE, 'aisp_main' );
		add_settings_field( 'aisp_webhook_secret', __( 'Webhook Secret Key', 'ai-social-publisher' ), array( __CLASS__, 'secret_field' ), self::PAGE, 'aisp_main' );
		add_settings_field( 'aisp_message_format', __( 'Default social message', 'ai-social-publisher' ), array( __CLASS__, 'message_field' ), self::PAGE, 'aisp_main' );
		add_settings_field( 'aisp_debug_logging', __( 'Debug logging', 'ai-social-publisher' ), array( __CLASS__, 'logging_field' ), self::PAGE, 'aisp_main' );

		add_settings_section( 'aisp_platforms', __( 'Platforms and captions', 'ai-social-publisher' ), array( __CLASS__, 'platform_section' ), self::PAGE );
		foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X' ) as $key => $label ) {
			add_settings_field( 'aisp_enable_' . $key, sprintf( __( '%s enabled', 'ai-social-publisher' ), $label ), array( __CLASS__, 'platform_enabled_field' ), self::PAGE, 'aisp_platforms', array( 'platform' => $key, 'label' => $label ) );
			add_settings_field( 'aisp_' . $key . '_caption_template', sprintf( __( '%s caption', 'ai-social-publisher' ), $label ), array( __CLASS__, 'caption_field' ), self::PAGE, 'aisp_platforms', array( 'platform' => $key ) );
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
			add_settings_error( 'aisp_webhook_url', 'aisp_invalid_url', __( 'Enter a valid HTTP or HTTPS webhook URL.', 'ai-social-publisher' ) );
			return (string) get_option( 'aisp_webhook_url', '' );
		}
		return $url;
	}

	public static function sanitize_checkbox( $value ) {
		return ! empty( $value );
	}

	public static function sanitize_delivery_method( $value ) { return in_array( $value, array( 'buffer', 'webhook' ), true ) ? $value : 'buffer'; }
	public static function sanitize_openai_key( $value ) { $value = sanitize_text_field( (string) $value ); return '' === $value ? (string) get_option( 'aisp_openai_api_key', '' ) : $value; }
	public static function sanitize_openai_model( $value ) {
		$model = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $model ) {
			add_settings_error( 'aisp_openai_model', 'aisp_openai_model_required', __( 'Enter an OpenAI model ID.', 'ai-social-publisher' ) );
			return self::openai_model();
		}
		return $model;
	}
	public static function sanitize_openai_caption_prompt( $value ) {
		$value = wp_kses_post( (string) $value );
		return '' === AISP_OpenAI_Client::normalize_caption_prompt( $value ) ? '' : $value;
	}
	public static function sanitize_buffer_key( $value ) { $value = sanitize_text_field( (string) $value ); return '' === $value ? (string) get_option( 'aisp_buffer_api_key', '' ) : $value; }

	public static function sanitize_secret( $value ) {
		$value = sanitize_text_field( (string) $value );
		return '' === $value ? (string) get_option( 'aisp_webhook_secret', '' ) : $value;
	}

	public static function webhook_field() {
		printf(
			'<input type="url" class="regular-text code" name="aisp_webhook_url" value="%s" placeholder="https://" autocomplete="off" />',
			esc_attr( get_option( 'aisp_webhook_url', '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Use any public HTTP or HTTPS webhook endpoint. The URL is stored server-side and never exposed on the public site. HTTPS is strongly recommended.', 'ai-social-publisher' ) . '</p>';
	}

	public static function delivery_method_field() {
		$value = (string) get_option( 'aisp_delivery_method', 'buffer' );
		printf( '<select name="aisp_delivery_method"><option value="buffer" %1$s>%2$s</option><option value="webhook" %3$s>%4$s</option></select>', selected( $value, 'buffer', false ), esc_html__( 'Buffer API - immediate shareNow', 'ai-social-publisher' ), selected( $value, 'webhook', false ), esc_html__( 'Legacy webhook', 'ai-social-publisher' ) );
	}

	private static function masked_key_field( $option, $name, $description ) {
		$has = '' !== (string) get_option( $option, '' );
		printf( '<input type="password" class="regular-text code" name="%1$s" value="" placeholder="%2$s" autocomplete="new-password" /><p class="description">%3$s</p>', esc_attr( $name ), esc_attr( $has ? __( 'Saved - enter a new value to replace', 'ai-social-publisher' ) : __( 'Enter API key', 'ai-social-publisher' ) ), esc_html( $description ) );
	}
	public static function openai_key_field() { self::masked_key_field( 'aisp_openai_api_key', 'aisp_openai_api_key', __( 'Stored server-side and never sent to browser JavaScript. AISP_OPENAI_API_KEY may be defined in wp-config.php instead.', 'ai-social-publisher' ) ); }
	public static function buffer_key_field() { self::masked_key_field( 'aisp_buffer_api_key', 'aisp_buffer_api_key', __( 'Stored server-side and never sent to browser JavaScript. AISP_BUFFER_API_KEY may be defined in wp-config.php instead.', 'ai-social-publisher' ) ); }
	public static function openai_model_field() {
		printf( '<input type="text" class="regular-text code" name="aisp_openai_model" value="%s" />', esc_attr( self::openai_model() ) );
		echo '<p class="description">' . esc_html__( 'Enter the exact OpenAI model ID to use for caption generation. You can change the model here without modifying plugin code.', 'ai-social-publisher' ) . '</p>';
	}
	public static function openai_caption_prompt_field() {
		$value = (string) get_option( 'aisp_openai_caption_prompt', '' );
		if ( '' === AISP_OpenAI_Client::normalize_caption_prompt( $value ) ) {
			$value = AISP_OpenAI_Client::default_caption_prompt();
		}

		wp_editor(
			$value,
			'aisp_openai_caption_prompt_editor',
			array(
				'media_buttons' => false,
				'teeny'         => false,
				'textarea_name' => 'aisp_openai_caption_prompt',
				'textarea_rows' => 18,
				'quicktags'     => true,
			)
		);
		echo '<p class="description">' . esc_html__( 'Controls how OpenAI generates Facebook, LinkedIn, and X captions from the supplied WordPress article. Leave empty to use the built-in default. The plugin continues to enforce its technical output validation.', 'ai-social-publisher' ) . '</p>';
	}
	public static function openai_model() {
		$model = trim( (string) get_option( 'aisp_openai_model', self::OPENAI_MODEL ) );
		return '' === $model ? self::OPENAI_MODEL : $model;
	}
	public static function buffer_organization_field() { printf( '<input id="aisp_buffer_organization_id" type="text" class="regular-text code" name="aisp_buffer_organization_id" value="%s" />', esc_attr( get_option( 'aisp_buffer_organization_id', '' ) ) ); }
	public static function buffer_channel_field( $args ) { $platform = sanitize_key( $args['platform'] ); $value = (string) get_option( 'aisp_buffer_channel_' . $platform, '' ); printf( '<select id="aisp_buffer_channel_%1$s" name="aisp_buffer_channel_%1$s"><option value="">%2$s</option>%3$s</select>', esc_attr( $platform ), esc_html__( 'Select a channel', 'ai-social-publisher' ), $value ? '<option value="' . esc_attr( $value ) . '" selected>' . esc_html( $value ) . '</option>' : '' ); }
	public static function buffer_section() { echo '<p>' . esc_html__( 'Uses Buffer GraphQL createPost with mode shareNow and automatic publishing. It never calls addToQueue.', 'ai-social-publisher' ) . '</p><p><button type="button" class="button" id="aisp-test-buffer">' . esc_html__( 'Test Buffer and load channels', 'ai-social-publisher' ) . '</button> <span id="aisp-test-buffer-result"></span></p>'; }

	public static function secret_field() {
		$has_secret = '' !== (string) get_option( 'aisp_webhook_secret', '' );
		printf(
			'<input type="password" class="regular-text code" name="aisp_webhook_secret" value="" placeholder="%s" autocomplete="new-password" />',
			esc_attr( $has_secret ? __( 'Saved - enter a new value to replace', 'ai-social-publisher' ) : __( 'Optional secret', 'ai-social-publisher' ) )
		);
		echo '<p class="description">' . esc_html__( 'Sent as X-Techgenyz-Webhook-Key. Leave blank to retain the saved secret.', 'ai-social-publisher' ) . '</p>';
	}

	public static function message_field() {
		printf(
			'<textarea class="large-text code" rows="7" name="aisp_message_format">%s</textarea>',
			esc_textarea( get_option( 'aisp_message_format', "{title}\n\nRead more:\n{url}" ) )
		);
		echo '<p class="description">' . esc_html__( 'Placeholders: {title}, {excerpt}, {url}, {author}, {category}, {featured_image}, {site_name}', 'ai-social-publisher' ) . '</p>';
	}

	public static function logging_field() {
		printf(
			'<label><input type="checkbox" name="aisp_debug_logging" value="1" %s /> %s</label>',
			checked( (bool) get_option( 'aisp_debug_logging', false ), true, false ),
			esc_html__( 'Store webhook result details in the plugin log table.', 'ai-social-publisher' )
		);
	}

	public static function platform_section() {
		echo '<p>' . esc_html__( 'Choose which platforms the automation workflow should publish to and customize each caption.', 'ai-social-publisher' ) . '</p>';
	}

	public static function platform_enabled_field( $args ) {
		$platform = sanitize_key( $args['platform'] );
		printf(
			'<input type="hidden" name="aisp_enable_%1$s" value="0" /><label><input type="checkbox" name="aisp_enable_%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $platform ),
			checked( (bool) get_option( 'aisp_enable_' . $platform, true ), true, false ),
			esc_html( sprintf( __( 'Include %s in publishing requests.', 'ai-social-publisher' ), $args['label'] ) )
		);
	}

	public static function caption_field( $args ) {
		$platform = sanitize_key( $args['platform'] );
		$value    = (string) get_option( 'aisp_' . $platform . '_caption_template', '' );
		if ( '' === $value ) {
			$value = AISP_Caption_Generator::default_template( $platform );
		}
		printf( '<textarea class="large-text code" rows="6" name="aisp_%1$s_caption_template">%2$s</textarea>', esc_attr( $platform ), esc_textarea( $value ) );
		echo '<p class="description">' . esc_html__( 'Placeholders: {title}, {excerpt}, {url}, {author}, {category}, {featured_image}, {site_name}', 'ai-social-publisher' ) . '</p>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'AI Social Publisher', 'ai-social-publisher' ); ?></h1>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'For the final workflow, select Buffer API, save both API keys, load and map the three Buffer channels, then use Share from Posts > All Posts.', 'ai-social-publisher' ); ?></p></div>
			<?php $connection = get_option( 'aisp_webhook_connection_status', array() ); ?>
			<div class="card" style="max-width:800px;padding:16px;margin-bottom:16px;">
				<h2><?php echo esc_html__( 'Webhook connection', 'ai-social-publisher' ); ?></h2>
				<p>
					<button type="button" class="button" id="aisp-test-webhook"><?php echo esc_html__( 'Test connection', 'ai-social-publisher' ); ?></button>
					<span id="aisp-test-webhook-result">
						<?php
						if ( is_array( $connection ) && ! empty( $connection['checked_at'] ) ) {
							echo esc_html( sprintf( __( 'Last check: %1$s - %2$s', 'ai-social-publisher' ), $connection['checked_at'], isset( $connection['message'] ) ? $connection['message'] : '' ) );
						}
						?>
					</span>
				</p>
				<?php $last_success = (string) get_option( 'aisp_webhook_last_success', '' ); ?>
				<?php if ( $last_success ) : ?><p><?php echo esc_html( sprintf( __( 'Last successful delivery: %s UTC', 'ai-social-publisher' ), $last_success ) ); ?></p><?php endif; ?>
			</div>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'aisp_settings' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
