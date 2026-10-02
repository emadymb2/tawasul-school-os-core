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
use TawasulOS\Forms\Builder\FormBuilder;
use TawasulOS\Forms\Builder\Storage\ApplicationFormStorage;
use TawasulOS\Forms\Builder\Processor\FormProcessorFactory;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;
use TawasulOS\Domain\Admissions\AdmissionsApplicationGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
$tawasulAdmissionsApplicationID = $_REQUEST['tawasulAdmissionsApplicationID'] ?? '';
$search = $_REQUEST['search'] ?? '';

$URL = Url::fromModuleRoute('TawasulAdmissions', 'applications_manage_accept')->withQueryParams(['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID, 'search' => $search]);

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/applications_manage_accept.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    ini_set('memory_limit', '2048M');
    ini_set('max_execution_time', 1800);
    set_time_limit(1800);

    // Get the application form data
    $application = $container->get(AdmissionsApplicationGateway::class)->getByID($tawasulAdmissionsApplicationID);
    if (empty($tawasulAdmissionsApplicationID) || empty($application)) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }

    // Get the admissions account
    $admissionsAccountGateway = $container->get(AdmissionsAccountGateway::class);
    $account = $admissionsAccountGateway->getByID($application['foreignTableID']);
    if (empty($account)) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }

    // Setup the builder class
    $formBuilder = $container->get(FormBuilder::class)->populate($application['tawasulFormID'], -1, ['identifier' => $application['identifier'], 'accessID' => $account['accessID']])->includeHidden();

    // Setup the form data
    $formData = $container->get(ApplicationFormStorage::class)->setContext($formBuilder->getFormID(), $formBuilder->getPageID(), 'tawasulAdmissionsAccount', $account['tawasulAdmissionsAccountID'], $account['email']);
    $formData->load($application['identifier']);
    
    // Link the application to this parent, if one exists (eg: created after the application)
    if (!$formData->has('tawasulPersonIDParent1') && !empty($account['tawasulPersonID'])) {
        $formData->set('tawasulPersonIDParent1', $account['tawasulPersonID']);
    }
    // Link the application to this account family, if one exists
    if (!$formData->has('tawasulFamilyID') && !empty($account['tawasulFamilyID'])) {
        $formData->set('tawasulFamilyID', $account['tawasulFamilyID']);
    }

    // Set the data in readonly mode, so all changes are recorded as results
    $formBuilder->addConfig(['foreignTableID' => $formData->identify($application['identifier'])]);
    $formData->setResults([]);
    $formData->setReadOnly(true);
    
    // Run any accept-related processes
    $formProcessor = $container->get(FormProcessorFactory::class)->getProcessor($formBuilder->getDetail('type'));
    $formProcessor->acceptForm($formBuilder, $formData);

    // Handle errors
    if ($formProcessor->hasErrors()) {
        $formData->setResult('errors', $formProcessor->getErrors());
        $return = $formProcessor->getMode() == 'rollback' ? 'error3' : 'warning1';
    }

    // Save the final results of the acceptance
    $formData->save($application['identifier']);

    // Link the admissions account to new parent and family, if they were created during this acceptance process
    if ($formData->getStatus() == 'Accepted') {
        if ($formData->hasResult('familyCreated') && $formData->hasResult('tawasulFamilyID') && empty($account['tawasulFamilyID'])) {
            $admissionsAccountGateway->update($account['tawasulAdmissionsAccountID'], [
                'tawasulFamilyID' => $formData->getResult('tawasulFamilyID'),
            ]);
        }
        if ($formData->hasResult('parent1created') && $formData->hasResult('tawasulPersonIDParent1') && empty($account['tawasulPersonID'])) {
            $admissionsAccountGateway->update($account['tawasulAdmissionsAccountID'], [
                'tawasulPersonID' => $formData->getResult('tawasulPersonIDParent1'),
            ]);
        }
    }

    header("Location: {$URL->withReturn(!empty($return) ? $return : 'success0')}");
}
