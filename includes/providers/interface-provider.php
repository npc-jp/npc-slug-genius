<?php
/**
 * Provider interface. All AI provider adapters must implement this contract.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface NPC_Slug_Genius_Provider {

    /**
     * Generate an SEO-optimized English slug from a Japanese title.
     *
     * @param string $title   Post title.
     * @param array  $context Optional. Reserved for future extension (category, tags, etc).
     * @return string|WP_Error Normalized slug, or WP_Error on failure.
     */
    public function generate_slug( $title, $context = array() );

    /**
     * Provider identifier.
     *
     * @return string e.g. 'claude' / 'openai' / 'gemini'.
     */
    public function get_id();

    /**
     * Display name shown in settings UI.
     *
     * @return string
     */
    public function get_display_name();

    /**
     * Whether an API key is configured for this provider.
     *
     * @return bool
     */
    public function has_api_key();
}
