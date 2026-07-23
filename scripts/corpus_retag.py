#!/usr/bin/env python3
"""
Re-detect theme tags on existing corpus/chronicle_entries.jsonl.

Keeps non-theme tags (channel tags, admin extras). Does not rebuild media.

Usage:
  python3 scripts/corpus_retag.py
  python3 scripts/corpus_retag.py --dry-run
"""
from __future__ import annotations

import argparse
import json
import sys
from collections import Counter
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from corpus_build import (  # noqa: E402
    CORPUS_PATH,
    compile_theme_patterns,
    detect_tags,
    load_catalog,
)


def entry_text(entry: dict[str, Any]) -> str:
    parts: list[str] = []
    for block in entry.get("blocks") or []:
        if not isinstance(block, dict):
            continue
        for key in ("body", "caption", "text", "alt"):
            value = block.get(key)
            if isinstance(value, str) and value.strip():
                parts.append(value)
    for key in ("title", "lede", "voice_transcript"):
        value = entry.get(key)
        if isinstance(value, str) and value.strip():
            parts.append(value)
    return "\n".join(parts)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", help="Print stats only, do not write")
    args = parser.parse_args()

    catalog = load_catalog()
    patterns = compile_theme_patterns(catalog)
    theme_slugs = set(patterns.keys())

    if not CORPUS_PATH.is_file():
        print(f"missing corpus: {CORPUS_PATH}", file=sys.stderr)
        return 1

    rows: list[dict[str, Any]] = []
    before = Counter()
    after = Counter()
    changed = 0

    for line in CORPUS_PATH.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        entry = json.loads(line)
        old_tags = [str(t) for t in (entry.get("tags") or []) if str(t).strip()]
        for tag in old_tags:
            before[tag] += 1

        kept = [t for t in old_tags if t not in theme_slugs]
        detected = detect_tags(entry_text(entry), patterns)
        new_tags = list(dict.fromkeys([*kept, *detected]))
        for tag in new_tags:
            after[tag] += 1

        if new_tags != old_tags:
            changed += 1
        entry["tags"] = new_tags
        rows.append(entry)

    print(f"entries={len(rows)} changed={changed}")
    print("theme tags before → after (public-relevant counts over all rows):")
    for slug in sorted(theme_slugs):
        print(f"  {slug}: {before[slug]} → {after[slug]}")

    if args.dry_run:
        print("dry-run: not writing")
        return 0

    CORPUS_PATH.write_text(
        "\n".join(json.dumps(row, ensure_ascii=False, separators=(",", ":")) for row in rows) + "\n",
        encoding="utf-8",
    )
    print(f"wrote {CORPUS_PATH}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
