<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Woocommerce\WP\Prepare\Prepare_Order;
use WC_Order;

class Process_Orders extends Process implements Hooked {

	protected static string $entity = 'shop_order';


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'woocommerce_new_order', [ $this, 'process_create' ], 10, 1 );
		add_action( 'woocommerce_update_order', [ $this, 'process_update' ], 10, 2 );
		add_action( 'woocommerce_delete_order', [ $this, 'process_delete' ], 10, 1 );
		add_action( 'woocommerce_trash_order', [ $this, 'process_delete' ], 10, 1 );
	}


	/**
	 * Sends new product details to AINSYS
	 *
	 * @param  int $order_id
	 *
	 * @return void
	 */
	public function process_create( int $order_id ): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}
		error_log( print_r( $order_id, 1 ) );
		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data( $order_id ),
			$order_id
		);

		$this->send_data( $order_id, self::$entity, self::$action, $fields );

	}


	/**
	 * Sends updated product details to AINSYS.
	 *
	 * @param  int $order_id
	 * @param      $order
	 */
	public function process_update( int $order_id, $order ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $order_id ),
			$order_id
		);

		$this->send_data( $order_id, self::$entity, self::$action, $fields );
	}


	/**
	 * Sends delete post details to AINSYS
	 *
	 * @param  int $order_id
	 *
	 * @return void
	 */
	public function process_delete( int $order_id ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_delete_fields_' . self::$entity,
			$this->prepare_data( $order_id ),
			$order_id
		);

		$this->send_data( $order_id, self::$entity, self::$action, $fields );

	}

	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param $order_id
	 *
	 * @return array
	 */
	public function process_checking( $order_id ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $order_id ),
			$order_id
		);

		return $this->send_data( $order_id, self::$entity, self::$action, $fields );
	}


	/**
	 * @param $order_id
	 *
	 * @return array
	 * Prepare product data, for send to AINSYS
	 */
	public function prepare_data( $order_id ): array {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return [];
		}

		return ( new Prepare_Order( $order ) )->prepare_data();

	}

}