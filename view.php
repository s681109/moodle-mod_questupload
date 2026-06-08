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
 * This main view page for a questupload.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$SESSION->questupload->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // Or questupload ID.

$sid = optional_param('sid', null, PARAM_INT);  // Survey id.

list($cm, $course, $questupload) = questupload_get_standard_page_items($id, $a);

// Check login and get context.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/questupload/view.php');
if (isset($id)) {
    $url->param('id', $id);
} else {
    $url->param('a', $a);
}
if (isset($sid)) {
    $url->param('sid', $sid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$questupload = new questupload($course, $cm, 0, $questupload);
// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
$questupload->add_page(new \mod_questupload\output\viewpage());

$PAGE->set_title(format_string($questupload->name));
$PAGE->set_heading(format_string($course->fullname));

echo $questupload->renderer->header();
// No need to print out intro or name in Moodle 4 and above.

$cm = $questupload->cm;
$currentgroupid = groups_get_activity_group($cm);
if (!groups_is_member($currentgroupid, $USER->id)) {
    $currentgroupid = 0;
}

$message = $questupload->user_access_messages($USER->id);
if ($message !== false) {
    $questupload->page->add_to_page('message', $message);
} else if ($questupload->user_can_take($USER->id)) {
    if ($questupload->questions) { // Sanity check.
        if (!$questupload->user_has_saved_response($USER->id)) {
            $questupload->page->add_to_page('complete',
                '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questupload/complete.php?' .
                'id=' . $questupload->cm->id) . '" class="btn btn-primary">' .
                get_string('answerquestions', 'questupload') . '</a>');
        } else {
            $resumesurvey = get_string('resumesurvey', 'questupload');
            $questupload->page->add_to_page('complete',
                '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questupload/complete.php?' .
                'id='.$questupload->cm->id.'&resume=1').'" title="'.$resumesurvey.
                '" class="btn btn-primary">'.$resumesurvey.'</a>');
        }
    } else {
        $questupload->page->add_to_page('message', get_string('noneinuse', 'questupload'));
    }
}

if ($questupload->capabilities->editquestions && !$questupload->questions && $questupload->is_active()) {
    $questupload->page->add_to_page('complete',
        '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questupload/questions.php?'.
        'id=' . $questupload->cm->id) . '" class="btn btn-primary">' .
        get_string('addquestions', 'questupload') . '</a>');
}

// Time zone message (if required).
if (!$message && $questupload->is_open() && !$questupload->is_closed()) {
    $info = $questupload->view_information();
    $questupload->page->add_to_page('info', $questupload->access_messages($info));
}

if (isguestuser()) {
    $guestno = html_writer::tag('p', get_string('noteligible', 'questupload'));
    $liketologin = html_writer::tag('p', get_string('liketologin'));
    $questupload->page->add_to_page('guestuser',
        $questupload->renderer->confirm($guestno."\n\n".$liketologin."\n", get_login_url(), get_local_referer(false)));
}

// Log this course module view.
// Needed for the event logging.
$context = context_module::instance($questupload->cm->id);
$anonymous = $questupload->respondenttype == 'anonymous';

$event = \mod_questupload\event\course_module_viewed::create(array(
                'objectid' => $questupload->id,
                'anonymous' => $anonymous,
                'context' => $context
));
$event->trigger();

$usernumresp = $questupload->count_submissions($USER->id);

if ($questupload->capabilities->readownresponses && ($usernumresp > 0)) {
    $argstr = 'instance='.$questupload->id.'&user='.$USER->id;
    if ($usernumresp > 1) {
        $titletext = get_string('viewyourresponses', 'questupload', $usernumresp);
    } else {
        $titletext = get_string('yourresponse', 'questupload');
        $argstr .= '&byresponse=1&action=vresp';
    }
    $questupload->page->add_to_page('yourresponse',
        '<a href="' .$CFG->wwwroot.htmlspecialchars('/mod/questupload/myreport.php?' . $argstr).
        '" class="btn btn-primary">' . $titletext . '</a>');
}

if ($questupload->can_view_all_responses($usernumresp)) {
    $argstr = 'instance='.$questupload->id.'&group='.$currentgroupid;
    $questupload->page->add_to_page('allresponses',
        '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questupload/report.php?'.$argstr).'" class="btn btn-primary">'.
        get_string('viewallresponses', 'questupload').'</a>');
}

echo $questupload->renderer->render($questupload->page);
echo $questupload->renderer->footer();
