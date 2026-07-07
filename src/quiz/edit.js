import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.scss';

const ALLOWED = [ 'interactive-quiz-poll/question' ];
const TEMPLATE = [ [ 'interactive-quiz-poll/question' ] ];

export default function Edit( { attributes, setAttributes } ) {
	const { allowRetake } = attributes;
	const blockProps = useBlockProps( { className: 'd9qp-editor d9qp-quiz-editor' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Quiz settings', 'interactive-quiz-poll' ) }>
					<ToggleControl
						label={ __( 'Allow visitors to retake', 'interactive-quiz-poll' ) }
						help={ __(
							'Show a Retake button on the results so a visitor can clear their attempt and try again.',
							'interactive-quiz-poll'
						) }
						checked={ !! allowRetake }
						onChange={ ( value ) => setAttributes( { allowRetake: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks
					allowedBlocks={ ALLOWED }
					template={ TEMPLATE }
					templateLock={ false }
				/>
			</div>
		</>
	);
}
