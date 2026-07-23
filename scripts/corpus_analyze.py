#!/usr/bin/env python3
"""Generate analysis artifacts from corpus (timeline + themes)."""

from __future__ import annotations

import argparse
import json
import re
from collections import Counter, defaultdict
from datetime import datetime
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
CORPUS_DIR = ROOT / "corpus"
ANALYSIS_DIR = ROOT / "analysis"
CATALOG_PATH = ROOT / "config" / "content" / "catalog.json"


def load_jsonl(path: Path) -> list[dict[str, Any]]:
    if not path.is_file():
        return []
    rows = []
    with path.open(encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if line:
                rows.append(json.loads(line))
    return rows


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--from-entries", action="store_true", help="Analyze chronicle_entries instead of items")
    args = parser.parse_args()

    catalog = json.loads(CATALOG_PATH.read_text(encoding="utf-8"))
    source = CORPUS_DIR / ("chronicle_entries.jsonl" if args.from_entries else "items.jsonl")
    rows = load_jsonl(source)
    if not rows:
        print(f"No data in {source}. Run scripts/corpus_build.py first.")
        return 1

    ANALYSIS_DIR.mkdir(parents=True, exist_ok=True)

    by_era: Counter[str] = Counter()
    by_channel: Counter[str] = Counter()
    by_month: Counter[str] = Counter()
    themes_by_era: dict[str, Counter[str]] = defaultdict(Counter)
    themes_total: Counter[str] = Counter()

    for row in rows:
        era = row.get("era") or "?"
        channel = row.get("channel") or row.get("source") or "?"
        by_era[era] += 1
        by_channel[channel] += 1
        date = row.get("date") or ""
        try:
            dt = datetime.fromisoformat(date[:19].replace("Z", ""))
            by_month[f"{dt.year}-{dt.month:02d}"] += 1
        except ValueError:
            pass
        tags = row.get("theme_tags") or row.get("tags") or []
        # entries store mixed channel+theme tags; filter to theme catalog
        theme_slugs = {t["slug"] for t in catalog.get("theme_tags", [])}
        for t in tags:
            if t in theme_slugs:
                themes_total[t] += 1
                themes_by_era[era][t] += 1

    # Timeline markdown
    era_meta = {e["slug"]: e for e in catalog["eras"]}
    lines = [
        "# Карта эпох (corpus)",
        "",
        f"Источник: `{source.relative_to(ROOT)}` · записей: **{len(rows)}**",
        "",
        "## По эпохам",
        "",
        "| Эпоха | Период | Кол-во |",
        "|---|---|---:|",
    ]
    for slug, count in by_era.most_common():
        meta = era_meta.get(slug, {})
        title = meta.get("title", slug)
        period = meta.get("period_label") or "—"
        nested = f" _(в {meta['nested_in']})_" if meta.get("nested_in") else ""
        lines.append(f"| {title}{nested} | {period} | {count} |")

    lines += ["", "## По каналам", ""]
    for ch, count in by_channel.most_common():
        lines.append(f"- **{ch}**: {count}")

    lines += ["", "## Темы (частотность)", ""]
    name_by_slug = {t["slug"]: t["name"] for t in catalog.get("theme_tags", [])}
    for slug, count in themes_total.most_common():
        lines.append(f"- **{name_by_slug.get(slug, slug)}** (`{slug}`): {count}")

    lines += ["", "## Темы × эпохи (топ-5)", ""]
    for era, counter in sorted(themes_by_era.items(), key=lambda x: -sum(x[1].values())):
        title = era_meta.get(era, {}).get("title", era)
        top = ", ".join(f"{name_by_slug.get(s, s)} ({n})" for s, n in counter.most_common(5))
        lines.append(f"- **{title}**: {top}")

    lines += ["", "## Активность по месяцам (топ плотности)", ""]
    for month, count in sorted(by_month.items(), key=lambda x: -x[1])[:24]:
        lines.append(f"- `{month}`: {count}")

    timeline_path = ANALYSIS_DIR / "timeline.md"
    timeline_path.write_text("\n".join(lines) + "\n", encoding="utf-8")

    # Machine-readable summary
    summary = {
        "source": str(source.relative_to(ROOT)),
        "count": len(rows),
        "by_era": dict(by_era),
        "by_channel": dict(by_channel),
        "themes_total": dict(themes_total),
        "built_at": datetime.now().isoformat(timespec="seconds"),
    }
    (ANALYSIS_DIR / "summary.json").write_text(
        json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )

    print(f"Wrote {timeline_path.relative_to(ROOT)}")
    print(f"Wrote analysis/summary.json")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
