<?php
declare( strict_types=1 );

namespace GPT_CPT;

class Admin_Notices {
	public function initialize() {
		add_action( 'admin_notices', array( $this, 'custom_admin_notices' ) );
		add_filter( 'post_updated_messages', array( $this, 'custom_post_updated_notices' ) );
	}

	public static function set_success_notice( $post_id, $message ) {
		set_transient( 'gpt_cpt_success_' . $post_id, $message, 60 );
	}

	public static function set_error_notice( $post_id, $message ) {
		set_transient( 'gpt_cpt_error_' . $post_id, $message, 60 );
	}

	public function custom_post_updated_notices( $messages ) {
		$post = get_post();
		$post_id = $post->ID;
		$assistant_id = get_post_meta( $post_id, 'assistant_id', true );
		$assistant_url_html = '';
		if ( $assistant_id ) {
			$assistant_url = sprintf( 'https://platform.openai.com/playground?mode=assistant&assistant=%s', $assistant_id );
			$assistant_url_html = ' <a href="' . esc_url( $assistant_url ) . '" target="_blank">View Assistant</a>';
		}

		$preview_post_link_html = '';
		$scheduled_post_link_html = '';
		$scheduled_date = '';

		$messages['gpt_cpt'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Assistant updated.' ) . $assistant_url_html,
			2  => __( 'Custom field updated.' ),
			3  => __( 'Custom field deleted.' ),
			4  => __( 'Assistant updated.' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Assistant restored to revision from %s.' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Assistant published.' ) . $assistant_url_html,
			7  => __( 'Assistant saved.' ),
			8  => __( 'Assistant submitted.' ) . $preview_post_link_html,
			9  => sprintf( __( 'Assistant scheduled for: %s.' ), '<strong>' . $scheduled_date . '</strong>' ) . $scheduled_post_link_html,
			10 => __( 'Assistant draft updated.' ) . $preview_post_link_html,
		);
		return $messages;
	}

	/**
	 * Shows a notice if Jetpack is not installed or connected.
	 */
	public static function show_jetpack_notice() {
		echo '<div class="notice notice-error"><p>Jetpack is not connected. Please activate and connect Jetpack to use the GPT CPT plugin.</p></div>';
		return;
	}

	public static function show_error_notice( $message ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		return;
	}

	public static function show_success_notice( $message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		return;
	}

	public static function custom_admin_notices() {
		global $pagenow, $post;

		if ( $pagenow == 'post.php' && get_post_type( $post->ID ) === 'gpt_cpt' ) {
			$error = get_transient( 'gpt_cpt_error_' . $post->ID );
			if ( $error ) {
				if ( ! is_string( $error ) ) {
					$error = $error->message ?? $error->get_error_message();
				}
				self::show_error_notice( $error );
				delete_transient( 'gpt_cpt_error_' . $post->ID ); // Delete the transient to avoid repetitive notices
			}
		}
	}
}
