/*
 * Builds src/Resource/actionMap.php — the missing link between REST resources
 * and TawasulOS's own permission matrix.
 *
 * Method (no database, no execution — pure static analysis):
 *   1. Read every tos_action row from core/gibbon.sql (name, module, URLList).
 *   2. Expand each action into the PHP files it owns (URLList + entryURL).
 *   3. Read every PHP file under core/modules/<Module>/ and record which core
 *      tables it names.
 *   4. Score action <-> table pairs by how often the action's own files touch
 *      the table, then pick a read action and a write action per table.
 *
 * Run from the repo root:
 *   node "gibbon-api-addon/TawasulCore/tools/generate-action-map.mjs"
 */
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../../..');
const addon = path.resolve(import.meta.dirname, '..');
const coreDir = path.join(root, 'gibbon-source/core');
const sql = fs.readFileSync(path.join(coreDir, 'gibbon.sql'), 'utf8');

const tables = [...sql.matchAll(/CREATE TABLE `?(\w+)`?/gi)].map((m) => m[1]);

function rowsFor(table) {
  const rows = [];
  for (const insert of sql.matchAll(new RegExp('INSERT INTO `' + table + '`[\\s\\S]*?\\);\\n', 'gi'))) {
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
          if (ch === '\\') cur += body[++i] ?? '';
          else if (ch === "'" && body[i + 1] === "'") { cur += "'"; i++; }
          else if (ch === "'") quoted = false;
          else cur += ch;
        } else if (ch === "'") quoted = true;
        else if (ch === ',') { cells.push(cur.trim()); cur = ''; }
        else cur += ch;
      }
      cells.push(cur.trim());
      rows.push(cells);
    }
  }
  return rows;
}

// ---- modules and actions --------------------------------------------------
const modules = {};
for (const cells of rowsFor('tos_module')) {
  modules[String(parseInt(cells[0], 10))] = cells[1];
}

const actions = [];
for (const cells of rowsFor('tos_action')) {
  const [, moduleID, name, , category, , , urlList, entryURL] = cells;
  const module = modules[String(parseInt(moduleID, 10))];
  if (!module) continue;
  const files = (urlList || '')
    .split(',')
    .map((f) => f.trim())
    .filter(Boolean);
  if (entryURL && !files.includes(entryURL)) files.push(entryURL);
  actions.push({ name, base: name.split('_')[0], module, category, files, entryURL });
}

// ---- which file mentions which table --------------------------------------
const tableRegex = new Map(tables.map((t) => [t, new RegExp('\\b' + t + '\\b', 'g')]));

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, out);
    else if (entry.name.endsWith('.php')) out.push(full);
  }
  return out;
}

// file (module-relative path) -> Map(table -> hits)
const fileTables = new Map();
const modulesDir = path.join(coreDir, 'modules');
for (const moduleName of fs.readdirSync(modulesDir)) {
  const dir = path.join(modulesDir, moduleName);
  if (!fs.statSync(dir).isDirectory()) continue;
  for (const file of walk(dir)) {
    const source = fs.readFileSync(file, 'utf8');
    const hits = new Map();
    for (const [table, re] of tableRegex) {
      if (!source.includes(table)) continue;
      const count = (source.match(re) || []).length;
      if (count) hits.set(table, count);
    }
    if (hits.size) fileTables.set(moduleName + '/' + path.relative(dir, file), hits);
  }
}

// Gateways carry the real table ownership; map gateway class -> tables so an
// action's page files inherit the tables of the gateways they use.
const gatewayTables = new Map();
for (const file of walk(path.join(coreDir, 'src'))) {
  if (!file.endsWith('Gateway.php')) continue;
  const source = fs.readFileSync(file, 'utf8');
  const hits = new Map();
  for (const [table, re] of tableRegex) {
    if (!source.includes(table)) continue;
    const count = (source.match(re) || []).length;
    if (count) hits.set(table, count);
  }
  if (hits.size) gatewayTables.set(path.basename(file, '.php'), hits);
}

const WRITE_HINT = /(_add|_edit|_delete|_manage|Process|manage_)/i;

function tablesForAction(action) {
  const read = new Map();
  const write = new Map();
  for (const rel of action.files) {
    const key = action.module + '/' + rel;
    const direct = fileTables.get(key);
    const source = direct
      ? null
      : null;
    const hits = new Map(direct || []);

    // pull in gateway tables referenced by the page itself
    if (direct) {
      const full = path.join(modulesDir, key);
      if (fs.existsSync(full)) {
        const text = fs.readFileSync(full, 'utf8');
        for (const [gateway, gatewayHits] of gatewayTables) {
          if (!text.includes(gateway)) continue;
          for (const [table, count] of gatewayHits) {
            hits.set(table, (hits.get(table) || 0) + count);
          }
        }
      }
    }
    void source;

    const isWrite = WRITE_HINT.test(rel);
    const isEntry = rel === action.entryURL;
    for (const [table, count] of hits) {
      const weight = count * (isEntry ? 2 : 1);
      read.set(table, (read.get(table) || 0) + weight);
      if (isWrite) write.set(table, (write.get(table) || 0) + weight);
    }
  }
  return { read, write };
}

// table -> candidate actions
const readScores = new Map();
const writeScores = new Map();
for (const action of actions) {
  const { read, write } = tablesForAction(action);
  for (const [table, score] of read) {
    if (!readScores.has(table)) readScores.set(table, []);
    readScores.get(table).push({ action, score });
  }
  for (const [table, score] of write) {
    if (!writeScores.has(table)) writeScores.set(table, []);
    writeScores.get(table).push({ action, score });
  }
}

// A table named in an action's own name/entry file is a much stronger signal.
function nameAffinity(action, table) {
  const stem = table.replace(/^gibbon/, '').toLowerCase();
  const hay = (action.name + ' ' + action.entryURL).toLowerCase().replace(/[^a-z]/g, '');
  return hay.includes(stem.replace(/[^a-z]/g, '')) ? 1 : 0;
}

function pick(candidates, table) {
  if (!candidates || !candidates.length) return null;
  const ranked = [...candidates].sort(
    (a, b) =>
      nameAffinity(b.action, table) - nameAffinity(a.action, table) ||
      b.score - a.score ||
      a.action.name.localeCompare(b.action.name)
  );
  const best = ranked[0];
  const total = ranked.reduce((sum, c) => sum + c.score, 0) || 1;
  return {
    module: best.action.module,
    action: best.action.base,
    category: best.action.category,
    score: best.score,
    share: Math.round((best.score / total) * 100),
    affinity: nameAffinity(best.action, table) === 1,
  };
}

/*
 * Hand-checked mappings for the tables the heuristic gets wrong or that carry
 * enough risk to deserve a human decision. Prefix match: an entry for
 * "tos_person" also covers tos_person_medical* unless that table has its
 * own entry. These always win over the derived scores.
 */
const OVERRIDES = {
  tos_person: ['User Admin', 'Manage Users', 'Manage Users'],
  tos_person_medical: ['Students', 'Manage Medical Forms', 'Manage Medical Forms'],
  tos_person_medical_condition: ['Students', 'Manage Medical Forms', 'Manage Medical Forms'],
  tos_person_medical_condition_update: ['Students', 'Manage Medical Forms', 'Manage Medical Forms'],
  tos_family: ['User Admin', 'Manage Families', 'Manage Families'],
  tos_family_adult: ['User Admin', 'Manage Families', 'Manage Families'],
  tos_family_child: ['User Admin', 'Manage Families', 'Manage Families'],
  tos_family_relationship: ['User Admin', 'Manage Families', 'Manage Families'],
  tos_role: ['User Admin', 'Manage Roles', 'Manage Roles'],
  tos_permission: ['User Admin', 'Manage Permissions', 'Manage Permissions'],
  tos_student_enrolment: ['User Admin', 'Manage Users', 'Manage Users'],
  tos_staff: ['Staff', 'Manage Staff', 'Manage Staff'],
  tos_staff_absence: ['Staff', 'View Absences', 'Manage Staff Absences'],
  tos_staff_absence_date: ['Staff', 'View Absences', 'Manage Staff Absences'],
  tos_staff_coverage: ['Staff', 'Manage Staff Coverage', 'Manage Staff Coverage'],
  tos_staff_coverage_date: ['Staff', 'Manage Staff Coverage', 'Manage Staff Coverage'],
  tos_staff_application_form: ['Staff', 'Manage Applications', 'Manage Applications'],
  tos_staff_job_opening: ['Staff', 'Job Openings', 'Job Openings'],
  tos_staff_duty: ['Staff', 'Duty Schedule', 'Duty Schedule'],
  tos_behaviour: ['Behaviour', 'View Behaviour Records', 'Manage Behaviour Records'],
  tos_behaviour_letter: ['Behaviour', 'View Behaviour Letters', 'View Behaviour Letters'],
  tos_attendance_log_person: ['Attendance', 'Attendance By Person', 'Attendance By Person'],
  tos_attendance_log_form_group: ['Attendance', 'Attendance By Form Group', 'Attendance By Form Group'],
  tos_attendance_log_course_class: ['Attendance', 'Attendance By Class', 'Attendance By Class'],
  tos_attendance_code: ['School Admin', 'Attendance Settings', 'Attendance Settings'],
  tos_markbook_column: ['Markbook', 'View Markbook', 'Edit Markbook'],
  tos_markbook_entry: ['Markbook', 'View Markbook', 'Edit Markbook'],
  tos_markbook_target: ['Markbook', 'View Markbook', 'Edit Markbook'],
  tos_markbook_weight: ['Markbook', 'Manage Weightings', 'Manage Weightings'],
  tos_messenger: ['Messenger', 'Manage Messages', 'New Message'],
  tos_messenger_receipt: ['Messenger', 'Manage Messages', 'New Message'],
  tos_messenger_target: ['Messenger', 'Manage Messages', 'New Message'],
  tos_group: ['Messenger', 'Manage Groups', 'Manage Groups'],
  tos_group_person: ['Messenger', 'Manage Groups', 'Manage Groups'],
  tos_planner_entry: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  tos_planner_entry_discuss: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  tos_planner_entry_homework: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  tos_planner_entry_outcome: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  tos_planner_entry_student_homework: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  tos_planner_entry_student_tracker: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  tos_unit: ['Planner', 'Unit Planner', 'Unit Planner'],
  tos_unit_block: ['Planner', 'Unit Planner', 'Unit Planner'],
  tos_unit_class: ['Planner', 'Unit Planner', 'Unit Planner'],
  tos_unit_class_block: ['Planner', 'Unit Planner', 'Unit Planner'],
  tos_outcome: ['Planner', 'Manage Outcomes', 'Manage Outcomes'],
  tos_individual_needs_person_descriptor: ['Individual Needs', 'Individual Needs Records', 'Individual Needs Records'],
  tos_individual_needs_investigation: ['Individual Needs', 'Manage Investigations', 'Manage Investigations'],
  tos_application_form: ['Students', 'Manage Applications', 'Manage Applications'],
  tos_course: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  tos_course_class: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  tos_course_class_person: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  tos_school_year: ['School Admin', 'Manage School Years', 'Manage School Years'],
  tos_school_year_term: ['School Admin', 'Manage Terms', 'Manage Terms'],
  tos_school_year_special_day: ['School Admin', 'Manage Special Days', 'Manage Special Days'],
  tos_year_group: ['School Admin', 'Manage Year Groups', 'Manage Year Groups'],
  tos_form_group: ['School Admin', 'Manage Form Groups', 'Manage Form Groups'],
  tos_house: ['School Admin', 'Manage Houses', 'Manage Houses'],
  tos_department: ['School Admin', 'Manage Departments', 'Manage Departments'],
  tos_department_staff: ['School Admin', 'Manage Departments', 'Manage Departments'],
  tos_space: ['School Admin', 'Manage Facilities', 'Manage Facilities'],
  tos_scale: ['School Admin', 'Manage Grade Scales', 'Manage Grade Scales'],
  tos_scale_grade: ['School Admin', 'Manage Grade Scales', 'Manage Grade Scales'],
  tos_finance_fee: ['Finance', 'Manage Fees', 'Manage Fees'],
  tos_finance_fee_category: ['Finance', 'Manage Fee Categories', 'Manage Fee Categories'],
  tos_finance_invoice: ['Finance', 'Manage Invoices', 'Manage Invoices'],
  tos_finance_invoicee: ['Finance', 'Manage Invoicees', 'Manage Invoicees'],
  tos_finance_budget: ['Finance', 'Manage Budgets', 'Manage Budgets'],
  tos_finance_expense: ['Finance', 'Manage Expenses', 'Manage Expenses'],
  tos_library_item: ['Library', 'Manage Catalog', 'Manage Catalog'],
  tos_library_type: ['Library', 'Manage Catalog', 'Manage Catalog'],
  tos_activity: ['Activities', 'Manage Activities', 'Manage Activities'],
  tos_activity_student: ['Activities', 'Manage Activities', 'Manage Activities'],
  tos_activity_attendance: ['Activities', 'Enter Activity Attendance', 'Enter Activity Attendance'],
  timetable: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_day: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_day_row_class: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_day_date: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_column: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_column_row: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_import: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_space_booking: ['Timetable', 'Manage Facility Bookings', 'Manage Facility Bookings'],
  tos_calendar_event: ['Calendar', 'Manage Events', 'Manage Events'],
  tos_crowd_assessment_comment: ['Crowd Assessment', 'Manage Crowd Assessment', 'Manage Crowd Assessment'],
  tos_rubric: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  tos_rubric_cell: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  tos_rubric_column: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  tos_rubric_row: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  tos_external_assessment: ['School Admin', 'Manage External Assessments', 'Manage External Assessments'],
  tos_action: ['System Admin', 'Manage Modules', 'Manage Modules'],
  tos_module: ['System Admin', 'Manage Modules', 'Manage Modules'],
  tos_hook: ['System Admin', 'Manage Modules', 'Manage Modules'],
  tos_theme: ['System Admin', 'Manage Themes', 'Manage Themes'],
  i18n: ['System Admin', 'Manage Languages', 'Manage Languages'],
  tos_country: ['System Admin', 'System Settings', 'System Settings'],
  tos_alarm: ['System Admin', 'Sound Alarm', 'Sound Alarm'],
  tos_alarm_confirm: ['System Admin', 'Sound Alarm', 'Sound Alarm'],
  tos_form_upload: ['System Admin', 'Form Builder', 'Form Builder'],
  tos_person_photo: ['System Admin', 'Upload Photos & Files', 'Upload Photos & Files'],
  tos_username_format: ['User Admin', 'User Settings', 'User Settings'],
  tos_personal_document_type: ['User Admin', 'Personal Document Settings', 'Personal Document Settings'],
  tos_person_reset: ['User Admin', 'Manage Users', 'Manage Users'],
  tos_alert: ['School Admin', 'Student Alert Settings', 'Student Alert Settings'],
  tos_medical_condition: ['School Admin', 'Manage Medical Conditions', 'Manage Medical Conditions'],
  tos_space_person: ['School Admin', 'Manage Facilities', 'Manage Facilities'],
  tos_department_resource: ['School Admin', 'Manage Departments', 'Manage Departments'],
  tos_activity_category: ['Activities', 'Manage Categories', 'Manage Categories'],
  tos_activity_slot: ['Activities', 'Manage Activities', 'Manage Activities'],
  tos_activity_staff: ['Activities', 'Manage Staffing', 'Manage Staffing'],
  tos_activity_type: ['School Admin', 'Activity Settings', 'Activity Settings'],
  tos_behaviour_follow_up: ['Behaviour', 'View Behaviour Records', 'Manage Behaviour Records'],
  tos_first_aid_follow_up: ['Students', 'First Aid Record', 'First Aid Record'],
  tos_student_note: ['Students', 'View Student Profile', 'View Student Profile'],
  tos_student_note_category: ['Students', 'View Student Profile', 'View Student Profile'],
  tos_person_medical_update: ['Data Updater', 'Medical Form Updates', 'Update Medical Data'],
  tos_staff_update: ['Data Updater', 'Staff Data Updates', 'Update Staff Data'],
  tos_individual_needs_archive: ['Individual Needs', 'Archive Records', 'Archive Records'],
  tos_individual_needs_assistant: ['Individual Needs', 'Individual Needs Records', 'Individual Needs Records'],
  tos_individual_needs_investigation_contribution: ['Individual Needs', 'Manage Investigations', 'Submit Contributions'],
  tos_planner_entry_guest: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  tos_unit_outcome: ['Planner', 'Unit Planner', 'Unit Planner'],
  tos_rubric_entry: ['Rubrics', 'View Rubrics', 'Manage Rubrics'],
  tos_report_archive: ['Reports', 'Manage Archives', 'Manage Archives'],
  tos_report_archive_entry: ['Reports', 'Manage Archives', 'Manage Archives'],
  tos_reporting_access: ['Reports', 'Manage Access', 'Manage Access'],
  tos_reporting_criteria_type: ['Reports', 'Manage Criteria', 'Manage Criteria'],
  tos_reporting_progress: ['Reports', 'Progress by Reporting Cycle', 'Write Reports'],
  tos_reporting_proof: ['Reports', 'Proof Reading Progress', 'Proof Read'],
  tos_reporting_value: ['Reports', 'View by Student', 'Write Reports'],
  tos_report_prototype_section: ['Reports', 'Template Builder', 'Template Builder'],
  tos_report_template: ['Reports', 'Template Builder', 'Template Builder'],
  tos_report_template_font: ['Reports', 'Template Builder', 'Template Builder'],
  tos_report_template_section: ['Reports', 'Template Builder', 'Template Builder'],
  tos_course_class_map: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  tos_timetable_day_row_class_exception: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  tos_timetable_space_change: ['Timetable', 'Manage Facility Changes', 'Manage Facility Changes'],
  tos_setting: ['System Admin', 'Third Party Settings', 'Third Party Settings'],
};

/*
 * Alternate screens that legitimately reach the same data. A role only has to
 * hold ONE of these to pass the check, mirroring TawasulOS itself: a teacher with
 * "Student History" can read attendance without holding "Attendance By Person".
 * Format: 'Module|Action'. Listed as read (GET) or write (everything else).
 */
const ALTERNATES = {
  tos_attendance_log_person: {
    read: ['Attendance|Student History', 'Attendance|View Daily Attendance', 'Attendance|Attendance Summary by Date', 'Attendance|Attendance Trends', 'Attendance|Students Not Present', 'Attendance|Students Not Onsite', 'Attendance|Students Not In Class', 'Attendance|Consecutive Absences', 'Attendance|Manage Attendance Logs', 'Attendance|Attendance By Form Group', 'Attendance|Attendance By Class'],
    write: ['Attendance|Ad Hoc Attendance', 'Attendance|Set Future Absence', 'Attendance|Student Self Registration', 'Attendance|Manage Attendance Logs', 'Attendance|Attendance By Form Group', 'Attendance|Attendance By Class'],
  },
  tos_attendance_log_form_group: {
    read: ['Attendance|Form Groups Not Registered', 'Attendance|View Daily Attendance', 'Attendance|Attendance Summary by Date', 'Attendance|Manage Attendance Logs'],
    write: ['Attendance|Ad Hoc Attendance', 'Attendance|Manage Attendance Logs'],
  },
  tos_attendance_log_course_class: {
    read: ['Attendance|Classes Not Registered', 'Attendance|Students Not In Class', 'Attendance|Attendance Summary by Date', 'Attendance|Manage Attendance Logs'],
    write: ['Attendance|Ad Hoc Attendance', 'Attendance|Manage Attendance Logs'],
  },
  tos_messenger: {
    read: ['Messenger|View Message Wall', 'Messenger|Manage Mailing Lists', 'Messenger|Manage Mailing List Recipients', 'Messenger|Canned Response'],
    write: ['Messenger|New Quick Wall Message', 'Messenger|Manage Mailing Lists'],
  },
  tos_messenger_receipt: { read: ['Messenger|View Message Wall'], write: ['Messenger|New Quick Wall Message'] },
  tos_messenger_target: { read: ['Messenger|Manage Mailing Lists', 'Messenger|View Message Wall'], write: ['Messenger|Manage Mailing Lists', 'Messenger|New Quick Wall Message'] },
  tos_planner_entry: {
    read: ['Planner|Concept Explorer', 'Planner|Scope & Sequence', 'Planner|Work Summary by Form Group', 'Planner|Outcomes By Course', 'Planner|View Resources'],
    write: ['Planner|Unit Planner'],
  },
  tos_unit: { read: ['Planner|Scope & Sequence', 'Planner|Concept Explorer', 'Planner|Outcomes By Course'], write: ['Planner|Lesson Planner'] },
  tos_outcome: { read: ['Planner|Outcomes By Course', 'Planner|Concept Explorer'], write: [] },
  tos_person: {
    read: ['Students|View Student Profile', 'Students|Students by Form Group', 'Students|Students by House', 'Students|Form Group Summary', 'Students|Age & Gender Summary', 'Students|Emergency Data Summary', 'Students|Family Address by Student', 'Students|Student ID Cards', 'Students|Student Transport', 'Students|Privacy Choices by Student', 'Students|Letters Home by Form Group', 'Students|My Student History', 'Students|Emergency SMS by Year Group', 'Students|Emergency SMS by Transport', 'Students|Personal Document Summary', 'Staff|Staff Directory', 'Form Groups|View Form Groups'],
    write: ['Data Updater|Update Personal Data'],
  },
  tos_student_enrolment: {
    read: ['Students|Students by Form Group', 'Students|Students by House', 'Students|Form Group Summary', 'Students|View Student Profile', 'Timetable Admin|Class Enrolment by Form Group'],
    write: ['Timetable Admin|Course Enrolment Rollover', 'User Admin|Rollover'],
  },
  tos_student_note: {
    read: ['Students|View Student Profile', 'Student Alerts|Manage Student Alerts', 'Student Alerts|Student Alerts by Class', 'Student Alerts|Student Alerts by Form Group'],
    write: ['Student Alerts|Manage Student Alerts'],
  },
  tos_alert: {
    read: ['Student Alerts|Manage Student Alerts', 'Student Alerts|Student Alerts by Class', 'Student Alerts|Student Alerts by Form Group'],
    write: ['Student Alerts|Manage Student Alerts'],
  },
  tos_staff: {
    read: ['Staff|Staff Directory', 'Staff|Weekly Absences', 'Staff|Staff Absence Summary', 'Staff|Staff Coverage Summary', 'Staff|Substitute Availability', 'Staff|Daily Coverage Planner'],
    write: ['Data Updater|Update Staff Data'],
  },
  tos_staff_absence: {
    read: ['Staff|Staff Absence Summary', 'Staff|Weekly Absences', 'Staff|View Absences', 'Staff|Approve Staff Absences'],
    write: ['Staff|New Absence', 'Staff|Approve Staff Absences'],
  },
  tos_staff_absence_date: {
    read: ['Staff|Staff Absence Summary', 'Staff|Weekly Absences', 'Staff|View Absences'],
    write: ['Staff|New Absence', 'Staff|Approve Staff Absences'],
  },
  tos_staff_coverage: {
    read: ['Staff|Staff Coverage Summary', 'Staff|My Coverage', 'Staff|Open Requests', 'Staff|Daily Coverage Planner', 'Staff|Manage Substitutes', 'Staff|Substitute Availability'],
    write: ['Staff|Request Coverage', 'Staff|Manage Substitutes'],
  },
  tos_staff_coverage_date: {
    read: ['Staff|Staff Coverage Summary', 'Staff|My Coverage', 'Staff|Open Requests', 'Staff|Daily Coverage Planner'],
    write: ['Staff|Request Coverage', 'Staff|Manage Substitutes'],
  },
  tos_markbook_entry: { read: ['Markbook|Edit Markbook', 'Markbook|Manage Weightings'], write: ['Markbook|Manage Weightings'] },
  tos_markbook_column: { read: ['Markbook|Edit Markbook', 'Markbook|Manage Weightings'], write: ['Markbook|Manage Weightings'] },
  tos_markbook_target: { read: ['Markbook|Edit Markbook'], write: ['Markbook|Edit Markbook'] },
  tos_reporting_value: {
    read: ['Reports|View by Report', 'Reports|View by Student', 'Reports|View Draft Reports', 'Reports|View Past Reports', 'Reports|View Reports', 'Reports|My Reporting', 'Reports|Proof Read', 'Reports|Student Name Conflicts'],
    write: ['Reports|Write Reports', 'Reports|Generate Reports', 'Reports|Upload Reports', 'Reports|Proof Read'],
  },
  tos_reporting_progress: {
    read: ['Reports|Progress by Person', 'Reports|Progress by Department', 'Reports|Proof Reading Progress', 'Reports|My Reporting'],
    write: ['Reports|Write Reports', 'Reports|Generate Reports'],
  },
  tos_reporting_cycle: {
    read: ['Reports|Manage Reporting Cycles', 'Reports|My Reporting', 'Reports|View by Report', 'Reports|View Past Reports'],
    write: ['Reports|Manage Reporting Cycles', 'Reports|Manage Reports'],
  },
  tos_report_archive_entry: {
    read: ['Reports|View Past Reports', 'Reports|View by Student', 'Reports|View Reports'],
    write: ['Reports|Upload Reports', 'Reports|Generate Reports', 'Reports|Send Reports', 'Reports|Send Notifications'],
  },
  tos_report_template: { read: ['Reports|Manage Reports'], write: ['Reports|Manage Reports', 'Reports|Generate Reports'] },
  tos_reporting_proof: { read: ['Reports|Proof Reading Progress'], write: ['Reports|Proof Read'] },
};


const actionNames = new Set(actions.map((a) => a.module + '|' + a.base));

const map = {};

function attachAlternates(table) {
  const alt = ALTERNATES[table];
  if (!alt || !map[table]) return;
  const clean = (list) =>
    (list || [])
      .map((entry) => {
        const [module, action] = entry.split('|');
        if (!actionNames.has(module + '|' + action)) {
          console.warn(`WARNING: alternate for ${table} names a missing action: ${entry}`);
          return null;
        }
        return { module, action };
      })
      .filter(Boolean);
  const read = clean(alt.read);
  const write = clean(alt.write);
  if (read.length) map[table].readActions = read;
  if (write.length) map[table].writeActions = write;
}

for (const table of tables) {
  if (OVERRIDES[table]) {
    const [module, action, writeAction] = OVERRIDES[table];
    map[table] = { module, action, writeAction, confidence: 'curated' };
    attachAlternates(table);
    if (!actionNames.has(module + '|' + action) || !actionNames.has(module + '|' + writeAction)) {
      console.warn(`WARNING: override for ${table} names an action that is not in gibbon.sql`);
    }
    continue;
  }

  const read = pick(readScores.get(table), table);
  const write = pick(writeScores.get(table), table) || read;
  if (!read) continue;
  // Only keep a mapping we can defend: either the action names the table, or
  // its pages dominate the references to it.
  if (!read.affinity && read.share < 25) continue;
  map[table] = {
    module: read.module,
    action: read.action,
    writeAction: write && write.module === read.module ? write.action : read.action,
    category: read.category,
    confidence: read.affinity ? 'high' : read.share >= 50 ? 'medium' : 'low',
  };
  attachAlternates(table);
}


// ---- emit PHP -------------------------------------------------------------
const esc = (s) => String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
const lines = [
  '<?php',
  '/*',
  'Table => TawasulOS module/action mapping used for role-permission enforcement.',
  '',
  'GENERATED FILE — do not edit by hand.',
  'Run: node "tools/generate-action-map.mjs" from the module folder.',
  'Derived statically from core/gibbon.sql (tos_action.URLList) and the PHP',
  'pages of each core module — see tools/generate-action-map.mjs for the method.',
  '*/',
  '',
  'return [',
];
for (const [table, entry] of Object.entries(map).sort(([a], [b]) => a.localeCompare(b))) {
  const altList = (list) =>
    '[' + list.map((a) => `['module' => '${esc(a.module)}', 'action' => '${esc(a.action)}']`).join(', ') + ']';
  const extra =
    (entry.readActions ? `, 'readActions' => ${altList(entry.readActions)}` : '') +
    (entry.writeActions ? `, 'writeActions' => ${altList(entry.writeActions)}` : '');
  lines.push(
    `    '${table}' => ['module' => '${esc(entry.module)}', 'action' => '${esc(entry.action)}', 'writeAction' => '${esc(entry.writeAction)}', 'confidence' => '${entry.confidence}'${extra}],`
  );
}
lines.push('];', '');
fs.writeFileSync(path.join(addon, 'src/Resource/actionMap.php'), lines.join('\n'));

// ---- report ---------------------------------------------------------------
const byConfidence = { curated: 0, high: 0, medium: 0, low: 0 };
for (const e of Object.values(map)) byConfidence[e.confidence]++;

const unmapped = tables.filter((t) => !map[t]);

const doc = [
  '# Action map — REST resources to TawasulOS permissions',
  '',
  `Generated ${new Date().toISOString()} by \`tools/generate-action-map.mjs\`.`,
  '',
  `- Core tables: **${tables.length}**`,
  `- Tables mapped to a TawasulOS action: **${Object.keys(map).length}** (${Math.round((Object.keys(map).length / tables.length) * 100)}%)`,
  `- Confidence: ${byConfidence.curated} curated, ${byConfidence.high} high, ${byConfidence.medium} medium, ${byConfidence.low} low`,
  `- Core actions in gibbon.sql: **${actions.length}**`,
  `- Distinct actions used by the map: **${new Set(Object.values(map).map((e) => e.module + ' / ' + e.action)).size}**`,
  '',
  '| Table | Module | Read action | Write action | Confidence |',
  '| --- | --- | --- | --- | --- |',
  ...Object.entries(map)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([t, e]) => `| \`${t}\` | ${e.module} | ${e.action} | ${e.writeAction} | ${e.confidence} |`),
  '',
  `## Unmapped tables (${unmapped.length})`,
  '',
  'No core screen owns these tables, so the API falls back to scope-only checks',
  'plus the linked-user requirement.',
  '',
  ...unmapped.map((t) => `- \`${t}\``),
  '',
];
fs.mkdirSync(path.join(root, 'docs'), { recursive: true });
fs.writeFileSync(path.join(root, 'docs/ACTION-MAP.md'), doc.join('\n'));

console.log(
  `actionMap.php: ${Object.keys(map).length}/${tables.length} tables mapped ` +
    `(${byConfidence.curated} curated, ${byConfidence.high} high, ${byConfidence.medium} medium, ${byConfidence.low} low)`
);
