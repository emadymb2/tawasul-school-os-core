#!/usr/bin/env python3
"""Checks every intra-module link in the merged TawasulFinance module.

A UI merge is mostly a question of whether the pages still point at each other,
and the ported pages inherited URLs from the module they came from. This reports
any setURL()/q= target, include or redirect that does not resolve to a file in
the module, so a broken entry point is found before the page is opened.

  python3 tools/merge/check_links.py
"""
import os
import re
import sys

MODULE = 'modules/TawasulFinance'

# How a page can name another page.
PATTERNS = [
    re.compile(r"setURL\(\s*'(/modules/TawasulFinance/[^']+\.php)'"),
    re.compile(r"require_once\s+__DIR__\s*\.\s*'(/[^']+\.php)'"),
    re.compile(r"q=(/modules/TawasulFinance/[^'\"&\s]+\.php)"),
    re.compile(r"breadcrumbs->add\([^,]+,\s*'([^']+\.php)'\)"),
]

def resolve(target: str) -> str:
    """Map a link as written in a page onto a path in the module.

    Pages name each other both module-absolute (/modules/TawasulFinance/x.php)
    and relative to the module (x.php), so both forms have to be normalised
    before the file can be looked up."""
    t = target.split('?', 1)[0].split('#', 1)[0]
    for prefix in ('/modules/TawasulFinance/', '/modules/SchoolAccounting/', '/'):
        if t.startswith(prefix):
            t = t[len(prefix):]
            break
    return os.path.normpath(os.path.join(MODULE, t))


checked = 0
broken = []

for name in sorted(os.listdir(MODULE)):
    if not name.endswith('.php'):
        continue
    path = os.path.join(MODULE, name)
    text = open(path, encoding='utf-8').read()

    for pattern in PATTERNS:
        for target in pattern.findall(text):
            checked += 1
            if not os.path.exists(resolve(target)):
                broken.append((name, target))

# Redirects of the form header("Location: {$URL}...")
redirect = re.compile(r"\$URL\s*=\s*'([^']*q=)(/modules/TawasulFinance/[^'&]+\.php)")
for name in sorted(os.listdir(MODULE)):
    if not name.endswith('.php'):
        continue
    text = open(os.path.join(MODULE, name), encoding='utf-8').read()
    for _, target in redirect.findall(text):
        checked += 1
        if not os.path.exists(resolve(target)):
            broken.append((name, target))

print(f'links checked: {checked}')
if broken:
    print(f'BROKEN: {len(broken)}')
    seen = set()
    for name, target in broken:
        if (name, target) in seen:
            continue
        seen.add((name, target))
        print(f'  {name} -> {target}')
    sys.exit(1)

print('every intra-module link resolves')
