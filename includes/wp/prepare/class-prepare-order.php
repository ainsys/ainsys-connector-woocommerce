<?php

namespace Ainsys\Connector\Woocommerce\WP\Prepare;

use DateTime;
use DateTimeZone;
use Exception;
use WC_Order;

class Prepare_Order {

	protected WC_Order $order;


	public function __construct( $order ) {

		if ( is_null( $order ) ) {
			$this->order = wc_get_order( $order );
		} else {
			$this->order = $order;
		}

	}


	public function prepare_data(): array {

		$data = [];

		$data = array_merge( $data, $this->get_general_info() );
		$data = array_merge( $data, $this->get_dates_info() );
		$data = array_merge( $data, $this->get_items_info() );
		$data = array_merge( $data, $this->get_payment_info() );
		$data = array_merge( $data, $this->get_delivery_info() );
		$data = array_merge( $data, $this->get_taxes_info() );
		$data = array_merge( $data, $this->get_fees_info() );
		$data = array_merge( $data, $this->get_coupon_info() );
		$data = array_merge( $data, $this->get_billing_info() );
		$data = array_merge( $data, $this->get_shipping_info() );
		$data = array_merge( $data, $this->get_customer_info() );

		return apply_filters( 'ainsys_prepared_data_before_send', $data, $this->order );

	}


	protected function get_general_info(): array {

		return [
			'id'                        => $this->order->get_id(),
			'order_number'              => $this->order->get_order_number(),
			'status'                    => $this->order->get_status(),
			'currency'                  => $this->order->get_currency(),
			'total'                     => (float) wc_format_decimal( $this->order->get_total(), 2 ),
			'subtotal'                  => (float) wc_format_decimal( $this->get_order_subtotal( $this->order ), 2 ),
			'total_line_items_quantity' => (float) $this->order->get_item_count(),
			'total_tax'                 => (float) wc_format_decimal( $this->order->get_total_tax(), 2 ),
			'total_shipping'            => (float) wc_format_decimal( $this->order->get_shipping_total(), 2 ),
			'cart_tax'                  => (float) wc_format_decimal( $this->order->get_cart_tax(), 2 ),
			'shipping_tax'              => (float) wc_format_decimal( $this->order->get_shipping_tax(), 2 ),
			'total_discount'            => (float) wc_format_decimal( $this->order->get_total_discount(), 2 ),
			'cart_discount'             => (float) wc_format_decimal( 0, 2 ),
			'order_discount'            => (float) wc_format_decimal( 0, 2 ),
			'view_order_url'            => $this->order->get_view_order_url(),
		];
	}


	protected function get_delivery_info(): array {

		$data = [];
		foreach ( $this->order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
			$data = [
				'delivery_id'           => $shipping_item_id,
				'delivery_method_id'    => $shipping_item->get_method_id(),
				'delivery_method_title' => $shipping_item->get_name(),
				'delivery_total'        => (float) wc_format_decimal( $shipping_item->get_total(), 2 ),
			];
		}

		return $data;
	}


	protected function get_taxes_info(): array {

		$data = [];
		foreach ( $this->order->get_tax_totals() as $tax_code => $tax ) {
			$data = [
				'order_tax_code'     => $tax_code,
				'order_tax_title'    => $tax->label,
				'order_tax_total'    => (float) wc_format_decimal( $tax->amount, 2 ),
				'order_tax_compound' => (bool) $tax->is_compound,
			];
		}

		return $data;
	}


	protected function get_fees_info(): array {

		$data = [];
		foreach ( $this->order->get_fees() as $fee_item_id => $fee_item ) {
			$data = [
				'order_fees_id'        => $fee_item_id,
				'order_fees_title'     => $fee_item->get_name(),
				'order_fees_tax_class' => $fee_item->get_tax_class(),
				'order_fees_total'     => (float) wc_format_decimal( $this->order->get_line_total( $fee_item ), 2 ),
				'order_fees_total_tax' => (float) wc_format_decimal( $this->order->get_line_tax( $fee_item ), 2 ),
			];
		}

		return $data;
	}


	protected function get_coupon_info(): array {

		$data = [];
		foreach ( $this->order->get_items( 'coupon' ) as $coupon_item_id => $coupon_item ) {
			$data = [
				'order_coupon_id'     => $coupon_item_id,
				'order_coupon_code'   => $coupon_item->get_code(),
				'order_coupon_amount' => (float) wc_format_decimal( $coupon_item->get_discount(), 2 ),
			];
		}

		return $data;
	}


	protected function get_items_info(): array {

		$data = [];
		foreach ( $this->order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			$data['order_items'][] = [
				'id'         => $item_id,
				'name'       => $item->get_name(),
				'product_id' => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
				'sku'        => is_object( $product ) ? $product->get_sku() : null,
				'quantity'   => $item->get_quantity(),
				'price'      => (float) wc_format_decimal( $this->order->get_item_total( $item ), 2 ),
				'subtotal'   => (float) wc_format_decimal( $this->order->get_line_subtotal( $item ), 2 ),
				'total'      => (float) wc_format_decimal( $this->order->get_line_total( $item ), 2 ),
				'total_tax'  => (float) wc_format_decimal( $this->order->get_line_tax( $item ), 2 ),
				'tax_class'  => $item->get_tax_class(),
			];
		}

		return $data;
	}


	protected function get_dates_info(): array {

		return [
			'date_created'      => $this->get_date( $this->order->get_date_created() ),
			'date_modified'     => $this->get_date( $this->order->get_date_modified() ),
			'date_completed'    => $this->get_date( $this->order->get_date_completed() ),
			'date_paid'         => $this->get_date( $this->order->get_date_paid() ),
			'date_created_at'   => $this->format_datetime( $this->order->get_date_created() ? $this->order->get_date_created()->getTimestamp() : 0, false, false ),
			'date_updated_at'   => $this->format_datetime( $this->order->get_date_modified() ? $this->order->get_date_modified()->getTimestamp() : 0, false, false ),
			'date_completed_at' => $this->format_datetime( $this->order->get_date_completed() ? $this->order->get_date_completed()->getTimestamp() : 0, false, false ),
		];
	}


	protected function get_billing_info(): array {

		return [
			'billing_first_name' => $this->order->get_billing_first_name(),
			'billing_last_name'  => $this->order->get_billing_last_name(),
			'billing_full_name'  => $this->order->get_formatted_billing_full_name(),
			'billing_company'    => $this->order->get_billing_company(),
			'billing_phone'      => $this->order->get_billing_phone(),
			'billing_email'      => $this->order->get_billing_email(),
			'billing_country'    => $this->order->get_billing_country(),
			'billing_state'      => $this->order->get_billing_state(),
			'billing_city'       => $this->order->get_billing_city(),
			'billing_address_1'  => $this->order->get_billing_address_1(),
			'billing_address_2'  => $this->order->get_billing_address_2(),
			'billing_address'    => $this->order->get_formatted_billing_address(),
			'billing_postcode'   => $this->order->get_billing_postcode(),
		];

	}


	protected function get_shipping_info(): array {

		return [
			'shipping_first_name' => $this->order->get_shipping_first_name(),
			'shipping_last_name'  => $this->order->get_shipping_last_name(),
			'shipping_full_name'  => $this->order->get_formatted_shipping_full_name(),
			'shipping_company'    => $this->order->get_shipping_company(),
			'shipping_phone'      => $this->order->get_shipping_phone(),
			'shipping_country'    => $this->order->get_shipping_country(),
			'shipping_state'      => $this->order->get_shipping_state(),
			'shipping_city'       => $this->order->get_shipping_city(),
			'shipping_address_1'  => $this->order->get_shipping_address_1(),
			'shipping_address_2'  => $this->order->get_shipping_address_2(),
			'shipping_address'    => $this->order->get_formatted_shipping_address(),
			'shipping_postcode'   => $this->order->get_shipping_postcode(),
		];

	}


	protected function get_customer_info(): array {

		return [
			'customer_id'         => $this->order->get_customer_id(),
			'customer_ip_address' => $this->order->get_customer_ip_address(),
			'customer_user_agent' => $this->order->get_customer_user_agent(),
			'customer_note'       => $this->order->get_customer_note(),
		];

	}


	protected function get_payment_info(): array {

		return [
			'payment_method_id'    => $this->order->get_payment_method(),
			'payment_method_title' => $this->order->get_payment_method_title(),
			'payment_paid'         => ! is_null( $this->order->get_date_paid() ),
		];

	}


	protected function get_date( $order_date ): string {

		return $order_date ? wc_format_datetime( $order_date, get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) ) : '';
	}


	private function get_order_subtotal( $order ) {

		$subtotal = 0;

		// subtotal
		foreach ( $order->get_items() as $item ) {
			$subtotal += $item->get_subtotal();
		}

		return $subtotal;
	}


	public function format_datetime( $timestamp, $convert_to_utc = false, $convert_to_gmt = false ): string {

		if ( $convert_to_gmt ) {
			if ( is_numeric( $timestamp ) ) {
				$timestamp = date( 'Y-m-d H:i:s', $timestamp );
			}

			$timestamp = get_gmt_from_date( $timestamp );
		}

		if ( $convert_to_utc ) {
			$timezone = new DateTimeZone( wc_timezone_string() );
		} else {
			$timezone = new DateTimeZone( 'UTC' );
		}

		try {

			if ( is_numeric( $timestamp ) ) {
				$date = new DateTime( "@$timestamp" );
			} else {
				$date = new DateTime( $timestamp, $timezone );
			}

			// convert to UTC by adjusting the time based on the offset of the site's timezone
			if ( $convert_to_utc ) {
				$date->modify( - 1 * $date->getOffset() . ' seconds' );
			}
		} catch ( Exception $e ) {

			$date = new DateTime( '@0' );
		}

		return $date->format( 'Y-m-d\TH:i:s\Z' );
	}

}