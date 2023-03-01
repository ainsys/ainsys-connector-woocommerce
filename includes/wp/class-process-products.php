<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Hooked;
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

		add_action( 'woocommerce_new_product', [$this, 'process_create'], 10, 1 );
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

		/*if ( ! $this->is_updated( $product_id, $product, $update ) ) {
			return [];
		}*/

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

		/*if ( ! $this->is_updated( $product_id, $product, $update ) ) {
			return;
		}*/

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

		/*$file = plugin_dir_path(__FILE__) . 'test-simple.json';
		
		if(file_exists($file)){
			file_put_contents($file, json_encode($data, JSON_FORCE_OBJECT));
		}*/

		return $data;

	}

}