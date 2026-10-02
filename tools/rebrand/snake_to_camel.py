"""Unify naming on the camelCase style (tawasulPerson / tawasulPersonID).

Most modules use tawasulPerson; the TawasulCore API and the old tos_os dump
used snake_case (tos_person / person_id). This script:
  1. writes migrations/tos_snake_to_camel.sql to rename an existing database
  2. rewrites TawasulCore + TawasulFinance code from snake to camel names
Usage: python3 snake_to_camel.py /path/to/gibbon-checkout
"""
import os, re, sys
SRC = sys.argv[1] if len(sys.argv) > 1 else '/tmp/gibbon'
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))

def parse(p):
    t = {}; cur = None
    for line in open(p, encoding='utf8', errors='ignore'):
        m = re.match(r"CREATE TABLE (?:IF NOT EXISTS )?`(\w+)`", line)
        if m: cur = m.group(1); t[cur] = []; continue
        if cur:
            m = re.match(r"\s+`(\w+)`\s", line)
            if m: t[cur].append(m.group(1))
            elif line.startswith(')'): cur = None
    return t

def snake(n):
    n = re.sub(r'^gibbon', '', n)
    n = re.sub(r'^IN(?=[A-Z]|$)', 'IndividualNeeds', n)
    n = re.sub(r'^TT(?=[A-Z]|$)', 'Timetable', n)
    return re.sub(r'(?<!^)(?=[A-Z])', '_', n).lower()

def camel(n): return re.sub(r'gibbon', 'tawasul', n)

g = parse(SRC + '/gibbon.sql')
s = parse(ROOT + '/tawasul_os.sql')
tables, cols, sql = {}, {}, ['-- Rename an existing tos_* (snake) database to the tawasul* naming used by the code.', 'SET FOREIGN_KEY_CHECKS=0;']
for gt, gc in g.items():
    st = 'tos_' + snake(gt)
    if st not in s: continue
    ct = camel(gt); tables[st] = ct
    sql.append(f'RENAME TABLE `{st}` TO `{ct}`;')
    for a, b in zip(gc, s[st]):
        if a != b:
            cols[b] = camel(a)
            sql.append(f'ALTER TABLE `{ct}` RENAME COLUMN `{b}` TO `{camel(a)}`;')
sql.append('SET FOREIGN_KEY_CHECKS=1;')
os.makedirs(ROOT + '/migrations', exist_ok=True)
open(ROOT + '/migrations/tos_snake_to_camel.sql', 'w').write('\n'.join(sql) + '\n')
print('tables', len(tables), 'columns', len(cols))

tre = re.compile(r'\b(' + '|'.join(sorted(map(re.escape, tables), key=len, reverse=True)) + r')\b')
cre = re.compile(r'\b(' + '|'.join(sorted(map(re.escape, cols), key=len, reverse=True)) + r')\b')
changed = 0
for mod in ('TawasulCore', 'TawasulFinance'):
    for root, _, fs in os.walk(os.path.join(ROOT, 'modules', mod)):
        for f in fs:
            if not f.endswith(('.php', '.json', '.js')): continue
            p = os.path.join(root, f)
            t = open(p, encoding='utf8', errors='surrogateescape').read()
            if 'tos_' not in t: continue
            n = tre.sub(lambda m: tables[m.group(1)], t)
            n = cre.sub(lambda m: cols[m.group(1)], n)
            if n != t:
                open(p, 'w', encoding='utf8', errors='surrogateescape').write(n); changed += 1
print('files updated', changed)
