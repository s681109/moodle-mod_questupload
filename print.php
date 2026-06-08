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
 * The main page to print a questupload.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

$qid = required_param('qid', PARAM_INT);
$rid = required_param('rid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sec = required_param('sec', PARAM_INT);
$null = null;
$referer = $CFG->wwwroot.'/mod/questupload/report.php';

if (! $questupload = $DB->get_record("questupload", array("id" => $qid))) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}
if (! $course = $DB->get_record("course", array("id" => $questupload->course))) {
    throw new \moodle_exception('coursemisconf', 'mod_questupload');
}
if (! $cm = get_coursemodule_from_instance("questupload", $questupload->id, $course->id)) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

// Check login and get context.
require_login($courseid);

$questupload = new questupload($course, $cm, 0, $questupload);

// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
if (!empty($rid)) {
    $questupload->add_page(new \mod_questupload\output\reportpage());
} else {
    $questupload->add_page(new \mod_questupload\output\previewpage());
}

// If you can't view the questupload, or can't view a specified response, error out.
if (!($questupload->capabilities->view && (($rid == 0) || $questupload->can_view_response($rid)))) {
    // Should never happen, unless called directly by a snoop...
    throw new \moodle_exception('nopermissions', 'mod_questupload');
}
$blankquestupload = true;
if ($rid != 0) {
    $blankquestupload = false;
}
$url = new moodle_url($CFG->wwwroot.'/mod/questupload/print.php');
$url->param('qid', $qid);
$url->param('rid', $rid);
$url->param('courseid', $courseid);
$url->param('sec', $sec);
$PAGE->set_url($url);
$PAGE->set_title($questupload->survey->title);
$PAGE->set_pagelayout('popup');
echo $questupload->renderer->header();
$questupload->page->add_to_page('closebutton', $questupload->renderer->close_window_button());
$questupload->survey_print_render($courseid, '', 'print', $rid, $blankquestupload);
echo $questupload->renderer->render($questupload->page);
echo $questupload->renderer->footer();
