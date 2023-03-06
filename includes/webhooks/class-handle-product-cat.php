<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Webhook_Handler;
use Ainsys\Connector\Master\Webhooks\Handle;
use Ainsys\Connector\Woocommerce\Helper;

class Handle_Product_Cat extends Handle implements Hooked, Webhook_Handler {

	protected static string $entity = 'product_cat';

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

		if ( term_exists($data['slug']) ) {

			$message = sprintf( __( 'Error: %s already exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => 0,
				'message' => $message
			];
		}

		$result = Helper::add_term($data);

		return [
			'id'      => $result,
			'message' => $this->get_message($result, $data, self::$entity, $action)
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

		$result = Helper::update_term($data);

		return [
			'id'      => $object_id,
			'message' => $this->get_message($result, $data, self::$entity, $action)
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

			$message = sprintf( __( 'Error: %s delete is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => $object_id,
				'message' => $message
			];

		}

		$term_id = Helper::get_term_id($data);
		$result = wp_delete_term($term_id, 'product_cat');

		return [
			'id'      => $object_id,
			'message' => $this->get_message($result, $data, self::$entity, $action)
		];

	}

}
