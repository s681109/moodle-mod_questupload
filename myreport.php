<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This page shows results of a questupload to a student.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

$instance = required_param('instance', PARAM_INT);   // Questionnaire Upload ID.
$userid = optional_param('user', $USER->id, PARAM_INT);
$rid = optional_param('rid', null, PARAM_INT);
$byresponse = optional_param('byresponse', 0, PARAM_INT);
$action = optional_param('action', 'summary', PARAM_ALPHA);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

if (! $questupload = $DB->get_record("questupload", array("id" => $instance))) {
    throw new \moodle_exception('incorrectquestupload', 'mod_questupload');
}
if (! $course = $DB->get_record("course", array("id" => $questupload->course))) {
    throw new \moodle_exception('coursemisconf', 'mod_questupload');
}
if (! $cm = get_coursemodule_from_instance("questupload", $questupload->id, $course->id)) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
$questupload->canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
// Should never happen, unless called directly by a snoop...
if ( !has_capability('mod/questupload:readownresponses', $context)
    || $userid != $USER->id) {
    throw new \moodle_exception('nopermissions', 'mod_questupload');
}
$url = new moodle_url($CFG->wwwroot.'/mod/questupload/myreport.php', array('instance' => $instance));
if (isset($userid)) {
    $url->param('userid', $userid);
}
if (isset($byresponse)) {
    $url->param('byresponse', $byresponse);
}

if (isset($currentgroupid)) {
    $url->param('group', $currentgroupid);
}

if (isset($action)) {
    $url->param('action', $action);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('questuploadreport', 'questupload'));
$PAGE->set_heading(format_string($course->fullname));

$questupload = new questupload($course, $cm, 0, $questupload);
// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
$questupload->add_page(new \mod_questupload\output\reportpage());

$sid = $questupload->survey->id;
$courseid = $course->id;

// Tab setup.
if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$SESSION->questupload->current_tab = 'myreport';

switch ($action) {
    case 'summary':
        if (empty($questupload->survey)) {
            throw new \moodle_exception('surveynotexists', 'mod_questupload');
        }
        $SESSION->questupload->current_tab = 'mysummary';
        $resps = $questupload->get_responses($userid);
        $rids = array_keys($resps);
        if (count($resps) > 1) {
            $titletext = get_string('myresponsetitle', 'questupload', count($resps));
        } else {
            $titletext = get_string('yourresponse', 'questupload');
        }

        // Print the page header.
        echo $questupload->renderer->header();

        // Print the tabs.
        include('tabs.php');

        $questupload->page->add_to_page('myheaders', $titletext);
        $questupload->survey_results($rids, $USER->id);

        echo $questupload->renderer->render($questupload->page);

        // Finish the page.
        echo $questupload->renderer->footer($course);
        break;

    case 'vall':
        if (empty($questupload->survey)) {
            throw new \moodle_exception('surveynotexists', 'mod_questupload');
        }
        $SESSION->questupload->current_tab = 'myvall';
        $questupload->add_user_responses($userid);
        $titletext = get_string('myresponses', 'questupload');

        // Print the page header.
        echo $questupload->renderer->header();

        // Print the tabs.
        include('tabs.php');

        $questupload->page->add_to_page('myheaders', $titletext);
        $questupload->view_all_responses();
        echo $questupload->renderer->render($questupload->page);
        // Finish the page.
        echo $questupload->renderer->footer($course);
        break;

    case 'vresp':
        if (empty($questupload->survey)) {
            throw new \moodle_exception('surveynotexists', 'mod_questupload');
        }
        $SESSION->questupload->current_tab = 'mybyresponse';
        $usergraph = get_config('questupload', 'usergraph');
        if ($usergraph) {
            $charttype = $questupload->survey->chart_type;
            if ($charttype) {
                $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.common.core.js');

                switch ($charttype) {
                    case 'bipolar':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.bipolar.js');
                        break;
                    case 'hbar':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.hbar.js');
                        break;
                    case 'radar':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.radar.js');
                        break;
                    case 'rose':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.rose.js');
                        break;
                    case 'vprogress':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.vprogress.js');
                        break;
                }
            }
        }
        $resps = $questupload->get_responses($userid);

        // All participants.
        $respsallparticipants = $questupload->get_responses();

        $respsuser = $questupload->get_responses($userid);

        $SESSION->questupload->numrespsallparticipants = count($respsallparticipants);
        $SESSION->questupload->numselectedresps = $SESSION->questupload->numrespsallparticipants;
        $iscurrentgroupmember = false;

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode > 0) {
            // Check if current user is member of any group.
            $usergroups = groups_get_user_groups($courseid, $userid);
            $isgroupmember = count($usergroups[0]) > 0;
            // Check if current user is member of current group.
            $iscurrentgroupmember = groups_is_member($currentgroupid, $userid);

            if ($groupmode == 1) {
                $questuploadgroups = groups_get_all_groups($course->id, $userid);
            }
            if ($groupmode == 2 || $questupload->canviewallgroups) {
                $questuploadgroups = groups_get_all_groups($course->id);
            }

            if (!empty($questuploadgroups)) {
                $groupscount = count($questuploadgroups);
                foreach ($questuploadgroups as $key) {
                    $firstgroupid = $key->id;
                    break;
                }
                if ($groupscount === 0 && $groupmode == 1) {
                    $currentgroupid = 0;
                }
                if ($groupmode == 1 && !$questupload->canviewallgroups && $currentgroupid == 0) {
                    $currentgroupid = $firstgroupid;
                }
                // If currentgroup is All Participants, current user is of course member of that "group"!
                if ($currentgroupid == 0) {
                    $iscurrentgroupmember = true;
                }
                // Current group members.
                $currentgroupresps = $questupload->get_responses(false, $currentgroupid);

            } else {
                // Groupmode = separate groups but user is not member of any group
                // and does not have moodle/site:accessallgroups capability -> refuse view responses.
                if (!$questupload->canviewallgroups) {
                    $currentgroupid = 0;
                }
            }

            if ($currentgroupid > 0) {
                $groupname = get_string('group').' <strong>'.groups_get_group_name($currentgroupid).'</strong>';
            } else {
                $groupname = '<strong>'.get_string('allparticipants').'</strong>';
            }
        }

        $rids = array_keys($resps);
        if (!$rid) {
            // If more than one response for this respondent, display most recent response.
            $rid = end($rids);
        }
        $numresp = count($rids);
        if ($numresp > 1) {
            $titletext = get_string('myresponsetitle', 'questupload', $numresp);
        } else {
            $titletext = get_string('yourresponse', 'questupload');
        }

        $compare = false;
        // Print the page header.
        echo $questupload->renderer->header();

        // Print the tabs.
        include('tabs.php');
        $questupload->page->add_to_page('myheaders', $titletext);

        if (count($resps) > 1) {
            $userresps = $resps;
            $questupload->survey_results_navbar_student ($rid, $userid, $instance, $userresps);
        }
        $resps = array();
        // Determine here which "global" responses should get displayed for comparison with current user.
        // Current user is viewing his own group's results.
        if (isset($currentgroupresps)) {
            $resps = $currentgroupresps;
        }

        // Current user is viewing another group's results so we must add their own results to that group's results.

        if (!$iscurrentgroupmember) {
            $resps += $respsuser;
        }
        // No groups.
        if ($groupmode == 0 || $currentgroupid == 0) {
            $resps = $respsallparticipants;
        }
        $compare = true;
        $questupload->view_response($rid, null, $resps, $compare, $iscurrentgroupmember, false, $currentgroupid);
        // Finish the page.
        echo $questupload->renderer->render($questupload->page);
        echo $questupload->renderer->footer($course);
        break;

    case get_string('return', 'questupload'):
    default:
        redirect('view.php?id='.$cm->id);
}
