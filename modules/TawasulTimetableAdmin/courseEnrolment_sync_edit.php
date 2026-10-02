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

use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_sync_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulYearGroupID = $_REQUEST['tawasulYearGroupID'] ?? '';
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? '';
    $pattern = $_POST['pattern'] ?? '';

    $page->breadcrumbs
        ->add(__('Sync Course Enrolment'), 'courseEnrolment_sync.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Map Classes'));

    if (empty($tawasulYearGroupID) || empty($tawasulSchoolYearID)) {
        $page->addError(__('Your request failed because your inputs were invalid.'));
        return;
    }

    $form = Form::createBlank('courseEnrolmentSyncEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/courseEnrolment_sync_addEditProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulYearGroupID', $tawasulYearGroupID);
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

    if (!empty($pattern)) {
        // Allows for Form Group naming patterns with different formats
        $subQuery = "(SELECT syncBy.tawasulFormGroupID FROM tawasulFormGroup AS syncBy WHERE REPLACE(REPLACE(REPLACE(REPLACE(:pattern, '[courseShortName]', tawasulCourse.nameShort), '[classShortName]', tawasulCourseClass.nameShort), '[yearGroupShortName]', tawasulYearGroup.nameShort), '[formGroupShortName]', nameShort) LIKE CONCAT('%', syncBy.nameShort) AND syncBy.tawasulSchoolYearID=:tawasulSchoolYearID LIMIT 1)";

        // Grab courses by year group, optionally match to a pattern
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'pattern' => $pattern);
        $sql = "SELECT tawasulCourse.name as courseName, tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.name as courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourseClass.nameShort as classShortName, tawasulYearGroup.nameShort as yearGroupShortName,
                $subQuery as syncTo
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=:tawasulYearGroupID)
                WHERE FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList)
                AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                GROUP BY tawasulCourseClass.tawasulCourseClassID
                ORDER BY tawasulCourse.nameShort, tawasulCourseClass.nameShort
                ";
        $result = $pdo->executeQuery($data, $sql);
    } else {
        // Grab courses by year group, pull in existing mapped classes
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulYearGroupID' => $tawasulYearGroupID);
        $sql = "SELECT tawasulCourse.name as courseName, tawasulCourseClassMap.tawasulFormGroupID as syncTo,  tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.name as courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourseClass.nameShort as classShortName, tawasulYearGroup.nameShort as yearGroupShortName
                FROM tawasulCourseClass
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList))
                LEFT JOIN tawasulCourseClassMap ON (tawasulCourseClassMap.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassMap.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                WHERE tawasulYearGroup.tawasulYearGroupID=:tawasulYearGroupID
                AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                GROUP BY tawasulCourseClass.tawasulCourseClassID
                ORDER BY tawasulCourse.name, tawasulCourseClass.nameShort
                ";
        $result = $pdo->executeQuery($data, $sql);
    }

    if ($result->rowCount() > 0) {
        $classesGroupedByCourse = $result->fetchAll(PDO::FETCH_GROUP);

        foreach ($classesGroupedByCourse as $courseName => $classes) {
            $course = current($classes);
            $optionsSelected = array_filter($classes, function ($item) {
                return !empty($item['syncTo']);
            });

            $form->addRow()->addHeading($courseName);
            $table = $form->addRow()->addTable()->setClass('smallIntBorder colorOddEven w-full standardForm');

            $header = $table->addHeaderRow();
                $header->addCheckbox('checkall'.$course['tawasulCourseID'])->checked(!empty($optionsSelected))->setClass();
                $header->addContent(__('Class'));
                $header->addContent('');
                $header->addContent(__('Form Group'));

            foreach ($classes as $class) {
                $row = $table->addRow();
                    $row->addCheckbox('syncEnabled['.$class['tawasulCourseClassID'].']')
                        ->checked(!empty($class['syncTo']))
                        ->setClass($course['tawasulCourseID'].' w-12');
                    $row->addLabel('syncEnabled['.$class['tawasulCourseClassID'].']', $class['courseNameShort'].'.'.$class['classShortName'])
                        ->setTitle($class['courseNameShort'])
                        ->setClass('w-36');
                    $row->addContent((empty($class['syncTo'])? '<em>'.__('No match found').'</em>' : '') )
                        ->setClass('w-1/3 text-right');
                    $row->addSelectFormGroup('syncTo['.$class['tawasulCourseClassID'].']', $tawasulSchoolYearID)
                        ->selected($class['syncTo'])
                        ->setClass('flex-1');
            }

            // Checkall by course
            echo '<script type="text/javascript">';
            echo '$(function () {';
                echo "$('#checkall".$course['tawasulCourseID']."').click(function () {";
                echo "$('.".$course['tawasulCourseID']."').find(':checkbox').attr('checked', this.checked);";
                echo '});';
            echo '});';
            echo '</script>';
        }
    }

    $table = $form->addRow()->addTable()->setClass('smallIntBorder colorOddEven w-full standardForm');

    $row = $table->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
