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
 * prints the tabbed bar
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $SESSION;
$tabs = array();
$row = array();
$inactive = array();
$activated = array();
if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$currenttab = $SESSION->questupload->current_tab;

// In a questupload instance created "using" a PUBLIC questupload, prevent anyone from editing settings, editing questions,
// viewing all responses...except in the course where that PUBLIC questupload was originally created.

$owner = $questupload->is_survey_owner();
if ($questupload->capabilities->manage  && $owner) {
    $row[] = new tabobject('settings', $CFG->wwwroot.htmlspecialchars('/mod/questupload/qsettings.php?'.
            'id='.$questupload->cm->id), get_string('advancedsettings'));
}

if ($questupload->capabilities->editquestions && $owner) {
    $row[] = new tabobject('questions', $CFG->wwwroot.htmlspecialchars('/mod/questupload/questions.php?'.
            'id='.$questupload->cm->id), get_string('questions', 'questupload'));
}

if ($questupload->capabilities->editquestions && $owner) {
    $row[] = new tabobject('feedback', $CFG->wwwroot.htmlspecialchars('/mod/questupload/feedback.php?'.
            'id='.$questupload->cm->id), get_string('feedback'));
}

if ($questupload->capabilities->preview && $owner) {
    if (!empty($questupload->questions)) {
        $row[] = new tabobject('preview', $CFG->wwwroot.htmlspecialchars('/mod/questupload/preview.php?'.
                        'id='.$questupload->cm->id), get_string('preview_label', 'questupload'));
    }
}

$usernumresp = $questupload->count_submissions($USER->id);

if ($questupload->capabilities->readownresponses && ($usernumresp > 0)) {
    $argstr = 'instance='.$questupload->id.'&user='.$USER->id.'&group='.$currentgroupid;
    if ($usernumresp == 1) {
        $argstr .= '&byresponse=1&action=vresp';
        $yourrespstring = get_string('yourresponse', 'questupload');
    } else {
        $yourrespstring = get_string('yourresponses', 'questupload');
    }
    $row[] = new tabobject('myreport', $CFG->wwwroot.htmlspecialchars('/mod/questupload/myreport.php?'.
                           $argstr), $yourrespstring);

    if ($usernumresp > 1 && in_array($currenttab, array('mysummary', 'mybyresponse', 'myvall', 'mydownloadcsv'))) {
        $inactive[] = 'myreport';
        $activated[] = 'myreport';
        $row2 = array();
        $argstr2 = $argstr.'&action=summary';
        $row2[] = new tabobject('mysummary', $CFG->wwwroot.htmlspecialchars('/mod/questupload/myreport.php?'.$argstr2),
                                get_string('summary', 'questupload'));
        $argstr2 = $argstr.'&byresponse=1&action=vresp';
        $row2[] = new tabobject('mybyresponse', $CFG->wwwroot.htmlspecialchars('/mod/questupload/myreport.php?'.$argstr2),
                                get_string('viewindividualresponse', 'questupload'));
        $argstr2 = $argstr.'&byresponse=0&action=vall&group='.$currentgroupid;
        $row2[] = new tabobject('myvall', $CFG->wwwroot.htmlspecialchars('/mod/questupload/myreport.php?'.$argstr2),
                                get_string('myresponses', 'questupload'));
        if ($questupload->capabilities->downloadresponses) {
            $argstr2 = $argstr.'&action=dwnpg';
            $link = $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2);
            $row2[] = new tabobject('mydownloadcsv', $link, get_string('downloadtextformat', 'questupload'));
        }
    } else if (in_array($currenttab, array('mybyresponse', 'mysummary'))) {
        $inactive[] = 'myreport';
        $activated[] = 'myreport';
    }
}

$numresp = $questupload->count_submissions();
// Number of responses in currently selected group (or all participants etc.).
if (isset($SESSION->questupload->numselectedresps)) {
    $numselectedresps = $SESSION->questupload->numselectedresps;
} else {
    $numselectedresps = $numresp;
}

// If questupload is set to separate groups, prevent user who is not member of any group
// to view All responses.
$canviewgroups = true;
$groupmode = groups_get_activity_groupmode($cm, $course);
if ($groupmode == 1) {
    $canviewgroups = groups_has_membership($cm, $USER->id);
}
$canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
$grouplogic = $canviewallgroups || $canviewgroups;
$resplogic = ($numresp > 0) && ($numselectedresps > 0);

if ($questupload->can_view_all_responses_anytime($grouplogic, $resplogic)) {
    $argstr = 'instance='.$questupload->id;
    $row[] = new tabobject('allreport', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.
                           $argstr.'&action=vall'), get_string('viewallresponses', 'questupload'));
    if (in_array($currenttab, array('vall', 'vresp', 'valldefault', 'vallasort', 'vallarsort', 'deleteall', 'downloadcsv',
                                     'vrespsummary', 'individualresp', 'printresp', 'deleteresp'))) {
        $inactive[] = 'allreport';
        $activated[] = 'allreport';
        if ($currenttab == 'vrespsummary' || $currenttab == 'valldefault') {
            $inactive[] = 'vresp';
        }
        $row2 = array();
        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row2[] = new tabobject('vall', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                get_string('summary', 'questupload'));
        if ($questupload->capabilities->viewsingleresponse) {
            $argstr2 = $argstr.'&byresponse=1&action=vresp&group='.$currentgroupid;
            $row2[] = new tabobject('vrespsummary', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                get_string('viewbyresponse', 'questupload'));
            if ($currenttab == 'individualresp' || $currenttab == 'deleteresp') {
                $argstr2 = $argstr.'&byresponse=1&action=vresp';
                $row2[] = new tabobject('vresp', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                        get_string('viewindividualresponse', 'questupload'));
            }
        }
    }
    if (in_array($currenttab, array('valldefault',  'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'))) {
        $activated[] = 'vall';
        $row3 = array();

        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row3[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                get_string('order_default', 'questupload'));
        if ($currenttab != 'downloadcsv' && $currenttab != 'deleteall') {
            $argstr2 = $argstr.'&action=vallasort&group='.$currentgroupid;
            $row3[] = new tabobject('vallasort', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                    get_string('order_ascending', 'questupload'));
            $argstr2 = $argstr.'&action=vallarsort&group='.$currentgroupid;
            $row3[] = new tabobject('vallarsort', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                    get_string('order_descending', 'questupload'));
        }
        if ($questupload->capabilities->deleteresponses) {
            $argstr2 = $argstr.'&action=delallresp&group='.$currentgroupid;
            $row3[] = new tabobject('deleteall', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                    get_string('deleteallresponses', 'questupload'));
        }

        if ($questupload->capabilities->downloadresponses) {
            $argstr2 = $argstr.'&action=dwnpg&group='.$currentgroupid;
            $link = $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2);
            $row3[] = new tabobject('downloadcsv', $link, get_string('downloadtextformat', 'questupload'));
        }
    }

    if (in_array($currenttab, array('individualresp', 'deleteresp'))) {
        $inactive[] = 'vresp';
        if ($currenttab != 'deleteresp') {
            $activated[] = 'vresp';
        }
        if ($questupload->capabilities->deleteresponses) {
            $argstr2 = $argstr.'&action=dresp&rid='.$rid.'&individualresponse=1';
            $row2[] = new tabobject('deleteresp', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                            get_string('deleteresp', 'questupload'));
        }

    }
} else if ($questupload->can_view_all_responses_with_restrictions($usernumresp, $grouplogic, $resplogic)) {
    $argstr = 'instance='.$questupload->id.'&sid='.$questupload->sid;
    $row[] = new tabobject('allreport', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.
                           $argstr.'&action=vall&group='.$currentgroupid), get_string('viewallresponses', 'questupload'));
    if (in_array($currenttab, array('valldefault',  'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'))) {
        $inactive[] = 'vall';
        $activated[] = 'vall';
        $row2 = array();
        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row2[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                get_string('summary', 'questupload'));
        $inactive[] = $currenttab;
        $activated[] = $currenttab;
        $row3 = array();
        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row3[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                get_string('order_default', 'questupload'));
        $argstr2 = $argstr.'&action=vallasort&group='.$currentgroupid;
        $row3[] = new tabobject('vallasort', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                get_string('order_ascending', 'questupload'));
        $argstr2 = $argstr.'&action=vallarsort&group='.$currentgroupid;
        $row3[] = new tabobject('vallarsort', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                get_string('order_descending', 'questupload'));
        if ($questupload->capabilities->deleteresponses) {
            $argstr2 = $argstr.'&action=delallresp';
            $row2[] = new tabobject('deleteall', $CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr2),
                                    get_string('deleteallresponses', 'questupload'));
        }

        if ($questupload->capabilities->downloadresponses) {
            $argstr2 = $argstr.'&action=dwnpg';
            $link = htmlspecialchars('/mod/questupload/report.php?'.$argstr2);
            $row2[] = new tabobject('downloadcsv', $link, get_string('downloadtextformat', 'questupload'));
        }
        if (count($row2) <= 1) {
            $currenttab = 'allreport';
        }
    }
}

if ($questupload->capabilities->viewsingleresponse && ($canviewallgroups || $canviewgroups)) {
    $nonrespondenturl = new moodle_url('/mod/questupload/show_nonrespondents.php', array('id' => $questupload->cm->id));
    $row[] = new tabobject('nonrespondents',
                    $nonrespondenturl->out(),
                    get_string('show_nonrespondents', 'questupload'));
}

if ((count($row) > 1) || (!empty($row2) && (count($row2) > 1))) {
    $tabs[] = $row;

    if (!empty($row2) && (count($row2) > 1)) {
        $tabs[] = $row2;
    }

    if (!empty($row3) && (count($row3) > 1)) {
        $tabs[] = $row3;
    }

    $questupload->page->add_to_page('tabsarea', print_tabs($tabs, $currenttab, $inactive, $activated, true));
}
