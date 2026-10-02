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
use TawasulOS\Domain\Timetable\CourseClassGateway;
use TawasulOS\Domain\FormalAssessment\InternalAssessmentColumnGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/internalAssessment_manage.php') == false) {
    //Access denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    //Get class variable
    $tawasulCourseClassID = null;
    if (isset($_GET['tawasulCourseClassID'])) {
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    } else {

            $result = $container->get(CourseClassGateway::class)->selectClassesByPerson($session->get('tawasulPersonID'));

        if ($result->rowCount() > 0) {
            $row = $result->fetch();
            $tawasulCourseClassID = $row['tawasulCourseClassID'];
        }
    }
    if ($tawasulCourseClassID == '') {
        echo '<h1>';
        echo 'Manage Internal Assessment';
        echo '</h1>';
        echo "<div class='warning'>";
        echo __('Use the class listing on the right to choose an Internal Assessment to edit.');
        echo '</div>';
    }
    //Check existence of and access to this class.
    else {
            $result = $container->get(CourseClassGateway::class)->getCourseClass($tawasulCourseClassID);

        if (empty($result)) {
            echo '<h1>';
            echo __('Manage Internal Assessment');
            echo '</h1>';
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
            $row = $result;
            $page->breadcrumbs->add(__('Manage').' '.$row['course'].'.'.$row['class'].' '.__('Internal Assessments'));

            //Add multiple columns
            $params = [
                "tawasulCourseClassID" => $tawasulCourseClassID
            ];
            $page->navigator->addHeaderAction('addMultiple', __('Add Multiple Columns'))
                ->setURL('/modules/TawasulFormalAssessment/internalAssessment_manage_add.php')
                ->addParams($params)
                ->setIcon('page_new_multi')
                ->displayLabel();

            //Get teacher list
            $teaching = false;

            $result = $container->get(CourseClassGateway::class)->selectTeacherListByClass($tawasulCourseClassID);

            if ($result->rowCount() > 0) {
                echo '<h3>';
                echo __('Teachers');
                echo '</h3>';
                echo '<ul>';
                while ($row = $result->fetch()) {
                    if ($row['reportable'] != 'Y') continue;

                    echo '<li>'.Format::name($row['title'], $row['preferredName'], $row['surname'], 'Staff').'</li>';
                    if ($row['tawasulPersonID'] == $session->get('tawasulPersonID')) {
                        $teaching = true;
                    }
                }
                echo '</ul>';
            }

            //Print mark
            echo '<h3>';
            echo __('Internal Assessment Columns');
            echo '</h3>';
            
            $result = $container->get(InternalAssessmentColumnGateway::class)->selectColumnsByClass($tawasulCourseClassID);

            if ($result->rowCount() < 1) {
                echo $page->getBlankSlate();
            } else {
                echo "<table cellspacing='0' style='width: 100%'>";
                echo "<tr class='head'>";
                echo '<th>';
                echo __('Name').'<br/>';
                echo "<span style='font-size: 85%; font-style: italic'>".__('Type').'</span>';
                echo '</th>';
                echo '<th>';
                echo __('Date<br/>Complete');
                echo '</th>';
                echo '<th>';
                echo __('Actions');
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

                    //COLOR ROW BY STATUS!
                    echo "<tr class=$rowNum>";
                    echo '<td>';
                    echo '<b>'.$row['name'].'</b><br/>';
                    echo "<span style='font-size: 85%; font-style: italic'>".$row['type'].'</span>';
                    echo '</td>';
                    echo '<td>';
                    if ($row['complete'] == 'Y') {
                        echo Format::date($row['completeDate']);
                    }
                    echo '</td>';
                    echo '<td>';
                    echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/internalAssessment_manage_edit.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulInternalAssessmentColumnID=".$row['tawasulInternalAssessmentColumnID']."'><img title='".__('Edit')."' src='./themes/".$session->get('tawasulThemeName')."/img/config.png'/></a> ";
                    echo "<a class='thickbox' href='".$session->get('absoluteURL').'/fullscreen.php?q=/modules/'.$session->get('module')."/internalAssessment_manage_delete.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulInternalAssessmentColumnID=".$row['tawasulInternalAssessmentColumnID']."&width=650&height=135'><img title='".__('Delete')."' src='./themes/".$session->get('tawasulThemeName')."/img/garbage.png'/></a> ";
                    echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/internalAssessment_write_data.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulInternalAssessmentColumnID=".$row['tawasulInternalAssessmentColumnID']."'><img title='".__('Enter Data')."' src='./themes/".$session->get('tawasulThemeName')."/img/markbook.png'/></a> ";
                    echo '</td>';
                    echo '</tr>';

                    ++$count;
                }
                echo '</table>';
            }
        }
    }

    //Print sidebar
    $session->set('sidebarExtra',sidebarExtra($guid, $connection2, $tawasulCourseClassID));
}
