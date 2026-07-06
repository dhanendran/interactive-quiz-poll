import { InnerBlocks } from '@wordpress/block-editor';

/**
 * The quiz is server-rendered; save persists only the authored inner blocks.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
