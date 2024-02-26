/**
 * External dependencies
 */
import { useWooBlockProps } from '@woocommerce/block-templates';
import { useInstanceId } from '@wordpress/compose';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	__experimentalInputControl as InputControl,
	Button
} from '@wordpress/components';
import {
	useValidation,
	__experimentalUseProductEntityProp as useProductEntityProp,
} from '@woocommerce/product-editor';

export function Edit( {
	attributes,
	clientId,
	context,
} ) {
	const blockProps = useWooBlockProps( attributes );
	const stockQuantityId = useInstanceId(
		BaseControl,
		'product_stock_quantity'
	);

	const [ manageStock ] = useProductEntityProp( 'manage_stock', context.postType );
	const [ stockQuantity, setStockQuantity ] = useProductEntityProp( 'stock_quantity', context.postType );
	const [ productId ] = useProductEntityProp( 'id', context.postType );
	const [ isSquareSynced ] = useProductEntityProp( 'is_square_synced', context.postType );
	const [ isSyncEnabled ] = useProductEntityProp( 'is_sync_enabled', context.postType );
	const [ isInventorySyncEnabled ] = useProductEntityProp( 'is_inventory_sync_enabled', context.postType );
	const [ fetchStockNonce ] = useProductEntityProp( 'fetch_stock_nonce', context.postType );
	const [ sor ] = useProductEntityProp( 'sor', context.postType );
	const [ fetchStockProgress, setFetchStockProgress ] = useState( false );
	const [ isQuantityDisabled, setIsQuantityDisabled ] = useState( true );
	const isSavingPost = useSelect( ( __select ) => __select( 'core/editor' ).isSavingPost() );


	const {
		ref: stockQuantityRef,
		error: stockQuantityValidationError,
		validate: validateStockQuantity,
	} = useValidation(
		`stock_quantity-${ clientId }`,
		async function stockQuantityValidator() {
			if ( manageStock && stockQuantity && stockQuantity < 0 ) {
				return __(
					'Stock quantity must be a positive number.',
					'woocommerce-square'
				);
			}
		},
		[ manageStock, stockQuantity ]
	);

	useEffect( () => {
		if ( manageStock && stockQuantity === null ) {
			setStockQuantity( 1 );
		}
	}, [ manageStock, stockQuantity ] );

	if ( ! manageStock ) {
		return null;
	}

	async function fetchStockFromSquare() {
		const fetchStockData = new FormData();

		fetchStockData.append( 'action', 'wc_square_fetch_product_stock_with_square' );
		fetchStockData.append( 'security', fetchStockNonce );
		fetchStockData.append( 'product_id', productId );

		setFetchStockProgress( true );

		let response = await fetch( window.ajaxurl, {
			method: 'POST',
			body: fetchStockData,
		} );

		response = await response.json();

		if ( response.success ) {
			const { quantity, manage_stock: manageStock } = response.data;

			setIsQuantityDisabled( false );
			setStockQuantity( Number( quantity ) );
		}

		setFetchStockProgress( false );
	}

	return (
		<div { ...blockProps }>
			<div className="wp-block-columns">
				<div className="wp-block-column">
					<BaseControl
						id={ stockQuantityId }
						className={
							stockQuantityValidationError && 'has-error'
						}
						help={ stockQuantityValidationError ?? '' }
					>
						<InputControl
							id={ stockQuantityId }
							name="stock_quantity"
							ref={ stockQuantityRef }
							label={ __( 'Available quantity', 'woocommerce-square' ) }
							value={ stockQuantity }
							onChange={ setStockQuantity }
							onBlur={ validateStockQuantity }
							type="number"
							min={ 0 }
							disabled={ isSquareSynced && manageStock && isQuantityDisabled }
						/>
						{
							isSyncEnabled && isInventorySyncEnabled && isSquareSynced && manageStock && 'square' === sor && (
								<p>
									<a href="#">
										{ `${ __( 'Managed by Square', 'woocommerce-square' ) } (${ __( 'Sync stock from Square', 'woocommerce-square' ) })` }
									</a>
								</p>
							)
						}

						{
							isSyncEnabled && isInventorySyncEnabled && isSquareSynced && manageStock && 'woocommerce' === sor && (
								<p>
									<Button
										variant='link'
										text={ __( 'Fetch stock from Square', 'woocommerce-square' ) }
										onClick={ fetchStockFromSquare }
										isBusy={ fetchStockProgress }
									/>
								</p>
							)
						}
					</BaseControl>
				</div>
				<div className="wp-block-column" />
			</div>
		</div>
	);
}