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
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_unitOverview.php') == false) {
    //Acess denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
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
        } elseif ($viewBy == 'class') {
            $class = $_GET['class'] ?? [];
            $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        }
        $replyTo = null;
        if (isset($_GET['replyTo'])) {
            $replyTo = $_GET['replyTo'] ?? '';
        }

        $tawasulPersonID = null;
        if (isset($_GET['search'])) {
            $tawasulPersonID = $_GET['search'] ?? '';
        }

        //Get class variable
        $tawasulPlannerEntryID = null;
        if (isset($_GET['tawasulPlannerEntryID'])) {
            $tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
        }
        if ($tawasulPlannerEntryID == '') {
            echo "<div class='warning'>";
            echo __('You have not specified one or more required parameters.');
            echo '</div>';
        }
        //Check existence of and access to this class.
        else {
            if ($highestAction == 'Lesson Planner_viewMyChildrensClasses') {
                if ($_GET['search'] == '') {
                    echo "<div class='warning'>";
                    echo __('You have not specified one or more required parameters.');
                    echo '</div>';
                } else {

                        $dataChild = array('tawasulPersonID1' => $tawasulPersonID, 'tawasulPersonID2' => $session->get('tawasulPersonID'));
                        $sqlChild = "SELECT * FROM tawasulFamilyChild JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulFamilyChild.tawasulPersonID=:tawasulPersonID1 AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID2 AND childDataAccess='Y'";
                        $resultChild = $connection2->prepare($sqlChild);
                        $resultChild->execute($dataChild);
                    if ($resultChild->rowCount() != 1) {
                        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                    } else {
                        $data = array('date' => $date);
                        $data['tawasulPlannerEntryID1'] = $tawasulPlannerEntryID;
                        $data['tawasulPlannerEntryID2'] = $tawasulPlannerEntryID;
                        $data['tawasulPersonID'] = $tawasulPersonID;
                        $sql = "(SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND NOT role='Student - Left' AND NOT role='Teacher - Left' AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID1) UNION (SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulPlannerEntryGuest ON (tawasulPlannerEntryGuest.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE date=:date AND tawasulPlannerEntryGuest.tawasulPersonID=:tawasulPersonID AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID2) ORDER BY date, timeStart";
                    }
                }
            } elseif ($highestAction == 'Lesson Planner_viewMyClasses') {
                $data = array('date' => $date, 'tawasulPlannerEntryID1' => $tawasulPlannerEntryID, 'tawasulPlannerEntryID2' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                $sql = "(SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND NOT role='Student - Left' AND NOT role='Teacher - Left' AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID1) UNION (SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulPlannerEntryGuest ON (tawasulPlannerEntryGuest.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE date=:date AND tawasulPlannerEntryGuest.tawasulPersonID=:tawasulPersonID AND tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID2) ORDER BY date, timeStart";
            } elseif ($highestAction == 'Lesson Planner_viewAllEditMyClasses' or $highestAction == 'Lesson Planner_viewEditAllClasses' or $highestAction == 'Lesson Planner_viewOnly') {
                $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sql = "SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulCourseClass.tawasulCourseClassID, tawasulUnitID, tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.name, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, date, timeStart, timeEnd, summary, tawasulPlannerEntry.description, teachersNotes, homework, homeworkDueDateTime, homeworkDetails, viewableStudents, viewableParents, 'Teacher' AS role, homeworkSubmission, homeworkSubmissionDateOpen, homeworkSubmissionDrafts, homeworkSubmissionType FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntry.tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY date, timeStart";
            }

                $result = $connection2->prepare($sql);
                $result->execute($data);

            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $row = $result->fetch();

                // target of the planner
                $target = ($viewBy === 'class') ? $row['course'].'.'.$row['class'] : Format::date($date);

                // planner parameters
                $params = [];
                if ($date != '') {
                    $params['date'] = $_GET['date'] ?? '';
                }
                if ($viewBy != '') {
                    $params['viewBy'] = $_GET['viewBy'] ?? '';
                }
                if ($tawasulCourseClassID != '') {
                    $params['tawasulCourseClassID'] = $tawasulCourseClassID;
                }
                $params['subView'] = $subView;
                $paramsVar = '&' . http_build_query($params); // for backward compatibile uses below (should be get rid of)

                $page->breadcrumbs
                    ->add(__('Planner for {classDesc}', [
                        'classDesc' => $target,
                    ]), 'planner.php', $params)
                    ->add(__('View Lesson Plan'), 'planner_view_full.php', $params + ['tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'search' => $tawasulPersonID])
                    ->add(__('Unit Overview'));

                if ($row['tawasulUnitID'] == '') {
                    echo __('The selected record does not exist, or you do not have access to it.');
                } else {
                    //Get unit contents

                        $dataUnit = array('tawasulUnitID' => $row['tawasulUnitID']);
                        $sqlUnit = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID';
                        $resultUnit = $connection2->prepare($sqlUnit);
                        $resultUnit->execute($dataUnit);

                    if ($resultUnit->rowCount() != 1) {
                        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                    } else {
                        $rowUnit = $resultUnit->fetch();

                        echo '<h2>';
                        echo $rowUnit['name'];
                        echo '</h2>';
                        echo '<p>';
                        echo __('This page shows an overview of the unit that the current lesson belongs to, including all the outcomes, resources, lessons and chats for the classes you have access to.');
                        echo '</p>';

                        //Set up where and data array for getting items from accessible planners
                        if ($highestAction == 'Lesson Planner_viewEditAllClasses' or $highestAction == 'Lesson Planner_viewAllEditMyClasses' or $highestAction == 'Lesson Planner_viewOnly') {
                            $dataPlanners = array('tawasulUnitID' => $row['tawasulUnitID'], 'tawasulCourseClassID' => $row['tawasulCourseClassID']);
                            $sqlPlanners = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseClassID=:tawasulCourseClassID';
                        } elseif ($highestAction == 'Lesson Planner_viewMyClasses') {
                            $dataPlanners = array('tawasulUnitID1' => $row['tawasulUnitID'], 'tawasulPersonID1' => $session->get('tawasulPersonID'), 'tawasulCourseClassID1' => $row['tawasulCourseClassID'], 'tawasulUnitID2' => $row['tawasulUnitID'], 'tawasulPersonID2' => $session->get('tawasulPersonID'), 'tawasulCourseClassID2' => $row['tawasulCourseClassID']);
                            $sqlPlanners = "(SELECT tawasulPlannerEntry.* FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulUnitID=:tawasulUnitID1 AND tawasulPersonID=:tawasulPersonID1 AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID1 AND role='Teacher')
							UNION
							(SELECT tawasulPlannerEntry.* FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulUnitID=:tawasulUnitID2 AND tawasulPersonID=:tawasulPersonID2 AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID2 AND role='Student' AND viewableStudents='Y')";
                        } elseif ($highestAction == 'Lesson Planner_viewMyChildrensClasses') {
                            $dataPlanners = array('tawasulUnitID' => $row['tawasulUnitID'], 'tawasulCourseClassID' => $row['tawasulCourseClassID']);
                            $sqlPlanners = "SELECT * FROM tawasulPlannerEntry WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseClassID=:tawasulCourseClassID AND viewableParents='Y'";
                        }

                            $resultPlanners = $connection2->prepare($sqlPlanners);
                            $resultPlanners->execute($dataPlanners);

                        if ($resultPlanners->rowCount() < 1) {
                            echo $page->getBlankSlate();
                        } else {
                            $dataMulti = array();
                            $whereMulti = '(';
                            $multiCount = 0;
                            while ($rowPlanners = $resultPlanners->fetch()) {
                                $dataMulti['tawasulPlannerEntryID'.$multiCount] = $rowPlanners['tawasulPlannerEntryID'];
                                $whereMulti .= 'tawasulPlannerEntryID=:tawasulPlannerEntryID'.$multiCount.' OR ';
                                ++$multiCount;
                            }
                            $whereMulti = substr($whereMulti, 0, -4).')';
                            ?>
							<script type='text/javascript'>
								$(function() {
									$( "#tabs" ).tabs({
										ajaxOptions: {
											error: function( xhr, status, index, anchor ) {
												$( anchor.hash ).html(
													"Couldn't load this tab." );
											}
										}
									});
								});
							</script>
							<?php

                            echo "<div id='tabs' style='margin: 20px 0'>";
							//Tab links
							echo '<ul>';
                            echo "<li><a href='#tabs1'>".__('Unit Overview').'</a></li>';
                            echo "<li><a href='#tabs2'>".__('Smart Blocks').'</a></li>';
                            echo "<li><a href='#tabs3'>".__('Outcomes').'</a></li>';
                            echo "<li><a href='#tabs4'>".__('Lessons').'</a></li>';
                            echo "<li><a href='#tabs5'>".__('Resources').'</a></li>';
                            echo '</ul>';

							//Tab content
							//UNIT OVERVIEW
							echo "<div id='tabs1'>";
                            $shareUnitOutline = $container->get(SettingGateway::class)->getSettingByScope('Planner', 'shareUnitOutline');
                            echo '<h2>';
                            echo __('Description');
                            echo '</h2>';
                            echo '<p>';
                            echo $rowUnit['description'];
                            echo '</p>';

                            if ($rowUnit['tags'] != '') {
                                echo '<h2>';
                                echo __('Concepts & Keywords');
                                echo '</h2>';
                                echo '<p>';
                                echo $rowUnit['tags'];
                                echo '</p>';
                            }
                            if ($highestAction == 'Lesson Planner_viewEditAllClasses' or $highestAction == 'Lesson Planner_viewAllEditMyClasses' or $shareUnitOutline == 'Y') {
                                if ($rowUnit['details'] != '') {
                                    echo '<h2>';
                                    echo __('Unit Outline');
                                    echo '</h2>';
                                    echo '<p>';
                                    echo $rowUnit['details'];
                                    echo '</p>';
                                }
                            }
                            echo '</div>';
                            //SMART BLOCKS
                            echo "<div id='tabs2'>";

                                $dataBlocks = array('tawasulUnitID' => $row['tawasulUnitID']);
                                $sqlBlocks = 'SELECT * FROM tawasulUnitBlock WHERE tawasulUnitID=:tawasulUnitID ORDER BY sequenceNumber';
                                $resultBlocks = $connection2->prepare($sqlBlocks);
                                $resultBlocks->execute($dataBlocks);

                            while ($rowBlocks = $resultBlocks->fetch()) {
                                if ($rowBlocks['title'] != '' or $rowBlocks['type'] != '' or $rowBlocks['length'] != '') {
                                    echo "<div class='blockView' style='min-height: 35px'>";
                                    if ($rowBlocks['type'] != '' or $rowBlocks['length'] != '') {
                                        $width = '69%';
                                    } else {
                                        $width = '100%';
                                    }
                                    echo "<div style='padding-left: 3px; width: $width; float: left;'>";
                                    if ($rowBlocks['title'] != '') {
                                        echo "<h5 style='padding-bottom: 2px'>".$rowBlocks['title'].'</h5>';
                                    }
                                    echo '</div>';
                                    if ($rowBlocks['type'] != '' or $rowBlocks['length'] != '') {
                                        echo "<div style='float: right; width: 29%; padding-right: 3px; height: 55px'>";
                                        echo "<div style='text-align: right; font-size: 85%; font-style: italic; margin-top: 3px; border-bottom: 1px solid #ddd; height: 21px'>";
                                        if ($rowBlocks['type'] != '') {
                                            echo $rowBlocks['type'];
                                            if ($rowBlocks['length'] != '') {
                                                echo ' | ';
                                            }
                                        }
                                        if ($rowBlocks['length'] != '') {
                                            echo $rowBlocks['length'].' min';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                                if ($rowBlocks['contents'] != '') {
                                    echo "<div style='padding: 15px 3px 10px 3px; width: 100%; text-align: justify; border-bottom: 1px solid #ddd'>".$rowBlocks['contents'].'</div>';
                                }
                            }
                            echo '</div>';
                            //OUTCOMES
							echo "<div id='tabs3'>";

                                $dataOutcomes = $dataMulti;
                                $dataOutcomes['tawasulUnitID'] = $row['tawasulUnitID'];
                                $sqlOutcomes = "(SELECT tawasulOutcome.*, tawasulPlannerEntryOutcome.content FROM tawasulPlannerEntryOutcome JOIN tawasulOutcome ON (tawasulPlannerEntryOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) WHERE $whereMulti AND active='Y')
									UNION
									(SELECT tawasulOutcome.*, tawasulUnitOutcome.content FROM tawasulUnitOutcome JOIN tawasulOutcome ON (tawasulUnitOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) WHERE tawasulUnitID=:tawasulUnitID AND active='Y')
									ORDER BY scope DESC, name";
                                $resultOutcomes = $connection2->prepare($sqlOutcomes);
                                $resultOutcomes->execute($dataOutcomes);
                            if ($resultOutcomes->rowCount() < 1) {
                                echo $page->getBlankSlate();
                            } else {
                                echo "<table cellspacing='0' style='width: 100%'>";
                                echo "<tr class='head'>";
                                echo '<th>';
                                echo __('Scope');
                                echo '</th>';
                                echo '<th>';
                                echo __('Category');
                                echo '</th>';
                                echo '<th>';
                                echo __('Name');
                                echo '</th>';
                                echo '<th>';
                                echo __('Year Groups');
                                echo '</th>';
                                echo '<th>';
                                echo __('Actions');
                                echo '</th>';
                                echo '</tr>';

                                $count = 0;
                                $rowNum = 'odd';
                                while ($rowOutcomes = $resultOutcomes->fetch()) {
                                    if ($count % 2 == 0) {
                                        $rowNum = 'even';
                                    } else {
                                        $rowNum = 'odd';
                                    }

									//COLOR ROW BY STATUS!
									echo "<tr class=$rowNum>";
                                    echo '<td>';
                                    echo '<b>'.$rowOutcomes['scope'].'</b><br/>';
                                    if ($rowOutcomes['scope'] == 'Learning Area' and $rowOutcomes['tawasulDepartmentID'] != '') {

                                            $dataLearningArea = array('tawasulDepartmentID' => $rowOutcomes['tawasulDepartmentID']);
                                            $sqlLearningArea = 'SELECT * FROM tawasulDepartment WHERE tawasulDepartmentID=:tawasulDepartmentID';
                                            $resultLearningArea = $connection2->prepare($sqlLearningArea);
                                            $resultLearningArea->execute($dataLearningArea);
                                        if ($resultLearningArea->rowCount() == 1) {
                                            $rowLearningAreas = $resultLearningArea->fetch();
                                            echo "<span style='font-size: 75%; font-style: italic'>".$rowLearningAreas['name'].'</span>';
                                        }
                                    }
                                    echo '</td>';
                                    echo '<td>';
                                    echo '<b>'.$rowOutcomes['category'].'</b><br/>';
                                    echo '</td>';
                                    echo '<td>';
                                    echo '<b>'.$rowOutcomes['nameShort'].'</b><br/>';
                                    echo "<span style='font-size: 75%; font-style: italic'>".$rowOutcomes['name'].'</span>';
                                    echo '</td>';
                                    echo '<td>';
                                    echo getYearGroupsFromIDList($guid, $connection2, $rowOutcomes['tawasulYearGroupIDList']);
                                    echo '</td>';
                                    echo '<td>';
                                    echo "<script type='text/javascript'>";
                                    echo '$(document).ready(function(){';
                                    echo "\$(\".description-$count\").hide();";
                                    echo "\$(\".show_hide-$count\").fadeIn(1000);";
                                    echo "\$(\".show_hide-$count\").click(function(){";
                                    echo "\$(\".description-$count\").fadeToggle(1000);";
                                    echo '});';
                                    echo '});';
                                    echo '</script>';
                                    if ($rowOutcomes['content'] != '') {
                                        echo "<a title='".__('View Description')."' class='show_hide-$count' onclick='false' href='#'><img style='padding-left: 0px' src='".$session->get('absoluteURL').'/themes/'.$session->get('tawasulThemeName')."/img/page_down.png' alt='".__('Show Comment')."' onclick='return false;' /></a>";
                                    }
                                    echo '</td>';
                                    echo '</tr>';
                                    if ($rowOutcomes['content'] != '') {
                                        echo "<tr class='description-$count' id='description-$count'>";
                                        echo '<td colspan=6>';
                                        echo $rowOutcomes['content'];
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    echo '</tr>';

                                    ++$count;
                                }
                                echo '</table>';
                            }
                            echo '</div>';
                            //LESSONS
                            echo "<div id='tabs4'>";
                            $resourceContents = '';

                                $dataLessons = $dataMulti;
                                $sqlLessons = "SELECT * FROM tawasulPlannerEntry WHERE $whereMulti";
                                $resultLessons = $connection2->prepare($sqlLessons);
                                $resultLessons->execute($dataLessons);

                            if ($resultLessons->rowCount() < 1) {
                                echo "<div class='warning'>";
                                echo __('There are no records to display.');
                                echo '</div>';
                            } else {
                                while ($rowLessons = $resultLessons->fetch()) {
                                    echo '<h3>'.$rowLessons['name'].'</h3>';
                                    echo $rowLessons['description'];
                                    $resourceContents .= $rowLessons['description'];
                                    if ($rowLessons['teachersNotes'] != '' and ($highestAction == 'Lesson Planner_viewAllEditMyClasses' or $highestAction == 'Lesson Planner_viewEditAllClasses')) {
                                        echo "<div style='background-color: #F6CECB; padding: 0px 3px 10px 3px; width: 98%; text-align: justify; border-bottom: 1px solid #ddd'><p style='margin-bottom: 0px'><b>".__("Teacher's Notes").':</b></p> '.$rowLessons['teachersNotes'].'</div>';
                                        $resourceContents .= $rowLessons['teachersNotes'];
                                    }


                                        $dataBlock = array('tawasulPlannerEntryID' => $rowLessons['tawasulPlannerEntryID']);
                                        $sqlBlock = 'SELECT * FROM tawasulUnitClassBlock WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY sequenceNumber';
                                        $resultBlock = $connection2->prepare($sqlBlock);
                                        $resultBlock->execute($dataBlock);

                                    while ($rowBlock = $resultBlock->fetch()) {
                                        echo "<h5 style='font-size: 85%'>".$rowBlock['title'].'</h5>';
                                        echo '<p>';
                                        echo '<b>'.__('Type').'</b>: '.$rowBlock['type'].'<br/>';
                                        echo '<b>'.__('Length').'</b>: '.$rowBlock['length'].'<br/>';
                                        echo '<b>'.__('Contents').'</b>: '.$rowBlock['contents'].'<br/>';
                                        $resourceContents .= $rowBlock['contents'];
                                        if ($rowBlock['teachersNotes'] != '' and ($highestAction == 'Lesson Planner_viewAllEditMyClasses' or $highestAction == 'Lesson Planner_viewEditAllClasses')) {
                                            echo "<div style='background-color: #F6CECB; padding: 0px 3px 10px 3px; width: 98%; text-align: justify; border-bottom: 1px solid #ddd'><p style='margin-bottom: 0px'><b>".__("Teacher's Notes").':</b></p> '.$rowBlock['teachersNotes'].'</div>';
                                            $resourceContents .= $rowBlock['teachersNotes'];
                                        }
                                        echo '</p>';
                                    }

									//Print chats
                                    try {
                                        $dataDiscuss = array('tawasulPlannerEntryID' => $rowLessons['tawasulPlannerEntryID']);
                                        $sqlDiscuss = 'SELECT tawasulPlannerEntryDiscuss.*, title, surname, preferredName, category FROM tawasulPlannerEntryDiscuss JOIN tawasulPerson ON (tawasulPlannerEntryDiscuss.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY timestamp';
                                        $resultDiscuss = $connection2->prepare($sqlDiscuss);
                                        $resultDiscuss->execute($dataDiscuss);
                                    } catch (PDOException $e) {}

                                    if ($resultDiscuss->rowCount() > 0) {
                                        echo "<h5 style='font-size: 85%'>".__('Chat').'</h5>';
                                        echo '<style type="text/css">';
                                        echo 'table.chatbox { width: 90%!important }';
                                        echo '</style>';
                                        echo getThread($guid, $connection2, $rowLessons['tawasulPlannerEntryID'], null, 0, null, null, null, null, null, $class[1] ?? '', $session->get('tawasulPersonID'), 'Teacher', false, true);
                                    }
                                }
                            }
                            echo '</div>';
                            //RESOURCES
                            echo "<div id='tabs5'>";
                            $noReosurces = true;

                            if (!empty($resourceContents)) {
                                $resourceContents = '<?xml version="1.0" encoding="UTF-8"?>'.$resourceContents;

                                //Links
                                $links = '';
                                $linksArray = array();
                                $linksCount = 0;
                                $dom = new DOMDocument();
                                @$dom->loadHTML($resourceContents);
                                foreach ($dom->getElementsByTagName('a') as $node) {
                                    if ($node->nodeValue != '') {
                                        $linksArray[$linksCount] = "<li><a href='".$node->getAttribute('href')."'>".$node->nodeValue.'</a></li>';
                                        ++$linksCount;
                                    }
                                }

                                $linksArray = array_unique($linksArray);
                                natcasesort($linksArray);

                                foreach ($linksArray as $link) {
                                    $links .= $link;
                                }

                                if ($links != '') {
                                    echo '<h2>';
                                    echo 'Links';
                                    echo '</h2>';
                                    echo '<ul>';
                                    echo $links;
                                    echo '</ul>';
                                    $noReosurces = false;
                                }

                                //Images
                                $images = '';
                                $imagesArray = array();
                                $imagesCount = 0;
                                $dom2 = new DOMDocument();
                                @$dom2->loadHTML($resourceContents);
                                foreach ($dom2->getElementsByTagName('img') as $node) {
                                    if ($node->getAttribute('src') != '') {
                                        $imagesArray[$imagesCount] = "<img class='resource' style='margin: 10px 0; max-width: 560px' src='".$node->getAttribute('src')."'/><br/>";
                                        ++$imagesCount;
                                    }
                                }

                                $imagesArray = array_unique($imagesArray);
                                natcasesort($imagesArray);

                                foreach ($imagesArray as $image) {
                                    $images .= $image;
                                }

                                if ($images != '') {
                                    echo '<h2>';
                                    echo 'Images';
                                    echo '</h2>';
                                    echo $images;
                                    $noReosurces = false;
                                }

                                //Embeds
                                $embeds = '';
                                $embedsArray = array();
                                $embedsCount = 0;
                                $dom2 = new DOMDocument();
                                @$dom2->loadHTML($resourceContents);
                                foreach ($dom2->getElementsByTagName('iframe') as $node) {
                                    if ($node->getAttribute('src') != '') {
                                        $embedsArray[$embedsCount] = "<iframe style='max-width: 560px' width='".$node->getAttribute('width')."' height='".$node->getAttribute('height')."' src='".$node->getAttribute('src')."' frameborder='".$node->getAttribute('frameborder')."'></iframe>";
                                        ++$embedsCount;
                                    }
                                }

                                $embedsArray = array_unique($embedsArray);
                                natcasesort($embedsArray);

                                foreach ($embedsArray as $embed) {
                                    $embeds .= $embed.'<br/><br/>';
                                }

                                if ($embeds != '') {
                                    echo '<h2>';
                                    echo 'Embeds';
                                    echo '</h2>';
                                    echo $embeds;
                                    $noReosurces = false;
                                }
                            }

							//No resources!
							if ($noReosurces) {
								echo $page->getBlankSlate();
							}
                            echo '</div>';
                            echo '</div>';
                        }
                    }
                }
            }
        }
    }
}
