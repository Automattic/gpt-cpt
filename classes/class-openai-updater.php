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
		if ( 'publish' === $post->post_status ) {
			$this->maybe_upload_files( $post_id, $post );
			$assistant_data = $this->prepare_assistant_data( $post_id, $post );

			if ( $assistant_id && $update ) {
				// $result = $this->handle_create_assistant( $post_id, $assistant_data );
				$result = $this->handle_modify_assistant( $assistant_id, $assistant_data );
			} else {
				$result = $this->handle_create_assistant( $post_id, $assistant_data );
			}
		} else {
			$this->remove_file_from_openai( $post_id );
			$result = $this->handle_delete_assistant( $post_id, $assistant_id );
		}

		if ( is_wp_error( $result ) ) {
			Admin_Notices::set_error_notice( $post_id, $result->get_error_message() );
			delete_post_meta( $post_id, 'assistant_id', $assistant_id );
		}

		$this->refresh_openai_assistant_data( $post_id );
	}

	public function request_wpcom( $endpoint, $method = 'GET', $body = null ) {
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			require_lib( 'wpcom-api-direct' );
			$query_string = is_array( $body ) ? http_build_query( $body ) : '';
			$args = array(
				'headers' => array(
					'Content-Type'    => 'application/json',
				),
				'method'  => $method,
				'url'     => JETPACK__WPCOM_JSON_API_BASE . '/wpcom/v2' . $endpoint . '?force=wpcom' . '&' . $query_string,
			);
			$response = \WPCOM_API_Direct::do_request( $args, $body );
		} else {
			$response = Jetpack_Client::wpcom_json_api_request_as_user(
				$endpoint . '?force=wpcom',
				'v2',
				array(
					'method'  => $method,
					'headers' => array( 'content-type' => 'application/json' ),
					'timeout' => 60,
				),
				$body,
				'wpcom'
			);
		}

		return $response;
	}

	private function remove_file_from_openai( $post_id ) {
		$file_ids = get_post_meta( $post_id, 'knowledge_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			return;
		}

		$deleted_file_ids = array();
		foreach ( $file_ids as $file_id ) {
			$file_delete = $this->request_wpcom(
				"/wpcom-ai/files/$file_id/delete",
				'POST',
				array(
					'file_id' => $file_id,
				)
			);

			if ( ! is_wp_error( $file_delete ) ) {
				$deleted_file_ids[] = $file_id;
			}
		}

		// Remove the file IDs
		foreach ( $deleted_file_ids as $deleted_file_id ) {
			$file_ids = array_diff( $file_ids, array( $deleted_file_id ) );
		}
		update_post_meta( $post_id, 'knowledge_file_ids', array() );
	}

	private function maybe_upload_files( $post_id, $post ) {
		if ( ! $post->post_status === 'publish' ) {
			return;
		}

		$file_path = Knowledge::get_knowledge_file_path( $post_id );
		$file_url = Knowledge::get_knowledge_file_url( $post_id );

		if ( ! $file_url || ! file_exists( $file_path ) ) {
			error_log( 'doesnnt exist' );
			return;
		}

		$file_upload = $this->request_wpcom(
			'/wpcom-ai/files',
			'POST',
			array(
				'file' => $file_url,
				'purpose' => 'assistants',
			)
		);

		if ( is_wp_error( $file_upload ) ) {
			Admin_Notices::set_error_notice( $post_id, 'Failed to upload knowledge file.' );
			return;
		}

		$response = wp_remote_retrieve_body( $file_upload );
		$response = json_decode( $response );

		if ( $response->error ) {
			Admin_Notices::set_error_notice( $post_id, 'Failed to upload knowledge file.' );
			return;
		}

		update_post_meta( $post_id, 'knowledge_file_url', $file_url );
		update_post_meta( $post_id, 'knowledge_file_ids', array( $response->id ) );
	}

	public function get_file_id_from_file_name( $file_name ) {
		$files = $this->list_uploaded_files();
		if ( is_wp_error( $files ) ) {
			return $files;
		}

		foreach ( $files as $file ) {
			if ( $file_name === $file->name ) {
				return $file->id;
			}
		}

		return false;
	}

	public function list_uploaded_files() {
		$result = $this->request_wpcom(
			'/wpcom-ai/files',
			'GET',
			array(
				'purpose' => 'assistants',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = wp_remote_retrieve_body( $result );
		$response = json_decode( $response );

		if ( $response->error ) {
			return new \WP_Error( 'failed-to-list-files', 'Failed to list uploaded files.' );
		}

		return $response->files;
	}

	private function handle_modify_assistant( $assistant_id, $assistant_data ) {
		$result = $this->request_wpcom(
			"/wpcom-ai/assistants/$assistant_id",
			'POST',
			array_merge(
				array( 'assistant_id' => $assistant_id ),
				$assistant_data,
			)
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
		$result = $this->request_wpcom( '/wpcom-ai/assistants', 'POST', $assistant_data );

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
			$result = $this->request_wpcom( "/wpcom-ai/assistants/$assistant_id/delete", 'POST' );
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

		$result = $this->request_wpcom( "/wpcom-ai/assistants/$assistant_id", 'GET' );
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
