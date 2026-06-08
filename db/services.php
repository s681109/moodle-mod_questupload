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
 * Questionnaire Upload external functions and service definitions.
 *
 * @package    mod_questupload
 * @category   external
 * @copyright  2018 Igor Sazonov <sovletig@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

$services = [
    'mod_questupload_ws' => [
        'functions' => ['mod_questupload_submit_questupload_response'],
        'requiredcapability' => '',
        'enabled' => 1
    ]
];

$functions = [
    'mod_questupload_submit_questupload_response' => [
        'classname' => 'mod_questupload\external',
        'methodname' => 'submit_questupload_response',
        'classpath' => 'mod/questupload/externallib.php',
        'description' => 'Questionnaire Upload submit',
        'type' => 'write',
        'capabilities' => 'mod/questupload:submit',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ]
];
