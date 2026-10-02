<?php
namespace Gibbon\Module\RestAPI\Controller;

use PDO;
use Gibbon\Module\RestAPI\Auth\Credential;
use Gibbon\Module\RestAPI\Auth\Permissions;
use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;
use Gibbon\Module\RestAPI\Resource\Registry;

/**
 * Endpoints that answer a real question in one round trip.
 *
 * The generic resource endpoints are complete but chatty: building a student
 * profile screen from them takes eight calls. These three composites cover the
 * cases every integration hits first, and each one re-checks the scope for
 * every section it returns, so a credential limited to, say, attendance never
 * receives behaviour data as a side effect.
 */
class CompositeController
{
    protected $pdo;
    protected $permissions;
    protected $credential;

    public function __construct(PDO $pdo, Permissions $permissions, Credential $credential)
    {
        $this->pdo = $pdo;
        $this->permissions = $permissions;
        $this->credential = $credential;
    }

    /**
     * GET /v2/students/{tawasulPersonID}/profile
     */
    public function studentProfile(string $tawasulPersonID, Request $request): array
    {
        $schoolYearID = $this->resolveSchoolYear($request);

        $this->permissions->authorise($this->credential, Registry::get('students'), 'GET');

        $stmt = $this->pdo->prepare("SELECT tawasulPerson.tawasulPersonID, tawasulPerson.username, tawasulPerson.studentID, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.firstName, tawasulPerson.preferredName, tawasulPerson.officialName, tawasulPerson.gender, tawasulPerson.dob, tawasulPerson.email, tawasulPerson.status, tawasulPerson.image_240, tawasulPerson.dateStart, tawasulPerson.dateEnd,
                tawasulYearGroup.name AS yearGroup, tawasulFormGroup.name AS formGroup, tawasulHouse.name AS house
            FROM tawasulPerson
            LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:year)
            LEFT JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
            LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
            LEFT JOIN tawasulHouse ON (tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID)
            WHERE tawasulPerson.tawasulPersonID=:id LIMIT 1");
        $stmt->execute(['id' => $tawasulPersonID, 'year' => $schoolYearID]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($student)) {
            throw ApiException::notFound('No student with id '.$tawasulPersonID.'.');
        }

        $profile = ['student' => $student, 'schoolYearID' => $schoolYearID];

        $profile['classes'] = $this->ifPermitted('class-enrolments', function () use ($tawasulPersonID, $schoolYearID) {
            $stmt = $this->pdo->prepare("SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.name AS courseName, tawasulCourse.nameShort AS courseNameShort, tawasulCourseClass.name AS className, tawasulCourseClass.nameShort AS classNameShort
                FROM tawasulCourseClassPerson
                JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                WHERE tawasulCourseClassPerson.tawasulPersonID=:id AND tawasulCourseClassPerson.role='Student' AND tawasulCourse.tawasulSchoolYearID=:year
                ORDER BY tawasulCourse.nameShort");
            $stmt->execute(['id' => $tawasulPersonID, 'year' => $schoolYearID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        $profile['family'] = $this->ifPermitted('families', function () use ($tawasulPersonID) {
            $stmt = $this->pdo->prepare("SELECT tawasulFamily.tawasulFamilyID, tawasulFamily.name AS familyName, adult.tawasulPersonID, adult.surname, adult.preferredName, adult.email, adult.phone1, tawasulFamilyAdult.contactPriority, tawasulFamilyAdult.childDataAccess
                FROM tawasulFamilyChild
                JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                JOIN tawasulPerson AS adult ON (tawasulFamilyAdult.tawasulPersonID=adult.tawasulPersonID)
                WHERE tawasulFamilyChild.tawasulPersonID=:id
                ORDER BY tawasulFamilyAdult.contactPriority");
            $stmt->execute(['id' => $tawasulPersonID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        $profile['attendanceSummary'] = $this->ifPermitted('attendance', function () use ($tawasulPersonID, $schoolYearID) {
            $stmt = $this->pdo->prepare("SELECT tawasulAttendanceLogPerson.type, COUNT(*) AS count
                FROM tawasulAttendanceLogPerson
                JOIN tawasulSchoolYear ON (tawasulAttendanceLogPerson.date BETWEEN tawasulSchoolYear.firstDay AND tawasulSchoolYear.lastDay)
                WHERE tawasulAttendanceLogPerson.tawasulPersonID=:id AND tawasulSchoolYear.tawasulSchoolYearID=:year
                GROUP BY tawasulAttendanceLogPerson.type");
            $stmt->execute(['id' => $tawasulPersonID, 'year' => $schoolYearID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        $profile['behaviour'] = $this->ifPermitted('behaviour', function () use ($tawasulPersonID, $schoolYearID) {
            $stmt = $this->pdo->prepare("SELECT tawasulBehaviourID, date, type, descriptor, level, comment
                FROM tawasulBehaviour WHERE tawasulPersonID=:id AND tawasulSchoolYearID=:year
                ORDER BY date DESC LIMIT 20");
            $stmt->execute(['id' => $tawasulPersonID, 'year' => $schoolYearID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        $profile['medicalAlerts'] = $this->ifPermitted('medical', function () use ($tawasulPersonID) {
            $stmt = $this->pdo->prepare("SELECT tawasulPersonMedicalCondition.name, tawasulPersonMedicalCondition.triggers, tawasulPersonMedicalCondition.reaction, tawasulPersonMedicalCondition.response, tawasulAlertLevel.name AS alertLevel
                FROM tawasulPersonMedical
                JOIN tawasulPersonMedicalCondition ON (tawasulPersonMedicalCondition.tawasulPersonMedicalID=tawasulPersonMedical.tawasulPersonMedicalID)
                LEFT JOIN tawasulAlertLevel ON (tawasulPersonMedicalCondition.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID)
                WHERE tawasulPersonMedical.tawasulPersonID=:id");
            $stmt->execute(['id' => $tawasulPersonID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        return ['data' => $profile];
    }

    /**
     * GET /v2/people/{tawasulPersonID}/timetable?date=YYYY-MM-DD
     *
     * Works for students and teachers alike: both are rows in
     * tawasulCourseClassPerson, so one query serves both.
     */
    public function personTimetable(string $tawasulPersonID, Request $request): array
    {
        $this->permissions->authorise($this->credential, Registry::get('timetable-slots'), 'GET');

        $date = (string) $request->query('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ApiException::badRequest('date must be in YYYY-MM-DD format.');
        }

        $sql = "SELECT tawasulTTColumnRow.name AS period, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd,
                    tawasulCourse.nameShort AS courseNameShort, tawasulCourseClass.nameShort AS classNameShort,
                    tawasulCourseClass.tawasulCourseClassID, tawasulSpace.name AS space, tawasulCourseClassPerson.role
                FROM tawasulTTDayDate
                JOIN tawasulTTDay ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID)
                JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:id)
                LEFT JOIN tawasulSpace ON (tawasulTTDayRowClass.tawasulSpaceID=tawasulSpace.tawasulSpaceID)
                WHERE tawasulTTDayDate.date=:date
                ORDER BY tawasulTTColumnRow.timeStart";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $tawasulPersonID, 'date' => $date]);

        return ['data' => ['date' => $date, 'tawasulPersonID' => $tawasulPersonID, 'periods' => $stmt->fetchAll(PDO::FETCH_ASSOC)]];
    }

    /**
     * GET /v2/classes/{tawasulCourseClassID}/roster
     */
    public function classRoster(string $tawasulCourseClassID): array
    {
        $this->permissions->authorise($this->credential, Registry::get('class-enrolments'), 'GET');

        $stmt = $this->pdo->prepare("SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name AS className, tawasulCourse.name AS courseName, tawasulCourse.tawasulSchoolYearID
            FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
            WHERE tawasulCourseClass.tawasulCourseClassID=:id LIMIT 1");
        $stmt->execute(['id' => $tawasulCourseClassID]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($class)) {
            throw ApiException::notFound('No class with id '.$tawasulCourseClassID.'.');
        }

        $stmt = $this->pdo->prepare("SELECT tawasulCourseClassPerson.role, tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.studentID, tawasulPerson.email, tawasulPerson.image_240, tawasulFormGroup.nameShort AS formGroup
            FROM tawasulCourseClassPerson
            JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:year)
            LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
            WHERE tawasulCourseClassPerson.tawasulCourseClassID=:id
            ORDER BY tawasulCourseClassPerson.role, tawasulPerson.surname, tawasulPerson.preferredName");
        $stmt->execute(['id' => $tawasulCourseClassID, 'year' => $class['tawasulSchoolYearID']]);

        return ['data' => ['class' => $class, 'members' => $stmt->fetchAll(PDO::FETCH_ASSOC)]];
    }

    /**
     * GET /v2/staff/{tawasulPersonID}/profile
     *
     * The staff equivalent of a student profile: who they are, what they teach,
     * which departments they belong to and their absence record. Contract and
     * salary detail is deliberately not included — that lives behind the
     * staff-contracts resource, where a credential must ask for it explicitly.
     */
    public function staffProfile(string $tawasulPersonID, Request $request): array
    {
        $schoolYearID = $this->resolveSchoolYear($request);
        $this->permissions->authorise($this->credential, Registry::get('staff'), 'GET');

        $stmt = $this->pdo->prepare("SELECT tawasulPerson.tawasulPersonID, tawasulPerson.username, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.firstName, tawasulPerson.preferredName, tawasulPerson.officialName, tawasulPerson.email, tawasulPerson.emailAlternate, tawasulPerson.phone1, tawasulPerson.status, tawasulPerson.image_240, tawasulPerson.dateStart, tawasulPerson.dateEnd,
                tawasulStaff.tawasulStaffID, tawasulStaff.type AS staffType, tawasulStaff.initials, tawasulStaff.jobTitle, tawasulStaff.firstAidQualified, tawasulStaff.firstAidExpiry, tawasulStaff.biography,
                tawasulRole.name AS primaryRole
            FROM tawasulPerson
            LEFT JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
            WHERE tawasulPerson.tawasulPersonID=:id LIMIT 1");
        $stmt->execute(['id' => $tawasulPersonID]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($staff)) {
            throw ApiException::notFound('No person with id '.$tawasulPersonID.'.');
        }

        $profile = ['staff' => $staff, 'schoolYearID' => $schoolYearID];

        $profile['classes'] = $this->ifPermitted('class-enrolments', function () use ($tawasulPersonID, $schoolYearID) {
            $stmt = $this->pdo->prepare("SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.name AS courseName, tawasulCourse.nameShort AS courseNameShort, tawasulCourseClass.name AS className, tawasulCourseClass.nameShort AS classNameShort, tawasulCourseClassPerson.role,
                    (SELECT COUNT(*) FROM tawasulCourseClassPerson AS student WHERE student.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND student.role='Student') AS studentCount
                FROM tawasulCourseClassPerson
                JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                WHERE tawasulCourseClassPerson.tawasulPersonID=:id AND tawasulCourseClassPerson.role LIKE 'Teacher%' AND tawasulCourse.tawasulSchoolYearID=:year
                ORDER BY tawasulCourse.nameShort, tawasulCourseClass.nameShort");
            $stmt->execute(['id' => $tawasulPersonID, 'year' => $schoolYearID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        $profile['departments'] = $this->ifPermitted('departments', function () use ($tawasulPersonID) {
            $stmt = $this->pdo->prepare("SELECT tawasulDepartment.tawasulDepartmentID, tawasulDepartment.name, tawasulDepartment.nameShort, tawasulDepartment.type, tawasulDepartmentStaff.role
                FROM tawasulDepartmentStaff
                JOIN tawasulDepartment ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                WHERE tawasulDepartmentStaff.tawasulPersonID=:id
                ORDER BY tawasulDepartment.name");
            $stmt->execute(['id' => $tawasulPersonID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        $profile['absences'] = $this->ifPermitted('staff-absences', function () use ($tawasulPersonID, $schoolYearID) {
            $stmt = $this->pdo->prepare("SELECT tawasulStaffAbsence.tawasulStaffAbsenceID, tawasulStaffAbsenceType.name AS type, tawasulStaffAbsence.reason, tawasulStaffAbsence.status, tawasulStaffAbsence.coverageRequired, tawasulStaffAbsence.timestampCreator,
                    MIN(tawasulStaffAbsenceDate.date) AS dateStart, MAX(tawasulStaffAbsenceDate.date) AS dateEnd, COUNT(tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID) AS days
                FROM tawasulStaffAbsence
                LEFT JOIN tawasulStaffAbsenceType ON (tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID)
                LEFT JOIN tawasulStaffAbsenceDate ON (tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID)
                WHERE tawasulStaffAbsence.tawasulPersonID=:id AND tawasulStaffAbsence.tawasulSchoolYearID=:year
                GROUP BY tawasulStaffAbsence.tawasulStaffAbsenceID
                ORDER BY dateStart DESC LIMIT 50");
            $stmt->execute(['id' => $tawasulPersonID, 'year' => $schoolYearID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        return ['data' => $profile];
    }

    /**
     * GET /v2/families/{tawasulFamilyID}/profile
     *
     * The whole household in one call: address, the adults with their contact
     * priority and data-access flag, the children, and how everyone is related.
     * This is the shape a parent-contact or admissions integration needs, and
     * assembling it from the generic endpoints takes five calls.
     */
    public function familyProfile(string $tawasulFamilyID): array
    {
        $this->permissions->authorise($this->credential, Registry::get('families'), 'GET');

        $stmt = $this->pdo->prepare("SELECT tawasulFamilyID, name, nameAddress, homeAddress, homeAddressDistrict, homeAddressCountry, status, languageHomePrimary, languageHomeSecondary
            FROM tawasulFamily WHERE tawasulFamilyID=:id LIMIT 1");
        $stmt->execute(['id' => $tawasulFamilyID]);
        $family = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($family)) {
            throw ApiException::notFound('No family with id '.$tawasulFamilyID.'.');
        }

        $adults = $this->pdo->prepare("SELECT tawasulFamilyAdult.tawasulFamilyAdultID, tawasulFamilyAdult.contactPriority, tawasulFamilyAdult.childDataAccess, tawasulFamilyAdult.contactCall, tawasulFamilyAdult.contactSMS, tawasulFamilyAdult.contactEmail, tawasulFamilyAdult.contactMail, tawasulFamilyAdult.comment,
                tawasulPerson.tawasulPersonID, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.email, tawasulPerson.phone1, tawasulPerson.phone2, tawasulPerson.status, tawasulPerson.image_240
            FROM tawasulFamilyAdult
            JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
            WHERE tawasulFamilyAdult.tawasulFamilyID=:id
            ORDER BY tawasulFamilyAdult.contactPriority, tawasulPerson.surname");
        $adults->execute(['id' => $tawasulFamilyID]);

        $children = $this->pdo->prepare("SELECT tawasulFamilyChild.tawasulFamilyChildID, tawasulFamilyChild.comment,
                tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.studentID, tawasulPerson.dob, tawasulPerson.status, tawasulPerson.image_240,
                tawasulYearGroup.name AS yearGroup, tawasulFormGroup.name AS formGroup
            FROM tawasulFamilyChild
            JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
            LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
            WHERE tawasulFamilyChild.tawasulFamilyID=:id
            GROUP BY tawasulFamilyChild.tawasulFamilyChildID
            ORDER BY tawasulPerson.dob");
        $children->execute(['id' => $tawasulFamilyID]);

        $relationships = $this->pdo->prepare("SELECT tawasulPersonID1, tawasulPersonID2, relationship FROM tawasulFamilyRelationship WHERE tawasulFamilyID=:id");
        $relationships->execute(['id' => $tawasulFamilyID]);

        return ['data' => [
            'family' => $family,
            'adults' => $adults->fetchAll(PDO::FETCH_ASSOC),
            'children' => $children->fetchAll(PDO::FETCH_ASSOC),
            'relationships' => $relationships->fetchAll(PDO::FETCH_ASSOC),
        ]];
    }

    /**
     * GET /v2/courses/{tawasulCourseID}/overview
     *
     * A course with its classes, the staff teaching each one and how full each
     * class is — the timetabling question that otherwise needs one call per
     * class.
     */
    public function courseOverview(string $tawasulCourseID): array
    {
        $this->permissions->authorise($this->credential, Registry::get('courses'), 'GET');

        $stmt = $this->pdo->prepare("SELECT tawasulCourse.tawasulCourseID, tawasulCourse.tawasulSchoolYearID, tawasulCourse.name, tawasulCourse.nameShort, tawasulCourse.description, tawasulCourse.tawasulYearGroupIDList,
                tawasulDepartment.name AS department, tawasulSchoolYear.name AS schoolYear
            FROM tawasulCourse
            LEFT JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
            LEFT JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
            WHERE tawasulCourse.tawasulCourseID=:id LIMIT 1");
        $stmt->execute(['id' => $tawasulCourseID]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($course)) {
            throw ApiException::notFound('No course with id '.$tawasulCourseID.'.');
        }

        $classes = $this->pdo->prepare("SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourseClass.reportable, tawasulCourseClass.attendance, tawasulCourseClass.enrolmentMin, tawasulCourseClass.enrolmentMax,
                (SELECT COUNT(*) FROM tawasulCourseClassPerson AS s WHERE s.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND s.role='Student') AS studentCount
            FROM tawasulCourseClass
            WHERE tawasulCourseClass.tawasulCourseID=:id
            ORDER BY tawasulCourseClass.nameShort");
        $classes->execute(['id' => $tawasulCourseID]);
        $classes = $classes->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($classes)) {
            $ids = array_column($classes, 'tawasulCourseClassID');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $teachers = $this->pdo->prepare("SELECT tawasulCourseClassPerson.tawasulCourseClassID, tawasulCourseClassPerson.role, tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.email
                FROM tawasulCourseClassPerson
                JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulCourseClassPerson.tawasulCourseClassID IN ($placeholders) AND tawasulCourseClassPerson.role LIKE 'Teacher%'
                ORDER BY tawasulCourseClassPerson.role, tawasulPerson.surname");
            $teachers->execute($ids);

            $byClass = [];
            foreach ($teachers->fetchAll(PDO::FETCH_ASSOC) as $teacher) {
                $byClass[$teacher['tawasulCourseClassID']][] = $teacher;
            }

            foreach ($classes as $index => $class) {
                $classes[$index]['teachers'] = $byClass[$class['tawasulCourseClassID']] ?? [];
            }
        }

        return ['data' => ['course' => $course, 'classes' => $classes]];
    }

    /**
     * GET /v2/students/{tawasulPersonID}/finance
     *
     * What a family owes and has paid. Invoices carry their fee lines and any
     * recorded payment, and the totals are computed from the fee lines rather
     * than trusted from a single column, because Gibbon lets an invoice be
     * edited after issue.
     */
    public function financeSummary(string $tawasulPersonID, Request $request): array
    {
        $schoolYearID = $this->resolveSchoolYear($request);
        $this->permissions->authorise($this->credential, Registry::get('invoices'), 'GET');

        $stmt = $this->pdo->prepare("SELECT tawasulPersonID, surname, preferredName, studentID FROM tawasulPerson WHERE tawasulPersonID=:id LIMIT 1");
        $stmt->execute(['id' => $tawasulPersonID]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($person)) {
            throw ApiException::notFound('No person with id '.$tawasulPersonID.'.');
        }

        $stmt = $this->pdo->prepare("SELECT tawasulFinanceInvoice.tawasulFinanceInvoiceID, tawasulFinanceInvoice.tawasulSchoolYearID, tawasulFinanceInvoice.status, tawasulFinanceInvoice.invoiceIssueDate, tawasulFinanceInvoice.invoiceDueDate, tawasulFinanceInvoice.paidDate, tawasulFinanceInvoice.paidAmount, tawasulFinanceInvoice.billingScheduleType, tawasulFinanceInvoice.notes,
                tawasulFinanceBillingSchedule.name AS billingSchedule
            FROM tawasulFinanceInvoice
            JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID)
            LEFT JOIN tawasulFinanceBillingSchedule ON (tawasulFinanceInvoice.tawasulFinanceBillingScheduleID=tawasulFinanceBillingSchedule.tawasulFinanceBillingScheduleID)
            WHERE tawasulFinanceInvoicee.tawasulPersonID=:id".($schoolYearID ? " AND tawasulFinanceInvoice.tawasulSchoolYearID=:year" : '')."
            ORDER BY tawasulFinanceInvoice.invoiceDueDate DESC");
        $stmt->execute($schoolYearID ? ['id' => $tawasulPersonID, 'year' => $schoolYearID] : ['id' => $tawasulPersonID]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totals = ['invoiced' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0, 'invoiceCount' => count($invoices)];

        foreach ($invoices as $index => $invoice) {
            $fees = $this->pdo->prepare("SELECT tawasulFinanceInvoiceFeeID, name, description, fee, feeType
                FROM tawasulFinanceInvoiceFee WHERE tawasulFinanceInvoiceID=:id ORDER BY sequenceNumber");
            $fees->execute(['id' => $invoice['tawasulFinanceInvoiceID']]);
            $fees = $fees->fetchAll(PDO::FETCH_ASSOC);

            $amount = 0.0;
            foreach ($fees as $fee) {
                $amount += (float) $fee['fee'];
            }

            $paid = (float) $invoice['paidAmount'];
            $invoices[$index]['fees'] = $fees;
            $invoices[$index]['amount'] = round($amount, 2);
            $invoices[$index]['outstanding'] = round(max(0, $amount - $paid), 2);

            $payments = $this->pdo->prepare("SELECT tawasulPaymentID, type, status, amount, gateway, timestamp
                FROM tawasulPayment WHERE foreignTable='tawasulFinanceInvoice' AND foreignTableID=:id ORDER BY timestamp");
            $payments->execute(['id' => $invoice['tawasulFinanceInvoiceID']]);
            $invoices[$index]['payments'] = $payments->fetchAll(PDO::FETCH_ASSOC);

            $totals['invoiced'] += $amount;
            $totals['paid'] += $paid;
        }

        $totals['outstanding'] = round(max(0, $totals['invoiced'] - $totals['paid']), 2);
        $totals['invoiced'] = round($totals['invoiced'], 2);
        $totals['paid'] = round($totals['paid'], 2);

        return ['data' => [
            'person' => $person,
            'schoolYearID' => $schoolYearID,
            'totals' => $totals,
            'invoices' => $invoices,
        ]];
    }

    /**
     * Returns the section when the credential may see it, otherwise a marker
     * explaining why it is absent, rather than failing the whole request.
     */
    protected function ifPermitted(string $resourceName, callable $callback)
    {
        try {
            $this->permissions->authorise($this->credential, Registry::get($resourceName), 'GET');
        } catch (ApiException $e) {
            return ['omitted' => $e->getMessage()];
        }

        return $callback();
    }

    protected function resolveSchoolYear(Request $request): ?string
    {
        $year = $request->query('tawasulSchoolYearID') ?: $request->getHeader('x-school-year');
        if (!empty($year)) {
            return (string) $year;
        }

        return $this->pdo->query("SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current' LIMIT 1")->fetchColumn() ?: null;
    }
}
