<?php
/**
 * Class that handles meta box registration and post meta updating
 * every time a post is saved.
 *
 * @author Akeda Bagus <admin@gedex.web.id>
 * @since  0.1.0
 */
class Post_To_Gist_Metabox implements Post_To_Gist_Component_Interface {

	/**
	 * Plugin instance.
	 *
	 * @var    object Instance of Post_To_Gist
	 * @access private
	 */
	private $plugin;

	/**
	 * Register hooks to be able to:
	 *
	 * - Adds meta box
	 * - Updates the meta after save the post into Gist.
	 *
	 * @since  0.1.0
	 * @param  Post_To_Gist $plugin Plugin instance
	 * @return void
	 */
	public function load( Post_To_Gist $plugin ) {
		$this->plugin = $plugin;

		if ( is_admin() ) {
			add_action( 'load-post.php',     array( $this, 'hooks' ) );
			add_action( 'load-post-new.php', array( $this, 'hooks' ) );
		}
	}

	/**
	 * Hooks when is_admin.
	 *
	 * Creates meta box foreach enabled post types and adds save_post
	 * callback that do the real job (save post into Gist).
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post',      array( $this, 'save_post' ) );
	}

	/**
	 * Adds meta box.
	 *
	 * @since  0.1.0
	 * @action add_meta_boxes
	 * @param  string Post type
	 * @return void
	 */
	public function add_meta_box( $post_type ) {
		$setting    = $this->plugin->get_component_instance( 'setting' );
		$post_types = $setting->get_field( 'enabled_post_types' );

		foreach ( $post_types as $post_type => $enabled ) {
			if ( ! $enabled ) {
				continue;
			}

			add_meta_box(
				$this->plugin->name . '_metabox',
				__( 'Post to Gist', 'post-to-gist' ),
				array( $this, 'render_meta_box_content' ),
				$post_type,
				'advanced',
				'high'
			);
		}
	}

	/**
	 * Saves the meta when the post is saved.
	 *
	 * @since  0.1.0
	 * @action save_post
	 * @return void
	 */
	public function save_post( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		$cap = 'edit_post';
		if ( 'page' === get_post_type( $post_id ) )
			$cap = 'edit_page';

		if ( ! current_user_can( $cap, $post_id ) )
			return $post_id;

		if ( wp_is_post_revision( $post_id ) )
			$post_id = wp_get_post_parent_id( $post_id );

		$post_obj = get_post( $post_id );
		if ( empty( $post_obj->post_title ) || empty( $post_obj->post_content ) )
			return $post_id;

		// Gist meta contains decode JSON from: GET /gists/:id
		// @see http://developer.github.com/v3/gists/#get-a-single-gist
		$gist_meta = get_post_meta( $post_id, $this->plugin->name . '_meta', true );

		// Error meta contains a error message retrieved from previous request.
		// If this current request successes, error_meta will be deleted.
		$error_meta = get_post_meta( $post_id, $this->plugin->name . '_meta_error', true );

		// Get setting component from plugin.
		$setting = $this->plugin->get_component_instance( 'setting' );

		// Credentials before making a request to GitHub API.
		$user  = $setting->get_field( 'github_username' );
		$token = $setting->get_field( 'github_access_token' );

		// Get client component to make a call to /gists endpoint.
		$client = $this->plugin->get_component_instance( 'client' );

		// Data as body of the request.
		// @link http://developer.github.com/v3/gists/#create-a-gist
		// @link http://developer.github.com/v3/gists/#edit-a-gist
		$data = apply_filters( $this->plugin->name . '_saved_data', array(
			'description' => get_the_title( $post_id ),
			'public'      => $post_obj->post_status === 'publish' ? true : false,
			'files'       => array(
				'post-' . $post_id . '.txt' => array(
					'content' => $post_obj->post_content,
				),
				'post-' . $post_id . '.json' => array(
					'content' => json_encode( get_post( $post_id, 'ARRAY_A' ) ),
				),
				// @todo Post meta and featured image maybe?
			),
		) );

		if ( ! $gist_meta ) {
			// Creates Gist for the first time.
			$params   = array( $token, $data );
			$callback = array( $client, 'create_gist' );
		} else {
			// Updates Gist.
			$params   = array( $gist_meta['id'], $token, $data );
			$callback = array( $client, 'edit_gist' );
		}

		$error_message = '';
		try {
			$resp = call_user_func_array( $callback , $params );
			if ( ! isset( $resp['id'] ) ) {
				throw new Exception( __( 'Unexpected response format (missing Gist ID)', 'post-to-gist' ) );
			}

			// Now lets get saved Gist so that we can store it as meta.
			$callback = array( $client, 'get_gist' );
			$params   = array( $resp['id'], $token );
			$resp     = call_user_func_array( $callback, $params );
			if ( ! isset( $resp['id'] ) ) {
				throw new Exception( __( 'Unexpected response format (missing Gist ID)', 'post-to-gist' ) );
			}

			// Too much information if we put everything into meta.
			$excluded = array( 'description', 'files', 'forks', 'history' );
			foreach ( $resp as $key => $val ) {
				if ( in_array( $key, $excluded ) ) {
					unset( $resp[ $key ] );
				}
			}
			update_post_meta( $post_id, $this->plugin->name . '_meta', $resp );

		} catch ( Exception $e ) {

			$error_message = $e->getMessage();
			if ( ! $error_message ) {
				$error_message = __( 'Unexpected response', 'post-to-gist' );
			}
		}

		if ( $error_message ) {
			update_post_meta( $post_id, $this->plugin->name . '_meta_error', $error_message );
		} else {
			delete_post_meta( $post_id, $this->plugin->name . '_meta_error' );
		}
	}

	/**
	 * Renders meta box content.
	 *
	 * @since  0.1.0
	 * @param  WP_Post $post The post object.
	 * @return void
	 */
	public function render_meta_box_content( $post ) {
		$setting    = $this->plugin->get_component_instance( 'setting' );
		$user       = $setting->get_field( 'github_username' );
		$token      = $setting->get_field( 'github_access_token' );
		$user_info  = array();

		try {
			$client    = $this->plugin->get_component_instance( 'client' );
			$user_info = $client->get_user( $user, $token );
		} catch ( Exception $e ) {
			$user_info = array(
				'html_url' => '#',
			);

			$error_message = $e->getMessage();
			if ( ! $error_message ) {
				$error_message = __( 'Unexpected response during user information retrieval', 'post-to-gist' );
			}
			update_post_meta( $post->ID, $this->plugin->name . '_meta_error', $error_message );
		}
		$gist_meta  = get_post_meta( $post->ID, $this->plugin->name . '_meta', true );
		$error_meta = get_post_meta( $post->ID, $this->plugin->name . '_meta_error', true );
		$user_link  = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $user_info['html_url'] ), esc_html( $user ) );
		?>

		<?php if ( $error_meta ) : ?>
			<p>
				<?php _e( 'We got error from Gist client: ', 'post_to_gist' ); ?>
				<span style="color: red; font-style: italic;"><?php echo esc_html( $error_meta ); ?></span>
			</p>
		<?php endif; ?>

		<?php if ( ! $gist_meta ) : ?>
			<p><?php _e( 'Gist is not created yet.', 'post-to-gist' ); ?></p>
			<p><?php echo sprintf( __( 'Gist will be created under %s account', 'post-to-gist' ), $user_link ); ?></p>
		<?php else : ?>
			<p>
				<?php
				$gist_url = isset( $gist_meta['html_url'] ) ? $gist_meta['html_url'] : '#';
				printf( __( 'This post is saved into <a href="%s" target="_blank">this Gist</a>.', 'post_to_gist' ), $gist_url );
				?>
			</p>

			<p><?php _e( 'Information about the Gist:', 'post_to_gist' ); ?></p>
			<div style="overflow: scroll;">
				<?php printf( '<pre>%s</pre>', print_r( $gist_meta, true ) ); ?>
			</div>
		<?php endif;
	}
}
