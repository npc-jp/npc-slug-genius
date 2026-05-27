"""
プロンプト動作検証テストケース（プロバイダ間で共通）

30本のテストケース + 判定ルール。
各プロバイダ別テストランナーから import して使う。
"""

TEST_CASES = [
    # カテゴリA: コーポレート定型
    {"input": "会社案内", "acceptable": ["about-us", "about"], "category": "A"},
    {"input": "採用情報", "acceptable": ["careers", "jobs", "recruit"], "category": "A"},
    {"input": "お問い合わせ", "acceptable": ["contact", "contact-us"], "category": "A"},
    {"input": "プライバシーポリシー", "acceptable": ["privacy-policy", "privacy"], "category": "A"},
    {"input": "よくある質問", "acceptable": ["faq", "frequently-asked-questions"], "category": "A"},
    {"input": "会社概要", "acceptable": ["about", "about-us", "company", "about-company"], "category": "A"},
    # カテゴリB: サービス・料金系
    {"input": "料金・プラン", "acceptable": ["pricing", "plans", "pricing-plans"], "category": "B"},
    {"input": "サービス比較", "acceptable": ["compare-plans", "compare", "service-comparison", "compare-services"], "category": "B"},
    {
        "input": "無料トライアルのお申し込み",
        "acceptable_regex": r"^(apply-|start-|sign-up-|signup-)?free-trial(-signup|-application)?$|^free-trial$",
        "category": "B",
    },
    {
        "input": "導入事例・お客様の声",
        "acceptable_regex": r"^(case-studies|testimonials|customer-stories|success-stories)(-(case-studies|testimonials|client-testimonials|customer-stories))*$",
        "category": "B",
    },
    # カテゴリC: 補助金・支援事業（差別化の核）
    {
        "input": "ものづくり補助金の申請方法",
        "acceptable_regex": r"^(apply-)?monozukuri-subsidy(-(guide|application|how-to))?$|^how-to-apply-monozukuri-subsidy$",
        "category": "C",
    },
    {
        "input": "IT導入補助金の対象経費について",
        "acceptable_regex": r"^it-(hojyokin|hojokin|subsidy)-eligible-(costs|expenses)$|^it-(hojyokin|hojokin|subsidy)-(costs|expenses)-guide$",
        "category": "C",
    },
    {
        "input": "事業再構築補助金に採択された事例",
        "acceptable_regex": r"^(jigyou-saikouchiku|jigyo-saikochiku|saikouchiku)-(subsidy-)?(success-stories|case-studies|examples)$",
        "category": "C",
    },
    {
        "input": "小規模事業者持続化補助金とは",
        "acceptable_regex": r"^(what-is-)?jizokuka-(subsidy-?|hojokin-?)?(guide|overview|what-is)?$|^jizokuka-(subsidy|hojokin)$",
        "category": "C",
    },
    {
        "input": "地方創生に取り組む中小企業への支援制度",
        "acceptable_regex": r"^(regional-revitalization|chiho-sosei|chihousousei)-(support|grants?|programs?)$",
        "category": "C",
    },
    # カテゴリD: HowTo系
    {
        "input": "WordPressの使い方を初心者向けに解説",
        "acceptable_regex": r"^wordpress-(beginners?-guide|for-beginners|guide-for-beginners)$",
        "category": "D",
    },
    {
        "input": "Googleアナリティクス4の設定方法",
        "acceptable_regex": r"^(setup-|how-to-setup-|how-to-set-up-)?google-analytics-?4(-setup|-guide|-how-to)?$",
        "category": "D",
    },
    {
        "input": "確定申告をfreeeでかんたんに終わらせる方法",
        "acceptable_regex": r"^(tax-return|tax-filing|kakutei-shinkoku)-?(freee|with-freee)-?(guide|easy|simple)?$|^freee-tax-return-guide$",
        "category": "D",
    },
    {
        "input": "ネコの食欲不振を改善するための5つのポイント",
        "acceptable_regex": r"^(improve-)?cat-(appetite(-loss|-issues)?|loss-appetite|appetite-loss)-(tips|guide|advice)$",
        "category": "D",
    },
    {
        "input": "法人設立の手続きを最短で完了させる方法",
        "acceptable_regex": r"^(how-to-)?(incorporate|setup-company|set-up-company|start-company)-(fast|quickly|guide)$|^company-incorporation-guide$|^fastest-company-formation$",
        "category": "D",
    },
    # カテゴリE: リスト系
    {
        "input": "中小企業向けクラウド会計ソフト3選",
        "acceptable_regex": r"^(best-)?cloud-accounting(-software)?(-for)?-(small-business(es)?|smb|smbs|smes)$",
        "category": "E",
    },
    {
        "input": "SEOに強いWordPressテーマのおすすめ7選",
        "acceptable_regex": r"^best-seo-wordpress-themes?$|^best-wordpress-themes?-for-seo$",
        "category": "E",
    },
    {
        "input": "フリーランスが使うべき請求書作成ツール比較",
        "acceptable_regex": r"^(best-|compare-)?(invoice-(tools?|software)-for-freelancers?|freelance-invoice-(tools?|software))$",
        "category": "E",
    },
    # カテゴリF: イベント・ニュース
    {
        "input": "2025年度 事業説明会 開催のお知らせ",
        "acceptable_regex": r"^(briefing-session|business-briefing|info-session)-?2025$|^2025-(briefing-session|business-briefing|info-session)$",
        "category": "F",
    },
    {
        "input": "創業20周年記念キャンペーン",
        "acceptable_regex": r"^20th-anniversary(-campaign|-celebration)?$",
        "category": "F",
    },
    {
        "input": "新商品リリースのご案内",
        "acceptable": ["new-product-release", "new-product-launch", "product-release", "product-launch"],
        "category": "F",
    },
    # カテゴリG: 業界固有・難易度高め
    {
        "input": "インボイス制度対応の経理実務ガイド",
        "acceptable_regex": r"^invoice-system-(accounting|bookkeeping)-guide$|^invoice-system-guide$|^invoice-accounting-guide$",
        "category": "G",
    },
    {
        "input": "DX推進担当者向け社内研修プログラム",
        "acceptable_regex": r"^dx-(promotion-)?(training|education)(-program)?$|^dx-internal-training$",
        "category": "G",
    },
    {
        "input": "建設業許可申請の流れと必要書類一覧",
        "acceptable_regex": r"^construction-(permit|license)-application-(guide|requirements)$",
        "category": "G",
    },
    {
        "input": "ChatGPTをビジネスで活用するための社内ルール作り",
        "acceptable_regex": r"^chatgpt-business-(policy|policy-guide|use-policy|guidelines|rules)$|^chatgpt-(usage-)?policy(-guide)?$",
        "category": "G",
    },
]
