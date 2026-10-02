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
use TawasulOS\Domain\Timetable\TimetableGateway;
use TawasulOS\Domain\Timetable\TimetableDayGateway;
use TawasulOS\Domain\Timetable\TimetableColumnGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_byClass.php') == false) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
	$settingGateway = $container->get(SettingGateway::class);
    $timetableGateway = $container->get(TimetableGateway::class);
    $timetableDayGateway = $container->get(TimetableDayGateway::class);
    $timetableColumnGateway = $container->get(TimetableColumnGateway::class);

    $tawasulCourseClassID = $_REQUEST['tawasulCourseClassID'] ?? '';
    $tawasulTTID = $_REQUEST['tawasulTTID'] ?? '';

    $timetable = $timetableGateway->getByID($tawasulTTID);
    if (empty($timetable)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage Timetables'), 'tt.php', ['tawasulSchoolYearID' => $timetable['tawasulSchoolYearID']])
        ->add(__('Edit Timetable'), 'tt_edit.php', ['tawasulSchoolYearID' => $timetable['tawasulSchoolYearID'], 'tawasulTTID' => $tawasulTTID])
        ->add(__('Edit Timetable by Class'));
    
    // SELECT TIMETABLE & CLASS
    $form = Form::create('timetableByClass', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/TawasulTimetableAdmin/tt_edit_byClass.php');
    $form->addHiddenValue('tawasulSchoolYearID', $timetable['tawasulSchoolYearID']);
    $form->addHiddenValue('tawasulTTID', $tawasulTTID);

    $classResults = $timetableGateway->selectClassesByTimetable($tawasulTTID);

    $row = $form->addRow();
        $row->addLabel('tawasulCourseClassID', __('Class'));
        $row->addSelect('tawasulCourseClassID')
            ->fromResults($classResults)
            ->required()
            ->placeholder()
            ->selected($tawasulCourseClassID);

    $row = $form->addRow();
        $row->addSubmit('Next');

    echo $form->getOutput();

    if (!empty($tawasulCourseClassID)) {
        $form = Form::create('ttAdd', $session->get('absoluteURL').'/modules/TawasulTimetableAdmin/tt_edit_byClassProcess.php');
        $form->setFactory(DatabaseFormFactory::create($pdo));

        $form->setDescription(Format::alert(__('This is an administrative tool to assist with timetable changes. When changing facilities, it <b>does not</b> prevent timetabling into a facility that is already in use. Be sure to check availability before making such changes.'), 'message'));

        $form->addHiddenValue('tawasulSchoolYearID', $timetable['tawasulSchoolYearID']);
        $form->addHiddenValue('tawasulTTID', $tawasulTTID);
        $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);

        $form->addHiddenValue('address', $session->get('address'));

        $dayResults = $timetableDayGateway->selectTTDaysByTimetable($tawasulTTID);
        $columnResults = $timetableColumnGateway->selectTTColumnsByTimetable($tawasulTTID);

        $columnRows = ($columnResults->rowCount() > 0)? $columnResults->fetchAll() : array();
        $columnRowsChained = array_combine(array_column($columnRows, 'value'), array_column($columnRows, 'tawasulTTDayID'));
        $columnRowsOptions = array_combine(array_column($columnRows, 'value'), array_column($columnRows, 'name'));

        $tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
        $tawasulTTColumnRowID = $_GET['tawasulTTColumnRowID'] ?? '';
        $tawasulTTSpaceID = $_GET['tawasulTTSpaceID'] ?? '';

        $ttBlock = $form->getFactory()->createTable()->setClass('blank');
            $row = $ttBlock->addRow();
                $row->addLabel('tawasulTTDayID', __('Day'))->addClass('ml-4');
                $row->addSelect('tawasulTTDayID')
                    ->fromResults($dayResults)
                    ->required()
                    ->selected($tawasulTTDayID)
                    ->addClass('float-left');

                $row->addLabel('tawasulTTColumnRowID', __('Period'))->addClass('ml-4');
                $row->addSelect('tawasulTTColumnRowID')
                    ->fromArray($columnRowsOptions)
                    ->required()
                    ->chainedTo('', $columnRowsChained)
                    ->selected($tawasulTTColumnRowID)
                    ->addClass('chainTo float-left');

            // $row = $ttBlock->addRow();
                $row->addLabel('tawasulTTSpaceID', __('Facility'))->addClass('ml-4');
                $row->addSelectSpace('tawasulTTSpaceID')->selected($tawasulTTSpaceID)->addClass('float-left');

        $addTTButton = $form->getFactory()->createButton(__('Add Timetable Entry'))->addClass('addBlock');

        $row = $form->addRow();
            $ttBlocks = $row->addCustomBlocks('ttBlocks', $session)
                ->fromTemplate($ttBlock)
                ->settings([
                    'placeholder' => __('Timetable Entries will appear here.'),
                    'uniqueID'    => 'tawasulTTDayRowClassID',
                ])
                ->addToolInput($addTTButton);

        $ttResults = $timetableDayGateway->selectTTDayRowClassesByClass($tawasulTTID, $tawasulCourseClassID);

        while ($ttDay = $ttResults->fetch()) {
            $ttDay['tawasulTTColumnRowID'] .= '-' . $ttDay['tawasulTTDayID'];
            $ttDay['tawasulTTSpaceID'] = $ttDay['tawasulSpaceID'];
            $ttDay['primaryInput'] = $ttDay['dayName'].' - '.$ttDay['periodName'];
            $ttBlocks->addBlock($ttDay['tawasulTTDayRowClassID'], $ttDay);
        }

        $row = $form->addRow();
            $row->addSubmit(__('Submit'));

        echo $form->getOutput();

    }
}

?>
 <script>
    function chainSelects() {
            $('div.blocks').find('select.chainTo').each(function () {
            var index = $(this).attr('id').replace('tawasulTTColumnRowID' ,'');
            $(this).removeClass('chainTo').chainedTo('#tawasulTTDayID' + index);
        });
    }

    $(document).ready(chainSelects);

    $(document).on('click', '.addBlock', chainSelects);
</script>
