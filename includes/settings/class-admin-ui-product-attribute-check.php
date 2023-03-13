<?php

namespace Ainsys\Connector\Woocommerce\Settings;

use Ainsys\Connector\Master\Helper;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Woocommerce\WP\Process_Product_Attribute;
use Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking;

class Admin_Ui_Product_Attribute_Check implements Hooked {

	static public string $entity = 'product_attribute';


	public function init_hooks() {

		/**
		 * Check entity connection for products
		 */
		add_filter( 'ainsys_check_connection_request', [ $this, 'check_product_entity' ], 15, 3 );

	}


	/**
	 * @param                                                               $result_entity
	 * @param                                                               $entity
	 * @param  \Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking $entities_checking
	 *
	 * @return mixed
	 * Check Product Attribute entity filter callback
	 */
	public function check_product_entity( $result_entity, $entity, Admin_UI_Entities_Checking $entities_checking ) {

		if ( $entity !== self::$entity ) {
			return $result_entity;
		}

		$entities_checking->make_request = true;

		$result_test   = $this->get_product_attribute();
		$result_entity = Settings::get_option( 'check_connection_entity' );

		return $entities_checking->get_result_entity( $result_test, $result_entity, $entity );

	}


	/**
	 * Get product attribute data for AINSYS
	 *
	 * @return array
	 */
	protected function get_product_attribute(): array {

		$attributes = wc_get_attribute_taxonomies();

		if ( empty( $attributes ) ) {
			return [
				'request'  => __( 'Error: There is no data to check.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'response' => __( 'Error: There is no data to check.', AINSYS_CONNECTOR_TEXTDOMAIN ),
			];
		}

		$attributes_ids = Helper::get_rand_array( wp_list_pluck( $attributes, 'attribute_id' ) );

		$attribute_id = end( $attributes_ids );
		$attribute    = wp_list_filter( $attributes, [ 'attribute_id' => $attribute_id ] );

		return ( new Process_Product_Attribute() )->process_checking( $attribute_id, $attribute[ 'id:' . $attribute_id ] );

	}

}