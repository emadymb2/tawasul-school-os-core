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
use TawasulOS\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/student_view_details_notes_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $allStudents = $_GET['allStudents'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? '';
    $category = $_GET['category'] ?? '';

    $enableStudentNotes = $container->get(SettingGateway::class)->getSettingByScope('Students', 'enableStudentNotes');
    if ($enableStudentNotes != 'Y') {
        $page->addError(__('You do not have access to this action.'));
    } else {
        $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
        $subpage = $_GET['subpage'] ?? '';
        if ($tawasulPersonID == '' or $subpage == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            
                $data = array('tawasulPersonID' => $tawasulPersonID);
                $sql = 'SELECT * FROM tawasulPerson WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            if ($result->rowCount() != 1) {
                $page->addError(__('The selected record does not exist, or you do not have access to it.'));
            } else {
                $student = $result->fetch();

                //Proceed!
                $page->breadcrumbs
                    ->add(__('View Student Profiles'), 'student_view.php')
                    ->add(Format::name('', $student['preferredName'], $student['surname'], 'Student'), 'student_view_details.php', ['tawasulPersonID' => $tawasulPersonID, 'subpage' => $subpage, 'allStudents' => $allStudents])
                    ->add(__('Add Student Note'));
				
				$form = Form::create('notes', $session->get('absoluteURL').'/modules/'.$session->get('module')."/student_view_details_notes_addProcess.php?tawasulPersonID=$tawasulPersonID&search=".$search."&subpage=$subpage&category=".$category."&allStudents=$allStudents");

				$form->addHiddenValue('address', $session->get('address'));
				
				if ($search != '') {
                    $params = [
                        "search" => $search,
                        "tawasulPersonID" => $tawasulPersonID,
                        "subpage" => $subpage,
                        "category" => $category,
                        "allStudents" => $allStudents,
                    ];
                    $form->addHeaderAction('back', __('Back'))
                        ->setURL('/modules/TawasulStudents/student_view_details.php')
                        ->addParams($params);
				}

				$row = $form->addRow();
					$row->addLabel('title', __('Title'));
					$row->addTextField('title')->required()->maxLength(100);

				$sql = "SELECT tawasulStudentNoteCategoryID as value, name FROM tawasulStudentNoteCategory WHERE active='Y' ORDER BY name";
				$row = $form->addRow();
					$row->addLabel('tawasulStudentNoteCategoryID', __('Category'));
					$row->addSelect('tawasulStudentNoteCategoryID')->fromQuery($pdo, $sql)->required()->placeholder();

				$row = $form->addRow();
					$column = $row->addColumn();
					$column->addLabel('note', __('Note'));
					$column->addEditor('note', $guid)->required()->setRows(25)->showMedia();
								
				$row = $form->addRow();
					$row->addFooter();
					$row->addSubmit();
				
				echo $form->getOutput();
				?>

				<script type="text/javascript">
				$("#tawasulStudentNoteCategoryID").change(function() {
					if ($("#tawasulStudentNoteCategoryID").val() != "Please select...") {
						$.get('<?php echo $session->get('absoluteURL').'/modules/TawasulStudents/student_view_details_notes_addAjax.php?tawasulStudentNoteCategoryID=' ?>' + $("#tawasulStudentNoteCategoryID").val(), function(data){
							if (tinyMCE.activeEditor==null) {
								if ($("textarea#note").val()=="") {
									$("textarea#note").val(data) ;
								}
							} else {
								if (tinyMCE.get('note').getContent()=="") {
									tinyMCE.get('note').setContent(data) ;
								}
							}
						});
					
					}
				});
				</script>

				<?php
            }
        }
    }
}
