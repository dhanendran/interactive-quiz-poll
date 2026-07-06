import { useBlockProps, InnerBlocks, RichText } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './editor.scss';

const ALLOWED = [ 'interactive-quiz-poll/answer', 'interactive-quiz-poll/question-details' ];
const TEMPLATE = [
	[ 'interactive-quiz-poll/answer' ],
	[ 'interactive-quiz-poll/answer' ],
	[ 'interactive-quiz-poll/question-details' ],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { questionId, prompt } = attributes;

	// Assign a stable, collision-free ID once, at insert time.
	useEffect( () => {
		if ( ! questionId ) {
			const id =
				window.crypto && window.crypto.randomUUID
					? window.crypto.randomUUID()
					: clientId;
			setAttributes( { questionId: id } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const blockProps = useBlockProps( { className: 'd9qp-question-editor' } );

	return (
		<div { ...blockProps }>
			<RichText
				tagName="p"
				className="d9qp-prompt-input"
				value={ prompt }
				onChange={ ( value ) => setAttributes( { prompt: value } ) }
				placeholder={ __( 'Ask a question…', 'interactive-quiz-poll' ) }
				allowedFormats={ [ 'core/bold', 'core/italic' ] }
			/>
			<InnerBlocks allowedBlocks={ ALLOWED } template={ TEMPLATE } />
		</div>
	);
}
