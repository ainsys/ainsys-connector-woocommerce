<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Woocommerce\Prepare_Product_Data;
use Ainsys\Connector\Woocommerce\Prepare_Product_Variation_Data;

class Process_Products extends Process implements Hooked {

	protected static string $entity = 'product';


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_filter( 'ainsys_get_entities_list', array( $this, 'add_product_entity_to_list' ), 10, 1 );

		/**
		 * Check entity connection for products
		 */
		add_filter( 'ainsys_before_check_connection_make_request', function () {
			return true;
		} );
		add_filter( 'ainsys_check_connection_request', [ $this, 'check_product_entity' ], 15, 3 );

		add_action( 'woocommerce_new_product', 'on_product_save', 10, 1 );
		add_action( 'save_post_product', [ $this, 'process_update' ], 10, 4 );
		add_action( 'deleted_post', [ $this, 'process_delete' ], 10, 2 );

	}

	/**
	 * Sends delete post details to AINSYS
	 *
	 * @param  int $post_id
	 * @param      $post
	 *
	 * @return void
	 */
	public function process_delete( int $post_id, $post ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_delete_fields_' . self::$entity,
			$this->prepare_data( $post_id ),
			$post_id
		);

		$this->send_data( $post_id, self::$entity, self::$action, $fields );

	}

	/**
	 * Sends new attachment details to AINSYS
	 *
	 * @param  int $product_id
	 *
	 * @return void
	 */
	public function process_create( int $product_id): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$product = new \WC_Product($product_id);

		if(!$product){
			return;
		}

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data( $product_id ),
			$product_id
		);

		$this->send_data( $product_id, self::$entity, self::$action, $fields );

	}

	/**
	 * @param $entities_list
	 *
	 * @return mixed
	 */

	public function add_product_entity_to_list($entities_list){

		$entities_list['product'] = __( 'Product / fields', AINSYS_CONNECTOR_TEXTDOMAIN );

		/*if ( function_exists( 'wc_coupons_enabled' ) ) {
			if ( wc_coupons_enabled() ) {
				$entities_list['coupons'] = __( 'Coupons / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
			}
		}*/

		return $entities_list;

	}

	/**
	 * @param $result_entity
	 * @param $entity
	 * @param $make_request
	 *
	 * @return mixed
	 * Check "product" entity filter callback
	 */
	public function check_product_entity( $result_entity, $entity, $make_request ) {

		$result_test   = $this->get_product();
		$result_entity = Settings::get_option( 'check_connection_entity' );
		$result_entity = $this->get_result_entity( $result_test, $result_entity, $entity );

		return $result_entity;

	}

	/**
	 * @param array $result_test
	 * @param $result_entity
	 * @param $entity
	 *
	 * @return mixed
	 */
	protected function get_result_entity( array $result_test, $result_entity, $entity ) {

		if ( ! empty( $result_test['request'] ) ) {
			$result_request = $result_test['request'];
		} else {
			$result_request = 'Error: Data transfer is disabled. Check the Entities export settings tab';
		}

		if ( ! empty( $result_test['response'] ) ) {
			$result_response = $result_test['response'];
		} else {
			$result_response = __( 'Error: Data transfer is disabled. Check the Entities export settings tab', AINSYS_CONNECTOR_TEXTDOMAIN );
		}

		$result_entity[ $entity ] = [
			'request'        => $result_request,
			'response'       => $result_response,
			'short_request'  => mb_substr( Logger::convert_response( $result_request ), 0, 40 ) . ' ... ',
			'full_request'   => Logger::convert_response( $result_request ),
			'short_response' => mb_substr( Logger::convert_response( $result_response ), 0, 40 ) . ' ... ',
			'full_response'  => Logger::convert_response( $result_response ),
			'time'           => current_time( 'mysql' ),
			'status'         => false === strpos( $result_response, 'Error:' ),
		];

		Settings::set_option( 'check_connection_entity', $result_entity );

		return $result_entity;
	}

	/**
	 * @return array|false
	 *
	 * Get product data for AINSYS
	 *
	 */

	private function get_product() {

		$args = array(
			'limit' => 1,
		);

		$products = wc_get_products( $args );

		if ( ! empty( $products ) ) {

			$product    = end( $products );
			$product_id = $product->get_id();

			return $this->process_checking( $product_id, $product, true );

		} else {
			return false;
		}

	}

	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $product_id
	 * @param       $product
	 * @param       $update
	 *
	 * @return array
	 */
	public function process_checking( $product_id, $product, $update ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		if ( ! $this->is_updated( $product_id, $update ) ) {
			return [];
		}

		if ( get_post_type( $product_id ) !== self::$entity ) {
			return [];
		}

		/*$product = wc_get_product($product_id);
		$prepare = new Prepare_Product_Data($product);
		$data = $prepare->prepare_data();*/

		$product = wc_get_product($product_id);

		$prepare = new Prepare_Product_Data($product);
		$data = $prepare->prepare_data();

		if($product->is_type('variable')){

			foreach($product->get_children() as $variation_id){

				$variation = wc_get_product($variation_id);
				$prepare_variation = new Prepare_Product_Data($variation, 'variation');
				$variation_data = $prepare_variation->prepare_data();
				$data['variations'][] = $variation_data;

			}

		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$data,
			$product_id
		);


		return $this->send_data( $product_id, self::$entity, self::$action, $fields );
	}

	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $product_id
	 * @param       $product
	 * @param       $update
	 */
	public function process_update( $product_id, $product, $update ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if ( ! $this->is_updated( $product_id, $update ) ) {
			return;
		}

		if ( get_post_type($product_id) !== self::$entity ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			new Prepare_Product_Data($product_id),
			$product_id
		);

		$this->send_data( $product_id, self::$entity, self::$action, $fields );
	}

	/**
	 * Function for `add_attachment` action-hook.
	 *
	 * @param int $product_id Product_ID.
	 * @param      $product
	 *
	 * @return array
	 */
	protected function prepare_data( int $product_id): array {

		if ( get_post_type( $product_id ) != self::$entity ) {
			return [];
		}

		$product = new \WC_Product($product_id);

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

}