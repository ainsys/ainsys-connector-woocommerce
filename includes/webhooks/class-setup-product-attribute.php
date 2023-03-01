<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Woocommerce\Helper;

class Setup_Product_Attribute {

	protected $data;
	protected $helper;
	protected $new_attr_id;

	public function __construct( $data ) {
		$this->data   = $data;
		$this->helper = new Helper();
	}

	public function setup_product_attribute() {

		if ( $this->helper->attribute_taxonomy_exist( $this->data['attribute_label'] ) ) {
			$this->setup_attribute_terms();
		} else {
			$attribute_id = $this->helper->create_attribute_taxonomy( sanitize_title( $this->data['attribute_label'] ),
			                                                          $this->data );
			if ( ! is_wp_error( $attribute_id ) ) {
				$this->setup_attribute_terms();
			}
		}

	}

	/**
	 * Create or Update Attribute options(terms)
	 */
	protected function setup_attribute_terms() {
		$taxonomy = 'pa_' . sanitize_title( $this->data['attribute_label'] );

		if ( isset( $this->data['terms'] ) ) {
			$attribute_terms = get_terms( [
				                              'taxonomy'   => $taxonomy,
				                              'hide_empty' => false,
				                              'fields'     => 'id=>name'
			                              ] );

			if ( ! empty( $attribute_terms ) && is_array( $attribute_terms ) ) {
				foreach ( $attribute_terms as $attr_id => $attr_name ) {
					if ( ! in_array( $attr_name, $this->data['terms'] ) ) {
						wp_delete_term( $attr_id, $taxonomy );
					}
				}
			}

			if ( ! empty( $this->data['terms'] ) ) {
				foreach ( $this->data['terms'] as $term ) {
					if ( ! term_exists( $term, $taxonomy ) ) {
						Helper::add_term( $term, $taxonomy );
					}
				}
			}
		}
	}

}