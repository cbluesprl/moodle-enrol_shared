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
 * Shared enrolment plugin settings and presets.
 * Some settings has been commented because they are not relevant in this use case.
 *
 * @package    enrol_shared
 * @copyright  2021 CBlue SPRL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- general settings (In administration area) -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_shared_settings', '', get_string('pluginname_desc', 'enrol_shared')));

    $options = array(
        //ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        //ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
        //ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_shared/expiredaction', get_string('expiredaction', 'enrol_shared'), get_string('expiredaction_help', 'enrol_shared'), ENROL_EXT_REMOVED_KEEP, $options));

    $options = array();
    for ($i=0; $i<24; $i++) {
        $options[$i] = $i;
    }
    //$settings->add(new admin_setting_configselect('enrol_shared/expirynotifyhour', get_string('expirynotifyhour', 'core_enrol'), '', 6, $options));


    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_shared_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $settings->add(new admin_setting_configcheckbox('enrol_shared/defaultenrol',
        get_string('defaultenrol', 'enrol'), get_string('defaultenrol_desc', 'enrol'), 1));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_shared/status',
        get_string('status', 'enrol_shared'), get_string('status_desc', 'enrol_shared'), ENROL_INSTANCE_ENABLED, $options));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_shared/roleid',
            get_string('defaultrole', 'role'), '', $student->id, $options));
    }

    $options = array(2 => get_string('coursestart'), 3 => get_string('today'), 4 => get_string('now', 'enrol_shared'));
    $settings->add(
        new admin_setting_configselect('enrol_shared/enrolstart', get_string('defaultstart', 'enrol_shared'), '', 4, $options)
    );

    $settings->add(new admin_setting_configduration('enrol_shared/enrolperiod',
        get_string('defaultperiod', 'enrol_shared'), get_string('defaultperiod_desc', 'enrol_shared'), 0));

    //$options = array(0 => get_string('no'), 1 => get_string('expirynotifyenroller', 'core_enrol'), 2 => get_string('expirynotifyall', 'core_enrol'));
    //$settings->add(new admin_setting_configselect('enrol_shared/expirynotify',
    //    get_string('expirynotify', 'core_enrol'), get_string('expirynotify_help', 'core_enrol'), 0, $options));

    //$settings->add(new admin_setting_configduration('enrol_shared/expirythreshold',
    //    get_string('expirythreshold', 'core_enrol'), get_string('expirythreshold_help', 'core_enrol'), 86400, 86400));

}
