<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Webhook_Handler;
use Ainsys\Connector\Master\Webhooks\Handle;
use Ainsys\Connector\Woocommerce\Helper;

class Handle_Order extends Handle implements Hooked, Webhook_Handler {

	protected static string $entity = 'shop_order';

	public function register_webhook_handler( $handlers = [] ) {
		$handlers[ self::$entity ] = [ $this, 'handler' ];

		return $handlers;
	}

	/**
	 * @param array $data
	 * @param string $action
	 *
	 * @return array
	 */
	protected function create( array $data, string $action ): array {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {
			return [
				'id'      => 0,
				'message' => sprintf( __( 'Error: %s creation is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				                      self::$entity )
			];
		}

		$order = wc_get_order( $data['ID'] );

		if ( $order ) {
			return [
				'id'      => 0,
				'message' => sprintf( __( 'Error: %s already exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity )
			];
		}

		$new_order = wc_create_order();

		$setup_order = new Setup_Order($new_order, $data);
		$setup_order->setup_order();

//		$setup_product = new Setup_Product( $new_product, $data );
//		$setup_product->setup_product();

//		$new_product->save();

		return [
			'id'      => 1,
			'message' => sprintf( __( 'Success: %s creation is done.', AINSYS_CONNECTOR_TEXTDOMAIN ),
			                      self::$entity )
		];
	}


	/**
	 * @param $data
	 * @param $action
	 * @param $object_id
	 *
	 * @return string
	 */
	protected function update( $data, $action, $object_id ): array {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {

			return [
				'id'      => $object_id,
				'message' => sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				                      self::$entity )
			];
		}

		$order = wc_get_order($object_id);

		if(!is_object($order)){

			return [
				'id'      => $object_id,
				'message' => sprintf( __( 'Error: %s is not exist.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				                      self::$entity )
			];

		}

//		$setup_product = new Setup_Product( $product, $data );
//		$setup_product->setup_product();

//		$product->save();

		return [
			'id'      => $object_id,
			'message' => sprintf( __( 'Success: %s update is done.', AINSYS_CONNECTOR_TEXTDOMAIN ),
			                      self::$entity )
		];
	}


	/**
	 * @param $object_id
	 * @param $data
	 * @param $action
	 *
	 * @return string
	 */
	protected function delete( $object_id, $data, $action ): array {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {
			return sprintf( __( 'Error: %s delete is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ),
			                self::$entity );
		}

		$result = wp_delete_post( $object_id );

		return [
			'id'      => $object_id,
			'message' => $this->get_message( 'test', $data, self::$entity, $action )
		];
	}

}
