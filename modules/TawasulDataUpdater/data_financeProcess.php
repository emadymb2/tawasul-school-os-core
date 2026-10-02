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

use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulFinanceInvoiceeID = $_GET['tawasulFinanceInvoiceeID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/data_finance.php&tawasulFinanceInvoiceeID=$tawasulFinanceInvoiceeID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_finance.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulFinanceInvoiceeID specified
    if ($tawasulFinanceInvoiceeID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Get action with highest precendence
        $highestAction = getHighestGroupedAction($guid, $address, $connection2);
        if ($highestAction == false) {
            $URL .= "&return=error0$params";
            header("Location: {$URL}");
        } else {
            //Check access to person
            $checkCount = 0;
            if ($highestAction == 'Update Finance Data_any') {
                $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulDataUpdater/data_finance.php&tawasulFinanceInvoiceeID='.$tawasulFinanceInvoiceeID;


                    $dataSelect = array('tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID);
                    $sqlSelect = "SELECT surname, preferredName, tawasulPerson.tawasulPersonID, tawasulFinanceInvoicee.* FROM tawasulFinanceInvoicee JOIN tawasulPerson ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID ORDER BY surname, preferredName";
                    $resultSelect = $connection2->prepare($sqlSelect);
                    $resultSelect->execute($dataSelect);
                $checkCount = $resultSelect->rowCount();
                $values = $resultSelect->fetch();
            } else {
                $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulDataUpdater/data_updates.php&tawasulFinanceInvoiceeID='.$tawasulFinanceInvoiceeID;


                    $dataCheck = array('tawasulPersonID' => $session->get('tawasulPersonID'));
                    $sqlCheck = "SELECT tawasulFamilyAdult.tawasulFamilyID, name FROM tawasulFamilyAdult JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID AND childDataAccess='Y' ORDER BY name";
                    $resultCheck = $connection2->prepare($sqlCheck);
                    $resultCheck->execute($dataCheck);
                while ($rowCheck = $resultCheck->fetch()) {

                        $dataCheck2 = array('tawasulFamilyID' => $rowCheck['tawasulFamilyID']);
                        $sqlCheck2 = "SELECT surname, preferredName, tawasulPerson.tawasulPersonID, tawasulFamilyID, tawasulFinanceInvoicee.* FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND tawasulFamilyID=:tawasulFamilyID";
                        $resultCheck2 = $connection2->prepare($sqlCheck2);
                        $resultCheck2->execute($dataCheck2);
                    while ($rowCheck2 = $resultCheck2->fetch()) {
                        if ($tawasulFinanceInvoiceeID == $rowCheck2['tawasulFinanceInvoiceeID']) {
                            ++$checkCount;
                            $values = $rowCheck2;
                        }
                    }
                }
            }

            if ($checkCount < 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Proceed!
                $invoiceTo = $_POST['invoiceTo'] ?? '';
                if ($invoiceTo == 'Company') {
                    $data = [
                        'invoiceTo' => $invoiceTo,
                        'companyName' => $_POST['companyName'] ?? '',
                        'companyContact' => $_POST['companyContact'] ?? '',
                        'companyAddress' => $_POST['companyAddress'] ?? '',
                        'companyEmail' => $_POST['companyEmail'] ?? '',
                        'companyCCFamily' => $_POST['companyCCFamily'] ?? '',
                        'companyPhone' => $_POST['companyPhone'] ?? '',
                        'companyAll' => $_POST['companyAll'] ?? '',
                        'tawasulFinanceFeeCategoryIDList' => $_POST['tawasulFinanceFeeCategoryIDList'] ?? '',
                    ];

                    if ($data['companyAll'] == 'N') {
                        $data['tawasulFinanceFeeCategoryIDList'] = is_array($data['tawasulFinanceFeeCategoryIDList'])
                            ? implode(',', $data['tawasulFinanceFeeCategoryIDList'])
                            : $data['tawasulFinanceFeeCategoryIDList'];
                    }
                } else {
                    $data = [
                        'invoiceTo' => $invoiceTo,
                        'companyName' => '',
                        'companyContact' => '',
                        'companyAddress' => '',
                        'companyEmail' => '',
                        'companyCCFamily' => '',
                        'companyPhone' => '',
                        'companyAll' => '',
                        'tawasulFinanceFeeCategoryIDList' => '',
                    ];
                }

                // COMPARE VALUES: Has the data changed?
                $dataChanged = false;
                foreach ($values as $key => $value) {
                    if (!isset($data[$key])) continue; // Skip fields we don't plan to update
                    if (empty($data[$key]) && empty($value)) continue; // Nulls, false and empty strings should cause no change

                    if ($data[$key] != $value) {
                        $dataChanged = true;
                    }
                }

                // Auto-accept updates where no data had changed
                $data['status'] = $dataChanged ? 'Pending' : 'Complete';
                $data['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
                $data['tawasulPersonIDUpdater'] = $session->get('tawasulPersonID');
                $data['timestamp'] = date('Y-m-d H:i:s');

                //Write to database
                $existing = $_POST['existing'] ?? '';

                if ($existing != 'N') {
                    $data['tawasulFinanceInvoiceeUpdateID'] = $existing;
                    $sql = 'UPDATE tawasulFinanceInvoiceeUpdate SET `status`=:status, tawasulSchoolYearID=:tawasulSchoolYearID, invoiceTo=:invoiceTo, companyName=:companyName, companyContact=:companyContact, companyAddress=:companyAddress, companyEmail=:companyEmail, companyCCFamily=:companyCCFamily, companyPhone=:companyPhone, companyAll=:companyAll, tawasulFinanceFeeCategoryIDList=:tawasulFinanceFeeCategoryIDList, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, timestamp=:timestamp WHERE tawasulFinanceInvoiceeUpdateID=:tawasulFinanceInvoiceeUpdateID';
                } else {
                    $data['tawasulFinanceInvoiceeID'] = $tawasulFinanceInvoiceeID;
                    $sql = 'INSERT INTO tawasulFinanceInvoiceeUpdate SET `status`=:status, tawasulSchoolYearID=:tawasulSchoolYearID, tawasulFinanceInvoiceeID=:tawasulFinanceInvoiceeID, invoiceTo=:invoiceTo, companyName=:companyName, companyContact=:companyContact, companyAddress=:companyAddress, companyEmail=:companyEmail, companyCCFamily=:companyCCFamily, companyPhone=:companyPhone, companyAll=:companyAll, tawasulFinanceFeeCategoryIDList=:tawasulFinanceFeeCategoryIDList, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, timestamp=:timestamp';
                }
                $pdo->statement($sql, $data);

                if ($dataChanged) {
                    // Raise a new notification event
                    $event = new NotificationEvent('Data Updater', 'Finance Data Updates');

                    $event->addRecipient($session->get('organisationDBA'));
                    $event->setNotificationText(__('A finance data update request has been submitted.'));
                    $event->setActionLink('/index.php?q=/modules/TawasulDataUpdater/data_finance_manage.php');

                    $event->sendNotifications($pdo, $session);
                }


                $URLSuccess .= '&return=success0';
                header("Location: {$URLSuccess}");
            }
        }
    }
}
