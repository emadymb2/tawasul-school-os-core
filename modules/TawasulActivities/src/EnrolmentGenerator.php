<?php
/*
TawasulOS, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

namespace Tos\Module\TawasulActivities;

use TawasulOS\Http\Url;
use TawasulOS\Services\Format;
use TawasulOS\UI\Components\Alert;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivityChoiceGateway;
use TawasulOS\Domain\Activities\ActivityStudentGateway;
use TawasulOS\Domain\Activities\ActivityCategoryGateway;

/**
 * Facilitates turning student choices into a set of potential enrolment groups 
 * for each of the selected activities.
 */
class EnrolmentGenerator 
{
    protected $activityGateway;
    protected $activityStudentGateway;
    protected $activityChoiceGateway;
    protected $activityCategoryGateway;
    protected $alert;

    protected $newStudentPriority = true;
    protected $yearGroupPriority = true;
    protected $includePastChoices = true;
    protected $includeTimestamps = false;
    
    protected $signUpChoices;
    protected $activities;
    protected $enrolments;
    protected $choices;
    protected $groups;

    public function __construct(ActivityGateway $activityGateway, ActivityStudentGateway $activityStudentGateway, ActivityChoiceGateway $activityChoiceGateway, ActivityCategoryGateway $activityCategoryGateway, Alert $alert)
    {
        $this->activityGateway = $activityGateway;
        $this->activityStudentGateway = $activityStudentGateway;
        $this->activityChoiceGateway = $activityChoiceGateway;
        $this->activityCategoryGateway = $activityCategoryGateway;
        $this->alert = $alert;
    }

    public function getActivities()
    {
        return $this->activities;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function setOptions(array $options)
    {
        $this->newStudentPriority = in_array('newStudentPriority', $options);
        $this->yearGroupPriority = in_array('yearGroupPriority', $options);
        $this->includePastChoices = in_array('includePastChoices', $options);
        $this->includeTimestamps = in_array('includeTimestamps', $options);

        return $this;
    }

    public function loadActivities(string $tawasulActivityCategoryID, array $activityList)
    {
        // Filter details to only those checked for this generation process
        $this->activities = $this->activityGateway->selectActivityDetailsByCategory($tawasulActivityCategoryID)->fetchGroupedUnique();
        $this->activities = array_intersect_key($this->activities, $activityList);

        // Update max values for the selected activities
        foreach ($this->activities as $tawasulActivityID => $activity) {
            $activity['maxParticipants'] = $activityList[$tawasulActivityID]['maxParticipants'] ?? $activity['maxParticipants'];

            $this->activityGateway->update($tawasulActivityID, [
                'maxParticipants' => $activity['maxParticipants'],
            ]);

            $this->activities[$tawasulActivityID] = $activity;
        }

        return $this;
    }

    public function loadEnrolments(string $tawasulActivityCategoryID)
    {
        $this->enrolments = $this->activityStudentGateway->selectEnrolmentsByCategory($tawasulActivityCategoryID)->fetchGroupedUnique();

        return $this;
    }

    public function loadChoices(string $tawasulActivityCategoryID)
    {
        $category = $this->activityCategoryGateway->getByID($tawasulActivityCategoryID);
        $this->signUpChoices = $category['signUpChoices'] ?? 3;

        $enrolmentsByPerson = array_reduce($this->enrolments, function ($group, $item) {
            $group[$item['tawasulPersonID']] = $item; 
            return $group;
        }, []);
        
        $choices = $this->activityChoiceGateway->selectChoicesByCategory($tawasulActivityCategoryID)->fetchGroupedUnique();
        $this->choices = [];

        foreach ($choices as $tawasulPersonID => $person) {
            if (!empty($enrolmentsByPerson[$tawasulPersonID])) continue;
            
            for ($i = 1; $i <= $this->signUpChoices; $i++) {
                $person["choice{$i}"] = str_pad($person["choice{$i}"] ?? '', 8, '0', STR_PAD_LEFT);
                $person["choice{$i}Name"] = $this->activities[$person["choice{$i}"]]['name'] ?? '';
            }

            $this->choices[$tawasulPersonID] = $person;
        }

        $this->sortChoicesByWeighting($tawasulActivityCategoryID);

        return $this;
    }

    public function generateGroups()
    {
        // Preload any existing enrolments
        foreach ($this->enrolments as $tawasulActivityStudentID => $person) {
            $person = $this->getAlertData($person);
            $person['enrolled'] = true;

            $this->groups[$person['tawasulActivityID']][$person['tawasulPersonID']] = $person;
        }

        // Assign choices to groups until the groups fill up
        foreach ($this->choices as $tawasulPersonID => $person) {

            $person = $this->getAlertData($person);
            $enrolmentGroup = 0;

            for ($i = 1; $i <= 3; $i++) {
                if (empty($person["choice{$i}"])) continue;

                $choiceActivity = $this->activities[$person["choice{$i}"]] ?? ['maxParticipants' => 0];
                $groupCount = count($this->groups[$person["choice{$i}"]] ?? []);

                if ($groupCount < $choiceActivity['maxParticipants']) {
                    $enrolmentGroup = $person["choice{$i}"];
                    break;
                }
            }

            $this->groups[$enrolmentGroup][$tawasulPersonID] = $person;
        }

        // Sort each resulting group alphabetically
        foreach ($this->groups as $enrolmentGroup => $group) {
            uasort($group, function ($a, $b) {
                if ($a['surname'] != $b['surname']) {
                    return $a['surname'] <=> $b['surname'];
                }

                return $a['preferredName'] <=> $b['preferredName'];
            });

            $this->groups[$enrolmentGroup] = $group;
        }

        return $this;
    }

    public function createEnrolments($tawasulActivityCategoryID, $enrolmentList, $tawasulPersonIDCreated = null) : array
    {
        $results = ['total' => 0, 'choice0' => 0, 'choice1' => 0, 'choice2' => 0, 'choice3' => 0, 'choice4' => 0, 'choice5' => 0, 'unassigned' => 0, 'inserted' => 0, 'updated' => 0, 'error' => 0];

        foreach ($enrolmentList as $person => $tawasulActivityID) {
            list($tawasulPersonID, $enrolmentID) = array_pad(explode('-', $person, 2), 2, '');

            if (empty($tawasulActivityID)) {
                $results['unassigned']++;
                continue;
            }

            // Connect the choice to the enrolment, for future queries and weighting
            $choice = $this->activityChoiceGateway->getChoiceByActivityAndPerson($tawasulActivityID, $tawasulPersonID);
            $choiceNumber = intval($choice['choice'] ?? 0);

            $enrolment = $this->activityStudentGateway->getEnrolmentByCategoryAndPerson($tawasulActivityCategoryID, $tawasulPersonID);

            if (!empty($enrolment)) {
                // Update and existing enrolment
                $data = [
                    'tawasulActivityID'       => $tawasulActivityID,
                    'tawasulActivityChoiceID' => $choice['tawasulActivityChoiceID'] ?? null,
                ];
    
                $updated = $this->activityStudentGateway->update($enrolment['tawasulActivityStudentID'], $data);
                $results['total']++;
                $results['updated']++;
                $results["choice".$choiceNumber]++;
                
            } else {
                // Add a new enrolment
                $data = [
                    'tawasulActivityID'       => $tawasulActivityID,
                    'tawasulActivityChoiceID' => $choice['tawasulActivityChoiceID'] ?? null,
                    'tawasulPersonID'         => $tawasulPersonID,
                    'status'                 => 'Accepted',
                    'timestamp'              => date('Y-m-d H:i:s'),
                ];

                $inserted = $this->activityStudentGateway->insert($data);
                if ($inserted) {
                    $results['total']++;
                    $results['inserted']++;
                    $results["choice".$choiceNumber]++;
                } else {
                    $results['error']++;
                }
            }
        }

        return $results;
    }

    protected function sortChoicesByWeighting(string $tawasulActivityCategoryID)
    {
        $choiceWeights = $this->activityChoiceGateway->selectChoiceWeightingByCategory($tawasulActivityCategoryID)->fetchGroupedUnique();
        $timestampRange = $this->activityChoiceGateway->getTimestampMinMaxByCategory($tawasulActivityCategoryID);
        $yearGroupMax = $this->activityChoiceGateway->getYearGroupWeightingMax();

        foreach ($this->choices as $tawasulPersonID => $person) {
            $choiceWeight = $yearGroupWeight = 0;

            // Weight students who didn't get 1st choice in the past higher (0 - 3.0)
            if ($this->includePastChoices && !empty($choiceWeights[$tawasulPersonID]['choiceCount'])) {
                $choiceWeight += ($choiceWeights[$tawasulPersonID]['choiceCount']) / max(($choiceWeights[$tawasulPersonID]['categoryCount'] ?? 0), 1);
            }

            // Students who are brand new to DL get an extra boost
            if ($this->newStudentPriority && empty($choiceWeights[$tawasulPersonID]['categoryCount'])) {
                $choiceWeight += 1.5;
            }

            // Weight younger year groups more than older ones (0 - 1.0)
            if ($this->yearGroupPriority) {
                $yearGroupWeight = ($yearGroupMax - ($person['yearGroupSequence'] ?? 0)) / max($yearGroupMax, 1);
            }

            // Include timestamps (0 - 1.5), or add some randomization to keep things fresh (0 - 0.5)
            if ($this->includeTimestamps && !empty($timestampRange['max'])) {
                $timestamp = strtotime($person['timestampCreated']) - $timestampRange['min'];
                $timestampUpper = $timestampRange['max'] - $timestampRange['min'];
                $timestampWeight = 1.5 - ((floatval($timestamp) / floatval($timestampUpper)) * 1.5);
            } else {
                $timestampWeight = (mt_rand(0,500) / 1000);
            }

            $this->choices[$tawasulPersonID]['weight'] = $choiceWeight + $yearGroupWeight + $timestampWeight;
        }

        // A higher weighting gives students a higher priority to get their top choices
        uasort($this->choices, function ($a, $b) {
            return $b['weight'] <=> $a['weight'];
        });
    }

    protected function getAlertData(array $person) : array 
    {
        $person['age'] = !empty($person['dob']) ? Format::age($person['dob']) : '';
        $person['link'] = Url::fromModuleRoute('TawasulStudents', 'student_view_details')->withQueryParams(['tawasulPersonID' => $person['tawasulPersonID']]);
        $person['alerts'] = $this->alert->getAlertBar($person['tawasulPersonID'], ['wrap' => false, 'filter' => ['Medical', 'Individual Needs', 'Privacy']]);

        return $person;
    }
}
