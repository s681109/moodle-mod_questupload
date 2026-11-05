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
 * The main report page for a questupload.
 *
 * @package mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');

$instance = optional_param('instance', false, PARAM_INT);   // Questionnaire Upload ID.
$action = optional_param('action', 'vall', PARAM_ALPHA);
$sid = optional_param('sid', null, PARAM_INT);              // Survey id.
$rid = optional_param('rid', false, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$byresponse = optional_param('byresponse', false, PARAM_INT);
$individualresponse = optional_param('individualresponse', false, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$user = optional_param('user', '', PARAM_INT);
$outputtarget = optional_param('target', 'html', PARAM_ALPHA); // Default 'html'. Could be 'pdf'.
$userview = optional_param('responsestats', '0', PARAM_ALPHANUM);

$userid = $USER->id;
switch ($action) {
    case 'vallasort':
        $sort = 'ascending';
        break;
    case 'vallarsort':
        $sort = 'descending';
        break;
    default:
        $sort = 'default';
}

if ($instance === false) {
    if (!empty($SESSION->instance)) {
        $instance = $SESSION->instance;
    } else {
        throw new \moodle_exception('requiredparameter', 'mod_questupload');
    }
}
$SESSION->instance = $instance;
$usergraph = get_config('questupload', 'usergraph');

if (! $questupload = $DB->get_record("questupload", array("id" => $instance))) {
    throw new \moodle_exception('incorrectquestupload', 'mod_questupload');
}
if (! $course = $DB->get_record("course", array("id" => $questupload->course))) {
    throw new \moodle_exception('coursemisconf', 'mod_questupload');
}
if (! $cm = get_coursemodule_from_instance("questupload", $questupload->id, $course->id)) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questupload');
}

require_course_login($course, true, $cm);

$questupload = new questupload($course, $cm, 0, $questupload);

// Add renderer and page objects to the questupload object for display use.
$questupload->add_renderer($PAGE->get_renderer('mod_questupload'));
if ($outputtarget == 'pdf') {
    if ($action == 'vresp') {
        $questupload->add_page(new \mod_questupload\output\responsepagepdf());
    } else {
        $questupload->add_page(new \mod_questupload\output\reportpagepdf());
    }
} else { // Default to HTML.
    $questupload->add_page(new \mod_questupload\output\reportpage());
}

// If you can't view the questupload, or can't view a specified response, error out.
$context = context_module::instance($cm->id);
if (!$questupload->can_view_all_responses(null, true) && !$individualresponse) {
    // Should never happen, unless called directly by a snoop...
    throw new \moodle_exception('nopermissions', 'mod_questupload');
}

$questupload->canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
$sid = $questupload->survey->id;

$url = new moodle_url($CFG->wwwroot.'/mod/questupload/report.php');
if ($instance) {
    $url->param('instance', $instance);
}

$url->param('action', $action);

if ($type) {
    $url->param('type', $type);
}
if ($byresponse || $individualresponse) {
    $url->param('byresponse', 1);
}
if ($user) {
    $url->param('user', $user);
}
if ($action == 'dresp') {
    $url->param('action', 'dresp');
    $url->param('byresponse', 1);
    $url->param('rid', $rid);
    $url->param('individualresponse', 1);
}
if ($currentgroupid !== null) {
    $url->param('group', $currentgroupid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
if ($outputtarget == 'print') {
    $PAGE->set_pagelayout('popup');
    $PAGE->requires->js_init_call('M.mod_questupload.init_printing');
}

// Tab setup.
if (!isset($SESSION->questupload)) {
    $SESSION->questupload = new stdClass();
}
$SESSION->questupload->current_tab = 'allreport';

// Get all responses for further use in viewbyresp and deleteall etc.
// All participants.
$respsallparticipants = $questupload->get_responses();
$SESSION->questupload->numrespsallparticipants = count ($respsallparticipants);
$SESSION->questupload->numselectedresps = $SESSION->questupload->numrespsallparticipants;

// Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
$groupmode = groups_get_activity_groupmode($cm, $course);
$questuploadgroups = '';
$groupscount = 0;
$SESSION->questupload->respscount = 0;
$SESSION->questupload_surveyid = $sid;

if ($groupmode > 0) {
    if ($groupmode == 1) {
        $questuploadgroups = groups_get_all_groups($course->id, $userid);
    }
    if ($groupmode == 2 || $questupload->canviewallgroups) {
        $questuploadgroups = groups_get_all_groups($course->id);
    }

    if (!empty($questuploadgroups)) {
        $groupscount = count($questuploadgroups);
        foreach ($questuploadgroups as $key) {
            $firstgroupid = $key->id;
            break;
        }
        if ($groupscount === 0 && $groupmode == 1) {
            $currentgroupid = 0;
        }
        if ($groupmode == 1 && !$questupload->canviewallgroups && $currentgroupid == 0) {
            $currentgroupid = $firstgroupid;
        }
    } else {
        // Groupmode = separate groups but user is not member of any group
        // and does not have moodle/site:accessallgroups capability -> refuse view responses.
        if (!$questupload->canviewallgroups) {
            $currentgroupid = 0;
        }
    }

    if ($currentgroupid > 0) {
        $groupname = get_string('group').' <strong>'.groups_get_group_name($currentgroupid).'</strong>';
    } else {
        $groupname = '<strong>'.get_string('allparticipants').'</strong>';
    }
}
if ($usergraph) {
    $charttype = $questupload->survey->chart_type;
    if ($charttype) {
        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.common.core.js');

        switch ($charttype) {
            case 'bipolar':
                $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.bipolar.js');
                break;
            case 'hbar':
                $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.hbar.js');
                break;
            case 'radar':
                $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.radar.js');
                break;
            case 'rose':
                $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.rose.js');
                break;
            case 'vprogress':
                $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.vprogress.js');
                break;
        }
    }
}

$responsestatus = [
        'y' => get_string('fullsubmissions', 'questupload'),
        '0' => get_string('allresponses', 'questupload'),
        'n' => get_string('responsesnotsubmitted', 'questupload'),
];
// Set default userview to all responses.
$userview = array_key_exists($userview, $responsestatus) ? $userview : '0';

switch ($action) {

    case 'dresp':  // Delete individual response? Ask for confirmation.
        require_capability('mod/questupload:deleteresponses', $context);

        if (empty($questupload->survey)) {
            $id = $questupload->survey;
            notify ("questupload->survey = /$id/");
            throw new \moodle_exception('surveynotexists', 'mod_questupload');
        } else if ($questupload->survey->courseid != $course->id) {
            throw new \moodle_exception('surveyowner', 'mod_questupload');
        } else if (!$rid || !is_numeric($rid)) {
            throw new \moodle_exception('invalidresponse', 'mod_questupload');
        } else if (!($resp = $DB->get_record('questupload_response', array('id' => $rid)))) {
            throw new \moodle_exception('invalidresponserecord', 'mod_questupload');
        }

        $ruser = false;
        if (!empty($resp->userid)) {
            if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'questupload').' -';
            }
        } else {
            $ruser = $resp->userid;
        }

        // Print the page header.
        $PAGE->set_title(get_string('deletingresp', 'questupload'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $questupload->renderer->header();

        // Print the tabs.
        $SESSION->questupload->current_tab = 'deleteresp';
        include('tabs.php');

        $timesubmitted = '<br />'.get_string('submitted', 'questupload').'&nbsp;'.userdate($resp->submitted);
        if ($questupload->respondenttype == 'anonymous') {
            $ruser = '- '.get_string('anonymous', 'questupload').' -';
            $timesubmitted = '';
        }

        // Print the confirmation.
        $msg = '<div class="warning centerpara">';
        $msg .= get_string('confirmdelresp', 'questupload', $ruser.$timesubmitted);
        $msg .= '</div>';
        $urlyes = new moodle_url('report.php', array('action' => 'dvresp',
            'rid' => $rid, 'individualresponse' => 1, 'instance' => $instance, 'group' => $currentgroupid));
        $urlno = new moodle_url('report.php', array('action' => 'vresp', 'instance' => $instance,
            'rid' => $rid, 'individualresponse' => 1, 'group' => $currentgroupid));
        $buttonyes = new single_button($urlyes, get_string('delete'), 'post');
        $buttonno = new single_button($urlno, get_string('cancel'), 'get');
        $questupload->page->add_to_page('notifications', $questupload->renderer->confirm($msg, $buttonyes, $buttonno));
        echo $questupload->renderer->render($questupload->page);
        // Finish the page.
        echo $questupload->renderer->footer($course);
        break;

    case 'delallresp': // Delete all responses? Ask for confirmation.
        require_capability('mod/questupload:deleteresponses', $context);

        if (!empty($respsallparticipants)) {

            // Print the page header.
            $PAGE->set_title(get_string('deletingresp', 'questupload'));
            $PAGE->set_heading(format_string($course->fullname));
            echo $questupload->renderer->header();

            // Print the tabs.
            $SESSION->questupload->current_tab = 'deleteall';
            include('tabs.php');

            // Print the confirmation.
            $msg = '<div class="warning centerpara">';
            if ($groupmode == 0) {   // No groups or visible groups.
                $msg .= get_string('confirmdelallresp', 'questupload');
            } else {                 // Separate groups.
                $msg .= get_string('confirmdelgroupresp', 'questupload', $groupname);
            }
            $msg .= '</div>';

            $urlyes = new moodle_url('report.php', array('action' => 'dvallresp', 'sid' => $sid,
                'instance' => $instance, 'group' => $currentgroupid));
            $urlno = new moodle_url('report.php', array('instance' => $instance, 'group' => $currentgroupid));
            $buttonyes = new single_button($urlyes, get_string('delete'), 'post');
            $buttonno = new single_button($urlno, get_string('cancel'), 'get');

            $questupload->page->add_to_page('notifications', $questupload->renderer->confirm($msg, $buttonyes, $buttonno));
            echo $questupload->renderer->render($questupload->page);
            // Finish the page.
            echo $questupload->renderer->footer($course);
        }
        break;

    case 'dvresp': // Delete single response. Do it!
        require_capability('mod/questupload:deleteresponses', $context);
        require_sesskey();

        if (empty($questupload->survey)) {
            throw new \moodle_exception('surveynotexists', 'mod_questupload');
        } else if ($questupload->survey->courseid != $course->id) {
            throw new \moodle_exception('surveyowner', 'mod_questupload');
        } else if (!$rid || !is_numeric($rid)) {
            throw new \moodle_exception('invalidresponse', 'mod_questupload');
        } else if (!($response = $DB->get_record('questupload_response', array('id' => $rid)))) {
            throw new \moodle_exception('invalidresponserecord', 'mod_questupload');
        }

        if (questupload_delete_response($response, $questupload)) {
            if (!$DB->count_records('questupload_response', array('questuploadid' => $questupload->id, 'complete' => 'y'))) {
                $redirection = $CFG->wwwroot.'/mod/questupload/view.php?id='.$cm->id;
            } else {
                $redirection = $CFG->wwwroot.'/mod/questupload/report.php?action=vresp&amp;instance='.
                    $instance.'&amp;byresponse=1';
            }

            // Log this questupload delete single response action.
            $params = array('objectid' => $questupload->survey->id,
                'context' => $questupload->context,
                'courseid' => $questupload->course->id,
                'relateduserid' => $response->userid);
            $event = \mod_questupload\event\response_deleted::create($params);
            $event->trigger();

            redirect($redirection);
        } else {
            if ($questupload->respondenttype == 'anonymous') {
                $ruser = '- '.get_string('anonymous', 'questupload').' -';
            } else if (!empty($response->userid)) {
                if ($user = $DB->get_record('user', ['id' => $response->userid])) {
                    $ruser = fullname($user);
                } else {
                    $ruser = '- '.get_string('unknown', 'questupload').' -';
                }
            } else {
                $ruser = $response->userid;
            }
            error (get_string('couldnotdelresp', 'questupload').$rid.get_string('by', 'questupload').$ruser.'?',
                $CFG->wwwroot.'/mod/questupload/report.php?action=vresp&amp;sid='.$sid.'&amp;&amp;instance='.
                $instance.'byresponse=1');
        }
        break;

    case 'dvallresp': // Delete all responses in questupload (or group). Do it!
        require_capability('mod/questupload:deleteresponses', $context);
        require_sesskey();

        if (empty($questupload->survey)) {
            throw new \moodle_exception('surveynotexists', 'mod_questupload');
        } else if ($questupload->survey->courseid != $course->id) {
            throw new \moodle_exception('surveyowner', 'mod_questupload');
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (!($resps = $questupload->get_responses(false, $currentgroupid))) {
                        $resps = [];
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            } else {
                if ($rid === false) {
                    $resp = current($resps);
                    $rid = $resp->id;
                } else {
                    $resp = $DB->get_record('questupload_response', array('id' => $rid));
                }
                if (!empty($resp->userid)) {
                    if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                        $ruser = fullname($user);
                    } else {
                        $ruser = '- '.get_string('unknown', 'questupload').' -';
                    }
                } else {
                    $ruser = $resp->userid;
                }
            }
        } else {
            $resps = $respsallparticipants;
        }

        if (!empty($resps)) {
            foreach ($resps as $response) {
                questupload_delete_response($response, $questupload);
            }
            if (!$questupload->count_submissions()) {
                $redirection = $CFG->wwwroot.'/mod/questupload/view.php?id='.$cm->id;
            } else {
                $redirection = $CFG->wwwroot.'/mod/questupload/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance;
            }

            // Log this questupload delete all responses action.
            $context = context_module::instance($questupload->cm->id);
            $anonymous = $questupload->respondenttype == 'anonymous';

            $event = \mod_questupload\event\all_responses_deleted::create(array(
                'objectid' => $questupload->id,
                'anonymous' => $anonymous,
                'context' => $context
            ));
            $event->trigger();

            redirect($redirection);
        } else {
            error (get_string('couldnotdelresp', 'questupload'),
                $CFG->wwwroot.'/mod/questupload/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance);
        }
        break;

    case 'zipfiles':
        require_capability('mod/questupload:downloadresponses', $context);
        require_sesskey();

        $downloader = new \mod_questupload\downloader($questupload, $currentgroupid);
        if ($downloader->load_filelist()) {
            $downloader->download_zip();
        }

        redirect(new moodle_url('/mod/questupload/report.php', [
            'instance' => $instance,
            'action' => 'dwnpg',
            'group' => $currentgroupid,
        ]), get_string('fileuploadzipnofiles', 'questupload'), null, \core\output\notification::NOTIFY_INFO);
        break;
    case 'dwnpg': // Download page options.
        require_capability('mod/questupload:downloadresponses', $context);

        $PAGE->set_title(get_string('questuploadreport', 'questupload'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $questupload->renderer->header();

        // Print the tabs.
        // Tab setup.
        if (empty($user)) {
            $SESSION->questupload->current_tab = 'downloadcsv';
        } else {
            $SESSION->questupload->current_tab = 'mydownloadcsv';
        }

        include('tabs.php');

        $groupname = '';
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $groupname = get_string('allparticipants');
                    break;
                default:     // Members of a specific group.
                    $groupname = get_string('membersofselectedgroup', 'group').' '.get_string('group').' '.
                        $questuploadgroups[$currentgroupid]->name;
            }
        }
        $output = '';
        $output .= "<br /><br />\n";
        $output .= html_writer::tag('h2', (get_string('downloadtextformat', 'questupload'))
                . ':&nbsp;' . get_string('responses', 'questupload') . '&nbsp;' .
                $groupname . $questupload->renderer->help_icon('downloadtextformat', 'questupload'));
        $output .= $questupload->renderer->heading(get_string('textdownloadoptions', 'questupload'), 3);
        $output .= $questupload->renderer->box_start();
        $downloadparams = [
            'instance' => $instance,
            'user' => $user,
            'sid' => $sid,
            'action' => 'dfs',
            'group' => $currentgroupid,
            'zipwithcsv' => 1
        ];
        $extrafields = $questupload->renderer->render_from_template('mod_questupload/extrafields', []);
        $output .= $questupload->renderer->download_dataformat_selector(get_string('downloadtypes', 'questupload'),
            'report.php', 'downloadformat', $downloadparams, $extrafields);
        $zipurl = new moodle_url('/mod/questupload/report.php', [
            'instance' => $instance,
            'sid' => $sid,
            'action' => 'zipfiles',
            'group' => $currentgroupid,
            'sesskey' => sesskey(),
        ]);
        $output .= html_writer::div(
            html_writer::link($zipurl, get_string('fileuploadzipdownload', 'questupload'), ['class' => 'btn btn-outline-secondary']),
            'mt-3'
        );
        $output .= html_writer::div(get_string('fileuploadzipdownload_help', 'questupload'), 'form-text text-muted mt-2');
        $output .= $questupload->renderer->box_end();

        $questupload->page->add_to_page('respondentinfo', $output);
        echo $questupload->renderer->render($questupload->page);

        echo $questupload->renderer->footer('none');

        // Log saved as text action.
        $params = array('objectid' => $questupload->id,
            'context' => $questupload->context,
            'courseid' => $course->id,
            'other' => array('action' => $action, 'instance' => $instance, 'currentgroupid' => $currentgroupid)
        );
        $event = \mod_questupload\event\all_responses_saved_as_text::create($params);
        $event->trigger();

        exit();
        break;

    case 'dfs':
        require_capability('mod/questupload:downloadresponses', $context);
        require_sesskey();
        // Use the questupload name as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($questupload->name, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", trim($name));

        $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
        $choicetext = optional_param('choicetext', '0', PARAM_INT);
        $showincompletes = optional_param('complete', '0', PARAM_INT);
        $rankaverages = optional_param('rankaverages', '0', PARAM_INT);
        $dataformat = optional_param('downloadformat', '', PARAM_ALPHA);
        $emailroles = optional_param('emailroles', 0, PARAM_INT);
        $emailextra = optional_param('emailextra', '', PARAM_RAW);
        $zipwithcsv = optional_param('zipwithcsv', '', PARAM_RAW);
        $includeresponsepdfs = optional_param('includeresponsepdfs', 0, PARAM_INT);

        $output = $questupload->generate_csv($currentgroupid, '', $user, $choicecodes, $choicetext, $showincompletes,
            $rankaverages);

        $columns = $output[0];
        unset($output[0]);

        if (!empty($zipwithcsv)) {
            require_once($CFG->libdir . '/csvlib.class.php');
            $csvrows = array_merge([$columns], $output);
            $csvcontent = \csv_export_writer::print_array($csvrows, 'comma', '"', true);

            $downloader = new \mod_questupload\downloader($questupload, $currentgroupid);
            $downloader->set_zip_short_string('fileuploadzipwithcsvshort');
            if (!empty($includeresponsepdfs)) {
                $downloader->use_response_folder_structure('respondentes', 'anexos');
            } else {
                $downloader->set_attachment_prefix('anexos');
            }
            $downloader->add_string_file('relatorio_respostas.csv', $csvcontent);
            $downloader->load_filelist();
            if (!empty($includeresponsepdfs)) {
                $downloader->add_response_pdf_files($choicecodes, $choicetext, $showincompletes, $rankaverages);
            }
            $downloader->download_zip();
        }
        // Check if email report was selected.
        $emailreport = optional_param('emailreport', '', PARAM_ALPHA);
        if (empty($emailreport)) {
            \core\dataformat::download_data($name, $dataformat, $columns, $output);
        } else {
            // Emailreport button selected.
            if (get_config('questupload', 'allowemailreporting') && (!empty($emailroles) || !empty($emailextra))) {
                require_once('savefileformat.php');
                $users = !empty($emailroles) ? $questupload->get_notifiable_users($USER->id) : [];
                $otheremails = explode(',', $emailextra);
                if (!empty($users) || !empty($otheremails)) {
                    $thisurl = new moodle_url('report.php',
                        ['instance' => $instance, 'action' => 'dwnpg', 'group' => $currentgroupid]);
                    save_as_dataformat($name, $dataformat, $columns, $output, $users, $otheremails, $thisurl);
                }
            } else {
                redirect(new moodle_url('report.php', ['instance' => $instance, 'action' => 'dwnpg', 'group' => $currentgroupid]),
                    get_string('emailsnotspecified', 'questupload'));
            }
        }
        exit();
        break;

    case 'vall':         // View all responses.
    case 'vallasort':    // View all responses sorted in ascending order.
    case 'vallarsort':   // View all responses sorted in descending order.
        $PAGE->set_title(get_string('questuploadreport', 'questupload'));
        $PAGE->set_heading(format_string($course->fullname));
        if (!$questupload->capabilities->readallresponses && !$questupload->capabilities->readallresponseanytime) {
            echo $questupload->renderer->header();
            // Should never happen, unless called directly by a snoop.
            throw new \moodle_exception('nopermissions', 'mod_questupload');
            // Finish the page.
            echo $questupload->renderer->footer($course);
            break;
        }

        // Print the tabs.
        switch ($action) {
            case 'vallasort':
                $SESSION->questupload->current_tab = 'vallasort';
                break;
            case 'vallarsort':
                $SESSION->questupload->current_tab = 'vallarsort';
                break;
            default:
                $SESSION->questupload->current_tab = 'valldefault';
        }
        if ($outputtarget != 'print') {
            include('tabs.php');
        }

        $respinfo = '';
        $resps = array();
        // Enable choose_group if there are questupload groups and groupmode is not set to "no groups"
        // and if there are more goups than 1 (or if user can view all groups).
        if (is_array($questuploadgroups) && $groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            // Count number of responses in each group.
            foreach ($questuploadgroups as $group) {
                $respscount = $questupload->count_submissions(false, $group->id);
                $thisgroupname = groups_get_group_name($group->id);
                $escapedgroupname = preg_quote($thisgroupname, '/');
                if (!empty ($respscount)) {
                    // Add number of responses to name of group in the groups select list.
                    $groupselect = preg_replace('/\<option value="'.$group->id.'">'.$escapedgroupname.'<\/option>/',
                        '<option value="'.$group->id.'">'.$thisgroupname.' ('.$respscount.')</option>', $groupselect);
                } else {
                    // Remove groups with no responses from the groups select list.
                    $groupselect = preg_replace('/\<option value="'.$group->id.'">'.$escapedgroupname.
                        '<\/option>/', '', $groupselect);
                }
            }
            $respinfo .= isset($groupselect) ? ($groupselect . ' ') : '';
            $currentgroupid = groups_get_activity_group($cm);
        }
        if ($currentgroupid > 0) {
            $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
        } else {
            $groupname = '<strong>'.$responsestatus[$userview].'</strong>';
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (!($resps = $questupload->get_responses(false, $currentgroupid))) {
                        $resps = '';
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            }
        } else {
            $resps = $respsallparticipants;
        }
        if (!empty($resps)) {
            // NOTE: response_analysis uses $resps to get the id's of the responses only.
            // Need to figure out what this function does.
            $feedbackmessages = $questupload->response_analysis(0, $resps, false, false, true, $currentgroupid);

            if ($feedbackmessages) {
                $msgout = '';
                foreach ($feedbackmessages as $msg) {
                    $msgout .= $msg;
                }
                $questupload->page->add_to_page('feedbackmessages', $msgout);
            }
        }

        $params = array('objectid' => $questupload->id,
            'context' => $context,
            'courseid' => $course->id,
            'other' => array('action' => $action, 'instance' => $instance, 'groupid' => $currentgroupid)
        );

        if ($outputtarget == 'pdf') {
            $pdf = questupload_report_start_pdf();
            if ($currentgroupid > 0) {
                $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            } else {
                $groupname = '<strong>' . $responsestatus[$userview] . '</strong>';
            }
            $respinfo = get_string('view') . ' ' . $groupname;
            $strsort = get_string('order_' . $sort, 'questupload');
            $respinfo .= $strsort;
            $questupload->page->add_to_page('respondentinfo', $respinfo);
            $questupload->survey_results('', false, true, $currentgroupid, $sort);
            $html = $questupload->renderer->render($questupload->page);

            // Supress any warnings. There is at least one error in the TCPF library at line 16749 where 'text-align' is
            // not an array.
            $errorreporting = error_reporting(0);
            $pdf->writeHTML($html);
            @$pdf->Output(clean_param($questupload->name, PARAM_FILE) . '.pdf', 'D');
            error_reporting($errorreporting);

        } else { // Default to HTML.
            $event = \mod_questupload\event\all_responses_viewed::create($params);
            $event->trigger();

            if ($outputtarget != 'print') {
                $linkname = get_string('downloadpdf', 'mod_questupload');
                $link = new moodle_url('/mod/questupload/report.php',
                        ['action' => 'vall', 'instance' => $instance, 'group' => $currentgroupid, 'target' => 'pdf',
                                'responsestats' => $userview]);
                $downpdficon = new pix_icon('f/pdf', $linkname);
                $respinfo .= $questupload->renderer->action_link($link, null, null, null, $downpdficon);

                $linkname = get_string('print', 'mod_questupload');
                $link = new \moodle_url('/mod/questupload/report.php',
                        ['action' => 'vall', 'instance' => $instance, 'group' => $currentgroupid, 'target' => 'print',
                                'responsestats' => $userview]);
                $htmlicon = new pix_icon('t/print', $linkname);
                $options = ['menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
                    'height' => 600, 'width' => 800, 'title' => $linkname];
                $name = 'popup';
                $action = new popup_action('click', $link, $name, $options);
                $class = '';
                $respinfo .= $questupload->renderer->action_link($link, null, $action,
                        ['class' => $class, 'title' => $linkname], $htmlicon) . '&nbsp;';

                $respinfo .= $questupload->renderer->viewresponse_print_menu($url->out(), $responsestatus, $userview);
                $strsort = get_string('order_' . $sort, 'questupload');
                $respinfo .= $strsort;
                $respinfo .= $questupload->renderer->help_icon('orderresponses', 'questupload');
                $questupload->page->add_to_page('respondentinfo', $respinfo);
            }

            $ret = $questupload->survey_results('', false, false, $currentgroupid, $sort);

            echo $questupload->renderer->header();
            echo $questupload->renderer->render($questupload->page);
            echo $questupload->renderer->footer($course);
        }
        break;

    case 'vresp': // View by response.
    default:
        if (empty($questupload->survey)) {
            throw new \moodle_exception('surveynotexists', 'mod_questupload');
        } else if ($questupload->survey->courseid != $course->id) {
            throw new \moodle_exception('surveyowner', 'mod_questupload');
        }
        $ruser = false;
        $noresponses = false;
        if ($usergraph) {
            $charttype = $questupload->survey->chart_type;
            if ($charttype) {
                $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.common.core.js');

                switch ($charttype) {
                    case 'bipolar':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.bipolar.js');
                        break;
                    case 'hbar':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.hbar.js');
                        break;
                    case 'radar':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.radar.js');
                        break;
                    case 'rose':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.rose.js');
                        break;
                    case 'vprogress':
                        $PAGE->requires->js('/mod/questupload/javascript/RGraph/RGraph.vprogress.js');
                        break;
                }
            }
        }

        // Add group filter dropdown.
        if ($groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            $questupload->page->add_to_page('respondentinfo', $groupselect);
            $currentgroupid = groups_get_activity_group($cm);
        }

        if ($byresponse || $rid) {
            // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
            if ($groupmode > 0) {
                switch ($currentgroupid) {
                    case 0:     // All participants.
                        $resps = $respsallparticipants;
                        break;
                    default:     // Members of a specific group.
                        $resps = $questupload->get_responses(false, $currentgroupid);
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else {
                    if ($rid === false) {
                        $resp = current($resps);
                        $rid = $resp->id;
                    } else {
                        $resp = $DB->get_record('questupload_response', ['id' => $rid]);
                    }
                    if (!empty($resp->userid)) {
                        if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                            $ruser = fullname($user);
                        } else {
                            $ruser = '- '.get_string('unknown', 'questupload').' -';
                        }
                    } else {
                        $ruser = $resp->userid;
                    }
                }
            } else {
                $resps = $respsallparticipants;
            }
        }
        $rids = array_keys($resps);
        if (!$rid && !$noresponses) {
            $rid = $rids[0];
        }

        if ($outputtarget == 'pdf') {
            $pdf = questupload_report_start_pdf();
            if ($currentgroupid > 0) {
                $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            } else {
                $groupname = '<strong>' . $responsestatus[$userview] . '</strong>';
            }
            if (!$byresponse) { // Show respondents individual responses.
                $questupload->view_response($rid, '', $resps, true, true, false, $currentgroupid, $outputtarget);
            }
            $html = $questupload->renderer->render($questupload->page);
            // Supress any warnings. There is at least one error in the TCPF library at line 16749 where 'text-align' is
            // not an array.
            $errorreporting = error_reporting(0);
            $pdf->writeHTML($html);
            @$pdf->Output(clean_param($questupload->name, PARAM_FILE), 'D');
            error_reporting($errorreporting);

        } else { // Default to HTML.
            if ($noresponses) {
                $questupload->page->add_to_page('respondentinfo',
                    get_string('group') . ' <strong>' . groups_get_group_name($currentgroupid) . '</strong>: ' .
                    get_string('noresponses', 'questupload'));
            }

            // Print the page header.
            $PAGE->set_title(get_string('questuploadreport', 'questupload'));
            $PAGE->set_heading(format_string($course->fullname));

            // Print the tabs.
            if ($byresponse) {
                $SESSION->questupload->current_tab = 'vrespsummary';
            }
            if ($individualresponse) {
                $SESSION->questupload->current_tab = 'individualresp';
            }
            if ($outputtarget == 'html') {
                include('tabs.php');
            }

            // Print the main part of the page.
            // TODO provide option to select how many columns and/or responses per page.

            $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
            if ($currentgroupid == 0 ) {
                $groupname = $responsestatus[$userview];
            }
            if ($byresponse) {
                $respinfo = '';
                $respinfo .= $questupload->renderer->box_start();
                $respinfo .= $questupload->renderer->help_icon('viewindividualresponse', 'questupload').'&nbsp;';
                $respinfo .= get_string('viewindividualresponse', 'questupload').' <strong> : '.$groupname.'</strong>';
                $respinfo .= $questupload->renderer->box_end();
                $questupload->page->add_to_page('respondentinfo', $respinfo);
            }
            if ($outputtarget == 'html') {
                $questupload->survey_results_navbar_alpha($rid, $currentgroupid, $cm, $byresponse);
            }
            if (!$byresponse) { // Show respondents individual responses.
                $questupload->view_response($rid, '', $resps, true, true, false, $currentgroupid, $outputtarget);
            }
            echo $questupload->renderer->header();
            echo $questupload->renderer->render($questupload->page);
            echo $questupload->renderer->footer($course);
        }
        break;
}

/**
 * Return a pdf object.
 * @return pdf
 */
function questupload_report_start_pdf() {
    global $CFG;

    require_once($CFG->libdir . '/pdflib.php');
    $pdf = new pdf();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Moodle Questionnaire Upload');
    $pdf->SetTitle('All responses');
    $pdf->setPrintHeader(false);
    // Set default monospaced font.
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins.
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks.
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

    // Set image scale factor.
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    // Set background color for headings.
    $pdf->SetFillColor(238, 238, 238);
    $pdf->AddPage('L');
    return $pdf;
}
