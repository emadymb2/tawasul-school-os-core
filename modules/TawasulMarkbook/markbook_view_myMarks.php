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

use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\School\SchoolYearTermGateway;
use TawasulOS\Domain\Planner\PlannerEntryHomeworkGateway;
use TawasulOS\Domain\Departments\DepartmentGateway;

$page->breadcrumbs->add(__('View Markbook'));

// Lock the file so other scripts cannot call it
if (MARKBOOK_VIEW_LOCK !== sha1( $highestAction . $session->get('tawasulPersonID') ) . date('zWy') ) return;

    // Get settings
    $settingGateway = $container->get(SettingGateway::class);
    $enableEffort = $settingGateway->getSettingByScope('Markbook', 'enableEffort');
    $enableRubrics = $settingGateway->getSettingByScope('Markbook', 'enableRubrics');
    $showStudentAttainmentWarning = $settingGateway->getSettingByScope('Markbook', 'showStudentAttainmentWarning');
    $showStudentEffortWarning = $settingGateway->getSettingByScope('Markbook', 'showStudentEffortWarning');
    $attainmentAltName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
	$effortAltName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');

    $entryCount = 0;
    echo '<p>';
    echo __('This page shows you your academic results throughout your school career. Only subjects with published results are shown.');
    echo '</p>';

    $and = '';
    $and2 = '';
    $dataList = array();
    $dataEntry = array();

    $tawasulDepartmentID = isset($_REQUEST['tawasulDepartmentID']) ? $_REQUEST['tawasulDepartmentID'] : '*';
    if ($tawasulDepartmentID != '*') {
        $dataList['tawasulDepartmentID'] = $tawasulDepartmentID;
        $and .= ' AND tawasulDepartmentID=:tawasulDepartmentID';
    }

    $tawasulSchoolYearID = isset($_REQUEST['tawasulSchoolYearID'])? $_REQUEST['tawasulSchoolYearID'] : $session->get('tawasulSchoolYearID');
    if ($tawasulSchoolYearID != '*') {
        $dataList['tawasulSchoolYearID'] = $tawasulSchoolYearID;
        $and .= ' AND tawasulSchoolYearID=:tawasulSchoolYearID';
    }

    $enableGroupByTerm = $settingGateway->getSettingByScope('Markbook', 'enableGroupByTerm');
    if ($enableGroupByTerm == "Y") {
        $termDefault = '';
        $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);
        $termCurrent = $schoolYearTermGateway->getCurrentTermByDate(date('Y-m-d'));
        $termDefault = (is_array($termCurrent) && $termCurrent['tawasulSchoolYearID'] == $tawasulSchoolYearID) ? $termCurrent['tawasulSchoolYearTermID'] : '' ;
        $tawasulSchoolYearTermID = isset($_REQUEST['tawasulSchoolYearTermID']) ? $_REQUEST['tawasulSchoolYearTermID'] : $termDefault;
        if (!empty($tawasulSchoolYearTermID)) {
            $term = $schoolYearTermGateway->getByID($tawasulSchoolYearTermID);
            $dataEntry['firstDay'] = $term['firstDay'];
            $dataEntry['lastDay'] = $term['lastDay'];
            $and2 .= ' AND completeDate>=:firstDay AND completeDate<=:lastDay';
        }
    }

    $type = isset($_REQUEST['type'])? $_REQUEST['type'] : '';
    if ($type != '') {
        $dataEntry['type'] = $type;
        $and2 .= ' AND type=:type';
    }

    $form = Form::create('filter', $session->get('absoluteURL').'/index.php','get');
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/markbook_view.php');

    $results = $container->get(DepartmentGateway::class)->selectDepartmentsOfTypeLearningArea();
    $rowFilter = $form->addRow();
        $rowFilter->addLabel('tawasulDepartmentID', __('Learning Areas'));
        $rowFilter->addSelect('tawasulDepartmentID')
            ->fromArray(array('*' => __('All Learning Areas')))
            ->fromResults($results)
            ->selected($tawasulDepartmentID);

    $dataSelect = array('tawasulPersonID' => $session->get('tawasulPersonID'));
    $sqlSelect = "SELECT tawasulSchoolYear.tawasulSchoolYearID as value, CONCAT(tawasulSchoolYear.name, ' (', tawasulYearGroup.name, ')') AS name FROM tawasulStudentEnrolment JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) WHERE tawasulPersonID=:tawasulPersonID ORDER BY tawasulSchoolYear.sequenceNumber";
    $rowFilter = $form->addRow();
        $rowFilter->addLabel('tawasulSchoolYearID', __('School Years'));
        $rowFilter->addSelect('tawasulSchoolYearID')
            ->fromArray(array('*' => __('All Years')))
            ->fromQuery($pdo, $sqlSelect, $dataSelect)
            ->selected($tawasulSchoolYearID);

    if ($enableGroupByTerm == "Y") {
        $dataSelect = [];
        $sqlSelect = "SELECT tawasulSchoolYear.tawasulSchoolYearID as chainedTo, tawasulSchoolYearTerm.tawasulSchoolYearTermID as value, tawasulSchoolYearTerm.name FROM tawasulSchoolYearTerm JOIN tawasulSchoolYear ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) ORDER BY tawasulSchoolYearTerm.sequenceNumber";
        $rowFilter = $form->addRow();
            $rowFilter->addLabel('tawasulSchoolYearTermID', __('Term'));
            $rowFilter->addSelect('tawasulSchoolYearTermID')
                ->fromQueryChained($pdo, $sqlSelect, $dataSelect, 'tawasulSchoolYearID')
                ->placeholder()
                ->selected($tawasulSchoolYearTermID);
    }

    $types = $settingGateway->getSettingByScope('Markbook', 'markbookType');
    if (!empty($types)) {
        $rowFilter = $form->addRow();
        $rowFilter->addLabel('type', __('Type'));
        $rowFilter->addSelect('type')
            ->fromString($types)
            ->selected($type)
            ->placeholder();
    }

    $details = isset($_GET['details'])? $_GET['details'] : 'Yes';
    $form->addHiddenValue('details', 'No');
    $showHide = $form->getFactory()->createCheckbox('details')->addClass('details')->setValue('Yes')->checked($details)->inline(true)
        ->description(__('Show/Hide Details'))->wrap('&nbsp;<span class="text-xs italic inline-block">', '</span> &nbsp;&nbsp;');

    $rowFilter = $form->addRow();
        $rowFilter->addSearchSubmit($session, __('Clear Filters'))->prepend($showHide->getOutput());

    echo $form->getOutput();

    ?>
    <script type="text/javascript">
        /* Show/Hide detail control */
        $(document).ready(function(){
            var updateDetails = function (){
                if ($('input[name=details]:checked').val()=="Yes" ) {
                    $(".detailItem").slideDown("fast", $(".detailItem").css("{'display' : 'table-row'}"));
                }
                else {
                    $(".detailItem").slideUp("fast");
                }
            }
            $(".details").click(updateDetails);
            updateDetails();
        });
    </script>
    <?php

    // Get class list
    $dataList['tawasulPersonID'] = $session->get('tawasulPersonID');
    $dataList['tawasulPersonID2'] = $session->get('tawasulPersonID');
    $sqlList = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourse.name, tawasulCourseClass.tawasulCourseClassID, tawasulScaleGrade.value AS target, tawasulPerson.dateStart
    FROM tawasulCourse
    JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
    JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
    JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
    LEFT JOIN tawasulMarkbookTarget ON (tawasulMarkbookTarget.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulMarkbookTarget.tawasulPersonIDStudent=:tawasulPersonID2) LEFT JOIN tawasulScaleGrade ON (tawasulMarkbookTarget.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID)
    WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID $and ORDER BY course, class";
    $resultList = $connection2->prepare($sqlList);
    $resultList->execute($dataList);
    if ($resultList->rowCount() > 0) {
        while ($rowList = $resultList->fetch()) {

            $dataEntry['tawasulPersonIDStudent'] = $session->get('tawasulPersonID');
            $dataEntry['tawasulCourseClassID'] = $rowList['tawasulCourseClassID'];
            $sqlEntry = "SELECT *, tawasulMarkbookColumn.comment AS commentOn, tawasulMarkbookColumn.uploadedResponse AS uploadedResponseOn, tawasulMarkbookEntry.comment AS comment FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) WHERE tawasulPersonIDStudent=:tawasulPersonIDStudent AND tawasulCourseClassID=:tawasulCourseClassID AND tawasulMarkbookColumn.viewableStudents='Y' AND complete='Y' AND completeDate<='".date('Y-m-d')."' $and2  ORDER BY completeDate";
            $resultEntry = $connection2->prepare($sqlEntry);
            $resultEntry->execute($dataEntry);
            if ($resultEntry->rowCount() > 0) {
                echo '<h4>'.$rowList['course'].'.'.$rowList['class']." <span style='font-size:85%; font-style: italic'>(".$rowList['name'].')</span></h4>';

                $dataTeachers = array('tawasulCourseClassID' => $rowList['tawasulCourseClassID']);
                $sqlTeachers = "SELECT title, surname, preferredName FROM tawasulPerson JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE role='Teacher' AND tawasulCourseClassID=:tawasulCourseClassID ORDER BY surname, preferredName";
                $resultTeachers = $connection2->prepare($sqlTeachers);
                $resultTeachers->execute($dataTeachers);

                $teachers = '<p><b>'.__('Taught by:').'</b> ';
                while ($rowTeachers = $resultTeachers->fetch()) {
                    $teachers = $teachers.Format::name($rowTeachers['title'], $rowTeachers['preferredName'], $rowTeachers['surname'], 'Staff', false, false).', ';
                }
                $teachers = substr($teachers, 0, -2);
                $teachers = $teachers.'</p>';
                echo $teachers;

                if ($rowList['target'] != '') {
                    echo "<p class='text-right mb-2 text-xs font-bold'>";
                        echo __('Target').': '.$rowList['target'];
                    echo '</p>';
                }

                echo "<table cellspacing='0' style='width: 100%'>";
                echo "<tr class='head'>";
                echo "<th style='width: 120px'>";
                    echo __('Assessment');
                echo '</th>';
                if ($enableModifiedAssessment == 'Y') {
                    echo "<th style='width: 75px'>";
                        echo __('Modified');
                    echo '</th>';
                }
                echo "<th style='width: 75px; text-align: center'>";
                    echo (!empty($attainmentAltName))? $attainmentAltName : __('Attainment');
                echo '</th>';
                if ($enableEffort == 'Y') {
                    echo "<th style='width: 75px; text-align: center'>";
                        echo (!empty($effortAltName))? $effortAltName : __('Effort');
                    echo '</th>';
                }
                echo '<th>';
                    echo __('Comment');
                echo '</th>';
                echo "<th style='width: 75px'>";
                    echo __('Submission');
                echo '</th>';
                echo '</tr>';

                $count = 0;
                while ($rowEntry = $resultEntry->fetch()) {
                    if ($count % 2 == 0) {
                        $rowNum = 'even';
                    } else {
                        $rowNum = 'odd';
                    }
                    ++$count;
                    ++$entryCount;

                    echo "<a name='".$rowEntry['tawasulMarkbookEntryID']."'></a>";
                    echo "<tr class=$rowNum>";
                    echo '<td>';
                    echo "<span title='".htmlPrep($rowEntry['description'])."'><b><u>".$rowEntry['name'].'</u></b></span><br/>';
                    echo "<span style='font-size: 90%; font-style: italic; font-weight: normal'>";
                    $unit = getUnit($connection2, $rowEntry['tawasulUnitID'], $rowEntry['tawasulCourseClassID']);
                    if (isset($unit[0])) {
                        echo $unit[0].'<br/>';
                        if ($unit[1] != '') {
                            echo '<i>'.$unit[1].' '.__('Unit').'</i><br/>';
                        }
                    }
                    if ($rowEntry['completeDate'] != '') {
                        echo __('Marked on').' '.Format::date($rowEntry['completeDate']).'<br/>';
                    } else {
                        echo __('Unmarked').'<br/>';
                    }
                    echo $rowEntry['type'];
                    if ($rowEntry['attachment'] != '' and file_exists($session->get('absolutePath').'/'.$rowEntry['attachment'])) {
                        echo " | <a 'title='".__('Download more information')."' href='".$session->get('absoluteURL').'/'.$rowEntry['attachment']."'>".__('More info').'</a>';
                    }
                    echo '</span><br/>';
                    echo '</td>';
                    if ($enableModifiedAssessment == 'Y') {
                        if (!is_null($rowEntry['modifiedAssessment'])) {
                            echo "<td>";
                            echo Format::yesNo($rowEntry['modifiedAssessment']);
                            echo '</td>';
                        }
                        else {
                            echo "<td class='dull' style='color: #bbb; text-align: center'>";
                            echo __('N/A');
                            echo '</td>';
                        }
                    }
                    if ($rowEntry['attainment'] == 'N' or ($rowEntry['tawasulScaleIDAttainment'] == '' and $rowEntry['tawasulRubricIDAttainment'] == '')) {
                        echo "<td class='dull' style='color: #bbb; text-align: center'>";
                        echo __('N/A');
                        echo '</td>';
                    } else {
                        echo "<td style='text-align: center'>";
                        $attainmentExtra = '';

                            $dataAttainment = array('tawasulScaleID' => $rowEntry['tawasulScaleIDAttainment']);
                            $sqlAttainment = 'SELECT * FROM tawasulScale WHERE tawasulScaleID=:tawasulScaleID';
                            $resultAttainment = $connection2->prepare($sqlAttainment);
                            $resultAttainment->execute($dataAttainment);
                        if ($resultAttainment->rowCount() == 1) {
                            $rowAttainment = $resultAttainment->fetch();
                            $attainmentExtra = '<br/>'.__($rowAttainment['usage']);
                        }
                        $styleAttainment = "style='font-weight: bold'";
                        if ( ($rowEntry['attainmentConcern'] == 'Y' || $rowEntry['attainmentConcern'] == 'P') and $showStudentAttainmentWarning == 'Y') {
                            $styleAttainment = getAlertStyle($alert, $rowEntry['attainmentConcern'] );
                        }
                        echo "<div $styleAttainment>".$rowEntry['attainmentValue'];
                        if ($rowEntry['tawasulRubricIDAttainment'] != '' AND $enableRubrics =='Y') {
                            echo "<a class='thickbox' href='".$session->get('absoluteURL').'/fullscreen.php?q=/modules/TawasulMarkbook/markbook_view_rubric.php&tawasulRubricID='.$rowEntry['tawasulRubricIDAttainment'].'&tawasulCourseClassID='.$rowEntry['tawasulCourseClassID'].'&tawasulMarkbookColumnID='.$rowEntry['tawasulMarkbookColumnID'].'&tawasulPersonID='.$session->get('tawasulPersonID')."&mark=FALSE&type=attainment&width=1100&height=550'><img style='margin-bottom: -3px; margin-left: 3px' title='".__('View Rubric')."' src='./themes/".$session->get('tawasulThemeName')."/img/rubric.png'/></a>";
                        }
                        echo '</div>';
                        if ($rowEntry['attainmentValue'] != '') {
                            echo "<div class='detailItem' style='font-size: 75%; font-style: italic; margin-top: 2px'><b>".htmlPrep(__($rowEntry['attainmentDescriptor'])).'</b>'.__($attainmentExtra).'</div>';
                        }
                        echo '</td>';
                    }
					if ($enableEffort == 'Y') {
	                    if ($rowEntry['effort'] == 'N' or ($rowEntry['tawasulScaleIDEffort'] == '' and $rowEntry['tawasulRubricIDEffort'] == '')) {
	                        echo "<td class='dull' style='color: #bbb; text-align: center'>";
	                        echo __('N/A');
	                        echo '</td>';
	                    } else {
	                        echo "<td style='text-align: center'>";
	                        $effortExtra = '';

	                            $dataEffort = array('tawasulScaleID' => $rowEntry['tawasulScaleIDEffort']);
	                            $sqlEffort = 'SELECT * FROM tawasulScale WHERE tawasulScaleID=:tawasulScaleID';
	                            $resultEffort = $connection2->prepare($sqlEffort);
	                            $resultEffort->execute($dataEffort);
	                        if ($resultEffort->rowCount() == 1) {
	                            $rowEffort = $resultEffort->fetch();
	                            $effortExtra = '<br/>'.__($rowEffort['usage']);
	                        }
	                        $styleEffort = "style='font-weight: bold'";
	                        if ($rowEntry['effortConcern'] == 'Y' and $showStudentEffortWarning == 'Y') {
	                            $styleEffort = getAlertStyle($alert, $rowEntry['effortConcern'] );
	                        }
	                        echo "<div $styleEffort>".$rowEntry['effortValue'];
	                        if ($rowEntry['tawasulRubricIDEffort'] != '' AND $enableRubrics =='Y') {
	                            echo "<a class='thickbox' href='".$session->get('absoluteURL').'/fullscreen.php?q=/modules/TawasulMarkbook/markbook_view_rubric.php&tawasulRubricID='.$rowEntry['tawasulRubricIDEffort'].'&tawasulCourseClassID='.$rowEntry['tawasulCourseClassID'].'&tawasulMarkbookColumnID='.$rowEntry['tawasulMarkbookColumnID'].'&tawasulPersonID='.$session->get('tawasulPersonID')."&mark=FALSE&type=effort&width=1100&height=550'><img style='margin-bottom: -3px; margin-left: 3px' title='".__('View Rubric')."' src='./themes/".$session->get('tawasulThemeName')."/img/rubric.png'/></a>";
	                        }
	                        echo '</div>';
	                        if ($rowEntry['effortValue'] != '') {
	                            echo "<div class='detailItem' style='font-size: 75%; font-style: italic; margin-top: 2px'>";
	                            echo '<b>'.htmlPrep(__($rowEntry['effortDescriptor'])).'</b>';
	                            if ($effortExtra != '') {
	                                echo __($effortExtra);
	                            }
	                            echo '</div>';
	                        }
	                        echo '</td>';
	                    }
					}
                    if ($rowEntry['commentOn'] == 'N' and $rowEntry['uploadedResponseOn'] == 'N') {
                        echo "<td class='dull' style='color: #bbb; text-align: left'>";
                        echo __('N/A');
                        echo '</td>';
                    } else {
                        echo '<td>';
                        if ($rowEntry['comment'] != '') {
                            if (mb_strlen($rowEntry['comment']) > 200) {
                                echo "<script type='text/javascript'>";
                                echo '$(document).ready(function(){';
                                echo "\$(\".comment-$entryCount\").hide();";
                                echo "\$(\".show_hide-$entryCount\").fadeIn(1000);";
                                echo "\$(\".show_hide-$entryCount\").click(function(){";
                                echo "\$(\".comment-$entryCount\").fadeToggle(1000);";
                                echo '});';
                                echo '});';
                                echo '</script>';
                                echo '<span>'.mb_substr($rowEntry['comment'], 0, 200).'...<br/>';
                                echo "<a title='".__('View Description')."' class='show_hide-$entryCount' onclick='return false;' href='#'>".__('Read more').'</a></span><br/>';
                            } else {
                                echo nl2br($rowEntry['comment']);
                            }
                            echo '<br/>';
                        }
                        if ($rowEntry['response'] != '') {
                            echo "<a title='".__('Uploaded Response')."' href='".$session->get('absoluteURL').'/'.$rowEntry['response']."'>".__('Uploaded Response').'</a><br/>';
                        }
                        echo '</td>';
                    }
                    if ($rowEntry['tawasulPlannerEntryID'] == 0) {
                        echo "<td class='dull' style='color: #bbb; text-align: left'>";
                        echo __('N/A');
                        echo '</td>';
                    } else {

                            $dataSub = array('tawasulPlannerEntryID' => $rowEntry['tawasulPlannerEntryID']);
                            $sqlSub = "SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND homeworkSubmission='Y'";
                            $resultSub = $connection2->prepare($sqlSub);
                            $resultSub->execute($dataSub);
                        if ($resultSub->rowCount() != 1) {
                            echo "<td class='dull' style='color: #bbb; text-align: left'>";
                            echo __('N/A');
                            echo '</td>';
                        } else {
                            echo '<td>';
                            $rowSub = $resultSub->fetch();
                            
                                $resultWork = $container->get(PlannerEntryHomeworkGateway::class)->selectHomeworkByStudent($rowEntry['tawasulPlannerEntryID'], $session->get('tawasulPersonID'));
                                
                            if ($resultWork->rowCount() > 0) {
                                $rowWork = $resultWork->fetch();

                                if ($rowWork['status'] == 'Exemption') {
                                    $linkText = __('Exemption');
                                } elseif ($rowWork['version'] == 'Final') {
                                    $linkText = __('Final');
                                } else {
                                    $linkText = __('Draft').' '.$rowWork['count'];
                                }

                                $style = '';
                                $status = 'On Time';
                                if ($rowWork['status'] == 'Exemption') {
                                    $status = __('Exemption');
                                } elseif ($rowWork['status'] == 'Late') {
                                    $style = "style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px'";
                                    $status = __('Late');
                                }

                                if ($rowWork['type'] == 'File') {
                                    echo "<span title='".$rowWork['version'].". $status. ".sprintf(__('Submitted at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), Format::date(substr($rowWork['timestamp'], 0, 10)))."' $style><a href='".$session->get('absoluteURL').'/'.$rowWork['location']."'>$linkText</a></span>";
                                } elseif ($rowWork['type'] == 'Link') {
                                    echo "<span title='".$rowWork['version'].". $status. ".sprintf(__('Submitted at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), Format::date(substr($rowWork['timestamp'], 0, 10)))."' $style><a target='_blank' href='".$rowWork['location']."'>$linkText</a></span>";
                                } else {
                                    echo "<span title='$status. ".sprintf(__('Recorded at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), Format::date(substr($rowWork['timestamp'], 0, 10)))."' $style>$linkText</span>";
                                }
                            } else {
                                if (date('Y-m-d H:i:s') < $rowSub['homeworkDueDateTime']) {
                                    echo "<span title='Pending'>".__('Pending').'</span>';
                                } else {
                                    if (!empty($rowList['dateStart']) && $rowList['dateStart'] > $rowSub['date']) {
                                        echo "<span title='".__('Student joined school after assessment was given.')."' style='color: #000; font-weight: normal; border: 2px none #ff0000; padding: 2px 4px'>".__('NA').'</span>';
                                    } else {
                                        if ($rowSub['homeworkSubmissionRequired'] == 'Required') {
                                            echo "<div style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px; margin: 2px 0px'>".__('Incomplete').'</div>';
                                        } else {
                                            echo __('Not submitted online');
                                        }
                                    }
                                }
                            }
                            echo '</td>';
                        }
                    }
                    echo '</tr>';
                    if ($rowEntry['commentOn'] == 'Y' && mb_strlen($rowEntry['comment']) > 200) {
                        echo "<tr class='comment-$entryCount' id='comment-$entryCount'>";
                        echo '<td colspan=6>';
                        echo nl2br($rowEntry['comment']);
                        echo '</td>';
                        echo '</tr>';
                    }
                }

                $enableColumnWeighting = $settingGateway->getSettingByScope('Markbook', 'enableColumnWeighting');
                $enableDisplayCumulativeMarks = $settingGateway->getSettingByScope('Markbook', 'enableDisplayCumulativeMarks');

                if ($enableColumnWeighting == 'Y' && $enableDisplayCumulativeMarks == 'Y') {
                    renderStudentCumulativeMarks($tawasul, $pdo, $session->get('tawasulPersonID'), $rowList['tawasulCourseClassID'], $tawasulSchoolYearTermID ?? '');
                }

                echo '</table>';
            }
        }
    }

    if ($entryCount < 1) {
        echo "<div class='message'>";
        echo __('There are no records to display.');
        echo '</div>';
    }
