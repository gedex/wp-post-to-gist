<?php
/**
 * Class that acts as GitHub API client.
 *
 * @author Akeda Bagus <admin@gedex.web.id>
 * @since  0.1.0
 */
class Post_To_Gist_Client implements Post_To_Gist_Component_Interface {
	/**
	 * GitHub API Base URL.
	 *
	 * @link http://developer.github.com/v3/
	 */
	const BASE_URL = 'https://api.github.com';

	/**
	 * Plugin instance.
	 *
	 * @var    object Instance of Post_To_Gist
	 * @access private
	 */
	private $plugin;

	/**
	 * Callback fired by plugin instance. Doesn't do anything special.
	 *
	 * @since  0.1.0
	 * @param  Post_To_Gist $plugin Plugin instance
	 * @return void
	 */
	public function load( Post_To_Gist $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Makes a request to retrieve user info from a given username and its access_token.
	 *
	 * Exceptions are not caught.
	 *
	 * @since  0.1.0
	 * @param  string $username     GitHub username
	 * @param  string $access_token Access token
	 * @return array Result
	 *
	 * @throws Exception
	 */
	public function get_user( $username, $access_token ) {
		$url = add_query_arg( compact( 'access_token' ), trailingslashit( self::BASE_URL ) . 'user' );
		return $this->_get( $url );
	}

	/**
	 * Makes a request to get a Gist from a given Gist ID and access_token.
	 *
	 * Exceptions are not caught.
	 *
	 * @since  0.1.0
	 * @param  int    $id           Gist ID
	 * @param  string $access_token Access token
	 * @return array Result
	 *
	 * @throws Exception
	 */
	public function get_gist( $id, $access_token ) {
		$url = add_query_arg( compact( 'access_token' ), trailingslashit( self::BASE_URL ) . 'gists/' . $id );
		return $this->_get( $url );
	}

	/**
	 * Makes a request to create a Gist with a given data and access_token.
	 *
	 * Exceptions are not caught.
	 *
	 * @since  0.1.0
	 * @param  string $access_token Access token
	 * @param  array  $data
	 * @return array  Result
	 *
	 * @throws Exception
	 */
	public function create_gist( $access_token, $data ) {
		$url = add_query_arg( compact( 'access_token' ), trailingslashit( self::BASE_URL ) . 'gists' );
		return $this->_post( $url, $data );
	}


	/**
	 * Makes a request to edit a Gist with a given id, data, and access_token.
	 *
	 * Exceptions are not caught.
	 *
	 * @since  0.1.0
	 * @param  int    $id           Gist ID
	 * @param  string $access_token Access token
	 * @param  array  $data
	 * @return array  Result
	 *
	 * @throws Exception
	 */
	public function edit_gist( $id, $access_token, $data ) {
		$url = add_query_arg( compact( 'access_token' ), trailingslashit( self::BASE_URL ) . 'gists/' . $id );
		return $this->_post( $url, $data );
	}

	/**
	 * Makes a GET request given a full URL with access_token provided as query string.
	 *
	 * Exceptions are not caught.
	 *
	 * @since  0.1.0
	 * @param  string $url Full URL with access_token appears in query string
	 * @return array Result
	 *
	 * @throws Exception
	 */
	private function _get( $url ) {
		$resp = wp_remote_get( $url );

		if ( is_wp_error( $resp ) ) {
			throw new Exception( $resp->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data ) ) {
			throw new Exception( 'JSON parse error' );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $resp );
		if ( 200 !== $status_code ) {
			if ( isset( $data['message'] ) ) {
				throw new Exception( $data['message'] );
			} else {
				throw new Exception( sprintf( 'Client error with status code %d', $status_code ) );
			}
		}

		return $data;
	}

	/**
	 * Makes a POST request given a full URL with access_token provided as query string.
	 *
	 * Exceptions are not caught.
	 *
	 * @since  0.1.0
	 * @param  string $url  Full URL with access_token appears in query string
	 * @param  array  $data Data as body
	 * @return array  Result
	 *
	 * @throws Exception
	 */
	private function _post( $url, $data ) {
		$resp = wp_remote_post( $url, array(
			'body' => json_encode( $data ),
		) );

		if ( is_wp_error( $resp ) ) {
			throw new Exception( $resp->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data ) ) {
			throw new Exception( 'JSON parse error' );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $resp );
		if ( ! in_array( $status_code, array( 200, 201 ) ) ) {
			if ( isset( $data['message'] ) ) {
				$error_message = $data['message'];
				if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
					$details = array();
					foreach ( $data['errors'] as $error ) {
						$details[] = sprintf( '%s.%s %s', $error['resource'], $error['field'], $error['code'] );
					}
					$error_message .= '. ' . sprintf( __( 'Error details: %s.', 'post_to_gist' ), implode( ', ', $details ) );
				}
				throw new Exception( $error_message );
			} else {
				throw new Exception( sprintf( 'Client error with status code %d', $status_code ) );
			}
		}

		return $data;
	}
}
