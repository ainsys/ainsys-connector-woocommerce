<?php

namespace Ainsys\Connector\Woocommerce;

class Prepare_Product_Attribute_Data {

	protected $attribute;

	public function __construct($attribute) {

		if(is_array($attribute)){
			$this->attribute = (object) $attribute;
		}else{
			$this->attribute = $attribute;
		}

	}

	public function prepare_data(){

		if(!isset($this->attribute->attribute_id) ||
			$this->attribute->attribute_id == 0){
			$this->attribute->attribute_id = wc_attribute_taxonomy_id_by_name($this->attribute->attribute_label);
		}


		$data = [
			'id' => $this->attribute->attribute_id . '_' . random_int(78, 9999999),
			'attribute_label' => $this->attribute->attribute_label,
			'terms' => $this->get_attribute_terms()
		];

		return $data;

	}

	private function get_attribute_terms(){

		$format_terms = [];

		$attribute_terms = get_terms([
			'taxonomy' => 'pa_' . $this->attribute->attribute_name,
			'hide_empty' => false
		                   ] );

		if(!is_wp_error($attribute_terms)){
			foreach($attribute_terms as $term){
				$format_terms[] = $term->name;
			}
		}

		return $format_terms;

	}

}