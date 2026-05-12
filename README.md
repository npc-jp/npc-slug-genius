# NPC Slug Genius

[![Plugin Version](https://img.shields.io/badge/version-0.1.0-blue.svg)](https://github.com/npc-jp/npc-slug-genius)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759b.svg)](https://wordpress.org)
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
- ✅ Provider Adapter architecture: more AI providers coming (OpenAI, Gemini)

## Installation

### From WordPress.org (coming soon)

Once approved on WordPress.org, install through `Plugins > Add New > Search "NPC Slug Genius"`.

### From this repository

1. Download the [latest release zip](https://github.com/npc-jp/npc-slug-genius/releases) or clone this repo
2. Upload to `/wp-content/plugins/npc-slug-genius/`
3. Activate the plugin
4. Go to **Settings > NPC Slug Genius**
5. Paste your [Anthropic API key](https://console.anthropic.com/settings/keys)
6. Choose target post types (default: posts and pages)

## How it works

1. When you save a post with a Japanese title, the plugin sends the title to Anthropic Claude API.
2. The AI returns an SEO-optimized English slug based on a curated prompt with 12 carefully-selected Few-shot examples.
3. The slug is set as the post URL.
4. If you manually edit the slug afterward, the plugin respects your choice and won't overwrite it.
5. If the API call fails or the API key is missing, the post saves normally (no errors).

## Cost

Each slug generation uses Claude Haiku (the fastest, cheapest model), costing roughly **$0.0002 to $0.0005 per post**. For most blogs, monthly cost is well under a dollar.

## Roadmap

- **v0.1** (current): Anthropic Claude support
- **v0.2** (planned): Add OpenAI ChatGPT support + OpenAI-compatible endpoints (DeepSeek, Mistral, Ollama)
- **v0.3** (planned): Add Google Gemini support
- **v0.4** (planned): Gutenberg sidebar preview + bulk re-generate for existing posts

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
# Run prompt quality tests against Claude API
ANTHROPIC_API_KEY=sk-ant-... python3 tests/test_prompt_v1.py

# Pass threshold: 25/30 examples produce acceptable slugs
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
