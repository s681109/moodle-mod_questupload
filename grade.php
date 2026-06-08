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
 * Redirects the user to either a questupload or to the questupload report
 *
 * @package   mod_questupload
 * @copyright 2013 onwards Joseph Rézeau  email moodle@rezeau.org
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

/**
 * Require config.php
 */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('questupload', $id, 0, false, MUST_EXIST);
if (! $questupload = $DB->get_record("questupload", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_login($course, false, $cm);
$PAGE->set_url('/mod/questupload/grade.php', array('id' => $cm->id));

if (has_capability('mod/questupload:readallresponseanytime', context_module::instance($cm->id))) {
    redirect('report.php?instance='.$questupload->id);
} else {
    redirect('view.php?id='.$cm->id);
}
