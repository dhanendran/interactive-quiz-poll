import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Static content. Kept out of the initial page DOM by the container render and
 * returned only via REST after the visitor responds.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
