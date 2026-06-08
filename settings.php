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
 * Setting page for questionaire module
 *
 * @package    mod_questupload
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/mod/questupload/locallib.php');

if ($ADMIN->fulltree) {
    $options = array(0 => get_string('no'), 1 => get_string('yes'));
    $str = get_string('configusergraphlong', 'questupload');
    $settings->add(new admin_setting_configselect('questupload/usergraph',
                                    get_string('configusergraph', 'questupload'),
                                    $str, 0, $options));
    $settings->add(new admin_setting_configtext('questupload/maxsections',
                                    get_string('configmaxsections', 'questupload'),
                                    '', 10, PARAM_INT));
    $choices = array(
        'response' => get_string('response', 'questupload'),
        'submitted' => get_string('submitted', 'questupload'),
        'institution' => get_string('institution'),
        'department' => get_string('department'),
        'course' => get_string('course'),
        'group' => get_string('group'),
        'id' => get_string('id', 'questupload'),
        'useridnumber' => get_string('useridnumber', 'questupload'),
        'fullname' => get_string('fullname'),
        'username' => get_string('username'),
        'useridentityfields' => get_string('showuseridentity', 'admin')
    );

    $settings->add(new admin_setting_configmultiselect('questupload/downloadoptions',
            get_string('textdownloadoptions', 'questupload'), '', array_keys($choices), $choices));

    $settings->add(new admin_setting_configcheckbox('questupload/allowemailreporting',
        get_string('configemailreporting', 'questupload'), get_string('configemailreportinglong', 'questupload'), 0));

    $settings->add(new admin_setting_configduration('questupload_questiondeletion/duration',
        get_string('deletequestionsolderthan', 'questupload'),
        get_string('deletesettingdescription', 'questupload'),  7 * 86400));

    // Delete old responses after. The default value is 24 months.
    $options = [
            '0' => new lang_string('disabled', 'questupload'),
            '1' => new lang_string('enabled', 'questupload'),
    ];
    $name = get_string('autodeletereponse', 'questupload');
    $desc = get_string('autodeletereponse_desc', 'questupload');
    $setting = new admin_setting_configselect('questupload/autodeleteresponse', $name, $desc, 0, $options);
    $settings->add($setting);

    $options = questupload_create_remove_options();
    $settings->add(new admin_setting_configselect('questupload/removeoldresponses',
            get_string('removeoldresponsesafter', 'questupload'),
            get_string('configremoveoldresponses', 'questupload'), 0, $options));
}
