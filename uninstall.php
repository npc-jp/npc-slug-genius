<?php
/**
 * Uninstall handler. Cleans up plugin options.
 * Post meta (_npc_slug_genius_manual / _npc_slug_genius_auto) is intentionally preserved
 * so reinstalling the plugin retains manual slug protection.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'npc_slug_genius_provider' );
delete_option( 'npc_slug_genius_api_key_claude' );
delete_option( 'npc_slug_genius_post_types' );
delete_option( 'npc_slug_genius_include_existing' );
delete_option( 'npc_slug_genius_activated_at' );
