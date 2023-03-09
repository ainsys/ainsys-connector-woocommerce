<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Woocommerce\WP\Prepare\Prepare_Product_Attribute;

class Process_Product_Attribute extends Process implements Hooked {

	protected static string $entity = 'product_attribute';


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_action( 'create_term', [ $this, 'process_create' ], 10, 4 );
		add_action( 'edited_term', [ $this, 'process_update' ], 10, 4 );
		add_action( 'delete_term', [ $this, 'process_delete' ], 10, 5 );

		add_action( 'woocommerce_attribute_added', [ $this, 'attribute_create' ], 10, 2 );
		add_action( 'woocommerce_attribute_updated', [ $this, 'attribute_update' ], 10, 2 );
		add_action( 'woocommerce_attribute_deleted', [ $this, 'attribute_delete' ], 10, 3 );

	}


	/**
	 * Sends new Product Category details to AINSYS
	 *
	 * @param $term_id
	 * @param $tt_id
	 * @param $taxonomy
	 * @param $args
	 */
	public function process_create( $term_id, $tt_id, $taxonomy, $args ): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if ( wc_attribute_taxonomy_id_by_name( $taxonomy ) === 0 ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data( $term_id, $taxonomy ),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );

	}


	/**
	 * Sends updated Product Cat details to AINSYS.
	 *
	 * @param $term_id
	 * @param $tt_id
	 * @param $taxonomy
	 * @param $args
	 */
	public function process_update( $term_id, $tt_id, $taxonomy, $args ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if ( wc_attribute_taxonomy_id_by_name( $taxonomy ) === 0 ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $term_id, $taxonomy ),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );
	}


	/**
	 * Sends delete post details to AINSYS
	 *
	 * @param $term
	 * @param $tt_id
	 * @param $taxonomy
	 * @param $deleted_term
	 * @param $object_ids
	 */
	public function process_delete( $term, $tt_id, $taxonomy, $deleted_term, $object_ids ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if ( wc_attribute_taxonomy_id_by_name( $taxonomy ) === 0 ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_delete_fields_' . self::$entity,
			$this->prepare_data( $term, $taxonomy ),
			$term
		);

		$this->send_data( $term, self::$entity, self::$action, $fields );

	}


	public function attribute_create( $id, $data ): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data( $data ),
			$id, $data
		);

		$this->send_data( $id, self::$entity, self::$action, $fields );

	}


	public function attribute_update( $id, $data ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $data ),
			$id
		);

		$this->send_data( $id, self::$entity, self::$action, $fields );
	}


	public function attribute_delete( $id, $attribute_name, $taxonomy ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $id, $taxonomy ),
			$id
		);

		$this->send_data( $id, self::$entity, self::$action, $fields );
	}


	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $attribute_id
	 * @param       $attribute
	 *
	 * @return array
	 */
	public function process_checking( $attribute_id, $attribute ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $attribute ),
			$attribute_id
		);

		return $this->send_data( $attribute_id, self::$entity, self::$action, $fields );

	}


	/**
	 * Prepare product data, for send to AINSYS
	 *
	 * @param         $attribute
	 * @param  string $taxonomy
	 *
	 * @return array
	 */
	public function prepare_data( $attribute, string $taxonomy = '' ): array {

		if ( is_int( $attribute ) ) {

			$attributes = wc_get_attribute_taxonomies();

			foreach ( $attributes as $attr_key => $single_attribute ) {
				if ( $single_attribute->attribute_name === wc_attribute_taxonomy_slug( $taxonomy ) ) {
					$attribute = $single_attribute;
					break;
				}

			}

		}

		return ( new Prepare_Product_Attribute( $attribute ) )->prepare_data();

	}

}