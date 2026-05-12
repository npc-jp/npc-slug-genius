# npc-slug-genius WBS

> 作成: 2026-05-08 / product-dev-support
> 対象スコープ: Phase 0〜2（MVP実装 + WP.org提出）。Phase 3は概要レベル。
> 前提: タスク粒度は半日（0.5d）〜 1日（1.0d）。バッファ20%を各Phase末に確保。

---

## Phase 0: 設計・Few-shot例示確定

**ゴール**: 実装を開始できる最小設計資料を揃える。プロンプトとクラス構成を確定し、WP.org審査対策を初回から仕込む方針を固める。

| ID | タスク | 目的 | 成果物 | 想定時間 | 依存 |
|---|---|---|---|---|---|
| T0-01 | Few-shot例示リスト作成（30本） | スラッグ生成の品質の核。「翻訳」でなく「SEO最適化」の基準を言語化する | `prompts/few-shot-examples.txt`（タイトル→スラッグ 30ペア） | 1.0d | - |
| T0-02 | プロンプトテンプレート設計 | APIへ送るシステムプロンプト + ユーザープロンプトを確定する | `prompts/system-prompt-v1.txt` | 0.5d | T0-01 |
| T0-03 | プロンプト動作検証（Claude Haiku直接テスト） | 設計したプロンプトがHaikuで想定通りのスラッグを返すか確認する。Few-shot例示30本中25本以上が許容範囲のスラッグになることをゲートとする | テスト記録（`prompts/test-results.md`）。合格なら確定・不合格ならT0-01/T0-02に差し戻し | 0.5d | T0-02 |
| T0-04 | クラス構成図の確定（**Provider Adapter契約含む**） | 実装Phase 1で迷わないよう責務分割を事前に決める。npc-wp-healthcheckのシングルトン+includesクラス分割パターンを踏襲。**`IProvider` インターフェース（`generate_slug( $title, $context ): string\|WP_Error` 契約）を最初に確定し、ClaudeAdapter/OpenAIAdapter/GeminiAdapterが共通実装する設計を図示。MVPはClaudeAdapterのみ実装するが、契約定義はv0.2/v0.3を見据えて完成させる** | `design/class-diagram.md`（クラス名・ファイル名・責務の一覧 + Provider Adapter契約定義） | 1.0d | - |
| T0-05 | WP.org審査対策方針の確認と適用チェックリスト作成 | npc-acf-manual-generatorのPended教訓（早期サニタイズ・英語UI・admin_notices限定・load_plugin_textdomain削除）をPhase 1の実装ルールとして文書化する | `design/wporg-checklist.md` | 0.5d | - |
| T0-06 | Phase 0バッファ | 検証差し戻し・追加例示・設計見直し吸収用 | - | 0.5d | T0-01〜T0-05 |

**Phase 0 合計**: 実質4.0d + バッファ0.5d = **4.5d（約1週間）**

**Phase 0 完了ゲート**:
- `few-shot-examples.txt` が30本確定
- プロンプトテスト合格（25/30以上）
- クラス構成図が確定
- WP.org審査チェックリストが作成済み

---

## Phase 1: MVP実装

**ゴール**: M-01〜M-06の機能がすべてdev.n-pc.jpで動作確認できる状態にする。

| ID | タスク | 目的 | 成果物 | 想定時間 | 依存 |
|---|---|---|---|---|---|
| T1-01 | プラグイン骨格の作成 | ファイル/ディレクトリ構造、定数定義、シングルトンMainクラスの起動フローを確立する。npc-wp-healthcheckの構造をベースに流用 | `npc-slug-genius.php`（ヘッダー・定数・クラスロード・activation hook）、ディレクトリ構造（includes/ languages/ assets/） | 0.5d | T0-04 |
| T1-02 | ClaudeAdapter実装（Provider契約に準拠） | `wp_remote_post()` でAnthropic APIを呼び出すアダプタクラス。`IProvider` インターフェースを実装。npc-wp-healthcheck `class-ai-reporter.php` のAPIコール部分（api_url・headers・body構造）をほぼそのまま流用し、モデルをHaikuに変更。**v0.2でOpenAIAdapter / v0.3でGeminiAdapterを追加する際に既存ファイルを変更せず追加のみで対応できる構造にする** | `includes/providers/interface-provider.php`（契約定義） + `includes/providers/class-claude-adapter.php`（`generate_slug( $title )` メソッド、WP_Error返却パターン） | 1.0d | T1-01, T0-04 |
| T1-03 | APIキー設定画面の実装（M-03） | 管理画面 > 設定 > NPC Slug Genius でAPIキー入力・DB保存・マスク表示。設定ページUIはnpc-acf-manual-generatorの管理画面パターンを踏襲。admin_noticesの表示は自プラグインのページのみに限定（WP.org審査対策） | `includes/class-settings-page.php`、`templates/settings.php`、`get_option('npc_slug_genius_api_key')` | 1.0d | T1-01 |
| T1-04 | 対象投稿タイプ選択機能（M-06） | 設定画面に投稿タイプのチェックボックス一覧を追加。デフォルトはpost/page。選択状態をDB保存 | 設定画面に投稿タイプ選択UIを追加（T1-03に統合）、`get_option('npc_slug_genius_post_types', ['post','page'])` | 0.5d | T1-03 |
| T1-05 | save_postフックとスラッグ自動生成コアの実装（M-01・M-02） | 保存時にClaudeを呼んでスラッグを生成する中核ロジック。無限ループ回避（`remove_action`でフック一時解除 + `wp_update_post`）、対象投稿タイプ判定、APIキー未設定チェックを含む | `includes/class-slug-generator.php`（`on_save_post()` ハンドラ、スラッグ正規表現後処理を含む） | 1.0d | T1-02, T1-04 |
| T1-06 | 手動スラッグ保護フラグの実装（M-04） | Gutenbergでユーザーが手動でスラッグを変更した場合に上書きしない仕組み。`_npc_slug_genius_manual` postmetaフラグ方式で実装。REST APIのスラッグ更新検知を含む動作確認が必要 | `class-slug-generator.php` に保護判定ロジック追加、postmetaフラグ保存/参照 | 1.0d | T1-05 |
| T1-07 | API無応答時の安全動作とadmin_notice警告（M-05） | APIキー未設定時にadmin_noticeで設定画面へ誘導。API呼び出し失敗時はスラッグ変更せず通常動作継続。フォールバックはWP標準（日本語パーマリンクのまま）とする | admin_notice（設定画面誘導リンク付き）、エラーハンドリングがT1-05に統合済み | 0.5d | T1-05, T1-03 |
| T1-08 | 英語UIと日本語言語ファイルの整備 | WP.org審査要件。すべての表示文字列を `__()` / `esc_html__()` で英語として定義し、日本語翻訳は `.po/.mo` ファイルで提供 | `languages/npc-slug-genius.pot`、`languages/npc-slug-genius-ja.po`、`languages/npc-slug-genius-ja.mo` | 0.5d | T1-03, T1-07 |
| T1-09 | readme.txtの英語版作成 | WP.org提出必須。npc-acf-manual-generatorのreadme.txtを雛形として流用。サニタイズ・セキュリティ・BYOKの説明を含める | `readme.txt`（WP.org形式・英語） | 0.5d | T0-01, T0-05 |
| T1-10 | dev.n-pc.jpでのEnd-to-End動作確認 | 実際のWP環境で全M-01〜M-06が意図通り動作するか手動確認。確認シナリオに沿ってテストする | `design/test-results.md`（テストシナリオと結果） | 0.5d | T1-01〜T1-09 |
| T1-11 | Phase 1バッファ | 手動スラッグ保護の挙動調整・プロンプト微調整・テスト差し戻し吸収用 | - | 1.0d | T1-01〜T1-10 |

**Phase 1 合計**: 実質8.0d + バッファ1.0d = **9.0d（約2週間）**

**Phase 1 完了ゲート**:
- dev.n-pc.jpで日本語タイトルから英語スラッグが自動生成されることを確認
- 手動スラッグを設定した投稿が上書きされないことを確認
- APIキー未設定時にadmin_noticeが表示されサイトが壊れないことを確認
- 対象投稿タイプ設定が機能することを確認

---

## Phase 2: WP.org提出・審査

**ゴール**: WP.orgの審査を通過し、プラグインが公式ディレクトリから入手可能な状態にする。審査待ち時間（1〜3週間）はPhase 3（s-roots試験運用）と並行して進める。

| ID | タスク | 目的 | 成果物 | 想定時間 | 依存 |
|---|---|---|---|---|---|
| T2-01 | WP.org審査チェックリストの最終確認 | 初回提出前にT0-05で作成したチェックリストを全項目通す。前回Pended指摘の4種類（早期サニタイズ・英語UI・load_plugin_textdomain削除・admin_notices限定）を特に重点確認 | チェックリスト完了記録 | 0.5d | T1-01〜T1-09, T0-05 |
| T2-02 | 配布zip生成と除外確認 | `.gitattributes` のexport-ignore設定でビルドzipから不要ファイルを除外。npc-acf-manual-generatorの `.gitattributes` パターンを流用。`unzip -l` で `.sh` / `node_modules` / `tests` 等が混入していないことを確認 | `npc-slug-genius-{version}.zip`（配布用）、`.gitattributes` | 0.5d | T2-01 |
| T2-03 | WP.org初回提出 | `https://wordpress.org/plugins/developers/` の「Submit a Plugin」からzip提出。プラグインスラッグ `npc-slug-genius` を申請 | 提出完了（受付番号・待ち行列確認） | 0.5d | T2-02 |
| T2-04 | 審査待ち期間中のs-roots並行運用（参照: Phase 3） | 審査待ち1〜3週間を無駄にしない。s-roots.comにZIP直接インストールして試験運用を開始する（Phase 3 T3-01と同時着手） | Phase 3側の成果物参照 | - | T2-03 |
| T2-05 | Pended通知受信時の即時対応 | レビュアーからのPended指摘メールを受信したら指摘項目を整理し、対応版を作成して返信する。**返信先はPendedメール本文へのReply**（Developer Informationページから再提出するルートは存在しない）。zipサイズが8MB超なら添付せずGoogle Driveリンクで提出 | 修正版zip・返信メール | 0.5〜1.0d | T2-03（Pended発生時のみ） |
| T2-06 | Approved後のSVN初回コミット | 審査通過後にWP.orgのSVNリポジトリへ初回コミット。`trunk/` にソースを配置し `tags/{version}/` でタグを打つ | SVNコミット完了、WP.orgプラグインページの公開確認 | 0.5d | T2-03（Approved受信後） |
| T2-07 | Phase 2バッファ（Pended再提出往復の吸収） | Pended指摘が2往復以上発生した場合の吸収。前回のnpc-acf-manual-generatorは1往復で通過したが安全側に1.0d確保 | - | 1.0d | T2-01〜T2-06 |

**Phase 2 合計（実作業分）**: 実質2.0〜3.0d + バッファ1.0d = **3.0〜4.0d**
**審査待ち期間**: 1〜3週間（受動的待ち。この間はPhase 3を進める）

**Phase 2 完了ゲート**:
- WP.orgからApproved通知を受信
- SVNコミット完了
- `https://wordpress.org/plugins/npc-slug-genius/` が公開状態

---

## Phase 3: s-roots試験運用（概要レベル）

Phase 2審査待ち期間（T2-04）と並行して進める。審査結果に依存しないためZIP直接インストールで実施可能。

| ID | タスク | 概要 | 期間目安 |
|---|---|---|---|
| T3-01 | s-roots.comへのZIPインストールと初期設定 | Phase 1完了時点のZIPを直接インストール。APIキー設定・対象投稿タイプ設定 | 0.5d |
| T3-02 | 既存補助金系記事5〜10本でスラッグ品質検証 | 生成されたスラッグを記録し、許容範囲か確認。問題パターンをプロンプト改善にフィードバック | 1.0〜2.0d |
| T3-03 | 新規投稿での本運用開始 | s-roots管理者が通常投稿する中でスラッグ自動生成を利用。異常動作が発生しないことを確認 | 2〜4週間（並行モニタリング） |
| T3-04 | フィードバック集約と次バージョン（Should機能）への反映 | 実運用で得た改善点をS-01〜S-05の優先度判断材料にする | 0.5d |

---

## Phase 4: v0.2 OpenAI（ChatGPT）追加（概要レベル）

Phase 3完了後 or 並行。Provider Adapter契約が確定済みなので追加コストは小さい。

| ID | タスク | 概要 | 期間目安 |
|---|---|---|---|
| T4-01 | OpenAIAdapter実装 | `IProvider` 契約に準拠してOpenAI API呼び出しクラス追加。base_urlをユーザー設定可能にし、OpenAI互換エンドポイント（DeepSeek/Mistral/Ollama等）も使えるように | 1.0d |
| T4-02 | 設定画面のプロバイダ選択UI追加 | ラジオボタン or セレクトボックスで「Claude / OpenAI」を選択。各プロバイダごとのAPIキー欄を表示切替 | 0.5d |
| T4-03 | プロンプト動作検証（OpenAI gpt-4o-mini） | Few-shot 30本のテストセットで品質確認。25/30以上で合格 | 0.5d |
| T4-04 | readme.txt更新 + 動作確認 + WP.org SVN push | マルチプロバイダ対応の訴求を追記。SVNでv0.2を公開 | 0.5d |

**Phase 4 合計**: **2.5d（約1週間 / 待ち含む）**

---

## Phase 5: v0.3 Gemini追加（概要レベル）

Phase 4と同じ構造。Geminiは無料枠が太いので一般ユーザー獲得が主目的。

| ID | タスク | 概要 | 期間目安 |
|---|---|---|---|
| T5-01 | GeminiAdapter実装 | Google Generative AI APIアダプタ。Gemini APIはOpenAI形式と若干異なるので別実装 | 1.0d |
| T5-02 | 設定画面に「Gemini」選択肢追加 | T4-02のUIに3つ目のオプション追加 | 0.25d |
| T5-03 | プロンプト動作検証（Gemini Flash） | Few-shot 30本のテストセットで品質確認 | 0.5d |
| T5-04 | readme.txt更新 + WP.org SVN push | 3プロバイダ揃った状態の訴求にreadme.txtを再構成 | 0.5d |

**Phase 5 合計**: **2.25d（約1週間 / 待ち含む）**

---

## クリティカルパス

```
T0-01（Few-shot作成）
  → T0-02（プロンプト設計）
    → T0-03（プロンプトテスト） ← ここで差し戻しリスクあり
      → T1-01（プラグイン骨格）
        → T1-02（APIクライアント）
          → T1-05（save_postコア）
            → T1-06（手動保護フラグ） ← 挙動確認で追加時間リスクあり
              → T1-10（E2E動作確認）
                → T2-01（審査チェックリスト最終確認）
                  → T2-02（zip生成）
                    → T2-03（WP.org提出）
                      → [審査待ち1〜3週間]
                        → T2-05（Pended対応）または T2-06（SVNコミット）
```

**並列実行可能なタスクの組み合わせ**:

- T0-04（クラス構成図）とT0-01/T0-02/T0-05（プロンプト設計系）は依存関係なし → 並列可
- T1-03（設定画面）とT1-05（save_postコア）は、T1-01完了後に並列着手可。ただしT1-04（投稿タイプ選択）がT1-03に依存するため、T1-05はT1-04待ちになる。設定画面を先行して完成させてからT1-05に集中するシーケンシャル進行が安全
- T1-08（言語ファイル）とT1-09（readme.txt）は、T1-03/T1-07の表示文字列が確定した後なら並列可
- T2-03（WP.org提出）後のPhase 2審査待ち期間中にPhase 3（T3-01〜T3-02）を並列開始可

**ボトルネック**:
1. **T0-03 プロンプトテスト**: Few-shot例示の品質が低いと差し戻しが発生し、Phase 0全体が1週間延びる可能性。T0-01に最も時間と思考を投入することがPhase全体の品質を左右する
2. **T1-06 手動スラッグ保護**: GutenbergのREST API経由スラッグ変更の検知は実装してみないと挙動が見えない部分がある。バッファT1-11はこのタスクの調整に充当する想定
3. **WP.org審査待ち**: 前例（npc-acf-manual-generatorで600+待ち）があるため1〜3週間は見込む。実作業はほぼないが、Pendedメールには48時間以内に対応すること

---

## 想定リスクとリカバリ

| リスク | 発生確率 | 影響 | リカバリ手段 |
|---|---|---|---|
| **プロンプトテスト不合格（T0-03）**: 30本中25本未満が許容範囲外のスラッグになる | 中 | Phase 0が+0.5〜1.0d延びる | Few-shot例示を差し戻してパターンを追加・修正。プロンプトのルール文言（スラッグ長上限・固有名詞ルール）を調整して再テスト |
| **手動スラッグ保護の誤動作（T1-06）**: 保護フラグが機能せず自動上書きが止まらない、またはフラグが消えない | 中 | T1-06に+0.5d追加 | `$_POST` の値確認とGutenbergのREST APIリクエスト構造を実機ログで確認。postmetaの書き込みタイミングと`save_post`フック優先度を調整 |
| **WP.org Pended（既知4パターン）**: 早期サニタイズ漏れ・英語UI漏れ・admin_notices限定違反・load_plugin_textdomain残存 | 中（T0-05で事前対策済みなら低） | T2-05で1〜5d対応 | T0-05のチェックリストを初回提出前に全項目通していれば低減可能。Pended返信はPendedメール本文へのReplyで行い、zipが8MB超なら添付せずGoogle Driveリンクで提出 |
| **save_postフックの無限ループ**: `wp_update_post()`でフックが再帰呼び出しされてPHP実行時間超過 | 低 | dev環境でサイトが壊れる（本番影響なし） | `remove_action` で自フックを一時解除してから `wp_update_post()` を呼び出す実装パターンを最初から採用（T1-05） |
| **npc-acf-manual-generator審査対応との工数競合**: 同プラグインのPended返信作業が本プロジェクトと重なる | 高（発生確率として） | 本プロジェクトが数日スロー | 企画書記載の優先順位（WID保守月次 > acf-generator審査 > 本プロジェクト）に従い、acf-generator対応を優先してから本プロジェクトに戻る |
| **Haiku APIのレート制限**: 連続投稿テスト時に429エラーが頻発 | 低 | テスト時間が延びる | テストは連続投稿ではなく1件ずつ手動確認に切り替え。M-05のフォールバック（日本語スラッグのまま継続）が正常動作することを確認する機会として活用 |

---

## 既存資産流用マップ

| 流用元 | 流用するコード・パターン | 使用するタスク | 注意点 |
|---|---|---|---|
| **npc-wp-healthcheck `class-ai-reporter.php`** | `wp_remote_post()` でAnthropic APIを呼ぶパターン（api_url・headers構造・WP_Error返却・HTTPステータスコード判定） | T1-02（APIクライアントクラス） | モデル名を `claude-haiku-4-20250514`（Haiku）に変更する。max_tokensはスラッグ生成なので64〜128で十分（レポート生成の2000から大幅削減） |
| **npc-wp-healthcheck `npc-wp-healthcheck.php`** | シングルトンMainクラスの構造（`get_instance()`・`load_dependencies()`・フック登録パターン）、`get_api_key()` の定数優先+DBフォールバック設計 | T1-01（プラグイン骨格）、T1-03（設定画面） | APIキー保存方式は本プラグインでは設定画面（DB保存）を主とする。wp-config.php定数でのオーバーライドも可として設計する |
| **npc-wp-healthcheck `npc-wp-healthcheck.php`** | `is_ai_available()` パターン（APIキー存在チェック + フィルタ対応） | T1-07（API無応答時の安全動作） | そのままコピーせず、プラグイン固有の関数名に変更する |
| **npc-acf-manual-generator `class-admin-page.php`** | 設定画面のHTML構造（nonce・manage_optionsチェック・`sanitize_text_field( wp_unslash(...) )` パターン）、admin_noticesの「自プラグインページのみ表示」実装 | T1-03（設定画面）、T1-07（admin_notices） | メニュー位置は `add_menu_page` ではなく `add_options_page`（設定 > NPC Slug Genius）に変更する |
| **npc-acf-manual-generator `npc-acf-manual-generator.php`** | プラグインヘッダーの英語テンプレート・定数定義パターン・activation hookでのPHPバージョンチェック | T1-01（プラグイン骨格） | `Text Domain` はプラグインスラッグ `npc-slug-genius` と一致させる |
| **npc-acf-manual-generator `.gitattributes`** | `export-ignore` によるビルドzip除外設定（tests/ docs/ .github/ 等） | T2-02（zip生成） | 本プラグインはvendor/不要なので `vendor/**/tests/` 等の行は不要 |
| **npc-acf-manual-generator `languages/*.pot`** | `.pot` ファイル生成コマンドのパターン（`xgettext` でPHPソースをスキャン） | T1-08（言語ファイル） | textdomainは `npc-slug-genius` に変更する |
| **`lessons/wordpress.md` WP.org審査チェックリスト** | Pended4パターン（早期サニタイズ/英語UI/load_plugin_textdomain削除/admin_notices限定）と対応手順 | T0-05（チェックリスト作成）、T2-01（最終確認） | zip添付の8MB超過問題（Base64膨張でSMTP制限超過）と、Pended返信はメール本文へのReplyが正規フローであることを特に記録する |

---

## スプリント計画（1スプリント = 1週間・個人開発ペース）

| スプリント | 期間目安 | 対象タスク | 完了判定 |
|---|---|---|---|
| Sprint 1 | 1週間 | T0-01〜T0-06（Phase 0全体） | Few-shot30本確定・プロンプトテスト合格・クラス設計確定 |
| Sprint 2 | 1週間 | T1-01〜T1-05（骨格・APIクライアント・設定画面・save_postコア） | dev.n-pc.jpでスラッグが自動生成されることを確認 |
| Sprint 3 | 1週間 | T1-06〜T1-11（手動保護・admin_notice・言語ファイル・readme・E2E確認・バッファ） | Phase 1完了ゲート全項目クリア |
| Sprint 4 | 1週間 | T2-01〜T2-03（審査チェックリスト・zip生成・WP.org提出）+ T3-01開始 | 提出完了・s-roots試験運用開始 |
| Sprint 5〜7 | 1〜3週間 | 審査待ち（T2-04）+ T3-02〜T3-03（並行運用）+ T2-05/T2-06（Pended対応またはApproved後SVN） | WP.org Approved・SVNコミット完了 |

**全体スケジュール目安**: Phase 0〜2の実作業は約4スプリント（4週間）。審査待ちが最大3週間追加で発生しうるため、WP.org公開まで最短4週・最長7週を見込む。

---

*このWBSはPhase 0開始前の計画。T0-03プロンプトテストの結果次第でPhase 1タスク（特にT1-05・T1-06）の難易度見積もりを更新すること。*
