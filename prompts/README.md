# Prompts ディレクトリ

## ファイル

- `system-prompt-v1.txt` — システムプロンプト本体（Claude API の `system` パラメータに渡す）
- `few-shot-examples.json` — 30本+Anti-patterns 5本のJSON版（テストスクリプト用・実装中はこれを参照）。`../few-shot-examples.md` から抽出する想定

## 使い方

### Claude API 呼び出し時

```json
{
  "model": "claude-haiku-4-20250514",
  "max_tokens": 64,
  "temperature": 0.3,
  "system": "(system-prompt-v1.txt の内容)",
  "messages": [
    {"role": "user", "content": "Title: ものづくり補助金の申請方法"}
  ]
}
```

### バージョニング

- `v1`: 12本サブセット（コスト最小・MVPで採用）
- `v2`予定: 30本フル版（品質比較用・必要時に作成）

T0-03 動作検証で v1 が25/30以上を満たせなかった場合に v2 を試す。

## 重要な設計判断

1. **Few-shotはsystem promptに埋め込み**（messages配列に複数往復を入れる方式は使わない）。Haikuはsystem prompt内のFew-shotで十分機能するため、API呼び出し回数を増やさない
2. **temperature: 0.3** で再現性重視（0.0だと同じ入力でも稀に異なる出力が出る可能性、0.5以上だとブレが大きすぎる）
3. **max_tokens: 64** で十分。スラッグは最長60文字制限なのでバッファ込みで64
4. **後処理（正規化）はPHP側で必ず実行**: API応答に余計な文字（説明文・改行・引用符等）が混入したケースに備える
