<?php

namespace Ainsys\Connector\Woocommerce;

class Prepare_Product_Tag_Data {

	protected $product_tag;

	public function __construct($product_tag) {
		$this->product_tag = $product_tag;
	}

	public function prepare_data(){

		$data = [
			'id' => $this->product_tag->term_id . '_' . random_int(78, 9999999),
//			'term_id' => $this->product_tag->term_id,
			'slug' => $this->product_tag->slug,
			'description' => $this->product_tag->description,
			'name' => $this->product_tag->name,
		];

		return $data;

	}

}