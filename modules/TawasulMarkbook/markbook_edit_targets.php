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

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_targets.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Check if tawasulCourseClassID specified
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        if ($tawasulCourseClassID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            try {
                if ($highestAction == 'Edit Markbook_everything') {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList, tawasulScaleIDTarget FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } elseif ($highestAction == 'Edit Markbook_multipleClassesInDepartment') {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList
                    FROM tawasulCourse
                    JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                    LEFT JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID AND tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID)
                    LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID)
                    WHERE ((tawasulCourseClassPerson.tawasulCourseClassPersonID IS NOT NULL AND tawasulCourseClassPerson.role='Teacher')
                        OR (tawasulDepartmentStaff.tawasulDepartmentStaffID IS NOT NULL AND (tawasulDepartmentStaff.role = 'Coordinator' OR tawasulDepartmentStaff.role = 'Assistant Coordinator' OR tawasulDepartmentStaff.role= 'Teacher (Curriculum)'))
                        )
                    AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                } else {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList, tawasulScaleIDTarget FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                //Let's go!
                $course = $result->fetch();

                $page->breadcrumbs
                    ->add(
                        __('View {courseClass} Markbook', [
                            'courseClass' => Format::courseClassName($course['course'], $course['class']),
                        ]),
                        'markbook_view.php',
                        [
                            'tawasulCourseClassID' => $tawasulCourseClassID,
                        ]
                    )
                    ->add(__('Set Personalised Attainment Targets'));

                $form = Form::create('markbookTargets', $session->get('absoluteURL').'/modules/'.$session->get('module').'/markbook_edit_targetsProcess.php?tawasulCourseClassID='.$tawasulCourseClassID);
                $form->setFactory(DatabaseFormFactory::create($pdo));
                $form->addHiddenValue('address', $session->get('address'));

                $selectedGradeScale = !empty($course['tawasulScaleIDTarget'])? $course['tawasulScaleIDTarget'] : $session->get('defaultAssessmentScale');
                $row = $form->addRow();
                    $row->addLabel('tawasulScaleIDTarget', __('Target Scale'));
                    $row->addSelectGradeScale('tawasulScaleIDTarget')->selected($selectedGradeScale);

                $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full colorOddEven noMargin noPadding');

                $header = $table->addHeaderRow();
                $header->addContent(__('Student'));

                $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'today' => date('Y-m-d'));
                $sql = "SELECT title, surname, preferredName, tawasulPerson.tawasulPersonID, dateStart, tawasulMarkbookTarget.tawasulScaleGradeID as currentTarget
                        FROM tawasulCourseClassPerson
                        JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        LEFT JOIN tawasulMarkbookTarget ON (tawasulMarkbookTarget.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID
                            AND tawasulMarkbookTarget.tawasulPersonIDStudent=tawasulCourseClassPerson.tawasulPersonID)
                        WHERE role='Student' AND tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID
                        AND status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today)
                        ORDER BY surname, preferredName";
                $result = $pdo->executeQuery($data, $sql);

                if ($result->rowCount() > 0) {
                    $header->addContent(__('Attainment Target'))->setClass('w-64');

                    $sql = "SELECT tawasulScale.tawasulScaleID, tawasulScaleGradeID as value, tawasulScaleGrade.value as name
                            FROM tawasulScaleGrade
                            JOIN tawasulScale ON (tawasulScaleGrade.tawasulScaleID=tawasulScale.tawasulScaleID)
                            WHERE tawasulScale.active='Y'
                            ORDER BY tawasulScale.tawasulScaleID, sequenceNumber";
                    $resultGrades = $pdo->executeQuery(array(), $sql);

                    $grades = ($resultGrades->rowCount() > 0)? $resultGrades->fetchAll() : array();
                    $gradesChained = array_combine(array_column($grades, 'value'), array_column($grades, 'tawasulScaleID'));
                    $gradesOptions = array_combine(array_column($grades, 'value'), array_column($grades, 'name'));

                    $count = 0;
                    while ($student = $result->fetch()) {
                        $count++;

                        $row = $table->addRow();
                        $row->addWebLink(Format::name('', $student['preferredName'], $student['surname'], 'Student', true))
                            ->setURL($session->get('absoluteURL').'/index.php?q=/modules/TawasulStudents/student_view_details.php')
                            ->addParam('tawasulPersonID', $student['tawasulPersonID'])
                            ->addParam('subpage', 'Internal Assessment')
                            ->wrap('<strong>', '</strong>')
                            ->prepend($count.') ');

                        $row->addSelect($count.'-tawasulScaleGradeID')
                            ->fromArray($gradesOptions)
                            ->chainedTo('tawasulScaleIDTarget', $gradesChained)
                            ->setClass('standardWidth')
                            ->selected($student['currentTarget'])
                            ->placeholder();

                        $form->addHiddenValue($count.'-tawasulPersonID', $student['tawasulPersonID']);
                    }

                    $form->addHiddenValue('count', $count);
                } else {
                    $table->addRow()->addAlert(__('There are no records to display.'), 'error');
                }

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit();

                echo $form->getOutput();
            }
        }
    }

    // Print the sidebar
    $session->set('sidebarExtra', sidebarExtra($guid, $pdo, $session->get('tawasulPersonID'), $tawasulCourseClassID, 'markbook_edit_targets.php'));
}
