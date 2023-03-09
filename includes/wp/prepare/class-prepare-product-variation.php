<?php

namespace Ainsys\Connector\Woocommerce\WP\Prepare;

use Ainsys\Connector\Woocommerce\Helper;
use WC_Product_Variable;

class Prepare_Product_Variation extends Prepare_Product {

	protected WC_Product_Variable $variation;


	public function __construct( WC_Product_Variable $product ) {

		parent::__construct( $product );

		$this->variation = new WC_Product_Variable( $this->product->get_id() );

	}


	/**
	 * @return array
	 */
	public function prepare_data(): array {

		/**
		 * Add Extra data to default array
		 */
		add_filter( 'woocommerce_available_variation', [ $this, 'set_variation_data_list' ], 10, 3 );

		$data = $this->variation->get_available_variations();

		return apply_filters(
			'ainsys_prepared_product_variation_data_before_send',
			$data,
			$this->product,
			$this->variation
		);
	}


	/**
	 * @param $data
	 * @param $obj
	 * @param $variation
	 *
	 * @return array
	 */
	public function set_variation_data_list( $data, $obj, $variation ): array {

		$data['ID'] = $variation->get_id();

		$data = $this->unset_data( $data );

		/**
		 * Setup Variation price info
		 */
		$data = $this->setup_variation_price_info( $data, $variation );

		/**
		 * Setup Stock info
		 */
		$data = $this->setup_variation_stock_info( $data, $variation );

		/**
		 * Setup Images info
		 */
		$data['image'] = $this->get_image_data_by_id( $variation->get_image_id() );

		/**
		 * Setup Variation Shipping Info
		 */
		$data = $this->setup_variation_shipping_info( $data, $variation );

		/**
		 * Setup Variation Taxes info
		 */
		$data = $this->setup_variation_tax_info( $data, $variation );

		$downloadable_info = $this->get_downloadable_info_for_variation( $variation );

		$data = array_merge( $data, $downloadable_info );

		$data['variation_id'] = $variation->get_id();

		return $this->setup_variation_attributes( $data, $variation );
	}


	public function setup_variation_attributes( $data, $variation ): array {

		$attributes = $variation->get_attributes();

		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attribute_key => $attribute_value ) {

				if ( strpos( $attribute_key, 'attribute_' ) === false ) {
					$attribute_key = 'attribute_' . $attribute_key;
				}

				$data['attributes'][ $attribute_key ] = Helper::format_term_value(
					$attribute_value, $attribute_key,
					'slug', 'name'
				);
			}
		}

		return $data;
	}


	/**
	 * Setup Variation Shipping info
	 *
	 * @param $data
	 * @param $variation
	 *
	 * @return mixed
	 *
	 */
	public function setup_variation_shipping_info( $data, $variation ) {

		$data['purchase_note']     = $variation->get_purchase_note();
		$data['weight']            = (int) $variation->get_weight();
		$data['length']            = (int) $variation->get_length();
		$data['width']             = (int) $variation->get_width();
		$data['height']            = (int) $variation->get_height();
		$data['shipping_class_id'] = $variation->get_shipping_class_id();
		$data['shipping_class']    = $variation->get_shipping_class();

		return $data;
	}


	public function setup_variation_tax_info( $data, $variation ) {

		$data['taxable']    = $variation->is_taxable();
		$data['tax_status'] = $variation->get_tax_status();
		$data['tax_class']  = $variation->get_tax_class();

		return $data;
	}


	/**
	 * Setup Variation price info
	 *
	 * @param $data
	 * @param $variation
	 *
	 * @return mixed
	 *
	 */
	public function setup_variation_price_info( $data, $variation ) {

		$data['price']             = (int) $variation->get_price();
		$data['regular_price']     = (int) $variation->get_regular_price();
		$data['sale_price']        = (int) $variation->get_sale_price();
		$data['date_on_sale_from'] = $variation->get_date_on_sale_from();
		$data['date_on_sale_to']   = $variation->get_date_on_sale_to();

		return $data;
	}


	/**
	 * Setup stock info
	 *
	 * @param $data
	 * @param $variation
	 *
	 * @return mixed
	 *
	 */
	public function setup_variation_stock_info( $data, $variation ) {

		$data['manage_stock']       = $variation->get_manage_stock();
		$data['stock_qty']          = $variation->get_stock_quantity();
		$data['stock_status']       = $variation->get_stock_status();
		$data['backorders']         = $variation->get_backorders();
		$data['sold_individuality'] = $variation->get_sold_individually();
		$data['low_stock_amount']   = $variation->get_low_stock_amount();

		return $data;
	}


	/**
	 * Unset not needed data from prepare data to AINSYS
	 *
	 * @param $data
	 *
	 * @return mixed
	 *
	 */
	public function unset_data( $data ) {

		unset(
			$data['availability_html'],
			$data['dimensions_html'],
			$data['display_price'],
			$data['display_regular_price'],
			$data['price_html'],
			$data['weight_html'],
			$data['is_in_stock'],
			$data['backorders_allowed'],
			$data['is_sold_individually'],
			$data['image'], $data['max_qty'],
			$data['min_qty'],
			$data['dimensions'],
			$data['is_downloadable'],
			$data['variation_is_active'],
			$data['variation_is_visible']
		);

		return $data;
	}


	/**
	 * Setup downloads data for variation
	 *
	 * @param $variation
	 *
	 * @return array
	 *
	 */
	public function get_downloadable_info_for_variation( $variation ): array {

		return [
			'downloadable'    => $variation->get_downloadable(),
			'downloads'       => $this->get_product_downloads( $variation ),
			'download_expiry' => $variation->get_download_expiry(),
			'download_limit'  => $variation->get_download_limit(),
		];
	}

}