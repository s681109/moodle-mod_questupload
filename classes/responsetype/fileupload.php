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

namespace mod_questupload\responsetype;

use mod_questupload\db\bulk_sql_config;
use moodle_url;

/**
 * Class for text response types.
 *
 * @author Laurent David
 * @author Martin Cornu-Mansuy
 * @copyright 2023 onward CALL Learning <martin@call-learning.fr>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questupload
 */
class fileupload extends responsetype {
    /**
     * Provide an array of answer objects from web form data for the question.
     *
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param \mod_questupload\question\question $question
     * @return array \mod_questupload\responsetype\answer\answer An array of answer objects.
     */
    public static function answers_from_webform($responsedata, $question) {
        $answers = [];
        if (isset($responsedata->{'q' . $question->id}) && (strlen($responsedata->{'q' . $question->id}) > 0)) {
            $val = $responsedata->{'q' . $question->id};
            file_save_draft_area_files(
                $val,
                $question->context->id,
                'mod_questupload',
                'response_file',
                $val,
                $question->get_file_manager_option()
            );
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $question->context->id,
                'mod_questupload',
                'response_file',
                $val,
                'itemid, filepath, filename',
                false
            );
            foreach ($files as $file) {
                $record = new \stdClass();
                $record->responseid = $responsedata->rid;
                $record->questionid = $question->id;
                $record->value = $file->get_id();
                $answers[] = answer\answer::create_from_data($record);
            }
            if (empty($answers)) {
                self::delete_old_response((int)$question->id, (int)$responsedata->rid);
            }
        }
        return $answers;
    }

    /**
     * Return an array of answers by question/choice for the given response. Must be implemented by the subclass.
     *
     * @param int $rid The response id.
     * @return array
     */
    public static function response_select($rid) {
        global $DB;

        $values = [];
        $sql = 'SELECT q.id, q.content, a.fileid as aresponse ' .
            'FROM {' . static::response_table() . '} a, {questupload_question} q ' .
            'WHERE a.response_id=? AND a.question_id=q.id ';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $qid => $row) {
            unset($row->id);
            $row = (array) $row;
            $newrow = [];
            foreach ($row as $key => $val) {
                if (!is_numeric($key)) {
                    $newrow[] = $val;
                }
            }
            $values[$qid] = $newrow;
            $val = array_pop($values[$qid]);
            array_push($values[$qid], $val, $val);
        }

        return $values;
    }

    /**
     * Return an array of answer objects by question for the given response id.
     * THIS SHOULD REPLACE response_select.
     *
     * @param int $rid The response id.
     * @return array array answer
     * @throws \dml_exception
     */
    public static function response_answers_by_question($rid) {
        global $DB;

        $answers = [];
        $sql = 'SELECT id, response_id as responseid, question_id as questionid, 0 as choiceid, fileid as value ' .
            'FROM {' . static::response_table() . '} ' .
            'WHERE response_id = ? ';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $record) {
            $answers[$record->questionid][] = answer\answer::create_from_data($record);
        }

        return $answers;
    }

    /**
     * Delete old entries from the questupload_response_file table and also the corresponding entries
     * in the files table.
     * @param int $questionid
     * @param int $responseid
     * @return void
     * @throws \dml_exception
     */
    public static function delete_old_response(int $questionid, int $responseid) {
        global $DB;
        // Check if we have old response files from a former attempt.
        $records = $DB->get_records(static::response_table(), [
            'response_id' => $responseid,
            'question_id' => $questionid,
        ]);
        foreach ($records as $record) {
            $DB->delete_records(
                'files',
                [
                    'component' => 'mod_questupload',
                    'filearea' => 'response_file',
                    'itemid' => $record->id,
                ]
            );
            $DB->delete_records(self::response_table(), ['id' => $record->id]);
        }
    }

    /**
     * Insert a provided response to the question.
     *
     * @param \mod_questupload\responsetype\response\response|\stdClass $responsedata
     * @return bool|int
     * @throws \dml_exception
     */
    public function insert_response($responsedata) {
        global $DB;

        if (!$responsedata instanceof \mod_questupload\responsetype\response\response) {
            $response = \mod_questupload\responsetype\response\response::response_from_webform($responsedata, [$this->question]);
        } else {
            $response = $responsedata;
        }

        if (empty($response) || empty($response->answers[$this->question->id])) {
            return false;
        }

        // Delete any previous attempts for this question/response.
        self::delete_old_response((int)$this->question->id, (int)$response->id);

        $records = [];
        $olditemids = [];
        foreach ($response->answers[$this->question->id] as $answer) {
            $fileid = intval(clean_text($answer->value));
            $olditem = $DB->get_record('files', ['id' => $fileid], 'itemid');
            if (!$olditem) {
                continue;
            }
            $record = new \stdClass();
            $record->response_id = $response->id;
            $record->question_id = $this->question->id;
            $record->fileid = $fileid;
            $recordid = $DB->insert_record(static::response_table(), $record);
            if ($recordid) {
                $records[] = $recordid;
                $olditemids[$recordid] = $olditem->itemid;
            }
        }

        foreach ($olditemids as $recordid => $olditemid) {
            $siblings = $DB->get_records(
                'files',
                [
                    'component' => 'mod_questupload',
                    'filearea' => 'response_file',
                    'itemid' => $olditemid,
                ]
            );
            foreach ($siblings as $sibling) {
                if ($sibling->filename === '.') {
                    continue;
                }
                if ((int)$sibling->id === (int)$DB->get_field(static::response_table(), 'fileid', ['id' => $recordid])) {
                    if (!self::fix_file_itemid($recordid, $sibling)) {
                        return false;
                    }
                    $DB->set_field(static::response_table(), 'fileid', $sibling->id, ['id' => $recordid]);
                    break;
                }
            }
        }

        return empty($records) ? false : reset($records);
    }

    /**
     * Update records in the table file with the new given itemid. To do this, the pathnamehash
     * needs to be recalculated as well.
     * @param int $recordid
     * @param \stdClass $filerecord
     * @return bool
     * @throws \dml_exception
     */
    public static function fix_file_itemid(int $recordid, \stdClass $filerecord): bool {
        global $DB;
        if ((int)$filerecord->itemid === $recordid) {
            return true; // Reference is already good, nothing to do.
        }
        $fs = get_file_storage();
        $file = $fs->get_file_instance($filerecord);
        $newhash = $fs->get_pathname_hash(
            $filerecord->contextid,
            $filerecord->component,
            $filerecord->filearea,
            $recordid,
            $file->get_filepath(),
            $file->get_filename()
        );
        $filerecord->itemid = $recordid;
        $filerecord->pathnamehash = $newhash;
        return $DB->update_record('files', $filerecord);
    }

    /**
     * Provide the necessary response data table name. Should probably always be used with late static binding 'static::' form
     * rather than 'self::' form to allow for class extending.
     *
     * @return string response table name.
     */
    public static function response_table() {
        return 'questupload_response_file';
    }

    /**
     * Provide a template for results screen if defined.
     *
     * @param bool $pdf
     * @return mixed The template string or false/
     */
    public function results_template($pdf = false) {
        if ($pdf) {
            return 'mod_questupload/resultspdf_text';
        } else {
            return 'mod_questupload/results_text';
        }
    }

    /**
     * Provide the result information for the specified result records.
     *
     * @param int|array $rids - A single response id, or array.
     * @param string $sort - Optional display sort.
     * @param boolean $anonymous - Whether or not responses are anonymous.
     * @return string - Display output.
     */
    public function display_results($rids = false, $sort = '', $anonymous = false) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_results($rids, $anonymous)) {
            $numrespondents = count($rids);
            $numresponses = count($rows);
            $pagetags = $this->get_results_tags($rows, $numrespondents, $numresponses, $prtotal);
        } else {
            $pagetags = "";
        }
        return $pagetags;
    }

    /**
     * Provide the result information for the specified result records.
     *
     * @param int|array $rids - A single response id, or array.
     * @param boolean $anonymous - Whether or not responses are anonymous.
     * @return array - Array of data records.
     */
    public function get_results($rids = false, $anonymous = false) {
        global $DB;

        $rsql = '';
        if (!empty($rids)) {
            [$rsql, $params] = $DB->get_in_or_equal($rids);
            $rsql = ' AND response_id ' . $rsql;
        }

        if ($anonymous) {
            $sql = 'SELECT t.id, t.fileid, r.submitted AS submitted, ' .
                'r.questuploadid, r.id AS rid ' .
                'FROM {' . static::response_table() . '} t, ' .
                '{questupload_response} r ' .
                'WHERE question_id=' . $this->question->id . $rsql .
                ' AND t.response_id = r.id ' .
                'ORDER BY r.submitted DESC';
        } else {
            $sql = 'SELECT t.id, t.fileid, r.submitted AS submitted, r.userid, u.username AS username, ' .
                'u.id as usrid, ' .
                'r.questuploadid, r.id AS rid ' .
                'FROM {' . static::response_table() . '} t, ' .
                '{questupload_response} r, ' .
                '{user} u ' .
                'WHERE question_id=' . $this->question->id . $rsql .
                ' AND t.response_id = r.id' .
                ' AND u.id = r.userid ' .
                'ORDER BY u.lastname, u.firstname, r.submitted';
        }
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Override the results tags function for templates for questions with dates.
     *
     * @param array $weights
     * @param int $participants Number of questionnaire participants.
     * @param int $respondents Number of question respondents.
     * @param bool $showtotals
     * @param string $sort
     * @return \stdClass
     */
    public function get_results_tags($weights, $participants, $respondents, $showtotals = 1, $sort = '') {
        $pagetags = new \stdClass();
        if ($respondents == 0) {
            return $pagetags;
        }

        // If array element is an object, outputting non-numeric responses.
        if (is_object(reset($weights))) {
            global $CFG, $SESSION, $questupload, $DB;
            $viewsingleresponse = $questupload->capabilities->viewsingleresponse;
            $nonanonymous = $questupload->respondenttype != 'anonymous';
            if ($viewsingleresponse && $nonanonymous) {
                $currentgroupid = '';
                if (isset($SESSION->questupload->currentgroupid)) {
                    $currentgroupid = $SESSION->questupload->currentgroupid;
                }
                $url = $CFG->wwwroot . '/mod/questupload/report.php?action=vresp&amp;sid=' . $questupload->survey->id .
                    '&currentgroupid=' . $currentgroupid;
            }
            $users = [];
            $evencolor = false;
            foreach ($weights as $row) {
                $response = new \stdClass();
                $fs = get_file_storage();
                $file = $fs->get_file_by_id($row->fileid);

                if ($file) {
                    // There is a file.
                    $imageurl = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    );

                    $response->text = \html_writer::link($imageurl, $file->get_filename());
                    if ($viewsingleresponse && $nonanonymous) {
                        $rurl = $url . '&amp;rid=' . $row->rid . '&amp;individualresponse=1';
                        $title = userdate($row->submitted);
                        if (!isset($users[$row->userid])) {
                            $users[$row->userid] = $DB->get_record('user', ['id' => $row->userid]);
                        }
                        $response->respondent =
                            '<a href="' . $rurl . '" title="' . $title . '">' . fullname($users[$row->userid]) . '</a>';
                    }
                } else {
                    $response->respondent = '';
                }
                // The 'evencolor' attribute is used by the PDF template.
                $response->evencolor = $evencolor;
                $pagetags->responses[] = (object) ['response' => $response];
                $evencolor = !$evencolor;
            }

            if ($showtotals == 1) {
                $pagetags->total = new \stdClass();
                $pagetags->total->total = "$respondents/$participants";
            }
        } else {
            $nbresponses = 0;
            $sum = 0;
            $strtotal = get_string('totalofnumbers', 'questupload');
            $straverage = get_string('average', 'questupload');

            if (!empty($weights) && is_array($weights)) {
                ksort($weights);
                $evencolor = false;
                foreach ($weights as $text => $num) {
                    $response = new \stdClass();
                    $response->text = $text;
                    $response->respondent = $num;
                    // The 'evencolor' attribute is used by the PDF template.
                    $response->evencolor = $evencolor;
                    $nbresponses += $num;
                    $sum += $text * $num;
                    $evencolor = !$evencolor;
                    $pagetags->responses[] = (object) ['response' => $response];
                }

                $response = new \stdClass();
                $response->text = $sum;
                $response->respondent = $strtotal;
                $response->evencolor = $evencolor;
                $pagetags->responses[] = (object) ['response' => $response];
                $evencolor = !$evencolor;

                $response = new \stdClass();
                $response->respondent = $straverage;
                $avg = $sum / $nbresponses;
                $response->text = sprintf('%.' . $this->question->precise . 'f', $avg);
                $response->evencolor = $evencolor;
                $pagetags->responses[] = (object) ['response' => $response];
                $evencolor = !$evencolor;

                if ($showtotals == 1) {
                    $pagetags->total = new \stdClass();
                    $pagetags->total->total = "$respondents/$participants";
                    $pagetags->total->evencolor = $evencolor;
                }
            }
        }

        return $pagetags;
    }



    /**
     * Return download links for exported reports.
     *
     * @param \mod_questupload\question\question $question
     * @param int $responseid
     * @return string
     */
    public static function export_response_links($question, $responseid) {
        global $DB;

        $links = [];
        $records = $DB->get_records(static::response_table(), [
            'response_id' => $responseid,
            'question_id' => $question->id,
        ]);
        $fs = get_file_storage();
        foreach ($records as $record) {
            $file = $fs->get_file_by_id((int)$record->fileid);
            if ($file) {
                $url = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                    true
                );
                $links[] = $file->get_filename() . ': ' . $url->out(false);
            }
        }
        return implode("
", $links);
    }

    /**
     * Configure bulk sql
     *
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config(static::response_table(), 'qrt', false, false, false);
    }
}
