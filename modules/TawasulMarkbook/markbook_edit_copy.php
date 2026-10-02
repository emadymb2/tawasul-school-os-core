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
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_copy.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Check if tawasulCourseClassID and tawasulMarkbookCopyClassID specified
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        $tawasulMarkbookCopyClassID = (isset($_POST['tawasulMarkbookCopyClassID']))? $_POST['tawasulMarkbookCopyClassID'] : null;

        if ( empty($tawasulCourseClassID) or empty($tawasulMarkbookCopyClassID) ) {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {

        	$highestAction2 = getHighestGroupedAction($guid, '/modules/TawasulMarkbook/markbook_edit.php', $connection2);

            try {
                if ($highestAction == 'Edit Markbook_everything') {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } else {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                echo '<h1>';
                echo __('Copy Columns');
                echo '</h1>';
                $page->addError(('The selected record does not exist, or you do not have access to it.'));
            } else {
                $course = $result->fetch();

                //Get teacher list
                $teacherList = getTeacherList($pdo, $tawasulCourseClassID);
                $teaching = isset($teacherList[$session->get('tawasulPersonID')]);
                $isCoordinator = isDepartmentCoordinator($pdo, $session->get('tawasulPersonID'));

                $canEditThisClass = ($teaching == true || $isCoordinator == true or $highestAction2 == 'Edit Markbook_multipleClassesAcrossSchool' or $highestAction2 == 'Edit Markbook_everything');

                if ($canEditThisClass == false) {
                    //Acess denied
                    $page->addError(__('You do not have access to this action.'));
                } else {
                    $page->breadcrumbs
                        ->add(
                            __('Edit {courseClass} Markbook', [
                                'courseClass' => Format::courseClassName($course['course'], $course['class']),
                            ]),
                            'markbook_edit.php',
                            [
                                'tawasulCourseClassID' => $tawasulCourseClassID,
                            ]
                        )
                        ->add(__('Copy Columns'));


			            $data = array('tawasulCourseClassID' => $tawasulMarkbookCopyClassID);
			            $sql = "SELECT * FROM tawasulMarkbookColumn WHERE tawasulCourseClassID=:tawasulCourseClassID";
			            $result = $connection2->prepare($sql);
			            $result->execute($data);

			        if ($result->rowCount() < 1) {
	                    echo $page->getBlankSlate();
	                } else {

		                    $data2 = array('tawasulCourseClassID' => $tawasulMarkbookCopyClassID);
		                    $sql2 = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourseClassID=:tawasulCourseClassID';
		                    $result2 = $connection2->prepare($sql2);
		                    $result2->execute($data2);

		                $courseFrom = $result2->fetch();

	                	echo '<p>';
	                	printf( __('This action will copy the following columns from %s.%s to the current class %s.%s '), $courseFrom['course'], $courseFrom['class'], $course['course'], $course['class'] );
                        echo '</p>';

                        echo '<fieldset>';

                        $form = Form::create('action', $session->get('absoluteURL').'/modules/TawasulMarkbook/markbook_edit_copyProcess.php?tawasulCourseClassID='.$tawasulCourseClassID.'&tawasulMarkbookCopyClassID='.$tawasulMarkbookCopyClassID);
                        $form->setClass('w-full');

                        $form->addHiddenValue('address', $session->get('address'));

                        $table = $form->addRow()->addTable()->setClass('w-full colorOddEven noMargin noPadding noBorder');

                        $header = $table->addHeaderRow();
                            $header->addCheckAll()->checked(true);
                            $header->addContent(__('Name'));
                            $header->addContent(__('Type'));
                            $header->addContent(__('Description'));
                            $header->addContent(__('Date Added'));

                        while ($column = $result->fetch()) {
                            $row = $table->addRow();
                                $row->addCheckbox('copyColumnID['.$column['tawasulMarkbookColumnID'].']')->setClass('textCenter')->checked(true);
                                $row->addContent($column['name'])->wrap('<strong>', '</strong>');
                                $row->addContent($column['type']);
                                $row->addContent($column['description']);
                                $row->addContent(!empty($column['date'])? Format::date($column['date']) : '');
                        }

                        $row = $form->addRow();
                            $row->addSubmit();

                        echo $form->getOutput();

                        echo '</fieldset>';
	                }
	            }
		    }
        }
    }

    // Print the sidebar
    $session->set('sidebarExtra', sidebarExtra($guid, $pdo, $session->get('tawasulPersonID'), $tawasulCourseClassID, 'markbook_edit.php'));
}
