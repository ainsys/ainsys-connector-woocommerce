<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Webhook_Handler;

class Handle_Order implements Hooked, Webhook_Handler {

	public function __construct() {
	}

	/**
	 * Initializes WordPress hooks for component.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'ainsys_webhook_action_handlers', array( $this, 'register_webhook_handler' ), 10, 1 );
	}

	public function register_webhook_handler( $handlers = array() ) {
		$handlers['order'] = array( $this, 'handler' );

		return $handlers;
	}

	/**
	 * @throws \WC_Data_Exception
	 */
	public function handler( $action, $data, $object_id = 0 ) {
		switch ( $action ) {
			case 'add':
				$order = wc_create_order( $data );
				if ( ! is_wp_error( $order ) ) {
					return $order->get_id();
				} else {
					return $order->get_error_message();
				}

			case 'update':
				if ( ! wc_get_order( $object_id ) ) {
					return 'Заказ не найден';
				}

				return $this->update_order( $data, $object_id );
			case 'delete':
				return 'Метод DELETE не реализован';
		}

		return 'Action not registered';
	}

	/**
	 * @param array $data
	 * @param $object_id
	 *
	 * @return false|int
	 * @throws \WC_Data_Exception
	 */
	private function update_order( $data, $object_id ) {
		$data       = (array) $data;
		$data['id'] = $object_id;
		$order      = wc_get_order( $data["id"] ); // now use wc_get_order instead of new \WC_Order() as recommended in woocommerce.
		if ( $order ) {
			$fields_prefix = array(
				'shipping' => true,
				'billing'  => true,
			);

			$shipping_fields = array(
				'shipping_method' => true,
				'shipping_total'  => true,
				'shipping_tax'    => true,
			);
			foreach ( $data as $key => $value ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );
					// Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
				} elseif
				( isset( $fields_prefix[ current( explode( '_', $key ) ) ] )
				) {
					if ( ! isset( $shipping_fields[ $key ] ) ) {
						$order->update_meta_data( '_' . $key, $value );
					}
				}
			}

			$order->hold_applied_coupons( $data['billing_email'] );
			$order->set_created_via( 'checkout' );
			//TODO - check if we pass it in with $data[] - and if yes, if we need to sync it.
//			$order->set_cart_hash( $cart_hash ); // we don't have such variable here, it get's declared upon initial order creation.
//
			$order->set_customer_id( $data['customer_id'] );
			$order->set_currency( get_woocommerce_currency() );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_customer_ip_address( isset( $data['USER_IP'] ) ? $data['USER_IP'] : '' );
			$order->set_customer_user_agent( wc_get_user_agent() );
			$order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
			$order->set_payment_method( isset( $available_gateways[ $data['payment_method'] ] ) ? $available_gateways[ $data['payment_method'] ] : $data['payment_method'] );

			return $order->save();
		}

		return false;
	}


}