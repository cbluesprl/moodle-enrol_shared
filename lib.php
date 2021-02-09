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
 * Shared enrolment plugin main library file.
 *
 * @package    enrol_shared
 * @copyright  2021 CBlue SPRL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_shared_plugin extends enrol_plugin {

    /**
     * @param stdClass $instance
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Return true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function can_add_instance($courseid) {
        global $DB;

        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/shared:config', $context)) {
            return false;
        }

        if ($DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => 'shared'])) {
            // Multiple instances not supported.
            return false;
        }

        return true;
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * @param stdClass $course
     * @return int id of new instance, null if can not be created
     * @throws coding_exception
     * @throws dml_exception
     */
    public function add_default_instance($course) {
        $fields = [
            'status' => $this->get_config('status'),
            'roleid' => $this->get_config('roleid', 0),
            'enrolperiod' => $this->get_config('enrolperiod', 0),
        ];
        return $this->add_instance($course, $fields);
    }

    /**
     * Add new instance of enrol plugin.
     * @param stdClass $course
     * @param array instance fields
     * @return int|null id of new instance, null if can not be created
     * @throws coding_exception
     * @throws dml_exception
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        if ($DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'shared'])) {
            // only one instance allowed, sorry
            return null;
        }

        return parent::add_instance($course, $fields);
    }

    /**
     * Update instance of enrol plugin.
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     * @throws coding_exception
     * @throws dml_exception
     */
    public function update_instance($instance, $data) {
        global $DB;

        // Delete all other instances, leaving only one.
        if ($instances = $DB->get_records('enrol', ['courseid' => $instance->courseid, 'enrol' => 'shared'], 'id ASC')) {
            foreach ($instances as $anotherinstance) {
                if ($anotherinstance->id != $instance->id) {
                    $this->delete_instance($anotherinstance);
                }
            }
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/shared:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/shared:config', $context);
    }

    /**
     * Sync all meta course links.
     *
     * @param progress_trace $trace
     * @param int $courseid one course, empty mean all
     * @return int means ok, 1 means error, 2 means plugin disabled
     * @throws coding_exception
     * @throws dml_exception
     */
    public function sync(progress_trace $trace, $courseid = null) {
        global $DB;

        if (!enrol_is_enabled('shared')) {
            $trace->finished();
            return 2;
        }

        // Unfortunately this may take a long time, execution can be interrupted safely here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $trace->output('Verifying shared enrolment expiration...');

        $params = ['now' => time(), 'useractive' => ENROL_USER_ACTIVE, 'courselevel' => CONTEXT_COURSE];
        $coursesql = "";
        if ($courseid) {
            $coursesql = "AND e.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        // Deal with expired accounts.
        $instances = [];
        $sql =
            "SELECT ue.*, e.courseid, c.id AS contextid
            FROM {user_enrolments} ue
            JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'shared')
            JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
            WHERE ue.timeend > 0 AND ue.timeend < :now
                $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ue) {
            if (empty($instances[$ue->enrolid])) {
                $instances[$ue->enrolid] = $DB->get_record('enrol', ['id' => $ue->enrolid]);
            }
            $instance = $instances[$ue->enrolid];
            // Always remove all manually assigned roles here, this may break enrol_self roles but we do not want hardcoded hacks here.
            role_unassign_all(['userid' => $ue->userid, 'contextid' => $ue->contextid, 'component' => '', 'itemid' => 0], true);
            $this->unenrol_user($instance, $ue->userid);
            $trace->output("unenrolling expired user $ue->userid from course $instance->courseid", 1);
        }
        $rs->close();
        unset($instances);

        $trace->output('...manual enrolment updates finished.');
        $trace->finished();

        return 0;
    }


    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     * @throws coding_exception
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_shared'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_shared');
        $mform->setDefault('status', $this->get_config('status'));

        $roles = $this->get_roleid_options($instance, $context);
        $mform->addElement('select', 'roleid', get_string('defaultrole', 'role'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $options = ['optional' => true, 'defaultunit' => 86400];
        $mform->addElement('duration', 'enrolperiod', get_string('defaultperiod', 'enrol_shared'), $options);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'defaultperiod', 'enrol_shared');

        if (enrol_accessing_via_instance($instance)) {
            $warntext = get_string('instanceeditselfwarningtext', 'core_enrol');
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), $warntext);
        }

        return true;
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     * @throws coding_exception
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];

        $validstatus = array_keys($this->get_status_options());
        $validroles = array_keys($this->get_roleid_options($instance, $context));

        $tovalidate = [
            'status' => $validstatus,
            'roleid' => $validroles,
            'enrolperiod' => PARAM_INT,
        ];

        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }

    /**
     * Unenrol user from course,
     * the last unenrolment removes all remaining roles.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $CFG, $USER, $DB;
        require_once("$CFG->dirroot/group/lib.php");

        $name = $this->get_name();
        $courseid = $instance->courseid;

        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid, MUST_EXIST);

        if (!$ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            // weird, user not enrolled
            return;
        }

        // Remove all users groups linked to this enrolment instance.
        if ($gms = $DB->get_records('groups_members', ['userid' => $userid, 'component' => 'enrol_' . $name, 'itemid' => $instance->id])) {
            foreach ($gms as $gm) {
                groups_remove_member($gm->groupid, $gm->userid);
            }
        }

        role_unassign_all(['userid' => $userid, 'contextid' => $context->id, 'component' => 'enrol_' . $name, 'itemid' => $instance->id]);
        $DB->delete_records('user_enrolments', ['id' => $ue->id]);

        // add extra info
        $ue->courseid = $courseid;
        $ue->enrol = $name;

        $sql =
            "SELECT 'x'
            FROM {user_enrolments} ue
            JOIN {enrol} e ON (e.id = ue.enrolid)
            WHERE ue.userid = :userid AND e.courseid = :courseid";
        if ($DB->record_exists_sql($sql, ['userid' => $userid, 'courseid' => $courseid])) {
            $ue->lastenrol = false;
        } else {
            // the big cleanup IS necessary!
            require_once("$CFG->libdir/gradelib.php");

            // remove all remaining roles
            role_unassign_all(['userid' => $userid, 'contextid' => $context->id], true, false);

            //clean up ALL invisible user data from course if this is the last enrolment - groups, grades, etc.
            groups_delete_group_members($courseid, $userid);

            grade_user_unenrol($courseid, $userid);

            $DB->delete_records('user_lastaccess', ['userid' => $userid, 'courseid' => $courseid]);

            $ue->lastenrol = true; // means user not enrolled any more
        }
        // Trigger event.
        $event = \core\event\user_enrolment_deleted::create(
            [
                'courseid' => $courseid,
                'context' => $context,
                'relateduserid' => $ue->userid,
                'objectid' => $ue->id,
                'other' => [
                    'userenrolment' => (array) $ue,
                    'enrol' => $name
                ]
            ]
        );
        $event->trigger();

        // User enrolments have changed, so mark user as dirty.
        mark_user_dirty($userid);

        // Check if courrse contacts cache needs to be cleared.
        core_course_category::user_enrolment_changed($courseid, $ue->userid, ENROL_USER_SUSPENDED);

        // reset current user enrolment caching
        if ($userid == $USER->id) {
            if (isset($USER->enrol['enrolled'][$courseid])) {
                unset($USER->enrol['enrolled'][$courseid]);
            }
            if (isset($USER->enrol['tempguest'][$courseid])) {
                unset($USER->enrol['tempguest'][$courseid]);
                remove_temp_course_roles($context);
            }
        }
    }

    /**
     * Enrol user into course via enrol instance.
     *
     * @param stdClass $instance
     * @param int $userid
     * @param int $roleid optional role id
     * @param int $timestart 0 means unknown
     * @param int $timeend 0 means forever
     * @param int $status default to ENROL_USER_ACTIVE for new enrolments, no change by default in updates
     * @param bool $recovergrades restore grade history
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
        global $DB, $USER, $CFG;

        if ($instance->courseid == SITEID) {
            throw new coding_exception('invalid attempt to enrol into frontpage course!');
        }

        $name = $this->get_name();
        $courseid = $instance->courseid;
        $roleid = $instance->roleid;
        $timestart = $instance->enrolstartdate;
        $timeend = empty($instance->enrolperiod) ? 0 : time() + $instance->enrolperiod;

        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);
        if (!isset($recovergrades)) {
            $recovergrades = $CFG->recovergradesdefault;
        }

        $inserted = false;
        if ($ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            //only update if timestart or timeend or status are different.
            if ($ue->timestart != $timestart or $ue->timeend != $timeend or (!is_null($status) and $ue->status != $status)) {
                $this->update_user_enrol($instance, $userid, $status, $timestart, $timeend);
            }
        } else {
            $ue = new stdClass();
            $ue->enrolid = $instance->id;
            $ue->status = is_null($status) ? ENROL_USER_ACTIVE : $status;
            $ue->userid = $userid;
            $ue->timestart = $timestart;
            $ue->timeend = $timeend;
            $ue->modifierid = $USER->id;
            $ue->timecreated = time();
            $ue->timemodified = $ue->timecreated;
            $ue->id = $DB->insert_record('user_enrolments', $ue);

            $inserted = true;
        }

        if ($inserted) {
            // Trigger event.
            $event = \core\event\user_enrolment_created::create(
                [
                    'objectid' => $ue->id,
                    'courseid' => $courseid,
                    'context' => $context,
                    'relateduserid' => $ue->userid,
                    'other' => ['enrol' => $name]
                ]
            );
            $event->trigger();
            // Check if course contacts cache needs to be cleared.
            core_course_category::user_enrolment_changed(
                $courseid, $ue->userid,
                $ue->status, $ue->timestart, $ue->timeend
            );
        }

        if ($roleid) {
            // this must be done after the enrolment event so that the role_assigned event is triggered afterwards
            if ($this->roles_protected()) {
                role_assign($roleid, $userid, $context->id, 'enrol_' . $name, $instance->id);
            } else {
                role_assign($roleid, $userid, $context->id);
            }
        }

        // Recover old grades if present.
        if ($recovergrades) {
            require_once("$CFG->libdir/gradelib.php");
            grade_recover_history_grades($userid, $courseid);
        }

        // reset current user enrolment caching
        if ($userid == $USER->id) {
            if (isset($USER->enrol['enrolled'][$courseid])) {
                unset($USER->enrol['enrolled'][$courseid]);
            }
            if (isset($USER->enrol['tempguest'][$courseid])) {
                unset($USER->enrol['tempguest'][$courseid]);
                remove_temp_course_roles($context);
            }
        }
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     * @throws coding_exception
     */
    protected function get_status_options() {
        return [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no')
        ];
    }

    /**
     * Return an array of valid options for the roleid.
     *
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            return get_default_enrol_roles($context, $instance->roleid);
        } else {
            return get_default_enrol_roles($context, $this->get_config('roleid'));
        }
    }
}
