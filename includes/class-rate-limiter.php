<?php
/**
 * Abuse guards for the anonymous public endpoints.
 *
 * Anonymous polls can never be made perfectly fraud-proof, but these guards
 * make casual inflation meaningfully harder without collecting personal data.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Per-IP rate limiting, response de-duplication and quiz attempt tokens.
 *
 * All state lives in transients keyed by a salted hash of the caller's IP —
 * the raw IP is never stored.
 */
class Rate_Limiter {

	/**
	 * A privacy-preserving, non-reversible fingerprint of the caller's IP.
	 *
	 * Used for rate limiting only — the raw IP is never stored.
	 *
	 * @return string
	 */
	private static function fingerprint() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return wp_hash( 'd9qp|' . $ip );
	}

	/**
	 * A stable per-browser visitor id, stored in a first-party cookie.
	 *
	 * This is the "one response per person" boundary — a browser, not an IP —
	 * so distinct visitors sharing an IP (offices, households, localhost) each
	 * get their own vote. It is not personally identifying (random token).
	 *
	 * @return string
	 */
	private static function visitor_id() {
		if ( ! empty( $_COOKIE['d9qp_uid'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['d9qp_uid'] ) );
		}

		$id = wp_generate_password( 20, false );

		if ( ! headers_sent() ) {
			$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
			$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
			setcookie( 'd9qp_uid', $id, time() + YEAR_IN_SECONDS, $path, $domain, is_ssl(), true );
		}

		// Make it available for the remainder of this request.
		$_COOKIE['d9qp_uid'] = $id;
		return $id;
	}

	/**
	 * Whether rate limiting / dedup is enabled. Filterable so a site owner can
	 * disable it (e.g. behind their own caching/fraud layer).
	 *
	 * @return bool
	 */
	private static function enabled() {
		return (bool) apply_filters( 'd9qp_abuse_guards_enabled', true );
	}

	/**
	 * Enforce a simple per-IP request throttle.
	 *
	 * @return bool True if within the limit, false if throttled.
	 */
	public static function within_rate_limit() {
		if ( ! self::enabled() ) {
			return true;
		}

		$limit  = (int) apply_filters( 'd9qp_rate_limit_per_minute', 30 );
		$key    = 'd9qp_rl_' . self::fingerprint();
		$count  = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Whether this caller has already responded to a given question.
	 *
	 * @param int    $post_id     Container post ID.
	 * @param string $question_id Question UUID.
	 * @return bool
	 */
	public static function already_responded( $post_id, $question_id ) {
		if ( ! self::enabled() ) {
			return false;
		}
		return (bool) get_transient( self::dedup_key( $post_id, $question_id ) );
	}

	/**
	 * Record that this caller responded to a question, starting the cooldown.
	 *
	 * @param int    $post_id     Container post ID.
	 * @param string $question_id Question UUID.
	 */
	public static function mark_responded( $post_id, $question_id ) {
		if ( ! self::enabled() ) {
			return;
		}
		$window = (int) apply_filters( 'd9qp_dedup_window', DAY_IN_SECONDS );
		set_transient( self::dedup_key( $post_id, $question_id ), 1, $window );
	}

	/**
	 * Dedup transient key for a caller + question.
	 *
	 * @param int    $post_id     Container post ID.
	 * @param string $question_id Question UUID.
	 * @return string
	 */
	private static function dedup_key( $post_id, $question_id ) {
		return 'd9qp_dp_' . self::visitor_id() . '_' . (int) $post_id . '_' . md5( $question_id );
	}

	/**
	 * Issue a short-lived attempt token that ties quiz completion to having
	 * actually answered a question on that quiz.
	 *
	 * @param int $post_id Quiz post ID.
	 * @return string Opaque token.
	 */
	public static function issue_attempt_token( $post_id ) {
		$token = wp_generate_password( 24, false );
		set_transient( 'd9qp_tok_' . $token, (int) $post_id, HOUR_IN_SECONDS );
		return $token;
	}

	/**
	 * Validate a completion token against a quiz.
	 *
	 * @param string $token   Token supplied by the client.
	 * @param int    $post_id Quiz post ID.
	 * @return bool
	 */
	public static function valid_attempt_token( $token, $post_id ) {
		if ( empty( $token ) ) {
			return false;
		}
		$stored = get_transient( 'd9qp_tok_' . $token );
		return false !== $stored && (int) $stored === (int) $post_id;
	}
}
