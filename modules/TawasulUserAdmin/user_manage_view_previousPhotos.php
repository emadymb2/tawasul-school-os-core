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
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\User\PersonPhotoGateway;
use TawasulOS\Domain\User\UserStatusLogGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {

    // Check if tawasulPersonID specified
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    if (empty($tawasulPersonID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $userGateway = $container->get(UserGateway::class);
        $person = $userGateway->getByID($tawasulPersonID);

        if (empty($person)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            // Let's go!
            $personPhotoGateway = $container->get(PersonPhotoGateway::class);
            $criteria = $personPhotoGateway->newQueryCriteria(true)
                ->sortBy('tawasulSchoolYearID', 'DESC')
                ->fromPOST();

            $table = DataTable::createPaginated('photoLog', $criteria);

            $table->setTitle('Previous User Photos: ' . Format::name($person['title'], $person['preferredName'], $person['surname'], 'Student'));
        
            $table->addColumn('schoolYear', __('School Year'));

            $table->addColumn('image_240', __('Photo'))
                ->context('primary')
                ->format(function ($person) {
                    $photo = Format::userPhoto($person['personImage'], 'md');
                    return $photo;
                });

            $table->addColumn('timestamp', __('Date Uploaded'))
                ->format(Format::using('dateTime', ['timestamp']));

            $table->addColumn('modified', __('Uploaded By'))
                ->format(function($values) {
                    return !empty($values['tawasulPersonIDCreated'])
                        ? Format::nameLinked($values['tawasulPersonIDCreated'], '', $values['preferredName'], $values['surname'], 'Staff', false, true)
                        : Format::small(__('N/A'));
                });

            echo $table->render($personPhotoGateway->queryPersonPhotoByPerson($criteria, $tawasulPersonID));
        }
    }
}