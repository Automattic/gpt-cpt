<?php
declare( strict_types=1 );

namespace GPT_CPT;

class Knowledge {

	public static function get_knowledge_file_path( $post_id ) {
		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['path'] . '/knowledge-' . get_current_blog_id() . '-' . $post_id . '.json';
		return $file_path;
	}

	public static function get_knowledge_file_name( $post_id ) {
		$knowledge_file = self::get_knowledge_file_path( $post_id );
		if ( ! file_exists( $knowledge_file ) ) {
			return false;
		}
		return basename( $knowledge_file );
	}

	public function generate_knowledge_json( $post_types ) {
		// Fetch posts from the selected post types
		$args = array(
			'post_type'      => $post_types,
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


	public function save_json_to_file( $json_data, $post_id ) {
		$file_path = $this::get_knowledge_file_path( $post_id );
		file_put_contents( $file_path, $json_data );
		if ( ! file_exists( $file_path ) ) {
			Admin_Notices::set_error_notice( $post_id, 'Knowledge file could not be saved.' );
			return false;
		}
		Admin_Notices::set_success_notice( $post_id, 'Knowledge file saved successfully.' );
		return $file_path;
	}
}
