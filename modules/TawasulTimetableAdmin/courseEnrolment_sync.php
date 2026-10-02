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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Timetable\CourseSyncGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_sync.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Sync Course Enrolment'));

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

    $form = Form::create('settings', $session->get('absoluteURL').'/modules/TawasulTimetableAdmin/courseEnrolment_sync_settingsProcess.php');
    $form->setTitle(__('Settings'));
    $form->addHiddenValue('address', $session->get('address'));

    $setting = $container->get(SettingGateway::class)->getSettingByScope('Timetable Admin', 'autoEnrolCourses', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $row = $form->addRow();
        $row->addSubmit();

    echo $form->getOutput();

    $syncGateway = $container->get(CourseSyncGateway::class);

    // QUERY
    $criteria = $syncGateway->newQueryCriteria(true)
        ->sortBy(['tawasulYearGroup.sequenceNumber'])
        ->fromArray($_POST);

    $classMaps = $syncGateway->queryCourseClassMaps($criteria, $tawasulSchoolYearID);
    $classMapsAllYearGroups = implode(',', $classMaps->getColumn('tawasulYearGroupID'));

    $table = DataTable::createPaginated('sync', $criteria);

    $table->setTitle(__('Map Classes'));
    $table->setDescription(__('Syncing enrolment lets you enrol students into courses by mapping them to a Form Group and Year Group within the school. If auto-enrol is turned on, new students accepted through the application form and student enrolment process will be enrolled in courses automatically.'));

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_sync_add.php')
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->displayLabel();

    $table->addHeaderAction('sync', __('Sync All'))
        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_sync_run.php')
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->addParam('tawasulYearGroupIDList', $classMapsAllYearGroups)
        ->setIcon('refresh')
        ->displayLabel();

    $table->addColumn('yearGroupName', __('Year Group'))->sortable(['tawasulYearGroup.sequenceNumber']);
    $table->addColumn('formGroupList', __('Form Groups'));
    $table->addColumn('classCount', __('Classes'));

    // ACTIONS
    $table->addActionColumn()
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->addParam('tawasulYearGroupID')
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_sync_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_sync_delete.php');

            $actions->addAction('sync', __('Sync Now'))
                ->setIcon('refresh')
                ->addParam('tawasulYearGroupIDList', $row['tawasulYearGroupID'])
                ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_sync_run.php');
        });

    echo $table->render($classMaps);
}
