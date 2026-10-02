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

class Families extends DataSource
{
    public function getSchema()
    {
        return [
            0 => [
                'name'                  => ['name'],
                'nameAddress'           => ['sameAs', 'name'],
                'homeAddress'           => ['streetAddress'],
                'homeAddressDistrict'   => ['village'],
                'homeAddressCountry'    => ['country'],
                'languageHomePrimary'   => ['randomElement', ['English', 'Latin', 'Esperanto', 'Klingon']],
                'languageHomeSecondary' => ['randomElement', ['English', 'Latin', 'Esperanto', 'Klingon', '']],
                'adults' => [
                    0 => [
                        'title'         => ['title', 'female'],
                        'surname'       => ['lastName'],
                        'firstName'     => ['firstName', 'female'],
                        'preferredName' => ['sameAs', 'firstName'],
                        'officialName'  => ['sameAs', 'firstName surname'],
                        'email'         => ['sameAs', 'firstName.surname@example.com'],
                    ],
                    1 => [
                        'title'         => ['title', 'male'],
                        'surname'       => ['lastName'],
                        'firstName'     => ['firstName', 'male'],
                        'preferredName' => ['sameAs', 'firstName'],
                        'officialName'  => ['sameAs', 'firstName surname'],
                        'email'         => ['sameAs', 'firstName.surname@example.com'],
                    ],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID']];
        $sql = "SELECT tawasulFamily.tawasulFamilyID, tawasulFamily.name, tawasulFamily.nameAddress, tawasulFamily.homeAddress, tawasulFamily.homeAddressDistrict, tawasulFamily.homeAddressCountry, tawasulFamily.languageHomePrimary, tawasulFamily.languageHomeSecondary
                FROM tawasulStudentEnrolment 
                JOIN tawasulFamilyChild ON (tawasulStudentEnrolment.tawasulPersonID=tawasulFamilyChild.tawasulPersonID)
                JOIN tawasulFamily ON (tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID)
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID";

        $families = $this->db()->select($sql, $data)->fetchGroupedUnique();
        $tawasulFamilyIDList = implode(',', array_keys($families));

        $data = ['tawasulFamilyIDList' => $tawasulFamilyIDList];
        $sql = "SELECT tawasulFamilyAdult.tawasulFamilyID, adult.tawasulPersonID, adult.title, adult.surname, adult.firstName, adult.preferredName,  adult.officialName, adult.email 
                FROM tawasulFamilyAdult
                JOIN tawasulPerson AS adult ON (tawasulFamilyAdult.tawasulPersonID=adult.tawasulPersonID)
                WHERE FIND_IN_SET(tawasulFamilyAdult.tawasulFamilyID, :tawasulFamilyIDList)";

        $adults = $this->db()->select($sql, $data)->fetchGrouped();

        array_walk($families, function (&$family, $tawasulFamilyID) use (&$adults) {
            $family['languageHomePrimary'] = str_replace(['Chinese (Cantonese)', 'Chinese (Mandarin)'], ['Cantonese', 'Mandarin'], $family['languageHomePrimary']);
            $family['languageHomeSecondary'] = str_replace(['Chinese (Cantonese)', 'Chinese (Mandarin)'], ['Cantonese', 'Mandarin'], $family['languageHomeSecondary']);
            $family['adults'] = $adults[$tawasulFamilyID] ?? [];
        });

        return $families;
    }
}
