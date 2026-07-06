<?php
/**
 * Custom post types for authored quizzes and polls.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Registers the private `d9qp_quiz` and `d9qp_poll` post types.
 *
 * These are containers surfaced through embed blocks, not standalone URLs —
 * hence they are non-public with no archive or front-end query.
 */
class Post_Types {

	const QUIZ = 'd9qp_quiz';
	const POLL = 'd9qp_poll';

	/**
	 * Register both post types.
	 */
	public static function register() {
		self::register_quiz();
		self::register_poll();
	}

	/**
	 * The quiz container: one or more questions, seeded via a locked template.
	 */
	private static function register_quiz() {
		register_post_type(
			self::QUIZ,
			array(
				'labels'          => array(
					'name'          => __( 'Quizzes', 'interactive-quiz-poll' ),
					'singular_name' => __( 'Quiz', 'interactive-quiz-poll' ),
					'add_new_item'  => __( 'Add New Quiz', 'interactive-quiz-poll' ),
					'edit_item'     => __( 'Edit Quiz', 'interactive-quiz-poll' ),
					'menu_name'     => __( 'Quizzes', 'interactive-quiz-poll' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-forms',
				'menu_position'   => 25,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => true,
				'supports'        => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				'template'        => array(
					array(
						'interactive-quiz-poll/quiz',
						array(),
						array(
							array( 'interactive-quiz-poll/question' ),
						),
					),
				),
				'template_lock'   => 'insert',
			)
		);
	}

	/**
	 * The poll container: a single question.
	 */
	private static function register_poll() {
		register_post_type(
			self::POLL,
			array(
				'labels'          => array(
					'name'          => __( 'Polls', 'interactive-quiz-poll' ),
					'singular_name' => __( 'Poll', 'interactive-quiz-poll' ),
					'add_new_item'  => __( 'Add New Poll', 'interactive-quiz-poll' ),
					'edit_item'     => __( 'Edit Poll', 'interactive-quiz-poll' ),
					'menu_name'     => __( 'Polls', 'interactive-quiz-poll' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-chart-bar',
				'menu_position'   => 26,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => true,
				'supports'        => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				'template'        => array(
					array(
						'interactive-quiz-poll/poll',
						array(),
						array(
							array( 'interactive-quiz-poll/question' ),
						),
					),
				),
				'template_lock'   => 'insert',
			)
		);
	}

	/**
	 * Whether a post ID is one of our container post types and published.
	 *
	 * @param int $post_id Post ID.
	 * @return string|false The post type slug, or false if not a valid container.
	 */
	public static function valid_container( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		if ( ! in_array( $post->post_type, array( self::QUIZ, self::POLL ), true ) ) {
			return false;
		}
		if ( 'publish' !== $post->post_status ) {
			return false;
		}
		return $post->post_type;
	}
}
