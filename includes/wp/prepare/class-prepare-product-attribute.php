<?php

namespace Ainsys\Connector\Woocommerce\WP\Prepare;

use Ainsys\Connector\Master\Helper;

class Prepare_Product_Attribute {

	protected object $attribute;


	public function __construct( $attribute ) {

		if ( is_array( $attribute ) ) {
			$this->attribute = (object) $attribute;
		} else {
			$this->attribute = $attribute;
		}

	}


	public function prepare_data(): array {

		if ( ! isset( $this->attribute->attribute_id ) || $this->attribute->attribute_id === 0 ) {
			$this->attribute->attribute_id = wc_attribute_taxonomy_id_by_name( $this->attribute->attribute_label );
		}

		return [
			'id'                => $this->attribute->attribute_id . '_' . Helper::random_int( 78 ),
			'attribute_label'   => $this->attribute->attribute_label,
			'attribute_name'    => $this->attribute->attribute_name,
			'attribute_public'  => (bool) $this->attribute->attribute_public,
			'attribute_type'    => $this->attribute->attribute_type,
			'attribute_orderby' => $this->attribute->attribute_orderby,
			'attribute_terms'   => $this->get_attribute_terms(),
		];

	}


	private function get_attribute_terms(): array {

		$format_terms = [];

		$attribute_terms = get_terms( [
			'taxonomy'   => wc_attribute_taxonomy_name( $this->attribute->attribute_name ),
			'hide_empty' => false,
		] );

		if ( ! is_wp_error( $attribute_terms ) ) {
			foreach ( $attribute_terms as $term ) {
				$format_terms[] = $term->name;
			}
		}

		return $format_terms;

	}

}