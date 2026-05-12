# NPC Slug Genius - Few-shot 例示リスト

> プロダクト差別化資産。Claude API プロンプトに組み込む例示セットの正典。
> 作成: 2026-05-08 / 総数: 30本 + Anti-patterns 5本

---

## 1. このドキュメントの位置付けと更新ルール

### 位置付け

このファイルは `npc-slug-genius` の**差別化の核**となる Few-shot 例示の管理ファイル。
競合（Google 翻訳ベースの SLUG TRANSLATER、OpenAI ベースの Ailo）との差別化は、
コードそのものよりも「どの例示をプロンプトに仕込むか」で決まる。
ここに蓄積された例示の質と量がプロダクトの資産になる。

### 例示の選定基準（3点）

1. **SEO 最適化が見える**: 翻訳エンジンが出す「直訳スラッグ」と明確に違う結果になること
2. **再現性がある**: 似た構造のタイトルに対して同じ判断基準が効くこと
3. **日本語固有概念を正しく扱っている**: ローマ字維持すべき語と英訳すべき語を正しく判断していること

### 更新ルール

- s-roots 試験運用（Phase 3）でスラッグ品質の問題が出たら **即時追加・差し替え**
- ユーザーからのフィードバック（レビュー・サポート問い合わせ）で「この変換おかしい」が複数来たら対象パターンを更新
- 1回の更新で追加する例示は最大 5 本（増やしすぎるとプロンプトトークンが重くなる）
- 追加した場合は末尾の「メンテ履歴」に日付と変更内容を記録する

---

## 2. Few-shot 例示 30 本

### カテゴリ A: コーポレート定型ページ（6 本）

これらは「翻訳すると直訳になる」代表格。英語圏での検索・慣例に沿った短い語を優先する。

```yaml
- input: "会社案内"
  output: "about-us"
  reasoning: "「company-introduction」は直訳として正しいが英語圏では about/about-us が標準慣例。会社概要・会社紹介も同じ扱い。"

- input: "採用情報"
  output: "careers"
  reasoning: "「recruitment-information」は直訳で6語相当の意味を詰め込みすぎ。英語圏の求人ページは careers/jobs が標準。採用募集・求人情報も同じ。"

- input: "お問い合わせ"
  output: "contact"
  reasoning: "「inquiry / contact-us / get-in-touch」のどれでも通るが最短の contact が最も汎用的で SEO 実績も高い。"

- input: "プライバシーポリシー"
  output: "privacy-policy"
  reasoning: "固有名詞として英語に定着しているため直訳が正解。ローマ字化（puraibashi-porishii）は絶対 NG。"

- input: "よくある質問"
  output: "faq"
  reasoning: "英語圏では FAQ が完全に定着した略語。「frequently-asked-questions」はフルスペルでも通るが、faq の方が短く認知度も高い。"

- input: "会社概要"
  output: "about"
  reasoning: "「会社案内」が about-us なら概要ページは about 単体で十分。同一サイト内に両方ある場合は about-company との使い分けも可だが、単独なら about が最もシンプル。"
```

### カテゴリ B: サービス・料金系（4 本）

価格・プラン系は英語 SaaS サイトの慣例に倣う。

```yaml
- input: "料金・プラン"
  output: "pricing"
  reasoning: "「fee-plan / price-plan」は和製英語的。英語圏の SaaS・サービスサイトでは pricing が標準。料金表・料金一覧も同じ。"

- input: "サービス比較"
  output: "compare-plans"
  reasoning: "「service-comparison」は直訳。英語ランディングページでは compare または compare-plans が定番。比較ページは動詞を前に出す形が SEO でも検索意図に合いやすい。"

- input: "無料トライアルのお申し込み"
  output: "free-trial"
  reasoning: "「free-trial-application」は長すぎ。申し込みページのスラッグは行動名詞（free-trial/sign-up/get-started）が SEO 的に自然。"

- input: "導入事例・お客様の声"
  output: "case-studies"
  reasoning: "「introduction-examples-customer-voices」は直訳で非現実的な長さ。英語圏では case-studies または testimonials が標準。両方の意味を含む場合は case-studies が包括的。"
```

### カテゴリ C: 補助金・支援事業（5 本）

固有名詞（事業名）はローマ字維持、一般動詞・目的は英訳する。これが競合との最大の差別化ポイント。

```yaml
- input: "ものづくり補助金の申請方法"
  output: "apply-monozukuri-subsidy"
  reasoning: "「monozukuri」は英訳不可能な日本語固有概念のため維持。「補助金」は subsidy で英訳。「申請方法」は動詞 apply で短く表現。競合は「monotsukuri-hojokin-no-shinsei-hoho」のようなローマ字になる。"

- input: "IT導入補助金の対象経費について"
  output: "it-hojyokin-eligible-costs"
  reasoning: "「IT導入補助金」は固有名称なのでローマ字（hojyokin）維持。対象経費 = eligible-costs は英訳する。全ローマ字（it-dounyu-hojyokin-no-taisho-keihi）にすると英語 SEO キーワードを全く拾えない。"

- input: "事業再構築補助金に採択された事例"
  output: "jigyou-saikouchiku-success-stories"
  reasoning: "「事業再構築補助金」は Government の特定プログラム名なのでローマ字維持が適切。「採択事例」は success-stories と意訳することで英語圏でも意味が通る。"

- input: "小規模事業者持続化補助金とは"
  output: "jizokuka-subsidy-guide"
  reasoning: "「持続化補助金」の正式名称が長いためコアワード「jizokuka」で略す。「〜とは」は説明・入門記事を示す guide で表現。全ローマ字より英語混じりの方が SEO に有利。"

- input: "地方創生に取り組む中小企業への支援制度"
  output: "regional-revitalization-support"
  reasoning: "「地方創生（chiho-sosei）」はローマ字維持の選択肢もあるが、英語圏の「regional revitalization」という表現で意味が通じるため英訳を優先する珍しいケース。支援制度は support で短縮。"
```

### カテゴリ D: ブログ記事タイトル - HowTo 系（5 本）

「〜の方法」「〜するコツ」系は動詞を前に出す英語 SEO の定石を使う。

```yaml
- input: "WordPressの使い方を初心者向けに解説"
  output: "wordpress-beginners-guide"
  reasoning: "「解説」は guide で表現。「初心者向け」は for-beginners よりも beginners-guide の名詞句にする方が短い。「how-to-use-wordpress-for-beginners」は長すぎ。"

- input: "Google アナリティクス4の設定方法"
  output: "setup-google-analytics-4"
  reasoning: "「設定方法」= how-to-setup を動詞 setup に短縮。ツール名は固有名詞なのでそのまま維持。「google-analytics-4-settei-hoho」は論外。"

- input: "確定申告をfreeeでかんたんに終わらせる方法"
  output: "tax-return-freee-guide"
  reasoning: "「確定申告」は tax-return（または tax-filing）で英訳。「freee」は固有名称なので維持。「かんたんに終わらせる方法」は guide で集約。全部英語化しようとすると「how-to-complete-tax-return-easily-with-freee」と長くなりすぎる。"

- input: "ネコの食欲不振を改善するための5つのポイント"
  output: "cat-appetite-loss-tips"
  reasoning: "「5つのポイント」は tips で集約（数字を URL に入れると記事更新時に齟齬が出るリスクがあるため省略推奨）。「食欲不振」は appetite-loss と端的に英訳。「neko」より「cat」が英語 SEO に有利。"

- input: "法人設立の手続きを最短で完了させる方法"
  output: "how-to-incorporate-fast"
  reasoning: "「法人設立手続き」は incorporate（法人化する）という動詞1語に集約できる。「最短で」は fast で表現。how-to 形式はハウツー記事の意図を明確にするので残す。"
```

### カテゴリ E: ブログ記事タイトル - リスト系（3 本）

「〜選」「〜のおすすめ」系。数字と best の組み合わせが SEO 定石。

```yaml
- input: "中小企業向けクラウド会計ソフト3選"
  output: "cloud-accounting-for-small-business"
  reasoning: "数字（3選）はタイトル変更で齟齬が出るため省略。「中小企業向け」は for-small-business と素直に表現（smb は Server Message Block プロトコルと被るので避ける）。E-3「invoice-tools-for-freelancers」と for-X 構造を揃えることで一貫性確保。ランキング・比較記事を示す best は付けても可。"

- input: "SEO に強い WordPress テーマのおすすめ7選"
  output: "best-seo-wordpress-themes"
  reasoning: "「おすすめ」= best。数字は省略。「SEOに強い」を best-seo の構造で表現。「wordpress-theme-osusume-7」のようなローマ字混じりは最悪パターン。"

- input: "フリーランスが使うべき請求書作成ツール比較"
  output: "invoice-tools-for-freelancers"
  reasoning: "「比較」は URL に入れると「compare-invoice-tools」でもいいが、ランディング意図が「比較して選ぶ」なら for-freelancers という対象絞り込みの方が検索流入に直結する。"
```

### カテゴリ F: イベント・ニュース（3 本）

日付・回数の扱いと「お知らせ」の処理が重要。

```yaml
- input: "2025年度 事業説明会 開催のお知らせ"
  output: "briefing-session-2025"
  reasoning: "「開催のお知らせ」= announcement だが URL には不要（ページの性質は briefing-session で伝わる）。年度は数字で維持するとアーカイブ管理が楽になる。「2025-nendo-jigyo-setsumeikai-kaisai-no-oshirase」は全ローマ字で論外。"

- input: "創業20周年記念キャンペーン"
  output: "20th-anniversary-campaign"
  reasoning: "記念系は anniversary が定番。数字は維持（20th）。「創業」は founding の意訳も可だが anniversary に含意されている。"

- input: "新商品リリースのご案内"
  output: "new-product-release"
  reasoning: "「ご案内」は URL に不要な敬語表現。release または launch で事足りる。「shin-shohin-release-no-goannai」は敬語語尾のローマ字化という最悪パターン。"
```

### カテゴリ G: 業界固有・難易度高め（4 本）

翻訳ツールが最も失敗しやすいカテゴリ。英訳すべきか / ローマ字維持すべきかの判断力が差別化ポイント。

```yaml
- input: "インボイス制度対応の経理実務ガイド"
  output: "invoice-system-accounting-guide"
  reasoning: "「インボイス制度」は日本の税制特有の制度名だが英語の「invoice system」で意味が通じる稀なケース（invoice は英語）。「経理実務」= accounting practice を accounting に圧縮。「制度対応」= compliance という訳も可だが guide に含意させる。"

- input: "DX推進担当者向け社内研修プログラム"
  output: "dx-training-program"
  reasoning: "「DX（デジタルトランスフォーメーション）」は略語として定着しているため DX 維持。「社内研修」= internal training を training に圧縮。「担当者向け」は URL に入れると長くなりすぎるので省略しページ本文で補う。"

- input: "建設業許可申請の流れと必要書類一覧"
  output: "construction-permit-application-guide"
  reasoning: "「建設業許可申請」= construction permit application。「流れと必要書類一覧」という複合的な内容を guide 1語に集約。「kensetsu-gyo-kyoka-shinsei-no-nagare-to-hitsuyoshorui-ichiran」は検索には全く使えない。"

- input: "ChatGPTをビジネスで活用するための社内ルール作り"
  output: "chatgpt-business-policy-guide"
  reasoning: "「ChatGPT」は固有名詞なので維持。「ビジネスで活用」= business use を business に集約。「社内ルール作り」= policy（内規・社内方針の意）。「chatgpt-wo-bijinesu-de-katsuyo-suru」のような外来語ローマ字混じりは避ける。"
```

---

## 3. Anti-patterns（やってはいけない例）5 本

プロンプトには含めないが、開発・テスト時の判定基準として使用する。

```yaml
anti-patterns:
  - input: "採用情報"
    bad-output: "recruitment-information"
    reason: "直訳NGパターン。6シラブルの長い直訳よりも英語圏慣例の careers を使う。「採用=recruitment」は辞書的に正しいが SEO では不利。"
    correct-output: "careers"

  - input: "お客様の声"
    bad-output: "okyakusama-no-koe"
    reason: "ローマ字化NGパターン。「声」を koe のままにするのは英語 SEO として無意味。testimonials / reviews / success-stories に意訳する。"
    correct-output: "testimonials"

  - input: "ものづくり補助金について"
    bad-output: "about-manufacturing-subsidy"
    reason: "固有名詞を勝手に英訳するNGパターン。「ものづくり補助金」は Government 固有の事業名なのでローマ字で維持する。manufacturing と訳すと別の補助金と区別がつかなくなる。"
    correct-output: "monozukuri-subsidy-guide"

  - input: "ブログ"
    bad-output: "b"
    reason: "過度な短縮で意味消滅パターン。1文字スラッグは WordPress のシステム予約語と競合する可能性があり、SEO 的にも意味を伝えられない。blog / news / articles が適切。"
    correct-output: "blog"

  - input: "2024年版 WordPressセキュリティ対策の最新まとめ"
    bad-output: "the-latest-comprehensive-guide-to-wordpress-security-measures-2024"
    reason: "stop words 残存 + 過剰な直訳パターン。the, to, latest（最新）, comprehensive（まとめ）は SEO スラッグに不要。5語以内に収める。measures も不要で security で事足りる。"
    correct-output: "wordpress-security-guide-2024"
```

---

## 4. プロンプト組み込み時の注意

### トークン消費の見積もり

全 30 本をそのままプロンプトに入れると約 **600〜800 トークン** を消費する（input + output のみ、reasoning 除く）。
Claude Haiku での利用コストは 1 回の変換あたり約 **$0.0002〜0.0003**（入出力合わせて 1,500〜2,000 トークン前後の想定）。

### 推奨: カテゴリバランスを保った 10〜12 本サンプリング

プロンプトのトークン節約のため、カテゴリから均等に選んだ 10〜12 本のサブセットを使うことを推奨する。
選定基準:
- コーポレート定型から 2 本（about-us, careers は必須）
- 補助金・固有名詞から 2 本（差別化が最も見えるカテゴリ）
- ブログ HowTo 系から 2 本
- ブログリスト系から 1 本
- 難易度高めから 2 本
- イベント・ニュースから 1 本

### プロンプトへの組み込み形式例

```
以下は日本語タイトルとSEO最適化された英語スラッグの対応例です。翻訳ではなく「英語圏の検索者がこのページを探す時に使う言い回し」を基準にしています。

会社案内 → about-us
採用情報 → careers
ものづくり補助金の申請方法 → apply-monozukuri-subsidy
WordPressの使い方を初心者向けに解説 → wordpress-beginners-guide
...（以降サンプリングした例示）

上記のルールに従い、次のタイトルのSEO最適化スラッグを1語〜5語のハイフン区切り英小文字で返してください。説明は不要です。スラッグのみ返してください。
```

### reasoning フィールドについて

各例示の reasoning はプロンプトに含めない。開発者・メンテナーが判断根拠を理解するための資産として本ファイルにのみ記録する。

---

## 5. メンテ方針と履歴

### 追加・更新のトリガー

| トリガー | 対応 |
|---|---|
| s-roots 試験運用で「変なスラッグが出た」報告 | 該当カテゴリの例示を修正または追加 |
| WP.org レビューに「〜の変換がおかしい」コメント | 1 週間以内に例示を更新してプラグインを更新 |
| 新しい補助金制度・固有名詞の問い合わせ増加 | カテゴリ C（補助金系）に実例を追記 |
| カテゴリ C が 10 本超になったら | 補助金専用プロンプト variant の作成を検討 |

### 変更履歴

| 日付 | 変更内容 | 担当 |
|---|---|---|
| 2026-05-08 | 初版作成 30 本 + Anti-patterns 5 本 | product-planner |

---

*このファイルは `npc-slug-genius` プロジェクトの差別化資産。外部公開しない。*
