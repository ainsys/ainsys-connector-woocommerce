<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Process;

class Process_Orders extends Process implements Hooked{

	protected static string $entity = 'orders';


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

//		add_filter( 'ainsys_get_entities_list', array( $this, 'add_order_entity_to_list' ), 10, 1 );

		/**
		 * Check entity connection for products
		 */
		/*add_filter( 'ainsys_before_check_connection_make_request', function () {
			return true;
		} );
		add_filter( 'ainsys_check_connection_request', [ $this, 'check_order_entity' ], 15, 3 );*/

//		add_action( 'woocommerce_new_product', 'on_product_save', 10, 1 );
//		add_action( 'save_post_product', [ $this, 'process_update' ], 10, 4 );
//		add_action( 'deleted_post', [ $this, 'process_delete' ], 10, 2 );

	}

}