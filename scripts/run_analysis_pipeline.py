#!/usr/bin/env python3
"""Run full analysis pipeline: stats + mirror export."""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"


def run(name: str, *args: str) -> None:
    cmd = [sys.executable, str(SCRIPTS / name), *args]
    print(f"\n→ {' '.join(cmd)}")
    subprocess.run(cmd, check=True, cwd=ROOT)


def main() -> None:
    corpus = ROOT / "corpus" / "chronicle_entries.jsonl"
    if not corpus.is_file():
        print("corpus/chronicle_entries.jsonl not found.")
        print("Build first: python3 scripts/corpus_build.py [--instagram]")
        raise SystemExit(1)

    run("analyze_corpus_deep.py")
    run("export_analysis_mirror.py")
    print("\nDone. See analysis/deep-2026-07-21/data/ and analysis/deep-2026-07-21/mirror/")


if __name__ == "__main__":
    main()
