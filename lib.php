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
 * This plugin does not add any entries into the user_enrolments table,
 * the access control is granted on the fly via the tricks in require_login().
 *
 * @package    enrol_guest
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

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
     * @param object $course
     * @param array instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        $params = array('enrol' => 'sync', 'courseid' => $course->id);
        if ($instance = $DB->get_record('enrol', $params)) {
            $instance->status = 0;
            $DB->update_record('enrol', $instance);
            return $instance->id;
        }

        $fields = (array)$fields;
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

        if (!$DB->record_exists('enrol', array('courseid' => $data->courseid, 'enrol' => $this->get_name()))) {
            $this->add_instance($course, (array)$data);
        }

        // No need to set mapping, we do not restore users or roles here.
        $step->set_mapping('enrol', $oldid, 0);
    }

    /**
     * Instances will auto delete when the last synced encolled user has gone away.
     *
     * @param object $instance
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
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
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
        global $CFG;

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_guest'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_guest');
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
        $errors = array();

        $validstatus = array_keys($this->get_status_options());
        $tovalidate = array(
            'status' => $validstatus
        );
        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }

    /**
     * Tells if we have some users enrolled in this instance.
     * @param object $instance
     * @return the number of enrolled users in this instance.
     */
    protected function has_enrolled_users($instance) {
        global $DB;

        $params = array('enrolid' => $instance->id);
        return $DB->count_records('user_enrolments', $params);
    }

    /**
     * Enrols a user ensuring a sync enrol plugin instance is present in the course.
     * @param object $course the course record
     * @param int $userid
     * @param int $roleid
     * @param int $timestart
     * @param int $timeeend
     * @param bool $status
     * @param bool $shift If set, will remove previous manual enrolment from the user.
     */
    static public function static_enrol_user($course, $userid, $roleid, $timestart = 0, $timeend = 0, $status = null, $shift = false) {
        global $DB;

        $plugin = enrol_get_plugin('sync');
        $instanceid = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', array('id' => $instanceid));
        $plugin->enrol_user($instance, $userid, $roleid, $timestart, $timeend, $status, null);

        if ($shift) {
            $manualplugin = enrol_get_plugin('manual');
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
            if (!$instance) {
                $manualplugin->unenrol_user($instance, $userid);
            }
        }
    }

    /**
     * Enrols a user from the sync enrol plugin. Deletes the instance if last synced user is unenrolled from course.
     * @param object $course the course record
     * @param int $userid
     * @param int $roleid
     * @param int $timestart
     * @param int $timeeend
     * @param bool $status
     */
    static public function static_unenrol_user($course, $userid) {
        global $DB;

        $plugin = enrol_get_plugin('sync');
        $instanceid = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', array('id' => $instanceid));
        $plugin->unenrol_user($instance, $userid);

        if (!$plugin->has_enrolled_users($instance)) {
            $plugin->delete_instance($instance);
        }
    }
}
