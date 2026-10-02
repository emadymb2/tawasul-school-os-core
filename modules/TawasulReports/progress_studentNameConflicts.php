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

use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Http\Url;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingValueGateway;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/progress_studentNameConflicts.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Student Name Conflicts'));

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
    $tawasulReportingCycleID = $_GET['tawasulReportingCycleID'] ?? '';
    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);
    $reportingValueGateway = $container->get(ReportingValueGateway::class);
    $reportingCycles = $reportingCycleGateway->selectReportingCyclesBySchoolYear($tawasulSchoolYearID)->fetchKeyPair();

    if (empty($reportingCycles)) {
        $page->addMessage(__('There are no active reporting cycles.'));
        return;
    }
    
    // FORM
    $form = Form::create('archiveByReport', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/TawasulReports/progress_studentNameConflicts.php');

    $row = $form->addRow();
        $row->addLabel('tawasulReportingCycleID', __('Reporting Cycle'));
        $row->addSelect('tawasulReportingCycleID')
            ->fromArray($reportingCycles)
            ->selected($tawasulReportingCycleID)
            ->placeholder();

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    if (empty($tawasulReportingCycleID)) return;
    
    // Get all student preferred names
    $names = $container->get(StudentGateway::class)->selectActiveStudentNames($session->get('tawasulSchoolYearID'))->fetchAll(\PDO::FETCH_COLUMN, 0);
    sort($names);
    $names = array_unique($names);

    // Get all student comments in the selected reporting cycle
    $reportingValues = $reportingValueGateway->selectReportingCommentsByCycle($tawasulReportingCycleID)->fetchAll();    
    $foundNames = [];

    // Check all comments for names that don't match this student's name
    foreach ($reportingValues as $values) {
        $matches = [];
        $key = $values['tawasulReportingValueID'];

        foreach ($names as $name) {
            if ($values['preferredName'] == $name) continue;

            // Check for the presence of other student names in the comment. Use word boundaries to avoid partial matches.
            if (preg_match('/\b'.$name.'\b/', $values['comment'], $matches)) {
                $foundNames[$key] = array_merge($foundNames[$key] ?? [], $values);
                $foundNames[$key]['tawasulPersonIDStudent'] = $values['tawasulPersonID'];
                $foundNames[$key]['foundNames'][] = $name;
            }
        }

        // Check for the absence of the student's own name in the comment. 
        if (preg_match('/\b'.$values['preferredName'].'\b/', $values['comment'], $matches) === 0 && (stripos($values['criteriaName'], 'Individual') !== false || $values['scopeType'] != 'Course')) {
            $foundNames[$key] = array_merge($foundNames[$key] ?? [], $values);
            $foundNames[$key]['tawasulPersonIDStudent'] = $values['tawasulPersonID'];
            $foundNames[$key]['missingName'] = $values['preferredName'];
        }

        // Check that the student pronouns match the student gender
        $pronounMismatch = null;
        $gender = $values['gender'];
        if ($gender == 'M') {
            $pronounMismatch = '/(\bshe\b.*)|(\bher\b.*)|(\bherself\b)/i';
        } elseif ($gender == 'F') {
            $pronounMismatch = '/(\bhe\b.*)|(\bhis\b.*)|(\bhim\b.*)|(\bhimself\b)/i';
        }

        if ($pronounMismatch && preg_match($pronounMismatch, $values['comment'], $matches)) {
            $foundNames[$key] = array_merge($foundNames[$key] ?? [], $values);
            $foundNames[$key]['tawasulPersonIDStudent'] = $values['tawasulPersonID'];
            $foundNames[$key]['pronounMismatch'] = $gender;
        }

        // Check for invalid characters
        if (preg_match('/ /', $values['comment'], $matches)) {
            $foundNames[$key] = array_merge($foundNames[$key] ?? [], $values);
            $foundNames[$key]['tawasulPersonIDStudent'] = $values['tawasulPersonID'];
            $foundNames[$key]['invalidCharacter'] = ' ';
        }
    }

    // DATA TABLE
    $table = DataTable::create('nameCheck');
    $table->setTitle(__('Student Name Conflicts'));
    $table->setDescription(__('This report checks all comments in a reporting cycle for any other student names that do not match the name of the student commented on.'));

    $table->addColumn('checked', __('Checked'))
        ->format(function ($values) use (&$form) {
            $checked = $values['checked'];
            $url = Url::fromModuleRoute('TawasulReports', 'progress_studentNameConflicts_ajax.php')
                ->withQueryParams(['tawasulReportingProgressID' => $values['tawasulReportingProgressID'], 'checked' => $checked == 'Y' ? 'N' : 'Y'])
                ->directLink();

            return $form->getFactory()
                ->createButton('')
                ->setIcon('solid', $checked == 'Y' ? 'check' : 'question-mark', $checked == 'Y' ? 'size-5 text-green-600' : 'size-5')
                ->setAttribute('hx-get', $url)
                ->setAttribute('hx-target', 'this')
                ->setAttribute('hx-push-url', 'false')
                ->setAttribute('hx-swap', 'outerHTML show:none swap:0s')
                ->getOutput();
        });

    $table->addColumn('name', __('Name'))
        ->format(Format::using('name', ['', 'preferredName', 'surname', 'Student', true, true]));
    
    $table->addColumn('scopeType', __('Scope Type'))
        ->format(function ($values) {
            return $values['scopeType'].'<br/>'.Format::small($values['criteriaName']);
        });

    $table->addColumn('found', __('Found Names'))
        ->format(function ($values) {
            if (!empty($values['foundNames']) && is_array($values['foundNames'])) {
                sort($values['foundNames']);
                $values['foundNames'] = array_unique($values['foundNames']);

                return Format::tag(implode(', ', $values['foundNames'] ?? []), 'warning');
            }

            if (!empty($values['missingName'])) {
                return Format::tag(__('Name not found'), 'error');
            }

            if (!empty($values['pronounMismatch'])) {
                return Format::tag(__('Check pronouns'), 'message');
            }

            if (!empty($values['invalidCharacter'])) {
                return Format::tag(__('Should not include').' "'.$values['invalidCharacter'].'"', 'message');
            }
        });

    if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/reporting_write_byStudent.php')) {
        $table->addActionColumn()
            ->addParam('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->addParam('tawasulReportingScopeID')
            ->addParam('tawasulPersonIDStudent')
            ->addParam('scopeType')
            ->addParam('scopeTypeID')
            ->format(function ($values, $actions) {
                $actions->addAction('view', __('View'))
                        ->setURL('/modules/TawasulReports/reporting_write_byStudent.php');
            });
    }

    echo $table->render($foundNames);
}
