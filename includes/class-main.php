<?php
/**
 * Main plugin class. Singleton lifecycle manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NPC_Slug_Genius {

    const OPTION_PROVIDER          = 'npc_slug_genius_provider';
    const OPTION_API_KEY_CLAUDE    = 'npc_slug_genius_api_key_claude';
    const OPTION_POST_TYPES        = 'npc_slug_genius_post_types';
    const META_KEY_MANUAL          = '_npc_slug_genius_manual';
    const META_KEY_AUTO_GENERATED  = '_npc_slug_genius_auto';

    /** @var NPC_Slug_Genius|null */
    private static $instance = null;

    /** @var NPC_Slug_Genius_Settings_Page */
    private $settings_page;

    /** @var NPC_Slug_Genius_Slug_Generator */
    private $slug_generator;

    /** @var NPC_Slug_Genius_Admin_Notices */
    private $admin_notices;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( NPC_SLUG_GENIUS_BASENAME );
            wp_die(
                esc_html__( 'NPC Slug Genius requires PHP 7.4 or higher.', 'npc-slug-genius' ),
                esc_html__( 'Plugin Activation Error', 'npc-slug-genius' ),
                array( 'back_link' => true )
            );
        }
    }

    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies() {
        require_once NPC_SLUG_GENIUS_DIR . 'includes/providers/interface-provider.php';
        require_once NPC_SLUG_GENIUS_DIR . 'includes/providers/class-claude-adapter.php';
        require_once NPC_SLUG_GENIUS_DIR . 'includes/class-settings-page.php';
        require_once NPC_SLUG_GENIUS_DIR . 'includes/class-slug-generator.php';
        require_once NPC_SLUG_GENIUS_DIR . 'includes/class-admin-notices.php';

        $this->settings_page  = new NPC_Slug_Genius_Settings_Page();
        $this->slug_generator = new NPC_Slug_Genius_Slug_Generator();
        $this->admin_notices  = new NPC_Slug_Genius_Admin_Notices();
    }

    private function register_hooks() {
        $this->settings_page->register();
        $this->slug_generator->register();
        $this->admin_notices->register();
    }

    public static function get_active_provider_id() {
        $value = get_option( self::OPTION_PROVIDER, 'claude' );
        if ( ! in_array( $value, array( 'claude' ), true ) ) {
            return 'claude';
        }
        return $value;
    }

    public static function get_api_key( $provider_id = null ) {
        if ( null === $provider_id ) {
            $provider_id = self::get_active_provider_id();
        }
        if ( 'claude' === $provider_id ) {
            return (string) get_option( self::OPTION_API_KEY_CLAUDE, '' );
        }
        return '';
    }

    public static function get_target_post_types() {
        $stored = get_option( self::OPTION_POST_TYPES, array( 'post', 'page' ) );
        if ( ! is_array( $stored ) ) {
            $stored = array( 'post', 'page' );
        }
        $valid = array_values( array_filter( $stored, 'post_type_exists' ) );
        /**
         * Filter the target post types for automatic slug generation.
         *
         * @param array $valid List of post type names.
         */
        return apply_filters( 'npc_slug_genius_target_post_types', $valid );
    }

    public static function is_ai_available() {
        $key = self::get_api_key();
        return ! empty( $key );
    }

    public static function get_active_provider() {
        $provider_id = self::get_active_provider_id();
        if ( 'claude' === $provider_id ) {
            return new NPC_Slug_Genius_Claude_Adapter( self::get_api_key( 'claude' ) );
        }
        return null;
    }
}
