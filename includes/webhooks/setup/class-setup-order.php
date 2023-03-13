<?php

namespace Ainsys\Connector\Woocommerce\Webhooks\Setup;

use Ainsys\Connector\Woocommerce\Helper;

class Setup_Order {

	public function __construct( $order, $data ) {
		$this->data       = $data;
		$this->order    = $order;
		$this->helper     = new Helper();

		/**
		 * Временно! Не забыть убрать
		 */
//		$this->order = wc_get_order($data['ID']);
		/*******************************/

	}

	public function setup_order(){

		$this->order->save();

		$this->setup_default_data();

		

		$this->order->calculate_totals();
		$this->order->calculate_shipping();
		$this->order->calculate_taxes();

		$this->order->save();

	}

	protected function setup_default_data(){

		if(isset($this->data['status']) && Helper::is_valid_order_status($this->data['status'])){
			$this->order->set_status($this->data['status']);
		}

		if(isset($this->data['total'])){
			$this->order->set_total((float) $this->data['total']);
		}

		if(isset($this->data['shipping_total'])){
			$this->order->set_shipping_total($this->data['shipping_total']);
		}

		if(isset($this->data['currency'])){
			$this->order->set_currency($this->data['currency']);
		}

	}

}