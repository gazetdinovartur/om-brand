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
    1: "января",
    2: "февраля",
    3: "марта",
    4: "апреля",
    5: "мая",
    6: "июня",
    7: "июля",
    8: "августа",
    9: "сентября",
    10: "октября",
    11: "ноября",
    12: "декабря",
}


def load_catalog() -> dict[str, Any]:
    return json.loads(CATALOG_PATH.read_text(encoding="utf-8"))


def compile_theme_patterns(catalog: dict[str, Any]) -> dict[str, re.Pattern[str]]:
    raw = catalog.get("theme_patterns") or {}
    out: dict[str, re.Pattern[str]] = {}
    for slug, pat in raw.items():
        if isinstance(pat, str) and pat.strip():
            out[slug] = re.compile(pat, re.I | re.U)
    return out


def detect_tags(text: str, patterns: dict[str, re.Pattern[str]]) -> list[str]:
    return [slug for slug, pat in patterns.items() if pat.search(text)]


def slugify(text: str, max_len: int = 80) -> str:
    s = re.sub(r"[^\w\s-]", "", text.lower().strip(), flags=re.U)
    s = re.sub(r"[\s_-]+", "-", s).strip("-")
    return (s[:max_len] or "entry").strip("-")


def build_title(text: str, fallback_date: datetime | None = None) -> str:
    clean = re.sub(r"\s+", " ", text.strip())
    if not clean:
        if fallback_date:
            return f"Пост от {fallback_date.day} {MONTHS_RU[fallback_date.month]} {fallback_date.year} года"
        return "Без названия"

    candidate = clean.split("\n", 1)[0]
    if len(candidate) > 80:
        words = candidate.split()
        parts: list[str] = []
        for word in words:
            nxt = (" ".join(parts + [word])).strip()
            if len(nxt) > 80:
                break
            parts.append(word)
        while parts and parts[-1].lower().strip(".,!?") in PREPOSITIONS:
            parts.pop()
        candidate = " ".join(parts).rstrip(" ,.;:")
        if not candidate:
            candidate = clean[:80].rstrip(" ,.;:")
    return candidate or "Без названия"


def assign_era(dt: datetime, catalog: dict[str, Any]) -> str | None:
    day = dt.date()
    priority = catalog.get("era_priority") or []
    eras = catalog.get("eras") or []
    era_by_slug = {e.get("slug"): e for e in eras if isinstance(e, dict)}
    for slug in priority:
        era = era_by_slug.get(slug)
        if not era:
            continue
        for start_s, end_s in era.get("ranges") or []:
            start = datetime.fromisoformat(str(start_s)).date()
            end = datetime.fromisoformat(str(end_s)).date()
            if start <= day <= end:
                return slug
    for era in eras:
        if not isinstance(era, dict):
            continue
        slug = era.get("slug")
        for start_s, end_s in era.get("ranges") or []:
            start = datetime.fromisoformat(str(start_s)).date()
            end = datetime.fromisoformat(str(end_s)).date()
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
                parts.append(str(item.get("text") or ""))
        return "".join(parts)
    if isinstance(value, dict):
        return str(value.get("text") or "")
    return ""


def format_reactions_meta(counts: dict[str, int]) -> str | None:
    parts = [f"{emoji} {count}" for emoji, count in counts.items() if count > 0]
    return " · ".join(parts) if parts else None


def merge_message_reactions(messages: list[dict[str, Any]]) -> dict[str, int]:
    counts: dict[str, int] = defaultdict(int)
    for msg in messages:
        for reaction in msg.get("reactions") or []:
            if not isinstance(reaction, dict):
                continue
            if reaction.get("type") != "emoji":
                continue
            emoji = str(reaction.get("emoji") or "").strip()
            if not emoji:
                continue
            counts[emoji] += int(reaction.get("count") or 0)
    return dict(counts)


def parse_telegram_export(path: Path, channel: dict[str, Any], catalog: dict[str, Any]) -> list[dict[str, Any]]:
    if not path.is_file():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    messages = data.get("messages") or []
    patterns = compile_theme_patterns(catalog)
    wave_gap = int(channel.get("wave_gap_minutes") or 45) * 60
    min_chars = int(channel.get("min_chars") or 40)
    channel_id = str(channel["id"])

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
        last = current_wave[-1]
        dt = first["dt"]
        blocks: list[dict[str, Any]] = [{"type": "paragraph", "body": body}]
        for msg in current_wave:
            for photo in msg.get("photos") or []:
                blocks.append({"type": "image", "sourcePath": photo, "alt": ""})

        reaction_meta = format_reactions_meta(merge_message_reactions(current_wave))
        if reaction_meta:
            blocks.append({"type": "callout", "calloutStyle": "meta", "body": reaction_meta})

        title = build_title(body, dt)
        first_id = first["id"]
        last_id = last["id"]
        entry = {
            "source_key": f"tg:{channel_id}:wave:{first_id}-{last_id}",
            "channel": channel_id,
            "platform": "telegram",
            "title": title,
            "slug_hint": slugify(title),
            "lede": body.split("\n", 1)[0][:240] if body else None,
            "status": channel.get("status", "draft"),
            "date": dt.isoformat(timespec="seconds"),
            "era": assign_era(dt, catalog),
            "series": channel.get("series"),
            "tags": detect_tags(body, patterns),
            "media_dir": channel.get("media_dir"),
            "blocks": blocks,
        }
        if channel.get("admin_only"):
            entry["admin_only"] = True
        if channel.get("unlisted"):
            entry["unlisted"] = True
        entries.append(entry)
        current_wave = []

    for msg in messages:
        if msg.get("type") != "message":
            continue
        text = flatten_telegram_text(msg.get("text")).strip()
        date_raw = msg.get("date") or ""
        try:
            dt = datetime.fromisoformat(str(date_raw).replace("Z", "+00:00"))
        except ValueError:
            unixtime = msg.get("date_unixtime")
            if unixtime is None:
                continue
            dt = datetime.fromtimestamp(int(unixtime), tz=UTC)

        photos: list[str] = []
        if msg.get("photo"):
            photos.append(str(msg["photo"]))
        # Telegram desktop also nests photos in files sometimes; keep simple path from export.
        item = {
            "id": msg.get("id"),
            "text": text,
            "dt": dt,
            "photos": photos,
            "reactions": msg.get("reactions") or [],
        }

        if last_dt is not None and (dt - last_dt).total_seconds() > wave_gap:
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
    front, body = parts[1], parts[2].strip()
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

    date_raw = str(meta.get("date") or "")
    try:
        dt = datetime.fromisoformat(date_raw.replace("Z", "+00:00"))
    except ValueError:
        return None

    text_only = re.sub(r"!\[[^\]]*]\([^)]+\)", "", body)
    text_only = re.sub(r"\n{3,}", "\n\n", text_only).strip()
    blocks: list[dict[str, Any]] = []
    if text_only:
        blocks.append({"type": "paragraph", "body": text_only})
    for match in re.finditer(r"!\[([^\]]*)]\(([^)]+)\)", body):
        blocks.append({"type": "image", "sourcePath": match.group(2), "alt": match.group(1)})

    likes = meta.get("likes")
    if isinstance(likes, int) and likes > 0:
        blocks.append({"type": "callout", "calloutStyle": "meta", "body": f"❤ {likes}"})

    title = build_title(text_only, dt)
    year = path.parent.name if path.parent.name.isdigit() else str(dt.year)
    media_dir = f"content/vk/{year}"
    post_id = meta.get("id") or path.stem
    return {
        "source_key": f"vk:wall:{post_id}",
        "channel": "vk",
        "platform": "vk",
        "title": title,
        "slug_hint": slugify(title),
        "lede": text_only.split("\n", 1)[0][:240] if text_only else None,
        "status": "published",
        "date": dt.isoformat(timespec="seconds"),
        "era": assign_era(dt, catalog),
        "series": "vk-wall",
        "tags": detect_tags(text_only, compile_theme_patterns(catalog)),
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


def _fix_mojibake(value: str) -> str:
    """Repair Instagram export text (UTF-8 misread as latin1/cp1252)."""
    if not value:
        return ""
    text = value
    if text.startswith("`"):
        text = text[1:].lstrip()

    def to_bytes(s: str) -> bytes:
        out = bytearray()
        for ch in s:
            o = ord(ch)
            if o < 256:
                out.append(o)
            else:
                out.extend(ch.encode("utf-8"))
        return bytes(out)

    def decode_tolerant(data: bytes) -> str:
        buf = data
        while buf:
            try:
                return buf.decode("utf-8")
            except UnicodeDecodeError as exc:
                if exc.end >= len(buf):
                    buf = buf[: exc.start]
                    continue
                buf = buf[: exc.start] + buf[exc.start + 1 :]
        return ""

    candidates = [text]
    for enc in ("latin1", "cp1252"):
        try:
            candidates.append(decode_tolerant(text.encode(enc)))
        except UnicodeEncodeError:
            pass
    candidates.append(decode_tolerant(to_bytes(text)))

    def score(t: str) -> tuple[int, int, int, int]:
        cyr = len(re.findall(r"[А-Яа-яЁё]", t))
        hebrew = len(re.findall(r"[\u0590-\u05FF]", t))
        latin = len(re.findall(r"[A-Za-z]", t))
        # ÐÑÂÃ — Cyrillic mojibake; × — common for Hebrew/Arabic UTF-8 misread as latin1
        mojibake = len(re.findall(r"[ÐÑÂÃ×]", t))
        replacement = t.count("\ufffd")
        good = cyr + hebrew + latin
        return (good - 4 * mojibake - 10 * replacement, -mojibake, good, len(t))

    import unicodedata

    best = unicodedata.normalize("NFC", max(candidates, key=score))
    return re.sub(r"ðŸ[\x98-\x9f]?$", "", best).rstrip()


def load_instagram_likes_by_timestamp(zf: zipfile.ZipFile) -> dict[int, int]:
    """Map post creation_timestamp → likes from past_instagram_insights/posts.json."""
    likes_by_ts: dict[int, int] = {}
    insight_names = [
        n
        for n in zf.namelist()
        if n.endswith("past_instagram_insights/posts.json") or n.endswith("past_instagram_insights/videos.json")
    ]
    for name in insight_names:
        try:
            payload = json.loads(zf.read(name))
        except (KeyError, json.JSONDecodeError):
            continue
        if not isinstance(payload, dict):
            continue
        for key, rows in payload.items():
            if not isinstance(rows, list):
                continue
            for row in rows:
                if not isinstance(row, dict):
                    continue
                sm = row.get("string_map_data") or {}
                likes = 0
                for k, v in sm.items():
                    label = _fix_mojibake(str(k))
                    if "Нравится" in label or "Like" in label:
                        try:
                            likes = int((v or {}).get("value") or 0)
                        except (TypeError, ValueError):
                            likes = 0
                mm = row.get("media_map_data") or {}
                for media in mm.values():
                    if not isinstance(media, dict):
                        continue
                    ts = media.get("creation_timestamp")
                    if ts is None:
                        continue
                    ts_i = int(ts)
                    likes_by_ts[ts_i] = max(likes_by_ts.get(ts_i, 0), likes)
                # also creation timestamp from string_map
                for k, v in sm.items():
                    label = _fix_mojibake(str(k))
                    if "создания" in label.lower() or "creation" in label.lower():
                        ts = (v or {}).get("timestamp")
                        if ts:
                            likes_by_ts[int(ts)] = max(likes_by_ts.get(int(ts), 0), likes)
    return likes_by_ts


def instagram_source(catalog: dict[str, Any], channel: str) -> dict[str, Any]:
    for src in catalog.get("instagram_sources") or []:
        if isinstance(src, dict) and src.get("id") == channel:
            return src
    return {}


def instagram_min_chars(catalog: dict[str, Any], channel: str) -> int:
    return int(instagram_source(catalog, channel).get("min_chars") or 0)


def parse_instagram_zips(zips: list[str], catalog: dict[str, Any]) -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []
    patterns = compile_theme_patterns(catalog)
    seen_keys: set[str] = set()
    seen_media: set[str] = set()

    for zip_path in zips:
        path = Path(zip_path)
        if not path.is_file():
            path = ROOT / zip_path
        if not path.is_file():
            print(f"skip missing instagram zip: {zip_path}")
            continue

        channel = "instagram"
        stem = path.stem.lower()
        if "heyteaflow" in stem:
            channel = "instagram-heyteaflow"
        elif "arturlun" in stem:
            channel = "instagram"
        src_meta = instagram_source(catalog, channel)
        min_chars = int(src_meta.get("min_chars") or 0)
        series_slug = str(src_meta.get("series") or ("heyteaflow" if channel == "instagram-heyteaflow" else "instagram"))
        media_subdir = str(src_meta.get("media_subdir") or channel)
        media_dir = f"content/{media_subdir.rstrip('/')}"
        catalog_tags = [str(t) for t in (src_meta.get("tags") or []) if str(t).strip()]
        channel_tag = src_meta.get("channel_tag")
        if isinstance(channel_tag, str) and channel_tag.strip():
            catalog_tags.append(channel_tag.strip())
        catalog_tags = list(dict.fromkeys(catalog_tags))

        with zipfile.ZipFile(path) as zf:
            likes_by_ts = load_instagram_likes_by_timestamp(zf)
            posts_json = None
            for json_name in zf.namelist():
                if json_name.endswith("media/posts_1.json") or json_name.endswith("media/posts.json"):
                    if posts_json is None or json_name.endswith("posts_1.json"):
                        posts_json = json_name
            if not posts_json:
                continue
            try:
                payload = json.loads(zf.read(posts_json))
            except (KeyError, json.JSONDecodeError):
                continue
            posts = payload if isinstance(payload, list) else payload.get("posts") or []

            for post in posts:
                if not isinstance(post, dict):
                    continue
                media = post.get("media") or []
                if not isinstance(media, list) or not media:
                    continue
                first = media[0] if isinstance(media[0], dict) else {}
                caption = flatten_telegram_text(
                    first.get("title") or post.get("title") or post.get("caption") or ""
                ).strip()
                caption = _fix_mojibake(caption)
                # Instagram captions often start with a backtick artifact from export.
                if caption.startswith("`"):
                    caption = caption[1:].lstrip()
                ts = first.get("creation_timestamp") or post.get("taken_at") or post.get("creation_timestamp")
                if ts is None:
                    continue
                ts_i = int(ts)
                dt = datetime.fromtimestamp(ts_i, tz=UTC)

                images: list[str] = []
                for m in media:
                    if not isinstance(m, dict):
                        continue
                    uri = m.get("uri") or m.get("path")
                    if not uri:
                        continue
                    if not re.search(r"\.(jpe?g|png|webp)$", str(uri), re.I):
                        continue
                    images.append(str(uri))
                if not images:
                    continue

                # Short captions are fine; empty-only posts stay out (min_chars from catalog).
                if len(caption) < min_chars:
                    continue

                # Same carousel can appear twice with different media[0] timestamps.
                media_ids = []
                for img in images:
                    mid = re.search(r"(\d{10,})", str(img).split("/")[-1])
                    if mid:
                        media_ids.append(mid.group(1))
                if media_ids and any(mid in seen_media for mid in media_ids):
                    continue

                if channel == "instagram":
                    source_key = f"ig:instagram:post:{ts_i}"
                else:
                    # canonical heyteaflow key (not ig:instagram-heyteaflow:…)
                    source_key = f"ig:heyteaflow:post:{ts_i}"

                if source_key in seen_keys:
                    continue
                seen_keys.add(source_key)
                seen_media.update(media_ids)

                blocks: list[dict[str, Any]] = []
                if caption:
                    blocks.append({"type": "paragraph", "body": caption})
                media_imgs = images[:10]
                if len(media_imgs) >= 2:
                    blocks.append(
                        {
                            "type": "gallery",
                            "images": [{"sourcePath": img, "alt": ""} for img in media_imgs],
                        }
                    )
                elif media_imgs:
                    blocks.append({"type": "image", "sourcePath": media_imgs[0], "alt": ""})

                likes = likes_by_ts.get(ts_i, 0)
                if likes > 0:
                    blocks.append({"type": "callout", "calloutStyle": "meta", "body": f"❤ {likes}"})
                title = build_title(caption, dt)
                tags = list(dict.fromkeys([*detect_tags(caption, patterns), *catalog_tags]))
                entries.append(
                    {
                        "source_key": source_key,
                        "channel": channel,
                        "platform": "instagram",
                        "title": title,
                        "slug_hint": slugify(f"ig-{channel}-{dt.strftime('%Y%m%d')}-{ts_i}"),
                        "lede": caption.split("\n", 1)[0][:240] if caption else None,
                        "status": "published",
                        "date": dt.isoformat(timespec="seconds"),
                        "era": assign_era(dt, catalog),
                        "series": series_slug,
                        "tags": tags,
                        "media_dir": media_dir,
                        "blocks": blocks,
                        "stats": {"likes": likes, "images": len(images)},
                    }
                )

    return entries


def write_corpus(entries: list[dict[str, Any]]) -> None:
    entries = sorted(entries, key=lambda e: e.get("date") or "")
    CORPUS_PATH.parent.mkdir(parents=True, exist_ok=True)
    with CORPUS_PATH.open("w", encoding="utf-8") as fh:
        for entry in entries:
            fh.write(json.dumps(entry, ensure_ascii=False) + "\n")

    by_channel: dict[str, int] = defaultdict(int)
    for entry in entries:
        by_channel[str(entry.get("channel") or "?")] += 1

    MANIFEST_PATH.write_text(
        json.dumps(
            {
                "built_at": datetime.now(tz=UTC).isoformat(timespec="seconds"),
                "entries": len(entries),
                "by_channel": dict(by_channel),
            },
            ensure_ascii=False,
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )


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
        rel = channel.get("path")
        if platform == "telegram" and isinstance(rel, str):
            all_entries.extend(parse_telegram_export(ROOT / rel, channel, catalog))

    all_entries.extend(parse_vk_dir(ROOT / "content/vk", catalog))

    if args.instagram:
        zips = list((catalog.get("external_paths") or {}).get("instagram_exports") or [])
        # Optional heyteaflow zip often lives next to the arturlun exports.
        downloads = Path.home() / "Downloads"
        for candidate in downloads.glob("instagram-heyteaflow-*.zip"):
            path = str(candidate)
            if path not in zips:
                zips.append(path)
        all_entries.extend(parse_instagram_zips(zips, catalog))

    if not all_entries:
        print("No entries built. Ensure content/ exports exist:")
        print("  content/ChatExport_*/result.json")
        print("  content/vk/*/*.md")
        print("  Instagram zips in catalog external_paths")
        raise SystemExit(1)

    write_corpus(all_entries)
    print(f"Wrote {len(all_entries)} entries → {CORPUS_PATH}")
    print(f"Manifest → {MANIFEST_PATH}")


if __name__ == "__main__":
    main()
