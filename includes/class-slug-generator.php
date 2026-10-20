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
        if ( '' === $new_slug ) {
            return;
        }
        // 下書き・レビュー待ちには WordPress が重複よけの番号を付けないので、公開した扱いで先に番号を付ける。
        // そのまま書くと公開済みの投稿と同じスラッグになり、公開済みのほうが次の保存で "-2" に変わる（固定ページは旧 URL が切れる）
        $new_slug = wp_unique_post_slug( $new_slug, $post_id, 'publish', $post->post_type, $post->post_parent );
        if ( $new_slug === $post->post_name ) {
            return;
        }

        // 更新に成功したときだけ記録する。記録するのは WordPress が実際に保存したスラッグ
        // （同じスラッグがあると "-2" が付く）。ずれた値を記録すると、次の保存で「手で書き換えた」と誤ってロックする
        $saved_slug = $this->update_post_slug( $post_id, $new_slug );
        if ( '' === $saved_slug ) {
            return;
        }
        update_post_meta( $post_id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED, $saved_slug );
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
        if ( ! $this->is_within_activation_window( $post ) ) {
            return false;
        }
        return true;
    }

    /**
     * Skip posts created before plugin activation unless user opted in.
     * This protects existing URLs from being silently rewritten.
     */
    private function is_within_activation_window( $post ) {
        if ( NPC_Slug_Genius::should_include_existing() ) {
            return true;
        }
        // Use the previously stored auto-generated postmeta as an override:
        // posts that this plugin already generated a slug for are not skipped by the activation-time check.
        $previous_auto = get_post_meta( $post->ID, NPC_Slug_Genius::META_KEY_AUTO_GENERATED, true );
        if ( '' !== $previous_auto ) {
            return true;
        }
        $activated_at = NPC_Slug_Genius::get_activated_at();
        if ( '' === $activated_at ) {
            // No activation time recorded (legacy install). Be safe: skip.
            return false;
        }
        $post_gmt = (string) $post->post_date_gmt;
        if ( '' === $post_gmt || '0000-00-00 00:00:00' === $post_gmt ) {
            // Newly created post often has empty post_date_gmt at first save.
            // Treat as "new" -> safe to process.
            return true;
        }
        return strtotime( $post_gmt ) >= strtotime( $activated_at );
    }

    /**
     * Detect manual slug edit by user. Strategy:
     * - If postmeta META_KEY_MANUAL is already true -> already locked.
     * - Else if current post_name does not match the previously stored auto-generated slug -> user edited it manually,
     *   unless it is exactly the slug that WordPress makes from the stored slug on publishing (e.g. "contact-2").
     * - An empty post_name does not create a new manual lock.
     */
    private function is_manually_set( $post_id, $post ) {
        $locked = get_post_meta( $post_id, NPC_Slug_Genius::META_KEY_MANUAL, true );
        if ( '1' === (string) $locked ) {
            return true;
        }

        $previous_auto = get_post_meta( $post_id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED, true );
        if ( '' !== $previous_auto && $previous_auto !== $post->post_name && '' !== $post->post_name ) {
            // WordPress が記録したスラッグに重複よけの番号を付けたもの（例 "contact" → "contact-2"）なら、手の書き換えではない。
            // WordPress と同じ計算をして見分け、記録を保存されたスラッグに合わせる。リクエストをまたぐ値は持たない。
            // 状態は「公開した扱い」で計算する: 下書きは公開した時に番号が付き、クイック編集は下書きでも公開した扱いで
            // 先に番号を付ける（wp_ajax_inline_save）。公開済み・予約・非公開では、状態によらず計算は同じ
            $numbered = wp_unique_post_slug( $previous_auto, $post_id, 'publish', $post->post_type, $post->post_parent );
            if ( $numbered === $post->post_name ) {
                update_post_meta( $post_id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED, $post->post_name );
                return false;
            }
            // User modified the auto-generated slug. Lock it from now on.
            update_post_meta( $post_id, NPC_Slug_Genius::META_KEY_MANUAL, '1' );
            return true;
        }

        return false;
    }

    /**
     * Whether the current post_name needs AI generation.
     * Yes if the slug is empty, contains non-ASCII (Japanese) characters, or is percent-encoded.
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
     *
     * @return string 保存されたスラッグ。更新に失敗したときは空文字。
     */
    private function update_post_slug( $post_id, $new_slug ) {
        remove_action( 'save_post', array( $this, 'on_save_post' ), 20 );
        $result = wp_update_post(
            array(
                'ID'        => $post_id,
                'post_name' => $new_slug,
            ),
            true
        );
        add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );

        if ( is_wp_error( $result ) || ! $result ) {
            return '';
        }
        $saved = get_post( $post_id );
        return $saved instanceof WP_Post ? (string) $saved->post_name : '';
    }
}
