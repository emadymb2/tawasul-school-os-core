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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_family_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $urlParams = ['tawasulSchoolYearID' => $tawasulSchoolYearID];

    $page->breadcrumbs
        ->add(__('Family Data Updates'), 'data_family_manage.php', $urlParams)
        ->add(__('Edit Request'));

    //Check if tawasulFamilyUpdateID specified
    $tawasulFamilyUpdateID = $_GET['tawasulFamilyUpdateID'] ?? '';
    if ($tawasulFamilyUpdateID == 'Y') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulFamilyUpdateID' => $tawasulFamilyUpdateID);
            $sql = 'SELECT tawasulFamily.* FROM tawasulFamilyUpdate JOIN tawasulFamily ON (tawasulFamilyUpdate.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulFamilyUpdateID=:tawasulFamilyUpdateID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
			$data = array('tawasulFamilyUpdateID' => $tawasulFamilyUpdateID);
			$sql = 'SELECT tawasulFamilyUpdate.* FROM tawasulFamilyUpdate JOIN tawasulFamily ON (tawasulFamilyUpdate.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulFamilyUpdateID=:tawasulFamilyUpdateID';
			$newResult = $pdo->executeQuery($data, $sql);

            //Let's go!
			$oldValues = $result->fetch();
			$newValues = $newResult->fetch();
			
			// Provide a link back to edit the associated record
            if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit.php')) {
                $params = [ 
                    'tawasulFamilyID' => $oldValues['tawasulFamilyID']
                ];
                $page->navigator->addHeaderAction('edit', __('Edit Family'))
                    ->setURL('/modules/TawasulUserAdmin/family_manage_edit.php')
                    ->addParams($params)
                    ->setIcon('config')
                    ->displayLabel();
            }

			$compare = array(
				'nameAddress'           => __('Address Name'),
				'homeAddress'           => __('Home Address'),
				'homeAddressDistrict'   => __('Home Address (District)'),
				'homeAddressCountry'    => __('Home Address (Country)'),
				'languageHomePrimary'   => __('Home Language - Primary'),
				'languageHomeSecondary' => __('Home Language - Secondary'),
			);

			$form = Form::createTable('updateFamily', $session->get('absoluteURL').'/modules/'.$session->get('module').'/data_family_manage_editProcess.php?tawasulFamilyUpdateID='.$tawasulFamilyUpdateID);

			$form->setClass('w-full colorOddEven');
			$form->addHiddenValue('address', $session->get('address'));
			$form->addHiddenValue('tawasulFamilyID', $oldValues['tawasulFamilyID']);

			$row = $form->addRow()->setClass('head bg-gray-200');
				$row->addContent(__('Field'));
				$row->addContent(__('Current Value'));
				$row->addContent(__('New Value'));
				$row->addContent(__('Accept'));

            $changeCount = 0;
			foreach ($compare as $fieldName => $label) {
				$isMatching = ($oldValues[$fieldName] != $newValues[$fieldName]);

				$row = $form->addRow();
					$row->addLabel('new'.$fieldName.'On', $label);
					$row->addContent($oldValues[$fieldName]);
					$row->addContent($newValues[$fieldName])->addClass($isMatching ? 'matchHighlightText' : '');

				if ($isMatching) {
					$row->addCheckbox('new'.$fieldName.'On')->checked(true)->setClass('textCenter');
					$form->addHiddenValue('new'.$fieldName, $newValues[$fieldName]);
                    $changeCount++;
				} else {
					$row->addContent();
				}
			}

            $row = $form->addRow();
                $row->addSubmit();

			echo $form->getOutput();
        }
    }
}
