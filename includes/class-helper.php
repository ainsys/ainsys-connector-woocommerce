<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\Plugin_Common;
use WC_Product_External;
use WC_Product_Grouped;
use WC_Product_Simple;
use WC_Product_Variable;

class Helper {

	use Plugin_Common;

	/**
	 * @param $slug
	 *
	 * @return int
	 */
	public static function get_attribute_id_by_slug( $slug ): int {

		$slug = str_replace( 'pa_', '', $slug );

		$attributes = wc_get_attribute_taxonomies();

		$id = 0;

		foreach ( $attributes as $key => $attribute ) {

			if ( $attribute->attribute_name === $slug ) {
				$id = $attribute->attribute_id;
				break;
			}

		}

		return $id;

	}


	/**
	 * @param         $term
	 * @param  string $taxonomy
	 *
	 * @return int
	 */
	public static function get_term_id( $term, string $taxonomy = 'product_cat' ): int {

		if ( term_exists( $term['slug'] ) ) {
			return get_term_by( 'slug', $term['slug'], $taxonomy )->term_id;
		}

		if ( term_exists( $term['name'] ) ) {
			return get_term_by( 'name', $term['name'], $taxonomy )->term_id;
		}

		return 0;

	}


	/**
	 * @param $status
	 *
	 * @return bool
	 */
	public static function is_valid_order_status( $status ): bool {

		$statuses = wc_get_order_statuses();

		if ( array_key_exists( $status, $statuses ) ) {
			return true;
		}

		return false;

	}


	/**
	 * Format term value from - to
	 *
	 * @param $term_value
	 * @param $taxonomy
	 * @param $from
	 * @param $to
	 *
	 * @return int|string
	 */
	public static function format_term_value( $term_value, $taxonomy, $from, $to ) {


		if ( empty( $term_value ) || empty( $taxonomy ) ) {
			return '';
		}

		if ( strpos( $taxonomy, 'attribute_' ) !== false ) {
			$taxonomy = str_replace( 'attribute_', '', $taxonomy );
		}

		$term = get_term_by( $from, $term_value, $taxonomy, $to );

		if ( ! $term ) {
			return '';
		}

		switch ( $to ) {
			case 'slug' :
				$return = $term->slug;
				break;

			case 'name' :
				$return = $term->name;
				break;

			case 'term_id':
			case 'id' :
				$return = $term->term_id;
				break;

			default:
				$return = '';
				break;

		}

		return $return;

	}


	/**
	 * @param $type
	 *
	 * @return string|\WC_Product_External|\WC_Product_Grouped|\WC_Product_Simple|\WC_Product_Variable
	 *
	 * @todo Не понял зачем этот метод если он вызывается один раз, занести его туда где вызывается?
	 */
	public static function setup_product_type( $type ) {

		switch ( $type ) {
			case 'simple' :
				$product = new WC_Product_Simple();
				break;

			case 'variable' :
				$product = new WC_Product_Variable();
				break;

			case 'external' :
				$product = new WC_Product_External();
				break;

			case 'grouped' :
				$product = new WC_Product_Grouped();
				break;
			default:

				$product = '';
				break;
		}

		return $product;

	}


	/**
	 * Create new product_cat taxonomy term
	 *
	 * @param         $term
	 * @param  string $taxonomy
	 *
	 * @return array|int[]|\WP_Error
	 */
	public static function add_term( $term, string $taxonomy = 'product_cat' ) {


		$args = [];

		if ( is_array( $term ) ) {

			$term_name = $term['name'];

			if ( isset( $term['slug'] ) ) {
				$args['slug'] = $term['slug'];
			}

			if ( isset( $term['description'] ) ) {
				$args['description'] = $term['description'];
			}

			if ( $term['parent'] !== 0 && ! empty( $term['parent'] ) ) {
				$args['parent'] = $term['parent'];
			}
		} else {
			$term_name = $term;
		}

		return wp_insert_term( $term_name, $taxonomy, $args );
	}


	/**
	 * Update product_cat taxonomy term
	 *
	 * @param         $term
	 * @param  string $taxonomy
	 * @param  string $get_term_by
	 *
	 * @return array|object|\WP_Error|\WP_Term|null|false
	 */
	public static function update_term( $term, string $taxonomy = 'product_cat', string $get_term_by = 'name' ) {

		$term_id = self::get_term_id( $term, $taxonomy );

		if ( ! is_array( $term ) ) {
			$term = get_term_by( $get_term_by, $term, $taxonomy, ARRAY_A );

			if ( is_wp_error( $term ) ) {
				return false;
			}

		}

		$args = [
			'name' => $term['name'],
			'slug' => $term['slug'],
		];

		if ( isset( $term['description'] ) ) {
			$args['description'] = $term['description'];
		}

		if ( $term['parent'] !== 0 && ! empty( $term['parent'] ) ) {
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
	 *
	 * @todo почему метод не статический?
	 * @todo выхывается один раз, точно тут нужен? Может занечти туда где вызывается?
	 */
	public function create_attribute_taxonomy( $attr_key, $attribute ) {

		$name                 = str_replace( 'pa_', '', $attr_key );
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		$slug          = wc_sanitize_taxonomy_name( $name );
		$taxonomy_name = wc_attribute_taxonomy_name( $name );

		$attribute_name = wc_attribute_taxonomy_slug( $name );

		if ( ! in_array( $attribute_name, $attribute_taxonomies, true ) ) {
			$attribute_id = wc_create_attribute(
				[
					'name'         => $attribute['attribute_label'],
					'slug'         => $attribute_name,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				]
			);
		}

		if ( ! is_wp_error( $attribute_id ) ) {

			register_taxonomy(
				$taxonomy_name,
				apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, [ 'product' ] ),
				apply_filters(
					'woocommerce_taxonomy_args_' . $taxonomy_name,
					[
						'hierarchical' => true,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					]
				)
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
	 * Check if Attribute taxonomy already exist
	 *
	 * @param $attr_key
	 *
	 * @return bool
	 */
	public function attribute_taxonomy_exist( $attr_key ): bool {

		$attributes = wc_get_attribute_taxonomies();
		$slugs      = wp_list_pluck( $attributes, 'attribute_name' );

		return in_array( str_replace( 'pa_', '', sanitize_title( $attr_key ) ), $slugs, true );

	}


	public static function format_term_id_to_name( $term_id, $taxonomy ) {

		$term_name = '';

		$term = get_term_by( 'id', $term_id, $taxonomy );

		if ( is_object( $term ) ) {
			$term_name = $term->name;
		}

		return $term_name;

	}


	/**
	 * Check by url if image exists in media library
	 *
	 * @param $url
	 *
	 * @return int
	 *

	 */
	public static function get_attachment_id_by_url( $url ): int {

		return attachment_url_to_postid( $url );
	}


	/**
	 * Upload images to WordPress media gallery
	 *
	 * @param $image
	 *
	 * @return false|int|\WP_Error
	 *
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
		$file = [
			'name'     => basename( $image_url ),
			'type'     => mime_content_type( $temp_file ),
			'tmp_name' => $temp_file,
			'size'     => filesize( $temp_file ),
		];

		$sideload = wp_handle_sideload(
			$file,
			[
				'test_form' => false,
			]
		);

		if ( ! empty( $sideload['error'] ) ) {
			return false;
		}

		$attachment_id = wp_insert_attachment(
			[
				'guid'           => $sideload['url'],
				'post_mime_type' => $sideload['type'],
				'post_title'     => basename( $sideload['file'] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			],
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

		$update_meta = ( isset( $image['file'] ) ) ? false : self::update_image_metadata( $image );

		return $attachment_id;
	}


	/**
	 * Update image metadata
	 *
	 * @param  array $image
	 *
	 * @return int|\WP_Error
	 *
	 */
	public static function update_image_metadata( array $image ) {

		if ( empty( $image ) ) {
			return 0;
		}

		$id = $image['id'];

		$attachment = get_post( $id );

		if ( ! $attachment ) {
			return 0;
		}

		$update_data = [];

		if ( isset( $image['alt'] ) ) {
			$update_data['meta_input'] = [
				'_wp_attachment_image_alt' => $image['alt'],
			];
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