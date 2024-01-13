<?php
declare( strict_types=1 );

namespace GPT_CPT;

use Automattic\Jetpack\Connection\Client as Jetpack_Client;

/**
 * Chat management class.
 * Handles chats for assistants in the comments of the post.
 */
class Chat_Manager {
	public $openai_updater;

	public function initialize() {
		add_action( 'comment_post', array( $this, 'after_comment_posted' ), 10, 2 );
		$this->openai_updater = new OpenAI_Updater();
	}

	/**
	 * Handles chatting via comments.
	 */
	public function after_comment_posted( $comment_id, $comment_approved ) {
		$comment = get_comment( $comment_id );

		// Don't respond to itself :)
		if ( 'ai_response' === $comment->comment_type ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( 'gpt_cpt' !== $post->post_type ) {
			return;
		}

		$assistant_id = get_post_meta( $post->ID, 'assistant_id', true );
		if ( empty( $assistant_id ) ) {
			return;
		}

		// If this is a top-level comment, create a new thread.
		if ( $comment->comment_parent == 0 ) {
			$thread = $this->create_new_thread( $comment->comment_content );
			$thread_id = $thread->id;
			if ( isset( $thread->id ) ) {
				update_comment_meta( $comment_id, 'thread_id', $thread->id );
			}
		} else {
			$thread_id = $this->get_parent_comment_meta( $comment_id, 'thread_id' );
		}

		if ( ! $thread_id ) {
			error_log( 'no thread id' );
			return;
		}

		// Run the thread
		$run_thread = $this->run_thread( $thread_id, $assistant_id );

		// Poll the thread
		$run_id = $run_thread->id;
		$poll_run = $this->poll_run_for_completion( $thread_id, $run_id, 30 );

		// If the run is complete, get the answer
		if ( 'completed' !== $poll_run ) {
			error_log( "error in poll run $poll_run" );
			return;
		}

		// Get the answer
		$thread_messages = $this->get_thread_messages( $thread_id );
		$bot_response = $this->get_bot_response( $thread_messages );

		if ( ! $bot_response ) {
			error_log( 'no bot response' );
			return;
		}

		// $endpoint = $chat_id ? "/odie/chat/jetpack-gpt-assistant/$chat_id?force=wpcom" : '/odie/chat/jetpack-gpt-assistant?force=wpcom';
		// $result = Jetpack_Client::wpcom_json_api_request_as_user(
		// 	$endpoint,
		// 	'v2',
		// 	array(
		// 		'method'  => 'POST',
		// 		'headers' => array( 'content-type' => 'application/json' ),
		// 		'timeout' => 120,
		// 	),
		// 	array(
		// 		'message' => $comment->comment_content,
		// 		'assistant_id' => $assistant_id,
		// 		'test' => true,
		// 	),
		// 	'wpcom'
		// );

		// Bot comment data
		$bot_commentdata = array(
			'comment_post_ID' => $comment->comment_post_ID,
			'comment_author' => get_the_title( $comment->comment_post_ID ),
			'comment_author_email' => '',
			'comment_content' => $bot_response,
			'comment_type' => 'ai_response',
			'comment_parent' => $comment_id,
			'comment_approved' => 1,
		);

		$bot_comment_id = wp_insert_comment( $bot_commentdata );
		if ( $bot_comment_id ) {
			update_comment_meta( $bot_comment_id, 'thread_id', $thread_id );
		}
	}

	/**
	 * Creates a new thread for the assistant.
	 */
	private function create_new_thread( $message_content ) {
		$response = $this->openai_updater->request_wpcom(
			'/openai-proxy/v1/threads',
			'POST',
			array(
				'messages' => array(
					array(
						'content' => $message_content,
						'role' => 'user',
					),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response );
		return $response;
	}

	/**
	 * Runs a thread.
	 */
	private function run_thread( $thread_id, $assistant_id ) {
		$response = $this->openai_updater->request_wpcom(
			"/openai-proxy/v1/threads/$thread_id/runs",
			'POST',
			array(
				'assistant_id' => $assistant_id,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response );
		return $response;
	}

	/**
	 * Polls a run for completion status.
	 *
	 * @see https://platform.openai.com/docs/assistants/how-it-works/runs-and-run-steps
	 *
	 * @param int $thread_id The ID of the thread.
	 * @param int $run_id The ID of the run.
	 * @param int $timeout The maximum time to wait for completion status, in seconds.
	 *
	 * @return string The status of the run, or 'timeout' if the maximum time is reached.
	 */
	public function poll_run_for_completion( $thread_id, $run_id, $timeout ) {
		$timeout        = $timeout < 60 ? $timeout : 60;
		$sleep_interval = 0.25;

		for ( $i = 0; $i < $timeout / $sleep_interval; $i++ ) {
			usleep( (int) ( $sleep_interval * 1000 * 1000 ) );
			$run = $this->request_threads_get_run( $thread_id, $run_id );

			if ( in_array(
				$run->status,
				array(
					'requires_action',
					'cancelling',
					'cancelled',
					'failed',
					'completed',
					'expired',
				)
			)
			) {
				return $run->status;
			}
		}

		return 'timeout';
	}

	/**
	 * Gets a run.
	 */
	public function request_threads_get_run( $thread_id, $run_id ) {
		$api_call = $this->openai_updater->request_wpcom(
			"/openai-proxy/v1/threads/$thread_id/runs/$run_id",
			'GET',
		);

		$result = wp_remote_retrieve_body( $api_call );
		$result = json_decode( $result );
		if ( isset( $result->error ) ) {
			return new \WP_Error( $result->error->type ?? 'openai_error', $result->error );
		}

		return $result;
	}

	/**
	 * Gets the messages from a thread.
	 */
	public function get_thread_messages( $thread_id ) {
		$api_call = $this->openai_updater->request_wpcom(
			"/openai-proxy/v1/threads/$thread_id/messages",
			'GET',
		);

		$result = wp_remote_retrieve_body( $api_call );
		$result = json_decode( $result );
		if ( isset( $result->error ) ) {
			return new \WP_Error( $result->error->type ?? 'openai_error', $result->error );
		}

		return $result;
	}

	public function get_bot_response( $response ) {
		return $response->data[0]->content[0]->text->value;
	}

	// method to get a meta value of the parent comment
	public function get_parent_comment_meta( $comment_id, $meta_key ) {
		$comment = get_comment( $comment_id );
		if ( $comment->comment_parent ) {
			$parent_comment = get_comment( $comment->comment_parent );
			return get_comment_meta( $parent_comment->comment_ID, $meta_key, true );
		}
		return false;
	}
}
