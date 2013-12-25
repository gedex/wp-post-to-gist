<?php
/**
 * Plugin Name: Post to Gist
 * Plugin URI: https://github.com/gedex/wp-post-to-gist
 * Description: Post to GitHub Gist every time a post is saved.
 * Version: 0.1.0
 * Author: Akeda Bagus
 * Author URI: http://gedex.web.id/
 * Text Domain: post-to-gist
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires at least: 3.6
 * Tested up to: 3.8
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * Class that acts as plugin bootstrapper.
 *
 * @author Akeda Bagus <admin@gedex.web.id>
 * @since 0.1.0
 */
class Post_To_Gist {
	/**
	 * Name to use when referring this plugin.
	 *
	 * @var    string
	 * @access public
	 */
	public $name = 'post_to_gist';

	/**
	 * Plugin version.
	 *
	 * @var    string
	 * @access pubic
	 */
	public $version = '0.1.0';

	/**
	 * Container for component instances.
	 *
	 * @var    array
	 * @access private
	 */
	private $_components;

	/**
	 * Constructor.
	 *
	 * - Defines constants used in this plugin.
	 * - Loads the translation used in this plugin.
	 * - Loads components of this plugin.
	 *
	 * @since 0.1.0
	 * @access pubic
	 * @return void
	 */
	public function __construct() {
		$this->define_constants();
		$this->i18n();
		$this->load_components();
	}

	/**
	 * Defines constants used by the plugin.
	 *
	 * @since 0.1.0
	 * @access public
	 * @return void
	 */
	public function define_constants() {
		define( 'P2GIST_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'P2GIST_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

		define( 'P2GIST_INCLUDES_DIR', P2GIST_DIR . trailingslashit( 'includes' ) );

		define( 'P2GIST_JS_URL', P2GIST_URL . trailingslashit( 'js' ) );
	}

	/**
	 * Loads the translation files.
	 *
	 * @since 0.1.0
	 * @access public
	 * @return void
	 */
	public function i18n() {
		load_plugin_textdomain( 'post-to-gist', false, 'post-to-gist/languages' );
	}

	/**
	 * Loads components.
	 *
	 * @since 0.1.0
	 * @access public
	 * @return void
	 */
	public function load_components() {
		// Requires the component interface first.
		require_once P2GIST_INCLUDES_DIR . 'component-interface.php';
		$component_interface = __CLASS__ . '_Component_Interface';

		// Loads the component class.
		foreach ( array( 'setting', 'client', 'metabox' ) as $component ) {
			require_once P2GIST_INCLUDES_DIR . $component . '.php';
			$class = $this->get_component_class( $component );

			// Makes sure the component follows the interface.
			if ( in_array( $component_interface, class_implements( $class ) ) ) {
				$instance = new $class();
				$instance->load( $this );

				// Stores component into plugin's container.
				$this->_components[ $component ] = $instance;
			}
		}
	}

	/**
	 * Returns a string of component's class name.
	 *
	 * @since 0.1.0
	 * @param string $component Component's name, same as filename without '.php'
	 * @return string Class name of the component
	 */
	public function get_component_class( $component ) {
		// Replaces 'component-name' with 'Component Name' format.
		$humanized = ucwords( str_replace( array( '-', '_' ), ' ', $component ) );

		return __CLASS__ . '_' . str_replace( ' ', '_', $humanized );
	}

	/**
	 * Gets component instance from container.
	 *
	 * @since 0.1.0
	 * @param string $component Component's name, same as filename without '.php'
	 * @return null|object Returns component instance if exists, otherwise null is returned
	 */
	public function get_component_instance( $component ) {
		$component_exists = (
			isset( $this->_components[ $component ] )
			&&
			is_a( $this->_components[ $component ], __CLASS__ . '_Component_Interface' )
		);

		if ( $component_exists ) {
			return $this->_components[ $component ];
		} else {
			return null;
		}
	}
}

// Bootstrap the plugin.
add_action( 'plugins_loaded', function() {
	new Post_To_Gist();
} );
