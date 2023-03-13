<?php

namespace Ainsys\Connector\Woocommerce\Settings;

use Ainsys\Connector\Master\Helper;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Woocommerce\WP\Process_Orders;

class Admin_Ui_Order_Entity_Check implements Hooked {

	static public string $entity = 'shop_order';


	public function init_hooks() {

		add_filter( 'ainsys_check_connection_request', [ $this, 'check_order_entity' ], 15, 3 );

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
	public function check_order_entity( $result_entity, $entity, Admin_UI_Entities_Checking $entities_checking ) {

		if ( $entity !== self::$entity ) {
			return $result_entity;
		}

		$entities_checking->make_request = true;

		$result_test = $this->get_order();

		$result_entity = Settings::get_option( 'check_connection_entity' );

		return $entities_checking->get_result_entity( $result_test, $result_entity, $entity );

	}


	/**
	 * Get product data for AINSYS
	 *
	 * @return array
	 */
	protected function get_order(): array {

		$orders_ids = Helper::get_rand_posts( self::$entity );

		if ( empty( $orders_ids ) ) {
			return [
				'request'  => __( 'Error: There is no data to check.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'response' => __( 'Error: There is no data to check.', AINSYS_CONNECTOR_TEXTDOMAIN ),
			];
		}

		$order_id = reset( $orders_ids );

		return ( new Process_Orders )->process_checking( (int) $order_id );

	}

}