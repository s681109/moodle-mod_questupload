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
 * Manage feedback sections.
 *
 * @package mod_questupload
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Joseph Rezeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

$id = required_param('id', PARAM_INT);    // Course module ID.
$section = optional_param('section', 1, PARAM_INT);
if ($section == 0) {
    $section = 1;
}
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$action = optional_param('action', '', PARAM_ALPHA);
$sectionid = optional_param('sectionid', 0, PARAM_INT);

if (! $cm = get_coursemodule_from_id('questupload', $id)) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

if (! $course = $DB->get_record("course", ["id" => $cm->course])) {
    throw new \moodle_exception('coursemisconf', 'mod_questupload');
}

if (! $questupload = $DB->get_record("questupload", ["id" => $cm->instance])) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/questupload/fbsections.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}

$questupload = new questupload($course, $cm, 0, $questupload);

if (!$questupload->capabilities->editquestions) {
    throw new \moodle_exception('nopermissions', 'mod_questupload');
}

if ($sectionid) {
    // Get the specified section by its id.
    $feedbacksection = new mod_questupload\feedback\section($questupload->questions, ['id' => $sectionid]);

} else if (!$DB->count_records('questupload_fb_sections', ['surveyid' => $questupload->sid])) {
    // There are no sections currently, so create one.
    if ($questupload->survey->feedbacksections == 1) {
        $sectionlabel = get_string('feedbackglobal', 'questupload');
    } else {
        $sectionlabel = get_string('feedbackdefaultlabel', 'questupload');
    }
    $feedbacksection = mod_questupload\feedback\section::new_section($questupload->sid, $sectionlabel);

} else {
    // Get the specified section by section number.
    $feedbacksection = new mod_questupload\feedback\section($questupload->questions,
        ['surveyid' => $questupload->survey->id, 'sectionnum' => $section]);
}

// Get all questions that are valid feedback questions.
$validquestions = [];
foreach ($questupload->questions as $question) {
    if ($question->valid_feedback()) {
        $validquestions[$question->id] = $question->name;
    }
}

// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
$questupload->add_page(new \mod_questupload\output\feedbackpage());

$SESSION->questupload->current_tab = 'feedback';

// Handle confirmed actions that impact display immediately.
if ($action == 'removequestion') {
    require_sesskey();
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    $feedbacksection->remove_question($qid);

} else if ($action == 'deletesection') {
    require_sesskey();
    $sectionid = required_param('sectionid', PARAM_INT);
    if ($sectionid == $feedbacksection->id) {
        $feedbacksection->delete();
        redirect(new moodle_url('/mod/questupload/fbsections.php', ['id' => $cm->id]));
    }
}

$customdata = new stdClass();
$customdata->feedbacksection = $feedbacksection;
$customdata->validquestions = $validquestions;
$customdata->survey = $questupload->survey;
$customdata->sectionselect = $DB->get_records_menu('questupload_fb_sections', ['surveyid' => $questupload->survey->id],
    'section', 'id,sectionlabel');

$feedbackform = new \mod_questupload\feedback_section_form('fbsections.php', $customdata);
$sdata = clone($feedbacksection);
$sdata->sid = $questupload->survey->id;
$sdata->sectionid = $feedbacksection->id;
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('sectionheading');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_questupload', 'sectionheading',
    $feedbacksection->id, ['subdirs' => true], $feedbacksection->sectionheading);
$sdata->sectionheading = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url('/mod/questupload/feedback.php', ['id' => $cm->id]));
}

if ($settings = $feedbackform->get_data()) {
    // Because formslib doesn't support 'numeric' or 'image' inputs, the results won't show up in the $feedbackform object.
    $fullform = data_submitted();

    if (isset($settings->gotosection)) {
        if ($settings->navigatesections != $feedbacksection->id) {
            redirect(new moodle_url('/mod/questupload/fbsections.php',
                ['id' => $cm->id, 'sectionid' => $settings->navigatesections]));
        }

    } else if (isset($settings->addnewsection)) {
        $newsection = mod_questupload\feedback\section::new_section($questupload->survey->id, $settings->newsectionlabel);
        redirect(new moodle_url('/mod/questupload/fbsections.php', ['id' => $cm->id, 'sectionid' => $newsection->id]));

    } else if (isset($fullform->confirmdeletesection)) {
        redirect(new moodle_url('/mod/questupload/fbsections.php',
            ['id' => $cm->id, 'sectionid' => $feedbacksection->id, 'action' => 'confirmdeletesection']));

    } else if (isset($fullform->confirmremovequestion)) {
        $qid = key($fullform->confirmremovequestion);
        redirect(new moodle_url('/mod/questupload/fbsections.php',
            ['id' => $cm->id, 'sectionid' => $settings->sectionid, 'action' => 'confirmremovequestion', 'qid' => $qid]));

    } else if (isset($settings->addquestion)) {
        $scorecalculation = [];
        // Check for added question.
        if (isset($settings->addquestionselect) && ($settings->addquestionselect != 0)) {
            if ($questupload->questions[$settings->addquestionselect]->supports_feedback_scores()) {
                $scorecalculation[$settings->addquestionselect] = 1;
            } else {
                $scorecalculation[$settings->addquestionselect] = -1;
            }
        }
        // Get all current asigned questions.
        if (isset($fullform->weight)) {
            foreach ($fullform->weight as $qid => $value) {
                $scorecalculation[$qid] = $value;
            }
        }
        // Update the section with question weights.
        $feedbacksection->set_new_scorecalculation($scorecalculation);

    } else if (isset($settings->submitbutton)) {
        if (isset($fullform->weight)) {
            $feedbacksection->scorecalculation = $fullform->weight;
        } else {
            $feedbacksection->scorecalculation = [];
        }
        $feedbacksection->sectionlabel = $settings->sectionlabel;
        $feedbacksection->sectionheading = file_save_draft_area_files((int)$settings->sectionheading['itemid'], $context->id,
            'mod_questupload', 'sectionheading', $feedbacksection->id, ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
            $settings->sectionheading['text']);
        $feedbacksection->sectionheadingformat = $settings->sectionheading['format'];

        // May have changed the section label and weights, so update the data.
        $customdata->sectionselect[$feedbacksection->id] = $settings->sectionlabel;
        if (isset($fullform->weight)) {
            $customdata->feedbacksection->scorecalculation = $fullform->weight;
        }

        // Save current section's feedbacks
        // first delete all existing feedbacks for this section - if any - because we never know whether editing feedbacks will
        // have more or less texts, so it's easiest to delete all and start afresh.
        $feedbacksection->delete_sectionfeedback();

        $i = 0;
        while (!empty($settings->feedbackboundaries[$i])) {
            $boundary = trim($settings->feedbackboundaries[$i]);
            if (strlen($boundary) > 0 && $boundary[strlen($boundary) - 1] == '%') {
                $boundary = trim(substr($boundary, 0, -1));
            }
            $settings->feedbackboundaries[$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;
        $settings->feedbackboundaries[-1] = 101;
        $settings->feedbackboundaries[$numboundaries] = 0;
        $settings->feedbackboundarycount = $numboundaries;

        // Now set up new section feedback records for each saved boundary.
        for ($i = 0; $i <= $settings->feedbackboundarycount; $i++) {
            $feedback = new stdClass();
            $feedback->sectionid = $feedbacksection->id;
            if (isset($settings->feedbacklabel[$i])) {
                $feedback->feedbacklabel = $settings->feedbacklabel[$i];
            } else {
                $feedback->feedbacklabel = null;
            }
            $feedback->feedbacktext = '';
            $feedback->feedbacktextformat = $settings->feedbacktext[$i]['format'];
            $feedback->minscore = $settings->feedbackboundaries[$i];
            $feedback->maxscore = $settings->feedbackboundaries[$i - 1];

            $fbid = $feedbacksection->load_sectionfeedback($feedback);

            $feedbacktext = file_save_draft_area_files((int)$settings->feedbacktext[$i]['itemid'],
                $context->id, 'mod_questupload', 'feedback', $fbid, ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
                $settings->feedbacktext[$i]['text']);
            $feedbacksection->sectionfeedback[$fbid]->feedbacktext = $feedbacktext;
        }

        // Update all feedback data.
        $feedbacksection->update();
    }
    $feedbackform = new \mod_questupload\feedback_section_form('fbsections.php', $customdata);
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'questupload'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'questupload'));
echo $questupload->renderer->header();
require('tabs.php');

// Handle confirmations differently.
if ($action == 'confirmremovequestion') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    $msgargs = new stdClass();
    $msgargs->qname = $questupload->questions[$qid]->name;
    $msgargs->sname = $feedbacksection->sectionlabel;
    $msg = '<div class="warning centerpara"><p>' . get_string('confirmremovequestion', 'questupload', $msgargs) . '</p></div>';
    $args = ['id' => $questupload->cm->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/questupload/fbsections.php', $args);
    $args['action'] = 'removequestion';
    $args['qid'] = $qid;
    $urlyes = new moodle_url('/mod/questupload/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'), 'post');
    $buttonno = new single_button($urlno, get_string('no'), 'get');
    $questupload->page->add_to_page('formarea', $questupload->renderer->confirm($msg, $buttonyes, $buttonno));

} else if ($action == 'confirmdeletesection') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $msg = '<div class="warning centerpara"><p>' .
        get_string('confirmdeletesection', 'questupload', $feedbacksection->sectionlabel) . '</p></div>';
    $args = ['id' => $questupload->cm->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/questupload/fbsections.php', $args);
    $args['action'] = 'deletesection';
    $urlyes = new moodle_url('/mod/questupload/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'), 'post');
    $buttonno = new single_button($urlno, get_string('no'), 'get');
    $questupload->page->add_to_page('formarea', $questupload->renderer->confirm($msg, $buttonyes, $buttonno));

} else {
    $questupload->page->add_to_page('formarea', $feedbackform->render());
}

echo $questupload->renderer->render($questupload->page);
echo $questupload->renderer->footer($course);
