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

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/report_activityChoices_byFormGroup.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Activity Choices by Form Group'));

    echo '<h2>';
    echo __('Choose Form Group');
    echo '</h2>';

    $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
    $status = $_GET['status'] ?? '';

    $form = Form::create('action', $session->get('absoluteURL').'/index.php','get');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');


    $form->addHiddenValue('q', "/modules/".$session->get('module')."/report_activityChoices_byFormGroup.php");

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $session->get('tawasulSchoolYearID'))->selected($tawasulFormGroupID)->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if ($tawasulFormGroupID != '') {
        $output = '';
        echo '<h2>';
        echo __('Report Data');
        echo '</h2>';

            $data = array('tawasulFormGroupID' => $tawasulFormGroupID, 'today' => date('Y-m-d'));
            $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, name FROM tawasulPerson JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) AND tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID ORDER BY surname, preferredName";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() < 1) {
            echo $page->getBlankSlate();
        } else {
            echo "<table cellspacing='0' class='w-full colorOddEven'>";
            echo "<tr class='head'>";
            echo '<th>';
            echo __('Student');
            echo '</th>';
            echo '<th>';
            echo __('Activities');
            echo '</th>';
            echo '</tr>';

            while ($row = $result->fetch()) {
                echo '<tr>';
                echo '<td>';
                echo '<b><a href="index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$row['tawasulPersonID'].'&subpage=Activities">'.Format::name('', $row['preferredName'], $row['surname'], 'Student', true).'</a>';
                echo '</td>';

                echo '<td>';


                    $dataActivities = array('tawasulPersonID' => $row['tawasulPersonID'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                    $sqlActivities = "SELECT tawasulActivity.*, tawasulActivityStudent.status, GROUP_CONCAT(CONCAT(tawasulDaysOfWeek.nameShort, ' ', TIME_FORMAT(tawasulActivitySlot.timeStart, '%H:%i'), ' - ', (CASE WHEN tawasulActivitySlot.tawasulSpaceID IS NOT NULL THEN tawasulSpace.name ELSE tawasulActivitySlot.locationExternal END)) SEPARATOR '<br/>') as days
                        FROM tawasulActivity
                        JOIN tawasulActivityStudent ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID)
                        JOIN tawasulActivitySlot ON (tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID)
                        JOIN tawasulDaysOfWeek ON (tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulActivitySlot.tawasulDaysOfWeekID)
                        LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulActivitySlot.tawasulSpaceID)
                        WHERE tawasulActivityStudent.tawasulPersonID=:tawasulPersonID
                        AND tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID
                        GROUP BY tawasulActivity.tawasulActivityID
                        ORDER BY tawasulActivity.name";
                    $resultActivities = $connection2->prepare($sqlActivities);
                    $resultActivities->execute($dataActivities);

                if ($resultActivities->rowCount() > 0) {
                    echo '<table cellspacing="0" class="mini w-full">';
                    while ($activity = $resultActivities->fetch()) {
                        $timespan = getActivityTimespan($connection2, $activity['tawasulActivityID'], $activity['tawasulSchoolYearTermIDList']);
                        $timeStatus = '';
                        if (!empty($timespan)) {
                            $timeStatus = (time() < $timespan['start'])? __('Upcoming') : (time() > $timespan['end']? __('Ended') : __('Current'));
                        }
                        echo '<tr>';
                        echo '<td>';
                        echo '<a class="thickbox" title="'.__('View Details').'" href="'.$session->get('absoluteURL').'/fullscreen.php?q=/modules/'.$session->get('module').'/activities_view_full.php&tawasulActivityID='.$activity['tawasulActivityID'].'&width=1000&height=550" style="text-decoration: none; color:inherit;">'.$activity['name'].'</a>';
                        echo '</td>';
                        echo '<td width="15%">';
                        if (!empty($timeStatus)) {
                            echo '<span class="italic" title="'.Format::dateRangeReadable('@'.$timespan['start'], '@'.$timespan['end']).'">';
                            echo (time() < $timespan['start'])? __('Upcoming') : (time() > $timespan['end']? __('Ended') : __('Current'));
                            echo '</span>';
                        } else {
                            echo $activity['status'];
                        }
                        echo '</td>';
                        echo '<td width="30%">';
                        echo (!empty($timespan) && $timeStatus != __('Ended') && $activity['status'] == 'Accepted')? $activity['days'] : '';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }

                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }
}
?>
