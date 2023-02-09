<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Woocommerce\Webhooks\Plugin;

class Woo_UI implements Hooked {

	use Plugin_Common;

	private $Helper;

	public function __construct() {
		$this->Helper = new Helper();
	}


	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {

		if ( $this->Helper->is_woocommerce_active() ) {
			add_filter( 'ainsys_status_list', array( $this, 'add_status_of_component' ), 10, 1 );
			add_filter( 'ainsys_get_entities_list', array( $this, 'add_woocommerce_entities_to_list' ), 10, 1 );
		}

	}

	/**
	 * Generates a component status to show on the General tab of the master plugin settings.
	 *
	 * @return array
	 */
	public function add_status_of_component( $status_items = array() ) {

		$status_items['woocommerce'] = array(
			'title'  => __( 'WooCommerce', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'active' => $this->Helper->is_woocommerce_active(),
		);

		return $status_items;
	}

	/**
	 * @param $entities_list
	 *
	 * @return mixed
	 * Add Woocommerce entities to Admin panel from check
	 */

	public function add_woocommerce_entities_to_list($entities_list){

		$entities_list['product'] = __( 'Product / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
//		$entities_list['shop_order'] = __( 'Order / fields', AINSYS_CONNECTOR_TEXTDOMAIN );

		/*if ( function_exists( 'wc_coupons_enabled' ) ) {
			if ( wc_coupons_enabled() ) {
				$entities_list['coupons'] = __( 'Coupons / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
			}
		}*/

		return $entities_list;

	}

}
