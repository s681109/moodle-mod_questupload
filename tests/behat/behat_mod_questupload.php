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
 * Steps definitions related with the questupload activity.
 *
 * @package    mod_questupload
 * @category   test
 * @copyright  2016 Mike Churchward - Poet Open Source
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Behat\Context\Step\When as When,
    Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Gherkin\Node\PyStringNode as PyStringNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException;

#[\AllowDynamicProperties]
/**
 * Questionnaire Upload-related steps definitions.
 *
 * @package    mod_questupload
 * @category   test
 * @copyright  2016 Mike Churchward - Poet Open Source
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_questupload extends behat_base {

    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | None so far!      |                                                              |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Admin notification'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            default:
                throw new Exception('Unrecognised quiz page type "' . $page . '."');
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype          | name meaning                                | description                                           |
     * | view              | Questionnaire Upload name                          | The questupload info page (view.php)                |
     * | preview           | Questionnaire Upload name                          | The questupload preview page (preview.php)          |
     * | questions         | Questionnaire Upload name                          | The questupload questions page (questions.php)      |
     * | advsettings       | Questionnaire Upload name                          | The advanced settings page (questions.php)            |
     *
     * @param string $type identifies which type of page this is, e.g. 'preview'.
     * @param string $identifier identifies the particular page, e.g. 'Test questupload > preview > Attempt 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url('/mod/questupload/view.php',
                    ['id' => $this->get_cm_by_questupload_name($identifier)->id]);

            case 'preview':
                return new moodle_url('/mod/questupload/preview.php',
                    ['id' => $this->get_cm_by_questupload_name($identifier)->id]);

            case 'questions':
                return new moodle_url('/mod/questupload/questions.php',
                    ['id' => $this->get_cm_by_questupload_name($identifier)->id]);

            case 'advsettings':
                return new moodle_url('/mod/questupload/qsettings.php',
                    ['id' => $this->get_cm_by_questupload_name($identifier)->id]);

            default:
                throw new Exception('Unrecognised questupload page type "' . $type . '."');
        }
    }

    /**
     * Adds a question to the questupload with the provided data.
     *
     * @Given /^I add a "([^"]*)" question and I fill the form with:$/
     *
     * @param string $questiontype The question type by text name to enter.
     * @param TableNode $fielddata
     */
    public function i_add_a_question_and_i_fill_the_form_with($questiontype, TableNode $fielddata) {
        $validtypes = array(
            '----- Page Break -----',
            'Check Boxes',
            'Date',
            'Dropdown Box',
            'Essay Box',
            'Label',
            'Numeric',
            'Radio Buttons',
            'Rate (scale 1..5)',
            'Text Box',
            'Yes/No',
            'Slider');

        if (!in_array($questiontype, $validtypes)) {
            throw new ExpectationException('Invalid question type specified.', $this->getSession());
        }

        // We get option choices as CSV strings. If we have this, modify it for use in
        // multiline data.
        $rows = $fielddata->getRows();
        $hashrows = $fielddata->getRowsHash();
        if (isset($hashrows['Possible answers'])) {
            // Find the row that contained multiline data and add line breaks. Rows are two item arrays where the
            // first is an identifier and the second is the value.
            foreach ($rows as $key => $row) {
                if ($row[0] == 'Possible answers') {
                    $row[1] = str_replace(',', "\n", $row[1]);
                    $rows[$key] = $row;
                    break;
                }
            }
            $fielddata = new TableNode($rows);
        }
        if (isset($hashrows['Named degrees'])) {
            // Find the row that contained multiline data and add line breaks. Rows are two item arrays where the
            // first is an identifier and the second is the value.
            foreach ($rows as $key => $row) {
                if ($row[0] == 'Named degrees') {
                    $row[1] = str_replace(',', "\n", $row[1]);
                    $rows[$key] = $row;
                    break;
                }
            }
            $fielddata = new TableNode($rows);
        }

        $this->execute('behat_forms::i_set_the_field_to', array('id_type_id', $questiontype));
        $this->execute('behat_forms::press_button', 'Add selected question type');
        if (isset($hashrows['id_dependquestions_and_1'])) {
            $this->execute('behat_forms::press_button', 'id_adddependencies_and');
        }
        if (isset($hashrows['id_dependquestions_or_1'])) {
            $this->execute('behat_forms::press_button', 'id_adddependencies_or');
        }
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $fielddata);
        $this->execute('behat_forms::press_button', 'Save changes');
    }

    /**
     * Selects a radio button option in the named radio button group.
     *
     * @Given /^I click the "([^"]*)" radio button$/
     *
     * @param int $radioid
     */
    public function i_click_the_radio_button($radioid) {
        $session = $this->getSession();
        $page = $session->getPage();
        $radios = $page->findAll('xpath', '//input[@type="radio" and @id="'.$radioid.'"]');
        $radios[0]->click();
    }

    /**
     * Adds a questions and responses to the questupload with the provided name.
     *
     * @Given /^"([^"]*)" has questions and responses$/
     *
     * @param string $questuploadname The name of an existing questupload.
     */
    public function has_questions_and_responses($questuploadname) {
        global $DB;

        if (!$questupload = $DB->get_record('questupload', array('name' => $questuploadname), 'id,sid')) {
            throw new ExpectationException('Invalid questupload name specified.', $this->getSession());
        }

        if (!$DB->record_exists('questupload_survey', array('id' => $questupload->sid))) {
            throw new ExpectationException('Questionnaire Upload survey does not exist.', $this->getSession());
        }

        $this->add_question_data($questupload->sid);
        $this->add_response_data($questupload->id, $questupload->sid);
    }

    /**
     * Custom deleteddate for run cron task.
     *
     * @Given /^I custom deleted date in table questupload question for cron task$/
     */
    public function i_custom_deleted_date_in_table_questupload_question_for_cron_task() {
        global $DB;
        if (!$questupload = $DB->get_records_select("questupload_question", 'deleted IS NOT NULL')) {
            throw new ExpectationException('Invalid questupload name specified.', $this->getSession());
        }
        foreach ($questupload as $question) {
            $question->deleted = $question->deleted - 8 * 24 * 60 * 60;
            $DB->set_field('questupload_question', 'deleted', $question->deleted, ['id' => $question->id]);
        }
    }

    /**
     * Adds a question data to the given survey id.
     *
     * @param int $sid The id field of an existing questupload_survey record.
     * @return null
     */
    private function add_question_data($sid) {
        $questiondata = array(
            array("id", "surveyid", "name", "type_id", "result_id", "length", "precise", "position", "content", "required",
                  "deleted", "dependquestion", "dependchoice"),
            array("1", $sid, "own car", "1", null, "0", "0", "1", "<p>Do you own a car?</p>", "y", null, "0", "0"),
            array("2", $sid, "optional", "2", null, "20", "25", "3", "<p>What is the colour of your car?</p>", "y", null, "121",
                  "0"),
            array("3", $sid, null, "99", null, "0", "0", "2", "break", "n", null, "0", "0"),
            array("4", $sid, "optional2", "1", null, "0", "0", "5", "<p>Do you sometimes use public transport to go to work?</p>",
                  "y", null, "0", "0"),
            array("5", $sid, null, "99", null, "0", "0", "4", "break", "n", null, "0", "0"),
            array("6", $sid, "entertext", "2", null, "20", "10", "6", "<p>Enter no more than 10 characters.<br></p>", "n", null, "0",
                  "0"),
            array("7", $sid, "q7", "5", null, "0", "0", "7", "<p>Check all that apply<br></p>", "n", null, "0", "0"),
            array("8", $sid, "q8", "9", null, "0", "0", "8", "<p>Enter today's date<br></p>", "n", null, "0", "0"),
            array("9", $sid, "q9", "6", null, "0", "0", "9", "<p>Choose One<br></p>", "n", null, "0", "0"),
            array("10", $sid, "q10", "3", null, "5", "0", "10", "<p>Write an essay<br></p>", "n", null, "0", "0"),
            array("11", $sid, "q11", "10", null, "10", "0", "11", "<p>Enter a number<br></p>", "n", null, "0", "0"),
            array("12", $sid, "q12", "4", null, "1", "0", "13", "<p>Choose a colour<br></p>", "n", null, "0", "0"),
            array("13", $sid, "q13", "8", null, "5", "1", "14", "<p>Rate this.<br></p>", "n", null, "0", "0"),
            array("14", $sid, null, "99", null, "0", "0", "12", "break", "n", time(), "0", "0"),
            array("15", $sid, null, "99", null, "0", "0", "12", "break", "n", null, "0", "0"),
            array("16", $sid, "Q1", "10", null, "3", "2", "15", "Enter a number<br><p><br></p>", "y", null, "0", "0"),
        );

        $choicedata = array(
            array("id", "question_id", "content", "value"),
            array("1", "7", "1", null),
            array("2", "7", "2", null),
            array("3", "7", "3", null),
            array("4", "7", "4", null),
            array("5", "7", "5", null),
            array("6", "9", "1", null),
            array("7", "9", "One", null),
            array("8", "9", "2", null),
            array("9", "9", "Two", null),
            array("10", "9", "3", null),
            array("11", "9", "Three", null),
            array("12", "12", "Red", null),
            array("13", "12", "Toyota", null),
            array("14", "12", "Bird", null),
            array("15", "12", "Blew", null),
            array("16", "13", "Good", null),
            array("17", "13", "Great", null),
            array("18", "13", "So-so", null),
            array("19", "13", "Lamp", null),
            array("20", "13", "Huh?", null),
            array("21", "7", "!other=Another number", null),
            array("22", "12", "!other=Something else", null)
        );

        $this->add_data($questiondata, 'questupload_question', 'questionmap');
        $this->add_data($choicedata, 'questupload_quest_choice', 'choicemap', array('questionmap' => 'question_id'));
    }

    /**
     * Adds response data to the given questupload and survey id.
     *
     * @param int $qid The id field of an existing questupload record.
     * @param int $sid The id field of an existing questupload_survey record.
     * @return null
     */
    private function add_response_data($qid, $sid) {
        global $DB;

        $responses = array(
            array("id", "questuploadid", "submitted", "complete", "grade", "userid"),
            array("1", $qid, "1419011935", "y", "0", "2"),
            array("2", $qid, "1449064371", "y", "0", "2"),
            array("3", $qid, "1449258520", "y", "0", "2"),
            array("4", $qid, "1452020444", "y", "0", "2"),
            array("5", $qid, "1452804783", "y", "0", "2"),
            array("6", $qid, "1452806547", "y", "0", "2"),
            array("7", $qid, "1465415731", "n", "0", "2")
        );
        $this->add_data($responses, 'questupload_response', 'responsemap');

        $responsebool = array(
            array("id", "response_id", "question_id", "choice_id"),
            array("", "1", "1", "y"),
            array("", "1", "4", "n"),
            array("", "2", "1", "y"),
            array("", "2", "4", "n"),
            array("", "3", "1", "n"),
            array("", "3", "4", "y"),
            array("", "4", "1", "y"),
            array("", "4", "4", "n"),
            array("", "5", "1", "n"),
            array("", "5", "4", "n"),
            array("", "6", "1", "n"),
            array("", "6", "4", "n"),
            array("", "7", "1", "y"),
            array("", "7", "4", "y")
        );
        $this->add_data($responsebool, 'questupload_response_bool', '',
            array('responsemap' => 'response_id', 'questionmap' => 'question_id'));

        $responsedate = array(
            array("id", "response_id", "question_id", "response"),
            array("", "1", "8", "2014-12-19"),
            array("", "2", "8", "2015-12-02"),
            array("", "3", "8", "2015-12-04"),
            array("", "4", "8", "2016-01-06"),
            array("", "5", "8", "2016-01-13"),
            array("", "6", "8", "2016-01-13")
        );
        $this->add_data($responsedate, 'questupload_response_date', '',
            array('responsemap' => 'response_id', 'questionmap' => 'question_id'));

        $responseother = array(
            array("id", "response_id", "question_id", "choice_id", "response"),
            array("", "5", "7", "21", "Forty-four"),
            array("", "6", "12", "22", "Green"),
            array("", "7", "7", "21", "5")
        );
        $this->add_data($responseother, 'questupload_response_other', '',
            array('responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id'));

        $responserank = array(
            array("id", "response_id", "question_id", "choice_id", "rankvalue"),
            array("", "1", "13", "16", "0"),
            array("", "1", "13", "17", "1"),
            array("", "1", "13", "18", "2"),
            array("", "1", "13", "19", "3"),
            array("", "1", "13", "20", "4"),
            array("", "2", "13", "16", "0"),
            array("", "2", "13", "17", "1"),
            array("", "2", "13", "18", "2"),
            array("", "2", "13", "19", "3"),
            array("", "2", "13", "20", "4"),
            array("", "3", "13", "16", "4"),
            array("", "3", "13", "17", "0"),
            array("", "3", "13", "18", "3"),
            array("", "3", "13", "19", "1"),
            array("", "3", "13", "20", "2"),
            array("", "4", "13", "16", "2"),
            array("", "4", "13", "17", "2"),
            array("", "4", "13", "18", "2"),
            array("", "4", "13", "19", "2"),
            array("", "4", "13", "20", "2"),
            array("", "5", "13", "16", "1"),
            array("", "5", "13", "17", "1"),
            array("", "5", "13", "18", "1"),
            array("", "5", "13", "19", "1"),
            array("", "5", "13", "20", "-1"),
            array("", "6", "13", "16", "2"),
            array("", "6", "13", "17", "3"),
            array("", "6", "13", "18", "-1"),
            array("", "6", "13", "19", "1"),
            array("", "6", "13", "20", "-1"),
            array("", "7", "13", "16", "-999"),
            array("", "7", "13", "17", "-999"),
            array("", "7", "13", "18", "-999"),
            array("", "7", "13", "19", "-999"),
            array("", "7", "13", "20", "-999")
        );
        $this->add_data($responserank, 'questupload_response_rank', '',
            array('responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id'));

        $respmultiple = array(
            array("id", "response_id", "question_id", "choice_id"),
            array("", "1", "7", "1"),
            array("", "1", "7", "3"),
            array("", "1", "7", "5"),
            array("", "2", "7", "4"),
            array("", "3", "7", "2"),
            array("", "3", "7", "4"),
            array("", "4", "7", "2"),
            array("", "4", "7", "4"),
            array("", "4", "7", "5"),
            array("", "5", "7", "2"),
            array("", "5", "7", "3"),
            array("", "5", "7", "21"),
            array("", "6", "7", "2"),
            array("", "6", "7", "5"),
            array("", "7", "7", "21")
        );
        $this->add_data($respmultiple, 'questupload_resp_multiple', '',
            array('responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id'));

        $respsingle = array(
            array("id", "response_id", "question_id", "choice_id"),
            array("", "1", "9", "7"),
            array("", "1", "12", "15"),
            array("", "2", "9", "7"),
            array("", "2", "12", "14"),
            array("", "3", "9", "11"),
            array("", "3", "12", "15"),
            array("", "4", "9", "6"),
            array("", "4", "12", "12"),
            array("", "5", "9", "6"),
            array("", "5", "12", "13"),
            array("", "6", "9", "7"),
            array("", "6", "12", "22")
        );
        $this->add_data($respsingle, 'questupload_resp_single', '',
            array('responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id'));
    }

    /**
     * Helper function to insert record data, save mapping data and remap data where necessary.
     *
     * @param array $data Array of data record row arrays. The first row contains the field names.
     * @param string $datatable The name of the data table to insert records into.
     * @param string $mapvar The name of the object variable to store oldid / newid mappings (optional).
     * @param array|null $replvars Array of key/value pairs where key is the mapvar and value is the record field
     *                         to replace with mapped values.
     * @return null
     */
    private function add_data(array $data, $datatable, $mapvar = '', array $replvars = null) {
        global $DB;

        if ($replvars === null) {
            $replvars = array();
        }
        $fields = array_shift($data);
        foreach ($data as $row) {
            $record = new stdClass();
            foreach ($row as $key => $fieldvalue) {
                if ($fields[$key] == 'id') {
                    if (!empty($mapvar)) {
                        $oldid = $fieldvalue;
                    }
                } else if (($replvar = array_search($fields[$key], $replvars)) !== false) {
                    $record->{$fields[$key]} = $this->{$replvar}[$fieldvalue];
                } else {
                    $record->{$fields[$key]} = $fieldvalue;
                }
            }
            $newid = $DB->insert_record($datatable, $record);
            if (!empty($mapvar)) {
                $this->{$mapvar}[$oldid] = $newid;
            }
        }
    }
    /**
     * Get a questupload by name.
     *
     * @param string $name questupload name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_questupload_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('questupload', ['name' => $name], '*', MUST_EXIST);
    }

    /**
     * Get a questupload cmid from the quiz name.
     *
     * @param string $name questupload name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_questupload_name(string $name): stdClass {
        $questupload = $this->get_questupload_by_name($name);
        return get_coursemodule_from_instance('questupload', $questupload->id, $questupload->course);
    }
}
