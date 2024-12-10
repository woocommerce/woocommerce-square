const api = require( './api' );

/**
 * Create a variable product using the WooCommerce REST API.
 *
 * @param {{ name: string, visible: boolean, variation: boolean, options: string[] }[]} product Product object to create.
 * @return {Promise<number>} ID of the created variable product
 */
async function createVariableProduct( product ) {
	const payload = {
		name: product.name,
		description: product.description || 'This is a variable product',
		type: 'variable',
		attributes: product.attributes || [],
	};

	const productId = await api.create.product( payload );

	return productId;
}

/**
 * Update a variable product using the WooCommerce REST API.
 *
 * @param {{ id: number, name: string, description: string }} product    Product to update.
 * @param {{ name: string, option: string }[]}                attributes List of attributes. See [Product - Attributes properties](https://woocommerce.github.io/woocommerce-rest-api-docs/#product-attributes-properties).
 * @return {Promise<number>} ID of the updated variable product
 */
async function updateVariableProduct( product, attributes = [] ) {
	const productId = product.id;
	delete product.id;
	if ( ! productId ) {
		throw new Error( 'Product ID is required to update a product.' );
	}

	const productData = await api.get.product( productId );

	const attributesData = productData.attributes || [];

	const payload = {
		...product,
		attributes: [ ...attributesData, ...attributes ],
	};

	const id = await api.update.product( productId, payload );
	return id;
}

/**
 * Create variations through the WooCommerce REST API.
 *
 * @param {number}                                                                  productId  Product ID to add variations to.
 * @param {{regular_price: string, attributes: {name: string, option: string}[]}[]} variations List of variations to create.
 * @return {Promise<number[]>} Array of variation ID's created.
 */
async function createVariations( productId, variations ) {
	return await api.create.productVariations( productId, variations );
}

module.exports = {
	createVariableProduct,
	updateVariableProduct,
	createVariations,
};
