const api = require( './api' );

/**
 * Create a variable product using the WooCommerce REST API.
 *
 * @param {{ name: string, visible: boolean, variation: boolean, options: string[] }[]} attributes List of attributes. See [Product - Attributes properties](https://woocommerce.github.io/woocommerce-rest-api-docs/#product-attributes-properties).
 * @return {Promise<number>} ID of the created variable product
 */
async function createVariableProduct( product, attributes = [] ) {
	const payload = {
		name: product.name,
		description: product.description || 'This is a variable product',
		type: 'variable',
		attributes,
	};

	const productId = await api.create.product( payload );

	return productId;
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
	createVariations,
};
