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
use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\DataSet;
use Tos\Module\TawasulReports\Domain\ReportingProgressGateway;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/progress_byReportingCycle.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Progress by Reporting Cycle'));

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
    $tawasulReportingCycleID = $_GET['tawasulReportingCycleID'] ?? '';
    $reportingProgressGateway = $container->get(ReportingProgressGateway::class);
    $reportingCycleGateway = $container->get(ReportingCycleGateway::class);
    $reportingCycles = $reportingCycleGateway->selectReportingCyclesBySchoolYear($tawasulSchoolYearID)->fetchKeyPair();

    if (empty($reportingCycles)) {
        $page->addMessage(__('There are no active reporting cycles.'));
        return;
    }
    
    // FORM
    $form = Form::create('archiveByReport', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/TawasulReports/progress_byReportingCycle.php');

    $row = $form->addRow();
        $row->addLabel('tawasulReportingCycleID', __('Reporting Cycle'));
        $row->addSelect('tawasulReportingCycleID')
            ->fromArray($reportingCycles)
            ->selected($tawasulReportingCycleID)
            ->placeholder();

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // QUERY
    if (!empty($tawasulReportingCycleID)) {
        $reportingCycles = new DataSet([0 => $reportingCycleGateway->getByID($tawasulReportingCycleID)]);
    } else {
        $criteria = $reportingCycleGateway->newQueryCriteria()->sortBy('sequenceNumber');
        $reportingCycles = $reportingCycleGateway->queryReportingCyclesBySchoolYear($criteria, $tawasulSchoolYearID, true);
    }

    if ($reportingCycles->getResultCount() == 0) {
        $reportingCycles = new DataSet([0 => ['name' => __('View'), 'tawasulReportingCycleID' => 0]]);
    }

    foreach ($reportingCycles as $cycle) {
        $criteria = $reportingProgressGateway->newQueryCriteria()->sortBy('sequenceNumber');
        $progress = $reportingProgressGateway->queryReportingProgressByCycle($criteria, $cycle['tawasulReportingCycleID']);

        // DATA TABLE
        $table = DataTable::create('progress');
        $table->setTitle($cycle['name']);

        $table->addColumn('name', __('Name'))->width('30%');
        $table->addColumn('progress', __('Progress'))
            ->width('40%')
            ->format(function ($reporting) use (&$page) {
                return $page->fetchFromTemplate('ui/writingProgress.twig.html', [
                    'progressCount' => $reporting['progressCount'],
                    'totalCount'    => $reporting['totalCount'],
                    'width'         => 'w-64',
                ]);
            });


        $table->addActionColumn()
            ->addParam('tawasulReportingCycleID', $cycle['tawasulReportingCycleID'])
            ->addParam('tawasulReportingScopeID')
            ->format(function ($reportingCycle, $actions) {
                $actions->addAction('view', __('View'))
                        ->setURL('/modules/TawasulReports/progress_byPerson.php');
            });

        echo $table->render($progress);
    }
}
