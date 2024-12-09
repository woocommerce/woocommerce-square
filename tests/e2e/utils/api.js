const wcApi = require( '@woocommerce/woocommerce-rest-api' ).default;
const config = require( '../config/playwright.config' );

let api;

// Ensure that global-setup.js runs before creating api client
if ( process.env.CONSUMER_KEY && process.env.CONSUMER_SECRET ) {
	api = new wcApi( {
		url: config.use.baseURL,
		consumerKey: process.env.CONSUMER_KEY,
		consumerSecret: process.env.CONSUMER_SECRET,
		version: 'wc/v3',
	} );
}

/**
 * Allow explicit construction of api client.
 */
const constructWith = ( consumerKey, consumerSecret ) => {
	api = new wcApi( {
		url: config.use.baseURL,
		consumerKey,
		consumerSecret,
		version: 'wc/v3',
	} );
};

const throwCustomError = (
	error,
	customMessage = 'Something went wrong. See details below.'
) => {
	throw new Error(
		customMessage
			.concat(
				`\nResponse status: ${ error.response.status } ${ error.response.statusText }`
			)
			.concat(
				`\nResponse headers:\n${ JSON.stringify(
					error.response.headers,
					null,
					2
				) }`
			).concat( `\nResponse data:\n${ JSON.stringify(
			error.response.data,
			null,
			2
		) }
` )
	);
};

const get = {
	productAttributes: async ( params ) => {
		const response = await api
			.get( 'products/attributes', params )
			.then( ( res ) => res )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all product attributes.'
				);
			} );

		return response.data;
	},
};

const create = {
	product: async ( product ) => {
		const response = await api.post( 'products', product );

		return response.data.id;
	},
	/**
	 * Batch create product variations.
	 *
	 * @see {@link [Batch update product variations](https://woocommerce.github.io/woocommerce-rest-api-docs/#batch-update-product-variations)}
	 * @param {number|string} productId  Product ID to add variations to
	 * @param {object[]}      variations Array of variations to add. See [Product variation properties](https://woocommerce.github.io/woocommerce-rest-api-docs/#product-variation-properties)
	 * @return {Promise<number[]>} Array of variation ID's.
	 */
	productVariations: async ( productId, variations ) => {
		const response = await api.post(
			`products/${ productId }/variations/batch`,
			{
				create: variations,
			}
		);

		return response.data.create.map( ( { id } ) => id );
	},
};

module.exports = {
	get,
	create,
	constructWith,
};
