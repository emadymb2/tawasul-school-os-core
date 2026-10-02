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
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;
use TawasulOS\Domain\Departments\DepartmentGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $search = $_GET['search'] ?? '';
    $urlParams = compact('tawasulSchoolYearID', 'search');

    $page->breadcrumbs
        ->add(__('Manage Courses & Classes'), 'course_manage.php', $urlParams)
        ->add(__('Edit Course & Classes'));

    if (!empty($search)) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulTimetableAdmin', 'course_manage.php')->withQueryParams($urlParams));
    }

    $deleteReturn = $_GET['deleteReturn'] ?? '';
    $deleteReturnMessage = '';
    $class = 'error';
    if (!($deleteReturn == '')) {
        if ($deleteReturn == 'success0') {
            $deleteReturnMessage = __('Your request was completed successfully.');
            $class = 'success';
        }
        echo "<div class='$class'>";
        echo $deleteReturnMessage;
        echo '</div>';
    }

    //Check if tawasulCourseID specified
    $tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
    if ($tawasulCourseID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulCourseID' => $tawasulCourseID);
            $sql = 'SELECT tawasulCourseID, tawasulDepartmentID, tawasulCourse.name AS name, tawasulCourse.nameShort as nameShort, orderBy, tawasulCourse.description, tawasulCourse.map, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName, tawasulYearGroupIDList, tawasulCourse.fields FROM tawasulCourse, tawasulSchoolYear WHERE tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID AND tawasulCourseID=:tawasulCourseID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/course_manage_editProcess.php?tawasulCourseID='.$tawasulCourseID);
			$form->setFactory(DatabaseFormFactory::create($pdo));

			$form->addHiddenValue('address', $session->get('address'));
			$form->addHiddenValue('tawasulSchoolYearID', $values['tawasulSchoolYearID']);

            $row = $form->addRow()->addHeading('Basic Details', __('Basic Details'));

			$row = $form->addRow();
				$row->addLabel('schoolYearName', __('School Year'));
				$row->addTextField('schoolYearName')->required()->readonly()->setValue($values['yearName']);

            $results = $container->get(DepartmentGateway::class)->selectDepartmentsOfTypeLearningArea();
			$row = $form->addRow();
				$row->addLabel('tawasulDepartmentID', __('Learning Area'));
				$row->addSearchSelect('tawasulDepartmentID')->fromResults($results)->placeholder();

			$row = $form->addRow();
				$row->addLabel('name', __('Name'))->description(__('Must be unique for this school year.'));
				$row->addTextField('name')->required()->maxLength(60);

			$row = $form->addRow();
				$row->addLabel('nameShort', __('Short Name'));
				$row->addTextField('nameShort')->required()->maxLength(16);

            $row = $form->addRow()->addHeading('Display Information', __('Display Information'));

			$row = $form->addRow();
				$row->addLabel('orderBy', __('Order'))->description(__('May be used to adjust arrangement of courses in reports.'));
				$row->addNumber('orderBy')->maxLength(3);

			$row = $form->addRow();
				$column = $row->addColumn('blurb');
				$column->addLabel('description', __('Blurb'));
				$column->addEditor('description', $guid)->setRows(10);

			$row = $form->addRow();
				$row->addLabel('map', __('Include In Curriculum Map'));
                $row->addYesNo('map')->required();

            $row = $form->addRow()->addHeading('Configure', __('Configure'));

			$row = $form->addRow();
				$row->addLabel('tawasulYearGroupIDList', __('Year Groups'))->description(__('Enrolable year groups.'));
				$row->addCheckboxYearGroup('tawasulYearGroupIDList')->loadFromCSV($values);

            // Custom Fields
            $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Course', [], $values['fields']);

			$row = $form->addRow();
				$row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();

            echo '<h2>';
            echo __('Edit Classes');
            echo '</h2>';

            $courseGateway = $container->get(CourseGateway::class);
            $courseClassGateway = $container->get(CourseClassGateway::class);

            $classes = $courseClassGateway->selectClassesByCourseID($tawasulCourseID);

            // DATA TABLE
            $table = DataTable::create('courseClassManage');

            $table->addHeaderAction('add', __('Add'))
                ->setURL('/modules/TawasulTimetableAdmin/course_manage_class_add.php')
                ->addParam('tawasulSchoolYearID', $values['tawasulSchoolYearID'])
                ->addParam('tawasulCourseID', $tawasulCourseID)
                ->addParam('search', $search)
                ->displayLabel();

            $table->addColumn('nameShort', __('Short Name'));
            $table->addColumn('name', __('Name'));
            $table->addColumn('teachersTotal', __('Teachers'));
            $table->addColumn('studentsActive', __('Students'))->description(__('Active'));
            $table->addColumn('studentsExpected', __('Students'))->description(__('Expected'));

            $table->addColumn('studentsTotal', __('Students'))
                ->description(__('Total'))
                ->format(function ($values) {
                   $return = $values['studentsTotal'];
                   if (is_numeric($values['enrolmentMin']) && $values['studentsTotal'] < $values['enrolmentMin']) {
                       $return .= Format::tag(__('Under Enrolled'), 'warning ml-2');
                   }
                   if (is_numeric($values['enrolmentMax']) && $values['studentsTotal'] > $values['enrolmentMax']) {
                       $return .= Format::tag(__('Over Enrolled'), 'error ml-2');
                   }
                   return $return;
                });

            $table->addColumn('reportable', __('Reportable'))->format(Format::using('yesNo', 'reportable'));

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulSchoolYearID', $values['tawasulSchoolYearID'])
                ->addParam('tawasulCourseID', $tawasulCourseID)
                ->addParam('search', $search)
                ->addParam('tawasulCourseClassID')
                ->format(function ($class, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/course_manage_class_edit.php');

                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/course_manage_class_delete.php');

                    $actions->addAction('enrolment', __('Enrolment'))
                        ->setIcon('attendance')
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit.php');
                });

            echo $table->render($classes->toDataSet());
        }
    }
}
