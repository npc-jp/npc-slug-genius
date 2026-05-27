#!/usr/bin/env python3
"""
v1.1 プロンプト動作検証スクリプト（OpenAI ChatGPT版）

system-prompt-v1.txt を OpenAI gpt-4o-mini に投げて、30本のテストケースで
合格基準 25/30 を満たすかを測定する。

使い方:
  export OPENAI_API_KEY=sk-proj-...
  python3 tests/test_prompt_v1_openai.py

オプション:
  --model gpt-4o-mini  (デフォルト)
  --temperature 0.3
  --output tests/results-v1-openai.md
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

sys.path.insert(0, str(ROOT / "tests"))
from test_cases import TEST_CASES  # noqa: E402


def load_system_prompt() -> str:
    if not SYSTEM_PROMPT_PATH.exists():
        print(f"ERROR: system prompt not found at {SYSTEM_PROMPT_PATH}", file=sys.stderr)
        sys.exit(1)
    return SYSTEM_PROMPT_PATH.read_text(encoding="utf-8")


def call_openai(system_prompt: str, title: str, api_key: str, model: str, temperature: float) -> tuple[str, dict]:
    payload = {
        "model": model,
        "max_tokens": 64,
        "temperature": temperature,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": f"Title: {title}"},
        ],
    }
    req = urllib.request.Request(
        "https://api.openai.com/v1/chat/completions",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {api_key}",
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

    text = body.get("choices", [{}])[0].get("message", {}).get("content", "")
    return text.strip(), body


def normalize_slug(raw: str) -> str:
    first_line = raw.strip().splitlines()[0] if raw.strip() else ""
    cleaned = re.sub(r'["\'`]', "", first_line)
    cleaned = cleaned.lower()
    cleaned = re.sub(r"[^a-z0-9-]+", "-", cleaned)
    cleaned = re.sub(r"-+", "-", cleaned)
    cleaned = cleaned.strip("-")
    return cleaned


def judge(actual: str, case: dict) -> tuple[bool, str]:
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
    parser.add_argument("--model", default="gpt-4o-mini")
    parser.add_argument("--temperature", type=float, default=0.3)
    parser.add_argument("--output", default=str(ROOT / "tests" / "results-v1-openai.md"))
    parser.add_argument("--limit", type=int, default=0, help="先頭N件だけテスト（デバッグ用）")
    parser.add_argument("--sleep", type=float, default=0.5, help="リクエスト間スリープ（rate limit回避）")
    args = parser.parse_args()

    api_key = os.environ.get("OPENAI_API_KEY")
    if not api_key:
        print("ERROR: set OPENAI_API_KEY env var", file=sys.stderr)
        sys.exit(1)

    system_prompt = load_system_prompt()
    cases = TEST_CASES[: args.limit] if args.limit > 0 else TEST_CASES

    print(f"Running {len(cases)} test cases with OpenAI model={args.model}, temperature={args.temperature}")
    print("=" * 80)

    results = []
    ok_count = 0
    for i, case in enumerate(cases, 1):
        try:
            raw, _ = call_openai(system_prompt, case["input"], api_key, args.model, args.temperature)
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
    print(f"PASS: {ok_count}/{len(cases)}  (threshold: 25/30 for v1.1 acceptance)")

    out_path = Path(args.output)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    lines = []
    lines.append(f"# プロンプト動作検証結果 v1.1 (OpenAI)")
    lines.append("")
    lines.append(f"- 日時: {time.strftime('%Y-%m-%d %H:%M:%S')}")
    lines.append(f"- プロバイダ: OpenAI ChatGPT")
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
