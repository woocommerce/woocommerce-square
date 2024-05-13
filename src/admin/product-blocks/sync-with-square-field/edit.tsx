/**
 * External dependencies
 */
import { useWooBlockProps } from '@woocommerce/block-templates';
import { BaseControl, ToggleControl } from '@wordpress/components';
import {
	__experimentalUseProductEntityProp as useProductEntityProp
} from '@woocommerce/product-editor';
import { __, sprintf } from "@wordpress/i18n";
import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

export function Edit({ attributes, context: { postType } } ) {
	const blockProps = useWooBlockProps( attributes );
	const [ skuError, setSkuError ] = useState( '' );

	const [ isSquareSynced, setIsSquareSynced ] = useProductEntityProp( 'is_square_synced', postType );
	const [ sku ] = useProductEntityProp( 'sku', postType );
	const [ sor ] = useProductEntityProp( 'sor', postType );
	const [ editLink ] = useProductEntityProp( 'edit_link', postType );
	const [ product_id ] = useProductEntityProp( 'id', postType );
	const [ product_type ] = useProductEntityProp( 'type', postType );
	const variations = useSelect( ( select ) => {
		const { getProductVariations } = select( 'wc/admin/products/variations' );
		return getProductVariations( { product_id } );
	} );

	useEffect( () => {
		if ( 'simple' === product_type && ! sku.length ) {
			setSkuError(
				sprintf(
					__( `Please add an SKU to sync %s with Square. The SKU must match the item's SKU in your Square account.`, 'woocommerce-square' ),
					editLink
				)
			);
			return;
		}

		if ( 'variable' !== product_type ) {
			return;
		}

		if ( null === variations ) {
			return;
		}

		const skus = variations.map( variation => variation.sku );
		const hasDuplicates = new Set( skus ).size !== skus.length;

		if ( variations.some( variation => variation.sku === sku ) ) {
			setSkuError(
				__( 'SKU(s) of 1 or more variations are same as the parent product.', 'woocommerce-square' )
			);
			return;
		}

		if ( hasDuplicates ) {
			setSkuError(
				__( 'Variations have duplicate SKUs', 'woocommerce-square' )
			);
			return;
		}

		setSkuError( '' );
	}, [ variations, sku ] );

	return (
		<div {...blockProps}>
			<BaseControl label={ __( 'Sync with Square', 'woocommerce-square' ) }>
				<ToggleControl
					label={ 'square' === sor ? __( 'Update product data with Square data', 'woocommerce-square' ) : __( 'Send product data to Square', 'woocommerce-square' ) }
					checked={ isSquareSynced && ! skuError.length }
					disabled={ skuError.length }
					onChange={ setIsSquareSynced }
				/>
			</BaseControl>
			{ skuError.length ? (
				<p
					style={ { color: '#a00' } }
					dangerouslySetInnerHTML={ { __html: skuError } }
				/>
			): '' }
		</div>
	);
}
