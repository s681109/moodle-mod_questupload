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

use core\session\manager as sessionmanager;
use core_files\archive_writer;
use core_php_time_limit;
use stored_file;

/**
 * Builds a ZIP archive with files uploaded in questionnaire responses.
 *
 * @package mod_questupload
 */
class downloader {
    /** @var \questupload Questionnaire Upload manager instance. */
    protected $manager;

    /** @var \stdClass Course record. */
    protected $course;

    /** @var \stdClass Course module record. */
    protected $cm;

    /** @var \context_module Module context. */
    protected $context;

    /** @var int Current group id filter. */
    protected $groupid;

    /** @var array Files indexed by ZIP path. */
    protected $filesforzipping = [];

    /** @var array Response counters by user folder. */
    protected $responsecounters = [];

    /** @var array Response folder cache by response id. */
    protected $responsefolders = [];

    /** @var array String files indexed by ZIP path. */
    protected $stringfilesforzipping = [];

    /** @var string Folder prefix for uploaded attachments. */
    protected $attachmentprefix = '';

    /** @var string Language string key used as ZIP filename suffix. */
    protected $zipshortstring = 'fileuploadattachmentszipshort';
    /** @var bool Whether attachments should be stored below the response folder. */
    protected $responsefolderstructure = false;

    /** @var string Root folder used by the per-respondent ZIP structure. */
    protected $responsefolderroot = 'respondentes';

    /** @var string Folder used for uploaded files inside each response folder. */
    protected $responseattachmentfolder = 'anexos';

    /**
     * Constructor.
     *
     * @param \questupload $manager Questionnaire Upload manager instance.
     * @param int $groupid Optional current group id filter.
     */
    public function __construct(\questupload $manager, int $groupid = 0) {
        $this->manager = $manager;
        $this->course = $manager->course;
        $this->cm = $manager->cm;
        $this->context = $manager->context;
        $this->groupid = $groupid;
    }

    /**
     * Load all response files available for the configured report scope.
     *
     * @return bool true when there is at least one file to download.
     */
    public function load_filelist(): bool {
        global $DB;

        core_php_time_limit::raise();
        $this->filesforzipping = [];
        $this->responsecounters = [];
        $this->responsefolders = [];

        $sql = "SELECT rf.id AS responsefileid,
                       rf.response_id,
                       rf.question_id,
                       rf.fileid,
                       r.userid,
                       r.submitted,
                       q.position,
                       q.name AS questionname,
                       q.content AS questioncontent
                  FROM {questupload_response_file} rf
                  JOIN {questupload_response} r ON r.id = rf.response_id
                  JOIN {questupload_question} q ON q.id = rf.question_id
                 WHERE r.questuploadid = :questuploadid
              ORDER BY r.userid ASC, r.submitted ASC, r.id ASC, q.position ASC, rf.id ASC";
        $records = $DB->get_records_sql($sql, ['questuploadid' => $this->manager->id]);
        if (empty($records)) {
            return false;
        }

        $users = $this->load_users($records);
        $fs = get_file_storage();

        foreach ($records as $record) {
            if (!$this->response_matches_group($record)) {
                continue;
            }

            $file = $fs->get_file_by_id($record->fileid);
            if (!$file || $file->is_directory()) {
                continue;
            }

            $path = $this->attachmentprefix . $this->get_path_for_file($record, $file, $users);
            $this->filesforzipping[$this->get_unique_path($path)] = $file;
        }

        return !empty($this->filesforzipping);
    }

    /**
     * Add a generated text file to the ZIP archive.
     *
     * @param string $pathinzip Path inside the ZIP.
     * @param string $content File content.
     */
    public function add_string_file(string $pathinzip, string $content): void {
        $this->stringfilesforzipping[$this->get_unique_path(clean_param($pathinzip, PARAM_PATH))] = $content;
    }

    /**
     * Set a folder prefix for uploaded attachments.
     *
     * @param string $prefix Prefix path.
     */
    public function set_attachment_prefix(string $prefix): void {
        $prefix = trim($prefix, '/');
        $this->attachmentprefix = $prefix === '' ? '' : clean_param($prefix . '/', PARAM_PATH);
    }

    /**
     * Set the language string used as ZIP filename suffix.
     *
     * @param string $stringkey Language string key.
     */
    public function set_zip_short_string(string $stringkey): void {
        $this->zipshortstring = $stringkey;
    }

    /**
     * Store attachments under the same response folder used by generated response PDFs.
     *
     * @param string $rootfolder Root folder for respondent folders.
     * @param string $attachmentfolder Attachment folder inside each response folder.
     */
    public function use_response_folder_structure(string $rootfolder = 'respondentes', string $attachmentfolder = 'anexos'): void {
        $this->responsefolderstructure = true;
        $this->responsefolderroot = trim(clean_filename($rootfolder), '/');
        $this->responseattachmentfolder = trim(clean_filename($attachmentfolder), '/');
    }

    /**
     * Add one generated PDF with the response data for each response in the selected report scope.
     *
     * @param int $choicecodes Include choice codes in generated data.
     * @param int $choicetext Include choice text in generated data.
     * @param int $showincompletes Include incomplete responses.
     * @param int $rankaverages Include rank averages.
     */
    public function add_response_pdf_files(int $choicecodes, int $choicetext, int $showincompletes, int $rankaverages): void {
        $responses = $this->load_response_records($showincompletes);
        foreach ($responses as $response) {
            if (!$this->response_matches_group($response)) {
                continue;
            }

            $output = $this->manager->generate_csv($this->groupid, $response->response_id, '', $choicecodes, $choicetext,
                $showincompletes, $rankaverages);
            if (empty($output[0]) || count($output) < 2) {
                continue;
            }

            $columns = $this->normalise_csv_row($output[0]);
            unset($output[0]);
            $row = $this->normalise_csv_row(reset($output));
            $pdfcontent = $this->build_response_pdf($response, $columns, $row);
            $path = $this->get_response_base_folder($response) . '/resposta.pdf';
            $this->add_string_file($path, $pdfcontent);
        }
    }
    /**
     * Stream the ZIP archive to the browser and terminate the request.
     */
    public function download_zip(): void {
        sessionmanager::write_close();
        $zipwriter = archive_writer::get_stream_writer($this->get_zip_filename(), archive_writer::ZIP_WRITER);

        foreach ($this->stringfilesforzipping as $pathinzip => $content) {
            $zipwriter->add_file_from_string($pathinzip, $content);
        }

        foreach ($this->filesforzipping as $pathinzip => $file) {
            if ($file instanceof stored_file) {
                $zipwriter->add_file_from_stored_file($pathinzip, $file);
            }
        }

        $zipwriter->finish();
        exit();
    }

    /**
     * Load response records used to generate individual response PDFs.
     *
     * @param int $showincompletes Include incomplete responses.
     * @return array Response records indexed by response id.
     */
    protected function load_response_records(int $showincompletes): array {
        global $DB;

        $where = 'r.questuploadid = :questuploadid';
        $params = ['questuploadid' => $this->manager->id];
        if (empty($showincompletes)) {
            $where .= " AND r.complete = 'y'";
        }

        $sql = "SELECT r.id,
                       r.id AS response_id,
                       r.userid,
                       r.submitted
                  FROM {questupload_response} r
                 WHERE {$where}
              ORDER BY r.userid ASC, r.submitted ASC, r.id ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return the base folder for a response in the per-respondent ZIP structure.
     *
     * @param \stdClass $record Response or response file record.
     * @param array|null $users Optional users indexed by id.
     * @return string Folder path inside the ZIP.
     */
    protected function get_response_base_folder(\stdClass $record, ?array $users = null): string {
        if ($users === null) {
            $users = $this->load_users([$record]);
        }
        $userfolder = $this->get_user_folder($record, $users);
        $responsefolder = $this->get_response_folder($record, $userfolder);
        $root = trim($this->responsefolderroot, '/');

        return clean_param(($root === '' ? '' : $root . '/') . $userfolder . '/' . $responsefolder, PARAM_PATH);
    }

    /**
     * Generate the PDF content for one response.
     *
     * @param \stdClass $response Response record.
     * @param array $columns Report columns.
     * @param array $row Report values.
     * @return string PDF binary content.
     */
    protected function build_response_pdf(\stdClass $response, array $columns, array $row): string {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        ob_start();
        $errorreporting = error_reporting(0);
        try {
            $pdf = new \pdf();
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Moodle Questionnaire Upload');
            $pdf->SetTitle(format_string($this->manager->name, true, ['context' => $this->context]));
            $pdf->setPrintHeader(false);
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(14, 14, 14);
            $pdf->SetAutoPageBreak(true, 14);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            $pdf->AddPage('P');
            $pdf->writeHTML($this->build_response_pdf_html($response, $columns, $row));
            $content = $pdf->Output('', 'S');
        } finally {
            error_reporting($errorreporting);
            ob_end_clean();
        }

        return $content;
    }

    /**
     * Build the HTML rendered into one response PDF.
     *
     * @param \stdClass $response Response record.
     * @param array $columns Report columns.
     * @param array $row Report values.
     * @return string HTML.
     */
    protected function build_response_pdf_html(\stdClass $response, array $columns, array $row): string {
        $user = $this->get_response_user($response);
        $fullname = $user ? fullname($user, has_capability('moodle/site:viewfullnames', $this->context)) :
            get_string('anonymous', 'questupload');
        $submitted = !empty($response->submitted) ? userdate($response->submitted) : '-';
        $questionrows = [];
        $metarows = [];

        foreach ($columns as $index => $column) {
            $value = $row[$index] ?? '';
            if ($value === '' || $value === null) {
                $value = '-';
            }
            $target = preg_match('/^Q\d+_/u', (string)$column) ? 'questionrows' : 'metarows';
            ${$target}[] = [(string)$column, (string)$value];
        }

        if (empty($questionrows)) {
            $questionrows = $metarows;
            $metarows = [];
        }

        $html = '<style>
            h1 { color: #174a35; font-size: 22px; }
            h2 { color: #333333; font-size: 14px; margin-top: 12px; }
            .meta { background-color: #f2f6f4; border: 1px solid #d6e2dc; padding: 8px; }
            .label { color: #4a4a4a; font-weight: bold; }
            table.answers { border-collapse: collapse; width: 100%; }
            table.answers th { background-color: #174a35; color: #ffffff; font-weight: bold; padding: 6px; }
            table.answers td { border: 1px solid #d7d7d7; padding: 6px; vertical-align: top; }
            table.adminmeta { border-collapse: collapse; width: 100%; font-size: 9px; color: #555555; }
            table.adminmeta th { background-color: #eeeeee; color: #333333; font-weight: bold; padding: 4px; }
            table.adminmeta td { border: 1px solid #dddddd; padding: 4px; vertical-align: top; }
            .muted { color: #666666; font-size: 10px; }
        </style>';
        $html .= '<h1>' . s(format_string($this->manager->name, true, ['context' => $this->context])) . '</h1>';
        $html .= '<div class="meta">';
        $html .= '<span class="label">' . s(get_string('respondent', 'questupload')) . ':</span> ' . s($fullname) . '<br />';
        $html .= '<span class="label">' . s(get_string('submitted', 'questupload')) . ':</span> ' . s($submitted);
        $html .= '</div>';
        $html .= '<h2>' . s(get_string('responses', 'questupload')) . '</h2>';
        $html .= '<table class="answers" cellpadding="4"><thead><tr><th width="34%">' . s(get_string('question')) .
            '</th><th width="66%">' . s(get_string('response', 'questupload')) . '</th></tr></thead><tbody>';

        foreach ($questionrows as $questionrow) {
            [$column, $value] = $questionrow;
            $html .= '<tr><td width="34%">' . s($column) . '</td><td width="66%">' .
                nl2br(s($value)) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        $html .= $this->build_response_pdf_attachment_html($response);
        $html .= $this->build_response_pdf_metadata_html($response, $metarows);
        $html .= '<p class="muted">' . s(get_string('fileuploadresponsepdfgeneratednote', 'questupload')) . '</p>';

        return $html;
    }

    /**
     * Build a compact metadata table for the response PDF.
     *
     * @param \stdClass $response Response record.
     * @param array $metarows Metadata rows from the report.
     * @return string HTML.
     */
    protected function build_response_pdf_metadata_html(\stdClass $response, array $metarows): string {
        $html = '<h2>' . s(get_string('fileuploadresponsepdfmetadata', 'questupload')) . '</h2>';
        $html .= '<table class="adminmeta" cellpadding="3"><tbody>';
        $html .= '<tr><td width="34%">' . s(get_string('course')) . '</td><td width="66%">' .
            s(format_string($this->course->fullname)) . '</td></tr>';
        $html .= '<tr><td width="34%">' . s(get_string('response', 'questupload')) . '</td><td width="66%">' .
            (int)$response->response_id . '</td></tr>';
        $html .= '<tr><td width="34%">' . s(get_string('generated', 'moodle')) . '</td><td width="66%">' .
            s(userdate(time())) . '</td></tr>';
        foreach ($metarows as $metarow) {
            [$column, $value] = $metarow;
            $html .= '<tr><td width="34%">' . s($column) . '</td><td width="66%">' . nl2br(s($value)) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }
    /**
     * Add attachment information to the response PDF.
     *
     * @param \stdClass $response Response record.
     * @return string HTML.
     */
    protected function build_response_pdf_attachment_html(\stdClass $response): string {
        global $DB;

        $sql = "SELECT rf.id,
                       rf.fileid,
                       q.position,
                       q.name AS questionname,
                       q.content AS questioncontent
                  FROM {questupload_response_file} rf
                  JOIN {questupload_question} q ON q.id = rf.question_id
                 WHERE rf.response_id = :responseid
              ORDER BY q.position ASC, rf.id ASC";
        $records = $DB->get_records_sql($sql, ['responseid' => $response->response_id]);
        if (empty($records)) {
            return '';
        }

        $fs = get_file_storage();
        $html = '<h2>' . s(get_string('fileuploadattachments', 'questupload')) . '</h2><table class="answers" cellpadding="4"><tbody>';
        foreach ($records as $record) {
            $file = $fs->get_file_by_id($record->fileid);
            if (!$file || $file->is_directory()) {
                continue;
            }
            $html .= '<tr><td width="34%">' . s($this->get_question_folder($record)) . '</td><td width="66%">' .
                s($file->get_filename()) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Return a response user record.
     *
     * @param \stdClass $response Response record.
     * @return \stdClass|null
     */
    protected function get_response_user(\stdClass $response): ?\stdClass {
        global $DB;

        if (empty($response->userid)) {
            return null;
        }

        return $DB->get_record('user', ['id' => $response->userid]) ?: null;
    }

    /**
     * Flatten CSV cells that may still be keyed by question position.
     *
     * @param mixed $row Row data.
     * @return array Normalised row.
     */
    protected function normalise_csv_row($row): array {
        $normalised = [];
        foreach ((array)$row as $cell) {
            if (is_array($cell)) {
                $cell = reset($cell);
            }
            $normalised[] = $cell;
        }

        return $normalised;
    }
    /**
     * Load users referenced by response records.
     *
     * @param array $records Response file records.
     * @return array Users indexed by id.
     */
    protected function load_users(array $records): array {
        global $DB;

        $userids = [];
        foreach ($records as $record) {
            if (!empty($record->userid)) {
                $userids[(int)$record->userid] = (int)$record->userid;
            }
        }

        if (empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED);
        return $DB->get_records_select('user', "id {$insql}", $params);
    }

    /**
     * Check whether a response belongs to the selected group filter.
     *
     * @param \stdClass $record Response file record.
     * @return bool
     */
    protected function response_matches_group(\stdClass $record): bool {
        if (empty($this->groupid)) {
            return true;
        }
        if (empty($record->userid)) {
            return false;
        }
        return groups_is_member($this->groupid, $record->userid);
    }

    /**
     * Build the ZIP path for an uploaded file.
     *
     * @param \stdClass $record Response file record.
     * @param stored_file $file File API record.
     * @param array $users Users indexed by id.
     * @return string
     */
    protected function get_path_for_file(\stdClass $record, stored_file $file, array $users): string {
        $questionfolder = $this->get_question_folder($record);
        $filename = clean_filename($file->get_filename());

        if ($this->responsefolderstructure) {
            $path = $this->get_response_base_folder($record, $users) . '/' . $this->responseattachmentfolder . '/' .
                $questionfolder . '/' . $filename;
            return clean_param($path, PARAM_PATH);
        }

        $userfolder = $this->get_user_folder($record, $users);
        $responsefolder = $this->get_response_folder($record, $userfolder);

        return clean_param($userfolder . '/' . $responsefolder . '/' . $questionfolder . '/' . $filename, PARAM_PATH);
    }

    /**
     * Return the user folder name.
     *
     * @param \stdClass $record Response file record.
     * @param array $users Users indexed by id.
     * @return string
     */
    protected function get_user_folder(\stdClass $record, array $users): string {
        $userid = (int)$record->userid;
        if ($userid > 0 && isset($users[$userid])) {
            $fullname = fullname($users[$userid], has_capability('moodle/site:viewfullnames', $this->context));
        } else {
            $fullname = get_string('anonymous', 'questupload');
        }

        return clean_filename(str_replace('_', ' ', $fullname) . '_' . $userid);
    }

    /**
     * Return a stable response folder for the response id.
     *
     * @param \stdClass $record Response file record.
     * @param string $userfolder Current user folder.
     * @return string
     */
    protected function get_response_folder(\stdClass $record, string $userfolder): string {
        if (!isset($this->responsefolders[$record->response_id])) {
            if (!isset($this->responsecounters[$userfolder])) {
                $this->responsecounters[$userfolder] = 0;
            }
            $this->responsecounters[$userfolder]++;
            $this->responsefolders[$record->response_id] = 'resposta_' . $this->responsecounters[$userfolder];
        }

        return $this->responsefolders[$record->response_id];
    }

    /**
     * Return the question folder name.
     *
     * @param \stdClass $record Response file record.
     * @return string
     */
    protected function get_question_folder(\stdClass $record): string {
        $number = max(1, (int)$record->position);
        $label = $this->normalise_folder_label($record->questioncontent ?? '');
        if ($label === '') {
            $label = $this->normalise_folder_label(format_string($record->questionname ?? '', true, ['context' => $this->context]));
        }
        if ($label === '') {
            $label = get_string('question') . '_' . $number;
        }
        if (\core_text::strlen($label) > 80) {
            $label = \core_text::substr($label, 0, 80);
        }

        return clean_filename(sprintf('Q%02d_%s', $number, $label));
    }

    /**
     * Normalise text used in ZIP folder labels.
     *
     * @param string $label Raw label.
     * @return string
     */
    protected function normalise_folder_label(string $label): string {
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = preg_replace('/<[^>]+>/', ' ', $label);
        $label = preg_replace('/\s+/', ' ', $label);
        return trim($label ?? '');
    }

    /**
     * Ensure ZIP paths are unique.
     *
     * @param string $path Candidate path.
     * @return string Unique path.
     */
    protected function get_unique_path(string $path): string {
        if (!isset($this->filesforzipping[$path]) && !isset($this->stringfilesforzipping[$path])) {
            return $path;
        }

        $pathinfo = pathinfo($path);
        $dirname = $pathinfo['dirname'] ?? '';
        $filename = $pathinfo['filename'] ?? $path;
        $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

        $counter = 2;
        do {
            $candidate = ($dirname && $dirname !== '.') ? $dirname . '/' : '';
            $candidate .= $filename . '_' . $counter . $extension;
            $counter++;
        } while (isset($this->filesforzipping[$candidate]) || isset($this->stringfilesforzipping[$candidate]));

        return $candidate;
    }

    /**
     * Generate the ZIP file name.
     *
     * @return string
     */
    protected function get_zip_filename(): string {
        $parts = [
            $this->course->shortname,
            $this->manager->name,
            get_string($this->zipshortstring, 'questupload'),
            $this->cm->id,
        ];

        if (!empty($this->groupid)) {
            $parts[] = groups_get_group_name($this->groupid);
        }

        return clean_filename(implode('-', $parts) . '.zip');
    }
}