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
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Work Summary by Form Group'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/report_workSummary_byFormGroup.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    echo '<p>';
    echo __('This report draws data from the Markbook, Planner and Behaviour modules to give an overview of student performance and work completion. It only counts Online Submission data when submission is set to Required.');
    echo '</p>';

    echo '<h2>';
    echo __('Choose Form Group');
    echo '</h2>';

    $tawasulFormGroupID = isset($_GET['tawasulFormGroupID'])? $_GET['tawasulFormGroupID'] : null;

    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_workSummary_byFormGroup.php');

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $session->get('tawasulSchoolYearID'))->required()->selected($tawasulFormGroupID);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    if ($tawasulFormGroupID != '') {
        echo '<h2>';
        echo __('Report Data');
        echo '</h2>';

        
            $data = array('tawasulFormGroupID' => $tawasulFormGroupID);
            $sql = "SELECT surname, preferredName, name, tawasulPerson.tawasulPersonID, tawasulPerson.dateStart FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID ORDER BY surname, preferredName";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        echo "<table cellspacing='0' style='width: 100%'>";
        echo "<tr class='head'>";
        echo '<th>';
        echo __('Student');
        echo '</th>';
        echo '<th>';
        echo __('Satisfactory');
        echo '</th>';
        echo '<th>';
        echo __('Unsatisfactory');
        echo '</th>';
        echo '<th>';
        echo __('On Time');
        echo '</th>';
        echo '<th>';
        echo __('Late');
        echo '</th>';
        echo '<th>';
        echo __('Incomplete');
        echo '</th>';
        echo '</tr>';

        $count = 0;
        $rowNum = 'odd';
        while ($row = $result->fetch()) {
            if ($count % 2 == 0) {
                $rowNum = 'even';
            } else {
                $rowNum = 'odd';
            }
            ++$count;

            //COLOR ROW BY STATUS!
            echo "<tr class=$rowNum>";
            echo '<td>';
            echo "<a href='index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=".$row['tawasulPersonID']."&subpage=Homework'>".Format::name('', $row['preferredName'], $row['surname'], 'Student', true).'</a>';
            echo '</td>';
            echo "<td style='width:15%'>";
            
                $dataData = array('tawasulPersonID' => $row['tawasulPersonID'], 'dateStart' => $row['dateStart'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                $sqlData = "SELECT * FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) JOIN tawasulCourseClass ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulPersonIDStudent=:tawasulPersonID  AND (attainmentConcern='N' OR attainmentConcern IS NULL) AND (effortConcern='N' OR effortConcern IS NULL) AND tawasulSchoolYearID=:tawasulSchoolYearID AND complete='Y' AND (:dateStart IS NULL OR completeDate >= :dateStart)";
                $resultData = $connection2->prepare($sqlData);
                $resultData->execute($dataData);

            if ($resultData->rowCount() < 1) {
                echo '0';
            } else {
                echo $resultData->rowCount();
            }
            echo '</td>';
            echo "<td style='width:15%'>";
            //Count up unsatisfactory from markbook
            
                $dataData = array('tawasulPersonID' => $row['tawasulPersonID'], 'dateStart' => $row['dateStart'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                $sqlData = "SELECT * FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) JOIN tawasulCourseClass ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulPersonIDStudent=:tawasulPersonID AND (attainmentConcern='Y' OR effortConcern='Y') AND tawasulSchoolYearID=:tawasulSchoolYearID AND complete='Y' AND (:dateStart IS NULL OR completeDate >= :dateStart)";
                $resultData = $connection2->prepare($sqlData);
                $resultData->execute($dataData);
            $dataData2 = array();
            $sqlWhere = ' AND (';
            $countWhere = 0;
            while ($rowData = $resultData->fetch()) {
                if ($rowData['tawasulPlannerEntryID'] != '') {
                    if ($countWhere > 0) {
                        $sqlWhere .= ' AND ';
                    }
                    $dataData2['data2'.$countWhere] = $rowData['tawasulPlannerEntryID'];
                    $sqlWhere .= ' NOT tawasulBehaviour.tawasulPlannerEntryID=:data2'.$countWhere;
                    ++$countWhere;
                }
            }
            if ($countWhere > 0) {
                $sqlWhere .= ' OR tawasulBehaviour.tawasulPlannerEntryID IS NULL';
            }
            $sqlWhere .= ')';
            if ($sqlWhere == ' AND ()') {
                $sqlWhere = '';
            }

			//Count up unsatisfactory from behaviour, counting out $sqlWhere
			
				$dataData2['tawasulPersonID'] = $row['tawasulPersonID'];
				$dataData2['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
				$sqlData2 = "SELECT * FROM tawasulBehaviour WHERE tawasulBehaviour.tawasulPersonID=:tawasulPersonID AND type='Negative' AND (descriptor='Classwork - Unacceptable' OR descriptor='Homework - Unacceptable') AND tawasulSchoolYearID=:tawasulSchoolYearID $sqlWhere";
				$resultData2 = $connection2->prepare($sqlData2);
				$resultData2->execute($dataData2);

            if (($resultData->rowCount() + $resultData2->rowCount()) < 1) {
                echo '0';
            } else {
                echo $resultData->rowCount() + $resultData2->rowCount();
            }
            echo '</td>';


            echo "<td style='width:15%'>";
			//Count up on time in planner
            
				$dataData['tawasulPersonID'] = $row['tawasulPersonID'];
                $dataData['dateStart'] = $row['dateStart'];
				$dataData['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
				$sqlData = "SELECT DISTINCT tawasulPlannerEntryHomework.tawasulPlannerEntryID FROM tawasulPlannerEntryHomework JOIN tawasulPlannerEntry ON (tawasulPlannerEntryHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulPlannerEntryHomework.tawasulPersonID=:tawasulPersonID AND status='On Time' AND tawasulSchoolYearID=:tawasulSchoolYearID AND homeworkSubmissionRequired='Required' AND (:dateStart IS NULL OR tawasulPlannerEntry.date >= :dateStart) ";
				$resultData = $connection2->prepare($sqlData);
				$resultData->execute($dataData);

			//Print out total on times
			if (($resultData->rowCount() < 1)) {
				echo '0';
			} else {
				echo $resultData->rowCount();
			}
            echo '</td>';




            echo "<td style='width:15%'>";
			//Count up lates in markbook
			
				$dataData = array('tawasulPersonID' => $row['tawasulPersonID'], 'dateStart' => $row['dateStart'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
				$sqlData = "SELECT DISTINCT * FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) JOIN tawasulCourseClass ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulPersonIDStudent=:tawasulPersonID AND (attainmentValue='Late' OR effortValue='Late') AND tawasulSchoolYearID=:tawasulSchoolYearID AND complete='Y' AND (:dateStart IS NULL OR completeDate >= :dateStart)";
				$resultData = $connection2->prepare($sqlData);
				$resultData->execute($dataData);

            $dataData2 = array();
            $dataData3 = array();
            $sqlWhere = '';
            $sqlWhere2 = ' AND (';
            $countWhere = 0;
            while ($rowData = $resultData->fetch()) {
                $dataData2['data2i'.$countWhere] = $rowData['tawasulCourseClassID'];
                $sqlWhere .= ' AND NOT tawasulPlannerEntry.tawasulCourseClassID=:data2i'.$countWhere;
                if ($rowData['tawasulPlannerEntryID'] != '') {
                    if ($countWhere > 0) {
                        $sqlWhere2 .= ' AND ';
                    }
                    $dataData2['data2pl'.$countWhere] = $rowData['tawasulPlannerEntryID'];
                    $sqlWhere2 .= ' NOT tawasulBehaviour.tawasulPlannerEntryID=:data2pl'.$countWhere;
                    ++$countWhere;
                }
            }
            if ($countWhere > 0) {
                $sqlWhere2 .= ' OR tawasulBehaviour.tawasulPlannerEntryID IS NULL';
            }
            $sqlWhere2 .= ')';
            if ($sqlWhere2 == ' AND ()') {
                $sqlWhere2 = '';
            }

			//Count up lates in planner, counting out $sqlWhere
			
				$dataData2['tawasulPersonID'] = $row['tawasulPersonID'];
				$dataData2['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
				$sqlData2 = "SELECT DISTINCT tawasulPlannerEntryHomework.tawasulPlannerEntryID FROM tawasulPlannerEntryHomework JOIN tawasulPlannerEntry ON (tawasulPlannerEntryHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulPlannerEntryHomework.tawasulPersonID=:tawasulPersonID AND status='Late' AND tawasulSchoolYearID=:tawasulSchoolYearID AND homeworkSubmissionRequired='Required' $sqlWhere";
				$resultData2 = $connection2->prepare($sqlData2);
				$resultData2->execute($dataData2);

            $sqlWhere3 = ' AND (';
            $countWhere = 0;
            while ($rowData2 = $resultData2->fetch()) {
                if ($rowData2['tawasulPlannerEntryID'] != '') {
                    if ($countWhere > 0) {
                        $sqlWhere3 .= ' AND ';
                    }
                    $dataData3['data3i'.$countWhere] = $rowData2['tawasulPlannerEntryID'];
                    $sqlWhere3 .= ' NOT tawasulBehaviour.tawasulPlannerEntryID=:data3i'.$countWhere;
                    ++$countWhere;
                }
            }
            if ($countWhere > 0) {
                $sqlWhere3 .= ' OR tawasulBehaviour.tawasulPlannerEntryID IS NULL';
            }
            $sqlWhere3 .= ')';
            if ($sqlWhere3 == ' AND ()') {
                $sqlWhere3 = '';
            }

			//Count up lates from behaviour, counting out $sqlWhere2 and $sqlWhere3
			
				$dataData3['tawasulPersonID'] = $row['tawasulPersonID'];
				$dataData3['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
				$sqlData3 = "SELECT * FROM tawasulBehaviour WHERE tawasulBehaviour.tawasulPersonID=:tawasulPersonID AND type='Negative' AND (descriptor='Classwork - Late' OR descriptor='Homework - Late') AND tawasulSchoolYearID=:tawasulSchoolYearID $sqlWhere2 $sqlWhere3";
				$resultData3 = $connection2->prepare($sqlData3);
				$resultData3->execute($dataData3);
			//Print out total late
			if (($resultData->rowCount() + $resultData2->rowCount() + $resultData3->rowCount()) < 1) {
				echo '0';
			} else {
				echo $resultData->rowCount() + $resultData2->rowCount() + $resultData3->rowCount();
			}
            echo '</td>';
            echo "<td style='width:15%'>";
			//Count up incompletes in markbook
			
				$dataData = array('tawasulPersonID' => $row['tawasulPersonID'], 'dateStart' => $row['dateStart'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
				$sqlData = "SELECT * FROM tawasulMarkbookEntry JOIN tawasulMarkbookColumn ON (tawasulMarkbookEntry.tawasulMarkbookColumnID=tawasulMarkbookColumn.tawasulMarkbookColumnID) JOIN tawasulCourseClass ON (tawasulMarkbookColumn.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulPersonIDStudent=:tawasulPersonID AND (attainmentValue='Incomplete' OR effortValue='Incomplete') AND tawasulSchoolYearID=:tawasulSchoolYearID AND complete='Y' AND (:dateStart IS NULL OR completeDate >= :dateStart)";
				$resultData = $connection2->prepare($sqlData);
				$resultData->execute($dataData);

            $dataData2 = array();
            $dataData3 = array();
            $dataData4 = array();
            $sqlWhere = '';
            $sqlWhere2 = ' AND (';
            $countWhere = 0;
            while ($rowData = $resultData->fetch()) {
                $dataData2['data2i'.$countWhere] = $rowData['tawasulCourseClassID'];
                $sqlWhere .= ' AND NOT tawasulPlannerEntry.tawasulCourseClassID=:data2i'.$countWhere;
                if ($rowData['tawasulPlannerEntryID'] != '') {
                    if ($countWhere > 0) {
                        $sqlWhere2 .= ' AND ';
                    }
                    $dataData4['data4pl'.$countWhere] = $rowData['tawasulPlannerEntryID'];
                    $sqlWhere2 .= ' NOT tawasulBehaviour.tawasulPlannerEntryID=:data4pl'.$countWhere;
                    ++$countWhere;
                }
            }
            if ($countWhere > 0) {
                $sqlWhere2 .= ' OR tawasulBehaviour.tawasulPlannerEntryID IS NULL';
            }
            $sqlWhere2 .= ')';
            if ($sqlWhere2 == ' AND ()') {
                $sqlWhere2 = '';
            }

			//Count up incompletes in planner, counting out $sqlWhere
			
				$dataData2['tawasulPersonID'] = $row['tawasulPersonID'];
				$dataData2['dateStart'] = $row['dateStart'];
				$dataData2['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
				$dataData2['homeworkDueDateTime'] = date('Y-m-d H:i:s');
				$dataData2['date'] = date('Y-m-d');
				$sqlData2 = "SELECT * FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND homeworkSubmission='Y' AND homeworkDueDateTime<:homeworkDueDateTime AND homeworkSubmissionRequired='Required' AND (:dateStart IS NULL OR tawasulPlannerEntry.date >= :dateStart) AND date<=:date $sqlWhere";
				$resultData2 = $connection2->prepare($sqlData2);
				$resultData2->execute($dataData2);

            $countIncomplete = 0;
            $sqlWhere3 = ' AND (';
            $countWhere = 0;
            while ($rowData2 = $resultData2->fetch()) {
                
                    $dataData3['tawasulPersonID'] = $row['tawasulPersonID'];
                    $dataData3['tawasulPlannerEntryID'] = $rowData2['tawasulPlannerEntryID'];
                    $sqlData3 = "SELECT DISTINCT tawasulPlannerEntryHomework.tawasulPlannerEntryID FROM tawasulPlannerEntryHomework WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND tawasulPersonID=:tawasulPersonID AND version='Final'";
                    $resultData3 = $connection2->prepare($sqlData3);
                    $resultData3->execute($dataData3);

                if ($resultData3->rowCount() < 1) {
                    ++$countIncomplete;
                }
                if ($rowData2['tawasulPlannerEntryID'] != '') {
                    if ($countWhere > 0) {
                        $sqlWhere3 .= ' AND ';
                    }
                    $dataData4['data4i'.$countWhere] = $rowData2['tawasulPlannerEntryID'];
                    $sqlWhere3 .= ' NOT tawasulBehaviour.tawasulPlannerEntryID=:data4i'.$countWhere;
                    ++$countWhere;
                }
            }
            if ($countWhere > 0) {
                $sqlWhere3 .= ' OR tawasulBehaviour.tawasulPlannerEntryID IS NULL';
            }
            $sqlWhere3 .= ')';
            if ($sqlWhere3 == ' AND ()') {
                $sqlWhere3 = '';
            }

			//Count up incompletes from behaviour, counting out $sqlWhere2 and $sqlWhere3
			
				$dataData4['tawasulPersonID'] = $row['tawasulPersonID'];
				$dataData4['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
				$sqlData4 = "SELECT * FROM tawasulBehaviour WHERE tawasulBehaviour.tawasulPersonID=:tawasulPersonID AND type='Negative' AND (descriptor='Classwork - Incomplete' OR descriptor='Homework - Incomplete') AND tawasulSchoolYearID=:tawasulSchoolYearID $sqlWhere2 $sqlWhere3";
				$resultData4 = $connection2->prepare($sqlData4);
				$resultData4->execute($dataData4);

			//Print out total lates
			if (($resultData->rowCount() + $countIncomplete + $resultData4->rowCount() < 1)) {
				echo '0';
			} else {
				echo $resultData->rowCount() + $countIncomplete + $resultData4->rowCount();
			}
            echo '</td>';
            echo '</tr>';
        }
        if ($count == 0) {
            echo "<tr class=$rowNum>";
            echo '<td colspan=2>';
            echo __('There are no records to display.');
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
?>
