<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_questupload\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates the identity used when a participant submits a response.
 *
 * @package mod_questupload
 * @copyright 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response_submission_validator {
    /**
     * Ensures a caller may submit only their own response.
     *
     * @param int $userid Submitted response user id.
     * @param int $currentuserid Authenticated user id.
     * @return void
     * @throws \invalid_parameter_exception When identities differ.
     */
    public static function assert_user_identity(int $userid, int $currentuserid): void {
        if ($userid !== $currentuserid) {
            throw new \invalid_parameter_exception('The user must match the authenticated user.');
        }
    }
}
