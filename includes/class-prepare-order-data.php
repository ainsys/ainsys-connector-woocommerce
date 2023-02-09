<?php

namespace Ainsys\Connector\Woocommerce;

class Prepare_Order_Data {

	protected $order;

	public function __construct($order) {
		$this->order = wc_get_order($order);
	}

	public function prepare_data(){

		$data = [];

		$data = array_merge($data, $this->get_general_info());

		$data = array_merge($data, $this->get_dates_info());

		$data = array_merge($data, $this->get_price_info());

		$data = array_merge($data, $this->get_taxes_info());

		echo '<pre>';
		print_r($data);
		echo '</pre>';

		return $data;

	}

	protected function get_taxes_info(){
		return [
			'discount_tax' => $this->order->get_discount_tax(),
			'tax_total' => $this->order->get_tax_totals(),
			'taxes' => $this->order->get_taxes()
		];
	}

	protected function get_dates_info(){
		return [
			'date_created' => $this->order->get_date_created()->date('Y-m-d'),
			'modified' => $this->order->get_date_modified()->date('Y-m-d'),
			'completed' => ($this->order->get_date_completed()) ? $this->order->get_date_completed()->date('Y-m-d') : '',
			'paid' => $this->order->get_date_paid()->date('Y-m-d')
		];
	}

	protected function get_general_info(){
		return [
			'id' => $this->order->get_id(),
			'title' => 'Order#' . $this->order->get_id(),
			'order_key' => $this->order->get_order_key(),
			'status' => $this->order->get_status(),
			'currency' => $this->order->get_currency(),
		];
	}

	protected function get_price_info(){

		return [
//			'subtotal' => $this->order->get_subtotal(),
			'total' => $this->order->get_total(),
			'fees' => $this->order->get_total_fees(),
			'shipping_total' => $this->order->get_shipping_total(),
		];

	}

}