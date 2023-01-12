<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Woocommerce\Helper;

class Setup_Product {

	protected $product_id;
	protected $data;
	protected $action;
	protected $product;
	protected $helper;

	public function __construct ( $product, $data, $action ) {

		$this->data       = $data;
		$this->product_id = intval( $data['id'] );
		$this->product    = $product;
		$this->helper     = new Helper();

	}

	public function setup_product () {

		$this->set_general_info();
		$this->set_price_info();
		$this->set_taxes_info();
		$this->set_stock_info();
		$this->set_shipping_info();
		$this->set_linked_products();
		$this->set_images_info();
		$this->set_downloadable_info();

		$this->product->save();

	}

	public function set_downloadable_info(){

		if(isset($this->data['is_downloadable'])){

			$this->product->set_downloadable($this->data['is_downloadable']);


			if($this->data['is_downloadable'] === true &&
				isset($this->data['downloads'])){

				$downloads = [];

				/**
				 * Unset downloads
				 */
				$this->product->set_downloads([]);

				foreach($this->data['downloads'] as $download){

					if(!is_array($download)){
						continue;
					}

					$downloads[] = $this->create_download($download);

				}

				$this->product->set_downloads($downloads);

			}

		}

	}

	protected function create_download($data){

		$download = new \WC_Product_Download();

		if($this->helper->check_image_exist($data['file'])){
			$attachment_id = attachment_url_to_postid($data['file']);
		}else{
			$attachment_id = $this->helper->upload_image_to_library($data['file']);
		}

		if(!$attachment_id){
			return false;
		}

		$file_url = wp_get_attachment_url( $attachment_id ); // attachmend ID should be here

		$download->set_name( $data['name'] );
		$download->set_id( md5( $file_url ) );
		$download->set_file( $file_url );
		$download->set_enabled( $data['enabled'] );

		return $download;

	}

	public function set_reviews_info() {

		$this->product->set_reviews_allowed($this->data['reviews_allowed']);
		$this->product->set_rating_counts($this->data['rating_counts']);

	}

	public function set_images_info () {

		$this->product->set_image_id(
			$this->setup_image( $this->data['image'] )
		);

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

	/**
	 * @param array $image
	 *
	 * @return false|int|string|\WP_Error
	 */
	protected function setup_image ( array $image ) {

		if ( $this->helper->check_image_exist( $image['src'] ) ) {

			$image_id = $image['id'];
			$this->helper->update_image_metadata( $image );

		} else {

			$image_id = $this->helper->upload_image_to_library( $this->data['image'] );

		}

		return $image_id;

	}

	public function set_taxonomies_info () {

		$this->product->set_category_ids( $this->data['category_ids'] ); // Must be Array
		$this->product->set_tag_ids( $this->data['tag_ids'] ); // Must be array

	}

	public function set_linked_products () {

		$this->product->set_upsell_ids( $this->data['upsell_ids'] );
		$this->product->set_cross_sell_ids( $this->data['cross_sell_ids'] );

		//TODO: create function for set "related products"

	}

	public function set_shipping_info () {

		$this->product->set_purchase_note( $this->data['purchase_note'] );
		$this->product->set_shipping_class_id( $this->data['shipping_class_id'] );
		$this->product->set_weight( $this->data['weight'] );
		$this->product->set_length( $this->data['length'] );
		$this->product->set_width( $this->data['width'] );
		$this->product->set_height( $this->data['height'] );

	}

	public function set_stock_info () {

		$this->product->set_manage_stock( $this->data['manage_stock'] ); // Set Product Manage Stock Status (bool)
		$this->product->set_sku( $this->data['stock_qty'] );
		$this->product->set_stock_status( $this->data['stock_status'] );
		$this->product->set_backorders( $this->data['backorders'] );
		$this->product->set_sold_individually( $this->data['sold_individuality'] );
		$this->product->set_low_stock_amount( $this->data['low_stock_amount'] );

	}

	public function set_taxes_info () {

		$this->product->set_tax_status( $this->data['tax_status'] );
		$this->product->set_tax_class( $this->data['tax_class'] );

	}

	public function set_price_info () {

		$this->product->set_price( $this->data['price'] ); // Set Product Price
		$this->product->set_regular_price( $this->data['regular_price'] ); // Set Product Regular Price
		$this->product->set_sale_price( $this->data['sale_price'] ); // Set Product Sale Price
		$this->product->set_date_on_sale_from( $this->data['date_on_sale_from'] ); // Set Product Sale Start Date
		$this->product->set_date_on_sale_to( $this->data['date_on_sale_to'] ); // Set Product Sale End Date

	}

	/**
	 * Set General Product Data
	 */
	public function set_general_info () {

		$this->product->set_slug( $this->data['slug'] ); // Setup product name from $data
		$this->product->set_name( $this->data['name'] ); // Setup product slug
		$this->product->set_status( $this->data['status'] ); // Set product status
		$this->product->set_featured( $this->data['is_featured'] ); // Set product featured
		$this->product->set_catalog_visibility( $this->data['catalog_visibility'] ); // Set Catalog visibility
		$this->product->set_description( $this->data['description'] ); // Set Description
		$this->product->set_short_description( $this->data['set_short_description'] ); // Set Short Description
		$this->product->set_sku( $this->data['set_sku'] ); // Set Product SKU
		$this->product->set_menu_order( $this->data['menu_order'] ); // Set Product menu order
		$this->product->set_virtual( $this->data['is_virtual'] ); // Set Virtual Status

		/**
		 * TODO: Maybe not needed?? (total sales)
		 */
		$this->product->set_total_sales( $this->data['total_sales'] ); // Set total sales

	}

}