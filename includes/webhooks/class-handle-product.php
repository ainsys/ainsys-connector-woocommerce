<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Webhook_Handler;
use Ainsys\Connector\Master\Webhooks\Handle;
use Ainsys\Connector\Woocommerce\Helper;
use Ainsys\Connector\Woocommerce\Webhooks\Setup\Setup_Product;
use Ainsys\Connector\Woocommerce\Webhooks\Setup\Setup_Product_Variation;
use WC_Product_Variation;

class Handle_Product extends Handle implements Hooked, Webhook_Handler {

	protected static string $entity = 'product';


	public function register_webhook_handler( $handlers = [] ) {

		$handlers[ self::$entity ] = [ $this, 'handler' ];

		return $handlers;
	}


	/**
	 * @param  array  $data
	 * @param  string $action
	 *
	 * @return array
	 */
	protected function create( array $data, string $action ): array {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {

			$message = sprintf( __( 'Error: %s creation is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error( $data, '', $message, self::$entity, $action );

			return [
				'id'      => 0,
				'message' => $message,
			];
		}

		$new_product = Helper::get_product_type( $data['type'] );

		if ( ! is_object( $new_product ) ) {

			$message = sprintf( __( 'Error: %s creation is failed.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error( $data, '', $message, self::$entity, $action );

			return [
				'id'      => 0,
				'message' => $message,
			];
		}

		( new Setup_Product( $new_product, $data ) )->setup_product();

		if ( $data['type'] === 'variable' && ! empty( $data['variations'] ) ) {

			foreach ( $data['variations'] as $variation_data ) {
				$variation = new WC_Product_Variation();

				( new Setup_Product_Variation( $variation, $variation_data, $new_product ) )->setup_product_variation();
			}
		}

		return [
			'id'      => $new_product->get_id(),
			'message' => $this->get_message( $new_product->get_id(), $data, self::$entity, $action ),
		];
	}


	/**
	 * @param $data
	 * @param $action
	 * @param $object_id
	 *
	 * @return array
	 */
	protected function update( $data, $action, $object_id ): array {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {

			$message = sprintf( __( 'Error: %s update is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error( $data, '', $message, self::$entity, $action );

			return [
				'id'      => $object_id,
				'message' => $message,
			];
		}

		$product = wc_get_product( $object_id );

		if ( ! is_object( $product ) ) {

			$message = sprintf( __( 'Error: %s is not exist.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity . ' ' . $data['name'] );
			$this->handle_error( $data, '', $message, self::$entity, $action );

			return [
				'id'      => $object_id,
				'message' => $message,
			];

		}

		( new Setup_Product( $product, $data ) )->setup_product();

		if ( $data['type'] === 'variable' && isset( $data['variations'] ) && ! empty( $data['variations'] ) ) {

			$variations_ids = $product->get_children();

			foreach ( $data['variations'] as $variation_data ) {

				$variation_id = ( ! empty( $variation_data['ID'] ) ) ? $variation_data['ID'] : $variation_data['variation_id'];

				unset( $variations_ids[ array_search( (int) $variation_id, (array) $variations_ids ) ] );

				$variation = new WC_Product_Variation( $variation_id );

				$setup_variation = new Setup_Product_Variation( $variation, $variation_data, $product );
				$setup_variation->setup_product_variation();
			}

			if ( is_array( $variations_ids ) && ! empty( $variations_ids ) ) {
				foreach ( $variations_ids as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					$variation->delete();
				}
			}

		}


		return [
			'id'      => $product->get_id(),
			'message' => $this->get_message( $product->get_id(), $data, self::$entity, $action ),
		];
	}


	/**
	 * @param $object_id
	 * @param $data
	 * @param $action
	 *
	 * @return array
	 */
	protected function delete( $object_id, $data, $action ): array {

		if ( Conditions::has_entity_disable( self::$entity, $action, 'incoming' ) ) {

			$message = sprintf( __( 'Error: %s delete is disabled in settings.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity );
			$this->handle_error( $data, '', $message, self::$entity, $action );

			return [
				'id'      => $object_id,
				'message' => $message,
			];

		}

		$product = wc_get_product( $object_id );

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( ! empty( $child ) ) {
					$child->delete( true );
				}
			}
		} else {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( ! empty( $child ) ) {
					$child->set_parent_id( 0 );
					$child->save();
				}
			}
		}

		$result = $product->delete( true );

		if ( $parent_id = wp_get_post_parent_id( $object_id ) ) {
			wc_delete_product_transients( $parent_id );
		}

		if ( ! $result ) {

			$message = sprintf( __( 'Error: %s delete is failed.', AINSYS_CONNECTOR_TEXTDOMAIN ), self::$entity );
			$this->handle_error( $data, '', $message, self::$entity, $action );

			return [
				'id'      => 0,
				'message' => $message,
			];

		}

		//$message = sprintf( __( 'Success: %s delete is done.', AINSYS_CONNECTOR_WOOCOMMERCE_TEXTDOMAIN ), self::$entity);

		return [
			'id'      => $object_id,
			'message' => $this->get_message( $object_id, $data, self::$entity, $action ),
		];

	}

}
