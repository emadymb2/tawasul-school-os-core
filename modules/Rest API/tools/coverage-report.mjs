/*
 * Builds docs/COVERAGE-MATRIX.md: every Gibbon core model (table) and every
 * Gibbon action (page endpoint), matched against what the Rest API module
 * currently exposes.
 *
 * Run from the repo root:  node "gibbon-api-addon/Rest API/tools/coverage-report.mjs"
 */
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../../..');
const addon = path.resolve(import.meta.dirname, '..');
const sqlPath = path.join(root, 'gibbon-source/core/gibbon.sql');
const sql = fs.readFileSync(sqlPath, 'utf8');

// ---- core tables (models) -------------------------------------------------
const tables = [...sql.matchAll(/CREATE TABLE `?(\w+)`?/gi)].map((m) => m[1]).sort();

// ---- core actions (page endpoints) ---------------------------------------
// Rows look like: (id, moduleID, 'name', precedence, 'category', 'description',
//                  helpURL, 'URLList', 'entryURL', ...)
function rowsFor(table) {
  const rows = [];
  const inserts = sql.matchAll(new RegExp('INSERT INTO `' + table + '`[\\s\\S]*?\\);\\n', 'gi'));
  for (const insert of inserts) {
    for (const line of insert[0].split('\n')) {
      const trimmed = line.trim();
      if (!trimmed.startsWith('(')) continue;
      const body = trimmed.replace(/^\(/, '').replace(/\),?;?$/, '');
      const cells = [];
      let cur = '';
      let quoted = false;
      for (let i = 0; i < body.length; i++) {
        const ch = body[i];
        if (quoted) {
          if (ch === '\\') {
            cur += body[++i] ?? '';
          } else if (ch === "'" && body[i + 1] === "'") {
            cur += "'";
            i++;
          } else if (ch === "'") {
            quoted = false;
          } else {
            cur += ch;
          }
        } else if (ch === "'") {
          quoted = true;
        } else if (ch === ',') {
          cells.push(cur.trim());
          cur = '';
        } else {
          cur += ch;
        }
      }
      cells.push(cur.trim());
      rows.push(cells);
    }
  }
  return rows;
}

const moduleNames = new Map();
for (const cells of rowsFor('gibbonModule')) {
  moduleNames.set(String(Number(cells[0])), cells[1]);
}

const actions = rowsFor('gibbonAction').map((cells) => ({
  module: moduleNames.get(String(Number(cells[1]))) ?? `module#${cells[1]}`,
  name: cells[2],
  category: cells[4],
  urls: (cells[7] ?? '')
    .split(',')
    .map((u) => u.trim())
    .filter(Boolean),
}));


// ---- what the add-on exposes ---------------------------------------------
const defDir = path.join(addon, 'src/Resource/definitions');
const defFiles = [
  ...fs.readdirSync(defDir).filter((f) => f.endsWith('.php')).map((f) => path.join(defDir, f)),
  ...fs
    .readdirSync(path.join(defDir, 'generated'))
    .filter((f) => f.endsWith('.php'))
    .map((f) => path.join(defDir, 'generated', f)),
];

const resources = [];
for (const file of defFiles) {
  const text = fs.readFileSync(file, 'utf8');
  const blocks = text.split(/\n'([a-z0-9-]+)' => \[/);
  for (let i = 1; i < blocks.length; i += 2) {
    const name = blocks[i];
    const body = blocks[i + 1];
    const grab = (key) => (body.match(new RegExp(`'${key}' => '([^']*)'`)) || [])[1] ?? null;
    const methods = (body.match(/'methods' => \[([^\]]*)\]/) || [])[1] ?? "'GET'";
    resources.push({
      name,
      file: path.relative(addon, file),
      generated: file.includes('/generated/'),
      table: grab('table'),
      action: grab('action'),
      module: grab('module'),
      group: grab('group'),
      methods: [...methods.matchAll(/'(\w+)'/g)].map((m) => m[1]),
    });
  }
}

// hand-written definitions win over generated ones of the same name
const byName = new Map();
for (const r of resources.filter((r) => r.generated)) byName.set(r.name, r);
for (const r of resources.filter((r) => !r.generated)) byName.set(r.name, r);
const live = [...byName.values()].sort((a, b) => a.name.localeCompare(b.name));

const coveredTables = new Map();
for (const r of live) if (r.table) coveredTables.set(r.table, r);

const coveredActions = new Set(live.map((r) => r.action).filter(Boolean));

// Generated resources inherit their Gibbon action from actionMap.php, applied
// by Registry::applyActionMap(). Count those too.
const actionMapText = fs.existsSync(path.join(addon, 'src/Resource/actionMap.php'))
  ? fs.readFileSync(path.join(addon, 'src/Resource/actionMap.php'), 'utf8')
  : '';
const actionMap = new Map();
for (const line of actionMapText.split('\n')) {
  const head = line.match(/^\s*'(\w+)' => \['module' => '([^']*)', 'action' => '([^']*)', 'writeAction' => '([^']*)'/);
  if (!head) continue;
  // every action named on the line: primary, write, and the alternate screens
  const all = [...line.matchAll(/'action' => '([^']*)'/g)].map((m) => m[1]);
  actionMap.set(head[1], { module: head[2], action: head[3], writeAction: head[4], all });
}
for (const r of live) {
  const entry = r.table ? actionMap.get(r.table) : null;
  if (!entry) continue;
  if (!r.action) r.action = entry.action;
  coveredActions.add(entry.action);
  coveredActions.add(entry.writeAction);
  for (const a of entry.all) coveredActions.add(a);
}


// composite / non-CRUD routes served by controllers rather than the registry
const routerText = fs.readFileSync(path.join(addon, 'src/Http/Router.php'), 'utf8');
const staticRoutes = [...routerText.matchAll(/'((?:v2\/)?[a-z][a-z0-9\-\/\.{}]*)'/gi)]
  .map((m) => m[1])
  .filter((r) => r.includes('/') || r.includes('.'));

// ---- report ---------------------------------------------------------------
const missingTables = tables.filter((t) => !coveredTables.has(t));
const coreCovered = tables.length - missingTables.length;
const moduleTablesCovered = coveredTables.size - coreCovered;
const writable = live.filter((r) => r.methods.some((m) => m !== 'GET'));
const readOnly = live.filter((r) => !r.methods.some((m) => m !== 'GET'));
const actionNames = new Set(actions.map((a) => a.name));
const actionBases = new Set(actions.map((a) => a.name.split('_')[0]));
const mappedActions = actions.filter((a) => coveredActions.has(a.name) || coveredActions.has(a.name.split('_')[0]));

const isMapped = (a) => coveredActions.has(a.name) || coveredActions.has(a.name.split('_')[0]);

const pct = (n, d) => `${((n / d) * 100).toFixed(1)}%`;
const out = [];
out.push('# Gibbon Core ↔ Rest API coverage matrix');
out.push('');
out.push(`Generated by \`tools/coverage-report.mjs\` on ${new Date().toISOString().slice(0, 10)}.`);
out.push('');
out.push('## Summary');
out.push('');
out.push('| Metric | Count |');
out.push('| --- | --- |');
out.push(`| Core tables (models) in \`gibbon.sql\` | ${tables.length} |`);
out.push(`| Core tables exposed as a REST resource | ${coreCovered} (${pct(coreCovered, tables.length)}) |`);
out.push(`| Core tables with no resource | ${missingTables.length} |`);
out.push(`| Module / add-on tables also exposed | ${moduleTablesCovered} |`);
out.push(`| Live resources in the registry | ${live.length} |`);
out.push(`| — hand-written (curated joins/filters) | ${live.filter((r) => !r.generated).length} |`);
out.push(`| — generated from the schema | ${live.filter((r) => r.generated).length} |`);
out.push(`| Resources accepting writes | ${writable.length} |`);
out.push(`| Read-only resources | ${readOnly.length} |`);
out.push(`| Core actions (UI endpoints) in \`gibbonAction\` | ${actions.length} |`);
out.push(`| Core actions mapped to a resource for permission checks | ${mappedActions.length} (${pct(mappedActions.length, actions.length)}) |`);

out.push('');

out.push('## Non-CRUD routes served directly');
out.push('');
out.push(staticRoutes.map((r) => `\`${r}\``).join(', ') || '_none_');
out.push('');

out.push('## Models — core tables and their endpoint');
out.push('');
out.push('| Core table | Resource | Kind | Methods |');
out.push('| --- | --- | --- | --- |');
for (const t of tables) {
  const r = coveredTables.get(t);
  out.push(
    `| \`${t}\` | ${r ? `\`/${r.name}\`` : '**— none —**'} | ${r ? (r.generated ? 'generated' : 'curated') : '—'} | ${r ? r.methods.join(' ') : '—'} |`,
  );
}
out.push('');

out.push('## Core tables with no endpoint (build these next)');
out.push('');
out.push(missingTables.length ? missingTables.map((t) => `- \`${t}\``).join('\n') : '_None — every core table is reachable._');
out.push('');

out.push('## Actions — core UI endpoints by module');
out.push('');
const byModule = new Map();
for (const a of actions) {
  if (!byModule.has(a.module)) byModule.set(a.module, []);
  byModule.get(a.module).push(a);
}
for (const [mod, list] of [...byModule].sort()) {
  const hit = list.filter(isMapped).length;
  out.push(`### ${mod} — ${hit}/${list.length} actions mapped to a resource`);
  out.push('');
  out.push('| Action | Mapped | Pages |');
  out.push('| --- | --- | --- |');
  for (const a of list.sort((x, y) => x.name.localeCompare(y.name))) {
    out.push(`| ${a.name} | ${isMapped(a) ? 'yes' : 'no'} | ${a.urls.length} |`);
  }
  out.push('');
}

out.push('## Resources not backed by a core table');
out.push('');
const extra = live.filter((r) => r.table && !tables.includes(r.table));
out.push(
  extra.length
    ? extra.map((r) => `- \`/${r.name}\` → \`${r.table}\`${r.module ? ` (module: ${r.module})` : ''}`).join('\n')
    : '_none_',
);
out.push('');

const docs = path.join(root, 'docs/COVERAGE-MATRIX.md');
fs.writeFileSync(docs, out.join('\n'));

// machine-readable copy the dashboard page can read without re-parsing PHP
fs.writeFileSync(
  path.join(addon, 'src/Resource/coverage.json'),
  JSON.stringify(
    {
      generated: new Date().toISOString(),
      coreTables: tables.length,
      coveredTables: coreCovered,
      moduleTablesCovered,
      missingTables,
      resources: live.length,
      curated: live.filter((r) => !r.generated).length,
      generatedResources: live.filter((r) => r.generated).length,
      writable: writable.length,
      actions: actions.length,
      mappedActions: mappedActions.length,

    },
    null,
    2,
  ),
);

console.log(`tables=${tables.length} covered=${coveredTables.size} missing=${missingTables.length}`);
console.log(`resources=${live.length} actions=${actions.length} mappedActions=${coveredActions.size}`);
