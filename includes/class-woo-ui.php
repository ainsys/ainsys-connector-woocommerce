<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\Core;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Master\Settings\Admin_UI;
use Ainsys\Connector\Woocommerce\Webhooks\Plugin;

class Woo_UI implements Hooked {

	use Plugin_Common;

	public function __construct( $plugin, $logger, $admin_ui ) {
		$this->plugin   = $plugin;
		$this->logger   = $logger;
		$this->admin_ui = $admin_ui;
		$this->init_hooks();
	}


	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( $this->plugin->is_woocommerce_active() ) {
			add_filter( 'ainsys_test_table', array( $this, 'ainsys_test_table' ), 10, 1 );

			add_action( 'wp_ajax_test_woo_connection', array( $this, 'test_woo_connection' ) );

			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'ainsys_woo_enqueue_scripts' ) );
			}
		}
	}

	/**
	 * Enqueues script for ajax tests.
	 */
	public function ainsys_woo_enqueue_scripts() {
		wp_enqueue_script( 'ainsys_connector_admin_woo_handle', plugins_url( 'ainsys-connector-woocommerce/assets/js/ainsys_connector_woo_admin.js' ), array( 'jquery' ), '4.0.0', true );
	}

	/**
	 * Function to handle ajax.
	 *
	 * @return object $result - contains an array with request data and a string with server response
	 */
	public function test_woo_connection() {
		if ( isset( $_POST['entity'] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], $this->admin_ui::$nonce_title ) ) {

			$make_request = false;

			$entity = strip_tags( $_POST['entity'] );

			if ( 'product' === $entity ) {
				$args     = array(
					'limit' => 1,
				);
				$products = wc_get_products( $args );
				if ( ! empty( $products ) ) {
					foreach ( $products as $product ) {
						$fields     = $product;
						$product_id = $product->get_id();
						break;
					}

					$make_request         = true;
					$test_result          = $this->plugin->send_update_product_to_ainsys( $product_id, $fields, true );
					$test_result_request  = $test_result['request']; // array
					$test_result_response = $test_result['response']; // string
				}
			}

			if ( 'order' === $entity ) {
				$args   = array(
					'limit' => 1,
				);
				$orders = wc_get_orders( $args );
				if ( ! empty( $orders ) ) {
					foreach ( $orders as $order ) {
						$fields   = $order;
						$order_id = $order->get_id();
						break;
					}

					$make_request         = true;
					$test_result          = $this->plugin->ainsys_update_order( $order_id, $fields, $fields, true );
					$test_result_request  = $test_result['request']; // array
					$test_result_response = $test_result['response']; // string
				}
			}

			if ( 'coupons' === $entity ) {
				$args = array(
					'posts_per_page' => 1,
					'post_type'      => 'shop_coupon',
					'post_status'    => 'publish',
				);

				$coupons = get_posts( $args );
				if ( ! empty( $coupons ) ) {
					foreach ( $coupons as $coupon ) {
						$fields    = (array) $coupon;
						$coupon_id = $coupon->ID;
						break;
					}

					//$make_request         = true; TODO
					//$test_result          = $this->send_update_coupon_to_ainsys( $coupon_id, $fields, true );
					//$test_result_request  = $test_result['request']; // array
					//$test_result_response = $test_result['response']; // string
				}
			}

			if ( $make_request ) {

				$result = array(
					'short_request'  => mb_substr( serialize( $test_result_request ), 0, 80 ) . ' ... ',
					'short_responce' => mb_substr( $test_result_response, 0, 80 ) . ' ... ',
					'full_request'   => $this->logger::ainsys_render_json( $test_result_request ),
					'full_responce'  => 0 === strpos( 'Error: ', $test_result_response ) ? $test_result_response : $this->logger::ainsys_render_json( json_decode( $test_result_response ) ),
				);
			} else {
				$result = array(
					'short_request'  => __( 'No entities found', AINSYS_CONNECTOR_TEXTDOMAIN ),
					'short_responce' => '',
					'full_request'   => '',
					'full_responce'  => '',
				);
			}
			echo json_encode( $result );
		}
		die();
	}

	/**
	 * Function to handle ajax.
	 *
	 * @param string $html - html for table rows to append to the Test table
	 *
	 * @return string $html - html with additional rows for woocommerce entities
	 */
	public function ainsys_test_table( $html ) {
		$html .= '<tr><td class="ainsys_td_left">Product / fields</td><td class="ainsys_td_left ainsys-test-json"><div class="ainsys-responce-short"></div><div class="ainsys-responce-full"></div></td><td class="ainsys_td_left ainsys-test-responce"><div class="ainsys-responce-short"></div><div class="ainsys-responce-full"></div></td><td class="ainsys_td_btn"><a href="" class="btn btn-primary ainsys-woo-test" data-entity-name="product">' . __( 'Test', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a></td><td><span class="ainsys-success"></span><span class="ainsys-failure"></span></td></tr>';

		$html .= '<tr><td class="ainsys_td_left">Order / fields</td><td class="ainsys_td_left ainsys-test-json"><div class="ainsys-responce-short"></div><div class="ainsys-responce-full"></div></td><td class="ainsys_td_left ainsys-test-responce"><div class="ainsys-responce-short"></div><div class="ainsys-responce-full"></div></td><td class="ainsys_td_btn"><a href="" class="btn btn-primary ainsys-woo-test" data-entity-name="order">' . __( 'Test', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a></td><td><span class="ainsys-success"></span><span class="ainsys-failure"></span></td></tr>';

		if ( function_exists( 'wc_coupons_enabled' ) ) {
			if ( wc_coupons_enabled() ) {
				$html .= '<tr><td class="ainsys_td_left">Coupons / fields</td><td class="ainsys_td_left ainsys-test-json"><div class="ainsys-responce-short"></div><div class="ainsys-responce-full"></div></td><td class="ainsys_td_left ainsys-test-responce"><div class="ainsys-responce-short"></div><div class="ainsys-responce-full"></div></td><td class="ainsys_td_btn"><a href="" class="btn btn-primary ainsys-woo-test" data-entity-name="coupons">' . __( 'Test', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a></td><td><span class="ainsys-success"></span><span class="ainsys-failure"></span></td></tr>';
			}
		}

		return $html;
	}

}
