import { useBlockProps } from '@wordpress/block-editor';
import { SelectControl, Placeholder, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import './editor.scss';

const QUERY = { per_page: -1, status: 'publish', _fields: 'id,title' };

export default function Edit( { attributes, setAttributes } ) {
	const { ref } = attributes;

	const { records, isLoading } = useSelect( ( select ) => {
		const core = select( coreStore );
		return {
			records: core.getEntityRecords( 'postType', 'd9qp_poll', QUERY ),
			isLoading: core.isResolving( 'getEntityRecords', [ 'postType', 'd9qp_poll', QUERY ] ),
		};
	}, [] );

	const blockProps = useBlockProps();

	const options = [
		{ label: __( 'Select a poll…', 'interactive-quiz-poll' ), value: 0 },
		...( records || [] ).map( ( post ) => ( {
			label: post.title?.rendered || `#${ post.id }`,
			value: post.id,
		} ) ),
	];

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="chart-bar"
				label={ __( 'Poll', 'interactive-quiz-poll' ) }
				instructions={ __( 'Choose a poll to embed.', 'interactive-quiz-poll' ) }
			>
				{ isLoading ? (
					<Spinner />
				) : (
					<SelectControl
						value={ ref }
						options={ options }
						onChange={ ( value ) => setAttributes( { ref: parseInt( value, 10 ) || 0 } ) }
						__nextHasNoMarginBottom
					/>
				) }
			</Placeholder>
			{ !! ref && (
				<div className="d9qp-embed-preview">
					<ServerSideRender
						block="interactive-quiz-poll/poll-embed"
						attributes={ { ref } }
					/>
				</div>
			) }
		</div>
	);
}
