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

use TawasulOS\Domain\FormalAssessment\ExternalAssessmentFieldGateway;
use TawasulOS\Domain\FormalAssessment\ExternalAssessmentStudentEntryGateway;
use TawasulOS\Domain\FormalAssessment\ExternalAssessmentStudentGateway;
use TawasulOS\Domain\School\ExternalAssessmentGateway;
use TawasulOS\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_manage_details_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulExternalAssessmentStudentID = $_GET['tawasulExternalAssessmentStudentID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $search = $_GET['search'] ?? '';
    $allStudents = $_GET['allStudents'] ?? '';

    $page->breadcrumbs
        ->add(__('View All Assessments'), 'externalAssessment.php')
        ->add(__('Student Details'), 'externalAssessment_details.php', ['tawasulPersonID' => $tawasulPersonID])
        ->add(__('Edit Assessment'));

    //Check if tawasulExternalAssessmentStudentID and tawasulPersonID specified
    if ($tawasulExternalAssessmentStudentID == '' or $tawasulPersonID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
            $result = $container->get(ExternalAssessmentStudentGateway::class)->getStudentExternalAssessmentDetails( $tawasulExternalAssessmentStudentID);

        if (empty($result)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result;

            if ($search != '') {
                 $params = [
                    "tawasulPersonID" => $tawasulPersonID,
                    "search" => $search,
                    "allStudents" => $allStudents
                ];
                $page->navigator->addHeaderAction('back', __('Back'))
                    ->setURL('/modules/TawasulFormalAssessment/externalAssessment_details.php')
                    ->addParams($params);
            }

            //Check for all fields

                $resultCheck = $container->get(ExternalAssessmentFieldGateway::class)->selectBy(['tawasulExternalAssessmentID' => $values['tawasulExternalAssessmentID']]);

            while ($rowCheck = $resultCheck->fetch()) {

                    $resultCheck2 = $container->get(ExternalAssessmentStudentEntryGateway::class)->selectBy(['tawasulExternalAssessmentFieldID' => $rowCheck['tawasulExternalAssessmentFieldID'], 'tawasulExternalAssessmentStudentID' => $values['tawasulExternalAssessmentStudentID']]);

                if ($resultCheck2->rowCount() < 1) {

                        $dataCheck3 = array('tawasulExternalAssessmentStudentID' => $values['tawasulExternalAssessmentStudentID'], 'tawasulExternalAssessmentFieldID' => $rowCheck['tawasulExternalAssessmentFieldID']);
                        $sqlCheck3 = 'INSERT INTO tawasulExternalAssessmentStudentEntry SET tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID, tawasulExternalAssessmentFieldID=:tawasulExternalAssessmentFieldID';
                        $resultCheck3 = $connection2->prepare($sqlCheck3);
                        $resultCheck3->execute($dataCheck3);
                }
            }

            $form = Form::create('editAssessment', $session->get('absoluteURL').'/modules/'.$session->get('module').'/externalAssessment_manage_details_editProcess.php?search='.$search.'&allStudents='.$allStudents);

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
            $form->addHiddenValue('tawasulExternalAssessmentStudentID', $tawasulExternalAssessmentStudentID);

            $row = $form->addRow();
                $row->addLabel('name', __('Assessment Type'));
                $row->addTextField('name')->required()->readOnly()->setValue(__($values['assessment']));

            $row = $form->addRow();
                $row->addLabel('date', __('Date'));
                $row->addDate('date')->required()->loadFrom($values);

            if ($values['allowFileUpload'] == 'Y') {
                $row = $form->addRow();
                $row->addLabel('file', __('Upload File'))->description(__('Use this to attach raw data, graphical summary, etc.'));
                $row->addFileUpload('file')->setAttachment('attachment', $session->get('absoluteURL'), $values['attachment']);
            }
            
                $resultField = $container->get(ExternalAssessmentFieldGateway::class)-> selectFieldsByExternalAssessmentAndStudent($values['tawasulExternalAssessmentID'], $tawasulExternalAssessmentStudentID);

            if ($resultField->rowCount() <= 0) {
                $form->addRow()->addAlert(__('There are no fields in this assessment.'), 'warning');
            } else {
                $fieldGroup = $resultField->fetchAll(\PDO::FETCH_GROUP);
                $count = 0;

                foreach ($fieldGroup as $category => $fields) {
                    $categoryName = (strpos($category, '_') !== false)? substr($category, (strpos($category, '_') + 1)) : $category;

                    $row = $form->addRow();
                        $row->addHeading($categoryName);
                        $row->addContent(__('Grade'))->wrap('<b>', '</b>')->setClass('right');

                    foreach ($fields as $field) {
                        $form->addHiddenValue($count.'-tawasulExternalAssessmentStudentEntryID', $field['tawasulExternalAssessmentStudentEntryID']);
                        $gradeScale = renderGradeScaleSelect($connection2, $guid, $field['tawasulScaleID'], $count.'-tawasulScaleGradeID', 'id', false, '150', 'id', $field['tawasulScaleGradeID']);

                        $row = $form->addRow();
                            $row->addLabel($count.'-tawasulScaleGradeID', $field['name'])->setTitle($field['usage']);
                            $row->addContent($gradeScale);

                        $count++;
                    }
                }

                $form->addHiddenValue('count', $count);
            }

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
