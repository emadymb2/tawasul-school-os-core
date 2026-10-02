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
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;
use TawasulOS\Http\Url;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['comment' => 'HTML']);

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
$tawasulPlannerEntryHomeworkID = $_GET['tawasulPlannerEntryHomeworkID'] ?? '';
$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';

$URL = Url::fromModuleRoute('TawasulCrowdAssessment', 'crowdAssess_view_discuss')
    ->withQueryParams([
        'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
        'tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID,
        'tawasulPersonID' => $tawasulPersonID,
    ]);

if (isActionAccessible($guid, $connection2, '/modules/TawasulCrowdAssessment/crowdAssess_view_discuss_post.php') == false) {
    header('Location: ' . $URL->withReturn('error0'));
} else {
    //Proceed!
    //Check if tawasulPlannerEntryID, tawasulPlannerEntryHomeworkID, and tawasulPersonID specified
    if ($tawasulPlannerEntryID == '' or $tawasulPlannerEntryHomeworkID == '' or $tawasulPersonID == '') {
        header('Location: ' . $URL->withReturn('error1'));
    } else {
        $and = " AND tawasulPlannerEntryID=$tawasulPlannerEntryID";
        $sql = getLessons($guid, $connection2, $and);
        try {
            $result = $connection2->prepare($sql[1]);
            $result->execute($sql[0]);
        } catch (PDOException $e) {
            header('Location: ' . $URL->withReturn('error2'));
            exit();
        }

        if ($result->rowCount() != 1) {
            header('Location: ' . $URL->withReturn('error1'));
        } else {
            $row = $result->fetch();

            $comment = $_POST['comment'] ?? '';
            $role = getCARole($guid, $connection2, $row['tawasulCourseClassID']);

            if ($role == '' or empty($comment)) {
                header('Location: ' . $URL->withReturn('error2'));
            } else {
                $sqlList = getStudents($guid, $connection2, $role, $row['tawasulCourseClassID'], $row['homeworkCrowdAssessOtherTeachersRead'], $row['homeworkCrowdAssessOtherParentsRead'], $row['homeworkCrowdAssessSubmitterParentsRead'], $row['homeworkCrowdAssessClassmatesParentsRead'], $row['homeworkCrowdAssessOtherStudentsRead'], $row['homeworkCrowdAssessClassmatesRead'], " AND tawasulPerson.tawasulPersonID=$tawasulPersonID");

                if ($sqlList[1] != '') {
                    try {
                        $resultList = $connection2->prepare($sqlList[1]);
                        $resultList->execute($sqlList[0]);
                    } catch (PDOException $e) {
                        header('Location: ' . $URL->withReturn('error2'));
                        exit();
                    }

                    if ($resultList->rowCount() != 1) {
                        header('Location: ' . $URL->withReturn('error2'));
                    } else {
                        //INSERT
                        $replyTo = !empty($_GET['replyTo']) ? $_GET['replyTo'] : null;


                        try {
                            $data = array('tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'comment' => $comment, 'replyTo' => $replyTo);
                            $sql = 'INSERT INTO tawasulCrowdAssessDiscuss SET tawasulPlannerEntryHomeworkID=:tawasulPlannerEntryHomeworkID, tawasulPersonID=:tawasulPersonID, comment=:comment, tawasulCrowdAssessDiscussIDReplyTo=:replyTo';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            header('Location: ' . $URL->withReturn('error2'));
                            exit();
                        }


                        //Work out who we are replying too
                        $replyToID = null;
                        $dataClassGroup = array('tawasulCrowdAssessDiscussID' => $replyTo);
                        $sqlClassGroup = 'SELECT * FROM tawasulCrowdAssessDiscuss WHERE tawasulCrowdAssessDiscussID=:tawasulCrowdAssessDiscussID';
                        $resultClassGroup = $connection2->prepare($sqlClassGroup);
                        $resultClassGroup->execute($dataClassGroup);
                        if ($resultClassGroup->rowCount() == 1) {
                            $rowClassGroup = $resultClassGroup->fetch();
                            $replyToID = $rowClassGroup['tawasulPersonID'];
                        }

                        //Get lesson plan name
                        $dataLesson = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sqlLesson = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                        $resultLesson = $connection2->prepare($sqlLesson);
                        $resultLesson->execute($dataLesson);
                        if ($resultLesson->rowCount() == 1) {
                            $rowLesson = $resultLesson->fetch();
                            $name = $rowLesson['name'];
                        }

                        $homeworkNameSingular = $container->get(SettingGateway::class)->getSettingByScope('Planner', 'homeworkNameSingular');

                        $notificationSender = $container->get(NotificationSender::class);

                        $personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

                        //Create notification for homework owner, as long as it is not me.
                        if ($tawasulPersonID != $session->get('tawasulPersonID') and $tawasulPersonID != $replyToID) {
                            $notificationText = __('{person} has commented on your {homeworkName} for lesson plan "{lessonName}".', ['lessonName' => $name, 'homeworkName' => mb_strtolower(__($homeworkNameSingular)), 'person' => $personName]);
                            $notificationSender->addNotification($tawasulPersonID, $notificationText, 'Crowd Assessment', "/index.php?q=/modules/TawasulCrowdAssessment/crowdAssess_view_discuss.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&tawasulPlannerEntryHomeworkID=$tawasulPlannerEntryHomeworkID&tawasulPersonID=$tawasulPersonID");
                        }

                        //Create notification to person I am replying to
                        if (is_null($replyToID) == false) {
                            $notificationText = __('{person} has replied to a comment on the {homeworkName} for lesson plan "{lessonName}".', ['lessonName' => $name, 'homeworkName' => mb_strtolower(__($homeworkNameSingular)), 'person' => $personName]);
                            $notificationSender->addNotification($replyToID, $notificationText, 'Crowd Assessment', "/index.php?q=/modules/TawasulCrowdAssessment/crowdAssess_view_discuss.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&tawasulPlannerEntryHomeworkID=$tawasulPlannerEntryHomeworkID&tawasulPersonID=$tawasulPersonID");
                        }

                        $notificationSender->sendNotifications();

                        header('Location: ' . $URL->withReturn('success0')->withFragment($replyTo ?? ''));
                    }
                }
            }
        }
    }
}