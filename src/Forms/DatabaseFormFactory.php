<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace TawasulOS\Forms;

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\FormFactory;
use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Services\Format;

/**
 * DatabaseFormFactory
 *
 * Handles Form object creation that are pre-loaded from SQL queries
 *
 * @version v14
 * @since   v14
 */
class DatabaseFormFactory extends FormFactory
{
    /**
     * Database connection.
     *
     * @var Connection
     */
    protected $pdo;

    /**
     * Cached query results.
     *
     * @var array
     */
    protected $cachedQueries = array();

    /**
     * Is the Collator class available through the intl library?
     *
     * @var bool
     */
    protected static $intlCollatorAvailable = false;

    /**
     * Create a factory with access to the provided a database connection.
     * @param  Connection  $pdo
     */
    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;

        static::$intlCollatorAvailable = class_exists('Collator');
    }

    /**
     * Create and return an instance of DatabaseFormFactory.
     * @return  object DatabaseFormFactory
     */
    public static function create(Connection $pdo = null)
    {
        return new DatabaseFormFactory($pdo);
    }

    public function createSelectSchoolYear($name, $status = 'All', $orderBy = 'ASC')
    {
        $orderBy = ($orderBy == 'ASC' || $orderBy == 'DESC') ? $orderBy : 'ASC';
        switch ($status) {
            case 'Active':
                $sql = "SELECT tawasulSchoolYearID as value, name FROM tawasulSchoolYear WHERE status='Current' OR status='Upcoming' ORDER BY sequenceNumber $orderBy"; break;

            case 'Upcoming':
                $sql = "SELECT tawasulSchoolYearID as value, name FROM tawasulSchoolYear WHERE status='Upcoming' ORDER BY sequenceNumber $orderBy"; break;

            case 'Past':
                $sql = "SELECT tawasulSchoolYearID as value, name FROM tawasulSchoolYear WHERE status='Past' ORDER BY sequenceNumber $orderBy"; break;

            case 'Recent':
                $sql = "SELECT tawasulSchoolYearID as value, name FROM tawasulSchoolYear WHERE status='Current' OR status='Past' ORDER BY sequenceNumber $orderBy"; break;

            case 'All':
            case 'Any':
            default:
                $sql = "SELECT tawasulSchoolYearID as value, name FROM tawasulSchoolYear ORDER BY sequenceNumber $orderBy"; break;
        }
        $results = $this->pdo->select($sql);

        return $this->createSearchSelect($name)->fromResults($results)->placeholder();
    }

    /*
    The optional $all function adds an option to the top of the select, using * to allow selection of all year groups
    */
    public function createSelectYearGroup($name, $all = false)
    {
        $sql = "SELECT tawasulYearGroupID as value, name FROM tawasulYearGroup ORDER BY sequenceNumber";
        $results = $this->pdo->select($sql);

        if (!$all)
            return $this->createSearchSelect($name)->fromResults($results)->placeholder();
        else
            return $this->createSearchSelect($name)->fromArray(array("*" => "All"))->fromResults($results)->placeholder();
    }

    /*
    The optional $all function adds an option to the top of the select, using * to allow selection of all form groups
    */
    public function createSelectFormGroup($name, $tawasulSchoolYearID, $all = false)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulFormGroupID as value, name FROM tawasulFormGroup WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY LENGTH(name), name";
        $results = $this->pdo->select($sql, $data);

        if (!$all)
            return $this->createSearchSelect($name)->fromResults($results)->placeholder();
        else
            return $this->createSearchSelect($name)->fromArray(array("*" => "All"))->fromResults($results)->placeholder();
    }

    public function createSelectHouse($name)
    {
        $sql = "SELECT tawasulHouseID as value, name FROM tawasulHouse ORDER BY name";
        $results = $this->pdo->select($sql)->fetchKeyPair();
        $results = $this->localeFriendlySort($results);

        return $this->createSelect($name)->fromArray($results)->placeholder();
    }

    public function createSelectCourseByYearGroup($name, $tawasulSchoolYearID, $tawasulYearGroupIDList = '')
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulYearGroupIDList' => $tawasulYearGroupIDList];
        $sql = "SELECT tawasulCourse.tawasulCourseID as value, CONCAT(tawasulCourse.nameShort, ' - ', tawasulCourse.name) as name
                FROM tawasulCourse
                JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList))
                WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                AND FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, :tawasulYearGroupIDList)
                GROUP BY tawasulCourse.tawasulCourseID
                ORDER BY tawasulCourse.nameShort";
        $results = $this->pdo->select($sql, $data);

        return $this->createSearchSelect($name)->fromResults($results)->placeholder();
    }

    /**
     * Branch scope for a picker query, or null when multi-branch is not active.
     *
     * The form factory is built with only a database connection, so the session
     * and container are reached as globals. Returns null — meaning "do not
     * filter" — whenever the platform globals are unavailable, which is the
     * case in unit tests and CLI contexts, and also when the Tawasul OS
     * Branches module is not installed.
     *
     * @param string $sql    Query, already carrying a WHERE clause.
     * @param string $alias  Table or alias owning tawasulBranchID.
     * @param array  $params Existing bind parameters, merged with the scope's.
     * @param string $suffix Unique bind-name suffix.
     * @return array{0:string,1:array} The query and its parameters.
     */
    private function applyBranchScope($sql, $alias, array $params, $suffix)
    {
        global $session, $container, $pdo;

        if (!isset($session, $container, $pdo)) {
            return [$sql, $params];
        }

        $scope = tosBranchScope($session, $container, $pdo, $alias, $suffix);

        if ($scope === null) {
            return [$sql, $params];
        }

        return [$sql.$scope['sql'], array_merge($params, $scope['params'])];
    }

    public function createSelectClass($name, $tawasulSchoolYearID, $tawasulPersonID = null, $params = array())
    {
        $params = array_replace(['allClasses' => true], $params);

        $classes = array();

        if (!empty($tawasulPersonID) && !empty($params['courseFilter'])) {
            $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'courseFilter' => '%'.$params['courseFilter'].'%'];
            $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS class
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID )
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulCourse.name LIKE :courseFilter
                AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID
                AND NOT tawasulCourseClassPerson.role LIKE '% - Left%'
                ORDER BY class";

            [$sql, $data] = $this->applyBranchScope($sql, 'tawasulCourseClass', $data, 'SelectClassCourse');
            $result = $this->pdo->select($sql, $data);
            if ($result->rowCount() > 0) {
                $classes[$params['courseFilter']] = $result->fetchAll(\PDO::FETCH_KEY_PAIR);
            }
        }

        if (!empty($tawasulPersonID) && !empty($params['departments'])) {
            $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulDepartmentIDList' => $params['departments']];
            $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS class
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID )
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID
                AND FIND_IN_SET(tawasulCourse.tawasulDepartmentID, :tawasulDepartmentIDList)
                AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID
                AND NOT tawasulCourseClassPerson.role LIKE '% - Left%'
                ORDER BY class";

            [$sql, $data] = $this->applyBranchScope($sql, 'tawasulCourseClass', $data, 'SelectClassDept');
            $result = $this->pdo->select($sql, $data);
            if ($result->rowCount() > 0) {
                $classes[__('Learning Area')] = $result->fetchAll(\PDO::FETCH_KEY_PAIR);
            }
        }

        if (!empty($tawasulPersonID)) {
            $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
            $sql = "SELECT tawasulCourseClass.tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name
                FROM tawasulCourseClassPerson
                JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulPersonID=:tawasulPersonID
                AND NOT tawasulCourseClassPerson.role LIKE '% - Left%'";
            if (isset($params['attendance'])) {
                $data['attendance'] = $params['attendance'];
                $sql .= " AND tawasulCourseClass.attendance=:attendance";
            }
            if (isset($params['reportable'])) {
                $data['reportable'] = $params['reportable'];
                $sql .= " AND tawasulCourseClass.reportable=:reportable";
            }
            [$sql, $data] = $this->applyBranchScope($sql, 'tawasulCourseClass', $data, 'SelectClassMine');
            $sql .= " ORDER BY name";
            $result = $this->pdo->select($sql, $data);
            if ($result->rowCount() > 0) {
                $classes[__('My Classes')] = $result->fetchAll(\PDO::FETCH_KEY_PAIR);
            }
        }

        if ($params['allClasses']) {
            $data=['tawasulSchoolYearID'=>$tawasulSchoolYearID];
            $sql= "SELECT tawasulCourseClass.tawasulCourseClassID AS value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID";
            if (isset($params['attendance'])) {
                $data['attendance'] = $params['attendance'];
                $sql .= " AND tawasulCourseClass.attendance=:attendance";
            }
            if (isset($params['reportable'])) {
                $data['reportable'] = $params['reportable'];
                $sql .= " AND tawasulCourseClass.reportable=:reportable";
            }
            [$sql, $data] = $this->applyBranchScope($sql, 'tawasulCourseClass', $data, 'SelectClassAll');
            $sql .= " ORDER BY name";
            $result = $this->pdo->select($sql, $data);

            if ($result->rowCount() > 0) {
                if (!empty($tawasulPersonID)) {
                    $classes[__('All Classes')] = $result->fetchAll(\PDO::FETCH_KEY_PAIR);
                } else {
                    $classes = $result->fetchAll(\PDO::FETCH_KEY_PAIR);
                }
            }
        }

        return $this->createSearchSelect($name)->fromArray($classes)->placeholder();
    }

    public function createCheckboxYearGroup($name)
    {
        $sql = "SELECT tawasulYearGroupID as `value`, name FROM tawasulYearGroup ORDER BY sequenceNumber";
        $results = $this->pdo->select($sql);

        // Get the yearGroups in a $key => $value array
        $yearGroups = ($results && $results->rowCount() > 0)? $results->fetchAll(\PDO::FETCH_KEY_PAIR) : array();

        return $this->createCheckbox($name)->fromArray($yearGroups);
    }

    public function createCheckboxSchoolYearTerm($name, $tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulSchoolYearTermID as `value`, name FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY sequenceNumber";
        $results = $this->pdo->select($sql, $data);

        // Get the terms in a $key => $value array
        $terms = ($results && $results->rowCount() > 0)? $results->fetchAll(\PDO::FETCH_KEY_PAIR) : array();

        return $this->createCheckbox($name)->fromArray($terms);
    }

    public function createSelectDepartment($name)
    {
        $sql = "SELECT type, tawasulDepartmentID as value, name FROM tawasulDepartment ORDER BY name";
        $results = $this->pdo->select($sql);

        $departments = array();

        if ($results && $results->rowCount() > 0) {
            while ($row = $results->fetch()) {
                $departments[$row['type']][$row['value']] = $row['name'];
            }
        }

        return $this->createSearchSelect($name)->fromArray($departments)->placeholder();
    }

    public function createSelectSchoolYearTerm($name, $tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulSchoolYearTermID as `value`, name FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY sequenceNumber";
        $results = $this->pdo->select($sql, $data);

        return $this->createSelect($name)->fromResults($results)->placeholder();
    }

    public function createSelectTheme($name)
    {
        $sql = "SELECT tawasulThemeID as value, (CASE WHEN active='Y' THEN CONCAT(name, ' (', '".__('System Default')."', ')') ELSE name END) AS name FROM tawasulTheme ORDER BY name";
        $results = $this->pdo->select($sql);

        return $this->createSelect($name)->fromResults($results)->placeholder();
    }

    public function createSelectI18n($name)
    {
        $sql = "SELECT * FROM tawasuli18n WHERE active='Y' ORDER BY code";
        $results = $this->pdo->select($sql);

        $values = array_reduce($results->fetchAll(), function ($group, $item) {
            if (isset($item['installed']) && $item['installed'] == 'Y') {
                $group[$item['tawasuli18nID']] = $item['systemDefault'] == 'Y'? $item['name'].' ('.__('System Default').')' : $item['name'];
            }
            return $group;
        }, []);

        return $this->createSearchSelect($name)->fromArray($values)->placeholder();
    }

    public function createSelectLanguage($name)
    {
        $sql = "SELECT name as value, name FROM tawasulLanguage ORDER BY name";
        $results = $this->pdo->select($sql)->fetchKeyPair();
        $results = $this->localeFriendlySort($results);

        return $this->createSearchSelect($name)->fromArray($results)->placeholder();
    }

    public function createSelectCountry($name)
    {
        $sql = "SELECT printable_name as value, printable_name as name FROM tawasulCountry ORDER BY printable_name";
        $results = $this->pdo->select($sql)->fetchKeyPair();
        $results = $this->localeFriendlySort($results);

        return $this->createSearchSelect($name)->fromArray($results)->placeholder();
    }

    public function createSelectRole($name)
    {
        $sql = "SELECT tawasulRoleID as value, name FROM tawasulRole ORDER BY name";
        $results = $this->pdo->select($sql);

        return $this->createSearchSelect($name)->fromResults($results)->placeholder();
    }

    public function createSelectStatus($name)
    {
        global $container;

        $statuses = array(
            'Full'     => __('Full'),
            'Expected' => __('Expected'),
            'Left'     => __('Left'),
        );

        if ($container->get(SettingGateway::class)->getSettingByScope('User Admin', 'enablePublicRegistration') == 'Y') {
            $statuses['Pending Approval'] = __('Pending Approval');
        }

        return $this->createSelect($name)->fromArray($statuses);
    }

    public function createSelectBehaviourType($name)
    {
        $sql = "SELECT name, value FROM tawasulSetting WHERE scope='Behaviour' AND name IN ('enableNegativeBehaviour', 'enablePositiveBehaviour', 'enableObservationBehaviour')";
        $settings = $this->pdo->select($sql)->fetchKeyPair();

        $types = [];
        if (($settings['enableNegativeBehaviour'] ?? '') == 'Y') {
            $types['Negative'] = __('Negative');
        }
        if (($settings['enablePositiveBehaviour'] ?? '') == 'Y') {
            $types['Positive'] = __('Positive');
        }
        if (($settings['enableObservationBehaviour'] ?? '') == 'Y') {
            $types['Observation'] = __('Observation');
        }

        return $this->createSelect($name)->fromArray($types);
    }

    public function createSelectStaff($name)
    {
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, username
                FROM tawasulPerson JOIN tawasulStaff ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID)
                WHERE status='Full'";

        [$sql, $staffParams] = $this->applyBranchScope($sql, 'tawasulStaff', [], 'SelectStaff');
        $sql .= " ORDER BY surname, preferredName";

        $staff = $this->pdo->select($sql, $staffParams)->fetchGroupedUnique();

        $staff = array_map(function ($person) {
            return Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff', true, true)." (".$person['username'].")";
        }, $staff);

        return $this->createSelectPerson($name)->fromArray($staff);
    }

    public function createSelectUsersFromList($name, $people = [])
    {
        $data = ['tawasulPersonIDList' => implode(',', $people)];
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, username
                FROM tawasulPerson
                WHERE (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
                AND FIND_IN_SET(tawasulPersonID, :tawasulPersonIDList)
                ORDER BY FIND_IN_SET(tawasulPersonID, :tawasulPersonIDList), surname, preferredName";

        $people = $this->pdo->select($sql, $data)->fetchGroupedUnique();

        $people = array_map(function ($person) {
            return Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff', true, true)." (".$person['username'].")";
        }, $people);

        return $this->createSelectPerson($name)->fromArray($people);
    }

    public function createSelectUsers($name, $tawasulSchoolYearID = false, $params = [])
    {
        $params = array_replace(['includeStudents' => false, 'includeStaff' => false, 'useMultiSelect' => false, 'includeAllUsers' => true], $params);

        $users = [];
        $data = [];

        

        if ($params['includeStudents'] == true) {
            $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username, tawasulFormGroup.name AS formGroupName
                    FROM tawasulPerson
                    JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                    JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                     ";

            if (!empty($tawasulSchoolYearID)) {
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'date' => date('Y-m-d')];
                $sql .= "WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
                        AND (dateStart IS NULL OR dateStart<=:date)
                        AND (dateEnd IS NULL OR dateEnd>=:date)";
            }

            $sql .= " ORDER BY formGroupName, tawasulPerson.surname, tawasulPerson.preferredName";

            $result = $this->pdo->select($sql, $data);

            if ($result->rowCount() > 0) {
                $users[__('Enrolable Students')] = array_reduce($result->fetchAll(), function($group, $item) {
                    $group[$item['tawasulPersonID']] = $item['formGroupName'].' - '.Format::name('', $item['preferredName'], $item['surname'], 'Student', true). " (".$item['username'].")";
                    return $group;
                }, array());
            }
        }

        if ($params['includeStaff'] == true) {
            $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username
                    FROM tawasulPerson
                    JOIN tawasulStaff ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID) ";

            if (!empty($tawasulSchoolYearID)) {
                $sql .= " WHERE (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')";
            }
            $sql .= " ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";

            $result = $this->pdo->select($sql);
            if ($result->rowCount() > 0) {
                $users[__('Staff')] = array_reduce($result->fetchAll(), function ($group, $item) {
                    $group[$item['tawasulPersonID']] = Format::name('', htmlPrep($item['preferredName']), htmlPrep($item['surname']), 'Staff', true, true)." (".$item['username'].")";
                    return $group;
                }, array());
            }
        }

        if($params['includeAllUsers'] == true) {
            $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, username, tawasulRole.category
                    FROM tawasulPerson
                    JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary) ";

            if (!empty($tawasulSchoolYearID)) {
                $sql .= " WHERE (status='Full' OR status='Expected') ";
            }

            $sql .= " ORDER BY surname, preferredName";

            $result = $this->pdo->select($sql);

            if ($result->rowCount() > 0) {
                $users[__('All Users')] = array_reduce($result->fetchAll(), function ($group, $item) {
                    $group[$item['tawasulPersonID']] = Format::name('', $item['preferredName'], $item['surname'], 'Student', true).' ('.$item['username'].', '.__($item['category']).')';
                    return $group;
                }, array());
            }
        }

        if ($params['useMultiSelect']) {
            $multiSelect = $this->createMultiSelect($name);
            $multiSelect->source()->fromArray($users);

            return $multiSelect;
        } else {
            return $this->createSelectPerson($name)->fromArray($users);
        }
    }

    /*
    $params is an array, with the following options as keys:
        allStudents - false by default. true displays students regardless of status and start/end date
        byName - true by default. Adds students organised by name
        byForm - false by default. Adds students organised by form group. Can be used in conjunction with byName to have multiple sections
        showForm - true by default. Displays form group beside student's name, when organised byName. Incompatible with allStudents
    */
    public function createSelectStudent($name, $tawasulSchoolYearID, $params = [])
    {
        //Create arrays for use later on
        $values = [];
        $data = [];

        // Check params and set defaults if not defined
        $params = array_replace(['allStudents' => false, 'activeStudents' => false, 'byName' => true, 'byForm' => false, 'showForm' => true], $params);

        //Check for multiple by methods, so we know when to apply optgroups
        $multipleBys = false;
        if ($params["byName"] && $params["byForm"]) {
            $multipleBys = true;
        }

        //Add students by form group
        if ($params["byForm"]) {
            if ($params["allStudents"]) {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
                $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username, tawasulFormGroup.name AS name, tawasulStudentEnrolment.tawasulYearGroupID
                    FROM tawasulPerson
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    WHERE tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                    ORDER BY name, surname, preferredName";
            } elseif ($params["activeStudents"]) {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
                $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username, tawasulFormGroup.name AS name, tawasulStudentEnrolment.tawasulYearGroupID
                    FROM tawasulPerson
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    WHERE tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                    AND (tawasulPerson.status='Full' || tawasulPerson.status='Expected')
                    ORDER BY name, surname, preferredName";
            } else {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'date' => date('Y-m-d'));
                $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username, tawasulFormGroup.name AS name, tawasulStudentEnrolment.tawasulYearGroupID
                    FROM tawasulPerson
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    WHERE status='Full'
                        AND (dateStart IS NULL OR dateStart<=:date)
                        AND (dateEnd IS NULL  OR dateEnd>=:date)
                        AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                    ORDER BY name, surname, preferredName";
            }

            $results = $this->pdo->select($sql, $data);

            if ($results && $results->rowCount() > 0) {
                while ($row = $results->fetch()) {
                    if (!empty($params['tawasulYearGroupID']) && $row['tawasulYearGroupID'] != $params['tawasulYearGroupID']) continue;

                    if ($multipleBys) {
                        $values[__('Students by Form Group')][$row['tawasulPersonID']] = htmlPrep($row['name']).' - '.Format::name('', htmlPrep($row['preferredName']), htmlPrep($row['surname']), 'Student', true)." (".$row['username'].")";
                    } else {
                        $values[$row['tawasulPersonID']] = htmlPrep($row['name']).' - '.Format::name('', htmlPrep($row['preferredName']), htmlPrep($row['surname']), 'Student', true)." (".$row['username'].")";
                    }
                }
            }
        }

        //Clear all values
        $data = [];

        //Add students by name
        if ($params["byName"]) {
            if ($params["allStudents"]) {
                $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, username, null AS name
                    FROM tawasulPerson
                        JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                    WHERE tawasulRole.category='Student'
                    ORDER BY surname, preferredName";
            } elseif ($params["activeStudents"]) {
                $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, username, null AS name
                    FROM tawasulPerson
                        JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                    WHERE tawasulRole.category='Student'
                    AND (tawasulPerson.status='Full' || tawasulPerson.status='Expected')
                    ORDER BY surname, preferredName";
            } else {
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'date' => date('Y-m-d'));
                $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, username, tawasulFormGroup.name AS name
                    FROM tawasulPerson
                        JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                        JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    WHERE status='Full'
                        AND (dateStart IS NULL OR dateStart<=:date)
                        AND (dateEnd IS NULL  OR dateEnd>=:date)
                        AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                    ORDER BY surname, preferredName";
            }

            $results = $this->pdo->select($sql, $data);

            if ($results && $results->rowCount() > 0) {
                while ($row = $results->fetch()) {
                    if ($multipleBys) {
                        if (!$params['allStudents'] && $params['byName'] && $params['showForm']) {
                            $values[__('Students by Name')][$row['tawasulPersonID']] = Format::name(htmlPrep($row['title']), ($row['preferredName']), htmlPrep($row['surname']), 'Student', true, true).' ('.$row['name'].', '.$row['username'].')';
                        }
                        else {
                            $values[__('Students by Name')][$row['tawasulPersonID']] = Format::name(htmlPrep($row['title']), ($row['preferredName']), htmlPrep($row['surname']), 'Student', true, true).' ('.$row['username'].')';
                        }
                    } else {
                        if (!$params['allStudents'] && $params['byName'] && $params['showForm']) {
                            $values[$row['tawasulPersonID']] = Format::name(htmlPrep($row['title']), ($row['preferredName']), htmlPrep($row['surname']), 'Student', true, true).' ('.$row['name'].', '.$row['username'].')';
                        }
                        else {
                            $values[$row['tawasulPersonID']] = Format::name(htmlPrep($row['title']), ($row['preferredName']), htmlPrep($row['surname']), 'Student', true, true).' ('.$row['username'].')';
                        }
                    }
                }
            }
        }

        return $this->createSelectPerson($name)->fromArray($values);
    }

    public function createSelectGradeScale($name)
    {
        $sql = "SELECT tawasulScaleID as value, name FROM tawasulScale WHERE (active='Y') ORDER BY name";

        return $this->createSearchSelect($name)->fromQuery($this->pdo, $sql)->placeholder();
    }

    public function createSelectGradeScaleGrade($name, $tawasulScaleID, $params = array())
    {
        // Check params and set defaults if not defined
        $params = array_replace(array(
            'honourDefault' => true,
            'valueMode' => 'value',
            'labelMode' => 'value',
        ), $params);

        $data = array('tawasulScaleID' => $tawasulScaleID);
        $sql = "SELECT tawasulScaleGradeID, value, descriptor, isDefault FROM tawasulScaleGrade WHERE tawasulScaleID=:tawasulScaleID ORDER BY sequenceNumber";
        $results = $this->pdo->select($sql, $data);

        $grades = ($results->rowCount() > 0)? $results->fetchAll() : array();
        $default = '';

        $gradeOptions = array_reduce($grades, function ($group, $item) use ($params, &$default) {
            $identifier = $params['valueMode'] == 'id' ? 'tawasulScaleGradeID' : 'value';
            $value = $params['labelMode'] == 'descriptor' ? $item['descriptor'] : $item['value'];

            if ($item['isDefault'] == 'Y') {
                $default = $value;
            }

            if ($params['labelMode'] == 'both') {
                $value = $item['value'] == $item['descriptor'] ? $item['value'] : $item['value'].' | '.$item['descriptor'];
            }

            $group[$item[$identifier]] = $value;
            return $group;
        }, []);

        $selected = ($params['honourDefault'] && !empty($default))? $default: '';

        return $this->createSelect($name)->fromArray($gradeOptions)->selected($selected)->placeholder()->addClass('gradeSelect w-auto');
    }

    public function createSelectRubric($name, $tawasulYearGroupIDList = '', $tawasulDepartmentID = '')
    {
        $data = array('tawasulYearGroupIDList' => $tawasulYearGroupIDList, 'tawasulDepartmentID' => $tawasulDepartmentID, 'rubrics' => __('Rubrics'));
        $sql = "SELECT CONCAT(scope, ' ', :rubrics) as groupBy, tawasulRubricID as value,
                (CASE WHEN category <> '' THEN CONCAT(category, ' - ', tawasulRubric.name) ELSE tawasulRubric.name END) as name
                FROM tawasulRubric
                JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulRubric.tawasulYearGroupIDList))
                WHERE tawasulRubric.active='Y'
                AND FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, :tawasulYearGroupIDList)
                AND (scope='School' OR (scope='Learning Area' AND tawasulDepartmentID=:tawasulDepartmentID))
                GROUP BY tawasulRubric.tawasulRubricID
                ORDER BY scope, category, name";

        return $this->createSearchSelect($name)->fromQuery($this->pdo, $sql, $data, 'groupBy')->placeholder();
    }

    public function createSelectReportingCycle($name)
    {
        $sql = "SELECT tawasulSchoolYear.name as schoolYear, tawasulReportingCycleID as value, tawasulReportingCycle.name FROM tawasulReportingCycle JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID) ORDER BY tawasulSchoolYear.sequenceNumber DESC, tawasulReportingCycle.sequenceNumber";

        return $this->createSearchSelect($name)->fromQuery($this->pdo, $sql, [], 'schoolYear')->placeholder();
    }

    public function createPhoneNumber($name)
    {
        $countryCodes = $this->getCachedQuery('phoneNumber');

        if (empty($countryCodes)) {
            $sql = "SELECT iddCountryCode, printable_name FROM tawasulCountry ORDER BY (SELECT value FROM tawasulSetting WHERE scope='System' AND name='country' LIMIT 1)=printable_name DESC, iddCountryCode, printable_name";
            $results = $this->pdo->select($sql);
            if ($results && $results->rowCount() > 0) {
                $countryCodes = $results->fetchAll();

                // Transform the row data into value => name pairs
                $countryCodes = array_reduce($countryCodes, function($codes, $item) {
                    if (!empty($item['iddCountryCode'])) {
                        if (array_key_exists($item['iddCountryCode'], $codes)) {
                            $codes[$item['iddCountryCode']] = $codes[$item['iddCountryCode']].', '.__($item['printable_name']);
                        }
                        else {
                            $codes[$item['iddCountryCode']] = $item['iddCountryCode'].' - '.__($item['printable_name']);
                        }
                    }
                    return $codes;

                }, array());
            }
            $this->setCachedQuery('phoneNumber', $countryCodes);
        }

        return new Input\PhoneNumber($this, $name, $countryCodes);
    }

    public function createSequenceNumber($name, $tableName, $sequenceNumber = '', $columnName = null)
    {
        $columnName = empty($columnName)? $name : $columnName;

        $data = array('sequenceNumber' => $sequenceNumber);
        $sql = "SELECT GROUP_CONCAT(DISTINCT `{$columnName}` SEPARATOR '\',\'') FROM `{$tableName}` WHERE (`{$columnName}` IS NOT NULL AND `{$columnName}` <> :sequenceNumber) ORDER BY `{$columnName}`";
        $results = $this->pdo->select($sql, $data);

        $field = $this->createNumber($name)->minimum(1)->onlyInteger(true);

        if ($results && $results->rowCount() > 0) {
            $field->addValidation('Validate.Exclusion', 'within: [\''.$results->fetchColumn(0).'\'], failureMessage: "'.__('Value already in use!').'", partialMatch: false, caseSensitive: false');
        }

        if (!empty($sequenceNumber) || $sequenceNumber === false) {
            $field->setValue($sequenceNumber);
        } else {
            $sql = "SELECT MAX(`{$columnName}`) FROM `{$tableName}`";
            $results = $this->pdo->select($sql);
            $sequenceNumber = ($results && $results->rowCount() > 0)? $results->fetchColumn(0) : 1;

            $field->setValue($sequenceNumber+1);
        }

        return $field;
    }

    /*
    The optional $all function adds an option to the top of the select, using * to allow selection of all year groups
    */
    public function createSelectTransport($name, $all = false)
    {
        $sql = "SELECT DISTINCT transport AS value, transport AS name FROM tawasulPerson WHERE status='Full' AND NOT transport='' ORDER BY transport";
        $results = $this->pdo->select($sql);

        if (!$all)
            return $this->createSearchSelect($name)->fromResults($results)->placeholder();
        else
            return $this->createSearchSelect($name)->fromArray(array("*" => "All"))->fromResults($results)->placeholder();
    }

    public function createSelectSpace($name, $params = [])
    {
        $params = array_replace(array(
            'byType' => true,
        ), $params);

        if ($params['byType'] == true) {
            $sql = "SELECT tawasulSpaceID as value, name, type as groupBy FROM tawasulSpace ORDER BY type, name";
            $results = $this->pdo->select($sql);
            return $this->createSearchSelect($name)->fromResults($results, 'groupBy')->placeholder();

        } else {
            $sql = "SELECT tawasulSpaceID as value, name FROM tawasulSpace ORDER BY name";
            $results = $this->pdo->select($sql);
            return $this->createSearchSelect($name)->fromResults($results)->placeholder();
        }
    }

    public function createTextFieldDistrict($name)
    {
        $sql = "SELECT DISTINCT name FROM tawasulDistrict ORDER BY name";
        $result = $this->pdo->select($sql);
        $districts = ($result && $result->rowCount() > 0)? $result->fetchAll(\PDO::FETCH_COLUMN) : array();

        return $this->createTextField($name)->maxLength(30)->autocomplete($districts);
    }

    public function createSelectAlert($name)
    {
        $sql = 'SELECT tawasulAlertLevelID AS value, name FROM tawasulAlertLevel ORDER BY sequenceNumber';
        $results = $this->pdo->select($sql);

        return $this->createSelect($name)->fromResults($results)->placeholder();
    }

    protected function getCachedQuery($name)
    {
        return (isset($this->cachedQueries[$name]))? $this->cachedQueries[$name] : array();
    }

    protected function setCachedQuery($name, $results)
    {
        $this->cachedQueries[$name] = $results;
    }

    protected function localeFriendlySort($values)
    {
        $values = array_map('__', $values);
    
        if (static::$intlCollatorAvailable) {
            $locale = \Locale::getDefault();
            $collator = new \Collator($locale);
            $collator->asort($values);
        } else {
            uasort($values, 'strcoll');
        }

        return $values;
    }
}
