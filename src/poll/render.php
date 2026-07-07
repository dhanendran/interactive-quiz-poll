<?php
/**
 * Server render for the poll block.
 *
 * @package D9QP
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved inner content (ignored — we build our own UI).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

$d9qp_ref_id     = \D9QP\Blocks::ref_post_id();
$d9qp_context_id = \D9QP\Blocks::context_post_id();
if ( ! $d9qp_ref_id ) {
	return '';
}

\D9QP\Blocks::ensure_config();

$d9qp_tree     = new \D9QP\Block_Tree( $d9qp_ref_id );
$d9qp_question = $d9qp_tree->first_question();
if ( null === $d9qp_question ) {
	return '';
}

$d9qp_question_id = isset( $d9qp_question['attrs']['questionId'] ) ? $d9qp_question['attrs']['questionId'] : '';
$d9qp_prompt      = isset( $d9qp_question['attrs']['prompt'] ) ? $d9qp_question['attrs']['prompt'] : '';
$d9qp_answer_ids  = $d9qp_tree->get_answer_ids( $d9qp_question );

if ( empty( $d9qp_question_id ) || empty( $d9qp_answer_ids ) ) {
	return '';
}

// Seed the current tally so returning visitors (and post-vote reloads) show
// real numbers instead of an empty breakdown. Results stay hidden until the
// visitor votes.
$d9qp_breakdown = \D9QP\Counters::breakdown( $d9qp_ref_id, $d9qp_context_id, $d9qp_question_id, $d9qp_answer_ids );

$d9qp_context = array(
	'mode'       => 'poll',
	'refId'      => $d9qp_ref_id,
	'contextId'  => $d9qp_context_id,
	'questionId' => $d9qp_question_id,
	'counts'     => (object) $d9qp_breakdown['counts'],
	'totalVotes' => $d9qp_breakdown['total'],
);

$d9qp_wrapper = get_block_wrapper_attributes( array( 'class' => 'd9qp d9qp-poll' ) );
?>
<div
	<?php echo $d9qp_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>
	data-wp-interactive="interactive-quiz-poll"
	data-wp-context="<?php echo esc_attr( wp_json_encode( $d9qp_context ) ); ?>"
	data-wp-init="callbacks.initPoll"
>
	<fieldset class="d9qp-fieldset">
		<?php if ( '' !== $d9qp_prompt ) : ?>
			<legend class="d9qp-prompt"><?php echo wp_kses_post( $d9qp_prompt ); ?></legend>
		<?php endif; ?>
		<ul class="d9qp-options" role="list">
			<?php
			foreach ( $d9qp_answer_ids as $d9qp_answer_id ) :
				$d9qp_answer = $d9qp_tree->get_answer( $d9qp_question, $d9qp_answer_id );
				$d9qp_text   = ( $d9qp_answer && isset( $d9qp_answer['attrs']['text'] ) ) ? $d9qp_answer['attrs']['text'] : '';
				$d9qp_opt    = array( 'answerId' => $d9qp_answer_id );
				?>
				<li
					class="d9qp-option"
					data-wp-context="<?php echo esc_attr( wp_json_encode( $d9qp_opt ) ); ?>"
					data-wp-class--is-selected="state.optionIsSelected"
				>
					<span class="d9qp-bar" aria-hidden="true" data-wp-bind--hidden="!state.pollVoted">
						<span class="d9qp-bar-fill" data-wp-style--width="state.optionWidth"></span>
					</span>
					<button
						type="button"
						class="d9qp-option-btn"
						data-wp-on--click="actions.votePoll"
						data-wp-bind--disabled="state.pollVoted"
					>
						<span class="d9qp-option-text"><?php echo wp_kses_post( $d9qp_text ); ?></span>
						<span class="d9qp-option-meta">
							<span class="d9qp-selected-tag" data-wp-bind--hidden="!state.optionIsSelected">
								<span class="d9qp-icon d9qp-icon-selected" aria-hidden="true">
									<svg viewBox="0 0 20 20" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
								</span>
								<?php esc_html_e( 'Your answer', 'interactive-quiz-poll' ); ?>
							</span>
							<span class="d9qp-pct" data-wp-bind--hidden="!state.pollVoted" data-wp-text="state.optionPercentLabel"></span>
						</span>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
	<p class="d9qp-total" data-wp-bind--hidden="!state.pollVoted" data-wp-text="state.pollTotalVotesLabel"></p>
	<div class="d9qp-details" data-wp-bind--hidden="!state.pollHasDetails" data-wp-watch="callbacks.renderPollDetails"></div>
	<p class="d9qp-error" role="alert" data-wp-bind--hidden="!state.pollError" data-wp-text="state.pollErrorText"></p>
</div>
