import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Persist the authored answers and details; consumed by the container's render.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
