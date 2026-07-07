<?php
/**
 * Dedicated "Results" admin screen with per-post breakdown and CSV export.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * A reporting page for each post type, plus a CSV export endpoint.
 */
class Results_Page {

	/**
	 * Hook the menus and export handler.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menus' ) );
		add_action( 'admin_post_d9qp_export', array( $this, 'export' ) );
	}

	/**
	 * Add a "Results" submenu under each post type.
	 */
	public function menus() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::QUIZ,
			__( 'Quiz Results', 'interactive-quiz-poll' ),
			__( 'Results', 'interactive-quiz-poll' ),
			'edit_posts',
			'd9qp-quiz-results',
			function () {
				$this->render( Post_Types::QUIZ );
			}
		);
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::POLL,
			__( 'Poll Results', 'interactive-quiz-poll' ),
			__( 'Results', 'interactive-quiz-poll' ),
			'edit_posts',
			'd9qp-poll-results',
			function () {
				$this->render( Post_Types::POLL );
			}
		);
	}

	/**
	 * Read and normalise the per-post/per-answer counters for a post.
	 *
	 * @param int $post_id Quiz/poll CPT ID.
	 * @return array List of rows: [ ctx, qid, aid, count ].
	 */
	private function counter_rows( $post_id ) {
		global $wpdb;

		$prefix = '_d9qp_v_';
		// Admin-only report: read every per-post counter for this quiz/poll by
		// key prefix. A direct query is needed (the meta API can't match by a
		// LIKE prefix); results are only shown on the reporting screen.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$post_id,
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		$out = array();
		foreach ( $rows as $row ) {
			$parts = explode( '_', substr( $row->meta_key, strlen( $prefix ) ) );
			if ( count( $parts ) < 3 ) {
				continue;
			}
			$out[] = array(
				'ctx'   => (int) $parts[0],
				'qid'   => $parts[1],
				'aid'   => $parts[2],
				'count' => (int) $row->meta_value,
			);
		}
		return $out;
	}

	/**
	 * A friendly label for a display-context post.
	 *
	 * @param int $ctx_id  Context post ID.
	 * @param int $self_id The quiz/poll's own ID.
	 * @return string
	 */
	private function context_label( $ctx_id, $self_id ) {
		if ( $ctx_id === $self_id ) {
			return __( 'Direct (not embedded)', 'interactive-quiz-poll' );
		}
		$title = get_the_title( $ctx_id );
		return '' !== $title ? $title : sprintf( '#%d', $ctx_id );
	}

	/**
	 * Render the page (list of items, or one item's detail).
	 *
	 * @param string $type Post type.
	 */
	private function render( $type ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'interactive-quiz-poll' ) );
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view param.

		echo '<div class="wrap">';
		if ( $post_id && get_post_type( $post_id ) === $type ) {
			$this->render_detail( $post_id, $type );
		} else {
			$this->render_list( $type );
		}
		echo '</div>';
	}

	/**
	 * List every quiz/poll with headline totals.
	 *
	 * @param string $type Post type.
	 */
	private function render_list( $type ) {
		$is_quiz = Post_Types::QUIZ === $type;
		$page    = $is_quiz ? 'd9qp-quiz-results' : 'd9qp-poll-results';

		echo '<h1>' . esc_html( $is_quiz ? __( 'Quiz Results', 'interactive-quiz-poll' ) : __( 'Poll Results', 'interactive-quiz-poll' ) ) . '</h1>';

		$posts = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		if ( empty( $posts ) ) {
			echo '<p>' . esc_html__( 'Nothing published yet.', 'interactive-quiz-poll' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'interactive-quiz-poll' ) . '</th>';
		echo '<th>' . esc_html__( 'Total responses', 'interactive-quiz-poll' ) . '</th>';
		if ( $is_quiz ) {
			echo '<th>' . esc_html__( 'Completions', 'interactive-quiz-poll' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Last activity', 'interactive-quiz-poll' ) . '</th>';
		echo '<th></th></tr></thead><tbody>';

		foreach ( $posts as $post ) {
			$url  = add_query_arg(
				array(
					'post_type' => $type,
					'page'      => $page,
					'post'      => $post->ID,
				),
				admin_url( 'edit.php' )
			);
			$last = Counters::get( $post->ID, Counters::KEY_LAST );

			echo '<tr>';
			printf( '<td><a href="%s"><strong>%s</strong></a></td>', esc_url( $url ), esc_html( get_the_title( $post ) ) );
			printf( '<td>%s</td>', esc_html( number_format_i18n( Counters::get( $post->ID, Counters::KEY_TOTAL ) ) ) );
			if ( $is_quiz ) {
				printf( '<td>%s</td>', esc_html( number_format_i18n( Counters::get( $post->ID, Counters::KEY_COMPLETIONS ) ) ) );
			}
			printf(
				'<td>%s</td>',
				$last > 0
					? esc_html( sprintf( /* translators: %s: time difference. */ __( '%s ago', 'interactive-quiz-poll' ), human_time_diff( $last, time() ) ) )
					: '&mdash;'
			);
			printf( '<td><a class="button button-small" href="%s">%s</a></td>', esc_url( $url ), esc_html__( 'View results', 'interactive-quiz-poll' ) );
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render one item's detailed results.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Post type.
	 */
	private function render_detail( $post_id, $type ) {
		$is_quiz = Post_Types::QUIZ === $type;
		$page    = $is_quiz ? 'd9qp-quiz-results' : 'd9qp-poll-results';
		$rows    = $this->counter_rows( $post_id );
		$tree    = new Block_Tree( $post_id );

		$back = add_query_arg(
			array(
				'post_type' => $type,
				'page'      => $page,
			),
			admin_url( 'edit.php' )
		);

		$export = wp_nonce_url(
			admin_url( 'admin-post.php?action=d9qp_export&post=' . $post_id ),
			'd9qp_export_' . $post_id
		);

		echo '<h1 class="wp-heading-inline">' . esc_html( get_the_title( $post_id ) ) . '</h1>';
		printf( ' <a class="page-title-action" href="%s">%s</a>', esc_url( $export ), esc_html__( 'Export CSV', 'interactive-quiz-poll' ) );
		printf( '<p><a href="%s">&larr; %s</a></p>', esc_url( $back ), esc_html__( 'All results', 'interactive-quiz-poll' ) );

		$total = Counters::get( $post_id, Counters::KEY_TOTAL );
		echo '<p>';
		printf(
			/* translators: %s: total responses. */
			esc_html__( 'Total responses: %s', 'interactive-quiz-poll' ),
			'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
		);
		if ( $is_quiz ) {
			echo ' &nbsp;·&nbsp; ';
			printf(
				/* translators: %s: number of completed attempts. */
				esc_html__( 'Completed attempts: %s', 'interactive-quiz-poll' ),
				'<strong>' . esc_html( number_format_i18n( Counters::get( $post_id, Counters::KEY_COMPLETIONS ) ) ) . '</strong>'
			);
		}
		echo '</p>';

		// --- Responses by post (which posts drive engagement) ---
		$by_post = array();
		foreach ( $rows as $r ) {
			$by_post[ $r['ctx'] ] = ( isset( $by_post[ $r['ctx'] ] ) ? $by_post[ $r['ctx'] ] : 0 ) + $r['count'];
		}
		arsort( $by_post );

		echo '<h2>' . esc_html__( 'Responses by post', 'interactive-quiz-poll' ) . '</h2>';
		if ( empty( $by_post ) ) {
			echo '<p>' . esc_html__( 'No responses recorded yet.', 'interactive-quiz-poll' ) . '</p>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Post', 'interactive-quiz-poll' ) . '</th>';
			echo '<th>' . esc_html__( 'Responses', 'interactive-quiz-poll' ) . '</th>';
			echo '<th></th></tr></thead><tbody>';
			foreach ( $by_post as $ctx => $count ) {
				$permalink = ( $ctx !== $post_id ) ? get_permalink( $ctx ) : '';
				echo '<tr>';
				printf( '<td>%s</td>', esc_html( $this->context_label( $ctx, $post_id ) ) );
				printf( '<td>%s</td>', esc_html( number_format_i18n( $count ) ) );
				printf(
					'<td>%s</td>',
					$permalink ? '<a href="' . esc_url( $permalink ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'interactive-quiz-poll' ) . '</a>' : ''
				);
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		// --- Aggregate answer breakdown ---
		$agg = array();
		foreach ( $rows as $r ) {
			if ( ! isset( $agg[ $r['qid'] ] ) ) {
				$agg[ $r['qid'] ] = array();
			}
			$agg[ $r['qid'] ][ $r['aid'] ] = ( isset( $agg[ $r['qid'] ][ $r['aid'] ] ) ? $agg[ $r['qid'] ][ $r['aid'] ] : 0 ) + $r['count'];
		}

		echo '<h2>' . esc_html__( 'Answer breakdown', 'interactive-quiz-poll' ) . '</h2>';
		$q_index = 0;
		foreach ( $tree->get_questions() as $question ) {
			$q_index++;
			$qid     = isset( $question['attrs']['questionId'] ) ? $question['attrs']['questionId'] : '';
			$prompt  = isset( $question['attrs']['prompt'] ) ? wp_strip_all_tags( $question['attrs']['prompt'] ) : '';
			$q_counts = isset( $agg[ $qid ] ) ? $agg[ $qid ] : array();
			$q_total  = array_sum( $q_counts );

			printf(
				'<h3>%s</h3>',
				esc_html( '' !== $prompt ? $prompt : sprintf( /* translators: %d: question number. */ __( 'Question %d', 'interactive-quiz-poll' ), $q_index ) )
			);

			echo '<table class="wp-list-table widefat striped" style="max-width:640px"><tbody>';
			foreach ( $tree->get_answer_ids( $question ) as $aid ) {
				$answer     = $tree->get_answer( $question, $aid );
				$text       = ( $answer && isset( $answer['attrs']['text'] ) ) ? $answer['attrs']['text'] : '';
				$is_correct = $is_quiz && $answer && ! empty( $answer['attrs']['isCorrect'] );
				$count      = isset( $q_counts[ $aid ] ) ? $q_counts[ $aid ] : 0;
				$pct        = $q_total > 0 ? round( ( $count / $q_total ) * 100 ) : 0;

				echo '<tr>';
				printf(
					'<td>%s%s</td>',
					esc_html( $text ),
					$is_correct ? ' <span style="color:#16a34a;font-weight:600">(' . esc_html__( 'correct', 'interactive-quiz-poll' ) . ')</span>' : ''
				);
				printf( '<td style="width:140px">%s &middot; %s%%</td>', esc_html( number_format_i18n( $count ) ), esc_html( $pct ) );
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Stream a CSV of the full per-post/per-answer breakdown.
	 */
	public function export() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'interactive-quiz-poll' ) );
		}
		check_admin_referer( 'd9qp_export_' . $post_id );

		$type = get_post_type( $post_id );
		if ( Post_Types::QUIZ !== $type && Post_Types::POLL !== $type ) {
			wp_die( esc_html__( 'Not found.', 'interactive-quiz-poll' ) );
		}

		$is_quiz = Post_Types::QUIZ === $type;
		$rows    = $this->counter_rows( $post_id );
		$tree    = new Block_Tree( $post_id );

		// Map ids to labels up front.
		$prompts = array();
		$answers = array();
		foreach ( $tree->get_questions() as $question ) {
			$qid             = isset( $question['attrs']['questionId'] ) ? $question['attrs']['questionId'] : '';
			$prompts[ $qid ] = isset( $question['attrs']['prompt'] ) ? wp_strip_all_tags( $question['attrs']['prompt'] ) : '';
			foreach ( $tree->get_answer_ids( $question ) as $aid ) {
				$answer            = $tree->get_answer( $question, $aid );
				$answers[ $aid ]   = array(
					'text'    => ( $answer && isset( $answer['attrs']['text'] ) ) ? $answer['attrs']['text'] : '',
					'correct' => $is_quiz && $answer && ! empty( $answer['attrs']['isCorrect'] ),
				);
			}
		}

		$filename = sanitize_title( get_the_title( $post_id ) ) . '-results.csv';

		$lines   = array();
		$lines[] = $this->csv_row( array( 'Post ID', 'Post', 'Question', 'Answer', 'Correct', 'Responses' ) );
		foreach ( $rows as $r ) {
			$lines[] = $this->csv_row(
				array(
					$r['ctx'],
					$this->context_label( $r['ctx'], $post_id ),
					isset( $prompts[ $r['qid'] ] ) ? $prompts[ $r['qid'] ] : $r['qid'],
					isset( $answers[ $r['aid'] ] ) ? $answers[ $r['aid'] ]['text'] : $r['aid'],
					( isset( $answers[ $r['aid'] ] ) && $answers[ $r['aid'] ]['correct'] ) ? 'yes' : '',
					$r['count'],
				)
			);
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file download, fields escaped by csv_row().
		echo implode( "\r\n", $lines ) . "\r\n";
		exit;
	}

	/**
	 * Build a single CSV line with quoted, injection-safe fields.
	 *
	 * @param array $fields Field values.
	 * @return string
	 */
	private function csv_row( array $fields ) {
		$escaped = array();
		foreach ( $fields as $value ) {
			$value = (string) $value;
			// Neutralise spreadsheet formula injection.
			if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
				$value = "'" . $value;
			}
			$escaped[] = '"' . str_replace( '"', '""', $value ) . '"';
		}
		return implode( ',', $escaped );
	}
}
