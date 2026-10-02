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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\School\SchoolYearTermGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_view_register.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $page->breadcrumbs
            ->add(__('View Activities'), 'activities_view.php')
            ->add(__('Activity Registration'));

        if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_view_register') == false) {
            //Acess denied
            $page->addError(__('You do not have access to this action.'));
        } else {

            $settingGateway = $container->get(SettingGateway::class);
            $activityGateway = $container->get(ActivityGateway::class);

            //Get current role category
            $roleCategory = $session->get('tawasulRoleIDCurrentCategory');
            $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');

            //Check access controls
            $access = $settingGateway->getSettingByScope('Activities', 'access');

            $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
            $search = $_GET['search'] ?? '';

            if ($access != 'Register') {
                echo "<div class='error'>";
                echo __('Registration is closed, or you do not have permission to register.');
                echo '</div>';
            } else {
                //Check if tawasulActivityID specified
                $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
                if ($tawasulActivityID == 'Y') {
                    $page->addError(__('You have not specified one or more required parameters.'));
                } else {
                    $mode = $_GET['mode'] ?? '';

                    if ($_GET['search'] != '' or $tawasulPersonID != '') {
                        $params = [
                            "tawasulPersonID" => $tawasulPersonID,
                            "search" => $_GET['search'] ?? ''
                        ];
                        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulActivities', 'activities_view.php')->withQueryParams($params));
                    }

                    //Check Access
                    $continue = false;
                    //Student
                    if ($roleCategory == 'Student' and $highestAction == 'View Activities_studentRegister') {

                            $dataStudent = array('tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID);
                            $sqlStudent = 'SELECT * FROM tawasulStudentEnrolment WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID';
                            $resultStudent = $connection2->prepare($sqlStudent);
                            $resultStudent->execute($dataStudent);
                        if ($resultStudent->rowCount() == 1) {
                            $rowStudent = $resultStudent->fetch();
                            $tawasulYearGroupID = intval($rowStudent['tawasulYearGroupID']);
                            if ($tawasulYearGroupID != '') {
                                $continue = true;
                                $and = " AND tawasulYearGroupIDList LIKE '%$tawasulYearGroupID%'";
                            }
                        }
                    }
                    // Parent
                    else if ($roleCategory == 'Parent' and $highestAction == 'View Activities_studentRegisterByParent' and $tawasulPersonID != '') {
                        $children = $container->get(StudentGateway::class)->selectActiveStudentsByFamilyAdult($tawasulSchoolYearID, $session->get('tawasulPersonID'))->fetchGroupedUnique();

                        if (empty($children)) {
                            echo $page->getBlankSlate();
                        } else {

                            if (empty($children[$tawasulPersonID])) {
                                $page->addError(__('You do not have access to this action.'));
                                return;
                            }

                            $tawasulYearGroupID = intval($children[$tawasulPersonID]['tawasulYearGroupID'] ?? 0);
                            if ($tawasulYearGroupID != '') {
                                $continue = true;
                                $and = " AND tawasulYearGroupIDList LIKE '%$tawasulYearGroupID%'";
                            }
                        }
                    }

                    if ($mode == 'register') {
                        if ($continue == false) {
                            $page->addError(__('Your request failed due to a database error.'));
                        } else {
                            $today = date('Y-m-d');

                            //Should we show date as term or date?
                            $dateType = $settingGateway->getSettingByScope('Activities', 'dateType');
                            if ($dateType == 'Term') {
                                $maxPerTerm = $settingGateway->getSettingByScope('Activities', 'maxPerTerm');
                            }

                            try {
                                if ($dateType != 'Date') {
                                    $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulActivityID' => $tawasulActivityID);
                                    $sql = "SELECT tawasulActivity.*, tawasulActivityType.access, tawasulActivityType.maxPerStudent, tawasulActivityType.waitingList, tawasulActivityType.enrolmentType, tawasulActivityType.backupChoice FROM tawasulActivity LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' AND NOT tawasulSchoolYearTermIDList='' AND tawasulActivityID=:tawasulActivityID AND registration='Y' $and";
                                } else {
                                    $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulActivityID' => $tawasulActivityID, 'listingStart' => $today, 'listingEnd' => $today);
                                    $sql = "SELECT tawasulActivity.*, tawasulActivityType.access, tawasulActivityType.maxPerStudent, tawasulActivityType.waitingList, tawasulActivityType.enrolmentType, tawasulActivityType.backupChoice FROM tawasulActivity LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' AND listingStart<=:listingStart AND listingEnd>=:listingEnd AND tawasulActivityID=:tawasulActivityID AND registration='Y' $and";
                                }
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                            }

                            if ($result->rowCount() != 1) {
                                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                            } else {
                                $values = $result->fetch();

                                //Check for existing registration

                                    $dataReg = array('tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                                    $sqlReg = 'SELECT * FROM tawasulActivityStudent WHERE tawasulActivityID=:tawasulActivityID AND tawasulPersonID=:tawasulPersonID';
                                    $resultReg = $connection2->prepare($sqlReg);
                                    $resultReg->execute($dataReg);

                                if (!empty($values['access']) && $values['access'] != 'Register') {
                                    echo "<div class='error'>";
                                    echo __('Registration is closed, or you do not have permission to register.');
                                    echo '</div>';
                                } else if ($resultReg->rowCount() > 0) {
                                    echo "<div class='error'>";
                                    echo __('You are already registered for this activity and so cannot register again.');
                                    echo '</div>';
                                } else {
                                    $page->return->addReturns(['error3' => __('Registration failed because you are already registered in this activity.')]);

                                    //Check registration limit...
                                    $proceed = true;
                                    if ($dateType == 'Term' and $maxPerTerm > 0) {
                                        $termsList = explode(',', $values['tawasulSchoolYearTermIDList']);
                                        foreach ($termsList as $term) {

                                                $dataActivityCount = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearTermIDList' => '%'.$term.'%');
                                                $sqlActivityCount = "SELECT * FROM tawasulActivityStudent JOIN tawasulActivity ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearTermIDList LIKE :tawasulSchoolYearTermIDList AND NOT status='Not Accepted'";
                                                $resultActivityCount = $connection2->prepare($sqlActivityCount);
                                                $resultActivityCount->execute($dataActivityCount);
                                            if ($resultActivityCount->rowCount() >= $maxPerTerm) {
                                                $proceed = false;
                                            }
                                        }
                                    }

                                    $overlapCheck = $activityGateway->getOverlappingActivityTimeSlot($tawasulActivityID, $tawasulPersonID, $dateType)->fetchKeyPair();

                                    if (!empty($overlapCheck)) {
                                        echo Format::alert(__('The timing of this activity conflicts with one or more currently enrolled activities:').' '.Format::bold(implode(',', $overlapCheck)), 'warning');
                                    }

                                    $activityCountByType = $activityGateway->getStudentActivityCountByType($values['type'], $tawasulPersonID);
                                    if ($values['maxPerStudent'] > 0 && $activityCountByType >= $values['maxPerStudent']) {
                                        echo "<div class='error'>";
                                        echo __('You have subscribed for the maximum number of activities of this type, and so cannot register for this activity.');
                                        echo '</div>';
                                    } elseif ($proceed == false) {
                                        echo "<div class='error'>";
                                        echo __('You have subscribed for the maximum number of activities in a term, and so cannot register for this activity.');
                                        echo '</div>';
                                    } else {
                                        // Load the enrolmentType system setting, optionally override with the Activity Type setting
                                        $enrolment = $settingGateway->getSettingByScope('Activities', 'enrolmentType');
                                        $enrolment = !empty($values['enrolmentType'])? $values['enrolmentType'] : $enrolment;

                                        echo '<p>';
                                        if ($enrolment == 'Selection') {
                                            echo __('After you press the Register button below, your application will be considered by a member of staff who will decide whether or not there is space for you in this program.');
                                        } else if ($values['waitingList'] == 'Y') {
                                            echo __('If there is space on this program you will be accepted immediately upon pressing the Register button below. If there is not, then you will be placed on a waiting list.');
                                        }
                                        echo '</p>';

                                        $form = Form::create('courseEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/activities_view_registerProcess.php?search='.$search);

                                        $form->addHiddenValue('address', $session->get('address'));
                                        $form->addHiddenValue('mode', $mode);
                                        $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
                                        $form->addHiddenValue('tawasulActivityID', $tawasulActivityID);

                                        $row = $form->addRow();
                                            $row->addLabel('nameLabel', __('Activity'));
                                            $row->addTextField('name')->readonly();

                                        if ($dateType != 'Date') {
                                            /**
                                             * @var SchoolYearTermGateway
                                             */
                                            $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);
                                            $termList = $schoolYearTermGateway->getTermNamesByID($values['tawasulSchoolYearTermIDList']);

                                            $row = $form->addRow();
                                                $row->addLabel('terms', __('Terms'));
                                                $row->addTextField('terms')->readonly()->setValue(!empty($termList) ? implode(', ', $termList) : '-');
                                        } else {
                                            $row = $form->addRow();
                                                $row->addLabel('programStartLabel', __('Program Start Date'));
                                                $row->addDate('programStart')->readonly();

                                            $row = $form->addRow();
                                                $row->addLabel('programEndLabel', __('Program End Date'));
                                                $row->addDate('programEnd')->readonly();
                                        }

                                        $paymentType = $settingGateway->getSettingByScope('Activities', 'payment');
                                        if ($paymentType != 'None' && $paymentType != 'Single') {
                                            if ($values['payment'] > 0) {
                                                $row = $form->addRow();
                                                $row->addLabel('paymentLabel', __('Cost'))->description(__('For entire programme'));
                                                $row->addCurrency('payment')->readonly();
                                            }
                                        }

                                        // Load the backupChoice system setting, optionally override with the Activity Type setting
                                        $backupChoice = $settingGateway->getSettingByScope('Activities', 'backupChoice');
                                        $backupChoice = !empty($values['backupChoice'])? $values['backupChoice'] : $backupChoice;

                                        if ($backupChoice == 'Y') {
                                            if ($dateType != 'Date') {
                                                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID);
                                                $sql = "SELECT DISTINCT tawasulActivity.tawasulActivityID as value, tawasulActivity.name FROM tawasulActivity JOIN tawasulStudentEnrolment ON (tawasulActivity.tawasulYearGroupIDList LIKE concat( '%', tawasulStudentEnrolment.tawasulYearGroupID, '%' )) WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND NOT tawasulActivityID=:tawasulActivityID AND NOT tawasulSchoolYearTermIDList='' AND active='Y' $and ORDER BY name";
                                            } else {
                                                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID, 'listingStart' => $today, 'listingEnd' => $today);
                                                $sql = "SELECT DISTINCT tawasulActivity.tawasulActivityID as value, tawasulActivity.name FROM tawasulActivity JOIN tawasulStudentEnrolment ON (tawasulActivity.tawasulYearGroupIDList LIKE concat( '%', tawasulStudentEnrolment.tawasulYearGroupID, '%' )) WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND NOT tawasulActivityID=:tawasulActivityID AND listingStart<=:listingStart AND listingEnd>=:listingEnd AND active='Y' $and ORDER BY name";
                                            }
                                            $result = $pdo->executeQuery($data, $sql);

                                            $row = $form->addRow();
                                                $row->addLabel('tawasulActivityIDBackup', __('Backup Choice'))
                                                    ->description(sprintf(__('In case %1$s is full.'), $values['name']));
                                                $row->addSelect('tawasulActivityIDBackup')
                                                    ->fromResults($result)
                                                    ->required($result->rowCount() > 0)
                                                    ->placeholder();
                                        }

                                        $row = $form->addRow();
                                            $row->addSubmit(__('Register'));

                                        $form->loadAllValuesFrom($values);

                                        echo $form->getOutput();
                                    }
                                }
                            }
                        }
                    } elseif ($mode = 'unregister') {
                        if ($continue == false) {
                            $page->addError(__('Your request failed due to a database error.'));
                        } else {
                            $today = date('Y-m-d');

                            //Should we show date as term or date?
                            $dateType = $settingGateway->getSettingByScope('Activities', 'dateType');

                            try {
                                if ($dateType != 'Date') {
                                    $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID);
                                    $sql = "SELECT DISTINCT tawasulActivity.*, tawasulActivityType.access FROM tawasulActivity JOIN tawasulStudentEnrolment ON (tawasulActivity.tawasulYearGroupIDList LIKE concat( '%', tawasulStudentEnrolment.tawasulYearGroupID, '%' )) LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name) WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulActivityID=:tawasulActivityID AND NOT tawasulSchoolYearTermIDList='' AND active='Y' $and";
                                } else {
                                    $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID, 'listingStart' => $today, 'listingEnd' => $today);
                                    $sql = "SELECT DISTINCT tawasulActivity.*, tawasulActivityType.access FROM tawasulActivity JOIN tawasulStudentEnrolment ON (tawasulActivity.tawasulYearGroupIDList LIKE concat( '%', tawasulStudentEnrolment.tawasulYearGroupID, '%' )) LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name) WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulActivityID=:tawasulActivityID AND listingStart<=:listingStart AND listingEnd>=:listingEnd AND active='Y' $and";
                                }
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                            }

                            if ($result->rowCount() != 1) {
                                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                            } else {
                                $values = $result->fetch();

                                //Check for existing registration

                                    $dataReg = array('tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID);
                                    $sqlReg = 'SELECT * FROM tawasulActivityStudent WHERE tawasulActivityID=:tawasulActivityID AND tawasulPersonID=:tawasulPersonID';
                                    $resultReg = $connection2->prepare($sqlReg);
                                    $resultReg->execute($dataReg);

                                if (!empty($values['access']) && $values['access'] != 'Register') {
                                    echo "<div class='error'>";
                                    echo __('Registration is closed, or you do not have permission to register.');
                                    echo '</div>';
                                } elseif ($resultReg->rowCount() < 1) {
                                    echo "<div class='error'>";
                                    echo __('You are not currently registered for this activity and so cannot unregister.');
                                    echo '</div>';
                                } else {
                                    $form = Form::create('courseEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/activities_view_registerProcess.php?search='.$search);
                                    $form->removeClass('smallIntBorder');

                                    $form->addHiddenValue('address', $session->get('address'));
                                    $form->addHiddenValue('mode', $mode);
                                    $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
                                    $form->addHiddenValue('tawasulActivityID', $tawasulActivityID);

                                    $form->addRow()->addContent(sprintf(__('Are you sure you want to unregister from activity "%1$s"? If you try to reregister later you may lose a space already assigned to you.'), $values['name']))->wrap('<strong>', '</strong>');

                                    $row = $form->addRow();
                                        $row->addSubmit(__('Unregister'));

                                    echo $form->getOutput();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
