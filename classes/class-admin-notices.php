<?php
declare( strict_types=1 );

namespace GPT_CPT;

class Admin_Notices {
	public function initialize() {
		add_action( 'admin_notices', array( $this, 'custom_admin_notices' ) );
	}

	public static function get_openai_view_assistant_url( $assistant_id ) {
		$openai_assistant_url = 'https://platform.openai.com/playground?mode=assistant&assistant=' . esc_url( $assistant_id );
		return $openai_assistant_url;
	}

	public static function set_success_notice( $post_id, $message ) {
		set_transient( 'gpt_cpt_success_' . $post_id, $message, 60 );
	}

	public static function set_error_notice( $post_id, $message ) {
		set_transient( 'gpt_cpt_error_' . $post_id, $message, 60 );
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

	public static function show_success_notice( $message, $assistant_id = false ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '';
		if ( $assistant_id ) {
			$assistant_url = sprintf( 'https://platform.openai.com/playground?mode=assistant&assistant=%s', $assistant_id );
			echo' <a href="' . esc_url( $assistant_url ) . '" target="_blank">View Assistant</a>';
		}
		echo '</p></div>';
		return;
	}

	public static function custom_admin_notices() {
		global $pagenow, $post;

		if ( $pagenow == 'post.php' && get_post_type( $post->ID ) === 'gpt_cpt' ) {
			$error = get_transient( 'gpt_cpt_error_' . $post->ID );
			$success = get_transient( 'gpt_cpt_success_' . $post->ID );
			$assistant_id = get_post_meta( $post->ID, 'assistant_id', true );
			if ( $success ) {
				self::show_success_notice( $success, $assistant_id );
				delete_transient( 'gpt_cpt_success_' . $post->ID ); // Delete the transient to avoid repetitive notices
			}
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
