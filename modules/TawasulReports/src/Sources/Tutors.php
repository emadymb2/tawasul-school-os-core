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

namespace Tos\Module\TawasulReports\Sources;

use Tos\Module\TawasulReports\DataSource;
use TawasulOS\Services\Format;

class Tutors extends DataSource
{
    public function getSchema()
    {
        $gender = rand(0, 99) > 50 ? 'female' : 'male';
        return [
            0 => [
                'title'         => ['title', $gender],
                'surname'       => ['lastName'],
                'firstName'     => ['firstName', $gender],
                'preferredName' => ['sameAs', 'firstName'],
                'officialName'  => ['sameAs', 'firstName surname'],
                'fullName'      => ['sameAs', 'title firstName surname'],
                'email'         => ['sameAs', 'firstName.surname@example.com'],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID']];
        $sql = "SELECT tutor.tawasulPersonID, tutor.title, tutor.surname, tutor.firstName, tutor.preferredName,  tutor.officialName, tutor.email
                FROM tawasulStudentEnrolment
                JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                JOIN tawasulPerson as tutor ON (
                    tutor.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor 
                    OR tutor.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor2 
                    OR tutor.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor3)
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                ORDER BY tutor.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor DESC, tutor.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor2 DESC";

        $values = $this->db()->select($sql, $data)->fetchGroupedUnique();

        $values = array_map(function ($tutor) {
            $tutor['fullName'] = Format::name($tutor['title'], $tutor['preferredName'], $tutor['surname'], 'Staff', false, false);
            return $tutor;
        }, $values);

        return $values;
    }
}
