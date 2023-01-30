<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\WP\Process;

class Prepare_Product_Data {

	protected $product;
	protected $process;

	public function __construct( $product ) {
		$this->product = $product;
		$this->process = new Process();
	}

	public function prepare_data() {
		$data = [];

		/**
		 * Get product Main Info
		 */
		$data = array_merge( $data, $this->get_general_info() );

		if ( $this->product->is_type( 'external' ) ) {
			/**
			 * Merge External Link info
			 */
			$data = array_merge( $data, $this->get_external_info() );
		}

		if ( ! $this->product->is_type( 'grouped' ) ) {
			/**
			 * Merge product price info
			 */
			$data = array_merge( $data, $this->get_prices_info() );
		}

		if ( ! $this->product->is_type( 'grouped' ) &&
		     ! $this->product->is_type( 'external' ) ) {
			/**
			 * Merge taxes data
			 */
			$data = array_merge( $data, $this->get_taxes_info() );

			/**
			 * Merge stock info
			 */
			$data = array_merge( $data, $this->get_stock_info() );

			/**
			 * Merge Shipping info
			 */
			$data = array_merge( $data, $this->get_shipping_info() );
		}

		/**
		 * Merge Linked Products
		 */
		$data = array_merge( $data, $this->get_linked_products() );

		/**
		 * Merge Taxonomies Info
		 */
		$data = array_merge( $data, $this->get_taxonomies_info() );

		if ( $this->product->is_downloadable() ) {
			/**
			 * Merge downloadable info
			 */
			$data = array_merge( $data, $this->get_downloadable_info() );
		}

		/**
		 * Merge images info
		 */
		$data = array_merge( $data, $this->get_images_info() );

		/**
		 * Merge Reviews info
		 */
		$data = array_merge( $data, $this->get_reviews_info() );

		/**
		 * Merge Metadata info
		 */
//		$data = array_merge( $data, $this->get_metadata_info() );

		return apply_filters( 'ainsys_prepared_data_before_send', $data, $this->product );
	}

	public function get_external_info() {
		return [
			'external_url'         => $this->product->get_product_url(),
			'external_button_text' => $this->product->get_button_text()
		];
	}

	public function get_metadata_info() {
		return [
			'metadata' => $this->product->get_meta_data()
		];
	}

	public function get_reviews_info() {
		return [
			'reviews_allowed' => $this->product->get_reviews_allowed(),
			'rating_counts'   => $this->product->get_rating_counts(),
			'average_rating'  => $this->product->get_average_rating(),
			'review_count'    => $this->product->get_review_count()
		];
	}

	public function get_images_info() {
		$data = [
			'image'              => $this->get_image_data_by_id(
				$this->product->get_image_id()
			),
			'gallery_images_ids' => []
		];

		if ( $this->product->get_gallery_image_ids() ) {
			foreach ( $this->product->get_gallery_image_ids() as $id ) {
				$data['gallery_images_ids'][] = $this->get_image_data_by_id( $id );
			}
		}

		return $data;
	}

	public function get_downloadable_info() {
		$data = [
			'downloads'       => $this->get_product_downloads(),
			'download_expiry' => $this->product->get_download_expiry(),
			'downloadable'    => $this->product->get_downloadable(),
			'download_limit'  => $this->product->get_download_limit(),
		];

		return $data;
	}

	/**
	 * @param $product
	 *
	 * @return array
	 */
	protected function get_product_downloads( $product = '' ) {
		if ( empty( $product ) ) {
			$product = $this->product;
		}

		$data = [];

		foreach ( $product->get_downloads() as $download ) {
			$data[] = [
				'name'    => $download['name'],
				'file'    => $download['file'],
				'enabled' => $download['enabled']
			];
		}

		return $data;
	}

	public function get_taxonomies_info() {
		$data = [
			'category_ids'       => $this->product->get_category_ids(),
			'product_cat'        => get_the_terms( $this->product->get_id(), 'product_cat' ),
			'tag_ids'            => get_the_terms( $this->product->get_id(), 'product_tag' ),
			'default_attributes' => $this->get_default_attributes_info(),
			'attributes'         => $this->get_attributes_info()
		];

		return $data;
	}

	/**
	 * @return array
	 * Special Formatted for default attributes info
	 */

	protected function get_default_attributes_info(){

		$formatted_attributes = [];

		$default_attributes = $this->product->get_default_attributes();

		if(is_array($default_attributes)){

			foreach($default_attributes as $attribute_key => $attribute_value){
				$formatted_attributes[$attribute_key] = Helper::format_term_value($attribute_value, $attribute_key, 'slug', 'name');
			}

		}

		return $formatted_attributes;

	}

	protected function get_attributes_info() {
		$attributes = [];
		foreach ( $this->product->get_attributes() as $attr_key => $attribute ) {
			$attr = [
				'id'        => $attribute['id'],
				'taxonomy_slug' => $attr_key,
				'name'      => $attribute['name'],
				'position'  => $attribute['position'],
				'visible'   => $attribute['visible'],
				'variation' => $attribute['variation']
			];

			if ( ! empty( $attribute['options'] ) && is_array( $attribute['options'] ) ) {
				foreach ( $attribute['options'] as $option ) {
					$attr['options'][] = (is_int($option)) ? Helper::format_term_value($option, $attr_key, 'term_id', 'name') : $option;
				}
			}

			$attributes[] = $attr;
		}

		return $attributes;
	}

	public function get_linked_products() {
		$data = [
			'upsell_ids' => $this->product->get_upsell_ids(),
		];

		if ( ! $this->product->is_type( 'grouped' &&
		                                ! $this->product->is_type( 'external' ) ) ) {
			$data = array_merge(
				$data,
				[
					'cross_sell_ids'   => $this->product->get_cross_sell_ids(),
					'related_products' => wc_get_related_products( $this->product->get_id(), - 1 )
				] );
		}

		if ( $this->product->is_type( 'grouped' ) ) {
			$data['grouped_products_ids'] = $this->product->get_children();
		}

		return $data;
	}

	/**
	 * @return array
	 */
	public function get_shipping_info() {
		return [
			'purchase_note'     => $this->product->get_purchase_note(),
			'shipping_class_id' => $this->product->get_shipping_class_id(),
			'shipping_class'    => $this->product->get_shipping_class(),
			'weight'            => $this->product->get_weight(),
			'length'            => $this->product->get_length(),
			'width'             => $this->product->get_width(),
			'height'            => $this->product->get_height(),
		];
	}

	public function get_stock_info() {
		return [
			'manage_stock'          => $this->product->get_manage_stock(),
			'stock_qty'             => $this->product->get_stock_quantity(),
			'stock_status'          => $this->product->get_stock_status(),
			'backorders'            => $this->product->get_backorders(),
			'sold_individuality'    => $this->product->get_sold_individually(),
			'availability'          => $this->product->get_availability(),
			'max_purchase_quantity' => $this->product->get_max_purchase_quantity(),
			'min_purchase_quantity' => $this->product->get_min_purchase_quantity(),
			'low_stock_amount'      => $this->product->get_low_stock_amount()
		];
	}

	public function get_taxes_info() {
		return [
			'taxable'    => $this->product->is_taxable(),
			'tax_status' => $this->product->get_tax_status(),
			'tax_class'  => $this->product->get_tax_class(),
		];
	}

	public function get_general_info() {
		return [
			'ID'                 => $this->product->get_id(),
			'type'               => $this->product->get_type(),
			'name'               => $this->product->get_name(),
			'slug'               => $this->product->get_slug(),
			'date_created'       => $this->product->get_date_created(),
			'date_modified'      => $this->product->get_date_modified(),
			'status'             => $this->product->get_status(),
			'is_featured'        => $this->product->get_featured(),
			'catalog_visibility' => $this->product->get_catalog_visibility(),
			'description'        => $this->product->get_description(),
			'short_description'  => $this->product->get_short_description(),
			'sku'                => $this->product->get_sku(),
			'menu_order'         => $this->product->get_menu_order(),
			'is_virtual'         => $this->product->get_virtual(),
			'total_sales'        => $this->product->get_total_sales()
		];
	}

	public function get_prices_info() {
		$sale_date_from = $this->product->get_date_on_sale_from();
		$sale_date_to   = $this->product->get_date_on_sale_to();

		if ( is_object( $sale_date_from ) ) {
			$sale_date_from = $sale_date_from->date( 'Y-m-d H:m:s' );
		}

		if ( is_object( $sale_date_to ) ) {
			$sale_date_to = $sale_date_to->date( 'Y-m-d H:m:s' );
		}

		return [
			'price'               => (int) $this->product->get_price(),
			'regular_price'       => (int) $this->product->get_regular_price(),
			'sale_price'          => (int) $this->product->get_sale_price(),
			'date_on_sale_from'   => $sale_date_from,
			'date_on_sale_to'     => $sale_date_to,
			'price_excluding_tax' => wc_get_price_excluding_tax( $this->product ),
			'price_includes_tax'  => wc_get_price_including_tax( $this->product )
		];
	}

	protected function get_image_data_by_id( $id ) {
		if ( empty( $id ) ) {
			return [];
		}

		$image = get_post( $id );

		if ( ! $image ) {
			return [];
		}

		$image_data = [
			'alt'         => get_post_meta( $image->ID, '_wp_attachment_image_alt', true ),
			'caption'     => $image->post_excerpt,
			'description' => $image->post_content,
			'src'         => $image->guid,
			'title'       => $image->post_title
		];

		return $image_data;
	}

}