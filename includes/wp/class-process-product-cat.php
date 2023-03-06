<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Woocommerce\Prepare_Product_Cat_Data;

class Process_Product_Cat extends Process implements Hooked{

	protected static string $entity = 'product_cat';

	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_action('created_product_cat', [$this, 'process_create'], 10, 3);
		add_action('delete_product_cat', [$this, 'process_delete'], 10, 4);
		add_action('edited_product_cat', [$this, 'process_update'], 10, 3);

	}

	/**
	 * @param $term_id
	 * @param $tt_id
	 * @param $args
	 *
	 * Sends updated Product Cat details to AINSYS.
	 */
	public function process_update( $term_id, $tt_id, $args ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$term = get_term($term_id);

		if(!$term ||
			$term->taxonomy != self::$entity){
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data($term_id),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );
	}

	/**
	 * @param $term
	 * @param $tt_id
	 * @param $deleted_term
	 * @param $object_ids
	 *
	 * Sends delete post details to AINSYS
	 */
	public function process_delete( $term, $tt_id, $deleted_term, $object_ids ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_delete_fields_' . self::$entity,
			$this->prepare_data( $term ),
			$term
		);

		$this->send_data( $term, self::$entity, self::$action, $fields );

	}

	/**
	 * @param $term_id
	 * @param $tt_id
	 * @param $args
	 *
	 * Sends new Product Category details to AINSYS
	 */
	public function process_create( $term_id, $tt_id, $args ): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data($term_id),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );

	}

	/**
	 * Sends updated Product Category details to AINSYS.
	 *
	 * @param       $product_cat_id
	 * @param       $product_cat
	 * @param       $update
	 *
	 * @return array
	 */
	public function process_checking( $product_cat_id, $product_cat, $update ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		if ( $product_cat->taxonomy !== self::$entity ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data($product_cat),
			$product_cat_id
		);

		return $this->send_data( $product_cat_id, self::$entity, self::$action, $fields );
	}

	/**
	 * @param $order_id
	 *
	 * @return array|mixed|void
	 * Prepare Product Category data, for send to AINSYS
	 */
	public function prepare_data( $term_id ){

		$data = [];

		$product_cat = get_term_by('id', $term_id, self::$entity);

		if(!$product_cat){
			return $data;
		}

		$prepare = new Prepare_Product_Cat_Data($product_cat);
		$data = $prepare->prepare_data();

		return $data;

	}

}