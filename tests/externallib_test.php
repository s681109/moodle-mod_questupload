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

/**
 * Tests for the Questupload external API.
 *
 * @package mod_questupload
 * @copyright 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questupload;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for Questupload web service methods.
 *
 * @group mod_questupload
 */
class externallib_test extends \advanced_testcase {
    /**
     * A caller must not submit a response using another user's identity.
     *
     * @return void
     */
    public function test_submit_response_rejects_different_user(): void {
        $this->expectException(\invalid_parameter_exception::class);
        \mod_questupload\local\response_submission_validator::assert_user_identity(42, 7);
    }
}
