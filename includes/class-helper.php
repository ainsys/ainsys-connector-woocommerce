<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\Plugin_Common;

class Helper {

	use Plugin_Common;

	public static function get_attribute_id_by_slug($slug){

		$slug = str_replace('pa_', '', $slug);

		$attributes = wc_get_attribute_taxonomies();

		$id = 0;

		foreach($attributes as $key => $attribute){

			if($attribute->attribute_name == $slug){
				$id = $attribute->attribute_id;
				break;
			}

		}

		return $id;

	}

	/**
	 * @param $term
	 * @param string $taxonomy
	 *
	 * @return false|int
	 */
	public static function get_term_id($term, $taxonomy = 'product_cat'){

		if(term_exists($term['slug'])){
			$term_id = get_term_by('slug', $term['slug'], $taxonomy)->term_id;
			return $term_id;
		}

		if(term_exists($term['name'])){
			$term_id = get_term_by('name', $term['name'], $taxonomy)->term_id;
			return $term_id;
		}

		return false;

	}

	/**
	 * @param $status
	 *
	 * @return bool
	 */
	public static function is_valide_order_status($status){

		$statuses = wc_get_order_statuses();

		if(array_key_exists($status, $statuses)){
			return true;
		}

		return false;

	}

	/**
	 * @param $status
	 *
	 * @return bool
	 */
	public static function is_valide_order_status($status){

		$statuses = wc_get_order_statuses();

		if(array_key_exists($status, $statuses)){
			return true;
		}

		return false;

	}

	/**
	 * @param $term
	 * @param $taxonomy
	 * @param $from
	 * @param $to
	 *
	 * @return int|string
	 * Format term value from - to
	 */
	public static function format_term_value($term_value, $taxonomy, $from, $to){

		$return = '';

		if(empty($term_value) || empty($taxonomy)){
			return $return;
		}

		if(strpos( $taxonomy, 'attribute_' ) !== false){
			$taxonomy = str_replace('attribute_', '', $taxonomy);
		}

		$term = get_term_by($from, $term_value, $taxonomy, $to);

		if(!$term){
			return $return;
		}

		switch ($to){
			case 'slug' :
				$return = $term->slug;
				break;

			case 'name' :
				$return = $term->name;
				break;

			case 'id' :
				$return = $term->term_id;
				break;

			case 'term_id' :
				$return = $term->term_id;

		}

		return $return;

	}

	public static function setup_product_type($type){

		$product = '';

		switch ( $type ) {
			case 'simple' :
				$product = new \WC_Product_Simple();
				break;

			case 'variable' :
				$product = new \WC_Product_Variable();
				break;

			case 'external' :
				$product = new \WC_Product_External();
				break;

			case 'grouped' :
				$new_product = new \WC_Product_Grouped();
				break;
			default:

				$product = '';
		}

		return $product;

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
	 * @param $term
	 * @param string $taxonomy
	 *
	 * @return array|int[]|\WP_Error
	 * Create new product_cat taxonomy term
	 */
	public static function add_term( $term, $taxonomy = 'product_cat') {


		$args = [];

		if(is_array($term)){

			$term_name = $term['name'];

			if(isset($term['slug'])){
				$args['slug'] = $term['slug'];
			}

			if(isset($term['description'])){
				$args['description'] = $term['description'];
			}

			if($term['parent'] != 0 && !empty($term['parent'])){
				$args['parent'] = $term['parent'];
			}
		}else{
			$term_name = $term;
		}

		return wp_insert_term( $term_name, $taxonomy, $args );
	}

	/**
	 * @param $term
	 * @param string $taxonomy
	 *
	 * @return array|object|\WP_Error|\WP_Term|null|false
	 * Update product_cat taxonomy term
	 */
	public static function update_term( $term, $taxonomy = 'product_cat', $get_term_by = 'name' ) {

		$term_id = Helper::get_term_id($term, $taxonomy);

		if(!is_array($term)){
			$term = get_term_by($get_term_by, $term, $taxonomy, ARRAY_A);

			if(is_wp_error($term)){
				return false;
			}

		}

		$args = [
			'name'        => $term['name'],
			'slug'        => $term['slug'],
		];

		if(isset($term['description'])){
			$args['description'] = $term['description'];
		}

		if($term['parent'] != 0 && !empty($term['parent'])){
			$args['parent'] = $term['parent'];
		}

		return wp_update_term(
			$term_id,
			$taxonomy,
			$args
		);
	}

	/**
	 * @param $attr_key
	 * @param $attribute
	 *
	 * @return int|\WP_Error
	 */
	public function create_attribute_taxonomy( $attr_key, $attribute ) {

		$name                 = str_replace( 'pa_', '', $attr_key );
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		/*$attribute_tax = register_taxonomy($attr_key, 'product', [
			'label' => $attribute['name'],
		]);*/

		$slug          = wc_sanitize_taxonomy_name( $name );
		$taxonomy_name = wc_attribute_taxonomy_name( $name );

		$attribute_name = wc_attribute_taxonomy_slug( $name );

		if ( ! in_array( $attribute_name, $attribute_taxonomies, true ) ) {
			$attribute_id = wc_create_attribute(
				[
//					'name'         => $name,
					'name'         => $attribute['attribute_label'],
					'slug'         => $attribute_name,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				]
			);
		}

		if(!is_wp_error($attribute_id)){

			register_taxonomy(
				$taxonomy_name,
				apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
				apply_filters( 'woocommerce_taxonomy_args_' . $taxonomy_name,
				               array(
					               'hierarchical' => true,
					               'show_ui'      => false,
					               'query_var'    => true,
					               'rewrite'      => false,
				               ) )
			);

		}

		//Clear caches
		delete_transient( 'wc_attribute_taxonomies' );

		return $attribute_id;
	}

	/**
	 * @param $attr_key
	 *
	 * @return bool
	 *
	 * Return answer, is attribute individual or based on taxonomy
	 */
	public function is_taxonomy_attribute( $attr_key ) {
		return strpos( $attr_key, 'pa_' ) !== false;
	}

	/**
	 * @param $attr_key
	 *
	 * @return bool
	 * Check if Attribute taxonomy already exist
	 */
	public function attribute_taxonomy_exist( $attr_key ) {

		$attributes = wc_get_attribute_taxonomies();
		$slugs      = wp_list_pluck( $attributes, 'attribute_name' );

		return in_array( str_replace( 'pa_', '', sanitize_title($attr_key) ), $slugs );

	}

	public static function format_term_id_to_name($term_id, $taxonomy){

		$term_name = '';

		$term = get_term_by('id', $term_id, $taxonomy);

		if(is_object($term)){
			$term_name = $term->name;
		}

		return $term_name;

	}

	/**
	 * @param $url
	 *
	 * @return bool
	 *
	 * Check by url if image exists in media library
	 */
	public static function get_attachment_id_by_url( $url ) {

		return attachment_url_to_postid($url);


		/*$dir = wp_upload_dir();

		if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?

			$file = basename( $url );

			$query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'value'   => $file,
						'compare' => 'LIKE',
						'key'     => '_wp_attachment_metadata',
					),
				)
			);

			$query = new \WP_Query( $query_args );

			if ( $query->have_posts() ) {
				return true;
			}
		}

		return false;*/
	}

	/**
	 * @param $image
	 *
	 * @return false|int|\WP_Error
	 *
	 * Upload images to Wordpress media gallery
	 */
	public static function upload_image_to_library( $image ) {

		if ( ! is_array( $image ) ) {
			return false;
		}

		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		$image_url = $image['file'] ?? $image['src'];

		// download to temp dir
		$temp_file = download_url( $image_url );

		if ( is_wp_error( $temp_file ) ) {
			return false;
		}

		// move the temp file into the uploads directory
		$file     = array(
			'name'     => basename( $image_url ),
			'type'     => mime_content_type( $temp_file ),
			'tmp_name' => $temp_file,
			'size'     => filesize( $temp_file ),
		);
		$sideload = wp_handle_sideload(
			$file,
			array(
				'test_form' => false
			)
		);

		if ( ! empty( $sideload['error'] ) ) {
			return false;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'guid'           => $sideload['url'],
				'post_mime_type' => $sideload['type'],
				'post_title'     => basename( $sideload['file'] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$sideload['file']
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return false;
		}

		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $sideload['file'] )
		);

		$update_meta = ( isset( $image['file'] ) ) ? false : Helper::update_image_metadata( $image );

		return $attachment_id;
	}

	/**
	 * @param array $image
	 *
	 * @return false|int|\WP_Error
	 *
	 * Update image metadata
	 */
	public static function update_image_metadata( array $image ) {
		if ( empty( $image ) || ! is_array( $image ) ) {
			return false;
		}

		$id = $image['id'];

		$attachment = get_post( $id );

		if ( ! $attachment ) {
			return false;
		}

		$update_data = [];

		if ( isset( $image['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $image['alt'] );
		}

		if ( isset( $image['caption'] ) ) {
			$update_data['post_excerpt'] = $image['caption'];
		}

		if ( isset( $image['description'] ) ) {
			$update_data['post_content'] = $image['description'];
		}

		if ( isset( $image['title'] ) ) {
			$update_data['post_title'] = $image['title'];
		}

		return wp_update_post( $update_data );
	}

}