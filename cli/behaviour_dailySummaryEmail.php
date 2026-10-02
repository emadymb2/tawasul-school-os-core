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

use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;

require getcwd().'/../tawasul.php';

//Check for CLI, so this cannot be run through browser
$remoteCLIKey = $container->get(SettingGateway::class)->getSettingByScope('System Admin', 'remoteCLIKey');
$remoteCLIKeyInput = $_GET['remoteCLIKey'] ?? null;
if (!(isCommandLineInterface() OR ($remoteCLIKey != '' AND $remoteCLIKey == $remoteCLIKeyInput))) {
    echo __('This script cannot be run from a browser, only via CLI.');
} else {
    $currentDate = date('Y-m-d');

    if (isSchoolOpen($guid, $currentDate, $connection2, true)) {
        $report = '';
        $reportInner = '';

        $partialFail = false;

        try {
            $data = array('date' => $currentDate, 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));

            $sql = "SELECT tawasulFormGroup.nameShort AS formGroup, tawasulStudentEnrolment.tawasulYearGroupID, tawasulBehaviour.tawasulBehaviourID, tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, staff.surname as staffSurname, staff.preferredName as staffPreferredName, tawasulBehaviour.descriptor, tawasulBehaviour.level, tawasulBehaviour.comment, tawasulBehaviour.followup, tawasulBehaviour.timestamp, tawasulBehaviour.type
                    FROM tawasulBehaviour
                    JOIN tawasulPerson ON (tawasulBehaviour.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                    JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    JOIN tawasulPerson as staff ON (tawasulBehaviour.tawasulPersonIDCreator=staff.tawasulPersonID)
                    WHERE tawasulBehaviour.tawasulSchoolYearID=:tawasulSchoolYearID
                    AND tawasulBehaviour.date=:date
                    AND tawasulPerson.status='Full'
                    AND tawasulFormGroup.tawasulSchoolYearID=tawasulBehaviour.tawasulSchoolYearID
                    GROUP BY tawasulBehaviour.tawasulBehaviourID
                    ORDER BY tawasulBehaviour.timestamp";

            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $partialFail = true;
        }

        if ($result && $result->rowCount() > 0) {
            $report .= __('Daily Behaviour Summary').': '.$result->rowCount().' '.__('Records').'<br/><br/>';
            while ($row = $result->fetch()) {

                $studentName = Format::name('', $row['preferredName'], $row['surname'], 'Student', false);
                $staffName = Format::name('', $row['staffPreferredName'], $row['staffSurname'], 'Staff', false, true);

                $report .= date('g:i a', strtotime($row['timestamp'])).' - '.__($row['type']).' '.__('Behaviour').' - '.$row['level'];
                $report .= '<br/>';

                $report .= sprintf(__('%1$s (%2$s) received a report for %3$s from %4$s'), '<b>'.$studentName.'</b>', $row['formGroup'], $row['descriptor'], $staffName);
                $report .= ' &raquo; <a href="'.$session->get('absoluteURL').'/index.php?q=/modules/TawasulBehaviour/behaviour_manage_edit.php&tawasulBehaviourID='.$row['tawasulBehaviourID'].'&tawasulPersonID='.$row['tawasulPersonID'].'&tawasulFormGroupID=&tawasulYearGroupID=&type=">'.__('View').'</a>';

                $report .= '<br/><br/>';
            }

            // Raise a new notification event
            $event = new NotificationEvent('Behaviour', 'Daily Behaviour Summary');

            $event->setNotificationText(__('A Behaviour CLI script has run.').'<br/><br/>'.$report);
            $event->setActionLink('/index.php?q=/modules/TawasulBehaviour/behaviour_pattern.php&minimumCount=1&fromDate='.Format::date($currentDate));

            // Send all notifications
            $sendReport = $event->sendNotifications($pdo, $session);

            // Output the result to terminal
            echo sprintf('Sent %1$s notifications: %2$s inserts, %3$s updates, %4$s emails sent, %5$s emails failed.', $sendReport['count'], $sendReport['inserts'], $sendReport['updates'], $sendReport['emailSent'], $sendReport['emailFailed'])."\n";

        } else {
            // Output the result to terminal
            echo __('There are no records to display.')."\n";
        }
    }
}
