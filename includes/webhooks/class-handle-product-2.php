<?php
namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Webhook_Handler;
use Ainsys\Connector\Master\Webhooks\Handle;

class Handle_Product_2 extends Handle implements Hooked, Webhook_Handler {

	protected static string $entity = 'product';

	public function register_webhook_handler( $handlers = [] ) {

		$handlers[ self::$entity ] = [ $this, 'handler' ];

		return $handlers;

	}

	/**
	 * @param  array  $data
	 * @param  string $action
	 *
	 * @return string
	 */
	protected function create( array $data, string $action ): string {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {
			return sprintf( __( 'Error: %s creation is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity );
		}

		$product = wc_get_product($data['id']);

		if($product){
			return sprintf( __( 'Error: %s already exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity );
		}

		$new_product = '';

		if($data['type'] == 'simple'){
			$new_product = new \WC_Product_Simple();
		}

		if($data['type'] == 'variable'){
			$new_product = new \WC_Product_Variable();
		}

		$setup_product = new Setup_Product($new_product, $data, $action);
		$setup_product->setup_product();

		if(!is_object($product)){
			return sprintf( __( 'Error: %s creation is failed.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity );
		}

		if(is_object($new_product)){
			$new_product->save();
		}

		return $this->get_message( 'test', $data, self::$entity, $action );

	}


	/**
	 * @param $data
	 * @param $action
	 * @param $object_id
	 *
	 * @return string
	 */
	protected function update( $data, $action, $object_id ): string {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {
			return sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity );
		}

	}


	/**
	 * @param $object_id
	 * @param $data
	 * @param $action
	 *
	 * @return string
	 */
	protected function delete( $object_id, $data, $action ): string {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {
			return sprintf( __( 'Error: %s delete is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity );
		}

		$result = wp_delete_post( $object_id );

		return $this->get_message( $result, $data, self::$entity, $action );
	}

}