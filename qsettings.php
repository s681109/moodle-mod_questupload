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
 * This page handles the question settings.
 *
 * @package    mod_questupload
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

$id = required_param('id', PARAM_INT);    // Course module ID.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$cancel = optional_param('cancel', '', PARAM_ALPHA);
$submitbutton2 = optional_param('submitbutton2', '', PARAM_ALPHA);

if (! $cm = get_coursemodule_from_id('questupload', $id)) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    throw new \moodle_exception('coursemisconf', 'mod_questupload');
}

if (! $questupload = $DB->get_record("questupload", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/questupload/qsettings.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_context($context);
if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$questupload = new questupload($course, $cm, 0, $questupload);

// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
$questupload->add_page(new \mod_questupload\output\qsettingspage());

$SESSION->questupload->current_tab = 'settings';

if (!$questupload->capabilities->manage) {
    throw new \moodle_exception('nopermissions', 'mod_questupload');
}

$settingsform = new \mod_questupload\settings_form('qsettings.php');
$sdata = clone($questupload->survey);
$sdata->sid = $questupload->survey->id;
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('info');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_questupload', 'info',
                $sdata->sid, array('subdirs' => true), $questupload->survey->info);
$sdata->info = array('text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor);

$draftideditor = file_get_submitted_draft_itemid('thankbody');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_questupload', 'thankbody',
                $sdata->sid, array('subdirs' => true), $questupload->survey->thank_body);
$sdata->thank_body = array('text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor);

$settingsform->set_data($sdata);

if ($settingsform->is_cancelled()) {
    redirect ($CFG->wwwroot.'/mod/questupload/view.php?id='.$questupload->cm->id, '');
}

if ($settings = $settingsform->get_data()) {
    $sdata = new stdClass();
    $sdata->id = $settings->sid;
    $sdata->name = $settings->name;
    $sdata->realm = $settings->realm;
    $sdata->title = $settings->title;
    $sdata->subtitle = $settings->subtitle;

    $sdata->infoitemid = $settings->info['itemid'];
    $sdata->infoformat = $settings->info['format'];
    $sdata->info = $settings->info['text'];
    $sdata->info = file_save_draft_area_files($sdata->infoitemid, $context->id, 'mod_questupload', 'info',
                                              $sdata->id, array('subdirs' => true), $sdata->info);

    $sdata->theme = ''; // Deprecated theme field.
    $sdata->thanks_page = $settings->thanks_page;
    $sdata->thank_head = $settings->thank_head;

    $sdata->thankitemid = $settings->thank_body['itemid'];
    $sdata->thankformat = $settings->thank_body['format'];
    $sdata->thank_body = $settings->thank_body['text'];
    $sdata->thank_body = file_save_draft_area_files($sdata->thankitemid, $context->id, 'mod_questupload', 'thankbody',
                                                    $sdata->id, array('subdirs' => true), $sdata->thank_body);
    $sdata->email = $settings->email;

    $sdata->courseid = $settings->courseid;
    if (!($sid = $questupload->survey_update($sdata))) {
        throw new \moodle_exception('couldnotcreatenewsurvey', 'mod_questupload');
    } else {
        if ($submitbutton2) {
            $redirecturl = course_get_url($cm->course);
        } else {
            $redirecturl = $CFG->wwwroot.'/mod/questupload/view.php?id='.$questupload->cm->id;
        }

        // Save current advanced settings only.
        if (isset($settings->submitbutton) || isset($settings->submitbutton2)) {
            redirect ($redirecturl, get_string('settingssaved', 'questupload'));
        }
    }
}

// Print the page header.
$PAGE->set_title(get_string('editingquestupload', 'questupload'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingquestupload', 'questupload'));
echo $questupload->renderer->header();
require('tabs.php');
$questupload->page->add_to_page('formarea', $settingsform->render());
echo $questupload->renderer->render($questupload->page);
echo $questupload->renderer->footer($course);
