<?php
/**
 * Plugin Name: GPT CPT
 * Description: Manage and chat with CPT Assistants
 * Version: 1.0.0
 */

namespace GPT_CPT;

use Automattic\Jetpack\Connection\Client;

require_once 'classes/class-admin-notices.php';
require_once 'classes/class-chat-manager.php';
require_once 'classes/class-cpt.php';
require_once 'classes/class-meta-boxes.php';
require_once 'classes/class-openai-updater.php';
require_once 'classes/class-knowledge-file.php';

class GPT_CPT_Plugin {
	private $cpt;
	private $meta_boxes;
	private $openai_updater;
	private $chat_manager;
	private $admin_notices;

	public function __construct() {
		$this->cpt = new CPT();
		$this->meta_boxes = new Meta_Boxes();
		$this->chat_manager = new Chat_Manager();
		$this->openai_updater = new OpenAI_Updater();
		$this->admin_notices = new Admin_Notices();

		$this->initialize();
	}

	private function initialize() {
		$this->cpt->register();
		$this->meta_boxes->initialize();
		$this->chat_manager->initialize();
		$this->openai_updater->initialize();
		$this->admin_notices->initialize();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function enqueue_admin_scripts() {
		global $post_type;
		if ( 'gpt_cpt' === $post_type ) {
			wp_enqueue_script(
				'gpt-cpt-knowledge',
				plugins_url( '/js/knowledge.js', __FILE__ ),
				array(),
				null,
				true
			);

			$knowledge_file = get_post_meta( get_the_ID(), 'knowledge_file_path', true );
			$file_contents = '';
			if ( is_string( $knowledge_file ) && file_exists( $knowledge_file ) ) {
				$file_contents = file_get_contents( $knowledge_file );
			}
			wp_localize_script( 'gpt-cpt-knowledge', 'knowledgeData', array( 'fileContents' => $file_contents ) );
		}
	}
}

add_action( 'plugins_loaded', function() {
	if ( ! class_exists( 'Automattic\Jetpack\Connection\Client' ) ) {
		Admin_Notices::show_jetpack_notice();
		return;
	}
	new GPT_CPT_Plugin();
} );
