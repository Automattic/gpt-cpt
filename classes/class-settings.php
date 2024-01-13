<?php

declare( strict_types=1 );

namespace GPT_CPT;

class OpenAI_Settings {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_submenu_page(
            'options-general.php',
            'GPT CPT Settings',
            'GPT CPT',
            'manage_options',
            'gpt-cpt-settings',
            array( $this, 'settings_page_content' )
        );
    }

    public function settings_page_content() {
        ?>
        <div class="wrap">
            <h1>GPT CPT Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'gpt_cpt_options' );
                do_settings_sections( 'gpt-cpt-settings' );
                submit_button();
                ?>
            </form>
        </div>
		<h2>OpenAI Stuff</h2>
		<h4>Files</h4>
        <?php
		$files = new OpenAI_Updater();
		echo "<pre>";
		echo print_r( $files->list_uploaded_files(), true );
		echo "</pre>";

    }

    public function register_settings() {
        register_setting( 'gpt_cpt_options', 'gpt_cpt_openai_api_token' );
        register_setting( 'gpt_cpt_options', 'gpt_cpt_openai_org_id' );

        add_settings_section(
            'gpt_cpt_main',
            'OpenAI Settings',
            null,
            'gpt-cpt-settings'
        );

        add_settings_field(
            'gpt_cpt_openai_api_token',
            'OpenAI API Token',
            array( $this, 'settings_field_input_text' ),
            'gpt-cpt-settings',
            'gpt_cpt_main',
            array( 'id' => 'gpt_cpt_openai_api_token' )
        );

        add_settings_field(
            'openai_org_id',
            'OpenAI Org ID',
            array( $this, 'settings_field_input_text' ),
            'gpt-cpt-settings',
            'gpt_cpt_main',
            array( 'id' => 'openai_org_id' )
        );
    }

    public function settings_field_input_text($args) {
        $option = get_option($args['id']);
        echo '<input type="text" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" value="' . esc_attr($option) . '" />';
    }
}
