#!/usr/bin/env python3
"""
Enrich corpus/chronicle_entries.jsonl like meta blocks:

1. Telegram — rebuild meta from ChatExport reactions (sum all emoji counts in a wave)
2. Instagram — pull likes from zip past_instagram_insights by post timestamp

Does not rebuild titles/media; only updates/inserts calloutStyle=meta blocks.
"""
from __future__ import annotations

import json
import re
import zipfile
from collections import defaultdict
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
CATALOG_PATH = ROOT / "config/content/catalog.json"
CORPUS_PATH = ROOT / "corpus/chronicle_entries.jsonl"


def fix_mojibake(value: str) -> str:
    try:
        return value.encode("latin1").decode("utf-8")
    except (UnicodeEncodeError, UnicodeDecodeError):
        return value


def format_reactions_meta(counts: dict[str, int]) -> str | None:
    parts = [f"{emoji} {count}" for emoji, count in counts.items() if count > 0]
    return " · ".join(parts) if parts else None


def merge_reactions(messages: list[dict[str, Any]]) -> dict[str, int]:
    counts: dict[str, int] = defaultdict(int)
    for msg in messages:
        for reaction in msg.get("reactions") or []:
            if not isinstance(reaction, dict) or reaction.get("type") != "emoji":
                continue
            emoji = str(reaction.get("emoji") or "").strip()
            if emoji:
                counts[emoji] += int(reaction.get("count") or 0)
    return dict(counts)


def tg_meta_for_source_key(source_key: str, catalog: dict[str, Any], cache: dict[str, Any]) -> str | None:
    m = re.match(r"^tg:([^:]+):wave:(\d+)-(\d+)$", source_key)
    if not m:
        return None
    channel_id, a, b = m.group(1), int(m.group(2)), int(m.group(3))
    key = f"__msgs__{channel_id}"
    if key not in cache:
        channel = next(
            (c for c in (catalog.get("channels") or []) if isinstance(c, dict) and c.get("id") == channel_id),
            None,
        )
        if not channel:
            return None
        path = ROOT / str(channel.get("path"))
        if not path.is_file():
            return None
        data = json.loads(path.read_text(encoding="utf-8"))
        cache[key] = {
            int(msg["id"]): msg
            for msg in data.get("messages") or []
            if isinstance(msg, dict) and msg.get("type") == "message" and msg.get("id") is not None
        }
    by_id: dict[int, dict[str, Any]] = cache[key]
    msgs = [by_id[i] for i in range(a, b + 1) if i in by_id]
    return format_reactions_meta(merge_reactions(msgs))


def load_instagram_like_indexes(zips: list[str]) -> tuple[dict[int, int], dict[str, int]]:
    """
    Returns:
      likes_by_ts: creation_timestamp → likes (from insights)
      likes_by_media_id: posts_1 media file id → likes (insights joined via post timestamp)
    """
    likes_by_ts: dict[int, int] = {}
    likes_by_media_id: dict[str, int] = {}

    for zip_path in zips:
        path = Path(zip_path)
        if not path.is_file():
            continue
        with zipfile.ZipFile(path) as zf:
            names = zf.namelist()
            for name in names:
                if not (
                    name.endswith("past_instagram_insights/posts.json")
                    or name.endswith("past_instagram_insights/videos.json")
                ):
                    continue
                try:
                    payload = json.loads(zf.read(name))
                except (KeyError, json.JSONDecodeError):
                    continue
                if not isinstance(payload, dict):
                    continue
                for rows in payload.values():
                    if not isinstance(rows, list):
                        continue
                    for row in rows:
                        if not isinstance(row, dict):
                            continue
                        sm = row.get("string_map_data") or {}
                        likes = 0
                        for k, v in sm.items():
                            label = fix_mojibake(str(k))
                            if "Нравится" in label or "Like" in label:
                                try:
                                    likes = int((v or {}).get("value") or 0)
                                except (TypeError, ValueError):
                                    likes = 0
                        for media in (row.get("media_map_data") or {}).values():
                            if not isinstance(media, dict):
                                continue
                            ts = media.get("creation_timestamp")
                            if ts is not None:
                                ts_i = int(ts)
                                likes_by_ts[ts_i] = max(likes_by_ts.get(ts_i, 0), likes)

            posts_json = None
            for name in names:
                if name.endswith("media/posts_1.json") or name.endswith("media/posts.json"):
                    if posts_json is None or name.endswith("posts_1.json"):
                        posts_json = name
            if not posts_json:
                continue
            try:
                posts = json.loads(zf.read(posts_json))
            except (KeyError, json.JSONDecodeError):
                continue
            if isinstance(posts, dict):
                posts = posts.get("posts") or []
            if not isinstance(posts, list):
                continue
            for post in posts:
                if not isinstance(post, dict):
                    continue
                media = post.get("media") or []
                if not isinstance(media, list) or not media:
                    continue
                first = media[0] if isinstance(media[0], dict) else {}
                ts = first.get("creation_timestamp")
                if ts is None:
                    continue
                likes = likes_by_ts.get(int(ts), 0)
                if likes <= 0:
                    continue
                for item in media:
                    if not isinstance(item, dict):
                        continue
                    uri = str(item.get("uri") or "")
                    mid = re.search(r"(\d{10,})", uri.split("/")[-1])
                    if mid:
                        likes_by_media_id[mid.group(1)] = max(
                            likes_by_media_id.get(mid.group(1), 0),
                            likes,
                        )

    return likes_by_ts, likes_by_media_id

def upsert_meta(blocks: list[dict[str, Any]], body: str | None) -> bool:
    """Update or insert meta callout. Returns True if changed."""
    meta_idxs = [i for i, b in enumerate(blocks) if isinstance(b, dict) and b.get("calloutStyle") == "meta"]
    if not body:
        if not meta_idxs:
            return False
        for i in reversed(meta_idxs):
            blocks.pop(i)
        return True

    if meta_idxs:
        changed = False
        for i in meta_idxs[1:]:
            pass
        # keep one meta block
        first = meta_idxs[0]
        if blocks[first].get("body") != body:
            blocks[first] = {"type": "callout", "calloutStyle": "meta", "body": body}
            changed = True
        for i in reversed(meta_idxs[1:]):
            blocks.pop(i)
            changed = True
        return changed

    blocks.append({"type": "callout", "calloutStyle": "meta", "body": body})
    return True


def main() -> None:
    catalog = json.loads(CATALOG_PATH.read_text(encoding="utf-8"))
    zips = list((catalog.get("external_paths") or {}).get("instagram_exports") or [])
    downloads = Path.home() / "Downloads"
    for candidate in list(downloads.glob("instagram-*.zip")):
        path = str(candidate)
        if path not in zips:
            zips.append(path)

    ig_likes_by_ts, ig_likes_by_media = load_instagram_like_indexes(zips)
    tg_cache: dict[str, Any] = {}

    rows: list[dict[str, Any]] = []
    with CORPUS_PATH.open(encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if line:
                rows.append(json.loads(line))

    tg_updated = ig_updated = 0
    for row in rows:
        blocks = row.get("blocks")
        if not isinstance(blocks, list):
            continue
        platform = row.get("platform")
        source_key = str(row.get("source_key") or "")

        if platform == "telegram":
            meta = tg_meta_for_source_key(source_key, catalog, tg_cache)
            if upsert_meta(blocks, meta):
                tg_updated += 1
            continue

        if platform == "instagram":
            likes = 0
            m = re.search(r"post:(\d+)$", source_key)
            if m:
                ts = int(m.group(1))
                # exports sometimes differ by 1–2s from corpus source_key
                for delta in (0, -1, 1, -2, 2):
                    likes = max(likes, ig_likes_by_ts.get(ts + delta, 0))
            for block in blocks:
                if not isinstance(block, dict):
                    continue
                paths: list[str] = []
                if block.get("type") == "image":
                    paths.append(str(block.get("sourcePath") or ""))
                elif block.get("type") == "gallery":
                    for image in block.get("images") or []:
                        if isinstance(image, dict):
                            paths.append(str(image.get("sourcePath") or ""))
                for path in paths:
                    mid = re.search(r"(\d{10,})", path.split("/")[-1])
                    if mid:
                        likes = max(likes, ig_likes_by_media.get(mid.group(1), 0))
            meta = f"❤ {likes}" if likes > 0 else None
            if upsert_meta(blocks, meta):
                ig_updated += 1

    with CORPUS_PATH.open("w", encoding="utf-8") as fh:
        for row in rows:
            fh.write(json.dumps(row, ensure_ascii=False) + "\n")

    print(f"Telegram meta updated: {tg_updated}")
    print(f"Instagram meta updated: {ig_updated}")
    print(
        "Instagram likes available: "
        f"ts={sum(1 for v in ig_likes_by_ts.values() if v > 0)}/{len(ig_likes_by_ts)}, "
        f"media={sum(1 for v in ig_likes_by_media.values() if v > 0)}/{len(ig_likes_by_media)}"
    )
    print(f"Wrote {CORPUS_PATH}")

if __name__ == "__main__":
    main()
