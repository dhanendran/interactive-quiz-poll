<?php
/**
 * REST endpoints for voting, grading and completion.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Registers and handles the plugin's public REST routes.
 */
class Rest_Controller {

	const NAMESPACE = 'interactive-quiz-poll/v1';

	/**
	 * Register all routes.
	 */
	public function register_routes() {
		$id_args = array(
			'id' => array(
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => static function ( $value ) {
					return absint( $value ) > 0;
				},
			),
		);

		$body_args = array(
			'questionId' => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'answerId'   => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/poll/respond/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'poll_respond' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array_merge( $id_args, $body_args ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/quiz/respond/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'quiz_respond' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array_merge( $id_args, $body_args ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/quiz/complete/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'quiz_complete' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array_merge(
					$id_args,
					array(
						'token' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					)
				),
			)
		);
	}

	/**
	 * Permission gate for the public endpoints.
	 *
	 * Anonymous by design, but rate limited, and optionally restricted to
	 * logged-in users by the site owner.
	 *
	 * @return bool|\WP_Error
	 */
	public function permission() {
		if ( apply_filters( 'd9qp_require_login', false ) && ! is_user_logged_in() ) {
			return new \WP_Error( 'd9qp_login_required', __( 'You must be logged in to respond.', 'interactive-quiz-poll' ), array( 'status' => 401 ) );
		}

		if ( ! Rate_Limiter::within_rate_limit() ) {
			return new \WP_Error( 'd9qp_rate_limited', __( 'Too many requests. Please slow down.', 'interactive-quiz-poll' ), array( 'status' => 429 ) );
		}

		return true;
	}

	/**
	 * Resolve and validate a submission down to the matched blocks.
	 *
	 * @param \WP_REST_Request $request       Request.
	 * @param string           $expected_type Expected post type (quiz/poll CPT).
	 * @return array|\WP_Error { tree, question, answer, post_id }
	 */
	private function resolve( $request, $expected_type ) {
		$post_id = absint( $request['id'] );
		$type    = Post_Types::valid_container( $post_id );

		if ( false === $type ) {
			return new \WP_Error( 'd9qp_not_found', __( 'Quiz or poll not found.', 'interactive-quiz-poll' ), array( 'status' => 404 ) );
		}
		if ( $type !== $expected_type ) {
			return new \WP_Error( 'd9qp_wrong_type', __( 'That content is not the expected type.', 'interactive-quiz-poll' ), array( 'status' => 400 ) );
		}

		$tree     = new Block_Tree( $post_id );
		$question = $tree->get_question( sanitize_text_field( $request['questionId'] ) );
		if ( null === $question ) {
			return new \WP_Error( 'd9qp_bad_question', __( 'Unknown question.', 'interactive-quiz-poll' ), array( 'status' => 400 ) );
		}

		$answer = $tree->get_answer( $question, sanitize_text_field( $request['answerId'] ) );
		if ( null === $answer ) {
			return new \WP_Error( 'd9qp_bad_answer', __( 'Unknown answer.', 'interactive-quiz-poll' ), array( 'status' => 400 ) );
		}

		return array(
			'tree'     => $tree,
			'question' => $question,
			'answer'   => $answer,
			'post_id'  => $post_id,
		);
	}

	/**
	 * Record a poll vote and return the updated breakdown.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function poll_respond( $request ) {
		$resolved = $this->resolve( $request, Post_Types::POLL );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$post_id     = $resolved['post_id'];
		$question_id = $resolved['question']['attrs']['questionId'];
		$answer_id   = $resolved['answer']['attrs']['answerId'];
		$answer_ids  = $resolved['tree']->get_answer_ids( $resolved['question'] );

		// One response per caller per question (best-effort, privacy friendly).
		if ( Rate_Limiter::already_responded( $post_id, $question_id ) ) {
			$breakdown = Counters::breakdown( $post_id, $question_id, $answer_ids );
			return rest_ensure_response(
				array(
					'success'             => false,
					'alreadyResponded'    => true,
					'answerId'            => null,
					'counts'              => $breakdown['counts'],
					'totalVotes'          => $breakdown['total'],
					'questionDetailsHtml' => $resolved['tree']->render_details( $resolved['question'] ),
				)
			);
		}

		Counters::increment( $post_id, Counters::answer_key( $question_id, $answer_id ) );
		Counters::increment( $post_id, Counters::KEY_TOTAL );
		Counters::touch_last_response( $post_id );
		Rate_Limiter::mark_responded( $post_id, $question_id );

		$breakdown = Counters::breakdown( $post_id, $question_id, $answer_ids );

		return rest_ensure_response(
			array(
				'success'             => true,
				'answerId'            => $answer_id,
				'counts'              => $breakdown['counts'],
				'totalVotes'          => $breakdown['total'],
				'questionDetailsHtml' => $resolved['tree']->render_details( $resolved['question'] ),
			)
		);
	}

	/**
	 * Grade a single quiz answer.
	 *
	 * The correct answer is read from the matched block attribute — never from
	 * anything the client sent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function quiz_respond( $request ) {
		$resolved = $this->resolve( $request, Post_Types::QUIZ );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$post_id     = $resolved['post_id'];
		$question_id = $resolved['question']['attrs']['questionId'];
		$answer_id   = $resolved['answer']['attrs']['answerId'];
		$is_correct  = ! empty( $resolved['answer']['attrs']['isCorrect'] );

		// Locate the correct answer authored under this question.
		$correct_answer_id = '';
		foreach ( $resolved['tree']->get_answer_ids( $resolved['question'] ) as $aid ) {
			$candidate = $resolved['tree']->get_answer( $resolved['question'], $aid );
			if ( $candidate && ! empty( $candidate['attrs']['isCorrect'] ) ) {
				$correct_answer_id = $aid;
				break;
			}
		}

		Counters::increment( $post_id, Counters::answer_key( $question_id, $answer_id ) );
		Counters::touch_last_response( $post_id );

		return rest_ensure_response(
			array(
				'success'             => true,
				'isCorrect'           => $is_correct,
				'correctAnswerId'     => $correct_answer_id,
				'questionDetailsHtml' => $resolved['tree']->render_details( $resolved['question'], $is_correct ),
				'token'               => Rate_Limiter::issue_attempt_token( $post_id ),
			)
		);
	}

	/**
	 * Record a finished quiz attempt.
	 *
	 * Gated behind a server-issued attempt token so it can't be called
	 * standalone to inflate completions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function quiz_complete( $request ) {
		$post_id = absint( $request['id'] );
		if ( Post_Types::QUIZ !== Post_Types::valid_container( $post_id ) ) {
			return new \WP_Error( 'd9qp_not_found', __( 'Quiz not found.', 'interactive-quiz-poll' ), array( 'status' => 404 ) );
		}

		if ( ! Rate_Limiter::valid_attempt_token( sanitize_text_field( $request['token'] ), $post_id ) ) {
			return new \WP_Error( 'd9qp_bad_token', __( 'This attempt could not be verified.', 'interactive-quiz-poll' ), array( 'status' => 403 ) );
		}

		$completions = Counters::increment( $post_id, Counters::KEY_COMPLETIONS );
		Counters::increment( $post_id, Counters::KEY_TOTAL );
		Counters::touch_last_response( $post_id );

		return rest_ensure_response(
			array(
				'success'          => true,
				'completionCount'  => $completions,
				'lastResponseTime' => Counters::get( $post_id, Counters::KEY_LAST ),
			)
		);
	}
}
