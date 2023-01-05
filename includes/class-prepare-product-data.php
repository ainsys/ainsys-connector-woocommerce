<?php
namespace Ainsys\Connector\Woocommerce;

class Prepare_Product_Data {

	protected $product;
	protected $mode;

	public function __construct($product, $mode = 'simple'){

		if(empty($product) || !is_object($product)){
			return false;
		}

		$this->product = $product;
		$this->mode = $mode;

		return $this->product;

	}

	public function prepare_data(){

		$data = [];

		/**
		 * Get product Main Info
		 */
		$data = array_merge($data, $this->get_general_info());

		/**
		 * Merge product price info
		 */
		$data = array_merge($data, $this->get_prices_info());

		/**
		 * Merge taxes data
		 */
		$data = array_merge($data, $this->get_taxes_info());

		/**
		 * Merge stock info
		 */
		$data = array_merge($data, $this->get_stock_info());

		/**
		 * Merge Shipping info
		 */
		$data = array_merge($data, $this->get_shipping_info());

		/**
		 * Merge Linked Products
		 */
		$data = array_merge($data, $this->get_linked_products());

		if($this->product->is_type('variable')){

			/**
			 * Merge Variation Info
			 */
			$data = array_merge($data, $this->get_variations_data());
		}

		/**
		 * Merge Taxonomies Info
		 */
		$data = array_merge($data, $this->get_taxonomies_info());

		/**
		 * Merge downloadable info
		 */
		$data = array_merge($data, $this->get_downloadable_info());

		/**
		 * Merge images info
		 */
		$data = array_merge($data, $this->get_images_info());

		/**
		 * Merge Reviews info
		 */
		$data = array_merge($data, $this->get_reviews_info());

		/**
		 * Merge Metadata info
		 */
		$data = array_merge($data, $this->get_metadata_info());

		if($this->mode == 'variation'){
			$exclude = [
				'is_featured',
				'short_description',
				'price_excluding_tax',
				'price_includes_tax',
				'taxable',
				'tax_status',
				'tax_class',
				'manage_stock',
				'stock_qty',
				'sold_individuality',
				'availability',
				'max_purchase_quantity',
				'min_purchase_quantity',
				'low_stock_amount',
				'purchase_note',
				'upsell_ids',
				'cross_sell_ids',
				'related_products',
				'category_ids',
				'tag_ids',
				'default_attributes',
				'attributes',
				'permalink',
				'reviews_allowed',
				'rating_counts',
				'average_rating',
				'review_count',
				'metadata'
			];

			$data = array_diff_key($data, array_flip($exclude));
		}

		return apply_filters('prepared_data_before_send_to_ainsys', $data, $this->product);
	}

	public function get_metadata_info(){

		return [
			'metadata' => $this->product->get_meta_data()
		];

	}

	public function get_reviews_info(){

		return [
			'reviews_allowed' => $this->product->get_reviews_allowed(),
			'rating_counts' => $this->product->get_rating_counts(),
			'average_rating' => $this->product->get_average_rating(),
			'review_count' => $this->product->get_review_count()
		];

	}

	public function get_images_info(){

		return [
			'image_id' => $this->product->get_image_id(),
			'gallery_images_ids' => $this->product->get_gallery_image_ids(),
		];

	}

	public function get_downloadable_info(){

		$data = [
			'downloads' => $this->product->get_downloads(),
			'download_expiry' => $this->product->get_download_expiry(),
			'downloadable' => $this->product->get_downloadable(),
			'download_limit' => $this->product->get_download_limit(),
		];

		if($this->product->get_downloads()){
			/**
			 * Merge downloads path`s
			 */
			array_merge($data, $this->get_downloads_path($this->product->get_downloads()));
		}

		return $data;

	}

	protected function get_downloads_path($downloads){

		$pathes = [];

		foreach($downloads as $download){
			$pathes[] = $this->product->get_file_download_path($download->get_id());
		}

		return $pathes;

	}

	public function get_taxonomies_info(){

		$data = [
			'category_ids' => $this->product->get_category_ids(),
			'tag_ids' => $this->product->get_tag_ids(),
			'default_attributes' => $this->product->get_default_attributes(),
			'attributes' => $this->setup_attributes_info()
		];

		return $data;

	}

	protected function setup_attributes_info(){

		$attributes = [];

		foreach ($this->product->get_attributes() as $attr_key => $attribute){
			$attributes[$attr_key] = [
				'id' => $attribute['id'],
				'name' => $attribute['name'],
				'position' => $attribute['position'],
				'visible' => $attribute['visible'],
				'variation' => $attribute['variation']
			];

			if(!empty($attribute['options']) && is_array($attribute['options'])){
				foreach($attribute['options'] as $option){
					$attributes[$attr_key]['options'][] = $option;
				}
			}
		}

		return $attributes;

	}

	public function get_variations_data(){

		$data = [
			'variations_ids' => $this->product->get_children()
		];

		return $data;

	}

	public function get_linked_products(){

		return [
			'upsell_ids' => $this->product->get_upsell_ids(),
			'cross_sell_ids' => $this->product->get_cross_sell_ids(),
			'related_products' => wc_get_related_products($this->product->get_id(), -1)
		];

	}

	/**
	 * @return array
	 */
	public function get_shipping_info(){

		return [
			'purchase_note' => $this->product->get_purchase_note(),
			'shipping_class_id' => $this->product->get_shipping_class_id(),
			'shipping_class' => $this->product->get_shipping_class(),
			'weight' => $this->product->get_dimensions(),
			'length' => $this->product->get_length(),
			'width' => $this->product->get_width(),
			'height' => $this->product->get_height(),
		];

	}

	public function get_stock_info(){

		return [
			'manage_stock' => $this->product->get_manage_stock(),
			'stock_qty' => $this->product->get_stock_quantity(),
			'stock_status' => $this->product->get_stock_status(),
			'backorders' => $this->product->get_backorders(),
			'sold_individuality' => $this->product->get_sold_individually(),
			'availability' => $this->product->get_availability(),
			'max_purchase_quantity' => $this->product->get_max_purchase_quantity(),
			'min_purchase_quantity' => $this->product->get_min_purchase_quantity(),
			'low_stock_amount' => $this->product->get_low_stock_amount()
		];

	}

	public function get_taxes_info(){

		return [
			'taxable' => $this->product->is_taxable(),
			'tax_status' => $this->product->get_tax_status(),
			'tax_class' => $this->product->get_tax_class(),
		];

	}

	public function get_general_info(){

		return [
			'id' => $this->product->get_id(),
			'type' => $this->product->get_type(),
			'name' => $this->product->get_name(),
			'formatted_name' => $this->product->get_formatted_name(),
			'title' => $this->product->get_title(),
			'slug' => $this->product->get_slug(),
			'date_created' => $this->product->get_date_created(),
			'date_modified' => $this->product->get_date_modified(),
			'status' => $this->product->get_status(),
			'is_featured' => $this->product->get_featured(),
			'catalog_visibility' => $this->product->get_catalog_visibility(),
			'description' => $this->product->get_description(),
			'short_description' => $this->product->get_short_description(),
			'sku' => $this->product->get_sku(),
			'menu_order' => $this->product->get_menu_order(),
			'is_virtual' => $this->product->get_virtual(),
			'permalink' => get_permalink($this->product->get_id()),
			'total_sales' => $this->product->get_total_sales()
		];

	}

	public function get_prices_info(){

		return [
			'price' => $this->product->get_price(),
			'regular_price' => $this->product->get_regular_price(),
			'sale_price' => $this->product->get_sale_price(),
			'date_on_sale_from' => $this->product->get_date_on_sale_from(),
			'date_on_sale_to' => $this->product->get_date_on_sale_to(),
			'price_excluding_tax' => wc_get_price_excluding_tax($this->product),
			'price_includes_tax' => wc_get_price_including_tax($this->product)
		];

	}

}