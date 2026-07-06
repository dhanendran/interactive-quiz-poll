<?php
/**
 * Block registration and shared render helpers.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Registers block types and coordinates rendering.
 */
class Blocks {

	/**
	 * The container post ID currently being rendered.
	 *
	 * When a quiz/poll is surfaced through an embed block, this is the referenced
	 * CPT ID (so votes are tallied against the single source of truth), not the
	 * page the embed lives on.
	 *
	 * @var int|null
	 */
	private static $render_post_id = null;

	/**
	 * Whether the interactivity config has been emitted this request.
	 *
	 * @var bool
	 */
	private static $config_done = false;

	/**
	 * Register every compiled block under build/.
	 */
	public static function register() {
		$build = D9QP_DIR . 'build';
		if ( ! is_dir( $build ) ) {
			return;
		}

		foreach ( scandir( $build ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$dir = $build . '/' . $entry;
			if ( is_dir( $dir ) && file_exists( $dir . '/block.json' ) ) {
				register_block_type( $dir );
			}
		}
	}

	/**
	 * The container post ID to attribute the current render to.
	 *
	 * @return int
	 */
	public static function current_post_id() {
		if ( null !== self::$render_post_id ) {
			return (int) self::$render_post_id;
		}
		return (int) get_the_ID();
	}

	/**
	 * Set the container post ID for an embed render.
	 *
	 * @param int $post_id Referenced CPT ID.
	 */
	public static function set_render_post_id( $post_id ) {
		self::$render_post_id = (int) $post_id;
	}

	/**
	 * Clear the embed render context.
	 */
	public static function reset_render_post_id() {
		self::$render_post_id = null;
	}

	/**
	 * Emit the interactivity config (REST base + i18n) exactly once per request.
	 */
	public static function ensure_config() {
		if ( self::$config_done ) {
			return;
		}
		self::$config_done = true;

		$config = array(
			'restUrl' => esc_url_raw( rest_url( Rest_Controller::NAMESPACE ) ),
			'i18n'    => array(
				'votes'     => __( 'votes', 'interactive-quiz-poll' ),
				'vote'      => __( 'vote', 'interactive-quiz-poll' ),
				'correct'   => __( 'Correct!', 'interactive-quiz-poll' ),
				'incorrect' => __( 'Not quite.', 'interactive-quiz-poll' ),
				'error'     => __( 'Something went wrong. Please try again.', 'interactive-quiz-poll' ),
				'scored'    => __( 'You scored', 'interactive-quiz-poll' ),
			),
		);

		wp_interactivity_config( 'interactive-quiz-poll', $config );
	}
}
