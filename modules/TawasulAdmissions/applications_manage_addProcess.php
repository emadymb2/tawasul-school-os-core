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
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Forms\FormPageGateway;
use TawasulOS\Forms\Builder\FormBuilder;
use TawasulOS\Forms\Builder\Processor\FormProcessorFactory;
use TawasulOS\Forms\Builder\Storage\ApplicationFormStorage;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['officeNotes' => 'HTML']);

$accessID = $_REQUEST['accessID'] ?? '';
$tawasulFormID = $_REQUEST['tawasulFormID'] ?? '';
$identifier = $_REQUEST['identifier'] ?? null;
$pageNumber = $_REQUEST['page'] ?? 1;

$URL = Url::fromModuleRoute('TawasulAdmissions', 'applications_manage_add')->withQueryParams(['tawasulFormID' => $tawasulFormID, 'page' => $pageNumber, 'identifier' => $identifier, 'accessID' => $accessID]);

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/applications_manage_add.php') == false) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} else {
    // Proceed!
    if (empty($tawasulFormID) || empty($pageNumber)) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }

    $partialFail = false;

    $admissionsAccountGateway = $container->get(AdmissionsAccountGateway::class);
    $account = $admissionsAccountGateway->getAccountByAccessID($accessID);
    if (empty($account)) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }
    
    // Setup the form data
    $formBuilder = $container->get(FormBuilder::class)->populate($tawasulFormID, $pageNumber, ['identifier' => $identifier, 'accessID' => $accessID]);
    $formData = $container->get(ApplicationFormStorage::class)->setContext($formBuilder->getFormID(), $formBuilder->getPageID(), 'tawasulAdmissionsAccount', $account['tawasulAdmissionsAccountID'], $account['email']);
    $formData->load($identifier);

    // Acquire data from POST - on error, return to the current page
    $data = $formBuilder->acquire();
    if (!$data) {
        header("Location: {$URL->withReturn('error1')}");
        exit;
    }

    // Save data before validation, so users don't lose data?
    $formData->addData($data);
    $formData->save($identifier);

    // Add configuration data to the form, such as recently created IDs
    $formBuilder->addConfig([
        'mode'           => 'manual',
        'foreignTableID' => $formData->identify($identifier),
        'accessID'       => $accessID,
        'accessToken'    => $account['accessToken'],
        'tawasulPersonID' => $account['tawasulPersonID'] ?? null,
        'tawasulFamilyID' => $account['tawasulFamilyID'] ?? null,
    ]);

    // Handle file uploads - on error, flag partial failures
    $uploaded = $formBuilder->upload();
    $partialFail &= !$uploaded;

    // Validate submitted data - on error, return to the current page
    $validated = $formBuilder->validate($data);
    if (!empty($validated)) {
        header("Location: {$URL->withReturn('error3')->withQueryParam('invalid', implode(',', $validated))}");
        exit;
    }

    // Update the admissions account email, if there is none
    if (empty($account['email']) && $formData->has('parent1email')) {
        $admissionsAccountGateway->update($account['tawasulAdmissionsAccountID'], [
            'email' => $formData->get('parent1email'),
        ]);
    }

    // Determine how to handle the next page
    $formPageGateway = $container->get(FormPageGateway::class);
    $finalPageNumber = $formPageGateway->getFinalPageNumber($tawasulFormID);
    $nextPage = $formPageGateway->getNextPageByNumber($tawasulFormID, $pageNumber);
    $maxPage = max($nextPage['sequenceNumber'] ?? $pageNumber, $formData->get('maxPage') ?? 1);

    if ($pageNumber >= $finalPageNumber) {
        // Do not run the submission processes, but do update the status manually
        $formData->setStatus('Pending');
        $formData->setResult('statusDate', date('Y-m-d H:i:s'));
        $formData->set('tawasulSchoolYearIDEntry', $formData->getAny('tawasulSchoolYearIDEntry') ?? $session->get('tawasulSchoolYearID'));

        $formData->save($identifier);

        $URL = $URL->withQueryParam('page', $pageNumber+1)->withReturn('success0');

    } elseif ($nextPage) {
        // Save data and proceed to the next page
        $formData->addData(['maxPage' => $maxPage]);
        $formData->save($identifier);

        $URL = $URL->withQueryParam('page', $nextPage['sequenceNumber'])->withReturn($partialFail ? 'warning1' : '');
    }

    header("Location: {$URL}");
}
