<?php
namespace Ainsys\Connector\Woocommerce;

use WC_Product_Variable;

class Prepare_Product_Variation_Data extends Prepare_Product_Data {

	protected $variation;

	public function __construct($product){

		parent::__construct($product);

		$this->variation = new WC_Product_Variable($this->product->get_id());

	}

	/**
	 * @return mixed|void
	 */
	public function prepare_data() {

		/**
		 * Add Extra data to default array
		 */
		add_filter('woocommerce_available_variation', [$this, 'set_variation_data_list'], 10, 3);

		$data = $this->variation->get_available_variations();

		return apply_filters('prepared_product_variation_data_before_send_to_ainsys', $data, $this->product, $this->variation);

	}

	/**
	 * @param $data
	 * @param $obj
	 * @param $variation
	 *
	 * @return array
	 */
	public function set_variation_data_list($data, $obj, $variation){

		if(!$variation->is_downloadable()){
			return $data;
		}

		$downloadable_info = $this->get_downloadable_info_for_variation($variation);

		$data = array_merge($data, $downloadable_info);

		return $data;
	}

	/**
	 * @param $variation
	 *
	 * @return array
	 * Setup downloads data for variation
	 */
	private function get_downloadable_info_for_variation($variation){
		$data = [
			'downloads' => $this->setup_downloads($variation),
			'download_expiry' => $variation->get_download_expiry(),
			'download_limit' => $variation->get_download_limit(),
		];

		return $data;

	}

}