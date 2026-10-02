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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\UI\Timetable\Timetable;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\System\HookGateway;
use TawasulOS\Domain\User\FamilyGateway;
use TawasulOS\Domain\School\HouseGateway;
use TawasulOS\UI\Timetable\TimetableContext;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Staff\StaffFacilityGateway;
use Tos\Module\TawasulStaff\StaffAttendanceStatus;
use TawasulOS\Domain\User\PersonalDocumentGateway;

// Module includes for User Admin (for custom fields)
include './modules/TawasulUserAdmin/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_view_details.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    $highestActionManage = getHighestGroupedAction($guid, "/modules/TawasulStaff/staff_manage.php", $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
        $tawasulPersonID = str_pad($tawasulPersonID, 10, 0, STR_PAD_LEFT);

        if ($tawasulPersonID == '' ) {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            $hookGateway = $container->get(HookGateway::class);
            $search = $_GET['search'] ?? '';
            $allStaff = $_GET['allStaff'] ?? '';
            $hook = $_GET['hook'] ?? '';

            if ($highestAction == 'Staff Directory_brief') {
                // Proceed!
                $data = ['tawasulPersonID' => $tawasulPersonID];
                $sql = "SELECT title, surname, preferredName, type, tawasulStaff.jobTitle, email, website, countryOfOrigin, qualifications, biography, image_240 FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulPerson.tawasulPersonID=:tawasulPersonID";
                $result = $connection2->prepare($sql);
                $result->execute($data);

                if ($result->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    $row = $result->fetch();

                    $page->breadcrumbs
                        ->add(__('Staff Directory'), 'staff_view.php')
                        ->add(Format::name('', $row['preferredName'], $row['surname'], 'Student'));

                    if ($search != '') {
                        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStaff', 'staff_view.php')->withQueryParam('search', $search));
                    }

                    // Overview
                    $table = DataTable::createDetails('overview');

                    $col = $table->addColumn('Basic Information');

                    $col->addColumn('preferredName', __('Name'))
                        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Parent']));
                    $col->addColumn('type', __('Staff Type'));
                    $col->addColumn('jobTitle', __('Job Title'));
                    $col->addColumn('email', __('Email'))->format(Format::using('link', 'email'));
                    $col->addColumn('website', __('Website'))->format(Format::using('link', 'website'));

                    $col = $table->addColumn('Biography', __('Biography'));

                    $col->addColumn('countryOfOrigin', __('Country Of Origin'));
                    $col->addColumn('qualifications', __('Qualifications'))->addClass('col-span-2');
                    $col->addColumn('biography', __('Biography'))->addClass('col-span-3');

                    echo $table->render([$row]);

                    $page->addSidebarExtra(Format::userPhoto($row['image_240'], 240));
                }
            } else {
                try {
                    $data = array('tawasulPersonID' => $tawasulPersonID);
                    if ($allStaff != 'on') {
                        $sql = "SELECT tawasulPerson.*, tawasulStaff.initials, tawasulStaff.type, tawasulStaff.jobTitle, countryOfOrigin, qualifications, biography, tawasulStaff.tawasulStaffID, firstAidQualified, firstAidQualification, firstAidExpiry, tawasulStaff.fields as fieldsStaff FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulPerson.tawasulPersonID=:tawasulPersonID";
                    } else {
                        $sql = 'SELECT tawasulPerson.*, tawasulStaff.initials, tawasulStaff.type, tawasulStaff.jobTitle, countryOfOrigin, qualifications, biography, tawasulStaff.tawasulStaffID, firstAidQualified, firstAidQualification, firstAidExpiry, tawasulStaff.fields as fieldsStaff FROM tawasulPerson JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID';
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                }

                if ($result->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    $row = $result->fetch();

                    $customFieldHandler = $container->get(CustomFieldHandler::class);
                    $hooks = $hookGateway->selectHooksByType('Staff Profile')->fetchGroupedUnique();
                    $hooks = array_map(function ($item) {
                        $item['options'] = unserialize($item['options']);
                        return $item;
                    }, $hooks);

                    $page->breadcrumbs
                        ->add(__('Staff Directory'), 'staff_view.php', ['search' => $search, 'allStaff' => $allStaff])
                        ->add(Format::name('', $row['preferredName'], $row['surname'], 'Student'));

                    $subpage = null;
                    if (isset($_GET['subpage'])) {
                        $subpage = $_GET['subpage'] ?? '';
                    }
                    if ($subpage == '' and $hook == '') {
                        $subpage = 'Overview';
                    }

                    if ($search != '') {
                        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStaff', 'staff_view.php')->withQueryParam('search', $search));
                    }

                    echo '<h2>';
                    if ($subpage != '') {
                        echo __($subpage);
                    } else {
                        echo $hook;
                    }
                    echo '</h2>';

                    if ($subpage == 'Overview') {

                        // Display a message if the staff member is absent today.
                        $currentStaffAttendanceStatus = $container->get(StaffAttendanceStatus::class)->getCurrentAttendanceStatus($session->get('tawasulSchoolYearID'), $tawasulPersonID, $row['title'], $row['preferredName'], $row['surname']);

                        echo $currentStaffAttendanceStatus;
                        
                        // Overview
                        $table = DataTable::createDetails('overview');

                        if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage.php')) {
                            $table->addHeaderAction('edit', __('Edit User'))
                                ->setURL('/modules/TawasulUserAdmin/user_manage_edit.php')
                                ->addParam('tawasulPersonID', $tawasulPersonID)
                                ->displayLabel();
                        }

                        if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_manage.php')) {
                            $table->addHeaderAction('edit2', __('Edit Staff'))
                                ->setIcon('config')
                                ->setURL('/modules/TawasulStaff/staff_manage_edit.php')
                                ->addParam('tawasulStaffID', $row['tawasulStaffID'])
                                ->displayLabel();
                        }

                        $col = $table->addColumn('Basic Information');

                        $col->addColumn('preferredName', __('Name'))
                            ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Parent']));
                        $col->addColumn('type', __('Staff Type'))->translatable();
                        $col->addColumn('jobTitle', __('Job Title'));
                        $col->addColumn('username', __('Username'));
                        $col->addColumn('email', __('Email'))->format(Format::using('link', 'email'));
                        if (!empty($row['website'])) {
                            $col->addColumn('website', __('Website'))->format(Format::using('link', 'website'));
                        }

                        if (!empty($row['tawasulHouseID'])) {
                            $house = $container->get(HouseGateway::class)->getByID($row['tawasulHouseID'], ['name']);
                            $row['houseName'] = $house['name'] ?? '';
                            $col->addColumn('houseName', __('House'));
                        }

                        $col = $table->addColumn('Biography', __('Biography'));

                        $col->addColumn('countryOfOrigin', __('Country Of Origin'));
                        $col->addColumn('qualifications', __('Qualifications'))->addClass('col-span-2');
                        $col->addColumn('biography', __('Biography'))->addClass('col-span-3');

                        // Custom Fields
                        $customFieldHandler->addCustomFieldsToTable($table, 'Staff', ['heading' => 'Other Information', 'withHeading' => ['Basic Information', 'Biography']], $row['fieldsStaff']);

                        // Append the first aid details
                        $headingCol = $table->getColumn('Basic Information');
                        $headingCol->addColumn('firstAidQualified', __('First Aid Qualified'))
                            ->addClass('grid')
                            ->format(Format::using('yesNo', 'firstAidQualified'));

                        echo $table->render([$row]);

                        // Show timetable
                        echo "<a name='timetable'></a>";
                        echo '<h4>';
                        echo __('Timetable');
                        echo '</h4>';
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_view.php') == true) {
                            $table = DataTable::createDetails('timetable');

                            if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php') == true) {
                                $table->addHeaderAction('edit', __('Edit'))
                                    ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->addParam('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                    ->addParam('type', 'Staff')
                                    ->addParam('allUsers', '')
                                    ->displayLabel();
                            }

                            if ($tawasulPersonID == $session->get('tawasulPersonID')) {
                                $table->addHeaderAction('export', __('Export'))
                                    ->modalWindow()
                                    ->setURL('/modules/TawasulTimetable/tt_manage_subscription.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->setIcon('download')
                                    ->displayLabel();
                            }

                            echo $table->render(['' => '']);


                            $ttDate = !empty($_REQUEST['ttDate']) ? Format::dateConvert($_REQUEST['ttDate']) : null;
                            $tawasulTTID = $_REQUEST['tawasulTTID'] ?? '';
                            
                            // Create timetable context
                            $context = $container->get(TimetableContext::class)
                                ->set('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                ->set('tawasulPersonID', $tawasulPersonID)
                                ->set('tawasulTTID', $tawasulTTID);

                            // Build and render timetable
                            echo $container->get(Timetable::class)
                                ->setDate($ttDate)
                                ->setContext($context)
                                ->addCoreLayers($container)
                                ->getOutput(); 
                        }
                    } elseif ($subpage == 'Personal') {
                        $table = DataTable::createDetails('personal');

                        if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage.php')) {
                            $table->addHeaderAction('edit', __('Edit User'))
                                ->setURL('/modules/TawasulUserAdmin/user_manage_edit.php')
                                ->addParam('tawasulPersonID', $tawasulPersonID)
                                ->displayLabel();
                        }

                        if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/staff_manage.php')) {
                            $table->addHeaderAction('edit2', __('Edit Staff'))
                                ->setIcon('config')
                                ->setURL('/modules/TawasulStaff/staff_manage_edit.php')
                                ->addParam('tawasulStaffID', $row['tawasulStaffID'])
                                ->displayLabel();
                        }

                        $col = $table->addColumn('Basic Information');

                        $col->addColumn('preferredName', __('Name'))
                            ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Parent']));
                        $col->addColumn('type', __('Staff Type'))->translatable();
                        $col->addColumn('jobTitle', __('Job Title'));
                        $col->addColumn('initials', __('Initials'));
                        $col->addColumn('gender', __('Gender'))
                            ->format(Format::using('genderName', 'gender'));
                        $col->addColumn('initials', __('Initials'));

                        $col = $table->addColumn('Contacts', __('Contacts'));

                        for ($i = 1; $i < 5; ++$i) {
                            if (empty($row['phone' . $i])) continue;
                            if ($row['phone' . $i] != '') {
                                $col->addColumn('phone' . $i, __('Phone') . " $i")
                                    ->format(Format::using('phone', ['phone' . $i, 'phone'.$i.'CountryCode', 'phone'.$i.'Type']));
                            }
                        }

                        $col->addColumn('email', __('Email'))
                            ->format(Format::using('link', $row['email']));

                        $col->addColumn('emailAlternate', __('Alternate Email'))
                            ->format(function($row) {
                                if ($row['emailAlternate'] != '') {
                                    return Format::link($row['emailAlternate']);
                                }
                                return '';
                            });

                        $col->addColumn('website', __('Website'))
                            ->format(Format::using('link', ['website', 'website']));

                        $col = $table->addColumn('First Aid', __('First Aid'));

                        $col->addColumn('firstAidQualified', __('First Aid Qualified'))
                            ->addClass('grid')
                            ->format(Format::using('yesNo', 'firstAidQualified'));
                        if ($row["firstAidQualified"] == "Y") {
                            $col->addColumn('firstAidQualification', __('First Aid Qualification'))
                                ->addClass('grid')
                                ->format(Format::using('truncate', 'firstAidQualification'));
                            $col->addColumn('firstAidExpiry', __('Expiry Date'))
                                ->format(function($row) {
                                    $output = Format::date($row['firstAidExpiry']);
                                    if ($row['firstAidExpiry'] <= date('Y-m-d')) {
                                        $output .= Format::tag(__('Expired'), 'warning ml-2');
                                    }
                                    else if ($row['firstAidExpiry'] > date('Y-m-d')) {
                                        $output .= Format::tag(__('Current'), 'success ml-2');
                                    }
                                    return $output;
                                });
                        }

                        $col = $table->addColumn('Miscellaneous', __('Miscellaneous'));

                        $col->addColumn('transport', __('Transport'));
                        $col->addColumn('vehicleRegistration', __('Vehicle Registration'));
                        $col->addColumn('lockerNumber', __('Locker Number'));

                        // CUSTOM FIELDS
                        $customFieldHandler->addCustomFieldsToTable($table, 'Staff', ['withoutHeading' => ['Biography']], $row['fieldsStaff']);
                        $customFieldHandler->addCustomFieldsToTable($table, 'Person', ['staff' => 1], $row['fields']);

                        echo $table->render([$row]);

                        // PERSONAL DOCUMENTS
                        if ($highestActionManage == 'Manage Staff_confidential') {
                            $params = ['staff' => true, 'notEmpty' => true];
                            $documents = $container->get(PersonalDocumentGateway::class)->selectPersonalDocuments('tawasulPerson', $tawasulPersonID, $params)->fetchAll();

                            echo $page->fetchFromTemplate('ui/personalDocuments.twig.html', ['documents' => $documents]);
                        }

                    } elseif ($subpage == 'Family') {

                        $familyGateway = $container->get(FamilyGateway::class);

                        // CRITERIA
                        $criteria = $familyGateway->newQueryCriteria()
                            ->sortBy(['tawasulFamily.name'])
                            ->fromPOST('family');

                        $families = $familyGateway->queryFamiliesByAdult($criteria, $tawasulPersonID);
                        $familyIDs = $families->getColumn('tawasulFamilyID');

                        // Join a set of data per family
                        $childrenData = $familyGateway->selectChildrenByFamily($familyIDs, true)->fetchGrouped();
                        $families->joinColumn('tawasulFamilyID', 'children', $childrenData);
                        $adultData = $familyGateway->selectAdultsByFamily($familyIDs, true)->fetchGrouped();
                        $families->joinColumn('tawasulFamilyID', 'adults', $adultData);

                        $tawasulFamilyID = current($familyIDs);
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage.php') == true && !empty($tawasulFamilyID)) {
                            $form = Form::createBlank('buttons');
                            $form->addHeaderAction('edit', __('Edit Family'))
                                ->setURL('/modules/TawasulUserAdmin/family_manage_edit.php')
                                ->addParam('tawasulFamilyID', $tawasulFamilyID)
                                ->displayLabel();
                            echo $form->getOutput();
                        }

                        echo $page->fetchFromTemplate('profile/family.twig.html', [
                            'families' => $families,
                            'fullDetails' => $tawasulPersonID == $session->get('tawasulPersonID'),
                        ]);
                    } elseif ($subpage == 'Facilities') {
                        $staffFacilityGateway = $container->get(StaffFacilityGateway::class);
                        $criteria = $staffFacilityGateway->newQueryCriteria();
                        $facilities = $staffFacilityGateway->queryFacilitiesByPerson($criteria, $session->get('tawasulSchoolYearID'), $tawasulPersonID);

                        $table = DataTable::create('facilities');

                        $table->addColumn('name', __('Name'));
                        $table->addColumn('phoneInternal', __('Extension'));
                        $table->addColumn('usageType', __("Usage"))
                            ->format(function($row) {
                                return __($row['usageType']);
                            });

                        echo $table->render($facilities);
                    } elseif ($subpage == 'Emergency Contacts') {
                        if ($highestActionManage != 'Manage Staff_confidential') {
                            $page->addError(__('You do not have access to this action.'));
                        }
                        else {
                            if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage.php') == true) {
                                $form = Form::createBlank('buttons');
                                $form->addHeaderAction('edit', __('Edit User'))
                                    ->setURL('/modules/TawasulUserAdmin/user_manage_edit.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->displayLabel();
                                echo $form->getOutput();
                            }

                            echo '<p>';
                            echo __('In an emergency, please try and contact the adult family members listed below first. If these cannot be reached, then try the emergency contacts below.');
                            echo '</p>';

                            echo '<h4>';
                            echo __('Adult Family Members');
                            echo '</h4>';

                            $dataFamily = array('tawasulPersonID' => $tawasulPersonID);
                            $sqlFamily = 'SELECT * FROM tawasulFamily JOIN tawasulFamilyChild ON (tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID';
                            $resultFamily = $connection2->prepare($sqlFamily);
                            $resultFamily->execute($dataFamily);

                            if ($resultFamily->rowCount() != 1) {
                                echo "<div class='error'>";
                                echo __('There is no family information available for the current staff member.');
                                echo '</div>';
                            } else {
                                $rowFamily = $resultFamily->fetch();
                                $count = 1;
                                // Get adults
                                $dataMember = array('tawasulFamilyID' => $rowFamily['tawasulFamilyID']);
                                $sqlMember = 'SELECT * FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID ORDER BY contactPriority, surname, preferredName';
                                $resultMember = $connection2->prepare($sqlMember);
                                $resultMember->execute($dataMember);

                                while ($rowMember = $resultMember->fetch()) {
                                    $table = DataTable::createDetails('family' . $count);

                                    $table->addColumn('preferredName', __('Name'))
                                        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Parent']));

                                    $table->addColumn('relationship', __('Relationship'))
                                        ->format(function($rowMember) {
                                            if ($rowMember['role'] == 'Parent') {
                                                if ($rowMember['gender'] == 'M') {
                                                    echo __('Father');
                                                } elseif ($rowMember['gender'] == 'F') {
                                                    echo __('Mother');
                                                } else {
                                                    echo __($rowMember['role']);
                                                }
                                            } else {
                                                echo __($rowMember['role']);
                                            }
                                        });

                                    $table->addColumn('phone', __('Contact By Phone'))
                                        ->format(function($rowMember) {
                                            $phones = '';

                                            for ($i = 1; $i < 5; ++$i) {
                                                if ($rowMember['phone'.$i] != '') {
                                                    $phones = $phones . Format::using('phone', ['phone' . $i, 'phone'.$i.'CountryCode', 'phone'.$i.'Type']) . '<br/>';
                                                }
                                            }

                                            return $phones;
                                        });

                                    echo $table->render([$rowMember]);

                                    ++$count;
                                }
                            }

                            $table = DataTable::createDetails('emergency');
                            $table->setTitle(__('Emergency Contacts'));

                            for ($i = 1; $i <= 2; $i++) {
                                $emergency = 'emergency' . $i;
                                $table->addColumn($emergency . 'Name', __('Contact ' . $i))
                                    ->format(function($row) use ($emergency) {
                                        if ($row[$emergency . 'Relationship'] != '') {
                                            return $row[$emergency . 'Name'] . ' (' . __($row[$emergency . 'Relationship']) . ')';
                                        }
                                        return $row[$emergency . 'Name'];
                                    });

                                $table->addColumn($emergency . 'Number1', __('Number 1'));
                                $table->addColumn($emergency . 'Number2', __('Number 2'));
                            }

                            echo $table->render([$row]);
                        }

                    } elseif ($subpage == 'Activities') {

                        $highestActionActivities = getHighestGroupedAction($guid, '/modules/TawasulActivities/activities_attendance.php', $connection2);
                        $canAccessEnrolment = isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_enrolment.php');

                        // CRITERIA
                        $activityGateway = $container->get(ActivityGateway::class);
                        $criteria = $activityGateway->newQueryCriteria()
                            ->sortBy('name')
                            ->fromArray($_POST);

                        $activities = $activityGateway->queryActivitiesByParticipant($criteria, $session->get('tawasulSchoolYearID'), $tawasulPersonID);

                        // DATA TABLE
                        $table = DataTable::createPaginated('myActivities', $criteria);

                        $table->addColumn('name', __('Activity'))
                            ->format(function ($activity) {
                                return $activity['name'].'<br/><span class="text-xs italic">'.$activity['type'].'</span>';
                            });
                        $table->addColumn('role', __('Role'))
                            ->format(function ($activity) {
                                return !empty($activity['role']) ? __($activity['role']) : __('Student');
                            });

                        $table->addColumn('status', __('Status'))
                            ->format(function ($activity) {
                                return !empty($activity['status']) ? __($activity['status']) : '<i>'.__('N/A').'</i>';
                            });

                        $table->addActionColumn()
                            ->addParam('tawasulActivityID')
                            ->format(function ($activity, $actions) use ($highestActionActivities, $canAccessEnrolment) {
                                if ($activity['role'] == 'Organiser' &&  $canAccessEnrolment) {
                                    $actions->addAction('enrolment', __('Enrolment'))
                                        ->addParam('tawasulSchoolYearTermID', '')
                                        ->addParam('search', '')
                                        ->setIcon('config')
                                        ->setURL('/modules/TawasulActivities/activities_manage_enrolment.php');
                                }

                                $actions->addAction('view', __('View Details'))
                                    ->isModal(1000, 550)
                                    ->setURL('/modules/TawasulActivities/activities_my_full.php');

                                if ($highestActionActivities == "Enter Activity Attendance" ||
                                ($highestActionActivities == "Enter Activity Attendance_leader" && ($activity['role'] == 'Organiser' || $activity['role'] == 'Assistant' || $activity['role'] == 'Coach'))) {
                                    $actions->addAction('attendance', __('Attendance'))
                                        ->setIcon('attendance')
                                        ->setURL('/modules/TawasulActivities/activities_attendance.php');
                                }
                            });

                        echo $table->render($activities);

                    } elseif ($subpage == 'Timetable') {
                        if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_view.php') == false) {
                            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                        } else {
                            if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php') == true) {
                                $form = Form::createBlank('buttons');
                                $form->addHeaderAction('edit', __('Edit'))
                                    ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php')
                                    ->addParam('tawasulPersonID', $tawasulPersonID)
                                    ->addParam('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                    ->addParam('type', 'Staff')
                                    ->addParam('allUsers', 'on')
                                    ->displayLabel();
                                echo $form->getOutput();
                            }

                            $ttDate = !empty($_REQUEST['ttDate']) ? Format::dateConvert($_REQUEST['ttDate']) : null;
                            $tawasulTTID = $_REQUEST['tawasulTTID'] ?? '';
                            
                            // Create timetable context
                            $context = $container->get(TimetableContext::class)
                                ->set('tawasulSchoolYearID', $session->get('tawasulSchoolYearID'))
                                ->set('tawasulPersonID', $tawasulPersonID)
                                ->set('tawasulTTID', $tawasulTTID);

                            // Build and render timetable
                            echo $container->get(Timetable::class)
                                ->setDate($ttDate)
                                ->setContext($context)
                                ->addCoreLayers($container)
                                ->getOutput(); 
                        }
                    }

                    // Handle Staff Profile Hooks
                    if (!empty($hook)) {
                        $rowHook = $hookGateway->getByID($_GET['tawasulHookID'] ?? '');
                        if (empty($rowHook)) {
                            echo $page->getBlankSlate();
                        } else {
                            $options = unserialize($rowHook['options']);

                            // Check for permission to hook
                            $hookPermission = $hookGateway->getHookPermission($rowHook['tawasulHookID'], $session->get('tawasulRoleIDCurrent'), $options['sourceModuleName'] ?? '', $options['sourceModuleAction'] ?? '');

                            if (empty($options) || empty($hookPermission)) {
                                echo Format::alert(__('Your request failed because you do not have access to this action.'), 'error');
                            } else {
                                $include = $session->get('absolutePath').'/modules/'.$options['sourceModuleName'].'/'.$options['sourceModuleInclude'];
                                if (!file_exists($include)) {
                                    echo Format::alert(__('The selected page cannot be displayed due to a hook error.'), 'error');
                                } else {
                                    include $include;
                                }
                            }
                        }
                    }

                    $page->addSidebarExtra($page->fetchFromTemplate('profile/sidebar.twig.html', [
                        'canViewEmergency' => ($highestActionManage == 'Manage Staff_confidential') ? true : false,
                        'userPhoto' => Format::userPhoto($row['image_240'], 240),
                        'canViewTimetable' => isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/tt_view.php'),
                        'tawasulPersonID' => $tawasulPersonID,
                        'subpage' => $subpage,
                        'search' => $search,
                        'allStaff' => $allStaff,
                        'hooks' => $hooks,
                        'currentHook' => $hook,
                        'q' => $_GET['q'] ?? '',
                    ]));
                }
            }
        }
    }
}
