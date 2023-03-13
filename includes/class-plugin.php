<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\DI_Container;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Woocommerce\Settings\Admin_UI;
use Ainsys\Connector\Woocommerce\Settings\Admin_Ui_Order_Entity_Check;
use Ainsys\Connector\Woocommerce\Settings\Admin_Ui_Product_Attribute_Check;
use Ainsys\Connector\Woocommerce\Settings\Admin_Ui_Product_Category_Check;
use Ainsys\Connector\Woocommerce\Settings\Admin_Ui_Product_Entity_Check;
use Ainsys\Connector\Woocommerce\Settings\Admin_Ui_Product_Tag_Check;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Product;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Product_Attribute;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Product_Cat;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Product_Tag;
use Ainsys\Connector\Woocommerce\WP\Process_Orders;
use Ainsys\Connector\Woocommerce\WP\Process_Product_Attribute;
use Ainsys\Connector\Woocommerce\WP\Process_Product_Cat;
use Ainsys\Connector\Woocommerce\WP\Process_Product_Tag;
use Ainsys\Connector\Woocommerce\WP\Process_Products;

class Plugin implements Hooked {

	use Plugin_Common;

	/**
	 * @var DI_Container;
	 */
	public $di_container;


	public function __construct() {


		$this->init_plugin_metadata();

		$this->di_container = DI_Container::get_instance();

		/**
		 * Check if entities is enabled
		 */
		$this->components['check_order_entity']             = $this->di_container->resolve( Admin_Ui_Order_Entity_Check::class );
		$this->components['check_product_entity']           = $this->di_container->resolve( Admin_Ui_Product_Entity_Check::class );
		$this->components['check_product_category_entity']  = $this->di_container->resolve( Admin_Ui_Product_Category_Check::class );
		$this->components['check_product_attribute_entity'] = $this->di_container->resolve( Admin_Ui_Product_Attribute_Check::class );
		$this->components['check_product_tag_entity']       = $this->di_container->resolve( Admin_Ui_Product_Tag_Check::class );

		$this->components['woo_ui'] = $this->di_container->resolve( Admin_UI::class );

		$this->components['product_webhook']           = $this->di_container->resolve( Handle_Product::class );
		$this->components['product_cat_webhook']       = $this->di_container->resolve( Handle_Product_Cat::class );
		$this->components['product_tag_webhook']       = $this->di_container->resolve( Handle_Product_Tag::class );
		$this->components['product_attribute_webhook'] = $this->di_container->resolve( Handle_Product_Attribute::class );

		$this->components['process_products']          = $this->di_container->resolve( Process_Products::class );
		$this->components['process_product_cat']       = $this->di_container->resolve( Process_Product_Cat::class );
		$this->components['process_product_attribute'] = $this->di_container->resolve( Process_Product_Attribute::class );
		$this->components['process_product_tag']       = $this->di_container->resolve( Process_Product_Tag::class );
		$this->components['process_orders']            = $this->di_container->resolve( Process_Orders::class );
	}


	/**к
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {

		if ( $this->is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			foreach ( $this->components as $component ) {
				if ( $component instanceof Hooked ) {
					$component->init_hooks();
				}
			}
		}
	}

}
