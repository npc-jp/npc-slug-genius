<?php
/**
 * Settings page under Settings > NPC Slug Genius.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NPC_Slug_Genius_Settings_Page {

    const MENU_SLUG  = 'npc-slug-genius';
    const NONCE_NAME = '_npc_slug_genius_nonce';

    private $screen_id = '';

    public function register() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_menu() {
        $hook = add_options_page(
            __( 'NPC Slug Genius', 'npc-slug-genius' ),
            __( 'NPC Slug Genius', 'npc-slug-genius' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render' )
        );
        if ( is_string( $hook ) && '' !== $hook ) {
            $this->screen_id = $hook;
        }
    }

    public function register_settings() {
        register_setting(
            'npc_slug_genius_settings',
            NPC_Slug_Genius::OPTION_PROVIDER,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_provider' ),
                'default'           => 'claude',
            )
        );

        register_setting(
            'npc_slug_genius_settings',
            NPC_Slug_Genius::OPTION_API_KEY_CLAUDE,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_api_key_claude' ),
                'default'           => '',
            )
        );

        register_setting(
            'npc_slug_genius_settings',
            NPC_Slug_Genius::OPTION_API_KEY_OPENAI,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_api_key_openai' ),
                'default'           => '',
            )
        );

        register_setting(
            'npc_slug_genius_settings',
            NPC_Slug_Genius::OPTION_API_KEY_GEMINI,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_api_key_gemini' ),
                'default'           => '',
            )
        );

        register_setting(
            'npc_slug_genius_settings',
            NPC_Slug_Genius::OPTION_POST_TYPES,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_post_types' ),
                'default'           => array( 'post', 'page' ),
            )
        );

        register_setting(
            'npc_slug_genius_settings',
            NPC_Slug_Genius::OPTION_INCLUDE_EXISTING,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_boolean' ),
                'default'           => '0',
            )
        );
    }

    public function sanitize_boolean( $input ) {
        $value = is_string( $input ) ? sanitize_text_field( wp_unslash( $input ) ) : '';
        return '1' === $value ? '1' : '0';
    }

    public function sanitize_provider( $input ) {
        $value = is_string( $input ) ? sanitize_key( wp_unslash( $input ) ) : '';
        return in_array( $value, NPC_Slug_Genius::SUPPORTED_PROVIDERS, true ) ? $value : 'claude';
    }

    public function sanitize_api_key_claude( $input ) {
        return $this->sanitize_api_key_for( 'claude', $input );
    }

    public function sanitize_api_key_openai( $input ) {
        return $this->sanitize_api_key_for( 'openai', $input );
    }

    public function sanitize_api_key_gemini( $input ) {
        return $this->sanitize_api_key_for( 'gemini', $input );
    }

    /**
     * Common sanitizer for provider API keys.
     * - "Delete saved key" checkbox -> returns empty (clears the key).
     * - Empty input             -> keeps existing key unchanged.
     * - Non-empty input         -> stored after sanitize_text_field.
     */
    private function sanitize_api_key_for( $provider_id, $input ) {
        $delete_field = 'npc_slug_genius_delete_api_key_' . $provider_id;
        $delete_requested = isset( $_POST[ $delete_field ] )
            && '1' === sanitize_text_field( wp_unslash( $_POST[ $delete_field ] ) );
        if ( $delete_requested ) {
            return '';
        }

        if ( ! is_string( $input ) ) {
            return '';
        }
        $value = sanitize_text_field( wp_unslash( $input ) );
        if ( '' === $value ) {
            // Empty input -> keep existing key unchanged.
            $option_name = $this->option_name_for( $provider_id );
            return (string) get_option( $option_name, '' );
        }
        return $value;
    }

    private function option_name_for( $provider_id ) {
        switch ( $provider_id ) {
            case 'openai':
                return NPC_Slug_Genius::OPTION_API_KEY_OPENAI;
            case 'gemini':
                return NPC_Slug_Genius::OPTION_API_KEY_GEMINI;
            case 'claude':
            default:
                return NPC_Slug_Genius::OPTION_API_KEY_CLAUDE;
        }
    }

    public function sanitize_post_types( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        $cleaned = array_map( 'sanitize_key', array_map( 'wp_unslash', $input ) );
        $cleaned = array_values( array_filter( $cleaned, 'post_type_exists' ) );
        return $cleaned;
    }

    public function get_screen_id() {
        return $this->screen_id;
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $provider         = NPC_Slug_Genius::get_active_provider_id();
        $api_key_claude   = NPC_Slug_Genius::get_api_key( 'claude' );
        $api_key_openai   = NPC_Slug_Genius::get_api_key( 'openai' );
        $api_key_gemini   = NPC_Slug_Genius::get_api_key( 'gemini' );
        $post_types       = NPC_Slug_Genius::get_target_post_types();
        $all_types        = get_post_types( array( 'public' => true ), 'objects' );
        $include_existing = NPC_Slug_Genius::should_include_existing();
        $activated_at     = NPC_Slug_Genius::get_activated_at();

        // Convert UTC-stored activation time to the site timezone for display.
        $activated_at_local = '';
        if ( '' !== $activated_at ) {
            $format             = trim( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s' ) );
            $activated_at_local = get_date_from_gmt( $activated_at, $format );
        }

        include NPC_SLUG_GENIUS_DIR . 'templates/settings.php';
    }
}
