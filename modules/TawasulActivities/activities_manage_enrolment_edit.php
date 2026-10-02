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

use TawasulOS\Domain\School\SchoolYearTermGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Http\Url;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_enrolment_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulActivityID = (isset($_GET['tawasulActivityID']))? $_GET['tawasulActivityID'] : null;

    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulActivities/activities_manage_enrolment.php', $connection2);
    if ($highestAction == 'My Activities_viewEditEnrolment') {


            $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulActivityID' => $tawasulActivityID);
            $sql = "SELECT tawasulActivity.*, NULL as status, tawasulActivityStaff.role FROM tawasulActivity JOIN tawasulActivityStaff ON (tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID) WHERE tawasulActivity.tawasulActivityID=:tawasulActivityID AND tawasulActivityStaff.tawasulPersonID=:tawasulPersonID AND tawasulActivityStaff.role='Organiser' AND tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' ORDER BY name";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if (!$result || $result->rowCount() == 0) {
            //Acess denied
            $page->addError(__('You do not have access to this action.'));
            return;
        }
    }

    $urlParams = ['tawasulActivityID' => $_GET['tawasulActivityID'], 'search' => $_GET['search'] ?? '', 'tawasulSchoolYearTermID' => $_GET['tawasulSchoolYearTermID'] ?? ''];

    $page->breadcrumbs
        ->add(__('Manage Activities'), 'activities_manage.php')
        ->add(__('Activity Enrolment'), 'activities_manage_enrolment.php',  $urlParams)
        ->add(__('Edit Enrolment'));

    //Check if tawasulActivityID and tawasulPersonID specified
    $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    if ($tawasulPersonID == '' or $tawasulActivityID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID);
            $sql = 'SELECT tawasulActivity.*, tawasulActivityStudent.*, surname, preferredName, tawasulActivityType.access, tawasulActivityType.maxPerStudent, tawasulActivityType.enrolmentType, tawasulActivityType.backupChoice, tawasulActivityType.waitingList FROM tawasulActivity JOIN tawasulActivityStudent ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID) JOIN tawasulPerson ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name) WHERE tawasulActivityStudent.tawasulActivityID=:tawasulActivityID AND tawasulActivityStudent.tawasulPersonID=:tawasulPersonID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $settingGateway = $container->get(SettingGateway::class);
            $dateType = $settingGateway->getSettingByScope('Activities', 'dateType');

            $form = Form::create('activityEnrolment', Url::fromModuleRoute('TawasulActivities', 'activities_manage_enrolment_editProcess')->withQueryParams($urlParams + ['tawasulPersonID' => $tawasulPersonID])->directLink());

            if (!empty($_GET['search']) != '' || !empty( $_GET['tawasulSchoolYearTermID'])) {
                $form->addHeaderAction('back', __('Back'))
                    ->setURL('/modules/TawasulActivities/activities_manage_enrolment.php')
                    ->addParams($urlParams);
			}

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);

            $row = $form->addRow();
                $row->addLabel('nameLabel', __('Name'));
                $row->addTextField('name')->readOnly()->setValue($values['name']);

            if ($dateType == 'Date') {
                $row = $form->addRow();
                $row->addLabel('listingDatesLabel', __('Listing Dates'));
                $row->addTextField('listingDates')->readOnly()->setValue(Format::date($values['listingStart']).'-'.Format::date($values['listingEnd']));

                $row = $form->addRow();
                $row->addLabel('programDatesLabel', __('Program Dates'));
                $row->addTextField('programDates')->readOnly()->setValue(Format::date($values['programStart']).'-'.Format::date($values['programEnd']));
            } else {
                /**
                 * @var SchoolYearTermGateway
                 */
                $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);
                $termList = $schoolYearTermGateway->getTermNamesByID($values['tawasulSchoolYearTermIDList']);

                $row = $form->addRow();
                $row->addLabel('termsLabel', __('Terms'));
                $row->addTextField('terms')->readOnly()->setValue(!empty($termList)? implode(', ', $termList) : '-');
	    }

            $row = $form->addRow();
            $row->addLabel('student', __('Student'));
            $row->addTextField('student')->readOnly()->setValue(Format::name('', htmlPrep($values['preferredName']), htmlPrep($values['surname']), 'Student'));

            // Load the enrolmentType system setting, optionally override with the Activity Type setting
            $enrolment = $settingGateway->getSettingByScope('Activities', 'enrolmentType');
            $enrolment = !empty($values['enrolmentType'])? $values['enrolmentType'] : $enrolment;

            $statuses = ['Accepted' => __('Accepted')];
            if ($enrolment == 'Competitive') {
                if (!empty($values['waitingList']) && $values['waitingList'] == 'Y') {
                    $statuses['Waiting List'] = __('Waiting List');
                }
            } else {
                $statuses['Pending'] = __('Pending');
            }
            $statuses['Not Accepted'] = __('Not Accepted');
            $statuses['Left'] = __('Left');

            $row = $form->addRow();
            $row->addLabel('status', __('Status'));
            $row->addSelect('status')->fromArray($statuses)->required();

            $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();
        }
    }
}
