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

use TawasulOS\Services\Format;
use TawasulOS\Forms\Builder\FormBuilder;
use TawasulOS\Forms\Builder\Storage\ApplicationFormStorage;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;
use TawasulOS\Domain\Admissions\AdmissionsApplicationGateway;
use TawasulOS\Tables\Renderer\SpreadsheetRenderer;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\Builder\Processor\FormProcessorFactory;

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/applications_manage_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $search = $_GET['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Applications'), 'applications_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'search' => $search])
        ->add(__('View & Print Application'));

    $tawasulAdmissionsApplicationID = $_GET['tawasulAdmissionsApplicationID'] ?? '';
    $viewMode = $_GET['format'] ?? '';

    $admissionsApplicationGateway = $container->get(AdmissionsApplicationGateway::class);
    $application = $admissionsApplicationGateway->getByID($tawasulAdmissionsApplicationID);

    if (empty($application)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get the admissions account
    $account = $container->get(AdmissionsAccountGateway::class)->getByID($application['foreignTableID']);
    if (empty($account)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Setup the form builder & data
    $formBuilder = $container->get(FormBuilder::class)->populate($application['tawasulFormID'], 1, ['identifier' => $application['identifier'], 'accessID' => $account['accessID']])->includeHidden();
    $formData = $container->get(ApplicationFormStorage::class)->setContext($formBuilder->getFormID(), $formBuilder->getPageID(), 'tawasulAdmissionsAccount', $account['tawasulAdmissionsAccountID'], $account['email']);
    $formData->load($application['identifier']);
    $formBuilder->addConfig(['foreignTableID' => $tawasulAdmissionsApplicationID]);

    // Verify the form
    $formProcessor = $container->get(FormProcessorFactory::class)->getProcessor($formBuilder->getDetail('type'));
    $errors = $formProcessor->verifyForm($formBuilder);

    // Display any validation errors
    foreach ($errors as $errorMessage) {
        echo Format::alert($errorMessage);
    }

    // Display a message for incomplete applications
    if ($application['status'] == 'Incomplete') {
        echo Format::alert(__('This application form was created by {email} and is still in progress. It has not been submitted yet.', ['email' => '<u>'.$account['email'].'</u>']), 'warning');
    }
    
    // Display the submitted data
    $table = $formBuilder->display();

    if ($viewMode == 'print') {
        $table->addHeaderAction('print', __('Print'))
            ->onClick('javascript:window.print(); return false;')
            ->setURL('#')
            ->displayLabel();
        $table->addMetaData('hidePagination', true);
    } else {
        $table->addHeaderAction('print', __('Print'))
            ->setURL('/report.php')
            ->addParam('q', '/modules/TawasulAdmissions/applications_manage_view.php')
            ->addParam('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID)
            ->addParam('format', 'print')
            ->setTarget('_blank')
            ->directLink()
            ->displayLabel();
    }

    if ($viewMode == 'export') {
        $table->setRenderer(new SpreadsheetRenderer($session->get('absolutePath')));
        $table->addMetaData('filename', 'tawasulExport_'.$tawasulAdmissionsApplicationID);
        $table->addMetaData('creator', Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff'));

    } else {
        $table->addHeaderAction('export', __('Export'))
            ->setURL('/export.php')
            ->addParam('q', '/modules/TawasulAdmissions/applications_manage_view.php')
            ->addParam('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID)
            ->addParam('format', 'export')
            ->setTarget('_blank')
            ->directLink()
            ->displayLabel();
    }

    echo $table->render([$formData->getData()]);
}
