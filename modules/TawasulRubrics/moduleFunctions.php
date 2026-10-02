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
use Tos\Module\TawasulRubrics\Visualise;

function rubricEdit($guid, $connection2, $tawasulRubricID, $scaleName = '', $search = '', $filter2 = '')
{
    global $pdo, $session;

    $output = false;

    $data = array('tawasulRubricID' => $tawasulRubricID);

    //Get rows, columns and cells
    $sqlRows = "SELECT * FROM tawasulRubricRow WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";
    $resultRows = $pdo->executeQuery($data, $sqlRows);
    $rowCount = $resultRows->rowCount();

    $sqlColumns = "SELECT * FROM tawasulRubricColumn WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";
    $resultColumns = $pdo->executeQuery($data, $sqlColumns);
    $columnCount = $resultColumns->rowCount();

    $sqlCells = "SELECT * FROM tawasulRubricCell WHERE tawasulRubricID=:tawasulRubricID";
    $resultCells = $pdo->executeQuery($data, $sqlCells);
    $cellCount = $resultCells->rowCount();

    $sqlGradeScales = "SELECT tawasulScaleGrade.tawasulScaleGradeID, tawasulScaleGrade.* FROM tawasulRubricColumn
        JOIN tawasulScaleGrade ON (tawasulRubricColumn.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID)
        WHERE tawasulRubricColumn.tawasulRubricID=:tawasulRubricID";
    $resultGradeScales = $pdo->executeQuery($data, $sqlGradeScales);
    $gradeScales = ($resultGradeScales->rowCount() > 0)? $resultGradeScales->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

    $sqlOutcomes = "SELECT tawasulOutcome.tawasulOutcomeID, tawasulOutcome.* FROM tawasulRubricRow
        JOIN tawasulOutcome ON (tawasulRubricRow.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID)
        WHERE tawasulRubricRow.tawasulRubricID=:tawasulRubricID";
    $resultOutcomes = $pdo->executeQuery($data, $sqlOutcomes);
    $outcomes = ($resultOutcomes->rowCount() > 0)? $resultOutcomes->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

    if ($rowCount <= 0 or $columnCount <= 0) {
        $output .= "<div class='error'>";
        $output .= __('The rubric cannot be drawn.');
        $output .= '</div>';
    } else {
        $rows = $resultRows->fetchAll();
        $columns = $resultColumns->fetchAll();

        $cells = array();
        while ($rowCells = $resultCells->fetch()) {
            $cells[$rowCells['tawasulRubricRowID']][$rowCells['tawasulRubricColumnID']] = $rowCells;
        }

        $form = Form::createTable('editRubric', $session->get('absoluteURL').'/modules/'.$session->get('module').'/rubrics_edit_editCellProcess.php?tawasulRubricID='.$tawasulRubricID.'&search='.$search.'&filter2='.$filter2);
        $form->setTitle(__('Rubric Design'));

        $form->setClass('rubricTable w-full');
        $form->addHiddenValue('address', $session->get('address'));

        $form->addHeaderAction('edit', __('Edit Rows & Columns'))
            ->setURL('/modules/TawasulRubrics/rubrics_edit_editRowsColumns.php')
            ->addParam('tawasulRubricID', $tawasulRubricID)
            ->addParam('search', $search)
            ->addParam('filter2', $filter2)
            ->displayLabel();

        $row = $form->addRow()->addClass();
            $row->addContent()->addClass('rubricCellEmpty');

        // Column Headers
        for ($n = 0; $n < $columnCount; ++$n) {
            $col = $row->addColumn()->addClass('rubricHeading column'.$columns[$n]['tawasulRubricColumnID']);

            // Display grade scale, otherwise column title
            if (!empty($gradeScales[$columns[$n]['tawasulScaleGradeID']])) {
                $gradeScaleGrade = $gradeScales[$columns[$n]['tawasulScaleGradeID']];
                $col->addContent('<b>'.$gradeScaleGrade['descriptor'].'</b>')
                    ->append(' ('.$gradeScaleGrade['value'].')')
                    ->append('<br/><span class="text-xs italic">'.__($scaleName).' '.__('Scale').'</span>');
            } else {
                $col->addContent($columns[$n]['title'])->wrap('<b>', '</b>');
            }

            $col->addContent("<a onclick='return confirm(\"".__('Are you sure you want to delete this column? Any unsaved changes will be lost.')."\")' href='".$session->get('absoluteURL').'/modules/'.$session->get('module')."/rubrics_edit_deleteColumnProcess.php?tawasulRubricID=$tawasulRubricID&tawasulRubricColumnID=".$columns[$n]['tawasulRubricColumnID'].'&address='.$_GET['q']."&search=$search&filter2=$filter2'>".icon('solid', 'delete', 'size-6 text-gray-600')."</a>");
        }

        // Rows
        $count = 0;
        for ($i = 0; $i < $rowCount; ++$i) {
            $row = $form->addRow();
            $col = $row->addColumn()->addClass('rubricHeading row'.$rows[$i]['tawasulRubricRowID']);

            // Row Header
            if (!empty($outcomes[$rows[$i]['tawasulOutcomeID']])) {
                $outcome = $outcomes[$rows[$i]['tawasulOutcomeID']];
                $col->addContent('<b>'.__($outcome['name']).'</b>')
                    ->append(!empty($outcome['category'])? ('<i> - <br/>'.$outcome['category'].'</i>') : '')
                    ->append('<br/><span class="text-xs italic">'.$outcome['scope'].' '.__('Outcome').'</span>');
                $rows[$i]['title'] = $outcome['name'];
            } else {
                $col->addContent($rows[$i]['title'])->wrap('<b>', '</b>');
            }

            $col->addContent("<a onclick='return confirm(\"".__('Are you sure you want to delete this row? Any unsaved changes will be lost.')."\")' href='".$session->get('absoluteURL').'/modules/'.$session->get('module')."/rubrics_edit_deleteRowProcess.php?tawasulRubricID=$tawasulRubricID&tawasulRubricRowID=".$rows[$i]['tawasulRubricRowID'].'&address='.$_GET['q']."&search=$search&filter2=$filter2'>".icon('solid', 'delete', 'size-6 text-gray-600')."</a><br/>");

            for ($n = 0; $n < $columnCount; ++$n) {
                $cell = @$cells[$rows[$i]['tawasulRubricRowID']][$columns[$n]['tawasulRubricColumnID']];
                $row->addTextArea("cell[$count]")->setValue(isset($cell['contents'])? $cell['contents']: '')->setClass('rubricCell rubricCellEdit');

                $form->addHiddenValue("tawasulRubricCellID[$count]", isset($cell['tawasulRubricCellID'])? $cell['tawasulRubricCellID']: '');
                $form->addHiddenValue("tawasulRubricColumnID[$count]", $columns[$n]['tawasulRubricColumnID']);
                $form->addHiddenValue("tawasulRubricRowID[$count]", $rows[$i]['tawasulRubricRowID']);

                $count++;
            }
        }

        $row = $form->addRow();
            $row->addSubmit();

        $output .= $form->getOutput();

        $output .= "<style>";
        for ($i = 0; $i < $rowCount; ++$i) {
            $color = $rows[$i]['backgroundColor'] ?? '#ffffff';
            $colorValue = hexdec(substr($color, 1, 2)) + hexdec(substr($color, 3, 2)) + hexdec(substr($color, 5, 2));
            $textColor = $colorValue > 580 ? '#5b5757' : '#5b5757';
            $output .= ".row".$rows[$i]['tawasulRubricRowID'].'{ background-color: '.$color.'; color: '.$textColor.'; } ';
        }
        for ($i = 0; $i < $columnCount; ++$i) {
            $color = $columns[$i]['backgroundColor'] ?? '#ffffff';
            $colorValue = hexdec(substr($color, 1, 2)) + hexdec(substr($color, 3, 2)) + hexdec(substr($color, 5, 2));
            $textColor = empty($colorValue) || $colorValue > 450 ? '#5b5757' : '#ffffff';
            $output .= ".column".$columns[$i]['tawasulRubricColumnID'].'{ background-color: '.$color.'; color: '.$textColor.'; } ';
        }
        $output .= "</style>";
    }

    return $output;
}

//If $mark=TRUE, then marking tools are made available, otherwise it is view only
function rubricView($guid, $connection2, $tawasulRubricID, $mark, $tawasulPersonID = '', $contextDBTable = '', $contextDBTableIDField = '', $contextDBTableID = '', $contextDBTableTawasulOSRubricIDField = '', $contextDBTableNameField = '', $contextDBTableDateField = '')
{
    global $pdo, $page, $tawasul, $session;

    $roleCategory = $session->get('tawasulRoleIDCurrentCategory');
    $schoolYearFirstDay = $session->get('tawasulSchoolYearFirstDay');

    $output = false;
    $hasContexts = $contextDBTable != '' and $contextDBTableIDField != '' and $contextDBTableID != '' and $contextDBTableTawasulOSRubricIDField != '' and $contextDBTableNameField != '' and $contextDBTableDateField != '';


        $data = array('tawasulRubricID' => $tawasulRubricID);
        $sql = 'SELECT * FROM tawasulRubric WHERE tawasulRubricID=:tawasulRubricID';
        $result = $connection2->prepare($sql);
        $result->execute($data);

    if ($result->rowCount() != 1) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        $values = $result->fetch();

        //Get rows, columns and cells
        $sqlRows = "SELECT * FROM tawasulRubricRow WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";
        $resultRows = $pdo->executeQuery($data, $sqlRows);
        $rowCount = $resultRows->rowCount();

        $sqlColumns = "SELECT * FROM tawasulRubricColumn WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";
        $resultColumns = $pdo->executeQuery($data, $sqlColumns);
        $columnCount = $resultColumns->rowCount();

        $sqlCells = "SELECT * FROM tawasulRubricCell WHERE tawasulRubricID=:tawasulRubricID";
        $resultCells = $pdo->executeQuery($data, $sqlCells);
        $cellCount = $resultCells->rowcount();

        $sqlGradeScales = "SELECT tawasulScaleGrade.tawasulScaleGradeID, tawasulScaleGrade.*, tawasulScale.name FROM tawasulRubricColumn
            JOIN tawasulScaleGrade ON (tawasulRubricColumn.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID)
            JOIN tawasulScale ON (tawasulScale.tawasulScaleID=tawasulScaleGrade.tawasulScaleID)
            WHERE tawasulRubricColumn.tawasulRubricID=:tawasulRubricID";
        $resultGradeScales = $pdo->executeQuery($data, $sqlGradeScales);
        $gradeScales = ($resultGradeScales->rowCount() > 0)? $resultGradeScales->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

        $sqlOutcomes = "SELECT tawasulOutcome.tawasulOutcomeID, tawasulOutcome.* FROM tawasulRubricRow
            JOIN tawasulOutcome ON (tawasulRubricRow.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID)
            WHERE tawasulRubricRow.tawasulRubricID=:tawasulRubricID";
        $resultOutcomes = $pdo->executeQuery($data, $sqlOutcomes);
        $outcomes = ($resultOutcomes->rowCount() > 0)? $resultOutcomes->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

        // Check if outcomes are specified in unit
        $unitOutcomes = array();
        if ($hasContexts) {
            $dataUnitOutcomes = array();
            $sqlUnitOutcomes = "SHOW COLUMNS FROM `$contextDBTable` LIKE 'tawasulUnitID'";
            $resultUnitOutcomes = $pdo->executeQuery($dataUnitOutcomes, $sqlUnitOutcomes);

            if ($resultUnitOutcomes->rowCount() > 0) {
                $dataUnitOutcomes = array('tawasulRubricID' => $tawasulRubricID, 'contextDBTableID' => $contextDBTableID);
                $sqlUnitOutcomes = "SELECT tawasulUnitOutcome.tawasulOutcomeID, tawasulUnitOutcome.tawasulUnitOutcomeID FROM tawasulRubricRow
                    JOIN tawasulOutcome ON (tawasulRubricRow.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID)
                    JOIN tawasulUnitOutcome ON (tawasulUnitOutcome.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID)
                    JOIN `$contextDBTable` ON (`$contextDBTable`.tawasulUnitID=tawasulUnitOutcome.tawasulUnitID AND `$contextDBTableIDField`=:contextDBTableID)
                    WHERE tawasulRubricRow.tawasulRubricID=:tawasulRubricID";
                $resultUnitOutcomes = $pdo->executeQuery($dataUnitOutcomes, $sqlUnitOutcomes);
                $unitOutcomes = ($resultUnitOutcomes->rowCount() > 0)? $resultUnitOutcomes->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();
            }
        }

        // Load rubric data for this student
        $dataEntries = array('tawasulRubricID' => $tawasulRubricID, 'tawasulPersonID' => $tawasulPersonID, 'contextDBTable' => $contextDBTable, 'contextDBTableID' => $contextDBTableID);
        $sqlEntries = "SELECT tawasulRubricEntry.tawasulRubricCellID, tawasulRubricEntry.* FROM tawasulRubricCell
            LEFT JOIN tawasulRubricEntry ON (tawasulRubricEntry.tawasulRubricCellID=tawasulRubricCell.tawasulRubricCellID)
            WHERE tawasulRubricCell.tawasulRubricID=:tawasulRubricID
            AND tawasulRubricEntry.tawasulPersonID=:tawasulPersonID
            AND tawasulRubricEntry.contextDBTable=:contextDBTable
            AND tawasulRubricEntry.contextDBTableID=:contextDBTableID";
        $resultEntries = $pdo->executeQuery($dataEntries, $sqlEntries);
        $entries = ($resultEntries->rowCount() > 0)? $resultEntries->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();


        if ($rowCount <= 0 or $columnCount <= 0) {
            $output .= "<div class='error'>";
            $output .= __('The rubric cannot be drawn.');
            $output .= '</div>';
        } else {
            $rows = $resultRows->fetchAll();
            $columns = $resultColumns->fetchAll();

            $cells = array();
            while ($rowCells = $resultCells->fetch()) {
                $cells[$rowCells['tawasulRubricRowID']][$rowCells['tawasulRubricColumnID']] = $rowCells;
            }

            //Get other uses of this rubric in this context, and store for use in visualisation
            $contexts = array();
            $containsFutureData = false;
            if ($hasContexts) {
                $dataContext = array('tawasulPersonID' => $tawasulPersonID);
                $sqlContext = "SELECT tawasulRubricEntry.*, $contextDBTable.*, tawasulRubricEntry.*, tawasulRubricCell.*, tawasulCourse.nameShort AS course, tawasulCourseClass.nameshort AS class
                    FROM tawasulRubricEntry
                    JOIN $contextDBTable ON (tawasulRubricEntry.contextDBTableID=$contextDBTable.$contextDBTableIDField
                        AND tawasulRubricEntry.tawasulRubricID=$contextDBTable.$contextDBTableTawasulOSRubricIDField)
                    JOIN tawasulRubricCell ON (tawasulRubricEntry.tawasulRubricCellID=tawasulRubricCell.tawasulRubricCellID)
                    LEFT JOIN tawasulCourseClass ON ($contextDBTable.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                    LEFT JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                    WHERE contextDBTable='$contextDBTable'
                    AND tawasulRubricEntry.tawasulPersonID=:tawasulPersonID
                    AND NOT $contextDBTableDateField IS NULL
                    ORDER BY $contextDBTableDateField DESC";
                $resultContext = $pdo->executeQuery($dataContext,  $sqlContext);

                if ($resultContext->rowCount() > 0) {
                    $currentDate = date('Y-m-d');
                    while ($rowContext = $resultContext->fetch()) {
                        // Skip data before the current school year
                        if (!empty($schoolYearFirstDay) && $rowContext[$contextDBTableDateField] < $schoolYearFirstDay) {
                            continue;
                        }

                        // Skip data for any column that has not met its complete date yet
                        if (!empty($rowContext[$contextDBTableDateField]) && $currentDate < $rowContext[$contextDBTableDateField]) {
                            $containsFutureData = true;
                            if ($roleCategory != 'Staff') {
                                continue;
                            }
                        }

                        $context = $rowContext['course'].'.'.$rowContext['class'].' - '.$rowContext[$contextDBTableNameField].' ('.Format::date($rowContext[$contextDBTableDateField]).')';
                        $cells[$rowContext['tawasulRubricRowID']][$rowContext['tawasulRubricColumnID']]['context'][] = $context;

                        array_push($contexts, array('tawasulRubricEntry' => $rowContext['tawasulRubricEntry'], 'tawasulRubricID' => $rowContext['tawasulRubricID'], 'tawasulPersonID' => $rowContext['tawasulPersonID'], 'tawasulRubricCellID' => $rowContext['tawasulRubricCellID'], 'contextDBTable' => $rowContext['contextDBTable'], 'contextDBTableID' => $rowContext['contextDBTableID']));
                    }
                }
            }

            
            
            //Controls for viewing mode
            if ($tawasulPersonID != '') {
                $output .= "<div class='linkTop'>";
                $output .= "Viewing Mode: <select name='rubricTypeSelect' id='rubricTypeSelect' class='type' style='width: 152px; float: none'>";
                $output .= "<option name='rubricTypeSelect' value='Current'>".__('Current').'</option>';
                $output .= "<option name='rubricTypeSelect' value='Visualise'>".__('Visualise').'</option>';
                $output .= "<option name='rubricTypeSelect' value='Historical'>".__('Historical Data').'</option>';
                $output .= '</select>';
                $output .= '</div>';
            }

            if ($containsFutureData && $roleCategory == 'Staff') {
                $output .= Format::alert(__('As a staff member, your view of this rubric accounts for all current records, including those before their complete date. Parents and students will only see the rubric based on completed data.'), 'message historical visualised');
            }

            //Div to contain rubric for current and historicla views
            $output .= "<div id='rubric' style='overflow-x: auto'>";
            
                if ($mark == true) {
                    $output .= '<p>';
                    $output .= __('Click on any of the cells below to highlight them. Data is saved automatically after each click.');
                    $output .= '</p>';
                }

                $form = Form::createTable('viewRubric', $session->get('absoluteURL').'/index.php');
                $form->setClass('rubricTable w-full');

                $row = $form->addRow()->addClass();
                    $row->addContent()->addClass('');

                if ($hasContexts) {
                    $form->toggleVisibilityByClass('currentView')->onSelect('rubricTypeSelect')->when('Current');
                    $form->toggleVisibilityByClass('historical')->onSelect('rubricTypeSelect')->when('Historical');
                    $form->toggleVisibilityByClass('visualised')->onSelect('rubricTypeSelect')->when('Visualise');
                }

                    // Column Headers
                    for ($n = 0; $n < $columnCount; ++$n) {
                        $column = $row->addColumn()->addClass('rubricHeading column'.$columns[$n]['tawasulRubricColumnID']);

                        // Display grade scale, otherwise column title
                        if (!empty($gradeScales[$columns[$n]['tawasulScaleGradeID']])) {
                            $gradeScaleGrade = $gradeScales[$columns[$n]['tawasulScaleGradeID']];
                            $column->addContent('<b>'.$gradeScaleGrade['descriptor'].'</b>')
                                ->append(' ('.$gradeScaleGrade['value'].')')
                                ->append('<br/><span class="text-xs italic">'.__($gradeScaleGrade['name']).' '.__('Scale').'</span>');
                        } else {
                            $column->addContent($columns[$n]['title'])->wrap('<b>', '</b>');
                        }
                    }

                    // Rows
                    $count = 0;
                    for ($i = 0; $i < $rowCount; ++$i) {
                        $row = $form->addRow();
                        $col = $row->addColumn()->addClass('rubricHeading rubricRowHeading row'.$rows[$i]['tawasulRubricRowID']);

                        // Row Header
                        if (!empty($outcomes[$rows[$i]['tawasulOutcomeID']])) {
                            $outcome = $outcomes[$rows[$i]['tawasulOutcomeID']];
                            $content = $col->addContent('<b>'.__($outcome['name']).'</b>')
                                ->append(!empty($outcome['category'])? ('<i> - <br/>'.$outcome['category'].'</i>') : '')
                                ->append('<br/><span class="text-xs italic">'.$outcome['scope'].' '.__('Outcome').'</span>')
                                ->wrap('<span title="'.$outcome['description'].'">', '</span>');
                            // Highlight unit outcomes with a checkmark
                            if (isset($unitOutcomes[$rows[$i]['tawasulOutcomeID']])) {
                                $content->append(Format::tooltip(icon('solid', 'check', 'size-6 fill-current text-green-600'),  __('This outcome is one of the unit outcomes.')));
                            }
                            $rows[$i]['title'] = $outcomes[$rows[$i]['tawasulOutcomeID']]['name'];
                            $rows[$i]['title'];
                        } else {
                            $col->addContent($rows[$i]['title'])->wrap('<b>', '</b>');
                        }

                        // Cells
                        for ($n = 0; $n < $columnCount; ++$n) {
                            if (!isset($cells[$rows[$i]['tawasulRubricRowID']][$columns[$n]['tawasulRubricColumnID']])) {
                                $row->addColumn()->addClass('rubricCell');
                                continue;
                            }

                            $cell = $cells[$rows[$i]['tawasulRubricRowID']][$columns[$n]['tawasulRubricColumnID']];

                            $highlightClass = isset($entries[$cell['tawasulRubricCellID']])? 'rubricCellHighlight' : '';
                            $markableClass = ($mark == true)? 'markableCell' : '';

                            $col = $row->addColumn()->addClass('rubricCell '.$highlightClass);
                                $col->addContent($cell['contents'])
                                    ->addClass('currentView '.$markableClass)
                                    ->append('<span class="cellID" data-cell="'.$cell['tawasulRubricCellID'].'"></span>');

                            // Add historical contexts if applicable, shown/hidden by dropdown
                            $countHistorical = isset($cell['context']) ? count($cell['context']) : 0;
                            if ($hasContexts && $countHistorical > 0) {
                                $historicalContent = '';
                                for ($h = 0; $h < min(7, $countHistorical); ++$h) {
                                    $historicalContent .= ($h + 1) . ') ' . $cell['context'][$h] . '<br/>';
                                }

                                $col->addContent($historicalContent)
                                    ->addClass('historical')
                                    ->prepend('<b><u>' . __('Total Occurences:') . ' ' . $countHistorical . '</u></b><br/>')
                                    ->append(($countHistorical > 7)? '<b>'.__('Older occurrences not shown...').'</b>' : '')
                                    ->append('<span class="cellID" data-cell="' . $cell['tawasulRubricCellID'] . '"></span>');
                            }
                        }
                    }

                    if ($mark == true) {
                        $output .= "<script type='text/javascript'>";
                        $output .= '$(document).ready(function(){';
                        $output .= '$(".markableCell").parent().click(function(){';
                            $output .= "var mode = '';";
                            $output .= "var cellID = $(this).find('.cellID').data('cell');";
                            $output .= "if ($(this).hasClass('rubricCellHighlight') == false ) {";
                                $output .= "$(this).addClass('rubricCellHighlight');";
                                $output .= "mode = 'Add';";
                            $output .= '} else {';
                                $output .= "$(this).removeClass('rubricCellHighlight');";
                                $output .= "mode = 'Remove';";
                            $output .= '}';
                            $output .= 'var request=$.ajax({ url: "'.$session->get('absoluteURL').'/modules/TawasulRubrics/rubrics_data_saveAjax.php", type: "GET", data: {mode: mode, tawasulRubricID : "' . $tawasulRubricID.'", tawasulPersonID : "'.$tawasulPersonID.'", tawasulRubricCellID : cellID, contextDBTable : "'.$contextDBTable.'",contextDBTableID : "'.$contextDBTableID.'"}, dataType: "html"});';
                            $output .= '});';
                        $output .= '});';
                        $output .= '</script>';
                    }


                $output .= $form->getOutput();

            $output .= "</div>";

            //Div to contain visualisation
            $output .= "<div id='visualise' style='display: none'>";
                $output .= "<p>";
                    $output .= __("This view offers a visual representation of all rubric data for the current student, this year, in the current context:");
                $output .= "</p>";

                require_once __DIR__ . '/src/Visualise.php';
                $visualise = new Visualise($session->get('absoluteURL'), $page, $tawasulPersonID, $columns, $rows, $cells, $contexts);

                $output .= $visualise->renderVisualise();

            $output .= "</div>";

            //Function to show/hide rubric/visualisation
            $output .= "<script type='text/javascript'>
                 $(document).ready(function(){
                    $('#rubricTypeSelect').change(function () {
                        if ($(this).val() == 'Current' || $(this).val() == 'Historical') {
                            $('#rubric').slideDown('fast', $('#rubric').css('display','block'));
                            $('#visualise').css('display','none');
                        } else {
                            $('#visualise').slideDown('fast', $('#visualise').css('display','block'));
                            $('#rubric').css('display','none');
                        }
                    });
                });
            </script>";

            $output .= "<style>";
            for ($i = 0; $i < $rowCount; ++$i) {
                $color = $rows[$i]['backgroundColor'] ?? '#666666';
                $color = $color == '#ffffff' ? '#666666' : $color;
                $colorValue = hexdec(substr($color, 1, 2)) + hexdec(substr($color, 3, 2)) + hexdec(substr($color, 5, 2));
                $textColor = $colorValue > 580 ? '#5b5757' : '#ffffff';
                $output .= ".row".$rows[$i]['tawasulRubricRowID'].'{ background-color: '.$color.'; color: '.$textColor.'; } ';
            }
            for ($i = 0; $i < $columnCount; ++$i) {
                $color = $columns[$i]['backgroundColor'] ?? '#ffffff';
                $colorValue = hexdec(substr($color, 1, 2)) + hexdec(substr($color, 3, 2)) + hexdec(substr($color, 5, 2));
                $textColor = empty($colorValue) || $colorValue > 450 ? '#5b5757' : '#ffffff';
                $output .= ".column".$columns[$i]['tawasulRubricColumnID'].'{ background-color: '.$color.'; color: '.$textColor.'; } ';
            }
            $output .= "</style>";
        }

        // Append the Rubric stylesheet to the current page - for Markbook view of Rubric (only if it's not already included)
        $output .= '<script>';
        $output .= "if (!$('link[href*=\"./modules/TawasulRubrics/css/module.css\"]').length) {";
        $output .= "$('<link>').appendTo('head').attr({type: 'text/css', rel: 'stylesheet', href: './modules/TawasulRubrics/css/module.css'})";
        $output .= '}';
        $output .= '</script>';
    }

    return $output;
}
