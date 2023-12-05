<?php
declare( strict_types=1 );

namespace GPT_CPT;

class Meta_Boxes {
	public function initialize() {
		add_action( 'add_meta_boxes', array( $this, 'add_assistant_meta_boxes' ) );
		add_action( 'save_post_gpt_cpt', array( $this, 'save_meta_data' ), 10, 1 );
	}

	public function add_assistant_meta_boxes() {
		// Upload a knowledge file.
		add_meta_box(
			'assistant_generate_knowledge_box',
			'Upload Knowledge',
			array( $this, 'metabox_knowledge' ),
			'gpt_cpt',
			'normal',
			'high'
		);

		// Select tools to use.
		add_meta_box(
			'assistant_tools',
			'Tools',
			array( $this, 'metabox_tools' ),
			'gpt_cpt',
			'side',
			'default'
		);

		// Assistant ID.
		add_meta_box(
			'assistant_id',
			'Assistant ID (OpenAI)',
			array( $this, 'metabox_assistant_id' ),
			'gpt_cpt',
			'side',
			'low'
		);

		// Assistant description.
		add_meta_box(
			'assistant_description',
			'Description',
			array( $this, 'metabox_description' ),
			'gpt_cpt',
			'side',
			'default'
		);

		// Assistant data.
		add_meta_box(
			'assistant_data',
			'Assistant Data (OpenAI)',
			array( $this, 'assistant_data' ),
			'gpt_cpt',
			'normal',
			'low'
		);
	}

	public function metabox_description() {
		wp_nonce_field( basename( __FILE__ ), 'gpt_cpt_nonce' );

		global $post;
		$assistant_description = get_post_meta( $post->ID, 'assistant_description', true );

		echo '<p>This is only used in the OpenAI assistant meta. Does not influence chats.</p>';
		echo '<textarea name="assistant_description" style="width: 100%; height: 100px;">' . esc_html( $assistant_description ) . '</textarea>';
	}

	public function metabox_knowledge( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'gpt_cpt_nonce' );

		$selected_post_types = get_post_meta( $post->ID, 'knowledge_post_types', true );
		if ( ! is_array( $selected_post_types ) ) {
			$selected_post_types = ( $selected_post_types ) ? array( $selected_post_types ) : array();
		}
		$allowed_post_types = array_values( get_post_types( array( 'public' => true ) ) );

		$file_path = get_post_meta( $post->ID, 'knowledge_file_path', true );
		if ( ! is_string( $file_path ) ) {
			$file_path = '';
			delete_post_meta( $post->ID, 'knowledge_file_path' );
		}
		$file_ids = get_post_meta( $post->ID, 'knowledge_file_ids', true );

		if ( $file_ids ) {
			echo '<p>Knowledge Files:</p>';
		}

		if ( file_exists( $file_path ) ) {
			$knowledge_file_url = Knowledge::get_knowledge_file_url( $post->ID );
			echo '<p>Knowledge File: <a href="' . esc_url( $knowledge_file_url ) . '" target="_blank">' . esc_html( $knowledge_file_url ) . '</a></p>';

			// echo knowledge file ids
			$file_ids = get_post_meta( $post->ID, 'knowledge_file_ids', true );
			if ( $file_ids ) {
				echo '<p>Knowledge File IDs: ' . json_encode( $file_ids ) . '</p>';
			}
		}

		echo '<label>Post types to include in knowledge:</label>';
		echo '<ul>';
		$this->render_post_type_list_html( $allowed_post_types, $selected_post_types );
		echo '</ul>';
	}

	private function render_post_type_list_html( $allowed_post_types, $selected_post_types = array() ) {
		foreach ( $allowed_post_types as $type ) {
			$post_type_object = get_post_type_object( $type );
			$label            = $post_type_object->labels->name;
			$checked = '';
			if ( in_array( $type, $selected_post_types, true ) ) {
				$checked = 'checked="checked" ';
			}
			?>
		<li>
			<label>
				<input
					value="<?php echo esc_attr( $type ); ?>"
					name="<?php echo 'knowledge_post_types[]'; ?>"
					id="<?php echo 'assistants-post-types-' . esc_attr( $type ); ?>"
					type="checkbox"
					<?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				>
				<?php echo esc_html( $label ); ?>
			</label>
		</li>
			<?php
		} // End foreach().
	}

	/**
	 * Select the tools to use for the assistant.
	 * TODO: Currently only supports one tool.
	 * TODO: Function calling?
	 */
	public function metabox_tools() {
		wp_nonce_field( basename( __FILE__ ), 'gpt_cpt_nonce' );

		global $post;
		$assistant_tool = get_post_meta( $post->ID, 'assistant_tools', true );
		echo '<select name="assistant_tools" style="width: 100%;">';
		echo '<option value="retrieval"' . ( 'retrieval' == $assistant_tool ? ' selected' : '' ) . '>Retrieval</option>';
		echo '<option value="code_interpreter"' . ( 'code_interpreter' == $assistant_tool ? ' selected' : '' ) . '>Code Interpreter</option>';
		// echo '<option value="function"' . ( 'function' == $assistant_tool ? ' selected' : '' ) . '>Function</option>';
		echo '</select>';
	}

	/**
	 * Adds the meta field for the OpenAI assistant ID.
	 */
	public function metabox_assistant_id() {
		wp_nonce_field( basename( __FILE__ ), 'gpt_cpt_nonce' );

		global $post;
		$custom = get_post_custom( $post->ID );
		if ( empty( $custom['assistant_id'] ) ) {
			echo '<p>Automatically generated. Publish the CPT to get an ID.</p>';
		} else {
			$assistant_id = $custom['assistant_id'][0];
			echo '<input name="assistant_id" value="' . esc_html( $assistant_id ) . '" disabled />';
		}
	}

	public function save_meta_data( $post_id ) {
		if ( ! isset( $_POST['gpt_cpt_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['gpt_cpt_nonce'] ), basename( __FILE__ ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
			return;
		}

		// Tools
		if ( isset( $_POST['assistant_tools'] ) ) {
			update_post_meta( $post_id, 'assistant_tools', sanitize_text_field( wp_unslash( $_POST['assistant_tools'] ) ) );
		} else {
			delete_post_meta( $post_id, 'assistant_tools' );
		}

		// Description
		if ( isset( $_POST['assistant_description'] ) ) {
			update_post_meta( $post_id, 'assistant_description', sanitize_text_field( wp_unslash( $_POST['assistant_description'] ) ) );
		} else {
			delete_post_meta( $post_id, 'assistant_description' );
		}

		// Knowledge file
		if ( isset( $_POST['knowledge_post_types'] ) ) {
			$new_post_types = array_map( 'sanitize_text_field', wp_unslash( $_POST['knowledge_post_types'] ) );

			// Generate JSON data
			$knowledge_file = new Knowledge();
			$json_data = $knowledge_file->generate_knowledge_json( $new_post_types );

			// Save JSON to file
			$file_path = $knowledge_file->save_json_to_file( $json_data, $post_id );

			if ( $file_path ) {
				update_post_meta( $post_id, 'knowledge_file_url', Knowledge::get_knowledge_file_url( $post_id ) );
				update_post_meta( $post_id, 'knowledge_file_path', $file_path );
				update_post_meta( $post_id, 'knowledge_post_types', $new_post_types );
			}
		} else {
			delete_post_meta( $post_id, 'knowledge_post_types' );
			delete_post_meta( $post_id, 'knowledge_file_path' );
			delete_post_meta( $post_id, 'knowledge_file_id' );
		}
	}

	/**
	 * Gets the assistant data from OpenAI.
	 */
	public function assistant_data( $post_id ) {
		$assistant_id = get_post_meta( $post_id->ID, 'assistant_id', true );
		if ( empty( $assistant_id ) ) {
			return;
		}
		$assistant_data = get_post_meta( $post_id->ID, 'assistant_data', true );
		$assistant_data = json_encode( $assistant_data, JSON_PRETTY_PRINT );

		echo '<p>The Assistant object returned from OpenAI</p>';
		echo '<textarea disabled name="assistant_data" style="width: 100%; height: 400px;">' . esc_html( $assistant_data ) . '</textarea>';
	}
}
