<?php
/**
 * Plugin Name: NPC Slug Genius
 * Plugin URI: https://github.com/npc-jp/npc-slug-genius
 * Description: Automatically generates SEO-optimized English URL slugs from Japanese post titles using AI. BYOK (Bring Your Own Key) with support for Anthropic Claude, OpenAI ChatGPT, and Google Gemini.
 * Version: 1.1.1
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: npc
 * Author URI: https://n-pc.jp
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: npc-slug-genius
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NPC_SLUG_GENIUS_VERSION', '1.1.1' );
define( 'NPC_SLUG_GENIUS_FILE', __FILE__ );
define( 'NPC_SLUG_GENIUS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NPC_SLUG_GENIUS_URL', plugin_dir_url( __FILE__ ) );
define( 'NPC_SLUG_GENIUS_BASENAME', plugin_basename( __FILE__ ) );
define( 'NPC_SLUG_GENIUS_SLUG', 'npc-slug-genius' );

require_once NPC_SLUG_GENIUS_DIR . 'includes/class-main.php';

register_activation_hook( __FILE__, array( 'NPC_Slug_Genius', 'activate' ) );

add_action( 'plugins_loaded', array( 'NPC_Slug_Genius', 'get_instance' ) );
