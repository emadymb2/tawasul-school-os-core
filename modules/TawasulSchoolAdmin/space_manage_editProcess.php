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

$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/space_manage_edit.php&tawasulSpaceID=$tawasulSpaceID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/space_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulSpaceID specified
    if ($tawasulSpaceID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulSpaceID' => $tawasulSpaceID);
            $sql = 'SELECT * FROM tawasulSpace WHERE tawasulSpaceID=:tawasulSpaceID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? '';
            $active = $_POST['active'] ?? '';
            $bookable = $_POST['bookable'] ?? '';
            $capacity = $_POST['capacity'] ?? '';
            $computer = $_POST['computer'] ?? '';
            $computerStudent = $_POST['computerStudent'] ?? '';
            $projector = $_POST['projector'] ?? '';
            $tv = $_POST['tv'] ?? '';
            $dvd = $_POST['dvd'] ?? '';
            $hifi = $_POST['hifi'] ?? '';
            $speakers = $_POST['speakers'] ?? '';
            $iwb = $_POST['iwb'] ?? '';
            $phoneInternal = $_POST['phoneInternal'] ?? '';
            $phoneExternal = preg_replace('/[^0-9+]/', '', $_POST['phoneExternal'] ?? '');
            $comment = $_POST['comment'] ?? '';

            //Validate Inputs
            if ($name == '' or $type == '' or $active == '' or $bookable == '' or $computer == '' or $computerStudent == '' or $projector == '' or $tv == '' or $dvd == '' or $hifi == '' or $speakers == '' or $iwb == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('name' => $name, 'tawasulSpaceID' => $tawasulSpaceID);
                    $sql = 'SELECT * FROM tawasulSpace WHERE name=:name AND NOT tawasulSpaceID=:tawasulSpaceID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() > 0) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('name' => $name, 'type' => $type, 'active' => $active, 'bookable' => $bookable, 'capacity' => $capacity, 'computer' => $computer, 'computerStudent' => $computerStudent, 'projector' => $projector, 'tv' => $tv, 'dvd' => $dvd, 'hifi' => $hifi, 'speakers' => $speakers, 'iwb' => $iwb, 'phoneInternal' => $phoneInternal, 'phoneExternal' => $phoneExternal, 'comment' => $comment, 'tawasulSpaceID' => $tawasulSpaceID);
                        $sql = 'UPDATE tawasulSpace SET name=:name, type=:type, active=:active, bookable=:bookable, capacity=:capacity, computer=:computer, computerStudent=:computerStudent, projector=:projector, tv=:tv, dvd=:dvd , hifi=:hifi, speakers=:speakers, iwb=:iwb, phoneInternal=:phoneInternal, phoneExternal=:phoneExternal, comment=:comment WHERE tawasulSpaceID=:tawasulSpaceID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
