<?php
namespace Ainsys\Connector\Woocommerce;

class Prepare_Product_Variation_Data extends Prepare_Product_Data {

	public function __construct($product){

		parent::__construct($product);

	}

	public function prepare_data() {

		$data = parent::prepare_data();

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

		return apply_filters('prepared_product_variation_data_before_send_to_ainsys', array_diff($data, $exclude), $this->product);

	}

}