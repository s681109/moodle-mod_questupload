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
use mod_questupload\feedback\section;

/**
 * Define all the restore steps that will be used by the restore_questupload_activity_task.
 *
 * Structure step to restore one questupload activity.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_questupload_activity_structure_step extends restore_activity_structure_step {

    /**
     * @var array $olddependquestions Contains any question id's with dependencies.
     */
    protected $olddependquestions = [];

    /**
     * @var array $olddependchoices Contains any choice id's for questions with dependencies.
     */
    protected $olddependchoices = [];

    /**
     * @var array $olddependencies Contains the old id's from the dependencies array.
     */
    protected $olddependencies = [];

    /**
     * @var array Contains new questupload_response_file.id in key and old id in value.
     */
    protected $responsefileids = [];

    /**
     * Implementation of define_structure.
     * @return mixed
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('questupload', '/activity/questupload');
        $paths[] = new restore_path_element('questupload_survey', '/activity/questupload/surveys/survey');
        $paths[] = new restore_path_element('questupload_fb_sections',
                        '/activity/questupload/surveys/survey/fb_sections/fb_section');
        $paths[] = new restore_path_element('questupload_feedback',
                        '/activity/questupload/surveys/survey/fb_sections/fb_section/feedbacks/feedback');
        $paths[] = new restore_path_element('questupload_question',
                        '/activity/questupload/surveys/survey/questions/question');
        $paths[] = new restore_path_element('questupload_quest_choice',
                        '/activity/questupload/surveys/survey/questions/question/quest_choices/quest_choice');
        $paths[] = new restore_path_element('questupload_dependency',
                '/activity/questupload/surveys/survey/questions/question/quest_dependencies/quest_dependency');

        if ($userinfo) {
            if ($this->task->get_old_moduleversion() < 2018050102) {
                // Old system.
                $paths[] = new restore_path_element('questupload_attempt', '/activity/questupload/attempts/attempt');
                $paths[] = new restore_path_element('questupload_response',
                    '/activity/questupload/attempts/attempt/responses/response');
                $paths[] = new restore_path_element('questupload_response_bool',
                    '/activity/questupload/attempts/attempt/responses/response/response_bools/response_bool');
                $paths[] = new restore_path_element('questupload_response_date',
                    '/activity/questupload/attempts/attempt/responses/response/response_dates/response_date');
                $paths[] = new restore_path_element('questupload_response_multiple',
                    '/activity/questupload/attempts/attempt/responses/response/response_multiples/response_multiple');
                $paths[] = new restore_path_element('questupload_response_other',
                    '/activity/questupload/attempts/attempt/responses/response/response_others/response_other');
                $paths[] = new restore_path_element('questupload_response_rank',
                    '/activity/questupload/attempts/attempt/responses/response/response_ranks/response_rank');
                $paths[] = new restore_path_element('questupload_response_single',
                    '/activity/questupload/attempts/attempt/responses/response/response_singles/response_single');
                $paths[] = new restore_path_element('questupload_response_text',
                    '/activity/questupload/attempts/attempt/responses/response/response_texts/response_text');
                $paths[] = new restore_path_element('questupload_response_file',
                    '/activity/questupload/attempts/attempt/responses/response/response_files/response_file');

            } else {
                // New system.
                $paths[] = new restore_path_element('questupload_response', '/activity/questupload/responses/response');
                $paths[] = new restore_path_element('questupload_response_bool',
                    '/activity/questupload/responses/response/response_bools/response_bool');
                $paths[] = new restore_path_element('questupload_response_date',
                    '/activity/questupload/responses/response/response_dates/response_date');
                $paths[] = new restore_path_element('questupload_response_multiple',
                    '/activity/questupload/responses/response/response_multiples/response_multiple');
                $paths[] = new restore_path_element('questupload_response_other',
                    '/activity/questupload/responses/response/response_others/response_other');
                $paths[] = new restore_path_element('questupload_response_rank',
                    '/activity/questupload/responses/response/response_ranks/response_rank');
                $paths[] = new restore_path_element('questupload_response_single',
                    '/activity/questupload/responses/response/response_singles/response_single');
                $paths[] = new restore_path_element('questupload_response_text',
                    '/activity/questupload/responses/response/response_texts/response_text');
                $paths[] = new restore_path_element('questupload_response_file',
                    '/activity/questupload/responses/response/response_files/response_file');
            }
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Implementation of process_questupload.
     * @param array $data
     */
    protected function process_questupload($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $data->opendate = $this->apply_date_offset($data->opendate);
        $data->closedate = $this->apply_date_offset($data->closedate);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the questupload record.
        $newitemid = $DB->insert_record('questupload', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process the survey table.
     * @param array $data
     */
    protected function process_questupload_survey($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->courseid = $this->get_courseid();

        // Check for a 'feedbacksections' value larger than 2, and limit it to 2. As of 3.5.1 this has a different meaning.
        if ($data->feedbacksections > 2) {
            $data->feedbacksections = 2;
        }

        // Insert the questupload_survey record.
        $newitemid = $DB->insert_record('questupload_survey', $data);
        $this->set_mapping('questupload_survey', $oldid, $newitemid, true);

        // Update the questupload record we just created with the new survey id.
        $DB->set_field('questupload', 'sid', $newitemid, array('id' => $this->get_new_parentid('questupload')));
    }

    /**
     * Process the questions.
     * @param array $data
     */
    protected function process_questupload_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('questupload_survey');
        // Cover for legacy backup data.
        if ($data->deleted === 'n') {
            $data->deleted = null;
        }
        // Insert the questupload_question record.
        $newitemid = $DB->insert_record('questupload_question', $data);
        $this->set_mapping('questupload_question', $oldid, $newitemid, true);

        if (isset($data->dependquestion) && ($data->dependquestion > 0)) {
            // Questions using the old dependency system will need to be processed and restored using the new system.
            // See CONTRIB-6787.
            $this->olddependquestions[$newitemid] = $data->dependquestion;
            $this->olddependchoices[$newitemid] = $data->dependchoice;
        }
    }

    /**
     * Process the feedback sections.
     * $qid is unused, but is needed in order to get the $key elements of the array. Suppress PHPMD warning.
     * @param array $data
     */
    protected function process_questupload_fb_sections($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('questupload_survey');

        // If this questupload has separate sections feedbacks.
        if (isset($data->scorecalculation)) {
            $scorecalculation = section::decode_scorecalculation($data->scorecalculation);
            $newscorecalculation = array();
            foreach ($scorecalculation as $qid => $val) {
                $newqid = $this->get_mappingid('questupload_question', $qid);
                $newscorecalculation[$newqid] = $val;
            }
            $data->scorecalculation = serialize($newscorecalculation);
        }

        // Insert the questupload_fb_sections record.
        $newitemid = $DB->insert_record('questupload_fb_sections', $data);
        $this->set_mapping('questupload_fb_sections', $oldid, $newitemid, true);
    }

    /**
     * Process feedback.
     * @param array $data
     */
    protected function process_questupload_feedback($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->sectionid = $this->get_new_parentid('questupload_fb_sections');

        // Insert the questupload_feedback record.
        $newitemid = $DB->insert_record('questupload_feedback', $data);
        $this->set_mapping('questupload_feedback', $oldid, $newitemid, true);
    }

    /**
     * Process question choices.
     * @param array $data
     */
    protected function process_questupload_quest_choice($data) {
        global $CFG, $DB;

        $data = (object)$data;

        require_once($CFG->dirroot.'/mod/questupload/locallib.php');

        // Some old systems had '' instead of NULL. Change it to NULL.
        if ($data->value === '') {
            $data->value = null;
        }

        // Replace the = separator with :: separator in quest_choice content.
        // This fixes radio button options using old "value"="display" formats.
        if (($data->value == null || $data->value == 'NULL') && !preg_match("/^([0-9]{1,3}=.*|!other=.*)$/", $data->content)) {
            $content = questupload_choice_values($data->content);
            if (strpos($content->text, '=')) {
                $data->content = str_replace('=', '::', $content->text);
            }
        }

        $oldid = $data->id;
        $data->question_id = $this->get_new_parentid('questupload_question');

        // Insert the questupload_quest_choice record.
        $newitemid = $DB->insert_record('questupload_quest_choice', $data);
        $this->set_mapping('questupload_quest_choice', $oldid, $newitemid);
    }

    /**
     * Process dependencies.
     * @param array $data
     */
    protected function process_questupload_dependency($data) {
        $data = (object)$data;

        $data->questionid = $this->get_new_parentid('questupload_question');
        $data->surveyid = $this->get_new_parentid('questupload_survey');

        if (isset($data)) {
            $this->olddependencies[] = $data;
        }
    }

    /**
     * Process attempts (these are no longer used).
     * @param array $data
     * @return bool
     */
    protected function process_questupload_attempt($data) {
        // New structure will be completed in process_questupload_response. Nothing to do here any more.
        return true;
    }

    /**
     * Process responses.
     * @param array $data
     */
    protected function process_questupload_response($data) {
        global $DB;

        $data = (object)$data;

        // Older versions of questupload used 'username' instead of 'userid'. If 'username' exists, change it to 'userid'.
        if (isset($data->username) && !isset($data->userid)) {
            $data->userid = $data->username;
        }

        $oldid = $data->id;
        $data->questuploadid = $this->get_new_parentid('questupload');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Insert the questupload_response record.
        $newitemid = $DB->insert_record('questupload_response', $data);
        $this->set_mapping('questupload_response', $oldid, $newitemid, true);
    }

    /**
     * Process boolean responses.
     * @param array $data
     * @throws dml_exception
     */
    protected function process_questupload_response_bool($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);

        // Insert the questupload_response_bool record.
        $DB->insert_record('questupload_response_bool', $data);
    }

    /**
     * Process date responses.
     * @param array $data
     * @throws dml_exception
     */
    protected function process_questupload_response_date($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);

        // Insert the questupload_response_date record.
        $DB->insert_record('questupload_response_date', $data);
    }

    /**
     * Process multiple responses.
     * @param array $data
     * @throws dml_exception
     */
    protected function process_questupload_response_multiple($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questupload_quest_choice', $data->choice_id);

        // Insert the questupload_resp_multiple record.
        $DB->insert_record('questupload_resp_multiple', $data);
    }

    /**
     * Process other responses.
     * @param array $data
     * @throws dml_exception
     */
    protected function process_questupload_response_other($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questupload_quest_choice', $data->choice_id);

        // Insert the questupload_response_other record.
        $DB->insert_record('questupload_response_other', $data);
    }

    /**
     * Process rank responses.
     * @param array $data
     * @throws dml_exception
     */
    protected function process_questupload_response_rank($data) {
        global $DB;

        $data = (object)$data;

        // Older versions of questupload used 'rank' instead of 'rankvalue'. If 'rank' exists, change it to 'rankvalue'.
        if (isset($data->rank) && !isset($data->rankvalue)) {
            $data->rankvalue = $data->rank;
        }

        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questupload_quest_choice', $data->choice_id);

        // Insert the questupload_response_rank record.
        $DB->insert_record('questupload_response_rank', $data);
    }



    /**
     * Process file responses.
     * @param array $data
     * @throws dml_exception
     */
    protected function process_questupload_response_file($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);

        // Insert the questupload_response_file record.
        $newitemid = $DB->insert_record('questupload_response_file', $data);
        $this->responsefileids[$newitemid] = $oldid;
    }

    /**
     * Process single responses.
     * @param array $data
     * @throws dml_exception
     */
    protected function process_questupload_response_single($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questupload_quest_choice', $data->choice_id);

        // Insert the questupload_resp_single record.
        $DB->insert_record('questupload_resp_single', $data);
    }

    /**
     * Process text answers.
     * @param array $data
     */
    protected function process_questupload_response_text($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('questupload_response');
        $data->question_id = $this->get_mappingid('questupload_question', $data->question_id);

        // Insert the questupload_response_text record.
        $DB->insert_record('questupload_response_text', $data);
    }



    /**
     * Handle related files for response file assignments and update references between response_file and files records.
     */
    protected function handle_related_files_for_response_file_assignments() {
        global $DB;

        if (empty($this->responsefileids)) {
            return;
        }

        $newctx = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'context', $this->task->get_old_contextid());
        restore_dbops::send_files_to_pool(
            $this->get_basepath(),
            $this->get_restoreid(),
            'mod_questupload',
            'response_file',
            $this->task->get_old_contextid(),
            $this->task->get_userid()
        );

        [$sql, $params] = $DB->get_in_or_equal(array_values($this->responsefileids), SQL_PARAMS_NAMED, 'id');
        $params['component'] = 'mod_questupload';
        $params['filearea'] = 'response_file';
        $params['ctx'] = $newctx->newitemid;
        $filerecords = $DB->get_records_select(
            'files',
            'contextid = :ctx AND component = :component AND filearea = :filearea AND itemid ' . $sql,
            $params
        );
        $oldtonew = array_flip($this->responsefileids);
        foreach ($filerecords as $filerecord) {
            if (array_key_exists($filerecord->itemid, $oldtonew)) {
                if ($filerecord->filename != '.') {
                    $DB->set_field(
                        'questupload_response_file',
                        'fileid',
                        $filerecord->id,
                        ['id' => $oldtonew[$filerecord->itemid]]
                    );
                }
                $filerecord->itemid = $oldtonew[$filerecord->itemid];
                $filerecord->pathnamehash = \file_storage::get_pathname_hash(
                    $filerecord->contextid,
                    $filerecord->component,
                    $filerecord->filearea,
                    $filerecord->itemid,
                    $filerecord->filepath,
                    $filerecord->filename
                );
                $DB->update_record('files', $filerecord);
            }
        }
    }

    /**
     * Stuff to do after execution.
     */
    protected function after_execute() {
        global $DB;

        // Process any question dependencies after all questions and choices have already been processed to ensure we have all of
        // the new id's.

        // First, process any old system question dependencies into the new system.
        foreach ($this->olddependquestions as $newid => $olddependid) {
            $newrec = new stdClass();
            $newrec->questionid = $newid;
            $newrec->surveyid = $this->get_new_parentid('questupload_survey');
            $newrec->dependquestionid = $this->get_mappingid('questupload_question', $olddependid);
            // Only change mapping for RADIO and DROP question types, not for YESNO question.
            $dependqtype = $DB->get_field('questupload_question', 'type_id', ['id' => $newrec->dependquestionid]);
            if (($dependqtype !== false) && ($dependqtype != 1)) {
                $newrec->dependchoiceid = $this->get_mappingid('questupload_quest_choice',
                    $this->olddependchoices[$newid]);
            } else {
                $newrec->dependchoiceid = $this->olddependchoices[$newid];
            }
            $newrec->dependlogic = 1; // Set to "answer given", previously the only option.
            $newrec->dependandor = 'and'; // Not used previously.
            $DB->insert_record('questupload_dependency', $newrec);
        }

        // Next process all new system dependencies.
        foreach ($this->olddependencies as $data) {
            $data->dependquestionid = $this->get_mappingid('questupload_question', $data->dependquestionid);

            // Only change mapping for RADIO and DROP question types, not for YESNO question.
            $dependqtype = $DB->get_field('questupload_question', 'type_id', ['id' => $data->dependquestionid]);
            if (($dependqtype !== false) && ($dependqtype != 1)) {
                $data->dependchoiceid = $this->get_mappingid('questupload_quest_choice', $data->dependchoiceid);
            }
            $DB->insert_record('questupload_dependency', $data);
        }

        // Add questupload related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_questupload', 'intro', null);
        $this->add_related_files('mod_questupload', 'info', 'questupload_survey');
        $this->add_related_files('mod_questupload', 'thankbody', 'questupload_survey');
        $this->add_related_files('mod_questupload', 'feedbacknotes', 'questupload_survey');
        $this->add_related_files('mod_questupload', 'question', 'questupload_question');
        $this->add_related_files('mod_questupload', 'response_attachment', 'questupload_response');
        $this->handle_related_files_for_response_file_assignments();
        $this->add_related_files('mod_questupload', 'sectionheading', 'questupload_fb_sections');
        $this->add_related_files('mod_questupload', 'feedback', 'questupload_feedback');

        // Process any old rate question named degree choices after all questions and choices have been restored.
        if ($this->task->get_old_moduleversion() < 2018110103) {
            \mod_questupload\question\rate::move_all_nameddegree_choices($this->get_new_parentid('questupload_survey'));
        }
    }
}
