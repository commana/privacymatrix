<?php
/*
Privacy Matrix for Moodle

Copyright (C) 2013 THM webMedia

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/roles/lib.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

require_login($course);

$url = new moodle_url('/report/privacymatrix/index.php', array('id'=>$course->id));
$PAGE->set_url($url);

// These are the capabilities we are most concerned with.
// I sorted them by my own judgement of their importance.
$capabilities = array(
    'report/loglive:view',
    'report/log:view',
    'moodle/site:readallmessages',
    'mod/chat:readlog',
    'moodle/site:viewfullnames',
    'moodle/course:viewhiddenuserfields',
    'moodle/grade:viewall', // view grades of all users (incl. hidden grades)
    'moodle/grade:viewhidden',
    'moodle/site:viewuseridentity', // view ID and email
    'moodle/user:viewalldetails',
    'moodle/user:viewdetails',
    'moodle/user:viewhiddendetails',
    'moodle/user:viewuseractivitiesreport',
    'mod/forum:viewdiscussion',
    'moodle/course:viewparticipants',
    
    //'moodle/site:viewparticipants',
    //'moodle/site:accessallgroups', // users with capability 'moodle/course:managegroups'
                                   // could assign themselves this permission
    //'moodle/user:viewusergrades', // not found via has_capability
    //'moodle/course:useremail', // "enable/disable email address"
);

//$context = context_system::instance();
$context = get_context_instance(CONTEXT_COURSE, $course->id);
$roles = role_fix_names(get_all_roles(), $context);
$roleheader = array('&nbsp;');
$roleheader[] = get_string('administrator');
foreach ($roles as $role) {
	$roleheader[] = $role->localname;
}

$siteadmins = explode(',', $CFG->siteadmins);

$rows = array();
foreach ($capabilities as $capability) {
    // site admin
	$cells = array(has_capability($capability, $context, $siteadmins[0]));
	// all other roles
	for ($i = 1; $i <= count($roles); $i++) {
	    $access = get_role_access($roles[$i]->id);
		$cells[] = has_capability_in_accessdata($capability, $context, $access);
	}
	$rows[get_capability_string($capability)] = $cells;
}

// Output...

$colors = array(
    'possible' => '#0BDB00',
    'not_possible' => '#FF1C1C'
);

$strrows = array();
foreach ($rows as $name => $row) {
    $str = "";
    $numCells = count($row) + 1; // +1 to account for the name row
    foreach ($row as $cell) {
        $color = $cell ? $colors['possible'] : $colors['not_possible'];
        $str .= "<td style='background-color: $color'>&nbsp;</td>";
    }
	$strrows[] = "<td>$name</td>$str";
}
$strtable = '<tr>' . implode('</tr><tr>', $strrows) . '</tr>';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('what_do_we_know', 'report_privacymatrix').":");
$tableStyle = "width: 100%; table-layout: fixed; border-spacing: 2px; border-collapse: separate;";
echo "<table style='$tableStyle'><col span='1' style='width: 10em'/><tr><th>", implode('</th><th>', $roleheader), "</th></tr>";
echo $strtable;
echo "</table>";

echo "<table style='$tableStyle'>";
echo "<tr><td style='background-color: " . $colors['possible'] . "; width: 4em;'>&nbsp;</td><td>" . get_string('possible', 'report_privacymatrix') . "</td></tr>";
echo "<tr><td style='background-color: " . $colors['not_possible'] . "'>&nbsp;</td><td>" . get_string('not_possible', 'report_privacymatrix') . "</td></tr>";
echo "</table>";

echo $OUTPUT->footer();

