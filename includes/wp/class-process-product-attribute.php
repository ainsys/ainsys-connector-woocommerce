<?php

namespace Ainsys\Connector\Woocommerce\WP;

use Ainsys\Connector\Master\Conditions;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Woocommerce\Prepare_Product_Attribute_Data;
use Ainsys\Connector\Woocommerce\Prepare_Product_Cat_Data;

class Process_Product_Attribute extends Process implements Hooked{

	protected static string $entity = 'product_attribute';

	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_action('create_term', [$this, 'process_create'], 10, 4);
		add_action('delete_term', [$this, 'process_delete'], 10, 5);
		add_action('edited_term', [$this, 'process_update'], 10, 4);
		add_action('woocommerce_attribute_added', [$this, 'attribute_created'], 10, 2);

	}

	public function attribute_created($id, $data){

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data($data),
			$id, $data
		);

		$this->send_data( $id, self::$entity, self::$action, $fields );

	}

	/**
	 * @param $term_id
	 * @param $tt_id
	 * @param $args
	 *
	 * Sends updated Product Cat details to AINSYS.
	 */
	public function process_update( $term_id, $tt_id, $taxonomy, $args ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if(wc_attribute_taxonomy_id_by_name($taxonomy) === 0){
			return;
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data($term_id, $taxonomy),
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
	public function process_delete( $term, $tt_id, $taxonomy, $deleted_term, $object_ids ): void {

		self::$action = 'DELETE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if(wc_attribute_taxonomy_id_by_name($taxonomy) === 0){
			return;
		}

		$fields = apply_filters(
			'ainsys_process_delete_fields_' . self::$entity,
			$this->prepare_data( $term, $taxonomy ),
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
	public function process_create( $term_id, $tt_id, $taxonomy, $args ): void {

		self::$action = 'CREATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		if(wc_attribute_taxonomy_id_by_name($taxonomy) === 0){
			Logger::save([
				             'object_id'       => 0,
				             'entity'          => self::$entity,
				             'request_action'  => self::$action,
				             'request_type'    => 'outgoing',
				             'request_data'    => serialize( [$term_id, $tt_id, $taxonomy] ),
				             'error'           => 1,
			             ]);
			return;
		}

		$fields = apply_filters(
			'ainsys_process_create_fields_' . self::$entity,
			$this->prepare_data($term_id, $taxonomy),
			$term_id
		);

		$this->send_data( $term_id, self::$entity, self::$action, $fields );

	}

	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param       $attribute_id
	 * @param       $attribute
	 * @param       $update
	 *
	 * @return array
	 */
	public function process_checking( $attribute_id, $attribute, $update ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		if ( !array_key_exists('attribute_id', $attribute) ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data($attribute),
			$attribute_id
		);

		return $this->send_data( $attribute_id, self::$entity, self::$action, $fields );

	}

	/**
	 * @param $attribute
	 *
	 * @return array|mixed|void
	 * Prepare product data, for send to AINSYS
	 */
	public function prepare_data( $attribute, $taxonomy = ''){

		$data = [];

		if(is_int($attribute)){

			$attributes = wc_get_attribute_taxonomies();

			foreach($attributes as $attr_key => $single_attribute){

				if($single_attribute->attribute_name == wc_attribute_taxonomy_slug($taxonomy)){
					$attribute = $single_attribute;
					break;
				}

			}

		}

		$prepare = new Prepare_Product_Attribute_Data($attribute);
		$data = $prepare->prepare_data();

		return $data;

	}

}