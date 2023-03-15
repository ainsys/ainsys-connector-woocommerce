<?php

namespace Ainsys\Connector\Woocommerce\Webhooks\Setup;

use Ainsys\Connector\Woocommerce\Helper;

class Setup_Product_Variation extends Setup_Product {

	protected $parent_product;

	public function __construct($product, $data, $parent_product) {

		parent::__construct($product, $data);

		$this->parent_product = $parent_product;

	}

	public function setup_product_variation(){
		/**
		 * Add Variation to product
		 */
		$this->product->set_parent_id($this->parent_product->get_id());

		//

		/**
		 * Setup variation general info
		 */
		$this->setup_variation_general_info();

		/**
		 * Setup variation attributes
		 */
		$this->product->set_attributes(
			$this->format_variation_attributes()
		);

		/**
		 * Setup variation prices
		 */
		parent::set_price_info();

		/**
		 * Setup Variation Stock Info
		 */
		parent::set_stock_info();

		/**
		 * Setup variation image
		 */
		$this->product->set_image_id(
			parent::get_image($this->data['image'])
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


		$this->product->save();

	}

	private function format_variation_attributes(){

		$formatted_attributes = [];
		//error_log( print_r($this->data['attributes'] , 1 ) );
		if(!empty($this->data['attributes'])){

			foreach($this->data['attributes'] as $attribute_key => $attribute_value){
				$formatted_attributes[$attribute_key] = Helper::format_term_value($attribute_value, $attribute_key, 'name', 'slug');
			}

		}
		/*error_log( print_r('$formatted_attributes' , 1 ) );
		error_log( print_r($formatted_attributes , 1 ) );*/
		return $formatted_attributes;

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