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
 * This page displays a non-completable instance of questupload.
 *
 * @package    mod_questupload
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

$id = optional_param('id', 0, PARAM_INT);
$sid = optional_param('sid', 0, PARAM_INT);
$popup = optional_param('popup', 0, PARAM_INT);
$qid = optional_param('qid', 0, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

if ($id) {
    if (! $cm = get_coursemodule_from_id('questupload', $id)) {
        throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        throw new \moodle_exception('coursemisconf', 'mod_questupload');
    }

    if (! $questupload = $DB->get_record("questupload", array("id" => $cm->instance))) {
        throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
    }
} else {
    if (! $survey = $DB->get_record("questupload_survey", array("id" => $sid))) {
        throw new \moodle_exception('surveynotexists', 'mod_questupload');
    }
    if (! $course = $DB->get_record("course", ["id" => $survey->courseid])) {
        throw new \moodle_exception('coursemisconf', 'mod_questupload');
    }
    // Dummy questupload object.
    $questupload = new stdClass();
    $questupload->id = 0;
    $questupload->course = $course->id;
    $questupload->name = $survey->title;
    $questupload->sid = $sid;
    $questupload->resume = 0;
    // Dummy cm object.
    if (!empty($qid)) {
        $cm = get_coursemodule_from_instance('questupload', $qid, $course->id);
    } else {
        $cm = false;
    }
}

// Check login and get context.
// Do not require login if this questupload is viewed from the Add questupload page
// to enable teachers to view template or public questuploads located in a course where they are not enroled.
if (!$popup) {
    require_login($course->id, false, $cm);
}
$context = $cm ? context_module::instance($cm->id) : false;

$url = new moodle_url('/mod/questupload/preview.php');
if ($id !== 0) {
    $url->param('id', $id);
}
if ($sid) {
    $url->param('sid', $sid);
}
$PAGE->set_url($url);

$PAGE->set_context($context);
$PAGE->set_cm($cm);   // CONTRIB-5872 - I don't know why this is needed.

$questupload = new questupload($course, $cm, $qid, $questupload);

// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
$questupload->add_page(new \mod_questupload\output\previewpage());

$canpreview = (!isset($questupload->capabilities) &&
               has_capability('mod/questupload:preview', context_course::instance($course->id))) ||
              (isset($questupload->capabilities) && $questupload->capabilities->preview);
if (!$canpreview && !$popup) {
    // Should never happen, unless called directly by a snoop...
    throw new \moodle_exception('nopermissions', 'mod_questupload');
}

if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$SESSION->questupload->current_tab = new stdClass();
$SESSION->questupload->current_tab = 'preview';

$qp = get_string('preview_questupload', 'questupload');
$pq = get_string('previewing', 'questupload');

// Print the page header.
if ($popup) {
    $PAGE->set_pagelayout('popup');
}
$PAGE->set_title(format_string($qp));
if (!$popup) {
    $PAGE->set_heading(format_string($course->fullname));
}

// Include the needed js.


$PAGE->requires->js('/mod/questupload/module.js');
// Print the tabs.


echo $questupload->renderer->header();
if (!$popup) {
    require('tabs.php');
}
$questupload->page->add_to_page('heading', clean_text($pq));

if ($questupload->capabilities->printblank) {
    // Open print friendly as popup window.

    $linkname = '&nbsp;'.get_string('printblank', 'questupload');
    $title = get_string('printblanktooltip', 'questupload');
    $url = '/mod/questupload/print.php?qid='.$questupload->id.'&amp;rid=0&amp;'.'courseid='.
            $questupload->course->id.'&amp;sec=1';
    $options = array('menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
                    'height' => 600, 'width' => 800, 'title' => $title);
    $name = 'popup';
    $link = new moodle_url($url);
    $action = new popup_action('click', $link, $name, $options);
    $class = "floatprinticon";
    $questupload->page->add_to_page('printblank',
        $questupload->renderer->action_link($link, $linkname, $action, array('class' => $class, 'title' => $title),
            new pix_icon('t/print', $title)));
}
$questupload->survey_print_render($course->id, '', 'preview', $rid = 0, $popup);
if ($popup) {
    $questupload->page->add_to_page('closebutton', $questupload->renderer->close_window_button());
}
echo $questupload->renderer->render($questupload->page);
echo $questupload->renderer->footer($course);

// Log this questupload preview.
$context = context_module::instance($questupload->cm->id);
$anonymous = $questupload->respondenttype == 'anonymous';

$event = \mod_questupload\event\questupload_previewed::create(array(
                'objectid' => $questupload->id,
                'anonymous' => $anonymous,
                'context' => $context
));
$event->trigger();
