<?php
/**
 * Race-safe aggregate counters stored in post meta.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Atomic counters for votes, completions and totals.
 *
 * Counters are never read-modify-written from PHP; each increment is a single
 * SQL statement so concurrent requests cannot lose updates.
 */
class Counters {

	/**
	 * Per-answer vote counter key, scoped to the post the poll is shown on.
	 *
	 * All counters live on the quiz/poll CPT, but the key includes the display
	 * post ID so the same poll embedded on two posts keeps two tallies. UUIDs
	 * contain no underscores, and we always look these up by known IDs (never
	 * by parsing the key), so the underscore delimiter is unambiguous.
	 *
	 * @param int    $context_id  Display post ID.
	 * @param string $question_id Question UUID.
	 * @param string $answer_id   Answer UUID.
	 * @return string
	 */
	public static function answer_key( $context_id, $question_id, $answer_id ) {
		return '_d9qp_v_' . (int) $context_id . '_' . $question_id . '_' . $answer_id;
	}

	const KEY_COMPLETIONS = '_d9qp_completions';
	const KEY_TOTAL       = '_d9qp_total';
	const KEY_LAST        = '_d9qp_last';
	const KEY_REV         = '_d9qp_rev';

	/**
	 * Atomically increment an integer post-meta counter by one.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return int The counter value after incrementing.
	 */
	public static function increment( $post_id, $meta_key ) {
		global $wpdb;

		// Single-statement increment — race-safe, no lost updates.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE post_id = %d AND meta_key = %s",
				$post_id,
				$meta_key
			)
		);

		if ( 0 === $updated ) {
			// Row didn't exist — create it. unique=true so a racing request
			// that created it in the gap makes this fail, and we fall through.
			$added = add_post_meta( $post_id, $meta_key, 1, true );
			if ( false === $added ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE post_id = %d AND meta_key = %s",
						$post_id,
						$meta_key
					)
				);
			}
		}

		wp_cache_delete( $post_id, 'post_meta' );
		return (int) get_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Read a counter value without side effects.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return int
	 */
	public static function get( $post_id, $meta_key ) {
		return (int) get_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Stamp the last-response time (used by the admin activity column).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function touch_last_response( $post_id ) {
		update_post_meta( $post_id, self::KEY_LAST, time() );
	}

	/**
	 * Build the vote breakdown for a question on a given display post.
	 *
	 * @param int      $ref_id      Quiz/poll CPT ID (where counters are stored).
	 * @param int      $context_id  Display post ID (the tally scope).
	 * @param string   $question_id Question UUID.
	 * @param string[] $answer_ids  Known answer IDs (from the block tree).
	 * @return array { counts: {answerId:int}, total: int }
	 */
	public static function breakdown( $ref_id, $context_id, $question_id, array $answer_ids ) {
		$counts = array();
		$total  = 0;
		foreach ( $answer_ids as $answer_id ) {
			$n                    = self::get( $ref_id, self::answer_key( $context_id, $question_id, $answer_id ) );
			$counts[ $answer_id ] = $n;
			$total               += $n;
		}
		return array(
			'counts' => $counts,
			'total'  => $total,
		);
	}
}
