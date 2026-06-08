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

namespace mod_questupload\question;
use core_media_manager;
use form_filemanager;
use html_writer;
use mod_questupload\responsetype\response\response;
use moodle_url;
use MoodleQuickForm;

/**
 * This file contains the parent class for text question types.
 *
 * @author Laurent David
 * @author Martin Cornu-Mansuy
 * @copyright 2023 onward CALL Learning <martin@call-learning.fr>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questupload
 */
class fileupload extends question {
    /**
     * Default file upload settings.
     *
     * @param int $id
     * @param \stdClass|null $question
     * @param \context|null $context
     * @param array $params
     */
    public function __construct($id = 0, $question = null, $context = null, $params = []) {
        $this->length = 2;
        $this->precise = 1;
        parent::__construct($id, $question, $context, $params);
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function helpname() {
        return 'fileupload';
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function question_template() {
        return false;
    }

    /**
     * Override and return a response template if provided. Output of response_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function response_template() {
        return false;
    }

    /**
     * Get response class.
     *
     * @return object|string
     */
    protected function responseclass() {
        return '\\mod_questupload\\responsetype\\fileupload';
    }

    /**
     * Survey display output.
     *
     * @param response $response
     * @param object $descendantsdata
     * @param bool $blankquestionnaire
     * @return string
     */
    protected function question_survey_display($response, $descendantsdata, $blankquestionnaire = false) {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/filelib.php');

        $elname = 'q' . $this->id;
        $draftitemid = file_get_submitted_draft_itemid($elname);
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_questupload',
            'response_file',
            0,
            $this->get_file_manager_option()
        );

        if (isset($response->answers[$this->id]) && !empty($response->answers[$this->id]) &&
                isset($_REQUEST['resume']) && $_REQUEST['resume'] === '1') {
            $this->prepare_existing_files_in_draft_area($draftitemid, $response->answers[$this->id]);
        }
        // Filemanager form element implementation is far from optimal, we need to rework this if we ever fix it...
        require_once("$CFG->dirroot/lib/form/filemanager.php");
        $fm = new form_filemanager((object)$this->get_file_manager_option($draftitemid));
        $output = $PAGE->get_renderer('core', 'files');
        $html = '<div class="form-filemanager" data-fieldtype="filemanager">' .
            $output->render($fm) .
            '<input type="hidden" name="' . $elname . '" value="' . $draftitemid . '" />' .
            '</div>';
        return $html;
    }

    /**
     * Check question's form data for complete response.
     * @param \stdClass $responsedata The data entered into the response.
     * @return bool
     */
    public function response_complete($responsedata) {
        $answered = false;
        // If $responsedata is a response object, look through the answers.
        if (
            is_a($responsedata, 'mod_questupload\\responsetype\\response\\response') &&
            isset($responsedata->answers[$this->id]) &&
            !empty($responsedata->answers[$this->id])
        ) {
            $answer = reset($responsedata->answers[$this->id]);
            $answered = ((int)$answer->value > 0);
        } else if (isset($responsedata->{'q' . $this->id})) { // If $responsedata is webform data, check that it is not empty.
            $draftitemid = (int)$responsedata->{'q' . $this->id};
            if ($draftitemid > 0) {
                $info = file_get_draft_area_info($draftitemid);
                $answered = $info['filecount'] > 0;
            }
        }
        return !($this->required() && ($this->deleted == 'n') && !$answered);
    }

    /**
     * Get file manager options, here allow only one image or pdf file, without
     * creating subdirectories.
     *
     * @param ?int $draftitemid
     * @return array
     */
    public function get_file_manager_option(?int $draftitemid = 0) {
        global $CFG;

        return [
            'subdirs' => false,
            'accepted_types' => $this->get_accepted_types(),
            'maxbytes' => $this->get_maxbytes(),
            'maxfiles' => $this->get_maxfiles(),
            'itemid' => $draftitemid,
        ];
    }

    /**
     * Return accepted file types from question configuration.
     *
     * @return array
     */
    public function get_accepted_types() {
        $data = $this->get_fileupload_extradata();
        if (!empty($data->acceptedtypes) && is_array($data->acceptedtypes)) {
            return $this->normalise_accepted_types($data->acceptedtypes);
        }
        return $this->normalise_accepted_types(['image', 'document']);
    }

    /**
     * Return selected file type options for the editing form.
     *
     * @return array
     */
    protected function get_selected_type_options() {
        $data = $this->get_fileupload_extradata();
        if (empty($data->acceptedtypes) || !is_array($data->acceptedtypes)) {
            return ['image', 'document'];
        }

        $selected = [];
        foreach ($data->acceptedtypes as $type) {
            foreach ($this->split_accepted_type((string)$type) as $part) {
                if (in_array($part, ['.doc', '.docx', '.odt'])) {
                    $selected['wordprocessing'] = 'wordprocessing';
                } else if (in_array($part, ['.xls', '.xlsx', '.ods'])) {
                    $selected['spreadsheet'] = 'spreadsheet';
                } else {
                    $selected[$part] = $part;
                }
            }
        }

        return array_values($selected);
    }

    /**
     * Expand stored shortcut values to the accepted_types format expected by the Moodle filemanager.
     *
     * @param array $types
     * @return array
     */
    protected function normalise_accepted_types(array $types) {
        $normalised = [];
        $groups = [
            'wordprocessing' => ['.doc', '.docx', '.odt'],
            'spreadsheet' => ['.xls', '.xlsx', '.ods'],
        ];

        foreach ($types as $type) {
            foreach ($this->split_accepted_type((string)$type) as $part) {
                $expanded = $groups[$part] ?? $this->expand_type_group($part);
                foreach ($expanded as $acceptedtype) {
                    $normalised[$acceptedtype] = $acceptedtype;
                }
            }
        }

        return empty($normalised) ? $this->expand_type_group(['image', 'document']) : array_values($normalised);
    }

    /**
     * Expand Moodle file type groups to concrete extensions for upload validation.
     *
     * @param string|array $types
     * @return array
     */
    protected function expand_type_group($types) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $expanded = \file_get_typegroup('extension', $types);
        if (!empty($expanded)) {
            return $expanded;
        }
        return is_array($types) ? $types : [$types];
    }

    /**
     * Split legacy comma-separated type values.
     *
     * @param string $type
     * @return array
     */
    protected function split_accepted_type(string $type) {
        return array_values(array_filter(array_map('trim', explode(',', $type))));
    }
    /**
     * Return maximum number of files for this question.
     *
     * @return int
     */
    public function get_maxfiles() {
        return max(1, (int)$this->precise);
    }

    /**
     * Return maximum file size in bytes.
     *
     * @return int
     */
    public function get_maxbytes() {
        global $CFG;

        $mb = (int)$this->length;
        if ($mb <= 0) {
            return $CFG->maxbytes ?? 0;
        }
        return $mb * 1024 * 1024;
    }

    /**
     * Decode file upload settings stored in extradata.
     *
     * @return \stdClass
     */
    protected function get_fileupload_extradata() {
        $data = json_decode((string)$this->extradata);
        return is_object($data) ? $data : (object)[];
    }

    /**
     * Response display output, e.g. render the file image/pdf or show a linkt to download it.
     *
     * @param \stdClass $data
     * @return string
     */
    protected function response_survey_display($data) {
        global $PAGE, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/resourcelib.php');
        if (empty($data->answers[$this->id])) {
            return '';
        }

        $fs = get_file_storage();
        $mediamanager = core_media_manager::instance($PAGE);
        $embedoptions = [
            core_media_manager::OPTION_TRUSTED => true,
            core_media_manager::OPTION_BLOCK => true,
        ];
        $outputs = [];

        foreach ($data->answers[$this->id] as $answer) {
            $file = $fs->get_file_by_id($answer->value);
            if (!$file) {
                continue;
            }

            $moodleurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            $mimetype = $file->get_mimetype();
            $title = $file->get_filename();
            if (file_mimetype_in_typegroup($mimetype, 'web_image')) {
                $outputs[] = resourcelib_embed_image($moodleurl->out(), $title);
            } else if ($mimetype === 'application/pdf') {
                $outputs[] = resourcelib_embed_pdf($moodleurl->out(), $title, get_string('view'));
            } else if ($mediamanager->can_embed_url($moodleurl, $embedoptions)) {
                $outputs[] = $mediamanager->embed_url($moodleurl, $title, 0, 0, $embedoptions);
            } else {
                $outputs[] = $this->render_download_link($moodleurl, $file);
            }
        }

        return '<div class="response text">' . implode('', $outputs) . '</div>';
    }



    /**
     * Render a reliable fallback for file types that browsers cannot preview natively.
     *
     * @param moodle_url $url
     * @param \stored_file $file
     * @return string
     */
    protected function render_download_link(moodle_url $url, \stored_file $file) {
        $downloadurl = new moodle_url($url);
        $downloadurl->param('forcedownload', 1);
        $filename = $file->get_filename();
        $filesize = display_size($file->get_filesize());
        $mimetype = $file->get_mimetype();
        $label = get_string('fileuploaddownloadfile', 'questupload', (object)[
            'filename' => $filename,
            'filesize' => $filesize,
            'mimetype' => $mimetype,
        ]);

        return html_writer::div(
            html_writer::link($downloadurl, s($filename), ['class' => 'btn btn-secondary', 'target' => '_blank']) .
                html_writer::span($label, 'ms-2 text-muted'),
            'questupload-filedownload my-2'
        );
    }

    /**
     * Add the length element as hidden.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_length(MoodleQuickForm $mform, $helpname = '') {
        $mform->addElement('text', 'length', get_string('fileuploadmaxbytes', 'questupload'), ['size' => '5']);
        $mform->setType('length', PARAM_INT);
        $mform->setDefault('length', max(0, (int)$this->length));
        $mform->addHelpButton('length', 'fileuploadmaxbytes', 'questupload');
        return $mform;
    }

    /**
     * Add maximum files control.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_precise(MoodleQuickForm $mform, $helpname = '') {
        $options = [];
        for ($i = 1; $i <= 10; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'precise', get_string('fileuploadmaxfiles', 'questupload'), $options);
        $mform->setType('precise', PARAM_INT);
        $mform->setDefault('precise', $this->get_maxfiles());
        $mform->addHelpButton('precise', 'fileuploadmaxfiles', 'questupload');
        return $mform;
    }

    /**
     * Add accepted file types selector.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_extradata(MoodleQuickForm $mform, $helpname = '') {
        $options = [
            'image' => get_string('fileuploadtypeimage', 'questupload'),
            'document' => get_string('fileuploadtypedocument', 'questupload'),
            'audio' => get_string('fileuploadtypeaudio', 'questupload'),
            'video' => get_string('fileuploadtypevideo', 'questupload'),
            'archive' => get_string('fileuploadtypearchive', 'questupload'),
            '.pdf' => get_string('fileuploadtypepdf', 'questupload'),
            'wordprocessing' => get_string('fileuploadtypeword', 'questupload'),
            'spreadsheet' => get_string('fileuploadtypespreadsheet', 'questupload'),
        ];
        $select = $mform->addElement('autocomplete', 'acceptedtypes', get_string('fileuploadacceptedtypes', 'questupload'),
            $options, ['multiple' => true]);
        $select->setMultiple(true);
        $mform->setType('acceptedtypes', PARAM_RAW);
        $mform->setDefault('acceptedtypes', $this->get_selected_type_options());
        $mform->addHelpButton('acceptedtypes', 'fileuploadacceptedtypes', 'questupload');
        $mform->addElement('hidden', 'extradata');
        $mform->setType('extradata', PARAM_RAW);
        return $mform;
    }

    /**
     * Store accepted file types in extradata.
     *
     * @param \stdClass $formdata
     * @return bool
     */
    protected function form_preprocess_data($formdata) {
        parent::form_preprocess_data($formdata);
        $acceptedtypes = $formdata->acceptedtypes ?? [];
        if (!is_array($acceptedtypes)) {
            $acceptedtypes = [$acceptedtypes];
        }
        $acceptedtypes = array_values(array_filter(array_map('trim', $acceptedtypes)));
        if (empty($acceptedtypes)) {
            $acceptedtypes = ['image', 'document'];
        }
        $formdata->length = max(0, (int)($formdata->length ?? 0));
        $formdata->precise = max(1, min(10, (int)($formdata->precise ?? 1)));
        $formdata->extradata = json_encode(['acceptedtypes' => $acceptedtypes]);
        return true;
    }
}
