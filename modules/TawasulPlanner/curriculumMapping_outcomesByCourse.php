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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Outcomes By Course'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/curriculumMapping_outcomesByCourse.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    echo '<p>';
    echo __('This view gives an overview of which whole school and learning area outcomes are covered by classes in a given course, allowing for curriculum mapping by outcome and course.');
    echo '</p>';

    echo '<h2>';
    echo __('Choose Course');
    echo '</h2>';

    $tawasulCourseID = isset($_GET['tawasulCourseID'])? $_GET['tawasulCourseID'] : null;

	$form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');

	$form->addHiddenValue('q', '/modules/'.$session->get('module').'/curriculumMapping_outcomesByCourse.php');


	$data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
	$sql = "SELECT tawasulCourse.tawasulCourseID, tawasulCourse.name, tawasulDepartment.name AS department FROM tawasulCourse LEFT JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND NOT tawasulYearGroupIDList='' ORDER BY department, tawasulCourse.nameShort";
	$result = $pdo->executeQuery($data, $sql);

	$courses = ($result->rowCount() > 0)? $result->fetchAll() : array();
	$courses = array_reduce($courses, function($group, $item) {
		$group['--'.$item['department'].'--'][$item['tawasulCourseID']] = $item['name'];
		return $group;
	}, array());

	$row = $form->addRow();
		$row->addLabel('tawasulCourseID', __('Course'));
		$row->addSelect('tawasulCourseID')->fromArray($courses)->required()->selected($tawasulCourseID)->placeholder();

	$row = $form->addRow();
		$row->addSearchSubmit($session, __('Clear Filters'));

	echo $form->getOutput();

    if ($tawasulCourseID != '') {
        echo '<h2>';
        echo __('Outcomes');
        echo '</h2>';

        //Check course exists
        
            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulCourseID' => $tawasulCourseID);
            $sql = "SELECT tawasulCourse.*, tawasulDepartment.name AS department FROM tawasulCourse LEFT JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND NOT tawasulYearGroupIDList='' AND tawasulCourseID=:tawasulCourseID ORDER BY department, nameShort";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
            $row = $result->fetch();
            //Get classes in this course
            
                $dataClasses = array('tawasulCourseID' => $tawasulCourseID);
                $sqlClasses = 'SELECT * FROM tawasulCourseClass WHERE tawasulCourseID=:tawasulCourseID ORDER BY name';
                $resultClasses = $connection2->prepare($sqlClasses);
                $resultClasses->execute($dataClasses);

            if ($resultClasses->rowCount() < 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $classCount = $resultClasses->rowCount();
                $classes = $resultClasses->fetchAll();

                //GET ALL OUTCOMES MET IN THIS COURSE, AND STORE IN AN ARRAY FOR DB-EFFICIENT USE IN TABLE
                
                    $dataOutcomes = array('tawasulCourseID1' => $tawasulCourseID, 'tawasulCourseID2' => $tawasulCourseID);
                    $sqlOutcomes = "(SELECT 'Unit' AS type, tawasulCourseClass.tawasulCourseClassID, tawasulOutcome.* FROM tawasulOutcome JOIN tawasulUnitOutcome ON (tawasulUnitOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) JOIN tawasulUnit ON (tawasulUnitOutcome.tawasulUnitID=tawasulUnit.tawasulUnitID) JOIN tawasulUnitClass ON (tawasulUnitClass.tawasulUnitID=tawasulUnit.tawasulUnitID) JOIN tawasulCourseClass ON (tawasulUnitClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulCourseClass.tawasulCourseID=:tawasulCourseID1 AND tawasulOutcome.active='Y' AND running='Y')
					UNION ALL
					(SELECT 'Planner Entry' AS type, tawasulCourseClass.tawasulCourseClassID, tawasulOutcome.* FROM tawasulOutcome JOIN tawasulPlannerEntryOutcome ON (tawasulPlannerEntryOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) JOIN tawasulPlannerEntry ON (tawasulPlannerEntryOutcome.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulCourseClass.tawasulCourseID=:tawasulCourseID2 AND tawasulOutcome.active='Y')";
                    $resultOutcomes = $connection2->prepare($sqlOutcomes);
                    $resultOutcomes->execute($dataOutcomes);
                $allOutcomes = $resultOutcomes->fetchAll();

                echo "<table class='smallIntBorder' cellspacing='0' style='width: 100%'>";
                echo "<tr class='head'>";
                echo '<th>';
                echo __('Category');
                echo '</th>';
                echo '<th>';
                echo __('Outcome');
                echo '</th>';
                foreach ($classes as $class) {
                    echo '<th colspan=2>';
                    echo $row['nameShort'].'.'.__($class['nameShort']);
                    echo '</th>';
                }
                echo '</tr>';
                echo "<tr class='head'>";
                echo '<th>';

                echo '</th>';
                echo '<th>';

                echo '</th>';
                foreach ($classes as $class) {
                    echo '<th>';
                    echo "<span style='font-style: italic; font-size: 85%'>".__('Unit').'</span>';
                    echo '</th>';
                    echo '<th>';
                    echo "<span style='font-style: italic; font-size: 85%'>".__('Lesson').'</span>';
                    echo '</th>';
                }
                echo '</tr>';

				//SCHOOL OUTCOMES
				echo "<tr class='break'>";
                echo '<td colspan='.(($classCount * 2) + 2).'>';
                echo '<h4>'.__('School Outcomes').'</h4>';
                echo '</td>';
                echo '</tr>';
                try {
                    $dataOutcomes = ['tawasulYearGroupIDList' => $row['tawasulYearGroupIDList']];
                    $sqlOutcomes = "SELECT DISTINCT tawasulOutcome.* FROM tawasulOutcome JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulOutcome.tawasulYearGroupIDList)) WHERE scope='School' AND active='Y' AND FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, :tawasulYearGroupIDList) ORDER BY tawasulOutcome.category, tawasulOutcome.name";
                    $resultOutcomes = $connection2->prepare($sqlOutcomes);
                    $resultOutcomes->execute($dataOutcomes);
                } catch (PDOException $e) {
                    echo '<tr>';
                    echo '<td colspan='.(($classCount * 2) + 2).'>';
                    echo '</td>';
                    echo '</tr>';
                }

                if ($resultOutcomes->rowCount() < 1) {
                    echo '<tr>';
                    echo '<td colspan='.(($classCount * 2) + 2).'>';
                    echo "<div class='error'>".__('There are no records to display.').'</div>';
                    echo '</td>';
                    echo '</tr>';
                } else {
                    $count = 0;
                    $rowNum = 'odd';
                    while ($rowOutcomes = $resultOutcomes->fetch()) {
                        if ($count % 2 == 0) {
                            $rowNum = 'even';
                        } else {
                            $rowNum = 'odd';
                        }
                        ++$count;

                        //COLOR ROW BY STATUS!
                        echo "<tr class=$rowNum>";
                        echo '<td>';
                        echo $rowOutcomes['category'];
                        echo '</td>';
                        echo '<td>';
                        echo $rowOutcomes['name'];
                        echo '</td>';

						//Deal with outcomes
						foreach ($classes as $class) {
							echo '<td>';
							$outcomeCount = 0;
							foreach ($allOutcomes as $anOutcome) {
								if ($anOutcome['type'] == 'Unit' and $anOutcome['scope'] == 'School' and $anOutcome['tawasulOutcomeID'] == $rowOutcomes['tawasulOutcomeID'] and $class['tawasulCourseClassID'] == $anOutcome['tawasulCourseClassID']) {
									++$outcomeCount;
								}
							}
							if ($outcomeCount < 1) {
								echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconCross.png'/> ";
							} else {
								echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconTick.png'/>&nbsp;x&nbsp;".$outcomeCount;
							}
							echo '</td>';
							echo '<td>';
							$outcomeCount = 0;
							foreach ($allOutcomes as $anOutcome) {
								if ($anOutcome['type'] != 'Unit' and $anOutcome['scope'] == 'School' and $anOutcome['tawasulOutcomeID'] == $rowOutcomes['tawasulOutcomeID'] and $class['tawasulCourseClassID'] == $anOutcome['tawasulCourseClassID']) {
									++$outcomeCount;
								}
							}
							if ($outcomeCount < 1) {
								echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconCross.png'/> ";
							} else {
								echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconTick.png'/>&nbsp;x&nbsp;".$outcomeCount;
							}
							echo '</td>';
						}
                        echo '</tr>';
                    }
                }

                    //LEARNING AREA OUTCOMES
                    echo "<tr class='break'>";
					echo '<td colspan='.(($classCount * 2) + 2).'>';
					echo '<h4>'.sprintf(__('%1$s Outcomes'), $row['department']).'</h4>';
					echo '</td>';
					echo '</tr>';
					try {
						$dataOutcomes = array('tawasulDepartmentID' => $row['tawasulDepartmentID']);
						$sqlOutcomes = "SELECT * FROM tawasulOutcome WHERE scope='Learning Area' AND tawasulDepartmentID=:tawasulDepartmentID AND active='Y' ORDER BY category, name";
						$resultOutcomes = $connection2->prepare($sqlOutcomes);
						$resultOutcomes->execute($dataOutcomes);
					} catch (PDOException $e) {
						echo '<tr>';
						echo '<td colspan='.(($classCount * 2) + 2).'>';
						echo '</td>';
						echo '</tr>';
					}

					if ($resultOutcomes->rowCount() < 1) {
						echo '<tr>';
						echo '<td colspan='.(($classCount * 2) + 2).'>';
						echo "<div class='error'>".__('There are no records to display.').'</div>';
						echo '</td>';
						echo '</tr>';
					} else {
						$count = 0;
						$rowNum = 'odd';
						while ($rowOutcomes = $resultOutcomes->fetch()) {
							if ($count % 2 == 0) {
								$rowNum = 'even';
							} else {
								$rowNum = 'odd';
							}
							++$count;

							//COLOR ROW BY STATUS!
							echo "<tr class=$rowNum>";
							echo '<td>';
							echo $rowOutcomes['category'];
							echo '</td>';
							echo '<td>';
							echo $rowOutcomes['name'];
							echo '</td>';

							//Deal with outcomes
							foreach ($classes as $class) {
								echo '<td>';
								$outcomeCount = 0;
								foreach ($allOutcomes as $anOutcome) {
									if ($anOutcome['type'] == 'Unit' and $anOutcome['scope'] == 'Learning Area' and $anOutcome['tawasulOutcomeID'] == $rowOutcomes['tawasulOutcomeID'] and $class['tawasulCourseClassID'] == $anOutcome['tawasulCourseClassID']) {
										++$outcomeCount;
									}
								}
								if ($outcomeCount < 1) {
									echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconCross.png'/> ";
								} else {
									echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconTick.png'/>&nbsp;x&nbsp;".$outcomeCount;
								}
								echo '</td>';
								echo '<td>';
								$outcomeCount = 0;
								foreach ($allOutcomes as $anOutcome) {
									if ($anOutcome['type'] != 'Unit' and $anOutcome['scope'] == 'Learning Area' and $anOutcome['tawasulOutcomeID'] == $rowOutcomes['tawasulOutcomeID'] and $class['tawasulCourseClassID'] == $anOutcome['tawasulCourseClassID']) {
										++$outcomeCount;
									}
								}
								if ($outcomeCount < 1) {
									echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconCross.png'/> ";
								} else {
									echo "<img src='./themes/".$session->get('tawasulThemeName')."/img/iconTick.png'/>&nbsp;x&nbsp;".$outcomeCount;
								}
								echo '</td>';
							}

                        echo '</tr>';
                    }
                }
                echo '</table>';
            }
        }
    }
}
