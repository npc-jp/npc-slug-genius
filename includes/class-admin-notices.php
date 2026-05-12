<?php
/**
 * Admin notices. Shown only on this plugin's own admin pages to comply with WP.org guideline 11.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NPC_Slug_Genius_Admin_Notices {

    public function register() {
        add_action( 'admin_notices', array( $this, 'maybe_show' ) );
    }

    public function maybe_show() {
        if ( ! $this->is_own_admin_page() ) {
            return;
        }
        if ( ! NPC_Slug_Genius::is_ai_available() ) {
            $this->notice_api_key_missing();
        }
    }

    private function is_own_admin_page() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }
        $screen = get_current_screen();
        if ( ! $screen || empty( $screen->id ) ) {
            return false;
        }
        return false !== strpos( $screen->id, NPC_Slug_Genius_Settings_Page::MENU_SLUG );
    }

    private function notice_api_key_missing() {
        $settings_url = admin_url( 'options-general.php?page=' . NPC_Slug_Genius_Settings_Page::MENU_SLUG );
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__( 'NPC Slug Genius', 'npc-slug-genius' ); ?>:</strong>
                <?php echo esc_html__( 'API key is not configured. Slug auto-generation will not run until you set an API key.', 'npc-slug-genius' ); ?>
                <a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'Open settings', 'npc-slug-genius' ); ?></a>
            </p>
        </div>
        <?php
    }
}
