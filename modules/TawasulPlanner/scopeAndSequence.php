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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Scope And Sequence'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/scopeAndSequence.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    echo '<h2>';
    echo __('Choose Course');
    echo '</h2>';

    $tawasulCourseIDs = array();
    if (isset($_POST['tawasulCourseID'])) {
        $tawasulCourseIDs = $_POST['tawasulCourseID'] ?? '';
    }
    $tawasulYearGroupID = '';
    if (isset($_POST['tawasulYearGroupID'])) {
        $tawasulYearGroupID = $_POST['tawasulYearGroupID'] ?? '';
    }

    $form = Form::create('action', $session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/scopeAndSequence.php");

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $options = array();
    
        $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
        $sql = "SELECT tawasulCourse.tawasulCourseID, tawasulCourse.name, tawasulDepartment.name AS department FROM tawasulCourse LEFT JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND NOT tawasulYearGroupIDList='' AND map='Y' ORDER BY department, tawasulCourse.nameShort";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    while ($row = $result->fetch()) {
        $options[$row["department"]][$row["tawasulCourseID"]] = $row["name"];
    }

    $row = $form->addRow();
        $row->addLabel('tawasulCourseID', __('Course'));
        $row->addSelect('tawasulCourseID')->fromArray($options)->selectMultiple()->selected($tawasulCourseIDs);

    $row = $form->addRow();
        $row->addLabel('tawasulYearGroupID', __('Year Group'));
        $row->addSelectYearGroup('tawasulYearGroupID')->selected($tawasulYearGroupID);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if (count($tawasulCourseIDs) > 0) {
        //Set up for edit access
        $highestAction = getHighestGroupedAction($guid, '/modules/TawasulPlanner/units.php', $connection2);
        $departments = array();
        if ($highestAction == 'Unit Planner_learningAreas') {
            $departmentCount = 1 ;
            try {
                $dataSelect = array('tawasulPersonID' => $session->get('tawasulPersonID'));
                $sqlSelect = "SELECT tawasulDepartment.tawasulDepartmentID FROM tawasulDepartment JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)') ORDER BY tawasulDepartment.name";
                $resultSelect = $connection2->prepare($sqlSelect);
                $resultSelect->execute($dataSelect);
            } catch (PDOException $e) { }
            while ($rowSelect = $resultSelect->fetch()) {
                $departments[$departmentCount] = $rowSelect['tawasulDepartmentID'];
                $departmentCount ++;
            }
        }

        //Set up stats variables
        $countCourses = 0 ;
        $countCoursesNoUnits = 0 ;
        $coursesNoUnits = '';
        $countUnits = 0;
        $countUnitsNoKeywords = 0 ;
        $unitsNoKeywords = '';

        //Cycle through courses
        foreach ($tawasulCourseIDs as $tawasulCourseID) {
            //Check course exists
            try {
                $data = array();
                $sqlWhere = '';
                if ($tawasulYearGroupID != '') {
                    $data['tawasulYearGroupID'] = '%'.$tawasulYearGroupID.'%';
                    $sqlWhere = ' AND tawasulYearGroupIDList LIKE :tawasulYearGroupID ';
                }
                $data['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
                $data['tawasulCourseID'] = $tawasulCourseID;
                $sql = "SELECT tawasulCourse.*, tawasulDepartment.name AS department FROM tawasulCourse LEFT JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND NOT tawasulYearGroupIDList='' AND tawasulCourseID=:tawasulCourseID AND map='Y' $sqlWhere ORDER BY department, nameShort";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() == 1) {
                $countCourses ++ ;

                $row = $result->fetch();

                //Can this course's units be edited?
                $canEdit = false ;
                if ($highestAction == 'Unit Planner_all') {
                    $canEdit = true ;
                }
                else if ($highestAction == 'Unit Planner_learningAreas') {
                    foreach ($departments AS $department) {
                        if ($department == $row['tawasulDepartmentID']) {
                            $canEdit = true ;
                        }
                    }
                }

                echo '<h3 class=\'mt-4\'>';
                echo $row['name'].' - '.$row['nameShort'];
                echo '</h3>';

                
                    $dataUnit = array('tawasulCourseID' => $tawasulCourseID);
                    $sqlUnit = 'SELECT tawasulUnitID, tawasulUnit.name, tawasulUnit.description, attachment, tags FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulUnit.tawasulCourseID=:tawasulCourseID AND active=\'Y\' AND tawasulCourse.map=\'Y\' AND tawasulUnit.map=\'Y\' ORDER BY ordering, name';
                    $resultUnit = $connection2->prepare($sqlUnit);
                    $resultUnit->execute($dataUnit);

                if ($resultUnit->rowCount() < 1) {
                    echo $page->getBlankSlate();
                    $countCoursesNoUnits ++;
                    $coursesNoUnits .= $row['nameShort'].', ';
                }
                else {
                    echo "<table cellspacing='0' style='width: 100%'>";
                    echo "<tr class='head'>";
                    echo '<th style=\'width: 15%\'>';
                    echo __('Unit');
                    echo '</th>';
                    echo '<th style=\'width: 45%\'>';
                    echo __('Description');
                    echo '</th>';
                    echo "<th style=\'width: 30%\'>";
                    echo __('Concepts & Keywords');
                    echo '</th>';
                    echo "<th style='width: 10%'>";
                    echo __('Actions');
                    echo '</th>';
                    echo '</tr>';

                    $count = 0;
                    $rowNum = 'odd';
                    while ($rowUnit = $resultUnit->fetch()) {
                        if ($count % 2 == 0) {
                            $rowNum = 'even';
                        } else {
                            $rowNum = 'odd';
                        }
                        ++$count;
                        $countUnits ++;

                        //COLOR ROW BY STATUS!
                        echo "<tr class=$rowNum>";
                        echo '<td>';
                        echo $rowUnit['name'].'<br/>';
                        echo '</td>';
                        echo '<td>';
                        echo $rowUnit['description'].'<br/>';
                        if ($rowUnit['attachment'] != '') {
                            echo "<br/><br/><a href='".$session->get('absoluteURL').'/'.$rowUnit['attachment']."'>".__('Download Unit Outline').'</a></li>';
                        }
                        echo '</td>';
                        echo '<td>';
                        if ($rowUnit['tags'] == '') {
                            $countUnitsNoKeywords ++;
                            $unitsNoKeywords .= $row['nameShort'].' ('.$rowUnit['name'].'), ';
                        }
                        else {
                            $tags = explode(',', $rowUnit['tags']);
                            $tagsOutput = '' ;
                            foreach ($tags as $tag) {
                                $tagsOutput .= "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/TawasulPlanner/conceptExplorer.php&tag=$tag'>".$tag.'</a>, ';
                            }
                            if ($tagsOutput != '')
                                $tagsOutput = substr($tagsOutput, 0, -2);
                            echo $tagsOutput;
                        }
                        echo '</td>';
                        echo '<td>';
                            if ($canEdit) {
                                echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/TawasulPlanner/units_edit.php&tawasulUnitID=".$rowUnit['tawasulUnitID']."&tawasulCourseID=".$row['tawasulCourseID']."&tawasulSchoolYearID=".$row['tawasulSchoolYearID']."'><img title='".__('Edit')."' src='./themes/".$session->get('tawasulThemeName')."/img/config.png'/></a> ";
                            }
                            echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/TawasulPlanner/units_dump.php&tawasulCourseID=".$row['tawasulCourseID']."&tawasulUnitID=".$rowUnit['tawasulUnitID']."&tawasulSchoolYearID=".$row['tawasulSchoolYearID']."'><img title='".__('View')."' src='./themes/".$session->get('tawasulThemeName')."/img/plus.png'/></a>";
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            }
        }

        echo "<div class='success'>";
            echo '<b>'.__('Total Courses').'</b>: '.$countCourses.'<br/>';
            echo '<b>'.__('Courses Without Units').'</b>: '.$countCoursesNoUnits.'<br/>';
            if ($coursesNoUnits != '') {
                print '<i>'.substr($coursesNoUnits, 0, -2).'</i><br/>';
            }
            echo '<b>'.__('Total Units').'</b>: '.$countUnits.'<br/>';
            echo '<b>'.__('Units Without Concepts & Keywords').'</b>: '.$countUnitsNoKeywords.'<br/>';
            if ($unitsNoKeywords != '') {
                print '<i>'.substr($unitsNoKeywords, 0, -2).'</i><br/>';
            }
        echo "</div>";
    }
}
