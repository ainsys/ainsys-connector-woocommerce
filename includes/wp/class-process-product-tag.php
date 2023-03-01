<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Woocommerce\Prepare_Product_Tag_Data;

class Process_Product_Tag extends Process implements Hooked{

	protected static string $entity = 'product_tag';

	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action('created_product_tag', [$this, 'process_create'], 10, 3);
		add_action('delete_product_tag', [$this, 'process_delete'], 10, 4);
		add_action('edited_product_tag', [$this, 'process_update'], 10, 3);
	}

	/**
	 * @param $term_id
	 * @param $tt_id
	 * @param $args
	 *
	 * Sends updated post details to AINSYS.
	 */
	public function process_update( $term_id, $tt_id, $args ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		/*if ( ! $this->is_updated( $product_id, $product, $update ) ) {
			return;
		}*/

		if ( $args['taxonomy'] !== self::$entity ) {
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
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $product_tag_id
	 * @param       $product_tag
	 * @param       $update
	 *
	 * @return array
	 */
	public function process_checking( $product_tag_id, $product_tag, $update ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		if ( $product_tag->taxonomy !== self::$entity ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data($product_tag_id),
			$product_tag_id
		);

		return $this->send_data( $product_tag_id, self::$entity, self::$action, $fields );
	}

	/**
	 * @param $product_tag_id
	 *
	 * @return array|mixed|void
	 * Prepare product data, for send to AINSYS
	 */
	public function prepare_data( $product_tag_id ){

		$data = [];

		$product_tag = get_term_by('id', $product_tag_id, 'product_tag');

		$prepare = new Prepare_Product_Tag_Data($product_tag);
		$data = $prepare->prepare_data();

		return $data;

	}

}