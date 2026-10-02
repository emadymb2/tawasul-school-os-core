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

use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\UI\Timetable\TimetableContext;
use TawasulOS\UI\Timetable\Timetable;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
        $search = $_GET['search'] ?? '';
        $allUsers = $_GET['allUsers'] ?? '';
        $tawasulTTID = $_REQUEST['tawasulTTID'] ?? null;
        $format = $_GET['format'] ?? '';


        $canViewAllTimetables = $highestAction == 'View Timetable by Person' || $highestAction == 'View Timetable by Person_allYears';

        try {
            if ($highestAction == 'View Timetable by Person_myChildren') {
                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID1' => $session->get('tawasulPersonID'), 'tawasulPersonID2' => $tawasulPersonID);
                $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulStudentEnrolmentID, surname, preferredName, title, image_240, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup, 'Student' AS type, tawasulRoleIDPrimary
                    FROM tawasulPerson
                    JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                    JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                    JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                    JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID1)
                    JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulFamilyChild.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
                    WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                    AND tawasulPerson.tawasulPersonID=:tawasulPersonID2
                    AND tawasulPerson.status='Full' AND tawasulFamilyAdult.childDataAccess='Y'
                    GROUP BY tawasulPerson.tawasulPersonID";
            } else {
                if ($allUsers == 'on' && $canViewAllTimetables && $session->get('tawasulRoleIDCurrentCategory') == 'Staff') {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID);
                    $sql = "SELECT tawasulPerson.tawasulPersonID, surname, preferredName, title, image_240, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup, 'Student' AS type, tawasulRoleIDPrimary FROM tawasulPerson LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID) LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) LEFT JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                    WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID ORDER BY surname, preferredName";
                } else {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID1' => $tawasulPersonID, 'tawasulPersonID2' => $tawasulPersonID);
                    $sql = "(SELECT tawasulPerson.tawasulPersonID, tawasulStudentEnrolmentID, surname, preferredName, title, image_240, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup, 'Student' AS type, tawasulRoleIDPrimary FROM tawasulPerson, tawasulStudentEnrolment, tawasulYearGroup, tawasulFormGroup WHERE (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID) AND (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID) AND (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPerson.status='Full' AND tawasulPerson.tawasulPersonID=:tawasulPersonID1) UNION (SELECT tawasulPerson.tawasulPersonID, NULL AS tawasulStudentEnrolmentID, surname, preferredName, title, image_240, NULL AS yearGroup, NULL AS formGroup, 'Staff' AS type, tawasulRoleIDPrimary FROM tawasulPerson JOIN tawasulStaff ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID) JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary) WHERE tawasulStaff.type='Teaching' AND tawasulPerson.status='Full' AND tawasulPerson.tawasulPersonID=:tawasulPersonID2) ORDER BY surname, preferredName";
                }
            }
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
        }

        if ($result->rowCount() != 1) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else if ($highestAction == 'View Timetable by Person_my' && $tawasulPersonID != $session->get('tawasulPersonID')) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
            $row = $result->fetch();

            $page->breadcrumbs
                ->add(__('View Timetable by Person'), 'tt.php', ['allUsers' => $allUsers])
                ->add(Format::name($row['title'], $row['preferredName'], $row['surname'], $row['type']));

            $canEdit = isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php');

            /** @var RoleGateway */
            $roleGateway = $container->get(RoleGateway::class);

            $roleCategory = $roleGateway->getRoleCategory($row['tawasulRoleIDPrimary']);

            // DISPLAY PERSON DATA
            $table = DataTable::createDetails('personal');

            if ($format == 'print') {
                $table->addHeaderAction('print', __('Print'))
                    ->setURL('#')
                    ->onClick('javascript:window.print(); return false;');
            } else {
                if ($search != '') {
                    $params = [
                        "search" => $search,
                        "allUsers" => $allUsers,
                    ];
                    $table->addHeaderAction('back', __('Back to Search Results'))
                        ->setURL('/modules/TawasulTimetable/tt.php')
                        ->addParams($params)
                        ->setIcon('search')
                        ->displayLabel();
                }
                if ($canEdit && ($roleCategory == 'Student' or $roleCategory == 'Staff')) {
                    $params = [
                        "tawasulPersonID" => $tawasulPersonID,
                        "tawasulSchoolYearID" => $session->get('tawasulSchoolYearID'),
                        "type" => $roleCategory,
                        "allUsers" => $allUsers,
                    ];
                    $table->addHeaderAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php')
                        ->addParams($params)
                        ->setIcon('config')
                        ->displayLabel()
                        ->prepend((!empty($search)) ? ' | ' : '');
                    }

                    if ($_GET['tawasulPersonID'] == $session->get('tawasulPersonID')) {
                        $table->addHeaderAction('export', __('Export'))
                            ->modalWindow()
                            ->setURL('/modules/TawasulTimetable/tt_manage_subscription.php')
                            ->addParam('tawasulPersonID', $_GET['tawasulPersonID'])
                            ->setIcon('download')
                            ->displayLabel();
                    }
                }

            $table->addColumn('name', __('Name'))->format(Format::using('name', ['title', 'preferredName', 'surname', 'type', false, false]));
            $table->addColumn('yearGroup', __('Year Group'));
            $table->addColumn('formGroup', __('Form Group'));

            echo $table->render([$row]);

            $ttDate = null;
            if (!empty($_REQUEST['ttDate'])) {
                $ttDate = Format::dateConvert($_REQUEST['ttDate']);
            }

            // Create timetable context
            $context = $container->get(TimetableContext::class)
                ->set('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                ->set('tawasulPersonID', $tawasulPersonID)
                ->set('tawasulTTID', $tawasulTTID)
                ->set('format', $format);

            // Build and render timetable
            echo $container->get(Timetable::class)
                ->setDate($ttDate)
                ->setContext($context)
                ->addCoreLayers($container)
                ->getOutput(); 

            //Set sidebar
            $session->set('sidebarExtra', Format::userPhoto($row['image_240'], 240));
        }
    }
}
