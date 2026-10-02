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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use Tos\Module\TawasulAttendance\AttendanceView;
use TawasulOS\Services\Format;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/src/AttendanceView.php';

$urlParams = ['tawasulPersonID' => $_GET['tawasulPersonID'], 'currentDate' => $_GET['currentDate']];

$page->breadcrumbs
	->add(__('Take Attendance by Person'), 'attendance_take_byPerson.php', $urlParams)
	->add(__('Edit Attendance by Person'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_byPerson_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {

	$tawasulAttendanceLogPersonID = $_GET['tawasulAttendanceLogPersonID'] ?? '';
	$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';

	if ( empty($tawasulAttendanceLogPersonID) || empty($tawasulPersonID) ) {
		$page->addError(__('You have not specified one or more required parameters.'));
	} else {
	    //Proceed!
	    $page->return->addReturns(['error3' => __('Your request failed because the specified date is in the future, or is not a school day.')]);

	    $attendance = new AttendanceView($tawasul, $pdo, $container->get(SettingGateway::class));


			$dataPerson = array('tawasulPersonID' => $tawasulPersonID, 'tawasulAttendanceLogPersonID' => $tawasulAttendanceLogPersonID );
			$sqlPerson = "SELECT p.preferredName, p.surname, type, reason, comment, date, context, timestampTaken, tawasulAttendanceLogPerson.tawasulCourseClassID, t.preferredName as teacherPreferredName, t.surname as teacherSurname, tawasulCourseClass.nameShort as className, tawasulCourse.nameShort as courseName FROM tawasulAttendanceLogPerson JOIN tawasulPerson p ON (tawasulAttendanceLogPerson.tawasulPersonID=p.tawasulPersonID) JOIN tawasulPerson t ON (tawasulAttendanceLogPerson.tawasulPersonIDTaker=t.tawasulPersonID) LEFT JOIN tawasulCourseClass ON (tawasulAttendanceLogPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) LEFT JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID AND tawasulAttendanceLogPersonID=:tawasulAttendanceLogPersonID ";
			$resultPerson = $connection2->prepare($sqlPerson);
			$resultPerson->execute($dataPerson);

	    if ($resultPerson->rowCount() != 1) {
	    	$page->addError(__('The specified record does not exist.'));
	    } else {
            $values = $resultPerson->fetch();
            $currentDate = Format::date($values['date']);

			$form = Form::create('attendanceEdit', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/attendance_take_byPerson_editProcess.php');
			$form->setAutocomplete('off');

			$form->addHiddenValue('address', $session->get('address'));
			$form->addHiddenValue('tawasulAttendanceLogPersonID', $tawasulAttendanceLogPersonID);
			$form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
			$form->addHiddenValue('currentDate', $currentDate);

			$form->addRow()->addHeading('Edit Attendance', __('Edit Attendance'));

			$row = $form->addRow();
				$row->addLabel('student', __('Student'));
				$row->addTextField('student')->readonly()->setValue(Format::name('', htmlPrep($values['preferredName']), htmlPrep($values['surname']), 'Student', true));

			$row = $form->addRow();
				$row->addLabel('date', __('Date'));
				$row->addDate('date')->readonly()->setValue($currentDate);

			$row = $form->addRow();
				$row->addLabel('recordedBy', __('Recorded By'));
				$row->addTextField('recordedBy')->readonly()->setValue(Format::name('', htmlPrep($values['teacherPreferredName']), htmlPrep($values['teacherSurname']), 'Staff', false, true));

			$row = $form->addRow();
				$row->addLabel('time', __('Time'));
				$row->addTextField('time')->readonly()->setValue(substr($values['timestampTaken'], 11) . ' ' . Format::date(substr($values['timestampTaken'], 0, 10)));

			$row = $form->addRow();
				$row->addLabel('where', __('Where'));
				$row->addTextField('where')->readonly()->setValue(__($values['context']));

            $restricted = $attendance->isTypeRestricted($values['type']);
			$row = $form->addRow();
				$row->addLabel('type', __('Type'));
				$row->addSelect('type')
                    ->fromArray($attendance->getAttendanceTypes($restricted))
                    ->readOnly($restricted);

			$row = $form->addRow();
				$row->addLabel('reason', __('Reason'));
				$row->addSelect('reason')->fromArray($attendance->getAttendanceReasons());

			$row = $form->addRow();
				$row->addLabel('comment', __('Comment'))->description(__('255 character limit'));
				$row->addTextArea('comment')->setRows(3)->maxLength(255);

			$row = $form->addRow();
				$row->addFooter();
				$row->addSubmit();

			$form->loadAllValuesFrom($values);

			echo $form->getOutput();

	    }
	}
}
