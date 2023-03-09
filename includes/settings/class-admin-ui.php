<?php

namespace Ainsys\Connector\Woocommerce\Settings;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Plugin_Common;

class Admin_UI implements Hooked {

	use Plugin_Common;

	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {

		if ( $this->is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			add_filter( 'ainsys_status_list', [ $this, 'add_status_of_component' ], 10, 1 );
			add_filter( 'ainsys_get_entities_list', [ $this, 'add_woocommerce_entities_to_list' ], 10, 1 );
		}

	}


	/**
	 * Generates a component status to show on the General tab of the master plugin settings.
	 *
	 * @param  array $status_items
	 *
	 * @return array
	 */
	public function add_status_of_component( array $status_items = [] ): array {

		$status_items['woocommerce'] = [
			'title'   => __( 'WooCommerce', AINSYS_CONNECTOR_WOOCOMMERCE_TEXTDOMAIN ),
			'slug'    => 'woocommerce',
			'active'  => $this->is_plugin_active( 'woocommerce/woocommerce.php' ),
			'install' => $this->is_plugin_install( 'woocommerce/woocommerce.php' ),
		];

		return $status_items;
	}


	/**
	 * Add Woocommerce entities to Admin panel from check
	 *
	 * @param $entities_list
	 *
	 * @return mixed
	 */

	public function add_woocommerce_entities_to_list( $entities_list ) {

		$entities_list['product']           = __( 'Product / fields', AINSYS_CONNECTOR_WOOCOMMERCE_TEXTDOMAIN );
		$entities_list['product_cat']       = __( 'Product Category / fields', AINSYS_CONNECTOR_WOOCOMMERCE_TEXTDOMAIN );
		$entities_list['product_tag']       = __( 'Product Tag / fields', AINSYS_CONNECTOR_WOOCOMMERCE_TEXTDOMAIN );
		$entities_list['product_attribute'] = __( 'Product Attribute / fields', AINSYS_CONNECTOR_WOOCOMMERCE_TEXTDOMAIN );

		return $entities_list;

	}

}
