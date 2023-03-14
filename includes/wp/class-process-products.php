<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Woocommerce\WP\Prepare\Prepare_Product;
use Ainsys\Connector\Woocommerce\WP\Prepare\Prepare_Product_Variation;
use WC_Product;

class Process_Products extends Process implements Hooked {

	protected static string $entity = 'product';


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_action( 'wp_after_insert_post', [ $this, 'process_create' ], 1000, 4 );
		add_action( 'woocommerce_update_product', [ $this, 'process_update' ], 1010, 2 );

		add_action( 'deleted_post', [ $this, 'process_delete' ], 10, 2 );
		add_action( 'trashed_post', [ $this, 'process_trash' ], 10, 1 );

	}


	/**
	 * Sends new product details to AINSYS
	 *
	 * @param  int  $product_id
	 * @param       $post
	 * @param  bool $update
	 * @param       $post_before
	 *
	 * @return void
	 */
	public function process_create( int $product_id, $post, bool $update, $post_before ): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status || is_int( wp_is_post_revision( $post ) ) || is_int( wp_is_post_autosave( $post ) ) ) {
			return;
		}

		if ( $post_before && 'publish' === $post_before->post_status ) {
			return;
		}

		if ( $post->post_type !== self::$entity ) {
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
	 * Sends updated product details to AINSYS.
	 *
	 * @param  int        $product_id
	 * @param  WC_Product $product
	 */
	public function process_update( int $product_id, WC_Product $product ): void {


		if ( $_REQUEST['action'] === 'editpost' && did_action( 'woocommerce_update_product' ) > 1 ) {
			return;
		}

		if ( ! in_array( get_post_type( $product_id ), [ self::$entity, 'product_variation' ], true ) ) {
			return;
		}

		if ( strtotime( $product->get_date_created()->date( 'Y-m-d H:i:s' ) ) === strtotime( $product->get_date_modified()->date( 'Y-m-d H:i:s' ) ) ) {
			return;
		}

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $product_id ),
			$product_id
		);

		$this->send_data( $product_id, self::$entity, self::$action, $fields );

		clean_post_cache( $product_id );
	}


	/**
	 * Sends delete post details to AINSYS
	 *
	 * @param  int $product_id
	 * @param      $product
	 *
	 * @return void
	 */
	public function process_delete( int $product_id, $product ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

if ( ! in_array( get_post_type( $product_id ), [ self::$entity, 'product_variation' ], true ) ) {
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
	 * Sends delete post details to AINSYS
	 *
	 * @param  int $product_id
	 *
	 * @return void
	 */
	public function process_trash( int $product_id ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if ( ! in_array( get_post_type( $product_id ), [ self::$entity, 'product_variation' ], true ) ) {
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
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $product_id
	 *
	 * @return array
	 */
	public function process_checking( $product_id ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		if ( get_post_type( $product_id ) !== self::$entity ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $product_id ),
			$product_id
		);

		return $this->send_data( $product_id, self::$entity, self::$action, $fields );
	}


	/**
	 * Prepare product data, for send to AINSYS
	 *
	 * @param $product_id
	 *
	 * @return array
	 */
	public function prepare_data( $product_id ): array {

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return [];
		}

		$data = ( new Prepare_Product( $product ) )->prepare_data();

		if ( $product->is_type( 'variable' ) ) {
			$data['variations'] = ( new Prepare_Product_Variation( $product ) )->prepare_data();
		}

		return $data;

	}

}