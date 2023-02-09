<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Woocommerce\Prepare_Order_Data;
use Ainsys\Connector\Woocommerce\Prepare_Product_Data;
use Ainsys\Connector\Woocommerce\Prepare_Product_Variation_Data;

class Process_Orders extends Process implements Hooked{

//	protected static string $entity = 'orders';
	protected static string $entity = 'shop_order';

	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

	}

	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $product_id
	 * @param       $product
	 * @param       $update
	 *
	 * @return array
	 */
	public function process_checking( $order_id, $product, $update ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		if ( ! $this->is_updated( $order_id, $update ) ) {
			return [];
		}

		if ( get_post_type( $order_id ) !== self::$entity ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data($order_id),
			$order_id
		);

		return $this->send_data( $order_id, self::$entity, self::$action, $fields );
	}

	/**
	 * @param $order_id
	 *
	 * @return array|mixed|void
	 * Prepare product data, for send to AINSYS
	 */
	public function prepare_data( $order_id ){

		$data = [];

		$order = wc_get_order($order_id);

		if(!$order){
			return $data;
		}

		$prepare = new Prepare_Order_Data($order_id);
		$data = $prepare->prepare_data();

		return $data;

	}

}