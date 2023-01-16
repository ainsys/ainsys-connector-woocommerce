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
		var_dump( 'start' );
		$this->set_general_info();
		$this->set_price_info();
		$this->set_taxes_info();
		$this->set_stock_info();
		$this->set_shipping_info();
		$this->set_linked_products();
		$this->set_images_info();
		$this->set_downloadable_info();
		$this->setup_taxonomies();
		var_dump( 'end' );
	}

	public function setup_taxonomies() {
		$this->product->set_category_ids(
			$this->setup_product_terms_ids( $this->data['product_cat'], 'product_cat' )
		);

		$this->product->set_tag_ids(
			$this->setup_product_terms_ids( $this->data['tag_ids'], 'product_tag' )
		);

		if ( isset( $this->data['attributes'] ) ) {
			$this->product->set_attributes( [] );

			$attributes = $this->setup_attributes( $this->data['attributes'] );

			$this->product->set_attributes( $attributes );
		}
	}

	/**
	 * @param $terms
	 * @param $taxonomy
	 *
	 * @return array
	 * Setup product categories
	 */
	protected function setup_product_terms_ids( $terms, $taxonomy ) {
		if ( $taxonomy === 'product_cat' ) {
			$ids = $this->product->get_category_ids();
		} elseif ( $taxonomy === 'product_tag' ) {
			$ids = $this->product->get_tag_ids();
		} else {
			$ids = [];
		}


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

		foreach ( $attributes as $attr_key => $attribute ) {
			$new_attribute = new \WC_Product_Attribute();

			if ( $this->helper->is_taxonomy_attribute( $attr_key ) ) {
				if ( ! $this->helper->attribute_taxonomy_exist( $attr_key ) ) {
					$result = $this->helper->create_attribute_taxonomy( $attr_key, $attribute );

					if ( is_wp_error( $result ) ) {
						//TODO: return log error
					} else {
						$new_attribute->set_id( $result );
					}
				} else {
					$new_attribute->set_id( wc_attribute_taxonomy_id_by_name( $attr_key ) );
				}

				$new_attribute->set_name( $attribute['name'] );

				$options = $this->helper->format_terms_name_to_ids( $attribute['options'], $attr_key );

				$new_attribute->set_options( $options );
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
		$this->product->set_download_limit($this->data['download_limit']); // int \ if -1 = unlimited
		$this->product->set_download_expiry($this->data['download_expiry']);

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

		if ( $this->helper->check_image_exist( $data['file'] ) ) {
			$attachment_id = attachment_url_to_postid( $data['file'] );
		} else {
			$attachment_id = $this->helper->upload_image_to_library( $data );
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
		$this->product->set_reviews_allowed( $this->data['reviews_allowed'] );
		$this->product->set_rating_counts( $this->data['rating_counts'] );
	}

	public function set_images_info() {
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
	protected function setup_image( array $image ) {
		if ( $this->helper->check_image_exist( $image['src'] ) ) {
			$image_id = $image['id'];
			$this->helper->update_image_metadata( $image );
		} else {
			$image_id = $this->helper->upload_image_to_library( $this->data['image'] );
		}

		return $image_id;
	}

	public function set_taxonomies_info() {
		$this->product->set_category_ids( $this->data['category_ids'] ); // Must be Array
		$this->product->set_tag_ids( $this->data['tag_ids'] ); // Must be array

	}

	public function set_linked_products() {
		$this->product->set_upsell_ids( $this->data['upsell_ids'] );
		$this->product->set_cross_sell_ids( $this->data['cross_sell_ids'] );
		//TODO: create function for set "related products"

	}

	public function set_shipping_info() {

		$this->product->set_purchase_note( $this->data['purchase_note'] );

		/**
		 * TODO: Написать функционал что бы менять айди класса, по имени класса
		 */
		$this->product->set_shipping_class_id( $this->data['shipping_class_id'] );
		$this->product->set_weight( $this->data['weight'] );
		$this->product->set_length( $this->data['length'] );
		$this->product->set_width( $this->data['width'] );
		$this->product->set_height( $this->data['height'] );
	}

	public function set_stock_info() {
		$this->product->set_manage_stock( $this->data['manage_stock'] ); // Set Product Manage Stock Status (bool)
		$this->product->get_stock_quantity( $this->data['stock_qty'] );
		$this->product->set_stock_status( $this->data['stock_status'] );
		$this->product->set_backorders( $this->data['backorders'] );
		$this->product->set_sold_individually( $this->data['sold_individuality'] );
		$this->product->set_low_stock_amount( $this->data['low_stock_amount'] );
	}

	public function set_taxes_info() {
		$this->product->set_tax_status( $this->data['tax_status'] ); // [taxable, shipping, none]
		$this->product->set_tax_class( $this->data['tax_class'] );
	}

	public function set_price_info() {
		$this->product->set_price( $this->data['price'] ); // Set Product Price
		$this->product->set_regular_price( $this->data['regular_price'] ); // Set Product Regular Price
		$this->product->set_sale_price( $this->data['sale_price'] ); // Set Product Sale Price
		$this->product->set_date_on_sale_from( $this->data['date_on_sale_from']['date'] ); // Set Product Sale Start Date
		$this->product->set_date_on_sale_to( $this->data['date_on_sale_to']['date'] ); // Set Product Sale End Date
	}

	/**
	 * Set General Product Data
	 */
	public function set_general_info() {
		$this->product->set_slug( $this->data['slug'] ); // Setup product name from $data
		$this->product->set_name( $this->data['name'] ); // Setup product slug
		$this->product->set_status( $this->data['status'] ); // Set product status
		$this->product->set_featured( $this->data['is_featured'] ); // Set product featured
		$this->product->set_catalog_visibility( $this->data['catalog_visibility'] ); // Set Catalog visibility
		$this->product->set_description( $this->data['description'] ); // Set Description
		$this->product->set_short_description( $this->data['short_description'] ); // Set Short Description
		$this->product->set_sku( $this->data['sku'] ); // Set Product SKU
//		$this->product->set_sku( rand(1,9999999) ); // Set Product SKU
		$this->product->set_menu_order( $this->data['menu_order'] ); // Set Product menu order
		$this->product->set_virtual( $this->data['is_virtual'] ); // Set Virtual Status

		/**
		 * TODO: Maybe not needed?? (total sales)
		 */
//		$this->product->set_total_sales( $this->data['total_sales'] ); // Set total sales

	}

}