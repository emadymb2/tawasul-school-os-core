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
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Forms\Prefab\BulkActionForm;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\Domain\Timetable\TimetableGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulCourseID, tawasulSchoolYearID, and tawasulCourseClassID specified
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $search = $_GET['search'] ?? '';

    if (empty($tawasulCourseID) or empty($tawasulSchoolYearID) or empty($tawasulCourseClassID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $userGateway = $container->get(UserGateway::class);
        $courseGateway = $container->get(CourseGateway::class);
        $courseClassGateway = $container->get(CourseClassGateway::class);
        $courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);

        $values = $courseClassGateway->getCourseClassByID($tawasulCourseClassID);

        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $page->breadcrumbs
                ->add(__('Course Enrolment by Class'), 'courseEnrolment_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
                ->add(__('Edit %1$s.%2$s Enrolment', ['%1$s' => $values['courseNameShort'], '%2$s' => $values['name']]));

            //Report minimum/maximum enrolment messages
            if (is_numeric($values['enrolmentMin']) && $values['studentsTotal'] < $values['enrolmentMin']) {
                $page->addWarning(__('This class is currently under enrolled, based on a minimum enrolment of {enrolmentMin} students.', ['enrolmentMin' => $values['enrolmentMin']]));
            }
            if (is_numeric($values['enrolmentMax']) && $values['studentsTotal'] > $values['enrolmentMax']) {
                $page->addError(__('This class is currently over enrolled, based on a maximum enrolment of {enrolmentMax} students.', ['enrolmentMax' => $values['enrolmentMax']]));
            }

            if ($search != '') {
                $params = [
                    "search" => $search,
                    "tawasulSchoolYearID" => $tawasulSchoolYearID
                ];
                $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulTimetableAdmin', 'courseEnrolment_manage.php')->withQueryParams($params));
            }

            $timetables = $container->get(TimetableGateway::class)->selectTimetablesByClass($tawasulCourseClassID)->fetchAll();
            if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_byClass.php') && count($timetables) == 1) {
                $tawasulTTID = $timetables[0]['tawasulTTID'];
                $page->navigator->addHeaderAction('edit', __('Edit Timetable by Class'))
                    ->setURL('/modules/TawasulTimetableAdmin/tt_edit_byClass.php')
                    ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                    ->addParam('tawasulTTID', $tawasulTTID)
                    ->addParam('tawasulCourseID', $tawasulCourseID)
                    ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
                    ->displayLabel();
            }

            echo '<h2>';
            echo __('Add Participants');
            echo '</h2>';

            $form = Form::create('manageEnrolment', $session->get('absoluteURL').'/modules/'.$session->get('module')."/courseEnrolment_manage_class_edit_addProcess.php?tawasulCourseClassID=$tawasulCourseClassID&tawasulCourseID=$tawasulCourseID&tawasulSchoolYearID=$tawasulSchoolYearID&search=$search");

            $form->addHiddenValue('address', $session->get('address'));

                $allUsers = $userGateway->selectUserNamesByStatus(['Full', 'Expected'], null, $tawasulSchoolYearID)->fetchAll();

            $people = Format::keyValue($allUsers, 'tawasulPersonID', function ($item) {
                    $name = $item['surname'].', '.$item['preferredName'].' ('.__($item['roleCategory']);
                if ($item['roleCategory'] == 'Student' && $item['formGroupName'] != '') {
                    $name .= ', '.$item['formGroupName'];
                }
                    $name .= ', '.$item['username'].')';

                if ($item['status'] == 'Expected') {
                    $name .= ' ('.__('Expected').')';
                }

                return $name;
            });

            $col = $form->addRow()->addColumn();
                $col->addLabel('Members', __('Participants'));
                $col->addMultiSelect('Members')->required()->source()->fromArray($people);

            $roles = array(
                'Student'    => __('Student'),
                'Teacher'    => __('Teacher'),
                'Assistant'  => __('Assistant'),
                'Technician' => __('Technician'),
                'Parent'     => __('Parent'),
            );

            $row = $form->addRow();
                $row->addLabel('role', __('Role'));
                $row->addSelect('role')->fromArray($roles)->required();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();

            $linkedName = function ($person) {
                $isStudent = stripos($person['role'], 'Student') !== false;
                $name = Format::name('', $person['preferredName'], $person['surname'], $isStudent ? 'Student' : 'Staff', true, true);
                return $isStudent
                    ? Format::link('./index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$person['tawasulPersonID'].'&subpage=Timetable', $name).'<br/>'.Format::userStatusInfo($person)
                    : $name;
            };

            // QUERY
            $criteria = $courseEnrolmentGateway->newQueryCriteria(true)
                ->sortBy('roleSortOrder')
                ->sortBy(['tawasulPerson.surname', 'tawasulPerson.preferredName'])
                ->fromPOST();

            $enrolment = $courseEnrolmentGateway->queryCourseEnrolmentByClass($criteria, $tawasulSchoolYearID, $tawasulCourseClassID, false, true);

            // FORM
            $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/courseEnrolment_manage_class_editProcessBulk.php');
            $form->setTitle(__('Current Participants'));

            $form->addHiddenValue('tawasulCourseID', $tawasulCourseID);
            $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            $form->addHiddenValue('search', $search);

            $linkParams = array(
                'tawasulCourseID'      => $tawasulCourseID,
                'tawasulCourseClassID' => $tawasulCourseClassID,
                'tawasulSchoolYearID'  => $tawasulSchoolYearID,
                'search'  => $search,
            );

            $bulkActions = array(
                'Copy to class' => __('Copy to class'),
                'Mark as left'  => __('Mark as left'),
                'Delete'        => __('Delete'),
            );

            $col = $form->createBulkActionColumn($bulkActions);
                $classesBySchoolYear = $courseGateway->selectClassesBySchoolYear($tawasulSchoolYearID)->fetchAll();
                $classesBySchoolYear = Format::keyValue($classesBySchoolYear, 'tawasulCourseClassID', 'courseClassName', ['course', 'class']);
                $col->addSelect('tawasulCourseClassIDCopyTo')->fromArray($classesBySchoolYear)->setClass('shortWidth copyTo');
                $col->addSubmit(__('Go'));

            $form->toggleVisibilityByClass('copyTo')->onSelect('action')->when('Copy to class');

            // DATA TABLE
            $table = $form->addRow()->addDataTable('enrolment', $criteria)->withData($enrolment);

            $table->modifyRows(function ($person, $row) {
                if (!(empty($person['dateStart']) || $person['dateStart'] <= date('Y-m-d'))) $row->addClass('error');
                return $row;
            });
            $table->addMetaData('bulkActions', $col);

            $table->addColumn('name', __('Name'))
                  ->sortable(['tawasulPerson.surname', 'tawasulPerson.preferredName'])
                  ->format($linkedName);
            $table->addColumn('email', __('Email'));
            $table->addColumn('role', __('Class Role'))->translatable();
            $table->addColumn('reportable', __('Reportable'))
                  ->format(Format::using('yesNo', 'reportable'));

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulCourseClassPersonID')
                ->addParam('tawasulPersonID')
                ->addParams($linkParams)
                ->format(function ($person, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit_edit.php');
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit_delete.php');
                });

            $table->addCheckboxColumn('tawasulCourseClassPersonID');

            echo $form->getOutput();

            $enrolmentLeft = $courseEnrolmentGateway->queryCourseEnrolmentByClass($criteria, $tawasulSchoolYearID, $tawasulCourseClassID, true, true);

            $table = DataTable::createPaginated('enrolmentLeft', $criteria);
            $table->setTitle(__('Former Participants'));

            $table->modifyRows(function ($person, $row) {
                if (!(empty($person['dateStart']) || $person['dateStart'] <= date('Y-m-d'))) $row->addClass('error');
                return $row;
            });

            $table->addColumn('name', __('Name'))
                ->sortable(['surname', 'preferredName'])
                ->format($linkedName);
            $table->addColumn('email', __('Email'));
            $table->addColumn('role', __('Class Role'))->translatable();

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulCourseClassPersonID')
                ->addParam('tawasulPersonID')
                ->addParams($linkParams)
                ->format(function ($person, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit_edit.php');
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit_delete.php');
                });

            echo $table->render($enrolmentLeft);
        }
    }
}