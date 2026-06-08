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
 * Updates the contents of the survey with the provided data. If no data is provided, it checks for posted data.
 *
 * This library replaces the phpESP application with Moodle specific code. It will eventually
 * replace all of the phpESP application, removing the dependency on that.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/calendar/lib.php');
// Constants.

define ('QUESTUPLOADUNLIMITED', 0);
define ('QUESTUPLOADONCE', 1);
define ('QUESTUPLOADDAILY', 2);
define ('QUESTUPLOADWEEKLY', 3);
define ('QUESTUPLOADMONTHLY', 4);

define ('QUESTUPLOAD_STUDENTVIEWRESPONSES_NEVER', 0);
define ('QUESTUPLOAD_STUDENTVIEWRESPONSES_WHENANSWERED', 1);
define ('QUESTUPLOAD_STUDENTVIEWRESPONSES_WHENCLOSED', 2);
define ('QUESTUPLOAD_STUDENTVIEWRESPONSES_ALWAYS', 3);

define('QUESTUPLOAD_MAX_EVENT_LENGTH', 5 * 24 * 60 * 60);   // 5 days maximum.

define('QUESTUPLOAD_DEFAULT_PAGE_COUNT', 20);

define('QUESTUPLOAD_CONFIRM_DELETE_PERMANENTLY', 'confirmdelpermanentlyq');
define('QUESTUPLOAD_RESTORE_PARAM', 'restoreq');

global $questuploadtypes;
$questuploadtypes = array (QUESTUPLOADUNLIMITED => get_string('qtypeunlimited', 'questupload'),
                              QUESTUPLOADONCE => get_string('qtypeonce', 'questupload'),
                              QUESTUPLOADDAILY => get_string('qtypedaily', 'questupload'),
                              QUESTUPLOADWEEKLY => get_string('qtypeweekly', 'questupload'),
                              QUESTUPLOADMONTHLY => get_string('qtypemonthly', 'questupload'));

global $questuploadrespondents;
$questuploadrespondents = array ('fullname' => get_string('respondenttypefullname', 'questupload'),
                                    'anonymous' => get_string('respondenttypeanonymous', 'questupload'));

global $questuploadrealms;
$questuploadrealms = array ('private' => get_string('private', 'questupload'),
                               'public' => get_string('public', 'questupload'),
                               'template' => get_string('template', 'questupload'));

global $questuploadresponseviewers;
$questuploadresponseviewers = array (
            QUESTUPLOAD_STUDENTVIEWRESPONSES_WHENANSWERED => get_string('responseviewstudentswhenanswered', 'questupload'),
            QUESTUPLOAD_STUDENTVIEWRESPONSES_WHENCLOSED => get_string('responseviewstudentswhenclosed', 'questupload'),
            QUESTUPLOAD_STUDENTVIEWRESPONSES_ALWAYS => get_string('responseviewstudentsalways', 'questupload'),
            QUESTUPLOAD_STUDENTVIEWRESPONSES_NEVER => get_string('responseviewstudentsnever', 'questupload'));

global $autonumbering;
$autonumbering = array (0 => get_string('autonumberno', 'questupload'),
        1 => get_string('autonumberquestions', 'questupload'),
        2 => get_string('autonumberpages', 'questupload'),
        3 => get_string('autonumberpagesandquestions', 'questupload'));

/**
 * Return the choice values for the content.
 * @param string $content
 * @return stdClass
 */
function questupload_choice_values($content) {

    // If we run the content through format_text first, any filters we want to use (e.g. multilanguage) should work.
    // examines the content of a possible answer from radio button, check boxes or rate question
    // returns ->text to be displayed, ->image if present, ->modname name of modality, image ->title.
    $contents = new stdClass();
    $contents->text = '';
    $contents->image = '';
    $contents->modname = '';
    $contents->title = '';
    // Has image.
    if (preg_match('/(<img)\s .*(src="(.[^"]{1,})")/isxmU', $content, $matches)) {
        $contents->image = $matches[0];
        $imageurl = $matches[3];
        // Image has a title or alt text: use one of them.
        if (preg_match('/(title=.)([^"]{1,})/', $content, $matches)
             || preg_match('/(alt=.)([^"]{1,})/', $content, $matches) ) {
            $contents->title = $matches[2];
        } else {
            // Image has no title nor alt text: use its filename (without the extension).
            preg_match("/.*\/(.*)\..*$/", $imageurl, $matches);
            $contents->title = $matches[1];
        }
        // Content has text or named modality plus an image.
        if (preg_match('/(.*)(<img.*)/', $content, $matches)) {
            $content = $matches[1];
        } else {
            // Just an image.
            return $contents;
        }
    }

    // Check for score value first (used e.g. by personality test feature).
    $r = preg_match_all("/^(\d{1,2}=)(.*)$/", $content, $matches);
    if ($r) {
        $content = $matches[2][0];
    }

    // Look for named modalities.
    $contents->text = $content;
    // DEV JR from version 2.5, a double colon :: must be used here instead of the equal sign.
    if ($pos = strpos($content, '::')) {
        $contents->text = substr($content, $pos + 2);
        $contents->modname = substr($content, 0, $pos);
    }
    return $contents;
}

/**
 * Get the information about the standard questupload JavaScript module.
 * @return array a standard jsmodule structure.
 */
function questupload_get_js_module() {
    return [
            'name' => 'mod_questupload',
            'fullpath' => '/mod/questupload/module.js',
            'requires' => ['base', 'dom', 'event-delegate', 'event-key',
                    'core_question_engine', 'moodle-core-formchangechecker'],
            'strings' => [
                    ['cancel', 'moodle'],
                    ['flagged', 'question'],
                    ['functiondisabledbysecuremode', 'quiz'],
                    ['startattempt', 'quiz'],
                    ['timesup', 'quiz'],
                    ['changesmadereallygoaway', 'moodle'],
                    ['leftpart', 'questupload'],
                    ['leftpartdefault', 'questupload'],
                    ['middlepart', 'questupload'],
                    ['middlepartdefault', 'questupload'],
                    ['middlepartwithtwovalues', 'questupload'],
                    ['middlepartwithtwovaluesdefault', 'questupload'],
                    ['rightpart', 'questupload'],
                    ['rightpartdefault', 'questupload'],
                    ['where', 'questupload'],
            ],
    ];
}

/**
 * Get all the questupload responses for a user.
 * @param int $questuploadid
 * @param int $userid
 * @param bool $complete
 * @return array
 */
function questupload_get_user_responses($questuploadid, $userid, $complete=true) {
    global $DB;
    $andcomplete = '';
    if ($complete) {
        $andcomplete = " AND complete = 'y' ";
    }
    return $DB->get_records_sql ("SELECT *
        FROM {questupload_response}
        WHERE questuploadid = ?
        AND userid = ?
        ".$andcomplete."
        ORDER BY submitted ASC ", array($questuploadid, $userid)) ?? [];
}

/**
 * get the capabilities for the questupload
 * @param int $cmid
 * @return object the available capabilities from current user
 */
function questupload_load_capabilities($cmid) {
    static $cb;

    if (isset($cb)) {
        return $cb;
    }

    $context = questupload_get_context($cmid);

    $cb = new stdClass();
    $cb->view = has_capability('mod/questupload:view', $context);
    $cb->submit = has_capability('mod/questupload:submit', $context);
    $cb->viewsingleresponse = has_capability('mod/questupload:viewsingleresponse', $context);
    $cb->submissionnotification = has_capability('mod/questupload:submissionnotification', $context);
    $cb->downloadresponses = has_capability('mod/questupload:downloadresponses', $context);
    $cb->deleteresponses = has_capability('mod/questupload:deleteresponses', $context);
    $cb->manage = has_capability('mod/questupload:manage', $context);
    $cb->editquestions = has_capability('mod/questupload:editquestions', $context);
    $cb->createtemplates = has_capability('mod/questupload:createtemplates', $context);
    $cb->createpublic = has_capability('mod/questupload:createpublic', $context);
    $cb->readownresponses = has_capability('mod/questupload:readownresponses', $context);
    $cb->readallresponses = has_capability('mod/questupload:readallresponses', $context);
    $cb->readallresponseanytime = has_capability('mod/questupload:readallresponseanytime', $context);
    $cb->printblank = has_capability('mod/questupload:printblank', $context);
    $cb->preview = has_capability('mod/questupload:preview', $context);

    $cb->viewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $context, null, false);

    return $cb;
}

/**
 * returns the context-id related to the given coursemodule-id
 * @param int $cmid the coursemodule-id
 * @return object $context
 */
function questupload_get_context($cmid) {
    static $context;

    if (isset($context)) {
        return $context;
    }

    if (!$context = context_module::instance($cmid)) {
        throw new \moodle_exception('badcontext', 'mod_questupload');
    }
    return $context;
}

/**
 * This function *really* shouldn't be needed, but since sometimes we can end up with
 * orphaned surveys, this will clean them up.
 * @return bool
 * @throws dml_exception
 */
function questupload_cleanup() {
    global $DB;

    // Find surveys that don't have questuploads associated with them.
    $sql = 'SELECT qs.* FROM {questupload_survey} qs '.
           'LEFT JOIN {questupload} q ON q.sid = qs.id '.
           'WHERE q.sid IS NULL';

    if ($surveys = $DB->get_records_sql($sql)) {
        foreach ($surveys as $survey) {
            questupload_delete_survey($survey->id, 0);
        }
    }
    // Find deleted questions and remove them from database (with their associated choices, etc.).
    return true;
}

/**
 * Delete the survey.
 * @param int $sid
 * @param int $questuploadid
 * @return bool
 */
function questupload_delete_survey($sid, $questuploadid) {
    global $DB;
    $status = true;
    // Delete all survey attempts and responses.
    if ($responses = $DB->get_records('questupload_response', ['questuploadid' => $questuploadid], 'id')) {
        foreach ($responses as $response) {
            $status = $status && questupload_delete_response($response);
        }
    }

    // There really shouldn't be any more, but just to make sure...
    $DB->delete_records('questupload_response', ['questuploadid' => $questuploadid]);

    // Delete all question data for the survey.
    if ($questions = $DB->get_records('questupload_question', ['surveyid' => $sid], 'id')) {
        foreach ($questions as $question) {
            $DB->delete_records('questupload_quest_choice', ['question_id' => $question->id]);
            questupload_delete_dependencies($question->id);
        }
        $status = $status && $DB->delete_records('questupload_question', ['surveyid' => $sid]);
        // Just to make sure.
        $status = $status && $DB->delete_records('questupload_dependency', ['surveyid' => $sid]);
    }

    // Delete all feedback sections and feedback messages for the survey.
    if ($fbsections = $DB->get_records('questupload_fb_sections', ['surveyid' => $sid], 'id')) {
        foreach ($fbsections as $fbsection) {
            $DB->delete_records('questupload_feedback', ['sectionid' => $fbsection->id]);
        }
        $status = $status && $DB->delete_records('questupload_fb_sections', ['surveyid' => $sid]);
    }

    $status = $status && $DB->delete_records('questupload_survey', ['id' => $sid]);

    return $status;
}

/**
 * Delete permanently questions and data reference.
 *
 * @param int $qid question id.
 * @param int $sid survey question id.
 * @return void
 */
function questupload_delete_permanently_questions($qid, $sid) {
    global $DB;
    $select = 'id = :id AND surveyid = :sid AND deleted IS NOT NULL';
    $DB->delete_records_select('questupload_question', $select , ['id' => $qid, 'sid' => $sid]);
    $DB->delete_records('questupload_response', ['questuploadid' => $qid]);
    questupload_delete_responses($qid);
    questupload_delete_dependencies($qid);
}

/**
 * Log question deleted event.
 *
 * @param int $cmid of module.
 * @param string $questiontype of question.
 * @param int $courseid of question.
 * @return void.
 */
function questupload_observe_event_delete($cmid, $questiontype, $courseid) {
    $context = context_module::instance($cmid);
    $params = [
            'context' => $context,
            'courseid' => $courseid,
            'other' => ['questiontype' => $questiontype]
    ];
    $event = \mod_questupload\event\question_deleted::create($params);
    $event->trigger();
}

/**
 * Restore deleted questions.
 *
 * @param int $qid question id.
 * @param int $sid survey id.
 * @return void
 */
function questupload_restore_deleted_question($qid, $sid) {
    global $DB;
    // Get current deleted question and last position.
    $sql = "SELECT *, (
                    SELECT position + 1
                      FROM {questupload_question}
                     WHERE surveyid = ?
                       AND deleted IS NULL
                  ORDER BY position DESC
                     LIMIT 1 ) as lastposition
              FROM {questupload_question}
             WHERE id = ?
               AND surveyid = ?
               AND deleted IS NOT NULL";
    $question = $DB->get_record_sql($sql, [$sid, $qid, $sid]);
    if ($question) {
        // Update question using Moodle API. Only 'id' is needed for update_record.
        $question->deleted = null;
        $question->position = $question->lastposition ?? 1;
        $DB->update_record('questupload_question', $question);
    }
}

/**
 * Get range of time permanently in setup cron task.
 */
function questupload_get_range_time_permanently() {
    return get_config('questupload_questiondeletion', 'duration');
}

/**
 * Delete the response.
 * @param stdClass $response
 * @param string $questupload
 * @return bool
 */
function questupload_delete_response($response, $questupload='') {
    global $DB;
    $status = true;
    $cm = '';
    $rid = $response->id;
    // The questupload_delete_survey function does not send the questupload array.
    if ($questupload != '') {
        $cm = get_coursemodule_from_instance("questupload", $questupload->id, $questupload->course->id);
    }

    questupload_delete_response_files($response, $cm);

    // Delete all of the response data for a response.
    $DB->delete_records('questupload_response_bool', array('response_id' => $rid));
    $DB->delete_records('questupload_response_date', array('response_id' => $rid));
    $DB->delete_records('questupload_resp_multiple', array('response_id' => $rid));
    $DB->delete_records('questupload_response_other', array('response_id' => $rid));
    $DB->delete_records('questupload_response_rank', array('response_id' => $rid));
    $DB->delete_records('questupload_resp_single', array('response_id' => $rid));
    $DB->delete_records('questupload_response_text', array('response_id' => $rid));
    $DB->delete_records('questupload_response_file', array('response_id' => $rid));

    $status = $status && $DB->delete_records('questupload_response', array('id' => $rid));

    if ($status && $cm) {
        // Update completion state if necessary.
        $completion = new completion_info($questupload->course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && $questupload->completionsubmit) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $response->userid);
        }
    }

    return $status;
}

/**
 * Delete all responses for the questupload.
 * @param int $qid
 * @return bool
 */
function questupload_delete_responses($qid) {
    global $DB;

    // Delete all of the response data for a question.
    $DB->delete_records('questupload_response_bool', ['question_id' => $qid]);
    $DB->delete_records('questupload_response_date', ['question_id' => $qid]);
    $DB->delete_records('questupload_resp_multiple', ['question_id' => $qid]);
    $DB->delete_records('questupload_response_other', ['question_id' => $qid]);
    $DB->delete_records('questupload_response_rank', ['question_id' => $qid]);
    $DB->delete_records('questupload_resp_single', ['question_id' => $qid]);
    $DB->delete_records('questupload_response_text', ['question_id' => $qid]);
    questupload_delete_question_response_files($qid);
    $DB->delete_records('questupload_response_file', ['question_id' => $qid]);

    return true;
}

/**
 * Delete response attachment files for a response.
 *
 * @param stdClass $response The response record.
 * @param stdClass|string $cm Course module record, if already known.
 */
function questupload_delete_response_files($response, $cm = '') {
    global $DB;

    $context = null;
    if (!empty($cm)) {
        $context = context_module::instance($cm->id);
    } else if (!empty($response->questuploadid)) {
        $sql = 'SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {questupload} q ON q.id = cm.instance
                 WHERE q.id = :questuploadid';
        if ($cmid = $DB->get_field_sql($sql, ['modname' => 'questupload', 'questuploadid' => $response->questuploadid])) {
            $context = context_module::instance($cmid);
        }
    }

    if ($context) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_questupload', 'response_attachment', $response->id);
        $records = $DB->get_records('questupload_response_file', ['response_id' => $response->id], '', 'id');
        foreach ($records as $record) {
            $fs->delete_area_files($context->id, 'mod_questupload', 'response_file', $record->id);
        }
    }
}

/**
 * Delete response attachment files for a question.
 *
 * @param int $qid The question id.
 */
function questupload_delete_question_response_files($qid) {
    global $DB;

    $fs = get_file_storage();
    $files = $DB->get_recordset('files', [
        'component' => 'mod_questupload',
        'filearea' => 'response_attachment',
        'filepath' => '/' . $qid . '/',
    ]);
    foreach ($files as $file) {
        $fs->get_file_instance($file)->delete();
    }
    $files->close();

    $sql = 'SELECT f.*
              FROM {files} f
              JOIN {questupload_response_file} rf ON rf.id = f.itemid
             WHERE f.component = :component
               AND f.filearea = :filearea
               AND rf.question_id = :questionid';
    $files = $DB->get_recordset_sql($sql, [
        'component' => 'mod_questupload',
        'filearea' => 'response_file',
        'questionid' => $qid,
    ]);
    foreach ($files as $file) {
        $fs->get_file_instance($file)->delete();
    }
    $files->close();
}

/**
 * Delete all dependencies for the questupload.
 * @param int $qid
 * @return bool
 */
function questupload_delete_dependencies($qid) {
    global $DB;

    // Delete all dependencies for this question.
    $DB->delete_records('questupload_dependency', ['questionid' => $qid]);
    $DB->delete_records('questupload_dependency', ['dependquestionid' => $qid]);

    return true;
}

/**
 * Delete all page break deleted.
 *
 * @param int $sid question survey id.
 */
function questupload_delete_pagebreaks($sid) {
    global $DB;
    $DB->delete_records_select('questupload_question',
            'surveyid = :sid AND deleted IS NOT NULL AND type_id = :type_id',
            [
                    'sid' => $sid,
                    'type_id' => QUESPAGEBREAK
            ]);
}

/**
 * Get a survey selection records.
 * @param int $courseid
 * @param string $type
 * @return array|false
 */
function questupload_get_survey_list($courseid=0, $type='') {
    global $DB;

    if ($courseid == 0) {
        if (isadmin()) {
            $sql = "SELECT id,name,courseid,realm,status " .
                   "{questupload_survey} " .
                   "ORDER BY realm,name ";
            $params = null;
        } else {
            return false;
        }
    } else {
        if ($type == 'public') {
            $sql = "SELECT s.id,s.name,s.courseid,s.realm,s.status,s.title,q.id as qid,q.name as qname " .
                   "FROM {questupload} q " .
                   "INNER JOIN {questupload_survey} s ON s.id = q.sid AND s.courseid = q.course " .
                   "WHERE realm = ? " .
                   "ORDER BY realm,name ";
            $params = [$type];
        } else if ($type == 'template') {
            $sql = "SELECT s.id,s.name,s.courseid,s.realm,s.status,s.title,q.id as qid,q.name as qname " .
                   "FROM {questupload} q " .
                   "INNER JOIN {questupload_survey} s ON s.id = q.sid AND s.courseid = q.course " .
                   "WHERE (realm = ?) " .
                   "ORDER BY realm,name ";
            $params = [$type];
        } else if ($type == 'private') {
            $sql = "SELECT s.id,s.name,s.courseid,s.realm,s.status,q.id as qid,q.name as qname " .
                "FROM {questupload} q " .
                "INNER JOIN {questupload_survey} s ON s.id = q.sid " .
                "WHERE s.courseid = ? and realm = ? " .
                "ORDER BY realm,name ";
            $params = [$courseid, $type];

        } else {
            // Current get_survey_list is called from function questupload_reset_userdata so we need to get a
            // complete list of all questuploads in current course to reset them.
            $sql = "SELECT s.id,s.name,s.courseid,s.realm,s.status,q.id as qid,q.name as qname " .
                   "FROM {questupload} q " .
                    "INNER JOIN {questupload_survey} s ON s.id = q.sid AND s.courseid = q.course " .
                   "WHERE s.courseid = ? " .
                   "ORDER BY realm,name ";
            $params = [$courseid];
        }
    }
    return $DB->get_records_sql($sql, $params) ?? [];
}

/**
 * Get survey selection list.
 * @param int $courseid
 * @param string $type
 * @return array
 */
function questupload_get_survey_select($courseid=0, $type='') {
    global $OUTPUT, $DB;

    $surveylist = array();

    if ($surveys = questupload_get_survey_list($courseid, $type)) {
        $strpreview = get_string('preview_questupload', 'questupload');
        foreach ($surveys as $survey) {
            $originalcourse = $DB->get_record('course', ['id' => $survey->courseid]);
            if (!$originalcourse) {
                // This should not happen, but we found a case where a public survey
                // still existed in a course that had been deleted, and so this
                // code lead to a notice, and a broken link. Since that is useless
                // we just skip surveys like this.
                continue;
            }

            // Prevent creating a copy of a public questupload IN THE SAME COURSE as the original.
            if (($type == 'public') && ($survey->courseid == $courseid)) {
                continue;
            } else {
                $args = "sid={$survey->id}&popup=1";
                if (!empty($survey->qid)) {
                    $args .= "&qid={$survey->qid}";
                }
                $link = new moodle_url("/mod/questupload/preview.php?{$args}");
                $action = new popup_action('click', $link);
                $label = $OUTPUT->action_link($link, $survey->qname.' ['.$originalcourse->fullname.']',
                    $action, array('title' => $strpreview));
                $surveylist[$type.'-'.$survey->id] = $label;
            }
        }
    }
    return $surveylist;
}

/**
 * Return the language string for the specified question type.
 * @param int $id
 * @return lang_string|mixed|string
 * @throws coding_exception
 */
function questupload_get_type ($id) {
    switch ($id) {
        case 1:
            return get_string('yesno', 'questupload');
        case 2:
            return get_string('textbox', 'questupload');
        case 3:
            return get_string('essaybox', 'questupload');
        case 4:
            return get_string('radiobuttons', 'questupload');
        case 5:
            return get_string('checkboxes', 'questupload');
        case 6:
            return get_string('dropdown', 'questupload');
        case 8:
            return get_string('ratescale', 'questupload');
        case 9:
            return get_string('date', 'questupload');
        case 10:
            return get_string('numeric', 'questupload');
        case 11:
            return get_string('slider', 'questupload');
        case 12:
            return get_string('fileupload', 'questupload');
        case 100:
            return get_string('sectiontext', 'questupload');
        case 99:
            return get_string('sectionbreak', 'questupload');
        default:
        return $id;
    }
}

/**
 * This creates new events given as opendate and closedate by $questupload.
 * @param object $questupload
 * @return void
 */
function questupload_set_events($questupload) {
    // Adding the questupload to the eventtable.
    global $DB;
    if ($events = $DB->get_records('event', array('modulename' => 'questupload', 'instance' => $questupload->id))) {
        foreach ($events as $event) {
            $event = calendar_event::load($event);
            $event->delete();
        }
    }

    // The open-event.
    $event = new stdClass;
    $event->description = $questupload->name;
    $event->courseid = $questupload->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->modulename = 'questupload';
    $event->instance = $questupload->id;
    $event->eventtype = 'open';
    $event->type = CALENDAR_EVENT_TYPE_ACTION;
    $event->timestart = $questupload->opendate;
    $event->visible = instance_is_visible('questupload', $questupload);
    $event->timeduration = ($questupload->closedate - $questupload->opendate);

    if ($questupload->closedate && $questupload->opendate && ($event->timeduration <= QUESTUPLOAD_MAX_EVENT_LENGTH)) {
        // Single event for the whole questupload.
        $event->name = $questupload->name;
        $event->timesort = $questupload->opendate;
        calendar_event::create($event);
    } else {
        // Separate start and end events.
        $event->timeduration = 0;
        if ($questupload->opendate) {
            $event->name = $questupload->name.' ('.get_string('questuploadopens', 'questupload').')';
            $event->timesort = $questupload->opendate;
            calendar_event::create($event);
            unset($event->id); // So we can use the same object for the close event.
        }
        if ($questupload->closedate) {
            $event->name = $questupload->name.' ('.get_string('questuploadcloses', 'questupload').')';
            $event->timestart = $questupload->closedate;
            $event->timesort = $questupload->closedate;
            $event->eventtype = 'close';
            calendar_event::create($event);
        }
    }
}

/**
 * Get users who have not completed the questupload
 *
 * @param object $cm
 * @param int $sid
 * @param bool $group single groupid
 * @param string $sort
 * @param bool $startpage
 * @param bool $pagecount
 * @return object the userrecords
 * @throws coding_exception
 * @throws dml_exception
 */
function questupload_get_incomplete_users($cm, $sid,
                $group = false,
                $sort = '',
                $startpage = false,
                $pagecount = false) {

    global $DB;

    $context = context_module::instance($cm->id);

    // First get all users who can complete this questupload.
    $cap = 'mod/questupload:submit';
    $fields = 'u.id, u.username';
    if (!$allusers = get_enrolled_users($context, $cap, $group, $fields, $sort)) {
        return false;
    }
    $allusers = array_keys($allusers);

    // Nnow get all completed questuploads.
    $params = array('questuploadid' => $cm->instance, 'complete' => 'y');
    $sql = "SELECT userid FROM {questupload_response} " .
           "WHERE questuploadid = :questuploadid AND complete = :complete " .
           "GROUP BY userid ";

    if (!$completedusers = $DB->get_records_sql($sql, $params)) {
        return $allusers;
    }
    $completedusers = array_keys($completedusers);
    // Now strike all completedusers from allusers.
    $allusers = array_diff($allusers, $completedusers);
    // For paging I use array_slice().
    if (($startpage !== false) && ($pagecount !== false)) {
        $allusers = array_slice($allusers, $startpage, $pagecount);
    }
    return $allusers;
}

/**
 * Called by HTML editor in showrespondents and Essay question. Based on question/essay/renderer.
 * Pending general solution to using the HTML editor outside of moodleforms in Moodle pages.
 * @param int $context
 * @return array
 */
function questupload_get_editor_options($context) {
    return array(
                    'subdirs' => 0,
                    'maxbytes' => 0,
                    'maxfiles' => -1,
                    'context' => $context,
                    'noclean' => 0,
                    'trusttext' => 0
    );
}

/**
 * Get the parent of a child question.
 * @param stdClass $question
 * @return array
 */
function questupload_get_parent ($question) {
    global $DB;
    $qid = $question->id;
    $parent = array();
    $dependquestion = $DB->get_record('questupload_question', ['id' => $question->dependquestionid],
        'id, position, name, type_id');
    if (is_object($dependquestion)) {
        $qdependchoice = '';
        switch ($dependquestion->type_id) {
            case QUESRADIO:
            case QUESDROP:
            case QUESCHECK:
                $dependchoice = $DB->get_record('questupload_quest_choice', ['id' => $question->dependchoiceid], 'id,content');
                $qdependchoice = $dependchoice->id;
                $dependchoice = $dependchoice->content;

                $contents = questupload_choice_values($dependchoice);
                if ($contents->modname) {
                    $dependchoice = $contents->modname;
                }
                break;
            case QUESYESNO:
                switch ($question->dependchoiceid) {
                    case 0:
                        $dependchoice = get_string('yes');
                        $qdependchoice = 'y';
                        break;
                    case 1:
                        $dependchoice = get_string('no');
                        $qdependchoice = 'n';
                        break;
                }
                break;
        }
        // Qdependquestion, parenttype and qdependchoice fields to be used in preview mode.
        $parent[$qid]['qdependquestion'] = 'q'.$dependquestion->id;
        $parent[$qid]['qdependchoice'] = $qdependchoice;
        $parent[$qid]['parenttype'] = $dependquestion->type_id;
        // Other fields to be used in Questions edit mode.
        $parent[$qid]['position'] = $question->position;
        $parent[$qid]['name'] = $question->name;
        $parent[$qid]['content'] = $question->content;
        $parent[$qid]['parentposition'] = $dependquestion->position;
        $parent[$qid]['parent'] = format_string($dependquestion->name) . '->' . format_string ($dependchoice);
    }
    return $parent;
}

/**
 * Get parent position of all child questions in current questupload.
 * Use the parent with the largest position value.
 *
 * @param array $questions
 * @return array An array with Child-ID->Parentposition.
 */
function questupload_get_parent_positions ($questions) {
    $parentpositions = array();
    foreach ($questions as $question) {
        foreach ($question->dependencies as $dependency) {
            $dependquestion = $dependency->dependquestionid;
            if (isset($dependquestion) && $dependquestion != 0) {
                $childid = $question->id;
                $parentpos = $questions[$dependquestion]->position;

                if (!isset($parentpositions[$childid])) {
                    $parentpositions[$childid] = $parentpos;
                }
                if (isset ($parentpositions[$childid]) && $parentpos > $parentpositions[$childid]) {
                    $parentpositions[$childid] = $parentpos;
                }
            }
        }
    }
    return $parentpositions;
}

/**
 * Get child position of all parent questions in current questupload.
 * Use the child with the smallest position value.
 *
 * @param array $questions
 * @return array An array with Parent-ID->Childposition.
 */
function questupload_get_child_positions ($questions) {
    $childpositions = array();
    foreach ($questions as $question) {
        foreach ($question->dependencies as $dependency) {
            $dependquestion = $dependency->dependquestionid;
            if (isset($dependquestion) && $dependquestion != 0) {
                $parentid = $questions[$dependquestion]->id; // Equals $dependquestion?.
                $childpos = $question->position;

                if (!isset($childpositions[$parentid])) {
                    $childpositions[$parentid] = $childpos;
                }

                if (isset ($childpositions[$parentid]) && $childpos < $childpositions[$parentid]) {
                    $childpositions[$parentid] = $childpos;
                }
            }
        }
    }
    return $childpositions;
}

/**
 * Check that the needed page breaks are present to separate child questions.
 * @param stdClass $questupload
 * @return false|lang_string|string
 */
function questupload_check_page_breaks($questupload) {
    global $DB;
    $msg = '';
    // Store the new page breaks ids.
    $newpbids = array();
    $delpb = 0;
    $sid = $questupload->survey->id;
    $positions = array();
    if ($questions = $DB->get_records_select('questupload_question',
            'surveyid = :sid AND deleted IS NULL', ['sid' => $sid], 'position')) {
        foreach ($questions as $key => $qu) {
            $newqu = new stdClass();
            $newqu->question_id = $key;
            $newqu->type_id = $qu->type_id;
            $newqu->qname = $qu->name;
            $newqu->qpos = $qu->position;

            $dependencies = $DB->get_records('questupload_dependency', ['questionid' => $key, 'surveyid' => $sid],
                    'id ASC', 'id, dependquestionid, dependchoiceid, dependlogic');
            $newqu->dependencies = $dependencies ?? [];
            $positions[] = (array)$newqu;
        }
    }
    $count = count($positions);

    for ($i = $count - 1; $i >= 0; $i--) {
        $qu = $positions[$i];
        $questionnb = $i;
        $prevqu = null;
        $prevtypeid = null;
        if ($i > 0) {
            $prevqu = $positions[$i - 1];
            $prevtypeid = $prevqu['type_id'];
        }
        if ($qu['type_id'] == QUESPAGEBREAK) {
            $questionnb--;
            // If more than one consecutive page breaks, remove extra one(s).
            // Remove that extra page break in 1st position.
            if ($prevtypeid == QUESPAGEBREAK || $i == $count - 1 || $qu['qpos'] == 1) {
                $qid = $qu['question_id'];
                $delpb ++;
                $msg .= get_string("checkbreaksremoved", "questupload", $delpb).'<br />';
                // Need to reload questions.
                if ($questions = $DB->get_records_select('questupload_question',
                        'surveyid = :sid AND deleted IS NULL', ['sid' => $sid], 'id')) {


                    $DB->set_field('questupload_question', 'deleted', time(), ['id' => $qid, 'surveyid' => $sid]);
                    $select = 'surveyid = :sid AND deleted IS NULL AND position > :pos';
                    $records = $DB->get_records_select('questupload_question', $select,
                            ['sid' => $sid, 'pos' => $questions[$qid]->position], 'position ASC');
                    if ($records) {
                        foreach ($records as $record) {
                            $DB->set_field('questupload_question', 'position', $record->position - 1, ['id' => $record->id]);
                        }
                    }
                }
            }
        }
        // Add pagebreak between question child and not dependent question that follows.
        if ($qu['type_id'] != QUESPAGEBREAK) {
            if ($prevqu) {
                $prevdependencies = $prevqu['dependencies'];
                $outerdependencies = count($qu['dependencies']) >= count($prevdependencies) ?
                    $qu['dependencies'] : $prevdependencies;
                $innerdependencies = count($qu['dependencies']) < count($prevdependencies) ?
                    $qu['dependencies'] : $prevdependencies;

                $okeys = [];
                $ikeys = [];
                foreach ($outerdependencies as $okey => $outerdependency) {
                    foreach ($innerdependencies as $ikey => $innerdependency) {
                        if ($outerdependency->dependquestionid === $innerdependency->dependquestionid &&
                                $outerdependency->dependchoiceid === $innerdependency->dependchoiceid &&
                                $outerdependency->dependlogic === $innerdependency->dependlogic) {
                            $okeys[] = $okey;
                            $ikeys[] = $ikey;
                        }
                    }
                }

                foreach ($okeys as $key) {
                    if (key_exists($key, $outerdependencies)) {
                        unset($outerdependencies[$key]);
                    }
                }
                foreach ($ikeys as $key) {
                    if (key_exists($key, $innerdependencies)) {
                        unset($innerdependencies[$key]);
                    }
                }

                $diffdependencies = count($outerdependencies) + count($innerdependencies);

                if (($prevtypeid != QUESPAGEBREAK && $diffdependencies != 0)
                        || (!isset($qu['dependencies']) && isset($prevdependencies))) {
                    $sql = "SELECT MAX(position) as maxpos
                              FROM {questupload_question}
                             WHERE surveyid = :sid
                               AND deleted IS NULL";
                    if ($record = $DB->get_record_sql($sql, ['sid' => $questupload->survey->id])) {
                        $pos = $record->maxpos + 1;
                    } else {
                        $pos = 1;
                    }
                    $question = new stdClass();
                    $question->surveyid = $questupload->survey->id;
                    $question->type_id = QUESPAGEBREAK;
                    $question->position = $pos;
                    $question->content = 'break';

                    if (!($newqid = $DB->insert_record('questupload_question', $question))) {
                        return (false);
                    }
                    $newpbids[] = $newqid;
                    $questupload = new questupload($course, $cm, $questupload->id, null);
                    $questupload->move_question($newqid, $qu['qpos']);
                }
            }
        }
    }
    if (empty($newpbids) && !$msg) {
        $msg = get_string('checkbreaksok', 'questupload');
    } else if ($newpbids) {
        $msg .= get_string('checkbreaksadded', 'questupload').'&nbsp;';
        $newpbids = array_reverse ($newpbids);
        $questupload = new questupload($course, $cm, $questupload->id, null);
        foreach ($newpbids as $newpbid) {
            $msg .= $questupload->questions[$newpbid]->position.'&nbsp;';
        }
    }
    return($msg);
}

/**
 * Code snippet used to set up the questionform.
 * @param stdClass $questupload
 * @param int $qid
 * @param int $qtype
 * @return mixed|\mod_questupload\question\question
 */
function questupload_prep_for_questionform($questupload, $qid, $qtype) {
    $context = context_module::instance($questupload->cm->id);
    if ($qid != 0) {
        $question = clone($questupload->questions[$qid]);
        $question->qid = $question->id;
        $question->sid = $questupload->survey->id;
        $question->id = $questupload->cm->id;
        $draftideditor = file_get_submitted_draft_itemid('question');
        $content = file_prepare_draft_area($draftideditor, $context->id, 'mod_questupload', 'question',
                                           $qid, array('subdirs' => true), $question->content);
        $question->content = array('text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor);

        if (isset($question->dependencies)) {
            foreach ($question->dependencies as $dependencies) {
                if ($dependencies->dependandor === "and") {
                    $question->dependquestions_and[] = $dependencies->dependquestionid.','.$dependencies->dependchoiceid;
                    $question->dependlogic_and[] = $dependencies->dependlogic;
                } else if ($dependencies->dependandor === "or") {
                    $question->dependquestions_or[] = $dependencies->dependquestionid.','.$dependencies->dependchoiceid;
                    $question->dependlogic_or[] = $dependencies->dependlogic;
                }
            }
        }
    } else {
        $question = \mod_questupload\question\question::question_builder($qtype);
        $question->sid = $questupload->survey->id;
        $question->id = $questupload->cm->id;
        $question->type_id = $qtype;
        $question->type = '';
        $draftideditor = file_get_submitted_draft_itemid('question');
        $content = file_prepare_draft_area($draftideditor, $context->id, 'mod_questupload', 'question',
                                           null, array('subdirs' => true), '');
        $question->content = array('text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor);
    }
    return $question;
}

/**
 * Get the standard page contructs and check for validity.
 * @param int $id The coursemodule id.
 * @param int $a  The module instance id.
 * @return array An array with the $cm, $course, and $questupload records in that order.
 */
function questupload_get_standard_page_items($id = null, $a = null) {
    global $DB;

    if ($id) {
        if (! $cm = get_coursemodule_from_id('questupload', $id)) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
        }

        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            throw new \moodle_exception('coursemisconf', 'mod_questupload');
        }

        if (! $questupload = $DB->get_record("questupload", array("id" => $cm->instance))) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
        }

    } else {
        if (! $questupload = $DB->get_record("questupload", array("id" => $a))) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
        }
        if (! $course = $DB->get_record("course", array("id" => $questupload->course))) {
            throw new \moodle_exception('coursemisconf', 'mod_questupload');
        }
        if (! $cm = get_coursemodule_from_instance("questupload", $questupload->id, $course->id)) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
        }
    }

    return (array($cm, $course, $questupload));
}


/**
 * Count responses already saved for that question.
 *
 * @param int $qid question id.
 * @param int $qtype question type.
 * @return int number or 0 if responses were not found.
 */
function count_reponses_question(int $qid, int $qtype): int {
    global $DB;

    $countresps = 0;
    if ($qtype != QUESSECTIONTEXT) {
        $responsetable = $DB->get_field('questupload_question_type', 'response_table', ['typeid' => $qtype]);
        if (!empty($responsetable)) {
            $countresps = $DB->count_records('questupload_'.$responsetable, ['question_id' => $qid]);
        }
    }

    return $countresps;
}


/**
 * Create options for remove old responses in the questionare.
 *
 * @return array
 */
function questupload_create_remove_options() {
    $options = [];
    $options[0] = get_string('removeoldresponsesdefault', 'questupload');
    for ($i = 1; $i <= 36; $i++) {
        $options[$i * 2592000] = $i > 1 ? get_string('nummonths', 'moodle', $i) : get_string('onemonth', 'questupload');
    }
    return $options;
}

/**
 * Delete all the old responses when we have setting the questupload.
 *
 * @throws coding_exception
 * @throws dml_exception
 */
function questupload_delete_old_responses() {
    global $DB;
    $currenttime = time();

    $sql = "SELECT qr.id
              FROM {questupload} q
              JOIN {questupload_response} qr ON qr.questuploadid = q.id AND qr.complete = 'y'
             WHERE q.removeafter <> 0 AND (q.removeafter < :currenttime - qr.submitted)";

    // Get all old response IDs from questuploads.
    $oldresponses = $DB->get_records_sql($sql, ['currenttime' => $currenttime]);

    if (!empty($oldresponses)) {
        try {
            $oldresponsesid = array_keys($oldresponses);
            $count = count($oldresponsesid);
            if (!PHPUNIT_TEST) {
                mtrace("\nBeginning deleting $count old responses requests");
            }
            // Tables to delete responses from.
            $responsetables = [
                    'questupload_response_bool', 'questupload_response_date', 'questupload_resp_multiple',
                    'questupload_response_other', 'questupload_response_rank', 'questupload_resp_single',
                    'questupload_response_text'];

            // Delete related response data.
            foreach ($responsetables as $tablename) {
                $DB->delete_records_list($tablename, 'response_id', $oldresponsesid);
            }

            // Delete from the main response table.
            $DB->delete_records_list('questupload_response', 'id', $oldresponsesid);
            if (!PHPUNIT_TEST) {
                mtrace("\nCompleted deleting $count old responses requests");
            }
        } catch (\dml_exception $ex) {
            debugging('Error: ' . $ex->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
