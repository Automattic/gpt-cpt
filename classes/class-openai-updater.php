<?php
declare( strict_types=1 );

namespace GPT_CPT;

use Automattic\Jetpack\Connection\Client as Jetpack_Client;

class OpenAI_Updater {
	private $assistant;

	public function initialize() {
		add_action( 'save_post_gpt_cpt', array( $this, 'save_post' ), 20, 3 );
	}

	/**
	 * Handles creating, modifying, or deleting OpenAI assistants from a post.
	 */
	public function save_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$assistant_id = get_post_meta( $post_id, 'assistant_id', true );
		// $this->maybe_upload_files( $post_id, $post );
		$assistant_data = $this->prepare_assistant_data( $post_id, $post );
		if ( 'publish' === $post->post_status ) {
			if ( $assistant_id && $update ) {
				$result = $this->handle_modify_assistant( $assistant_id, $assistant_data );
			} else {
				$result = $this->handle_create_assistant( $post_id, $assistant_data );
			}
			// $this->attach_assistant_file( $post_id, $assistant_id );
		} else {
			// $this->remove_file_from_openai( $post_id );
			$result = $this->handle_delete_assistant( $post_id, $assistant_id );
		}

		if ( is_wp_error( $result ) ) {
			Admin_Notices::set_error_notice( $post_id, $result->get_error_message() );
		}

		$this->refresh_openai_assistant_data( $post_id );
	}

	private function remove_file_from_openai( $post_id ) {
		$file_ids = get_post_meta( $post_id, 'knowledge_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			return;
		}
		foreach ( $file_ids as $file_id ) {
			$this->assistant->request_file_delete( $file_id );
		}

		// Remove the file IDs
		delete_post_meta( $post_id, 'knowledge_file_ids' );
	}

	private function maybe_upload_files( $post_id, $post ) {
		if ( ! $post->post_status === 'publish' ) {
			return;
		}
		// If one with the same name already exists, delete it first
		$file_path = get_post_meta( $post_id, 'knowledge_file_path', true );
		$file_name = basename( $file_path );
		$file_id = $this->assistant->get_file_id_from_file_name( $file_name );
		if ( $file_id ) {
			$this->assistant->request_file_delete( $file_id );
		}
		$file_upload = $this->assistant->request_file_upload( $file_path, $file_name );
		if ( is_wp_error( $file_upload ) ) {
			Admin_Notices::set_error_notice( $post_id, 'Failed to upload knowledge file.' );
			return;
		}

		if ( $file_upload ) {
			$file_id = $this->assistant->get_file_id_from_file_name( $file_name );
			update_post_meta( $post_id, 'knowledge_file_ids', array( $file_id ) );
		}
	}

	private function attach_assistant_file( $post_id, $assistant_id ) {
		// Attach the knowledge file to the assistant
		$file_ids = get_post_meta( $post_id, 'knowledge_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			return;
		}
		if ( count( $file_ids ) === 0 ) {
			return;
		}

		foreach ( $file_ids as $file_id ) {
			$attach_request = $this->assistant->request_assistant_add_file( $assistant_id, $file_id );
			if ( is_wp_error( $attach_request ) ) {
				Admin_Notices::set_error_notice( $post_id, 'Failed to attach knowledge file' );
			}
		}
	}

	private function handle_modify_assistant( $assistant_id, $assistant_data ) {
		$result = Jetpack_Client::wpcom_json_api_request_as_user(
			"/wpcom-ai/assistants/$assistant_id?force=wpcom",
			'v2',
			array(
				'method'  => 'POST',
				'headers' => array( 'content-type' => 'application/json' ),
			),
			array_merge(
				array( 'assistant_id'   => $assistant_id, ),
				$assistant_data,
			),
			'wpcom'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = wp_remote_retrieve_body( $result );
		$response = json_decode( $response );
		if ( empty( $response->id ) ) {
			return new \WP_Error( 'no-assistant-id', 'No assistant ID returned from OpenAI.' );
		}

		return true;
	}

	private function handle_create_assistant( $post_id, array $assistant_data ) {
		$result = Jetpack_Client::wpcom_json_api_request_as_user(
			'/wpcom-ai/assistants?force=wpcom',
			'v2',
			array(
				'method'  => 'POST',
				'headers' => array( 'content-type' => 'application/json' ),
			),
			$assistant_data,
			'wpcom'
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = wp_remote_retrieve_body( $result );
		$response = json_decode( $response );
		if ( empty( $response->id ) ) {
			return new \WP_Error( 'no-assistant-id', 'No assistant ID returned from OpenAI.' );
		}
		update_post_meta( $post_id, 'assistant_id', $response->id );
		return true;
	}

	private function handle_delete_assistant( $post_id, $assistant_id ) {
		if ( $assistant_id ) {
			$result = Jetpack_Client::wpcom_json_api_request_as_user(
				"/wpcom-ai/assistants/$assistant_id/delete?force=wpcom",
				'v2',
				array(
					'method'  => 'POST',
					'headers' => array( 'content-type' => 'application/json' ),
				),
				array(
					'assistant_id' => $assistant_id,
				),
				'wpcom'
			);
			$response = wp_remote_retrieve_body( $result );
			$response = json_decode( $response );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! isset( $response->deleted ) ) {
				return new \WP_Error( 'assistant-not-deleted', 'Assistant was not deleted.' );
			}

			delete_post_meta( $post_id, 'assistant_id' );
			return true;
		}

		return false;
	}

	public function refresh_openai_assistant_data( $post_id ) {
		$assistant_id = get_post_meta( $post_id, 'assistant_id', true );
		if ( ! $assistant_id ) {
			return;
		}

		$result = Jetpack_Client::wpcom_json_api_request_as_user(
			"/wpcom-ai/assistants/$assistant_id?force=wpcom",
			'v2',
			array(
				'method'  => 'GET',
				'headers' => array( 'content-type' => 'application/json' ),
			),
			null,
			'wpcom'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = wp_remote_retrieve_body( $result );
		update_post_meta( $post_id, 'assistant_data', $response );
		$response = json_decode( $response );
		if ( empty( $response->id ) ) {
			return new \WP_Error( 'no-assistant-id', 'No assistant ID returned from OpenAI.' );
		}
	}

	private function prepare_assistant_data( $post_id, $post ) {
		$tools = get_post_meta( $post_id, 'assistant_tools', true );
		$file_ids = get_post_meta( $post_id, 'knowledge_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			$file_ids = array();
		}
		return array(
			'name' => $post->post_title,
			'description' => get_post_meta( $post_id, 'assistant_description', true ),
			'instructions' => $post->post_content,
			'tools' => [
				[ 'type' => $tools ], // TODO: Add support for multiple tools
			],
			'file_ids' => $file_ids,
		);
	}
}
