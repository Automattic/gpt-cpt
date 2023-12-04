<?php
declare( strict_types=1 );

namespace GPT_CPT;

use Automattic\Jetpack\Connection\Client;

class Admin_Notices {
	public function initialize() {
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
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

	public static function show_admin_notices() {
		global $pagenow, $post;

		if ( $pagenow == 'post.php' && get_post_type( $post->ID ) === 'gpt_cpt' ) {
			$success = get_transient( 'gpt_cpt_success_' . $post->ID );
			if ( $success ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success ) . '</p></div>';
				delete_transient( 'gpt_cpt_success_' . $post->ID ); // Delete the transient to avoid repetitive notices
			}

			$error = get_transient( 'gpt_cpt_error_' . $post->ID );
			if ( $error ) {
				if ( ! is_string( $error ) ) {
					$error = $error->message ?? $error->get_error_message();
				}
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
				delete_transient( 'gpt_cpt_error_' . $post->ID ); // Delete the transient to avoid repetitive notices
			}
		}
	}
}
