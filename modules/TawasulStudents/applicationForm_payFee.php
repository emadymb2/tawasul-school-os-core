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
use TawasulOS\Services\Format;
use TawasulOS\Contracts\Services\Payment;
use TawasulOS\Domain\Students\ApplicationFormGateway;

$tawasulApplicationFormHash = $_GET['key'] ?? '';
$tawasulApplicationFormID = $_GET['tawasulApplicationFormID'] ?? '';

$page->breadcrumbs->add(__('Application Fee'));

$payment = $container->get(Payment::class);

if (!empty($_GET['return'])) {
    $payment->setForeignTable('tawasulApplicationForm', $tawasulApplicationFormID);
    $page->return->addReturns($payment->getReturnMessages());
    return;
}

if ($tawasulApplicationFormHash == '') {
    echo Format::alert(__('You have not specified one or more required parameters.'));
    return;
}

$application = $container->get(ApplicationFormGateway::class)->selectBy(['tawasulApplicationFormHash' => $tawasulApplicationFormHash])->fetch();
if (empty($application)) {
    echo Format::alert(__('The specified record does not exist.'));
    return;
}

if (!empty($application['tawasulPaymentID2']) || $application['paymentMade2'] != 'N') {
    echo Format::alert(__('A payment has already been made for this application form.'), 'success');
    return;
}

if (!$payment->isEnabled()) {
    echo Format::alert(__('Online payment options are not available at this time.'));
    return;
}

$settingGateway = $container->get(SettingGateway::class);
$paymentGateway = $settingGateway->getSettingByScope('System', 'paymentGateway');
$currency = $settingGateway->getSettingByScope('System', 'currency');
$applicationProcessFee = $settingGateway->getSettingByScope('Application Form', 'applicationProcessFee');

$form = Form::create('action', $session->get('absoluteURL').'/modules/TawasulStudents/applicationForm_payFeeProcess.php');
            
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('key', $tawasulApplicationFormHash);
$form->addHiddenValue('tawasulApplicationFormID', $application['tawasulApplicationFormID']);

$form->addRow()->addHeading('Application Fee', __('Application Fee'));

$row = $form->addRow()->addContent(sprintf(__('Payment can be made by credit card, using our secure {gateway} payment gateway. When you press Pay Online Now, you will be directed to {gateway} in order to make payment. During this process we do not see or store your credit card details. Once the transaction is complete you will be returned to %1$s.', ['gateway' => $paymentGateway]), $session->get('systemName')))->wrap('<p class="my-2">', '</p>');

$row = $form->addRow();
    $row->addLabel('tawasulApplicationFormIDLabel', __('Application ID'));
    $row->addTextField('tawasulApplicationFormID')->readOnly()->setValue($application['tawasulApplicationFormID']);

$row = $form->addRow();
    $row->addLabel('applicationProcessFeeLabel', __('Application Processing Fee'));
    $row->addTextField('applicationProcessFee')->readOnly()->setValue($currency.$applicationProcessFee);

$row = $form->addRow();
    $row->addSubmit(__('Pay Online Now'));

echo $form->getOutput();
