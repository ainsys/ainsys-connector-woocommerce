<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\DI_Container;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Woocommerce\Settings\Admin_Ui_Product_Entity_Check;
use Ainsys\Connector\Woocommerce\Settings\Admin_Ui_Order_Entity_Check;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Order;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Product;
use Ainsys\Connector\Woocommerce\WP\Process_Orders;
use Ainsys\Connector\Woocommerce\WP\Process_Products;

class Plugin implements Hooked {

	use Plugin_Common;

	/**
	 * @var Helper
	 */
	private $Helper;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var DI_Container;
	 */
	public $di_container;


	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		$this->Helper   = new Helper();

		$this->init_plugin_metadata();

		$this->di_container = DI_Container::get_instance();

		$this->components['check_product_entity'] = $this->di_container->resolve( Admin_Ui_Product_Entity_Check::class );
//		$this->components['check_order_entity'] = $this->di_container->resolve( Admin_Ui_Order_Entity_Check::class );
		$this->components['woo_ui']               = $this->di_container->resolve( Woo_UI::class );

		$this->components['product_webhook']      = $this->di_container->resolve( Handle_Product::class );
//		$this->components['order_webhook']      = $this->di_container->resolve( Handle_Order::class );

		$this->components['process_products'] = $this->di_container->resolve( Process_Products::class );
//		$this->components['process_orders']   = $this->di_container->resolve( Process_Orders::class );
	}

	/**к
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( $this->Helper->is_woocommerce_active() ) {
			foreach ( $this->components as $component ) {
				if ( $component instanceof Hooked ) {
					$component->init_hooks();
				}
			}
		}
	}
}
