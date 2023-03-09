<?php

namespace Ainsys\Connector\Woocommerce\Settings;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Master\WP\Prepare\Prepare_Taxonomies;
use Ainsys\Connector\Woocommerce\WP\Process_Product_Cat;
use Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking;

class Admin_Ui_Product_Category_Check implements Hooked {

	static public string $entity = 'product_cat';


	public function init_hooks() {

		/**
		 * Check entity connection for products
		 */
		add_filter( 'ainsys_check_connection_request', [ $this, 'check_product_entity' ], 15, 3 );

	}


	/**
	 * Check "product" entity filter callback
	 *
	 * @param                                                               $result_entity
	 * @param                                                               $entity
	 * @param  \Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking $entities_checking
	 *
	 * @return mixed
	 */
	public function check_product_entity( $result_entity, $entity, Admin_UI_Entities_Checking $entities_checking ) {

		if ( $entity !== self::$entity ) {
			return $result_entity;
		}

		$entities_checking->make_request = true;

		$result_test   = ( new Prepare_Taxonomies() )->get_tax_to_check( self::$entity, new Process_Product_Cat() );
		$result_entity = Settings::get_option( 'check_connection_entity' );

		return $entities_checking->get_result_entity( $result_test, $result_entity, $entity );

	}

}