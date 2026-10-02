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
use TawasulOS\Forms\Prefab\BulkActionForm;
use TawasulOS\Domain\Messenger\MessengerGateway;

if (isActionAccessible($guid, $connection2, "/modules/TawasulMessenger/messenger_manage_report.php")==FALSE) {
    // Access denied
    $page->addError(__("You do not have access to this action."));
}
else {
    // Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET["q"], $connection2) ;
    if ($highestAction == FALSE) {
        $page->addError(__("The highest grouped action cannot be determined."));
    }
    else {
        $tawasulMessengerID = $_GET['tawasulMessengerID'] ?? null;
        $search = $_GET['search'] ?? null;
        $confirmationMode = $_GET['confirmationMode'] ?? 'One';

        $page->breadcrumbs
            ->add(__('Manage Messages'), 'messenger_manage.php', ['search' => $search])
            ->add(__('View Send Report'));

        $nonConfirm = 0;
        $noConfirm = 0;
        $yesConfirm = 0;

        $data = ['tawasulMessengerID' => $tawasulMessengerID];
        $sql = "SELECT tawasulMessenger.* FROM tawasulMessenger WHERE tawasulMessengerID=:tawasulMessengerID";
        $result = $connection2->prepare($sql);
        $result->execute($data);

        if ($result->rowCount() < 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            $values = $result->fetch();
            
            $page->return->addReturns(['error2' => __('Some elements of your request failed, but others were successful.'),
            'success1' => __("Your message has been dispatched to a team of highly trained tawasuls for delivery: not all messages may arrive at their destination, but an attempt has been made to get them all out. You'll receive a notification once all messages have been sent.")]);

            // Create a reusable confirmation closure
            $confirmationIndicator = function($recipient, $emailReceipt = false, $confirmationRequired = true)  {
                if ($emailReceipt == 'N') return '';
                if (empty($recipient['key'])) return '';
                
                $title = Format::yesNo($recipient['confirmed']);
                $icon = $recipient['confirmed'] == 'Y'
                    ? icon('solid', 'check', 'size-6 mr-2 fill-current text-green-600')   
                    : icon('solid', 'cross', 'size-6 mr-2 fill-current text-red-700'); 

                if (!$confirmationRequired && $recipient['confirmed'] != 'Y') {
                    $icon = icon('solid', 'cross', 'size-6 mr-2 fill-current text-gray-700 opacity-50'); 
                    $title = __('Not Required');
                }

                return Format::tooltip($icon, $title, 'inline-block align-middle');
            };

            $sender = false;
            if ($values['tawasulPersonID'] == $session->get('tawasulPersonID') || $highestAction == 'Manage Messages_all')  {
                $sender = true;
            }

            if ($highestAction != 'Manage Messages_all' && $values['tawasulPersonID'] != $session->get('tawasulPersonID') && $values['enableSharingLink'] == 'N') {
                $page->addError(__("You do not have access to this action."));
                return;
            }

            if ($sender && $values['email'] == 'Y' && $values['emailReceipt'] == 'Y') {
                $alertText = __('Email read receipts have been enabled for this message. You can use the Resend action along with the checkboxes next to recipients who have not yet confirmed to send a reminder to these users.').' '.__('Recipients who may not have received the original email since they were added later or due to a delivery issue are highlighted in orange.');

                if (!empty($values['emailReceiptText'])) {
                    $alertText .= '<br/><br/><b>'.__('Receipt Confirmation Text') . '</b>: '.$values['emailReceiptText'];
                }

                echo Format::alert($alertText, 'success');
            } elseif ($sender && $values['email'] == 'Y' && $values['emailReceipt'] == 'N') {
                echo Format::alert(__('Email read receipts have not been enabled for this message, however you can still use the Resend action to manually send messages.').' '.__('Recipients who may not have received the original email since they were added later or due to a delivery issue are highlighted in orange.'), 'message');
            }

            echo '<h2>';
            echo __('Report Data');
            echo '</h2>';

            // CONFIRMATION MODE
            if ($values['email'] == 'Y' && $values['emailReceipt'] == 'Y') {
                // Determine the target categories that are active
                $messengerGateway = $container->get(MessengerGateway::class);
                $targets = $messengerGateway->selectMessageTargetsByID($tawasulMessengerID)->fetchAll();

                $parents = $students = $staff = false;

                foreach ($targets as $target) {
                    $parents = $target['parents'] == 'Y' ? true : $parents;
                    $students = $target['students'] == 'Y' ? true : $students;
                    $staff = $target['staff'] == 'Y' ? true : $staff;
                }

                // Auto-submitting form to select the confirmation mode
                $form = Form::create('filters', $session->get('absoluteURL') . '/index.php', 'get')->enableQuickSubmit()->setAttribute('hx-trigger', 'change from:#confirmationMode');

                $form->addHiddenValue('q', '/modules/TawasulMessenger/messenger_manage_report.php');
                $form->addHiddenValue('tawasulMessengerID', $tawasulMessengerID);
                $form->addHiddenValue('search', $search);
                $form->addHiddenValue('sidebar', 'true');

                $form->setClass('noIntBorder w-full pb-1');

                $row = $form->addRow();
                    $row->addLabel('subjectLabel', __('Message'));
                    $row->addTextField('subject')->readonly()->setValue($values['subject']);

                $confirmationOptions = [];
                if ($parents) {
                    $confirmationOptions['One'] = __('At Least One Parent');
                    $confirmationOptions['Both'] = __('Both Parents');
                }
                if ($parents && $students) {
                    $confirmationOptions['All'] = __('Student and Parent');
                }
                $confirmationOptions['Any'] = __('Any Recipient');

                $row = $form->addRow();
                    $row->addLabel('confirmationMode', __('Confirmation Required By'));
                    $row->addSelect('confirmationMode')->fromArray($confirmationOptions)->selected($confirmationMode);

                if ($values['enableSharingLink'] == 'Y'  && ($values['tawasulPersonID'] == $session->get('tawasulPersonID') || $highestAction == 'Manage Messages_all')) {
                    $linkURL = Url::fromModuleRoute('TawasulMessenger', 'messenger_manage_report')->withQueryParams(['tawasulMessengerID' => $tawasulMessengerID])->withAbsoluteUrl(true);
                    $row = $form->addRow();
                        $row->addLabel('sharingLink', __('Shareable Send Report'))->description(__('You can copy this link to share it with other users.'));
                        $row->addTextField('sharingLink')->setValue(urldecode($linkURL));
                    }
                echo $form->getOutput();
            }

            // TABS
            $tabs = [];

            $data = ['tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'today' => date('Y-m-d')];
            $sql = "SELECT tawasulFormGroup.nameShort AS formGroup, tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFamilyChild.tawasulFamilyID, parent1.email AS parent1email, parent1.surname AS parent1surname, parent1.preferredName AS parent1preferredName, parent1.tawasulPersonID AS parent1tawasulPersonID, parent2.email AS parent2email, parent2.surname AS parent2surname, parent2.preferredName AS parent2preferredName, parent2.tawasulPersonID AS parent2tawasulPersonID
                FROM tawasulPerson
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                LEFT JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID)
                LEFT JOIN tawasulFamilyAdult AS parent1Fam ON (parent1Fam.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID AND parent1Fam.contactPriority=1)
                LEFT JOIN tawasulPerson AS parent1 ON (parent1Fam.tawasulPersonID=parent1.tawasulPersonID AND parent1.status='Full' AND NOT parent1.surname IS NULL)
                LEFT JOIN tawasulFamilyAdult AS parent2Fam ON (parent2Fam.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID AND parent2Fam.contactPriority=2 AND parent2Fam.contactEmail='Y')
                LEFT JOIN tawasulPerson AS parent2 ON (parent2Fam.tawasulPersonID=parent2.tawasulPersonID AND parent2.status='Full' AND NOT parent2.surname IS NULL)
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulPerson.status='Full'
                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today) AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)
                GROUP BY tawasulPerson.tawasulPersonID
                ORDER BY formGroup, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFamilyChild.tawasulFamilyID";
            $result = $connection2->prepare($sql);
            $result->execute($data);

            if ($result->rowCount() < 1) {
                // echo $page->getBlankSlate();
            } else {
                $confirmationRequired = [];

                // Store receipt for this message data in an array
                $dataReceipts = ['tawasulMessengerID' => $tawasulMessengerID];
                $sqlReceipts = "SELECT tawasulPersonID, tawasulMessengerReceiptID, confirmed, sent, `key`, targetType, targetID, tawasulPersonIDListStudent FROM tawasulMessengerReceipt WHERE tawasulMessengerID=:tawasulMessengerID";
                $resultReceipts = $connection2->prepare($sqlReceipts);
                $resultReceipts->execute($dataReceipts);
                $receiptRows = $resultReceipts->fetchAll();

                $receipts = [];

                foreach ($receiptRows as $receiptRow) {
                    $receiptRow['tawasulPersonIDListStudent'] = empty($receiptRow['tawasulPersonIDListStudent'])
                        ? null
                        : explode(',', $receiptRow['tawasulPersonIDListStudent']);

                    if (!empty($receiptRow['tawasulPersonID'])) {
                        $receipts[$receiptRow['tawasulPersonID']] = $receiptRow;
                    } else {
                        $receipts['ext_'.$receiptRow['tawasulMessengerReceiptID']] = $receiptRow;
                    }
                }
                
                $form = BulkActionForm::create('resendByRecipient', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/messenger_manage_report_processBulk.php?tawasulMessengerID='.$tawasulMessengerID.'&search='.$search);
                $form->addHiddenValue('address', $session->get('address'));

                if ($sender) {

                    $row = $form->addHeaderAction('Add recipients', __('Add Recipients'))
                    ->setURL('/modules/TawasulMessenger/messenger_manage_report_addRecipients.php')
                    ->addParam('tawasulMessengerID', $tawasulMessengerID)
                    ->addParam('sidebar', 'true')
                    ->setIcon('add')
                    ->displayLabel(); 

                    $row = $form->addBulkActionRow(array('resend' => __('Resend')))->addClass('flex justify-end mb-2');
                    $row->addSubmit(__('Go'));
                }

                $formGroups = $result->fetchAll(\PDO::FETCH_GROUP);
                $countTotal = 0;

                foreach ($formGroups as $formGroupName => $recipients) {
                    $count = 0;

                    // Filter the array for only those individuals involved in the message (student or parent)
                    $recipients = array_filter($recipients, function($recipient) use (&$receipts) {
                       if (!empty($recipient['tawasulPersonID']) && !empty($receipts[$recipient['tawasulPersonID']])) {
                            return true;
                        }

                        if (!empty($recipient['parent1tawasulPersonID']) && !empty($receipts[$recipient['parent1tawasulPersonID']]) && (is_null($receipts[$recipient['parent1tawasulPersonID']]['tawasulPersonIDListStudent']) || in_array($recipient['tawasulPersonID'], $receipts[$recipient['parent1tawasulPersonID']]['tawasulPersonIDListStudent']))) {
                            return true;
                        }

                        if (!empty($recipient['parent2tawasulPersonID']) && !empty($receipts[$recipient['parent2tawasulPersonID']]) && (is_null($receipts[$recipient['parent2tawasulPersonID']]['tawasulPersonIDListStudent']) || in_array($recipient['tawasulPersonID'], $receipts[$recipient['parent2tawasulPersonID']]['tawasulPersonIDListStudent']))) {
                            return true;
                        }

                        return false;
                    });

                    // Skip this form group if there's no involved individuals
                    if (empty($recipients)) continue;

                    $form->addRow()->addHeading($formGroupName);
                    $table = $form->addRow()->addTable()->setClass('colorOddEven w-full');
                    
                    $header = $table->addHeaderRow();
                        $header->addContent(__('Total Count'));
                        $header->addContent(__('Form Count'));
                        $header->addContent(__('Student'))->addClass('w-1/4');
                        $header->addContent(__('Parent 1'))->addClass('w-1/4');
                        $header->addContent(__('Parent 2'))->addClass('w-1/4');

                    foreach ($recipients as $recipient) {
                        $countTotal++;
                        $count++;

                        $studentName = Format::name('', $recipient['preferredName'], $recipient['surname'], 'Student', true);
                        $parent1Name = Format::name('', $recipient['parent1preferredName'], $recipient['parent1surname'], 'Parent', true);
                        $parent2Name = Format::name('', $recipient['parent2preferredName'], $recipient['parent2surname'], 'Parent', true);

                        // Tests for row completion, to set colour
                        $studentComplete = (!empty($receipts[$recipient['tawasulPersonID']]) && $receipts[$recipient['tawasulPersonID']]['confirmed'] == "Y");
                        
                        $parent1Complete = (!empty($receipts[$recipient['parent1tawasulPersonID']]) && $receipts[$recipient['parent1tawasulPersonID']]['confirmed'] == "Y");
                        $parent2Complete = (!empty($receipts[$recipient['parent2tawasulPersonID']]) && $receipts[$recipient['parent2tawasulPersonID']]['confirmed'] == "Y");

                        $bothParentsComplete = $parent1Complete && $parent2Complete;
                        $anyParentComplete = $parent1Complete || $parent2Complete;

                        $class = $values['emailReceipt'] == 'Y' ? 'error' : '';
                        
                        if ($confirmationMode == 'All' && $studentComplete && $anyParentComplete) {
                            $class = 'current';
                        } elseif ($confirmationMode == 'One' && $anyParentComplete) {
                            $class = 'current';
                        } elseif ($confirmationMode == 'Both' && $bothParentsComplete) {
                            $class = 'current';
                        } elseif ($confirmationMode == 'Any' && ($studentComplete || $anyParentComplete)) {
                            $class = 'current';
                        }

                        // Store confirmation details for consistency
                        $confirmationRequired[$recipient['tawasulPersonID']] = ($confirmationMode == 'Both' || $confirmationMode == 'Any') && !($studentComplete || $anyParentComplete) || ($confirmationMode == 'All' && !($studentComplete && $anyParentComplete));
                        $confirmationRequired[$recipient['parent1tawasulPersonID']] = (($confirmationMode == 'All' || $confirmationMode == 'One') && !$anyParentComplete) || ($confirmationMode == 'Both' && !$bothParentsComplete) || ($confirmationMode == 'Any' && !($studentComplete || $anyParentComplete));
                        $confirmationRequired[$recipient['parent2tawasulPersonID']] = (($confirmationMode == 'All' || $confirmationMode == 'One') && !$anyParentComplete) || ($confirmationMode == 'Both' && !$bothParentsComplete) || ($confirmationMode == 'Any' && !($studentComplete || $anyParentComplete));

                        $row = $table->addRow()->setClass($class);
                            $row->addContent($countTotal);
                            $row->addContent($count);

                            $studentReceipt = $receipts[$recipient['tawasulPersonID']] ?? null;
                            $col = $row->addColumn()->setClass('')->addClass(!empty($studentReceipt) && $studentReceipt['confirmed'] != 'Y' && $studentReceipt['sent'] != 'Y' ? 'bg-orange-200' : '');
                                $col->addContent($confirmationIndicator($studentReceipt, $values['emailReceipt'], $confirmationRequired[$recipient['tawasulPersonID']] ?? true));
                                $col->onlyIf($sender == true && !empty($studentReceipt) && ($studentReceipt['confirmed'] == 'N' || $values['emailReceipt'] == 'N'))
                                    ->addCheckbox('tawasulMessengerReceiptIDs[]')
                                    ->setValue($studentReceipt['tawasulMessengerReceiptID'] ?? '')
                                    ->setClass('inline-block align-middle')
                                    ->alignLeft();
                                $col->addContent(!empty($studentName)? $studentName : __('N/A'))->addClass('w-auto inline-block align-middle');

                            $parent1Receipt = $receipts[$recipient['parent1tawasulPersonID']] ?? null;
                            $col = $row->addColumn()->setClass('')->addClass(!empty($parent1Receipt) && $parent1Receipt['confirmed'] != 'Y' && $parent1Receipt['sent'] != 'Y' ? 'bg-orange-200' : '');
                                $col->addContent($confirmationIndicator($parent1Receipt, $values['emailReceipt'], $confirmationRequired[$recipient['parent1tawasulPersonID']] ?? true));
                                $col->onlyIf($sender == true && !empty($parent1Receipt) && ($parent1Receipt['confirmed'] == 'N' || $values['emailReceipt'] == 'N'))
                                    ->addCheckbox('tawasulMessengerReceiptIDs[]')
                                    ->setValue($parent1Receipt['tawasulMessengerReceiptID'] ?? '')
                                    ->setClass('inline-block align-middle')
                                    ->alignLeft();
                                $col->addContent(!empty($recipient['parent1surname'])? $parent1Name : __('N/A'))->addClass('w-auto inline-block align-middle');

                            $parent2Receipt = $receipts[$recipient['parent2tawasulPersonID']] ?? null;
                            $col = $row->addColumn()->setClass('')->addClass(!empty($parent2Receipt) && $parent2Receipt['confirmed'] != 'Y' && $parent2Receipt['sent'] != 'Y' ? 'bg-orange-200' : '');
                                $col->addContent($confirmationIndicator($parent2Receipt, $values['emailReceipt'], $confirmationRequired[$recipient['parent2tawasulPersonID']] ?? true));
                                $col->onlyIf($sender == true && !empty($parent2Receipt) && ($parent2Receipt['confirmed'] == 'N' || $values['emailReceipt'] == 'N'))
                                    ->addCheckbox('tawasulMessengerReceiptIDs[]')
                                    ->setValue($parent2Receipt['tawasulMessengerReceiptID'] ?? '')
                                    ->setClass('inline-block align-middle')
                                    ->alignLeft();
                                $col->addContent(!empty($recipient['parent2surname'])? $parent2Name : __('N/A'))->addClass('w-auto inline-block align-middle');
                    }
                }

                if ($countTotal == 0) {
                    $table = $form->addRow()->addTable()->setClass('colorOddEven w-full');
                    $table->addRow()->addTableCell(__('There are no records to display.'))->colSpan(8);
                }

                $tabs['byFormGroup'] = [
                    'label'   => __('By Form Group'),
                    'content' => $form->getOutput(),
                    'icon'    => 'users',
                ];
            }
            
            if (!is_null($tawasulMessengerID)) {
                
                $data = ['tawasulMessengerID' => $tawasulMessengerID];
                $sql = "SELECT COALESCE(tawasulPerson.surname, tawasulMessengerMailingListRecipient.surname) AS surname, COALESCE(tawasulPerson.preferredName, tawasulMessengerMailingListRecipient.preferredName) AS preferredName, tawasulPerson.tawasulPersonID, tawasulMessenger.*, tawasulMessengerReceipt.*, COALESCE(tawasulRole.category, 'External') AS roleCategory
                    FROM tawasulMessengerReceipt
                    JOIN tawasulMessenger ON (tawasulMessengerReceipt.tawasulMessengerID=tawasulMessenger.tawasulMessengerID)
                    LEFT JOIN tawasulPerson ON (tawasulMessengerReceipt.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    LEFT JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
                    LEFT JOIN tawasulMessengerMailingListRecipient ON (tawasulMessengerMailingListRecipient.email = tawasulMessengerReceipt.contactDetail AND tawasulMessengerReceipt.targetType = 'Mailing List')
                    WHERE tawasulMessengerReceipt.tawasulMessengerID=:tawasulMessengerID ORDER BY FIELD(confirmed, 'Y','N',NULL), confirmedTimestamp, surname, preferredName, contactType";
                $result = $connection2->prepare($sql);
                $result->execute($data);

                $form = BulkActionForm::create('resendByRecipient', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/messenger_manage_report_processBulk.php?tawasulMessengerID='.$tawasulMessengerID.'&search='.$search);

                $form->addHiddenValue('address', $session->get('address'));

                if ($sender) {
                    $row = $form->addHeaderAction('Add recipients', __('Add Recipients'))
                    ->setURL('/modules/TawasulMessenger/messenger_manage_report_addRecipients.php')
                    ->addParam('tawasulMessengerID', $tawasulMessengerID)
                    ->addParam('sidebar', 'true')
                    ->setIcon('add')
                    ->displayLabel(); 

                    $row = $form->addBulkActionRow(array('resend' => __('Resend')))->addClass('flex justify-end mb-2');
                    $row->addSubmit(__('Go'));
                }

                $table = $form->addRow()->addTable()->setClass('colorOddEven w-full');

                $header = $table->addHeaderRow();
                    $header->addContent();
                    $header->addContent(__('Recipient'));
                    $header->addContent(__('Role'));
                    $header->addContent(__('Contact Type'));
                    $header->addContent(__('Contact Detail'));
                    $header->addContent(__('Sent'));
                    $header->addContent(__('Receipt Confirmed'));
                    $header->addContent(__('Timestamp'));
                    if ($sender == true) {
                        $header->addCheckAll();
                    }

                $recipients = $result->fetchAll();
                $recipientIDs = array_column($recipients, 'tawasulPersonID');

                foreach ($recipients as $count => $recipient) {
                    $row = $table->addRow()->addClass($recipient['confirmed'] != 'Y' && $recipient['sent'] != 'Y' ? 'warning' : '');
                        $row->addContent($count+1);
                        $row->addContent(($recipient['preferredName'] != '' && $recipient['surname'] != '') ? Format::name('', $recipient['preferredName'], $recipient['surname'], 'Student', true) : __('N/A'));
                        $row->addContent($recipient['roleCategory']);
                        $row->addContent($recipient['contactType']);
                        $row->addContent($recipient['contactDetail']);
                        $row->addContent($recipient['sent'] == 'Y' ? __('Sent') : __('Undelivered') );
                        $row->addContent($confirmationIndicator($recipient, false, $confirmationRequired[$recipient['tawasulPersonID']] ?? true));
                        $row->addContent(!empty($recipient['confirmedTimestamp']) ? Format::date(substr($recipient['confirmedTimestamp'],0,10)).' '.substr($recipient['confirmedTimestamp'],11,5) : '');

                        if ($sender == true && $recipient['contactType'] == 'Email') {
                            $required = ($recipient['sent'] != 'Y' || ($confirmationRequired[$recipient['tawasulPersonID']] ?? true)) && !empty($recipient['contactDetail']) || $values['emailReceipt'] == 'N';
                            $row->onlyIf($required)
                                ->addCheckbox('tawasulMessengerReceiptIDs[]')
                                ->setValue($recipient['tawasulMessengerReceiptID'])
                                ->addClass('bulkCheckbox')
                                ->alignCenter();

                            $row->onlyIf(!$required)->addContent();
                        } else {
                            $row->addContent();
                        }

                    if (is_null($recipient['key'])) $nonConfirm++;
                    else if ($recipient['confirmed'] == 'Y') $yesConfirm++;
                    else if ($recipient['confirmed'] == 'N') $noConfirm++;
                }

                if (count($recipients) == 0) {
                    $table->addRow()->addTableCell(__('There are no records to display.'))->colSpan(8);
                } else {
                    $sendReport = '<b>'.__('Total Messages:')." ".count($recipients)."</b><br/>";
                    $sendReport .= "<span>".__('Messages not eligible for confirmation of receipt:')." <b>$nonConfirm</b><br/>";
                    $sendReport .= "<span>".__('Messages confirmed:').' <b>'.$yesConfirm.'</b><br/>';
                    $sendReport .= "<span>".__('Messages not yet confirmed:').' <b>'.$noConfirm.'</b><br/>';

                    $form->addRow()->addClass('right')->addAlert($sendReport, 'success');
                }

                $tabs['byRecipient'] = [
                    'label'   => __('Recipient'),
                    'content' => $form->getOutput(),
                    'icon'    => 'user',
                ];
            }

            echo $page->fetchFromTemplate('ui/tabs.twig.html', [
                'tabs'   => $tabs,
                'class'  => 'mt-6',
                'outset' => false,
                'icons'  => true,
            ]);
        }

    }
}
