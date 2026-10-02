# Action map — REST resources to Gibbon permissions

Generated 2026-10-02T01:02:08.897Z by `tools/generate-action-map.mjs`.

- Core tables: **208**
- Tables mapped to a Gibbon action: **98** (47%)
- Confidence: 0 curated, 0 high, 49 medium, 49 low
- Core actions in gibbon.sql: **388**
- Distinct actions used by the map: **57**

| Table | Module | Read action | Write action | Confidence |
| --- | --- | --- | --- | --- |
| `tawasulActivityChoice` | TawasulActivities | Students Not Signed Up | Manage Choices | low |
| `tawasulActivityPhoto` | TawasulActivities | Manage Activities | Manage Activities | low |
| `tawasulAdmissionsAccount` | TawasulAdmissions | Manage Applications | Manage Applications | medium |
| `tawasulAdmissionsApplication` | TawasulAdmissions | Manage Applications | Manage Applications | medium |
| `tawasulAlertType` | TawasulSchoolAdmin | Student Alert Settings | Student Alert Settings | medium |
| `tawasulApplicationForm` | TawasulStudents | Manage Applications | Manage Applications | low |
| `tawasulApplicationFormFile` | TawasulStudents | Manage Applications | Manage Applications | medium |
| `tawasulApplicationFormLink` | TawasulStudents | Manage Applications | Manage Applications | low |
| `tawasulApplicationFormRelationship` | TawasulStudents | Manage Applications | Manage Applications | medium |
| `tawasulAttendanceLogCourseClass` | TawasulAttendance | View Daily Attendance | View Daily Attendance | low |
| `tawasulAttendanceLogFormGroup` | TawasulAttendance | View Daily Attendance | View Daily Attendance | low |
| `tawasulBehaviour` | TawasulPlanner | Work Summary by Form Group | Work Summary by Form Group | medium |
| `tawasulCalendar` | TawasulActivities | Enter Activity Attendance | Enter Activity Attendance | low |
| `tawasulCalendarEditor` | TawasulCalendar | Manage Events | Manage Events | low |
| `tawasulCalendarEvent` | TawasulActivities | Enter Activity Attendance | Enter Activity Attendance | low |
| `tawasulCalendarEventPerson` | TawasulCalendar | Manage Events | Manage Events | low |
| `tawasulCalendarEventType` | TawasulActivities | Enter Activity Attendance | Enter Activity Attendance | low |
| `tawasulCrowdAssessDiscuss` | TawasulCrowdAssessment | Assess | Assess | medium |
| `tawasulCustomField` | TawasulStudents | Medical Data Summary | Medical Data Summary | medium |
| `tawasulDataRetention` | TawasulUserAdmin | Manage Users | Manage Users | medium |
| `tawasulDiscussion` | TawasulCrowdAssessment | Assess | Assess | medium |
| `tawasulDistrict` | TawasulUserAdmin | Manage Districts | Manage Districts | medium |
| `tawasulEmailTemplate` | TawasulSystemAdmin | Email Templates | Email Templates | medium |
| `tawasulExternalAssessment` | TawasulFormalAssessment | Write Internal Assessments | External Assessment Data | low |
| `tawasulExternalAssessmentField` | TawasulFormalAssessment | External Assessment Data | External Assessment Data | low |
| `tawasulExternalAssessmentStudent` | TawasulFormalAssessment | Write Internal Assessments | External Assessment Data | low |
| `tawasulExternalAssessmentStudentEntry` | TawasulFormalAssessment | External Assessment Data | External Assessment Data | medium |
| `tawasulFamilyRelationship` | TawasulAdmissions | Manage Applications | Manage Applications | low |
| `tawasulFamilyUpdate` | TawasulDataUpdater | Family Data Updater History | Family Data Updates | medium |
| `tawasulFileExtension` | TawasulSchoolAdmin | Manage File Extensions | Manage File Extensions | low |
| `tawasulFinanceBillingSchedule` | TawasulFinance | Manage Invoices | Manage Invoices | medium |
| `tawasulFinanceBudget` | TawasulFinance | Manage Expenses | Manage Expenses | low |
| `tawasulFinanceBudgetCycle` | TawasulFinance | Manage Budgets | Manage Budgets | low |
| `tawasulFinanceBudgetCycleAllocation` | TawasulFinance | Manage Budget Cycles | Manage Budget Cycles | medium |
| `tawasulFinanceBudgetPerson` | TawasulFinance | Manage Expenses | Manage Expenses | low |
| `tawasulFinanceExpense` | TawasulFinance | Manage Expenses | Manage Expenses | low |
| `tawasulFinanceExpenseApprover` | TawasulFinance | Manage Expenses | Manage Expenses | low |
| `tawasulFinanceExpenseLog` | TawasulFinance | Manage Expenses | Manage Expenses | medium |
| `tawasulFinanceFee` | TawasulFinance | Manage Fees | Manage Fees | medium |
| `tawasulFinanceFeeCategory` | TawasulFinance | Manage Fees | Manage Fees | low |
| `tawasulFinanceInvoice` | TawasulFinance | Manage Invoices | Manage Invoices | medium |
| `tawasulFinanceInvoicee` | TawasulFinance | Manage Invoicees | Manage Invoicees | low |
| `tawasulFinanceInvoiceeUpdate` | TawasulDataUpdater | Finance Data Updates | Finance Data Updates | medium |
| `tawasulFinanceInvoiceFee` | TawasulFinance | Manage Invoices | Manage Invoices | medium |
| `tawasulFinancePettyCash` | TawasulFinance | Petty Cash | Petty Cash | medium |
| `tawasulForm` | TawasulAdmissions | Manage Applications | Manage Applications | low |
| `tawasulFormField` | TawasulStaff | Manage Applications | Manage Applications | low |
| `tawasulFormPage` | TawasulAdmissions | My Application Forms | Manage Applications | low |
| `tawasulFormSubmission` | TawasulAdmissions | My Application Forms | Manage Applications | low |
| `tawasulIN` | TawasulIndividualNeeds | Archive Records | Archive Records | low |
| `tawasulINDescriptor` | TawasulIndividualNeeds | Archive Records | Archive Records | low |
| `tawasulINPersonDescriptor` | TawasulIndividualNeeds | Archive Records | Archive Records | low |
| `tawasulInternalAssessmentColumn` | TawasulTracking | Graphing | Graphing | low |
| `tawasulInternalAssessmentEntry` | TawasulTracking | Graphing | Graphing | low |
| `tawasulLanguage` | TawasulStudents | Application Form | Manage Applications | medium |
| `tawasulLibraryItem` | TawasulLibrary | Manage Catalog | Manage Catalog | low |
| `tawasulLibraryItemEvent` | TawasulLibrary | Lending & Activity Log | Manage Catalog | low |
| `tawasulLibraryShelf` | TawasulLibrary | Browse The Library | Browse The Library | medium |
| `tawasulLibraryShelfItem` | TawasulLibrary | Browse The Library | Browse The Library | medium |
| `tawasulLibraryType` | TawasulLibrary | Manage Catalog | Manage Catalog | low |
| `tawasulMarkbookWeight` | TawasulMarkbook | Manage Weightings | Manage Weightings | low |
| `tawasulMessenger` | TawasulMessenger | Manage Messages | Manage Messages | medium |
| `tawasulMessengerCannedResponse` | TawasulMessenger | Canned Response | Canned Response | medium |
| `tawasulMessengerMailingList` | TawasulMessenger | Manage Mailing List Recipients | Manage Mailing List Recipients | low |
| `tawasulMessengerMailingListRecipient` | TawasulMessenger | Manage Mailing List Recipients | Manage Mailing List Recipients | low |
| `tawasulMessengerReceipt` | TawasulMessenger | Manage Messages | Manage Messages | medium |
| `tawasulMessengerTarget` | TawasulMessenger | Manage Messages | Manage Messages | medium |
| `tawasulNotification` | TawasulSystemAdmin | Notification Events | Notification Events | medium |
| `tawasulNotificationEvent` | TawasulSystemAdmin | Notification Events | Notification Events | medium |
| `tawasulNotificationListener` | TawasulSystemAdmin | Notification Events | Notification Events | medium |
| `tawasulPayment` | TawasulStudents | Manage Applications | Manage Applications | low |
| `tawasulPersonMedicalConditionUpdate` | TawasulDataUpdater | Update Medical Data | Medical Form Updates | low |
| `tawasulPersonStatusLog` | TawasulUserAdmin | Rollover | Rollover | medium |
| `tawasulPersonUpdate` | TawasulDataUpdater | Student Data Updater History | Personal Data Updates | low |
| `tawasulPlannerParentWeeklyEmailSummary` | TawasulPlanner | Parent Weekly Email Summary | Parent Weekly Email Summary | medium |
| `tawasulReport` | TawasulReports | Generate Reports | Manage Reports | medium |
| `tawasulReportingCriteria` | TawasulReports | Manage Criteria | Manage Criteria | medium |
| `tawasulReportingCycle` | TawasulReports | Generate Reports | Manage Reports | low |
| `tawasulReportingScope` | TawasulReports | My Reporting | My Reporting | low |
| `tawasulResource` | TawasulPlanner | View Resources | Manage Resources | medium |
| `tawasulResourceTag` | TawasulPlanner | Manage Resources | Manage Resources | low |
| `tawasulStaffAbsence` | TawasulStaff | Weekly Absences | Manage Staff Coverage | medium |
| `tawasulStaffAbsenceDate` | TawasulStaff | Weekly Absences | Manage Staff Coverage | medium |
| `tawasulStaffAbsenceType` | TawasulStaff | Weekly Absences | Manage Staff Coverage | low |
| `tawasulStaffApplicationForm` | TawasulStaff | Manage Applications | Manage Applications | medium |
| `tawasulStaffApplicationFormFile` | TawasulStaff | Manage Applications | Manage Applications | medium |
| `tawasulStaffContract` | TawasulStaff | Manage Staff | Manage Staff | medium |
| `tawasulStaffCoverage` | TawasulStaff | Daily Coverage Planner | Manage Staff Coverage | medium |
| `tawasulStaffCoverageDate` | TawasulStaff | Daily Coverage Planner | Manage Staff Coverage | medium |
| `tawasulStaffDuty` | TawasulStaff | Daily Coverage Planner | Duty Schedule | low |
| `tawasulStaffDutyPerson` | TawasulStaff | Daily Coverage Planner | Manage Staff Coverage | medium |
| `tawasulStaffJobOpening` | TawasulStaff | Manage Applications | Manage Applications | medium |
| `tawasulString` | TawasulSystemAdmin | String Replacement | String Replacement | medium |
| `tawasulSubstitute` | TawasulStaff | Substitute Availability | Manage Staff Coverage | medium |
| `tawasulTT` | TawasulTimetable | View Master Timetable | View Master Timetable | low |
| `tawasulTTImport` | TawasulTimetableAdmin | Manage Timetables | Manage Timetables | medium |
| `tawasulTTSpaceBooking` | TawasulTimetable | View Available Facilities | Manage Facility Bookings | low |
| `tawasulUnitBlock` | TawasulPlanner | Unit Planner | Unit Planner | low |

## Unmapped tables (110)

No core screen owns these tables, so the API falls back to scope-only checks
plus the linked-user requirement.

- `tawasulAction`
- `tawasulActivity`
- `tawasulActivityAttendance`
- `tawasulActivityCategory`
- `tawasulActivitySlot`
- `tawasulActivityStaff`
- `tawasulActivityStudent`
- `tawasulActivityType`
- `tawasulAlarm`
- `tawasulAlarmConfirm`
- `tawasulAlert`
- `tawasulAlertLevel`
- `tawasulAttendanceCode`
- `tawasulAttendanceLogPerson`
- `tawasulBehaviourFollowUp`
- `tawasulBehaviourLetter`
- `tawasulCountry`
- `tawasulCourse`
- `tawasulCourseClass`
- `tawasulCourseClassMap`
- `tawasulCourseClassPerson`
- `tawasulDaysOfWeek`
- `tawasulDepartment`
- `tawasulDepartmentResource`
- `tawasulDepartmentStaff`
- `tawasulFamily`
- `tawasulFamilyAdult`
- `tawasulFamilyChild`
- `tawasulFirstAid`
- `tawasulFirstAidFollowUp`
- `tawasulFormGroup`
- `tawasulFormUpload`
- `tawasulGroup`
- `tawasulGroupPerson`
- `tawasulHook`
- `tawasulHouse`
- `tawasuli18n`
- `tawasulINArchive`
- `tawasulINAssistant`
- `tawasulINInvestigation`
- `tawasulINInvestigationContribution`
- `tawasulLog`
- `tawasulMarkbookColumn`
- `tawasulMarkbookEntry`
- `tawasulMarkbookTarget`
- `tawasulMedicalCondition`
- `tawasulMigration`
- `tawasulModule`
- `tawasulOutcome`
- `tawasulPermission`
- `tawasulPerson`
- `tawasulPersonalDocument`
- `tawasulPersonalDocumentType`
- `tawasulPersonMedical`
- `tawasulPersonMedicalCondition`
- `tawasulPersonMedicalUpdate`
- `tawasulPersonPhoto`
- `tawasulPersonReset`
- `tawasulPlannerEntry`
- `tawasulPlannerEntryDiscuss`
- `tawasulPlannerEntryGuest`
- `tawasulPlannerEntryHomework`
- `tawasulPlannerEntryOutcome`
- `tawasulPlannerEntryStudentHomework`
- `tawasulPlannerEntryStudentTracker`
- `tawasulReportArchive`
- `tawasulReportArchiveEntry`
- `tawasulReportingAccess`
- `tawasulReportingCriteriaType`
- `tawasulReportingProgress`
- `tawasulReportingProof`
- `tawasulReportingValue`
- `tawasulReportPrototypeSection`
- `tawasulReportTemplate`
- `tawasulReportTemplateFont`
- `tawasulReportTemplateSection`
- `tawasulRole`
- `tawasulRubric`
- `tawasulRubricCell`
- `tawasulRubricColumn`
- `tawasulRubricEntry`
- `tawasulRubricRow`
- `tawasulScale`
- `tawasulScaleGrade`
- `tawasulSchoolYear`
- `tawasulSchoolYearSpecialDay`
- `tawasulSchoolYearTerm`
- `tawasulSession`
- `tawasulSetting`
- `tawasulSpace`
- `tawasulSpacePerson`
- `tawasulStaff`
- `tawasulStaffUpdate`
- `tawasulStudentEnrolment`
- `tawasulStudentNote`
- `tawasulStudentNoteCategory`
- `tawasulTheme`
- `tawasulTTColumn`
- `tawasulTTColumnRow`
- `tawasulTTDay`
- `tawasulTTDayDate`
- `tawasulTTDayRowClass`
- `tawasulTTDayRowClassException`
- `tawasulTTSpaceChange`
- `tawasulUnit`
- `tawasulUnitClass`
- `tawasulUnitClassBlock`
- `tawasulUnitOutcome`
- `tawasulUsernameFormat`
- `tawasulYearGroup`
