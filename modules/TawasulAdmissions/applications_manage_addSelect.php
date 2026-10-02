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
use TawasulOS\Domain\Forms\FormGateway;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;
use TawasulOS\Services\Format;


if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/applications_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $search = $_REQUEST['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Applications'), 'applications_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'search' => $search])
        ->add(__('Add Application'));

    // Display form actions
    if (!empty($search)) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulAdmissions', 'applications_manage')->withQueryParams($urlParams));
    }
    
    $form = Form::create('addApplication', $session->get('absoluteURL').'/modules/TawasulAdmissions/applications_manage_addSelectProcess.php');
    $form->setDescription(__('You can use this page to manually create an application form on behalf of another user. If the user already exists in the system, be sure to select them below so that their application will be connected to their admissions account. If the user does not exist, a new admissions account will be created.'));
    $form->addHiddenValue('address', $session->get('address'));

    // QUERY
    $formGateway = $container->get(FormGateway::class);
    $criteria = $formGateway->newQueryCriteria(true)
        ->sortBy('name', 'ASC')
        ->filterBy('type', 'Application')
        ->filterBy('active', 'Y');

    $forms = $formGateway->queryForms($criteria);

    $row = $form->addRow();
        $row->addLabel('tawasulFormID', __('Application Form'));
        $row->addSelect('tawasulFormID')->fromDataSet($forms, 'tawasulFormID', 'name')->required()->placeholder();

    $types = array(
        'blank'   => __('Blank Application'),
        'account' => __('Current').' '.__('Admissions Account'),
        'person'  => __('Current').' '.__('User'),
    );

    $row = $form->addRow();
        $row->addLabel('applicationType', __('Type'));
        $row->addSelect('applicationType')->fromArray($types)->required()->placeholder();

    $form->toggleVisibilityByClass('typeBlank')->onSelect('applicationType')->when('blank');
        
    $row = $form->addRow()->addClass('typeBlank');
        $row->addLabel('email', __('Email Address'));
        $row->addEmail('email')->required();

    // QUERY
    $admissionsAccountGateway = $container->get(AdmissionsAccountGateway::class);
    $criteria = $admissionsAccountGateway->newQueryCriteria()
        ->sortBy(['sortOrder', 'surname', 'preferredName']);

    $accounts = $admissionsAccountGateway->queryAdmissionsAccounts($criteria);
    $accounts = array_reduce($accounts->toArray(), function ($group, $item) {
        $role = !empty($item['roleName']) ? $item['roleName'] : __('Unknown');
        $name = !empty($item['surname']) ? Format::name('', $item['preferredName'], $item['surname'], 'Parent', true) : __('N/A');
        $group[$role][$item['tawasulAdmissionsAccountID']] = "{$name} ({$item['email']})";
        return $group;
    }, []);

    $form->toggleVisibilityByClass('typeAccount')->onSelect('applicationType')->when('account');

    $row = $form->addRow()->addClass('typeAccount');
        $row->addLabel('tawasulAdmissionsAccountID', __('Admissions Account'));
        $row->addSearchSelect('tawasulAdmissionsAccountID')->fromArray($accounts)->required()->placeholder();

    $sql = "SELECT tawasulRole.category as groupBy, tawasulPersonID as value, CONCAT(tawasulPerson.surname, ', ', tawasulPerson.preferredName, ' (', tawasulPerson.username, ')') as name
            FROM tawasulPerson
            JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
            WHERE tawasulRole.category <> 'Student'
            AND tawasulPerson.status='Full'
            ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";

    $form->toggleVisibilityByClass('typePerson')->onSelect('applicationType')->when('person');

    $row = $form->addRow()->addClass('typePerson');
        $row->addLabel('tawasulPersonID', __('Person'));
        $row->addSearchSelect('tawasulPersonID')->fromQuery($pdo, $sql, [], 'groupBy')->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
