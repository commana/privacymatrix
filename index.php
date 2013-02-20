<?php

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/roles/lib.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

require_login($course);

$url = new moodle_url('/report/privacymatrix/index.php', array('id'=>$course->id));
$PAGE->set_url($url);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('what_do_we_know', 'report_privacymatrix').":");

$logactions = array();
$mylogs = build_logs_array($course, $USER->id);
if ($mylogs['totalcount'] > 0) {
	foreach ($mylogs['logs'] as $log) {
		$logactions[] = $log->module . " =&gt; " . $log->action;
	}
}

$logactions = array_unique($logactions);
//var_dump(get_capability_info('report/log:view'));
//var_dump(get_user_accessdata($USER->id, true));
//var_dump(role_fix_names(get_all_roles(), $systemcontext, ROLENAME_ORIGINAL));
//var_dump($ACCESSLIB_PRIVATE->accessdatabyuser[$USER->id]);
//$systemcontext = context_system::instance();
//var_dump(fetch_context_capabilities($systemcontext));
//$roleid=1;
//$definitiontable = new view_role_definition_table($systemcontext, $roleid);
//$definitiontable->read_submitted_permissions();
//$definitiontable->display();
//var_dump($context);
//var_dump($mylogs);

////////

class Capa {
public $systemcontext = null;
public $contexts = null;
public $allroles = null;
public $capability = "";
function __construct($capability) {
	global $DB, $OUTPUT;
	$this->capability = $capability;
$systemcontext = context_system::instance();
$roleids = array(0);
$allroles = role_fix_names(get_all_roles());
$cleanedroleids = array_keys($allroles);

    // Work out the bits needed for the SQL WHERE clauses.
    $params = array($capability);
    $sqlroletest = '';
    if (count($cleanedroleids) != count($allroles)) {
        list($sqlroletest, $roleparams) = $DB->get_in_or_equal($cleanedroleids);
        $params = array_merge($params, $roleparams);
        $sqlroletest = 'AND roleid ' . $sqlroletest;
    }

    // Get all the role_capabilities rows for this capability - that is, all
    // role definitions, and all role overrides.
    $rolecaps = $DB->get_records_sql("
SELECT id, roleid, contextid, permission
FROM {role_capabilities}
WHERE capability = ? $sqlroletest", $params);

    // In order to display a nice tree of contexts, we need to get all the
    // ancestors of all the contexts in the query we just did.
    $relevantpaths = $DB->get_records_sql_menu("
SELECT DISTINCT con.path, 1
FROM {context} con JOIN {role_capabilities} rc ON rc.contextid = con.id
WHERE capability = ? $sqlroletest", $params);
    $requiredcontexts = array($systemcontext->id);
    foreach ($relevantpaths as $path => $notused) {
        $requiredcontexts = array_merge($requiredcontexts, explode('/', trim($path, '/')));
    }
    $requiredcontexts = array_unique($requiredcontexts);

    // Now load those contexts.
    list($sqlcontexttest, $contextparams) = $DB->get_in_or_equal($requiredcontexts);
    $contexts = get_sorted_contexts('ctx.id ' . $sqlcontexttest, $contextparams);

    // Prepare some empty arrays to hold the data we are about to compute.
    foreach ($contexts as $conid => $con) {
        $contexts[$conid]->children = array();
        $contexts[$conid]->rolecapabilities = array();
    }

    // Put the contexts into a tree structure.
    foreach ($contexts as $conid => $con) {
        $context = context::instance_by_id($conid);
        $parentcontextid = get_parent_contextid($context);
        if ($parentcontextid) {
            $contexts[$parentcontextid]->children[] = $conid;
        }
    }

    // Put the role capabilities into the context tree.
    foreach ($rolecaps as $rolecap) {
        $contexts[$rolecap->contextid]->rolecapabilities[$rolecap->roleid] = $rolecap->permission;
    }

    // Fill in any missing rolecaps for the system context.
    foreach ($cleanedroleids as $roleid) {
        if (!isset($contexts[$systemcontext->id]->rolecapabilities[$roleid])) {
            $contexts[$systemcontext->id]->rolecapabilities[$roleid] = CAP_INHERIT;
        }
    }

    // Print the report heading.
    //echo $OUTPUT->heading(get_string('reportforcapability', 'report_privacymatrix', get_capability_string($capability)), 2, 'main', 'report');
    if (count($cleanedroleids) != count($allroles)) {
        $rolenames = array();
        foreach ($cleanedroleids as $roleid) {
            $rolenames[] = $allroles[$roleid]->localname;
        }
        //echo '<p>', get_string('forroles', 'tool_capability', implode(', ', $rolenames)), '</p>';
    }

    // Now, recursively print the contexts, and the role information.
    //print_report_tree($systemcontext->id, $contexts, $allroles);
    $this->systemcontext = $systemcontext;
    $this->contexts = $contexts;
    $this->allroles = $allroles;
}

function get_capa_string($contextid, $contexts, $role) {
    global $CFG;

    // Array for holding lang strings.
    static $strpermissions = null;
    if (is_null($strpermissions)) {
        $strpermissions = array(
            CAP_INHERIT => get_string('notset','role'),
            CAP_ALLOW => get_string('allow','role'),
            CAP_PREVENT => get_string('prevent','role'),
            CAP_PROHIBIT => get_string('prohibit','role')
        );
    }

    // Start the list item, and print the context name as a link to the place to
    // make changes.
    /*if ($contextid == get_system_context()->id) {
        $url = "$CFG->wwwroot/$CFG->admin/roles/manage.php";
        $title = get_string('changeroles', 'tool_capability');
    } else {
        $url = "$CFG->wwwroot/$CFG->admin/roles/override.php?contextid=$contextid";
        $title = get_string('changeoverrides', 'tool_capability');
    }*/
    $context = context::instance_by_id($contextid);
    //echo '<h3><a href="' . $url . '" title="' . $title . '">', $context->get_context_name(), '</a></h3>';

    $permission = null;
    // If there are any role overrides here, print them.
    if (!empty($contexts[$contextid]->rolecapabilities)) {
        if (isset($contexts[$contextid]->rolecapabilities[$role->id])) {
            $permission = $contexts[$contextid]->rolecapabilities[$role->id];
        }
    }

    return $strpermissions[$permission]; // if a capability is "not set", it essentially means CAP_PROHIBIT!
    
    // After we have done the site context, change the string for CAP_INHERIT
    // from 'notset' to 'inherit'.
    $strpermissions[CAP_INHERIT] = get_string('inherit','role');

    // If there are any child contexts, print them recursively.
    if (!empty($contexts[$contextid]->children)) {
        var_dump($contexts[$contextid]->children);
        foreach ($contexts[$contextid]->children as $childcontextid) {
            //print_report_tree($childcontextid, $contexts, $allroles);
        }
    }
    
}
}
////////

/*

1) What are the base permissions in the system's context?
    a) What is the default permission if it is "not set"?
       - has_capability(..) will return false if it has not been set to ALLOW

2) Leftmost column needs something like has_role_capability('report/log:view', ...)

*/

$systemcontext = context_system::instance();
$roles = role_fix_names(get_all_roles(), $systemcontext);
$c = new capa("report/log:view");
$roleheader = array('&nbsp;');
foreach ($roles as $role) {
	$roleheader[] = $role->localname;
}

echo "<table><tr><th>", implode('</th><th>', $roleheader), "</th></tr>";
$rows = array();
foreach ($logactions as $action) {
	$cells = array($action);
	for ($i = 1; $i <= count($roles); $i++) {
		$cells[] = $c->get_capa_string($c->systemcontext->id, $c->contexts, $roles[$i]);
	}
	$rows[] = $cells;
}

$strrows = array();
foreach ($rows as $row) {
	$strrows[] = '<td>' . implode('</td><td>', $row) . '</td>';
}
$strtable = '<tr>' . implode('</tr><tr>', $strrows) . '</tr>';

echo $strtable;

echo "</table>";

echo $OUTPUT->footer();

