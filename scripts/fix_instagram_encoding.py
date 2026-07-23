#!/usr/bin/env python3
"""
Fix Instagram mojibake in corpus/chronicle_entries.jsonl
(UTF-8 text that was decoded as latin1/cp1252).

Usage:
  python3 scripts/fix_instagram_encoding.py
"""
from __future__ import annotations

import json
import re
import unicodedata
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORPUS_PATH = ROOT / "corpus/chronicle_entries.jsonl"


def _bytes_from_mojibake(text: str) -> bytes:
    out = bytearray()
    for ch in text:
        o = ord(ch)
        if o < 256:
            out.append(o)
        else:
            # Already-decoded char inside broken string — keep its UTF-8 bytes
            out.extend(ch.encode("utf-8"))
    return bytes(out)


def _decode_utf8_tolerant(data: bytes) -> str:
    """Decode UTF-8, trimming incomplete trailing sequences / skipping bad bytes."""
    if not data:
        return ""
    buf = data
    while buf:
        try:
            return buf.decode("utf-8")
        except UnicodeDecodeError as exc:
            # Incomplete sequence at the end (often truncated emoji)
            if exc.end >= len(buf):
                buf = buf[: exc.start]
                continue
            # Skip the bad byte and continue
            buf = buf[: exc.start] + buf[exc.start + 1 :]
    return ""


def fix_instagram_text(value: str | None) -> str:
    if not value:
        return ""
    text = value
    if text.startswith("`"):
        text = text[1:].lstrip()

    candidates = [text]
    for enc in ("latin1", "cp1252"):
        try:
            candidates.append(_decode_utf8_tolerant(text.encode(enc)))
        except UnicodeEncodeError:
            pass

    # Mixed / truncated mojibake (common in Instagram exports)
    candidates.append(_decode_utf8_tolerant(_bytes_from_mojibake(text)))

    try:
        once = _decode_utf8_tolerant(text.encode("latin1"))
        candidates.append(_decode_utf8_tolerant(once.encode("latin1")))
    except UnicodeEncodeError:
        pass

    def score(t: str) -> tuple[int, int, int, int]:
        cyr = len(re.findall(r"[А-Яа-яЁё]", t))
        hebrew = len(re.findall(r"[\u0590-\u05FF]", t))
        latin = len(re.findall(r"[A-Za-z]", t))
        # ÐÑÂÃ — Cyrillic mojibake; × — common for Hebrew/Arabic UTF-8 misread as latin1
        mojibake = len(re.findall(r"[ÐÑÂÃ×]", t))
        replacement = t.count("\ufffd")
        good = cyr + hebrew + latin
        return (good - 4 * mojibake - 10 * replacement, -mojibake, good, len(t))

    best = max(candidates, key=score)
    best = unicodedata.normalize("NFC", best)
    # Strip leftover broken emoji prefixes like ðŸ˜
    best = re.sub(r"ðŸ[\x98-\x9f]?$", "", best).rstrip()
    return best


def is_mojibake(value: str | None) -> bool:
    if not value:
        return False
    cyr = len(re.findall(r"[А-Яа-яЁё]", value))
    marks = len(re.findall(r"[ÐÑÂÃ]", value))
    return marks >= 2 and marks > cyr


def build_title(text: str) -> str:
    """Keep in sync with scripts/corpus_build.build_title (word-safe ≤80)."""
    from corpus_build import build_title as _build

    return _build(text)


def main() -> None:
    rows: list[dict] = []
    with CORPUS_PATH.open(encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if line:
                rows.append(json.loads(line))

    updated = 0
    for row in rows:
        if row.get("platform") != "instagram":
            continue
        changed = False

        for field in ("title", "lede", "slug_hint"):
            raw = row.get(field)
            if isinstance(raw, str):
                fixed = fix_instagram_text(raw)
                if fixed != raw:
                    row[field] = fixed
                    changed = True

        body = ""
        for block in row.get("blocks") or []:
            if not isinstance(block, dict):
                continue
            for field in ("body", "alt", "caption", "author"):
                raw = block.get(field)
                if isinstance(raw, str):
                    fixed = fix_instagram_text(raw)
                    if fixed != raw:
                        block[field] = fixed
                        changed = True
            if block.get("type") == "paragraph" and not body:
                body = str(block.get("body") or "")

        if body:
            body = fix_instagram_text(body)
            # Always rebuild title from body with word-safe truncation
            new_title = build_title(body)
            if row.get("title") != new_title:
                row["title"] = new_title
                changed = True
            new_lede = body.split("\n", 1)[0][:240]
            if row.get("lede") != new_lede:
                row["lede"] = new_lede
                changed = True

        if changed:
            updated += 1

    with CORPUS_PATH.open("w", encoding="utf-8") as fh:
        for row in rows:
            fh.write(json.dumps(row, ensure_ascii=False) + "\n")

    still_bad = 0
    for row in rows:
        if row.get("platform") != "instagram":
            continue
        if is_mojibake(row.get("title") or "") or is_mojibake(row.get("lede") or ""):
            still_bad += 1
            continue
        if any(is_mojibake(b.get("body") or "") for b in row.get("blocks") or [] if isinstance(b, dict)):
            still_bad += 1

    print(f"Updated {updated} Instagram entries → {CORPUS_PATH}")
    print(f"Still mojibake: {still_bad}")


if __name__ == "__main__":
    main()
