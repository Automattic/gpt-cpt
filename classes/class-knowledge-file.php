<?php
declare( strict_types=1 );

namespace GPT_CPT;
use Jetpack_Options;

class Knowledge {
	public function gpt_mime_types( $mimes ) {
		$mimes['json'] = 'application/json';
		return $mimes;
	}

	public static function get_knowledge_file_dir() {
		$upload_dir = wp_upload_dir();
		$dir = $upload_dir['basedir'] . '/knowledge';
		return $dir;
	}

	public static function get_knowledge_files() {
		$upload_dir = wp_upload_dir();
		$folder = $upload_dir['basedir'] . '/knowledge';
		$files = list_files( $folder, 1 );
		$knowledge_files = array();
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$filename = basename( $file );
				$knowledge_files[] = $filename;
			}
		}
		return $knowledge_files;
	}

	public static function get_knowledge_file_name( $post_id ) {
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			$blog_id = get_current_blog_id();
		} else {
			$blog_id = Jetpack_Options::get_option( 'id' );
		}
		return 'knowledge-' . $blog_id . '-' . $post_id . '.json';
	}

	public static function get_knowledge_file_base_url() {
		$upload_dir = wp_upload_dir();
		$knowledge = $upload_dir['baseurl'] . '/knowledge';
		return $knowledge;
	}

	public function generate_knowledge_json( $post_types ) {
		// Fetch posts from the selected post types
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$posts = get_posts( $args );

		// Filter the posts to include only those with content longer than 5 words
		$filtered_posts = array_filter(
			$posts,
			function ( $post ) {
				$content = strip_tags( strip_shortcodes( $post->post_content ) );
				$word_count = str_word_count( $content );
				return $word_count > 5;
			}
		);

		$data = array_map(
			function ( $post ) {
				$content = strip_tags( strip_shortcodes( $post->post_content ) );
				return array(
					'title'   => $post->post_title,
					'url'     => get_permalink( $post->ID ),
					'content' => $content,
					'type'    => $post->post_type,
				);
			},
			$filtered_posts
		);

		return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	public function knowledge_upload_dir( $dirs ) {
		$dirs['subdir'] = '/knowledge';
		$dirs['path'] = $dirs['basedir'] . '/knowledge';
		$dirs['url'] = $dirs['baseurl'] . '/knowledge';
		return $dirs;
	}

	public function save_json_to_file( $json_data, $post_id ) {
		$file_path = tempnam( sys_get_temp_dir(), 'json' );
		file_put_contents( $file_path, $json_data );
		if ( ! file_exists( $file_path ) ) {
			Admin_Notices::set_error_notice( $post_id, 'Knowledge file could not be saved.' );
			return false;
		}

		// Temporarily change the upload directory
		add_filter( 'upload_dir', array( $this, 'knowledge_upload_dir' ) );
		add_filter( 'upload_mimes', array( $this, 'gpt_mime_types' ) );

		$file = array(
			'name'     => self::get_knowledge_file_name( $post_id ), // New name
			'type'     => 'application/json',
			'tmp_name' => $file_path, // Path to the file
			'error'    => 0,
			'size'     => filesize( $file_path ),
		);

		// Use wp_handle_upload to handle the file upload
		$overrides = array( 'test_form' => false, 'test_type' => true, 'action' => 'generated_file', );
		$upload = wp_handle_upload( $file, $overrides );

		remove_filter( 'upload_dir', array( $this, 'change_upload_dir' ) );

		if ( isset( $upload[ 'error' ] ) ) {
			Admin_Notices::set_error_notice( $post_id, 'Knowledge file could not be uploaded.' );
			return $upload;
		}

		// Prepare an array of post data for the attachment
		$file_url = $upload['url'];
		$attachment = array(
			'guid'           => $file_url,
			'post_mime_type' => 'application/json',
			'post_title'     => basename( $upload['file'] ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		// Insert the attachment
		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
		if ( $attach_id <= 0 ) {
			Admin_Notices::set_error_notice( $post_id, 'Failed to upload file to Media Library.' );
			return false;
		}
		Admin_Notices::set_success_notice( $post_id, 'Knowledge file saved successfully.' );
		return $file_url;
	}
}
