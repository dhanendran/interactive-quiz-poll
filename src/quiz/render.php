<?php
/**
 * Server render for the quiz block.
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

$d9qp_post_id = \D9QP\Blocks::current_post_id();
if ( ! $d9qp_post_id ) {
	return '';
}

\D9QP\Blocks::ensure_config();

$d9qp_tree      = new \D9QP\Block_Tree( $d9qp_post_id );
$d9qp_questions = $d9qp_tree->get_questions();
if ( empty( $d9qp_questions ) ) {
	return '';
}

$d9qp_context = array(
	'mode'   => 'quiz',
	'postId' => $d9qp_post_id,
	'total'  => count( $d9qp_questions ),
);

$d9qp_wrapper = get_block_wrapper_attributes( array( 'class' => 'd9qp d9qp-quiz' ) );
?>
<div
	<?php echo $d9qp_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>
	data-wp-interactive="interactive-quiz-poll"
	data-wp-context="<?php echo esc_attr( wp_json_encode( $d9qp_context ) ); ?>"
	data-wp-init="callbacks.initQuiz"
>
	<?php
	foreach ( $d9qp_questions as $d9qp_question ) :
		$d9qp_question_id = isset( $d9qp_question['attrs']['questionId'] ) ? $d9qp_question['attrs']['questionId'] : '';
		$d9qp_prompt      = isset( $d9qp_question['attrs']['prompt'] ) ? $d9qp_question['attrs']['prompt'] : '';
		$d9qp_answer_ids  = $d9qp_tree->get_answer_ids( $d9qp_question );
		if ( empty( $d9qp_question_id ) || empty( $d9qp_answer_ids ) ) {
			continue;
		}
		$d9qp_q_ctx = array( 'questionId' => $d9qp_question_id );
		?>
		<div
			class="d9qp-question"
			data-wp-context="<?php echo esc_attr( wp_json_encode( $d9qp_q_ctx ) ); ?>"
			data-wp-init="callbacks.initQuestion"
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
							data-wp-class--is-correct="state.optionIsCorrect"
							data-wp-class--is-wrong="state.optionIsWrongChosen"
						>
							<button
								type="button"
								class="d9qp-option-btn"
								data-wp-on--click="actions.answerQuiz"
								data-wp-bind--disabled="state.quizAnswered"
							>
								<span class="d9qp-option-text"><?php echo wp_kses_post( $d9qp_text ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			</fieldset>
			<div class="d9qp-details" role="status" data-wp-bind--hidden="!state.quizHasDetails" data-wp-watch="callbacks.renderQuizDetails"></div>
		</div>
	<?php endforeach; ?>

	<div class="d9qp-quiz-result" role="status" data-wp-bind--hidden="!state.quizCompleted">
		<p class="d9qp-score" data-wp-text="state.quizScoreText"></p>
	</div>
</div>
