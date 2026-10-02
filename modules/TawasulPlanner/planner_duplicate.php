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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_duplicate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Set variables
        $today = date('Y-m-d');

        $settingGateway = $container->get(SettingGateway::class);
        $homeworkNameSingular = $settingGateway->getSettingByScope('Planner', 'homeworkNameSingular');
        $homeworkNamePlural = $settingGateway->getSettingByScope('Planner', 'homeworkNamePlural');

        //Proceed!
        //Get viewBy, date and class variables
        $params = [];
        $viewBy = null;
        if (isset($_GET['viewBy'])) {
            $viewBy = $_GET['viewBy'] ?? '';
        }
        $subView = null;
        if (isset($_GET['subView'])) {
            $subView = $_GET['subView'] ?? '';
        }
        if ($viewBy != 'date' and $viewBy != 'class') {
            $viewBy = 'date';
        }
        $tawasulCourseClassID = null;
        $date = null;
        $dateStamp = null;
        if ($viewBy == 'date') {
            $date = $_GET['date'] ?? '';
            if (isset($_GET['dateHuman'])) {
                $date = Format::dateConvert($_GET['dateHuman']);
            }
            if ($date == '') {
                $date = date('Y-m-d');
            }
            [$dateYear, $dateMonth, $dateDay] = explode('-', $date);
            $dateStamp = mktime(0, 0, 0, $dateMonth, $dateDay, $dateYear);
            $params += [
                'viewBy' => 'date',
                'date' => $date,
            ];
        } elseif ($viewBy == 'class') {
            $class = null;
            if (isset($_GET['class'])) {
                $class = $_GET['class'] ?? '';
            }
            $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
            $params += [
                'viewBy' => 'class',
                'date' => $class,
                'tawasulCourseClassID' => $tawasulCourseClassID,
                'subView' => $subView,
            ];
        }

        [$todayYear, $todayMonth, $todayDay] = explode('-', $today);
        $todayStamp = mktime(12, 0, 0, $todayMonth, $todayDay, $todayYear);

        ///Check if tawasulPlannerEntryID and tawasulCourseClassID specified
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? ''; 
        $tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
        if ($tawasulPlannerEntryID == '' or ($viewBy == 'class' and $tawasulCourseClassID == 'Y')) {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            try {
                if ($viewBy == 'date') {
                    $data = array('date' => $date, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                    $sql = 'SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, tawasulPlannerEntry.homework, tawasulPlannerEntry.homeworkSubmission FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE date=:date AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                } else {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                    $sql = 'SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, tawasulPlannerEntry.homework, tawasulPlannerEntry.homeworkSubmission FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                $page->breadcrumbs
                    ->add(__('Duplicate Lesson Plan'));

                $otherYearDuplicateSuccess = !empty($_GET['return']) && $_GET['return'] == 'success0';
                //Deal with duplicate to other year
                $returns = array();
                $returns['success0'] = __('Your request was completed successfully, but the target class is in another year, so you cannot see the results here.');
                $page->return->addReturns($returns);
                if ($otherYearDuplicateSuccess != true) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                }
            } else {
                //Let's go!
                $values = $result->fetch();

                // target of the planner
                $target = ($viewBy === 'class') ? $values['course'].'.'.$values['class'] : Format::date($date);

                $page->breadcrumbs
                    ->add(__('Planner for {classDesc}', [
                        'classDesc' => $target,
                    ]), 'planner.php', $params)
                    ->add(__('Duplicate Lesson Plan'));

                $step = null;
                if (isset($_GET['step'])) {
                    $step = $_GET['step'] ?? '';
                }
                if ($step != 1 and $step != 2) {
                    $step = 1;
                }

                if ($step == 1) {
                    echo "<p>".__('This process will duplicate all aspects of the selected lesson. If a lesson is copied into another course, Smart Block content will be added into the lesson body, so it does not get left out.')."</p>";

                    $form = Form::create('action', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/planner_duplicate.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&viewBy=$viewBy&tawasulCourseClassID=$tawasulCourseClassID&date=$date&step=2");

                    $form->addHiddenValue('viewBy', $viewBy);
                    $form->addHiddenValue('tawasulPlannerEntryID_org',  $tawasulPlannerEntryID);
                    $form->addHiddenValue('subView', $subView);
                    $form->addHiddenValue('address', $session->get('address'));

                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                    $sql = 'SELECT tawasulSchoolYearID AS value, name FROM tawasulSchoolYear WHERE sequenceNumber>=(SELECT sequenceNumber FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID) ORDER BY sequenceNumber';
                    $row = $form->addRow();
                        $row->addLabel('tawasulSchoolYearID', __('Target Year'));
                        $row->addSelect('tawasulSchoolYearID')->fromQuery($pdo, $sql, $data)->required()->placeholder()->selected($session->get('tawasulSchoolYearID'));


                    if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'groupBy' => __('My Classes'), 'groupByAll' => __('All Classes'));
                        $sql = 'SELECT (CASE WHEN tawasulCourseClassPersonID IS NOT NULL THEN :groupBy ELSE :groupByAll END) as groupBy, tawasulSchoolYear.tawasulSchoolYearID AS chainedTo, tawasulCourseClass.tawasulCourseClassID AS value, CONCAT(tawasulCourse.nameShort,".",tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass 
                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) 
                        JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) 
                        LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID)
                        WHERE tawasulSchoolYear.sequenceNumber>=(SELECT sequenceNumber FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID) ORDER BY tawasulCourseClassPersonID IS NULL, tawasulSchoolYear.tawasulSchoolYearID, name';
                    } else {
                        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'groupBy' => __('My Classes'));
                        $sql = 'SELECT :groupBy as groupBy, tawasulSchoolYear.tawasulSchoolYearID AS chainedTo, tawasulCourseClass.tawasulCourseClassID AS value, CONCAT(tawasulCourse.nameShort,".",tawasulCourseClass.nameShort) AS name FROM tawasulCourseClassPerson JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulSchoolYear.sequenceNumber>=(SELECT sequenceNumber FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID) AND tawasulPersonID=:tawasulPersonID ORDER BY name';
                    }
                    $row = $form->addRow();
                        $row->addLabel('tawasulCourseClassID', __('Target Class'));
                        $row->addSelect('tawasulCourseClassID')->fromQueryChained($pdo, $sql, $data, 'tawasulSchoolYearID', 'groupBy')->required()->placeholder();

                    //DUPLICATE MARKBOOK COLUMN?

                        $dataMarkbook = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sqlMarkbook = 'SELECT * FROM tawasulMarkbookColumn WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                        $resultMarkbook = $connection2->prepare($sqlMarkbook);
                        $resultMarkbook->execute($dataMarkbook);
                    if ($resultMarkbook->rowCount() >= 1) {
                        $row = $form->addRow();
                            $row->addLabel('duplicate', __('Duplicate Markbook Columns?'))->description(__('Will duplicate any columns linked to this lesson.'));
                            $row->addYesNo('duplicate')->selected('N');
                    }

                    $row = $form->addRow();
                        $row->addFooter();
                        $row->addSubmit(__('Next'));

                    echo $form->getOutput();

                } elseif ($step == 2) {
                    $tawasulPlannerEntryID_org = $_POST['tawasulPlannerEntryID_org'] ?? '';
                    $tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
                    $tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
                    $duplicate = null;
                    if (isset($_POST['duplicate'])) {
                        $duplicate = $_POST['duplicate'] ?? '';
                    }
                    if ($tawasulCourseClassID == '' or $tawasulSchoolYearID == '') {
                        $page->addError(__('You have not specified one or more required parameters.'));
                    } else {
                        $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/planner_duplicateProcess.php?tawasulPlannerEntryID=$tawasulPlannerEntryID");

                        $form->addHiddenValue('duplicate', $duplicate);
                        $form->addHiddenValue('tawasulPlannerEntryID_org', $tawasulPlannerEntryID_org);
                        $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
                        $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
                        $form->addHiddenValue('viewBy', $viewBy);
                        $form->addHiddenValue('subView', $subView);
                        $form->addHiddenValue('address', $session->get('address'));

                        $class='';
                        try {
                            if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                                $dataSelect = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulSchoolYearID' => $tawasulSchoolYearID);
                                $sqlSelect = 'SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                            } else {
                                $dataSelect = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                                $sqlSelect = 'SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClassPerson JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                            }
                            $resultSelect = $connection2->prepare($sqlSelect);
                            $resultSelect->execute($dataSelect);
                        } catch (PDOException $e) {
                        }
                        if ($resultSelect->rowCount() == 1) {
                            $rowSelect = $resultSelect->fetch();
                            $class = htmlPrep($rowSelect['course']).'.'.htmlPrep($rowSelect['class']);
                        }
                        $row = $form->addRow();
                            $row->addLabel('class', __('Class'));
                            $row->addTextField('class')->setValue($class)->readonly()->required();

                        if ($values['tawasulUnitID'] != '' && $tawasulSchoolYearID == $session->get('tawasulSchoolYearID')) {
                            //KEEP IN UNIT

                                $dataMarkbook = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulUnitID' => $values['tawasulUnitID']);
                                $sqlMarkbook = 'SELECT * FROM tawasulUnitClass WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulUnitID=:tawasulUnitID';
                                $resultMarkbook = $connection2->prepare($sqlMarkbook);
                                $resultMarkbook->execute($dataMarkbook);

                            if ($resultMarkbook->rowCount() == 1) {
                                $rowMarkbook = $resultMarkbook->fetch();
                                $form->addHiddenValue('tawasulUnitClassID', $rowMarkbook['tawasulUnitClassID']);

                                $row = $form->addRow();
                                    $row->addLabel('keepUnit', __('Keep lesson in original unit?'))->description(__('Only available if source and target classes are in the same course.'));
                                    $row->addYesNo('keepUnit')->selected('Y')->required();

                            }
                        }

                        $row = $form->addRow();
                            $row->addLabel('name', __('Name'));
                            $row->addTextField('name')->setValue($values['name'])->maxLength(50)->required();

                        //Try and find the next unplanned slot for this class.

                            $dataNext = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => date('Y-m-d'));
                            $sqlNext = 'SELECT timeStart, timeEnd, date FROM tawasulTTDayRowClass JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) JOIN tawasulTTColumn ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) JOIN tawasulTTDay ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND date>=:date ORDER BY date, timestart LIMIT 0, 10';
                            $resultNext = $connection2->prepare($sqlNext);
                            $resultNext->execute($dataNext);
                        $next = array('date' => null, 'start' => null, 'end' => null, 'date2' => null, 'start2' => null);
                        $nextSet = false;
                        while ($rowNext = $resultNext->fetch()) {
                            if ($nextSet == false) {

                                    $dataPlanner = array('date' => $rowNext['date'], 'timeStart' => $rowNext['timeStart'], 'timeEnd' => $rowNext['timeEnd'], 'tawasulCourseClassID' => $tawasulCourseClassID);
                                    $sqlPlanner = 'SELECT * FROM tawasulPlannerEntry WHERE date=:date AND timeStart=:timeStart AND timeEnd=:timeEnd AND tawasulCourseClassID=:tawasulCourseClassID';
                                    $resultPlanner = $connection2->prepare($sqlPlanner);
                                    $resultPlanner->execute($dataPlanner);
                                if ($resultPlanner->rowCount() == 0) {
                                    $nextSet = true;
                                    $next['date'] = $rowNext['date'];
                                    $next['start'] = $rowNext['timeStart'];
                                    $next['end'] = $rowNext['timeEnd'];
                                }
                            }
                            else {
                                $next['date2'] = $rowNext['date'];
                                $next['start2'] = $rowNext['timeStart'];
                                break;
                            }
                        }
                        $row = $form->addRow();
                            $row->addLabel('date', __('Date'));
                            $row->addDate('date')->setValue(Format::date($next['date']))->required();

                        $row = $form->addRow();
                            $row->addLabel('timeStart', __('Start Time'))->description("Format: hh:mm (24hr)");
                            $row->addTime('timeStart')->setValue(substr($next['start'] ?? '', 0, 5))->required();

                        $row = $form->addRow();
                            $row->addLabel('timeEnd', __('End Time'))->description("Format: hh:mm (24hr)");
                            $row->addTime('timeEnd')->setValue(substr($next['end'] ?? '', 0, 5))->required();

                        if ($values['homework'] == 'Y') {
                            $form->addRow()->addHeading($homeworkNamePlural, __($homeworkNamePlural));

                            $row = $form->addRow();
                                $row->addLabel('homeworkDueDate', __('{homeworkName} Due Date', ['homeworkName' => __($homeworkNameSingular)]));
                                $row->addDate('homeworkDueDate')->setValue(Format::date($next['date2']))->required();

                            $row = $form->addRow();
                                $row->addLabel('homeworkDueDateTime', __('{homeworkName} Due Date Time', ['homeworkName' => __($homeworkNameSingular)]))->description("Format: hh:mm (24hr)");
                                $row->addTime('homeworkDueDateTime')->setValue(substr($next['start2'] ?? '', 0, 5))->required();

                            if ($values['homeworkSubmission'] == 'Y') {
                                $row = $form->addRow();
                                    $row->addLabel('homeworkSubmissionDateOpen', __('Submission Open Date'));
                                    $row->addDate('homeworkSubmissionDateOpen')->setValue(Format::date($next['date']))->required();
                            }
                        }

                        $row = $form->addRow();
                            $row->addFooter();
                            $row->addSubmit();

                        echo $form->getOutput();
                    }
                }
            }
        }
        //Print sidebar
        $session->set('sidebarExtra', sidebarExtra($guid, $connection2, $todayStamp, $session->get('tawasulPersonID'), $dateStamp, $tawasulCourseClassID));
    }
}
