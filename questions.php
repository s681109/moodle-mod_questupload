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
 * This page handles the main question editing screen.
 *
 * @package    mod_questupload
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');
require_once($CFG->dirroot.'/mod/questupload/classes/question/question.php'); // Needed for question type constants.

$id = required_param('id', PARAM_INT);                 // Course module ID.
$action = optional_param('action', 'main', PARAM_ALPHA);   // Screen.
$qid = optional_param('qid', 0, PARAM_INT);             // Question id.
$moveq = optional_param('moveq', 0, PARAM_INT);           // Question id to move.
$delq = optional_param('delq', 0, PARAM_INT);             // Question id to delete.
$qtype = optional_param('type_id', 0, PARAM_INT);         // Question type.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.
$delpermanentlyq = optional_param('delpermanentlyq', 0, PARAM_INT); // Question id to delete.
$restoreq = optional_param(QUESTUPLOAD_RESTORE_PARAM, 0, PARAM_INT); // Question id to restore question.

if (! $cm = get_coursemodule_from_id('questupload', $id)) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    throw new \moodle_exception('coursemisconf', 'mod_questupload');
}

if (! $questupload = $DB->get_record("questupload", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/questupload/questions.php');
$url->param('id', $id);
if ($qid) {
    $url->param('qid', $qid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);

$questupload = new questupload($course, $cm, 0, $questupload);
$questupload->get_delete_questions();

// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
$questupload->add_page(new \mod_questupload\output\questionspage());

if (!$questupload->capabilities->editquestions) {
    throw new \moodle_exception('nopermissions', 'mod_questupload');
}

$questuploadhasdependencies = $questupload->has_dependencies();
$dependants = null;
if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$SESSION->questupload->current_tab = 'questions';
$reload = false;
$sid = $questupload->survey->id;
// Process form data.

// Delete question button has been pressed in questions_form AND deletion has been confirmed on the confirmation page.
if ($delq) {
    $qid = $delq;
    $sid = $questupload->survey->id;
    $questuploadid = $questupload->id;

    // Need to reload questions before setting deleted question to 'y'.
    $questions = $DB->get_records_select('questupload_question',
            'surveyid = :sid AND deleted IS NULL', ['sid' => $sid], 'id');
    if (isset($questions[$qid]) && $questions[$qid]->type_id == QUESPAGEBREAK) {
        $DB->delete_records('questupload_question', ['id' => $qid]);
    } else {
        $updatesql = "UPDATE {questupload_question}
                         SET deleted = ?
                       WHERE id = ?
                         AND surveyid = ?";
        $DB->execute($updatesql, [time(), $qid, $sid]);
    }

    // Delete all dependency records for this question.
    questupload_delete_dependencies($qid);
    // Delete all page break that references to question deleted.
    questupload_delete_pagebreaks($sid);

    // Just in case the page is refreshed (F5) after a question has been deleted.
    if (isset($questions[$qid])) {
        $select = 'surveyid = '.$sid.' AND deleted IS NULL AND position > '.
                        $questions[$qid]->position;
    } else {
        redirect($CFG->wwwroot.'/mod/questupload/questions.php?id='.$questupload->cm->id);
    }

    if ($records = $DB->get_records_select('questupload_question', $select, null, 'position ASC')) {
        foreach ($records as $record) {
            $DB->set_field('questupload_question', 'position', $record->position - 1, array('id' => $record->id));
        }
    }
    // Delete section breaks without asking for confirmation.
    // No need to delete responses to those "question types" which are not real questions.
    if (!$questupload->questions[$qid]->supports_responses()) {
        $reload = true;
    } else {
        // Delete responses to that deleted question.
        questupload_delete_responses($qid);

        // If no questions left in this questupload, remove all responses.
        if ($DB->count_records_select('questupload_question',
                        'surveyid = :sid AND deleted IS NULL', ['sid' => $sid]) == 0) {
            $DB->delete_records('questupload_response', ['questuploadid' => $qid]);
        }
    }

    // Log question deleted event.
    $questiontype = \mod_questupload\question\question::qtypename($questupload->questions[$qid]->type_id);
    questupload_observe_event_delete($questupload->cm->id, $questiontype, $questupload->course->id);

    if ($questuploadhasdependencies) {
        $SESSION->questupload->validateresults = questupload_check_page_breaks($questupload);
    }
    $reload = true;
}

// Delete question permanently.
if ($delpermanentlyq) {
    $qid = $delpermanentlyq;
    $sid = $questupload->survey->id;
    questupload_delete_permanently_questions($qid, $sid);
    $deletedquestion = $questupload->deletequestions[$qid] ?? null;
    if ($deletedquestion !== null) {
        $questiontype = \mod_questupload\question\question::qtypename($deletedquestion->type_id);
        questupload_observe_event_delete($questupload->cm->id, $questiontype, $questupload->course->id);
        $url = new moodle_url('/mod/questupload/questions.php', ['id' => $questupload->cm->id]);
        $PAGE->set_url($url->out(false));
        $reload = true;
    }
}

// Restore question.
if ($restoreq) {
    $qid = $restoreq;
    $qdeleted = isset($questupload->deletequestions[$qid]) ? $questupload->deletequestions[$qid] : false;
    if ($qid && $qdeleted) {
        questupload_restore_deleted_question($qid, $qdeleted->surveyid);
    }
    $url = new moodle_url('/mod/questupload/questions.php', ['id' => $questupload->cm->id]);
    $PAGE->set_url($url->out(false));
    $reload = true;
}

if ($action == 'main') {
    $questionsform = new \mod_questupload\questions_form('questions.php', $moveq);
    $sdata = clone($questupload->survey);
    $sdata->sid = $questupload->survey->id;
    $sdata->id = $cm->id;
    if (!empty($questupload->questions)) {
        $pos = 1;
        foreach ($questupload->questions as $qidx => $question) {
            $sdata->{'pos_'.$qidx} = $pos;
            $pos++;
        }
    }
    $questionsform->set_data($sdata);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        redirect($CFG->wwwroot.'/mod/questupload/questions.php?id='.$questupload->cm->id);
        $reload = true;
    }
    if ($qformdata = $questionsform->get_data()) {
        // Quickforms doesn't return values for 'image' input types using 'exportValue', so we need to grab
        // it from the raw submitted data.
        $exformdata = data_submitted();

        if (isset($exformdata->movebutton)) {
            $qformdata->movebutton = $exformdata->movebutton;
        } else if (isset($exformdata->moveherebutton)) {
            $qformdata->moveherebutton = $exformdata->moveherebutton;
        } else if (isset($exformdata->editbutton)) {
            $qformdata->editbutton = $exformdata->editbutton;
        } else if (isset($exformdata->removebutton)) {
            $qformdata->removebutton = $exformdata->removebutton;
        } else if (isset($exformdata->requiredbutton)) {
            $qformdata->requiredbutton = $exformdata->requiredbutton;
        } else if (isset($exformdata->deletebutton)) {
            $qformdata->deletebutton = $exformdata->deletebutton;
        } else if (isset($exformdata->restorebutton)) {
            $qformdata->restorebutton = $exformdata->restorebutton;
        }

        // Insert a section break.
        if (isset($qformdata->removebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->removebutton);
            $qtype = $questupload->questions[$qid]->type_id;

            // Delete section breaks without asking for confirmation.
            if ($qtype == QUESPAGEBREAK) {
                redirect(new \moodle_url('/mod/questupload/questions.php',
                    ['id' => $questupload->cm->id, 'delq' => $qid]));
            }

            $action = "confirmdelquestion";
            if ($questuploadhasdependencies) {
                // Important: due to possibly multiple parents per question
                // just remove the dependency and inform the user about it.
                $dependants = $questupload->get_all_dependants($qid);
                if (!(empty($dependants->directs) && empty($dependants->indirects))) {
                    $action = "confirmdelquestionparent";
                }
            }
        } else if (isset($qformdata->editbutton)) {
            // Switch to edit question screen.
            $action = 'question';
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->editbutton);
            $reload = true;

        } else if (isset($qformdata->requiredbutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            $qid = key($qformdata->requiredbutton);
            if ($questupload->questions[$qid]->required()) {
                $questupload->questions[$qid]->set_required(false);

            } else {
                $questupload->questions[$qid]->set_required(true);
            }

            $reload = true;

        } else if (isset($qformdata->addqbutton)) {
            if ($qformdata->type_id == QUESPAGEBREAK) { // Adding section break is handled right away....
                $questionrec = new stdClass();
                $questionrec->surveyid = $qformdata->sid;
                $questionrec->type_id = QUESPAGEBREAK;
                $questionrec->content = 'break';
                $question = \mod_questupload\question\question::question_builder(QUESPAGEBREAK);
                $question->add($questionrec);
                $reload = true;
            } else {
                // Switch to edit question screen.
                $action = 'question';
                $qtype = $qformdata->type_id;
                $qid = 0;
                $reload = true;
            }

        } else if (isset($qformdata->movebutton)) {
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/questupload/questions.php?id='.$questupload->cm->id.
                     '&moveq='.key($qformdata->movebutton));
            $reload = true;



        } else if (isset($qformdata->moveherebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            // No need to move question if new position = old position!
            $qpos = key($qformdata->moveherebutton);
            if ($qformdata->moveq != $qpos) {
                $questupload->move_question($qformdata->moveq, $qpos);
            }
            if ($questuploadhasdependencies) {
                $SESSION->questupload->validateresults = questupload_check_page_breaks($questupload);
            }
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/questupload/questions.php?id='.$questupload->cm->id);
            $reload = true;

        } else if (isset($qformdata->validate)) {
            // Validates page breaks for depend questions.
            $SESSION->questupload->validateresults = questupload_check_page_breaks($questupload);
            $reload = true;
        } else if (isset($qformdata->deletebutton)) {
            $action = QUESTUPLOAD_CONFIRM_DELETE_PERMANENTLY;
        } else if (isset($qformdata->restorebutton)) {
            $qid = key($qformdata->restorebutton);
            redirect(new moodle_url('/mod/questupload/questions.php',
                ['id' => $questupload->cm->id, QUESTUPLOAD_RESTORE_PARAM => $qid]));
        }
    }


} else if ($action == 'question') {
    $question = questupload_prep_for_questionform($questupload, $qid, $qtype);
    $questionsform = new \mod_questupload\edit_question_form('questions.php');
    $questionsform->set_data($question);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        $reload = true;

    } else if ($qformdata = $questionsform->get_data()) {
        // Saving question data.
        if (isset($qformdata->makecopy)) {
            $qformdata->qid = 0;
        }

        $question->form_update($qformdata, $questupload);

        // Make these field values 'sticky' for further new questions.
        if (!isset($qformdata->required)) {
            $qformdata->required = 'n';
        }

        questupload_check_page_breaks($questupload);
        $SESSION->questupload->required = $qformdata->required;
        $SESSION->questupload->type_id = $qformdata->type_id;
        // Switch to main screen.
        $action = 'main';
        $reload = true;
    }

    // Log question created event.
    if (isset($qformdata)) {
        $context = context_module::instance($questupload->cm->id);
        $questiontype = \mod_questupload\question\question::qtypename($qformdata->type_id);
        $params = array(
                        'context' => $context,
                        'courseid' => $questupload->course->id,
                        'other' => array('questiontype' => $questiontype)
        );
        $event = \mod_questupload\event\question_created::create($params);
        $event->trigger();
    }

    $questionsform->set_data($question);
}

// Reload the form data if called for...
if ($reload) {
    unset($questionsform);
    $questupload = new questupload($course, $cm, $questupload->id, null);
    $questupload->get_delete_questions();
    // Add renderer and page objects to the questupload object for display use.
    $questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
    $questupload->add_page(new \mod_questupload\output\questionspage());
    if ($action == 'main') {
        $questionsform = new \mod_questupload\questions_form('questions.php', $moveq);
        $sdata = clone($questupload->survey);
        $sdata->sid = $questupload->survey->id;
        $sdata->id = $cm->id;
        if (!empty($questupload->questions)) {
            $pos = 1;
            foreach ($questupload->questions as $qidx => $question) {
                $sdata->{'pos_'.$qidx} = $pos;
                $pos++;
            }
        }
        $questionsform->set_data($sdata);
    } else if ($action == 'question') {
        $question = questupload_prep_for_questionform($questupload, $qid, $qtype);
        $questionsform = new \mod_questupload\edit_question_form('questions.php');
        $questionsform->set_data($question);
    }
}

// Print the page header.
if ($action == 'question') {
    if (isset($question->qid)) {
        $streditquestion = get_string('editquestion', 'questupload', questupload_get_type($question->type_id));
    } else {
        $streditquestion = get_string('addnewquestion', 'questupload', questupload_get_type($question->type_id));
    }
} else {
    $streditquestion = get_string('managequestions', 'questupload');
}

$PAGE->set_title($streditquestion);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($streditquestion);
echo $questupload->renderer->header();
require('tabs.php');

if ($action == "confirmdelquestion" || $action == "confirmdelquestionparent") {

    $qid = key($qformdata->removebutton);
    $question = $questupload->questions[$qid];
    $qtype = $question->type_id;

    $countresps = count_reponses_question($qid, $qtype);

    // Needed to print potential media in question text.

    // If question text is "empty", i.e. 2 non-breaking spaces were inserted, do not display any question text.

    if ($question->content == '<p>  </p>') {
        $question->content = '';
    }

    $qname = '';
    if ($question->name) {
        $qname = ' ('.$question->name.')';
    }

    $num = get_string('position', 'questupload');
    $pos = $question->position.$qname;

    $msg = '<div class="warning centerpara"><p>'.get_string('confirmdelquestion', 'questupload', $pos).'</p>';
    if ($countresps !== 0) {
        $msg .= '<p>'.get_string('confirmdelquestionresps', 'questupload', $countresps).'</p>';
    }
    $msg .= '</div>';
    $msg .= '<div class = "qn-container">'.$num.' '.$pos.'<div class="qn-question">'.$question->content.'</div></div>';
    $args = "id={$questupload->cm->id}";
    $urlno = new moodle_url("/mod/questupload/questions.php?{$args}");
    $args .= "&delq={$qid}";
    $urlyes = new moodle_url("/mod/questupload/questions.php?{$args}");
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    if ($action == "confirmdelquestionparent") {
        $strnum = get_string('position', 'questupload');
        $qid = key($qformdata->removebutton);
        if ($dependants) {
            // Show the dependencies and inform about the dependencies to be removed.
            // Split dependencies in direct and indirect ones to separate for the confirm-dialogue.
            // Only direct ones will be deleted. List direct dependencies.
            $msg .= $questupload->renderer->dependency_warnings($dependants->directs, 'directwarnings', $strnum);
            // List indirect dependencies.
            $msg .= $questupload->renderer->dependency_warnings($dependants->indirects, 'indirectwarnings', $strnum);
        }
    }
    $questupload->page->add_to_page('formarea', $questupload->renderer->confirm($msg, $buttonyes, $buttonno));

} else if ($action === QUESTUPLOAD_CONFIRM_DELETE_PERMANENTLY) {
    $qid = key($qformdata->deletebutton);
    $qtype = $questupload->deletequestions[$qid]->type_id;
    $questiondelete = $questupload->deletequestions[$qid];
    $countresps = count_reponses_question($qid, $qtype);

    $urlno = new moodle_url("/mod/questupload/questions.php", ['id' => $questupload->cm->id]);
    $urlyes = new moodle_url("/mod/questupload/questions.php", ['id' => $questupload->cm->id, "delpermanentlyq" => $qid]);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $msg = '<div class="warning centerpara"><p>'.get_string('confirmdelpermanentlyq', 'questupload').'</p>';
    if ($countresps !== 0) {
        $msg .= '<p>'.get_string('confirmdelquestionresps', 'questupload', $countresps).'</p>';
    }
    $msg .= '</div>';
    $msg .= '<div class = "qn-container">NA ('. $questiondelete->name .')
                <div class="qn-question">'.$questiondelete->content.'</div></div>';

    $questupload->page->add_to_page('formarea', $questupload->renderer->confirm($msg, $buttonyes, $buttonno));
} else {
    $questupload->page->add_to_page('formarea', $questionsform->render());
}
echo $questupload->renderer->render($questupload->page);
echo $questupload->renderer->footer();
