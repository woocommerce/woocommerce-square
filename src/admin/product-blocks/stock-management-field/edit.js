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
	const [ sor ] = useProductEntityProp( 'sor', { postType } );
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

	let canManageStock = true;

	if ( disabled || ( isSquareSynced && isSyncEnabled && sku.length ) ) {
		if ( ! isInventorySyncEnabled || 'square' === sor ) {
			canManageStock = false;
		} else if ( ! ( disabled || ( isSquareSynced && isSyncEnabled && sku.length ) ) ) {
			canManageStock = true;
		} else {
			canManageStock = false;
		}
	}

	return (
		<>
			<BaseControl
				label={ __( 'Stock management', 'woocommerce-square' ) }
				className='wc-square-track-quantity'
			>
				<ToggleControl
					label={ label }
					disabled={ ! canManageStock }
					help={ helpText }
					checked={ manageStock }
					onChange={ setManageStock }
				/>
			</BaseControl>
		</>
	);
};