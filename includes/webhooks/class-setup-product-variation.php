<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

class Setup_Product_Variation extends Setup_Product {

	protected $parent_product;

	public function __construct($product, $data, $parent_product) {

		parent::__construct($product, $data);

		$this->parent_product = $parent_product;

	}

	public function setup_product_variation(){
		var_dump('start variation');
		/**
		 * Add Variation to product
		 */
		$this->product->set_parent_id($this->parent_product->get_id());

		/**
		 * C
		 */
		$this->product->save();

		/**
		 * Setup variation general info
		 */
		$this->setup_variation_general_info();

		/**
		 * Setup variation attributes
		 */
		$this->product->set_attributes(
			$this->data['attributes']
		);

		/**
		 * Setup variation prices
		 */
//		$this->setup_variation_price($variation);
		parent::set_price_info();

		/**
		 * Setup Variation Stock Info
		 */
		parent::set_stock_info();

		/**
		 * Setup variation image
		 */
		$this->product->set_image_id(
			parent::setup_image($this->data['image'])
		);

		/**
		 * Setup variation downloads
		 */
		parent::set_downloadable_info();

		/**
		 * Setup Shipping Info
		 */
		parent::set_shipping_info();

		/**
		 * Setup variation tax info
		 */
		parent::set_taxes_info();

		var_dump('end variation');
	}

	private function setup_variation_general_info(){

		$this->product->set_virtual($this->data['is_virtual']);

		if(!empty($this->data['sku'])){
			$this->product->set_sku('');
//			$this->product->set_sku($this->data['sku']);
		}

		$this->product->set_description($this->data['variation_description']);

	}

}