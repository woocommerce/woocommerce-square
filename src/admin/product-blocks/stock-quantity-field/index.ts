/**
 * External dependencies
 */
import { registerProductEditorBlockType } from '@woocommerce/product-editor';

/**
 * Internal dependencies
 */
import { Edit } from './edit';
import blockConfiguration from './block.json';

const { name, ...metadata } = blockConfiguration;
const settings = {
	example: {},
	edit: Edit,
};


/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
registerProductEditorBlockType(
	{
		name,
		metadata: metadata as never,
		settings: settings as never,
	}
);
