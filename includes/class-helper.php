<?php

namespace Ainsys\Connector\Woocommerce;

class Helper {

	/**
	 * @param $url
	 *
	 * @return bool
	 *
	 * Check by url if image exists in media library
	 */
	public function check_image_exist($url){

		$dir = wp_upload_dir();

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

		return false;

	}

	public function upload_image_to_library($image){

		if(!is_array($image)){
			return false;
		}

		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		$image_url = $image['src'];

		// download to temp dir
		$temp_file = download_url( $image_url );

		if( is_wp_error( $temp_file ) ) {
			return false;
		}

		// move the temp file into the uploads directory
		$file = array(
			'name'     => basename( $image_url ),
			'type'     => mime_content_type( $temp_file ),
			'tmp_name' => $temp_file,
			'size'     => filesize( $temp_file ),
		);
		$sideload = wp_handle_sideload(
			$file,
			array(
				'test_form'   => false
			)
		);

		if( ! empty( $sideload[ 'error' ] ) ) {
			return false;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'guid'           => $sideload[ 'url' ],
				'post_mime_type' => $sideload[ 'type' ],
				'post_title'     => basename( $sideload[ 'file' ] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$sideload[ 'file' ]
		);

		if( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return false;
		}

		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $sideload[ 'file' ] )
		);

		$this->update_image_metadata($image);

		return $attachment_id;

	}

	public function update_image_metadata(array $image){

		if(empty($image) || !is_array($image)){
			return false;
		}

		$id = $image['id'];

		$attachment = get_post($id);

		if(!$attachment){
			return false;
		}

		$update_data = [];

		if(isset($image['alt'])){
			update_post_meta($id, '_wp_attachment_image_alt', $image['alt']);
		}

		if(isset($image['caption'])){
			$update_data['post_excerpt'] = $image['caption'];
		}

		if(isset($image['description'])){
			$update_data['post_content'] = $image['description'];
		}

		if(isset($image['title'])){
			$update_data['post_title'] = $image['title'];
		}

		return wp_update_post($update_data);

	}

}