<?php

namespace Ainsys\Connector\Woocommerce;

class Prepare_Product_Cat_Data {

	protected $product_cat;

	public function __construct($product_cat) {
		$this->product_cat = $product_cat;
	}

	public function prepare_data(){

		$data = [
			'id' => $this->product_cat->term_id . '_' . random_int(78, 9999999),
//			'term_id' => $this->product_cat->term_id,
			'slug' => $this->product_cat->slug,
			'name' => $this->product_cat->name,
			'description' => $this->product_cat->description,
			'parent' => $this->product_cat->parent
		];

		return $data;

	}

}