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
                'sanitize_callback' => array( $this, 'sanitize_api_key' ),
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
    }

    public function sanitize_provider( $input ) {
        $allowed = array( 'claude' );
        $value   = is_string( $input ) ? sanitize_key( wp_unslash( $input ) ) : '';
        return in_array( $value, $allowed, true ) ? $value : 'claude';
    }

    public function sanitize_api_key( $input ) {
        // If user explicitly requested deletion via the checkbox, return empty.
        $delete_requested = isset( $_POST['npc_slug_genius_delete_api_key_claude'] )
            && '1' === sanitize_text_field( wp_unslash( $_POST['npc_slug_genius_delete_api_key_claude'] ) );
        if ( $delete_requested ) {
            return '';
        }

        if ( ! is_string( $input ) ) {
            return '';
        }
        $value = sanitize_text_field( wp_unslash( $input ) );
        if ( '' === $value ) {
            // Empty input -> keep existing key unchanged.
            $stored = get_option( NPC_Slug_Genius::OPTION_API_KEY_CLAUDE, '' );
            return (string) $stored;
        }
        return $value;
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

        $provider     = NPC_Slug_Genius::get_active_provider_id();
        $api_key      = NPC_Slug_Genius::get_api_key( 'claude' );
        $post_types   = NPC_Slug_Genius::get_target_post_types();
        $all_types    = get_post_types( array( 'public' => true ), 'objects' );

        include NPC_SLUG_GENIUS_DIR . 'templates/settings.php';
    }
}
