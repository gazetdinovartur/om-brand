#!/usr/bin/env python3
"""
Deep corpus analysis for chronicle content.
Reads corpus/chronicle_entries.jsonl and writes stats to analysis/deep-*/data/

Usage:
  python3 scripts/analyze_corpus_deep.py
  python3 scripts/analyze_corpus_deep.py --corpus path/to.jsonl --out analysis/deep-2026-07-21/data
"""

from __future__ import annotations

import argparse
import json
import re
import statistics
from collections import Counter
from datetime import UTC, datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CORPUS = ROOT / "corpus" / "chronicle_entries.jsonl"
DEFAULT_OUT = ROOT / "analysis" / "deep-2026-07-21" / "data"

# Theme patterns from catalog (simplified)
THEME_PATTERNS = {
    "telo": re.compile(
        r"(?:тел(?:о|а|у|ом|е)|телесн|соматик|дыхан|напряж|расслаб|таз|позвоноч|мышц|заземл|воплощ)",
        re.I,
    ),
    "gorod": re.compile(
        r"(?:улиц|город|екатеринбург|ленина|трамвай|двор|переулк|площад|мост|парк|проспект|набережн)",
        re.I,
    ),
    "praktika": re.compile(r"(?:йог|медитац|практик|ритуал|асан|пранаям|созерцан)", re.I),
    "yazyk": re.compile(r"(?:язык|текст|писат|стих|песн|музык|голос|творчеств|поэти|проз)", re.I),
    "gore": re.compile(r"(?:горев|горю|утрат|смерт|прощан|слёз|слез|горе\s+)", re.I),
    "put": re.compile(r"(?:путь|дорог|странств|переезд|предназначен|маршрут|паломнич)", re.I),
    "kod": re.compile(
        r"(?:git|symfony|php|docker|рефактор|бэкенд|фронтенд|разработчик|программ|commit|код)",
        re.I,
    ),
    "snovidenie-taro": re.compile(
        r"(?:маги|таро|аркан|расклад|сновиден|сновид|во\s+сне|приснил|архетип|мандал|оракул)",
        re.I,
    ),
    "zerkalo": re.compile(r"(?:обратн\s*связ|отражен|зеркал|рефлекси)", re.I),
}


def load_jsonl(path: Path) -> list[dict]:
    entries = []
    if not path.is_file():
        return entries
    with path.open(encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                entries.append(json.loads(line))
            except json.JSONDecodeError:
                continue
    return entries


def extract_text(entry: dict) -> str:
    parts = []
    if entry.get("title"):
        parts.append(str(entry["title"]))
    if entry.get("lede"):
        parts.append(str(entry["lede"]))
    for block in entry.get("blocks") or []:
        if block.get("body"):
            parts.append(str(block["body"]))
    return "\n".join(parts)


def word_stats(text: str) -> dict:
    words = re.findall(r"[а-яёa-z]+", text.lower())
    if not words:
        return {"words": 0, "unique": 0, "avg_word_len": 0}
    return {
        "words": len(words),
        "unique": len(set(words)),
        "avg_word_len": round(statistics.mean(len(w) for w in words), 2),
    }


def detect_themes(text: str) -> list[str]:
    return [name for name, pat in THEME_PATTERNS.items() if pat.search(text)]


def analyze(entries: list[dict]) -> dict:
    by_channel: Counter = Counter()
    by_era: Counter = Counter()
    by_series: Counter = Counter()
    theme_hits: Counter = Counter()
    lengths: list[int] = []
    years: Counter = Counter()

    for e in entries:
        ch = e.get("channel") or "unknown"
        by_channel[ch] += 1
        if e.get("era"):
            by_era[e["era"]] += 1
        if e.get("series"):
            by_series[e["series"]] += 1
        text = extract_text(e)
        lengths.append(len(text))
        for t in detect_themes(text):
            theme_hits[t] += 1
        date = e.get("date") or ""
        if len(date) >= 4:
            years[date[:4]] += 1

    return {
        "generated_at": datetime.now(UTC).isoformat().replace("+00:00", "Z"),
        "total_entries": len(entries),
        "by_channel": dict(by_channel.most_common()),
        "by_era": dict(by_era.most_common()),
        "by_series": dict(by_series.most_common()),
        "theme_hits": dict(theme_hits.most_common()),
        "by_year": dict(sorted(years.items())),
        "text_length": {
            "min": min(lengths) if lengths else 0,
            "max": max(lengths) if lengths else 0,
            "mean": round(statistics.mean(lengths), 1) if lengths else 0,
            "median": round(statistics.median(lengths), 1) if lengths else 0,
        },
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Deep chronicle corpus analysis")
    parser.add_argument("--corpus", type=Path, default=DEFAULT_CORPUS)
    parser.add_argument("--out", type=Path, default=DEFAULT_OUT)
    args = parser.parse_args()

    entries = load_jsonl(args.corpus)
    stats = analyze(entries)
    stats["corpus_path"] = str(args.corpus)
    stats["corpus_exists"] = args.corpus.is_file()

    args.out.mkdir(parents=True, exist_ok=True)
    out_file = args.out / "corpus-stats.json"
    out_file.write_text(json.dumps(stats, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"Entries: {stats['total_entries']}")
    if not stats["corpus_exists"]:
        print(f"WARNING: corpus not found at {args.corpus}")
        print("Run corpus_build.py locally first.")
    else:
        print(f"Written: {out_file}")
        print("Channels:", stats.get("by_channel", {}))


if __name__ == "__main__":
    main()
