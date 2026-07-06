import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import './editor.scss';

const ALLOWED = [ 'interactive-quiz-poll/question' ];
const TEMPLATE = [ [ 'interactive-quiz-poll/question' ] ];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'd9qp-editor d9qp-quiz-editor' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks
				allowedBlocks={ ALLOWED }
				template={ TEMPLATE }
				templateLock={ false }
			/>
		</div>
	);
}
