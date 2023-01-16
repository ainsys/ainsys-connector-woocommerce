<?php

namespace Ainsys\Connector\Woocommerce;

use WC_Product_Variable;

class Prepare_Product_Variation_Data extends Prepare_Product_Data {

	protected $variation;

	public function __construct( $product ) {
		parent::__construct( $product );

		$this->variation = new WC_Product_Variable( $this->product->get_id() );
	}

	/**
	 * @return mixed|void
	 */
	public function prepare_data() {
		/**
		 * Add Extra data to default array
		 */
		add_filter( 'woocommerce_available_variation', [ $this, 'set_variation_data_list' ], 10, 3 );

		$data = $this->variation->get_available_variations();

		return apply_filters( 'prepared_product_variation_data_before_send_to_ainsys',
		                      $data,
		                      $this->product,
		                      $this->variation );
	}

	/**
	 * @param $data
	 * @param $obj
	 * @param $variation
	 *
	 * @return array
	 */
	public function set_variation_data_list( $data, $obj, $variation ) {
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
		$data['image'] = parent::setup_image_data_by_id( $variation->get_image_id() );

		/**
		 * Setup Variation Shipping Info
		 */
		$data = $this->setup_variation_shipping_info( $data, $variation );

		/**
		 * Setup Variation Taxes info
		 */
		$data = $this->setup_variation_tax_info( $data, $variation );


		if ( $variation->is_downloadable() ) {
			$downloadable_info = $this->get_downloadable_info_for_variation( $variation );

			$data                 = array_merge( $data, $downloadable_info );
			$data['variation_id'] = $variation->get_id();
		}

		return $data;
	}

	/**
	 * @param $data
	 * @param $variation
	 *
	 * @return mixed
	 * Setup Variation Shipping info
	 */
	public function setup_variation_shipping_info( $data, $variation ) {
		$data['purchase_note']     = $variation->get_purchase_note();
		$data['weight']            = $variation->get_weight();
		$data['length']            = $variation->get_length();
		$data['width']             = $variation->get_width();
		$data['height']            = $variation->get_height();
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
	 * @param $data
	 * @param $variation
	 *
	 * @return mixed
	 * Setup Variation price info
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
	 * @param $data
	 * @param $variation
	 *
	 * @return mixed
	 * Setup stock info
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
	 * @param $data
	 *
	 * @return mixed
	 * Unset not needed data from prepare data to AINSYS
	 */
	public function unset_data( $data ) {
		unset( $data['availability_html'] );
		unset( $data['dimensions_html'] );
		unset( $data['display_price'] );
		unset( $data['display_regular_price'] );
		unset( $data['price_html'] );
		unset( $data['weight_html'] );
		unset( $data['is_in_stock'] );
		unset( $data['backorders_allowed'] );
		unset( $data['is_sold_individually'] );
		unset( $data['image'] );
		unset( $data['max_qty'] );
		unset( $data['min_qty'] );
		unset( $data['dimensions'] );
		unset( $data['is_downloadable'] );
		unset( $data['variation_is_active'] );
		unset( $data['variation_is_visible'] );

		return $data;
	}

	/**
	 * @param $variation
	 *
	 * @return array
	 * Setup downloads data for variation
	 */
	public function get_downloadable_info_for_variation( $variation ) {
		$data = [
			'downloadable'    => $variation->get_downloadable(),
			'downloads'       => $this->setup_downloads( $variation ),
			'download_expiry' => $variation->get_download_expiry(),
			'download_limit'  => $variation->get_download_limit(),
		];

		return $data;
	}

}