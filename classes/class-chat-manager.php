<?php
declare( strict_types=1 );

namespace GPT_CPT;

use Automattic\Jetpack\Connection\Client as Jetpack_Client;

/**
 * Chat management class.
 * Handles chats for assistants in the comments of the post.
 */
class Chat_Manager {
	public function initialize() {
		add_action( 'comment_post', array( $this, 'after_comment_posted' ), 10, 2 );
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

		$chat_id = $this->get_parent_comment_meta( $comment_id, 'chat_id' );
		$thread_id = $this->get_parent_comment_meta( $comment_id, 'thread_id' );

		$endpoint = $chat_id ? "/odie/chat/jetpack-gpt-assistant/$chat_id?force=wpcom" : '/odie/chat/jetpack-gpt-assistant?force=wpcom';
		$result = Jetpack_Client::wpcom_json_api_request_as_user(
			$endpoint,
			'v2',
			array(
				'method'  => 'POST',
				'headers' => array( 'content-type' => 'application/json' ),
				'timeout' => 60,
			),
			array(
				'message' => $comment->comment_content,
				'assistant_id' => $assistant_id,
				'test' => true,
			),
			'wpcom'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = wp_remote_retrieve_body( $result );
		$response = json_decode( $response );

		$messages = $response->messages;
		$answer = $messages[0]->content;
		$chat_id = $response->chat_id ?? false;

		update_comment_meta( $comment_id, 'thread_id', $thread_id );
		update_comment_meta( $comment_id, 'chat_id', $chat_id );

		if ( ! $answer ) {
			return;
		}

		// Bot comment data
		$bot_commentdata = array(
			'comment_post_ID' => $comment->comment_post_ID,
			'comment_author' => get_the_title( $comment->comment_post_ID ),
			'comment_author_email' => '',
			'comment_content' => $answer,
			'comment_type' => 'ai_response',
			'comment_parent' => $comment_id,
			'comment_approved' => 1,
		);

		$bot_comment_id = wp_insert_comment( $bot_commentdata );
		if ( $bot_comment_id ) {
			update_comment_meta( $bot_comment_id, 'thread_id', $thread_id );
			update_comment_meta( $bot_comment_id, 'chat_id', $chat_id );
		}
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
