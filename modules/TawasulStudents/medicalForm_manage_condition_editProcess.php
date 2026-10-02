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

use TawasulOS\FileUploader;
use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\System\AlertLevelGateway;
use TawasulOS\UI\Components\Alert;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonMedicalID = $_GET['tawasulPersonMedicalID'] ?? '';
$tawasulPersonMedicalConditionID = $_GET['tawasulPersonMedicalConditionID'] ?? '';
$search = $_GET['search'] ?? '';
if ($tawasulPersonMedicalID == '' or $tawasulPersonMedicalConditionID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/medicalForm_manage_condition_edit.php&tawasulPersonMedicalID=$tawasulPersonMedicalID&tawasulPersonMedicalConditionID=$tawasulPersonMedicalConditionID&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/medicalForm_manage_condition_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulPersonMedicalConditionID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $medicalGateway = $container->get(MedicalGateway::class);
            $values = $medicalGateway->getMedicalConditionByID($tawasulPersonMedicalConditionID);

            if (empty($values)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Validate Inputs
                $name = $_POST['name'] ?? '';
                $tawasulAlertLevelID = $_POST['tawasulAlertLevelID'] ?? '';
                $triggers = $_POST['triggers'] ?? '';
                $reaction = $_POST['reaction'] ?? '';
                $response = $_POST['response'] ?? '';
                $medication = $_POST['medication'] ?? '';
                if ($_POST['lastEpisode'] == '') {
                    $lastEpisode = null;
                } else {
                    $lastEpisode = !empty($_POST['lastEpisode']) ? Format::dateConvert($_POST['lastEpisode']) : null;
                }
                $lastEpisodeTreatment = $_POST['lastEpisodeTreatment'] ?? '';
                $comment = $_POST['comment'] ?? '';

                // File Upload
                $fileMetaData = null;
                if (!empty($_FILES['attachment']['tmp_name'])) {
                    // Upload the file, return the /uploads relative path
                    $fileUploader = new FileUploader($pdo, $session);
                    $attachment = $fileUploader->uploadFromPost($_FILES['attachment']);

                    if (empty($attachment)) {
                        $URL .= '&return=error3';
                        header("Location: {$URL}");
                        exit;
                    }
                    
                    $fileMetaData = $fileUploader->getFileMetaData($attachment);
                } else {
                    // Remove the attachment if it has been deleted, otherwise retain the original value
                    $attachment = empty($_POST['attachment']) ? '' : $values['attachment'];
                }

                if ($name == '' or $tawasulAlertLevelID == '') {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulPersonMedicalID' => $tawasulPersonMedicalID, 'name' => $name, 'tawasulAlertLevelID' => $tawasulAlertLevelID, 'triggers' => $triggers, 'reaction' => $reaction, 'response' => $response, 'medication' => $medication, 'lastEpisode' => $lastEpisode, 'lastEpisodeTreatment' => $lastEpisodeTreatment, 'comment' => $comment, 'attachment' => $attachment, 'tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID);
                        $sql = 'UPDATE tawasulPersonMedicalCondition SET tawasulPersonMedicalID=:tawasulPersonMedicalID, name=:name, tawasulAlertLevelID=:tawasulAlertLevelID, triggers=:triggers, reaction=:reaction, response=:response, medication=:medication, lastEpisode=:lastEpisode, lastEpisodeTreatment=:lastEpisodeTreatment, comment=:comment, attachment=:attachment WHERE tawasulPersonMedicalConditionID=:tawasulPersonMedicalConditionID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }
                                        
                    // Record file tracking
                    if (!empty($fileMetaData) && !empty($tawasulPersonMedicalConditionID)) {
                        $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulPersonMedicalCondition', $tawasulPersonMedicalConditionID, 'attachment');

                        if (empty($tawasulFileID)) {
                            $URL .= '&return=warning1';
                            header("Location: {$URL}");
                            exit();
                        }
                    }

                     // Handle file deletion when user removes attachment
                    if (empty($attachment) && !empty($values['attachment'])) {
                        $deleted = $container->get(FileHandler::class)->deleteFile('tawasulPersonMedicalCondition', $tawasulPersonMedicalConditionID, 'attachment');
                    }

                    // ALERTS: possible change to Medical alert status, recalculate alerts
                    $container->get(Alert::class)->recalculateAlerts($values['tawasulPersonID']);
                    
                    /**
                     * @var AlertLevelGateway
                     */
                    $alertLevelGateway = $container->get(AlertLevelGateway::class);
                    $alert = $alertLevelGateway->getByID($tawasulAlertLevelID);

                    // Has the medical condition risk changed?
                    if ($values['tawasulAlertLevelID'] != $tawasulAlertLevelID && ($alert['tawasulAlertLevelID'] == '001' || $alert['tawasulAlertLevelID'] == '002')) {
                        $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $values['tawasulPersonID'])->fetch();

                        // Raise a new notification event
                        $event = new NotificationEvent('Students', 'Medical Condition');
                        $event->addScope('tawasulPersonIDStudent', $student['tawasulPersonID']);
                        $event->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);

                        $event->setNotificationText(__('{name} has a new or updated medical condition ({condition}) with a {risk} risk level.', [
                            'name' => Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true),
                            'condition' => $name,
                            'risk' => $alert['name'],
                        ]));
                        $event->setActionLink('/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$student['tawasulPersonID'].'&search=&allStudents=&subpage=Medical');

                        // Send all notifications
                        $sendReport = $event->sendNotifications($pdo, $session);
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
