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

use TawasulOS\Domain\User\FamilyGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Http\Url;
use TawasulOS\Forms\CustomFieldHandler;

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Families'), 'family_manage.php')
        ->add(__('Edit Family'));        

    //Check if search and tawasulFamilyID specified
    $tawasulFamilyID = $_GET['tawasulFamilyID'] ?? '';
    $search = $_GET['search'] ?? '';
    if ($search != '') {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulUserAdmin', 'family_manage.php')->withQueryParam('search', $search));
    }

    if (empty($tawasulFamilyID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    } else {
        $familyGateway = $container->get(FamilyGateway::class);
        $family = $familyGateway->getByID($tawasulFamilyID);

        if (empty($family)) {
            $page->addError(__('The specified record cannot be found.'));
            return;
        } else {
            //Let's go!
            $form = Form::create('action1', $session->get('absoluteURL').'/modules/'.$session->get('module')."/family_manage_editProcess.php?tawasulFamilyID=$tawasulFamilyID&search=$search");
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));

            $form->addRow()->addHeading('General Information', __('General Information'));

            $row = $form->addRow();
                $row->addLabel('name', __('Family Name'));
                $row->addTextField('name')->maxLength(100)->required();

            $row = $form->addRow();
        		$row->addLabel('status', __('Marital Status'));
        		$row->addSelectMaritalStatus('status')->required();

            $row = $form->addRow();
                $row->addLabel('languageHomePrimary', __('Home Language - Primary'));
                $row->addSelectLanguage('languageHomePrimary');

            $row = $form->addRow();
                $row->addLabel('languageHomeSecondary', __('Home Language - Secondary'));
                $row->addSelectLanguage('languageHomeSecondary');

            $row = $form->addRow();
                $row->addLabel('nameAddress', __('Address Name'))->description(__('Formal name to address parents with.'));
                $row->addTextField('nameAddress')->maxLength(100)->required();

            $row = $form->addRow();
                $row->addLabel('homeAddress', __('Home Address'))->description(__('Unit, Building, Street'));
                $row->addTextArea('homeAddress')->maxLength(255)->setRows(2);

            $row = $form->addRow();
                $row->addLabel('homeAddressDistrict', __('Home Address (District)'))->description(__('County, State, District'));
                $row->addTextFieldDistrict('homeAddressDistrict');

            $row = $form->addRow();
                $row->addLabel('homeAddressCountry', __('Home Address (Country)'));
                $row->addSelectCountry('homeAddressCountry');

            // CUSTOM FIELDS
            $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Family', [], $family['fields'] ?? '');

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($family);

            echo $form->getOutput();


            //Get children and prep array
            $dataChildren = array('tawasulFamilyID' => $tawasulFamilyID);
            $sqlChildren = 'SELECT * FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID ORDER BY surname, preferredName';
            $resultChildren = $pdo->select($sqlChildren, $dataChildren);

            $children = array();
            $count = 0;
            while ($rowChildren = $resultChildren->fetch()) {
                $children[$count]['image_240'] = $rowChildren['image_240'];
                $children[$count]['tawasulPersonID'] = $rowChildren['tawasulPersonID'];
                $children[$count]['preferredName'] = $rowChildren['preferredName'];
                $children[$count]['surname'] = $rowChildren['surname'];
                $children[$count]['status'] = $rowChildren['status'];
                $children[$count]['comment'] = $rowChildren['comment'];

                $dataDetail = array('tawasulPersonID' => $rowChildren['tawasulPersonID'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                $sqlDetail = 'SELECT * FROM tawasulFormGroup JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID';
                $resultDetail = $pdo->select($sqlDetail, $dataDetail);

                if ($resultDetail->rowCount() == 1) {
                    $rowDetail = $resultDetail->fetch();
                    $children[$count]['formGroup'] = $rowDetail['name'];
                }

                ++$count;
            }
            //Get adults and prep array
            $dataAdults = array('tawasulFamilyID' => $tawasulFamilyID);
            $sqlAdults = 'SELECT * FROM tawasulFamilyAdult, tawasulPerson WHERE (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) AND tawasulFamilyID=:tawasulFamilyID ORDER BY contactPriority, surname, preferredName';
            $resultAdults = $pdo->select($sqlAdults, $dataAdults);

            $adults = array();
            $count = 0;
            while ($rowAdults = $resultAdults->fetch()) {
                $adults[$count]['image_240'] = $rowAdults['image_240'];
                $adults[$count]['tawasulPersonID'] = $rowAdults['tawasulPersonID'];
                $adults[$count]['title'] = $rowAdults['title'];
                $adults[$count]['preferredName'] = $rowAdults['preferredName'];
                $adults[$count]['surname'] = $rowAdults['surname'];
                $adults[$count]['status'] = $rowAdults['status'];
                $adults[$count]['comment'] = $rowAdults['comment'];
                $adults[$count]['childDataAccess'] = $rowAdults['childDataAccess'];
                $adults[$count]['contactPriority'] = $rowAdults['contactPriority'];
                $adults[$count]['contactCall'] = $rowAdults['contactCall'];
                $adults[$count]['contactSMS'] = $rowAdults['contactSMS'];
                $adults[$count]['contactEmail'] = $rowAdults['contactEmail'];
                $adults[$count]['contactMail'] = $rowAdults['contactMail'];
                ++$count;
            }

            //Get relationships and prep array
            $dataRelationships = array('tawasulFamilyID' => $tawasulFamilyID);
            $sqlRelationships = 'SELECT * FROM tawasulFamilyRelationship WHERE tawasulFamilyID=:tawasulFamilyID';
            $resultRelationships = $pdo->select($sqlRelationships, $dataRelationships);

            $relationships = array();
            $count = 0;
            while ($rowRelationships = $resultRelationships->fetch()) {
                $relationships[$rowRelationships['tawasulPersonID1']][$rowRelationships['tawasulPersonID2']] = $rowRelationships['relationship'];
                ++$count;
            }

            // RELATIONSHIPS
            $form = Form::createTable('action2', $session->get('absoluteURL').'/modules/'.$session->get('module')."/family_manage_edit_relationshipsProcess.php?tawasulFamilyID=$tawasulFamilyID&search=$search");
            $form->setTitle(__('Relationships'));
            $form->setDescription(__('Use the table below to show how each child is related to each adult in the family.'));

            if ($resultChildren->rowCount() < 1 or $resultAdults->rowCount() < 1) {
                $form->setDescription(Format::alert(__('There are not enough people in this family to form relationships.')));
            } else {
                $form->setFactory(DatabaseFormFactory::create($pdo));
                $form->setClass('colorOddEven w-full');

                $form->addHiddenValue('address', $session->get('address'));

                $row = $form->addRow()->addClass('head break');
                    $row->addContent(__('Adults'));
                    foreach ($children as $child) {
                        $row->addContent(Format::name('', $child['preferredName'], $child['surname'], 'Student'));
                    }

                $count = 0;
                foreach ($adults as $adult) {
                    ++$count;
                    $row = $form->addRow();
                        $row->addContent(Format::name($adult['title'], $adult['preferredName'], $adult['surname'], 'Parent'));
                        foreach ($children as $child) {
                            $form->addHiddenValue('tawasulPersonID1[]', $adult['tawasulPersonID']);
                            $form->addHiddenValue('tawasulPersonID2[]', $child['tawasulPersonID']);
                            $relationshipSet = (isset($relationships[$adult['tawasulPersonID']][$child['tawasulPersonID']]) ? $relationships[$adult['tawasulPersonID']][$child['tawasulPersonID']] : null);
                            $row->addSelectRelationship('relationships['.$adult['tawasulPersonID'].']['.$child['tawasulPersonID'].']')->setClass('smallWidth floatNone')->selected($relationshipSet);
                        }
                }

                $row = $form->addRow();
                    $row->addSubmit();

                $form->loadAllValuesFrom($family);
            }

            echo $form->getOutput();

            // CHILDREN
            $table = DataTable::create('children');
            $table->setTitle(__('View Children'));

            $table->addColumn('photo', __('Photo'))
                ->format(Format::using('photo', ['image_240']));

            $table->addColumn('name', __('Name'))
                ->format(Format::using('nameLinked', ['tawasulPersonID', '', 'preferredName', 'surname', 'Student']));

            $table->addColumn('status', __('Status'))->translatable();

            $table->addColumn('formGroup', __('Form Group'));
            $table->addColumn('comment', __('Comment'))
                ->format(function ($child) {
                    return nl2br($child['comment']);
                });

            $table->addActionColumn()
                ->addParam('search', $search)
                ->addParam('tawasulFamilyID', $tawasulFamilyID)
                ->addParam('tawasulPersonID')
                ->format(function($child, $actions) use ($session) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/' . $session->get('module') . '/family_manage_edit_editChild.php');

                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/' . $session->get('module') . '/family_manage_edit_deleteChild.php');

                    $actions->addAction('changePassword', __('Change Password'))
                        ->setIcon('key')
                        ->setURL('/modules/' . $session->get('module') . '/user_manage_password.php');
                });

            echo $table->render($children);

            // ADD CHILD
            $form = Form::create('action3', $session->get('absoluteURL').'/modules/'.$session->get('module')."/family_manage_edit_addChildProcess.php?tawasulFamilyID=$tawasulFamilyID&search=$search");
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));

            $form->addRow()->addHeading('Add Child', __('Add Child'));

            $row = $form->addRow();
                $row->addLabel('tawasulPersonID', __('Child\'s Name'));
                $row->addSelectStudent('tawasulPersonID', $session->get('tawasulSchoolYearID'), array('allStudents' => true, 'byName' => true, 'byForm' => true, 'showForm' => true))->placeholder()->required();

            $row = $form->addRow();
                $row->addLabel('comment', __('Comment'));
                $row->addTextArea('comment')->setRows(8);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();

            // ADULTS
            $table = DataTable::create('adults');
            $table->setTitle(__('View Adults'));
            $table->setDescription(Format::alert(__('Logic exists to try and ensure that there is always one and only one parent with Contact Priority set to 1. This may result in values being set which are not exactly what you chose.'), 'warning'));

            $table->addColumn('name', __('Name'))
                ->format(function ($adult) {
                    $name = Format::name($adult['title'], $adult['preferredName'], $adult['surname'], 'Parent');
                    return Format::link('./index.php?q=/modules/TawasulUserAdmin/user_manage_edit.php&tawasulPersonID=' . $adult['tawasulPersonID'], $name);
                });

            $table->addColumn('status', __('Status'))->translatable();

            $table->addColumn('comment', __('Comment'))
                ->format(function ($adult) {
                    return nl2br($adult['comment']);
                });

            //Note: This is hacky, but will have to exist until rotating becomes built-in functionality
            $table->addColumn('childDataAccess', '<div class="transform -rotate-90"> ' . __('Data Access') . '</div>')
                ->width('50px')
                ->format(function($adult){
                    return Format::yesNo($adult['childDataAccess']);
                });

            $table->addColumn('contactPriority', '<div class="transform -rotate-90"> ' . __('Contact Priority') . '</div>')
                ->width('50px');

            $table->addColumn('contactCall', '<div class="transform -rotate-90"> ' . __('Contact By Phone') . '</div>')
                ->format(function($adult){
                    return Format::yesNo($adult['contactCall']);
                })
                ->width('50px');

            $table->addColumn('contactSMS', '<div class="transform -rotate-90"> ' . __('Contact By SMS') . '</div>')
                ->format(function($adult){
                    return Format::yesNo($adult['contactSMS']);
                })
                ->width('50px');

            $table->addColumn('contactEmail', '<div class="transform -rotate-90"> ' . __('Contact By Email') . '</div>')
                ->width('50px')
                ->format(function($adult){
                    return Format::yesNo($adult['contactEmail']);
                });

            $table->addColumn('contactMail', '<div class="transform -rotate-90"> ' . __('Contact By Mail') . '</div>')
                ->width('50px')
                ->format(function($adult){
                    return Format::yesNo($adult['contactMail']);
                });

            $table->addActionColumn()
                ->addParam('tawasulFamilyID', $tawasulFamilyID)
                ->addParam('tawasulPersonID')
                ->addParam('search', $search)
                ->format(function ($adult, $actions) use ($session) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/' . $session->get('module') . '/family_manage_edit_editAdult.php');

                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/' . $session->get('module') . '/family_manage_edit_deleteAdult.php');

                    $actions->addAction('changePassword', __('Change Password'))
                        ->setIcon('key')
                        ->setURL('/modules/' . $session->get('module') . '/user_manage_password.php');
                });

            echo $table->render($adults);


            $form = Form::create('action4', $session->get('absoluteURL').'/modules/'.$session->get('module')."/family_manage_edit_addAdultProcess.php?tawasulFamilyID=$tawasulFamilyID&search=$search");

            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));

            $form->addRow()->addHeading('Add Adult', __('Add Adult'));

            $adults = array();

            $sqlSelect = "SELECT status, tawasulPersonID, preferredName, surname, username FROM tawasulPerson WHERE status='Full' OR status='Expected' ORDER BY surname, preferredName";
            $resultSelect = $pdo->select($sqlSelect);

            while ($rowSelect = $resultSelect->fetch()) {
                $expected = (($rowSelect['status'] == 'Expected') ? ' ('.__('Expected').')' : '');
                $adults[$rowSelect['tawasulPersonID']] = Format::name('', htmlPrep($rowSelect['preferredName']), htmlPrep($rowSelect['surname']), 'Parent', true, true).' ('.$rowSelect['username'].')'.$expected;
            }

            $row = $form->addRow();
                $row->addLabel('tawasulPersonID2', __('Adult\'s Name'));
                $row->addSelect('tawasulPersonID2')->fromArray($adults)->placeHolder()->required();

            $row = $form->addRow();
                $row->addLabel('comment2', __('Comment'))->description(__('Data displayed in full Student Profile'));
                $row->addTextArea('comment2')->setRows(8);

            $row = $form->addRow();
                $row->addLabel('childDataAccess', __('Data Access?'))->description(__('Access data on family\'s children?'));
                $row->addYesNo('childDataAccess')->required();

            $priorities = array(
                '1' => __('1'),
                '2' => __('2'),
                '3' => __('3')
            );
            $row = $form->addRow();
                $row->addLabel('contactPriority', __('Contact Priority'))->description(__('The order in which school should contact family members.'));
                $row->addSelect('contactPriority')->fromArray($priorities)->required();

            $form->toggleVisibilityByClass('contact')->onSelect('contactPriority')->whenNot('1');

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactCall', __('Call?'))->description(__('Receive non-emergency phone calls from school?'));
                $row->addYesNo('contactCall')->required();

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactSMS', __('SMS?'))->description(__('Receive non-emergency SMS messages from school?'));
                $row->addYesNo('contactSMS')->required();

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactEmail', __('Email?'))->description(__('Receive non-emergency emails from school?'));
                $row->addYesNo('contactEmail')->required();

            $row = $form->addRow()->addClass('contact');
                $row->addLabel('contactMail', __('Mail?'))->description(__('Receive postage mail from school?'));
                $row->addYesNo('contactMail')->required();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
