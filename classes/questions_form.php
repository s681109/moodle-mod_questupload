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

namespace mod_questupload;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

#[\AllowDynamicProperties]
/**
 * The form definition class for questions.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward & Joseph Rézeau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions_form extends \moodleform {

    /**
     * The constructor.
     * @param mixed $action
     * @param bool $moveq
     */
    public function __construct($action, $moveq=false) {
        $this->moveq = $moveq;
        return parent::__construct($action);
    }

    /**
     * Form definition.
     */
    public function definition() {
        global $CFG, $questupload, $SESSION;
        global $DB;

        $sid = $questupload->survey->id;
        $mform    =& $this->_form;
        $qidrestore = optional_param(QUESTUPLOAD_RESTORE_PARAM, 0, PARAM_INT);

        $mform->addElement('header', 'questionhdr', get_string('addquestions', 'questupload'));
        $mform->addHelpButton('questionhdr', 'questiontypes', 'questupload');

        $strremove = get_string('remove', 'questupload');
        $strmove = get_string('move');
        $strmovehere = get_string('movehere');
        $strposition = get_string('position', 'questupload');

        if (!isset($questupload->questions)) {
            $questupload->questions = array();
        }
        if ($this->moveq) {
            $moveqposition = $questupload->questions[$this->moveq]->position;
        }

        $pos = 0;
        $select = '';
        if (!($qtypes = $DB->get_records_select_menu('questupload_question_type', $select, null, '', 'typeid,type'))) {
            $qtypes = array();
        }
        // Get the names of each question type in the appropriate language.
        foreach ($qtypes as $key => $qtype) {
            // Do not allow "Page Break" to be selected as first element of a Questionnaire Upload.
            if (empty($questupload->questions) && ($qtype == 'Page Break')) {
                unset($qtypes[$key]);
            } else {
                $qtypes[$key] = questupload_get_type($key);
            }
        }
        natsort($qtypes);
        $addqgroup = array();
        $addqgroup[] =& $mform->createElement('select', 'type_id', '', $qtypes);

        // The 'sticky' type_id value for further new questions.
        if (isset($SESSION->questupload->type_id)) {
                $mform->setDefault('type_id', $SESSION->questupload->type_id);
        }

        $addqgroup[] =& $mform->createElement('submit', 'addqbutton', get_string('addselqtype', 'questupload'));

        $questuploadhasdependencies = $questupload->has_dependencies();

        $mform->addGroup($addqgroup, 'addqgroup', '', ' ', false);

        if (isset($SESSION->questupload->validateresults) &&  $SESSION->questupload->validateresults != '') {
            $mform->addElement('static', 'validateresult', '', '<div class="qdepend warning">'.
                $SESSION->questupload->validateresults.'</div>');
            $SESSION->questupload->validateresults = '';
        }

        $qnum = 0;

        // JR skip logic :: to prevent moving child higher than parent OR parent lower than child
        // we must get now the parent and child positions.

        if ($questuploadhasdependencies) {
            $parentpositions = questupload_get_parent_positions($questupload->questions);
            $childpositions = questupload_get_child_positions($questupload->questions);
        }

        $mform->addElement('header', 'manageq', get_string('managequestions', 'questupload'));
        $mform->addHelpButton('manageq', 'managequestions', 'questupload');

        $mform->addElement('html', '<div class="qcontainer">');

        foreach ($questupload->questions as $question) {

            $manageqgroup = array();

            $qid = $question->id;
            $tid = $question->type_id;
            $qtype = $question->type;
            $required = $question->required;

            // Get displayable list of parents for the questions in questions_form.
            if ($questuploadhasdependencies) {
                // TODO - Perhaps this should be a function called by the questupload after it loads all questions?
                $questupload->load_parents($question);
                $dependencies = $questupload->renderer->get_dependency_html($question->id, $question->dependencies);
            } else {
                $dependencies = '';
            }

            $pos = $question->position;

            // No page break in first position!
            if ($tid == QUESPAGEBREAK && $pos == 1) {
                $DB->set_field('questupload_question', 'deleted', time(), ['id' => $qid, 'surveyid' => $sid]);
                if ($records = $DB->get_records_select('questupload_question', $select, null, 'position ASC')) {
                    foreach ($records as $record) {
                        $DB->set_field('questupload_question', 'position', $record->position - 1, array('id' => $record->id));
                    }
                }
                redirect($CFG->wwwroot.'/mod/questupload/questions.php?id='.$questupload->cm->id);
            }

            if ($question->is_numbered()) {
                $qnum++;
            }

            // Needed for non-English languages JR.
            $qtype = '['.questupload_get_type($tid).']';
            $content = '';
            // If question text is "empty", i.e. 2 non-breaking spaces were inserted, do not display any question text.
            if ($question->content == '<p>  </p>') {
                $question->content = '';
            }
            if ($tid != QUESPAGEBREAK) {
                // Needed to print potential media in question text.
                $content = format_text(file_rewrite_pluginfile_urls($question->content, 'pluginfile.php',
                    $question->context->id, 'mod_questupload', 'question', $question->id), FORMAT_HTML, ['noclean' => true]);
            }
            $moveqgroup = array();

            $spacer = $questupload->renderer->image_url('spacer');

            if (!$this->moveq) {
                if ($dependencies) {
                    // Begin div qn-container with indent if questupload has child.
                    $mform->addElement('html', '<div class="qn-container qn-indent">');
                } else {
                    $containerclass = "qn-container";
                    if (isset($qidrestore) && $qidrestore == $question->id) {
                        $containerclass .= " restored-question";
                    }
                    // Begin div qn-container.
                    $mform->addElement('html', "<div class='$containerclass'>");
                }

                $mextra = array('value' => $question->id,
                                'alt' => $strmove,
                                'title' => $strmove);
                $eextra = array('value' => $question->id,
                                'alt' => get_string('edit', 'questupload'),
                                'title' => get_string('edit', 'questupload'));
                $rextra = array('value' => $question->id,
                                'alt' => $strremove,
                                'title' => $strremove);

                if ($tid == QUESPAGEBREAK) {
                    $esrc = $spacer;
                    $eextra = array('disabled' => 'disabled');
                } else {
                    $esrc = $questupload->renderer->image_url('t/edit');
                }
                $rsrc = $questupload->renderer->image_url('t/delete');

                // Question numbers.
                $manageqgroup[] =& $mform->createElement('static', 'qnums', '',
                                '<div class="qnums">'.$strposition.' '.$pos.'</div>');

                // Need to index by 'id' since IE doesn't return assigned 'values' for image inputs.
                $manageqgroup[] =& $mform->createElement('static', 'opentag_'.$question->id, '', '');
                $msrc = $questupload->renderer->image_url('t/move');

                if ($questuploadhasdependencies) {
                    // Do not allow moving parent question at position #1 to be moved down if it has a child at position < 4.
                    if ($pos == 1) {
                        if (isset($childpositions[$qid])) {
                            $maxdown = $childpositions[$qid];
                            if ($maxdown < 4) {
                                $strdisabled = get_string('movedisabled', 'questupload');
                                $msrc = $questupload->renderer->image_url('t/block');
                                $mextra = array('value' => $question->id,
                                                'alt' => $strdisabled,
                                                'title' => $strdisabled);
                                $mextra += array('disabled' => 'disabled');
                            }
                        }
                    }

                    // Do not allow moving or deleting a page break if immediately followed by a child question
                    // or immediately preceded by a question with a dependency and followed by a non-dependent question.
                    if ($tid == QUESPAGEBREAK) {
                        $select = 'surveyid = ? AND position = ? AND deleted IS NULL';
                        $nextquestion = $DB->get_record_select('questupload_question', $select,
                            [$sid, $pos + 1], 'id, name, content');
                        if ($nextquestion) {
                            $nextquestiondependencies = $DB->get_records('questupload_dependency',
                                ['questionid' => $nextquestion->id , 'surveyid' => $sid], 'id ASC');

                            if ($previousquestion = $DB->get_record_select('questupload_question', $select,
                                [$sid, $pos - 1], 'id, name, content')) {

                                $previousquestiondependencies = $DB->get_records('questupload_dependency',
                                    ['questionid' => $previousquestion->id , 'surveyid' => $sid], 'id ASC');

                                if (!empty($nextquestiondependencies) ||
                                    (!empty($previousquestiondependencies) && empty($nextquestiondependencies))) {
                                    $strdisabled = get_string('movedisabled', 'questupload');
                                    $msrc = $questupload->renderer->image_url('t/block');
                                    $mextra = array('value' => $question->id,
                                                    'alt' => $strdisabled,
                                                    'title' => $strdisabled);
                                    $mextra += array('disabled' => 'disabled');

                                    $rsrc = $msrc;
                                    $strdisabled = get_string('deletedisabled', 'questupload');
                                    $rextra = array('value' => $question->id,
                                                    'alt' => $strdisabled,
                                                    'title' => $strdisabled);
                                    $rextra += array('disabled' => 'disabled');
                                }
                            }
                        }
                    }
                }
                $manageqgroup[] =& $mform->createElement('image', 'movebutton['.$question->id.']',
                                $msrc, $mextra);
                $manageqgroup[] =& $mform->createElement('image', 'editbutton['.$question->id.']', $esrc, $eextra);
                $manageqgroup[] =& $mform->createElement('image', 'removebutton['.$question->id.']', $rsrc, $rextra);

                if ($tid != QUESPAGEBREAK && $tid != QUESSECTIONTEXT  && $tid != QUESSLIDER) {
                    if ($required == 'y') {
                        $reqsrc = $questupload->renderer->image_url('t/stop');
                        $strrequired = get_string('required', 'questupload');
                    } else {
                        $reqsrc = $questupload->renderer->image_url('t/go');
                        $strrequired = get_string('notrequired', 'questupload');
                    }
                    $strrequired .= ' '.get_string('clicktoswitch', 'questupload');
                    $reqextra = array('value' => $question->id,
                                    'alt' => $strrequired,
                                    'title' => $strrequired);
                    $manageqgroup[] =& $mform->createElement('image', 'requiredbutton['.$question->id.']', $reqsrc, $reqextra);
                }
                $manageqgroup[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '');

            } else {
                $manageqgroup[] =& $mform->createElement('static', 'qnum', '',
                                '<div class="qnums">'.$strposition.' '.$pos.'</div>');
                $moveqgroup[] =& $mform->createElement('static', 'qnum', '', '');

                $display = true;
                if ($questuploadhasdependencies) {
                    // Prevent moving child to higher position than its parent.
                    if (isset($parentpositions[$this->moveq])) {
                        $maxup = $parentpositions[$this->moveq];
                        if ($pos <= $maxup) {
                            $display = false;
                        }
                    }
                    // Prevent moving parent to lower position than its (first) child.
                    if (isset($childpositions[$this->moveq])) {
                        $maxdown = $childpositions[$this->moveq];
                        if ($pos >= $maxdown) {
                            $display = false;
                        }
                    }
                }

                $typeid = $DB->get_field('questupload_question', 'type_id', array('id' => $this->moveq));

                if ($display) {
                    // Do not move a page break to first position.
                    if ($typeid == QUESPAGEBREAK && $pos == 1) {
                        $manageqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                    } else {
                        if ($this->moveq == $question->id) {
                            $moveqgroup[] =& $mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
                        } else {
                            $mextra = array('value' => $question->id,
                                            'alt' => $strmove,
                                            'title' => $strmovehere.' (position '.$pos.')');
                            $msrc = $questupload->renderer->image_url('movehere');
                            $moveqgroup[] =& $mform->createElement('static', 'opentag_'.$question->id, '', '');
                            $moveqgroup[] =& $mform->createElement('image', 'moveherebutton['.$pos.']', $msrc, $mextra);
                            $moveqgroup[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '');
                        }
                    }
                } else {
                    $manageqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                    $moveqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                }
            }
            if ($question->name) {
                $qname = '('.$question->name.')';
            } else {
                $qname = '';
            }
            $manageqgroup[] =& $mform->createElement('static', 'qinfo_'.$question->id, '', $qtype.' '.$qname);

            if (!empty($dependencies)) {
                $mform->addElement('static', 'qdepend_' . $question->id, '', $dependencies);
            }

            if ($question->is_numbered()) {
                    $qnumber = '<div class="qn-info"><h2 class="qn-number">'.$qnum.'</h2></div>';
            } else {
                    $qnumber = '';
            }

            if ($this->moveq && $pos < $moveqposition) {
                $mform->addGroup($moveqgroup, 'moveqgroup', '', '', false);
            }
            if ($this->moveq) {
                if ($this->moveq == $question->id && $display) {
                    $mform->addElement('html', '<div class="moving" title="'.$strmove.'">'); // Begin div qn-container.
                } else {
                    $mform->addElement('html', '<div class="qn-container">'); // Begin div qn-container.
                }
            }
            $mform->addGroup($manageqgroup, 'manageqgroup', '', '&nbsp;', false);
            if ($tid != QUESPAGEBREAK) {
                $mform->addElement('static', 'qcontent_'.$question->id, '',
                    $qnumber.'<div class="qn-question">'.$content.'</div>');
            }
            $mform->addElement('html', '</div>'); // End div qn-container.

            if ($this->moveq && $pos >= $moveqposition) {
                $mform->addGroup($moveqgroup, 'moveqgroup', '', '', false);
            }
        }

        // Question deletion area.
        $mform->addElement('header', 'deletionq', get_string('deletionquetions', 'questupload'));
        $mform->addHelpButton('deletionq', 'deletionquetions', 'questupload');
        $mform->addElement('html', '<div class="qcontainer">');
        if (isset($questupload->deletequestions)) {
            $restoreimg = $questupload->renderer->image_url('i/up');
            $deleteimg = $questupload->renderer->image_url('t/delete');
            $rangetimecrontask = questupload_get_range_time_permanently();
            foreach ($questupload->deletequestions as $deletequestion) {
                $delquestiongroup = [];
                // Preparing deleted time to display time permanently question.
                $timedeleted = $deletequestion->deleted ?? "";
                if ($rangetimecrontask == 0) {
                    $timedeleted = get_string('recylebindisabled', 'questupload');
                } else {
                    if (!empty($timedeleted)) {
                        $timedeleted = get_string('timedeletednext7days', 'questupload',
                            date("D j M, Y", $timedeleted + $rangetimecrontask));
                    }
                }
                $qtypeandname = [];
                $qtypeandname['name'] = $deletequestion->name;
                $qtypeandname['type'] = questupload_get_type($deletequestion->type_id);

                $content = format_text(
                    file_rewrite_pluginfile_urls($deletequestion->content, 'pluginfile.php',
                    $deletequestion->context->id,
                    'mod_questupload', 'question', $deletequestion->id),
                    FORMAT_HTML, ['noclean' => true]
                );

                $qnumber = '<div class="qn-info"><h2 class="qn-number">NA</h2></div>';
                $restorextra = [
                    'value' => $deletequestion->id,
                    'alt' => get_string('restorebutton', 'questupload'),
                    'title' => get_string('restorebutton', 'questupload'),
                    'class' => 'mod_questupload_recycleq',
                ];
                $deleleextra = [
                    'value' => $deletequestion->id,
                    'alt' => get_string('deletepermanentlybutton', 'questupload'),
                    'title' => get_string('deletepermanentlybutton', 'questupload'),
                    'class' => 'mod_questupload_recycleq',
                ];
                $mform->addElement('html', '<div class="qn-container">'); // Begin div qn-container.
                $delquestiongroup[] =& $mform->createElement('static', 'opentag_' . $deletequestion->id, '', '');
                $delquestiongroup[] =& $mform->createElement('image', 'restorebutton[' . $deletequestion->id . ']',
                    $restoreimg, $restorextra);
                $delquestiongroup[] =& $mform->createElement('image', 'deletebutton[' . $deletequestion->id . ']',
                    $deleteimg, $deleleextra);
                $delquestiongroup[] =& $mform->createElement('static', 'closetag_' . $deletequestion->id, '', '');
                $delquestiongroup[] =& $mform->createElement('static', 'qinfo_' . $deletequestion->id, '',
                    get_string('questiontypeandname', 'questupload', $qtypeandname));
                $delquestiongroup[] =& $mform->createElement('static', 'qinfo_' . $deletequestion->id,
                        '', $timedeleted);
                $mform->addGroup($delquestiongroup, 'delquestiongroup', '', '&nbsp;', false);
                $mform->addElement('static', 'qcontent_'.$deletequestion->id, '',
                    $qnumber.'<div class="qn-question">'.$content.'</div>');
                $mform->addElement('html', '</div>'); // End div qn-container.
            }
        }

        if ($this->moveq) {
            $mform->addElement('hidden', 'moveq', $this->moveq);
        }

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'main');
        $mform->setType('action', PARAM_RAW);
        $mform->setType('moveq', PARAM_RAW);

        $mform->addElement('html', '</div>');
        $mform->setExpanded('questionhdr');
        $mform->setExpanded('manageq');
        $mform->setExpanded('deletionq');
    }

    /**
     * Form validation.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
