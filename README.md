# NPC Slug Genius

[![Plugin Version](https://img.shields.io/badge/version-1.1.1-blue.svg)](https://github.com/npc-jp/npc-slug-genius)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%E2%80%937.0-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net)
[![License](https://img.shields.io/badge/license-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> Automatically generate SEO-optimized English URL slugs from Japanese post titles using AI.

WordPress plugin for Japanese sites that want clean, SEO-friendly English URLs without manual translation work.

## What makes this different

Most "slug translator" plugins translate Japanese to English literally:

- `採用情報` → `recruitment-information` (technically correct, but unnatural)
- `会社案内` → `company-introduction`
- `ものづくり補助金の申請方法` → `monotsukuri-hojokin-no-shinsei-hoho` (full Romaji)

**NPC Slug Genius takes a different approach.** It thinks about how an English-speaking searcher would actually look for the page:

- `採用情報` → `careers`
- `会社案内` → `about-us`
- `ものづくり補助金の申請方法` → `apply-monozukuri-subsidy` (keep Japanese-specific term, translate surrounding words)

## Features

- ✅ Automatic SEO-optimized English slug generation on post save
- ✅ Manual slug edits are respected (never overwritten)
- ✅ **Safe by default for existing sites**: posts created before plugin activation are skipped
- ✅ Configurable target post types
- ✅ Safe fallback: API failures don't break post saves
- ✅ BYOK (Bring Your Own Key): you control the API usage and cost
- ✅ **Three AI providers supported** (since v1.1.0): Anthropic Claude, OpenAI ChatGPT, Google Gemini — switch anytime from the settings page

## Installation

### From WordPress.org (recommended)

Install through `Plugins > Add New > Search "NPC Slug Genius"`, or download from [wordpress.org/plugins/npc-slug-genius](https://wordpress.org/plugins/npc-slug-genius/).

### From this repository

1. Download the [latest release zip](https://github.com/npc-jp/npc-slug-genius/releases) or clone this repo
2. Upload to `/wp-content/plugins/npc-slug-genius/`
3. Activate the plugin
4. Go to **Settings > NPC Slug Genius**
5. Choose your preferred AI provider and paste the API key:
   - **Anthropic Claude**: get key at [console.anthropic.com](https://console.anthropic.com/settings/keys)
   - **OpenAI ChatGPT**: get key at [platform.openai.com](https://platform.openai.com/api-keys)
   - **Google Gemini**: get key at [aistudio.google.com](https://aistudio.google.com/apikey)
6. Choose target post types (default: posts and pages)

## How it works

1. When you save a post with a Japanese title, the plugin sends the title to the AI provider you selected (Claude / OpenAI / Gemini).
2. The AI returns an SEO-optimized English slug based on a curated prompt with 12 carefully-selected Few-shot examples.
3. The slug is set as the post URL.
4. If you manually edit the slug afterward, the plugin respects your choice and won't overwrite it.
5. If the API call fails or the API key is missing, the post saves normally (no errors).

## Cost

All three supported providers offer cost-effective lightweight models:

- **Anthropic Claude (Haiku)**: roughly $0.0002 – $0.0005 per post
- **OpenAI ChatGPT (gpt-4o-mini)**: similar price range
- **Google Gemini (2.0 Flash)**: free tier available, then cheap pay-as-you-go

For most blogs, monthly cost stays well under a dollar regardless of provider.

## Release history

- **v1.1.1** (2026-05-27): Tested with WordPress 7.0
- **v1.1.0** (2026-05-27): Added OpenAI ChatGPT and Google Gemini providers
- **v1.0.0** (2026-05-27): Initial WordPress.org release (Anthropic Claude only)

## Roadmap

- v1.2 (planned): Gutenberg sidebar preview + bulk re-generate for existing posts
- v1.3 (planned): OpenAI-compatible endpoint support (DeepSeek, Mistral, Ollama)

## Architecture

The plugin uses a **Provider Adapter pattern** (`IProvider` interface) so adding new AI providers requires only a new adapter file, not changes to the core logic. See [`design/class-diagram.md`](design/class-diagram.md) for details.

## Few-shot prompt design

The differentiation is not in the code — it's in the **Few-shot example set**. See [`few-shot-examples.md`](few-shot-examples.md) for the 30 curated examples + 5 anti-patterns that train Claude on:

- English-web conventions (about-us, careers, contact, pricing)
- When to keep Japanese-specific terms in Romaji (補助金 program names, etc.)
- Stop word removal and verb-first conventions
- Stop words (the, a, of) and Japanese particles (の, は, を)

## Development

```bash
# Run prompt quality tests against each provider
ANTHROPIC_API_KEY=sk-ant-... python3 tests/test_prompt_v1.py
OPENAI_API_KEY=sk-proj-... python3 tests/test_prompt_v1_openai.py
GEMINI_API_KEY=AIza...     python3 tests/test_prompt_v1_gemini.py

# Pass threshold: 25/30 examples produce acceptable slugs.
# v1.1.0 verified: Claude 25+/30, OpenAI 27/30, Gemini 27/30.
```

## Privacy

Only the post title is sent to the AI provider. No post content, no user data, no site metadata.

## License

GPLv2 or later. See [LICENSE](LICENSE).

## Related plugins

- [NPC Manual Generator for ACF](https://wordpress.org/plugins/npc-manual-generator-for-advanced-custom-fields/) — Generate operation manuals from Advanced Custom Fields
- [NPC Site Doctor](https://github.com/npc-jp/npc-site-doctor) — WordPress maintenance health check tool

## Author

Built by [npc](https://n-pc.jp) — a freelance WordPress designer/developer based in Japan.
