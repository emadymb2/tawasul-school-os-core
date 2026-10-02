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

use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_sync_run.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Allows for a single value or a csv list of tawasulYearGroupID
    $tawasulYearGroupIDList = $_GET['tawasulYearGroupIDList'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';

    $page->breadcrumbs
        ->add(__('Sync Course Enrolment'), 'courseEnrolment_sync.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Sync Now'));

    if (empty($tawasulYearGroupIDList) || empty($tawasulSchoolYearID)) {
        $page->addError(__('Your request failed because your inputs were invalid.'));
        return;
    }

    if ($tawasulYearGroupIDList == 'all') {
        // All class mappings
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulCourseClassMap.*, tawasulYearGroup.name as tawasulYearGroupName
                FROM tawasulCourseClassMap
                JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulCourseClassMap.tawasulFormGroupID)
                JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulCourseClassMap.tawasulYearGroupID)
                WHERE tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                GROUP BY tawasulCourseClassMap.tawasulYearGroupID";
    } else {
        // Pull up the class mapping for this year group
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulYearGroupID' => $tawasulYearGroupIDList);
        $sql = "SELECT tawasulCourseClassMap.*, tawasulYearGroup.name as tawasulYearGroupName
                FROM tawasulCourseClassMap
                JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulCourseClassMap.tawasulFormGroupID)
                JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulCourseClassMap.tawasulYearGroupID)
                WHERE FIND_IN_SET(tawasulCourseClassMap.tawasulYearGroupID, :tawasulYearGroupID)
                AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                GROUP BY tawasulCourseClassMap.tawasulYearGroupID";
    }

    $result = $pdo->executeQuery($data, $sql);

    if ($result->rowCount() == 0) {
        $page->addError(__('Your request failed because your inputs were invalid.'));
        return;
    }

    $form = Form::createBlank('courseEnrolmentSyncRun', $session->get('absoluteURL').'/modules/'.$session->get('module').'/courseEnrolment_sync_runProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulYearGroupIDList', $tawasulYearGroupIDList);
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

    // Checkall options
    $row = $form->addRow()->addContent('<h4>'.__('Options').'</h4>');
    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');

    $row = $table->addRow();
        $row->addLabel('includeStudents', __('Include Students'));
        $row->addCheckbox('includeStudents')->checked(true);
    $row = $table->addRow();
        $row->addLabel('includeTeachers', __('Include Teachers'));
        $row->addCheckbox('includeTeachers')->checked(true);

    $enrolableCount = 0;

    while ($classMap = $result->fetch()) {
        $form->addRow()->addHeading($classMap['tawasulYearGroupName']);

        $data = array(
            'tawasulSchoolYearID' => $tawasulSchoolYearID,
            'tawasulYearGroupID' => $classMap['tawasulYearGroupID'],
            'date' => date('Y-m-d'),
        );

        // Grab mapped classes for all teachers & students grouped by year group, excluding those already enrolled
        $sql = "(SELECT tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.name as tawasulFormGroupName, GROUP_CONCAT(CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) ORDER BY tawasulCourse.nameShort, tawasulCourseClass.nameShort SEPARATOR ', ') AS courseList, 'Teacher' as role
                FROM tawasulCourseClassMap
                JOIN tawasulFormGroup ON (tawasulCourseClassMap.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                JOIN tawasulPerson ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID || tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID || tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID AND tawasulCourseClassPerson.role = 'Teacher')
                WHERE tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulCourseClassMap.tawasulYearGroupID=:tawasulYearGroupID
                AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:date OR tawasulPerson.status='Expected')
                AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:date)
                AND tawasulCourseClassPerson.tawasulCourseClassPersonID IS NULL
                GROUP BY tawasulPerson.tawasulPersonID
            ) UNION ALL (
                SELECT tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.name as tawasulFormGroupName, GROUP_CONCAT(CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) ORDER BY tawasulCourse.nameShort, tawasulCourseClass.nameShort SEPARATOR ', ') AS courseList, 'Student' as role
                FROM tawasulCourseClassMap
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulCourseClassMap.tawasulYearGroupID AND tawasulStudentEnrolment.tawasulFormGroupID=tawasulCourseClassMap.tawasulFormGroupID)
                JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulFormGroup ON (tawasulCourseClassMap.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID  AND tawasulCourseClassPerson.role = 'Student')
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulCourseClassMap.tawasulYearGroupID=:tawasulYearGroupID
                AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:date OR tawasulPerson.status='Expected')
                AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:date)
                AND tawasulCourseClassPerson.tawasulCourseClassPersonID IS NULL
                GROUP BY tawasulPerson.tawasulPersonID
            ) ORDER BY role DESC, surname, preferredName";

        $enrolmentResult = $pdo->executeQuery($data, $sql);

        if ($enrolmentResult->rowCount() == 0) {
            $form->addRow()->addAlert(__('Course enrolments are already synced. No changes will be made.'), 'success');
        } else {
            $enrolableCount += $enrolmentResult->rowCount();

            $table = $form->addRow()->addTable()->setClass('smallIntBorder colorOddEven w-full standardForm');
            $header = $table->addHeaderRow();
                $header->addCheckbox('checkall'.$classMap['tawasulYearGroupID'])->checked(true);
                $header->addContent(__('Name'));
                $header->addContent(__('Role'));
                $header->addContent(__('Form Group'));
                $header->addContent(__('Enrolment by Class'));

            while ($person = $enrolmentResult->fetch()) {
                $row = $table->addRow();
                    $row->addCheckbox('syncData['.$person['tawasulFormGroupID'].']['.$person['tawasulPersonID'].']')
                        ->setValue($person['role'])
                        ->checked($person['role'])
                        ->setClass($classMap['tawasulYearGroupID'])
                        ->addClass(strtolower($person['role']))
                        ->description('&nbsp;&nbsp;');
                    $row->addLabel('syncData['.$person['tawasulFormGroupID'].']['.$person['tawasulPersonID'].']', Format::name('', $person['preferredName'], $person['surname'], 'Student', true))->addClass('mediumWidth');
                    $row->addContent($person['role']);
                    $row->addContent($person['tawasulFormGroupName']);
                    $row->addContent($person['courseList']);
            }

            // Checkall by Year Group
            echo '<script type="text/javascript">';
            echo '$(function () {';
                echo "$('#checkall".$classMap['tawasulYearGroupID']."').click(function () {";
                echo "$('.".$classMap['tawasulYearGroupID']."').find(':checkbox').attr('checked', this.checked);";
                echo '});';
            echo '});';
            echo '</script>';
        }
    }

    // Only display a submit button if a sync is required
    if ($enrolableCount > 0) {
        $table = $form->addRow()->addTable()->setClass('smallIntBorder colorOddEven w-full standardForm');
        $table->addRow()->addSubmit(__('Proceed'));
    }

    echo $form->getOutput();

    // Checkall by Student/Teacher
    echo '<script type="text/javascript">';
    echo '$(function () {';
        echo "$('#includeStudents').click(function () {";
        echo "$('.student').find(':checkbox').attr('checked', this.checked);";
        echo '});';

        echo "$('#includeTeachers').click(function () {";
        echo "$('.teacher').find(':checkbox').attr('checked', this.checked);";
        echo '});';
    echo '});';
    echo '</script>';
}
