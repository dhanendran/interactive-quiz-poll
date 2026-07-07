<?php
/**
 * Plugin orchestrator.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Wires the plugin's pieces together.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register all hooks.
	 */
	public function init() {
		add_action( 'init', array( Post_Types::class, 'register' ) );
		add_action( 'init', array( Blocks::class, 'register' ) );
		add_action( 'rest_api_init', array( new Rest_Controller(), 'register_routes' ) );

		if ( is_admin() ) {
			( new Admin_Columns() )->init();
			( new Results() )->init();
			( new Results_Page() )->init();
		}
	}
}
