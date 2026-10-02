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

// TawasulOS system-wide include
require_once __DIR__ . '/tawasul.php';

$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? null;

$session->set('pageLoads', null);
$URL = Url::fromRoute();

// Check for access
if (!$session->has('tawasulPersonID') || !$session->has('tawasulRoleIDCurrent')) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} elseif (empty($tawasulSchoolYearID)) {
    header("Location: {$URL->withReturn('error0')}");
    exit;
} else {

        $data = array('tawasulRoleID' => $session->get('tawasulRoleIDCurrent'));
        $sql = "SELECT futureYearsLogin, pastYearsLogin FROM tawasulRole WHERE tawasulRoleID=:tawasulRoleID";
        $result = $connection2->prepare($sql);
        $result->execute($data);

    //Test to see if username exists and is unique
    if ($result->rowCount() == 1) {
        $row = $result->fetch();

        if ($row['futureYearsLogin'] != 'Y' and $row['pastYearsLogin'] != 'Y') { //NOT ALLOWED DUE TO CONTROLS ON ROLE, KICK OUT!
            header("Location: {$URL->withReturn('error0')}");
            exit();
        } else {
            //Get details on requested school year

                $dataYear = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
                $sqlYear = 'SELECT * FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
                $resultYear = $connection2->prepare($sqlYear);
                $resultYear->execute($dataYear);

            //Get current year sequenceNumber

                $dataYearCurrent = array();
                $sqlYearCurrent = "SELECT * FROM tawasulSchoolYear WHERE status='Current'";
                $resultYearCurrent = $connection2->prepare($sqlYearCurrent);
                $resultYearCurrent->execute($dataYearCurrent);

            //Check number of rows returned.
            //If it is not 1, show error
            if (!($resultYear->rowCount() == 1) && !($resultYearCurrent->rowCount() == 1)) {
                header("Location: {$URL->withReturn('error0')}");
                exit;
            }
            //Else get year details
            else {
                $rowYear = $resultYear->fetch();
                $rowYearCurrent = $resultYearCurrent->fetch();
                if ($row['futureYearsLogin'] != 'Y' and $rowYearCurrent['sequenceNumber'] < $rowYear['sequenceNumber']) { //POSSIBLY NOT ALLOWED DUE TO CONTROLS ON ROLE, CHECK YEAR
                    header("Location: {$URL->withReturn('error0')}");
                    exit();
                } elseif ($row['pastYearsLogin'] != 'Y' and $rowYearCurrent['sequenceNumber'] > $rowYear['sequenceNumber']) { //POSSIBLY NOT ALLOWED DUE TO CONTROLS ON ROLE, CHECK YEAR
                    header("Location: {$URL->withReturn('error0')}");
                    exit();
                } else { //ALLOWED
                    $session->set('tawasulSchoolYearID', $rowYear['tawasulSchoolYearID']);
                    $session->set('tawasulSchoolYearName', $rowYear['name']);
                    $session->set('tawasulSchoolYearSequenceNumber', $rowYear['sequenceNumber']);
                    $session->set('tawasulSchoolYearFirstDay', $rowYear['firstDay']);
                    $session->set('tawasulSchoolYearLastDay', $rowYear['lastDay']);

                    // Clear cached FF actions
                    $session->forget('fastFinderActions');

                    // Clear the main menu from session cache
                    $session->forget('menuMainItems');

                    header("Location: {$URL->withReturn('success0')}");
                }
            }
        }
    }
}
