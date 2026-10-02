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

use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\System\AlertLevelGateway;
use TawasulOS\UI\Components\Alert;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonMedicalUpdateID = $_GET['tawasulPersonMedicalUpdateID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/data_medical_manage_edit.php&tawasulPersonMedicalUpdateID=$tawasulPersonMedicalUpdateID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_medical_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulPersonMedicalUpdateID specified
    if ($tawasulPersonMedicalUpdateID == '' or $tawasulPersonID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        $medicalGateway = $container->get(MedicalGateway::class);

        try {
            $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
            $sql = 'SELECT * FROM tawasulPersonMedicalUpdate WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID';
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
            $tawasulPersonMedicalID = $row['tawasulPersonMedicalID'];
            $row2 = $medicalGateway->getByID($tawasulPersonMedicalID);
            $conditions = [];

            //Set values
            $data = array();
            $sqlSet = '';
            if (isset($_POST['longTermMedicationOn'])) {
                if ($_POST['longTermMedicationOn'] == 'on') {
                    $data['longTermMedication'] = $_POST['longTermMedication'] ?? '';
                    $sqlSet .= 'longTermMedication=:longTermMedication, ';
                }
            }
            if (isset($_POST['longTermMedicationDetailsOn'])) {
                if ($_POST['longTermMedicationDetailsOn'] == 'on') {
                    $data['longTermMedicationDetails'] = $_POST['longTermMedicationDetails'] ?? '';
                    $sqlSet .= 'longTermMedicationDetails=:longTermMedicationDetails, ';
                }
            }
            if (isset($_POST['commentOn'])) {
                if ($_POST['commentOn'] == 'on') {
                    $data['comment'] = $_POST['comment'] ?? '';
                    $sqlSet .= 'comment=:comment, ';
                }
            }

            // CUSTOM FIELDS
            $data['fields'] = $container->get(CustomFieldHandler::class)->getFieldDataFromDataUpdate('Medical Form', [], $row2['fields'] ?? []);
            $sqlSet .= 'fields=:fields, ';

            $partialFail = false;

            //Write to database
            //If form already exisits
            $count = 0;
            $count2 = 0;
            if ($_POST['formExists'] == true) {
                //Scan through existing conditions
                if (isset($_POST['count'])) {
                    $count = $_POST['count'] ?? '';
                }
                for ($i = 0; $i < $count; ++$i) {
                    $dataCond = array();
                    $sqlSetCond = '';
                    if (isset($_POST["nameOn$i"])) {
                        if ($_POST["nameOn$i"] == 'on') {
                            $dataCond['name'] = $_POST["name$i"] ?? '';
                            $sqlSetCond .= 'name=:name, ';
                        }
                    }
                    if (isset($_POST["tawasulAlertLevelIDOn$i"])) {
                        if ($_POST["tawasulAlertLevelIDOn$i"] == 'on') {
                            if ($_POST["tawasulAlertLevelID$i"] != '') {
                                $dataCond['tawasulAlertLevelID'] = $_POST["tawasulAlertLevelID$i"] ?? '';
                                $sqlSetCond .= 'tawasulAlertLevelID=:tawasulAlertLevelID, ';

                                if (!empty($_POST["tawasulPersonMedicalConditionID$i"]) && ($dataCond['tawasulAlertLevelID'] == '001' || $dataCond['tawasulAlertLevelID'] == '002')) {
                                    $condition = $medicalGateway->getMedicalConditionByID($_POST["tawasulPersonMedicalConditionID$i"]);
                                    $conditions[] = $condition['name'] ?? '';
                                }
                            }
                        }
                    }
                    if (isset($_POST["triggersOn$i"])) {
                        if ($_POST["triggersOn$i"] == 'on') {
                            $dataCond['triggers'] = $_POST["triggers$i"] ?? '';
                            $sqlSetCond .= 'triggers=:triggers, ';
                        }
                    }
                    if (isset($_POST["reactionOn$i"])) {
                        if ($_POST["reactionOn$i"] == 'on') {
                            $dataCond['reaction'] = $_POST["reaction$i"] ?? '';
                            $sqlSetCond .= 'reaction=:reaction, ';
                        }
                    }
                    if (isset($_POST["responseOn$i"])) {
                        if ($_POST["responseOn$i"] == 'on') {
                            $dataCond['response'] = $_POST["response$i"] ?? '';
                            $sqlSetCond .= 'response=:response, ';
                        }
                    }
                    if (isset($_POST["medicationOn$i"])) {
                        if ($_POST["medicationOn$i"] == 'on') {
                            $dataCond['medication'] = $_POST["medication$i"] ?? '';
                            $sqlSetCond .= 'medication=:medication, ';
                        }
                    }
                    if (isset($_POST["lastEpisodeOn$i"])) {
                        if ($_POST["lastEpisodeOn$i"] == 'on') {
                            if ($_POST["lastEpisode$i"] != '') {
                                $dataCond['lastEpisode'] = $_POST["lastEpisode$i"] ?? '';
                                $sqlSetCond .= 'lastEpisode=:lastEpisode, ';
                            } else {
                                $sqlSetCond .= 'lastEpisode=NULL, ';
                            }
                        }
                    }
                    if (isset($_POST["lastEpisodeTreatmentOn$i"])) {
                        if ($_POST["lastEpisodeTreatmentOn$i"] == 'on') {
                            $dataCond['lastEpisodeTreatment'] = $_POST["lastEpisodeTreatment$i"] ?? '';
                            $sqlSetCond .= 'lastEpisodeTreatment=:lastEpisodeTreatment, ';
                        }
                    }
                    if (isset($_POST["commentOn$i"])) {
                        if ($_POST["commentOn$i"] == 'on') {
                            $dataCond['comment'] = $_POST["comment$i"] ?? '';
                            $sqlSetCond .= 'comment=:comment, ';
                        }
                    }

                    if (!empty($_POST["attachmentOn$i"]) && $_POST["attachmentOn$i"] == 'on') {
                        $dataCond['attachment'] = $_POST["attachment$i"] ?? null;
                        $sqlSetCond .= 'attachment=:attachment, ';
                    }

                    try {
                        $dataCond['tawasulPersonMedicalID'] = $tawasulPersonMedicalID;
                        $dataCond['tawasulPersonMedicalConditionID'] = $_POST["tawasulPersonMedicalConditionID$i"] ?? '';
                        $sqlCond = "UPDATE tawasulPersonMedicalCondition SET $sqlSetCond tawasulPersonMedicalID=:tawasulPersonMedicalID WHERE tawasulPersonMedicalConditionID=:tawasulPersonMedicalConditionID";
                        $resultCond = $connection2->prepare($sqlCond);
                        $resultCond->execute($dataCond);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }

                //Scan through new conditions
                $tawasulAlertLevelID = 001;
                if (isset($_POST['count2'])) {
                    $count2 = $_POST['count2'] ?? '';
                }
                for ($i = ($count + 1); $i <= ($count + $count2); ++$i) {
                    if (isset($_POST["nameOn$i"]) && $_POST["nameOn$i"] == 'on' && $_POST["tawasulPersonMedicalConditionUpdateID$i"] != '') {
                        $dataCond = array();
                        $sqlSetCond = '';
                        if (isset($_POST["nameOn$i"])) {
                            if ($_POST["nameOn$i"] == 'on') {
                                $dataCond['name'] = $_POST["name$i"] ?? '';
                                $sqlSetCond .= 'name=:name, ';
                            }
                        }
                        if (isset($_POST["tawasulAlertLevelIDOn$i"])) {
                            if ($_POST["tawasulAlertLevelIDOn$i"] == 'on') {
                                if ($_POST["tawasulAlertLevelID$i"] != '') {
                                    $dataCond['tawasulAlertLevelID'] = $_POST["tawasulAlertLevelID$i"] ?? '';
                                    $sqlSetCond .= 'tawasulAlertLevelID=:tawasulAlertLevelID, ';

                                    if (!empty($_POST["name$i"]) && ($dataCond['tawasulAlertLevelID'] == '001' || $dataCond['tawasulAlertLevelID'] == '002')) {
                                        $tawasulAlertLevelID = $dataCond['tawasulAlertLevelID'];
                                        $conditions[] = $_POST["name$i"] ?? '';
                                    }
                                }
                            }
                        }
                        if (isset($_POST["triggersOn$i"])) {
                            if ($_POST["triggersOn$i"] == 'on') {
                                $dataCond['triggers'] = $_POST["triggers$i"] ?? '';
                                $sqlSetCond .= 'triggers=:triggers, ';
                            }
                        }
                        if (isset($_POST["reactionOn$i"])) {
                            if ($_POST["reactionOn$i"] == 'on') {
                                $dataCond['reaction'] = $_POST["reaction$i"] ?? '';
                                $sqlSetCond .= 'reaction=:reaction, ';
                            }
                        }
                        if (isset($_POST["responseOn$i"])) {
                            if ($_POST["responseOn$i"] == 'on') {
                                $dataCond['response'] = $_POST["response$i"] ?? '';
                                $sqlSetCond .= 'response=:response, ';
                            }
                        }
                        if (isset($_POST["medicationOn$i"])) {
                            if ($_POST["medicationOn$i"] == 'on') {
                                $dataCond['medication'] = $_POST["medication$i"] ?? '';
                                $sqlSetCond .= 'medication=:medication, ';
                            }
                        }
                        if (isset($_POST["lastEpisodeOn$i"])) {
                            if ($_POST["lastEpisodeOn$i"] == 'on') {
                                if ($_POST["lastEpisode$i"] != '') {
                                    $dataCond['lastEpisode'] = $_POST["lastEpisode$i"] ?? '';
                                    $sqlSetCond .= 'lastEpisode=:lastEpisode, ';
                                } else {
                                    $sqlSetCond .= 'lastEpisode=NULL, ';
                                }
                            }
                        }
                        if (isset($_POST["lastEpisodeTreatmentOn$i"])) {
                            if ($_POST["lastEpisodeTreatmentOn$i"] == 'on') {
                                $dataCond['lastEpisodeTreatment'] = $_POST["lastEpisodeTreatment$i"] ?? '';
                                $sqlSetCond .= 'lastEpisodeTreatment=:lastEpisodeTreatment, ';
                            }
                        }
                        if (isset($_POST["commentOn$i"])) {
                            if ($_POST["commentOn$i"] == 'on') {
                                $dataCond['comment'] = $_POST["comment$i"] ?? '';
                                $sqlSetCond .= 'comment=:comment, ';
                            }
                        }

                        if (!empty($_POST["attachmentOn$i"]) && $_POST["attachmentOn$i"] == 'on') {
                            $dataCond['attachment'] = $_POST["attachment$i"] ?? null;
                            $sqlSetCond .= 'attachment=:attachment, ';
                        }

                        try {
                            $dataCond['tawasulPersonMedicalID'] = $tawasulPersonMedicalID;
                            $sqlCond = "INSERT INTO tawasulPersonMedicalCondition SET $sqlSetCond tawasulPersonMedicalID=:tawasulPersonMedicalID";
                            $resultCond = $connection2->prepare($sqlCond);
                            $resultCond->execute($dataCond);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        try {
                            $dataCond = array('tawasulPersonMedicalConditionID' => $connection2->lastInsertID(), 'tawasulPersonMedicalConditionUpdateID' => $_POST["tawasulPersonMedicalConditionUpdateID$i"]);
                            $sqlCond = 'UPDATE tawasulPersonMedicalConditionUpdate SET tawasulPersonMedicalConditionID=:tawasulPersonMedicalConditionID WHERE tawasulPersonMedicalConditionUpdateID=:tawasulPersonMedicalConditionUpdateID';
                            $resultCond = $connection2->prepare($sqlCond);
                            $resultCond->execute($dataCond);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }

                try {
                    $data['tawasulPersonMedicalID'] = $tawasulPersonMedicalID;
                    $data['tawasulPersonID'] = $tawasulPersonID;
                    $sql = "UPDATE tawasulPersonMedical SET $sqlSet tawasulPersonMedicalID=:tawasulPersonMedicalID WHERE tawasulPersonID=:tawasulPersonID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
                        $sql = "UPDATE tawasulPersonMedicalUpdate SET status='Complete' WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=warning1';
                        header("Location: {$URL}");
                        exit();
                    }
                }
            }

            //If form does not already exist
            else {
                try {
                    if ($sqlSet != '') {
                        $data['tawasulPersonID'] = $tawasulPersonID;
                        $sql = 'INSERT INTO tawasulPersonMedical SET tawasulPersonID=:tawasulPersonID, '.substr($sqlSet, 0, (strlen($sqlSet) - 2));
                    } else {
                        $data['tawasulPersonID'] = $tawasulPersonID;
                        $sql = 'INSERT INTO tawasulPersonMedical SET tawasulPersonID=:tawasulPersonID';
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $tawasulPersonMedicalID = $connection2->lastInsertID();

                //Scan through new conditions
                if (isset($_POST['count2'])) {
                    $count2 = $_POST['count2'] ?? '';
                }
                for ($i = ($count + 1); $i <= ($count + $count2); ++$i) {
                    if ($_POST["nameOn$i"] == 'on' and $_POST["tawasulAlertLevelIDOn$i"] == 'on') {
                        //Scan through existing conditions
                        $dataCond = array();
                        $sqlSetCond = '';
                        if (isset($_POST["nameOn$i"])) {
                            if ($_POST["nameOn$i"] == 'on') {
                                $dataCond['name'] = $_POST["name$i"] ?? '';
                                $sqlSetCond .= 'name=:name, ';

                            }
                        }
                        if (isset($_POST["tawasulAlertLevelIDOn$i"])) {
                            if ($_POST["tawasulAlertLevelIDOn$i"] == 'on') {
                                if ($_POST["tawasulAlertLevelID$i"] != '') {
                                    $dataCond['tawasulAlertLevelID'] = $_POST["tawasulAlertLevelID$i"] ?? '';
                                    $sqlSetCond .= 'tawasulAlertLevelID=:tawasulAlertLevelID, ';

                                    if (!empty($_POST["name$i"]) && ($dataCond['tawasulAlertLevelID'] == '001' || $dataCond['tawasulAlertLevelID'] == '002')) {
                                        $tawasulAlertLevelID = $dataCond['tawasulAlertLevelID'];
                                        $conditions[] = $_POST["name$i"] ?? '';
                                    }
                                }
                            }
                        }
                        if (isset($_POST["triggersOn$i"])) {
                            if ($_POST["triggersOn$i"] == 'on') {
                                $dataCond['triggers'] = $_POST["triggers$i"] ?? '';
                                $sqlSetCond .= 'triggers=:triggers, ';
                            }
                        }
                        if (isset($_POST["reactionOn$i"])) {
                            if ($_POST["reactionOn$i"] == 'on') {
                                $dataCond['reaction'] = $_POST["reaction$i"] ?? '';
                                $sqlSetCond .= 'reaction=:reaction, ';
                            }
                        }
                        if (isset($_POST["responseOn$i"])) {
                            if ($_POST["responseOn$i"] == 'on') {
                                $dataCond['response'] = $_POST["response$i"] ?? '';
                                $sqlSetCond .= 'response=:response, ';
                            }
                        }
                        if (isset($_POST["medicationOn$i"])) {
                            if ($_POST["medicationOn$i"] == 'on') {
                                $dataCond['medication'] = $_POST["medication$i"] ?? '';
                                $sqlSetCond .= 'medication=:medication, ';
                            }
                        }
                        if (isset($_POST["lastEpisodeOn$i"])) {
                            if ($_POST["lastEpisodeOn$i"] == 'on') {
                                if ($_POST["lastEpisode$i"] != '') {
                                    $dataCond['lastEpisode'] = $_POST["lastEpisode$i"] ?? '';
                                    $sqlSetCond .= 'lastEpisode=:lastEpisode, ';
                                } else {
                                    $sqlSetCond .= 'lastEpisode=NULL, ';
                                }
                            }
                        }
                        if (isset($_POST["lastEpisodeTreatmentOn$i"])) {
                            if ($_POST["lastEpisodeTreatmentOn$i"] == 'on') {
                                $dataCond['lastEpisodeTreatment'] = $_POST["lastEpisodeTreatment$i"] ?? '';
                                $sqlSetCond .= 'lastEpisodeTreatment=:lastEpisodeTreatment, ';
                            }
                        }
                        if (isset($_POST["commentOn$i"])) {
                            if ($_POST["commentOn$i"] == 'on') {
                                $dataCond['comment'] = $_POST["comment$i"] ?? '';
                                $sqlSetCond .= 'comment=:comment, ';
                            }
                        }

                        if (!empty($_POST["attachmentOn$i"]) && $_POST["attachmentOn$i"] == 'on') {
                            $dataCond['attachment'] = $_POST["attachment$i"] ?? null;
                            $sqlSetCond .= 'attachment=:attachment, ';
                        }

                        try {
                            $dataCond['tawasulPersonMedicalID'] = $tawasulPersonMedicalID;
                            $sqlCond = "INSERT INTO tawasulPersonMedicalCondition SET $sqlSetCond tawasulPersonMedicalID=:tawasulPersonMedicalID";
                            $resultCond = $connection2->prepare($sqlCond);
                            $resultCond->execute($dataCond);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        try {
                            $dataCond = array('tawasulPersonMedicalConditionID' => $connection2->lastInsertID(), 'tawasulPersonMedicalConditionUpdateID' => $_POST["tawasulPersonMedicalConditionUpdateID$i"]);
                            $sqlCond = 'UPDATE tawasulPersonMedicalConditionUpdate SET tawasulPersonMedicalConditionID=:tawasulPersonMedicalConditionID WHERE tawasulPersonMedicalConditionUpdateID=:tawasulPersonMedicalConditionUpdateID';
                            $resultCond = $connection2->prepare($sqlCond);
                            $resultCond->execute($dataCond);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }

                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID);
                        $sql = "UPDATE tawasulPersonMedicalUpdate SET status='Complete' WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&updateReturn=success1';
                        header("Location: {$URL}");
                        exit();
                    }
                }
            }

            // ALERTS: possible change to Medical alert status, recalculate alerts
            $container->get(Alert::class)->recalculateAlerts($tawasulPersonID);

            if (!empty($conditions)) {
                $student = $container->get(StudentGateway::class)->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $tawasulPersonID)->fetch();
                $alert = $container->get(AlertLevelGateway::class)->getByID($tawasulAlertLevelID);

                // Raise a new notification event
                $event = new NotificationEvent('Students', 'Medical Condition');
                $event->addScope('tawasulPersonIDStudent', $student['tawasulPersonID']);
                $event->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);

                $event->setNotificationText(__('{name} has a new or updated medical condition ({condition}) with a {risk} risk level.', [
                    'name' => Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true),
                    'condition' => implode(', ', $conditions),
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
