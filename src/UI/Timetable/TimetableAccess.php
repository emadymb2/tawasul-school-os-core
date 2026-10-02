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

namespace TawasulOS\UI\Timetable;

use TawasulOS\Support\Facades\Access;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Students\StudentGateway;

/**
 * Timetable UI: TimetableAccess
 * 
 * Controls access to the timetable via actions and permissions, based on the current session.
 *
 * @version  v29
 * @since    v29
 */
class TimetableAccess
{
    protected $session;
    protected $userGateway;
    protected $studentGateway;

    public function __construct(Session $session, UserGateway $userGateway, StudentGateway $studentGateway)
    {
        $this->session = $session;
        $this->userGateway = $userGateway;
        $this->studentGateway = $studentGateway;
    }

    public function checkAccess($context)
    {
        $action = Access::get('Timetable', 'tt');

        if ($action->allows('View Timetable by Person_allYears')) return true;
        
        if ($this->session->get('tawasulSchoolYearIDCurrent') != $context->get('tawasulSchoolYearID')) return false;

        if ($action->allows('View Timetable by Person')) return true;

        if ($action->allows('View Timetable by Person_my')) {
            return $this->session->get('tawasulPersonID') == $context->get('tawasulPersonID');
        }

        if ($action->allows('View Timetable by Person_myChildren')) {
            $children = $this->studentGateway->selectActiveStudentsByFamilyAdult($context->get('tawasulSchoolYearID'), $this->session->get('tawasulPersonID'))->fetchGroupedUnique();

            return !empty($children[$context->get('tawasulPersonID')]);
        }

        return false;
    }

    public function getPreferences()
    {
        if (!$this->session->has('tawasulPersonID')) return [];
        
        return $this->userGateway->getUserPreferences($this->session->get('tawasulPersonID'));
    }
}
