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

namespace Tos\Module\TawasulStaff\View;

use TawasulOS\View\Page;
use TawasulOS\Services\Format;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use TawasulOS\Domain\Staff\StaffCoverageGateway;
use Tos\Module\TawasulStaff\View\CoverageView;

/**
 * AbsenceView
 *
 * A view composer class: receives an absence ID and displays the status information for the absence.
 *
 * @version v18
 * @since   v18
 */
class AbsenceView
{
    protected $staffAbsenceGateway;
    protected $userGateway;
    protected $staffCoverageGateway;
    protected $coverageView;

    protected $tawasulStaffAbsenceID;
    protected $tawasulPersonIDViewing;

    public function __construct(StaffAbsenceGateway $staffAbsenceGateway, StaffCoverageGateway $staffCoverageGateway, UserGateway $userGateway, CoverageView $coverageView)
    {
        $this->staffAbsenceGateway = $staffAbsenceGateway;
        $this->userGateway = $userGateway;
        $this->staffCoverageGateway = $staffCoverageGateway;
        $this->coverageView = $coverageView;
    }

    public function setAbsence($tawasulStaffAbsenceID, $tawasulPersonIDViewing)
    {
        $this->tawasulStaffAbsenceID = $tawasulStaffAbsenceID;
        $this->tawasulPersonIDViewing = $tawasulPersonIDViewing;

        return $this;
    }

    public function compose(Page $page)
    {
        $absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($this->tawasulStaffAbsenceID);
        if (empty($absence)) return;

        $person = $this->userGateway->getByID($absence['tawasulPersonIDCreator']);
        $canViewConfidential = $absence['tawasulPersonIDApproval'] == $this->tawasulPersonIDViewing || $absence['tawasulPersonID'] == $this->tawasulPersonIDViewing;
        
        // Absence Details
        $page->writeFromTemplate('statusComment.twig.html', [
            'name'    => Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff', false, true),
            'action'   => !empty($absence['tawasulPersonIDApproval'])? __('Requested Leave') : __('Submitted Leave'),
            'photo'   => $person['image_240'],
            'date'    => Format::relativeTime($absence['timestampCreator']),
            'comment' => $absence['comment'],
            'message' => $canViewConfidential && !empty($absence['commentConfidential']) ? __('Confidential Comment').': '.$absence['commentConfidential'] : '',
        ]);

        // Approval Details
        if (!empty($absence['tawasulPersonIDApproval'])) {
            $approver = $this->userGateway->getByID($absence['tawasulPersonIDApproval']);
            $page->writeFromTemplate('statusComment.twig.html', [
                'name'    => Format::name($approver['title'], $approver['preferredName'], $approver['surname'], 'Staff', false, true),
                'action'  => $absence['status'] != 'Pending Approval' ? __($absence['status']) : '',
                'photo'   => $approver['image_240'],
                'date'    => Format::relativeTime($absence['timestampApproval']),
                'status'  => __($absence['status']),
                'tag'     => $this->getStatusColor($absence['status']),
                'comment' => $canViewConfidential ? $absence['notesApproval'] : '',
            ]);
        }

        $coverageList = $this->staffCoverageGateway->selectCoverageByAbsenceID($absence['tawasulStaffAbsenceID'], true)->fetchAll();
        
        // Coverage Details
        if (!empty($coverageList)) {
            foreach ($coverageList as $coverage) {
                $this->coverageView->setCoverage($coverage['tawasulStaffCoverageID'])->compose($page);
            }
        }
    }

    protected function getStatusColor($status)
    {
        switch ($status) {
            case 'Approved':
                return 'success';

            case 'Declined':
                return 'error';

            default:
                return 'message';
        }
    }
}
