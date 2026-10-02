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

namespace Tos\Module\TawasulReports\Forms;

use TawasulOS\Forms\Form;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Forms\DatabaseFormFactory;
use Tos\Module\TawasulReports\Domain\ReportingCycleGateway;
use Tos\Module\TawasulReports\Domain\ReportingCriteriaGateway;

/**
 * ReportingSidebarForm
 *
 * @version v19
 * @since   v19
 */
class ReportingSidebarForm extends Form
{
    protected $databaseFormFactory;
    protected $reportingCycleGateway;
    protected $reportingCriteriaGateway;
    protected $session;

    public function __construct(Session $session, ReportingCycleGateway $reportingCycleGateway, ReportingCriteriaGateway $reportingCriteriaGateway, DatabaseFormFactory $databaseFormFactory)
    {
        $this->session = $session;
        $this->databaseFormFactory = $databaseFormFactory;
        $this->reportingCycleGateway = $reportingCycleGateway;
        $this->reportingCriteriaGateway = $reportingCriteriaGateway;
    }

    public function createForm($urlParams)
    {
        $tawasulPersonID = $urlParams['tawasulPersonID'] ?? $this->session->get('tawasulPersonID');

        $form = parent::createBlank('reportingSelector', $this->session->get('absoluteURL').'/index.php', 'get')->enableQuickSubmit()->setAttribute('hx-trigger', 'change from:.auto-submit');
        $form->setFactory($this->databaseFormFactory);
        $form->setClass('w-full mt-2');

        $form->addHiddenValue('q', '/modules/TawasulReports/reporting_write.php');
        $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
        $form->addHiddenValue('allStudents', $urlParams['allStudents'] ?? '');
        $form->addHiddenValue('tawasulPersonIDStudent', $urlParams['tawasulPersonIDStudent'] ?? '');

        $row = $form->addRow()->addClass('py-1');
            $row->addLabel('tawasulSchoolYearID', __('School Year'))->addClass('sm:text-xs/6');
            $row->addSelectSchoolYear('tawasulSchoolYearID', 'Recent')
                ->setClass('auto-submit flex-grow')
                ->selected($urlParams['tawasulSchoolYearID'])
                ->placeholder(null);

        $reportingCycles = $this->reportingCycleGateway->selectReportingCyclesBySchoolYear($urlParams['tawasulSchoolYearID']);
        $row = $form->addRow()->addClass('py-1');
            $row->addLabel('tawasulReportingCycleID', __('Reporting Cycle'))->addClass('sm:text-xs/6');
            $row->addSelect('tawasulReportingCycleID')
                ->fromResults($reportingCycles)
                ->setClass('auto-submit flex-grow')
                ->selected($urlParams['tawasulReportingCycleID'])
                ->placeholder();

        if (!empty($urlParams['tawasulReportingCycleID'])) {
            $criteria = $this->reportingCriteriaGateway->newQueryCriteria()->sortBy(['sequenceNumber', 'nameOrder']);
            $criteriaGroups = $this->reportingCriteriaGateway->queryReportingCriteriaGroupsByCycle($criteria, $urlParams['tawasulReportingCycleID']);

            $row = $form->addRow()->addClass('py-1');
                $row->addLabel('criteriaSelector', __('Scope'))->addClass('sm:text-xs/6');
                $row->addSelect('criteriaSelector')
                    ->fromDataSet($criteriaGroups, 'value', 'name', 'scopeName')
                    ->setClass('auto-submit flex-grow')
                    ->selected($urlParams['tawasulReportingScopeID'].'-'.$urlParams['scopeTypeID'])
                    ->placeholder();
        } else {
            $form->addHiddenValue('criteriaSelector', '0-0');
        }

        return $form;
        
    }
}
