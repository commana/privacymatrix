<?php

defined('MOODLE_INTERNAL') || die;

// Display inside "website administration" menu
$ADMIN->add('reports', new admin_externalpage('reportprivacymatrix', get_string('pluginname', 'report_privacymatrix'), "$CFG->wwwroot/report/privacymatrix/index.php"));

// no report settings
$settings = null;
