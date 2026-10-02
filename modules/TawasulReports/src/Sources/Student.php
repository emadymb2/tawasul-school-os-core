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

class Student extends DataSource
{
    public function getSchema()
    {
        $gender = rand(0, 99) > 50 ? 'female' : 'male';
        return [
            'tawasulPersonID'     => ['randomNumber', 8],
            'surname'            => ['lastName'],
            'firstName'          => ['firstName', $gender],
            'preferredName'      => ['sameAs', 'firstName'],
            'officialName'       => ['sameAs', 'firstName surname'],
            'image_240'          => $gender == 'female' ? 'modules/TawasulReports/img/placeholder-female.jpg' : 'modules/TawasulReports/img/placeholder-male.jpg',
            'dob'                => ['date', 'Y-m-d'],
            'email'              => ['safeEmail'],
            'nameInCharacters'   => 'TEST',
            'studentID'          => ['randomNumber', 8],
            'dayType'            => ['randomElement', ['Full Day', 'Half Day']],

            '#'                  => ['randomDigit'], // Random Year Group Number
            '%'                  => ['randomDigit'], // Random Form Group Number

            'tawasulYearGroupID'  => 0,
            'yearGroupName'      => ['sameAs', 'Year #'],
            'yearGroupNameShort' => ['sameAs', 'Y0#'],

            'tawasulFormGroupID'  => 0,
            'formGroupName'      => ['sameAs', 'Y0#.%'],
            'formGroupNameShort' => ['sameAs', 'Y0#.%'],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID']];
        $sql = "SELECT 
                tawasulPerson.tawasulPersonID,
                tawasulPerson.surname,
                tawasulPerson.firstName,
                tawasulPerson.preferredName,
                tawasulPerson.officialName,
                tawasulPerson.image_240,
                tawasulPerson.dob,
                tawasulPerson.email,
                tawasulPerson.nameInCharacters,
                tawasulPerson.studentID,
                tawasulPerson.dayType,
                tawasulPerson.gender,
                tawasulYearGroup.tawasulYearGroupID,
                tawasulYearGroup.name as yearGroupName,
                tawasulYearGroup.nameShort as yearGroupNameShort,
                tawasulFormGroup.tawasulFormGroupID,
                tawasulFormGroup.name as formGroupName,
                tawasulFormGroup.nameShort as formGroupNameShort
                FROM tawasulStudentEnrolment 
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
                JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                WHERE tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID";

        return $this->db()->selectOne($sql, $data);
    }
}
