<?php
/**
 * Read-only "Results" meta box on the quiz/poll edit screen.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Shows the aggregate response breakdown to editors.
 */
class Results {

	/**
	 * Hook the meta box.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
	}

	/**
	 * Register the meta box on both post types.
	 */
	public function register() {
		foreach ( array( Post_Types::QUIZ, Post_Types::POLL ) as $type ) {
			add_meta_box(
				'd9qp_results',
				__( 'Results', 'interactive-quiz-poll' ),
				array( $this, 'render' ),
				$type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Sum per-answer counters across every post this quiz/poll is shown on.
	 *
	 * Counters are stored on this post with keys of the form
	 * `_d9qp_v_{contextId}_{questionId}_{answerId}`. IDs are UUIDs (no
	 * underscores) so the key splits cleanly.
	 *
	 * @param int $post_id Post ID.
	 * @return array [questionId][answerId] => total count
	 */
	private function aggregate( $post_id ) {
		global $wpdb;

		$prefix = '_d9qp_v_';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$post_id,
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		$agg = array();
		foreach ( $rows as $row ) {
			$parts = explode( '_', substr( $row->meta_key, strlen( $prefix ) ) );
			if ( count( $parts ) < 3 ) {
				continue;
			}
			// [0] = contextId, [1] = questionId, [2] = answerId.
			$qid = $parts[1];
			$aid = $parts[2];
			if ( ! isset( $agg[ $qid ] ) ) {
				$agg[ $qid ] = array();
			}
			$agg[ $qid ][ $aid ] = ( isset( $agg[ $qid ][ $aid ] ) ? $agg[ $qid ][ $aid ] : 0 ) + (int) $row->meta_value;
		}
		return $agg;
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render( $post ) {
		$is_quiz = Post_Types::QUIZ === $post->post_type;
		$tree    = new Block_Tree( $post->ID );
		$agg     = $this->aggregate( $post->ID );

		echo '<div class="d9qp-results-box">';

		if ( $is_quiz ) {
			$completions = Counters::get( $post->ID, Counters::KEY_COMPLETIONS );
			printf(
				'<p class="d9qp-results-summary">%s</p>',
				sprintf(
					/* translators: %s: number of completed attempts. */
					esc_html( _n( '%s completed attempt', '%s completed attempts', $completions, 'interactive-quiz-poll' ) ),
					'<strong>' . esc_html( number_format_i18n( $completions ) ) . '</strong>'
				)
			);
		}

		$questions = $tree->get_questions();
		if ( empty( $questions ) ) {
			echo '<p>' . esc_html__( 'Add questions and answers to start collecting responses.', 'interactive-quiz-poll' ) . '</p>';
			echo '</div>';
			$this->styles();
			return;
		}

		$q_index = 0;
		foreach ( $questions as $question ) {
			$q_index++;
			$qid    = isset( $question['attrs']['questionId'] ) ? $question['attrs']['questionId'] : '';
			$prompt = isset( $question['attrs']['prompt'] ) ? $question['attrs']['prompt'] : '';
			$answer_ids = $tree->get_answer_ids( $question );
			$q_counts   = isset( $agg[ $qid ] ) ? $agg[ $qid ] : array();
			$q_total    = array_sum( $q_counts );

			echo '<div class="d9qp-results-q">';
			printf(
				'<h4>%s</h4>',
				'' !== $prompt
					? wp_kses_post( $prompt )
					: esc_html( sprintf( /* translators: %d: question number. */ __( 'Question %d', 'interactive-quiz-poll' ), $q_index ) )
			);

			if ( 0 === $q_total ) {
				echo '<p class="d9qp-results-empty">' . esc_html__( 'No responses yet.', 'interactive-quiz-poll' ) . '</p>';
			}

			foreach ( $answer_ids as $aid ) {
				$answer     = $tree->get_answer( $question, $aid );
				$text       = ( $answer && isset( $answer['attrs']['text'] ) ) ? $answer['attrs']['text'] : '';
				$is_correct = $is_quiz && $answer && ! empty( $answer['attrs']['isCorrect'] );
				$count      = isset( $q_counts[ $aid ] ) ? (int) $q_counts[ $aid ] : 0;
				$pct        = $q_total > 0 ? round( ( $count / $q_total ) * 100 ) : 0;

				printf(
					'<div class="d9qp-results-row%1$s">
						<div class="d9qp-results-label">
							<span>%2$s%3$s</span>
							<span class="d9qp-results-num">%4$s &middot; %5$s%%</span>
						</div>
						<div class="d9qp-results-bar"><span style="width:%5$s%%"></span></div>
					</div>',
					$is_correct ? ' is-correct' : '',
					esc_html( $text ),
					$is_correct ? ' <span class="d9qp-results-correct">' . esc_html__( 'correct', 'interactive-quiz-poll' ) . '</span>' : '',
					esc_html( number_format_i18n( $count ) ),
					esc_html( $pct )
				);
			}

			echo '</div>';
		}

		echo '</div>';
		$this->styles();
	}

	/**
	 * Inline styles for the meta box (kept local — no extra asset to enqueue).
	 */
	private function styles() {
		?>
		<style>
			.d9qp-results-summary { font-size: 14px; margin: 0 0 1em; }
			.d9qp-results-q { margin: 0 0 1.5em; }
			.d9qp-results-q h4 { margin: 0 0 0.6em; font-size: 14px; }
			.d9qp-results-empty { color: #757575; font-style: italic; margin: 0; }
			.d9qp-results-row { margin: 0 0 0.7em; }
			.d9qp-results-label { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 3px; }
			.d9qp-results-num { color: #50575e; font-variant-numeric: tabular-nums; white-space: nowrap; }
			.d9qp-results-correct { color: #16a34a; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; margin-left: 4px; }
			.d9qp-results-bar { background: #f0f0f1; border-radius: 4px; height: 12px; overflow: hidden; }
			.d9qp-results-bar > span { display: block; height: 100%; background: #3b82f6; border-radius: 4px; transition: width 0.3s ease; }
			.d9qp-results-row.is-correct .d9qp-results-bar > span { background: #16a34a; }
		</style>
		<?php
	}
}
