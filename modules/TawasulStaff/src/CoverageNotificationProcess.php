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
use TawasulOS\Domain\Staff\StaffCoverageGateway;
use TawasulOS\Domain\Staff\StaffCoverageDateGateway;
use TawasulOS\Domain\Staff\SubstituteGateway;
use Tos\Module\TawasulStaff\Messages\NewCoverage;
use Tos\Module\TawasulStaff\Messages\CoverageAccepted;
use Tos\Module\TawasulStaff\Messages\CoveragePartial;
use Tos\Module\TawasulStaff\Messages\CoverageCancelled;
use Tos\Module\TawasulStaff\Messages\CoverageDeclined;
use Tos\Module\TawasulStaff\Messages\IndividualRequest;
use Tos\Module\TawasulStaff\Messages\BroadcastRequest;
use Tos\Module\TawasulStaff\Messages\NoCoverageAvailable;
use Tos\Module\TawasulStaff\Messages\NewCoverageRequest;
use Tos\Module\TawasulStaff\Messages\NewAbsenceWithCoverage;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use Tos\Module\TawasulStaff\Messages\AbsenceWithCoverageCancelled;

/**
 * CoverageNotificationProcess
 *
 * @version v18
 * @since   v18
 */
class CoverageNotificationProcess extends BackgroundProcess
{
    protected $staffAbsenceGateway;
    protected $staffCoverageGateway;
    protected $staffCoverageDateGateway;
    protected $substituteGateway;
    protected $groupGateway;

    protected $messageSender;
    protected $urgentNotifications;
    protected $internalCoverage;
    protected $urgencyThreshold;
    protected $organisationHR;
    protected $coverageMode;

    public function __construct(
        StaffAbsenceGateway $staffAbsenceGateway,
        StaffCoverageGateway $staffCoverageGateway,
        StaffCoverageDateGateway $staffCoverageDateGateway,
        SubstituteGateway $substituteGateway,
        GroupGateway $groupGateway,
        SettingGateway $settingGateway,
        MessageSender $messageSender
    ) {
        $this->staffAbsenceGateway = $staffAbsenceGateway;
        $this->staffCoverageGateway = $staffCoverageGateway;
        $this->staffCoverageDateGateway = $staffCoverageDateGateway;
        $this->substituteGateway = $substituteGateway;
        $this->groupGateway = $groupGateway;
        $this->messageSender = $messageSender;

        $this->internalCoverage = $settingGateway->getSettingByScope('Staff', 'coverageInternal');
        $this->urgentNotifications = $settingGateway->getSettingByScope('Staff', 'urgentNotifications');
        $this->urgencyThreshold = intval($settingGateway->getSettingByScope('Staff', 'urgencyThreshold')) * 86400;
        $this->organisationHR = $settingGateway->getSettingByScope('System', 'organisationHR');
        $this->coverageMode =  $settingGateway->getSettingByScope('Staff', 'coverageMode');
    }

    public function runNewCoverageRequest($coverageList)
    {
        if (empty($coverageList)) return false;

        $dates = $this->getCoverageDates($coverageList);

        $coverage = $this->getCoverageDetailsByID(current($coverageList));

        $recipients = [$this->organisationHR];
        $message = new NewCoverageRequest($coverage, $dates);

        // Add the absent person, if this coverage request was created by someone else
        if ($coverage['tawasulPersonID'] != $coverage['tawasulPersonIDStatus']) {
            $recipients[] = $coverage['tawasulPersonID'];
        }

        // Add the notification group members, if selected
        if (!empty($coverage['tawasulGroupID'])) {
            $groupRecipients = $this->groupGateway->selectPersonIDsByGroup($coverage['tawasulGroupID'])->fetchAll(\PDO::FETCH_COLUMN, 0);
            $recipients = array_merge($recipients, $groupRecipients);
        }

        if ($sent = $this->messageSender->send($message, $recipients, $coverage['tawasulPersonID'])) {
            $data = [
                'notificationSent' => 'Y',
                'notificationList' => json_encode($recipients),
            ];
            foreach ($coverageList as $tawasulStaffCoverageID) {
                $this->staffCoverageGateway->update($tawasulStaffCoverageID, $data);
            }
            
        }

        return $sent;
    }

    public function runNewAbsenceWithCoverageRequest($coverageList)
    {
        if (empty($coverageList)) return false;

        $dates = $this->getCoverageDates($coverageList);

        $coverage = $this->getCoverageDetailsByID(current($coverageList));
        $absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($coverage['tawasulStaffAbsenceID'] ?? '');

        $message = new NewAbsenceWithCoverage($absence, $coverage, $dates);

        $recipients = !empty($coverage['notificationListAbsence']) ? json_decode($coverage['notificationListAbsence']) : [];
        $recipients[] = $this->organisationHR;

        // Add the absent person, if this coverage request was created by someone else
        if ($coverage['tawasulPersonID'] != $coverage['tawasulPersonIDStatus'] || empty($coverage['tawasulPersonIDApproval'])) {
            $recipients[] = $coverage['tawasulPersonID'];
        }

        // Add the notification group members, if selected
        if (!empty($coverage['tawasulGroupID'])) {
            $groupRecipients = $this->groupGateway->selectPersonIDsByGroup($coverage['tawasulGroupID'])->fetchAll(\PDO::FETCH_COLUMN, 0);
            $recipients = array_merge($recipients, $groupRecipients);
        }

        if ($sent = $this->messageSender->send($message, $recipients, $coverage['tawasulPersonID'])) {
            $data = [
                'status' => $this->coverageMode == 'Assigned' && !empty($coverage['tawasulPersonIDCoverage'])? 'Accepted' : 'Requested',
                'notificationSent' => 'Y',
                'notificationList' => json_encode($recipients),
            ];
            foreach ($coverageList as $tawasulStaffCoverageID) {
                $this->staffCoverageGateway->update($tawasulStaffCoverageID, $data);
            }

            $this->staffAbsenceGateway->update($coverage['tawasulStaffAbsenceID'], [
                'notificationSent' => 'Y',
            ]);
        }

        return $sent;
    }

    public function runApprovedRequest($coverageList)
    {
        if (empty($coverageList)) return false;
        $sent = [];

        foreach ($coverageList as $tawasulStaffCoverageID) {
            $coverage = $this->getCoverageDetailsByID($tawasulStaffCoverageID);

            $sent = $coverage['requestType'] == 'Broadcast'
                ? $this->runBroadcastRequest($tawasulStaffCoverageID)
                : $this->runIndividualRequest($tawasulStaffCoverageID);
        }

        return $sent;
    }

    public function runIndividualRequest($tawasulStaffCoverageID)
    {
        $coverage = $this->getCoverageDetailsByID($tawasulStaffCoverageID);
        if (empty($coverage)) return false;

        $recipients = [$coverage['tawasulPersonIDCoverage']];
        $message = new IndividualRequest($coverage);

        if ($sent = $this->messageSender->send($message, $recipients, $coverage['tawasulPersonID'])) {
            $this->staffCoverageGateway->update($tawasulStaffCoverageID, [
                'notificationSent' => 'Y',
                'notificationList' => json_encode($recipients),
            ]);
        }

        return $sent;
    }

    public function runBroadcastRequest($tawasulStaffCoverageID)
    {
        $coverage = $this->getCoverageDetailsByID($tawasulStaffCoverageID);
        if (empty($coverage)) return false;

        $coverageDates = $this->staffCoverageDateGateway->selectDatesByCoverage($tawasulStaffCoverageID)->fetchAll();

        // Get available subs
        $availableSubs = [];
        foreach ($coverageDates as $date) {
            $criteria = $this->substituteGateway
                ->newQueryCriteria()
                ->filterBy('allStaff', $this->internalCoverage == 'Y')
                ->filterBy('substituteTypes', $coverage['substituteTypes']);
            $availableByDate = $this->substituteGateway->queryAvailableSubsByDate($criteria, $date['date'])->toArray();
            $availableSubs = array_merge($availableSubs, $availableByDate);
        }
        
        if ($this->internalCoverage == 'N' || $coverage['urgent'] == true) {
            if (count($availableSubs) > 0) {
                // Send messages to available subs
                $recipients = array_column($availableSubs, 'tawasulPersonID');
                $message = new BroadcastRequest($coverage);
            } else {
                // Send a message to admin - no coverage
                $recipients = [$this->organisationHR];
                $message = new NoCoverageAvailable($coverage);
            }
        }

        if ($sent = $this->messageSender->send($message, $recipients, $coverage['tawasulPersonID'])) {
            $this->staffCoverageGateway->update($tawasulStaffCoverageID, [
                'notificationSent' => 'Y',
                'notificationList' => json_encode($recipients),
            ]);
        }

        return $sent;
    }

    public function runCoverageAccepted($tawasulStaffCoverageID, $uncoveredDates = [])
    {
        $coverage = $this->getCoverageDetailsByID($tawasulStaffCoverageID);
        if (empty($coverage)) return false;

        // Send the coverage accepted message to the requesting staff member
        $recipients = [$coverage['tawasulPersonIDStatus']];
        $message = !empty($uncoveredDates)
            ? new CoveragePartial($coverage, $uncoveredDates)
            : new CoverageAccepted($coverage);

        $sent = $this->messageSender->send($message, $recipients, $coverage['tawasulPersonIDCoverage']);

        // Send a coverage arranged message to the selected staff for this absence
        if (!empty($coverage['tawasulStaffAbsenceID'])) {
            $recipients = !empty($coverage['notificationListAbsence']) ? json_decode($coverage['notificationListAbsence']) : [];
            
            // Add the absent person, if this coverage request was created by someone else
            if ($coverage['tawasulPersonID'] != $coverage['tawasulPersonIDStatus']) {
                $recipients[] = $coverage['tawasulPersonID'];
            }

            // Add the notification group members, if selected
            if (!empty($coverage['tawasulGroupID'])) {
                $groupRecipients = $this->groupGateway->selectPersonIDsByGroup($coverage['tawasulGroupID'])->fetchAll(\PDO::FETCH_COLUMN, 0);
                $recipients = array_merge($recipients, $groupRecipients);
            }

            $message = new NewCoverage($coverage);
            $sent += $this->messageSender->send($message, $recipients, $coverage['tawasulPersonID']);
        }

        return $sent;
    }

    public function runCoverageDeclined($tawasulStaffCoverageID)
    {
        $coverage = $this->getCoverageDetailsByID($tawasulStaffCoverageID);
        if (empty($coverage)) return false;

        $recipients = [$coverage['tawasulPersonIDStatus']];
        if ($coverage['requestType'] == 'Assigned') {
            $recipients[] = $this->organisationHR;
        }

        $message = new CoverageDeclined($coverage);

        return $this->messageSender->send($message, $recipients, $coverage['tawasulPersonIDCoverage']);
    }

    public function runCoverageCancelled($tawasulStaffCoverageID)
    {
        $coverage = $this->getCoverageDetailsByID($tawasulStaffCoverageID);
        if (empty($coverage)) return false;

        $dates = $this->getCoverageDates($tawasulStaffCoverageID);

        $recipients = [$coverage['tawasulPersonIDStatus'], $coverage['tawasulPersonIDCoverage'], $coverage['tawasulPersonID']];
        if ($coverage['requestType'] == 'Assigned') {
            $recipients[] = $this->organisationHR;
        }

        // Add the absence approver, if there was one.
        if (!empty($coverage['tawasulPersonIDApproval'])) {
            $recipients[] = $coverage['tawasulPersonIDApproval'];
        }

        $message = new CoverageCancelled($coverage, $dates);

        return $this->messageSender->send($message, $recipients, $coverage['tawasulPersonID']);
    }

    /**
     * Sends a message to relevant users when an absence with coverage has been cancelled.
     *
     * @param string $tawasulStaffAbsenceID
     * @param array $coverageList
     * @return array
     */
    public function runAbsenceWithCoverageCancelled($tawasulStaffAbsenceID, $coverageList = [])
    {
        $absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($tawasulStaffAbsenceID);
        $dates = $this->getCoverageDates($coverageList);
        $coverage = $this->getCoverageDetailsByID(current($coverageList));

        if (empty($absence) || empty($coverage)) return false;

        // Target the absence message to the selected staff
        $message = new AbsenceWithCoverageCancelled($absence, $coverage, $dates);
        $recipients = !empty($absence['notificationList']) ? json_decode($absence['notificationList']) : [];
        $recipients[] = $absence['tawasulPersonID'];

        // Add the coverage creator, if it is not the same as the absent person
        if ($coverage['tawasulPersonIDStatus'] != $absence['tawasulPersonID']) {
            $recipients[] = $coverage['tawasulPersonIDStatus'];
        }
        
        // If this is assigned coverage, let the manager know
        if ($coverage['requestType'] == 'Assigned') {
            $recipients[] = $this->organisationHR;
        }

        // Add the absence approver, if there is one
        if (!empty($absence['tawasulPersonIDApproval'])) {
            $recipients[] = $absence['tawasulPersonIDApproval'];
        }

        // Add the notification group members, if selected
        if (!empty($absence['tawasulGroupID'])) {
            $groupRecipients = $this->groupGateway->selectPersonIDsByGroup($absence['tawasulGroupID'])->fetchAll(\PDO::FETCH_COLUMN, 0);
            $recipients = array_merge($recipients, $groupRecipients);
        }

        $sent = $this->messageSender->send($message, $recipients, $absence['tawasulPersonID']);

        // Notify the coverage teachers who have received individual requests
        $message = new CoverageCancelled($coverage, $dates);
        $recipients = [];

        // Add any users who have already been assigned to this coverage
        foreach ($dates as $date) {
            if ($date['requestType'] != 'Individual') continue;
            if (empty($date['tawasulPersonIDCoverage'])) continue;
            $recipients[] = $date['tawasulPersonIDCoverage'];
        }

        $sent += $this->messageSender->send($message, $recipients, $absence['tawasulPersonID']);
    

        return $sent;
    }
    
    private function getCoverageDetailsByID($tawasulStaffCoverageID)
    {
        if ($coverage = $this->staffCoverageGateway->getCoverageDetailsByID($tawasulStaffCoverageID)) {
            if ($this->urgentNotifications == 'Y') {
                $relativeSeconds = strtotime($coverage['dateStart']) - time();
                $coverage['urgent'] = $relativeSeconds > 0 && $relativeSeconds <= $this->urgencyThreshold;
            } else {
                $coverage['urgent'] = false;
            }
        }

        return $coverage ?? [];
    }

    private function getCoverageDates($tawasulStaffCoverageID)
    {
        $dates = $this->staffCoverageDateGateway->selectDatesByCoverage($tawasulStaffCoverageID)->toDataSet();

        $coverageByTimetable = count(array_filter($dates->toArray(), function($item) {
            return !empty($item['foreignTableID']);
        }));

        if (!$coverageByTimetable) return $dates;

        $dates->transform(function (&$item) {
            if (empty($item['foreignTableID'])) return;

            $times = $this->staffCoverageDateGateway->getCoverageTimesByForeignTable($item['foreignTable'], $item['foreignTableID'], $item['date']);

            $item['period'] = $times['period'] ?? '';
            $item['contextName'] = $times['contextName'] ?? '';
        });

        return $dates;
    }
}
