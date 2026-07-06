import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.scss';

const TEMPLATE = [
	[
		'core/paragraph',
		{ placeholder: __( 'Explain the answer (shown after responding)…', 'interactive-quiz-poll' ) },
	],
];

const LABELS = {
	any: __( 'Shown after any answer', 'interactive-quiz-poll' ),
	correct: __( 'Shown only when correct', 'interactive-quiz-poll' ),
	incorrect: __( 'Shown only when incorrect', 'interactive-quiz-poll' ),
};

export default function Edit( { attributes, setAttributes, context } ) {
	const { showWhen } = attributes;
	const isQuiz = context[ 'interactive-quiz-poll/mode' ] === 'quiz';
	const blockProps = useBlockProps( {
		className: `d9qp-details-editor is-when-${ showWhen }`,
	} );

	return (
		<>
			{ isQuiz && (
				<InspectorControls>
					<PanelBody title={ __( 'Explanation', 'interactive-quiz-poll' ) }>
						<SelectControl
							label={ __( 'Show this explanation', 'interactive-quiz-poll' ) }
							value={ showWhen }
							options={ [
								{ label: LABELS.any, value: 'any' },
								{ label: LABELS.correct, value: 'correct' },
								{ label: LABELS.incorrect, value: 'incorrect' },
							] }
							onChange={ ( value ) => setAttributes( { showWhen: value } ) }
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				</InspectorControls>
			) }
			<div { ...blockProps }>
				{ isQuiz && showWhen !== 'any' && (
					<span className="d9qp-when-badge">{ LABELS[ showWhen ] }</span>
				) }
				<InnerBlocks template={ TEMPLATE } />
			</div>
		</>
	);
}
