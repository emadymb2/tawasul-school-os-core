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

use TawasulOS\Locale;
use TawasulOS\UI\Icon;
use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Data\PasswordPolicy;
use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\System\AlertLevelGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Input\Editor;

function getIPAddress()
{
    $return = false;

    if (getenv('HTTP_CLIENT_IP'))
        $return = getenv('HTTP_CLIENT_IP');
    else if (getenv('HTTP_X_FORWARDED_FOR'))
        $return = getenv('HTTP_X_FORWARDED_FOR');
    else if (getenv('HTTP_X_FORWARDED'))
        $return = getenv('HTTP_X_FORWARDED');
    else if (getenv('HTTP_FORWARDED_FOR'))
        $return = getenv('HTTP_FORWARDED_FOR');
    else if (getenv('HTTP_FORWARDED'))
        $return = getenv('HTTP_FORWARDED');
    else if (getenv('REMOTE_ADDR'))
        $return = getenv('REMOTE_ADDR');

    return $return;
}

/**
 * Custom translation function to allow custom string replacement
 *
 * @param string        $text    Text to Translate. See documentation for
 *                               TawasulOS\Locale::translate for more info.
 * @param array         $params  Assoc array of key value pairs for named
 *                               string replacement. See documentation for
 *                               TawasulOS\Locale::translate for more info.
 * @param array|string  $options Options for translations (e.g. domain).
 *                               Or string of domain (for backward
 *                               compatibility, deprecated).
 *
 * @return string The resulted translation string.
 */
function __($text, $params = [], $options = [])
{
    global $tawasul, $guid; // For backwards compatibilty

    $args = func_get_args();

    // Note: should remove the compatibility code in next
    // version, then properly state function signature.

    // Compatibility with __($guid, $text) and __($guid, $text, $domain) calls.
    // Deprecated.
    if ($args[0] === $guid) {
        array_shift($args); // discard $guid
    }
    if (empty($args)) {
        return ''; // if there is nothing after $guid, return nothing
    }

    // Basic __($text) signature handle by default.
    $text = array_shift($args);
    $params = [];
    $options = [];

    // Handle replacement parameters, if exists.
    if (!empty($args) && is_array($args[0])) {
        $params = array_shift($args);
    }

    // Handle options, if exists.
    if (!empty($args)) {
        $options = array_shift($args);

        // Backward compatibility layer.
        // Treat non-array options as 'domain'.
        $options = is_array($options) ? $options : ['domain' => $options];
    }

    // Cancel out early for empty translations
    if (empty($text)) {
        return $text;
    }

    // Fallback to format string if global locale does not exists.
    return isset($tawasul->locale)
        ? $tawasul->locale->translate($text, $params, $options)
        : Locale::formatString($text, $params);
}

/**
 * Custom translation function to allow custom string replacement with
 * plural string.
 *
 * @param string $singular The singular message ID.
 * @param string $plural   The plural message ID.
 * @param int    $n        The number (e.g. item count) to determine
 *                         the translation for the respective grammatical
 *                         number.
 * @param array  $params   Assoc array of key value pairs for named
 *                         string replacement.
 * @param array  $options  Options for translations (e.g. domain).
 *
 * @return string Translated Text
 */
function __n(string $singular, string $plural, int $n, array $params = [], array $options = [])
{
    global $tawasul;
    return $tawasul->locale->translateN($singular, $plural, $n, $params, $options);
}

/**
 * Identical to __() but automatically includes the current module as the text domain.
 *
 * @see __()
 * @param string $text
 * @param array  $params
 * @param array  $options
 * @return string
 */
function __m(string $text, array $params = [], array $options = [])
{
    global $tawasul, $session;

    if ($session->has('module')) {
        $options['domain'] = $session->get('module');
    }

    return $tawasul->locale->translate($text, $params, $options);
}

/**
 * Return an SVG icon from a specified icon library.
 * Many of the icons come from: https://heroicons.com
 *
 * @param string $library   One of: basic, solid, outline
 * @param string $icon      The name of an icon
 * @param string $class     Applies a class to the svg returned
 * @param array $options    Eg: strokeWidth for outline icons
 * @return string
 */
function icon(string $library, string $icon, string $class = '', array $options = []) : string
{
    return Icon::get($library, $icon, $class, $options);
}

//$valueMode can be "value" or "id" according to what goes into option's value field
//$selectMode can be "value" or "id" according to what is used to preselect an option
//$honourDefault can TRUE or FALSE, and determines whether or not the default grade is selected

function renderGradeScaleSelect($connection2, $guid, $tawasulScaleID, $fieldName, $valueMode, $honourDefault = true, $width = 50, $selectedMode = 'value', $selectedValue = null)
{
    $return = false;

    $return .= "<select name='$fieldName' id='$fieldName' style='width: " . $width . "px'>";

    $dataSelect = array('tawasulScaleID' => $tawasulScaleID);
    $sqlSelect = 'SELECT * FROM tawasulScaleGrade WHERE tawasulScaleID=:tawasulScaleID ORDER BY sequenceNumber';
    $resultSelect = $connection2->prepare($sqlSelect);
    $resultSelect->execute($dataSelect);
    $return .= "<option value=''></option>";
    $sequence = '';
    $descriptor = '';
    while ($rowSelect = $resultSelect->fetch()) {
        $selected = '';
        if ($honourDefault and is_null($selectedValue)) { //Select entry based on scale default
            if ($rowSelect['isDefault'] == 'Y') {
                $selected = 'selected';
            }
        } elseif ($selectedMode == 'value') { //Select entry based on value passed
            if ($rowSelect['value'] == $selectedValue) {
                $selected = 'selected';
            }
        } elseif ($selectedMode == 'id') { //Select entry based on id passed
            if ($rowSelect['tawasulScaleGradeID'] == $selectedValue) {
                $selected = 'selected';
            }
        }
        if ($valueMode == 'value') {
            $return .= "<option $selected value='" . htmlPrep($rowSelect['value']) . "'>" . htmlPrep(__($rowSelect['value'])) . '</option>';
        } else {
            $return .= "<option $selected value='" . htmlPrep($rowSelect['tawasulScaleGradeID']) . "'>" . htmlPrep(__($rowSelect['value'])) . '</option>';
        }
    }
    $return .= '</select>';

    return $return;
}

function getSalt()
{
    $c = './aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ0123456789';
    $s = '';
    $l = strlen($c);
    for ($x = 0; $x < 22; $x++) {
        $ind =  mt_rand(0, $l - 1);
        $s .= $c[$ind];
    }
    return $s;
}

//Get information on a unit of work, inlcuding the possibility that it is a hooked unit
function getUnit($connection2, $tawasulUnitID, $tawasulCourseClassID)
{
    $output = array();
    $unitType = false;
    if ($tawasulUnitID != '') {
        try {
            $dataUnit = array('tawasulUnitID' => $tawasulUnitID);
            $sqlUnit = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID';
            $resultUnit = $connection2->prepare($sqlUnit);
            $resultUnit->execute($dataUnit);
            if ($resultUnit->rowCount() == 1) {
                $rowUnit = $resultUnit->fetch();
                if (isset($rowUnit['type'])) {
                    $unitType = $rowUnit['type'];
                }
                $output[0] = $rowUnit['name'];
                $output[1] = '';
            }
        } catch (PDOException $e) {
        }
    }

    return $output;
}

function getWeekNumber($date, $connection2, $guid)
{
    global $session;

    $week = 0;
    try {
        $dataWeek = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
        $sqlWeek = 'SELECT * FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY sequenceNumber';
        $resultWeek = $connection2->prepare($sqlWeek);
        $resultWeek->execute($dataWeek);
        while ($rowWeek = $resultWeek->fetch()) {
            $firstDayStamp = strtotime($rowWeek['firstDay']);
            $lastDayStamp = strtotime($rowWeek['lastDay']);
            while (date('N', $firstDayStamp) !== '1') {
                $firstDayStamp = $firstDayStamp - 86400;
            }
            $head = $firstDayStamp;
            while ($head <= ($date) and $head < ($lastDayStamp + 86399)) {
                $head = $head + (86400 * 7);
                ++$week;
            }
            if ($head < ($lastDayStamp + 86399)) {
                break;
            }
        }
    } catch (PDOException $e) {
    }

    if ($week <= 0) {
        return false;
    } else {
        return $week;
    }
}

function getYearGroups($connection2)
{
    $output = false;
    //Scan through year groups
    //SELECT NORMAL
    try {
        $sql = 'SELECT * FROM tawasulYearGroup ORDER BY sequenceNumber';
        $result = $connection2->query($sql);
        while ($row = $result->fetch()) {
            $output .= $row['tawasulYearGroupID'] . ',';
            $output .= $row['name'] . ',';
        }
    } catch (PDOException $e) {
    }

    if ($output != false) {
        $output = substr($output, 0, (strlen($output) - 1));
        $output = explode(',', $output);
    }

    return $output;
}

function getYearGroupsFromIDList($guid, $connection2, $ids, $vertical = false, $translated = true)
{
    $output = false;

    try {
        $sqlYears = 'SELECT DISTINCT nameShort, sequenceNumber FROM tawasulYearGroup ORDER BY sequenceNumber';
        $resultYears = $connection2->query($sqlYears);

        $years = explode(',', $ids ?? '');
        if (count($years) > 0 and $years[0] != '') {
            if (count($years) == $resultYears->rowCount()) {
                $output = '<i>' . __('All') . '</i>';
            } else {
                try {
                    $dataYears = array();
                    $sqlYearsOr = '';
                    for ($i = 0; $i < count($years); ++$i) {
                        if ($i == 0) {
                            $dataYears["year$i"] = $years[$i];
                            $sqlYearsOr = $sqlYearsOr . ' WHERE tawasulYearGroupID=:year' . $i;
                        } else {
                            $dataYears["year$i"] = $years[$i];
                            $sqlYearsOr = $sqlYearsOr . ' OR tawasulYearGroupID=:year' . $i;
                        }
                    }

                    $sqlYears = "SELECT DISTINCT nameShort, sequenceNumber FROM tawasulYearGroup $sqlYearsOr ORDER BY sequenceNumber";
                    $resultYears = $connection2->prepare($sqlYears);
                    $resultYears->execute($dataYears);
                } catch (PDOException $e) {
                }

                $count3 = 0;
                while ($rowYears = $resultYears->fetch()) {
                    if ($count3 > 0) {
                        if ($vertical == false) {
                            $output .= ', ';
                        } else {
                            $output .= '<br/>';
                        }
                    }
                    if ($translated == true) {
                        $output .= __($rowYears['nameShort']);
                    } else {
                        $output .= $rowYears['nameShort'];
                    }
                    ++$count3;
                }
            }
        } else {
            $output = '<i>' . __('None') . '</i>';
        }
    } catch (PDOException $e) {
    }

    return $output;
}

//Encode strring using htmlentities with the ENT_QUOTES option
function htmlPrep($str)
{
    if (is_array($str)) return $str;

    return htmlentities($str ?? '', ENT_QUOTES, 'UTF-8');
}

//Looks at the grouped actions accessible to the user in the current module and returns the highest
function getHighestGroupedAction($guid, $address, $connection2)
{
    global $session;

    if (empty($session->get('tawasulRoleIDCurrent'))) return false;

    $output = false;
    $module = getModuleName($address);

    try {
        $data = [
            'actionName' => '%' . getActionName($address) . '%',
            'tawasulRoleID' => $session->get('tawasulRoleIDCurrent'),
            'moduleName' => $module,
        ];
        $sql = 'SELECT
        tawasulAction.name
        FROM
        tawasulAction
        INNER JOIN tawasulModule ON (tawasulModule.tawasulModuleID=tawasulAction.tawasulModuleID)
        INNER JOIN tawasulPermission ON (tawasulAction.tawasulActionID=tawasulPermission.tawasulActionID)
        INNER JOIN tawasulRole ON (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID)
        WHERE
        tawasulAction.URLList LIKE :actionName AND
        tawasulPermission.tawasulRoleID=:tawasulRoleID AND
        tawasulModule.name=:moduleName
        ORDER BY tawasulAction.precedence DESC, tawasulAction.tawasulActionID';

        $result = $connection2->prepare($sql);
        $result->execute($data);
        if ($result->rowCount() > 0) {
            $row = $result->fetch();
            $output = $row['name'];
        }
    } catch (PDOException $e) {
    }

    return $output;
}


//Checks to see if a specified date (YYYY-MM-DD) is a day where school is open in the current academic year. There is an option to search all years
function isSchoolOpen($guid, $date, $connection2, $allYears = '')
{
    global $session;

    //Set test variables
    $isInTerm = false;
    $isSchoolDay = false;
    $isSchoolOpen = false;

    //Turn $date into UNIX timestamp and extract day of week
    $timestamp = Format::timestamp($date);

    $dayOfWeek = date('D', $timestamp);

    //See if date falls into a school term
    $data = [];
    $sql = "SELECT tawasulSchoolYearTerm.firstDay, tawasulSchoolYearTerm.lastDay FROM tawasulSchoolYearTerm, tawasulSchoolYear WHERE tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID";

    if ($allYears != true) {
        $data['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
        $sql .= ' AND tawasulSchoolYear.tawasulSchoolYearID=:tawasulSchoolYearID';
    }

    $result = $connection2->prepare($sql);
    $result->execute($data);

    while ($row = $result->fetch()) {
        if ($date >= $row['firstDay'] and $date <= $row['lastDay']) {
            $isInTerm = true;
        }
    }

    //See if date's day of week is a school day
    if ($isInTerm == true) {
        $data = array('nameShort' => $dayOfWeek);
        $sql = "SELECT * FROM tawasulDaysOfWeek WHERE nameShort=:nameShort AND schoolDay='Y'";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        if ($result->rowCount() > 0) {
            $isSchoolDay = true;
        }
    }

    //See if there is a special day
    if ($isInTerm == true and $isSchoolDay == true) {
        $data = array('date' => $date);
        $sql = "SELECT * FROM tawasulSchoolYearSpecialDay WHERE type='School Closure' AND date=:date";
        $result = $connection2->prepare($sql);
        $result->execute($data);

        if ($result->rowCount() < 1) {
            $isSchoolOpen = true;
        }
    }

    return $isSchoolOpen;
}

function getAlertBar($guid, $connection2, $tawasulPersonID, $privacy = '', $divExtras = '', $div = true, $large = false, $target = "_self")
{
    return '';
}

//Gets system settings from database and writes them to individual session variables.
function getSystemSettings($guid, $connection2)
{
    global $session;

    //System settings from tawasulSetting
    try {
        $data = array();
        $sql = "SELECT * FROM tawasulSetting WHERE scope='System'";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $session->set('systemSettingsSet', false);
    }

    while ($row = $result->fetch()) {
        $name = $row['name'];
        $session->set($name, $row['value']);
    }

    //Get names and emails for administrator, dba, admissions
    //System Administrator
    $data = array('tawasulPersonID' => $session->get('organisationAdministrator'));
    $sql = 'SELECT surname, preferredName, email FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID';
    $result = $connection2->prepare($sql);
    $result->execute($data);
    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        $session->set('organisationAdministratorName', Format::name('', $row['preferredName'], $row['surname'], 'Staff', false, true));
        $session->set('organisationAdministratorEmail', $row['email']);
    }
    //DBA
    $data = array('tawasulPersonID' => $session->get('organisationDBA'));
    $sql = 'SELECT surname, preferredName, email FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID';
    $result = $connection2->prepare($sql);
    $result->execute($data);
    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        $session->set('organisationDBAName', Format::name('', $row['preferredName'], $row['surname'], 'Staff', false, true));
        $session->set('organisationDBAEmail', $row['email']);
    }
    //Admissions
    $data = array('tawasulPersonID' => $session->get('organisationAdmissions'));
    $sql = 'SELECT surname, preferredName, email FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID';
    $result = $connection2->prepare($sql);
    $result->execute($data);
    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        $session->set('organisationAdmissionsName', Format::name('', $row['preferredName'], $row['surname'], 'Staff', false, true));
        $session->set('organisationAdmissionsEmail', $row['email']);
    }
    //HR Administrator
    $data = array('tawasulPersonID' => $session->get('organisationHR'));
    $sql = 'SELECT surname, preferredName, email FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID';
    $result = $connection2->prepare($sql);
    $result->execute($data);
    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        $session->set('organisationHRName', Format::name('', $row['preferredName'], $row['surname'], 'Staff', false, true));
        $session->set('organisationHREmail', $row['email']);
    }

    //Language settings from tawasuli18n
    try {
        $data = array();
        $sql = "SELECT * FROM tawasuli18n WHERE systemDefault='Y'";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $session->set('systemSettingsSet', false);
    }
    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        setLanguageSession($guid, $row);
    }

    $session->set('systemSettingsSet', true);
}

//Set language session variables
function setLanguageSession($guid, $row, $defaultLanguage = true)
{
    global $session;

    $i18n = [
        'tawasuli18nID' => $row['tawasuli18nID'],
        'code' => $row['code'],
        'name' => $row['name'],
        'dateFormat' => $row['dateFormat'],
        'dateFormatRegEx' => $row['dateFormatRegEx'],
        'dateFormatPHP' => $row['dateFormatPHP'],
        'rtl' => $row['rtl'],
    ];

    if ($defaultLanguage) {
        $i18n['default']['code'] = $row['code'];
        $i18n['default']['name'] = $row['name'];
    }

    $session->set('i18n', $i18n);
}

function isActionAccessible($guid, $connection2, $address, $sub = '')
{
    global $session;

    $output = false;
    //Check user is logged in
    if ($session->has('username')) {
        //Check user has a current role set
        if ($session->get('tawasulRoleIDCurrent') != '') {
            //Check module ready
            $module = getModuleName($address);
            if (!empty($module)) {
                //Check current role has access rights to the current action.
                try {
                    $data = array('actionName' => '%' . getActionName($address) . '%', 'tawasulRoleID' => $session->get('tawasulRoleIDCurrent'), 'moduleName' => $module);

                    $sql = "SELECT tawasulAction.name FROM tawasulAction
                    JOIN tawasulModule ON (tawasulModule.tawasulModuleID=tawasulAction.tawasulModuleID)
                    JOIN tawasulPermission ON (tawasulAction.tawasulActionID=tawasulPermission.tawasulActionID)
                    JOIN tawasulRole ON (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID)
                    WHERE tawasulAction.URLList LIKE :actionName
                        AND tawasulPermission.tawasulRoleID=:tawasulRoleID
                        AND tawasulModule.name=:moduleName 
                        AND tawasulModule.active='Y' ";

                    if ($sub != '') {
                        $data['sub'] = $sub;
                        $sql .= ' AND tawasulAction.name=:sub';
                    }

                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                    if ($result->rowCount() > 0) {
                        $output = true;
                    }
                } catch (PDOException $e) {
                }
            }
        }
    }

    return $output;
}

/**
 * @deprecated in v23
 */
function isModuleAccessible($guid, $connection2, $address = '')
{
    global $session;

    if ($address == '') {
        $address = $session->get('address');
    }
    $output = false;
    //Check user is logged in && Check user has a current role set
    if ($session->has('username') && $session->has('tawasulRoleIDCurrent')) {

        //Check module ready
        $moduleID = checkModuleReady($address, $connection2);
        if ($moduleID != false) {
            //Check current role has access rights to an action in the current module.
            try {
                $data = array('tawasulRoleID' => $session->get('tawasulRoleIDCurrent'), 'moduleID' => $moduleID);
                $sql = 'SELECT * FROM tawasulAction, tawasulPermission, tawasulRole WHERE (tawasulAction.tawasulActionID=tawasulPermission.tawasulActionID) AND (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID) AND (tawasulPermission.tawasulRoleID=:tawasulRoleID) AND (tawasulAction.tawasulModuleID=:moduleID)';
                $result = $connection2->prepare($sql);
                $result->execute($data);
                if ($result->rowCount() > 0) {
                    $output = true;
                }
            } catch (PDOException $e) {
            }
        }
    }

    return $output;
}

//Get the module name from the address
function getModuleName($address)
{
    $pos = stripos($address, 'modules/');
    if ($pos !== false) {
        $dir = substr($address, $pos + 8);
        $nextSlash = stripos($dir, '/');
        return $nextSlash !== false ? substr($dir, 0, $nextSlash) : $dir;
    }

    $pos = stripos($address, 'module');
    if ($pos !== false) {
        $dir = substr($address, $pos + 6);

        $nextSlash = stripos($dir, '/');
        return $nextSlash !== false ? trim(substr($dir, 0, $nextSlash)) : trim($dir);
    }

    return '';
}

//Get the action name from the address
function getActionName($address)
{
    $module = getModuleName($address);

     if (!empty($module)) {
        $pos = stripos($address, 'modules/');
        if ($pos !== false) {
           return substr($address, (10 + strlen($module)));
        } else {
            $pos = stripos($address, 'module');
            $actionStart = stripos($address, '/', $pos + 6) + 1;
            return basename(substr($address, $actionStart));
        }
    }
    
    return basename($address);
}

//Using the current address, checks to see that a module exists and is ready to use, returning the ID if it is
function checkModuleReady($address, $connection2)
{
    $output = false;

    //Get module name from address
    $module = getModuleName($address);
    try {
        $data = array('name' => $module);
        $sql = "SELECT * FROM tawasulModule WHERE name=:name AND active='Y'";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        if ($result->rowCount() == 1) {
            $row = $result->fetch();
            $output = $row['tawasulModuleID'];
        }
    } catch (PDOException $e) {
    }

    return $output;
}

//Using the current address, get's the module's category
function getModuleCategory($address, $connection2)
{
    $output = false;

    //Get module name from address
    $module = getModuleName($address);


    $data = array('name' => $module);
    $sql = "SELECT * FROM tawasulModule WHERE name=:name AND active='Y'";
    $result = $connection2->prepare($sql);
    $result->execute($data);
    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        $output = __($row['category']);
    }

    return $output;
}

function getModuleIDFromName($connection2, $name)
{

    $dataModuleID = array('name' => $name);
    $sqlModuleID = 'SELECT tawasulModuleID FROM tawasulModule WHERE name=:name';
    $resultModuleID = $connection2->prepare($sqlModuleID);
    $resultModuleID->execute($dataModuleID);
    $row = $resultModuleID->fetch();

    return $row['tawasulModuleID'];
}

/**
 * Checks if PHP is currently running from the command line. Additional checks added to help with cgi/fcgi systems, currently limited to that scope.
 *
 * @version  v14
 * @since    24th May 2017
 * @return   bool
 */
function isCommandLineInterface()
{
    if (php_sapi_name() === 'cli') {
        return true;
    }

    if (stripos(php_sapi_name(), 'cgi') !== false) {
        if (defined('STDIN')) {
            return true;
        }

        if (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv'] ?? []) > 0) {
            return true;
        }

        if (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
            return true;
        }
    }

    return false;
}

/**
 * @deprecated in v22. Use Page's ReturnMessage.
 */
function returnProcess($guid, $return, $editLink = null, $customReturns = null)
{
    global $page;
    $page->return->setEditLink($editLink ?? '');
    $page->return->addReturns($customReturns ?? []);
}

/*
 * Tawasul OS Branches — platform integration seam.
 *
 * Other modules need to scope their queries to the active branch. Rather than
 * scatter a hard-coded path to the module's moduleFunctions.php across every
 * page, they call tosBranchHelpers() once and then tosBranchScope().
 *
 * Both are defined here, in core, deliberately:
 *
 *   - tosBranchHelpers() loads the module's helpers on demand, so core does not
 *     have to know whether the module is installed.
 *   - The tosBranchScope() stub below is declared only when the real one is
 *     absent, i.e. when the module is not installed or has been uninstalled. It
 *     returns null, which every caller already treats as "do not filter". So
 *     uninstalling the module leaves the platform working, unfiltered, rather
 *     than fataling.
 */

/**
 * Loads the Tawasul OS Branches helpers if the module is present.
 */
function tosBranchHelpers()
{
    static $tried = false;

    if ($tried) {
        return;
    }
    $tried = true;

    global $session;

    $absolutePath = (string) ($session->get('absolutePath') ?? '');
    if ($absolutePath === '') {
        return;
    }

    $file = $absolutePath.'/modules/Tawasul OS Branches/moduleFunctions.php';

    if (is_file($file)) {
        require_once $file;
    }
}

/**
 * Scopes a query to the active branch.
 *
 * This is a dispatcher rather than the implementation itself: the real
 * tosBranchScopeImpl() lives in the module and is only loaded when the module
 * is present. Declaring the implementation here under the same name would
 * collide with the module's copy the moment it is loaded.
 *
 * @return array{sql:string, params:array}|null Null means "do not filter".
 */
function tosBranchScope($session, $container, $pdo, $alias, $suffix = 'Branch', $includeUnassigned = true)
{
    tosBranchHelpers();

    if (!function_exists('tosBranchScopeImpl')) {
        // Module not installed, or not loadable: never filter.
        return null;
    }

    return tosBranchScopeImpl($session, $container, $pdo, $alias, $suffix, $includeUnassigned);
}
