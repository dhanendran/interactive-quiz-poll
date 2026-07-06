import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.scss';

const TEMPLATE = [
	[
		'core/paragraph',
		{ placeholder: __( 'Explain the answer (shown after responding)…', 'interactive-quiz-poll' ) },
	],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'd9qp-details-editor' } );
	return (
		<div { ...blockProps }>
			<InnerBlocks template={ TEMPLATE } />
		</div>
	);
}
