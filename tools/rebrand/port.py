"""Rebuild Tawasul OS core from upstream Gibbon, white-labelled.

Usage: python3 port.py /path/to/gibbon-core-checkout
Applies the same renaming your modules already use:
  Gibbon\\X -> TawasulOS\\X, Gibbon\\Module\\N -> Tos\\Module\\TawasulN,
  gibbonPerson -> tawasulPerson, modules/Attendance -> modules/TawasulAttendance,
  gibbon.php -> tawasul.php. GPL copyright headers are kept untouched.
"""
import os, re, sys, json, shutil

SRC = sys.argv[1] if len(sys.argv) > 1 else '/tmp/gibbon'
DST = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
LEGACY = DST + '/_legacy_shell'
DOMAIN = 'tos.fiksutiliratkaisut.fi'

MODULES = sorted(os.listdir(SRC + '/modules'), key=len, reverse=True)
def tmod(n): return 'Tawasul' + n.replace(' ', '')

TEXT_EXT = {'.php', '.js', '.twig', '.html', '.sql', '.json', '.css', '.xml', '.yml', '.yaml', '.neon', '.sh', '.md', '.txt', '.ini', '.htaccess', '.dist', ''}
SKIP_FILES = {'LICENSE', 'CHANGELOG.txt'}
HEADER_RE = re.compile(r'\A(<\?php\s*/\*.*?\*/)', re.S)

def transform(s):
    for n in MODULES:
        t = tmod(n); esc = re.escape(n); ns = n.replace(' ', '')
        s = re.sub(r'modules/' + esc + r'(?=/|\'|"|$)', 'modules/' + t, s)
        s = s.replace('modules/' + n.replace(' ', '%20') + '/', 'modules/' + t + '/')
        s = s.replace('Gibbon\\\\Module\\\\' + ns + '\\\\', 'Tos\\\\Module\\\\' + t + '\\\\')
        s = s.replace('Gibbon\\Module\\' + ns + '\\', 'Tos\\Module\\' + t + '\\')
        for q in ("'", '"'):
            s = s.replace('registerModuleNamespace(' + q + n + q, 'registerModuleNamespace(' + q + t + q)
            s = re.sub(r"(\bname\s*=\s*)" + q + esc + q, r'\g<1>' + q + t + q, s)
            s = re.sub(r"(getModuleByName\(|getModuleIDFromName\(\$connection2,\s*|isModuleAccessible\(\$guid,\s*\$connection2,\s*)" + q + esc + q, r'\g<1>' + q + t + q, s)
    s = s.replace('Gibbon\\\\Module\\\\', 'Tos\\\\Module\\\\').replace('Gibbon\\Module\\', 'Tos\\Module\\')
    s = s.replace('Gibbon\\\\', 'TawasulOS\\\\').replace('Gibbon\\', 'TawasulOS\\')
    s = s.replace('gibbonedu.org', DOMAIN).replace('gibbonedu/core', 'afhaam/tawasul-os')
    s = s.replace('gibbon.php', 'tawasul.php').replace('gibbon_demo.sql', 'tawasul_demo.sql').replace('gibbon.sql', 'tawasul.sql')
    s = re.sub(r'\bgibbon(?=[A-Z_0-9])', 'tawasul', s)
    s = re.sub(r'\$gibbon\b', '$tawasul', s)
    s = re.sub(r'\bGIBBON(?=_|\b)', 'TAWASUL', s)
    s = re.sub(r'\bGibbon(?=[A-Z])', 'TawasulOS', s)
    s = re.sub(r'\bGibbon\b', 'TawasulOS', s)
    s = re.sub(r'\bgibbon\b', 'tawasul', s)
    return s

def transform_file(text):
    m = HEADER_RE.match(text)
    if m and 'Copyright' in m.group(1) and 'Gibbon' in m.group(1):
        return m.group(1) + transform(text[m.end():])
    return transform(text)

def dest_name(rel):
    rel = rel.replace('src/Gibbon', 'src/TawasulOS')
    base = os.path.basename(rel)
    nb = {'gibbon.php': 'tawasul.php', 'gibbon.sql': 'tawasul.sql', 'gibbon_demo.sql': 'tawasul_demo.sql'}.get(base, base)
    nb = re.sub(r'^gibbon', 'tawasul', nb)
    return os.path.join(os.path.dirname(rel), nb)

# 1. move the old login/dashboard shell aside (kept for reference)
os.makedirs(LEGACY, exist_ok=True)
for item in ['tawasul.php', 'src', 'public', 'config', 'migrate_data.php', 'vendor']:
    p = os.path.join(DST, item)
    if os.path.exists(p) and not os.path.exists(os.path.join(LEGACY, item)):
        shutil.move(p, os.path.join(LEGACY, item))

# 2. copy + transform Gibbon core (not its modules; yours are already renamed)
count = 0
for root, dirs, files in os.walk(SRC):
    rel_root = os.path.relpath(root, SRC)
    if rel_root == '.':
        dirs[:] = [d for d in dirs if d not in ('.git', 'modules', 'vendor', '.github')]
    for f in files:
        rel = os.path.normpath(os.path.join(rel_root, f))
        if rel.startswith('uploads/') and f not in ('.htaccess', 'index.html'):
            continue
        out = os.path.join(DST, dest_name(rel))
        os.makedirs(os.path.dirname(out), exist_ok=True)
        src = os.path.join(root, f); ext = os.path.splitext(f)[1]
        if f in SKIP_FILES or ext not in TEXT_EXT or (rel.startswith('i18n/') and ext != '.php'):
            shutil.copy2(src, out)
        else:
            try: txt = open(src, encoding='utf8').read()
            except UnicodeDecodeError: shutil.copy2(src, out); continue
            open(out, 'w', encoding='utf8').write(transform_file(txt))
        count += 1
print('core files written', count)

# 3. composer: TawasulOS namespace + Tos\\ alias loader
cj = json.load(open(DST + '/composer.json'))
cj['name'] = 'afhaam/tawasul-os'
cj['description'] = 'Tawasul School OS'
cj['autoload']['psr-4'] = {'TawasulOS\\': ['src/', 'src/TawasulOS']}
files = cj['autoload'].setdefault('files', [])
if 'src/tos_aliases.php' not in files: files.append('src/tos_aliases.php')
json.dump(cj, open(DST + '/composer.json', 'w'), indent=4)

# 4. restore original GPL notices in module headers (GPL-3.0 requirement)
orig = ("Gibbon: the flexible, open school platform\n"
        "Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)\n"
        "Copyright © 2010, Gibbon Foundation\n"
        "Gibbon™, Gibbon Education Ltd. (Hong Kong)\n")
bad = orig.replace('Gibbon', 'TawasulOS').replace('gibbonedu', 'tawasuledu')
fixed = 0
for root, _, fs in os.walk(DST + '/modules'):
    for f in fs:
        if f.endswith('.php'):
            p = os.path.join(root, f)
            t = open(p, encoding='utf8', errors='surrogateescape').read()
            if bad in t:
                open(p, 'w', encoding='utf8', errors='surrogateescape').write(t.replace(bad, orig, 1)); fixed += 1
print('module notices restored', fixed)
