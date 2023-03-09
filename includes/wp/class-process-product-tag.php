<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Prepare\Prepare_Taxonomies;

class Process_Product_Tag extends Prepare_Taxonomies implements Hooked {

	protected static string $entity = 'product_tag';


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_action( 'created_' . self::$entity, [ $this, 'process_create' ], 10, 1 );
		add_action( 'edited_' . self::$entity, [ $this, 'process_update' ], 10, 1 );
		add_action( 'delete_' . self::$entity, [ $this, 'process_delete' ], 10, 1 );

	}


	/**
	 * Sends new Product Category details to AINSYS
	 *
	 * @param $term_id
	 */
	public function process_create( $term_id ): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data( $term_id ),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );

	}


	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param $term_id
	 */
	public function process_update( $term_id ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $term_id ),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );
	}


	/**
	 * Sends delete post details to AINSYS
	 *
	 * @param $term_id
	 */
	public function process_delete( $term_id ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_delete_fields_' . self::$entity,
			$this->prepare_data( $term_id ),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );

	}


	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $term_id
	 * @param       $product_tag
	 *
	 * @return array
	 */
	public function process_checking( $term_id, $product_tag ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		if ( $product_tag->taxonomy !== self::$entity ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $term_id ),
			$term_id
		);

		return $this->send_data( $term_id, self::$entity, self::$action, $fields );
	}


	/**
	 * @param $term_id
	 *
	 * @return array
	 * Prepare product data, for send to AINSYS
	 */
	protected function prepare_data( $term_id ): array {

		return $this->get_prepare_data_tax( $term_id, self::$entity );

	}

}