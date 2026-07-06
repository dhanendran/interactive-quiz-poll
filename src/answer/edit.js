import { useBlockProps, RichText } from '@wordpress/block-editor';
import { ToggleControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './editor.scss';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { answerId, text, isCorrect } = attributes;
	const isQuiz = context[ 'interactive-quiz-poll/mode' ] === 'quiz';

	useEffect( () => {
		if ( ! answerId ) {
			const id =
				window.crypto && window.crypto.randomUUID
					? window.crypto.randomUUID()
					: clientId;
			setAttributes( { answerId: id } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const blockProps = useBlockProps( {
		className: 'd9qp-answer-editor' + ( isQuiz && isCorrect ? ' is-correct' : '' ),
	} );

	return (
		<div { ...blockProps }>
			<RichText
				tagName="span"
				className="d9qp-answer-input"
				value={ text }
				onChange={ ( value ) => setAttributes( { text: value } ) }
				placeholder={ __( 'Answer option…', 'interactive-quiz-poll' ) }
				allowedFormats={ [] }
			/>
			{ isQuiz && (
				<ToggleControl
					className="d9qp-correct-toggle"
					label={ __( 'Correct answer', 'interactive-quiz-poll' ) }
					checked={ !! isCorrect }
					onChange={ ( value ) => setAttributes( { isCorrect: value } ) }
					__nextHasNoMarginBottom
				/>
			) }
		</div>
	);
}
