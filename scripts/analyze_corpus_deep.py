#!/usr/bin/env python3
"""Deep corpus statistics → analysis/deep-2026-07-21/data/corpus-stats.json"""

from __future__ import annotations

import argparse
import json
import statistics
import sys
from collections import Counter
from datetime import UTC, datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from corpus_utils import (
    compile_theme_patterns,
    detect_themes,
    extract_text,
    load_catalog,
    load_jsonl,
    parse_entry_date,
    text_length,
    word_count,
)

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUT = ROOT / "analysis" / "deep-2026-07-21" / "data"
DEFAULT_CATALOG = ROOT / "config/content/catalog.json"


def analyze(entries: list[dict], catalog: dict) -> dict:
    patterns = compile_theme_patterns(catalog)
    by_channel: Counter = Counter()
    by_era: Counter = Counter()
    by_series: Counter = Counter()
    by_status: Counter = Counter()
    theme_hits: Counter = Counter()
    lengths: list[int] = []
    words: list[int] = []
    years: Counter = Counter()

    for entry in entries:
        by_channel[str(entry.get("channel") or "unknown")] += 1
        by_era[str(entry.get("era") or "_unassigned")] += 1
        by_series[str(entry.get("series") or "—")] += 1
        by_status[str(entry.get("status") or "draft")] += 1
        text = extract_text(entry)
        lengths.append(len(text))
        words.append(word_count(text))
        for theme in detect_themes(text, patterns):
            theme_hits[theme] += 1
        d = parse_entry_date(entry)
        if d:
            years[str(d.year)] += 1

    return {
        "generated_at": datetime.now(UTC).isoformat().replace("+00:00", "Z"),
        "total_entries": len(entries),
        "by_channel": dict(by_channel.most_common()),
        "by_era": dict(by_era.most_common()),
        "by_series": dict(by_series.most_common()),
        "by_status": dict(by_status.most_common()),
        "theme_hits": dict(theme_hits.most_common()),
        "by_year": dict(sorted(years.items())),
        "text_length": {
            "min": min(lengths) if lengths else 0,
            "max": max(lengths) if lengths else 0,
            "mean": round(statistics.mean(lengths), 1) if lengths else 0,
            "median": round(statistics.median(lengths), 1) if lengths else 0,
        },
        "word_stats": {
            "mean": round(statistics.mean(words), 1) if words else 0,
            "median": round(statistics.median(words), 1) if words else 0,
        },
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Deep chronicle corpus analysis")
    parser.add_argument("--corpus", type=Path, default=DEFAULT_CORPUS)
    parser.add_argument("--catalog", type=Path, default=DEFAULT_CATALOG)
    parser.add_argument("--out", type=Path, default=DEFAULT_OUT)
    args = parser.parse_args()

    catalog = load_catalog(args.catalog) if args.catalog.is_file() else {}
    entries = load_jsonl(args.corpus)
    stats = analyze(entries, catalog)
    stats["corpus_path"] = str(args.corpus)
    stats["corpus_exists"] = args.corpus.is_file()

    args.out.mkdir(parents=True, exist_ok=True)
    out_file = args.out / "corpus-stats.json"
    out_file.write_text(json.dumps(stats, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"Entries: {stats['total_entries']}")
    if not stats["corpus_exists"]:
        print(f"WARNING: corpus not found at {args.corpus}")
    else:
        print(f"Written: {out_file}")


if __name__ == "__main__":
    main()
