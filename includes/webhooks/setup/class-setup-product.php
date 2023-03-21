<?php

namespace Ainsys\Connector\Woocommerce\Webhooks\Setup;

use Ainsys\Connector\Woocommerce\Helper;
use WC_Product_Attribute;

class Setup_Product {

	protected int $product_id;

	protected array $data;

	protected object $product;

	public bool $has_update = false;


	public function __construct( $product, $data ) {

		$this->data       = $data;
		$this->product_id = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
		$this->product    = $product;

		//$this->has_update = false;
	}


	public function setup_product(): void {

		if ( $this->has_update() ) {
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

			$this->product->save();
		}

	}


	public function setup_grouped_products_info(): void {

		if ( isset( $this->data['grouped_products_ids'] ) ) {
			if ( ! is_array( $this->data['grouped_products_ids'] ) ) {
				$this->data['grouped_products_ids'] = [];
			}

			$this->product->set_children( $this->data['grouped_products_ids'] ); // Must be ids(int) array

		}
	}


	public function setup_external_data(): void {

		if ( isset( $this->data['external_url'] ) ) {
			$this->product->set_product_url( $this->data['external_url'] );
		}

		if ( isset( $this->data['external_button_text'] ) ) {
			$this->product->set_button_text( $this->data['external_button_text'] );
		}
	}


	public function setup_taxonomies(): void {

		if ( isset( $this->data['product_categories'] ) ) {
			$this->product->set_category_ids(
				$this->get_product_terms_ids( $this->data['product_categories'], 'product_cat' )
			);
		}

		if ( isset( $this->data['product_tags'] ) ) {
			$this->product->set_tag_ids(
				$this->get_product_terms_ids( $this->data['product_tags'], 'product_tag' )
			);
		}

		if ( isset( $this->data['product_attributes'] ) ) {

			//$this->product->set_attributes( [] );

			$attributes = $this->get_attributes( $this->data['product_attributes'] );

			$this->product->set_attributes( $attributes );

		}

		if ( $this->data['type'] === 'variable' ) {
			$this->product->set_default_attributes(
				$this->get_default_attributes_info()
			);
		}
	}


	protected function get_default_attributes_info(): array {

		$default_attributes = [];

		if ( ! empty( $this->data['default_attributes'] ) && is_array( $this->data['default_attributes'] ) ) {
			foreach ( $this->data['default_attributes'] as $attr_key => $attr_value ) {
				$default_attributes[ $attr_key ] = Helper::format_term_value( $attr_value, $attr_key, 'name', 'slug' );
			}
		}

		return $default_attributes;
	}


	/**
	 * Get product categories ids
	 *
	 * @param $terms
	 * @param $taxonomy
	 *
	 * @return array
	 *
	 */
	protected function get_product_terms_ids( $terms, $taxonomy ): array {

		$ids = [];

		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return $ids;
		}

		foreach ( $terms as $term ) {

			if ( term_exists( $term ) ) {
				$term_id = Helper::format_term_value( $term, $taxonomy, 'name', 'term_id' );
			}

			if ( empty( $term_id ) ) {
				continue;
			}

			$ids[] = $term_id;
		}

		return array_unique( $ids );
	}


	/**
	 * This function Create new attributes from data from AINSYS
	 *
	 * @param $attributes
	 *
	 * @return array
	 *
	 * @todo неверно атрибуты создаются, надо переделать
	 */
	protected function get_attributes( $attributes ): array { // Need to delete

		$new_attributes = [];
		//error_log( print_r( $attributes, 1 ) );
		foreach ( $attributes as $attribute ) {

			$new_attribute = new WC_Product_Attribute();

			$new_attribute->set_id( 0 );
			$new_attribute->set_name( wc_attribute_taxonomy_slug( $attribute['name'] ) );

			if ( $attribute['position'] ) {
				$new_attribute->set_position( $attribute['position'] );
			}

			if ( $attribute['visible'] ) {
				$new_attribute->set_visible( $attribute['visible'] );
			}

			if ( $attribute['variation'] ) {
				$new_attribute->set_variation( $attribute['variation'] );
			}

			/*$options = [];

			foreach($attribute['options'] as $option){
				$options[] = Helper::format_term_value($option, 'pa_' . wc_sanitize_taxonomy_name($attribute['name']),
					'name', 'term_id');
			}*/

			$new_attribute->set_options( $attribute['options'] );

			$new_attributes[] = $new_attribute;

			/*if (wc_attribute_taxonomy_id_by_name( $attribute['name'] ) !== 0) {
				if ( ! $this->helper->attribute_taxonomy_exist( wc_attribute_taxonomy_slug($attribute['name'])) ) {
					continue;
				} else {
					$new_attribute->set_id( wc_attribute_taxonomy_id_by_name( $attribute['name'] ) );
				}

				$new_attribute->set_name( wc_attribute_taxonomy_slug($attribute['name']) );

//				$options = Helper::format_terms_name_to_ids( $attribute['options'], $attr_key );

				$new_attribute->set_options( $attribute['options'] );
			} else {
				$new_attribute->set_name( $attribute['name'] );
				$new_attribute->set_options( $attribute['options'] );
			}

			$new_attribute->set_position( $attribute['position'] );
			$new_attribute->set_visible( $attribute['visible'] );
			$new_attribute->set_variation( $attribute['variation'] );

			$new_attributes[] = $new_attribute;*/
		}

		/*$attributes = wc_get_attribute_taxonomies();

		foreach($attributes as $attr_key => $attribute){

			if()

		}*/

		/*foreach ( $attributes as $attribute_data ) {

			$attribute_id = Helper::get_attribute_id_by_slug(sanitize_title($attribute_data['name']));

			$attribute = wc_get_attribute($attribute_id);

			if(!is_object($attribute)){
				continue;
			}

			$new_attribute = new \WC_Product_Attribute();

			if ( $this->helper->is_taxonomy_attribute( $attribute->slug ) ) {
				if ( ! $this->helper->attribute_taxonomy_exist( $attribute->slug ) ) {
					continue;
				} else {
					$new_attribute->set_id( wc_attribute_taxonomy_id_by_name( $attribute_data['name'] ) );
				}
			}

			$new_attribute->set_name( $attribute_data['name'] );

			$options = [];

			foreach($attribute_data['options'] as $option){
				$options[] = Helper::format_term_value($option, $attribute->slug, 'name', 'term_id');
			}

			$new_attribute->set_options( $options );

			if(isset($attribute_data['position'])){
				$new_attribute->set_position( $attribute_data['position'] );
			}

			if(isset($attribute_data['visible'])){
				$new_attribute->set_visible( $attribute_data['visible'] );
			}

			if(isset($attribute_data['variation'])){
				$new_attribute->set_variation( $attribute_data['variation'] );
			}

			$new_attributes[$attribute->slug] = $new_attribute;

		}*/

		return $new_attributes;
	}


	public function set_downloadable_info(): void {

		if ( ! isset( $this->data['downloadable'] ) ) {
			return;
		}

		$this->product->set_downloadable( $this->data['downloadable'] );

		if ( isset( $this->data['download_limit'] ) ) {
			$this->product->set_download_limit( $this->data['download_limit'] ); // int \ if -1 = unlimited
		}

		if ( isset( $this->data['download_expiry'] ) ) {
			$this->product->set_download_expiry( $this->data['download_expiry'] );
		}

		if ( $this->data['downloadable'] === true
		     && isset( $this->data['downloads'] )
		) {
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

		$attachment_id = Helper::get_attachment_id_by_url( $data['file'] );

		if ( $attachment_id === 0 ) {
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


	public function set_reviews_info(): void {

		if ( isset( $this->data['reviews_allowed'] ) ) {
			$this->product->set_reviews_allowed( $this->data['reviews_allowed'] );
		}

		if ( isset( $this->data['rating_counts'] ) ) {
			$this->product->set_rating_counts( $this->data['rating_counts'] );
		}
	}


	public function set_images_info(): void {

		if ( isset( $this->data['image'] ) ) {
			$this->product->set_image_id(
				$this->get_image( $this->data['image'] )
			);
		}

		if ( isset( $this->data['gallery_images_ids'] ) ) {
			$this->product->set_gallery_image_ids( [] );

			$gallery_images = $this->data['gallery_images_ids'];

			if ( ! empty( $gallery_images ) && is_array( $gallery_images ) ) {
				$images_ids = [];

				foreach ( $gallery_images as $image ) {
					$images_ids[] = $this->get_image( $image );
				}

				/**
				 * Set gallery images
				 */
				$this->product->set_gallery_image_ids( $images_ids );
			}
		}
	}


	/**
	 * @param  array $image
	 *
	 * @return int|\WP_Error
	 */
	protected function get_image( array $image ) {

		$image_id = 0;

		if ( isset( $image['src'] ) ) {
			$image_id = Helper::get_attachment_id_by_url( $image['src'] );
		}

		if ( $image_id !== 0 ) {
			$image['id'] = $image_id;
			Helper::update_image_metadata( $image );
		} else {
			$image_id = Helper::upload_image_to_library( $image );
		}

		return $image_id;
	}


	public function set_linked_products(): void {

		if ( isset( $this->data['upsell_ids'] ) ) {
			// TODO: If is string create array from string
			if ( ! is_array( $this->data['upsell_ids'] ) ) {
				$this->data['upsell_ids'] = [];
			}

			$this->product->set_upsell_ids( $this->data['upsell_ids'] );
		}

		if ( isset( $this->data['cross_sell_ids'] ) ) {
			// TODO: If is string create array from string
			if ( ! is_array( $this->data['cross_sell_ids'] ) ) {
				$this->data['cross_sell_ids'] = [];
			}

			$this->product->set_cross_sell_ids( $this->data['cross_sell_ids'] );
		}

	}


	public function set_shipping_info(): void {

		if ( isset( $this->data['purchase_note'] ) ) {
			$this->product->set_purchase_note( $this->data['purchase_note'] );
		}

		/**
		 * TODO: Написать функционал что бы менять айди класса, по имени класса
		 */
		if ( isset( $this->data['shipping_class_id'] ) ) {
			$this->product->set_shipping_class_id( $this->data['shipping_class_id'] );
		}

		if ( isset( $this->data['weight'] ) ) {
			$this->product->set_weight( $this->data['weight'] );
		}

		if ( isset( $this->data['length'] ) ) {
			$this->product->set_length( $this->data['length'] );
		}

		if ( isset( $this->data['width'] ) ) {
			$this->product->set_width( $this->data['width'] );
		}

		if ( isset( $this->data['height'] ) ) {
			$this->product->set_height( $this->data['height'] );
		}

	}


	public function set_stock_info(): void {

		if ( isset( $this->data['manage_stock'] ) ) {
			$this->product->set_manage_stock( $this->data['manage_stock'] ); // Set Product Manage Stock Status (bool)
		}

		if ( isset( $this->data['stock_qty'] ) ) {
			$this->product->set_stock_quantity( $this->data['stock_qty'] );
		}

		if ( isset( $this->data['stock_status'] ) ) {
			$this->product->set_stock_status( $this->data['stock_status'] );
		}

		if ( isset( $this->data['backorders'] ) ) {
			$this->product->set_backorders( $this->data['backorders'] );
		}

		if ( isset( $this->data['sold_individuality'] ) ) {
			$this->product->set_sold_individually( $this->data['sold_individuality'] );
		}

		if ( isset( $this->data['low_stock_amount'] ) ) {
			$this->product->set_low_stock_amount( $this->data['low_stock_amount'] );
		}

	}


	public function set_taxes_info(): void {

		if ( isset( $this->data['tax_status'] ) ) {
			$statuses = [
				'taxable',
				'shipping',
				'none',
			];

			if ( ! in_array( $this->data['tax_status'], $statuses, true ) ) {
				$this->data['tax_status'] = 'none';
			}

			$this->product->set_tax_status( $this->data['tax_status'] ); // [taxable, shipping, none]
		}

		if ( isset( $this->data['tax_class'] ) ) {
			$this->product->set_tax_class( $this->data['tax_class'] );
		}

	}


	public function set_price_info(): void {

		if ( ! empty( $this->data['price'] ) ) {
			$this->product->set_price( $this->data['price'] ); // Set Product Price
		}

		if ( ! empty( $this->data['regular_price'] ) ) {
			$this->product->set_regular_price( $this->data['regular_price'] ); // Set Product Regular Price
		}

		if ( ! empty( $this->data['sale_price'] ) ) {
			$this->product->set_sale_price( $this->data['sale_price'] ); // Set Product Sale Price
		}

		if ( isset( $this->data['date_on_sale_from'] ) ) {
			$this->product->set_date_on_sale_from( $this->data['date_on_sale_from'] ); // Set Product Sale Start Date
		}

		if ( isset( $this->data['date_on_sale_to'] ) ) {
			$this->product->set_date_on_sale_to( $this->data['date_on_sale_to'] ); // Set Product Sale End Date
		}

	}


	/**
	 * Set General Product Data
	 */
	public function set_general_info(): void {

		if ( isset( $this->data['slug'] ) && $this->product->get_slug() !== $this->data['slug'] ) {
			$this->product->set_slug( $this->data['slug'] ); // Setup product name from $data
		}

		if ( isset( $this->data['name'] ) && $this->product->get_name() !== $this->data['name'] ) {
			$this->product->set_name( $this->data['name'] ); // Setup product slug
		}

		if ( isset( $this->data['status'] ) ) {
			$this->product->set_status( $this->data['status'] ); // Set product status
		}

		if ( isset( $this->data['featured'] ) ) {
			$this->product->set_featured( $this->data['featured'] ); // Set product featured
		}

		if ( isset( $this->data['catalog_visibility'] ) ) {
			$this->product->set_catalog_visibility( $this->data['catalog_visibility'] ); // Set Catalog visibility
		}

		if ( isset( $this->data['description'] ) ) {
			$this->product->set_description( $this->data['description'] ); // Set Description
		}

		if ( isset( $this->data['short_description'] ) ) {
			$this->product->set_short_description( $this->data['short_description'] ); // Set Short Description
		}

		if ( isset( $this->data['sku'] ) ) {
			$this->product->set_sku( $this->data['sku'] ); // Set Product SKU
		} /*else {
			$this->product->set_sku( \Ainsys\Connector\Master\Helper::random_int( 1, 999999 ) );
		}*/

		if ( isset( $this->data['menu_order'] ) ) {
			$this->product->set_menu_order( $this->data['menu_order'] ); // Set Product menu order
		}

		if ( isset( $this->data['virtual'] ) ) {
			$this->product->set_virtual( $this->data['virtual'] ); // Set Virtual Status
		}

		/**
		 * TODO: Maybe not needed?? (total sales)
		 */
		if ( isset( $this->data['total_sales'] ) ) {
			$this->product->set_total_sales( $this->data['total_sales'] ); // Set total sales
		}

	}


	/**
	 * @return bool
	 * @todo сделать проверку на массивы
	 */
	public function has_update(): bool {

		$data = [];

		foreach ( $this->data as $key => $val ) {

			if ( $key === 'name' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'slug' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'status' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'featured' ) {
				$data = $this->set_update_data( $key, $val, $data, 'bool' );
			}

			if ( $key === 'catalog_visibility' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'description' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'short_description' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'sku' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'menu_order' ) {
				$data = $this->set_update_data( $key, $val, $data, 'int' );
			}

			if ( $key === 'virtual' ) {
				$data = $this->set_update_data( $key, $val, $data, 'bool' );
			}

			if ( $key === 'total_sales' ) {
				$data = $this->set_update_data( $key, $val, $data, 'int' );
			}

			if ( $key === 'price' ) {
				$data = $this->set_update_data( $key, $val, $data, 'float' );
			}

			if ( $key === 'regular_price' ) {
				$data = $this->set_update_data( $key, $val, $data, 'float' );
			}

			if ( $key === 'sale_price' ) {
				$data = $this->set_update_data( $key, $val, $data, 'float' );
			}

			if ( $key === 'date_on_sale_from' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'date_on_sale_to' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'tax_status' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'tax_class' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'manage_stock' ) {
				$data = $this->set_update_data( $key, $val, $data, 'bool' );
			}

			if ( $key === 'stock_quantity' ) {
				$data = $this->set_update_data( $key, $val, $data, 'int' );
			}

			if ( $key === 'stock_status' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'backorders' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'sold_individually' ) {
				$data = $this->set_update_data( $key, $val, $data, 'bool' );
			}

			if ( $key === 'max_purchase_quantity' ) {
				$data = $this->set_update_data( $key, $val, $data, 'int' );
			}

			if ( $key === 'min_purchase_quantity' ) {
				$data = $this->set_update_data( $key, $val, $data, 'int' );
			}

			if ( $key === 'low_stock_amount' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'purchase_note' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'shipping_class_id' ) {
				$data = $this->set_update_data( $key, $val, $data, 'int' );
			}

			if ( $key === 'shipping_class' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'weight' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'length' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'width' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'height' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}

			/*if ( $key === 'upsell_ids' ) {

				$data = $this->set_update_data( $key, $val, $data );
			}

			if ( $key === 'cross_sell_ids' ) {

				$data = $this->set_update_data( $key, $val, $data );
			}*/

			/*if ( $key === 'rating_counts' ) {
				$data = $this->set_update_data( $key, $val, $data );
			}*/

			if ( $key === 'reviews_allowed' ) {
				$data = $this->set_update_data( $key, $val, $data, 'bool' );
			}

			if ( $key === 'average_rating' ) {
				$data = $this->set_update_data( $key, $val, $data, 'float' );
			}

			if ( $key === 'review_count' ) {
				$data = $this->set_update_data( $key, $val, $data, 'int' );
			}

		}

		return in_array( 'yes', $data, true );
	}


	/**
	 * @param  string $key
	 * @param         $val
	 * @param  array  $data
	 * @param  string $type
	 *
	 * @return array
	 */
	protected function set_update_data( string $key, $val, array $data, $type = 'string' ): array {

		$method = 'get_' . $key;

		if ( $type === 'string' && $this->is_update_data_string( $key, $val, $method ) ) {
			$data[ $key ] = 'no';
		} elseif ( $type === 'bool' && $this->is_update_data_bool( $key, $val, $method ) ) {
			$data[ $key ] = 'no';
		} elseif ( $type === 'int' && $this->is_update_data_int( $key, $val, $method ) ) {
			$data[ $key ] = 'no';
		} elseif ( $type === 'float' && $this->is_update_data_float( $key, $val, $method ) ) {
			$data[ $key ] = 'no';
		} else {
			$data[ $key ] = 'yes';
		}

		return $data;
	}


	/**
	 * @param  string $key
	 * @param         $val
	 * @param         $method
	 *
	 * @return bool
	 */
	protected function is_update_data_string( string $key, $val, $method ): bool {

		return (string) $this->product->$method() === (string) $val;
	}


	/**
	 * @param  string $key
	 * @param         $val
	 * @param         $method
	 *
	 * @return bool
	 */
	protected function is_update_data_bool( string $key, $val, $method ): bool {

		return (bool) $this->product->$method() === (bool) $val;
	}


	/**
	 * @param  string $key
	 * @param         $val
	 * @param         $method
	 *
	 * @return bool
	 */
	protected function is_update_data_int( string $key, $val, $method ): bool {

		return (int) $this->product->$method() === (int) $val;
	}


	/**
	 * @param  string $key
	 * @param         $val
	 * @param         $method
	 *
	 * @return bool
	 */
	protected function is_update_data_float( string $key, $val, $method ): bool {

		return (float) $this->product->$method() === (float) $val;
	}

}