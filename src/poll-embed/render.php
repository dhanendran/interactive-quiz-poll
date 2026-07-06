<?php
/**
 * Server render for the poll embed.
 *
 * @package D9QP
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

$d9qp_ref = isset( $attributes['ref'] ) ? absint( $attributes['ref'] ) : 0;
if ( ! $d9qp_ref ) {
	return;
}

if ( \D9QP\Post_Types::POLL !== \D9QP\Post_Types::valid_container( $d9qp_ref ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p>' . esc_html__( 'Select a published poll to embed.', 'interactive-quiz-poll' ) . '</p>';
	}
	return;
}

$d9qp_post = get_post( $d9qp_ref );

// Attribute the render (and therefore votes) to the referenced poll.
\D9QP\Blocks::set_render_post_id( $d9qp_ref );
$d9qp_html = do_blocks( $d9qp_post->post_content );
\D9QP\Blocks::reset_render_post_id();

$d9qp_wrapper = get_block_wrapper_attributes( array( 'class' => 'd9qp-embed d9qp-poll-embed' ) );

printf(
	'<div %1$s>%2$s</div>',
	$d9qp_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core.
	$d9qp_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered block content.
);
