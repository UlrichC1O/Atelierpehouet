#!/usr/bin/env python3
"""Animation catalogue builder & auditor for Ateliers Pehouet.

Parses every @keyframes in public/css/**/*.css together with the /** @anim … */ doc comment
that must precede it, validates them, numbers them and writes resources/content/animations.json
(the data behind the "Mouvement" page).

    python3 python/tools/animations.py            # rebuild the JSON + print a report
    python3 python/tools/animations.py --check    # exit 1 on any problem, < 100 animations, or stale JSON
    python3 python/tools/animations.py --root DIR --min 100 --json-out PATH
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path

GROUPS = ["brand", "text", "reveal", "ui", "ambient", "loader", "transition", "page", "scene"]
DEMOS = {"shape", "blocks", "text", "letters", "path", "bar", "ring", "dots", "panel", "scene"}
DEFAULTS = {"dur": "2.4s", "ease": "ease-in-out", "dir": "normal", "iter": "infinite"}
PREFIXES = ("ap-",)

KEYFRAMES = re.compile(r"@(-webkit-|-moz-|-o-)?keyframes\s+([A-Za-z0-9_-]+)\s*\{")
DOC = re.compile(r"/\*\*\s*@anim\b(.*?)\*/", re.S)
ATTR = re.compile(r'([a-z]+)=("([^"]*)"|\S+)')


@dataclass
class Keyframe:
    name: str
    file: str
    line: int
    start: int
    end: int
    attrs: dict = field(default_factory=dict)
    documented: bool = False


@dataclass
class Report:
    keyframes: list[Keyframe] = field(default_factory=list)
    problems: list[str] = field(default_factory=list)


def css_files(root: Path) -> list[Path]:
    base = root / "public" / "css"
    top = sorted(p for p in base.glob("*.css"))
    pages = sorted((base / "pages").glob("*.css")) if (base / "pages").is_dir() else []
    scenes = sorted((base / "scenes").glob("*.css")) if (base / "scenes").is_dir() else []
    return top + pages + scenes


def block_end(text: str, open_brace: int) -> int:
    """Index just after the brace matching text[open_brace] (comments and strings aware)."""
    depth, i, n = 0, open_brace, len(text)
    while i < n:
        ch = text[i]
        if text.startswith("/*", i):
            j = text.find("*/", i + 2)
            i = n if j == -1 else j + 2
            continue
        if ch in "\"'":
            j = i + 1
            while j < n and text[j] != ch:
                j += 2 if text[j] == "\\" else 1
            i = j + 1
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return i + 1
        i += 1
    return n


def parse_attrs(raw: str) -> dict:
    out = {}
    for key, value, quoted in ATTR.findall(raw):
        out[key] = quoted if value.startswith('"') else value
    return out


def strip_comments(text: str) -> str:
    return re.sub(r"/\*.*?\*/", lambda m: " " * len(m.group(0)), text, flags=re.S)


def scan(root: Path) -> Report:
    report = Report()
    seen: dict[str, Keyframe] = {}
    for path in css_files(root):
        rel = path.relative_to(root / "public").as_posix()
        text = path.read_text(encoding="utf-8")
        code = strip_comments(text)
        for m in KEYFRAMES.finditer(code):
            if m.group(1):
                continue  # vendor-prefixed duplicate
            name = m.group(2)
            line = text.count("\n", 0, m.start()) + 1
            kf = Keyframe(name, rel, line, m.start(), block_end(code, m.end() - 1))
            # The doc comment must be the last thing before the @keyframes.
            before = text[: m.start()].rstrip()
            if before.endswith("*/"):
                start = before.rfind("/**")
                doc = DOC.search(before[start:]) if start != -1 else None
                if doc and before[start:].strip().endswith("*/"):
                    kf.attrs = parse_attrs(doc.group(1))
                    kf.documented = True
            if name in seen:
                report.problems.append(f"{rel}:{line}: duplicate keyframes '{name}' (first in {seen[name].file}:{seen[name].line})")
                continue
            seen[name] = kf
            report.keyframes.append(kf)
    validate(root, report)
    return report


def validate(root: Path, report: Report) -> None:
    usage_text = []
    for path in css_files(root):
        usage_text.append((path, strip_comments(path.read_text(encoding="utf-8"))))
    extra = list((root / "resources" / "views").rglob("*.blade.php")) + list((root / "public" / "js").glob("*.js"))
    extra_text = "\n".join(p.read_text(encoding="utf-8") for p in extra if p.is_file())

    for kf in report.keyframes:
        where = f"{kf.file}:{kf.line}"
        if not kf.name.startswith(PREFIXES):
            report.problems.append(f"{where}: '{kf.name}' must start with 'ap-'")
        if not kf.documented:
            report.problems.append(f"{where}: '{kf.name}' has no /** @anim … */ doc comment")
            continue
        a = kf.attrs
        for req in ("group", "demo", "fr", "en"):
            if not a.get(req):
                report.problems.append(f"{where}: '{kf.name}' doc comment lacks {req}=")
        if a.get("group") and a["group"] not in GROUPS:
            report.problems.append(f"{where}: '{kf.name}' unknown group '{a['group']}'")
        if a.get("demo") and a["demo"] not in DEMOS:
            report.problems.append(f"{where}: '{kf.name}' unknown demo '{a['demo']}'")
        if a.get("demo") == "scene" and not a.get("scene"):
            report.problems.append(f"{where}: '{kf.name}' demo=scene needs scene=<slug>")
        # Usage: referenced outside its own block in any CSS file, or in Blade / JS.
        pattern = re.compile(r"(?<![A-Za-z0-9_-])" + re.escape(kf.name) + r"(?![A-Za-z0-9_-])")
        used = False
        for path, code in usage_text:
            rel = path.relative_to(root / "public").as_posix()
            for m in pattern.finditer(code):
                if rel == kf.file and kf.start <= m.start() < kf.end:
                    continue
                if rel == kf.file and code[max(0, m.start() - 40): m.start()].rstrip().endswith("keyframes"):
                    continue
                used = True
                break
            if used:
                break
        if not used and not pattern.search(extra_text):
            report.problems.append(f"{where}: '{kf.name}' is never used (no animation references it)")


def catalogue(report: Report) -> dict:
    order = {g: i for i, g in enumerate(GROUPS)}
    files: list[str] = []
    for kf in report.keyframes:
        if kf.file not in files:
            files.append(kf.file)
    entries = sorted(
        (kf for kf in report.keyframes if kf.documented),
        key=lambda k: (order.get(k.attrs.get("group", ""), len(GROUPS)), files.index(k.file), k.start),
    )
    out = []
    for n, kf in enumerate(entries, start=1):
        a = kf.attrs
        out.append({
            "n": n,
            "name": kf.name,
            "group": a.get("group"),
            "demo": a.get("demo"),
            "file": "css/" + kf.file.split("css/", 1)[-1],
            "scene": a.get("scene"),
            "label": {"fr": a.get("fr", kf.name), "en": a.get("en", a.get("fr", kf.name))},
            "dur": a.get("dur", DEFAULTS["dur"]),
            "ease": a.get("ease", DEFAULTS["ease"]),
            "dir": a.get("dir", DEFAULTS["dir"]),
            "iter": a.get("iter", DEFAULTS["iter"]),
        })
    groups = [g for g in GROUPS if any(e["group"] == g for e in out)]
    return {"version": 1, "total": len(out), "groups": groups, "animations": out}


def dumps(data: dict) -> str:
    return json.dumps(data, ensure_ascii=False, indent=2) + "\n"


def main(argv: list[str] | None = None) -> int:
    here = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--root", type=Path, default=here)
    parser.add_argument("--json-out", type=Path, default=None)
    parser.add_argument("--min", type=int, default=100)
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--quiet", action="store_true")
    args = parser.parse_args(argv)

    root = args.root.resolve()
    out = args.json_out or root / "resources" / "content" / "animations.json"
    report = scan(root)
    data = catalogue(report)
    text = dumps(data)

    if not args.quiet:
        by_group: dict[str, int] = {}
        by_file: dict[str, int] = {}
        for e in data["animations"]:
            by_group[e["group"]] = by_group.get(e["group"], 0) + 1
            by_file[e["file"]] = by_file.get(e["file"], 0) + 1
        print(f"{data['total']} animations ({len(report.keyframes)} @keyframes found)")
        print("  by group: " + ", ".join(f"{g} {c}" for g, c in by_group.items()))
        for f, c in by_file.items():
            print(f"  {c:4d}  {f}")

    problems = list(report.problems)
    if data["total"] < args.min:
        problems.append(f"only {data['total']} animations — at least {args.min} are required")

    if args.check:
        current = out.read_text(encoding="utf-8") if out.is_file() else ""
        if current != text:
            problems.append(f"{out.relative_to(root) if out.is_relative_to(root) else out} is stale — run python3 python/tools/animations.py")
    else:
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(text, encoding="utf-8")
        if not args.quiet:
            print(f"wrote {out}")

    for p in problems:
        print("✗ " + p, file=sys.stderr)
    if problems:
        print(f"{len(problems)} problem(s)", file=sys.stderr)
        return 1
    if not args.quiet:
        print("✓ animation catalogue OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
