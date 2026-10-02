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

namespace TawasulOS\Domain\Staff;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByTimestamp;

/**
 * StaffApplicationForm Gateway
 *
 * @version v16
 * @since   v16
 */
class StaffApplicationFormGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByTimestamp;

    private static $tableName = 'tawasulStaffApplicationForm';
    private static $primaryKey = 'tawasulStaffApplicationFormID';

    private static $searchableColumns = ['tawasulStaffApplicationFormID', 'tawasulStaffApplicationForm.preferredName', 'tawasulStaffApplicationForm.surname', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulStaffJobOpening.jobTitle'];
    
    private static $scrubbableKey = 'timestamp';
    private static $scrubbableColumns = ['gender' => null, 'dob' => null, 'email' => null, 'homeAddress' => null, 'homeAddressDistrict' => null, 'homeAddressCountry' => null, 'phone1Type' => null, 'phone1CountryCode' => null, 'phone1' => null, 'countryOfBirth' => null, 'languageFirst' => null, 'languageSecond' => null, 'languageThird' => null, 'notes' => '', 'questions' => '', 'fields' => '', 'referenceEmail1' => '', 'referenceEmail2' => ''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryApplications(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffApplicationForm.tawasulStaffApplicationFormID', 'tawasulStaffApplicationForm.status', 'tawasulStaffApplicationForm.priority', 'tawasulStaffApplicationForm.email', 'tawasulStaffApplicationForm.timestamp', 'milestones', 'tawasulStaffJobOpening.jobTitle', 'tawasulStaffApplicationForm.tawasulPersonID', 'tawasulStaffApplicationForm.surname as applicationSurname', 'tawasulStaffApplicationForm.preferredName as applicationPreferredName', 'tawasulPerson.surname', 'tawasulPerson.preferredName'
            ])
            ->innerJoin('tawasulStaffJobOpening', 'tawasulStaffApplicationForm.tawasulStaffJobOpeningID=tawasulStaffJobOpening.tawasulStaffJobOpeningID')
            ->leftJoin('tawasulPerson', 'tawasulStaffApplicationForm.tawasulPersonID=tawasulPerson.tawasulPersonID');

        return $this->runQuery($query, $criteria);
    }
}
