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
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$validator = $container->get(Validator::class);
$_POST = $validator->sanitize($_POST);

if (isActionAccessible($guid, $connection2, '/modules/TawasulTracking/graphing.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Get action with highest precendence
        $page->breadcrumbs->add(__('Graphing'));

        echo '<h2>';
        echo __('Filter');
        echo '</h2>';
        
        $tawasulPersonIDs = $_POST['tawasulPersonIDs'] ?? [];
        $tawasulDepartmentIDs =  $_POST['tawasulDepartmentIDs'] ?? [];
        $dataType = $_POST['dataType'] ?? null;

        // Sanitize the tawasulPersonIDs and tawasulDepartmentIDs List
        $tawasulPersonIDs = $validator->sanitizeArrayIDs($tawasulPersonIDs);
        $tawasulDepartmentIDs = $validator->sanitizeArrayIDs($tawasulDepartmentIDs);

        $settingGateway = $container->get(SettingGateway::class);
        $attainmentAlt = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
        $effortAlt = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');
        $dataTypes = array(
            'attainment' => (!empty($attainmentAlt))? $attainmentAlt : __('Attainment'),
            'effort' => (!empty($effortAlt))? $effortAlt : __('Effort'),
        );

        $form = Form::create('action', $session->get('absoluteURL').'/index.php?q=/modules/TawasulTracking/graphing.php');
        $form->setFactory(DatabaseFormFactory::create($pdo));

        $form->addHiddenValue('address', $session->get('address'));

        $row = $form->addRow();
            $row->addLabel('tawasulPersonIDs', __('Student'));
            $row->addSelectStudent('tawasulPersonIDs', $session->get('tawasulSchoolYearID'), array('byName' => true, 'byForm' => true))->selectMultiple()->required();
        $row = $form->addRow();
            $row->addLabel('dataType', __('Data Type'));
            $row->addSelect('dataType')->fromArray($dataTypes)->required();

        $sql = "SELECT tawasulDepartmentID as value, name FROM tawasulDepartment WHERE type='Learning Area' ORDER BY name";
        $results = $pdo->executeQuery(array(), $sql);

        $row = $form->addRow();
        $row->addLabel('tawasulDepartmentIDs', __('Learning Areas'))
            ->description(__('Only Learning Areas for which the student has data will be displayed.'));
            if ($results->rowCount() == 0) {
                $row->addContent(__('No Learning Areas available.'))->wrap('<i>', '</i>');
            } else {
                $row->addCheckbox('tawasulDepartmentIDs')->fromResults($results)->addCheckAllNone();
            }

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        $form->loadAllValuesFrom($_POST);

        echo $form->getOutput();

        if (count($_POST) > 0) {
            if ($tawasulPersonIDs == null or $tawasulDepartmentIDs == null or ($dataType != 'attainment' and $dataType != 'effort')) {
                echo $page->getBlankSlate();
            } else {
                $output = '';
                echo '<h2>';
                echo __('Report Data');
                echo '</h2>';
                echo '<p>';
                echo __('The chart below shows Years and Terms along the X axis, and mean Markbook grades, converted to a 0-1 scale, on the Y axis.');
                echo '</p>';

                //GET DEPARTMENTS
                $departments = array();
                $departmentCount = 0;
                $colours = getColourArray();
                try {
                    $dataDepartments = array();
                    $departmentExtra_MB = '';
                    $departmentExtra_IA = '';
                    foreach ($tawasulDepartmentIDs as $tawasulDepartmentID) { //INCLUDE ONLY SELECTED DEPARTMENTS
                        $dataDepartments['department_MB'.$tawasulDepartmentID] = $tawasulDepartmentID;
                        $departmentExtra_MB .= 'tawasulDepartment.tawasulDepartmentID=:department_MB'.$tawasulDepartmentID.' OR ';
                        $dataDepartments['department_IA'.$tawasulDepartmentID] = $tawasulDepartmentID;
                        $departmentExtra_IA .= 'tawasulDepartment.tawasulDepartmentID=:department_IA'.$tawasulDepartmentID.' OR ';
                    }
                    if ($departmentExtra_MB != '') {
                        $departmentExtra_MB = 'AND ('.substr($departmentExtra_MB, 0, -4).')';
                    }
                    if ($departmentExtra_IA != '') {
                        $departmentExtra_IA = 'AND ('.substr($departmentExtra_IA, 0, -4).')';
                    }
                    $personExtra_MB = '';
                    $personExtra_IA = '';
                    foreach ($tawasulPersonIDs as $tawasulPersonID) { //INCLUDE ONLY SELECTED STUDENTS
                        $dataDepartments['person_MB'.$tawasulPersonID] = $tawasulPersonID;
                        $personExtra_MB .= 'tawasulMarkbookEntry.tawasulPersonIDStudent=:person_MB'.$tawasulPersonID.' OR ';
                        $dataDepartments['person_IA'.$tawasulPersonID] = $tawasulPersonID;
                        $personExtra_IA .= 'tawasulInternalAssessmentEntry.tawasulPersonIDStudent=:person_IA'.$tawasulPersonID.' OR ';
                    }
                    if ($personExtra_MB != '') {
                        $personExtra_MB = 'AND ('.substr($personExtra_MB, 0, -4).')';
                    }
                    if ($personExtra_IA != '') {
                        $personExtra_IA = 'AND ('.substr($personExtra_IA, 0, -4).')';
                    }

                    $dataType = $validator->sanitizeAlphaNumeric($dataType);
                    $sqlDepartments = '(SELECT DISTINCT tawasulDepartment.name AS department
                        FROM tawasulMarkbookEntry
                        JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID)
                        JOIN tawasulCourseClass ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                        JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                        JOIN tawasulScale ON (tawasulMarkbookColumn.tawasulScaleID'.ucfirst($dataType)."=tawasulScale.tawasulScaleID)
                        JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.firstDay<=completeDate AND tawasulSchoolYearTerm.lastDay>=completeDate)
                        JOIN tawasulSchoolYear ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                        WHERE complete='Y' AND completeDate<='".date('Y-m-d')."' AND (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID)>3 AND ".$dataType."Value!='' AND ".$dataType."Value IS NOT NULL $departmentExtra_MB $personExtra_MB)
                        UNION
                        (SELECT DISTINCT tawasulDepartment.name AS department
                        FROM tawasulInternalAssessmentEntry
                        JOIN tawasulInternalAssessmentColumn ON (tawasulInternalAssessmentEntry.tawasulInternalAssessmentColumnID=tawasulInternalAssessmentColumn.tawasulInternalAssessmentColumnID)
                        JOIN tawasulCourseClass ON (tawasulInternalAssessmentColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                        JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                        JOIN tawasulScale ON (tawasulInternalAssessmentColumn.tawasulScaleID".ucfirst($dataType)."=tawasulScale.tawasulScaleID)
                        JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.firstDay<=completeDate AND tawasulSchoolYearTerm.lastDay>=completeDate)
                        JOIN tawasulSchoolYear ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                        WHERE complete='Y' AND completeDate<='".date('Y-m-d')."' AND (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID)>3 AND ".$dataType."Value!='' AND ".$dataType."Value IS NOT NULL $departmentExtra_IA $personExtra_IA)
                        ORDER BY department";
                    $resultDepartments = $connection2->prepare($sqlDepartments);
                    $resultDepartments->execute($dataDepartments);
                } catch (PDOException $e) {
                }
                while ($rowDepartments = $resultDepartments->fetch()) {
                    $departments[$departmentCount]['department'] = $rowDepartments['department'];
                    $departments[$departmentCount]['colour'] = $colours[$departmentCount % 12];
                    ++$departmentCount;
                }

                //GET GRADES & TERMS
                try {
                    $dataGrades = array();
                    $departmentExtra_MB = '';
                    $departmentExtra_IA = '';
                    foreach ($tawasulDepartmentIDs as $tawasulDepartmentID) { //INCLUDE ONLY SELECTED DEPARTMENTS
                        $dataGrades['department_MB'.$tawasulDepartmentID] = $tawasulDepartmentID;
                        $departmentExtra_MB .= 'tawasulDepartment.tawasulDepartmentID=:department_MB'.$tawasulDepartmentID.' OR ';
                        $dataGrades['department_IA'.$tawasulDepartmentID] = $tawasulDepartmentID;
                        $departmentExtra_IA .= 'tawasulDepartment.tawasulDepartmentID=:department_IA'.$tawasulDepartmentID.' OR ';
                    }
                    if ($departmentExtra_MB != '') {
                        $departmentExtra_MB = 'AND ('.substr($departmentExtra_MB, 0, -4).')';
                    }
                    if ($departmentExtra_IA != '') {
                        $departmentExtra_IA = 'AND ('.substr($departmentExtra_IA, 0, -4).')';
                    }
                    $personExtra_MB = '';
                    $personExtra_IA = '';
                    foreach ($tawasulPersonIDs as $tawasulPersonID) { //INCLUDE ONLY SELECTED STUDENTS
                        $dataGrades['person_MB'.$tawasulPersonID] = $tawasulPersonID;
                        $personExtra_MB .= 'tawasulMarkbookEntry.tawasulPersonIDStudent=:person_MB'.$tawasulPersonID.' OR ';
                        $dataGrades['person_IA'.$tawasulPersonID] = $tawasulPersonID;
                        $personExtra_IA .= 'tawasulInternalAssessmentEntry.tawasulPersonIDStudent=:person_IA'.$tawasulPersonID.' OR ';
                    }
                    if ($personExtra_MB != '') {
                        $personExtra_MB = 'AND ('.substr($personExtra_MB, 0, -4).')';
                    }
                    if ($personExtra_IA != '') {
                        $personExtra_IA = 'AND ('.substr($personExtra_IA, 0, -4).')';
                    }
                    $sqlGrades = '(SELECT tawasulSchoolYearTerm.sequenceNumber AS termSequence, tawasulSchoolYear.sequenceNumber AS yearSequence, tawasulSchoolYear.name AS year, tawasulSchoolYearTerm.name AS term, tawasulSchoolYearTerm.tawasulSchoolYearTermID AS termID, tawasulDepartment.name AS department, tawasulMarkbookColumn.name AS markbook, completeDate, '.$dataType.', tawasulScaleID'.ucfirst($dataType).', '.$dataType.'Value, '.$dataType.'Descriptor, (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID) AS totalGrades, (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID AND sequenceNumber>=(SELECT sequenceNumber FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID AND value=tawasulMarkbookEntry.'.$dataType.'Value) ORDER BY sequenceNumber DESC) AS gradePosition
                        FROM tawasulMarkbookEntry
                        JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID)
                        JOIN tawasulCourseClass ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                        JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                        JOIN tawasulScale ON (tawasulMarkbookColumn.tawasulScaleID'.ucfirst($dataType)."=tawasulScale.tawasulScaleID)
                        JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.firstDay<=completeDate AND tawasulSchoolYearTerm.lastDay>=completeDate)
                        JOIN tawasulSchoolYear ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                        WHERE complete='Y' AND completeDate<='".date('Y-m-d')."' AND (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID)>3 AND ".$dataType."Value!='' AND ".$dataType."Value IS NOT NULL $departmentExtra_MB $personExtra_MB)
                        UNION
                        (SELECT tawasulSchoolYearTerm.sequenceNumber AS termSequence, tawasulSchoolYear.sequenceNumber AS yearSequence, tawasulSchoolYear.name AS year, tawasulSchoolYearTerm.name AS term, tawasulSchoolYearTerm.tawasulSchoolYearTermID AS termID, tawasulDepartment.name AS department, tawasulInternalAssessmentColumn.name AS markbook, completeDate, ".$dataType.', tawasulScaleID'.ucfirst($dataType).', '.$dataType.'Value, '.$dataType.'Descriptor, (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID) AS totalGrades, (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID AND sequenceNumber>=(SELECT sequenceNumber FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID AND value=tawasulInternalAssessmentEntry.'.$dataType.'Value) ORDER BY sequenceNumber DESC) AS gradePosition
                        FROM tawasulInternalAssessmentEntry
                        JOIN tawasulInternalAssessmentColumn ON (tawasulInternalAssessmentEntry.tawasulInternalAssessmentColumnID=tawasulInternalAssessmentColumn.tawasulInternalAssessmentColumnID)
                        JOIN tawasulCourseClass ON (tawasulInternalAssessmentColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                        JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                        JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                        JOIN tawasulScale ON (tawasulInternalAssessmentColumn.tawasulScaleID'.ucfirst($dataType)."=tawasulScale.tawasulScaleID)
                        JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.firstDay<=completeDate AND tawasulSchoolYearTerm.lastDay>=completeDate)
                        JOIN tawasulSchoolYear ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                        WHERE complete='Y' AND completeDate<='".date('Y-m-d')."' AND (SELECT count(*) FROM tawasulScaleGrade WHERE tawasulScaleID=tawasulScale.tawasulScaleID)>3 AND ".$dataType."Value!='' AND ".$dataType."Value IS NOT NULL $departmentExtra_IA $personExtra_IA)
                        ORDER BY yearSequence, termSequence, completeDate, markbook";
                    $resultGrades = $connection2->prepare($sqlGrades);
                    $resultGrades->execute($dataGrades);
                } catch (PDOException $e) {
                }

                if ($resultGrades->rowCount() < 1) {
                    echo $page->getBlankSlate();;
                } else {
                    //Prep grades & terms
                    $grades = array();
                    $gradeCount = 0;
                    $lastDepartment = '';
                    $terms = array();
                    $termCount = 0;
                    $lastTerm = '';
                    while ($rowGrades = $resultGrades->fetch()) {
                        //Store grades
                        $grades[$gradeCount]['department'] = $rowGrades['department'];
                        $grades[$gradeCount]['year'] = $rowGrades['year'];
                        $grades[$gradeCount]['term'] = $rowGrades['term'];
                        $grades[$gradeCount]['termID'] = $rowGrades['termID'];
                        $grades[$gradeCount]['markbook'] = $rowGrades['markbook'];
                        $grades[$gradeCount]['completeDate'] = $rowGrades['completeDate'];
                        $grades[$gradeCount][$dataType] = $rowGrades[$dataType];
                        $grades[$gradeCount]['tawasulScaleID'.ucfirst($dataType)] = $rowGrades['tawasulScaleID'.ucfirst($dataType)];
                        $grades[$gradeCount][$dataType.'Value'] = $rowGrades[$dataType.'Value'];
                        $grades[$gradeCount][$dataType.'Descriptor'] = $rowGrades[$dataType.'Descriptor'];
                        $grades[$gradeCount]['totalGrades'] = $rowGrades['totalGrades'];
                        $grades[$gradeCount]['gradePosition'] = $rowGrades['gradePosition'];
                        $grades[$gradeCount]['gradeWeighted'] = round($rowGrades['gradePosition'] / $rowGrades['totalGrades'], 2);

                        //Store terms for axis
                        if ($lastTerm != $rowGrades['term']) {
                            $terms[$termCount]['year'] = $rowGrades['year'];
                            $terms[$termCount]['term'] = $rowGrades['term'];
                            $terms[$termCount]['termID'] = $rowGrades['termID'];
                            $terms[$termCount]['termFullName'] = $rowGrades['year'].' '.$rowGrades['term'];
                            ++$termCount;
                        }
                        $lastTerm = $rowGrades['term'];

                        ++$gradeCount;
                    }

                    //POPULATE FINAL DATA
                    $finalData = array();
                    foreach ($terms as $term) {
                        foreach ($departments as $department) {
                            $finalData[$term['termID']][$department['department']]['termID'] = $term['termID'];
                            $finalData[$term['termID']][$department['department']]['termFullName'] = $term['termFullName'];
                            $finalData[$term['termID']][$department['department']]['department'] = $department['department'];
                            $finalData[$term['termID']][$department['department']]['gradeWeightedTotal'] = null;
                            $finalData[$term['termID']][$department['department']]['gradeWeightedDivisor'] = 0;
                            $finalData[$term['termID']][$department['department']]['gradeWeightedMean'] = null;

                            foreach ($grades as $grade) {
                                if ($grade['termID'] == $term['termID'] and $grade['department'] == $department['department']) {
                                    $finalData[$term['termID']][$department['department']]['gradeWeightedTotal'] += $grade['gradeWeighted'];
                                    ++$finalData[$term['termID']][$department['department']]['gradeWeightedDivisor'];
                                }
                            }
                        }
                    }

                    //CALCULATE AVERAGES
                    foreach ($departments as $department) {
                        foreach ($terms as $term) {
                            if ($finalData[$term['termID']][$department['department']]['gradeWeightedDivisor'] > 0) {
                                $finalData[$term['termID']][$department['department']]['gradeWeightedMean'] = round(($finalData[$term['termID']][$department['department']]['gradeWeightedTotal'] / $finalData[$term['termID']][$department['department']]['gradeWeightedDivisor']), 2);
                            } else {
                                $finalData[$term['termID']][$department['department']]['gradeWeightedMean'] = 'null';
                            }
                        }
                    }

                    if (count($grades) < 4) {
                        echo "<div class='warning'>";
                        echo __('There are less than 4 data points, so no graph can be produced.');
                        echo '</div>';
                    } else {
                        //PLOT DATA
                        echo '<script type="text/javascript" src="'.$session->get('absoluteURL').'/lib/Chart.js/3.0/chart.min.js"></script>';

                        echo "<p style='margin-top: 20px; margin-bottom: 5px'><b>".__('Data').'</b></p>';
                        echo '<div style="width:100%">';
                        echo '<div>';
                        echo '<canvas id="canvas"></canvas>';
                        echo '</div>';
                        echo '</div>';

                        ?>
                        <script>
                            var lineChartData = {
                                labels : [
                                    <?php
                                        foreach ($terms as $term) {
                                            echo "'".$term['termFullName']."',";
                                        }
                                    ?>								],
                                datasets : [
                                    <?php
                                        foreach ($departments as $department) {
                                        ?>
                                        {
                                            label: "<?php echo $department['department']; ?>",
                                            backgroundColor : "rgba(<?php echo $department['colour'] ?>,0.6)",
                                            borderColor : "rgba(<?php echo $department['colour'] ?>,1)",
                                            hoverBorderColor : "rgba(<?php echo $department['colour'] ?>,0)",
                                            pointColor : "rgba(<?php echo $department['colour'] ?>,1)",
                                            pointBorderColor : "rgba(<?php echo $department['colour'] ?>,0.4)",
                                            pointBackgroundColor : "rgba(<?php echo $department['colour'] ?>,4)",
                                            lineTension: 0.3,
                                            data : [
                                                <?php
                                                    foreach ($terms as $term) {
                                                        if ($finalData[$term['termID']][$department['department']]['termID'] == $term['termID']) {
                                                            if ($finalData[$term['termID']][$department['department']]['department'] == $department['department']) {
                                                                echo $finalData[$term['termID']][$department['department']]['gradeWeightedMean'].',';
                                                            }
                                                        }
                                                    }
                                        ?>
                                            ]
                                        },
                                    <?php

                                    }
                                ?>
                            ]
                            }

                            window.onload = function(){
                                var ctx = document.getElementById("canvas").getContext("2d");
                                window.myLine = new Chart(ctx, {
                                    type: 'line',
                                    data: lineChartData,
                                    options: {
                                        responsive: true,
                                        spanGaps: true,
                                        showTooltips: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                min: 0,
                                                max: 1.0,
                                                stepSize: 0.1,
                                            }
                                        }
                                    }
                                });
                            }
                        </script>
                        <?php

                    }
                }
            }
        }
    }
}
