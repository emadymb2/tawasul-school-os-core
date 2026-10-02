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

require_once __DIR__ . '/tawasul.php';

$tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
$URL = Url::fromRoute();

try {
    $data = array('tawasulPersonIDTutor' => $session->get('tawasulPersonID'), 'tawasulPersonIDTutor2' => $session->get('tawasulPersonID'), 'tawasulPersonIDTutor3' => $session->get('tawasulPersonID'));
    $sql = 'SELECT * FROM tawasulFormGroup WHERE (tawasulPersonIDTutor=:tawasulPersonIDTutor OR tawasulPersonIDTutor2=:tawasulPersonIDTutor2 OR tawasulPersonIDTutor3=:tawasulPersonIDTutor3)';
    $result = $connection2->prepare($sql);
    $result->execute($data);
} catch (PDOException $e) {
    header("Location: {$URL->withReturn('error0')}");
}

if ($result) {
    if ($tawasulFormGroupID == '') {
        header("Location: {$URL->withReturn('error1')}");
    } else {
        if ($result->rowCount() < 1) {
            header("Location: {$URL->withReturn('error3')}");
        } else {
            //Proceed!
            $data = ['tawasulFormGroupID' => $tawasulFormGroupID, 'today' => date('Y-m-d')];
            $sql = "SELECT surname, preferredName, email
                    FROM tawasulStudentEnrolment
                    JOIN tawasulPerson ON tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID
                    WHERE tawasulFormGroupID=:tawasulFormGroupID AND status='Full'
                    AND (dateStart IS NULL OR dateStart<=:today)
                    AND (dateEnd IS NULL  OR dateEnd>=:today)
                    ORDER BY surname, preferredName";

            $result = $pdo->select($sql, $data);

            $exp = new TawasulOS\Excel();
            $exp->exportWithQuery($result, 'classList.xls');
        }
    }
}
