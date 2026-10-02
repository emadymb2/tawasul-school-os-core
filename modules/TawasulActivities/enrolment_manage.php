<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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
use TawasulOS\Forms\MultiPartForm;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use Tos\Module\TawasulActivities\EnrolmentGenerator;
use TawasulOS\Domain\Activities\ActivityChoiceGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;
use TawasulOS\Domain\IndividualNeeds\INPersonDescriptorGateway;
use TawasulOS\View\Component;
use TawasulOS\UI\Components\Alert;
use TawasulOS\Domain\StudentAlerts\AlertGateway;



if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/enrolment_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Enrolment'));
     
    $page->return->addReturns([
        'error4' => __(''),
    ]);

    $categoryGateway = $container->get(ActivityCategoryGateway::class);
    $activityGateway = $container->get(ActivityGateway::class);
    $activityStudentGateway = $container->get(ActivityStudentGateway::class);
    $alertGateway = $container->get(AlertGateway::class);

    $categories = $categoryGateway->selectCategoriesBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();
    
    $params = [
        'sidebar' => 'false',
        'tawasulActivityCategoryID' => $_REQUEST['tawasulActivityCategoryID'] ?? '',
    ];

    // FILTER
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/enrolment_manage.php');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
    $row->addLabel('tawasulActivityCategoryID', __('Category'));
    $row->addSelect('tawasulActivityCategoryID')->fromArray($categories)->required()->placeholder()->selected($params['tawasulActivityCategoryID']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if (empty($params['tawasulActivityCategoryID'])) return;

    // Get groups
    $category = $categoryGateway->getByID($params['tawasulActivityCategoryID']);
    $signUpChoices = $category['signUpChoices'] ?? 3;
    $choiceList = [1 => __('First Choice'), 2 => __('Second Choice'), 3 => __('Third Choice'), 4 => __('Fourth Choice'), 5 => __('Fifth Choice')];

    $activities = $activityGateway->selectActivityDetailsByCategory($params['tawasulActivityCategoryID'])->fetchGroupedUnique();
    $enrolments = $activityStudentGateway->selectEnrolmentsByCategory($params['tawasulActivityCategoryID'])->fetchGroupedUnique();

    $criteria = $activityStudentGateway->newQueryCriteria()->sortBy(['formGroup', 'preferredName', 'surname'])->pageSize(-1);
    $unenrolled = $activityStudentGateway->queryUnenrolledStudentsByCategory($criteria, $params['tawasulActivityCategoryID'])->toArray();

    $enrolments = array_merge($enrolments, $unenrolled);
    $alert = $container->get(Alert::class);

    foreach ($enrolments as $index => $person) {
        $tawasulPersonID = $person['tawasulPersonID'] ?? '';
        $person['age'] = !empty($person['dob']) ? Format::age($person['dob']) : '';
        $person['link'] = Url::fromModuleRoute('TawasulStudents', 'student_view_details')->withQueryParams(['tawasulPersonID' => $person['tawasulPersonID']]);
        $person['alerts'] = $alert->getAlertBar($tawasulPersonID, ['wrap' => false, 'filter' => ['Medical', 'Individual Needs', 'Privacy']]);

        $enrolments[$index] = $person;
    }

    $groups = [];

    foreach ($enrolments as $person) {
        for ($i = 1; $i <= $signUpChoices; $i++) {
            if (empty($person["choice{$i}"])) continue;
            $person["choice{$i}"] = str_pad($person["choice{$i}"], 8, '0', STR_PAD_LEFT);
            $person["choice{$i}Name"] = $activities[$person["choice{$i}"]]['name'] ?? '';
        }

        $groups[$person['tawasulActivityID']][$person['tawasulPersonID']] = $person;
    }

    // FORM
    $form = MultiPartForm::create('groups', $session->get('absoluteURL').'/modules/TawasulActivities/enrolment_manageProcess.php');
    $form->setTitle(__('Manage Enrolment'));
    $form->setClass('blank w-full');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulActivityCategoryID', $params['tawasulActivityCategoryID']);

    $form->addHeaderAction('generate', __('Generate Enrolment'))
        ->setURL('/modules/TawasulActivities/choices_manage_generate.php')
        ->addParam('tawasulActivityCategoryID', $params['tawasulActivityCategoryID'])
        ->addParam('sidebar', 'false')
        ->setIcon('run')
        ->displayLabel();

    // Display the drag-drop group editor
    $form->addRow()->addContent($page->fetchFromTemplate('generate.twig.html', [
        'signUpChoices' => $signUpChoices,
        'activities' => $activities,
        'groups'      => $groups,
        'mode' => 'student',
    ]));

    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
    $row = $table->addRow()->addSubmit(__('Submit'));
    
    echo $form->getOutput();
}
