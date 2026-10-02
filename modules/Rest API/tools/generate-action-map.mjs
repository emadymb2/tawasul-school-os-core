/*
 * Builds src/Resource/actionMap.php — the missing link between REST resources
 * and the platform's own permission matrix.
 *
 * Method (no database, no execution — pure static analysis):
 *   1. Read every tawasulAction row from the core schema (name, module, URLList).
 *   2. Expand each action into the PHP files it owns (URLList + entryURL).
 *   3. Read every PHP file under <modules>/<Module>/ and record which core
 *      tables it names.
 *   4. Score action <-> table pairs by how often the action's own files touch
 *      the table, then pick a read action and a write action per table.
 *
 * The paths default to an upstream Gibbon source checkout, where this tool was
 * written. On Tawasul OS they can be pointed at this repository instead:
 *
 *   TAWASUL_CORE_DIR=/path/to/root node "tools/generate-action-map.mjs"
 *
 * Run from the module directory:
 *   node "tools/generate-action-map.mjs"
 */
import fs from 'node:fs';
import path from 'node:path';

const addon = path.resolve(import.meta.dirname, '..');
const root = path.resolve(import.meta.dirname, '../../..');
const coreDir = process.env.TAWASUL_CORE_DIR ?? path.join(root, 'gibbon-source/core');
const sql = fs.readFileSync(path.join(coreDir, 'tawasul.sql'), 'utf8');

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
for (const cells of rowsFor('tawasulModule')) {
  modules[String(parseInt(cells[0], 10))] = cells[1];
}

const actions = [];
for (const cells of rowsFor('tawasulAction')) {
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
 * "gibbonPerson" also covers gibbonPersonMedical* unless that table has its
 * own entry. These always win over the derived scores.
 */
const OVERRIDES = {
  gibbonPerson: ['User Admin', 'Manage Users', 'Manage Users'],
  gibbonPersonMedical: ['Students', 'Manage Medical Forms', 'Manage Medical Forms'],
  gibbonPersonMedicalCondition: ['Students', 'Manage Medical Forms', 'Manage Medical Forms'],
  gibbonPersonMedicalConditionUpdate: ['Students', 'Manage Medical Forms', 'Manage Medical Forms'],
  gibbonFamily: ['User Admin', 'Manage Families', 'Manage Families'],
  gibbonFamilyAdult: ['User Admin', 'Manage Families', 'Manage Families'],
  gibbonFamilyChild: ['User Admin', 'Manage Families', 'Manage Families'],
  gibbonFamilyRelationship: ['User Admin', 'Manage Families', 'Manage Families'],
  gibbonRole: ['User Admin', 'Manage Roles', 'Manage Roles'],
  gibbonPermission: ['User Admin', 'Manage Permissions', 'Manage Permissions'],
  gibbonStudentEnrolment: ['User Admin', 'Manage Users', 'Manage Users'],
  gibbonStaff: ['Staff', 'Manage Staff', 'Manage Staff'],
  gibbonStaffAbsence: ['Staff', 'View Absences', 'Manage Staff Absences'],
  gibbonStaffAbsenceDate: ['Staff', 'View Absences', 'Manage Staff Absences'],
  gibbonStaffCoverage: ['Staff', 'Manage Staff Coverage', 'Manage Staff Coverage'],
  gibbonStaffCoverageDate: ['Staff', 'Manage Staff Coverage', 'Manage Staff Coverage'],
  gibbonStaffApplicationForm: ['Staff', 'Manage Applications', 'Manage Applications'],
  gibbonStaffJobOpening: ['Staff', 'Job Openings', 'Job Openings'],
  gibbonStaffDuty: ['Staff', 'Duty Schedule', 'Duty Schedule'],
  gibbonBehaviour: ['Behaviour', 'View Behaviour Records', 'Manage Behaviour Records'],
  gibbonBehaviourLetter: ['Behaviour', 'View Behaviour Letters', 'View Behaviour Letters'],
  gibbonAttendanceLogPerson: ['Attendance', 'Attendance By Person', 'Attendance By Person'],
  gibbonAttendanceLogFormGroup: ['Attendance', 'Attendance By Form Group', 'Attendance By Form Group'],
  gibbonAttendanceLogCourseClass: ['Attendance', 'Attendance By Class', 'Attendance By Class'],
  gibbonAttendanceCode: ['School Admin', 'Attendance Settings', 'Attendance Settings'],
  gibbonMarkbookColumn: ['Markbook', 'View Markbook', 'Edit Markbook'],
  gibbonMarkbookEntry: ['Markbook', 'View Markbook', 'Edit Markbook'],
  gibbonMarkbookTarget: ['Markbook', 'View Markbook', 'Edit Markbook'],
  gibbonMarkbookWeight: ['Markbook', 'Manage Weightings', 'Manage Weightings'],
  gibbonMessenger: ['Messenger', 'Manage Messages', 'New Message'],
  gibbonMessengerReceipt: ['Messenger', 'Manage Messages', 'New Message'],
  gibbonMessengerTarget: ['Messenger', 'Manage Messages', 'New Message'],
  gibbonGroup: ['Messenger', 'Manage Groups', 'Manage Groups'],
  gibbonGroupPerson: ['Messenger', 'Manage Groups', 'Manage Groups'],
  gibbonPlannerEntry: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  gibbonPlannerEntryDiscuss: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  gibbonPlannerEntryHomework: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  gibbonPlannerEntryOutcome: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  gibbonPlannerEntryStudentHomework: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  gibbonPlannerEntryStudentTracker: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  gibbonUnit: ['Planner', 'Unit Planner', 'Unit Planner'],
  gibbonUnitBlock: ['Planner', 'Unit Planner', 'Unit Planner'],
  gibbonUnitClass: ['Planner', 'Unit Planner', 'Unit Planner'],
  gibbonUnitClassBlock: ['Planner', 'Unit Planner', 'Unit Planner'],
  gibbonOutcome: ['Planner', 'Manage Outcomes', 'Manage Outcomes'],
  gibbonINPersonDescriptor: ['Individual Needs', 'Individual Needs Records', 'Individual Needs Records'],
  gibbonINInvestigation: ['Individual Needs', 'Manage Investigations', 'Manage Investigations'],
  gibbonApplicationForm: ['Students', 'Manage Applications', 'Manage Applications'],
  gibbonCourse: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  gibbonCourseClass: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  gibbonCourseClassPerson: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  gibbonSchoolYear: ['School Admin', 'Manage School Years', 'Manage School Years'],
  gibbonSchoolYearTerm: ['School Admin', 'Manage Terms', 'Manage Terms'],
  gibbonSchoolYearSpecialDay: ['School Admin', 'Manage Special Days', 'Manage Special Days'],
  gibbonYearGroup: ['School Admin', 'Manage Year Groups', 'Manage Year Groups'],
  gibbonFormGroup: ['School Admin', 'Manage Form Groups', 'Manage Form Groups'],
  gibbonHouse: ['School Admin', 'Manage Houses', 'Manage Houses'],
  gibbonDepartment: ['School Admin', 'Manage Departments', 'Manage Departments'],
  gibbonDepartmentStaff: ['School Admin', 'Manage Departments', 'Manage Departments'],
  gibbonSpace: ['School Admin', 'Manage Facilities', 'Manage Facilities'],
  gibbonScale: ['School Admin', 'Manage Grade Scales', 'Manage Grade Scales'],
  gibbonScaleGrade: ['School Admin', 'Manage Grade Scales', 'Manage Grade Scales'],
  gibbonFinanceFee: ['Finance', 'Manage Fees', 'Manage Fees'],
  gibbonFinanceFeeCategory: ['Finance', 'Manage Fee Categories', 'Manage Fee Categories'],
  gibbonFinanceInvoice: ['Finance', 'Manage Invoices', 'Manage Invoices'],
  gibbonFinanceInvoicee: ['Finance', 'Manage Invoicees', 'Manage Invoicees'],
  gibbonFinanceBudget: ['Finance', 'Manage Budgets', 'Manage Budgets'],
  gibbonFinanceExpense: ['Finance', 'Manage Expenses', 'Manage Expenses'],
  gibbonLibraryItem: ['Library', 'Manage Catalog', 'Manage Catalog'],
  gibbonLibraryType: ['Library', 'Manage Catalog', 'Manage Catalog'],
  gibbonActivity: ['Activities', 'Manage Activities', 'Manage Activities'],
  gibbonActivityStudent: ['Activities', 'Manage Activities', 'Manage Activities'],
  gibbonActivityAttendance: ['Activities', 'Enter Activity Attendance', 'Enter Activity Attendance'],
  gibbonTT: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTDay: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTDayRowClass: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTDayDate: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTColumn: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTColumnRow: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTImport: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTSpaceBooking: ['Timetable', 'Manage Facility Bookings', 'Manage Facility Bookings'],
  gibbonCalendarEvent: ['Calendar', 'Manage Events', 'Manage Events'],
  gibbonCrowdAssessmentComment: ['Crowd Assessment', 'Manage Crowd Assessment', 'Manage Crowd Assessment'],
  gibbonRubric: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  gibbonRubricCell: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  gibbonRubricColumn: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  gibbonRubricRow: ['Rubrics', 'Manage Rubrics', 'Manage Rubrics'],
  gibbonExternalAssessment: ['School Admin', 'Manage External Assessments', 'Manage External Assessments'],
  gibbonAction: ['System Admin', 'Manage Modules', 'Manage Modules'],
  gibbonModule: ['System Admin', 'Manage Modules', 'Manage Modules'],
  gibbonHook: ['System Admin', 'Manage Modules', 'Manage Modules'],
  gibbonTheme: ['System Admin', 'Manage Themes', 'Manage Themes'],
  gibboni18n: ['System Admin', 'Manage Languages', 'Manage Languages'],
  gibbonCountry: ['System Admin', 'System Settings', 'System Settings'],
  gibbonAlarm: ['System Admin', 'Sound Alarm', 'Sound Alarm'],
  gibbonAlarmConfirm: ['System Admin', 'Sound Alarm', 'Sound Alarm'],
  gibbonFormUpload: ['System Admin', 'Form Builder', 'Form Builder'],
  gibbonPersonPhoto: ['System Admin', 'Upload Photos & Files', 'Upload Photos & Files'],
  gibbonUsernameFormat: ['User Admin', 'User Settings', 'User Settings'],
  gibbonPersonalDocumentType: ['User Admin', 'Personal Document Settings', 'Personal Document Settings'],
  gibbonPersonReset: ['User Admin', 'Manage Users', 'Manage Users'],
  gibbonAlert: ['School Admin', 'Student Alert Settings', 'Student Alert Settings'],
  gibbonMedicalCondition: ['School Admin', 'Manage Medical Conditions', 'Manage Medical Conditions'],
  gibbonSpacePerson: ['School Admin', 'Manage Facilities', 'Manage Facilities'],
  gibbonDepartmentResource: ['School Admin', 'Manage Departments', 'Manage Departments'],
  gibbonActivityCategory: ['Activities', 'Manage Categories', 'Manage Categories'],
  gibbonActivitySlot: ['Activities', 'Manage Activities', 'Manage Activities'],
  gibbonActivityStaff: ['Activities', 'Manage Staffing', 'Manage Staffing'],
  gibbonActivityType: ['School Admin', 'Activity Settings', 'Activity Settings'],
  gibbonBehaviourFollowUp: ['Behaviour', 'View Behaviour Records', 'Manage Behaviour Records'],
  gibbonFirstAidFollowUp: ['Students', 'First Aid Record', 'First Aid Record'],
  gibbonStudentNote: ['Students', 'View Student Profile', 'View Student Profile'],
  gibbonStudentNoteCategory: ['Students', 'View Student Profile', 'View Student Profile'],
  gibbonPersonMedicalUpdate: ['Data Updater', 'Medical Form Updates', 'Update Medical Data'],
  gibbonStaffUpdate: ['Data Updater', 'Staff Data Updates', 'Update Staff Data'],
  gibbonINArchive: ['Individual Needs', 'Archive Records', 'Archive Records'],
  gibbonINAssistant: ['Individual Needs', 'Individual Needs Records', 'Individual Needs Records'],
  gibbonINInvestigationContribution: ['Individual Needs', 'Manage Investigations', 'Submit Contributions'],
  gibbonPlannerEntryGuest: ['Planner', 'Lesson Planner', 'Lesson Planner'],
  gibbonUnitOutcome: ['Planner', 'Unit Planner', 'Unit Planner'],
  gibbonRubricEntry: ['Rubrics', 'View Rubrics', 'Manage Rubrics'],
  gibbonReportArchive: ['Reports', 'Manage Archives', 'Manage Archives'],
  gibbonReportArchiveEntry: ['Reports', 'Manage Archives', 'Manage Archives'],
  gibbonReportingAccess: ['Reports', 'Manage Access', 'Manage Access'],
  gibbonReportingCriteriaType: ['Reports', 'Manage Criteria', 'Manage Criteria'],
  gibbonReportingProgress: ['Reports', 'Progress by Reporting Cycle', 'Write Reports'],
  gibbonReportingProof: ['Reports', 'Proof Reading Progress', 'Proof Read'],
  gibbonReportingValue: ['Reports', 'View by Student', 'Write Reports'],
  gibbonReportPrototypeSection: ['Reports', 'Template Builder', 'Template Builder'],
  gibbonReportTemplate: ['Reports', 'Template Builder', 'Template Builder'],
  gibbonReportTemplateFont: ['Reports', 'Template Builder', 'Template Builder'],
  gibbonReportTemplateSection: ['Reports', 'Template Builder', 'Template Builder'],
  gibbonCourseClassMap: ['Timetable Admin', 'Manage Courses & Classes', 'Manage Courses & Classes'],
  gibbonTTDayRowClassException: ['Timetable Admin', 'Manage Timetables', 'Manage Timetables'],
  gibbonTTSpaceChange: ['Timetable', 'Manage Facility Changes', 'Manage Facility Changes'],
  gibbonSetting: ['System Admin', 'Third Party Settings', 'Third Party Settings'],
};

/*
 * Alternate screens that legitimately reach the same data. A role only has to
 * hold ONE of these to pass the check, mirroring Gibbon itself: a teacher with
 * "Student History" can read attendance without holding "Attendance By Person".
 * Format: 'Module|Action'. Listed as read (GET) or write (everything else).
 */
const ALTERNATES = {
  gibbonAttendanceLogPerson: {
    read: ['Attendance|Student History', 'Attendance|View Daily Attendance', 'Attendance|Attendance Summary by Date', 'Attendance|Attendance Trends', 'Attendance|Students Not Present', 'Attendance|Students Not Onsite', 'Attendance|Students Not In Class', 'Attendance|Consecutive Absences', 'Attendance|Manage Attendance Logs', 'Attendance|Attendance By Form Group', 'Attendance|Attendance By Class'],
    write: ['Attendance|Ad Hoc Attendance', 'Attendance|Set Future Absence', 'Attendance|Student Self Registration', 'Attendance|Manage Attendance Logs', 'Attendance|Attendance By Form Group', 'Attendance|Attendance By Class'],
  },
  gibbonAttendanceLogFormGroup: {
    read: ['Attendance|Form Groups Not Registered', 'Attendance|View Daily Attendance', 'Attendance|Attendance Summary by Date', 'Attendance|Manage Attendance Logs'],
    write: ['Attendance|Ad Hoc Attendance', 'Attendance|Manage Attendance Logs'],
  },
  gibbonAttendanceLogCourseClass: {
    read: ['Attendance|Classes Not Registered', 'Attendance|Students Not In Class', 'Attendance|Attendance Summary by Date', 'Attendance|Manage Attendance Logs'],
    write: ['Attendance|Ad Hoc Attendance', 'Attendance|Manage Attendance Logs'],
  },
  gibbonMessenger: {
    read: ['Messenger|View Message Wall', 'Messenger|Manage Mailing Lists', 'Messenger|Manage Mailing List Recipients', 'Messenger|Canned Response'],
    write: ['Messenger|New Quick Wall Message', 'Messenger|Manage Mailing Lists'],
  },
  gibbonMessengerReceipt: { read: ['Messenger|View Message Wall'], write: ['Messenger|New Quick Wall Message'] },
  gibbonMessengerTarget: { read: ['Messenger|Manage Mailing Lists', 'Messenger|View Message Wall'], write: ['Messenger|Manage Mailing Lists', 'Messenger|New Quick Wall Message'] },
  gibbonPlannerEntry: {
    read: ['Planner|Concept Explorer', 'Planner|Scope & Sequence', 'Planner|Work Summary by Form Group', 'Planner|Outcomes By Course', 'Planner|View Resources'],
    write: ['Planner|Unit Planner'],
  },
  gibbonUnit: { read: ['Planner|Scope & Sequence', 'Planner|Concept Explorer', 'Planner|Outcomes By Course'], write: ['Planner|Lesson Planner'] },
  gibbonOutcome: { read: ['Planner|Outcomes By Course', 'Planner|Concept Explorer'], write: [] },
  gibbonPerson: {
    read: ['Students|View Student Profile', 'Students|Students by Form Group', 'Students|Students by House', 'Students|Form Group Summary', 'Students|Age & Gender Summary', 'Students|Emergency Data Summary', 'Students|Family Address by Student', 'Students|Student ID Cards', 'Students|Student Transport', 'Students|Privacy Choices by Student', 'Students|Letters Home by Form Group', 'Students|My Student History', 'Students|Emergency SMS by Year Group', 'Students|Emergency SMS by Transport', 'Students|Personal Document Summary', 'Staff|Staff Directory', 'Form Groups|View Form Groups'],
    write: ['Data Updater|Update Personal Data'],
  },
  gibbonStudentEnrolment: {
    read: ['Students|Students by Form Group', 'Students|Students by House', 'Students|Form Group Summary', 'Students|View Student Profile', 'Timetable Admin|Class Enrolment by Form Group'],
    write: ['Timetable Admin|Course Enrolment Rollover', 'User Admin|Rollover'],
  },
  gibbonStudentNote: {
    read: ['Students|View Student Profile', 'Student Alerts|Manage Student Alerts', 'Student Alerts|Student Alerts by Class', 'Student Alerts|Student Alerts by Form Group'],
    write: ['Student Alerts|Manage Student Alerts'],
  },
  gibbonAlert: {
    read: ['Student Alerts|Manage Student Alerts', 'Student Alerts|Student Alerts by Class', 'Student Alerts|Student Alerts by Form Group'],
    write: ['Student Alerts|Manage Student Alerts'],
  },
  gibbonStaff: {
    read: ['Staff|Staff Directory', 'Staff|Weekly Absences', 'Staff|Staff Absence Summary', 'Staff|Staff Coverage Summary', 'Staff|Substitute Availability', 'Staff|Daily Coverage Planner'],
    write: ['Data Updater|Update Staff Data'],
  },
  gibbonStaffAbsence: {
    read: ['Staff|Staff Absence Summary', 'Staff|Weekly Absences', 'Staff|View Absences', 'Staff|Approve Staff Absences'],
    write: ['Staff|New Absence', 'Staff|Approve Staff Absences'],
  },
  gibbonStaffAbsenceDate: {
    read: ['Staff|Staff Absence Summary', 'Staff|Weekly Absences', 'Staff|View Absences'],
    write: ['Staff|New Absence', 'Staff|Approve Staff Absences'],
  },
  gibbonStaffCoverage: {
    read: ['Staff|Staff Coverage Summary', 'Staff|My Coverage', 'Staff|Open Requests', 'Staff|Daily Coverage Planner', 'Staff|Manage Substitutes', 'Staff|Substitute Availability'],
    write: ['Staff|Request Coverage', 'Staff|Manage Substitutes'],
  },
  gibbonStaffCoverageDate: {
    read: ['Staff|Staff Coverage Summary', 'Staff|My Coverage', 'Staff|Open Requests', 'Staff|Daily Coverage Planner'],
    write: ['Staff|Request Coverage', 'Staff|Manage Substitutes'],
  },
  gibbonMarkbookEntry: { read: ['Markbook|Edit Markbook', 'Markbook|Manage Weightings'], write: ['Markbook|Manage Weightings'] },
  gibbonMarkbookColumn: { read: ['Markbook|Edit Markbook', 'Markbook|Manage Weightings'], write: ['Markbook|Manage Weightings'] },
  gibbonMarkbookTarget: { read: ['Markbook|Edit Markbook'], write: ['Markbook|Edit Markbook'] },
  gibbonReportingValue: {
    read: ['Reports|View by Report', 'Reports|View by Student', 'Reports|View Draft Reports', 'Reports|View Past Reports', 'Reports|View Reports', 'Reports|My Reporting', 'Reports|Proof Read', 'Reports|Student Name Conflicts'],
    write: ['Reports|Write Reports', 'Reports|Generate Reports', 'Reports|Upload Reports', 'Reports|Proof Read'],
  },
  gibbonReportingProgress: {
    read: ['Reports|Progress by Person', 'Reports|Progress by Department', 'Reports|Proof Reading Progress', 'Reports|My Reporting'],
    write: ['Reports|Write Reports', 'Reports|Generate Reports'],
  },
  gibbonReportingCycle: {
    read: ['Reports|Manage Reporting Cycles', 'Reports|My Reporting', 'Reports|View by Report', 'Reports|View Past Reports'],
    write: ['Reports|Manage Reporting Cycles', 'Reports|Manage Reports'],
  },
  gibbonReportArchiveEntry: {
    read: ['Reports|View Past Reports', 'Reports|View by Student', 'Reports|View Reports'],
    write: ['Reports|Upload Reports', 'Reports|Generate Reports', 'Reports|Send Reports', 'Reports|Send Notifications'],
  },
  gibbonReportTemplate: { read: ['Reports|Manage Reports'], write: ['Reports|Manage Reports', 'Reports|Generate Reports'] },
  gibbonReportingProof: { read: ['Reports|Proof Reading Progress'], write: ['Reports|Proof Read'] },
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
  'Table => Gibbon module/action mapping used for role-permission enforcement.',
  '',
  'GENERATED FILE — do not edit by hand.',
  'Run: node "tools/generate-action-map.mjs" from the module folder.',
  'Derived statically from core/gibbon.sql (tawasulAction.URLList) and the PHP',
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

// A wrong TAWASUL_CORE_DIR silently produces an empty map: the generator reads
// its table list from the core schema and its actions from that same tree, so
// point it at a directory without action INSERT rows and every table goes
// unmapped. Writing that out would quietly strip role-permission enforcement
// from the whole API, which is far worse than refusing to run. Require a real
// share of the tables to map before overwriting the file that ships.
const share = tables.length === 0 ? 0 : Object.keys(map).length / tables.length;
if (share < 0.25) {
  console.error(
    `refusing to write actionMap.php: only ${Object.keys(map).length}/${tables.length} tables mapped. ` +
      'Check TAWASUL_CORE_DIR points at the core schema root and modules directory.',
  );
  process.exit(1);
}
fs.writeFileSync(path.join(addon, 'src/Resource/actionMap.php'), lines.join('\n'));

// ---- report ---------------------------------------------------------------
const byConfidence = { curated: 0, high: 0, medium: 0, low: 0 };
for (const e of Object.values(map)) byConfidence[e.confidence]++;

const unmapped = tables.filter((t) => !map[t]);

const doc = [
  '# Action map — REST resources to Gibbon permissions',
  '',
  `Generated ${new Date().toISOString()} by \`tools/generate-action-map.mjs\`.`,
  '',
  `- Core tables: **${tables.length}**`,
  `- Tables mapped to a Gibbon action: **${Object.keys(map).length}** (${Math.round((Object.keys(map).length / tables.length) * 100)}%)`,
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
