#!/usr/bin/env python3
"""
Export corpus-derived analysis mirrors (by era and by channel).

Usage:
  python3 scripts/export_analysis_mirror.py
  python3 scripts/export_analysis_mirror.py --corpus corpus/chronicle_entries.jsonl \\
      --out analysis/deep-2026-07-21/mirror
"""

from __future__ import annotations

import argparse
import json
import shutil
import sys
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from corpus_utils import (
    compile_theme_patterns,
    compute_slice_stats,
    load_catalog,
    load_jsonl,
    sample_excerpts,
    select_quotes,
    sha256_file,
    top_titles,
)

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CORPUS = ROOT / "corpus" / "chronicle_entries.jsonl"
DEFAULT_CATALOG = ROOT / "config/content/catalog.json"
DEFAULT_OUT = ROOT / "analysis" / "deep-2026-07-21" / "mirror"


def group_by_era(entries: list[dict[str, Any]], catalog: dict[str, Any]) -> dict[str, list[dict[str, Any]]]:
    era_slugs = [e["slug"] for e in catalog.get("eras", []) if isinstance(e, dict) and e.get("slug")]
    groups: dict[str, list[dict[str, Any]]] = {slug: [] for slug in era_slugs}
    groups["_unassigned"] = []
    for entry in entries:
        era = entry.get("era")
        if isinstance(era, str) and era in groups:
            groups[era].append(entry)
        else:
            groups["_unassigned"].append(entry)
    return groups


def group_by_channel(entries: list[dict[str, Any]], catalog: dict[str, Any]) -> dict[str, list[dict[str, Any]]]:
    channel_ids = [c["id"] for c in catalog.get("channels", []) if isinstance(c, dict) and c.get("id")]
    groups: dict[str, list[dict[str, Any]]] = {cid: [] for cid in channel_ids}
    groups["_unknown"] = []
    for entry in entries:
        channel = entry.get("channel")
        if isinstance(channel, str) and channel in groups:
            groups[channel].append(entry)
        elif isinstance(channel, str) and channel:
            if channel not in groups:
                groups[channel] = []
            groups[channel].append(entry)
        else:
            groups["_unknown"].append(entry)
    return groups


def era_meta(slug: str, catalog: dict[str, Any]) -> dict[str, Any]:
    if slug == "_unassigned":
        return {"slug": slug, "title": "Без эпохи", "period_label": None}
    for era in catalog.get("eras", []):
        if isinstance(era, dict) and era.get("slug") == slug:
            return era
    return {"slug": slug, "title": slug, "period_label": None}


def channel_meta(channel_id: str, catalog: dict[str, Any]) -> dict[str, Any]:
    if channel_id == "_unknown":
        return {"id": channel_id, "title": "Неизвестный канал", "platform": None, "series": None}
    for channel in catalog.get("channels", []):
        if isinstance(channel, dict) and channel.get("id") == channel_id:
            title = channel_id
            for series in catalog.get("series", []):
                if isinstance(series, dict) and series.get("slug") == channel.get("series"):
                    title = series.get("title") or title
            return {**channel, "title": title}
    return {"id": channel_id, "title": channel_id, "platform": None, "series": None}


def render_theme_table(theme_distribution: dict[str, Any]) -> str:
    if not theme_distribution:
        return "_Нет совпадений по theme_patterns._\n"
    lines = ["| Тег | Записей | % |", "|-----|---------|---|"]
    for slug, data in theme_distribution.items():
        hits = data.get("hits", 0)
        pct = round(float(data.get("pct", 0)) * 100, 1)
        lines.append(f"| {slug} | {hits} | {pct}% |")
    lines.append("")
    lines.append("_Одна запись может иметь несколько тегов — сумма % может превышать 100._")
    return "\n".join(lines) + "\n"


def render_mirror_md(
    slice_type: str,
    meta: dict[str, Any],
    stats: dict[str, Any],
    titles: list[dict[str, Any]],
    excerpts: list[dict[str, Any]],
    quotes: list[dict[str, Any]],
    generated_at: str,
    qualitative_link: str | None,
) -> str:
    if slice_type == "era":
        title = meta.get("title") or meta.get("slug")
        subtitle = meta.get("period_label") or ""
        key = meta.get("slug")
    else:
        title = meta.get("title") or meta.get("id")
        subtitle = meta.get("platform") or ""
        key = meta.get("id")

    date_range = "—"
    if stats.get("date_min") and stats.get("date_max"):
        date_range = f"{stats['date_min']} – {stats['date_max']}"

    lines = [
        f"# {title} · corpus mirror",
        "",
        f"> Auto-generated from `corpus/chronicle_entries.jsonl` · {generated_at}",
        f"> Slice: **{slice_type}** = `{key}`",
        "",
    ]
    if subtitle:
        lines.append(f"> Period / platform: {subtitle}")
        lines.append("")

    lines.extend(
        [
            "## Summary",
            "",
            "| Metric | Value |",
            "|--------|-------|",
            f"| Entries | {stats.get('entry_count', 0)} |",
            f"| Date range | {date_range} |",
            f"| Avg text length | {stats.get('avg_text_chars', 0)} chars ({stats.get('avg_words', 0)} words) |",
            f"| Channels | {stats.get('by_channel', {})} |",
            f"| Status | {stats.get('by_status', {})} |",
            "",
            "## Theme tag distribution",
            "",
            render_theme_table(stats.get("theme_distribution", {})),
            "## Top titles",
            "",
        ]
    )

    if titles:
        for i, row in enumerate(titles, 1):
            lines.append(
                f"{i}. **{row.get('date', '')}** — {row.get('title', '')} "
                f"· `{row.get('channel', '—')}` · `{row.get('era', '—')}`"
            )
    else:
        lines.append("_Нет записей._")
    lines.extend(["", "## Sample lede / excerpts", ""])

    if excerpts:
        for row in excerpts:
            lines.extend(
                [
                    f"### {row.get('date', '')} · {row.get('title', '')}",
                    "",
                    f"> {row.get('excerpt', '')}",
                    "",
                ]
            )
    else:
        lines.append("_Нет текстов._\n")

    lines.extend(["## Representative quotes", ""])
    if quotes:
        for i, row in enumerate(quotes, 1):
            lines.append(f"{i}. **{row.get('date', '')} · {row.get('title', '')}** — «{row.get('quote', '')}»")
    else:
        lines.append("_Недостаточно материала для цитат._")

    lines.extend(["", "---", ""])
    if qualitative_link:
        lines.append(f"*Qualitative mirror:* [{qualitative_link}]({qualitative_link})")
    else:
        lines.append("*Qualitative mirror:* (нет ручного зеркала в `eras/` или `channels/`)")

    return "\n".join(lines) + "\n"


def render_overview_md(slice_type: str, rows: list[dict[str, Any]]) -> str:
    title = "Эпохи" if slice_type == "era" else "Каналы"
    lines = [
        f"# {title} · overview",
        "",
        "| Key | Title | Entries | Date range | Top theme |",
        "|-----|-------|---------|------------|-----------|",
    ]
    for row in sorted(rows, key=lambda r: r.get("entry_count", 0), reverse=True):
        dr = "—"
        if row.get("date_min") and row.get("date_max"):
            dr = f"{row['date_min']} – {row['date_max']}"
        top_theme = "—"
        td = row.get("theme_distribution") or {}
        if td:
            top_theme = next(iter(td))
        lines.append(
            f"| `{row.get('key')}` | {row.get('title')} | {row.get('entry_count', 0)} | {dr} | {top_theme} |"
        )
    return "\n".join(lines) + "\n"


def write_mirror_tree(
    out_dir: Path,
    catalog: dict[str, Any],
    entries: list[dict[str, Any]],
    corpus_path: Path,
    run_id: str,
) -> dict[str, Any]:
    if out_dir.exists():
        shutil.rmtree(out_dir)
    out_dir.mkdir(parents=True)

    patterns = compile_theme_patterns(catalog)
    generated_at = datetime.now(UTC).isoformat().replace("+00:00", "Z")

    era_groups = group_by_era(entries, catalog)
    channel_groups = group_by_channel(entries, catalog)

    manifest_slices: dict[str, list[dict[str, Any]]] = {"era": [], "channel": []}
    files_written: list[str] = ["README.md", "manifest.json"]

    readme = out_dir / "README.md"
    readme.write_text(
        "\n".join(
            [
                "# Corpus mirrors",
                "",
                "Auto-generated from `corpus/chronicle_entries.jsonl`. **Do not hand-edit.**",
                "",
                "Regenerate:",
                "",
                "```bash",
                "python3 scripts/analyze_corpus_deep.py",
                "python3 scripts/export_analysis_mirror.py",
                "```",
                "",
                "Qualitative analysis: `../eras/` (ручные зеркала эпох).",
                "",
            ]
        ),
        encoding="utf-8",
    )

    era_overview_rows: list[dict[str, Any]] = []
    for slug, group in era_groups.items():
        meta = era_meta(slug, catalog)
        stats = compute_slice_stats(group, patterns)
        titles = top_titles(group)
        excerpts = sample_excerpts(group)
        quotes = select_quotes(group)
        qual_path = ROOT / "analysis" / run_id / "eras" / f"{slug}.md"
        qual_link = f"../eras/{slug}.md" if qual_path.is_file() else None
        rel_file = f"by-era/{slug}.md"
        content = render_mirror_md("era", meta, stats, titles, excerpts, quotes, generated_at, qual_link)
        target = out_dir / "by-era" / f"{slug}.md"
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding="utf-8")
        files_written.append(rel_file)
        slice_row = {
            "key": slug,
            "title": meta.get("title"),
            "period_label": meta.get("period_label"),
            "file": rel_file,
            "entry_count": stats["entry_count"],
            "date_range": [stats.get("date_min"), stats.get("date_max")],
            "avg_text_chars": stats.get("avg_text_chars"),
            "theme_distribution": stats.get("theme_distribution"),
            "top_titles": titles[:5],
            "quote_count": len(quotes),
            "empty": stats["entry_count"] == 0,
        }
        manifest_slices["era"].append(slice_row)
        era_overview_rows.append({"key": slug, "title": meta.get("title"), **stats})

    (out_dir / "by-era" / "_overview.md").write_text(
        render_overview_md("era", era_overview_rows), encoding="utf-8"
    )
    files_written.append("by-era/_overview.md")

    channel_overview_rows: list[dict[str, Any]] = []
    for channel_id, group in channel_groups.items():
        meta = channel_meta(channel_id, catalog)
        stats = compute_slice_stats(group, patterns)
        titles = top_titles(group)
        excerpts = sample_excerpts(group)
        quotes = select_quotes(group)
        rel_file = f"by-channel/{channel_id}.md"
        content = render_mirror_md("channel", meta, stats, titles, excerpts, quotes, generated_at, None)
        target = out_dir / "by-channel" / f"{channel_id}.md"
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding="utf-8")
        files_written.append(rel_file)
        manifest_slices["channel"].append(
            {
                "key": channel_id,
                "title": meta.get("title"),
                "platform": meta.get("platform"),
                "series": meta.get("series"),
                "file": rel_file,
                "entry_count": stats["entry_count"],
                "date_range": [stats.get("date_min"), stats.get("date_max")],
                "avg_text_chars": stats.get("avg_text_chars"),
                "theme_distribution": stats.get("theme_distribution"),
                "empty": stats["entry_count"] == 0,
            }
        )
        channel_overview_rows.append({"key": channel_id, "title": meta.get("title"), **stats})

    (out_dir / "by-channel" / "_overview.md").write_text(
        render_overview_md("channel", channel_overview_rows), encoding="utf-8"
    )
    files_written.append("by-channel/_overview.md")

    data_dir = out_dir / "data"
    data_dir.mkdir(exist_ok=True)
    (data_dir / "era-slices.json").write_text(
        json.dumps(manifest_slices["era"], ensure_ascii=False, indent=2), encoding="utf-8"
    )
    (data_dir / "channel-slices.json").write_text(
        json.dumps(manifest_slices["channel"], ensure_ascii=False, indent=2), encoding="utf-8"
    )
    files_written.extend(["data/era-slices.json", "data/channel-slices.json"])

    dates = [d for e in entries if (d := e.get("date", ""))][:2]
    all_dates = sorted(
        [e.get("date", "")[:10] for e in entries if isinstance(e.get("date"), str) and e.get("date")]
    )

    manifest = {
        "$schema": "om-brand/mirror-manifest/v1",
        "generated_at": generated_at,
        "run_id": run_id,
        "corpus": {
            "path": str(corpus_path.relative_to(ROOT) if corpus_path.is_relative_to(ROOT) else corpus_path),
            "exists": corpus_path.is_file(),
            "sha256": sha256_file(corpus_path),
            "total_entries": len(entries),
            "date_range": [all_dates[0], all_dates[-1]] if all_dates else [None, None],
        },
        "catalog": {
            "path": "config/content/catalog.json",
            "era_count": len(catalog.get("eras", [])),
            "channel_count": len(catalog.get("channels", [])),
        },
        "slices": manifest_slices,
        "unassigned": {
            "era_null_count": len(era_groups.get("_unassigned", [])),
            "era_file": "by-era/_unassigned.md",
            "channel_unknown_count": len(channel_groups.get("_unknown", [])),
            "channel_file": "by-channel/_unknown.md",
        },
        "files_written": files_written,
    }

    (out_dir / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    return manifest


def main() -> None:
    parser = argparse.ArgumentParser(description="Export corpus analysis mirrors")
    parser.add_argument("--corpus", type=Path, default=DEFAULT_CORPUS)
    parser.add_argument("--catalog", type=Path, default=DEFAULT_CATALOG)
    parser.add_argument("--out", type=Path, default=DEFAULT_OUT)
    parser.add_argument("--run-id", default="deep-2026-07-21")
    args = parser.parse_args()

    if not args.corpus.is_file():
        print(f"ERROR: corpus not found: {args.corpus}")
        print("Run: python3 scripts/corpus_build.py [--instagram]")
        raise SystemExit(1)

    catalog = load_catalog(args.catalog)
    entries = load_jsonl(args.corpus)
    manifest = write_mirror_tree(args.out, catalog, entries, args.corpus, args.run_id)
    print(f"Mirror export: {manifest['corpus']['total_entries']} entries → {args.out}")
    print(f"Manifest: {args.out / 'manifest.json'}")


if __name__ == "__main__":
    main()
