<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Sync;
use WooCommerce\Square\Handlers\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class to represent a single catalog item.
 *
 * @since 2.0.0
 */
class Catalog_Item {


	/** @var \WC_Product the product object */
	protected $product;

	/** @var \Square\Models\CatalogObjectBatch the batch object */
	protected $batch;

	/** @var int the total number of catalog objects in this batch */
	protected $batch_object_count = 0;

	/** @var bool whether or not this catalog item should be soft-deleted */
	protected $soft_delete = false;

	/**
	 * Constructs the catalog item.
	 *
	 * @since 2.0.0
	 *
	 * @param int|false|\WC_Product $product the product ID or product object
	 * @param bool $is_soft_delete whether this catalog object should be soft-deleted
	 * @throws \Exception
	 */
	public function __construct( $product, $is_soft_delete = false ) {

		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;

		if ( ! $product instanceof \WC_Product ) {

			throw new \Exception( 'Invalid product' );
		}

		$this->product     = $product;
		$this->soft_delete = $is_soft_delete;
	}


	/**
	 * Gets the object batch.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\Models\CatalogObject|null $catalog_object existing catalog object or null to create a new one
	 * @return \Square\Models\CatalogObjectBatch
	 * @throws \Exception
	 */
	public function get_batch( \Square\Models\CatalogObject $catalog_object = null ) {

		if ( ! $this->batch ) {
			$this->create_batch( $catalog_object );
		}

		return $this->batch;
	}


	/**
	 * Gets the total number of objects in the batch.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_batch_object_count() {

		return $this->batch_object_count;
	}


	/**
	 * Returns whether this catalog object should be soft-deleted.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_soft_delete() {

		return true === $this->soft_delete;
	}


	/**
	 * Creates a batch containing this item and any variations.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\Models\CatalogObject|null $catalog_object existing catalog object or null to create a new one
	 * @throws \Exception
	 */
	protected function create_batch( \Square\Models\CatalogObject $catalog_object = null ) {

		if ( ! $catalog_object ) {
			$catalog_id     = Product\Woo_SOR::get_square_item_id( $this->product );
			$catalog_object = new \Square\Models\CatalogObject( 'ITEM', '' );
		}

		// update the object data from the Woo product
		$catalog_object = Product\Woo_SOR::update_catalog_item( $catalog_object, $this->product );

		$batch_data = array( $catalog_object );

		$this->batch = new \Square\Models\CatalogObjectBatch( $batch_data );

		$variations = $catalog_object->getItemData()->getVariations() ?: array();

		$this->batch_object_count = 1 + count( $variations );
	}


}
