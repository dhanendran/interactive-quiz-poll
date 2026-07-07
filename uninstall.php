<?php
/**
 * Uninstall handler.
 *
 * Data removal is opt-in: quizzes, polls and their recorded responses are kept
 * unless the site owner explicitly asks for them to be deleted by defining
 * D9QP_DELETE_DATA_ON_UNINSTALL as true (e.g. in wp-config.php).
 *
 * @package D9QP
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only run when WordPress is actually uninstalling the plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'D9QP_DELETE_DATA_ON_UNINSTALL' ) || ! D9QP_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

$d9qp_types = array( 'd9qp_quiz', 'd9qp_poll' );

foreach ( $d9qp_types as $d9qp_type ) {
	$d9qp_posts = get_posts(
		array(
			'post_type'   => $d9qp_type,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $d9qp_posts as $d9qp_post_id ) {
		wp_delete_post( $d9qp_post_id, true );
	}
}
