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
 * This script lists all the instances of questupload in a particular course
 *
 * @package    mod_questupload
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questupload/locallib.php');

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/questupload/index.php', array('id' => $id));
if (! $course = $DB->get_record('course', array('id' => $id))) {
    throw new \moodle_exception('Filter has not been set.', 'mod_questupload');
}
$coursecontext = context_course::instance($id);
require_login($course->id);
$PAGE->set_pagelayout('incourse');

$event = \mod_questupload\event\course_module_instance_list_viewed::create(array(
                'context' => context_course::instance($course->id)));
$event->trigger();

// Print the header.
$strquestuploads = get_string("modulenameplural", "questupload");
$PAGE->navbar->add($strquestuploads);
$PAGE->set_title("$course->shortname: $strquestuploads");
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();

// Get all the appropriate data.
if (!$questuploads = get_all_instances_in_course("questupload", $course)) {
    notice(get_string('thereareno', 'moodle', $strquestuploads), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the closing date header.
$showclosingheader = false;
foreach ($questuploads as $questupload) {
    if ($questupload->closedate != 0) {
        $showclosingheader = true;
    }
    if ($showclosingheader) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

if ($showclosingheader) {
    array_push($headings, get_string('questuploadcloses', 'questupload'));
    array_push($align, 'left');
}

array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
array_unshift($align, 'left');

$showing = '';

// Current user role == admin or teacher.
if (has_capability('mod/questupload:viewsingleresponse', $coursecontext)) {
    array_push($headings, get_string('responses', 'questupload'));
    array_push($align, 'center');
    $showing = 'stats';
    array_push($headings, get_string('realm', 'questupload'));
    array_push($align, 'left');
    // Current user role == student.
} else if (has_capability('mod/questupload:submit', $coursecontext)) {
    array_push($headings, get_string('status'));
    array_push($align, 'left');
    $showing = 'responses';
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
foreach ($questuploads as $questupload) {
    $cmid = $questupload->coursemodule;
    $data = array();
    $realm = $DB->get_field('questupload_survey', 'realm', array('id' => $questupload->sid));
    // Template surveys should NOT be displayed as an activity to students.
    if (!($realm == 'template' && !has_capability('mod/questupload:manage', context_module::instance($cmid)))) {
        // Section number if necessary.
        $strsection = '';
        if ($questupload->section != $currentsection) {
            $strsection = get_section_name($course, $questupload->section);
            $currentsection = $questupload->section;
        }
        $data[] = $strsection;
        // Show normal if the mod is visible.
        $class = '';
        if (!$questupload->visible) {
            $class = ' class="dimmed"';
        }
        $data[] = "<a$class href=\"view.php?id=$cmid\">$questupload->name</a>";

        // Close date.
        if ($questupload->closedate) {
            $data[] = userdate($questupload->closedate);
        } else if ($showclosingheader) {
            $data[] = '';
        }

        if ($showing == 'responses') {
            $status = '';
            if ($responses = questupload_get_user_responses($questupload->id, $USER->id, $complete = false)) {
                foreach ($responses as $response) {
                    if ($response->complete == 'y') {
                        $status .= get_string('submitted', 'questupload').' '.userdate($response->submitted).'<br />';
                    } else {
                        $status .= get_string('attemptstillinprogress', 'questupload').' '.
                            userdate($response->submitted).'<br />';
                    }
                }
            }
            $data[] = $status;
        } else if ($showing == 'stats') {
            $data[] = $DB->count_records('questupload_response', ['questuploadid' => $questupload->id, 'complete' => 'y']);
            if ($survey = $DB->get_record('questupload_survey', ['id' => $questupload->sid])) {
                // For a public questupload, look for the original public questupload that it is based on.
                if ($survey->realm == 'public') {
                    $strpreview = get_string('preview_questupload', 'questupload');
                    if ($survey->courseid != $course->id) {
                        $publicoriginal = '';
                        $originalcourse = $DB->get_record('course', ['id' => $survey->courseid]);
                        $originalcoursecontext = context_course::instance($survey->courseid);
                        $originalquestupload = $DB->get_record('questupload',
                            ['sid' => $survey->id, 'course' => $survey->courseid]);
                        $cm = get_coursemodule_from_instance("questupload", $originalquestupload->id, $survey->courseid);
                        $context = context_course::instance($survey->courseid, MUST_EXIST);
                        $canvieworiginal = has_capability('mod/questupload:preview', $context, $USER->id, true);
                        // If current user can view questuploads in original course,
                        // provide a link to the original public questupload.
                        if ($canvieworiginal) {
                            $publicoriginal = '<br />'.get_string('publicoriginal', 'questupload').'&nbsp;'.
                                '<a href="'.$CFG->wwwroot.'/mod/questupload/preview.php?id='.
                                $cm->id.'" title="'.$strpreview.']">'.$originalquestupload->name.' ['.
                                $originalcourse->fullname.']</a>';
                        } else {
                            // If current user is not enrolled as teacher in original course,
                            // only display the original public questupload's name and course name.
                            $publicoriginal = '<br />'.get_string('publicoriginal', 'questupload').'&nbsp;'.
                                $originalquestupload->name.' ['.$originalcourse->fullname.']';
                        }
                        $data[] = get_string($realm, 'questupload').' '.$publicoriginal;
                    } else {
                        // Original public questupload was created in current course.
                        // Find which courses it is used in.
                        $publiccopy = '';
                        $select = 'course != '.$course->id.' AND sid = '.$questupload->sid;
                        if ($copies = $DB->get_records_select('questupload', $select, null,
                                $sort = 'course ASC', $fields = 'id, course, name')) {
                            foreach ($copies as $copy) {
                                $copycourse = $DB->get_record('course', array('id' => $copy->course));
                                $select = 'course = '.$copycourse->id.' AND sid = '.$questupload->sid;
                                $copyquestupload = $DB->get_record('questupload',
                                    array('id' => $copy->id, 'sid' => $survey->id, 'course' => $copycourse->id));
                                $cm = get_coursemodule_from_instance("questupload", $copyquestupload->id, $copycourse->id);
                                $context = context_course::instance($copycourse->id, MUST_EXIST);
                                $canviewcopy = has_capability('mod/questupload:view', $context, $USER->id, true);
                                if ($canviewcopy) {
                                    $publiccopy .= '<br />'.get_string('publiccopy', 'questupload').'&nbsp;:&nbsp;'.
                                        '<a href = "'.$CFG->wwwroot.'/mod/questupload/preview.php?id='.
                                        $cm->id.'" title = "'.$strpreview.'">'.
                                        $copyquestupload->name.' ['.$copycourse->fullname.']</a>';
                                } else {
                                    // If current user does not have "view" capability in copy course,
                                    // only display the copied public questupload's name and course name.
                                    $publiccopy .= '<br />'.get_string('publiccopy', 'questupload').'&nbsp;:&nbsp;'.
                                        $copyquestupload->name.' ['.$copycourse->fullname.']';
                                }
                            }
                        }
                        $data[] = get_string($realm, 'questupload').' '.$publiccopy;
                    }
                } else {
                    $data[] = get_string($realm, 'questupload');
                }
            } else {
                // If a questupload is a copy of a public questupload which has been deleted.
                $data[] = get_string('removenotinuse', 'questupload');
            }
        }
    }
    $table->data[] = $data;
} // End of loop over questupload instances.

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
