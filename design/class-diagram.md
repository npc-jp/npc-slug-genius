# NPC Slug Genius クラス構成図

> Phase 0 T0-04 / 作成: 2026-05-12
> 目的: 実装Phase 1 で迷わないよう責務分割を事前に確定する
> 流用元: npc-wp-healthcheck（シングルトンMain+includes分割パターン）

---

## 1. 設計方針

1. **シングルトンMainクラス**: プラグイン全体のライフサイクル管理。`get_instance()` でアクセス
2. **責務分離**: 各クラスは単一責任。設定画面・スラッグ生成・APIアダプタを完全分離
3. **Provider Adapter パターン**: v0.1=Claude単体 / v0.2=OpenAI追加 / v0.3=Gemini追加 をファイル追加のみで実現
4. **依存性注入**: SlugGenerator は IProvider 実装クラスを受け取る（Provider切替が容易）
5. **WP標準フックのみ**: カスタムイベントは使わず `save_post` `admin_init` `admin_menu` 等で完結

---

## 2. ディレクトリ構造

```
npc-slug-genius/
├── npc-slug-genius.php              # プラグインヘッダー + Mainクラス起動
├── readme.txt                        # WP.org形式（英語）
├── uninstall.php                     # アンインストール時のクリーンアップ
├── .gitattributes                    # export-ignore設定（配布zip除外）
├── includes/
│   ├── class-main.php                # シングルトンMainクラス（ライフサイクル）
│   ├── class-settings-page.php       # 管理画面 > 設定 > NPC Slug Genius
│   ├── class-slug-generator.php      # save_postフックハンドラ・スラッグ生成コア
│   ├── class-admin-notices.php       # APIキー未設定時の警告等
│   └── providers/
│       ├── interface-provider.php    # IProvider 契約定義
│       ├── class-claude-adapter.php  # Anthropic Claude（MVP）
│       ├── class-openai-adapter.php  # OpenAI ChatGPT（v0.2）
│       └── class-gemini-adapter.php  # Google Gemini（v0.3）
├── prompts/
│   ├── system-prompt-v1.txt          # システムプロンプト本体
│   └── few-shot-examples.json        # Few-shot 30本+Anti-patterns 5本
├── templates/
│   └── settings.php                  # 設定画面のHTML
├── languages/
│   ├── npc-slug-genius.pot
│   ├── npc-slug-genius-ja.po
│   └── npc-slug-genius-ja.mo
└── assets/
    └── (将来のCSS/JS用・MVPでは空)
```

---

## 3. クラス一覧と責務

### 3.1 NPC_Slug_Genius（Mainクラス）

**ファイル**: `npc-slug-genius.php` 内 + `includes/class-main.php`
**責務**: プラグイン全体のライフサイクル管理

| メソッド | 役割 |
|---|---|
| `get_instance()` | シングルトンアクセサ |
| `load_dependencies()` | includes/配下のファイルrequire |
| `register_hooks()` | WPフック登録（admin_menu / save_post 等） |
| `activation()` | プラグイン有効化時のチェック（PHP≥7.4、WP≥5.0） |
| `get_api_key( $provider )` | 設定画面DB値を返却（プロバイダ別） |
| `get_active_provider()` | 設定画面で選択されているプロバイダ識別子を返却（'claude' / 'openai' / 'gemini'） |
| `get_target_post_types()` | 対象投稿タイプ配列を返却（デフォルト `['post','page']`） |
| `is_ai_available()` | アクティブプロバイダのAPIキーが設定済みかチェック |

**設計判断**:
- APIキー保存は DB（`get_option`）が主。wp-config.php定数オーバーライドも許容（既存npc-wp-healthcheckパターン踏襲）
- フィルタフック `npc_slug_genius_target_post_types` で開発者がカスタマイズ可能にする（拡張性）

---

### 3.2 IProvider インターフェース（契約）

**ファイル**: `includes/providers/interface-provider.php`
**責務**: 全Adapterが実装すべき共通契約

```php
interface NPC_Slug_Genius_Provider {

    /**
     * 日本語タイトルから英語SEOスラッグを生成
     *
     * @param string $title   投稿タイトル（日本語想定だが英語も許容）
     * @param array  $context オプション。将来拡張用（カテゴリ・タグ等）
     * @return string|WP_Error 生成されたスラッグ（英小文字・数字・ハイフンのみ）
     *                          またはエラー
     */
    public function generate_slug( $title, $context = array() );

    /**
     * このプロバイダの識別子
     *
     * @return string 'claude' / 'openai' / 'gemini' / 'openai-compatible'
     */
    public function get_id();

    /**
     * このプロバイダの表示名（設定画面で使用）
     *
     * @return string 'Anthropic Claude' / 'OpenAI ChatGPT' / 'Google Gemini'
     */
    public function get_display_name();

    /**
     * APIキーが設定されているか
     *
     * @return bool
     */
    public function has_api_key();
}
```

**設計判断**:
- 返却値は `string|WP_Error` で統一。例外は投げない（WPエコシステムの慣例）
- `generate_slug()` の戻り値は**生成済みの正規化済みスラッグ**（呼び出し側が再加工不要）
- 正規化（英小文字化・無効文字除去・連続ハイフン除去）は各Adapter内で完結

---

### 3.3 NPC_Slug_Genius_Claude_Adapter

**ファイル**: `includes/providers/class-claude-adapter.php`
**責務**: Anthropic Claude API へのHTTPリクエスト・レスポンス処理

| メソッド | 役割 |
|---|---|
| `__construct( $api_key )` | コンストラクタでAPIキーを受け取る |
| `generate_slug( $title, $context )` | IProvider実装。`wp_remote_post()` で `https://api.anthropic.com/v1/messages` を叩く |
| `get_id()` | `'claude'` |
| `get_display_name()` | `'Anthropic Claude'` |
| `has_api_key()` | コンストラクタで受けたキーが空でないかを返却 |
| `build_messages( $title )` (private) | システムプロンプト + Few-shot + user messageを構築 |
| `extract_slug( $api_response )` (private) | API応答テキストからスラッグ部分を抽出 |
| `normalize_slug( $raw )` (private) | 英小文字化・無効文字除去・連続ハイフン除去 |

**APIパラメータ**:
- `model`: `claude-haiku-4-20250514`（コスト最小化）
- `max_tokens`: 64（スラッグ生成には十分。レポート生成の2000から大幅削減）
- `temperature`: 0.3（再現性重視）
- `system`: システムプロンプト本体（prompts/system-prompt-v1.txt から読み込み）
- `messages`: `[{role:'user', content:'タイトル: 「{title}」'}]`

**エラーハンドリング**:
- HTTP通信失敗 → `WP_Error('api_connection_failed', ...)`
- HTTPステータス非200 → `WP_Error('api_error', ...)`
- 応答テキスト空 → `WP_Error('empty_response', ...)`
- 抽出スラッグが正規化後に空文字 → `WP_Error('invalid_slug', ...)`

---

### 3.4 NPC_Slug_Genius_OpenAI_Adapter（v0.2 / Phase 4）

**ファイル**: `includes/providers/class-openai-adapter.php`
**責務**: OpenAI Chat Completions API（および OpenAI互換エンドポイント）への呼び出し

**v0.1時点では実装しない**が、契約定義（IProvider）には準拠する設計を確定。

**追加パラメータ（v0.2実装時）**:
- `base_url`: デフォルト `https://api.openai.com/v1`、設定画面でカスタマイズ可能
- `model`: デフォルト `gpt-4o-mini`、設定画面で変更可能（OpenAI互換エンドポイント想定）

---

### 3.5 NPC_Slug_Genius_Gemini_Adapter（v0.3 / Phase 5）

**ファイル**: `includes/providers/class-gemini-adapter.php`
**責務**: Google Generative AI API への呼び出し

**v0.1時点では実装しない**が、契約定義（IProvider）には準拠。

---

### 3.6 NPC_Slug_Genius_Settings_Page

**ファイル**: `includes/class-settings-page.php`
**責務**: 管理画面 > 設定 > NPC Slug Genius のUI・保存処理

| メソッド | 役割 |
|---|---|
| `register()` | `admin_menu` フックで `add_options_page()` 登録 |
| `register_settings()` | `admin_init` フックで `register_setting()` 登録 |
| `render()` | `templates/settings.php` を読み込んで描画 |
| `sanitize_api_key( $input )` | APIキー入力のサニタイズ（`sanitize_text_field( wp_unslash( $input ) )`） |
| `sanitize_post_types( $input )` | 投稿タイプ配列のサニタイズ（`array_map( 'sanitize_key', ... )` + `post_type_exists()` 検証） |
| `sanitize_provider( $input )` | プロバイダ選択値のサニタイズ（許容値リストでホワイトリスト検証） |

**設定項目（v0.1）**:
| キー（DB） | 内容 |
|---|---|
| `npc_slug_genius_provider` | 使用プロバイダ識別子（v0.1は `'claude'` 固定だがUIは将来拡張前提） |
| `npc_slug_genius_api_key_claude` | Claude APIキー |
| `npc_slug_genius_post_types` | 対象投稿タイプ配列 |

**設計判断**:
- メニュー位置: `add_options_page` で「設定」配下（npc-acf-manual-generator は専用メニューだが、本プラグインは設定項目が少ないので設定配下）
- APIキーは画面でマスク表示（保存済みの場合は `••••••••` で表示、再入力で上書き）
- 保存後のフィードバックは WP標準の `settings_errors()` を使用

---

### 3.7 NPC_Slug_Genius_Slug_Generator

**ファイル**: `includes/class-slug-generator.php`
**責務**: `save_post` フックからスラッグ生成・更新までのワークフロー

| メソッド | 役割 |
|---|---|
| `register()` | `save_post` フック登録（priority: 20 = WP本体の後） |
| `on_save_post( $post_id, $post, $update )` | フックハンドラ。各種ガード→Adapter呼び出し→スラッグ更新 |
| `should_process( $post_id, $post )` (private) | 処理対象判定（投稿タイプ・revision/auto-draft除外・ユーザー権限） |
| `is_manually_set( $post_id )` (private) | postmeta `_npc_slug_genius_manual` を確認 |
| `update_post_slug( $post_id, $new_slug )` (private) | 無限ループ回避（`remove_action`→`wp_update_post`→`add_action`） |
| `mark_as_auto_generated( $post_id )` (private) | postmeta `_npc_slug_genius_auto` を保存（後の追跡用） |

**フロー**:
```
1. should_process() で対象判定
   ├─ NG → return（何もしない）
   └─ OK → 次へ
2. is_ai_available() でAPIキー確認
   ├─ NG → return（admin_notice側で警告済み）
   └─ OK → 次へ
3. is_manually_set() で手動設定確認
   ├─ True（保護中）→ return
   └─ False → 次へ
4. 現在のpost_nameが日本語/空 or 自動生成postmetaあり
   ├─ どちらでもない → return（既存の手動値を尊重）
   └─ いずれか該当 → 次へ
5. Adapter::generate_slug( $title ) 呼び出し
   ├─ WP_Error → log + return（投稿は壊さない）
   └─ success → 次へ
6. update_post_slug() で post_name 更新（無限ループ回避）
7. mark_as_auto_generated() でpostmeta保存
```

**手動上書き検知（M-04）**:
- Gutenberg/Classic Editor 共通: POST に `post_name` フィールドがあり、かつ既存の自動生成postmetaと異なる場合に「ユーザーが手動編集した」と判定
- 手動編集を検知したら `_npc_slug_genius_manual` postmetaを true に
- 以降の `save_post` では is_manually_set() でガードして上書きしない

---

### 3.8 NPC_Slug_Genius_Admin_Notices

**ファイル**: `includes/class-admin-notices.php`
**責務**: 管理画面の通知表示（WP.org審査対策で「自プラグインのページのみ」に限定）

| メソッド | 役割 |
|---|---|
| `register()` | `admin_notices` フック登録 |
| `maybe_show()` | 表示判定（自プラグインページか + APIキー未設定か） |
| `is_own_admin_page()` (private) | `get_current_screen()->id` で自プラグインページ判定 |
| `notice_api_key_missing()` (private) | APIキー未設定時のHTML出力（設定画面誘導リンク付き） |

**設計判断**:
- npc-acf-manual-generator のPended指摘で「admin_notices を自プラグイン外でも表示するな」と指摘された教訓を反映
- WP.org審査対策として `is_own_admin_page()` 判定を必ず通す

---

## 4. データフロー（典型的なケース）

### 4.1 新規投稿保存時（自動生成）

```
[ユーザー] Gutenbergで日本語タイトル入力 → 公開
   ↓
[WP] save_post フック発火
   ↓
[NPC_Slug_Genius_Slug_Generator::on_save_post]
   ↓
   should_process() OK
   ↓
   NPC_Slug_Genius::is_ai_available() OK
   ↓
   is_manually_set() False
   ↓
   現在のpost_nameは日本語 → 自動生成対象
   ↓
   NPC_Slug_Genius::get_active_provider() → 'claude'
   ↓
   ClaudeAdapter::generate_slug( $title )
   ├─ build_messages() でプロンプト構築
   ├─ wp_remote_post() で Anthropic API呼び出し
   ├─ extract_slug() で応答からスラッグ抽出
   └─ normalize_slug() で正規化
   ↓
   update_post_slug() で post_name 更新（無限ループ回避）
   ↓
   mark_as_auto_generated() で postmeta 保存
   ↓
[完了] スラッグが英語SEOスラッグに置換された状態で投稿が公開される
```

### 4.2 手動編集後の保存時（保護動作）

```
[ユーザー] 自動生成されたスラッグを手動で書き換え → 更新
   ↓
[save_post]
   ↓
[Slug_Generator::on_save_post]
   ↓
   is_manually_set() True または「現在のpost_nameが既存の自動生成値と異なる」を検知
   ↓
   _npc_slug_genius_manual = true を保存
   ↓
   return（スラッグは上書きしない）
   ↓
[完了] ユーザー編集が保護される
```

### 4.3 APIキー未設定時（安全動作）

```
[ユーザー] 投稿を保存
   ↓
[save_post]
   ↓
[Slug_Generator::on_save_post]
   ↓
   should_process() OK
   ↓
   is_ai_available() False
   ↓
   return（WP標準動作のまま：日本語パーマリンクが維持される）
   ↓
[別途] admin_notices で「APIキー未設定」警告（自プラグインページのみ）
```

---

## 5. v0.2/v0.3 でのプロバイダ追加手順

Provider Adapter契約を確定済みなので、新規プロバイダ追加は以下のみ:

1. `includes/providers/class-{name}-adapter.php` を新規作成（IProvider実装）
2. Main クラスの `get_active_provider()` 戻り値の許容リストに識別子追加
3. Settings_Page の `register_settings()` でAPIキー保存項目追加
4. `templates/settings.php` でプロバイダ選択UI追加
5. `readme.txt` で新プロバイダ訴求

既存ファイル（Slug_Generator・Main・Claude_Adapter等）には**一切変更不要**。Open/Closed原則を満たす。

---

## 6. 流用元との対応表

| 本プラグインのクラス | 流用元 | 流用内容 | 変更点 |
|---|---|---|---|
| `NPC_Slug_Genius`（Main） | npc-wp-healthcheck `NPC_WP_Healthcheck` | シングルトン構造・`get_api_key()` パターン | プロバイダ別キー取得に拡張 |
| `NPC_Slug_Genius_Claude_Adapter` | npc-wp-healthcheck `NPC_AI_Reporter` | `wp_remote_post()` でClaude API呼び出し | モデルをHaikuに / max_tokensを64に / IProvider実装 |
| `NPC_Slug_Genius_Settings_Page` | npc-acf-manual-generator `class-admin-page.php` | nonce・サニタイズ・`add_options_page` パターン | プロバイダ選択UI追加 |
| `NPC_Slug_Genius_Admin_Notices` | npc-acf-manual-generator `class-admin-page.php` admin_notices部分 | `is_own_admin_page()` 限定表示 | そのまま |
| `.gitattributes` | npc-acf-manual-generator | export-ignore | `vendor/**` 行は削除（本プラグインはvendor不要） |

---

## 7. テスト戦略（Phase 1 の T1-10 で実行）

| シナリオ | 期待動作 |
|---|---|
| S-01: 日本語タイトル新規投稿（自動生成対象タイプ） | 英語SEOスラッグが自動生成される |
| S-02: 英語タイトル新規投稿 | 既存のWP標準動作（タイトルそのままスラッグ化）で動く → 上書きされない、または同等品質のスラッグ生成 |
| S-03: APIキー未設定 | 投稿は壊れない・admin_noticeが出る・WP標準のスラッグ動作 |
| S-04: 手動でスラッグを書き換えて保存 | 以降 `_npc_slug_genius_manual` でロックされ自動生成されない |
| S-05: 対象投稿タイプ外（例: customer_post） | 自動生成されない |
| S-06: API失敗（不正キー） | WP_Errorが返り、投稿は壊れない・post_nameは元のまま |
| S-07: 連続投稿（10件） | rate limitに当たらない・無限ループ発生しない |
| S-08: タイトル空 | `should_process()` で除外、何も起きない |
| S-09: revision/auto-draft保存 | 除外、何も起きない |

---

*Phase 1 実装開始時にこのドキュメントの「クラス・メソッド名」「ファイルパス」を実装で参照する。設計から実装が逸れた場合はこのドキュメントを先に更新してから実装を続ける。*
