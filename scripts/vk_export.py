#!/usr/bin/env python3
"""One-shot export of a VK wall into content/vk/{year}/ markdown + media."""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

API = "https://api.vk.com/method/"
API_VERSION = "5.199"
RATE_SLEEP = 0.35


class VkError(RuntimeError):
    pass


def api(method: str, token: str, retries: int = 4, **params: Any) -> Any:
    query = urllib.parse.urlencode(
        {**params, "access_token": token, "v": API_VERSION}
    )
    url = f"{API}{method}?{query}"
    req = urllib.request.Request(url, headers={"User-Agent": "vk-export/1.0"})
    last_exc: Exception | None = None
    for attempt in range(1, retries + 1):
        try:
            with urllib.request.urlopen(req, timeout=45) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            if "error" in data:
                err = data["error"]
                raise VkError(f"{err.get('error_code')}: {err.get('error_msg')}")
            time.sleep(RATE_SLEEP)
            return data["response"]
        except VkError:
            raise
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            last_exc = exc
            time.sleep(1.5 * attempt)
    raise RuntimeError(f"VK API {method} failed after retries: {last_exc}")


def download(url: str, dest: Path, retries: int = 3) -> bool:
    if not url:
        return False
    dest.parent.mkdir(parents=True, exist_ok=True)
    if dest.exists() and dest.stat().st_size > 0:
        return True
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": (
                "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            ),
            "Accept": "*/*",
            "Referer": "https://vk.com/",
        },
    )
    last_exc: Exception | None = None
    for attempt in range(1, retries + 1):
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                dest.write_bytes(resp.read())
            return True
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            last_exc = exc
            if dest.exists():
                dest.unlink(missing_ok=True)
            time.sleep(0.5 * attempt)
    print(f"  ! download failed: {url[:80]}… ({last_exc})", file=sys.stderr, flush=True)
    return False


def yaml_escape(value: str) -> str:
    if value is None:
        return '""'
    if re.search(r'[:#\[\]{},&*!|>\'"%@`]|\n|^[-?]', value) or value != value.strip():
        return json.dumps(value, ensure_ascii=False)
    return value


def to_yaml(obj: Any, indent: int = 0) -> str:
    pad = "  " * indent
    if isinstance(obj, dict):
        if not obj:
            return "{}"
        lines = []
        for key, val in obj.items():
            if isinstance(val, (dict, list)):
                rendered = to_yaml(val, indent + 1)
                if rendered in ("{}", "[]"):
                    lines.append(f"{pad}{key}: {rendered}")
                else:
                    lines.append(f"{pad}{key}:")
                    lines.append(rendered)
            elif isinstance(val, bool):
                lines.append(f"{pad}{key}: {'true' if val else 'false'}")
            elif isinstance(val, (int, float)) and not isinstance(val, bool):
                lines.append(f"{pad}{key}: {val}")
            elif val is None:
                lines.append(f"{pad}{key}: null")
            else:
                lines.append(f"{pad}{key}: {yaml_escape(str(val))}")
        return "\n".join(lines)
    if isinstance(obj, list):
        if not obj:
            return "[]"
        lines = []
        for item in obj:
            if isinstance(item, dict):
                item_lines = to_yaml(item, indent + 1).splitlines()
                if not item_lines:
                    lines.append(f"{pad}- {{}}")
                else:
                    lines.append(f"{pad}- {item_lines[0].lstrip()}")
                    for line in item_lines[1:]:
                        lines.append(line)
            elif isinstance(item, list):
                lines.append(f"{pad}-")
                lines.append(to_yaml(item, indent + 1))
            elif isinstance(item, bool):
                lines.append(f"{pad}- {'true' if item else 'false'}")
            elif isinstance(item, (int, float)) and not isinstance(item, bool):
                lines.append(f"{pad}- {item}")
            elif item is None:
                lines.append(f"{pad}- null")
            else:
                lines.append(f"{pad}- {yaml_escape(str(item))}")
        return "\n".join(lines)
    return yaml_escape(str(obj))


def best_photo_url(photo: dict) -> str | None:
    sizes = photo.get("sizes") or []
    if not sizes:
        return None
    best = max(sizes, key=lambda s: s.get("width", 0) * s.get("height", 0))
    return best.get("url")


def normalize_screen_name(screen_name: str) -> str:
    raw = screen_name.strip().rstrip("/")
    raw = re.sub(r"^https?://(m\.)?vk\.(com|ru)/", "", raw)
    return raw.split("?")[0].split("/")[0]


def resolve_owner(screen_name: str) -> tuple[int | None, str]:
    """Return (owner_id|None, screen_or_id). Named pages use domain=."""
    raw = normalize_screen_name(screen_name)
    if raw.isdigit() or (raw.startswith("-") and raw[1:].isdigit()):
        return int(raw), raw
    return None, raw


def fetch_wall(
    token: str,
    *,
    owner_id: int | None,
    domain: str | None,
    archived: bool,
) -> list[dict]:
    posts: list[dict] = []
    offset = 0
    count = 100
    while True:
        params: dict[str, Any] = {
            "offset": offset,
            "count": count,
            "extended": 0,
        }
        if owner_id is not None:
            params["owner_id"] = owner_id
        elif domain:
            params["domain"] = domain
        else:
            raise VkError("Need owner_id or domain")
        params["filter"] = "archived" if archived else "all"
        try:
            resp = api("wall.get", token, **params)
        except VkError as exc:
            if archived:
                print(f"  archived filter unsupported/empty: {exc}", file=sys.stderr, flush=True)
                return []
            raise
        items = resp.get("items") or []
        if not items:
            break
        for item in items:
            item["_archived"] = archived
            posts.append(item)
        offset += len(items)
        total = resp.get("count", offset)
        print(f"  {'archived' if archived else 'public'}: {offset}/{total}", flush=True)
        if offset >= total or len(items) < count:
            break
    return posts


def attachment_basename(post_id: int, index: int, url: str, fallback_ext: str) -> str:
    path = urllib.parse.urlparse(url).path
    ext = Path(path).suffix.lower().split("?")[0]
    if not ext or len(ext) > 5:
        ext = fallback_ext
    return f"{post_id}_{index}{ext}"


def safe_filename(name: str, max_len: int = 60) -> str:
    name = re.sub(r"[^\w\-а-яА-ЯёЁ]+", "_", name, flags=re.UNICODE).strip("_")
    return (name or "file")[:max_len]


def process_attachments(
    attachments: list[dict],
    *,
    post_id: int,
    media_dir: Path,
    rel_prefix: str,
    token: str,
    start_index: int = 1,
) -> tuple[list[dict], int]:
    meta: list[dict] = []
    idx = start_index

    for att in attachments or []:
        atype = att.get("type")
        payload = att.get(atype) or {}
        entry: dict[str, Any] = {"type": atype}

        if atype == "photo":
            url = best_photo_url(payload)
            entry["vk_id"] = f"{payload.get('owner_id')}_{payload.get('id')}"
            entry["source_url"] = url
            if url:
                name = attachment_basename(post_id, idx, url, ".jpg")
                local = media_dir / name
                if download(url, local):
                    entry["path"] = f"{rel_prefix}/{name}"
                    idx += 1

        elif atype == "posted_photo":
            url = payload.get("photo_604") or payload.get("photo_130")
            entry["source_url"] = url
            if url:
                name = attachment_basename(post_id, idx, url, ".jpg")
                local = media_dir / name
                if download(url, local):
                    entry["path"] = f"{rel_prefix}/{name}"
                    idx += 1

        elif atype == "doc":
            url = payload.get("url")
            title = payload.get("title") or f"doc_{payload.get('id')}"
            entry["title"] = title
            entry["ext"] = payload.get("ext")
            entry["size"] = payload.get("size")
            entry["source_url"] = url
            if url:
                ext = (
                    payload.get("ext")
                    or Path(urllib.parse.urlparse(url).path).suffix.lstrip(".")
                    or "bin"
                )
                stem = safe_filename(Path(title).stem if Path(title).suffix else title)
                name = f"{post_id}_{idx}_{stem}.{ext}"
                local = media_dir / name
                if download(url, local):
                    entry["path"] = f"{rel_prefix}/{name}"
                    idx += 1

        elif atype == "video":
            owner = payload.get("owner_id")
            vid = payload.get("id")
            access_key = payload.get("access_key")
            entry["vk_id"] = f"{owner}_{vid}"
            entry["title"] = payload.get("title")
            entry["duration"] = payload.get("duration")
            entry["url"] = f"https://vk.com/video{owner}_{vid}"
            files = payload.get("files") or {}
            file_url = None
            for key in ("mp4_1080", "mp4_720", "mp4_480", "mp4_360", "mp4_240", "external"):
                if files.get(key):
                    file_url = files[key]
                    break
            if not file_url and owner is not None and vid is not None:
                try:
                    vparams = {
                        "videos": f"{owner}_{vid}"
                        + (f"_{access_key}" if access_key else "")
                    }
                    vresp = api("video.get", token, **vparams)
                    items = vresp.get("items") or []
                    if items:
                        files = items[0].get("files") or {}
                        for key in (
                            "mp4_1080",
                            "mp4_720",
                            "mp4_480",
                            "mp4_360",
                            "mp4_240",
                            "external",
                        ):
                            if files.get(key):
                                file_url = files[key]
                                break
                        if not file_url:
                            entry["player"] = items[0].get("player")
                except VkError as exc:
                    entry["video_fetch_error"] = str(exc)
            entry["source_url"] = file_url
            if file_url and file_url.startswith("http"):
                lower = file_url.split("?")[0].lower()
                if ".mp4" in lower or "mp4_" in lower:
                    name = attachment_basename(post_id, idx, file_url, ".mp4")
                    local = media_dir / name
                    if download(file_url, local):
                        entry["path"] = f"{rel_prefix}/{name}"
                        idx += 1
                else:
                    entry["external"] = file_url

        elif atype == "audio":
            url = payload.get("url")
            entry["artist"] = payload.get("artist")
            entry["title"] = payload.get("title")
            entry["duration"] = payload.get("duration")
            entry["source_url"] = url
            if url:
                name = (
                    f"{post_id}_{idx}_"
                    f"{safe_filename(payload.get('artist') or 'audio')}_"
                    f"{safe_filename(payload.get('title') or 'track')}.mp3"
                )
                local = media_dir / name
                if download(url, local):
                    entry["path"] = f"{rel_prefix}/{name}"
                    idx += 1

        elif atype == "link":
            entry["title"] = payload.get("title")
            entry["url"] = payload.get("url")
            entry["caption"] = payload.get("caption")
            entry["description"] = payload.get("description")
            photo = payload.get("photo")
            if photo:
                url = best_photo_url(photo)
                entry["source_url"] = url
                if url:
                    name = attachment_basename(post_id, idx, url, ".jpg")
                    local = media_dir / name
                    if download(url, local):
                        entry["path"] = f"{rel_prefix}/{name}"
                        idx += 1

        elif atype == "poll":
            entry["question"] = payload.get("question")
            entry["votes"] = payload.get("votes")
            entry["answers"] = [
                {"text": a.get("text"), "votes": a.get("votes")}
                for a in (payload.get("answers") or [])
            ]

        elif atype == "album":
            entry["title"] = payload.get("title")
            entry["size"] = payload.get("size")
            entry["url"] = (
                f"https://vk.com/album{payload.get('owner_id')}_{payload.get('id')}"
            )
            thumb = payload.get("thumb")
            if thumb:
                url = best_photo_url(thumb)
                entry["source_url"] = url
                if url:
                    name = attachment_basename(post_id, idx, url, ".jpg")
                    local = media_dir / name
                    if download(url, local):
                        entry["path"] = f"{rel_prefix}/{name}"
                        idx += 1

        elif atype == "market":
            entry["title"] = payload.get("title")
            entry["price"] = (payload.get("price") or {}).get("text")
            entry["url"] = (
                f"https://vk.com/market?w=product"
                f"{payload.get('owner_id')}_{payload.get('id')}"
            )

        elif atype == "graffiti":
            url = payload.get("url") or payload.get("photo_604") or payload.get("photo_130")
            entry["source_url"] = url
            if url:
                name = attachment_basename(post_id, idx, url, ".png")
                local = media_dir / name
                if download(url, local):
                    entry["path"] = f"{rel_prefix}/{name}"
                    idx += 1

        elif atype == "page":
            entry["title"] = payload.get("title")
            entry["url"] = (
                f"https://vk.com/page-{abs(payload.get('group_id', 0))}_{payload.get('id')}"
            )

        elif atype == "note":
            entry["title"] = payload.get("title")
            entry["url"] = f"https://vk.com/note{payload.get('owner_id')}_{payload.get('id')}"

        else:
            entry["raw_keys"] = sorted(payload.keys())

        meta.append(entry)

    return meta, idx


def format_repost(copy: dict) -> str:
    owner = copy.get("owner_id") or copy.get("from_id")
    pid = copy.get("id")
    date = datetime.fromtimestamp(copy.get("date", 0), tz=timezone.utc).strftime("%Y-%m-%d")
    text = (copy.get("text") or "").strip()
    header = f"> Репост {date} · https://vk.com/wall{owner}_{pid}"
    if text:
        quoted = "\n".join(f"> {line}" if line else ">" for line in text.splitlines())
        return f"{header}\n>\n{quoted}"
    return header


def write_post(post: dict, *, out_root: Path, token: str) -> Path:
    post_id = int(post["id"])
    owner_id = int(post["owner_id"])
    ts = int(post.get("date", 0))
    dt = datetime.fromtimestamp(ts, tz=timezone.utc)
    year = str(dt.year)
    year_dir = out_root / year
    media_dir = year_dir / "media"
    media_dir.mkdir(parents=True, exist_ok=True)

    attachments_meta, next_idx = process_attachments(
        post.get("attachments") or [],
        post_id=post_id,
        media_dir=media_dir,
        rel_prefix="media",
        token=token,
    )

    copy_history_meta = []
    for copy in post.get("copy_history") or []:
        copy_atts, next_idx = process_attachments(
            copy.get("attachments") or [],
            post_id=post_id,
            media_dir=media_dir,
            rel_prefix="media",
            token=token,
            start_index=next_idx,
        )
        copy_history_meta.append(
            {
                "id": copy.get("id"),
                "owner_id": copy.get("owner_id"),
                "date": datetime.fromtimestamp(copy.get("date", 0), tz=timezone.utc)
                .isoformat()
                .replace("+00:00", "Z"),
                "url": f"https://vk.com/wall{copy.get('owner_id')}_{copy.get('id')}",
                "text": copy.get("text") or "",
                "attachments": copy_atts,
            }
        )

    front = {
        "id": post_id,
        "owner_id": owner_id,
        "date": dt.isoformat().replace("+00:00", "Z"),
        "url": f"https://vk.com/wall{owner_id}_{post_id}",
        "archived": bool(post.get("_archived")),
        "post_type": post.get("post_type"),
        "likes": (post.get("likes") or {}).get("count"),
        "reposts": (post.get("reposts") or {}).get("count"),
        "views": (post.get("views") or {}).get("count"),
        "comments": (post.get("comments") or {}).get("count"),
        "is_pinned": bool(post.get("is_pinned")),
        "attachments": attachments_meta,
    }
    if copy_history_meta:
        front["copy_history"] = copy_history_meta

    body_parts = []
    text = (post.get("text") or "").strip()
    if text:
        body_parts.append(text)

    for copy in post.get("copy_history") or []:
        body_parts.append(format_repost(copy))

    image_paths = [
        a["path"]
        for a in attachments_meta
        if a.get("path")
        and a.get("type") in ("photo", "posted_photo", "graffiti", "link", "album")
    ]
    for copy in copy_history_meta:
        for a in copy.get("attachments") or []:
            if a.get("path") and a.get("type") in (
                "photo",
                "posted_photo",
                "graffiti",
                "link",
                "album",
            ):
                image_paths.append(a["path"])

    if image_paths:
        body_parts.append("")
        for path in image_paths:
            body_parts.append(f"![]({path})")

    md = "---\n" + to_yaml(front) + "\n---\n\n" + "\n\n".join(body_parts).strip() + "\n"
    filename = f"{dt.strftime('%Y-%m-%d')}_{post_id}.md"
    out_path = year_dir / filename
    out_path.write_text(md, encoding="utf-8")
    return out_path


def main() -> int:
    parser = argparse.ArgumentParser(description="Export VK wall to markdown")
    parser.add_argument("--token", default=os.environ.get("VK_TOKEN", ""))
    parser.add_argument(
        "--token-file",
        default="",
        help="Path to file with access token (preferred over --token)",
    )
    parser.add_argument("--user", default="arturlun")
    parser.add_argument(
        "--out",
        default=str(Path(__file__).resolve().parents[1] / "content" / "vk"),
    )
    args = parser.parse_args()
    if args.token_file:
        args.token = Path(args.token_file).read_text(encoding="utf-8").strip()
    if not args.token:
        print("Pass --token-file, --token, or set VK_TOKEN", file=sys.stderr)
        return 1

    out_root = Path(args.out)
    out_root.mkdir(parents=True, exist_ok=True)

    owner_id, screen = resolve_owner(args.user)
    domain = None if owner_id is not None else screen
    print(
        f"Owner: {screen} → {owner_id if owner_id is not None else f'domain:{domain}'}",
        flush=True,
    )

    posts = fetch_wall(args.token, owner_id=owner_id, domain=domain, archived=False)
    archived = fetch_wall(args.token, owner_id=owner_id, domain=domain, archived=True)

    if owner_id is None and posts:
        owner_id = int(posts[0]["owner_id"])
        print(f"  resolved owner_id from wall: {owner_id}", flush=True)

    by_id: dict[int, dict] = {}
    for p in archived + posts:
        by_id[int(p["id"])] = p
    unique = sorted(by_id.values(), key=lambda p: p.get("date", 0))

    print(
        f"Total unique posts: {len(unique)} (public={len(posts)}, archived={len(archived)})",
        flush=True,
    )

    for i, post in enumerate(unique, 1):
        path = write_post(post, out_root=out_root, token=args.token)
        print(f"[{i}/{len(unique)}] {path.relative_to(out_root.parent.parent)}", flush=True)

    manifest = {
        "owner_id": owner_id,
        "screen_name": screen,
        "exported_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "posts": len(unique),
        "public": len(posts),
        "archived": len(archived),
    }
    (out_root / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    print("Done:", json.dumps(manifest, ensure_ascii=False), flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
