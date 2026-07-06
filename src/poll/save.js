import { InnerBlocks } from '@wordpress/block-editor';

/**
 * The poll is server-rendered; save persists only the authored inner blocks.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
