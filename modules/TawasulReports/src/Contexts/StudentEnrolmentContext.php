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

namespace Tos\Module\TawasulReports\Contexts;

use TawasulOS\Services\Format;
use TawasulOS\Contracts\Database\Connection;
use Tos\Module\TawasulReports\DataContext;

class StudentEnrolmentContext implements DataContext
{
    public function getFormatter()
    {
        return function ($values) {
            return Format::nameLinked($values['tawasulPersonID'], '', $values['preferredName'], $values['surname'], 'Student', true, false, ['subpage' => 'Reports']);
        };
    }

    public function getIdentifiers(Connection $db, string $tawasulReportID, string $tawasulYearGroupID)
    {
        $data = ['tawasulReportID' => $tawasulReportID, 'tawasulYearGroupID' => $tawasulYearGroupID];
        $sql = "SELECT tawasulStudentEnrolmentID, tawasulPerson.tawasulPersonID, tawasulPerson.preferredName, tawasulPerson.surname, tawasulFormGroup.nameShort as formGroup
                FROM tawasulReport
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
                JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                WHERE tawasulReport.tawasulReportID=:tawasulReportID 
                AND FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, :tawasulYearGroupID)
                ORDER BY tawasulYearGroup.sequenceNumber, tawasulStudentEnrolment.rollOrder, tawasulPerson.surname, tawasulPerson.preferredName";

        return $db->select($sql, $data)->fetchAll();
    }
}
