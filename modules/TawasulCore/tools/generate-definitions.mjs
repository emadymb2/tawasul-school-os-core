// Generates the "deep coverage" resource definitions for the TawasulOS REST API.
//
// It reads the TawasulOS core schema (gibbon.sql) and every community module
// manifest/CHANGEDB, then writes declarative definition files that the
// Resource\Registry merges underneath the hand-curated definitions. Curated
// definitions always win, so this only fills in the tables nobody wrote by
// hand — which is what makes full-schema coverage maintainable.
//
// Usage:  node "tools/generate-definitions.mjs"
// Output: src/Resource/definitions/generated/core.php
//         src/Resource/definitions/generated/modules.php

import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';

const here = path.dirname(url.fileURLToPath(import.meta.url));
const addonRoot = path.resolve(here, '..');
const sourceRoot = path.resolve(addonRoot, '../../gibbon-source');
const coreSql = path.join(sourceRoot, 'core/gibbon.sql');
const modulesDir = path.join(sourceRoot, 'modules');
const outDir = path.join(addonRoot, 'src/Resource/definitions/generated');

/* ------------------------------------------------------------------ parsing */

const CREATE_TABLE = /CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?([A-Za-z0-9_]+)`?\s*\(([\s\S]*?)\)\s*(?:ENGINE|;)/gi;

// Splits a CREATE TABLE body into its column and key definitions.
//
// Core's dump puts one column per line, but community module manifests hold the
// whole statement on a single line, so splitting on newlines saw only the first
// column of every module table. Commas inside enum('a','b') and decimal(13,2)
// have to be ignored, which is why this is a scanner rather than a split.
function splitDefinitions(body) {
  const parts = [];
  let current = '';
  let depth = 0;
  let quote = null;

  for (const char of body) {
    if (quote) {
      current += char;
      if (char === quote) quote = null;
      continue;
    }
    if (char === "'" || char === '"') { quote = char; current += char; continue; }
    if (char === '(') depth++;
    if (char === ')') depth--;
    if (char === ',' && depth === 0) { parts.push(current); current = ''; continue; }
    current += char;
  }
  parts.push(current);

  return parts.map((p) => p.trim()).filter(Boolean);
}

function parseTables(sql) {
  const tables = [];
  let match;
  CREATE_TABLE.lastIndex = 0;
  while ((match = CREATE_TABLE.exec(sql)) !== null) {
    const name = match[1];
    const body = match[2];
    const columns = [];
    let primaryKey = null;

    for (const rawLine of splitDefinitions(body)) {
      const line = rawLine.trim().replace(/,$/, '');
      if (!line) continue;

      const pk = line.match(/^PRIMARY KEY\s*\(`?([A-Za-z0-9_]+)`?\)/i);
      if (pk) { primaryKey = pk[1]; continue; }
      if (/^(UNIQUE |FULLTEXT |)KEY\b/i.test(line) || /^CONSTRAINT\b/i.test(line) || /^INDEX\b/i.test(line)) continue;

      const col = line.match(/^`([A-Za-z0-9_]+)`\s+([a-zA-Z]+)(\([^)]*\))?(.*)$/);
      if (!col) continue;

      const [, colName, type, size, rest] = col;
      columns.push({
        name: colName,
        type: type.toLowerCase(),
        size: size || '',
        notNull: /NOT NULL/i.test(rest),
        autoIncrement: /AUTO_INCREMENT/i.test(rest),
        hasDefault: /DEFAULT/i.test(rest),
        currentTimestamp: /CURRENT_TIMESTAMP/i.test(rest),
        enumValues: type.toLowerCase() === 'enum' && size
          ? size.slice(1, -1).split(',').map((v) => v.trim().replace(/^'|'$/g, ''))
          : [],
      });
    }

    if (columns.length) tables.push({ name, columns, primaryKey });
  }

  // The core dump declares keys separately (ALTER TABLE ... ADD PRIMARY KEY),
  // so a table read from CREATE TABLE alone looks keyless. Without this the
  // whole core schema would be demoted to read-only.
  const ALTER_PK = /ALTER TABLE\s+`?([A-Za-z0-9_]+)`?[\s\S]{0,4000}?ADD PRIMARY KEY\s*\(`?([A-Za-z0-9_]+)`?\)/gi;
  const byName = new Map(tables.map((t) => [t.name, t]));
  let alter;
  while ((alter = ALTER_PK.exec(sql)) !== null) {
    const table = byName.get(alter[1]);
    if (table && !table.primaryKey) table.primaryKey = alter[2];
  }

  // AUTO_INCREMENT is also applied by a later ALTER in the dump.
  const ALTER_AI = /ALTER TABLE\s+`?([A-Za-z0-9_]+)`?\s+MODIFY\s+`([A-Za-z0-9_]+)`[^;]*AUTO_INCREMENT/gi;
  let ai;
  while ((ai = ALTER_AI.exec(sql)) !== null) {
    const table = byName.get(ai[1]);
    if (!table) continue;
    const column = table.columns.find((c) => c.name === ai[2]);
    if (column) column.autoIncrement = true;
  }

  return tables;
}

/* ------------------------------------------------------------- name helpers */

function kebab(value) {
  return value
    .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
    .replace(/([A-Z]+)([A-Z][a-z])/g, '$1-$2')
    .replace(/_/g, '-')
    .toLowerCase();
}

function pluralise(word) {
  if (/(s|x|z|ch|sh)$/.test(word)) return word + 'es';
  if (/[^aeiou]y$/.test(word)) return word.slice(0, -1) + 'ies';
  if (/s$/.test(word)) return word;
  return word + 's';
}

function singularise(word) {
  if (/ies$/.test(word)) return word.slice(0, -3) + 'y';
  if (/(ses|xes|zes|ches|shes)$/.test(word)) return word.slice(0, -2);
  if (/s$/.test(word)) return word.slice(0, -1);
  return word;
}

function titleise(value) {
  return kebab(value).split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function resourceNameFor(table, stripPrefix) {
  let base = table;
  if (stripPrefix && base.toLowerCase().startsWith(stripPrefix.toLowerCase())) {
    base = base.slice(stripPrefix.length);
  }
  if (!base) base = table;
  const parts = kebab(base).split('-').filter(Boolean);
  parts[parts.length - 1] = pluralise(parts[parts.length - 1]);
  return parts.join('-');
}

/* -------------------------------------------------------- column classifiers */

const SENSITIVE = /(password|passwordstrong|salt|secret|mfa|refreshtoken|apikey|privatekey|creditcard)/i;
const SEARCHABLE_NAMES = /^(name|nameshort|namedisplay|title|subject|description|comment|body|username|email|surname|firstname|preferredname|officialname|studentid|reference)$/i;
const TEXTY = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'];
const NUMERIC = ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'];
const DATEY = ['date', 'datetime', 'timestamp', 'time', 'year'];

function jsonType(col) {
  if (NUMERIC.includes(col.type)) return /decimal|float|double/.test(col.type) ? 'number' : 'integer';
  if (col.type === 'date') return 'date';
  if (col.type === 'datetime' || col.type === 'timestamp') return 'date-time';
  return 'string';
}

function isForeignKey(col) {
  return /ID$/.test(col.name) && NUMERIC.includes(col.type);
}

// Tables that record what happened rather than what is. Writing to them
// through a generic API corrupts an audit trail instead of changing school
// data, so they are published read-only and have to be curated by hand if a
// school genuinely needs to write one.
const HISTORY_TABLES = /(log|logs|history|audit|archive|snapshot|session|cache|backup|migration|reminder|delivery)/i;

function readOnlyReason(table) {
  if (HISTORY_TABLES.test(table.name)) {
    return 'History and log tables are published read-only so an audit trail cannot be rewritten through the API.';
  }
  if (!table.primaryKey) {
    return 'This table has no single-column primary key, so a record cannot be addressed safely for writes.';
  }
  return null;
}

// Longest accepted value per column, so a too-long name comes back as a
// per-field 422 rather than a truncated row or an opaque database error.
function maxLengths(columns) {
  const lengths = {};
  for (const col of columns) {
    if (!['varchar', 'char'].includes(col.type)) continue;
    const size = parseInt(col.size.replace(/[()]/g, ''), 10);
    if (Number.isFinite(size) && size > 0) lengths[col.name] = size;
  }
  return lengths;
}

/* ------------------------------------------------------------ php rendering */

function php(value, indent = 0) {
  const pad = ' '.repeat(indent);
  if (value === null) return 'null';
  if (typeof value === 'boolean') return value ? 'true' : 'false';
  if (typeof value === 'number') return String(value);
  if (typeof value === 'string') return "'" + value.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
  if (Array.isArray(value)) {
    if (!value.length) return '[]';
    const flat = value.every((v) => typeof v === 'string' && v.length < 40);
    if (flat && value.length <= 8) return '[' + value.map((v) => php(v)).join(', ') + ']';
    return '[\n' + value.map((v) => pad + '    ' + php(v, indent + 4)).join(',\n') + ',\n' + pad + ']';
  }
  const entries = Object.entries(value);
  if (!entries.length) return '[]';
  return '[\n' + entries
    .map(([k, v]) => pad + '    ' + php(k) + ' => ' + php(v, indent + 4))
    .join(',\n') + ',\n' + pad + ']';
}

/* -------------------------------------------------------- definition builder */

function buildDefinition(table, options) {
  const { group, scope, moduleName, actionName, primaryKeyIndex } = options;
  const pk = table.primaryKey || (table.columns[0] && table.columns[0].name);
  const columns = table.columns;
  const names = columns.map((c) => c.name);

  const filters = {};
  for (const col of columns) {
    if (Object.keys(filters).length >= 30) break;
    const qualified = table.name + '.' + col.name;
    if (col.name === pk) { filters[col.name] = qualified; continue; }
    if (isForeignKey(col) || col.type === 'enum' || DATEY.includes(col.type)) {
      filters[col.name] = qualified;
      continue;
    }
    if (/^(name|nameshort|status|type|category|active|scope|code|sequencenumber)$/i.test(col.name)) {
      filters[col.name] = qualified;
    }
  }

  const search = columns
    .filter((c) => TEXTY.includes(c.type) && SEARCHABLE_NAMES.test(c.name))
    .slice(0, 8)
    .map((c) => table.name + '.' + c.name);

  const sort = {};
  for (const col of columns) {
    if (Object.keys(sort).length >= 12) break;
    if (col.name === pk || SEARCHABLE_NAMES.test(col.name) || DATEY.includes(col.type)
      || /^(sequencenumber|priority|status|type|timestamp)/i.test(col.name)) {
      sort[col.name] = table.name + '.' + col.name;
    }
  }

  const writable = columns
    .filter((c) => c.name !== pk && !c.autoIncrement && !(c.currentTimestamp && !c.notNull))
    .map((c) => c.name);

  const required = columns
    .filter((c) => c.name !== pk && c.notNull && !c.hasDefault && !c.autoIncrement && !c.currentTimestamp)
    .map((c) => c.name);

  const sensitive = names.filter((n) => SENSITIVE.test(n));

  const types = {};
  for (const col of columns) types[col.name] = jsonType(col);

  const enums = {};
  for (const col of columns) if (col.enumValues.length) enums[col.name] = col.enumValues;

  const relations = {};
  for (const col of columns) {
    if (!isForeignKey(col) || col.name === pk) continue;
    const target = primaryKeyIndex[col.name];
    if (!target || target.resource === options.resourceName) continue;
    relations[target.relationName] = {
      resource: target.resource,
      type: 'belongsTo',
      localKey: col.name,
      foreignKey: col.name,
    };
  }

  const defaultSortColumn = ['name', 'surname', 'title', 'sequenceNumber', 'date', 'timestamp']
    .map((c) => names.find((n) => n.toLowerCase() === c.toLowerCase()))
    .find(Boolean) || pk;

  const lengths = maxLengths(columns);
  const readOnly = readOnlyReason(table);

  const definition = {
    title: titleise(options.resourceName.replace(/-/g, ' ')),
    group,
    scope,
    module: moduleName,
    action: actionName,
    table: table.name,
    primaryKey: pk,
    description: 'Generated from the ' + table.name + ' table.',
    select: 'SELECT ' + table.name + '.* FROM ' + table.name,
    idColumn: table.name + '.' + pk,
    filters,
    search,
    sort,
    defaultSort: table.name + '.' + defaultSortColumn,
    writable,
    required,
    methods: readOnly ? ['GET'] : ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
    sensitive,
    types,
    generated: true,
  };

  if (readOnly) definition.readOnlyReason = readOnly;
  if (Object.keys(lengths).length) definition.maxLength = lengths;
  if (Object.keys(enums).length) definition.enums = enums;
  if (Object.keys(relations).length) definition.relations = relations;
  if (names.includes('school_year_id')) definition.yearFilter = table.name + '.school_year_id';

  return definition;
}

/* ------------------------------------------------------------------ grouping */

const CORE_GROUPS = [
  [/^gibbon(Person|Family|Role|District|Staff|Substitute|Alumni|Username)/, 'People'],
  [/^gibbon(SchoolYear|YearGroup|FormGroup|House|Space|Department|DaysOfWeek|Scale)/, 'School'],
  [/^gibbon(Course|Unit|TT|Timetable|Markbook|InternalAssessment|ExternalAssessment|Rubric|Outcome|Planner|CrowdAssess|Report)/, 'Academics'],
  [/^gibbon(Attendance|Behaviour|Alert|IN|StudentNote|StudentSupport|Medical|FirstAid)/, 'Wellbeing'],
  [/^gibbon(Activity|Library|Finance|Payment|Messenger|Calendar|Group|Resource|Form|Admissions|Application)/, 'Operations'],
  [/^gibbon(Student|Enrol)/, 'People'],
  [/^gibbon/, 'System'],
];

function coreGroup(table) {
  for (const [pattern, group] of CORE_GROUPS) if (pattern.test(table)) return group;
  return 'System';
}

const CORE_ACTIONS = {
  People: { module: null, action: null },
};

/* ----------------------------------------------------------------- execution */

const curated = fs
  .readdirSync(path.join(addonRoot, 'src/Resource/definitions'))
  .filter((f) => f.endsWith('.php'))
  .map((f) => fs.readFileSync(path.join(addonRoot, 'src/Resource/definitions', f), 'utf8'))
  .join('\n');

const curatedTables = new Set(
  [...curated.matchAll(/'table'\s*=>\s*'([A-Za-z0-9_]+)'/g)].map((m) => m[1])
);
const curatedNames = new Set(
  [...curated.matchAll(/^'([a-z0-9-]+)'\s*=>\s*\[/gm)].map((m) => m[1])
);

// Pass 1: index every primary key so foreign keys can be resolved to resources.
const coreTables = parseTables(fs.readFileSync(coreSql, 'utf8'));

const moduleDirs = fs
  .readdirSync(modulesDir)
  .filter((d) => fs.statSync(path.join(modulesDir, d)).isDirectory());

const moduleSets = [];
for (const dir of moduleDirs) {
  const moduleRoot = path.join(modulesDir, dir);
  const inner = fs.readdirSync(moduleRoot).filter((d) => {
    try { return fs.statSync(path.join(moduleRoot, d)).isDirectory(); } catch { return false; }
  });
  const folder = inner.find((d) => fs.existsSync(path.join(moduleRoot, d, 'manifest.php')))
    || (fs.existsSync(path.join(moduleRoot, 'manifest.php')) ? '' : null);
  if (folder === null) continue;

  const base = path.join(moduleRoot, folder);
  const manifest = fs.readFileSync(path.join(base, 'manifest.php'), 'utf8');
  const changedbPath = path.join(base, 'CHANGEDB.php');
  const changedb = fs.existsSync(changedbPath) ? fs.readFileSync(changedbPath, 'utf8') : '';

  const nameMatch = manifest.match(/\$name\s*=\s*'([^']+)'/);
  const actionMatch = manifest.match(/'name'\s*=>\s*'([^']+)'/);
  const moduleName = nameMatch ? nameMatch[1] : titleise(dir.replace(/^module-/, ''));

  const tables = new Map();
  for (const table of [...parseTables(manifest), ...parseTables(changedb)]) {
    tables.set(table.name, table); // later definitions (CHANGEDB) win
  }

  if (!tables.size) continue;

  moduleSets.push({
    dir,
    moduleName,
    actionName: actionMatch ? actionMatch[1] : null,
    slug: kebab(dir.replace(/^module-/, '')),
    tables: [...tables.values()],
  });
}

// Primary key index: column name -> { resource, relationName }
const primaryKeyIndex = {};
const taken = new Set(curatedNames);

function claim(name) {
  let candidate = name;
  let suffix = 2;
  while (taken.has(candidate)) candidate = name + '-' + suffix++;
  taken.add(candidate);
  return candidate;
}

const corePlan = [];
for (const table of coreTables) {
  if (curatedTables.has(table.name)) continue;
  if (/^tos_migration$|^tos_session$|^tos_string$/.test(table.name)) continue;
  const resource = claim(resourceNameFor(table.name, 'gibbon'));
  corePlan.push({ table, resource });
  if (table.primaryKey) {
    primaryKeyIndex[table.primaryKey] = { resource, relationName: singularise(resource) };
  }
}

const modulePlan = [];
for (const set of moduleSets) {
  for (const table of set.tables) {
    if (curatedTables.has(table.name)) continue;
    const resource = claim(set.slug + '-' + resourceNameFor(table.name, set.slug.replace(/-/g, '')));
    modulePlan.push({ table, resource, set });
    if (table.primaryKey && !primaryKeyIndex[table.primaryKey]) {
      primaryKeyIndex[table.primaryKey] = { resource, relationName: singularise(resource) };
    }
  }
}

// Curated resources also take part in relation resolution.
for (const match of curated.matchAll(/^'([a-z0-9-]+)'\s*=>\s*\[[\s\S]*?'primaryKey'\s*=>\s*'([A-Za-z0-9_]+)'/gm)) {
  primaryKeyIndex[match[2]] = { resource: match[1], relationName: match[1].replace(/s$/, '') };
}

function render(header, entries) {
  const body = entries
    .map(([name, definition]) => "'" + name + "' => " + php(definition, 0) + ',\n')
    .join('\n');
  return '<?php\n/*\n' + header + '\n\nGENERATED FILE — do not edit by hand.\nRun: node "tools/generate-definitions.mjs" from the module folder.\nHand-written definitions in the parent folder always take precedence.\n*/\n\nreturn [\n\n' + body + '\n];\n';
}

const coreEntries = corePlan.map(({ table, resource }) => {
  const group = coreGroup(table.name);
  return [resource, buildDefinition(table, {
    group,
    scope: resource,
    moduleName: (CORE_ACTIONS[group] || {}).module || null,
    actionName: (CORE_ACTIONS[group] || {}).action || null,
    resourceName: resource,
    primaryKeyIndex,
  })];
});

const moduleEntries = modulePlan.map(({ table, resource, set }) => [
  resource,
  buildDefinition(table, {
    group: 'Modules: ' + set.moduleName,
    scope: resource,
    moduleName: set.moduleName,
    actionName: set.actionName,
    resourceName: resource,
    primaryKeyIndex,
  }),
]);

/* --------------------------------------------------- inverse (hasMany) pass */
//
// buildDefinition only knows the table in front of it, so it can describe the
// parent a row belongs to but never the children that point back at it. This
// pass walks every generated belongsTo relation and writes the mirror image
// onto the parent, which is what turns /courses/{id}/classes and
// ?include=children into working endpoints instead of hand-written exceptions.

const MAX_CHILD_RELATIONS = 40;

const byResource = new Map([...coreEntries, ...moduleEntries]);

for (const [childName, child] of byResource) {
  for (const relation of Object.values(child.relations || {})) {
    const parent = byResource.get(relation.resource);
    if (!parent || !parent.primaryKey) continue;
    if (relation.foreignKey !== parent.primaryKey) continue;

    parent.relations = parent.relations || {};
    if (Object.keys(parent.relations).length >= MAX_CHILD_RELATIONS) continue;

    // A parent may be reached from several columns of the same child table;
    // name the relation after the column when that happens.
    let name = childName;
    if (parent.relations[name]) name = childName + '-by-' + kebab(relation.localKey);
    if (parent.relations[name]) continue;

    parent.relations[name] = {
      resource: childName,
      type: 'hasMany',
      localKey: parent.primaryKey,
      foreignKey: relation.localKey,
    };
  }
}

let childRelationCount = 0;
for (const definition of byResource.values()) {
  for (const relation of Object.values(definition.relations || {})) {
    if (relation.type === 'hasMany') childRelationCount++;
  }
}

fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(
  path.join(outDir, 'core.php'),
  render('Full TawasulOS core schema coverage, derived from core/gibbon.sql.', coreEntries)
);
fs.writeFileSync(
  path.join(outDir, 'modules.php'),
  render('Community module coverage, derived from each module manifest and CHANGEDB.', moduleEntries)
);

const readOnlyCount = [...byResource.values()].filter((d) => d.methods.length === 1).length;

console.log('core resources:   ' + coreEntries.length);
console.log('module resources: ' + moduleEntries.length);
console.log('curated (kept):   ' + curatedNames.size);
console.log('read-only:        ' + readOnlyCount);
console.log('child relations:  ' + childRelationCount);
