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

namespace Tos\Module\TawasulStaff;

use TawasulOS\Services\BackgroundProcess;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Messenger\GroupGateway;
use Tos\Module\TawasulStaff\MessageSender;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use Tos\Module\TawasulStaff\Messages\NewAbsence;
use Tos\Module\TawasulStaff\Messages\AbsenceApproval;
use Tos\Module\TawasulStaff\Messages\AbsencePendingApproval;
use Tos\Module\TawasulStaff\Messages\NewAbsencePendingApproval;
use Tos\Module\TawasulStaff\Messages\AbsenceCancelled;

/**
 * AbsenceNotificationProcess
 *
 * @version v18
 * @since   v18
 */
class AbsenceNotificationProcess extends BackgroundProcess
{
    protected $staffAbsenceGateway;
    protected $groupGateway;

    protected $messageSender;
    protected $urgentNotifications;
    protected $urgencyThreshold;

    public function __construct(StaffAbsenceGateway $staffAbsenceGateway, GroupGateway $groupGateway, SettingGateway $settingGateway, MessageSender $messageSender)
    {
        $this->staffAbsenceGateway = $staffAbsenceGateway;
        $this->groupGateway = $groupGateway;
        $this->messageSender = $messageSender;

        $this->urgentNotifications = $settingGateway->getSettingByScope('Staff', 'urgentNotifications');
        $this->urgencyThreshold = intval($settingGateway->getSettingByScope('Staff', 'urgencyThreshold')) * 86400;
    }
    
    /**
     * Sends a message to alert users of a new absence in the system. Includes anyone selected in a notification
     * group or optional additional list of people to notify.
     *
     * @param string $tawasulStaffAbsenceID
     * @return array
     */
    public function runNewAbsence($tawasulStaffAbsenceID)
    {
        $absence = $this->getAbsenceDetailsByID($tawasulStaffAbsenceID);
        if (empty($absence)) return false;

        $message = new NewAbsence($absence);

        // Target the absence message to the selected staff
        $recipients = !empty($absence['notificationList']) ? json_decode($absence['notificationList']) : [];

        // Add the notification group members, if selected
        if (!empty($absence['tawasulGroupID'])) {
            $groupRecipients = $this->groupGateway->selectPersonIDsByGroup($absence['tawasulGroupID'])->fetchAll(\PDO::FETCH_COLUMN, 0);
            $recipients = array_merge($recipients, $groupRecipients);
        }

        // Add the absent person
        $recipients[] = $absence['tawasulPersonID'];

        if ($sent = $this->messageSender->send($message, $recipients, $absence['tawasulPersonID'])) {
            $this->staffAbsenceGateway->update($tawasulStaffAbsenceID, [
                'notificationSent' => 'Y',
            ]);
        }

        return $sent;
    }

    /**
     * Sends a message back to a staff member that their absence was approved (or declined).
     *
     * @param string $tawasulStaffAbsenceID
     * @return array
     */
    public function runAbsenceApproval($tawasulStaffAbsenceID)
    {
        $absence = $this->getAbsenceDetailsByID($tawasulStaffAbsenceID);
        if (empty($absence)) return false;
        
        $message = new AbsenceApproval($absence);
        $recipients = [$absence['tawasulPersonID']];

        return $this->messageSender->send($message, $recipients, $absence['tawasulPersonIDApproval']);
    }

    /**
     * Sends a message to the selected approval to notify them of a new absence needing approval.
     *
     * @param string $tawasulStaffAbsenceID
     * @return array
     */
    public function runAbsencePendingApproval($tawasulStaffAbsenceID)
    {
        $absence = $this->getAbsenceDetailsByID($tawasulStaffAbsenceID);
        if (empty($absence)) return false;

        $message = new AbsencePendingApproval($absence);
        $recipients = [$absence['tawasulPersonIDApproval']];

        $sent = $this->messageSender->send($message, $recipients, $absence['tawasulPersonID']);

        $message = new NewAbsencePendingApproval($absence);
        $this->messageSender->send($message, [$absence['tawasulPersonID']]);

        return $sent;
    }

    /**
     * Sends a message to relevant users when an absence has been cancelled.
     *
     * @param string $tawasulStaffAbsenceID
     * @return array
     */
    public function runAbsenceCancelled($tawasulStaffAbsenceID)
    {
        $absence = $this->getAbsenceDetailsByID($tawasulStaffAbsenceID);
        if (empty($absence)) return false;

        // Target the absence message to the selected staff
        $message = new AbsenceCancelled($absence);
        $recipients = !empty($absence['notificationList']) ? json_decode($absence['notificationList']) : [];

        // Add the absence approver, if there is one
        if (!empty($absence['tawasulPersonIDApproval'])) {
            $recipients[] = $absence['tawasulPersonIDApproval'];
        }

        // Add the notification group members, if selected
        if (!empty($absence['tawasulGroupID'])) {
            $groupRecipients = $this->groupGateway->selectPersonIDsByGroup($absence['tawasulGroupID'])->fetchAll(\PDO::FETCH_COLUMN, 0);
            $recipients = array_merge($recipients, $groupRecipients);
        }

        return $this->messageSender->send($message, $recipients, $absence['tawasulPersonID']);
    }

    /**
     * Gets the absence details from a gateway and appends the urgency information based on the Staff settings.
     *
     * @param string $tawasulStaffAbsenceID
     * @return array
     */
    private function getAbsenceDetailsByID($tawasulStaffAbsenceID)
    {
        if ($absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($tawasulStaffAbsenceID)) {
            if ($this->urgentNotifications == 'Y') {
                $relativeSeconds = strtotime($absence['dateStart']) - time();
                $absence['urgent'] = $relativeSeconds <= $this->urgencyThreshold;
            } else {
                $absence['urgent'] = false;
            }
        }

        return $absence ?? [];
    }
}
