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

namespace mod_questupload\output;

use mod_questupload\responsetype\response\response;

/**
 * Mobile output class for mod_questupload.
 *
 * @package    mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the initial page when viewing the activity for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_view_activity($args) {
        global $OUTPUT, $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

        $args = (object) $args;

        $versionname = $args->appversioncode >= 44000 ? 'latest' : 'ionic5';
        $cmid = $args->cmid;
        $rid = isset($args->rid) ? $args->rid : 0;
        $action = isset($args->action) ? $args->action : 'index';
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 1;
        $userid = isset($args->userid) ? $args->userid : $USER->id;
        $submit = isset($args->submit) ? $args->submit : false;
        $completed = isset($args->completed) ? $args->completed : false;

        list($cm, $course, $questupload) = questupload_get_standard_page_items($cmid);
        $questupload = new \questupload($course, $cm, 0, $questupload);

        $data = [];
        $data['cmid'] = $cmid;
        $data['userid'] = $userid;
        $data['intro'] = $questupload->intro;
        $data['autonumquestions'] = $questupload->autonum;
        $data['id'] = $questupload->id;
        $data['rid'] = $rid;
        $data['surveyid'] = $questupload->survey->id;
        $data['pagenum'] = $pagenum;
        $data['prevpage'] = 0;
        $data['nextpage'] = 0;

        // Capabilities check.
        $context = \context_module::instance($cmid);
        self::require_capability($cm, $context, 'mod/questupload:view');

        // Any notifications will be displayed on top of main page, and prevent questupload from being completed. This also checks
        // appropriate capabilities.
        $data['notifications'] = $questupload->user_access_messages($userid);
        $responses = [];
        $result = '';

        $data['emptypage'] = 1;
        $template = "mod_questupload/local/mobile/$versionname/main_index_page";

        switch ($action) {
            case 'index':
                self::add_index_data($questupload, $data, $userid);
                $template = "mod_questupload/local/mobile/$versionname/main_index_page";
                break;

            case 'submit':
            case 'nextpage':
            case 'previouspage':
                if (!$data['notifications']) {
                    $result = $questupload->save_mobile_data($userid, $pagenum, $completed, $rid, $submit, $action, (array)$args);
                }

            case 'respond':
            case 'resume':
                // Completing a questupload.
                if (!$data['notifications']) {
                    if ($questupload->user_has_saved_response($userid)) {
                        if (empty($rid)) {
                            $rid = $questupload->get_latest_responseid($userid);
                        }
                        $questupload->add_response($rid);
                        $data['rid'] = $rid;
                    }
                    $response = (isset($questupload->responses) && !empty($questupload->responses)) ?
                        end($questupload->responses) : \mod_questupload\responsetype\response\response::create_from_data([]);
                    $response->sec = $pagenum;
                    if (isset($result['warnings'])) {
                        if ($action == 'submit') {
                            $response = $result['response'];
                        }
                        $data['notifications'] = $result['warnings'];
                    } else if ($action == 'nextpage') {
                        $pageresult = $result['nextpagenum'];
                        if ($pageresult === false) {
                            $pagenum = count($questupload->questionsbysec);
                        } else if (is_string($pageresult)) {
                            $data['notifications'] .= !empty($data['notifications']) ? "\n<br />$pageresult" : $pageresult;
                        } else {
                            $pagenum = $pageresult;
                        }
                    } else if ($action == 'previouspage') {
                        $prevpage = $result['nextpagenum'];
                        if ($prevpage === false) {
                            $pagenum = 1;
                        } else {
                            $pagenum = $prevpage;
                        }
                    } else if ($action == 'submit') {
                        self::add_index_data($questupload, $data, $userid);
                        $data['action'] = 'index';
                        $template = "mod_questupload/local/mobile/$versionname/main_index_page";
                        break;
                    }
                    $pagequestiondata = self::add_pagequestion_data($questupload, $pagenum, $response);
                    $data['pagequestions'] = $pagequestiondata['pagequestions'];
                    $responses = $pagequestiondata['responses'];
                    $numpages = count($questupload->questionsbysec);
                    // Set some variables we are going to be using.
                    if (!empty($questupload->questionsbysec) && ($numpages > 1)) {
                        if ($pagenum > 1) {
                            $data['prevpage'] = true;
                        }
                        if ($pagenum < $numpages) {
                            $data['nextpage'] = true;
                        }
                    }
                    $data['pagenum'] = $pagenum;
                    $data['completed'] = 0;
                    $data['emptypage'] = 0;
                    $template = "mod_questupload/local/mobile/$versionname/view_activity_page";
                }
                break;

            case 'review':
                // If reviewing a submission.
                if ($questupload->capabilities->readownresponses && isset($args->submissionid) && !empty($args->submissionid)) {
                    $questupload->add_response($args->submissionid);
                    $response = $questupload->responses[$args->submissionid];
                    $qnum = 1;
                    $pagequestions = [];
                    foreach ($questupload->questions as $question) {
                        if ($question->supports_mobile()) {
                            $pagequestions[] = $question->mobile_question_display($qnum, $questupload->autonum);
                            $responses = array_merge($responses, $question->get_mobile_response_data($response));
                            if ($question->is_numbered()) {
                                $qnum++;
                            }
                        }
                    }
                    $data['prevpage'] = 0;
                    $data['nextpage'] = 0;
                    $data['pagequestions'] = $pagequestions;
                    $data['completed'] = 1;
                    $data['emptypage'] = 0;
                    $template = "mod_questupload/local/mobile/$versionname/view_activity_page";
                }
                break;
        }

        $data['hasmorepages'] = $data['prevpage'] || $data['nextpage'];

        $return = [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template($template, $data)
                ],
            ],
            'javascript' => file_get_contents($CFG->dirroot . '/mod/questupload/appjs/uncheckother.js'),
            'otherdata' => $responses,
            'files' => null
        ];
        return $return;
    }

    /**
     * Confirms the user is logged in and has the specified capability.
     *
     * @param \stdClass $cm
     * @param \context $context
     * @param string $cap
     */
    protected static function require_capability(\stdClass $cm, \context $context, string $cap) {
        require_login($cm->course, false, $cm, true, true);
        require_capability($cap, $context);
    }

    /**
     * Add the submissions.
     * @param \questupload $questupload
     * @param array $data
     * @param int $userid
     */
    protected static function add_index_data($questupload, &$data, $userid) {
        // List any existing submissions, if user is allowed to review them.
        if ($questupload->capabilities->readownresponses) {
            $questupload->add_user_responses();
            $submissions = [];
            foreach ($questupload->responses as $response) {
                $submissions[] = ['submissiondate' => userdate($response->submitted), 'submissionid' => $response->id];
            }
            if (!empty($submissions)) {
                $data['submissions'] = $submissions;
            } else {
                $data['emptypage'] = 1;
            }
            if ($questupload->user_has_saved_response($userid)) {
                $data['resume'] = 1;
            }
            $data['emptypage'] = 0;
        }
    }

    /**
     * Ass the questions for the page.
     * @param \questupload $questupload
     * @param int $pagenum
     * @param response $response
     * @return array
     */
    protected static function add_pagequestion_data($questupload, $pagenum, $response=null) {
        $qnum = 1;
        $pagequestions = [];
        $responses = [];

        // Find out what question number we are on $i New fix for question numbering.
        $i = 0;
        if ($pagenum > 1) {
            for ($j = 2; $j <= $pagenum; $j++) {
                foreach ($questupload->questionsbysec[$j - 1] as $questionid) {
                    if ($questupload->questions[$questionid]->type_id < QUESPAGEBREAK) {
                        $i++;
                    }
                }
            }
        }
        $qnum = $i + 1;

        foreach ($questupload->questionsbysec[$pagenum] as $questionid) {
            $question = $questupload->questions[$questionid];
            if ($question->supports_mobile()) {
                $pagequestions[] = $question->mobile_question_display($qnum, $questupload->autonum);
                $mobileotherdata = $question->mobile_otherdata();
                if (!empty($mobileotherdata)) {
                    $responses = array_merge($responses, $mobileotherdata);
                }
                if (($response !== null) && isset($response->answers[$questionid])) {
                    $responses = array_merge($responses, $question->get_mobile_response_data($response));
                }
                if ($question->is_numbered()) {
                    $qnum++;
                }
            }
        }

        return ['pagequestions' => $pagequestions, 'responses' => $responses];
    }
}
