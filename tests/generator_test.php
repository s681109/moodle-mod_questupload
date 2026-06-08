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
 * PHPUnit questupload generator tests
 *
 * @package    mod_questupload
 * @copyright  2015 Mike Churchward (mike@churchward.ca)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questupload;

/**
 * Unit tests for questupload_generator_testcase.
 * @group mod_questupload
 */
class generator_test extends \advanced_testcase {
    /**
     * Test generator create_instance function.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     *
     * @covers \mod_questupload\generator\
     */
    public function test_create_instance() {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($DB->record_exists('questupload', array('course' => $course->id)));

        /** @var mod_questupload_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questupload');
        $this->assertInstanceOf('mod_questupload_generator', $generator);
        $this->assertEquals('questupload', $generator->get_modulename());

        $questupload = $generator->create_instance(array('course' => $course->id));
        $this->assertEquals(1, $DB->count_records('questupload'));

        $cm = get_coursemodule_from_instance('questupload', $questupload->id);
        $this->assertEquals($questupload->id, $cm->instance);
        $this->assertEquals('questupload', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = \context_module::instance($cm->id);
        $this->assertEquals($questupload->cmid, $context->instanceid);

        $survey = $DB->get_record('questupload_survey', array('id' => $questupload->sid));
        $this->assertEquals($survey->id, $questupload->sid);
        $this->assertEquals($questupload->name, $survey->name);
        $this->assertEquals($questupload->name, $survey->title);

        // Should test creating a public questupload, template questupload and creating one from a template.

        // Should test event creation if open dates and close dates are specified?
    }

    /**
     * Test generator create_content function.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     *
     * @covers \mod_questupload\generator\
     */
    public function test_create_content() {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questupload');
        $questupload = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questupload', $questupload->id);
        $questupload = new \questupload($course, $cm, $questupload->id, null, false);

        $newcontent = array(
            'title' => 'New title',
            'email' => 'test@email.com',
            'subtitle' => 'New subtitle',
            'info' => 'New info',
            'thanks_page' => 'http://thankurl.com',
            'thank_head' => 'New thank header',
            'thank_body' => 'New thank body',
        );
        $sid = $generator->create_content($questupload, $newcontent);
        $this->assertEquals($sid, $questupload->sid);
        $survey = $DB->get_record('questupload_survey', array('id' => $sid));
        foreach ($newcontent as $name => $value) {
            $this->assertEquals($survey->{$name}, $value);
        }
    }
}
