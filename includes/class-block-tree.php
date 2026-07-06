<?php
/**
 * Parses a container post's blocks and answers questions about its structure.
 *
 * This is the trust boundary: the client sends only identifiers, and every one
 * is resolved against the post's own authored block tree before it is used.
 *
 * @package D9QP
 */

namespace D9QP;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Read-only helpers over a parsed block tree.
 */
class Block_Tree {

	const B_QUESTION = 'interactive-quiz-poll/question';
	const B_ANSWER   = 'interactive-quiz-poll/answer';
	const B_DETAILS  = 'interactive-quiz-poll/question-details';

	/**
	 * Parsed blocks for the post.
	 *
	 * @var array
	 */
	private $blocks;

	/**
	 * @param int $post_id Post ID to read blocks from.
	 */
	public function __construct( $post_id ) {
		$post         = get_post( $post_id );
		$this->blocks = $post ? parse_blocks( $post->post_content ) : array();
	}

	/**
	 * Depth-first search for a block by name matching a predicate.
	 *
	 * @param array    $blocks    Blocks to search.
	 * @param callable $predicate Receives a block array, returns bool.
	 * @return array|null The matched block, or null.
	 */
	private function find( array $blocks, callable $predicate ) {
		foreach ( $blocks as $block ) {
			if ( $predicate( $block ) ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find( $block['innerBlocks'], $predicate );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Locate a question block by its questionId attribute.
	 *
	 * @param string $question_id Question UUID.
	 * @return array|null
	 */
	public function get_question( $question_id ) {
		return $this->find(
			$this->blocks,
			static function ( $block ) use ( $question_id ) {
				return isset( $block['blockName'], $block['attrs']['questionId'] )
					&& self::B_QUESTION === $block['blockName']
					&& $block['attrs']['questionId'] === $question_id;
			}
		);
	}

	/**
	 * Find an answer block within a question, by answerId.
	 *
	 * @param array  $question  Question block array.
	 * @param string $answer_id Answer UUID.
	 * @return array|null
	 */
	public function get_answer( array $question, $answer_id ) {
		$inner = isset( $question['innerBlocks'] ) ? $question['innerBlocks'] : array();
		return $this->find(
			$inner,
			static function ( $block ) use ( $answer_id ) {
				return isset( $block['blockName'], $block['attrs']['answerId'] )
					&& self::B_ANSWER === $block['blockName']
					&& $block['attrs']['answerId'] === $answer_id;
			}
		);
	}

	/**
	 * All answer IDs declared under a question, in document order.
	 *
	 * @param array $question Question block array.
	 * @return string[]
	 */
	public function get_answer_ids( array $question ) {
		$ids   = array();
		$inner = isset( $question['innerBlocks'] ) ? $question['innerBlocks'] : array();
		$walk  = function ( $blocks ) use ( &$walk, &$ids ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block['blockName'], $block['attrs']['answerId'] ) && self::B_ANSWER === $block['blockName'] ) {
					$ids[] = $block['attrs']['answerId'];
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		$walk( $inner );
		return $ids;
	}

	/**
	 * The first question block in the tree (used by single-question polls).
	 *
	 * @return array|null
	 */
	public function first_question() {
		return $this->find(
			$this->blocks,
			static function ( $block ) {
				return isset( $block['blockName'] ) && self::B_QUESTION === $block['blockName'];
			}
		);
	}

	/**
	 * All question blocks in the tree, in document order.
	 *
	 * @return array[]
	 */
	public function get_questions() {
		$questions = array();
		$walk      = function ( $blocks ) use ( &$walk, &$questions ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block['blockName'] ) && self::B_QUESTION === $block['blockName'] ) {
					$questions[] = $block;
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		$walk( $this->blocks );
		return $questions;
	}

	/**
	 * Count of question blocks in the tree.
	 *
	 * @return int
	 */
	public function question_count() {
		$count = 0;
		$walk  = function ( $blocks ) use ( &$walk, &$count ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block['blockName'] ) && self::B_QUESTION === $block['blockName'] ) {
					$count++;
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		$walk( $this->blocks );
		return $count;
	}

	/**
	 * Render the trusted answer-explanation block(s) for a question to HTML.
	 *
	 * Returns editorial content authored in the block editor — safe to inject
	 * client-side because it never contains client input. Each explanation may
	 * be scoped with a `showWhen` of `any`, `correct` or `incorrect`, so a quiz
	 * can show different text for a right vs. wrong answer.
	 *
	 * @param array     $question   Question block array.
	 * @param bool|null $is_correct Whether the chosen answer was correct. Null
	 *                              (polls) matches only `any`-scoped blocks.
	 * @return string Rendered HTML, or empty string if nothing matches.
	 */
	public function render_details( array $question, $is_correct = null ) {
		$inner = isset( $question['innerBlocks'] ) ? $question['innerBlocks'] : array();
		$html  = '';

		$walk = function ( $blocks ) use ( &$walk, &$html, $is_correct ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block['blockName'] ) && self::B_DETAILS === $block['blockName'] ) {
					$when  = isset( $block['attrs']['showWhen'] ) ? $block['attrs']['showWhen'] : 'any';
					$match = 'any' === $when
						|| ( true === $is_correct && 'correct' === $when )
						|| ( false === $is_correct && 'incorrect' === $when );
					if ( $match ) {
						$html .= render_block( $block );
					}
				} elseif ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		$walk( $inner );

		return $html;
	}
}
