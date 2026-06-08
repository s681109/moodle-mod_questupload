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
 * This page prints a particular instance of questupload.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once("../../config.php");
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$SESSION->questupload->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // Questionnaire Upload ID.

$sid = optional_param('sid', null, PARAM_INT);  // Survey id.
$resume = optional_param('resume', null, PARAM_INT);    // Is this attempt a resume of a saved attempt?

list($cm, $course, $questupload) = questupload_get_standard_page_items($id, $a);

// Check login and get context.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/questupload:view', $context);

$url = new moodle_url($CFG->wwwroot.'/mod/questupload/complete.php');
if (isset($id)) {
    $url->param('id', $id);
} else {
    $url->param('a', $a);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$questupload = new questupload( $course, $cm, 0, $questupload);
// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
$questupload->add_page(new \mod_questupload\output\completepage());

$questupload->strquestuploads = get_string("modulenameplural", "questupload");
$questupload->strquestupload = get_string("modulename", "questupload");

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

if ($resume) {
    $context = context_module::instance($questupload->cm->id);
    $anonymous = $questupload->respondenttype == 'anonymous';

    $event = \mod_questupload\event\attempt_resumed::create(array(
                    'objectid' => $questupload->id,
                    'anonymous' => $anonymous,
                    'context' => $context
    ));
    $event->trigger();
}

// Generate the view HTML in the page.
$questupload->view();

// Output the page.
echo $questupload->renderer->header();
echo $questupload->renderer->render($questupload->page);
echo $questupload->renderer->footer($course);
