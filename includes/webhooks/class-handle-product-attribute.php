<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Webhook_Handler;
use Ainsys\Connector\Master\Webhooks\Handle;
use Ainsys\Connector\Woocommerce\Helper;

class Handle_Product_Attribute extends Handle implements Hooked, Webhook_Handler {

	protected static string $entity = 'product_attribute';

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

			$message = sprintf( __( 'Error: %s creation is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => $data['id'],
				'message' => $message
			];
		}

		if ( taxonomy_exists( 'pa_' . sanitize_title($data['attribute_label']) ) ) {

			$message = sprintf( __( 'Error: %s already exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );
			$this->handle_error($data, '', $message, self::$entity, $action);

			return [
				'id'      => $data['id'],
				'message' => $message
			];
		}


		$new_attribute = new Setup_Product_Attribute( $data );
		$new_attribute->setup_product_attribute();

		$message = sprintf( __( 'Success: %s creation is done.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );

		Logger::save(
			[
				'object_id'       => 0,
				'entity'          => self::$entity,
				'request_action'  => $action,
				'request_type'    => 'incoming',
				'request_data'    => serialize( $data ),
				'server_response' => $message,
			]
		);

		return [
			'id'      => $data['id'],
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

			$message = sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );
			$this->handle_error($data, '', $message, self::$entity, $action, $object_id);

			return [
				'id'      => $object_id,
				'message' => sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				                      self::$entity )
			];
		}
		if ( ! taxonomy_exists( 'pa_' . sanitize_title($data['attribute_label']) )) {

			$message = sprintf( __( 'Error: %s is not exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );
			$this->handle_error($data, '', $message, self::$entity, $action, $object_id);

			return [
				'id'      => $object_id,
				'message' => $message
			];

		}

		$new_attribute = new Setup_Product_Attribute( $data );
		$new_attribute->setup_product_attribute();

		$message = sprintf( __( 'Success: %s creation is done.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );

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

			$message = sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );
			$this->handle_error($data, '', $message, self::$entity, $action, $object_id);

			return [
				'id'      => $object_id,
				'message' => sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				                      self::$entity )
			];

		}

		$attribute_id = Helper::get_attribute_id_by_slug(sanitize_title($data['attribute_label']));

		if($attribute_id && $attribute_id != 0){
			$deleted = wc_delete_attribute($attribute_id);
		}

		if ( $deleted ) {

			$message = sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );

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

		} else {

			$message = sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['attribute_label'] );

			Logger::save(
				[
					'object_id'       => $object_id,
					'entity'          => self::$entity,
					'request_action'  => $action,
					'request_type'    => 'incoming',
					'request_data'    => serialize( $data ),
					'server_response' => $message,
					'error'           => 1,
				]
			);

			return [
				'id'      => $object_id,
				'message' => $message
			];
		}
	}

}
