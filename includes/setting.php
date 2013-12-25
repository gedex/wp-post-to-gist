<?php
/**
 * Class that handles setting page of the plugin.
 *
 * @author Akeda Bagus <admin@gedex.web.id>
 * @since  0.1.0
 */
class Post_To_Gist_Setting implements Post_To_Gist_Component_Interface {

	/**
	 * Page where this setting is rendered.
	 */
	const PAGE_SLUG = 'writing';

	/**
	 * Fields on writing setting.
	 *
	 * @var    array
	 * @access private
	 */
	private $fields;

	/**
	 * Plugin instance.
	 *
	 * @var    Post_To_Gist
	 * @access private
	 */
	private $plugin;

	/**
	 * Callback which fired by plugin instance.
	 *
	 * Register settings into options-writings.php page and register AJAX handler
	 * to test access token.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function load( Post_To_Gist $plugin ) {
		$this->plugin = $plugin;
		$field_prefix = $plugin->name . '_';
		$this->fields = array(
			$field_prefix . 'github_username' => array(
				'title'   => __( 'GitHub Username', 'post-to-gist' ),
				'type'    => 'text',
				'pattern' => '[a-zA-Z0-9_]+',
			),
			$field_prefix . 'github_access_token' => array(
				'title'    => __( 'GitHub Access Token', 'post-to-gist' ),
				'type'     => 'access_token',
				'pattern'  => '[a-zA-Z0-9_-]+',
				'desc'     =>  sprintf( __( 'You can retrieve personal access token in <a href="%s" target="_blank">here</a>.', 'post_to-gist' ), esc_url( 'https://github.com/settings/tokens/new' ) ),
			),
			$field_prefix . 'enabled_post_types' => array(
				'title'     => __( 'Enabled Post Types', 'post-to-gist' ),
				'type'      => 'post_types',
				'sanitizer' => array( $this, 'sanitize_post_types' ),
			),
		);

		// Register settings, secion and filds on writing setting page.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Enqueue JS for testing access_token.
		add_action( 'load-options-writing.php', array( $this, 'enqueue_check_access_token_js' ) );

		// AJAX handler to check if stored access token can be used to make a call.
		add_action( 'wp_ajax_check_github_access_token', array( $this, 'ajax_check_github_access_token' ) );
	}

	/**
	 * Register settings (section and fields).
	 *
	 * @since  0.1.0
	 * @action admin_init
	 * @return void
	 */
	public function register_settings() {
		$page    = self::PAGE_SLUG;
		$section = $this->plugin->name;

		add_settings_section(
			$section,
			__( 'Post to Gist', 'post-to-gist' ),
			null,
			$page
		);

		$this->fields = apply_filters( 'post_to_git_fields', $this->fields );
		foreach ( $this->fields as $field => $properties ) {
			$args = array(
				'name'        => $field,
				'id'          => $field,
				'label_for'   => $field,
				'type'        => isset( $properties['type'] ) ? $properties['type'] : 'text',
				'desc'        => isset( $properties['desc'] ) ? $properties['desc'] : '',
				'value'       => get_option( $field ),
				'pattern'     => isset( $properties['pattern'] ) ? $properties['pattern'] : '',
				'placeholder' => isset( $properties['pattern'] ) ? $properties['pattern'] : '',
			);

			$field_renderer = array( $this, 'render_field_' . $properties['type'] );
			if ( isset( $properties['renderer'] ) && is_callable( $properties['renderer'] ) ) {
				$field_renderer = $properties['renderer'];
			}

			// Check field sanitizer.
			if ( isset( $properties['sanitizer'] ) && is_callable( $properties['sanitizer'] ) ) {
				$sanitizer = $properties['sanitizer'];
			} else if ( ! empty( $properties['pattern'] ) ) {
				$pattern  = $properties['pattern'];
				$sanitizer = function( $value ) use( $pattern ) {
					$regex = '#^(' . $pattern . ')$#';
					$value = sanitize_text_field( $value );
					if ( ! preg_match( $regex, $value ) ) {
						$value = '';
					}
					return $value;
				};
			} else {
				$sanitizer = 'sanitize_text_field';
			}

			add_settings_field( $field, $properties['title'], $field_renderer, $page, $section, $args );
			register_setting( $page, $field, $sanitizer );
		}
	}

	/**
	 * Callback for `add_settings_field` where field's type is text.
	 *
	 * @since  0.1.0
	 * @param  array $args Field args passed to `add_settings_field` call
	 * @return void
	 */
	public static function render_field_text( $args ) {

		extract( wp_parse_args( $args, array(
			'type'        => 'text',
			'pattern'     => '',
			'placeholder' => '',
		) ) );
		/**
		 * @var string $type
		 * @var string $pattern
		 * @var string $placeholder
		 * @var string $name
		 * @var string $id
		 * @var string $value
		 * @var string $desc
		 */
		?>
		<input
			type="<?php echo esc_attr( $type ) ?>"
			name="<?php echo esc_attr( $name ) ?>"
			id="<?php echo esc_attr( $id ) ?>"
			class="regular-text"
			<?php if ( $pattern ): ?>
				pattern="<?php echo esc_attr( $pattern ) ?>"
			<?php endif; ?>
			placeholder="<?php echo esc_attr( $placeholder ) ?>"
			value="<?php echo esc_attr( $value ); ?>">
		<?php if ( $desc ): ?>
			<p class="description"><?php echo $desc; ?></p>
		<?php endif;
	}

	/**
	 * Callback for `add_settings_field` where field's type is access_token.
	 *
	 * @since  0.1.0
	 * @param  array $args Field args passed to `add_settings_field` call
	 * @return void
	 */
	public function render_field_access_token( $args ) {
		$text_args = $args;
		$text_args['type'] = 'text';
		$this->render_field_text( $text_args );
		?>
		<br>
		<div style="width: 170px; height: 20px;">
			<input id="<?php echo esc_attr( $this->plugin->name . '_test_token' ); ?>" type="button" class="button" value="<?php _e( 'Test access token', 'post-to-gist' ); ?>" style="float: left">
			<div class="spinner" style="display: none"></div>
		</div>
		<br>
		<div id="<?php echo esc_attr( $this->plugin->name . '_test_token_response' ); ?>">
		</div>
		<?php
	}

	/**
	 * Callback for `add_settings_field` where field's type is post_types.
	 *
	 * @since  0.1.0
	 * @param  array $args Field args passed to `add_settings_field` call
	 * @return void
	 */
	public function render_field_post_types( $args ) {
		$post_types = get_post_types( array(
			'public'  => true,
			'show_ui' => true,
		), 'objects' );

		// Excludes attachment.
		$post_types = array_filter( $post_types, function( $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				return false;
			} else {
				return true;
			}
		} );

		$post_types = apply_filters( $this->plugin->name . '_post_types', $post_types );

		extract( wp_parse_args( $args, array(
			'type'        => 'checkbox',
			'pattern'     => '',
			'placeholder' => '',
			'desc'        => '',
		) ) );
		/**
		 * @var string $type
		 * @var string $pattern
		 * @var string $placeholder
		 * @var string $name
		 * @var string $id
		 * @var string $value
		 * @var string $desc
		 */
		?>
		<fieldset>
		<legend class="screen-reader-text"><span><?php echo esc_html( $name ); ?></span></legend>
		<?php foreach ( $post_types as $post_type ) : ?>
			<?php $cb_name = esc_attr( $name . '[' . $post_type->name . ']' ); ?>
			<label for="<?php echo $cb_name; ?>">
				<input
					type="checkbox"
					value="1"
					id="<?php echo $cb_name ?>"
					name="<?php echo $cb_name ?>"
					<?php checked( ( isset( $value[ $post_type->name ] ) && $value[ $post_type->name ] ) ); ?>>
				<?php echo esc_html( $post_type->label ); ?>
			</label><br>
		<?php endforeach; ?>
		</fieldset>
		<?php if ( $desc ): ?>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
		<?php endif;
	}

	/**
	 * Sanitizer for field with type post_types.
	 *
	 * @since  0.1.0
	 * @param  array $post_types Post types
	 * @return array
	 */
	public function sanitize_post_types( $post_types ) {
		foreach ( $post_types as $post_type => $val ) {
			if ( ! post_type_exists( $post_type ) ) {
				unset( $post_types[ $key ] );
			}
		}

		return $post_types;
	}

	/**
	 * Enqueue JS to be used by access_token checker.
	 *
	 * @since  0.1.0
	 * @action load-options-writing.php
	 * @return void
	 */
	public function enqueue_check_access_token_js() {
		wp_enqueue_script(
			$this->plugin->name . '-check-access-token',
			P2GIST_JS_URL . 'check-access-token.js',
			array( 'jquery' ),
			$this->plugin->version,
			false
		);

		global $wp_scripts;
		$data = sprintf(
			'var post_to_gist_access_token_checker = %s',
			json_encode( array(
				'nonce' => wp_create_nonce( 'check_github_access_token' ),
			) )
		);
		$wp_scripts->add_data( $this->plugin->name . '-check-access-token', 'data', $data );
	}

	/**
	 * AJAX handler to check access token.
	 *
	 * @since  0.1.0
	 * @action wp_ajax_check_github_access_token
	 * @return void
	 */
	public function ajax_check_github_access_token() {
		try {
			if ( ! isset( $_REQUEST[ 'user' ] ) || ! isset( $_REQUEST[ 'access_token' ] ) ) {
				throw new Exception( __( 'Missing GitHub username or access token', 'post-to-gist' ) );
			}

			$user  = $_REQUEST[ 'user' ];
			$token = $_REQUEST[ 'access_token' ];
			if ( ! $user || ! $token ) {
				throw new Exception( __( 'Invalid GitHub username or access token value', 'post-to-gist' ) );
			}

			if ( ! isset( $_REQUEST['nonce'] ) ) {
				throw new Exception( __( 'Missing nonce', 'post-to-gist' ) );
			}

			if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'check_github_access_token' ) ) {
				throw new Exception( __( 'Malformed value for nonce', 'post-to-gist' ) );
			}

			$client = $this->plugin->get_component_instance( 'client' );
			$user   = $client->get_user( $user, $token );

			wp_send_json_success( compact( 'user' ) );

		} catch ( Exception $e ) {
			$status_code = 500;
			$message = $e->getMessage();
			if ( ! $message ) {
				$message = __( 'Unexpected response', 'post-to-gist' );
			}

			status_header( 500 );
			wp_send_json_error( $message );
		}
	}

	/**
	 * Gets field value.
	 *
	 * @since  0.1.0
	 * @param  string $field Field's name (with or without plugin prefix)
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function get_field( $field, $default = false ) {
		$field_prefix = $this->plugin->name . '_';

		if ( false === strpos( $field_prefix, $field ) ) {
			$field = $field_prefix . $field;
		}

		$value = get_option( $field );
		if ( ! $value ) {
			return $default;
		}
		return $value;
	}
}
