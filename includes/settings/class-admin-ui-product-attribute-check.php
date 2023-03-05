<?php

namespace Ainsys\Connector\Woocommerce\Settings;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Woocommerce\WP\Process_Product_Attribute;
use Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking;

class Admin_Ui_Product_Attribute_Check implements Hooked {

	protected $process;

	static public $entity = 'product_attribute';

	public function init_hooks() {

		$this->process = new Process_Product_Attribute();

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
	 * Check Product Attribute entity filter callback
	 */
	public function check_product_entity( $result_entity, $entity, Admin_UI_Entities_Checking $entities_checking) {

		if ( $entity !== self::$entity ) {
			return $result_entity;
		}

		$result_test   = $this->get_product_attribute();
		$result_entity = Settings::get_option( 'check_connection_entity' );

		return $entities_checking->get_result_entity($result_test, $result_entity, $entity);

	}

	/**
	 * @return array|false
	 *
	 * Get product attribute data for AINSYS
	 *
	 */

	private function get_product_attribute() {

		$attributes = wc_get_attribute_taxonomies();

		if ( ! empty( $attributes ) ) {

			$attribute    = end( $attributes );
			$attribute_id = $attribute->attribute_id;

			return $this->process->process_checking( $attribute_id, $attribute, true );

		} else {
			return false;
		}

	}

}