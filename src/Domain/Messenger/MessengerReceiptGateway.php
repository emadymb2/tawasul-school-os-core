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

namespace TawasulOS\Domain\Messenger;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * MessengerReceiptGateway
 *
 * @version v25
 * @since   v25
 */
class MessengerReceiptGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulMessengerReceipt';
    private static $primaryKey = 'tawasulMessengerReceiptID';
    private static $searchableColumns = [];
    
    /**
     * Queries the list of messages for the Manage Messages page, optionally filtered for the current user.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryMessageRecipients(QueryCriteria $criteria, $tawasulMessengerID, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'tawasulMessenger.tawasulMessengerID', 'tawasulMessenger.status', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.email', 'tawasulPerson.phone1', 'tawasulRole.category as role', 'tawasulMessengerReceipt.tawasulMessengerReceiptID', 'tawasulMessengerReceipt.targetType', 'tawasulMessengerReceipt.contactType', 'tawasulMessengerReceipt.contactDetail', 'tawasulFormGroup.name as formGroup', 'tawasulMessengerReceipt.sent'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulMessenger', 'tawasulMessenger.tawasulMessengerID=tawasulMessengerReceipt.tawasulMessengerID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulMessengerReceipt.tawasulPersonID')
            ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('tawasulMessenger.tawasulMessengerID=:tawasulMessengerID')
            ->bindValue('tawasulMessengerID', $tawasulMessengerID)
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("NOT tawasulMessengerReceipt.targetType='Mailing List'");
        
        $this->unionAllWithCriteria($query, $criteria)
            ->cols([
                'tawasulMessenger.tawasulMessengerID', 'tawasulMessenger.status', 'NULL as title', 'tawasulMessengerMailingListRecipient.surname', 'tawasulMessengerMailingListRecipient.preferredName', 'tawasulMessengerMailingListRecipient.email', 'NULL as phone1', '\'External\' as role', 'tawasulMessengerReceipt.tawasulMessengerReceiptID', 'tawasulMessengerReceipt.targetType', 'tawasulMessengerReceipt.contactType', 'tawasulMessengerReceipt.contactDetail', 'NULL as formGroup', 'tawasulMessengerReceipt.sent'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulMessenger', 'tawasulMessenger.tawasulMessengerID=tawasulMessengerReceipt.tawasulMessengerID')
            ->innerJoin('tawasulMessengerMailingListRecipient', 'tawasulMessengerMailingListRecipient.email=tawasulMessengerReceipt.contactDetail AND tawasulMessengerReceipt.unsubscribeKey=tawasulMessengerMailingListRecipient.key')
            ->where('tawasulMessenger.tawasulMessengerID=:tawasulMessengerID')
            ->bindValue('tawasulMessengerID', $tawasulMessengerID)
            ->where("tawasulMessengerReceipt.targetType='Mailing List'");

        return $this->runQuery($query, $criteria);
    }

    public function selectMessageRecipientList($tawasulMessengerID)
    {
        $data = ['tawasulMessengerID' => $tawasulMessengerID];
        $sql = "SELECT * FROM tawasulMessengerReceipt WHERE tawasulMessengerID=:tawasulMessengerID";

        return $this->db()->select($sql, $data);
    }

    public function deleteRecipientsByID($tawasulMessengerID, $recipientList)
    {
        $recipientList = is_array($recipientList)? implode(',', $recipientList) : $recipientList;

        // Delete individual targets
        $data = ['tawasulMessengerID' => $tawasulMessengerID, 'recipientList' => $recipientList];
        $sql = "DELETE tawasulMessengerTarget FROM tawasulMessengerTarget
                JOIN tawasulMessengerReceipt ON (tawasulMessengerReceipt.tawasulMessengerID=tawasulMessengerTarget.tawasulMessengerID AND tawasulMessengerReceipt.targetType=tawasulMessengerTarget.type COLLATE utf8_general_ci AND tawasulMessengerReceipt.tawasulPersonID=tawasulMessengerTarget.id)
                WHERE tawasulMessengerTarget.tawasulMessengerID=:tawasulMessengerID 
                AND tawasulMessengerTarget.type='Individuals'
                AND FIND_IN_SET(tawasulMessengerReceipt.tawasulMessengerReceiptID, :recipientList)";

        $this->db()->delete($sql, $data);

        // Delete recipients
        $data = ['tawasulMessengerID' => $tawasulMessengerID, 'recipientList' => $recipientList];
        $sql = "DELETE FROM tawasulMessengerReceipt WHERE tawasulMessengerID=:tawasulMessengerID AND FIND_IN_SET(tawasulMessengerReceiptID, :recipientList)";

        return $this->db()->delete($sql, $data);
    }
}
