/**
 * External dependencies
 */
import { useWooBlockProps } from '@woocommerce/block-templates';
import { BaseControl, ToggleControl } from '@wordpress/components';
import {
	__experimentalUseProductEntityProp as useProductEntityProp,
} from '@woocommerce/product-editor';
import { __, sprintf } from "@wordpress/i18n";

export function Edit({ attributes, context: { postType } } ) {
	const blockProps = useWooBlockProps( attributes );

	const [ isSquareSynced, setIsSquareSynced ] = useProductEntityProp( 'is_square_synced', postType );
	const [ sku ] = useProductEntityProp( 'sku', postType );
	const [ sor ] = useProductEntityProp( 'sor', postType );
	const [ editLink ] = useProductEntityProp( 'edit_link', postType );

	return (
		<div {...blockProps}>
			<BaseControl label={ __( 'Sync with Square', 'woocommerce-square' ) }>
				<ToggleControl
					label={ 'square' === sor ? __( 'Update product data with Square data', 'woocommerce-square' ) : __( 'Send product data to Square', 'woocommerce-square' ) }
					checked={ isSquareSynced && sku.length }
					disabled={ ! sku.length }
					onChange={ setIsSquareSynced }
				/>
			</BaseControl>
			{ ! sku.length && (
				<p
					style={ { color: '#a00' } }
					dangerouslySetInnerHTML={ { __html: sprintf(
						__( `Please add an SKU to sync %s with Square. The SKU must match the item's SKU in your Square account.`, 'woocommerce-square' ),
						editLink
					) } }
				/>
			) }
		</div>
	);
}
