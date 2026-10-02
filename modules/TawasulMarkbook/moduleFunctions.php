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
use TawasulOS\Forms\DatabaseFormFactory;
use Tos\Module\TawasulMarkbook\MarkbookView;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\School\SchoolYearTermGateway;

function sidebarExtra($guid, $pdo, $tawasulPersonID, $tawasulCourseClassID = '', $basePage = '')
{
    global $session;

    $output = '';

    if (empty($basePage)) $basePage = 'markbook_view.php';

    //Show class picker in sidebar
    $output .= '<div class="column-no-break">';
    $output .= '<h2>';
    $output .= __('Choose A Class');
    $output .= '</h2>';

    $form = Form::createBlank('searchForm', $session->get('absoluteURL').'/index.php', 'get')->enableQuickSubmit();
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('q', '/modules/TawasulMarkbook/'.$basePage);

    $row = $form->addRow()->addClass('flex');
        $row->addSelectClass('tawasulCourseClassID', $session->get('tawasulSchoolYearID'), $tawasulPersonID)
            ->selected($tawasulCourseClassID)
            ->placeholder()
            ->groupAlign('left')
            ->setClass('flex-grow');
        $row->addSubmit(__('Go'))
            ->setType('quickSubmit')
            ->groupAlign('right')
            ->setClass('flex');

    $output .= $form->getOutput();
    $output .= '</div>';

    return $output;
}

function classChooser($guid, $pdo, $tawasulCourseClassID)
{
    global $session, $container;

    $settingGateway = $container->get(SettingGateway::class);
    $enableColumnWeighting = $settingGateway->getSettingByScope('Markbook', 'enableColumnWeighting');
    $enableGroupByTerm = $settingGateway->getSettingByScope('Markbook', 'enableGroupByTerm');
    $enableRawAttainment = $settingGateway->getSettingByScope('Markbook', 'enableRawAttainment');

    $output = '';

    // $output .= "<h3 style='margin-top: 0px'>";
    // $output .= __('Choose Class');
    // $output .= '</h3>';

    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get')->enableQuickSubmit();
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/markbook_view.php');

    $col = $form->addRow();

    // SEARCH
    $search = $_GET['search'] ?? '';

    $col->addContent(__('Search').':')->setClass('flex-shrink');
    $col->addTextField('search')
        ->setClass('flex-1')
        ->setValue($search);

    $selectTerm = ($session->has('markbookTerm'))? $session->get('markbookTerm') : -1;
    $selectTerm = (isset($_GET['tawasulSchoolYearTermID']))? $_GET['tawasulSchoolYearTermID'] : $selectTerm;

    if (!isset($_GET['tawasulSchoolYearTermID']) && $enableColumnWeighting == 'Y') { //Set to current term if not already set
        $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);
        $currentTerm = $schoolYearTermGateway->getCurrentTermByDate(date('Y-m-d'));
        if (isset($currentTerm['tawasulSchoolYearTermID'])) {
            $selectTerm = $currentTerm['tawasulSchoolYearTermID'];
        }

    }
    
    $data = array("tawasulSchoolYearID" => $session->get('tawasulSchoolYearID'));
    $sql = "SELECT tawasulSchoolYearTermID as value, name FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY sequenceNumber";
    $result = $pdo->executeQuery($data, $sql);
    $terms = ($result->rowCount() > 0)? $result->fetchAll(\PDO::FETCH_KEY_PAIR) : array();

    $col->addContent(__('Term').':')->setClass('flex-shrink');
    $col->addSelect('tawasulSchoolYearTermID')
        ->fromArray(array('-1' => __('All Terms')))
        ->fromArray($terms)
        ->selected($selectTerm)
        ->setClass('flex-1');

    $session->set('markbookTermName', isset($terms[$selectTerm])? $terms[$selectTerm] : $selectTerm);
    $session->set('markbookTerm', $selectTerm);

    // SORT BY
    $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulSchoolYearID'=>$session->get('tawasulSchoolYearID') );
    $sql = "SELECT COUNT(DISTINCT tawasulStudentEnrolment.rollOrder) FROM tawasulCourseClassPerson INNER JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE role='Student' AND tawasulCourseClassID=:tawasulCourseClassID AND tawasulPerson.status='Full' AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<='".date('Y-m-d')."') AND (tawasulPerson.dateEnd IS NULL  OR tawasulPerson.dateEnd>='".date('Y-m-d')."') AND tawasulSchoolYearID=:tawasulSchoolYearID";
    $result = $pdo->executeQuery($data, $sql);
    $rollOrderCount = ($result->rowCount() > 0)? $result->fetchColumn(0) : 0;

    $selectOrderBy = ($session->has('markbookOrderBy'))? $session->get('markbookOrderBy') : 'surname';
    $selectOrderBy = (isset($_GET['markbookOrderBy']))? $_GET['markbookOrderBy'] : $selectOrderBy;

    $orderBy = ['surname' => __('Surname'), 'preferredName' => __('Preferred Name')];

    if ($rollOrderCount > 0) {
        $orderBy = ['rollOrder' => __('Roll Order')] + $orderBy;
    } elseif ($selectOrderBy == 'rollOrder') {
        $selectOrderBy = 'surname';
    }

    $col->addContent(__('Sort By').':')->setClass('flex-shrink');
    $col->addSelect('markbookOrderBy')->fromArray($orderBy)->selected($selectOrderBy)->setClass('flex-1');

    $session->set('markbookOrderBy', $selectOrderBy);

    // SHOW
    $selectFilter = ($session->has('markbookFilter'))? $session->get('markbookFilter') : '';
    $selectFilter = (isset($_GET['markbookFilter']))? $_GET['markbookFilter'] : $selectFilter;

    $session->set('markbookFilter', $selectFilter);

    $filters = array('' => __('All Columns'));
    if ($enableColumnWeighting == 'Y') $filters['averages'] = __('Overall Grades');
    if ($enableRawAttainment == 'Y') $filters['raw'] = __('Raw Marks');
    $filters['marked'] = __('Marked');
    $filters['unmarked'] = __('Unmarked');

    $col->addContent(__('Show').':')->setClass('flex-shrink');
    $col->addSelect('markbookFilter')
        ->fromArray($filters)
        ->selected($selectFilter)
        ->setClass('flex-1');

    // CLASS
    $col->addContent(__('Class').':')->setClass('flex-shrink');
    $col->addSelectClass('tawasulCourseClassID', $session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'))
        ->setClass('flex-1')
        ->selected($tawasulCourseClassID);

    $col->addSubmit(__('Go'))->setClass('max-w-24');

    if (!empty($search)) {
        $clearURL = $session->get('absoluteURL').'/index.php?q='.$session->get('address');
        $clearLink = sprintf('<a href="%s" class="text-xs" style="">%s</a> &nbsp;', $clearURL, __('Clear Search'));

        $form->addRow()->addContent($clearLink)->addClass('right');
    }

    $output .= $form->getOutput();

    return $output;
}

function isDepartmentCoordinator( $pdo, $tawasulPersonID ) {

        $data = array('tawasulPersonID' => $tawasulPersonID );
        $sql = "SELECT count(*) FROM tawasulDepartmentStaff WHERE tawasulPersonID=:tawasulPersonID AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)')";
        $result = $pdo->executeQuery($data, $sql);


    return ($result->rowCount() > 0)? ($result->fetchColumn() >= 1) : false;
}

function getAnyTaughtClass( $pdo, $tawasulPersonID, $tawasulSchoolYearID ) {

        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID);
        $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID ORDER BY course, class LIMIT 1';
        $result = $pdo->executeQuery($data, $sql);

    return ($result->rowCount() > 0)? $result->fetch() : NULL;
}

function getClass( $pdo, $tawasulPersonID, $tawasulCourseClassID, $highestAction ) {
    try {
        if ($highestAction == 'View Markbook_allClassesAllData') {
            $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
            $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourse.name AS courseName, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
        } else if ($highestAction == 'View Markbook_myClasses') {
            $data = array( 'tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $tawasulCourseClassID);
            $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourse.name AS courseName, tawasulCourseClass.nameShort AS class, tawasulCourse.tawasulYearGroupIDList, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class";
        } else {
            return null;
        }
        $result = $pdo->executeQuery($data, $sql);
    } catch (PDOException $e) {
        return null;
    }

    return ($result->rowCount() > 0)? $result->fetch() : NULL;
}

function getTeacherList( $pdo, $tawasulCourseClassID ) {

        $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, tawasulCourseClassPerson.reportable FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE role='Teacher' AND tawasulPerson.status='Full' AND tawasulCourseClassID=:tawasulCourseClassID ORDER BY surname, preferredName";
        $result = $pdo->executeQuery($data, $sql);


    $teacherList = array();
    if ($result->rowCount() > 0) {
        foreach ($result->fetchAll() as $teacher) {
            if ($teacher['reportable'] != 'Y') continue;

            $teacherList[ $teacher['tawasulPersonID'] ] = Format::name($teacher['title'], $teacher['preferredName'], $teacher['surname'], 'Staff', false, false);
        }
    }

    return $teacherList;
}

function getAlertStyle( $alert, $concern ) {

    if ($concern == 'Y') {
        return "style='color: ".$alert['color'].'; font-weight: bold; border: 2px solid '.$alert['color'].'; padding: 2px 4px; background-color: '.$alert['colorBG'].";margin:0 auto;'";
    } else if ($concern == 'P') {
        return "style='color: #390; font-weight: bold; border: 2px solid #390; padding: 2px 4px; background-color: #D4F6DC;margin:0 auto;'";
    } else {
        return '';
    }
}

function renderStudentCumulativeMarks($tawasul, $pdo, $tawasulPersonID, $tawasulCourseClassID, $tawasulSchoolYearTermID = '') {
    global $container;

    require_once __DIR__ . '/src/MarkbookView.php';

    // Build the markbook object for this class & student
    $markbook = new MarkbookView($tawasul, $pdo, $tawasulCourseClassID, $container->get(SettingGateway::class));
    $assessmentScale = $markbook->getDefaultAssessmentScale();

    // Cancel our now if this isnt a percent-based mark
    if (empty($assessmentScale) || (stripos($assessmentScale['name'], 'percent') === false && $assessmentScale['nameShort'] !== '%')) {
        return;
    }

    // Calculate & get the cumulative average
    $markbook->cacheWeightings($tawasulPersonID);
    $cumulativeMark = round(floatval($markbook->getCumulativeAverage($tawasulPersonID, $tawasulSchoolYearTermID)));

    // Only display if there are marks
    if (!empty($cumulativeMark)) {
        // Divider
        echo '<tr class="break">';
            echo '<th colspan="7" style="height: 4px; padding: 0px;"></th>';
        echo '</tr>';

        // Display the cumulative average
        echo '<tr>';
            echo '<td style="width:120px;">';
                echo '<b>'.__('Cumulative Average').'</b>';
            echo '</td>';
            echo '<td style="padding: 10px !important; text-align: center;">';
                echo round( $cumulativeMark ).'%';
            echo '</td>';
            echo '<td colspan="3" class="dull"></td>';
         echo '</tr>';
    }
}

function renderStudentSubmission($student, $submission, $markbookColumn)
{
    global $guid, $session;

    $output = '';

    if (!empty($submission)) {
        if ($submission['status'] == 'Exemption') {
            $linkText = __('Exe');
        } elseif ($submission['version'] == 'Final') {
            $linkText = __('Fin');
        } else {
            $linkText = __('Dra').$submission['count'];
        }

        $style = '';
        $status = __('On Time');
        if ($submission['status'] == 'Exemption') {
            $status = __('Exemption');
        } elseif ($submission['status'] == 'Late') {
            $style = "style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px'";
            $status = __('Late');
        }

        if ($submission['type'] == 'File') {
            $submission['location'] = str_replace(['?','#'], ['%3F', '%23'], $submission['location'] ?? '');
            $output .= "<span title='".$submission['version'].". $status. ".__('Submitted at').' '.substr($submission['timestamp'], 11, 5).' '.__('on').' '.Format::date(substr($submission['timestamp'], 0, 10))."' $style><a target='_blank' href='".$session->get('absoluteURL').'/'.$submission['location']."'>$linkText</a></span>";
        } elseif ($submission['type'] == 'Link') {
            $output .= "<span title='".$submission['version'].". $status. ".__('Submitted at').' '.substr($submission['timestamp'], 11, 5).' '.__('on').' '.Format::date(substr($submission['timestamp'], 0, 10))."' $style><a target='_blank' href='".$submission['location']."'>$linkText</a></span>";
        } else {
            $output .= "<span title='$status. ".__('Recorded at').' '.substr($submission['timestamp'], 11, 5).' '.__('on').' '.Format::date(substr($submission['timestamp'], 0, 10))."' $style>$linkText</span>";
        }
    } else {
        if (date('Y-m-d H:i:s') < $markbookColumn['homeworkDueDateTime']) {
            $output .= "<span title='".__('Pending')."'>".__('Pen').'</span>';
        } else {
            if (!empty($student['dateStart']) && $student['dateStart'] > $markbookColumn['lessonDate']) {
                $output .= "<span title='".__('Student joined school after assessment was given.')."' style='color: #000; font-weight: normal; border: 2px none #ff0000; padding: 2px 4px'>NA</span>";
            } else {
                if ($markbookColumn['homeworkSubmissionRequired'] == 'Required') {
                    $output .= "<span title='".__('Incomplete')."' style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px'>".__('Inc').'</span>';
                } else {
                    $output .= "<span title='".__('Not submitted online')."'>".__('NA').'</span>';
                }
            }
        }
    }

    return $output;
}
