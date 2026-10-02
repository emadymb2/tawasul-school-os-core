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

use TawasulOS\Data\Validator;
use TawasulOS\Domain\Library\LibraryGateway;
use TawasulOS\Domain\Library\LibraryItemGateway;
use TawasulOS\Domain\Library\LibraryItemEventGateway;
use TawasulOS\Domain\System\SettingGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$urlParams = [
    'name'                     => $_POST['name'] ?? $_POST['itemID'] ?? '',
    'tawasulLibraryTypeID'      => $_POST['tawasulLibraryTypeID'] ?? '',
    'tawasulSpaceID'            => $_POST['tawasulSpaceID'] ?? '',
    'status'                   => $_POST['status'] ?? '',
];

$lendingAction = $_REQUEST['lendingAction'] ?? '';
$tawasulPersonIDStudent = $_REQUEST['tawasulPersonIDStudent'] ?? '';

if (!empty($tawasulPersonIDStudent)) {
    $URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=$tawasulPersonIDStudent&search=&search=&allStudents=&subpage=Library Borrowing&lendingAction=".$lendingAction;
    $URLSuccess = $URL;
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulLibrary/library_lending.php&'.http_build_query($urlParams);
    $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulLibrary/library_lending_item.php&'.http_build_query($urlParams);
}

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_lending.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    if (!empty($tawasulPersonIDStudent) && (empty($lendingAction) || empty($urlParams['name'])) ) {
        header("Location: {$URL}&return=error1");
        exit;
    } 

    $libraryGateway = $container->get(LibraryGateway::class);
    $libraryItemGateway = $container->get(LibraryItemGateway::class);
    $libraryEventGateway = $container->get(LibraryItemEventGateway::class);
    
    $item = $libraryGateway->getByRecordID($urlParams['name'], ['tawasulLibraryItemID']);
    $event = $libraryEventGateway->getActiveEventByBorrower($item['tawasulLibraryItemID'], $tawasulPersonIDStudent);

    if (empty($lendingAction)) {
        // If there is only one result that is an exact match, go directly to the lending log
        header(!empty($urlParams['name']) && !empty($item)
            ? "Location: {$URLSuccess}&tawasulLibraryItemID=".$item['tawasulLibraryItemID']
            : "Location: {$URL}"
        );
        exit;
    } elseif (empty($item)) {
        // If there is no valid record for this action, return a warning
        header("Location: {$URL}&return=warning3");
        exit;
    }

    // Calculate the default loan length
    $loanLength = $container->get(SettingGateway::class)->getSettingByScope('Library', 'defaultLoanLength');
    $loanLength = (is_numeric($loanLength) == false or $loanLength < 0) ? 7 : $loanLength ;
    $loanDate = date('Y-m-d', time() + ($loanLength * 60 * 60 * 24));

    $data = [
        'tawasulLibraryItemID'             => $item['tawasulLibraryItemID'],
        'tawasulPersonIDStatusResponsible' => $tawasulPersonIDStudent,
        'timestampOut'                    => date('Y-m-d H:i:s'),
        'tawasulPersonIDOut'               => $session->get('tawasulPersonID'),
        'returnExpected'                  => $_REQUEST['returnExpected'] ?? $loanDate,
    ];

    if ($lendingAction == 'SignOut' || $lendingAction == 'SignOutOther') {
        if ($item['status'] != 'Available' && $item['status'] != 'Reserved') {
            header("Location: {$URL}&return=warning1");
            exit;
        }

        $data['status'] = 'On Loan';
        $data['type'] = 'Loan';
        
        // Insert or update an On Loan event
        if (!empty($event)) {
            $tawasulLibraryItemEventID = $event['tawasulLibraryItemEventID'];
            $libraryEventGateway->update($event['tawasulLibraryItemEventID'], $data);
        } else {
            $tawasulLibraryItemEventID = $libraryEventGateway->insert($data);
        }

    } elseif ($lendingAction == 'Reserve') {
        if (!empty($event)) {
            header("Location: {$URL}&return=warning1");
            exit;
        }

        $data['status'] = 'Reserved';
        $data['type'] = 'Reserve';

        // Insert a new Reserved event
        $tawasulLibraryItemEventID = $libraryEventGateway->insert($data);

    } elseif ($lendingAction == 'Return') {
        if (empty($event)) {
            header("Location: {$URL}&return=warning1");
            exit;
        }

        // Update the existing records for this event
        $tawasulLibraryItemEventID = $event['tawasulLibraryItemEventID'];
        $libraryEventGateway->update($event['tawasulLibraryItemEventID'], $data + [
            'status'           => 'Returned',
            'timestampReturn'  => date('Y-m-d H:i:s'),
            'tawasulPersonIDIn' => $session->get('tawasulPersonID'),
        ]);

        $data['status'] = 'Available';
        $data['tawasulPersonIDStatusResponsible'] = null;
    } else {
        header("Location: {$URL}&return=error1");
        exit;
    }

    // Update the library item record
    $updated = $libraryItemGateway->update($item['tawasulLibraryItemID'], [
        'status'                          => $data['status'] ?? 'Available',
        'tawasulPersonIDStatusResponsible' => $data['tawasulPersonIDStatusResponsible'] ?? null,
        'tawasulPersonIDStatusRecorder'    => $session->get('tawasulPersonID'),
        'timestampStatus'                 => date('Y-m-d H:i:s'),
        'returnExpected'                  => $data['returnExpected'] ?? null,
    ]);

    $URLSuccess .= !empty($tawasulLibraryItemEventID)
        ? '&return=success0&tawasulLibraryItemID='.$item['tawasulLibraryItemID']
        : '&return=warning1';
    header("Location: {$URLSuccess}");
    exit;
}
