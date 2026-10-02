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

use TawasulOS\Data\Validator;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['comment' => 'HTML']);

//Module includes
include './moduleFunctions.php';

$tawasulPlannerEntryID = $_POST['tawasulPlannerEntryID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/planner_view_full.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&search=".$_POST['search'].($_POST['params'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_view_full.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if planner specified
        if ($tawasulPlannerEntryID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sql = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $row = $result->fetch();

                //INSERT
                $replyTo = !empty($_POST['replyTo']) ? $_POST['replyTo'] : null;
                $comment = $_POST['comment'] ?? '';
                
                try {
                    $dataInsert = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'comment' => $comment, 'replyTo' => $replyTo);
                    $sqlInsert = 'INSERT INTO tawasulPlannerEntryDiscuss SET tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulPersonID=:tawasulPersonID, comment=:comment, tawasulPlannerEntryDiscussIDReplyTo=:replyTo';
                    $resultInsert = $connection2->prepare($sqlInsert);
                    $resultInsert->execute($dataInsert);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                //Work out who we are replying too
                $replyToID = null;
                $dataClassGroup = array('tawasulPlannerEntryDiscussID' => $replyTo);
                $sqlClassGroup = 'SELECT * FROM tawasulPlannerEntryDiscuss WHERE tawasulPlannerEntryDiscussID=:tawasulPlannerEntryDiscussID';
                $resultClassGroup = $connection2->prepare($sqlClassGroup);
                $resultClassGroup->execute($dataClassGroup);
                if ($resultClassGroup->rowCount() == 1) {
                    $rowClassGroup = $resultClassGroup->fetch();
                    $replyToID = $rowClassGroup['tawasulPersonID'];
                }

                // Initialize the notification sender & gateway objects
                $notificationGateway = $container->get(NotificationGateway::class);
                $notificationSender = $container->get(NotificationSender::class);

                $personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

                //Create notification for all people in class except me
                $dataClassGroup = array('tawasulCourseClassID' => $row['tawasulCourseClassID']);
                $sqlClassGroup = "SELECT * FROM tawasulCourseClassPerson INNER JOIN tawasulPerson ON tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID WHERE tawasulCourseClassID=:tawasulCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND (NOT role='Student - Left') AND (NOT role='Teacher - Left') ORDER BY role DESC, surname, preferredName";
                $resultClassGroup = $connection2->prepare($sqlClassGroup);
                $resultClassGroup->execute($dataClassGroup);
                while ($rowClassGroup = $resultClassGroup->fetch()) {
                    if ($rowClassGroup['tawasulPersonID'] != $session->get('tawasulPersonID') and $rowClassGroup['tawasulPersonID'] != $replyToID) {
                        $notificationText = __('{person} has commented on your lesson plan {lessonName}.', ['person' => $personName, 'lessonName' => $row['name']]);

                        $notificationSender->addNotification($rowClassGroup['tawasulPersonID'], $notificationText, 'Planner', "/index.php?q=/modules/TawasulPlanner/planner_view_full.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&viewBy=date&date=".$row['date'].'&tawasulCourseClassID=&search=#chat');
                    }
                }

                $notificationSender->sendNotifications();

                //Create notification to person I am replying to
                if (is_null($replyToID) == false) {
                    $notificationText = __('{person} has replied to a comment you made on lesson plan {lessonName}.', ['person' => $personName, 'lessonName' => $row['name']]);
                    $notificationSender->addNotification($replyToID, $notificationText, 'Planner', "/index.php?q=/modules/TawasulPlanner/planner_view_full.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&viewBy=date&date=".$row['date'].'&tawasulCourseClassID=&search=#chat');

                    $notificationSender->sendNotifications();
                }

                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
