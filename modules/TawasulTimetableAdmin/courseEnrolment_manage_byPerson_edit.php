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
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Forms\Prefab\BulkActionForm;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;
use TawasulOS\UI\Timetable\Layers\ExceptionsLayer;
use TawasulOS\UI\Timetable\TimetableContext;
use TawasulOS\UI\Timetable\Timetable;
use TawasulOS\Http\Url;

//Module includes for Timetable module
include './modules/TawasulTimetable/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    //Check if tawasulPersonID and tawasulSchoolYearID specified
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $type = $_GET['type'] ?? '';
    $allUsers = $_GET['allUsers'] ?? '';
    $search = $_GET['search'] ?? '';

    if (empty($tawasulPersonID) or empty($tawasulSchoolYearID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $courseGateway = $container->get(CourseGateway::class);
        $courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);

        try {
            if ($allUsers == 'on') {
                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID);
                $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, title, NULL AS tawasulYearGroupID, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup, NULL AS type FROM tawasulPerson LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID) LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) LEFT JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) 
                WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID ORDER BY surname, preferredName";
            } else {
                if ($type == 'Student') {
                    $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID);
                    $sql = "(SELECT tawasulPerson.tawasulPersonID, tawasulStudentEnrolmentID, surname, preferredName, title, tawasulYearGroup.tawasulYearGroupID, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup, 'Student' AS type FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.status='Full' AND tawasulPerson.tawasulPersonID=:tawasulPersonID)";
                } elseif ($type == 'Staff') {
                    $data = array('tawasulPersonID' => $tawasulPersonID);
                    $sql = "(SELECT tawasulPerson.tawasulPersonID, NULL AS tawasulStudentEnrolmentID, surname, preferredName, title, NULL AS tawasulYearGroupID, NULL AS yearGroup, NULL AS formGroup, 'Staff' as type FROM tawasulPerson JOIN tawasulStaff ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID) JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary) WHERE tawasulPerson.status='Full' AND tawasulPerson.tawasulPersonID=:tawasulPersonID) ORDER BY surname, preferredName";
                }
            }
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
        }

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $page->breadcrumbs
                ->add(__('Course Enrolment by Person'), 'courseEnrolment_manage_byPerson.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'allUsers' => $allUsers])
                ->add(Format::name('', $values['preferredName'], $values['surname'], 'Student'));

            //INTERFACE TO ADD NEW CLASSES
            echo '<h2>';
            echo __('Add Classes');
            echo '</h2>';
            
            $form = Form::create('manageEnrolment', $session->get('absoluteURL').'/modules/'.$session->get('module')."/courseEnrolment_manage_byPerson_edit_addProcess.php?type=$type&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulPersonID=$tawasulPersonID&allUsers=$allUsers&search=$search");
                
            $form->addHiddenValue('address', $session->get('address'));
            
            if ($search != '') {
                $params = [
                    "search" => $search,
                    "allUsers" => $allUsers,
                    "tawasulSchoolYearID" => $tawasulSchoolYearID
                ];
                $form->addHeaderAction('back', __('Back to Search Results'))
                    ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson.php')
                    ->addParams($params)
                    ->setIcon('search')
                    ->displayLabel();
            }
            $params = [
                    "tawasulPersonID" => $tawasulPersonID,
                    "allUsers" => $allUsers,
                    "tawasulSchoolYearID" => $tawasulSchoolYearID
                ];
            $form->addHeaderAction('view', __('View'))
                ->setURL('/modules/TawasulTimetable/tt_view.php')
                ->addParams($params)
                ->setIcon('planner')
                ->displayLabel()
                ->prepend((!empty($search)) ? ' | ' : '');
            
            $classes = array();
            if ($type == 'Student') {
                $enrolableClasses = $courseEnrolmentGateway->selectEnrolableClassesByYearGroup($tawasulSchoolYearID, $values['tawasulYearGroupID'])->fetchAll();

                if (!empty($enrolableClasses)) {
                    $classes['--'.__('Enrolable Classes').'--'] = Format::keyValue($enrolableClasses, 'tawasulCourseClassID', function ($item) {
                        $courseClassName = Format::courseClassName($item['course'], $item['class']);
                        $teacherName = Format::name('', $item['preferredName'], $item['surname'], 'Staff');

                        return $courseClassName .' - '. (!empty($teacherName)? $teacherName.' - ' : '') . $item['studentCount'] . ' '.__('students');
                    });
                }
            }

            $allClasses = $courseGateway->selectClassesBySchoolYear($tawasulSchoolYearID)->fetchAll();

            if (!empty($allClasses)) {
                $classes['--'.__('All Classes').'--'] = Format::keyValue($allClasses, 'tawasulCourseClassID', function ($item) {
                    return Format::courseClassName($item['course'], $item['class']) .' - '. $item['courseName'];
                });
            }

            // $row = $form->addRow();
            //     $row->addLabel('Members', __('Classes'));
            //     $row->addSelect('Members')->fromArray($classes)->selectMultiple();

            $col = $form->addRow()->addColumn();
                $col->addLabel('Members', __('Classes'));
                $select = $col->addMultiSelect('Members')->required();
                $select->source()->fromArray($classes['--'.__('All Classes').'--'] ?? []);

            $roles = array(
                'Student'    => __('Student'),
                'Teacher'    => __('Teacher'),
                'Assistant'  => __('Assistant'),
                'Technician' => __('Technician'),
            );
            $selectedRole = ($type == 'Staff')? 'Teacher' : $type;

            $row = $form->addRow();
                $row->addLabel('role', __('Role'));
                $row->addSelect('role')->fromArray($roles)->required()->selected($selectedRole);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();

            
            //SHOW CURRENT ENROLMENT

            // QUERY
            $criteria = $courseEnrolmentGateway->newQueryCriteria(true)
                ->sortBy('roleSortOrder')
                ->sortBy(['course', 'class'])
                ->fromPOST();

            $enrolment = $courseEnrolmentGateway->queryCourseEnrolmentByPerson($criteria, $tawasulSchoolYearID, $tawasulPersonID);

            // FORM
            $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/courseEnrolment_manage_byPerson_editProcessBulk.php?allUsers='.$allUsers);
            $form->setTitle(__('Current Enrolment'));
            $form->addHiddenValue('type', $type);
            $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

            $linkParams = array(
                'tawasulSchoolYearID' => $tawasulSchoolYearID,
                'tawasulPersonID'     => $tawasulPersonID,
                'type'               => $type,
                'allUsers'           => $allUsers,
                'search'             => $search,
            );

            $bulkActions = array(
                'Mark as left'      => __('Mark as left'),
                'Delete'            => __('Delete'),
                'Reportable to Yes' => __('Reportable to Yes'),
                'Reportable to No'  => __('Reportable to No'),
            );

            $col = $form->createBulkActionColumn($bulkActions);
                $col->addSubmit(__('Go'));

            // DATA TABLE
            $table = $form->addRow()->addDataTable('enrolment', $criteria)->withData($enrolment);

            $table->addMetaData('bulkActions', $col);

            $table->addColumn('courseClass', __('Class Code'))
                  ->sortable(['course', 'class'])
                  ->format(Format::using('courseClassName', ['course', 'class']));
            $table->addColumn('courseName', __('Course'));
            $table->addColumn('role', __('Class Role'))->translatable();
            $table->addColumn('reportable', __('Reportable'))
                  ->format(Format::using('yesNo', 'reportable'));

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulCourseClassID')
                ->addParams($linkParams)
                ->format(function ($class, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit_edit.php');
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit_delete.php');
                });

            $table->addCheckboxColumn('tawasulCourseClassID');

            echo $form->getOutput();


            //SHOW CURRENT TIMETABLE IN EDIT VIEW
            echo "<a name='tt'></a>";
            echo '<h2>';
            echo __('Current Timetable View');
            echo '</h2>';

            $tawasulTTID = isset($_GET['tawasulTTID'])? $_GET['tawasulTTID'] : null;

            if (!empty($_REQUEST['ttDateNav'])) {
                $ttDate = $_REQUEST['ttDateNav'];
            } elseif (!empty($_REQUEST['ttDateChooser'])) {
                $ttDate = $_REQUEST['ttDateChooser'];
            } else {
                $ttDate = $_REQUEST['ttDate'] ?? null;
            }

            $apiEndpoint = Url::fromHandlerRoute('index.php')->withQueryParams(['q' => $_GET['q'], 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTID' => $tawasulTTID, 'tawasulPersonID' => $tawasulPersonID, 'type' => $type, 'ttDate' => $ttDate]);

            // Create timetable context
            $context = $container->get(TimetableContext::class)
                ->set('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->set('tawasulPersonID', $tawasulPersonID)
                ->set('tawasulTTID', $tawasulTTID)
                ->set('apiEndpoint', $apiEndpoint)
                ->set('edit', true);

            // Build and render timetable
            $exceptionsLayer = $container->get(ExceptionsLayer::class);
            
            echo $container->get(Timetable::class)
                ->setDate($ttDate)
                ->setContext($context)
                ->addCoreLayers($container)
                ->addLayer($exceptionsLayer)
                ->getOutput(); 

            //SHOW OLD ENROLMENT RECORDS
            $enrolmentLeft = $courseEnrolmentGateway->queryCourseEnrolmentByPerson($criteria, $tawasulSchoolYearID, $tawasulPersonID, true);

            $table = DataTable::createPaginated('enrolmentLeft', $criteria);
            $table->setTitle(__('Old Enrolment'));

            $table->addColumn('courseClass', __('Class Code'))
                ->sortable(['course', 'class'])
                ->format(Format::using('courseClassName', ['course', 'class']));
            $table->addColumn('courseName', __('Course'));
            $table->addColumn('role', __('Class Role'));

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulCourseClassID')
                ->addParams($linkParams)
                ->format(function ($class, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit_edit.php');
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit_delete.php');
                });

            echo $table->render($enrolmentLeft);
        }
    }
}
