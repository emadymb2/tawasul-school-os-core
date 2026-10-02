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

use TawasulOS\Domain\Planner\PlannerEntryStudentTrackerGateway;
use TawasulOS\Domain\Planner\PlannerEntryStudentHomeworkGateway;

require_once __DIR__ . '/../../tawasul.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_deadlines.php') == false) {
    die('error0');
} else {
    $category = $session->get('tawasulRoleIDCurrentCategory');
    if ($category != 'Student') {
        die('error0');
    } else {
        $complete = $_POST['complete'] ?? 'N';
        $type = $_POST['type'] ?? '';

        $data = [
            'tawasulPlannerEntryID' => $_POST['tawasulPlannerEntryID'] ?? '',
            'tawasulPersonID'       => $session->get('tawasulPersonID') ?? '',
        ];

        if (empty($complete) || empty($type) || empty($data['tawasulPlannerEntryID']) || empty($data['tawasulPersonID'])) {
            die('error1');
        }

        if ($type == 'teacherRecorded') {
            $studentTrackerGateway = $container->get(PlannerEntryStudentTrackerGateway::class);
            $values = $studentTrackerGateway->selectBy($data)->fetch();
            $data['homeworkComplete'] = $complete;

            if (!empty($values)) {
                $updated = $studentTrackerGateway->update($values['tawasulPlannerEntryStudentTrackerID'], $data);
            } else {
                $updated = $studentTrackerGateway->insert($data);
            }

        } elseif ($type == 'studentRecorded') {
            $studentHomeworkGateway = $container->get(PlannerEntryStudentHomeworkGateway::class);
            $values = $studentHomeworkGateway->selectBy($data)->fetch();
            $data['homeworkComplete'] = $complete;

            if (!empty($values)) {
                $updated = $studentHomeworkGateway->update($values['tawasulPlannerEntryStudentHomeworkID'], $data);
            } else {
                $updated = $studentHomeworkGateway->insert($data);
            }
        }

        die(!$updated ? 'error1' : 'success0');
    }
}
