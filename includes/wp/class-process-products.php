<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Woocommerce\Helper;
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
	public function process_delete( int $product_id, $post ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_delete_fields_' . self::$entity,
			$this->prepare_data( $product_id ),
			$product_id
		);

		$this->send_data( $product_id, self::$entity, self::$action, $fields );

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

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data($product_id),
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

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data($product_id),
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
			$this->prepare_data($product_id),
			$product_id
		);

		$this->send_data( $product_id, self::$entity, self::$action, $fields );
	}

	/**
	 * @param $product_id
	 *
	 * @return array|mixed|void
	 * Prepare product data, for send to AINSYS
	 */
	public function prepare_data($product_id){

		$data = [];

		$product = wc_get_product($product_id);

		if(!$product){
			return $data;
		}

		$prepare = new Prepare_Product_Data($product);
		$data = $prepare->prepare_data();

		if($product->is_type('variable')){
			$variations = new Prepare_Product_Variation_Data($product);
			$data['variations'] = $variations->prepare_data();
		}

		$helper = new Helper();
		var_dump($helper->check_image_exist('http://ainsys.loc/wp-content/uploads/2022/12/1200-675-twitter-linkedin-post-30.png'));

		/*$file = plugin_dir_path(__FILE__) . 'test-simple.json';
		
		if(file_exists($file)){
			file_put_contents($file, json_encode($data, JSON_FORCE_OBJECT));
		}*/

		return $data;

	}

}