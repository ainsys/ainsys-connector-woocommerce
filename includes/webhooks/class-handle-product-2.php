<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Webhook_Handler;
use Ainsys\Connector\Master\Webhooks\Handle;

class Handle_Product_2 extends Handle implements Hooked, Webhook_Handler {

	protected static string $entity = 'product';

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

		$product = wc_get_product( $data['ID'] );

		if ( $product ) {
			return [
				'id'      => 0,
				'message' => sprintf( __( 'Error: %s already exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity )
			];
		}

		$new_product = '';

		if ( $data['type'] === 'simple' ) {
			$new_product = new \WC_Product_Simple();
		}

		if ( $data['type'] === 'variable' ) {
			var_dump('create_variable_product');
			$new_product = new \WC_Product_Variable();
		}

		if ( ! is_object( $new_product ) ) {
			return [
				'id'      => 0,
				'message' => sprintf( __( 'Error: %s creation is failed.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				                      self::$entity )
			];
		}

		$setup_product = new Setup_Product( $new_product, $data );
		$setup_product->setup_product();

		if($data['type'] === 'variable' && isset($data['variations']) && !empty($data['variations'])){
			/**
			 * Need save product to DB before create variations
			 */
			$new_product->save();

			foreach($data['variations'] as $variation_data){

				$variation = new \WC_Product_Variation();

				$setup_variation = new Setup_Product_Variation($variation, $variation_data, $new_product);
				$setup_variation->setup_product_variation();

			}

		}

		$new_product->save();

		return [
			'id'      => $new_product->get_id(),
			'message' => $this->get_message( 'test', $data, self::$entity, $action )
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
			return sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ),
			                self::$entity );
		}

		return [
			'id'      => true, // временно
			'message' => $this->get_message( 'test', $data, self::$entity, $action )
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
			'id'      => true, // временно
			'message' => $this->get_message( 'test', $data, self::$entity, $action )
		];
	}

}