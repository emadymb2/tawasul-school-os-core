#!/usr/bin/env python3
"""Port modules/SchoolAccounting/ into modules/TawasulFinance/ as one module.

The two modules turned out to hold the same accounting model under different
naming, so the port is mechanical for everything except the invoice pages:

  1. the module path in URLs and the access check
  2. the table prefix      tawasulSchoolAccountingX -> tawasulFinanceX
  3. the column renames    parentID -> parentAccountID and 54 others
  4. the service namespace Tos\\Module\\SchoolAccounting -> Tos\\Module\\TawasulFinance

Steps 2 and 3 are ordered deliberately. Step 2 rewrites the module's own key and
foreign-key columns wholesale, and step 3 then handles only the bare names it
still has to touch. Both are applied on word boundaries, and the two modules
happily use a different case for the two kinds of column (tawasulFinanceAccountID
against accountID), so a bare name never matches inside a prefixed one.

feePlanID and feeItemID are excluded from the global rename because they resolve
differently in Invoice/InvoiceLine, whose model is rewritten rather than renamed.
Those files are listed by --excluded and must be ported by hand.

  python3 tools/merge/port.py           # dry run, prints what would change
  python3 tools/merge/port.py --apply   # write the ported files
"""
import os
import re
import sys
import json
import collections

ROOT = 'modules'
SOURCE = os.path.join(ROOT, 'SchoolAccounting')
TARGET = os.path.join(ROOT, 'TawasulFinance')
APPLY = '--apply' in sys.argv

# The invoice model differs between the modules, so its column renames are
# withheld from the global table. Every file that reads those columns is ported
# by hand rather than renamed: some because it drives the model directly, others
# because they join to the invoice from a receipt or a report.
HAND_PORT = {
    'invoices_generate.php',
    'invoices_generateProcess.php',
    'invoices_cancelProcess.php',
    'statement.php',
    'src/Service/Billing.php',
    'receipts_add.php',
    'receipts_addProcess.php',
    'receipts_manage.php',
    'receipts_print.php',
    'report_aging.php',
    'report_collections.php',
    'dashboard.php',
}

# The manifest, CHANGEDB and version belong to the module being retired; the
# surviving module keeps its own. moduleFunctions.php is merged by hand too,
# because 150 ported pages call the sa* helpers it defines.
SKIP = {'manifest.php', 'CHANGEDB.php', 'version.php', 'moduleFunctions.php'}

# Filenames both modules ship. TawasulFinance's copy is the live one, so the
# SchoolAccounting page is not written over it.
COLLIDES = {
    'accounts_manage.php', 'budgets_manage.php', 'invoices_manage.php',
    'invoices_view.php',
}

with open('tools/merge/global_rename_data.php', encoding='utf-8') as fh:
    raw = fh.read()
# The file is a PHP array literal; take the quoted pairs rather than shelling
# out to PHP for every run.
renames = dict(re.findall(r"'([^']+)'\s*=>\s*'([^']+)'", raw))
# Longest first so a name that is a prefix of another never wins the match.
ordered = sorted(renames.items(), key=lambda kv: -len(kv[0]))

# Applying rules in sequence is only safe if no replacement can create a string
# that a later rule would match again. Check every target for a word-bounded
# occurrence of any other source name.
def word_bounded_contains(haystack: str, needle: str) -> bool:
    for m in re.finditer(re.escape(needle), haystack):
        before = haystack[m.start() - 1] if m.start() else ''
        after = haystack[m.end()] if m.end() < len(haystack) else ''
        if not (before.isalnum() or before == '_') and not (after.isalnum() or after == '_'):
            return True
    return False

hazards = []
for src, dst in ordered:
    if src == dst:
        continue
    for other, _ in ordered:
        if other == src:
            continue
        if word_bounded_contains(dst, other):
            hazards.append(f'replacing {src} -> {dst} would create text matching {other}')
if hazards:
    for h in hazards:
        print('HAZARD: ' + h)
    sys.exit(1)

def transform(text: str):
    counts = collections.Counter()
    out = text

    # 1. module path
    out, n = re.subn(r'/modules/SchoolAccounting/', '/modules/TawasulFinance/', out)
    counts['module path'] = n

    # 2. the module's own table and key columns
    out, n = re.subn(r'tawasulSchoolAccounting(?=[A-Z0-9_])', 'tawasulFinance', out)
    counts['table prefix'] = n

    # 3. the remaining column renames, on word boundaries
    for src, dst in ordered:
        if src == dst:
            continue
        out, n = re.subn(r'(?<![A-Za-z0-9_])' + re.escape(src) + r'(?![A-Za-z0-9_])', dst, out)
        if n:
            counts[f'column {src}'] = n

    # 4. the service namespace
    out, n = re.subn(r'Tos\\Module\\SchoolAccounting', r'Tos\\Module\\TawasulFinance', out)
    counts['service namespace'] = n

    return out, counts


portable = []
hand_port_present = []
collisions = []
totals = collections.Counter()

for dirpath, dirnames, filenames in os.walk(SOURCE):
    dirnames[:] = [d for d in dirnames if d != '.git']
    for name in sorted(filenames):
        if not name.endswith('.php'):
            continue
        rel = os.path.relpath(os.path.join(dirpath, name), SOURCE)
        if rel in SKIP:
            continue
        if rel in HAND_PORT:
            hand_port_present.append(rel)
            continue

        path = os.path.join(SOURCE, rel)
        with open(path, encoding='utf-8') as fh:
            original = fh.read()

        new, counts = transform(original)
        if new == original:
            continue

        for k, v in counts.items():
            totals[k] += v

        if rel in COLLIDES:
            collisions.append(rel)
            continue

        portable.append((rel, new))

print(('APPLIED' if APPLY else 'DRY RUN') + f': {len(portable)} files to write into {TARGET}/')
print()
for k, v in totals.most_common(12):
    print(f'  {v:5d}  {k}')
if len(totals) > 12:
    print(f'  ... and {len(totals)-12} further column rules')

if collisions:
    print(f'\n{len(collisions)} filename collisions -- TawasulFinance already ships these, not overwritten:')
    for c in collisions:
        print(f'  {c}')

print(f'\n{len(hand_port_present)} files need a hand port (different invoice model):')
for e in hand_port_present:
    print(f'  {e}')

if APPLY:
    written = 0
    for rel, new in portable:
        dest = os.path.join(TARGET, rel)
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        with open(dest, 'w', encoding='utf-8') as fh:
            fh.write(new)
        written += 1
    print(f'\nwrote {written} files')
