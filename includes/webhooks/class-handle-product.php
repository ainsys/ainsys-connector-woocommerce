<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Webhook_Handler;
use Ainsys\Connector\Master\Webhooks\Handle;
use Ainsys\Connector\Woocommerce\Helper;

class Handle_Product extends Handle implements Hooked, Webhook_Handler {

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

			$message = sprintf( __( 'Error: %s creation is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => 0,
				'message' => $message
			];
		}

		/*$product = wc_get_product( $data['ID'] );

		if ( $product ) {
			return [
				'id'      => 0,
				'message' => sprintf( __( 'Error: %s already exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity )
			];
		}*/

		$new_product = Helper::setup_product_type($data['type']);

		if ( ! is_object( $new_product ) ) {

			$message = sprintf( __( 'Error: %s creation is failed.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => 0,
				'message' => $message
			];
		}

		$setup_product = new Setup_Product( $new_product, $data );
		$setup_product->setup_product();

		if ( $data['type'] === 'variable' && isset( $data['variations'] ) && ! empty( $data['variations'] ) ) {
			/**
			 * Need save product to DB before create variations
			 */
			$new_product->save();

			foreach ( $data['variations'] as $variation_data ) {
				$variation = new \WC_Product_Variation();

				$setup_variation = new Setup_Product_Variation( $variation, $variation_data, $new_product );
				$setup_variation->setup_product_variation();

				$variation->save();
			}
		}

		$new_product->save();

		$message = sprintf( __( 'Success: %s creation is done.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );

		Logger::save(
			[
				'object_id'       => $new_product->get_id(),
				'entity'          => self::$entity,
				'request_action'  => $action,
				'request_type'    => 'incoming',
				'request_data'    => serialize( $data ),
				'server_response' => $message,
			]
		);

		return [
			'id'      => $new_product->get_id(),
			'message' => $message
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

			$message = sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => $object_id,
				'message' => $message
			];
		}

		$product = wc_get_product($object_id);

		if(!is_object($product)){

			$message = sprintf( __( 'Error: %s is not exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => $object_id,
				'message' => $message
			];

		}

		$setup_product = new Setup_Product( $product, $data );
		$setup_product->setup_product();

		if ( $data['type'] === 'variable' && isset( $data['variations'] ) && ! empty( $data['variations'] ) ) {

			/**
			 * Need save product to DB before create variations
			 */
			$product->save();

			$variations_ids = $product->get_children();

			foreach ( $data['variations'] as $variation_data ) {

				$variation_id = (!empty($variation_data['ID'])) ? $variation_data['ID'] : $variation_data['variation_id'];

				unset($variations_ids[array_search($variation_id, $variations_ids)]);

				$variation = new \WC_Product_Variation($variation_id);

				$setup_variation = new Setup_Product_Variation( $variation, $variation_data, $product );
				$setup_variation->setup_product_variation();

				$variation->save();
			}

			if(is_array($variations_ids) && !empty($variations_ids)){
				foreach($variations_ids as $variation_id){
					$variation = wc_get_product($variation_id);
					$variation->delete();
				}
			}

		}

		$product->save();

		$message = sprintf( __( 'Success: %s update is done.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );

		Logger::save(
			[
				'object_id'       => $object_id,
				'entity'          => self::$entity,
				'request_action'  => $action,
				'request_type'    => 'incoming',
				'request_data'    => serialize( $data ),
				'server_response' => $message,
			]
		);

		return [
			'id'      => $object_id,
			'message' => $message
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

			$message = sprintf( __( 'Error: %s delete is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity);
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => $object_id,
				'message' => $message
			];

		}

		$result = wp_delete_post( $object_id );

		if(!$result){

			$message = sprintf( __( 'Error: %s delete is failed.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity);
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => $object_id,
				'message' => $message
			];

		}else{

			$message = sprintf( __( 'Success: %s delete is done.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity);

			Logger::save(
				[
					'object_id'       => $object_id,
					'entity'          => self::$entity,
					'request_action'  => $action,
					'request_type'    => 'incoming',
					'request_data'    => serialize( $data ),
					'server_response' => $message,
				]
			);

			return [
				'id'      => $object_id,
				'message' => $message
			];

		}

	}

}
