# WP.org 審査チェックリスト

> Phase 0 T0-05 / 作成: 2026-05-12
> 目的: npc-acf-manual-generator のPended教訓を集約し、初回提出で1ターン通過を狙う
> 適用タイミング: Phase 1 実装中に都度参照 + Phase 2 T2-01 初回提出前に全項目チェック

---

## 0. 前提

このチェックリストは以下の lessons から構築:
- `~/npc-team/.claude/rules/lessons/wordpress.md` の [2026-05-05] WP.org審査保留対応の総ざらいチェックリスト
- `~/npc-team/.claude/rules/lessons/wordpress.md` の [2026-05-06] WP.org Pended再提出フロー
- メモリ `reference_wporg-plugin-submission.md`
- メモリ `project_npc-acf-manual-generator.md`

---

## 1. セキュリティ（Pended指摘の最頻パターン）

### 1.1 入力サニタイズ ✅ 必須

- [ ] **すべての `$_POST` / `$_GET` / `$_REQUEST` / `$_COOKIE` / `$_SERVER` を grep で洗い出す**
  ```bash
  grep -rnE '\$_(POST|GET|REQUEST|COOKIE|SERVER)' --include='*.php' .
  ```
- [ ] 受信直後に `sanitize_text_field( wp_unslash( $input ) )` または `sanitize_key()` でラップ
- [ ] `wp_unslash` のみは NG（スラッシュは外れるが特殊文字は素通り）
- [ ] APIキー等の機密入力でも同様にサニタイズ（漏洩は別問題だが審査では引っかかる）

### 1.2 出力エスケープ ✅ 必須

- [ ] HTML出力時に `esc_html()` / `esc_attr()` / `esc_url()` を必ず通す
- [ ] エラーメッセージ表示時もユーザー入力が含まれるなら `esc_html()`
- [ ] 設定値の表示（API応答含む）も同様

### 1.3 Nonce 検証 ✅ 必須

- [ ] 設定画面のフォームに `wp_nonce_field( 'npc_slug_genius_save', '_npc_slug_genius_nonce' )` を必ず入れる
- [ ] 保存処理で `check_admin_referer()` または `wp_verify_nonce()` で検証
- [ ] AJAX エンドポイント（あれば）でも nonce 検証

### 1.4 権限チェック ✅ 必須

- [ ] 設定画面・保存処理で `current_user_can( 'manage_options' )` を確認
- [ ] save_post フックでも投稿編集権限を確認（`current_user_can( 'edit_post', $post_id )`）

### 1.5 SQLインジェクション対策

- [ ] 直接 `$wpdb->query()` を使う場合は `$wpdb->prepare()` で必ずバインド
- [ ] 本プラグインではDB直接操作なし想定 → 該当箇所が出たらこのチェックを通す

---

## 2. 国際化（i18n）

### 2.1 ソース原文は英語に統一 ✅ 必須

- [ ] **プラグインヘッダー** の `Description` を英語で書く
- [ ] `__()` / `_e()` / `esc_html__()` / `esc_html_e()` / `esc_attr__()` の第1引数は**すべて英語**
- [ ] `readme.txt` 全文を英語で書く
- [ ] 出力側で使う日本語ラベル配列（type_labels等）も英語に統一
- [ ] **日本語版 readme は別ファイル `readme-ja.txt` に退避**し、配布zipに含めない

### 2.2 PHP class property の初期化式

- [ ] class property の初期化式に `__()` を直接書かない（定数式しか許可されない）
- [ ] 翻訳が必要な配列はコンストラクタ内で初期化する

```php
// NG
class Foo {
    private $labels = array( 'title' => __( 'Title', 'npc-slug-genius' ) );
}

// OK
class Foo {
    private $labels;
    public function __construct() {
        $this->labels = array( 'title' => __( 'Title', 'npc-slug-genius' ) );
    }
}
```

### 2.3 textdomain

- [ ] `Text Domain: npc-slug-genius` をプラグインヘッダーに記載（**スラッグと完全一致**）
- [ ] すべての `__()` 等で第2引数を `'npc-slug-genius'` に統一
- [ ] `load_plugin_textdomain()` の明示呼び出しは**削除**（WP 4.6+ では自動・WP.org配布なら自動ロード）

### 2.4 言語ファイル

- [ ] `languages/npc-slug-genius.pot` を生成（xgettext）
- [ ] `languages/npc-slug-genius-ja.po` を作成
- [ ] `languages/npc-slug-genius-ja.mo` をビルド（msgfmt）

```bash
xgettext --language=PHP --from-code=UTF-8 \
  --keyword=__ --keyword=_e \
  --keyword=esc_html__ --keyword=esc_html_e \
  --keyword=esc_attr__ --keyword=esc_attr_e \
  -o languages/npc-slug-genius.pot \
  $(find . -name '*.php' -not -path './languages/*' -not -path './node_modules/*')

msgfmt -o languages/npc-slug-genius-ja.mo languages/npc-slug-genius-ja.po
```

---

## 3. 管理画面の行儀

### 3.1 admin_notices は自プラグインのページのみ ✅ 必須

- [ ] `add_action( 'admin_notices', ... )` のクロージャ内で必ず `get_current_screen()->id` を確認
- [ ] 自プラグインのスラッグを含むページ以外は早期 return
- [ ] ガイドライン11 "Don't Hijack the Admin Dashboard" 違反を回避

```php
add_action( 'admin_notices', function() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'npc-slug-genius' ) === false ) {
        return;
    }
    // ここに通知HTML
} );
```

### 3.2 メニュー位置

- [ ] `add_options_page()` で「設定」配下に配置（独自メニューは設定項目が多い時のみ）
- [ ] メニュータイトル・ページタイトルを `__( 'NPC Slug Genius', 'npc-slug-genius' )` で英語化

### 3.3 アンインストール

- [ ] `uninstall.php` を用意して `delete_option()` で本プラグインのoptionをクリーンアップ
- [ ] postmeta `_npc_slug_genius_*` は残してOK（投稿の自動生成履歴として有用）

---

## 4. 配布zip生成

### 4.1 除外ファイル ✅ 必須

- [ ] `.gitattributes` で `export-ignore` 指定
  - `tests/`, `docs/`, `marketing/`, `_archive/`, `design/`, `prompts/` (※README含む全部)
  - `.github/`, `.git*`, `.DS_Store`
  - `README.md` (※プロジェクト内部用。WP.org は `readme.txt` のみ)
  - `readme-ja.txt`（日本語版がある場合）
  - `phpunit.xml*`, `phpcs.xml*`, `composer.json`（vendor使う時のみ）

### 4.2 不要ファイル混入チェック

- [ ] `unzip -l npc-slug-genius-{version}.zip` で以下が**含まれていない**ことを確認:
  - `.sh` / `.bat` / `.exe` （実行可能ファイル禁止）
  - `node_modules/`
  - `tests/`
  - `.git*`
  - `.DS_Store`
  - 開発用ドキュメント（`design/` `prompts/` `docs/`）

### 4.3 サイズ確認

- [ ] zip全体サイズ **10MB以下**（admin uploaderの制限）
- [ ] 8MB を超えそうなら Pended時にメール添付ではなく公開リンク（Google Drive等）で再提出する流れを覚悟

---

## 5. readme.txt

### 5.1 必須項目

- [ ] `Contributors:` （WP.orgアカウント名 `npc01`）
- [ ] `Tags:` （5個まで・SEO効果あるキーワード厳選）
- [ ] `Requires at least:` `5.0`
- [ ] `Tested up to:` 現在のWP最新版
- [ ] `Requires PHP:` `7.4`
- [ ] `Stable tag:` バージョン番号と完全一致
- [ ] `License:` `GPLv2 or later`
- [ ] `License URI:` `https://www.gnu.org/licenses/gpl-2.0.html`

### 5.2 説明文

- [ ] **英語のみ**で書く
- [ ] `== Description ==` セクションで差別化ポイント（"NOT translation, but SEO-optimized slug generation"）を明示
- [ ] **商標表記の罠回避**: 「for WordPress」「for X」と書かない（"Plugin Name" + ":" + 説明 で書く）
- [ ] APIキー取得手順のリンクを明記（Anthropic Console）

### 5.3 Plugin URI

- [ ] `Plugin URI:` を入れない or GitHubリポURL（**他WP.orgプラグインと重複NG**）

---

## 6. プラグインヘッダー

```php
<?php
/**
 * Plugin Name: NPC Slug Genius
 * Plugin URI: https://github.com/npc-jp/npc-slug-genius
 * Description: Automatically generates SEO-optimized English URL slugs from Japanese post titles using AI. BYOK (Bring Your Own Key) with support for Claude, OpenAI, and Gemini.
 * Version: 0.1.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: npc
 * Author URI: https://n-pc.jp
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: npc-slug-genius
 * Domain Path: /languages
 */
```

- [ ] Plugin Name は**スラッグと一致しない英語名**でOK（NPC Slug Genius / スラッグは npc-slug-genius）
- [ ] Description は140文字以内推奨（プラグインリストでの表示崩れ回避）
- [ ] **"Translator" を Description に入れない**（翻訳プラグインと誤認される）

---

## 7. コードの行儀（前回Pended指摘以外の一般ガイドライン）

### 7.1 ファイル直アクセス防止

- [ ] すべての PHP ファイル先頭に以下を入れる
```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

### 7.2 WP標準APIの優先

- [ ] HTTP通信は `wp_remote_post()` / `wp_remote_get()` を使う（curl直叩き禁止）
- [ ] JSON処理は `wp_json_encode()` / `json_decode()` を使う
- [ ] DB保存は `update_option()` / `get_option()` / `update_post_meta()` を使う
- [ ] ファイル操作は `WP_Filesystem` を使う（直接 `file_put_contents()` 禁止）

### 7.3 グローバル空間の汚染防止

- [ ] すべてのクラスを `NPC_Slug_Genius_` プレフィックス付き
- [ ] グローバル変数を作らない（必要なら `NPC_Slug_Genius_*` クラスのstaticプロパティ）
- [ ] 関数は最小限・必ず `npc_slug_genius_` プレフィックス

### 7.4 外部リソース

- [ ] CDN リンクで CSS/JS を読み込まない（GDPR等で問題視される）
- [ ] 必要な assets は plugin内 `assets/` に同梱して `wp_enqueue_*` で読み込む
- [ ] 外部APIへの送信は**ユーザーが明示的にAPIキー入力した時のみ**動作する設計

### 7.5 readme.txt の Description が exceed 警告対応

- [ ] Description セクションは250文字以内推奨（WP.org審査ボットがflag するケースあり）

---

## 8. WP.org 自動スキャナー対応

### 8.1 既知の警告パターン

- [ ] **`error_log()` の本番呼び出しを除去**（デバッグログ出力は条件分岐で開発時のみ）
- [ ] **`var_dump()` / `print_r()` / `console.log()` の残存禁止**
- [ ] **`eval()` 完全禁止**
- [ ] **`base64_encode/decode` の使用は最小限**（npc-acf-manual-generator で警告対応の前例あり）

### 8.2 スキャナーが flag する関数

- [ ] `system()`, `exec()`, `passthru()`, `shell_exec()` 完全禁止
- [ ] `curl_*` 直接使用禁止（`wp_remote_*` 経由のみ）
- [ ] `mail()` 直接使用禁止（`wp_mail()` のみ）

---

## 9. 提出フロー

### 9.1 初回提出（T2-03）

1. https://wordpress.org/plugins/developers/ にアクセス
2. WP.orgアカウント `npc01` でログイン
3. 「Submit a Plugin」をクリック
4. プラグインスラッグ `npc-slug-genius` を申請
5. zipを添付（8MB以下なら直接、超えるならGoogle Driveリンク）
6. Submit → 自動スキャナー実行（数分）→ 結果待ち
7. **自動スキャナーPassなら審査キューに入る**（最大1〜数週間）

### 9.2 Pended 受信時（T2-05）

- [ ] **メール本文に直接Reply**（Developer Information ページからは再提出不可）
- [ ] zipが8MB超なら**Google Drive共有リンク**で送る（メール添付は Base64 膨張で10MB制限超過する）
- [ ] レビュアー指摘項目を**ファイル名:行番号** で示しながら具体的・簡潔に返信
- [ ] AI生成文の冗長な前置きは削る（"Apologies for ...", "Thank you for your review ..." レベルは可・章立てした長文はNG）

### 9.3 Approved 受信時（T2-06）

- [ ] SVN リポジトリにアクセス: `https://plugins.svn.wordpress.org/npc-slug-genius/`
- [ ] `trunk/` にソース配置
- [ ] `tags/0.1.0/` でタグ打ち
- [ ] `assets/` に スクリーンショット・banner・icon を配置
- [ ] `svn commit` → WP.orgプラグインページに反映（数分後）

---

## 10. 初回提出前の最終チェック（T2-01）

下記すべてに ✅ がついてから提出する。

- [ ] セクション1（セキュリティ）全項目クリア
- [ ] セクション2（i18n）全項目クリア
- [ ] セクション3（管理画面行儀）全項目クリア
- [ ] セクション4（zip）全項目クリア
- [ ] セクション5（readme.txt）全項目クリア
- [ ] セクション6（プラグインヘッダー）全項目クリア
- [ ] セクション7（コード行儀）全項目クリア
- [ ] セクション8（自動スキャナー対応）全項目クリア
- [ ] dev.n-pc.jp で全機能 E2E 動作確認済み
- [ ] Few-shot プロンプトテスト 25/30以上 合格済み
- [ ] **配布zip の `unzip -l` で不要ファイル混入なし**を最終確認

---

*本チェックリストは npc-acf-manual-generator のPended指摘4種類（早期サニタイズ・英語UI・load_plugin_textdomain削除・admin_notices限定）を含む。提出後にPended通知が来たら lessons-learned に新パターンを追記する。*
