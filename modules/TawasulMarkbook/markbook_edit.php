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
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\System\SettingGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit.php') == false) {
    //Acess denied
    $page->addError(__('Your request failed because you do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Get class variable
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        if ($tawasulCourseClassID == '') {
            $tawasulCourseClassID = $session->get('markbookClass') ?? '';
        }

        if ($tawasulCourseClassID == '') {
            $row = getAnyTaughtClass( $pdo, $session->get('tawasulPersonID'), $session->get('tawasulSchoolYearID') );
            $tawasulCourseClassID = $row['tawasulCourseClassID'] ?? '';
        }

        if ($tawasulCourseClassID == '') {
            echo '<h1>';
            echo __('Edit Markbook');
            echo '</h1>';
            echo "<div class='warning'>";
            echo __('The selected record does not exist, or you do not have access to it.');
            echo '</div>';

            //Get class chooser
            echo classChooser($guid, $pdo, $tawasulCourseClassID);
            return;
        }
        //Check existence of and access to this class.
        else {

            $highestAction2 = getHighestGroupedAction($guid, '/modules/TawasulMarkbook/markbook_edit.php', $connection2);

            try {
                if ($highestAction == 'Edit Markbook_everything') {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } else {
                    $data = array('tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                echo '<h1>';
                echo __('Edit Markbook');
                echo '</h1>';
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $row = $result->fetch();

                $page->breadcrumbs->add(__('Edit {courseClass} Markbook', [
                    'courseClass' => Format::courseClassName($row['course'], $row['class']),
                ]));

                //Add multiple columns
                if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit.php')) {
                    if ($highestAction2 == 'Edit Markbook_multipleClassesAcrossSchool' or $highestAction2 == 'Edit Markbook_multipleClassesInDepartment' or $highestAction2 == 'Edit Markbook_everything') {
                        //Check highest role in any department
                        $isCoordinator = isDepartmentCoordinator( $pdo, $session->get('tawasulPersonID') );
                        if ($isCoordinator == true or $highestAction2 == 'Edit Markbook_multipleClassesAcrossSchool' or $highestAction2 == 'Edit Markbook_everything') {
                            $params = [
                                "tawasulCourseClassID" => $tawasulCourseClassID
                            ];
                            $page->navigator->addHeaderAction('addMulti', __('Add Multiple Columns'))
                                ->setURL('/modules/TawasulMarkbook/markbook_edit_addMulti.php')
                                ->addParams($params)
                                ->setIcon('page_new_multi')
                                ->displayLabel();
                        }
                    }
                }

                //Get teacher list
                $teacherList = getTeacherList( $pdo, $tawasulCourseClassID );
                $teaching = (isset($teacherList[ $session->get('tawasulPersonID') ]) );

                $canEditThisClass = ($teaching == true || $isCoordinator == true or $highestAction2 == 'Edit Markbook_multipleClassesAcrossSchool' or $highestAction2 == 'Edit Markbook_everything');

                if (!empty($teacherList)) {
                    echo '<h3>';
                    echo __('Teachers');
                    echo '</h3>';
                    echo '<ul>';
                    foreach ($teacherList as $teacher) {
                        echo '<li>'. $teacher . '</li>';
                    }
                    echo '</ul>';
                }

                //Print mark
                echo '<h3>';
                echo __('Markbook Columns');
                echo '</h3>';

                $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                $sql = 'SELECT * FROM tawasulMarkbookColumn WHERE tawasulCourseClassID=:tawasulCourseClassID ORDER BY completeDate DESC, name';
                $result = $connection2->prepare($sql);
                $result->execute($data);

                if ($canEditThisClass) {
                    echo "<div class='linkTop'>";
                    echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/markbook_edit_add.php&tawasulCourseClassID=$tawasulCourseClassID'>".__('Add')."<img style='margin-left: 5px' title='".__('Add')."' src='./themes/".$session->get('tawasulThemeName')."/img/page_new.png'/></a>";

                    if ($container->get(SettingGateway::class)->getSettingByScope('Markbook', 'enableColumnWeighting') == 'Y') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/weighting_manage.php') == true) {
                            echo " | <a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/weighting_manage.php&tawasulCourseClassID=$tawasulCourseClassID'>".__('Manage Weightings')."<img title='".__('Manage Weightings')."' src='./themes/".$session->get('tawasulThemeName')."/img/run.png'/></a>";
                        }
                    }

                    echo '</div>';
                }

                if ($result->rowCount() < 1) {
                    echo $page->getBlankSlate();
                } else {
                    echo "<table cellspacing='0' style='width: 100%'>";
                    echo "<tr class='head'>";
                    echo '<th>';
                    echo __('Name/Unit');
                    echo '</th>';
                    echo '<th>';
                    echo __('Type');
                    echo '</th>';
                    echo '<th>';
                    echo __('Date<br/>Added');
                    echo '</th>';
                    echo '<th>';
                    echo __('Date<br/>Complete');
                    echo '</th>';
                    echo '<th style="width:80px">';
                    echo __('Viewable <br/>to Students');
                    echo '</th>';
                    echo '<th style="width:80px">';
                    echo __('Viewable <br/>to Parents');
                    echo '</th>';
                    echo '<th style="width:125px">';
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
                        $unit = getUnit($connection2, $row['tawasulUnitID'], $row['tawasulCourseClassID']);
                        if (isset($unit[0])) {
                            echo $unit[0];
                        }
                        if (isset($unit[1])) {
                            echo '<br/><i>'.$unit[1].' '.__('Unit').'</i>';
                        }
                        echo '</td>';
                        echo '<td>';
                        echo $row['type'];
                        echo '</td>';
                        echo '<td>';
                        if (!empty($row['date']) && $row['date'] != '0000-00-00') {
                            echo Format::date($row['date']);
                        }
                        echo '</td>';
                        echo '<td>';
                        if ($row['complete'] == 'Y') {
                            echo Format::date($row['completeDate']);
                        }
                        echo '</td>';
                        echo '<td>';
                        echo Format::yesNo($row['viewableStudents']);
                        echo '</td>';
                        echo '<td>';
                        echo Format::yesNo($row['viewableParents']);
                        echo '</td>';
                        echo '<td>';
                        echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/markbook_edit_edit.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulMarkbookColumnID=".$row['tawasulMarkbookColumnID']."'><img title='".__('Edit')."' src='./themes/".$session->get('tawasulThemeName')."/img/config.png'/></a> ";
                        echo "<a class='thickbox' href='".$session->get('absoluteURL').'/fullscreen.php?q=/modules/'.$session->get('module')."/markbook_edit_delete.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulMarkbookColumnID=".$row['tawasulMarkbookColumnID']."&width=650&height=135'><img title='".__('Delete')."' src='./themes/".$session->get('tawasulThemeName')."/img/garbage.png'/></a> ";
                        echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/markbook_edit_data.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulMarkbookColumnID=".$row['tawasulMarkbookColumnID']."'><img title='".__('Enter Data')."' src='./themes/".$session->get('tawasulThemeName')."/img/markbook.png'/></a> ";
                        echo "<a href='".$session->get('absoluteURL').'/modules/TawasulMarkbook/markbook_viewExport.php?tawasulMarkbookColumnID='.$row['tawasulMarkbookColumnID']."&tawasulCourseClassID=$tawasulCourseClassID&return=markbook_edit.php'><img title='".__('Export to Excel')."' src='./themes/".$session->get('tawasulThemeName')."/img/download.png'/></a>";
                        echo '</td>';
                        echo '</tr>';

                        ++$count;
                    }
                    echo '</table>';
                }

                echo '<br/>&nbsp;<br/>';

                if ($canEditThisClass) {
                    echo '<h3>';
                    echo __('Copy Markbook Columns');
                    echo '</h1>';

                    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php?q=/modules/TawasulMarkbook/markbook_edit_copy.php&tawasulCourseClassID='.$tawasulCourseClassID);
                    $form->setFactory(DatabaseFormFactory::create($pdo));
                    $form->setClass('noIntBorder w-full');

                    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/applicationForm_manage.php');

                    $col = $form->addRow()->addColumn()->addClass('inline right');
                        $col->addContent(__('Copy from').' '.__('Class').': &nbsp;');
                        $col->addSelectClass('tawasulMarkbookCopyClassID', $session->get('tawasulSchoolYearID'))->setClass('mediumWidth');
                        $col->addSubmit(__('Go'));

                    echo $form->getOutput();
                }
            }
        }
    }

    // Print the sidebar
    $session->set('sidebarExtra', sidebarExtra($guid, $pdo, $session->get('tawasulPersonID'), $tawasulCourseClassID, 'markbook_edit.php'));
}
