import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl, Modal } from '@wordpress/components';
import { RawHTML, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const IMPORT_PATH = '/wc/v3/wc_square/import-products';

/**
 * Normalises a form value so a checkbox that was toggled twice compares equal to
 * its stored 'yes'/'no' string again.
 *
 * @param {*} value Raw form value.
 * @return {*} Comparable value.
 */
const normalise = ( value ) => {
	if ( value === true ) {
		return 'yes';
	}

	if ( value === false ) {
		return 'no';
	}

	return value;
};

/**
 * Manual "Import Products" action.
 *
 * This is an action, not a setting: there is no option key behind it and its
 * save adapter is 'none'. Clicking opens the same confirmation modal as the
 * legacy screen and posts to the import REST route. Like the legacy screen the
 * button is disabled while the form has unsaved changes, because the import runs
 * against the stored settings, not the ones on screen.
 *
 * The legacy "View Progress" link is intentionally not rendered: it points at
 * the sync records screen (?tab=square&section=update), which the modern hub
 * redirects away from. The legacy component already supports this via its
 * showViewProgressButton prop.
 *
 * @param {Object} props
 * @param {Object} props.field         SDK field descriptor (id + label).
 * @param {Object} props.values        All current form values.
 * @param {Object} props.initialValues Form values as first rendered.
 */
export default function ImportProducts( { field, values, initialValues } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isImporting, setIsImporting ] = useState( false );
	const [ updateDuringImport, setUpdateDuringImport ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const isDirty = Object.keys( values || {} ).some(
		( key ) =>
			normalise( values[ key ] ) !==
			normalise( ( initialValues || {} )[ key ] )
	);

	const isSquare = values?.system_of_record === 'square';

	const description = isSquare
		? __(
				'Use this to bring new products from Square into WooCommerce. This is different from "Sync Now" which only updates products that are already synced.',
				'woocommerce-square'
		  )
		: __(
				'Use this to bring products from Square into WooCommerce.',
				'woocommerce-square'
		  );

	const runImport = async () => {
		setIsImporting( true );
		setError( '' );

		try {
			const response = await apiFetch( {
				path: IMPORT_PATH,
				method: 'POST',
				data: {
					update_during_import: updateDuringImport,
					api_callback: true,
				},
			} );

			if ( response?.success === false ) {
				setError(
					response?.data ||
						__(
							'Could not start import. Please try again.',
							'woocommerce-square'
						)
				);
			} else {
				setNotice( response?.data || '' );
			}
		} catch ( requestError ) {
			setError(
				requestError?.message ||
					__(
						'Could not start import. Please try again.',
						'woocommerce-square'
					)
			);
		}

		setIsImporting( false );
		setIsOpen( false );
	};

	return (
		<div className="wc-square-import-products">
			<span className="wc-square-import-products__label">
				{ field?.label ?? '' }
			</span>

			{ ! notice && (
				<Button
					variant="secondary"
					className="wc-square-import-products__button"
					disabled={ isDirty }
					onClick={ () => setIsOpen( true ) }
				>
					{ __(
						'Import all Products from Square',
						'woocommerce-square'
					) }
				</Button>
			) }

			<p className="wc-square-import-products__description">
				{ description }
			</p>

			{ isDirty && (
				<p className="wc-square-import-products__hint">
					{ __(
						'You have made changes to the settings. Please save the changes to enable the button.',
						'woocommerce-square'
					) }
				</p>
			) }

			{ notice && (
				<p className="wc-square-import-products__notice">{ notice }</p>
			) }

			{ error && (
				<p className="wc-square-import-products__error">{ error }</p>
			) }

			{ isOpen && (
				<Modal
					title={ __(
						'Import Products From Square',
						'woocommerce-square'
					) }
					size="large"
					className="wc-square-import-modal"
					onRequestClose={ () => setIsOpen( false ) }
				>
					<p>
						{ __(
							'You are about to import all new products, variations and categories from Square. This will create a new product in WooCommerce for every product retrieved from Square. If you have products in the trash from the previous imports, these will be ignored in the import.',
							'woocommerce-square'
						) }
					</p>
					<h3>
						{ __(
							'Do you wish to import existing product updates from Square?',
							'woocommerce-square'
						) }
					</h3>
					<RawHTML>
						{ sprintf(
							/* translators: %1$s and %2$s are placeholders for the link to the documentation */
							__(
								'Doing so will update existing WooCommerce products with the latest information from Square. %1$sView Documentation%2$s.',
								'woocommerce-square'
							),
							'<a href="https://woocommerce.com/document/woocommerce-square/#section-8" target="_blank" rel="noopener">',
							'</a>'
						) }
					</RawHTML>
					<CheckboxControl
						label={ __(
							'Update existing products during import.',
							'woocommerce-square'
						) }
						checked={ updateDuringImport }
						__nextHasNoMarginBottom
						onChange={ setUpdateDuringImport }
					/>
					<div className="wc-square-import-modal__actions">
						<Button
							variant="secondary"
							onClick={ () => setIsOpen( false ) }
						>
							{ __( 'Cancel', 'woocommerce-square' ) }
						</Button>
						<Button
							variant="primary"
							isBusy={ isImporting }
							disabled={ isImporting }
							onClick={ runImport }
						>
							{ __( 'Import Products', 'woocommerce-square' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
