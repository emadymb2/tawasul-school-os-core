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
use TawasulOS\Tables\DataTable;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/ttDates_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {

    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $page->breadcrumbs
        ->add(__('Tie Days to Dates'), 'ttDates.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Edit Days in Date'));

    //Check if tawasulSchoolYearID and dateStamp specified
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $dateStamp = $_GET['dateStamp'] ?? '';
    if ($tawasulSchoolYearID == '' or $dateStamp == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
        $data = array('date' => date('Y-m-d', $dateStamp));
        $sql = 'SELECT tawasulTTDay.tawasulTTDayID, tawasulTTDay.name AS dayName, tawasulTT.name AS ttName FROM tawasulTTDayDate JOIN tawasulTTDay ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) JOIN tawasulTT ON (tawasulTTDay.tawasulTTID=tawasulTT.tawasulTTID) WHERE date=:date';
        $result = $connection2->prepare($sql);
        $result->execute($data);

        $table = DataTable::create('ttDay');
        $table->setTitle(__('Edit Days in Date'));

        $table->addHeaderAction('add', __('Add'))
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('dateStamp', $dateStamp)
                ->displayLabel()
                ->setURL('/modules/' . $session->get('module') . '/ttDates_edit_add.php');

        $table->addColumn('ttName', __('Timetable'));
        $table->addColumn('dayName', __('Day'));
        $table->addActionColumn()
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('dateStamp', $dateStamp)
                ->addParam('tawasulTTDayID')
                ->format(function ($subcategory, $actions) use ($session) {
                    $actions->addAction('delete', __('Delete'))
                            ->setURL('/modules/' . $session->get('module') . '/ttDates_edit_delete.php');
                });

        echo $table->render($result->toDataSet());
    }
    
}
