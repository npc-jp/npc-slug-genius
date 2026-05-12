#!/usr/bin/env python3
"""
T0-03 プロンプト動作検証スクリプト

system-prompt-v1.txt を Claude Haiku に投げて、Few-shot例示30本がどれだけ
期待通りのスラッグを生成するかを測定する。

合格基準: 30本中25本以上が許容範囲。

使い方:
  export ANTHROPIC_API_KEY=sk-ant-...
  python3 tests/test_prompt_v1.py

オプション:
  --model claude-haiku-4-20250514  (デフォルト)
  --temperature 0.3
  --output tests/results-v1.md
"""

import os
import re
import json
import sys
import time
import argparse
import urllib.request
import urllib.error
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SYSTEM_PROMPT_PATH = ROOT / "prompts" / "system-prompt-v1.txt"

# 30本+anti-patternsの判定セット
# acceptable_outputs に含まれる正解、または starts_with / contains / regex で
# 許容範囲のスラッグを受け付ける（自然なゆらぎを許容）。
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
    {"input": "無料トライアルのお申し込み", "acceptable": ["free-trial", "free-trial-signup", "start-free-trial", "sign-up-free-trial"], "category": "B"},
    {"input": "導入事例・お客様の声", "acceptable": ["case-studies", "testimonials", "customer-stories", "success-stories"], "category": "B"},
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
        "acceptable_regex": r"^jizokuka-(subsidy-)?(guide|overview|what-is)$|^jizokuka-hojokin-guide$",
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
        "acceptable_regex": r"^cat-appetite(-loss|-issues)?-(tips|guide|advice)$",
        "category": "D",
    },
    {
        "input": "法人設立の手続きを最短で完了させる方法",
        "acceptable_regex": r"^(how-to-)?(incorporate|setup-company|set-up-company|start-company)-(fast|quickly|guide)$|^company-incorporation-guide$",
        "category": "D",
    },
    # カテゴリE: リスト系
    {
        "input": "中小企業向けクラウド会計ソフト3選",
        "acceptable_regex": r"^(best-)?cloud-accounting(-software)?-for-(small-business|smb|smbs)$",
        "category": "E",
    },
    {
        "input": "SEOに強いWordPressテーマのおすすめ7選",
        "acceptable_regex": r"^best-seo-wordpress-themes?$|^best-wordpress-themes?-for-seo$",
        "category": "E",
    },
    {
        "input": "フリーランスが使うべき請求書作成ツール比較",
        "acceptable_regex": r"^(best-|compare-)?invoice-(tools?|software)-for-freelancers?$",
        "category": "E",
    },
    # カテゴリF: イベント・ニュース
    {
        "input": "2025年度 事業説明会 開催のお知らせ",
        "acceptable_regex": r"^(briefing-session|business-briefing|info-session)-?2025$|^2025-briefing-session$",
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
        "acceptable_regex": r"^dx-(training|education)(-program)?$|^dx-internal-training$",
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


def load_system_prompt() -> str:
    if not SYSTEM_PROMPT_PATH.exists():
        print(f"ERROR: system prompt not found at {SYSTEM_PROMPT_PATH}", file=sys.stderr)
        sys.exit(1)
    return SYSTEM_PROMPT_PATH.read_text(encoding="utf-8")


def call_claude(system_prompt: str, title: str, api_key: str, model: str, temperature: float) -> tuple[str, dict]:
    payload = {
        "model": model,
        "max_tokens": 64,
        "temperature": temperature,
        "system": system_prompt,
        "messages": [
            {"role": "user", "content": f"Title: {title}"},
        ],
    }
    req = urllib.request.Request(
        "https://api.anthropic.com/v1/messages",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "x-api-key": api_key,
            "anthropic-version": "2023-06-01",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            body = json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {e.code}: {err_body}")
    except urllib.error.URLError as e:
        raise RuntimeError(f"Network error: {e}")

    text = body.get("content", [{}])[0].get("text", "")
    return text.strip(), body


def normalize_slug(raw: str) -> str:
    """API応答からスラッグ部分のみ抽出して正規化（実装側と同じロジックを再現）"""
    # 改行で複数行なら1行目を採用
    first_line = raw.strip().splitlines()[0] if raw.strip() else ""
    # 引用符・余計な記号を除去
    cleaned = re.sub(r'["\'`]', "", first_line)
    # 英小文字・数字・ハイフン以外を空白扱いで除去 → 連続ハイフン圧縮 → 前後ハイフン除去
    cleaned = cleaned.lower()
    cleaned = re.sub(r"[^a-z0-9-]+", "-", cleaned)
    cleaned = re.sub(r"-+", "-", cleaned)
    cleaned = cleaned.strip("-")
    return cleaned


def judge(actual: str, case: dict) -> tuple[bool, str]:
    """許容範囲かどうかを判定。OK=True、NG=False"""
    if "acceptable" in case:
        if actual in case["acceptable"]:
            return True, f"exact match: {actual}"
        return False, f"got '{actual}', expected one of {case['acceptable']}"
    if "acceptable_regex" in case:
        if re.match(case["acceptable_regex"], actual):
            return True, f"regex match: {actual}"
        return False, f"got '{actual}', expected pattern {case['acceptable_regex']}"
    return False, "no judge criteria defined"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", default="claude-haiku-4-5-20251001")
    parser.add_argument("--temperature", type=float, default=0.3)
    parser.add_argument("--output", default=str(ROOT / "tests" / "results-v1.md"))
    parser.add_argument("--limit", type=int, default=0, help="先頭N件だけテスト（デバッグ用）")
    parser.add_argument("--sleep", type=float, default=0.5, help="リクエスト間スリープ（rate limit回避）")
    args = parser.parse_args()

    api_key = os.environ.get("ANTHROPIC_API_KEY")
    if not api_key:
        print("ERROR: set ANTHROPIC_API_KEY env var", file=sys.stderr)
        sys.exit(1)

    system_prompt = load_system_prompt()
    cases = TEST_CASES[: args.limit] if args.limit > 0 else TEST_CASES

    print(f"Running {len(cases)} test cases with model={args.model}, temperature={args.temperature}")
    print("=" * 80)

    results = []
    ok_count = 0
    for i, case in enumerate(cases, 1):
        try:
            raw, _ = call_claude(system_prompt, case["input"], api_key, args.model, args.temperature)
        except Exception as e:
            print(f"[{i:2d}/{len(cases)}] {case['input']:40s} -> ERROR: {e}")
            results.append({**case, "raw": "", "normalized": "", "ok": False, "reason": str(e)})
            continue
        normalized = normalize_slug(raw)
        ok, reason = judge(normalized, case)
        mark = "OK " if ok else "NG "
        print(f"[{i:2d}/{len(cases)}] {mark} {case['input'][:36]:36s} -> {normalized:40s}  ({reason})")
        if raw != normalized:
            print(f"           (raw response: {raw!r})")
        results.append({
            **case,
            "raw": raw,
            "normalized": normalized,
            "ok": ok,
            "reason": reason,
        })
        if ok:
            ok_count += 1
        time.sleep(args.sleep)

    print("=" * 80)
    print(f"PASS: {ok_count}/{len(cases)}  (threshold: 25/30 for v1 acceptance)")

    # results をmarkdownで保存
    out_path = Path(args.output)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    lines = []
    lines.append(f"# プロンプト動作検証結果 v1")
    lines.append("")
    lines.append(f"- 日時: {time.strftime('%Y-%m-%d %H:%M:%S')}")
    lines.append(f"- モデル: `{args.model}`")
    lines.append(f"- temperature: {args.temperature}")
    lines.append(f"- システムプロンプト: `prompts/system-prompt-v1.txt`")
    lines.append(f"- **結果: {ok_count}/{len(cases)} 合格**（しきい値: 25/30）")
    lines.append(f"- 判定: {'✅ 合格' if ok_count >= 25 else '⚠️ 要改善'}")
    lines.append("")
    lines.append("## カテゴリ別合格数")
    lines.append("")
    cat_stats = {}
    for r in results:
        c = r["category"]
        cat_stats.setdefault(c, [0, 0])
        cat_stats[c][1] += 1
        if r["ok"]:
            cat_stats[c][0] += 1
    lines.append("| カテゴリ | 合格/総数 |")
    lines.append("|---|---|")
    for c in sorted(cat_stats):
        ok_n, total = cat_stats[c]
        lines.append(f"| {c} | {ok_n}/{total} |")
    lines.append("")
    lines.append("## 詳細結果")
    lines.append("")
    lines.append("| # | カテゴリ | 入力 | 出力（正規化後） | 判定 | 備考 |")
    lines.append("|---|---|---|---|---|---|")
    for i, r in enumerate(results, 1):
        mark = "✅" if r["ok"] else "❌"
        raw_note = "" if r["raw"] == r["normalized"] else f"raw: `{r['raw']}`"
        lines.append(f"| {i} | {r['category']} | {r['input']} | `{r['normalized']}` | {mark} | {r['reason']} {raw_note} |")
    out_path.write_text("\n".join(lines), encoding="utf-8")
    print(f"\nResults saved to: {out_path}")

    sys.exit(0 if ok_count >= 25 else 2)


if __name__ == "__main__":
    main()
