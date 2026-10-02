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
use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;
use TawasulOS\Domain\Activities\ActivitySlotGateway;
use TawasulOS\Domain\Activities\ActivityPhotoGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Activities'), 'activities_manage.php')
        ->add(__('Edit Activity'));
    
    $page->return->addReturns(['error3' => __('Your request failed due to an attachment error.')]);

    //Check if tawasulActivityID specified
    $tawasulActivityID = $_GET['tawasulActivityID'] ?? '';
    if ($tawasulActivityID == 'Y') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $activityGateway = $container->get(ActivityGateway::class);
        $values = $activityGateway->getByID($tawasulActivityID);

        if (empty($values)) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
            //Let's go!
            $search = $_GET['search'] ?? '';
            $tawasulSchoolYearTermID = $_GET['tawasulSchoolYearTermID'] ?? '';

            $settingGateway = $container->get(SettingGateway::class);

            $form = Form::create('activity', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/activities_manage_editProcess.php');
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulActivityID', $tawasulActivityID);
            $form->addHiddenValue('search', $search);
            $form->addHiddenValue('tawasulSchoolYearTermID', $tawasulSchoolYearTermID);

            if (!empty($search) || !empty($tawasulSchoolYearTermID)) {
                $form->addHeaderAction('back', __('Back to Search Results'))
                    ->setURL('/modules/' . $session->get('module') . '/activities_manage.php')
                    ->addParam('search', $search)
                    ->addParam('tawasulSchoolYearTermID', $tawasulSchoolYearTermID);
            }


            $form->addRow()->addHeading('Basic Information', __('Basic Information'));

            $row = $form->addRow();
                $row->addLabel('name', __('Name'));
                $row->addTextField('name')
                    ->required()
                    ->maxLength(40);

            $row = $form->addRow();
                $row->addLabel('provider', __('Provider'));
                $row->addSelect('provider')
                    ->required()
                    ->fromArray([
                        'School' => $session->get('organisationNameShort'),
                        'External' => __('External')
                    ]);

            $categories = $container->get(ActivityCategoryGateway::class)->selectCategoriesBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();
            $row = $form->addRow();
                $row->addLabel('tawasulActivityCategoryID', __('Category'));
                $row->addSelect('tawasulActivityCategoryID')->fromArray($categories)->placeholder();
                
            $activityTypes = $activityGateway->selectActivityTypeOptions()->fetchKeyPair();

            if (!empty($activityTypes)) {
                $row = $form->addRow();
                    $row->addLabel('type', __('Type'));
                    $row->addSelect('type')->fromArray($activityTypes)->placeholder();
            }

            $row = $form->addRow();
                $row->addLabel('active', __('Active'));
                $row->addYesNo('active')->required();

            $row = $form->addRow();
                $row->addLabel('registration', __('Registration'))->description(__('Assuming system-wide registration is open, should this activity be open for registration?'));
                $row->addYesNo('registration')->required();

            $dateType = $settingGateway->getSettingByScope('Activities', 'dateType');
            $form->addHiddenValue('dateType', $dateType);
            if ($dateType != 'Date') {
                $row = $form->addRow();
                    $row->addLabel('tawasulSchoolYearTermIDList', __('Terms'))->description(__('Terms in which the activity will run.'));
                    $row->addCheckboxSchoolYearTerm('tawasulSchoolYearTermIDList', $session->get('tawasulSchoolYearID'))->loadFromCSV($values);
            } else {
                $row = $form->addRow();
                    $row->addLabel('listingStart', __('Listing Start Date'))->description(__('Default: 2 weeks before the end of the current term.'));
                    $row->addDate('listingStart')
                        ->required()
                        ->setValue(Format::date($values['listingStart']));

                $row = $form->addRow();
                    $row->addLabel('listingEnd', __('Listing End Date'))->description(__('Default: 2 weeks after the start of next term.'));
                    $row->addDate('listingEnd')
                        ->required()
                        ->setValue(Format::date($values['listingEnd']));

                $row = $form->addRow();
                    $row->addLabel('programStart', __('Program Start Date'))->description(__('Default: first day of next term.'));
                    $row->addDate('programStart')
                        ->required()
                        ->setValue(Format::date($values['programStart']));

                $row = $form->addRow();
                    $row->addLabel('programEnd', __('Program End Date'))->description(__('Default: last day of the next term.'));
                    $row->addDate('programEnd')
                        ->required()
                        ->setValue(Format::date($values['programEnd']));
            }

            $row = $form->addRow();
                $row->addLabel('tawasulYearGroupIDList', __('Year Groups'));
                $row->addCheckboxYearGroup('tawasulYearGroupIDList')
                    ->addCheckAllNone()
                    ->loadFromCSV($values);

            $row = $form->addRow();
                $row->addLabel('maxParticipants', __('Max Participants'));
                $row->addNumber('maxParticipants')
                    ->required()
                    ->maxLength(4);

            $col = $form->addRow()->addColumn();
                $col->addLabel('description', __('Description'));
                $col->addEditor('description', $guid)
                    ->setRows(10)
                    ->showMedia();

            // PHOTOS
            $form->addRow()->addHeading(__('Photos'));

            $addBlockButton = $form->getFactory()->createButton(__('Add Photo'))->addClass('addBlock');

            $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
            $row = $blockTemplate->addRow()->addClass('w-full flex justify-between items-center mt-1 ml-2');
                $row->addFileUpload('fileUpload')->accepts('.jpg,.jpeg,.gif,.png')
                    ->setAttachment('filePath', $session->get('absoluteURL'), '')
                    ->setMaxUpload(false);
                $row->addTextField('caption')->setClass('w-4/5 ml-6 mr-6')->placeholder(__('Caption (optional)'));

            // Custom Blocks
            $row = $form->addRow();
            $customBlocks = $row->addCustomBlocks('photos', $session, true)
                ->fromTemplate($blockTemplate)
                ->settings(['inputNameStrategy' => 'object', 'addOnEvent' => 'click', 'sortable' => true, 'orderName' => 'photoOrder', 'uniqueID' => 'tawasulActivityPhotoID' ])
                ->placeholder(__('Photos will be listed here...'))
                ->addToolInput($addBlockButton);

            $photos = $container->get(ActivityPhotoGateway::class)->selectPhotosByActivity($tawasulActivityID);
            while ($photo = $photos->fetch()) {
                $customBlocks->addBlock($photo['tawasulActivityPhotoID'], [
                    'tawasulActivityPhotoID' => $photo['tawasulActivityPhotoID'],
                    'filePath'              => $photo['filePath'],
                    'caption'               => $photo['caption'],
                ]);
            }

            // COST
            $payment = $settingGateway->getSettingByScope('Activities', 'payment');
            if ($payment != 'None' && $payment != 'Single') {
                $form->addRow()->addHeading('Cost', __('Cost'));

                $row = $form->addRow();
                    $row->addLabel('payment', __('Cost'));
                    $row->addCurrency('payment')
                        ->required()
                        ->maxLength(9);

                $row = $form->addRow();
                    $row->addLabel('paymentType', __('Cost Type'));
                    $row->addSelect('paymentType')
                        ->required()
                        ->fromArray([
                            'Entire Programme' => __('Entire Programme'),
                            'Per Session'      => __('Per Session'),
                            'Per Week'         => __('Per Week'),
                            'Per Term'         => __('Per Term')
                        ]);

                $row = $form->addRow();
                    $row->addLabel('paymentFirmness', __('Cost Status'));
                    $row->addSelect('paymentFirmness')
                        ->required()
                        ->fromArray([
                            'Finalised' => __('Finalised'),
                            'Estimated' => __('Estimated')
                        ]);

                $row = $form->addRow();
                    $row->addLabel('paymentDescription', __('Payment Description'));
                    $row->addTextArea('paymentDescription')->setRows(2);
            }

            $form->addRow()->addHeading('Time Slots', __('Time Slots'));

            //Block template
            $sqlWeekdays = "SELECT tawasulDaysOfWeekID as value, name FROM tawasulDaysOfWeek ORDER BY sequenceNumber";

            $slotBlock = $form->getFactory()->createTable()->setClass('blank');
                $row = $slotBlock->addRow();
                    $row->addLabel('tawasulDaysOfWeekID', __('Slot Day'));
                    $row->addSelect('tawasulDaysOfWeekID')
                        ->fromQuery($pdo, $sqlWeekdays)
                        ->placeholder()
                        ->addClass('floatLeft');

                $row = $slotBlock->addRow();
                    $row->addLabel('timeStart', __('Slot Start Time'));
                    $row->addTime('timeStart');
                
                    $row->addLabel('timeEnd', __('Slot End Time'));
                    $row->addTime('timeEnd')
                        ->chainedTo('timeStart');

                $row = $slotBlock->addRow();
                    $row->addLabel('location', __('Location'));
                    $row->addRadio('location')
                        ->inline()
                        ->alignLeft()
                        ->fromArray([
                            'Internal' => __('Internal'),
                            'External' => __('External')
                        ]);

                $row = $slotBlock->addRow()->addClass('hideShow');
                    $row->addSelectSpace('tawasulSpaceID')
                        ->placeholder()
                        ->addClass('sm:max-w-full w-full');

                $row = $slotBlock->addRow()->addClass('showHide');
                    $row->addTextField("locationExternal")
                        ->maxLength(50)
                        ->addClass('sm:max-w-full w-full');

            //Tool Button
            $addBlockButton = $form->getFactory()
                ->createButton(__('Add Time Slot'))
                ->addClass('addBlock');

            //Custom Blocks
            $row = $form->addRow();
                $slotBlocks = $row->addCustomBlocks('timeSlots', $session)
                    ->fromTemplate($slotBlock)
                    ->settings([
                        'placeholder' => __('Time Slots will appear here...'),
                        'sortable' => true,
                        'uniqueID' => 'tawasulActivitySlotID',
                    ])
                    ->addToolInput($addBlockButton);

            $slotBlocks->addPredefinedBlock("Add Time Slot", ['location' => 'Internal']);
            $activitySlotGateway = $container->get(ActivitySlotGateway::class);
            $timeSlots = $activitySlotGateway->selectBy(['tawasulActivityID' => $tawasulActivityID]);

            foreach ($timeSlots as $slot) {
                $slot['location'] = empty($slot['tawasulSpaceID']) ? 'External' : 'Internal';
                $slotBlocks->addBlock($slot['tawasulActivitySlotID'], $slot);
            }

            $form->addRow()->addHeading('Current Staff', __('Current Staff'));

            $form->addRow()->addContent('<b>'.__('Warning').'</b>: '.__('If you delete a member of staff, any unsaved changes to this record will be lost!'))->wrap('<i>', '</i>');

            $staffTable = $form->addRow()->addDataTable('staffTable');

            $staffTable->addColumn('name', __('Name'))
                ->format(Format::using('name', ['', 'preferredName', 'surname', 'Staff', true, true]));

            $staffTable->addColumn('role', __('Role'))
                ->format(function($staff) {
                    return __($staff['role']);
                });

            $staffTable->addActionColumn()
                ->addParam('tawasulActivityStaffID')
                ->addParam('tawasulActivityID', $tawasulActivityID)
                ->addParam('search', $search)
                ->addParam('tawasulSchoolYearTermID', $tawasulSchoolYearTermID)
                ->format(function ($staff, $actions) {
                    $actions->addAction('delete', __('Delete'))
                            ->setURL('/modules/TawasulActivities/activities_manage_edit_staff_delete.php');
                });

            $activityStaffGateway = $container->get(ActivityStaffGateway::class);
            $staffTable->withData($activityStaffGateway->selectActivityStaff($tawasulActivityID)->toDataSet());

            $form->addRow()->addHeading('New Staff', __('New Staff'));

            $row = $form->addRow();
                $row->addLabel('staff', __('Staff'));
                $row->addSelectUsers('staff', $session->get('tawasulSchoolYearID'), ['includeStaff' => true])->selectMultiple();
            
            $row = $form->addRow();
                $row->addLabel('role', __('Role'));
                $row->addSelect('role')
                    ->fromArray([
                        'Organiser' => __('Organiser'),
                        'Coach'     => __('Coach'),
                        'Assistant' => __('Assistant'),
                        'Other'     => __('Other')
                    ]);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();
            ?>

            <script type="text/javascript">
                //All of this javascript is due to limitations of CustomBlocks. If these limitaions are fixed in the future, the corresponding block of code should be removed.
                var radio = 'input[type="radio"][name$="[location]"]';

                $(document).ready(function () {

                    $('input[id^=fileUpload][name^=photos]').each(function() {
                        var inputName = this.name.replace('fileUpload', 'filePath');
                        var filePath = $('input[name="'+inputName+'"]');
                        if (filePath != undefined) {
                            var img = document.createElement("img");
                            img.src = "<?php echo $session->get('absoluteURL'); ?>/"+filePath.val();
                            img.style.height = '100px';
                            img.style.maxWidth = '200px';

                            $(this).parent().append(img);

                            $('.input-box-meta', $(this).parent()).hide();
                            $(this).parent().parent().attr('title', '');
                            $(this).hide();
                        }
                    });
                });

                function locationSwap() {
                    var block = $(this).closest('tbody');
                    if ($(this).prop('id').startsWith('location0')) {
                        block.find('.showHide').hide();
                        block.find('.hideShow').show();
                    } else {
                        block.find('.showHide').show();
                        block.find('.hideShow').hide();
                    }
                }

                $(document).ready(function(){
                    //This is to ensure that loaded blocks have the correct state.
                    $(radio + ':checked').each(locationSwap);
                });

                //This supplements triggers for the Internal and External Locations
                $(document).on('change', radio, locationSwap);
            </script>

            <?php
        }
    }
}
