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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/ttDates.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Tie Days to Dates'));

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');

    if ($tawasulSchoolYearID != '') {
        $page->navigator->addSchoolYearNavigation($tawasulSchoolYearID);

        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = 'SELECT * FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
        $result = $connection2->prepare($sql);
        $result->execute($data);

        if ($result->rowCount() < 1) {
            echo $page->getBlankSlate();
        } else {

            $page->addData('preventOverflow', true);

            $form = Form::createTable('ttDates', $session->get('absoluteURL').'/modules/'.$session->get('module').'/ttDates_addMultiProcess.php?tawasulSchoolYearID='.$tawasulSchoolYearID);
            $form->setClass('w-full blank');

            $form->addHiddenValue('q', $session->get('address'));

            while ($values = $result->fetch()) {
                $row = $form->addRow()->addHeading($values['name']);

                list($firstDayYear, $firstDayMonth, $firstDayDay) = explode('-', $values['firstDay']);
                $firstDayStamp = mktime(0, 0, 0, $firstDayMonth, $firstDayDay, $firstDayYear);
                list($lastDayYear, $lastDayMonth, $lastDayDay) = explode('-', $values['lastDay']);
                $lastDayStamp = mktime(0, 0, 0, $lastDayMonth, $lastDayDay, $lastDayYear);

                //Count back to first Monday before first day
                $startDayStamp = $firstDayStamp;
                while (date('D', $startDayStamp) != 'Mon') {
					$startDayStamp = strtotime('-1 day', $startDayStamp);
                }

                //Count forward to first Sunday after last day
                $endDayStamp = $lastDayStamp;
                while (date('D', $endDayStamp) != 'Sun') {
					$endDayStamp = strtotime('+1 day', $endDayStamp);
                }

                //Get the special days
                $dataSpecial = array('tawasulSchoolYearTermID' => $values['tawasulSchoolYearTermID']);
                $sqlSpecial = 'SELECT date, type, name FROM tawasulSchoolYearSpecialDay WHERE tawasulSchoolYearTermID=:tawasulSchoolYearTermID ORDER BY date';
                $resultSpecial = $connection2->prepare($sqlSpecial);
                $resultSpecial->execute($dataSpecial);

                $specialDays = $resultSpecial->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);

                // Get the TT day names
                $dataDay = array();
                $sqlDay = 'SELECT date, tawasulTTDay.nameShort AS dayName, tawasulTT.nameShort AS ttName, color, fontColor FROM tawasulTTDayDate JOIN tawasulTTDay ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) JOIN tawasulTT ON (tawasulTTDay.tawasulTTID=tawasulTT.tawasulTTID)';
                $resultDay = $connection2->prepare($sqlDay);
                $resultDay->execute($dataDay);

                $ttDays = $resultDay->fetchAll(\PDO::FETCH_GROUP);

				//Check which days are school days
                $dataDays = array();
                $sqlDays = "SELECT nameShort, schoolDay FROM tawasulDaysOfWeek";
                $resultDays = $connection2->prepare($sqlDays);
                $resultDays->execute($dataDays);

                $days = $resultDays->fetchAll(\PDO::FETCH_KEY_PAIR);

                $count = 1;

                $table = $form->addRow()->addTable()->setClass('w-full');
                $row = $table->addHeaderRow();

                for ($i = 1; $i < 8; ++$i) {
                    $dowLong = date('l', strtotime("Sunday +$i days"));
                    $dowShort = date('D', strtotime("Sunday +$i days"));

                    $script = '<script type="text/javascript">';
                    $script .= 'htmx.onLoad(function (content) {';
                    $script .= "$(document).on('click', '#checkall".$dowShort.$values['nameShort']."', function () {";
                    $script .= "$('.".$dowShort.$values['nameShort']." :checkbox').attr('checked', this.checked);";
                    $script .= '});';
                    $script .= '});';
                    $script .= '</script>';

                    // $column = $row->addColumn();
                    // $column->addContent()->addClass('textCenter');
                    $row->addCheckbox('checkall'.$dowShort.$values['nameShort'])->prepend(__($dowLong).'<br/>')->append($script)->alignCenter()->addClass('sticky top-0 z-10');
                }

                for ($i = $startDayStamp; $i <= $endDayStamp;$i = strtotime('+1 day', $i)) {
                    $date = date('Y-m-d', $i);
                    $dayOfWeek = date('D', $i);
                    $formattedDate = date($session->get('i18n')['dateFormatPHP'], $i);

                    if ($dayOfWeek == 'Mon') {
                        $row = $table->addRow();
                    }

                    if ($i < $firstDayStamp or $i > $lastDayStamp or $days[$dayOfWeek] == 'N') {
                        $row->addContent('')->addClass('ttDates textCenter');
                    } else {
                        if (isset($specialDays[$date]) and $specialDays[$date]['type'] == 'School Closure') {
                            $row->addContent($formattedDate)
                                ->append('<br/>')
                                ->append($specialDays[$date]['name'])
                                ->addClass('ttDates textCenter dull');
                        } else {
                            $column = $row->addColumn()->addClass('ttDates textCenter');
                            $column->addContent($formattedDate)->append('<br/>');
                            if (isset($specialDays[$date]) and $specialDays[$date]['type'] == 'Timing Change') {
                                $column->addContent(__('Timing Change'))->wrap('<span style="color: #f00" title="'.$specialDays[$date]['name'].'">', '</span>');
                            } else {
                                $column->addContent(__('School Day'));
                            }

                            $column->addCheckbox('dates[]')->setValue($i)->setClass($dayOfWeek.$values['nameShort'])->alignCenter();

                            $column->addContent("<br/><a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/ttDates_edit.php&tawasulSchoolYearID=$tawasulSchoolYearID&dateStamp=".$i."'>".icon('solid', 'edit', 'size-6 mb-2 text-gray-600 hover:text-blue-500')."</a><br/>");

                            if (isset($ttDays[$date])) {
                                foreach ($ttDays[$date] as $day) {
                                    if (empty($day['color'])) {
                                        $column->addContent($day['ttName'].' '.$day['dayName'])->wrap('<b>', '</b>');
                                    }
                                    else {
                                        $column->addContent($day['ttName'].' '.$day['dayName'])->wrap('<div class=\'h-8\'style=\'background-color: '.$day['color'].'; color: '.$day['fontColor'].'\'><b>', '</b></div>');
                                    }

                                }
                                $column->addClass('success');
                            }
                        }
                    }
                    ++$count;
                }
            }

            $form->addRow()->addHeading('Multi Add', __('Multi Add'));

            $data= array('tawasulSchoolYearID' => $tawasulSchoolYearID);
            $sql = "SELECT tawasulTTDay.tawasulTTDayID as value, CONCAT(tawasulTT.name, ': ', tawasulTTDay.nameShort) as name
                    FROM tawasulTTDay
                    JOIN tawasulTT ON (tawasulTTDay.tawasulTTID=tawasulTT.tawasulTTID)
                    WHERE tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY tawasulTT.name, tawasulTTDay.name";

            $table = $form->addRow()->addTable()->setClass('w-full smallIntBorder');
            $row = $table->addRow();
                $row->addLabel('tawasulTTDayID', __('Day'));
                $row->addSearchSelect('tawasulTTDayID')->fromQuery($pdo, $sql, $data)->addClass('mediumWidth');

            $row = $table->addRow();
                $row->addLabel('overwrite', __('Overwrite'))->description(__('Should existing timetable days be replaced by the new ones?'));
                $row->addCheckbox('overwrite')->setValue('Y')->checked('N');

            $row = $table->addRow()->addClass('right');
                $row->addContent();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
