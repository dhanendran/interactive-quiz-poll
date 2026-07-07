<?php
/**
 * Admin list-table columns and the reset-counts action.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Adds "Responses" and "Last activity" columns to the quiz/poll list tables,
 * plus a capability-gated action to reset a container's counts.
 */
class Admin_Columns {

	/**
	 * Hook everything up.
	 */
	public function init() {
		foreach ( array( Post_Types::QUIZ, Post_Types::POLL ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
		add_action( 'admin_post_d9qp_reset_counts', array( $this, 'handle_reset' ) );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
	}

	/**
	 * Add the columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$columns['d9qp_responses'] = __( 'Responses', 'interactive-quiz-poll' );
		$columns['d9qp_last']      = __( 'Last activity', 'interactive-quiz-poll' );
		return $columns;
	}

	/**
	 * Render a custom column's value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'd9qp_responses' === $column ) {
			echo (int) Counters::get( $post_id, Counters::KEY_TOTAL );
		} elseif ( 'd9qp_last' === $column ) {
			$ts = Counters::get( $post_id, Counters::KEY_LAST );
			if ( $ts > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: %s: human-readable time difference. */
						__( '%s ago', 'interactive-quiz-poll' ),
						human_time_diff( $ts, time() )
					)
				);
			} else {
				echo '&mdash;';
			}
		}
	}

	/**
	 * Add a "Reset counts" row action.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Post object.
	 * @return array
	 */
	public function row_action( $actions, $post ) {
		if ( ! in_array( $post->post_type, array( Post_Types::QUIZ, Post_Types::POLL ), true ) ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=d9qp_reset_counts&post=' . $post->ID ),
			'd9qp_reset_' . $post->ID
		);

		$actions['d9qp_reset'] = sprintf(
			'<a href="%s" onclick="return confirm(%s);">%s</a>',
			esc_url( $url ),
			esc_js( wp_json_encode( __( 'Reset all recorded responses for this item? This cannot be undone.', 'interactive-quiz-poll' ) ) ),
			esc_html__( 'Reset counts', 'interactive-quiz-poll' )
		);

		return $actions;
	}

	/**
	 * Handle the reset action.
	 */
	public function handle_reset() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'interactive-quiz-poll' ) );
		}
		check_admin_referer( 'd9qp_reset_' . $post_id );

		global $wpdb;
		// Remove every counter meta row for this post in one query.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$post_id,
				$wpdb->esc_like( '_d9qp_' ) . '%'
			)
		);
		wp_cache_delete( $post_id, 'post_meta' );

		// Bump the reset revision so visitors' locally-saved answers are
		// discarded on their next page load. Set after the delete above so it
		// isn't wiped by it.
		update_post_meta( $post_id, Counters::KEY_REV, time() );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php' ) );
		exit;
	}
}
