#!/usr/bin/env python3
"""Shared helpers for corpus analysis and mirror export."""

from __future__ import annotations

import hashlib
import json
import re
import statistics
from collections import Counter
from datetime import date, datetime
from pathlib import Path
from typing import Any

DEFAULT_THEME_PATTERNS: dict[str, str] = {
    "telo": r"(?:тел(?:о|а|у|ом|е)|телесн\w*|соматик\w*|дыхани\w*|напряж\w*|расслаб\w*|таз\w*|позвоноч\w*|мышц\w*|заземл\w*|воплощ\w*)",
    "gorod": r"(?:улиц\w*|город\w*|екатеринбург\w*|ленина|трамвай\w*|двор\w*|переулк\w*|площад\w*|мост\w*|парк\w*|проспект\w*|набережн\w*)",
    "praktika": r"(?:йог\w*|медитац\w*|практик\w*|ритуал\w*|асан\w*|пранаям\w*|созерцан\w*)",
    "yazyk": r"(?:язык\w*|текст\w*|писат\w*|стих\w*|песн\w*|музык\w*|голос\w*|творчеств\w*|поэти\w*|проз\w*)",
    "gore": r"(?:горев\w*|горю\w*|утрат\w*|смерт\w*|прощан\w*|слёз\w*|слез\w*|горе\s+)",
    "put": r"(?:путь\w*|дорог\w*|странств\w*|переезд\w*|предназначен\w*|маршрут\w*|паломнич\w*)",
    "kod": r"(?:git\w*|symfony|php|docker\w*|рефактор\w*|разработчик\w*|программ\w*|commit\w*|код\w*)",
    "snovidenie-taro": r"(?:маги\w*|таро|аркан\w*|расклад\w*|сновиден\w*|сновид\w*|приснил\w*|архетип\w*|мандал\w*)",
    "zerkalo": r"(?:обратн\w*\s*связ\w*|отражен\w*|зеркал\w*|рефлекси\w*)",
}


def load_jsonl(path: Path) -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []
    if not path.is_file():
        return entries
    with path.open(encoding="utf-8") as handle:
        for line in handle:
            line = line.strip()
            if not line:
                continue
            try:
                row = json.loads(line)
            except json.JSONDecodeError:
                continue
            if isinstance(row, dict):
                entries.append(row)
    return entries


def load_catalog(path: Path) -> dict[str, Any]:
    raw = path.read_text(encoding="utf-8")
    data = json.loads(raw)
    if not isinstance(data, dict):
        raise ValueError(f"Invalid catalog JSON: {path}")
    return data


def sha256_file(path: Path) -> str | None:
    if not path.is_file():
        return None
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(65536), b""):
            digest.update(chunk)
    return digest.hexdigest()


def extract_text(entry: dict[str, Any]) -> str:
    parts: list[str] = []
    for key in ("title", "lede"):
        value = entry.get(key)
        if isinstance(value, str) and value.strip():
            parts.append(value.strip())
    for block in entry.get("blocks") or []:
        if not isinstance(block, dict):
            continue
        body = block.get("body")
        if isinstance(body, str) and body.strip():
            parts.append(body.strip())
    return "\n".join(parts)


def compile_theme_patterns(catalog: dict[str, Any]) -> dict[str, re.Pattern[str]]:
    raw = catalog.get("theme_patterns") or DEFAULT_THEME_PATTERNS
    patterns: dict[str, re.Pattern[str]] = {}
    for slug, pattern in raw.items():
        if isinstance(pattern, str):
            patterns[slug] = re.compile(pattern, re.I | re.U)
    return patterns


def detect_themes(text: str, patterns: dict[str, re.Pattern[str]]) -> list[str]:
    return [slug for slug, pattern in patterns.items() if pattern.search(text)]


def parse_entry_date(entry: dict[str, Any]) -> date | None:
    raw = entry.get("date")
    if not isinstance(raw, str) or not raw.strip():
        return None
    try:
        return datetime.fromisoformat(raw[:19]).date()
    except ValueError:
        return None


def text_length(entry: dict[str, Any]) -> int:
    return len(extract_text(entry))


def word_count(text: str) -> int:
    return len(re.findall(r"[а-яёa-z]+", text.lower()))


def truncate(text: str, max_len: int) -> tuple[str, bool]:
    text = re.sub(r"\s+", " ", text.strip())
    if len(text) <= max_len:
        return text, False
    cut = text[: max_len - 1].rsplit(" ", 1)[0]
    return cut + "…", True


def first_excerpt(entry: dict[str, Any], max_len: int = 280) -> str:
    lede = entry.get("lede")
    if isinstance(lede, str) and lede.strip():
        return truncate(lede.strip(), max_len)[0]
    for block in entry.get("blocks") or []:
        if not isinstance(block, dict):
            continue
        if block.get("type") not in (None, "paragraph", "quote", "callout"):
            continue
        body = block.get("body")
        if isinstance(body, str) and body.strip():
            return truncate(body.strip(), max_len)[0]
    return ""


def collect_quote_candidates(entry: dict[str, Any]) -> list[str]:
    quotes: list[str] = []
    for block in entry.get("blocks") or []:
        if not isinstance(block, dict):
            continue
        body = block.get("body")
        if not isinstance(body, str) or not body.strip():
            continue
        if block.get("type") in ("quote", "callout"):
            quotes.append(body.strip())
    text = extract_text(entry)
    for sentence in re.split(r"(?<=[.!?…])\s+", text):
        sentence = sentence.strip()
        if len(sentence) >= 40:
            quotes.append(sentence)
    return quotes


def compute_slice_stats(
    entries: list[dict[str, Any]],
    patterns: dict[str, re.Pattern[str]],
) -> dict[str, Any]:
    lengths = [text_length(e) for e in entries]
    words = [word_count(extract_text(e)) for e in entries]
    dates = [d for e in entries if (d := parse_entry_date(e)) is not None]

    theme_hits: Counter[str] = Counter()
    for entry in entries:
        for theme in detect_themes(extract_text(entry), patterns):
            theme_hits[theme] += 1

    count = len(entries)
    theme_distribution = {
        slug: {"hits": hits, "pct": round(hits / count, 3) if count else 0.0}
        for slug, hits in theme_hits.most_common()
    }

    return {
        "entry_count": count,
        "date_min": min(dates).isoformat() if dates else None,
        "date_max": max(dates).isoformat() if dates else None,
        "avg_text_chars": round(statistics.mean(lengths), 1) if lengths else 0.0,
        "avg_words": round(statistics.mean(words), 1) if words else 0.0,
        "by_channel": dict(Counter(str(e.get("channel") or "—") for e in entries)),
        "by_status": dict(Counter(str(e.get("status") or "draft") for e in entries)),
        "by_series": dict(Counter(str(e.get("series") or "—") for e in entries)),
        "theme_distribution": theme_distribution,
    }


def top_titles(entries: list[dict[str, Any]], limit: int = 10) -> list[dict[str, Any]]:
    def sort_key(entry: dict[str, Any]) -> tuple[str, str]:
        d = parse_entry_date(entry)
        return (d.isoformat() if d else "", str(entry.get("source_key") or ""))

    rows: list[dict[str, Any]] = []
    for entry in sorted(entries, key=sort_key, reverse=True)[:limit]:
        d = parse_entry_date(entry)
        rows.append(
            {
                "date": d.isoformat() if d else "",
                "title": str(entry.get("title") or "Без названия"),
                "source_key": str(entry.get("source_key") or ""),
                "era": entry.get("era"),
                "channel": entry.get("channel"),
            }
        )
    return rows


def sample_excerpts(entries: list[dict[str, Any]], limit: int = 8) -> list[dict[str, Any]]:
    def sort_key(entry: dict[str, Any]) -> str:
        d = parse_entry_date(entry)
        return d.isoformat() if d else ""

    rows: list[dict[str, Any]] = []
    for entry in sorted(entries, key=sort_key, reverse=True):
        excerpt = first_excerpt(entry)
        if not excerpt:
            continue
        d = parse_entry_date(entry)
        rows.append(
            {
                "date": d.isoformat() if d else "",
                "title": str(entry.get("title") or "Без названия"),
                "source_key": str(entry.get("source_key") or ""),
                "excerpt": excerpt,
            }
        )
        if len(rows) >= limit:
            break
    return rows


def select_quotes(entries: list[dict[str, Any]], n: int = 5, max_len: int = 200) -> list[dict[str, Any]]:
    def sort_key(entry: dict[str, Any]) -> str:
        d = parse_entry_date(entry)
        return d.isoformat() if d else ""

    sorted_entries = sorted(entries, key=sort_key)
    if not sorted_entries:
        return []

    picked: list[dict[str, Any]] = []
    used_keys: set[str] = set()
    step = max(1, len(sorted_entries) // n)
    indices = list(range(0, len(sorted_entries), step))[:n]
    if len(indices) < n:
        indices = list(range(len(sorted_entries)))

    for idx in indices:
        entry = sorted_entries[idx]
        source_key = str(entry.get("source_key") or "")
        if source_key in used_keys:
            continue
        for candidate in collect_quote_candidates(entry):
            quote, truncated = truncate(candidate, max_len)
            if len(quote) < 20:
                continue
            d = parse_entry_date(entry)
            picked.append(
                {
                    "date": d.isoformat() if d else "",
                    "title": str(entry.get("title") or "Без названия"),
                    "source_key": source_key,
                    "quote": quote,
                    "truncated": truncated,
                }
            )
            used_keys.add(source_key)
            break
        if len(picked) >= n:
            break
    return picked
