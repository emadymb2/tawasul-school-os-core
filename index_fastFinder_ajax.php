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

use TawasulOS\Http\Url;
use TawasulOS\Domain\System\ActionGateway;

require_once __DIR__ . '/tawasul.php';

if (!isset($_SESSION[$guid]) or !$session->exists('tawasulPersonID')) {
    die( __('Your request failed because you do not have access to this action.') );
} else {

    $searchTerm = $_REQUEST['search'] ?? '';
    $searchType = $_REQUEST['searchType'] ?? '';

    // Allow for * as wildcard (as well as %)
    $searchTermSafe = preg_replace('/([#-.]|[[-^]|[?|{}]|[\/])/', '\\\\$1', $searchTerm);
    $searchTerm = str_replace('*', '%', $searchTerm);

    // Cancel out early for empty searches
    if (empty($searchTerm) or strlen($searchTerm) < 2) die('<span class="block px-4 py-2 text-sm text-gray-800">'.__('Start typing a name...').'</span>');

    // Check access levels
    $studentIsAccessible = isActionAccessible($guid, $connection2, '/modules/students/student_view.php');
    $highestActionStudent = getHighestGroupedAction($guid, '/modules/students/student_view.php', $connection2);

    $departmentIsAccessible = isActionAccessible($guid, $connection2, '/modules/TawasulDepartments/department.php');
    $facilityIsAccessible = isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_space_view.php');
    $staffIsAccessible = isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_view.php');
    $classIsAccessible = false;
    $alarmIsAccessible = isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/alarm.php');
    $highestActionClass = getHighestGroupedAction($guid, '/modules/TawasulPlanner/planner.php', $connection2);
    if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner.php') and $highestActionClass != 'Lesson Planner_viewMyChildrensClasses') {
        $classIsAccessible = true;
    }

    $resultSet = array();
    $resultCount = 0;
    $resultError = '<span class="block px-4 py-2 text-sm text-gray-800">'.__('Your request failed due to a database error.').'</span>';

    // ACTIONS
    // Grab the cached set of translated actions from the session
    if (!$session->has('fastFinderActions')) {
        $actions = $container->get(ActionGateway::class)->getFastFinderActions($session->get('tawasulRoleIDCurrent'));
        $session->set('fastFinderActions', $actions);
    } else {
        $actions = $session->get('fastFinderActions');
    }
    
    if (($searchType == 'all' || $searchType == 'actions') && !empty($actions) && is_array($actions)) {
        foreach ($actions as $action) {
            // Add actions that match the search query to the result set
            if (stristr($action['name'], $searchTerm) !== false) {
                $resultSet['Action'][] = $action;
                $resultCount++;
            }

            // Handle the special Lockdown case
            if ($alarmIsAccessible) {
                if (stristr('Lockdown', $searchTerm) !== false && $action['name'] == 'Sound Alarm') {
                    $action['name'] = 'Lockdown';
                    $resultSet['Action'][] = $action;
                    $resultCount++;
                }
            }
        }
    }

    // DEPARTMENT
    if (($searchType == 'all' || $searchType == 'departments') && $departmentIsAccessible == true) {
        try {
            $data = array('search' => '%'.$searchTerm.'%');
            $sql = "SELECT tawasulDepartment.tawasulDepartmentID AS id,
                    tawasulDepartment.name AS name,
                    tawasulDepartment.type as type
                    FROM tawasulDepartment
                    WHERE tawasulDepartment.name LIKE :search 
                    ORDER BY name";
            $resultList = $pdo->select($sql, $data);
        } catch (PDOException $e) { die($resultError); }

        if ($resultList->rowCount() > 0) $resultSet['Department'] = $resultList->fetchAll();
        $resultCount += $resultList->rowCount();
    }
    
    // CLASSES
    if (($searchType == 'all' || $searchType == 'classes') && $classIsAccessible) {
        try {
            if ($highestActionClass == 'Lesson Planner_viewEditAllClasses' or $highestActionClass == 'Lesson Planner_viewAllEditMyClasses') {
                $data = array( 'search' => '%'.$searchTerm.'%', 'tawasulSchoolYearID2' => $session->get('tawasulSchoolYearID') );
                $sql = "SELECT tawasulCourseClass.tawasulCourseClassID AS id, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name, NULL as type
                        FROM tawasulCourseClass
                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                        WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID2
                        AND (tawasulCourse.name LIKE :search OR CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) LIKE :search)
                        ORDER BY name";
            } else {
                $data = array('search' => '%'.$searchTerm.'%', 'tawasulSchoolYearID3' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID') );
                $sql = "SELECT tawasulCourseClass.tawasulCourseClassID AS id, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name, NULL as type
                        FROM tawasulCourseClassPerson
                        JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                        WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID3
                        AND tawasulPersonID=:tawasulPersonID
                        AND (tawasulCourse.name LIKE :search OR CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) LIKE :search)
                        ORDER BY name";
            }
            $resultList = $pdo->select($sql, $data);
        } catch (PDOException $e) { die($resultError); }

        if ($resultList->rowCount() > 0) $resultSet['Class'] = $resultList->fetchAll();
        $resultCount += $resultList->rowCount();
    }

    // STAFF
    if (($searchType == 'all' || $searchType == 'staff') && $staffIsAccessible == true) {
        try {
            $data = array('search' => '%'.$searchTerm.'%', 'today' => date('Y-m-d') );
            $sql = "SELECT tawasulPerson.tawasulPersonID AS id,
                    (CASE WHEN tawasulPerson.username LIKE :search
                        THEN concat(surname, ', ', preferredName, ' (', tawasulPerson.username, ')')
                        ELSE concat(surname, ', ', preferredName) END) AS name,
                    NULL as type
                    FROM tawasulPerson
                    JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    WHERE status='Full'
                    AND (dateStart IS NULL OR dateStart<=:today)
                    AND (dateEnd IS NULL  OR dateEnd>=:today)
                    AND (tawasulPerson.surname LIKE :search
                        OR tawasulPerson.preferredName LIKE :search
                        OR tawasulPerson.nameInCharacters LIKE :search
                        OR tawasulPerson.username LIKE :search)
                    ORDER BY name";
            $resultList = $pdo->select($sql, $data);
        } catch (PDOException $e) { die($resultError); }

        if ($resultList->rowCount() > 0) $resultSet['Staff'] = $resultList->fetchAll();
        $resultCount += $resultList->rowCount();
    }

    // FACILITY
    if (($searchType == 'all' || $searchType == 'facilities') && $facilityIsAccessible == true) {
        try {
            $data = array('search' => '%'.$searchTerm.'%');
            $sql = "SELECT tawasulSpace.tawasulSpaceID AS id,
                    tawasulSpace.name AS name,
                    NULL as type
                    FROM tawasulSpace
                    WHERE tawasulSpace.name LIKE :search 
                    AND tawasulSpace.active='Y'
                    ORDER BY name";
            $resultList = $pdo->select($sql, $data);
        } catch (PDOException $e) { die($resultError); }

        if ($resultList->rowCount() > 0) $resultSet['Facility'] = $resultList->fetchAll();
        $resultCount += $resultList->rowCount();
    }

    // STUDENTS
    if (($searchType == 'all' || $searchType == 'students') && $studentIsAccessible == true) {

        $data = array('search' => '%'.$searchTerm.'%', 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'today' => date('Y-m-d') );

        // Allow parents to search students in any family they belong to
        if ($highestActionStudent == 'View Student Profile_myChildren') {
            $data['tawasulPersonID'] = $session->get('tawasulPersonID');
            $sql = "SELECT tawasulPerson.tawasulPersonID AS id,
                    (CASE WHEN tawasulPerson.username LIKE :search THEN concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ', ', tawasulPerson.username, ')')
                        WHEN tawasulPerson.studentID LIKE :search THEN concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ', ', tawasulPerson.studentID, ')')
                        WHEN tawasulPerson.firstName LIKE :search AND firstName<>preferredName THEN concat(surname, ', ', firstName, ' \"', preferredName, '\" (', tawasulFormGroup.name, ')' )
                        ELSE concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ')') END) AS name,
                    NULL as type 
                    FROM tawasulPerson, tawasulStudentEnrolment, tawasulFormGroup, tawasulFamilyChild, tawasulFamilyAdult
                    WHERE tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID
                    AND tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID 
                    AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID
                    AND tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID 
                    AND tawasulFamilyChild.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID";
        }
        // Allow individuals to only search themselves
        else if ($highestActionStudent == 'View Student Profile_my') {
            $data['tawasulPersonID'] = $session->get('tawasulPersonID');
            $sql = "SELECT tawasulPerson.tawasulPersonID AS id,
                    (CASE WHEN tawasulPerson.username LIKE :search THEN concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ', ', tawasulPerson.username, ')')
                        WHEN tawasulPerson.studentID LIKE :search THEN concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ', ', tawasulPerson.studentID, ')')
                        WHEN tawasulPerson.firstName LIKE :search AND firstName<>preferredName THEN concat(surname, ', ', firstName, ' \"', preferredName, '\" (', tawasulFormGroup.name, ')' )
                        ELSE concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ')') END) AS name,
                    NULL as type
                    FROM tawasulPerson, tawasulStudentEnrolment, tawasulFormGroup
                    WHERE tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID
                    AND tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID 
                    AND tawasulPerson.tawasulPersonID=:tawasulPersonID";
        }
        // Allow searching of all students
        else {
            $sql = "SELECT tawasulPerson.tawasulPersonID AS id,
                    (CASE WHEN tawasulPerson.username LIKE :search THEN concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ', ', tawasulPerson.username, ')')
                        WHEN tawasulPerson.studentID LIKE :search THEN concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ', ', tawasulPerson.studentID, ')')
                        WHEN tawasulPerson.firstName LIKE :search AND firstName<>preferredName THEN concat(surname, ', ', firstName, ' \"', preferredName, '\" (', tawasulFormGroup.name, ')' )
                        ELSE concat(surname, ', ', preferredName, ' (', tawasulFormGroup.name, ')') END) AS name,
                    NULL as type
                    FROM tawasulPerson, tawasulStudentEnrolment, tawasulFormGroup
                    WHERE tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID
                    AND tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID
                    AND status='Full'";
        }

        $sql.=" AND (dateStart IS NULL OR dateStart<=:today)
                AND (dateEnd IS NULL OR dateEnd>=:today)
                AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                AND (tawasulPerson.surname LIKE :search
                    OR tawasulPerson.firstName LIKE :search
                    OR tawasulPerson.preferredName LIKE :search
                    OR tawasulPerson.nameInCharacters LIKE :search
                    OR tawasulPerson.username LIKE :search
                    OR tawasulPerson.studentID LIKE :search
                    OR tawasulFormGroup.name LIKE :search)
                ORDER BY name";

        try {
            $resultList = $pdo->select($sql, $data);
        } catch (PDOException $e) { die($resultError); }

        if ($resultList->rowCount() > 0) $resultSet['Student'] = $resultList->fetchAll();
        $resultCount += $resultList->rowCount();
    }

    $output = '';
    $outputCount = 0;
    foreach ($resultSet as $type => $results) {
        foreach ($results as $token) {

            if ($outputCount > 30) {
                $output .= '<span class="block px-4 py-2 text-sm italic text-gray-800">'.__('+{n} More Results', ['n' => $resultCount - $outputCount]).'</span>';
                break 2;
            }

            if ($type == 'Student') {
                $URL = Url::fromModuleRoute('Students', 'student_view_details')->withQueryParam('tawasulPersonID', $token['id']);
            } elseif ($type == 'Action') {
                $URL = Url::fromModuleRoute(strstr($token['id'], '/', true), trim(strstr($token['id'], '/'), '/ '));
            } elseif ($type == 'Staff') {
                $URL = Url::fromModuleRoute('Staff', 'staff_view_details')->withQueryParam('tawasulPersonID', $token['id']);
            } elseif ($type == 'Class') {
                $URL = Url::fromModuleRoute('Departments', 'department_course_class')->withQueryParam('tawasulCourseClassID', $token['id']);
            } elseif ($type == 'Facility') {
                $URL = Url::fromModuleRoute('Timetable', 'tt_space_view')->withQueryParam('tawasulSpaceID', $token['id']);
            } elseif ($type == 'Department') {
                $URL = Url::fromModuleRoute('Departments', 'department')->withQueryParam('tawasulDepartmentID', $token['id']);
            }

            if ($token['type'] == 'Core') {
                $name = htmlPrep(__($token['name']));
            } else if ($token['type'] == 'Additional') {
                $name = htmlPrep(__($token['name'], $token['module']));
            } else {
                $name = htmlPrep($token['name']);
            }


            $name = preg_replace('/'.$searchTermSafe.'/i', '<strong>$0</strong>', $name);

            $output .= '<a @click="finderOpen = false" hx-boost="true" hx-target="#content-wrap" hx-select="#content-wrap" hx-swap="outerHTML show:no-scroll swap:0s" href="'.($URL ?? '').'" class="block cursor-pointer px-4 py-2 text-sm text-gray-800 hover:bg-indigo-500 hover:text-white" role="menuitem" tabindex="-1" id="menu-item-0">'.htmlPrep(__($type)).' - '.$name.'</a>';
            $outputCount++;
            
        }
    }

    if ($resultCount == 0 || empty($output)) {
        die('<span class="block px-4 py-2 text-sm text-gray-800">'.($searchType == 'all' ? __('No results') : __('No results in {type}', 
        ['type' => __(ucfirst($searchType)) ] ) ).'</span>');
    }

    echo $output;
}
