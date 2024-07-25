/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	ToggleControl,
	Button,
	Flex,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __experimentalUseProductEntityProp as useProductEntityProp } from '@woocommerce/product-editor';
import parse from 'html-react-parser';

export const Edit = ({ attributes, context: { postType } }) => {
	const { disabled, disabledCopy } = attributes;

	const [manageStock, setManageStock] = useProductEntityProp('manage_stock', {
		postType,
	});
	const [isSquareSynced, setIsSquareSynced] = useProductEntityProp(
		'is_square_synced',
		{ postType }
	);
	const [isInventorySyncEnabled] = useProductEntityProp(
		'is_inventory_sync_enabled',
		{ postType }
	);
	const [isSyncEnabled] = useProductEntityProp('is_sync_enabled', {
		postType,
	});
	const [sku] = useProductEntityProp('sku', { postType });
	const [sor] = useProductEntityProp('sor', { postType });
	const [isGiftCard] = useProductEntityProp('is_gift_card', { postType });
	const [isStockManaged] = useProductEntityProp('manage_stock', { postType });
	const [productStatus] = useProductEntityProp('status', { postType });
	const [productId] = useProductEntityProp('id', { postType });
	const [fetchStockNonce] = useProductEntityProp('fetch_stock_nonce', {
		postType,
	});
	const [isInventorySyncing, setIsInventorySyncing] = useState(false);
	const [inventorySyncMessage, setInventorySyncMessage] = useState('');
	let label = __('Track quantity for this product', 'woocommerce-square');
	let helpText = '';

	useEffect(() => {
		if (isGiftCard === 'yes') {
			setIsSquareSynced(false);
		}
	}, [isGiftCard]);

	useEffect(() => {
		if (sku.length) {
			return;
		}

		setIsSquareSynced(false);
	}, [sku]);

	const syncInventory = async () => {
		const fetchStockData = new FormData();

		fetchStockData.append(
			'action',
			'wc_square_fetch_product_stock_with_square'
		);
		fetchStockData.append('security', fetchStockNonce);
		fetchStockData.append('product_id', productId);

		setIsInventorySyncing(true);
		setInventorySyncMessage('');

		let response = await fetch(window.ajaxurl, {
			method: 'POST',
			body: fetchStockData,
		});

		response = await response.json();

		if (!response.success) {
			setInventorySyncMessage(response.data);
		}

		setIsInventorySyncing(false);
	};

	if (isSquareSynced && isSyncEnabled && sku.length) {
		label = (
			<a href="/wp-admin/admin.php?page=wc-settings&tab=square">
				{__('Synced with Square', 'woocommerce-square')}
			</a>
		);
	}

	if (disabled && disabledCopy) {
		helpText = parse(disabledCopy);
	}

	let canManageStock = true;
	const isPublished = productStatus === 'publish';

	if (disabled || (isSquareSynced && isSyncEnabled && sku.length)) {
		if (!isInventorySyncEnabled || sor === 'square') {
			canManageStock = false;
		} else if (
			!(disabled || (isSquareSynced && isSyncEnabled && sku.length))
		) {
			canManageStock = true;
		} else {
			canManageStock = false;
		}
	}

	return (
		<>
			<BaseControl
				id="wc-square-track-quantity"
				label={__('Stock management', 'woocommerce-square')}
				className="wc-square-track-quantity"
			>
				<Flex justify="flex-start">
					<ToggleControl
						label={label}
						disabled={!canManageStock}
						help={helpText}
						checked={manageStock}
						onChange={setManageStock}
					/>
					{!isStockManaged && isSquareSynced && isPublished && (
						<>
							(
							<Button
								variant="link"
								disabled={isInventorySyncing}
								isBusy={isInventorySyncing}
								onClick={syncInventory}
							>
								{__('Sync Inventory', 'woocommerce-square')}
							</Button>
							)
						</>
					)}
				</Flex>
				{inventorySyncMessage && (
					<p style={{ color: 'rgb(170, 0, 0)' }}>
						{inventorySyncMessage}
					</p>
				)}
			</BaseControl>
		</>
	);
};
