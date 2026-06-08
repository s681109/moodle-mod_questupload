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

namespace mod_questupload\privacy;

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\userlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;

/**
 * Contains class mod_questupload\privacy\provider
 *
 * @package    mod_questupload
 * @copyright  2018 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The collection to add metadata to.
     * @return  collection  The array of metadata
     */
    public static function get_metadata(collection $collection): collection {

        // Add all of the relevant tables and fields to the collection.
        $collection->add_database_table('questupload_response', [
            'userid' => 'privacy:metadata:questupload_response:userid',
            'questuploadid' => 'privacy:metadata:questupload_response:questuploadid',
            'complete' => 'privacy:metadata:questupload_response:complete',
            'grade' => 'privacy:metadata:questupload_response:grade',
            'submitted' => 'privacy:metadata:questupload_response:submitted',
        ], 'privacy:metadata:questupload_response');

        $collection->add_database_table('questupload_response_bool', [
            'response_id' => 'privacy:metadata:questupload_response_bool:response_id',
            'question_id' => 'privacy:metadata:questupload_response_bool:question_id',
            'choice_id' => 'privacy:metadata:questupload_response_bool:choice_id',
        ], 'privacy:metadata:questupload_response_bool');

        $collection->add_database_table('questupload_response_date', [
            'response_id' => 'privacy:metadata:questupload_response_date:response_id',
            'question_id' => 'privacy:metadata:questupload_response_date:question_id',
            'response' => 'privacy:metadata:questupload_response_date:response',
        ], 'privacy:metadata:questupload_response_date');

        $collection->add_database_table('questupload_response_other', [
            'response_id' => 'privacy:metadata:questupload_response_other:response_id',
            'question_id' => 'privacy:metadata:questupload_response_other:question_id',
            'choice_id' => 'privacy:metadata:questupload_response_other:choice_id',
            'response' => 'privacy:metadata:questupload_response_other:response',
        ], 'privacy:metadata:questupload_response_other');

        $collection->add_database_table('questupload_response_rank', [
            'response_id' => 'privacy:metadata:questupload_response_rank:response_id',
            'question_id' => 'privacy:metadata:questupload_response_rank:question_id',
            'choice_id' => 'privacy:metadata:questupload_response_rank:choice_id',
            'rank' => 'privacy:metadata:questupload_response_rank:rankvalue',
        ], 'privacy:metadata:questupload_response_rank');

        $collection->add_database_table('questupload_response_text', [
            'response_id' => 'privacy:metadata:questupload_response_text:response_id',
            'question_id' => 'privacy:metadata:questupload_response_text:question_id',
            'response' => 'privacy:metadata:questupload_response_text:response',
        ], 'privacy:metadata:questupload_response_text');

        $collection->add_database_table('questupload_response_file', [
            'response_id' => 'privacy:metadata:questupload_response_file:response_id',
            'question_id' => 'privacy:metadata:questupload_response_file:question_id',
            'fileid' => 'privacy:metadata:questupload_response_file:fileid',
        ], 'privacy:metadata:questupload_response_file');

        $collection->add_database_table('questupload_resp_multiple', [
            'response_id' => 'privacy:metadata:questupload_resp_multiple:response_id',
            'question_id' => 'privacy:metadata:questupload_resp_multiple:question_id',
            'choice_id' => 'privacy:metadata:questupload_resp_multiple:choice_id',
        ], 'privacy:metadata:questupload_resp_multiple');

        $collection->add_database_table('questupload_resp_single', [
            'response_id' => 'privacy:metadata:questupload_resp_single:response_id',
            'question_id' => 'privacy:metadata:questupload_resp_single:question_id',
            'choice_id' => 'privacy:metadata:questupload_resp_single:choice_id',
        ], 'privacy:metadata:questupload_resp_single');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
             FROM {context} c
       INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
       INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
       INNER JOIN {questupload} q ON q.id = cm.instance
       INNER JOIN {questupload_response} qr ON qr.questuploadid = q.id
            WHERE qr.userid = :attemptuserid
       ";

        $params = [
            'modname' => 'questupload',
            'contextlevel' => CONTEXT_MODULE,
            'attemptuserid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist containing the list of users who have data in this
     * context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['modulename' => 'questupload', 'instanceid' => $context->instanceid];

        // Questionnaire Upload respondents.
        $sql = "SELECT qr.userid
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
              JOIN {questupload} q ON q.id = cm.instance
              JOIN {questupload_response} qr ON qr.questuploadid = q.id
             WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/questupload/questupload.class.php');

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                   q.id AS qid, q.course AS qcourse,
                   qr.id AS responseid, qr.submitted AS lastsaved, qr.complete AS complete
              FROM {context} c
        INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
        INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
        INNER JOIN {questupload} q ON q.id = cm.instance
        INNER JOIN {questupload_response} qr ON qr.questuploadid = q.id
             WHERE c.id {$contextsql}
                   AND qr.userid = :userid
          ORDER BY cm.id, qr.id ASC";

        $params = ['modname' => 'questupload', 'contextlevel' => CONTEXT_MODULE, 'userid' => $user->id] + $contextparams;

        // There can be more than one attempt per instance, so we'll gather them by cmid.
        $lastcmid = 0;
        $responsedata = [];
        $responses = $DB->get_recordset_sql($sql, $params);
        foreach ($responses as $response) {
            // If we've moved to a new choice, then write the last choice data and reinit the choice data array.
            if ($lastcmid != $response->cmid) {
                if (!empty($responsedata)) {
                    $context = \context_module::instance($lastcmid);
                    // Fetch the generic module data for the questupload.
                    $contextdata = \core_privacy\local\request\helper::get_context_data($context, $user);
                    // Merge with attempt data and write it.
                    $contextdata = (object)array_merge((array)$contextdata, $responsedata);
                    \core_privacy\local\request\writer::with_context($context)->export_data([], $contextdata);
                }
                $responsedata = [];
                $lastcmid = $response->cmid;
                $course = $DB->get_record("course", ["id" => $response->qcourse]);
                $cm = get_coursemodule_from_instance("questupload", $response->qid, $course->id);
                $questupload = new \questupload($course, $cm, $response->qid, null);
            }
            $responsedata['responses'][] = [
                'complete' => (($response->complete == 'y') ? get_string('yes') : get_string('no')),
                'lastsaved' => \core_privacy\local\request\transform::datetime($response->lastsaved),
                'questions' => $questupload->get_structured_response($response->responseid),
            ];
        }
        $responses->close();

        // The data for the last activity won't have been written yet, so make sure to write it now!
        if (!empty($responsedata)) {
            $context = \context_module::instance($lastcmid);
            // Fetch the generic module data for the questupload.
            $contextdata = \core_privacy\local\request\helper::get_context_data($context, $user);
            // Merge with attempt data and write it.
            $contextdata = (object)array_merge((array)$contextdata, $responsedata);
            \core_privacy\local\request\writer::with_context($context)->export_data([], $contextdata);
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!($context instanceof \context_module)) {
            return;
        }

        if (!$cm = get_coursemodule_from_id('questupload', $context->instanceid)) {
            return;
        }

        if (!($questupload = $DB->get_record('questupload', ['id' => $cm->instance]))) {
            return;
        }

        if ($responses = $DB->get_recordset('questupload_response', ['questuploadid' => $questupload->id])) {
            self::delete_responses($responses);
        }
        $responses->close();
        $DB->delete_records('questupload_response', ['questuploadid' => $questupload->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_module)) {
                continue;
            }
            if (!$cm = get_coursemodule_from_id('questupload', $context->instanceid)) {
                continue;
            }

            if (!($questupload = $DB->get_record('questupload', ['id' => $cm->instance]))) {
                continue;
            }

            if ($responses = $DB->get_recordset('questupload_response',
                ['questuploadid' => $questupload->id, 'userid' => $userid])) {
                self::delete_responses($responses);
            }
            $responses->close();
            $DB->delete_records('questupload_response', ['questuploadid' => $questupload->id, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The approved context and user information to delete
     * information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$cm = get_coursemodule_from_id('questupload', $context->instanceid)) {
            return;
        }
        if (!($questupload = $DB->get_record('questupload', ['id' => $cm->instance]))) {
            return;
        }

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['questuploadid' => $questupload->id], $userinparams);
        $select = 'questuploadid = :questuploadid AND userid ' . $userinsql;
        if ($responses = $DB->get_recordset_select('questupload_response', $select, $params)) {
            self::delete_responses($responses);
        }
        $responses->close();
        $DB->delete_records_select('questupload_response', $select, $params);
    }

    /**
     * Helper function to delete all the response records for a recordset array of responses.
     *
     * @param \moodle_recordset $responses The list of response records to delete for.
     */
    private static function delete_responses(\moodle_recordset $responses) {
        global $DB;
        $fs = get_file_storage();

        foreach ($responses as $response) {
            if ($context = self::get_context_for_response($response)) {
                $fs->delete_area_files($context->id, 'mod_questupload', 'response_attachment', $response->id);
                $records = $DB->get_records('questupload_response_file', ['response_id' => $response->id], '', 'id');
                foreach ($records as $record) {
                    $fs->delete_area_files($context->id, 'mod_questupload', 'response_file', $record->id);
                }
            }
            $DB->delete_records('questupload_response_bool', ['response_id' => $response->id]);
            $DB->delete_records('questupload_response_date', ['response_id' => $response->id]);
            $DB->delete_records('questupload_resp_multiple', ['response_id' => $response->id]);
            $DB->delete_records('questupload_response_other', ['response_id' => $response->id]);
            $DB->delete_records('questupload_response_rank', ['response_id' => $response->id]);
            $DB->delete_records('questupload_resp_single', ['response_id' => $response->id]);
            $DB->delete_records('questupload_response_text', ['response_id' => $response->id]);
            $DB->delete_records('questupload_response_file', ['response_id' => $response->id]);
        }
    }

    /**
     * Return the module context for a response, if available.
     *
     * @param object $response The response record.
     * @return \context_module|null
     */
    private static function get_context_for_response($response) {
        global $DB;

        $sql = 'SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {questupload} q ON q.id = cm.instance
                 WHERE q.id = :questuploadid';
        if ($cmid = $DB->get_field_sql($sql, ['modname' => 'questupload', 'questuploadid' => $response->questuploadid])) {
            return \context_module::instance($cmid);
        }
        return null;
    }
}
