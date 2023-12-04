<?php
declare( strict_types=1 );

namespace GPT_CPT;

class CPT {
	public function register() {
		add_action( 'init', array( $this, 'register_assistant_cpt' ) );

		// Customizations for the CPT editor.
		add_action( 'wp_editor_settings', array( $this, 'disable_media_buttons_for_assistants' ), 10, 1 );
		add_action( 'tiny_mce_before_init', array( $this, 'disable_text_formatting_for_assistants' ), 10, 1 );
		add_action( 'edit_form_after_title', array( $this, 'add_custom_title_above_editor' ) );
		add_filter( 'enter_title_here', array( $this, 'change_title_placeholder' ), 10, 2 );
	}

	public function register_assistant_cpt() {
		$assistant_labels = array(
			'name'                  => _x( 'GPT Assistants', 'Post type general name' ),
			'singular_name'         => _x( 'GPT GPT Assistant', 'Post type singular name' ),
			'menu_name'             => _x( 'GPT Assistants', 'Admin Menu text' ),
			'name_admin_bar'        => _x( 'GPT Assistant', 'Add New on Toolbar' ),
			'add_new'               => __( 'Add New GPT Assistant' ),
			'add_new_item'          => __( 'Add New GPT Assistant' ),
			'new_item'              => __( 'New GPT Assistant' ),
			'edit_item'             => __( 'Edit GPT Assistant' ),
			'view_item'             => __( 'View GPT Assistant' ),
			'view_items'            => __( 'View GPT Assistants' ),
			'all_items'             => __( 'All GPT Assistants' ),
			'search_items'          => __( 'Search GPT Assistants' ),
			'parent_item_colon'     => __( 'Parent GPT Assistants:' ),
			'not_found'             => __( 'No GPT Assistants found.' ),
			'not_found_in_trash'    => __( 'No GPT Assistants found in Trash.' ),
			'featured_image'        => _x( 'GPT Assistant Cover Image', 'Overrides the “Featured Image” phrase for this post type' ),
			'archives'              => _x( 'GPT Assistant archives', 'The post type archive label used in nav menus. Default “Post Archives”' ),
			'insert_into_item'      => _x( 'Insert into GPT Assistant', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post)' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this GPT Assistant', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post)' ),
			'filter_items_list'     => _x( 'Filter GPT Assistants list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”' ),
			'items_list_navigation' => _x( 'GPT Assistants list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”' ),
			'items_list'            => _x( 'GPT Assistants list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”' ),
		);

		$assistant_args = array(
			'labels'             => $assistant_labels,
			'description'        => 'GPT Assistants',
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => [ 'title', 'custom-fields', 'comments', 'revisions', 'editor' ],
			'taxonomies'         => [],
			'show_in_rest'       => false,
		);

		register_post_type( 'gpt_cpt', $assistant_args );
	}

	public function add_custom_title_above_editor() {
		global $post_type;
		if ( 'gpt_cpt' === $post_type ) {
			echo '<h2>Instructions</h2>';
		}
	}

	public function change_title_placeholder( $title_placeholder, $post ) {
		if ( 'gpt_cpt' === $post->post_type ) {
			$title_placeholder = 'GPT Assistant name';
		}
		return $title_placeholder;
	}

	/**
	 * Disables media buttons for the assistant post type.
	 *
	 * @param array $settings Editor settings.
	 * @return array Modified editor settings.
	 */
	public function disable_media_buttons_for_assistants( $settings ) {
		global $post_type;

		// Check if the current post type is your custom post type
		if ( 'gpt_cpt' === $post_type ) {
			$settings['media_buttons'] = false;
			$settings['tinymce'] = false;
			$settings['quicktags'] = false;
		}

		return $settings;
	}

	/**
	 * Disables text formatting tools for the assistant post type.
	 *
	 * @param array $init_array TinyMCE settings.
	 * @return array Modified TinyMCE settings.
	 */
	public function disable_text_formatting_for_assistants( $init_array ) {
		global $post_type;
		if ( 'gpt_cpt' === $post_type ) {
			$init_array['toolbar1'] = '';
			$init_array['toolbar2'] = '';
			$init_array['wp_shortcut_labels'] = '';
		}

		return $init_array;
	}
}
