/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { BaseControl, ToggleControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import {
	__experimentalUseProductEntityProp as useProductEntityProp,
} from '@woocommerce/product-editor';
import parse from 'html-react-parser';

export const Edit = ( { attributes, context: { postType } } ) => {
	const {
		disabled,
		disabledCopy,
	} = attributes;

	const [ manageStock, setManageStock ] = useProductEntityProp( 'manage_stock', { postType } );
	const [ isSquareSynced, setIsSquareSynced ] = useProductEntityProp( 'is_square_synced', { postType } );
	const [ isInventorySyncEnabled ] = useProductEntityProp( 'is_inventory_sync_enabled', { postType } );
	const [ isSyncEnabled ] = useProductEntityProp( 'is_sync_enabled', { postType } );
	const [ sku ] = useProductEntityProp( 'sku', { postType } );
	let label = __( 'Track quantity for this product', 'woocommerce' );
	let helpText = '';

	useEffect( () => {
		if ( sku.length ) {
			return;
		}

		setIsSquareSynced( false );
	}, [ sku ] );

	if ( isSquareSynced && isSyncEnabled && sku.length ) {
		label = <a href="/wp-admin/admin.php?page=wc-settings&tab=square">{ __( 'Synced with Square', 'woocommerce-square' ) }</a>
	}

	if ( disabled && disabledCopy ) {
		helpText = parse( disabledCopy );
	}

	return (
		<>
			<BaseControl
				label={ __( 'Stock management', 'woocommerce-square' ) }
				className='wc-square-track-quantity'
			>
				<ToggleControl
					label={ label }
					disabled={ disabled || ( isSquareSynced && isInventorySyncEnabled && isSyncEnabled && sku.length ) }
					help={ helpText }
					checked={ manageStock }
					onChange={ setManageStock }
				/>
			</BaseControl>
		</>
	);
};