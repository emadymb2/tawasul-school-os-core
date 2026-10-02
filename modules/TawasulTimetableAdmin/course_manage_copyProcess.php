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
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulSchoolYearIDNext = $_GET['tawasulSchoolYearIDNext'] ?? '';
$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulTimetableAdmin/course_manage.php&tawasulSchoolYearID=$tawasulSchoolYearIDNext";

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    // Check if school years specified (current and next)
    if (empty($tawasulSchoolYearID) || empty($tawasulSchoolYearIDNext)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $courseGateway = $container->get(CourseGateway::class);
    $courseClassGateway = $container->get(CourseClassGateway::class);

    // Get current courses
    $courses = $courseGateway->selectBy(['tawasulSchoolYearID' => $tawasulSchoolYearID])->fetchAll();
    if (empty($courses)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;

    foreach ($courses as $course) {
        $data = [
            'tawasulSchoolYearID'    => $tawasulSchoolYearIDNext,
            'tawasulDepartmentID'    => $course['tawasulDepartmentID'],
            'name'                  => $course['name'],
            'nameShort'             => $course['nameShort'],
            'description'           => $course['description'],
            'tawasulYearGroupIDList' => $course['tawasulYearGroupIDList'],
            'orderBy'               => $course['orderBy'],
            'map'                   => $course['map'],
            'fields'                => $course['fields'],
        ];

        // Skip courses that already exist
        if (!$courseGateway->unique($data, ['tawasulSchoolYearID', 'nameShort'])) {
            continue;
        }

        // Insert course into database
        $tawasulCourseIDNew = $courseGateway->insert($data);
            
        if (empty($tawasulCourseIDNew)) {
            $partialFail = true;
            continue;
        }
        
        $classes = $courseClassGateway->selectBy(['tawasulCourseID' => $course['tawasulCourseID']])->fetchAll();

        foreach ($classes as $class) {
            $data = [
                'tawasulCourseID'      => $tawasulCourseIDNew,
                'name'                => $class['name'],
                'nameShort'           => $class['nameShort'],
                'reportable'          => $class['reportable'],
                'attendance'          => $class['attendance'],
                'enrolmentMin'        => $class['enrolmentMin'],
                'enrolmentMax'        => $class['enrolmentMax'],
                'tawasulScaleIDTarget' => $class['tawasulScaleIDTarget'],
                'fields'              => $class['fields'],
            ];

            // Skip classes that already exist
            if (!$courseClassGateway->unique($data, ['tawasulCourseID', 'nameShort'])) {
                continue;
            }

            // Insert class into database
            $tawasulCourseClassIDNew = $courseClassGateway->insert($data);

            if (empty($tawasulCourseClassIDNew)) {
                $partialFail = true;
            }
        }
    }

    $URL .= $partialFail == true
        ? '&return=error5'
        : '&return=success0';

    header("Location: {$URL}");
}
