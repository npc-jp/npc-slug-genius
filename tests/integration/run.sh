#!/bin/bash
# Slug Genius 結合テストの実行（WordPress Playground CLI で WP 7.1 / PHP 8.3 を立てて /?sg_test=run を開く）
# 使い方: bash tests/integration/run.sh [プラグインのフォルダ（省略時は開発コピー）]
# 終了コード: 全件合格かつエラー記録なしで 0
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_SRC="${1:-$(cd "$HERE/../.." && pwd)}"
PORT="${SG_TEST_PORT:-9482}"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/sg-test.XXXXXX")"

# 配布物と同じ中身だけを複製する（SVN/Git 作業コピーを直接マウントしない）
mkdir -p "$WORK/plugin" "$WORK/mu"
rsync -a --exclude .svn --exclude .git --exclude tests --exclude _internal --exclude dist \
  --exclude design --exclude marketing --exclude assets --exclude '*.md' "$PLUGIN_SRC/" "$WORK/plugin/"
cp "$HERE/sg-test-runner.php" "$WORK/mu/"
MODE=run
if [ -n "${SG_TEST_LIVE:-}" ]; then
  # 本物の API を呼ぶ回: ~/.env のキーを一時フォルダの mu-plugin にだけ書く（終わったら作業フォルダごと消える）
  MODE=live
  python3 - "$WORK/mu/sg-live-keys.php" <<'PY'
import os, sys
env = {}
for line in open(os.path.expanduser("~/.env")):
    line = line.strip()
    if "=" in line and not line.startswith("#"):
        k, v = line.replace("export ", "", 1).split("=", 1)
        env[k] = v.strip().strip('"').strip("'")
pairs = {"claude": "ANTHROPIC_API_KEY", "openai": "OPENAI_API_KEY", "gemini": "GEMINI_API_KEY"}
body = ", ".join(f"'{p}' => '{env[k]}'" for p, k in pairs.items() if env.get(k))
open(sys.argv[1], "w").write("<?php\nfunction sg_t_live_keys() { return array( " + body + " ); }\n")
os.chmod(sys.argv[1], 0o600)
PY
fi

npx --yes @wp-playground/cli@3.1.59 server --port="$PORT" --php=8.3 --wp=7.1 \
  --mount="$WORK/plugin:/wordpress/wp-content/plugins/npc-slug-genius" \
  --mount="$WORK/mu:/wordpress/wp-content/mu-plugins" \
  --blueprint="$HERE/blueprint.json" > "$WORK/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null; pkill -P $SERVER_PID 2>/dev/null; [ -n "${SG_TEST_KEEP:-}" ] && echo "作業フォルダ: $WORK" || rm -rf "$WORK"' EXIT

for _ in $(seq 1 180); do
  grep -q "Ready!" "$WORK/server.log" && break
  sleep 1
done
if ! grep -q "Ready!" "$WORK/server.log"; then
  echo "Playground が起動しませんでした"; cat "$WORK/server.log"; exit 2
fi

curl -s -L -c "$WORK/cj.txt" -b "$WORK/cj.txt" -D "$WORK/headers.txt" --max-time 120 "http://127.0.0.1:$PORT/?sg_test=$MODE" > "$WORK/result.json"
python3 - "$WORK/result.json" "$HERE/sg-test-runner.php" "$MODE" <<'PY'
import json, re, sys
try:
    d = json.load(open(sys.argv[1]))
except Exception:
    print("結果が JSON ではありません:"); print(open(sys.argv[1]).read()[:2000]); sys.exit(2)
print(f"WordPress {d['wp_version']} / PHP {d['php_version']} / プラグイン {d['plugin_version']}")
fails = 0
for r in d["results"]:
    mark = "PASS" if r["pass"] else "FAIL"
    fails += 0 if r["pass"] else 1
    print(f"  {mark}  {r['name']}  ({r['detail']})")
counted = True
if d["log_markers"] is None:
    # 本物の API の回は3社そろって合格したときだけ成功
    names = sorted(r["name"] for r in d["results"])
    ok = fails == 0 and names == ["live_claude", "live_gemini", "live_openai"]
else:
    print(f"エラー記録: 印 {d['log_markers']}/2 件・それ以外 {len(d['log_other'])} 件")
    for l in d["log_other"]:
        print("   ", l)
    # テストファイルに書いたケースの数・実行時に登録された数・結果の数がそろわなければ失敗（0件の合格を防ぐ）
    written = len(re.findall(r"^sg_t_case\(", open(sys.argv[2]).read(), re.M))
    counted = written > 0 and written == d.get("case_count") == len(d["results"])
    if not counted:
        print(f"ケースの数が合いません: ファイル {written} 件・登録 {d.get('case_count')} 件・結果 {len(d['results'])} 件")
    ok = counted and fails == 0 and d["log_markers"] == 2 and not d["log_other"]
if d["log_markers"] is not None and not counted:
    print("失敗: ケースの数の照合が合わない")
else:
    print(f"{len(d['results']) - fails}/{len(d['results'])} 合格" + ("" if ok else "  → 失敗あり"))
sys.exit(0 if ok else 1)
PY
