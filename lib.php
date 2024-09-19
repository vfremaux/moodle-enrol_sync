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
 * Sync access plugin.
 *
 * @package    enrol_sync
 * @copyright  2013 Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @author  2013 Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * This plugin does not add any entries into the user_enrolments table,
 * the access control is granted on the fly via the tricks in require_login().
 */

/**
 * Class enrol_sync_plugin
 *
 * @copyright  2017 Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_sync_plugin extends enrol_plugin {

    /**
     * All instances are created automatically during the sync process. they canot be deleted or moved
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        return false;
    }

    /**
     * Add new instance of enrol plugin.
     * this plugin is a per course singleton
     *
     * @param StdClass $course
     * @param ?array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, ?array $fields = null) {
        global $DB;

        $params = ['enrol' => 'sync', 'courseid' => $course->id];
        if ($instance = $DB->get_record('enrol', $params)) {
            $instance->status = 0;
            $DB->update_record('enrol', $instance);
            return $instance->id;
        }

        $fields = (array) $fields;
        return parent::add_instance($course, $fields);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;

        if (!$DB->record_exists('enrol', ['courseid' => $data->courseid, 'enrol' => $this->get_name()])) {
            $this->add_instance($course, (array)$data);
        }

        // No need to set mapping, we do not restore users or roles here.
        $step->set_mapping('enrol', $oldid, 0);
    }

    /**
     * Instances will auto delete when the last synced encolled user has gone away.
     *
     * @param StdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        return false;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $config = get_config('enrol_sync');
        return $config->canhideshowinstances;
    }

    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return stdClass instance info.
     * @since Moodle 3.1
     */
    public function get_enrol_info(stdClass $instance) {

        $instanceinfo = new stdClass();
        $instanceinfo->id = $instance->id;
        $instanceinfo->courseid = $instance->courseid;
        $instanceinfo->type = $this->get_name();
        $instanceinfo->name = $this->get_instance_name($instance);
        $instanceinfo->status = $instance->status == ENROL_INSTANCE_ENABLED;

        return $instanceinfo;
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = [
            ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ];
        return $options;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_sync'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_sync');
        $mform->setDefault('status', $this->get_config('status'));
        $mform->setAdvanced('status', $this->get_config('status_adv'));
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
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name" => "error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];

        $validstatus = array_keys($this->get_status_options());
        $tovalidate = [
            'status' => $validstatus,
        ];
        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }

    /**
     * Tells if we have some users enrolled in this instance.
     * @param StdClass $instance
     * @return the number of enrolled users in this instance.
     */
    protected function has_enrolled_users($instance) {
        global $DB;

        $params = ['enrolid' => $instance->id];
        return $DB->count_records('user_enrolments', $params);
    }

    /**
     * Enrols a user ensuring a sync enrol plugin instance is present in the course.
     * @param StdClass $course the course record
     * @param int $userid
     * @param int $roleid
     * @param int $timestart
     * @param int $timeend
     * @param bool $status
     * @param bool $shift If set, will remove previous manual enrolment from the user.
     */
    public static function static_enrol_user($course, $userid, $roleid, $timestart = 0, $timeend = 0, $status = null,
                $shift = false) {
        global $DB;

        $plugin = enrol_get_plugin('sync');
        $instanceid = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        $plugin->enrol_user($instance, $userid, $roleid, $timestart, $timeend, $status, null);

        if ($shift) {
            $manualplugin = enrol_get_plugin('manual');
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
            if ($instance) {
                $manualplugin->unenrol_user($instance, $userid);
            }
        }
    }

    /**
     * Enrols a user from the sync enrol plugin. Deletes the instance if last synced user is unenrolled from course.
     * @param StdClass $course the course record
     * @param int $userid
     */
    public static function static_unenrol_user($course, $userid) {
        global $DB;

        $plugin = enrol_get_plugin('sync');
        $instanceid = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        $plugin->unenrol_user($instance, $userid);

        if (!$plugin->has_enrolled_users($instance)) {
            $plugin->delete_instance($instance);
        }
    }

    /**
     * Enrols a user ensuring a sync enrol plugin instance is present in the course. Parameters have been reordrered
     * to respect enrollib.php core library.
     * @param StdClass $course the course record
     * @param int $userid
     * @param bool $status
     * @param int $timestart
     * @param int $timeend
     */
    public static function static_update_user_enrol($course, $userid, $status = null, $timestart = 0, $timeend = 0) {
        global $DB;

        $plugin = enrol_get_plugin('sync');
        $instanceid = $plugin->add_instance($course); // Implicit singleton.
        $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        $plugin->update_user_enrol($instance, $userid, $status, $timestart, $timeend);
    }

    /**
     * @see lib/accesslib.php§get_user_roles_in_course
     * This function is used to print roles column in user profile page.
     * It is using the CFG->profileroles to limit the list to only interesting roles.
     * (The permission tab has full details of user role assignments.)
     * Restrict to enrol_sync component the roles query and returns the array of roles
     * rather than a string list of role names
     *
     * @param int $userid
     * @param int $courseid
     * @return array array of role records
     */
    public static function get_user_roles_in_course($userid, $courseid) {
        global $CFG, $DB;
        if ($courseid == SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($courseid);
        }
        // If the current user can assign roles, then they can see all roles on the profile and participants page,
        // provided the roles are assigned to at least 1 user in the context. If not, only the policy-defined roles.
        if (has_capability('moodle/role:assign', $context)) {
            $rolesinscope = array_keys(get_all_roles($context));
        } else {
            $rolesinscope = empty($CFG->profileroles) ? [] : array_map('trim', explode(',', $CFG->profileroles));
        }
        if (empty($rolesinscope)) {
            return '';
        }

        list($rallowed, $params) = $DB->get_in_or_equal($rolesinscope, SQL_PARAMS_NAMED, 'a');
        list($contextlist, $cparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'p');
        $params = array_merge($params, $cparams);

        if ($coursecontext = $context->get_course_context(false)) {
            $params['coursecontext'] = $coursecontext->id;
        } else {
            $params['coursecontext'] = 0;
        }

        $sql = "SELECT DISTINCT r.id, r.name, r.shortname, r.sortorder, rn.name AS coursealias
                  FROM {role_assignments} ra, {role} r
             LEFT JOIN {role_names} rn ON (rn.contextid = :coursecontext AND rn.roleid = r.id)
                 WHERE r.id = ra.roleid
                       AND ra.contextid $contextlist
                       AND r.id $rallowed
                       AND ra.userid = :userid
                       AND component = 'enrol_sync'
              ORDER BY r.sortorder ASC";
        $params['userid'] = $userid;

        $roles = $DB->get_records_sql($sql, $params);
        return $roles;
    }
}
