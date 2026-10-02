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
use TawasulOS\Domain\Forms\FormGateway;
use TawasulOS\Domain\Admissions\AdmissionsAccountGateway;
use TawasulOS\Domain\Admissions\AdmissionsApplicationGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Builder\FormPayment;
use TawasulOS\Domain\User\FamilyGateway;

$accessID = $_GET['acc'] ?? $_GET['accessID'] ?? '';
$accessToken = $_GET['tok'] ?? $_GET['accessToken'] ?? $session->get('admissionsAccessToken') ?? '';
$proceed = false;
$public = false;

if (!$session->has('tawasulPersonID')) {
    $public = true;
    if (!empty($accessID) && !empty($accessToken)) {
        $proceed = true;
    }
} else if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/applicationFormView.php') != false) {
    $proceed = true;
}

if (!$proceed) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('My Application Forms'));

    $admissionsAccountGateway = $container->get(AdmissionsAccountGateway::class);
    $admissionsApplicationGateway = $container->get(AdmissionsApplicationGateway::class);

    $account = $public
        ? $admissionsAccountGateway->getAccountByAccessToken($accessID, $accessToken)
        : $admissionsAccountGateway->getAccountByPerson($session->get('tawasulPersonID'));

    if ($public && empty($account)) {
        $page->addError(__('The application link does not match an existing record in our system. The record may have been removed or the link is no longer valid.').' '.__('Please visit the {application} page to request a new link.', ['application' => Format::link(Url::fromModuleRoute('TawasulAdmissions', 'applicationFormSelect')->withAbsoluteUrl(), __('Admissions Welcome'))]));
        $session->forget('admissionsAccessToken');
        return;
    }

    if (!empty($account)) {
        $session->set('admissionsAccessToken', $account['accessToken']);
        $admissionsAccountGateway->update($account['tawasulAdmissionsAccountID'], ['timestampActive' => date('Y-m-d H:i:s')]);

        $foreignTable = 'tawasulAdmissionsAccount';
        $foreignTableID = $account['tawasulAdmissionsAccountID'];
    } else {
        // New Admissions Account for Existing User
        $form = Form::create('admissionsAccount', $session->get('absoluteURL').'/modules/TawasulAdmissions/applicationFormViewProcess.php');

        $welcomeHeading = $container->get(SettingGateway::class)->getSettingByScope('Admissions', 'welcomeHeading');
        $form->setTitle(__($welcomeHeading, ['organisationNameShort' => $session->get('organisationNameShort')]));
        $form->addHiddenValue('address', $session->get('address'));

        $form->addRow()->addContent(__('You\'re ready to begin the admissions process. We do not have an admissions account in our system attached to your user. Please click Next to create an account.'))->wrap('<p>', '</p>');

        $families = $container->get(FamilyGateway::class)->selectFamiliesByAdult($session->get('tawasulPersonID'))->fetchAll();
        if (count($families) == 1) {
            $family = current($families);
            $form->addHiddenValue('tawasulFamilyID', $family['tawasulFamilyID'] ?? '');
        } else if (count($families) > 1) {
            $row = $form->addRow();
            $row->addLabel('tawasulFamilyID', __('Family'));
            $row->addSelect('tawasulFamilyID')->fromArray($families, 'tawasulFamilyID', 'name')->placeholder()->required();
        }

        $form->addRow()->addSubmit(__('Next'));

        echo $form->getOutput();
        return;
    }

    if ($public && !empty($account['timestampTokenExpire'])) {
        echo Format::alert(__('Welcome back! You are accessing this page through a unique link sent to your email address {email}. Please keep this link secret to protect your personal details. This link will expire {expiry}.', ['email' => '<u>'.$account['email'].'</u>', 'expiry' => Format::relativeTime($account['timestampTokenExpire'])]), 'message');
    }

    $page->return->addReturns(['success1' => __('A new admissions account has been created for {email}', ['email' => $account['email'] ?? ''])]);

    $formPayment = $container->get(FormPayment::class);

    $criteria = $admissionsApplicationGateway->newQueryCriteria(true)
        ->sortBy('timestampCreated', 'ASC');

    $submissions = $admissionsApplicationGateway->queryApplicationsByContext($criteria, $foreignTable, $foreignTableID);
    $submissions->transform(function (&$values) {
        // Prevent parents from seeing office-only statuses
        if ($values['status'] != 'Incomplete') {
            $values['status'] = 'Submitted';
        }
    });

    if (count($submissions) > 0) {
        // DATA TABLE
        $table = DataTable::create('submissions');
        $table->setTitle(__('Current Applications'));


        $table->addColumn('student', __('Applicant'))->format(function ($values) {
            return !empty($values['studentSurname'])
                ? Format::name('', $values['studentPreferredName'], $values['studentSurname'], 'Student')
                : Format::small(__('N/A'));

        });
        $table->addColumn('formName', __('Application Form'));
        $table->addColumn('status', __('Status'))->translatable();
        $table->addColumn('timestampCreated', __('Date'))->width('20%')->format(Format::using('dateTimeReadable', 'timestampCreated'));

        $table->modifyRows(function ($values, $row) {
            if ($values['status'] == 'Incomplete') $row->addClass('warning');
            if ($values['status'] == 'Accepted') $row->addClass('success');
            return $row;
        });

        $table->addActionColumn()
            ->addParam('accessID', $account['accessID'])
            ->addParam('tawasulFormID')
            ->addParam('identifier')
            ->addParam('page')
            ->format(function ($values, $actions) use ($accessToken, $formPayment) {
                if ($values['status'] == 'Incomplete') {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulAdmissions/applicationForm.php');
                } else {
                    $actions->addAction('view', __('View'))
                        ->setURL('/modules/TawasulAdmissions/applicationForm.php');
                }

                if ($values['status'] != 'Incomplete' && $values['status'] != 'Submitted' && $values['status'] != 'Accepted') return;

                $submitPaymentMade = !empty($values['tawasulPaymentIDSubmit']) || $values['submissionFeeComplete'] == 'Y';
                $processPaymentMade = !empty($values['tawasulPaymentIDProcess']) || $values['processingFeeComplete'] == 'Y';

                $submitPaymentPossible = !empty($values['formSubmissionFee']) && !$submitPaymentMade && $values['submissionFeeComplete'] != 'Exemption';
                $processPaymentPossible = !empty($values['formProcessingFee']) && !$processPaymentMade && $values['processingFeeComplete'] != 'Exemption';

                if ($formPayment->isEnabled() && ($submitPaymentPossible || $processPaymentPossible)) {
                    $actions->addAction('payment', __('Pay Online'))
                        ->addParam('tok', $accessToken)
                        ->setIcon('payment')
                        ->setURL('/modules/TawasulAdmissions/applicationForm_payFee.php');
                }
            });

        echo $table->render($submissions);
    }

    // QUERY
    $formGateway = $container->get(FormGateway::class);
    $criteria = $formGateway->newQueryCriteria(true)
        ->sortBy('name', 'ASC')
        ->filterBy('type', 'Application')
        ->filterBy('active', 'Y')
        ->filterBy('public', $public ? 'Y' : '');

    $forms = $formGateway->queryForms($criteria)->toArray();

    if (count($forms) == 0) {
        return;
    }

    // FORM
    $form = Form::createBlank('admissionsAccount', $session->get('absoluteURL').'/index.php?q=/modules/TawasulAdmissions/applicationForm.php');

    $form->setTitle(__('New Application Form'));
    $form->setDescription((count($submissions) > 0 ? __('You may continue submitting applications with the form below and they will be linked to your account data.').' ' : '').__('Some information has been pre-filled for you, feel free to change this information as needed.'));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('accessID', $account['accessID'] ?? '');

    // Display all available public forms
    $firstForm = current($forms);
    foreach ($forms as $index => $applicationForm) {
        $table = $form->addRow()->addTable()->setClass('w-full noIntBorder border rounded my-2 bg-blue-50 mb-2');

        $row = $table->addRow();
            $row->addLabel('tawasulFormID'.$index, __($applicationForm['name']))->description($applicationForm['description'])->setClass('block w-full p-6 font-medium text-sm text-gray-700');
            $row->addRadio('tawasulFormID')->setID('tawasulFormID'.$index)->fromArray([$applicationForm['tawasulFormID'] => ''])->required()->addClass('mr-6')->checked($firstForm['tawasulFormID'] ?? false);
    }

    $form->addRow()->addSubmit(__('Next'));

    echo $form->getOutput();
}
