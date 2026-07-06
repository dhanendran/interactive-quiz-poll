import { registerBlockType } from '@wordpress/blocks';
import './editor.scss';
import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
	// Attribute-only block: the container renders it. Nothing to save.
	save: () => null,
} );
