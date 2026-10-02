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
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/notificationSettings_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Notification Settings'), 'notificationSettings.php')
        ->add(__('Edit Notification Event'));

    $tawasulNotificationEventID = (isset($_GET['tawasulNotificationEventID']))? $_GET['tawasulNotificationEventID'] : null;

    if (empty($tawasulNotificationEventID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $gateway = new NotificationGateway($pdo);
        $result = $gateway->selectNotificationEventByID($tawasulNotificationEventID);

        if ($result->rowCount() != 1) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
            $event = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/notificationSettings_manage_editProcess.php');

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulNotificationEventID', $tawasulNotificationEventID);

            $row = $form->addRow();
                $row->addLabel('event', __('Event'));
                $row->addTextField('event')->setValue(__($event['moduleName']).': '.__($event['event']))->readOnly();

            $row = $form->addRow();
                $row->addLabel('permission', __('Permission Required'));
                $row->addTextField('permission')->setValue(__($event['actionName']))->readOnly();

            $row = $form->addRow();
                $row->addLabel('active', __('Active'));
                $row->addYesNo('active')->selected($event['active']);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();

            $description = '';
            if ($event['active'] == 'N') {
                $description .= Format::alert(__('This notification event is not active. The following subscribers will not receive any notifications until the event is set to active.'), 'warning');
            }

            if ($event['type'] == 'CLI') {
                $description .= Format::alert(__('This is a CLI notification event. It will only run if the corresponding CLI script has been setup on the server.'), 'message');
            }

            $gateway = new NotificationGateway($pdo);
            $result = $gateway->selectAllNotificationListeners($tawasulNotificationEventID, false);

            $table = DataTable::create('subscribers');
            $table->setTitle(__('Edit Subscribers'));
            $table->setDescription($description);

            $table->modifyRows(function ($listener, $row) {
                if ($listener['status'] <> 'Full') $row->addClass('error');
                if ($listener['receiveNotificationEmails'] == 'N') $row->addClass('warning');
                return $row;
            });

            $table->addColumn('name', __('Name'))
                ->sortable(['surname', 'preferredName'])
                ->format(function ($person) {
                    return Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff', false, true)
                        .'<br/>'.Format::small(Format::userStatusInfo($person));
                });

            $table->addColumn('receiveNotificationEmails', __('Receive Email Notifications?'))
                ->setTitle(__('Notifications can always be viewed on screen.'))
                ->width('15%')
                ->format(Format::using('yesNo', 'receiveNotificationEmails'));

            $table->addColumn('scope', __('Scope'))->format(function ($listener) use (&$pdo, $event) {
                if ($listener['scopeType'] == 'All') {
                    return __('All');
                } elseif ($listener['scopeType'] == 'context') {
                    return $event['contextName'].' - '.$listener['scopeContext'];
                } else {
                    switch($listener['scopeType']) {
                        case 'tawasulPersonIDStudent':   $data = array('tawasulPersonID' => $listener['scopeID']);
                                                        $sql = "SELECT 'Student' as scopeTypeName, CONCAT(surname, ' ', preferredName) as scopeIDName FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID";
                                                        break;

                        case 'tawasulYearGroupID':       $data = array('tawasulYearGroupID' => $listener['scopeID']);
                                                        $sql = "SELECT 'Year Group' as scopeTypeName, name as scopeIDName FROM tawasulYearGroup WHERE tawasulYearGroupID=:tawasulYearGroupID";
                                                        break;

                        default:                        $data = array();
                                                        $sql = "SELECT 'Scope' as scopeTypeName, 'Unknown' as scopeIDName";
                    }

                    $resultScope = $pdo->executeQuery($data, $sql);
                    if ($resultScope && $resultScope->rowCount() > 0) {
                        $scopeDetails = $resultScope->fetch();
                        return __($scopeDetails['scopeTypeName']).' - '.$scopeDetails['scopeIDName'];
                    }
                    return'';
                }
            });

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulNotificationEventID')
                ->addParam('tawasulNotificationListenerID')
                ->format(function ($listener, $actions) {
                    $actions->addAction('deleteDirect', __('Delete'))
                            ->setIcon('garbage')
                            ->directLink()
                            ->addParam('address', $_GET['q'])
                            ->setURL('/modules/TawasulSystemAdmin/notificationSettings_manage_listener_deleteProcess.php');
                });

            echo $table->render($result->toDataSet());
            echo '<br/>';

            // Filter users who can have permissions for the notification event action
            $staffMembers = array();
            $data=array( 'action' => $event['actionName']);
            $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulPerson.preferredName, tawasulPerson.surname, tawasulRole.name as roleName
                    FROM tawasulPerson
                    JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID OR FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
                    JOIN tawasulPermission ON (tawasulRole.tawasulRoleID=tawasulPermission.tawasulRoleID)
                    JOIN tawasulAction ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                    WHERE tawasulPerson.status='Full'
                    AND (tawasulAction.name=:action OR tawasulAction.name LIKE CONCAT(:action, '_%'))
                    GROUP BY tawasulPerson.tawasulPersonID
                    ORDER BY tawasulRole.tawasulRoleID, surname, preferredName" ;
            $resultSelect=$pdo->executeQuery($data, $sql);

            if ($resultSelect && $resultSelect->rowCount() > 0) {
                $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/notificationSettings_manage_listener_addProcess.php');
                $form->setFactory(DatabaseFormFactory::create($pdo));

                $form->addHiddenValue('address', $session->get('address'));
                $form->addHiddenValue('tawasulNotificationEventID', $tawasulNotificationEventID);

                while ($rowSelect = $resultSelect->fetch()) {
                    $staffMembers[__($rowSelect['roleName'])][$rowSelect['tawasulPersonID']] = Format::name("", $rowSelect["preferredName"], $rowSelect["surname"], "Staff", true, true);
                }

                $row = $form->addRow();
                    $row->addLabel('tawasulPersonID', __('Person'))->description(__('Available only to users with the required permission.'));
                    $row->addSelect('tawasulPersonID')->fromArray($staffMembers)->placeholder('Please select...')->required();

                if ($event['scopes'] == 'All') {
                    $form->addHiddenValue('scopeType', 'All');
                } else {
                    $allScopes = array(
                        'All'                   => __('All'),
                        'tawasulPersonIDStudent' => __('Student'),
                        'tawasulPersonIDStaff'   => __('Staff'),
                        'tawasulYearGroupID'     => __('Year Group'),
                        'context'               => __('Context'),
                    );

                    $eventScopes = array_combine(explode(',', $event['scopes']), explode(',', trim($event['scopes'])));
                    $availableScopes = array_intersect_key($allScopes, $eventScopes);

                    $row = $form->addRow();
                        $row->addLabel('scopeType', __('Scope'))->description(__('Apply an optional filter to notifications received.'));
                        $row->addSelect('scopeType')->fromArray($availableScopes);

                    $form->toggleVisibilityByClass('scopeTypeStudent')->onSelect('scopeType')->when('tawasulPersonIDStudent');
                    $row = $form->addRow()->addClass('scopeTypeStudent');
                        $row->addLabel('tawasulPersonIDStudent', __('Student'));
                        $row->addSelectStudent('tawasulPersonIDStudent', $session->get('tawasulSchoolYearID'))->required()->placeholder();

                    $form->toggleVisibilityByClass('scopeTypeStaff')->onSelect('scopeType')->when('tawasulPersonIDStaff');
                    $row = $form->addRow()->addClass('scopeTypeStaff');
                        $row->addLabel('tawasulPersonIDStaff', __('Student'));
                        $row->addSelectStaff('tawasulPersonIDStaff')->required()->placeholder();

                    $form->toggleVisibilityByClass('scopeTypeYearGroup')->onSelect('scopeType')->when('tawasulYearGroupID');
                    $row = $form->addRow()->addClass('scopeTypeYearGroup');
                        $row->addLabel('tawasulYearGroupID', __('Year Group'));
                        $row->addSelectYearGroup('tawasulYearGroupID')->required()->placeholder();

                    $form->toggleVisibilityByClass('scopeTypeContext')->onSelect('scopeType')->when('context');
                    $row = $form->addRow()->addClass('scopeTypeContext');
                        $row->addLabel('scopeContext', __('Context'))->description($event['contextName']);
                        $row->addTextField('scopeContext')->required()->placeholder();
                }

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit('Add');

                echo $form->getOutput();
            }
        }
    }
}
