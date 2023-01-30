<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Woocommerce\Helper;

class Setup_Product {

	protected $product_id;
	protected $data;
	protected $action;
	protected $product;
	protected $helper;

	public function __construct( $product, $data ) {
		$this->data       = $data;
		$this->product_id = (int) $data['ID'];
		$this->product    = $product;
		$this->helper     = new Helper();
	}

	public function setup_product() {

		$this->product->save();

		$this->set_general_info();
		$this->set_price_info();
		$this->set_taxes_info();
		$this->set_stock_info();
		$this->set_shipping_info();
		$this->set_linked_products();
		$this->set_images_info();
		$this->set_downloadable_info();
		$this->setup_taxonomies();
		$this->setup_external_data();
		$this->setup_grouped_products_info();

	}

	public function setup_grouped_products_info(){

		if(isset($this->data['grouped_products_ids'])){

			if(!is_array($this->data['grouped_products_ids'])){
				$this->data['grouped_products_ids'] = [];
			}

			$this->product->set_children($this->data['grouped_products_ids']); // Must be ids(int) array

		}

	}

	public function setup_external_data(){

		if(isset($this->data['external_url'])){
			$this->product->set_product_url($this->data['external_url']);
		}

		if(isset($this->data['external_button_text'])){
			$this->product->set_button_text($this->data['external_button_text']);
		}

	}

	public function setup_taxonomies() {

		if(isset($this->data['product_cat'])){
			$this->product->set_category_ids(
				$this->setup_product_terms_ids( $this->data['product_cat'], 'product_cat' )
			);
		}

		if(isset($this->data['tag_ids'])){
			$this->product->set_tag_ids(
				$this->setup_product_terms_ids( $this->data['tag_ids'], 'product_tag' )
			);
		}

		if ( isset( $this->data['attributes'] ) ) {
			$this->product->set_attributes( [] );

			$attributes = $this->setup_attributes( $this->data['attributes'] );

			$this->product->set_attributes( $attributes );
		}

		if ( $this->data['type'] === 'variable' ) {
			$this->product->set_default_attributes(
				$this->set_default_attributes_info()
			);
		}

	}

	protected function set_default_attributes_info(){

		$default_attributes = [];

		if(!empty($this->data['default_attributes']) && is_array($this->data['default_attributes'])){

			foreach($this->data['default_attributes'] as $attr_key => $attr_value){

				$default_attributes[$attr_key] = Helper::format_term_value($attr_value, $attr_key, 'name', 'slug');

			}

		}

		return $default_attributes;

	}

	/**
	 * @param $terms
	 * @param $taxonomy
	 *
	 * @return array
	 * Setup product categories
	 */
	protected function setup_product_terms_ids( $terms, $taxonomy ) {

		$ids = [];

		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return $ids;
		}

		wp_suspend_cache_invalidation( true );

		foreach ( $terms as $term ) {
			if ( term_exists( $term['term_id'], $term['taxonomy'] ) ) {
				$update_term = $this->helper->update_term( $term );

				if ( is_wp_error( $update_term ) ) {
					//TODO: return log error
				} else {
					$ids[] = $update_term['term_id'];
				}
			} else {
				$add_term = $this->helper->add_term( $term );

				if ( is_wp_error( $add_term ) ) {
					//TODO: return log error
				} else {
					$ids[] = $add_term['term_id'];
				}
			}
		}

		wp_suspend_cache_invalidation( false );

		return array_unique( $ids );
	}

	/**
	 * @param $attributes
	 *
	 * @return array
	 *
	 * This function Create new attributes from data from AINSYS
	 */
	protected function setup_attributes( $attributes ) {
		$new_attributes = [];

		foreach ( $attributes as $attribute ) {
			$new_attribute = new \WC_Product_Attribute();

			if ( $this->helper->is_taxonomy_attribute( $attribute['taxonomy_slug'] ) ) {
				if ( ! $this->helper->attribute_taxonomy_exist( $attribute['taxonomy_slug'] ) ) {
					$result = $this->helper->create_attribute_taxonomy( $attribute['taxonomy_slug'], $attribute );

					if ( is_wp_error( $result ) ) {
						//TODO: return log error
					} else {
						$new_attribute->set_id( $result );
					}
				} else {
					$new_attribute->set_id( wc_attribute_taxonomy_id_by_name( $attribute['taxonomy_slug'] ) );
				}

				$new_attribute->set_name( $attribute['name'] );

//				$options = Helper::format_terms_name_to_ids( $attribute['options'], $attr_key );

				$new_attribute->set_options( $attribute['options'] );
			} else {
				$new_attribute->set_name( $attribute['name'] );
				$new_attribute->set_options( $attribute['options'] );
			}

			$new_attribute->set_position( $attribute['position'] );
			$new_attribute->set_visible( $attribute['visible'] );
			$new_attribute->set_variation( $attribute['variation'] );

			$new_attributes[] = $new_attribute;
		}

		return $new_attributes;
	}

	public function set_downloadable_info() {
		if ( ! isset( $this->data['downloadable'] ) ) {
			return;
		}

		$this->product->set_downloadable( $this->data['downloadable'] );

		if(isset($this->data['download_limit'])){
			$this->product->set_download_limit( $this->data['download_limit'] ); // int \ if -1 = unlimited
		}

		if(isset($this->data['download_expiry'])){
			$this->product->set_download_expiry( $this->data['download_expiry'] );
		}

		if ( $this->data['downloadable'] === true &&
		     isset( $this->data['downloads'] ) ) {
			$downloads = [];

			/**
			 * Unset downloads
			 */
			$this->product->set_downloads( [] );

			foreach ( $this->data['downloads'] as $download ) {
				if ( ! is_array( $download ) ) {
					continue;
				}

				$downloads[] = $this->create_download( $download );
			}

			$this->product->set_downloads( $downloads );
		}
	}

	protected function create_download( $data ) {
		$download = new \WC_Product_Download();

		$attachment_id = Helper::get_attachment_id_by_url($data['file']);

		if(!$attachment_id || $attachment_id === 0){
			$attachment_id = Helper::upload_image_to_library( $data );
		}

		if ( ! $attachment_id ) {
			return false;
		}

		$file_url = wp_get_attachment_url( $attachment_id ); // attachment ID should be here

		$download->set_name( $data['name'] );
		$download->set_id( md5( $file_url ) );
		$download->set_file( $file_url );
		$download->set_enabled( $data['enabled'] );

		return $download;
	}

	public function set_reviews_info() {

		if(isset($this->data['reviews_allowed'])){
			$this->product->set_reviews_allowed( $this->data['reviews_allowed'] );
		}

		if(isset($this->data['rating_counts'])){
			$this->product->set_rating_counts( $this->data['rating_counts'] );
		}

	}

	public function set_images_info() {

		if(isset($this->data['image'])){
			$this->product->set_image_id(
				$this->setup_image( $this->data['image'] )
			);
		}



		if(isset($this->data['gallery_images_ids'])){

			$this->product->set_gallery_image_ids([]);

			$gallery_images = $this->data['gallery_images_ids'];

			if ( ! empty( $gallery_images ) && is_array( $gallery_images ) ) {

				$images_ids = [];

				foreach ( $gallery_images as $image ) {
					$images_ids[] = $this->setup_image( $image );
				}

				/**
				 * Set gallery images
				 */
				$this->product->set_gallery_image_ids( $images_ids );
			}

		}

	}

	/**
	 * @param array $image
	 *
	 * @return false|int|string|\WP_Error
	 */
	protected function setup_image( array $image ) {

		$image_id = Helper::get_attachment_id_by_url($image['src']);


		if($image_id && $image_id !== 0){
			$image['id'] = $image_id;
			Helper::update_image_metadata( $image );
		}else{
			$image_id = Helper::upload_image_to_library( $image );
		}

		return $image_id;
	}

	public function set_linked_products() {

		if(isset($this->data['upsell_ids'])){

			// TODO: If is string create array from string
			if(!is_array($this->data['upsell_ids'])){
				$this->data['upsell_ids'] = [];
			}

			$this->product->set_upsell_ids( $this->data['upsell_ids'] );
		}

		if(isset($this->data['cross_sell_ids'])){

			// TODO: If is string create array from string
			if(!is_array($this->data['cross_sell_ids'])){
				$this->data['cross_sell_ids'] = [];
			}

			$this->product->set_cross_sell_ids( $this->data['cross_sell_ids'] );
		}

		//TODO: create function for set "related products"

	}

	public function set_shipping_info() {

		if(isset($this->data['purchase_note'])){
			$this->product->set_purchase_note( $this->data['purchase_note'] );
		}

		/**
		 * TODO: Написать функционал что бы менять айди класса, по имени класса
		 */

		if(isset($this->data['shipping_class_id'])){
			$this->product->set_shipping_class_id( $this->data['shipping_class_id'] );
		}

		if(isset($this->data['weight'])){
			$this->product->set_weight( $this->data['weight'] );
		}

		if(isset($this->data['length'])){
			$this->product->set_length( $this->data['length'] );
		}

		if(isset($this->data['width'])){
			$this->product->set_width( $this->data['width'] );
		}

		if(isset($this->data['height'])){
			$this->product->set_height( $this->data['height'] );
		}

	}

	public function set_stock_info() {

		if(isset($this->data['manage_stock'])){
			$this->product->set_manage_stock( $this->data['manage_stock'] ); // Set Product Manage Stock Status (bool)
		}

		if(isset($this->data['stock_qty'])){
			$this->product->set_stock_quantity( $this->data['stock_qty'] );
		}

		if(isset($this->data['stock_status'])){
			$this->product->set_stock_status( $this->data['stock_status'] );
		}

		if(isset($this->data['backorders'])){
			$this->product->set_backorders( $this->data['backorders'] );
		}

		if(isset($this->data['sold_individuality'])){
			$this->product->set_sold_individually( $this->data['sold_individuality'] );
		}

		if(isset($this->data['low_stock_amount'])){
			$this->product->set_low_stock_amount( $this->data['low_stock_amount'] );
		}

	}

	public function set_taxes_info() {

		if(isset($this->data['tax_status'])){

			$statuses = array(
				'taxable',
				'shipping',
				'none',
			);

			if(!in_array($this->data['tax_status'], $statuses)){
				$this->data['tax_status'] = 'none';
			}

			$this->product->set_tax_status( $this->data['tax_status'] ); // [taxable, shipping, none]
		}

		if(isset($this->data['tax_class'])){
			$this->product->set_tax_class( $this->data['tax_class'] );
		}

	}

	public function set_price_info() {

		if(isset($this->data['price'])){
			$this->product->set_price( $this->data['price'] ); // Set Product Price
		}

		if(isset($this->data['regular_price'])){
			$this->product->set_regular_price( $this->data['regular_price'] ); // Set Product Regular Price
		}

		if(isset($this->data['sale_price'])){
			$this->product->set_sale_price( $this->data['sale_price'] ); // Set Product Sale Price
		}

		if(isset($this->data['date_on_sale_from'])){
			$this->product->set_date_on_sale_from( $this->data['date_on_sale_from']); // Set Product Sale Start Date
		}

		if(isset($this->data['date_on_sale_to'])){
			$this->product->set_date_on_sale_to( $this->data['date_on_sale_to']); // Set Product Sale End Date
		}

	}

	/**
	 * Set General Product Data
	 */
	public function set_general_info() {
		if(isset($this->data['slug'])){
			$this->product->set_slug( $this->data['slug'] ); // Setup product name from $data
		}

		if(isset($this->data['name'])){
			$this->product->set_name( $this->data['name'] ); // Setup product slug
		}

		if(isset($this->data['status'])){
			$this->product->set_status( $this->data['status'] ); // Set product status
		}

		if(isset($this->data['is_featured'])){
			$this->product->set_featured( $this->data['is_featured'] ); // Set product featured
		}

		if(isset($this->data['catalog_visibility'])){
			$this->product->set_catalog_visibility( $this->data['catalog_visibility'] ); // Set Catalog visibility
		}

		if(isset($this->data['description'])){
			$this->product->set_description( $this->data['description'] ); // Set Description
		}

		if(isset($this->data['short_description'])){
			$this->product->set_short_description( $this->data['short_description'] ); // Set Short Description
		}

		if(isset($this->data['sku'])){
//		$this->product->set_sku( $this->data['sku'] ); // Set Product SKU
			$this->product->set_sku( rand( 1, 9999999 ) ); // Set Product SKU
		}

		if(isset($this->data['menu_order'])){
			$this->product->set_menu_order( $this->data['menu_order'] ); // Set Product menu order
		}

		if(isset($this->data['is_virtual'])){
			$this->product->set_virtual( $this->data['is_virtual'] ); // Set Virtual Status
		}

		/**
		 * TODO: Maybe not needed?? (total sales)
		 */
//		$this->product->set_total_sales( $this->data['total_sales'] ); // Set total sales

	}

}