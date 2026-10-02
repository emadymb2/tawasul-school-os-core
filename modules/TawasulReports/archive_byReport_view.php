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
use Tos\Module\TawasulReports\Domain\ReportGateway;
use Tos\Module\TawasulReports\Domain\ReportArchiveEntryGateway;
use TawasulOS\Tables\DataTable;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_byReport_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('View by Report'), 'archive_byReport.php')
        ->add(__('View Reports'));

    $reportGateway = $container->get(ReportGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);

    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $tawasulReportID = $_GET['tawasulReportID'] ?? '';
    $reportIdentifier = $_GET['reportIdentifier'] ?? '';
    $tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
    $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';

    if (empty($tawasulReportID) && empty($reportIdentifier)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // FORM
    $form = Form::create('archiveByReport', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder w-full');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/TawasulReports/archive_byReport_view.php');
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
    $form->addHiddenValue('tawasulReportID', $tawasulReportID);
    $form->addHiddenValue('reportIdentifier', $reportIdentifier);

    $reportsBySchoolYear = $reportGateway->selectActiveReportsBySchoolYear($tawasulSchoolYearID)->fetchKeyPair();
    $row = $form->addRow();
        $row->addLabel('tawasulReportID', __('Report'));
        $row->addSelect('tawasulReportID')->fromArray($reportsBySchoolYear)->required()->placeholder()->selected($tawasulReportID);

    $row = $form->addRow();
        $row->addLabel('tawasulYearGroupID', __('Year Group'));
        $row->addSelectYearGroup('tawasulYearGroupID')->placeholder()->selected($tawasulYearGroupID);

    $row = $form->addRow();
        $row->addLabel('tawasulFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('tawasulFormGroupID', $tawasulSchoolYearID)->selected($tawasulFormGroupID)->placeholder();

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    $canViewDraftReports = isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_byReport.php', 'View Draft Reports');
    $canViewPastReports = isActionAccessible($guid, $connection2, '/modules/TawasulReports/archive_byReport.php', 'View Past Reports');
    $roleCategory = $session->get('tawasulRoleIDCurrentCategory');

    $criteria = $reportGateway->newQueryCriteria(true)
        ->sortBy($tawasulFormGroupID ? ['surname', 'preferredName'] : ['sequenceNumber', 'name'])
        ->fromPOST();

    // QUERY
    if (empty($tawasulReportID) && !empty($reportIdentifier)) {
        $reports = $reportArchiveEntryGateway->queryArchiveByReportIdentifier($criteria, $tawasulSchoolYearID, $reportIdentifier, $tawasulYearGroupID, $tawasulFormGroupID, $roleCategory, $canViewDraftReports, $canViewPastReports);

        $reports->transform(function (&$report) use ($roleCategory, $tawasulSchoolYearID, $canViewDraftReports, $canViewPastReports, &$reportArchiveEntryGateway) {
            $report['archive'] = $reportArchiveEntryGateway->getRecentArchiveEntryByReportIdentifier($tawasulSchoolYearID, $report['reportIdentifier'], 'Single', $report['tawasulPersonID'] ?? '', $roleCategory, $canViewDraftReports, $canViewPastReports);
        });
    } elseif (!empty($tawasulFormGroupID)) {
        $reports = $reportArchiveEntryGateway->queryArchiveByReport($criteria, !empty($tawasulReportID) ? $tawasulReportID : $reportIdentifier, $tawasulYearGroupID, $tawasulFormGroupID, $roleCategory, $canViewDraftReports, $canViewPastReports);

        $reports->transform(function (&$report) use ($roleCategory, $canViewDraftReports, $canViewPastReports, &$reportArchiveEntryGateway) {
            $report['archive'] = $reportArchiveEntryGateway->getRecentArchiveEntryByReport( $report['tawasulReportID'] ?? $report['reportIdentifier'], 'Single', $report['tawasulPersonID'], $roleCategory, $canViewDraftReports, $canViewPastReports);
        });
    } elseif (!empty($tawasulYearGroupID)) {
        $reports = $reportGateway->queryFormGroupsByReport($criteria, !empty($tawasulReportID) ? $tawasulReportID : $reportIdentifier, $tawasulYearGroupID, $roleCategory, $canViewDraftReports, $canViewPastReports);
    } else {
        $reports = $reportGateway->queryYearGroupsByReport($criteria, $tawasulReportID, $roleCategory, $canViewDraftReports, $canViewPastReports);

        $reports->transform(function (&$report) use ($roleCategory, $canViewDraftReports, $canViewPastReports, &$reportArchiveEntryGateway) {
            $report['archive'] = $reportArchiveEntryGateway->getRecentArchiveEntryByReport($report['tawasulReportID'] ?? $report['reportIdentifier'], 'Batch', $report['tawasulYearGroupID'], $roleCategory, $canViewDraftReports, $canViewPastReports);
        });
    }

    // Data TABLE
    $table = DataTable::createPaginated('reportsView', $criteria)->withData($reports);
    $table->setTitle(__('View'));

    if (!empty($tawasulFormGroupID)) {
        $table->addColumn('student', __('Student'))
            ->sortable(['surname', 'preferredName'])
            ->width('25%')
            ->format(function ($person) {
                return Format::nameLinked($person['tawasulPersonID'],'', $person['preferredName'], $person['surname'], 'Student', true, false, ['subpage' => 'Reports']);
            });

        $table->addColumn('status', __('Last Created'))
            ->notSortable()
            ->format(function ($report) {
                $output = '';
                $archive = $report['archive'] ?? null;
                if ($archive) {
                    $tag = '<span class="tag ml-2 '.($archive['status'] == 'Final' ? 'success' : 'dull').'">'.__($archive['status']).'</span>';
                    $url = './modules/TawasulReports/archive_byStudent_download.php?tawasulReportArchiveEntryID='.$archive['tawasulReportArchiveEntryID'].'&tawasulPersonID='.$report['tawasulPersonID'];
                    $title = Format::dateTimeReadable($archive['timestampModified']);
                    $output .= Format::link($url, $title).$tag;
                }

                if (!empty($report['timestampAccessed'])) {
                    $title = Format::name($report['parentTitle'], $report['parentPreferredName'], $report['parentSurname'], 'Parent', false).': '.Format::relativeTime($report['timestampAccessed'], false);
                    $output .= '<span class="tag ml-2 success" title="'.$title.'">'.__('Read').'</span>';
                }

                return $output;
            });
    } elseif (!empty($tawasulYearGroupID)) {
        $table->addColumn('name', __('Name'));

        $table->addColumn('count', __('Reports'));

        $table->addColumn('read', __('Read'))
            ->notSortable()
            ->width('30%')
            ->format(function ($report) use (&$page) {
                if (empty($report['readCount'])) return Format::small(__('N/A'));

                return $page->fetchFromTemplate('ui/writingProgress.twig.html', [
                    'progressName'   => __('Read'),
                    'progressColour' => 'bg-green-300',
                    'progressBorder' => 'border-green-600',
                    'progressCount' => $report['readCount'],
                    'totalCount'    => $report['count'],
                    'width'         => 'w-48',
                ]);
            });
    } else {
        $table->addColumn('name', __('Name'));

        $table->addColumn('timestamp', __('Last Created'))
            ->notSortable()
            ->format(function ($report) {
                $archive = $report['archive'] ?? null;
                if ($archive) {
                    $tag = '<span class="tag ml-2 '.($archive['status'] == 'Final' ? 'success' : 'dull').'">'.__($archive['status']).'</span>';
                    $url = './modules/TawasulReports/archive_byReport_download.php?tawasulReportArchiveEntryID='.$archive['tawasulReportArchiveEntryID'];
                    $title = Format::dateTimeReadable($archive['timestampModified']);
                    return Format::link($url, $title).$tag;
                }

                return '';
            });

        $table->addColumn('count', __('Reports'));

        $table->addColumn('read', __('Read'))
            ->notSortable()
            ->width('30%')
            ->format(function ($report) use (&$page) {
                if (empty($report['readCount'])) return Format::small(__('N/A'));

                return $page->fetchFromTemplate('ui/writingProgress.twig.html', [
                    'progressName'   => __('Read'),
                    'progressColour' => 'bg-green-300',
                    'progressBorder' => 'border-green-600',
                    'progressCount'  => $report['readCount'],
                    'totalCount'     => $report['count'],
                    'width'          => 'w-48',
                ]);
            });
    }

    $table->addActionColumn()
        ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
        ->addParam('tawasulReportID', $tawasulReportID)
        ->addParam('reportIdentifier', $reportIdentifier)
        ->format(function ($report, $actions) {
            if (!empty($report['tawasulFormGroupID']) && !empty($report['tawasulPersonID'])) {
                $actions->addAction('view', __('View'))
                        ->directLink()
                        ->addParam('action', 'view')
                        ->addParam('tawasulPersonID', $report['tawasulPersonID'] ?? '')
                        ->addParam('tawasulReportArchiveEntryID', $report['archive']['tawasulReportArchiveEntryID'] ?? '')
                        ->setURL('/modules/TawasulReports/archive_byStudent_download.php');

                $actions->addAction('download', __('Download'))
                        ->directLink()
                        ->setIcon('download')
                        ->addParam('tawasulPersonID', $report['tawasulPersonID'] ?? '')
                        ->addParam('tawasulReportArchiveEntryID', $report['archive']['tawasulReportArchiveEntryID'] ?? '')
                        ->setURL('/modules/TawasulReports/archive_byStudent_download.php');

                $actions->addAction('go', __('View by Student'))
                    ->setIcon('page_right')
                    ->addParam('tawasulReportID', $report['tawasulReportID'] ?? '')
                    ->addParam('tawasulPersonID', $report['tawasulPersonID'] ?? '')
                    ->setURL('/modules/TawasulReports/archive_byStudent_view.php');
            } else {
                $actions->addAction('go', __('Go'))
                    ->setIcon('page_right')
                    ->addParam('tawasulYearGroupID', $report['tawasulYearGroupID'] ?? '')
                    ->addParam('tawasulFormGroupID', $report['tawasulFormGroupID'] ?? '')
                    ->setURL('/modules/TawasulReports/archive_byReport_view.php');
            }
        });

    echo $table->render($reports);
}
