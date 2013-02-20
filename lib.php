<?php

/**
 * Display report for admins
 */
function report_privacymatrix_extend_navigation_course($navigation, $course, $context) {
	$url = new moodle_url('/report/privacymatrix/index.php', array('id' => $course->id));
	$navigation->add(get_string('pluginname', 'report_privacymatrix'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
}

/**
 * Display report for all users (global)
 */
function report_privacymatrix_extend_navigation_user($navigation, $user, $course) {
	$url = new moodle_url('/report/privacymatrix/index.php', array('id' => $course->id));
	$navigation->add(get_string('pluginname', 'report_privacymatrix'), $url);
}

/**
 *
 */
/*function report_privacymatrix_extend_navigation_module($naviagtion, $cm) {
	$url = new moodle_url('/report/privacymatrix/index.php', array('id' => $cm->course));
	$navigation->add(get_string('pluginname', 'report_privacymatrix'), $url);
}*/
