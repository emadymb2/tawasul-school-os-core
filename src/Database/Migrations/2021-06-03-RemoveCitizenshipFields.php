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
along with this program. If not, see <http: //www.gnu.org/licenses/>.
*/

use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Database\Migrations\Migration;

/**
 * Remove Citizenship Fields Migration - remove the citizenship, id card, visa and residency fields no longer needed.
 */
class RemoveCitizenshipFields extends Migration
{
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }   

    public function migrate()
    {
        $partialFail = false;

        // tawasulPerson
        $fieldPresent = $this->db->select("SHOW COLUMNS FROM `tawasulPerson` LIKE 'citizenship1'");
        if (!empty($fieldPresent) && $fieldPresent->rowCount() > 0) {
            $sql = "ALTER TABLE `tawasulPerson` DROP `citizenship1`, DROP `citizenship1Passport`, DROP `citizenship1PassportExpiry`, DROP `citizenship1PassportScan`, DROP `citizenship2`, DROP `citizenship2Passport`, DROP `citizenship2PassportExpiry`, DROP `nationalIDCardNumber`, DROP `nationalIDCardScan`, DROP `residencyStatus`, DROP `visaExpiryDate`;";

            $success = $this->db->statement($sql);
            $partialFail &= !$success;
        }

        // tawasulPersonUpdate
        $fieldPresent = $this->db->select("SHOW COLUMNS FROM `tawasulPersonUpdate` LIKE 'citizenship1'");
        if (!empty($fieldPresent) && $fieldPresent->rowCount() > 0) {
            $sql = "ALTER TABLE `tawasulPersonUpdate` DROP `citizenship1`, DROP `citizenship1Passport`, DROP `citizenship1PassportExpiry`, DROP `citizenship2`, DROP `citizenship2Passport`, DROP `citizenship2PassportExpiry`, DROP `nationalIDCardCountry`, DROP `nationalIDCardNumber`, DROP `residencyStatus`, DROP `visaExpiryDate`;";

            $success = $this->db->statement($sql);
            $partialFail &= !$success;
        }


        // tawasulApplicationForm
        $fieldPresent = $this->db->select("SHOW COLUMNS FROM `tawasulApplicationForm` LIKE 'citizenship1'");
        if (!empty($fieldPresent) && $fieldPresent->rowCount() > 0) {
            $sql = "ALTER TABLE `tawasulApplicationForm` DROP `citizenship1`, DROP `citizenship1Passport`, DROP `citizenship1PassportExpiry`, DROP `nationalIDCardNumber`, DROP `residencyStatus`, DROP `visaExpiryDate`, DROP `parent1citizenship1`, DROP `parent1nationalIDCardNumber`, DROP `parent1residencyStatus`, DROP `parent1visaExpiryDate`, DROP `parent2citizenship1`, DROP `parent2nationalIDCardNumber`, DROP `parent2residencyStatus`, DROP `parent2visaExpiryDate`;";

            $success = $this->db->statement($sql);
            $partialFail &= !$success;
        }

        // tawasulStaffApplicationForm
        $fieldPresent = $this->db->select("SHOW COLUMNS FROM `tawasulStaffApplicationForm` LIKE 'citizenship1'");
        if (!empty($fieldPresent) && $fieldPresent->rowCount() > 0) {
            $sql = "ALTER TABLE `tawasulStaffApplicationForm` DROP `citizenship1`, DROP `citizenship1Passport`, DROP `nationalIDCardNumber`, DROP `residencyStatus`, DROP `visaExpiryDate`;";

            $success = $this->db->statement($sql);
            $partialFail &= !$success;
        }

        return !$partialFail;
    }
}
