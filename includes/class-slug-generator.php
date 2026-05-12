<?php
/**
 * Slug generator. Hooks into save_post and replaces Japanese slugs with AI-generated English slugs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NPC_Slug_Genius_Slug_Generator {

    public function register() {
        add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
    }

    public function on_save_post( $post_id, $post, $update ) {
        if ( ! $this->should_process( $post_id, $post ) ) {
            return;
        }

        if ( $this->is_manually_set( $post_id, $post ) ) {
            return;
        }

        if ( ! NPC_Slug_Genius::is_ai_available() ) {
            return;
        }

        if ( ! $this->needs_generation( $post ) ) {
            return;
        }

        $provider = NPC_Slug_Genius::get_active_provider();
        if ( ! $provider instanceof NPC_Slug_Genius_Provider ) {
            return;
        }

        $result = $provider->generate_slug( $post->post_title );
        if ( is_wp_error( $result ) ) {
            // Silently skip on API failure. The post saves normally.
            return;
        }

        $new_slug = sanitize_title( $result );
        if ( '' === $new_slug || $new_slug === $post->post_name ) {
            return;
        }

        $this->update_post_slug( $post_id, $new_slug );
        update_post_meta( $post_id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED, $new_slug );
    }

    private function should_process( $post_id, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return false;
        }
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return false;
        }
        if ( in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
            return false;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }
        if ( '' === trim( (string) $post->post_title ) ) {
            return false;
        }
        $targets = NPC_Slug_Genius::get_target_post_types();
        if ( ! in_array( $post->post_type, $targets, true ) ) {
            return false;
        }
        return true;
    }

    /**
     * Detect manual slug edit by user. Strategy:
     * - If postmeta META_KEY_MANUAL is already true -> already locked.
     * - Else if current post_name does not match the previously stored auto-generated slug -> user edited it manually.
     */
    private function is_manually_set( $post_id, $post ) {
        $locked = get_post_meta( $post_id, NPC_Slug_Genius::META_KEY_MANUAL, true );
        if ( '1' === (string) $locked ) {
            return true;
        }

        $previous_auto = get_post_meta( $post_id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED, true );
        if ( '' !== $previous_auto && $previous_auto !== $post->post_name && '' !== $post->post_name ) {
            // User modified the auto-generated slug. Lock it from now on.
            update_post_meta( $post_id, NPC_Slug_Genius::META_KEY_MANUAL, '1' );
            return true;
        }

        return false;
    }

    /**
     * Whether the current post_name needs AI generation.
     * Yes if the slug contains non-ASCII (Japanese) characters or is empty.
     * Yes also if it already matches a previously stored auto-generated slug (regenerate not needed but safe).
     */
    private function needs_generation( $post ) {
        $slug = (string) $post->post_name;
        if ( '' === $slug ) {
            return true;
        }
        // Non-ASCII presence (Japanese / other) -> needs generation.
        if ( preg_match( '/[^\x20-\x7E]/', $slug ) ) {
            return true;
        }
        // URL-encoded Japanese (e.g. %e3%83%86%e3...) -> needs generation.
        if ( preg_match( '/%[0-9a-f]{2}/i', $slug ) ) {
            return true;
        }
        return false;
    }

    /**
     * Update post_name with infinite-loop guard.
     */
    private function update_post_slug( $post_id, $new_slug ) {
        remove_action( 'save_post', array( $this, 'on_save_post' ), 20 );
        wp_update_post(
            array(
                'ID'        => $post_id,
                'post_name' => $new_slug,
            )
        );
        add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
    }
}
