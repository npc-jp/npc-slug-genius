=== NPC Slug Genius ===
Contributors: npc01
Tags: slug, seo, japanese, permalink, ai
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.1
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

**Supported AI providers (choose any one):**

* Anthropic Claude (Haiku model)
* OpenAI ChatGPT (GPT-4o mini model)
* Google Gemini (2.0 Flash model)

You only need an API key from one provider. Switch between providers anytime from the settings page.

**Getting started:**

1. Install and activate the plugin.
2. Go to Settings > NPC Slug Genius.
3. Choose your preferred AI provider from the dropdown.
4. Get an API key from the provider's console and paste it in:
   * Anthropic: [console.anthropic.com](https://console.anthropic.com/settings/keys)
   * OpenAI: [platform.openai.com](https://platform.openai.com/api-keys)
   * Google: [aistudio.google.com](https://aistudio.google.com/apikey)
5. Choose which post types should get automatic slug generation (default: posts and pages).
6. Create a post with a Japanese title - the slug will be automatically generated when you save.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install through the WordPress admin.
2. Activate the plugin.
3. Go to Settings > NPC Slug Genius and configure your API key.

== Frequently Asked Questions ==

= Do I need an API key to use this plugin? =

Yes. You need an API key from one of the supported providers (Anthropic, OpenAI, or Google). You only need one - whichever you prefer.

= How much does it cost to run? =

Each slug generation costs only a fraction of a cent on any of the supported low-cost models (Claude Haiku, GPT-4o mini, Gemini 2.0 Flash). For most blogs, monthly cost is well under a dollar regardless of provider.

= Can I switch between providers later? =

Yes. You can change the active provider from the settings page anytime. API keys for unused providers are kept in your settings (but never sent anywhere) until you delete them.

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

== External services ==

This plugin connects to one of three AI provider APIs (whichever the user selects in the settings) to generate SEO-optimized English URL slugs from Japanese post titles. Only the provider you actively select is contacted; the other two are not used.

What the services are and what they are used for:
These are large language model APIs used to translate Japanese post titles into natural English URL slugs. The plugin uses a low-cost lightweight model from whichever provider you select.

What data is sent and when:
The plugin sends the post title (text only) to the selected provider's API. The request is made only when a post is saved AND a valid API key for the active provider is configured in the plugin settings. No post content, user data, IP address, or site metadata is sent by this plugin. If no API key is configured for the active provider, no request is made.

= Anthropic Claude (provided by Anthropic, PBC) =

* Endpoint: https://api.anthropic.com/v1/messages
* Anthropic Consumer Terms of Service: https://www.anthropic.com/legal/consumer-terms
* Anthropic Commercial Terms of Service: https://www.anthropic.com/legal/commercial-terms
* Anthropic Usage Policy: https://www.anthropic.com/legal/aup
* Anthropic Privacy Policy: https://www.anthropic.com/legal/privacy

= OpenAI ChatGPT (provided by OpenAI, L.L.C.) =

* Endpoint: https://api.openai.com/v1/chat/completions
* OpenAI Terms of Use: https://openai.com/policies/row-terms-of-use/
* OpenAI Usage Policies: https://openai.com/policies/usage-policies/
* OpenAI Privacy Policy: https://openai.com/policies/row-privacy-policy/

= Google Gemini (provided by Google LLC) =

* Endpoint: https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent
* Google APIs Terms of Service: https://developers.google.com/terms
* Gemini API Additional Terms of Service: https://ai.google.dev/gemini-api/terms
* Google Privacy Policy: https://policies.google.com/privacy

By obtaining an API key from your chosen provider and using this plugin, you agree to that provider's terms.

== Privacy Policy ==

When a post is saved, this plugin sends the post title to the AI provider you configured (Anthropic Claude, OpenAI ChatGPT, or Google Gemini). The post title is processed to generate an English slug and is not stored by this plugin beyond the resulting slug.

No personally identifiable information is collected or transmitted by this plugin.

== Changelog ==

= 1.1.1 =
* Tested with WordPress 7.0.

= 1.1.0 =
* Added OpenAI ChatGPT provider (GPT-4o mini model).
* Added Google Gemini provider (2.0 Flash model).
* Settings page now shows only the API key field for the selected provider.
* Saved API keys for non-active providers are preserved until you delete them.

= 1.0.0 =
* Initial release.
* Anthropic Claude provider (Haiku model).
* Settings page with API key, provider selection (Claude only for now), and target post types.
* Manual slug protection.
* Admin notice when API key is missing.
* Safe-by-default: existing posts (created before activation) are skipped to protect existing URLs.

== Upgrade Notice ==

= 1.1.1 =
Confirmed compatibility with WordPress 7.0.

= 1.1.0 =
Multi-provider support: OpenAI ChatGPT and Google Gemini are now available alongside Anthropic Claude.

= 1.0.0 =
First release.
