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
	 * The referenced quiz/poll CPT ID currently being rendered.
	 *
	 * When surfaced through an embed block, this is the referenced CPT ID —
	 * used to validate question/answer IDs against the authored blocks.
	 *
	 * @var int|null
	 */
	private static $render_post_id = null;

	/**
	 * The display context: the post the quiz/poll is shown on.
	 *
	 * Responses are tracked per this ID, so the same quiz/poll embedded on two
	 * different posts keeps two independent tallies.
	 *
	 * @var int|null
	 */
	private static $context_post_id = null;

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
	 * The referenced quiz/poll CPT ID (for validating authored blocks).
	 *
	 * @return int
	 */
	public static function ref_post_id() {
		if ( null !== self::$render_post_id ) {
			return (int) self::$render_post_id;
		}
		return (int) get_the_ID();
	}

	/**
	 * The display-context post ID (for tracking responses per post).
	 *
	 * @return int
	 */
	public static function context_post_id() {
		if ( null !== self::$context_post_id ) {
			return (int) self::$context_post_id;
		}
		return (int) get_the_ID();
	}

	/**
	 * Set the render context for an embed.
	 *
	 * @param int $ref_id     Referenced quiz/poll CPT ID.
	 * @param int $context_id The post the embed appears on.
	 */
	public static function set_render_context( $ref_id, $context_id ) {
		self::$render_post_id  = (int) $ref_id;
		self::$context_post_id = (int) $context_id;
	}

	/**
	 * Clear the embed render context.
	 */
	public static function reset_render_context() {
		self::$render_post_id  = null;
		self::$context_post_id = null;
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
