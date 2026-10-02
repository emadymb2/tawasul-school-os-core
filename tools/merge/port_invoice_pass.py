#!/usr/bin/env python3
"""Second pass of the port: the files whose columns had to be read by hand.

tools/merge/port.py skipped these because they touch the invoice model, whose
column names differ per table. They still need the mechanical part of the port
-- module path, table prefix, service namespace -- which this applies, and which
carries none of the withheld invoice renames. The invoice columns are then left
in place for the hand edit, so anything still naming a SchoolAccounting column
here is a genuine to-do rather than a silent breakage.

  python3 tools/merge/port_invoice_pass.py           # dry run
  python3 tools/merge/port_invoice_pass.py --apply
"""
import os
import re
import sys
import collections

SOURCE = 'modules/SchoolAccounting'
TARGET = 'modules/TawasulFinance'
APPLY = '--apply' in sys.argv

FILES = [
    'invoices_generate.php',
    'invoices_generateProcess.php',
    'invoices_cancelProcess.php',
    'statement.php',
    'receipts_add.php',
    'receipts_addProcess.php',
    'receipts_manage.php',
    'receipts_print.php',
    'report_aging.php',
    'report_collections.php',
    'dashboard.php',
    # src/Service/Billing.php is deliberately absent: it is a hand rewrite onto
    # the surviving invoice model, and running the mechanical pass over it would
    # put the old body back.
]


with open('tools/merge/global_rename_data.php', encoding='utf-8') as fh:
    raw = fh.read()
# The rename table is a PHP array literal; take the quoted pairs.
renames = sorted(re.findall(r"'([^']+)'\s*=>\s*'([^']+)'", raw), key=lambda kv: -len(kv[0]))


def basic(text: str):
    counts = collections.Counter()
    out = text
    out, n = re.subn(r'/modules/SchoolAccounting/', '/modules/TawasulFinance/', out)
    counts['module path'] = n
    out, n = re.subn(r'tawasulSchoolAccounting(?=[A-Z0-9_])', 'tawasulFinance', out)
    counts['table prefix'] = n
    out, n = re.subn(r'Tos\\Module\\SchoolAccounting', r'Tos\\Module\\TawasulFinance', out)
    counts['service namespace'] = n
    # The unambiguous column renames apply here too: the withheld ones are
    # exactly the invoice columns, which are resolved by hand below.
    for src, dst in renames:
        if src == dst:
            continue
        out, n = re.subn(r'(?<![A-Za-z0-9_])' + re.escape(src) + r'(?![A-Za-z0-9_])', dst, out)
        if n:
            counts[f'column {src}'] = n
    return out, counts


todo = collections.defaultdict(set)
written = 0

for rel in FILES:
    src = os.path.join(SOURCE, rel)
    if not os.path.exists(src):
        print(f'  missing: {rel}')
        continue

    text = open(src, encoding='utf-8').read()
    new, counts = basic(text)
    if new == text:
        print(f'  unchanged: {rel}')
        continue

    if APPLY:
        dest = os.path.join(TARGET, rel)
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        with open(dest, 'w', encoding='utf-8') as fh:
            fh.write(new)
        written += 1

    # Billing.php is a hand rewrite and no longer needs this pass; anything
    # else still naming a withheld invoice column is unfinished.
    if rel != 'src/Service/Billing.php':
        for name in re.findall(r'\b(?:invoiceNumber|installmentNo|issueDate|dueDate|feePlanID|feeItemID)\b', new):
            todo[rel].add(name)

print(('APPLIED' if APPLY else 'DRY RUN') + f': {written if APPLY else len(FILES)} files')
if todo:
    print('\nwithheld invoice columns still to resolve by hand:')
    for rel, names in sorted(todo.items()):
        print(f'  {rel}: {", ".join(sorted(names))}')
else:
    print('\nno withheld invoice column remains')
