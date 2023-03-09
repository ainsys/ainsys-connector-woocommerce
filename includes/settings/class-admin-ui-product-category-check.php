<?php

namespace Ainsys\Connector\Woocommerce\Settings;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Woocommerce\WP\Process_Product_Cat;
use Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking;

class Admin_Ui_Product_Category_Check implements Hooked {

	protected $process;

	static public $entity = 'product_cat';

	public function init_hooks() {

		$this->process = new Process_Product_Cat();

		/**
		 * Check entity connection for products
		 */
		add_filter( 'ainsys_check_connection_request', [ $this, 'check_product_entity' ], 15, 3 );

	}

	/**
	 * @param $result_entity
	 * @param $entity
	 * @param $make_request
	 *
	 * @return mixed
	 * Check "product" entity filter callback
	 */
	public function check_product_entity( $result_entity, $entity, Admin_UI_Entities_Checking $entities_checking) {

		if ( $entity !== self::$entity ) {
			return $result_entity;
		}

		$entities_checking->make_request = false;
		$result_test   = $this->get_product_cat();
		$result_entity = Settings::get_option( 'check_connection_entity' );

		return $entities_checking->get_result_entity($result_test, $result_entity, $entity);

	}

	/**
	 * @return array|false
	 *
	 * Get product data for AINSYS
	 *
	 */

	private function get_product_cat() {

		$args = array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false
		);

		$product_cats = get_terms($args);

		if ( ! empty( $product_cats ) ) {

			$product_cat    = end( $product_cats );
			$product_cat_id = $product_cat->term_id;

			return $this->process->process_checking( $product_cat_id, $product_cat, true );

		} else {
			return false;
		}

	}

}