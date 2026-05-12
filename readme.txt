=== NPC Slug Genius ===
Contributors: npc01
Tags: slug, seo, japanese, permalink, ai
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates SEO-optimized English URL slugs from Japanese post titles using AI. BYOK (Bring Your Own Key).

== Description ==

NPC Slug Genius is built for Japanese WordPress sites that want clean, SEO-friendly English URL slugs without the manual translation work.

**Most "slug translator" plugins translate Japanese to English literally.** That gives you slugs like `recruitment-information` (from 採用情報) or `company-introduction` (from 会社案内), which are technically correct but unnatural for English-speaking search engines.

NPC Slug Genius takes a different approach: it uses AI to think about how an English-speaking searcher would actually look for the page, and generates slugs like `careers` and `about-us`. For Japanese-specific concepts that have no clean English equivalent (補助金 program names, etc.), it keeps the Romaji and translates only the surrounding words, producing slugs like `apply-monozukuri-subsidy`.

**Key features:**

* Automatic SEO-optimized English slug generation on post save
* Manual slug edits are respected and never overwritten
* **Safe by default for existing sites**: posts created before plugin activation are skipped, so existing URLs are never rewritten unless you explicitly opt in
* Configurable target post types
* Safe fallback: if the API key is missing or the API call fails, the post saves normally with no error
* BYOK (Bring Your Own Key): you control the API usage and cost

**Currently supported AI providers:**

* Anthropic Claude (Haiku model, lowest cost)

**Coming soon:**

* OpenAI ChatGPT (v1.1)
* Google Gemini (v1.2)

**Getting started:**

1. Install and activate the plugin.
2. Go to Settings > NPC Slug Genius.
3. Get an Anthropic API key from [console.anthropic.com](https://console.anthropic.com/settings/keys) and paste it in.
4. Choose which post types should get automatic slug generation (default: posts and pages).
5. Create a post with a Japanese title - the slug will be automatically generated when you save.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install through the WordPress admin.
2. Activate the plugin.
3. Go to Settings > NPC Slug Genius and configure your API key.

== Frequently Asked Questions ==

= Do I need an API key to use this plugin? =

Yes. This plugin uses the Anthropic Claude API, and you need your own API key. Get one at https://console.anthropic.com/settings/keys.

= How much does it cost to run? =

Each slug generation uses the Claude Haiku model, which costs roughly $0.0002 to $0.0005 per post. For most blogs, monthly cost is well under a dollar.

= What if my API key is wrong or the API is down? =

The plugin fails silently. Your post saves normally with the default WordPress slug behavior (which keeps the Japanese title as the slug). No errors are shown to the visitor.

= Can I edit the auto-generated slug manually? =

Yes. Once you manually edit a slug, this plugin will not overwrite it on future saves.

= What data is sent to the AI provider? =

Only the post title is sent. No post content, no user data, no site metadata.

= Does this work with custom post types? =

Yes. Go to Settings > NPC Slug Genius and check the post types you want.

= I installed this on a site with existing Japanese posts. Will their URLs change? =

No, not by default. Posts created before plugin activation are skipped to protect existing URLs. If you actually want to rewrite all existing Japanese slugs to English, enable "Also process posts created before plugin activation" in the settings (warning: this will change existing URLs).

== Privacy Policy ==

When a post is saved, this plugin sends the post title to the AI provider you configured (currently Anthropic Claude). The post title is processed to generate an English slug and is not stored by this plugin beyond the resulting slug.

No personally identifiable information is collected or transmitted by this plugin.

== Changelog ==

= 1.0.0 =
* Initial release.
* Anthropic Claude provider (Haiku model).
* Settings page with API key, provider selection (Claude only for now), and target post types.
* Manual slug protection.
* Admin notice when API key is missing.
* Safe-by-default: existing posts (created before activation) are skipped to protect existing URLs.

== Upgrade Notice ==

= 1.0.0 =
First release.
