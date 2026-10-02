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
use TawasulOS\Forms\MultiPartForm;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/enrolment_manage_staffing.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Staffing'));

    
    $page->return->addReturns([
        'error4' => __(''),
    ]);

    $categoryGateway = $container->get(ActivityCategoryGateway::class);
    $activityGateway = $container->get(ActivityGateway::class);
    $enrolmentGateway = $container->get(ActivityStudentGateway::class);
    $staffGateway = $container->get(ActivityStaffGateway::class);

    $categories = $categoryGateway->selectCategoriesBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();

    $params = [
        'sidebar' => 'false',
        'tawasulActivityCategoryID' => $_REQUEST['tawasulActivityCategoryID'] ?? '',
    ];

    // FILTER
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/enrolment_manage_staffing.php');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
    $row->addLabel('tawasulActivityCategoryID', __('Category'));
    $row->addSelect('tawasulActivityCategoryID')->fromArray($categories)->required()->placeholder()->selected($params['tawasulActivityCategoryID']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if (empty($params['tawasulActivityCategoryID'])) return;

    // Get staffing

    $activities = $activityGateway->selectActivityDetailsByCategory($params['tawasulActivityCategoryID'])->fetchGroupedUnique();
    $staffing = $staffGateway->selectStaffByCategory($params['tawasulActivityCategoryID'])->fetchGrouped();

    $criteria = $staffGateway->newQueryCriteria();
    $unassigned = $staffGateway->queryUnassignedStaffByCategory($criteria, $params['tawasulActivityCategoryID'])->toArray();

    $groups = [0 => $unassigned];

    foreach ($staffing as $tawasulPersonID => $personActivities) {
        foreach ($personActivities as $person) {
            if (!empty($person['type']) && $person['type'] == 'Teaching') {
                $person['type'] = 'Teacher';
            }
        
            $groups[$person['tawasulActivityID']][$person['tawasulPersonID']] = $person;
        }   
    }

    // FORM
    $form = MultiPartForm::create('groups', $session->get('absoluteURL').'/modules/TawasulActivities/enrolment_manage_staffingProcess.php');
    $form->setTitle(__('Manage Staffing'));
    $form->setClass('blank w-full');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulActivityCategoryID', $params['tawasulActivityCategoryID']);

    // Display the drag-drop group editor
    $form->addRow()->addContent($page->fetchFromTemplate('generate.twig.html', [
        'activities' => $activities,
        'groups'      => $groups,
        'mode'        => 'staff',
    ]));

    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
    $row = $table->addRow()->addSubmit(__('Submit'));
    
    echo $form->getOutput();
}
