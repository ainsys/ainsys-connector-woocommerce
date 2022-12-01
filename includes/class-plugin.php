<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\Core;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Master\Settings\Admin_UI;
use Ainsys\Connector\Master\UTM_Handler;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Order;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Product;
use Ainsys\Connector\Woocommerce\Woo_UI;

class Plugin implements Hooked {

	use Plugin_Common;

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var UTM_Handler
	 */
	private $UTM_handler;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Admin_UI
	 */
	private $admin_ui;


	public function __construct( Core $core, Logger $logger, UTM_Handler $UTM_handler, Settings $settings, Admin_UI $admin_ui ) {
		$this->core        = $core;
		$this->logger      = $logger;
		$this->UTM_handler = $UTM_handler;
		$this->settings    = $settings;
		$this->admin_ui    = $admin_ui;

		$this->init_plugin_metadata();

		$this->components['product_webhook'] = new Handle_Product();
		$this->components['order_webhook']   = new Handle_Order();

		$woo_ui = new Woo_UI( $this, $this->logger, $this->admin_ui );
	}


	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'ainsys_status_list', array( $this, 'add_status_of_component' ), 10, 1 );
		if ( $this->is_woocommerce_active() ) {
			// add hooks.
			add_filter( 'ainsys_get_entities_list', array( $this, 'add_entity_to_list' ), 10, 1 );
			add_filter( 'ainsys_get_entity_fields_handlers', array( $this, 'add_fields_getters_for_entities' ), 10, 1 );
			add_filter( 'ainsys_default_apis_for_entities', array( $this, 'add_default_api_for_entities_option' ), 10, 1 );

			add_action( 'woocommerce_checkout_order_processed', array( $this, 'new_order_processed' ) );
			add_action( 'post_updated', array( $this, 'ainsys_update_order' ), 10, 4 );
			add_action( 'woocommerce_order_status_changed', array( $this, 'send_order_status_update_to_ainsys' ) );
			add_action( 'woocommerce_update_product', array( $this, 'send_update_product_to_ainsys' ), 10, 3 );

			foreach ( $this->components as $component ) {
				if ( $component instanceof Hooked ) {
					$component->init_hooks();
				}
			}
		}
	}

	/**
	 * Generates a component status to show on the General tab of the master plugin settings.
	 *
	 * @return array
	 */
	public function add_status_of_component( $status_items = array() ) {

		$status_items['woocommerce'] = array(
			'title'  => __( 'WooCommerce', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'active' => $this->is_woocommerce_active(),
		);

		return $status_items;
	}

	/**
	 * Checks if the woocommerce plugin is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return $this->is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Adds woocommerce entities to the entities list.
	 *
	 * @return array
	 */
	public function add_entity_to_list( $entities_list = array() ) {

		$entities_list['order']   = __( 'Order / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
		$entities_list['product'] = __( 'Product / fields', AINSYS_CONNECTOR_TEXTDOMAIN );

		if ( function_exists( 'wc_coupons_enabled' ) ) {
			if ( wc_coupons_enabled() ) {
				$entities_list['coupons'] = __( 'Coupons / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
			}
		}

		return $entities_list;
	}

	/**
	 * Adds names of functions to get woocommerce entities to the getters array.
	 *
	 * @return array
	 */
	public function add_fields_getters_for_entities( $getters = array() ) {
		$getters['product'] = array( $this, 'get_product_fields' );
		$getters['order']   = array( $this, 'get_order_fields' );
		$getters['coupons'] = array( $this, 'get_coupons_fields' );

		return $getters;
	}

	/**
	 * Adds woocommerce to the apis array.
	 *
	 * @return array
	 */
	public function add_default_api_for_entities_option( $default_apis ) {
		$default_apis['woocommerce'] = '';

		return $default_apis;
	}

	/**
	 * Sends an order to ainsys (used on order update action).
	 *
	 * @param int $order_id
	 * @param object $order_new
	 * @param object $order_old
	 * @param bool $test - if true returns an array with the request data and a server responce. If false returns void.
	 *
	 * @return mixed
	 */
	public function ainsys_update_order( $order_id, $order_new, $order_old, $test = false ) {

		if ( 'shop_order' === get_post_type( $order_id ) ) {

			$request_action = 'UPDATE';

			$fields = (array) $order_new;

			$request_data = array(
				'entity'  => array(
					'id'   => $order_id,
					'name' => 'order',
				),
				'action'  => $request_action,
				'payload' => $fields,
			);

			try {
				$server_response = $this->core->curl_exec_func( $request_data );
			} catch ( \Exception $e ) {
				$server_response = 'Error: ' . $e->getMessage();
				$this->core->send_error_email( $server_response );
			}

			$this->logger->save_log_information( $order_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

			if ( $test ) {
				$result = array(
					'request'  => $request_data,
					'response' => $server_response,
				);
				return $result;
			} else {
				return;
			}
		}
		return;
	}

	/**
	 * Sends a new order to AINSYS
	 *
	 * @param int $order_id
	 *
	 * @return mixed
	 */
	public function new_order_processed( $order_id = 0 ) {
		if ( ! $order_id ) {
			return false;
		}

		$request_action = 'CREATE';

		$order = new \WC_Order( $order_id );
		$data  = $order->get_data();
		if ( empty( $data ) ) {
			return false;
		}

		//self::save_log_information($order_id, 'settings dump', serialize($data), '', 0);

		// Prepare order data
		$fields   = $this->prepare_fields( $data );
		$utm_data = $this->get_utm_fields();

		//Prepare products
		if ( isset( $data['line_items'] ) && ! empty( $data['line_items'] ) ) {
			$products = $this->prepare_products( $data['line_items'] );
		} else {
			$products = array();
		}

		$fields_filtered = apply_filters( 'ainsys_new_order_fields', $fields, $order );

		$this->sanitize_aditional_order_fields( array_diff( $fields_filtered, $fields ), $data['id'] );

		$order_data = array(
			'entity'  => array(
				'id'   => $order_id,
				'name' => 'order',
			),
			'action'  => $request_action,
			'payload' => array_merge( $fields_filtered, $utm_data, array( 'products' => $products ) ),
		);

		try {
			$server_response = $this->core->curl_exec_func( $order_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
			$this->core->send_error_email( $server_response );
		}

		$this->logger->save_log_information( $order_id, $request_action, serialize( $order_data ), serialize( $server_response ), 0 );

		return true;
	}

	/**
	 * Sends an updated order status to AINSYS
	 *
	 * @param int $order_id
	 *
	 * @return
	 */
	public function send_order_status_update_to_ainsys( int $order_id ) {
		$host = false;
		if ( ! empty( $_SERVER['SERVER_NAME'] ) ) {
			$host = $_SERVER['SERVER_NAME'];
		}

		$request_action = 'update/order';

		$order  = new \WC_Order( $order_id );
		$status = $order->get_status() ?? false;

		$order_data = array(
			'object_id'      => $order_id,
			'request_action' => $request_action,
			'request_data'   => array(
				'status'   => strtoupper( trim( $status ) ),
				'hostname' => $host,
			),
		);

		try {
			$server_response = $this->core->curl_exec_func( $order_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
			$this->core->send_error_email( $server_response );
		}

		$this->logger->save_log_information( $order_id, $request_action, serialize( $order_data ), serialize( $server_response ), 0 );

		return;
	}

	/**
	 * Sends updated WC product to AINSYS
	 *
	 * @param int $product_id
	 * @param object $product
	 *
	 * @return
	 */
	public function send_update_product_to_ainsys( $product_id, $product, $test = false ) {
		$request_action = 'UPDATE';

		$fields = apply_filters( 'ainsys_update_product_fields', $this->prepare_single_product( $product ), $product );

		$request_data = array(
			'entity'  => array(
				'id'   => $product_id,
				'name' => 'product',
			),
			'action'  => $request_action,
			'payload' => $fields,
		);

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
			$this->core->send_error_email( $server_response );
		}

		$this->logger->save_log_information( $product_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		if ( $test ) {
			$result = array(
				'request'  => $request_data,
				'response' => $server_response,
			);
			return $result;
		} else {
			return;
		}
	}

	/**
	 * Prepares order fields
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	private function prepare_fields( $data = array() ) {
		$all_fields = WC()->checkout->get_checkout_fields();

		$prepare_data = array();
		if ( ! empty( $data['id'] ) ) {
			$prepare_data['id'] = $data['id'];
		}

		if ( ! empty( $data['currency'] ) ) {
			$prepare_data['currency'] = $data['currency'];
		}

		if ( ! empty( $data['customer_id'] ) ) {
			$prepare_data['customer_id'] = $data['customer_id'];
		}

		if ( ! empty( $data['shipping_total'] ) ) {
			$prepare_data['shipping_total'] = $data['shipping_total'];
		}

		if ( ! empty( $data['payment_method_title'] ) ) {
			$prepare_data['payment_method_title'] = $data['payment_method_title'];
		}

		if ( ! empty( $data['transaction_id'] ) ) {
			$prepare_data['transaction_id'] = $data['transaction_id'];
		}

		if ( ! empty( $data['customer_note'] ) ) {
			$prepare_data['customer_note'] = $data['customer_note'];
		}

		$prepare_data['date'] = date( 'Y-m-d H:i:s' );

		// get applaed cupons
		$coupons = WC()->cart->get_coupons();
		if ( ! empty( $coupons ) ) {
			foreach ( $coupons as $coupon ) {
				$prepare_data[ 'coupon_' . $coupon->get_code() ] = wc_format_decimal( $coupon->get_amount(), 2 ) . ' ' . $coupon->get_discount_type();
			}
		}

		//billing
		if ( ! empty( $data['billing'] ) ) {
			foreach ( $data['billing'] as $billing_key => $billing_value ) {
				if ( ! empty( $billing_value ) ) {
					$prepare_data[ 'billing_' . $billing_key ] = $billing_value;
				}
				unset( $all_fields['billing'][ 'billing_' . $billing_key ] );
			}
		}

		/// search for custom fields
		if ( ! empty( $all_fields['billing'] ) ) {
			$prepare_data = array_merge( $prepare_data, $this->sanitize_aditional_order_fields( $all_fields['billing'], $data['id'] ) );
		}

		//shipping
		if ( ! empty( $data['shipping'] ) ) {
			foreach ( $data['shipping'] as $shipping_key => $shipping_value ) {
				if ( ! empty( $shipping_value ) ) {
					$prepare_data[ 'shipping_' . $shipping_key ] = $shipping_value;
				}
				unset( $all_fields['shipping'][ 'shipping_' . $shipping_key ] );
			}
		}
		/// search for custom fields
		if ( ! empty( $all_fields['shipping'] ) ) {
			$prepare_data = array_merge( $prepare_data, $this->sanitize_aditional_order_fields( $all_fields['shipping'], $data['id'] ) );
		}

		return $prepare_data;
	}

	/**
	 * Grab UTM fields
	 *
	 * @return array
	 */
	private function get_utm_fields() {
		$data = array();
		if ( ! empty( $this->UTM_handler::get_referer_url() ) ) {
			$data['REFERER'] = $this->UTM_handler::get_referer_url();
		}

		if ( ! empty( $this->UTM_handler::get_my_host_name() ) ) {
			$data['HOSTNAME'] = $this->UTM_handler::get_my_host_name();
		}

		if ( ! empty( $this->UTM_handler::get_my_ip() ) ) {
			$data['USER_IP'] = $this->UTM_handler::get_my_ip();
		}

		if ( ! empty( $this->UTM_handler::get_roistat() ) ) {
			$data['ROISTAT_VISIT_ID'] = $this->UTM_handler::get_roistat();
		}

		return $data;
	}

	/**
	 * Prepares WC order products
	 *
	 * @param object $products
	 *
	 * @return array
	 */
	private function prepare_products( $products = array() ) {
		$prepare_data = array();
		if ( empty( $products ) ) {
			return $prepare_data;
		}

		foreach ( $products as $item_id => $item ) {
			$product       = $item->get_product();
			$regular_price = $product->get_regular_price();
			$price         = $product->get_price();
			$sku           = $product->get_sku();
			$product_id    = $product->get_id();

			$prepare_data[ $item_id ]['name']     = $item->get_name();
			$prepare_data[ $item_id ]['quantity'] = $item->get_quantity();
			$prepare_data[ $item_id ]['price']    = $price;
			$prepare_data[ $item_id ]['id']       = $product_id;
			if ( ! empty( $sku ) ) {
				$prepare_data[ $item_id ]['sku'] = $sku;
			}

			//If discounted
			if ( $price !== $regular_price ) {
				$prepare_data[ $item_id ]['discount_type_id'] = 1;
				$prepare_data[ $item_id ]['discount_sum']     = $regular_price - $price;
			}
		}

		return $prepare_data;
	}

	/**
	 * Prepares WC product fields
	 *
	 * @param object $product
	 *
	 * @return array
	 */
	private function prepare_single_product( $product ) {
		if ( empty( $product ) ) {
			return array();
		}

		return array(
			'title'              => $product->get_name(),
			'id'                 => $product->get_id(),
			'created_at'         => (array) $product->get_date_created(),
			'updated_at'         => (array) $product->get_date_modified(),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'downloadable'       => $product->is_downloadable(),
			'virtual'            => $product->is_virtual(),
			'permalink'          => $product->get_permalink(),
			'sku'                => $product->get_sku(),
			'price'              => wc_format_decimal( $product->get_price(), 2 ),
			'regular_price'      => wc_format_decimal( $product->get_regular_price(), 2 ),
			'sale_price'         => $product->get_sale_price() ? wc_format_decimal( $product->get_sale_price(), 2 ) : null,
			'price_html'         => $product->get_price_html(),
			'taxable'            => $product->is_taxable(),
			'tax_status'         => $product->get_tax_status(),
			'tax_class'          => $product->get_tax_class(),
			'managing_stock'     => $product->managing_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'in_stock'           => $product->is_in_stock(),
			'backorders_allowed' => $product->backorders_allowed(),
			'backordered'        => $product->is_on_backorder(),
			'sold_individually'  => $product->is_sold_individually(),
			'purchaseable'       => $product->is_purchasable(),
			'featured'           => $product->is_featured(),
			'visible'            => $product->is_visible(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'on_sale'            => $product->is_on_sale(),
			'weight'             => $product->get_weight() ? wc_format_decimal( $product->get_weight(), 2 ) : null,
			'dimensions'         => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
				'unit'   => get_option( 'woocommerce_dimension_unit' ),
			),
			'shipping_required'  => $product->needs_shipping(),
			'shipping_taxable'   => $product->is_shipping_taxable(),
			'shipping_class'     => $product->get_shipping_class(),
			'shipping_class_id'  => ( 0 !== $product->get_shipping_class_id() ) ? $product->get_shipping_class_id() : null,
			'description'        => apply_filters( 'the_content', $product->get_description() ),
			'short_description'  => apply_filters( 'woocommerce_short_description', $product->get_short_description() ),
			'reviews_allowed'    => $product->get_reviews_allowed(),
			'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
			'rating_count'       => $product->get_rating_count(),
			'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
			'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
			'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
			'categories'         => wc_get_object_terms( $product->get_id(), 'product_cat', 'name' ),
			'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ),
			//'images'             => $this->get_images( $product ),
			'featured_src'       => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) ),
			//'attributes'         => $this->get_attributes( $product ),
			//'downloads'          => $this->get_downloads( $product ),
			'download_limit'     => $product->get_download_limit(),
			'download_expiry'    => $product->get_download_expiry(),
			//'download_type'      => 'standard',
			//'purchase_note'      => apply_filters( 'the_content', $product->get_purchase_note() )
			//'total_sales'        => $product->get_total_sales(),
		);
	}


	/**
	 * Sanitizes additional order fields from current order and saves them to the order entity
	 *
	 * @param array $aditional_fields
	 * @param string $prefix
	 *
	 * @return array
	 */
	private function sanitize_aditional_order_fields( $aditional_fields, $order_id ) {
		global $wpdb;
		$prepare_data = array();
		foreach ( $aditional_fields as $field_name => $fields ) {
			$field_value = get_post_meta( $order_id, '_' . $field_name, true );
			if ( ! empty( $field_value ) ) {
				//$field_slug = empty(self::translit($field["label"])) ? $field_name : $prefix . self::translit($field["label"]);
				$prepare_data[ $field_name ] = $field_value;

				/// Saving Order field to DB
				$entity_saved_settings = $this->settings::get_saved_entity_settings_from_db( ' WHERE entity="order" AND setting_key="extra_field" AND setting_name="' . $field_name . '"' );
				$response              = '';
				if ( empty( $entity_saved_settings ) ) {
					$response      = $wpdb->insert(
						$wpdb->prefix . $this->settings::$ainsys_entities_settings_table,
						array(
							'entity'       => 'order',
							'setting_name' => $field_name,
							'setting_key'  => 'extra_field',
							'value'        => serialize( $fields ),
						)
					);
					$field_data_id = $wpdb->insert_id;

					/// Save new field to log
					$this->logger->save_log_information( $field_data_id, $field_name, 'order_cstom_field_saved', '', 0 );
				} else {
					$response = $wpdb->update(
						$wpdb->prefix . $this->settings::$ainsys_entities_settings_table,
						array( 'value' => serialize( $fields ) ),
						array( 'id' => $entity_saved_settings['id'] )
					);
				}
			}
		}

		return $prepare_data;
	}

	/**
	 * Creates an array with coupon fields.
	 *
	 * @return array
	 */
	public function get_coupons_fields() {
		return array(
			'code'                        => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'discount_type'               => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'amount'                      => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'date_expires'                => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'individual_use'              => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'product_ids'                 => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'excluded_product_ids'        => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'usage_limit'                 => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'usage_limit_per_user'        => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'limit_usage_to_x_items'      => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'free_shipping'               => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'product_categories'          => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'excluded_product_categories' => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'exclude_sale_items'          => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'minimum_amount'              => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'maximum_amount'              => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'email_restrictions'          => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
		);
	}

	/**
	 * Generates fields for PRODUCT entity.
	 *
	 * @return array
	 */
	public function get_product_fields() {
		return array(
			'title'              => array(
				'nice_name'   => __( 'Title', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'api'         => 'woocommerce',
				'description' => 'Product title',
			),
			'id'                 => array(
				'nice_name' => __( '{ID}', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'api'       => 'woocommerce',
			),
			'created_at'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'updated_at'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'type'               => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'status'             => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'downloadable'       => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'virtual'            => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'permalink'          => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'sku'                => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'price'              => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'regular_price'      => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'sale_price'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'price_html'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'taxable'            => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'tax_status'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'tax_class'          => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'managing_stock'     => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'stock_quantity'     => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'in_stock'           => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'backorders_allowed' => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'backordered'        => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'sold_individually'  => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'purchaseable'       => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'featured'           => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'visible'            => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'catalog_visibility' => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'on_sale'            => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'weight'             => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'dimensions'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'shipping_required'  => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'shipping_taxable'   => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'shipping_class'     => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'shipping_class_id'  => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'nice_name'          => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'short_nice_name'    => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'reviews_allowed'    => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'average_rating'     => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'rating_count'       => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'related_ids'        => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'upsell_ids'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'cross_sell_ids'     => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'categories'         => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'tags'               => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			//"images"
			'featured_src'       => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			//"attributes"
			//"downloads"
			'download_limit'     => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'download_expiry'    => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			//"download_type"
			//"purchase_note"
			//"total_sales"
		);
	}

	/**
	 * Generates fields for ORDER entity
	 *
	 * @return array
	 */
	public function get_order_fields() {
		$prepared_fields = array(
			'id'                   => array(
				'nice_name' => __( '{ID}', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'api'       => 'woocommerce',
			),
			'currency'             => array(
				'nice_name' => __( 'Currency', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'api'       => 'woocommerce',
			),
			'customer_id'          => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'payment_method_title' => array(
				'nice_name' => __( 'Payment', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'api'       => 'woocommerce',
			),
			'date'                 => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'referer'              => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'hostname'             => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'user_ip'              => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
			'products'             => array(
				'nice_name' => '',
				'api'       => 'woocommerce',
			),
		);

		$order_fields = WC()->checkout->get_checkout_fields();

		foreach ( $order_fields as $category => $fields ) {
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field_slug => $settings ) {
					$prepared_fields[ $field_slug ] = array(
						'nice_name'   => $settings['label'] ?? '',
						'description' => $settings['label'] ?? '',
						'api'         => 'woocommerce',
						'required'    => isset( $settings['required'] ) && $settings['required'] ? 1 : 0,
						'sample'      => isset( $settings['placeholder'] ) ? $settings['placeholder'] : '',
					);
				}
			} else {
				$prepared_fields[ $category ] = array(
					'api' => 'woocommerce',
				);
			}
		}

		$order_saved_settings = $this->settings::get_saved_entity_settings_from_db( ' WHERE entity="order" AND setting_key="extra_field"', false );
		$order_extra_fields   = array();
		if ( ! empty( $order_saved_settings ) ) {
			foreach ( $order_saved_settings as $saved_setting ) {
				//preg_match('/(?<cat>\S+)_/', $saved_setting["setting_name"], $matches);
				$order_extra_fields[ $saved_setting['setting_name'] ]        = maybe_unserialize( $saved_setting['value'] );
				$order_extra_fields[ $saved_setting['setting_name'] ]['api'] = 'mixed';
			}
		}
		$prepared_fields = array_merge(
			$prepared_fields,
			apply_filters( 'ainsys_woocommerce_extra_fields_for_order', $order_extra_fields )
		);

		return $prepared_fields;
	}
}
