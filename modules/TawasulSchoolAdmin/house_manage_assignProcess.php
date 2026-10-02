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

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulSchoolAdmin/house_manage_assign.php';
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStudents/report_students_byHouse.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/house_manage_assign.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Validate Inputs

    $tawasulYearGroupIDList = $_POST['tawasulYearGroupIDList'] ?? '';
    $tawasulHouseIDList = $_POST['tawasulHouseIDList'] ?? '';
    $balanceYearGroup = $_POST['balanceYearGroup'] ?? '';
    $balanceGender = $_POST['balanceGender'] ?? '';
    $overwrite = $_POST['overwrite'] ?? '';

    if (empty($tawasulYearGroupIDList) || empty($tawasulHouseIDList) || empty($balanceYearGroup) || empty($balanceGender) || empty($overwrite)) {
        $URL .= "&return=error1";
        header("Location: {$URL}");
        exit;
    } else {
        $partialFail = false;
        $count = 0;

        $tawasulHouseIDList = (is_array($tawasulHouseIDList))? implode(',', $tawasulHouseIDList) : $tawasulHouseIDList;
        $tawasulYearGroupIDList = (is_array($tawasulYearGroupIDList))? implode(',', $tawasulYearGroupIDList) : $tawasulYearGroupIDList;

        $yearGroupArray = ($balanceYearGroup == 'Y')? explode(',', $tawasulYearGroupIDList) : array($tawasulYearGroupIDList);

        foreach ($yearGroupArray as $tawasulYearGroupIDs) {

            if ($overwrite == 'Y') {
                // Grab the applicable houses, start all the counters at 0
                try {
                    $data = array('tawasulHouseIDList' => $tawasulHouseIDList);
                    $sql = "SELECT tawasulHouse.tawasulHouseID as groupBy, tawasulHouse.tawasulHouseID, 0 AS total, 0 as totalM, 0 as totalF
                        FROM tawasulHouse
                        WHERE FIND_IN_SET(tawasulHouse.tawasulHouseID, :tawasulHouseIDList)
                        GROUP BY tawasulHouse.tawasulHouseID
                        ORDER BY RAND()";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $partialFail = true;
                }
            } else {
                // Grab the applicable houses and current totals for this year group (or set of year groups)
                try {
                    $data = array('tawasulHouseIDList' => $tawasulHouseIDList, 'tawasulYearGroupIDs' => $tawasulYearGroupIDs, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'today' => date('Y-m-d'));
                    $sql = "SELECT tawasulHouse.tawasulHouseID as groupBy, tawasulHouse.tawasulHouseID, count(tawasulStudentEnrolment.tawasulPersonID) AS total, count(CASE WHEN tawasulPerson.gender='M' THEN tawasulStudentEnrolment.tawasulPersonID END) as totalM, count(CASE WHEN tawasulPerson.gender='F' THEN tawasulStudentEnrolment.tawasulPersonID END) as totalF
                        FROM tawasulHouse
                            LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID
                                AND tawasulPerson.status='Full'
                                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)
                                AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today) )
                            LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND FIND_IN_SET(tawasulYearGroupID, :tawasulYearGroupIDs) )
                        WHERE FIND_IN_SET(tawasulHouse.tawasulHouseID, :tawasulHouseIDList)
                        GROUP BY tawasulHouse.tawasulHouseID
                        ORDER BY RAND()";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $partialFail = true;
                }
            }

            $houses = ($result->rowCount() > 0)? $result->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

            // Build a closure for getting the tawasulHouseID with the minimum students for a particular group
            $getNextHouse = function($group) use (&$houses) {
                return array_reduce(array_keys($houses), function ($resultID, $currentID) use (&$houses, $group) {
                    $currentValue = $houses[$currentID][$group];
                    $resultValue = $houses[$resultID][$group];

                    return (is_null($resultValue) || $currentValue < $resultValue)? $currentID : $resultID;
                }, key($houses));
            };

            // Grab the list of students
            try {
                $data = array('tawasulYearGroupIDs' => $tawasulYearGroupIDs, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'today' => date('Y-m-d'));
                $sql = "SELECT tawasulStudentEnrolment.tawasulYearGroupID, tawasulPerson.gender, tawasulPerson.tawasulPersonID, tawasulPerson.tawasulHouseID FROM
                        tawasulPerson
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, :tawasulYearGroupIDs)
                        AND tawasulPerson.status='Full'
                        AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)
                        AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)";

                if ($overwrite == 'N') {
                    $sql .= " AND tawasulPerson.tawasulHouseID IS NULL";
                }

                $sql .= " ORDER BY RAND()";

                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $partialFail = true;
            }

            if (!empty($houses) && $result->rowCount() > 0) {

                while ($student = $result->fetch()) {
                    if ($student['gender'] == 'Other' || $student['gender'] == 'Unspecified') {
                        $student['gender'] = random_int(0, 1) == 1? 'M' : 'F';
                    }

                    // Use the closure to grab the next house to fill
                    $group = ($balanceGender == 'Y')? 'total'.$student['gender'] : 'total';
                    $tawasulHouseID = $getNextHouse($group);

                    if ($tawasulHouseID !== $student['tawasulHouseID']) {
                        //Write to database
                        try {
                            $data = array('tawasulPersonID' => $student['tawasulPersonID'], 'tawasulHouseID' => $tawasulHouseID);
                            $sql = 'UPDATE tawasulPerson SET tawasulHouseID=:tawasulHouseID WHERE tawasulPersonID=:tawasulPersonID';
                            $resultUpdate = $connection2->prepare($sql);
                            $resultUpdate->execute($data);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }

                    // Increment the counters so we're filling up each house
                    $houses[$tawasulHouseID]['total']++;
                    $houses[$tawasulHouseID]['total'.$student['gender']]++;
                    $count++;
                }
            }
        }

        if ($partialFail) {
            $URL .= "&return=warning1";
            header("Location: {$URL}");
        } else {
            $URLSuccess .= "&tawasulYearGroupIDList={$tawasulYearGroupIDList}&count={$count}";
            $URLSuccess .= "&return=success0";
            header("Location: {$URLSuccess}");
        }
    }
}
