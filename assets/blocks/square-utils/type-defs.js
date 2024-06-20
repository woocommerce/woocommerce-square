/**
 * @typedef {Object} SquareServerData
 *
 * @property {string}  title              The payment method title (used for payment method label)
 * @property {string}  applicationId      The connected Square account's application ID
 * @property {string}  locationId         The Square store location ID
 * @property {boolean} isSandbox          Is Square set to sandbox mode
 * @property {Object}  availableCardTypes List of available card types
 * @property {string}  loggingEnabled     Is logging enabled
 * @property {string}  generalError       General error message for unhandled errors
 * @property {boolean} showSavedCards     Is tokenization/saved cards enabled
 * @property {boolean} showSaveOption     Is tokenization/saved cards enabled
 * @property {Object}  supports           List of features supported by Square
 */

/**
 * Client-side Square logs to be logged to WooCommerce logs on checkout submission
 *
 * @typedef {Object} SquareLogData
 *
 * @property {Array} errors   Critical client-side errors to be logged
 * @property {Array} messages Non-critical errors to be logged
 */

/**
 * Square Billing Contact
 *
 * @typedef {Object} SquareBillingContact
 *
 * @property {string} familyName     The billing last name
 * @property {string} givenName      The billing first name
 * @property {string} email          The billing email
 * @property {string} country        The billing contact country
 * @property {string} region         The billing state/region
 * @property {string} city           The billing city
 * @property {string} postalCode     The postal/zip code
 * @property {string} phone          The billing email
 * @property {Array}  [addressLines] An array of address line items.
 */

/**
 * Square Context object
 *
 * @typedef {Object} SquareContext
 *
 * @property {Function} onCreateNonce Triggers the SqPaymentCustomer billing data
 * @property {Function} verifyBuyer   Triggers Payments.verifyBuyer() function
 */

/**
 * Square Payment Form Handler
 *
 * @typedef {Object} PaymentsFormHandler
 *
 * @property {Function} handleInputReceived  Handle inputs received on PaymentsForm
 * @property {boolean}  isLoaded             Used to determine if PaymentsForm has finished loading
 * @property {Function} setLoaded            Function to set isLoaded state
 * @property {Function} getPostalCode        Function to return billingContact postalcode value
 * @property {Function} cardBrandClass       Function to return billingContact postalcode value
 * @property {Function} createNonce          Function to return billingContact postalcode value
 * @property {Function} verifyBuyer          Function to return billingContact postalcode value
 * @property {Function} getPaymentMethodData Function to return billingContact postalcode value
 */
