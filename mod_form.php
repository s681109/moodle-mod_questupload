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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/questupload/questupload.class.php');
require_once($CFG->dirroot.'/mod/questupload/locallib.php');

/**
 * print the form to add or edit a questupload-instance
 *
 * @package mod_questupload
 * @author Mike Churchward
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class mod_questupload_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    protected function definition() {
        global $COURSE, $CFG;
        global $questuploadtypes, $questuploadrespondents, $questuploadresponseviewers, $autonumbering;

        $questupload = new questupload($COURSE, $this->_cm, $this->_instance, null);

        $mform    =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'questupload'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('description'));

        $mform->addElement('header', 'availabilityhdr', get_string('availability'));
        $mform->addElement('date_time_selector', 'opendate', get_string('opendate', 'questupload'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'closedate', get_string('closedate', 'questupload'), ['optional' => true]);

        $mform->addElement('header', 'questuploadhdr', get_string('responseoptions', 'questupload'));

        $mform->addElement('select', 'qtype', get_string('qtype', 'questupload'), $questuploadtypes);
        $mform->addHelpButton('qtype', 'qtype', 'questupload');

        $mform->addElement('hidden', 'cannotchangerespondenttype');
        $mform->setType('cannotchangerespondenttype', PARAM_INT);
        $mform->addElement('select', 'respondenttype', get_string('respondenttype', 'questupload'), $questuploadrespondents);
        $mform->addHelpButton('respondenttype', 'respondenttype', 'questupload');
        $mform->disabledIf('respondenttype', 'cannotchangerespondenttype', 'eq', 1);

        $mform->addElement('select', 'resp_view', get_string('responseview', 'questupload'), $questuploadresponseviewers);
        $mform->addHelpButton('resp_view', 'responseview', 'questupload');

        $notificationoptions = array(0 => get_string('no'), 1 => get_string('notificationsimple', 'questupload'),
            2 => get_string('notificationfull', 'questupload'));
        $mform->addElement('select', 'notifications', get_string('notifications', 'questupload'), $notificationoptions);
        $mform->addHelpButton('notifications', 'notifications', 'questupload');

        $options = array('0' => get_string('no'), '1' => get_string('yes'));
        $mform->addElement('select', 'resume', get_string('resume', 'questupload'), $options);
        $mform->addHelpButton('resume', 'resume', 'questupload');

        $options = array('0' => get_string('no'), '1' => get_string('yes'));
        $mform->addElement('select', 'navigate', get_string('navigate', 'questupload'), $options);
        $mform->addHelpButton('navigate', 'navigate', 'questupload');

        $mform->addElement('select', 'autonum', get_string('autonumbering', 'questupload'), $autonumbering);
        $mform->addHelpButton('autonum', 'autonumbering', 'questupload');
        // Default = autonumber both questions and pages.
        $mform->setDefault('autonum', 3);

        $mform->addElement('advcheckbox', 'progressbar', get_string('progressbar', 'questupload'));

        // Removed potential scales from list of grades. CONTRIB-3167.
        $grades[0] = get_string('nograde');
        for ($i = 100; $i >= 1; $i--) {
            $grades[$i] = $i;
        }
        $mform->addElement('select', 'grade', get_string('grade', 'questupload'), $grades);

        if (empty($questupload->sid)) {
            if (!isset($questupload->id)) {
                $questupload->id = 0;
            }

            $mform->addElement('header', 'contenthdr', get_string('contentoptions', 'questupload'));
            $mform->addHelpButton('contenthdr', 'createcontent', 'questupload');

            $mform->addElement('radio', 'create', get_string('createnew', 'questupload'), '', 'new-0');

            // Retrieve existing private questuploads from current course.
            $surveys = questupload_get_survey_select($COURSE->id, 'private');
            if (!empty($surveys)) {
                $prelabel = get_string('useprivate', 'questupload');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            }
            // Retrieve existing template questuploads from this site.
            $surveys = questupload_get_survey_select($COURSE->id, 'template');
            if (!empty($surveys)) {
                $prelabel = get_string('usetemplate', 'questupload');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            } else {
                $mform->addElement('static', 'usetemplate', get_string('usetemplate', 'questupload'),
                                '('.get_string('notemplatesurveys', 'questupload').')');
            }

            // Retrieve existing public questuploads from this site.
            $surveys = questupload_get_survey_select($COURSE->id, 'public');
            if (!empty($surveys)) {
                $prelabel = get_string('usepublic', 'questupload');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            } else {
                $mform->addElement('static', 'usepublic', get_string('usepublic', 'questupload'),
                                   '('.get_string('nopublicsurveys', 'questupload').')');
            }

            $mform->setDefault('create', 'new-0');
        }

        // Remove old responses.
        $isautodelete = (bool) get_config('questupload', 'autodeleteresponse');
        if ($isautodelete) {
            $options = $this->create_remove_options();
            $mform->addElement('header', 'responsehdr', get_string('removeoldresponses', 'questupload'));
            $mform->addElement('select', 'removeafter',
                    get_string('removeoldresponsesafter', 'questupload'), $options);
            $mform->addHelpButton('removeafter', 'removeoldresponses', 'questupload');
            // Just set default value when creating a new questionare.
            if (empty($questupload->sid)) {
                $defaultconfig = get_config('questupload', 'removeoldresponses');
                $mform->setDefault('removeafter', $defaultconfig);
            }
        }
        $this->standard_coursemodule_elements();

        // Buttons.
        $this->add_action_buttons();
    }

    /**
     * Pre-process form data.
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;
        if (empty($defaultvalues['opendate'])) {
            $defaultvalues['useopendate'] = 0;
        } else {
            $defaultvalues['useopendate'] = 1;
        }
        if (empty($defaultvalues['closedate'])) {
            $defaultvalues['useclosedate'] = 0;
        } else {
            $defaultvalues['useclosedate'] = 1;
        }
        // Prevent questupload set to "anonymous" to be reverted to "full name".
        $defaultvalues['cannotchangerespondenttype'] = 0;
        if (!empty($defaultvalues['respondenttype']) && $defaultvalues['respondenttype'] == "anonymous") {
            // If this questupload has responses.
            $numresp = $DB->count_records('questupload_response',
                            array('questuploadid' => $defaultvalues['instance'], 'complete' => 'y'));
            if ($numresp) {
                $defaultvalues['cannotchangerespondenttype'] = 1;
            }
        }
    }

    /**
     * Enforce validation rules here
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     **/
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['opendate'] && $data['closedate'] &&
            $data['closedate'] < $data['opendate']) {
            $errors['closedate'] = get_string('closebeforeopen', 'questupload');
        }

        return $errors;
    }

    /**
     * Add any completion rules for the form.
     * @return string[]
     */
    public function add_completion_rules() {
        global $CFG;

        // Changes for Moodle 4.3 - MDL-78516.
        if ($CFG->branch < 403) {
            $suffix = '';
        } else {
            $suffix = $this->get_suffix();
        }

        $mform =& $this->_form;
        $mform->addElement('checkbox', 'completionsubmit' . $suffix, '',
            get_string('completionsubmit', 'questupload'));
        return ['completionsubmit' . $suffix];
    }

    /**
     * True if the completion rule is enabled.
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }

    /**
     * Create options for remove old responses in the questionare.
     *
     * @return array
     */
    public function create_remove_options() {
        $options = [];
        $options[0] = get_string('removeoldresponsesdefault', 'questupload');
        for ($i = 1; $i <= 36; $i++) {
            $options[$i * 2592000] = $i > 1 ? get_string('nummonths', 'moodle', $i) : get_string('onemonth', 'questupload');
        }
        return $options;
    }

}
