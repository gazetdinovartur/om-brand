#!/usr/bin/env python3
"""
Build corpus/chronicle_entries.jsonl from content/ exports (TG, VK, Instagram).

Usage:
  python3 scripts/corpus_build.py
  python3 scripts/corpus_build.py --instagram
"""

from __future__ import annotations

import argparse
import json
import re
import zipfile
from collections import defaultdict
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
CATALOG_PATH = ROOT / "config/content/catalog.json"
CORPUS_PATH = ROOT / "corpus/chronicle_entries.jsonl"
MANIFEST_PATH = ROOT / "corpus/manifest.json"

PREPOSITIONS = frozenset(
    "и в на с к у о а но или для от до по из за под над при без через между".split()
)
MONTHS_RU = {
    1: "января", 2: "февраля", 3: "марта", 4: "апреля", 5: "мая", 6: "июня",
    7: "июля", 8: "августа", 9: "сентября", 10: "октября", 11: "ноября", 12: "декабря",
}


def load_catalog() -> dict[str, Any]:
    return json.loads(CATALOG_PATH.read_text(encoding="utf-8"))


def compile_theme_patterns(catalog: dict[str, Any]) -> dict[str, re.Pattern[str]]:
    raw = catalog.get("theme_patterns") or {}
    return {slug: re.compile(pat, re.I | re.U) for slug, pat in raw.items() if isinstance(pat, str)}


def detect_tags(text: str, patterns: dict[str, re.Pattern[str]]) -> list[str]:
    return [slug for slug, pat in patterns.items() if pat.search(text)]


def slugify(text: str, max_len: int = 80) -> str:
    text = text.lower().strip()
    text = re.sub(r"[^\w\s-]", "", text, flags=re.U)
    text = re.sub(r"[\s_-]+", "-", text, flags=re.U)
    return text[:max_len].strip("-") or "entry"


def build_title(text: str, fallback_date: datetime | None = None) -> str:
    clean = re.sub(r"\s+", " ", text.strip())
    if not clean:
        if fallback_date:
            return f"Пост от {fallback_date.day} {MONTHS_RU[fallback_date.month]} {fallback_date.year} года"
        return "Без названия"

    # Prefer first sentence / line up to ~80 chars, avoid breaking on prepositions.
    candidate = clean.split("\n", 1)[0]
    if len(candidate) > 80:
        words = candidate.split()
        parts: list[str] = []
        for word in words:
            if parts and len(" ".join(parts + [word])) > 80:
                if word.lower().strip(".,!?") in PREPOSITIONS:
                    parts.append(word)
                    continue
                break
            parts.append(word)
        candidate = " ".join(parts).strip(" ,.;:")
    return candidate or clean[:80]


def assign_era(dt: datetime, catalog: dict[str, Any]) -> str | None:
    day = dt.date()
    priority = catalog.get("era_priority") or [e.get("slug") for e in catalog.get("eras", [])]
    era_by_slug = {e["slug"]: e for e in catalog.get("eras", []) if e.get("slug")}

    for slug in priority:
        era = era_by_slug.get(slug)
        if not era:
            continue
        for start_s, end_s in era.get("ranges") or []:
            start = datetime.fromisoformat(start_s).date()
            end = datetime.fromisoformat(end_s).date()
            if start <= day <= end:
                return slug
    return None


def flatten_telegram_text(value: Any) -> str:
    if isinstance(value, str):
        return value
    if isinstance(value, list):
        parts: list[str] = []
        for item in value:
            if isinstance(item, str):
                parts.append(item)
            elif isinstance(item, dict):
                if item.get("type") == "text":
                    parts.append(str(item.get("text", "")))
                elif "text" in item:
                    parts.append(str(item["text"]))
        return "".join(parts)
    return ""


def parse_telegram_export(path: Path, channel: dict[str, Any], catalog: dict[str, Any]) -> list[dict[str, Any]]:
    if not path.is_file():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    messages = data.get("messages") or []
    patterns = compile_theme_patterns(catalog)
    min_chars = int(channel.get("min_chars") or 0)
    wave_gap = int(channel.get("wave_gap_minutes") or 20)
    entries: list[dict[str, Any]] = []

    current_wave: list[dict[str, Any]] = []
    last_dt: datetime | None = None

    def flush_wave() -> None:
        nonlocal current_wave
        if not current_wave:
            return
        texts = [m["text"] for m in current_wave if m.get("text")]
        body = "\n\n".join(texts).strip()
        if len(body) < min_chars:
            current_wave = []
            return
        first = current_wave[0]
        dt: datetime = first["dt"]
        blocks = [{"type": "paragraph", "body": body}]
        for msg in current_wave:
            for photo in msg.get("photos") or []:
                blocks.append({"type": "image", "sourcePath": photo, "alt": ""})
        title = build_title(body, dt)
        text_for_tags = body
        entry = {
            "source_key": f"tg:{channel['id']}:{first['id']}",
            "channel": channel["id"],
            "title": title,
            "slug_hint": slugify(title),
            "lede": body.split("\n", 1)[0][:240] if body else None,
            "status": channel.get("status", "draft"),
            "date": dt.isoformat(timespec="seconds"),
            "era": assign_era(dt, catalog),
            "series": channel.get("series"),
            "tags": detect_tags(text_for_tags, patterns),
            "media_dir": channel.get("media_dir"),
            "blocks": blocks,
        }
        entries.append(entry)
        current_wave = []

    for msg in messages:
        if not isinstance(msg, dict) or msg.get("type") != "message":
            continue
        text = flatten_telegram_text(msg.get("text")).strip()
        if not text and not msg.get("photo"):
            continue
        date_raw = msg.get("date") or msg.get("date_unixtime")
        if isinstance(date_raw, str):
            dt = datetime.fromisoformat(date_raw.replace("Z", "+00:00"))
        else:
            dt = datetime.fromtimestamp(int(date_raw), tz=UTC)
        photos: list[str] = []
        if msg.get("photo"):
            photos.append(str(msg["photo"]))
        for f in msg.get("file") or []:
            if isinstance(f, str):
                photos.append(f)
        item = {"id": msg.get("id"), "dt": dt, "text": text, "photos": photos}
        if last_dt and (dt - last_dt).total_seconds() > wave_gap * 60:
            flush_wave()
        current_wave.append(item)
        last_dt = dt
    flush_wave()
    return entries


def parse_vk_markdown(path: Path, catalog: dict[str, Any]) -> dict[str, Any] | None:
    raw = path.read_text(encoding="utf-8")
    if not raw.startswith("---"):
        return None
    parts = raw.split("---", 2)
    if len(parts) < 3:
        return None
    front = parts[1]
    body = parts[2].strip()
    meta: dict[str, Any] = {}
    for line in front.strip().splitlines():
        if ":" not in line:
            continue
        key, val = line.split(":", 1)
        key = key.strip()
        val = val.strip().strip('"')
        if key in ("likes", "id", "owner_id"):
            try:
                meta[key] = int(val)
            except ValueError:
                meta[key] = val
        else:
            meta[key] = val
    date_raw = meta.get("date", "")
    try:
        dt = datetime.fromisoformat(str(date_raw).replace("Z", "+00:00"))
    except ValueError:
        return None

    # Strip markdown images from body for text stats
    text_only = re.sub(r"!\[[^\]]*]\([^)]+\)", "", body).strip()
    text_only = re.sub(r"\n{3,}", "\n\n", text_only)

    blocks: list[dict[str, Any]] = []
    if text_only:
        blocks.append({"type": "paragraph", "body": text_only})
    for match in re.finditer(r"!\[[^\]]*]\(([^)]+)\)", body):
        blocks.append({"type": "image", "sourcePath": match.group(1), "alt": ""})

    likes = meta.get("likes")
    if isinstance(likes, int) and likes > 0:
        blocks.append({"type": "callout", "calloutStyle": "meta", "body": f"❤ {likes}"})

    if not text_only and blocks:
        title = build_title("", dt)
    else:
        title = build_title(text_only, dt)

    patterns = compile_theme_patterns(catalog)
    year = path.parent.name if path.parent.name.isdigit() else str(dt.year)
    media_dir = f"content/vk/{year}"
    post_id = meta.get("id", path.stem)

    return {
        "source_key": f"vk:{post_id}",
        "channel": "vk",
        "title": title,
        "slug_hint": slugify(title),
        "lede": (text_only.split("\n", 1)[0][:240] if text_only else title),
        "status": "published",
        "date": dt.isoformat(timespec="seconds"),
        "era": assign_era(dt, catalog),
        "series": "vk-wall",
        "tags": detect_tags(text_only or title, patterns),
        "media_dir": media_dir,
        "blocks": blocks,
    }


def parse_vk_dir(vk_root: Path, catalog: dict[str, Any]) -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []
    for md_path in sorted(vk_root.glob("*/*.md")):
        row = parse_vk_markdown(md_path, catalog)
        if row:
            entries.append(row)
    return entries


def parse_instagram_zips(zips: list[str], catalog: dict[str, Any]) -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []
    patterns = compile_theme_patterns(catalog)
    for zip_path in zips:
        path = Path(zip_path)
        if not path.is_file():
            # Try relative to project root
            path = ROOT / zip_path
        if not path.is_file():
            continue
        with zipfile.ZipFile(path) as zf:
            media_files = {n: n for n in zf.namelist() if re.search(r"\.(jpe?g|png|webp)$", n, re.I)}
            posts_json = [n for n in zf.namelist() if n.endswith("posts_1.json") or "/posts/" in n and n.endswith(".json")]
            for json_name in posts_json:
                try:
                    payload = json.loads(zf.read(json_name))
                except (KeyError, json.JSONDecodeError):
                    continue
                for post in payload if isinstance(payload, list) else payload.get("posts", []):
                    if not isinstance(post, dict):
                        continue
                    caption = post.get("title") or post.get("caption") or ""
                    if isinstance(caption, list):
                        caption = flatten_telegram_text(caption)
                    caption = str(caption).strip()
                    if len(caption) < 80:
                        continue
                    ts = post.get("creation_timestamp") or post.get("taken_at")
                    if not ts:
                        continue
                    dt = datetime.fromtimestamp(int(ts), tz=UTC)
                    media = post.get("media") or post.get("uri") or post.get("path")
                    images: list[str] = []
                    if isinstance(media, list):
                        for m in media:
                            if isinstance(m, dict) and m.get("uri"):
                                images.append(str(m["uri"]))
                    elif isinstance(media, str):
                        images.append(media)
                    if not images:
                        continue
                    blocks: list[dict[str, Any]] = [{"type": "paragraph", "body": caption}]
                    for img in images[:10]:
                        base = Path(img).name
                        for name in media_files:
                            if base in name:
                                blocks.append({"type": "image", "sourcePath": name, "alt": ""})
                                break
                    title = build_title(caption, dt)
                    entries.append(
                        {
                            "source_key": f"ig:{post.get('id') or post.get('pk') or title}",
                            "channel": "instagram",
                            "title": title,
                            "slug_hint": slugify(title),
                            "lede": caption.split("\n", 1)[0][:240],
                            "status": "published",
                            "date": dt.isoformat(timespec="seconds"),
                            "era": assign_era(dt, catalog),
                            "series": "instagram",
                            "tags": detect_tags(caption, patterns),
                            "media_dir": f"content/instagram/{path.stem}",
                            "blocks": blocks,
                        }
                    )
    return entries


def write_corpus(entries: list[dict[str, Any]]) -> None:
    CORPUS_PATH.parent.mkdir(parents=True, exist_ok=True)
    entries.sort(key=lambda e: e.get("date") or "")
    with CORPUS_PATH.open("w", encoding="utf-8") as handle:
        for entry in entries:
            handle.write(json.dumps(entry, ensure_ascii=False) + "\n")
    manifest = {
        "built_at": datetime.now(UTC).isoformat().replace("+00:00", "Z"),
        "entries": len(entries),
        "by_channel": dict(
            sorted(
                {k: sum(1 for e in entries if e.get("channel") == k) for k in {e.get("channel") for e in entries}}.items()
            )
        ),
    }
    MANIFEST_PATH.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description="Build chronicle corpus from content/")
    parser.add_argument("--instagram", action="store_true", help="Include Instagram zip exports from catalog")
    args = parser.parse_args()

    catalog = load_catalog()
    all_entries: list[dict[str, Any]] = []

    for channel in catalog.get("channels") or []:
        if not isinstance(channel, dict):
            continue
        platform = channel.get("platform")
        if platform == "telegram":
            rel = channel.get("path")
            if isinstance(rel, str):
                all_entries.extend(parse_telegram_export(ROOT / rel, channel, catalog))
        elif platform == "vk":
            rel = channel.get("path")
            if isinstance(rel, str):
                all_entries.extend(parse_vk_dir(ROOT / rel, catalog))

    if args.instagram:
        zips = (catalog.get("external_paths") or {}).get("instagram_exports") or []
        all_entries.extend(parse_instagram_zips(list(zips), catalog))

    if not all_entries:
        print("No entries built. Ensure content/ exports exist:")
        print("  content/ChatExport_*/result.json")
        print("  content/vk/*/*.md")
        if args.instagram:
            print("  Instagram zips in catalog external_paths")
        raise SystemExit(1)

    write_corpus(all_entries)
    print(f"Wrote {len(all_entries)} entries → {CORPUS_PATH}")
    print(f"Manifest → {MANIFEST_PATH}")


if __name__ == "__main__":
    main()
